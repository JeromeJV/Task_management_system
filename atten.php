<?php
require_once 'config/connection.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/autoLog.php';
require_once __DIR__ . '/config/account_access.php';
if (!isset($_SESSION['id'], $_SESSION['email'], $_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    exit('HR access is required to view attendance records.');
}
requireActiveAccount($conn);
require_once 'config/attendance_helpers.php';
require_once __DIR__ . '/config/leave_helpers.php';

// Set Timezone sa Philippine Time
date_default_timezone_set('Asia/Manila');

$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');
ensureLeaveSchema($conn);
finalizeDailyAttendance($conn, $currentDate, $currentTime);

$period = (string) ($_GET['period'] ?? 'daily');
if (!in_array($period, ['daily', 'weekly', 'monthly', 'all'], true)) {
    $period = 'daily';
}
$showAllDates = $period === 'all';
$isDailyRoster = $period === 'daily';
$selectedDate = $showAllDates ? '' : trim((string) ($_GET['date'] ?? $currentDate));
if (!$showAllDates) {
    $parsedSelectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        !$parsedSelectedDate
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $parsedSelectedDate->format('Y-m-d') !== $selectedDate
    ) {
        $selectedDate = $currentDate;
        $parsedSelectedDate = new DateTimeImmutable($currentDate);
    }
} else {
    $parsedSelectedDate = null;
}
$rangeStart = $selectedDate;
$rangeEnd = $selectedDate;
if ($period === 'weekly' && $parsedSelectedDate) {
    $rangeStart = $parsedSelectedDate->modify('monday this week')->format('Y-m-d');
    $rangeEnd = $parsedSelectedDate->modify('sunday this week')->format('Y-m-d');
} elseif ($period === 'monthly' && $parsedSelectedDate) {
    $rangeStart = $parsedSelectedDate->modify('first day of this month')->format('Y-m-d');
    $rangeEnd = $parsedSelectedDate->modify('last day of this month')->format('Y-m-d');
}
$searchName   = isset($_GET['search']) ? trim($_GET['search']) : '';
$clearSearchUrl = 'atten.php?' . http_build_query(array_filter(
    ['period' => $period, 'date' => $selectedDate],
    static fn ($value): bool => $value !== ''
));

$records = [];
$count = 0;

// Counter variables for summary cards
$totalPresent = 0;
$totalLate    = 0;
$totalAbsent  = 0;
$totalHalfDay = 0;
$totalUndertime = 0;
$totalLeave = 0;
$totalUnrecorded = 0;

// =========================================================
// FETCH ATTENDANCE WITH USER & EMPLOYEE DETAILS + SEARCH
// =========================================================
$query = $isDailyRoster
    ? "
        SELECT a.attendance_id, e.employee_id, COALESCE(a.attendance_date, ?) AS attendance_date,
               a.time_in, a.time_out, a.time_in_2, a.time_out_2, a.status, COALESCE(a.is_late, 0) AS is_late,
               COALESCE(u.name, e.username, 'N/A') AS display_name,
               COALESCE(u.email, e.email, '') AS user_email,
               COALESCE(u.role, e.position, 'N/A') AS user_role
        FROM employee e
        LEFT JOIN attendance a ON a.attendance_id = (
            SELECT MAX(today_attendance.attendance_id)
            FROM attendance today_attendance
            WHERE today_attendance.employee_id = e.employee_id
              AND today_attendance.attendance_date = ?
        )
        LEFT JOIN users u ON u.employee_id = e.employee_id
        WHERE (e.hire_date IS NULL OR e.hire_date <= ?)
    "
    : "
        SELECT a.attendance_id, a.employee_id, a.attendance_date,
               a.time_in, a.time_out, a.time_in_2, a.time_out_2, a.status, a.is_late,
               COALESCE(u.name, e.username, a.username, 'N/A') AS display_name,
               COALESCE(u.email, e.email, '') AS user_email,
               COALESCE(u.role, e.position, 'N/A') AS user_role
        FROM attendance a
        LEFT JOIN employee e ON a.employee_id = e.employee_id
        LEFT JOIN users u ON a.user_id = u.id OR e.username = u.name
        WHERE 1=1
    ";

$params = $isDailyRoster ? [$selectedDate, $selectedDate, $selectedDate] : [];
$types = $isDailyRoster ? 'sss' : '';
if (in_array($period, ['weekly', 'monthly'], true)) {
    $query .= ' AND a.attendance_date BETWEEN ? AND ?';
    $params[] = $rangeStart;
    $params[] = $rangeEnd;
    $types .= 'ss';
}

// Name/Email Filter
if (!empty($searchName)) {
    $query .= " AND (u.name LIKE ? OR e.username LIKE ? OR a.username LIKE ? OR u.email LIKE ? OR e.email LIKE ?)";
    $searchTerm = "%" . $searchName . "%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssss";
}

$query .= $isDailyRoster
    ? " ORDER BY e.username ASC, e.employee_id ASC"
    : " ORDER BY a.attendance_date DESC, a.attendance_id DESC";

$stmt = $conn->prepare($query);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $rawRecords = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Process status for each record
        foreach ($rawRecords as $rec) {
            $st = strtolower(trim($rec['status'] ?? ''));
            $isNotRecorded = $isDailyRoster && empty($rec['attendance_id']);

            // Kung walang status sa DB pero may Time In
            if (!$isNotRecorded && empty($st) && !empty($rec['time_in']) && $rec['time_in'] !== '00:00:00') {
                $st = (strtotime($rec['time_in']) > strtotime('08:00:00')) ? 'late' : 'present';
            }

            $isLate = !empty($rec['is_late']) || $st === 'late';
            $rec['display_status'] = $isNotRecorded ? 'No record yet' : (!empty($st) ? ucfirst($st) : 'N/A');
            if ($st === 'half day' && $isLate) {
                $rec['display_status'] = 'Half Day (Late)';
            }

            // Increment summary counts
            if ($isNotRecorded) {
                $totalUnrecorded++;
            } elseif ($st === 'present') {
                $totalPresent++;
            } elseif ($st === 'absent') {
                $totalAbsent++;
            } elseif ($st === 'half day') {
                $totalHalfDay++;
            } elseif ($st === 'undertime') {
                $totalUndertime++;
            } elseif (in_array($st, ['paid leave', 'unpaid leave'], true)) {
                $totalLeave++;
            }
            if ($isLate) {
                $totalLate++;
            }

            $records[] = $rec;
        }

        $count = count($records);
    }
}

// Helper function to format time (e.g., 08:30 AM)
function formatTime($timeStr) {
    return (!empty($timeStr) && $timeStr !== '00:00:00') ? date('h:i A', strtotime($timeStr)) : '-';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <style>
        .attendance-main {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .attendance-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 20px;
            flex-wrap: wrap;
            padding: 22px 24px;
            border: 1px solid #dce8e0;
            border-left: 4px solid #1f6e4a;
            border-radius: 13px;
            background: linear-gradient(110deg, #ffffff 0%, #f4f8f5 100%);
            box-shadow: 0 5px 18px rgba(31, 65, 46, .045);
        }
        .attendance-page-header h1 { margin: 0; color: #17251d; font-size: 29px; line-height: 1.2; }
        .attendance-page-header p { margin: 7px 0 0; color: #68756c; font-size: 13px; }
        .attendance-page-kicker { margin: 0 0 6px !important; color: #1f6e4a !important; font-size: 10px !important; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .filter-card {
            display: grid;
            width: 100%;
            grid-template-columns: minmax(220px, 1.1fr) minmax(145px, .7fr) minmax(180px, .9fr) auto;
            align-items: end;
            margin-bottom: 22px;
            padding: 16px;
            border: 1px solid #e1e8e3;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(31, 41, 55, .04);
            gap: 14px;
        }
        .filter-card .attendance-filter-field {
            display: grid;
            gap: 6px;
            min-width: 0;
        }
        .filter-card .attendance-filter-field > span {
            color: #445248;
            font-weight: 700;
            font-size: 12px;
        }
        .filter-card .calendar-picker {
            width: 100%;
        }
        .attendance-main select {
            width: 100%;
            min-height: 44px;
            padding: 9px 34px 9px 12px;
            border: 1px solid #d5e0d9;
            border-radius: 9px;
            background-color: #fff;
            color: #27372e;
            font: inherit;
        }
        .attendance-filter-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .attendance-main input[type="date"],
        .attendance-main input[type="text"] {
            width: 100%;
            min-height: 44px;
            padding: 9px 12px;
            border: 1px solid #d5e0d9;
            border-radius: 9px;
            background: #fff;
            color: #27372e;
        }
        .attendance-main .btn { min-height: 42px; padding: 10px 15px; border-radius: 8px; font-weight: 650; white-space: nowrap; }
        .attendance-main .btn-primary { background: #1f6e4a; }
        .attendance-main .btn-primary:hover { background: #185b3c; }
        .attendance-main .btn-outline { border-color: #c7d8cc; background: #fff; color: #1f6e4a; }
        .attendance-main .btn-outline.is-active { border-color: #1f6e4a; background: #eaf4ed; }
        .attendance-main > h2 { margin: 0 0 13px; color: #17251d; font-size: 19px; }
        .attendance-range-note { margin: -10px 0 14px; color: #68756c; font-size: 12px; }
        @media (max-width: 720px) {
            .filter-card {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .attendance-filter-actions {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 480px) {
            .filter-card {
                grid-template-columns: minmax(0, 1fr);
            }
            .attendance-filter-actions {
                grid-column: auto;
                flex-wrap: wrap;
            }
        }
        .btn {
            padding: 7px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-primary { background-color: #0d6efd; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-outline { background-color: transparent; border: 1px solid #6c757d; color: #333; }
        .btn:hover { opacity: 0.9; }
        
        /* Summary Cards */
        .summary-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(125px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            min-width: 0;
            padding: 14px 16px;
            border: 1px solid #e5eae6;
            border-radius: 11px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-top: 3px solid #7b8b80;
        }
        .summary-card.present { border-top-color: #198754; }
        .summary-card.late { border-top-color: #d99a20; }
        .summary-card.absent { border-top-color: #dc3545; }
        .summary-card.half-day { border-top-color: #0b9bb5; }
        .summary-card.undertime { border-top-color: #6c757d; }
        .summary-card h4 { margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; color: #6c757d; }
        .summary-card .number { font-size: 22px; font-weight: bold; margin: 0; }
        @media (max-width: 1150px) { .summary-container { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 720px) {
            .attendance-page-header { align-items: flex-start; }
            .attendance-page-header .btn { margin-left: auto; }
            .summary-container { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 480px) {
            .attendance-page-header { padding: 18px; border-radius: 11px; }
            .attendance-page-header h1 { font-size: 24px; }
            .attendance-page-header { display: grid; }
            .attendance-page-header .btn { margin-left: 0; }
            .filter-card { grid-template-columns: minmax(0, 1fr); }
            .attendance-filter-actions { grid-column: auto; flex-wrap: wrap; }
            .attendance-filter-actions .btn { flex: 1 1 auto; }
        }

        table {
            width: 100%;
            min-width: 1040px;
            border-collapse: collapse;
            background: #fff;
            box-shadow: none;
            border-radius: 0;
            overflow: visible;
        }
        th, td {
            padding: 13px 14px;
            border: 0;
            border-bottom: 1px solid #e8eeea;
            text-align: center;
            font-size: 13px;
        }
        th { position: sticky; top: 0; background-color: #f3f7f4; color: #526257; font-size: 10px; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
        tbody tr { transition: background-color .15s ease; }
        tbody tr:hover { background: #f6faf7; }
        .attendance-main .table-scroll { overflow: auto; border: 1px solid #e1e8e3; border-radius: 12px; background: #fff; box-shadow: 0 5px 18px rgba(31, 41, 55, .045); }
        .attendance-employee-cell { display: grid; min-width: 0; gap: 3px; text-align: left; }
        .attendance-employee-cell strong { line-height: 1.35; overflow-wrap: anywhere; }
        .attendance-main .sub-text { display: block; margin: 0; color: #728077; font-size: 11px; line-height: 1.35; overflow-wrap: anywhere; }
        
        .badge {
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        .status-present { background-color: #d1e7dd; color: #0f5132; }
        .status-absent { background-color: #f8d7da; color: #842029; }
        .status-late { background-color: #fff3cd; color: #664d03; }
        .status-half-day { background-color: #cff4fc; color: #055160; }
        .status-undertime { background-color: #e2e3e5; color: #41464b; }
        .status-leave { background-color: #fff3cd; color: #664d03; }
        .status-default { background-color: #e2e3e5; color: #41464b; }
        .status-no-record { background-color: #edf3ef; color: #53665a; }
    </style>
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
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
                <a class="hr-nav-link is-active" href="atten.php" aria-current="page">
                    <span class="hr-nav-icon" aria-hidden="true">◷</span>
                    <span class="hr-nav-label">Attendance</span>
                </a>
                <a class="hr-nav-link" href="leave_management.php">
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

        <main class="hr-main attendance-main">
    <header class="attendance-page-header">
        <div>
            <p class="attendance-page-kicker">Human Resources</p>
            <h1>Attendance records</h1>
            <p>Review daily punches, attendance statuses, and employee records.</p>
        </div>
        <a href="attendance_corrections.php" class="btn btn-outline">Correct attendance</a>
    </header>
    <form method="GET" action="" class="filter-card">
            <label class="attendance-filter-field attendance-search-field" for="search">
                <span>Search Employee:</span>
                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Name or Email..."
                    value="<?= htmlspecialchars($searchName); ?>"
                >
            </label>

            <label class="attendance-filter-field" for="period">
                <span>Period:</span>
                <select id="period" name="period">
                    <option value="daily" <?= $period === 'daily' ? 'selected' : ''; ?>>Daily roster</option>
                    <option value="weekly" <?= $period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?= $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : ''; ?>>All dates</option>
                </select>
            </label>

            <label class="attendance-filter-field attendance-date-field" for="date">
                <span><?= in_array($period, ['weekly', 'monthly'], true) ? 'Anchor date:' : 'Date:'; ?></span>
                <input
                    type="date"
                    class="calendar-control"
                    id="date"
                    name="date"
                    value="<?= htmlspecialchars($selectedDate); ?>"
                    <?= $showAllDates ? 'disabled' : ''; ?>
                >
            </label>

            <div class="attendance-filter-actions">
                <button type="submit" class="btn btn-primary">Search / Filter</button>
                <?php $hasAttendanceFilter = !empty($searchName); ?>
                <?php if ($hasAttendanceFilter): ?>
                    <a href="<?= htmlspecialchars($clearSearchUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Clear search</a>
                <?php endif; ?>
            </div>
    </form>
    <?php if (in_array($period, ['weekly', 'monthly'], true)): ?>
        <p class="attendance-range-note">Showing <?= date('M j, Y', strtotime($rangeStart)); ?> to <?= date('M j, Y', strtotime($rangeEnd)); ?>.</p>
    <?php endif; ?>

    <!-- Dynamic Title Header -->
    <h2 class="attendance-results-heading">
        <?php 
            $titleParts = [];
            if (!empty($searchName)) {
                $titleParts[] = 'Results for "' . htmlspecialchars($searchName) . '"';
            }
            if ($period === 'daily' && !empty($selectedDate)) {
                $titleParts[] = 'Date: ' . date('F j, Y', strtotime($selectedDate));
            } elseif ($period === 'weekly') {
                $titleParts[] = 'Week: ' . date('M j', strtotime($rangeStart)) . ' – ' . date('M j, Y', strtotime($rangeEnd));
            } elseif ($period === 'monthly') {
                $titleParts[] = 'Month: ' . date('F Y', strtotime($rangeStart));
            } elseif ($period === 'all') {
                $titleParts[] = 'All dates';
            }

            echo !empty($titleParts)
                ? 'Attendance - ' . implode(' | ', $titleParts)
                : 'Daily Attendance Roster';
        ?>
    </h2>

    <!-- Summary Cards -->
    <div class="summary-container">
        <div class="summary-card">
            <h4><?= $showAllDates ? 'Total Records' : 'Employees Listed'; ?></h4>
            <p class="number"><?= $count; ?></p>
        </div>
        <?php if ($isDailyRoster): ?>
        <div class="summary-card undertime">
            <h4>No Record Yet</h4>
            <p class="number" style="color: #53665a;"><?= $totalUnrecorded; ?></p>
        </div>
        <?php endif; ?>
        <div class="summary-card present">
            <h4>Present</h4>
            <p class="number" style="color: #198754;"><?= $totalPresent; ?></p>
        </div>
        <div class="summary-card late">
            <h4>Late</h4>
            <p class="number" style="color: #b58100;"><?= $totalLate; ?></p>
        </div>
        <div class="summary-card absent">
            <h4>Absent</h4>
            <p class="number" style="color: #dc3545;"><?= $totalAbsent; ?></p>
        </div>
        <div class="summary-card half-day">
            <h4>Half Day</h4>
            <p class="number" style="color: #055160;"><?= $totalHalfDay; ?></p>
        </div>
        <div class="summary-card undertime">
            <h4>Incomplete Session</h4>
            <p class="number" style="color: #41464b;"><?= $totalUndertime; ?></p>
        </div>
        <div class="summary-card">
            <h4>On Leave</h4>
            <p class="number" style="color: #8a5a00;"><?= $totalLeave; ?></p>
        </div>
    </div>

    <?php if ($count > 0): ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Attendance ID</th>
                    <th><?= $showAllDates ? 'System Account (Name / Email)' : 'Employee (Name / Email)'; ?></th>
                    <th>Role</th>
                    <th>Employee ID</th>
                    <th>Attendance Date</th>
                    <th>AM Time In</th>
                    <th>AM Time Out</th>
                    <th>PM Time In</th>
                    <th>PM Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= !empty($row['attendance_id']) ? htmlspecialchars($row['attendance_id']) : '—'; ?></td>
                        <td>
                            <div class="attendance-employee-cell">
                            <strong><?= htmlspecialchars($row['display_name']); ?></strong>
                            <?php if (!empty($row['user_email'])): ?>
                                <span class="sub-text"><?= htmlspecialchars($row['user_email']); ?></span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($row['user_role'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['employee_id'] ?? 'N/A'); ?></td>
                        <td><?= date('M d, Y', strtotime($row['attendance_date'])); ?></td>
                        <td><?= formatTime($row['time_in']); ?></td>
                        <td><?= formatTime($row['time_out']); ?></td>
                        <td><?= formatTime($row['time_in_2']); ?></td>
                        <td><?= formatTime($row['time_out_2']); ?></td>
                        <td>
                            <?php 
                                $statusKey = strtolower($row['display_status']);
                                $statusClass = match ($statusKey) {
                                    'present' => 'status-present',
                                    'absent'  => 'status-absent',
                                    'late'    => 'status-late',
                                    'half day', 'half day (late)' => 'status-half-day',
                                    'undertime' => 'status-undertime',
                                    'no record yet' => 'status-no-record',
                                    'paid leave', 'unpaid leave' => 'status-leave',
                                    default   => 'status-default',
                                };
                            ?>
                            <span class="badge <?= $statusClass; ?>">
                                <?= htmlspecialchars($row['display_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            No attendance records found.
        </p>
    <?php endif; ?>
        </main>
    </div>
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
</body> 
</html>