<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';
require_once __DIR__ . '/config/leave_helpers.php';
require_once __DIR__ . '/config/attendance_helpers.php';

if (!isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'HR') {
    header('Location: index.php');
    exit();
}

requireActiveAccount($conn, true);
ensureLeaveSchema($conn);
date_default_timezone_set('Asia/Manila');
if (empty($_SESSION['leave_csrf'])) {
    $_SESSION['leave_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['leave_csrf'], $token)) {
        $errorMessage = 'Your session expired. Refresh and try again.';
    } elseif (isset($_POST['record_leave'])) {
        $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $leaveType = (string) ($_POST['leave_type'] ?? '');
        $fraction = 1.0;
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        $validStart = $start && $start->format('Y-m-d') === $startDate;
        $validEnd = $end && $end->format('Y-m-d') === $endDate;

        if (!$employeeId || $employeeId < 1) {
            $errorMessage = 'Choose a valid employee.';
        } elseif (!$validStart || !$validEnd || $end < $start) {
            $errorMessage = 'Choose a valid leave date range.';
        } elseif (!in_array($leaveType, ['SIL', 'LWOP'], true)) {
            $errorMessage = 'Choose paid SIL or unpaid leave (LWOP).';
        } elseif (countLeaveWorkdays($startDate, $endDate) === 0) {
            $errorMessage = 'The selected dates contain no weekdays.';
        } elseif ($reason === '' || mb_strlen($reason) > 500) {
            $errorMessage = 'Enter a reason of no more than 500 characters.';
        } else {
            $employeeStatement = $conn->prepare('SELECT username, email, hire_date FROM employee WHERE employee_id = ?');
            if (!$employeeStatement) {
                throw new RuntimeException('Unable to prepare employee lookup: ' . $conn->error);
            }
            $employeeStatement->bind_param('i', $employeeId);
            if (!$employeeStatement->execute()) {
                throw new RuntimeException('Unable to load employee: ' . $employeeStatement->error);
            }
            $employee = $employeeStatement->get_result()->fetch_assoc();
            $employeeStatement->close();

            if (!$employee) {
                $errorMessage = 'The selected employee was not found.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the leave record transaction: ' . $conn->error);
                }
                $employeeLock = $conn->prepare('SELECT employee_id FROM employee WHERE employee_id = ? FOR UPDATE');
                if (!$employeeLock) {
                    throw new RuntimeException('Unable to lock employee leave balance: ' . $conn->error);
                }
                $employeeLock->bind_param('i', $employeeId);
                if (!$employeeLock->execute() || $employeeLock->get_result()->num_rows !== 1) {
                    $employeeLock->close();
                    throw new RuntimeException('Unable to lock the selected employee record.');
                }
                $employeeLock->close();

                $overlapStatement = $conn->prepare(
                    "SELECT leave_id FROM employee_leave_requests
                     WHERE employee_id = ? AND status = 'active'
                       AND start_date <= ? AND end_date >= ? LIMIT 1"
                );
                if (!$overlapStatement) {
                    throw new RuntimeException('Unable to prepare leave overlap check: ' . $conn->error);
                }
                $overlapStatement->bind_param('iss', $employeeId, $endDate, $startDate);
                if (!$overlapStatement->execute()) {
                    throw new RuntimeException('Unable to check existing leave: ' . $overlapStatement->error);
                }
                $hasOverlap = $overlapStatement->get_result()->num_rows > 0;
                $overlapStatement->close();

                if ($hasOverlap) {
                    $errorMessage = 'This employee already has an active leave record overlapping those dates.';
                } elseif ($leaveType === 'SIL') {
                    $hireDate = (string) ($employee['hire_date'] ?? '');
                    $firstBounds = $hireDate !== '' ? getServiceYearBounds($hireDate, $startDate) : null;
                    $lastBounds = $hireDate !== '' ? getServiceYearBounds($hireDate, $endDate) : null;
                    if (!$firstBounds || !$lastBounds) {
                        $errorMessage = 'Paid SIL requires a recorded hire date and at least one year of service. Update the employee profile first, or record LWOP.';
                    } elseif ($firstBounds !== $lastBounds) {
                        $errorMessage = 'A paid SIL request cannot cross an annual service-year boundary. Split it into separate requests.';
                    } else {
                        $used = getUsedSILDays($conn, (int) $employeeId, $firstBounds[0], $firstBounds[1]);
                        $requested = countLeaveWorkdays($startDate, $endDate) * $fraction;
                        if ($used + $requested > 5.0) {
                            $errorMessage = 'This request exceeds the five-day SIL allowance for the employee service year. Remaining balance: '
                                . number_format(max(0, 5.0 - $used), 1) . ' day(s). Record the excess as LWOP.';
                        }
                    }
                }

                if ($errorMessage !== '') {
                    $conn->rollback();
                }
                if ($errorMessage === '') {
                    $recorderId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
                    $attendanceStatus = $leaveType === 'SIL' ? 'Paid Leave' : 'Unpaid Leave';
                    $linkedUserId = null;
                    $linkedUserStatement = $conn->prepare(
                        "SELECT id FROM users
                         WHERE employee_id = ?
                            OR (employee_id IS NULL AND (
                                LOWER(TRIM(email)) = LOWER(TRIM(?))
                                OR LOWER(TRIM(name)) = LOWER(TRIM(?))
                            ))
                         ORDER BY (employee_id = ?) DESC, id ASC LIMIT 1"
                    );
                    if (!$linkedUserStatement) {
                        throw new RuntimeException('Unable to prepare linked account lookup: ' . $conn->error);
                    }
                    $employeeEmail = (string) ($employee['email'] ?? '');
                    $employeeUsername = (string) ($employee['username'] ?? '');
                    $linkedUserStatement->bind_param(
                        'issi',
                        $employeeId,
                        $employeeEmail,
                        $employeeUsername,
                        $employeeId
                    );
                    if (!$linkedUserStatement->execute()) {
                        throw new RuntimeException('Unable to look up linked account: ' . $linkedUserStatement->error);
                    }
                    $linkedUser = $linkedUserStatement->get_result()->fetch_assoc();
                    $linkedUserStatement->close();
                    if ($linkedUser) {
                        $linkedUserId = (int) $linkedUser['id'];
                    }

                    try {
                        $insertLeave = $conn->prepare(
                            "INSERT INTO employee_leave_requests
                                (employee_id, start_date, end_date, leave_type, day_fraction, reason, recorded_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );
                        if (!$insertLeave) {
                            throw new RuntimeException('Unable to prepare leave record: ' . $conn->error);
                        }
                        $insertLeave->bind_param(
                            'isssdsi',
                            $employeeId,
                            $startDate,
                            $endDate,
                            $leaveType,
                            $fraction,
                            $reason,
                            $recorderId
                        );
                        if (!$insertLeave->execute()) {
                            throw new RuntimeException('Unable to save leave record: ' . $insertLeave->error);
                        }
                        $insertLeave->close();

                        $day = $start;
                        while ($day <= $end) {
                            if ((int) $day->format('N') <= 5) {
                                $attendanceDate = $day->format('Y-m-d');
                                $existingAttendance = $conn->prepare(
                                    'SELECT attendance_id, time_in, time_out, time_in_2, time_out_2
                                     FROM attendance
                                     WHERE employee_id = ? AND attendance_date = ?
                                     ORDER BY attendance_id DESC LIMIT 1 FOR UPDATE'
                                );
                                if (!$existingAttendance) {
                                    throw new RuntimeException('Unable to prepare attendance leave update: ' . $conn->error);
                                }
                                $existingAttendance->bind_param('is', $employeeId, $attendanceDate);
                                if (!$existingAttendance->execute()) {
                                    throw new RuntimeException('Unable to read attendance for leave date: ' . $existingAttendance->error);
                                }
                                $attendance = $existingAttendance->get_result()->fetch_assoc();
                                $existingAttendance->close();
                                if ($fraction === 1.0 && $attendance) {
                                    foreach (['time_in', 'time_out', 'time_in_2', 'time_out_2'] as $punch) {
                                        if (!empty($attendance[$punch]) && $attendance[$punch] !== '00:00:00') {
                                            throw new RuntimeException('Cannot record a full-day leave on ' . $attendanceDate . ' because attendance punches already exist.');
                                        }
                                    }
                                }
                                if ($attendance) {
                                    $attendanceId = (int) $attendance['attendance_id'];
                                    $updateAttendance = $conn->prepare('UPDATE attendance SET status = ? WHERE attendance_id = ?');
                                    if (!$updateAttendance) {
                                        throw new RuntimeException('Unable to prepare attendance status update: ' . $conn->error);
                                    }
                                    $updateAttendance->bind_param('si', $attendanceStatus, $attendanceId);
                                    if (!$updateAttendance->execute()) {
                                        throw new RuntimeException('Unable to update leave attendance: ' . $updateAttendance->error);
                                    }
                                    $updateAttendance->close();
                                } else {
                                    $insertAttendance = $conn->prepare(
                                        $linkedUserId === null
                                            ? 'INSERT INTO attendance (employee_id, username, attendance_date, status) VALUES (?, ?, ?, ?)'
                                            : 'INSERT INTO attendance (user_id, employee_id, username, attendance_date, status) VALUES (?, ?, ?, ?, ?)'
                                    );
                                    if (!$insertAttendance) {
                                        throw new RuntimeException('Unable to prepare leave attendance record: ' . $conn->error);
                                    }
                                    if ($linkedUserId === null) {
                                        $insertAttendance->bind_param(
                                            'isss',
                                            $employeeId,
                                            $employee['username'],
                                            $attendanceDate,
                                            $attendanceStatus
                                        );
                                    } else {
                                        $insertAttendance->bind_param(
                                            'iisss',
                                            $linkedUserId,
                                            $employeeId,
                                            $employee['username'],
                                            $attendanceDate,
                                            $attendanceStatus
                                        );
                                    }
                                    if (!$insertAttendance->execute()) {
                                        throw new RuntimeException('Unable to create leave attendance record: ' . $insertAttendance->error);
                                    }
                                    $insertAttendance->close();
                                }
                            }
                            $day = $day->modify('+1 day');
                        }

                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to commit leave and attendance updates: ' . $conn->error);
                        }
                        $_SESSION['leave_flash'] = 'Leave recorded and attendance updated.';
                        header('Location: leave_management.php');
                        exit();
                    } catch (Throwable $error) {
                        $conn->rollback();
                        $errorMessage = $error->getMessage();
                    }
                }
            }
        }
    } elseif (isset($_POST['cancel_leave'])) {
        $leaveId = filter_var($_POST['leave_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$leaveId || $leaveId < 1) {
            $errorMessage = 'Invalid leave record.';
        } else {
            $conn->begin_transaction();
            $leaveStatement = $conn->prepare(
                "SELECT employee_id, start_date, end_date
                 FROM employee_leave_requests
                 WHERE leave_id = ? AND status = 'active' FOR UPDATE"
            );
            if (!$leaveStatement) {
                throw new RuntimeException('Unable to prepare leave cancellation: ' . $conn->error);
            }
            $leaveStatement->bind_param('i', $leaveId);
            if (!$leaveStatement->execute()) {
                throw new RuntimeException('Unable to load leave to cancel: ' . $leaveStatement->error);
            }
            $leave = $leaveStatement->get_result()->fetch_assoc();
            $leaveStatement->close();
            if (!$leave) {
                $conn->rollback();
                $errorMessage = 'Leave was already cancelled or could not be found.';
            } else {
                $cancelledBy = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
                $cancelStatement = $conn->prepare(
                    "UPDATE employee_leave_requests
                     SET status = 'cancelled', cancelled_by = ?, cancelled_at = CURRENT_TIMESTAMP
                     WHERE leave_id = ?"
                );
                if (!$cancelStatement) {
                    throw new RuntimeException('Unable to prepare leave cancellation update: ' . $conn->error);
                }
                $cancelStatement->bind_param('ii', $cancelledBy, $leaveId);
                if (!$cancelStatement->execute()) {
                    throw new RuntimeException('Unable to cancel leave: ' . $cancelStatement->error);
                }
                $cancelStatement->close();

                $day = new DateTimeImmutable((string) $leave['start_date']);
                $end = new DateTimeImmutable((string) $leave['end_date']);
                while ($day <= $end) {
                    if ((int) $day->format('N') <= 5) {
                        $date = $day->format('Y-m-d');
                        $employeeForRestore = (int) $leave['employee_id'];
                        $restoreRows = $conn->prepare(
                            "SELECT a.attendance_id, a.user_id, a.time_in, a.time_out, a.time_in_2, a.time_out_2,
                                    active_leave.leave_type
                             FROM attendance a
                             LEFT JOIN employee_leave_requests active_leave
                               ON active_leave.employee_id = a.employee_id
                              AND active_leave.status = 'active'
                              AND active_leave.start_date <= a.attendance_date
                              AND active_leave.end_date >= a.attendance_date
                             WHERE a.employee_id = ? AND a.attendance_date = ?
                               AND a.status IN ('Paid Leave', 'Unpaid Leave', 'Half Day')"
                        );
                        if (!$restoreRows) {
                            throw new RuntimeException('Unable to prepare attendance restoration: ' . $conn->error);
                        }
                        $restoreRows->bind_param('is', $employeeForRestore, $date);
                        if (!$restoreRows->execute()) {
                            throw new RuntimeException('Unable to load attendance after leave cancellation: ' . $restoreRows->error);
                        }
                        $restoreResult = $restoreRows->get_result();
                        while ($attendance = $restoreResult->fetch_assoc()) {
                            $isLate = attendancePunchExists($attendance, 'time_in')
                                && $attendance['time_in'] > '08:00:00';
                            $restoredStatus = $attendance['leave_type'] !== null
                                ? ($attendance['leave_type'] === 'SIL' ? 'Paid Leave' : 'Unpaid Leave')
                                : calculateAttendanceStatusFromPunches(
                                    $attendance,
                                    isLogisticsEmployee(
                                        $conn,
                                        $employeeForRestore,
                                        isset($attendance['user_id']) ? (int) $attendance['user_id'] : null
                                    ),
                                    $isLate
                                );
                            $attendanceId = (int) $attendance['attendance_id'];
                            $lateFlag = (int) $isLate;
                            $restore = $conn->prepare('UPDATE attendance SET status = ?, is_late = ? WHERE attendance_id = ?');
                            if (!$restore) {
                                throw new RuntimeException('Unable to prepare attendance status restoration: ' . $conn->error);
                            }
                            $restore->bind_param('sii', $restoredStatus, $lateFlag, $attendanceId);
                            if (!$restore->execute()) {
                                throw new RuntimeException('Unable to restore attendance after leave cancellation: ' . $restore->error);
                            }
                            $restore->close();
                        }
                        $restoreRows->close();
                    }
                    $day = $day->modify('+1 day');
                }
                if (!$conn->commit()) {
                    throw new RuntimeException('Unable to commit leave cancellation: ' . $conn->error);
                }
                $_SESSION['leave_flash'] = 'Leave cancelled. Attendance has been restored from recorded punches.';
                header('Location: leave_management.php');
                exit();
            }
        }
    }
}

$employeeResult = $conn->query('SELECT employee_id, username, position, hire_date FROM employee ORDER BY username');
if (!$employeeResult) {
    throw new RuntimeException('Unable to load employees: ' . $conn->error);
}
$employees = $employeeResult->fetch_all(MYSQLI_ASSOC);
$leaveResult = $conn->query(
    "SELECT l.leave_id, l.employee_id, l.start_date, l.end_date, l.leave_type,
            l.reason, l.recorded_at, e.username, u.name AS recorder
     FROM employee_leave_requests l
     INNER JOIN employee e ON e.employee_id = l.employee_id
     LEFT JOIN users u ON u.id = l.recorded_by
     WHERE l.status = 'active'
     ORDER BY l.start_date DESC, l.leave_id DESC"
);
if (!$leaveResult) {
    throw new RuntimeException('Unable to load leave records: ' . $conn->error);
}
$leaveRows = $leaveResult->fetch_all(MYSQLI_ASSOC);
$paidLeaveCount = count(array_filter($leaveRows, static fn(array $leave): bool => $leave['leave_type'] === 'SIL'));
$unpaidLeaveCount = count($leaveRows) - $paidLeaveCount;
$employeesOnLeave = count(array_unique(array_column($leaveRows, 'employee_id')));
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Management - TaskTrack</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <style>
        .leave-main .hr-greeting{padding:22px 24px;border:1px solid #dce8e0;border-left:4px solid #1f6e4a;border-radius:13px;background:linear-gradient(110deg,#fff 0%,#f4f8f5 100%);box-shadow:0 5px 18px rgba(31,65,46,.045)}
        .leave-main .hr-greeting h1{font-size:30px}
        .leave-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 18px}
        .leave-stat{display:flex;min-height:82px;align-items:center;justify-content:space-between;gap:12px;padding:14px 17px;border:1px solid #e1e8e3;border-radius:11px;background:#fff;box-shadow:0 3px 11px rgba(31,41,55,.035)}
        .leave-stat span{color:#66756b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.045em}
        .leave-stat strong{color:#17251d;font-size:25px;line-height:1}
        .leave-stat.is-paid{border-left:3px solid #258454}.leave-stat.is-paid strong{color:#1f6e4a}
        .leave-stat.is-unpaid{border-left:3px solid #d89a26}.leave-stat.is-unpaid strong{color:#98650a}
        .leave-grid{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:20px;align-items:start}
        .leave-card{min-width:0;background:#fff;border:1px solid #e1e8e3;border-radius:14px;padding:20px;box-shadow:0 6px 20px rgba(15,23,42,.045)}
        .leave-form-card{border-top:3px solid #1f6e4a}
        .leave-records-card{border-top:3px solid #647d6d}
        .leave-card-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
        .leave-card h2{margin:0;color:#17251d;font-size:17px}.leave-count{flex:0 0 auto;padding:5px 9px;border:1px solid #dce8e0;border-radius:999px;background:#f4f8f5;color:#53665a;font-size:11px;font-weight:700}
        .leave-form{display:grid;gap:14px}
        .leave-form label{display:grid;gap:7px;color:#314239;font-size:12px;font-weight:700}
        .leave-form>label> :is(input,textarea,.select-control,.calendar-picker){width:100%}
        .leave-form textarea{min-height:100px;resize:vertical}
        .leave-submit,.leave-cancel{border:0;border-radius:9px;padding:11px 14px;font-weight:700;cursor:pointer;transition:background-color .18s ease,transform .18s ease}
        .leave-submit{background:#1f6e4a;color:#fff}.leave-submit:hover{background:#185b3c;transform:translateY(-1px)}
        .leave-cancel{background:#fff0ef;color:#b42318}.leave-cancel:hover{background:#fee3e1}
        .leave-table-wrap{overflow:auto}
        .leave-table{width:100%;border-collapse:collapse;min-width:690px}.leave-table th,.leave-table td{text-align:left;padding:12px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top}
        .leave-table th{color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.06em;background:#f5f8f6}.leave-type{font-weight:700}.leave-reason{max-width:250px;white-space:normal}
        .leave-type-chip{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:750;white-space:nowrap}
        .leave-type-chip.is-paid{background:#e7f5ec;color:#176b3b}.leave-type-chip.is-unpaid{background:#fff4dc;color:#875b0b}
        .leave-help{margin:0;color:#6b7c70;font-size:11px;line-height:1.55}.leave-empty{padding:22px;color:#64748b;text-align:center}
        .leave-table small{color:#718078}
        @media(max-width:1050px){.leave-grid{grid-template-columns:minmax(260px,340px) minmax(0,1fr);gap:14px}.leave-card{padding:16px}}
        @media(max-width:820px){.leave-grid{grid-template-columns:1fr}.leave-table{min-width:690px}}
        @media(max-width:600px){
            .hr-main.leave-main{padding:24px 15px 40px}
            .leave-main .hr-greeting{padding:18px;border-radius:11px}
            .leave-main .hr-greeting h1{font-size:25px}
            .leave-overview{grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
            .leave-stat{min-height:75px;align-items:flex-start;flex-direction:column;gap:7px;padding:11px 9px}
            .leave-stat span{font-size:9px;line-height:1.3}
            .leave-stat strong{font-size:21px}
            .leave-grid{gap:14px}
            .leave-card{padding:16px;border-radius:12px}
            .leave-table-wrap{overflow:visible}
            .leave-table{min-width:0;border-collapse:separate;border-spacing:0 10px}
            .leave-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
            .leave-table tbody,.leave-table tr,.leave-table td{display:block;width:100%}
            .leave-table tr{padding:8px 12px;border:1px solid #e1e8e3;border-radius:10px;background:#fff;box-shadow:0 2px 8px rgba(31,41,55,.035)}
            .leave-table td{display:grid;grid-template-columns:minmax(88px,.35fr) minmax(0,1fr);gap:10px;padding:9px 0;border-bottom:1px solid #edf1ee;font-size:12px;overflow-wrap:anywhere}
            .leave-table td::before{content:attr(data-label);color:#718078;font-size:10px;font-weight:750;text-transform:uppercase}
            .leave-table td:last-child{display:flex;justify-content:flex-end;border-bottom:0}
            .leave-table td:last-child::before{content:""}
            .leave-reason{max-width:none}
        }
    </style>
    <link rel="stylesheet" href="css/action-confirmation.css?v=<?= filemtime(__DIR__ . '/css/action-confirmation.css'); ?>">
</head>
<body>
    <div class="hr-shell">
        <aside class="hr-sidebar" id="hrSidebar" aria-label="HR navigation">
            <?php include __DIR__ . '/config/hr_sidebar_brand.php'; ?>
            <nav class="hr-nav">
                <a class="hr-nav-link" href="HR.php">
                    <span class="hr-nav-icon" aria-hidden="true">▦</span>
                    <span class="hr-nav-label">Employees</span>
                </a>
                <a class="hr-nav-link" href="atten.php">
                    <span class="hr-nav-icon" aria-hidden="true">◷</span>
                    <span class="hr-nav-label">Attendance</span>
                </a>
                <a class="hr-nav-link is-active" href="leave_management.php" aria-current="page">
                    <span class="hr-nav-icon" aria-hidden="true">✚</span>
                    <span class="hr-nav-label">Leave Management</span>
                </a>
                <a class="hr-nav-link" href="application_form.php">
                    <span class="hr-nav-icon" aria-hidden="true">▤</span>
                    <span class="hr-nav-label">Applicants</span>
                </a>
                <a class="hr-nav-link" href="interview_sched.php">
                    <span class="hr-nav-icon" aria-hidden="true">▣</span>
                    <span class="hr-nav-label">Interview Schedule</span>
                </a>
                <a class="hr-nav-link" href="office_employee.php">
                    <span class="hr-nav-icon" aria-hidden="true">☑</span>
                    <span class="hr-nav-label">My Tasks</span>
                </a>
            </nav>
            <a class="hr-nav-link hr-logout" href="logout.php">
                <span class="hr-nav-icon" aria-hidden="true">↪</span>
                <span class="hr-nav-label">Log out</span>
            </a>
        </aside>
        <?php include __DIR__ . '/config/hr_mobile_navigation.php'; ?>
        <?php include __DIR__ . '/config/notification_bell.php'; ?>
        <main class="hr-main leave-main">
            <header class="hr-greeting">
                <div>
                    <p class="hr-greeting-kicker">HUMAN RESOURCES</p>
                    <h1>Leave Management</h1>
                    <p class="hr-greeting-quote">Record employee leave, including face-to-face requests and employees without system accounts.</p>
                </div>
                <span class="hr-greeting-date"><?= $escape(date('l, F j, Y')); ?></span>
            </header>
            <?php if ($errorMessage !== ''): ?><div class="hr-alert is-error" role="alert"><?= $escape($errorMessage); ?></div><?php endif; ?>
            <?php if (!empty($_SESSION['leave_flash'])): ?><div class="hr-alert is-success" role="status"><?= $escape($_SESSION['leave_flash']); ?></div><?php unset($_SESSION['leave_flash']); endif; ?>
            <section class="leave-overview" aria-label="Active leave summary">
                <article class="leave-stat"><span>Active leave records</span><strong><?= count($leaveRows); ?></strong></article>
                <article class="leave-stat is-paid"><span>Paid SIL</span><strong><?= $paidLeaveCount; ?></strong></article>
                <article class="leave-stat is-unpaid"><span>Unpaid LWOP</span><strong><?= $unpaidLeaveCount; ?></strong></article>
            </section>
            <div class="leave-grid">
                <section class="leave-card leave-form-card">
                    <div class="leave-card-heading"><h2>Record employee leave</h2></div>
                    <form class="leave-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['leave_csrf']); ?>">
                        <label>Employee
                            <select name="employee_id" required>
                                <option value="">Select employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= (int) $employee['employee_id']; ?>">
                                        <?= $escape($employee['username'] ?: 'Unnamed employee'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Leave pay type
                            <select name="leave_type" required>
                                <option value="LWOP">Unpaid leave / LWOP</option>
                                <option value="SIL">Paid SIL (up to 5 days per service year)</option>
                            </select>
                        </label>
                        <label>Start date<input class="calendar-control" type="date" name="start_date" required></label>
                        <label>End date<input class="calendar-control" type="date" name="end_date" required></label>
                        <label>Reason<textarea name="reason" maxlength="500" placeholder="Sick leave, personal leave, etc." required></textarea></label>
                        <p class="leave-help">Every weekday in the selected date range is recorded as a full leave day; weekends are excluded. Paid SIL requires a hire date on the employee profile and one year of service. This tracker does not include a holiday calendar.</p>
                        <button class="leave-submit" type="submit" name="record_leave" value="1">Record Leave</button>
                    </form>
                    </section>
                    <section class="leave-card leave-records-card">
                        <div class="leave-card-heading">
                            <h2>Active leave records</h2>
                            <span class="leave-count"><?= count($leaveRows); ?> active</span>
                        </div>
                        <?php if ($leaveRows === []): ?>
                            <div class="leave-empty">No active leave records.</div>
                        <?php else: ?>
                            <div class="leave-table-wrap">
                                <table class="leave-table">
                                    <thead><tr><th>Employee</th><th>Dates</th><th>Type</th><th>Reason</th><th>Recorded by</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($leaveRows as $leave): ?>
                                        <tr>
                                            <td data-label="Employee"><?= $escape($leave['username']); ?></td>
                                            <td data-label="Dates"><?= $escape($leave['start_date']); ?><?= $leave['end_date'] !== $leave['start_date'] ? ' – ' . $escape($leave['end_date']) : ''; ?><br>Full weekday(s)</td>
                                            <td class="leave-type" data-label="Type"><span class="leave-type-chip <?= $leave['leave_type'] === 'SIL' ? 'is-paid' : 'is-unpaid'; ?>"><?= $leave['leave_type'] === 'SIL' ? 'Paid SIL' : 'Unpaid / LWOP'; ?></span></td>
                                            <td class="leave-reason" data-label="Reason"><?= $escape($leave['reason']); ?></td>
                                            <td data-label="Recorded by"><?= $escape($leave['recorder'] ?: 'HR'); ?><br><small><?= $escape($leave['recorded_at']); ?></small></td>
                                            <td>
                                                <form method="post" data-confirm="Cancel this leave record and restore attendance from the employee punches?">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['leave_csrf']); ?>">
                                                    <input type="hidden" name="leave_id" value="<?= (int) $leave['leave_id']; ?>">
                                                    <button class="leave-cancel" type="submit" name="cancel_leave" value="1">Cancel</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
        <script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
        <script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
        <script src="js/action-confirmation.js?v=<?= filemtime(__DIR__ . '/js/action-confirmation.js'); ?>"></script>
</body>
</html>
