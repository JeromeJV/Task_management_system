<?php
header('Content-Type: application/json');

// 1. I-set ang Timezone sa Asia/Manila para 6:00 PM ang ma-record
date_default_timezone_set('Asia/Manila');

// Siguraduhing tama ang path ng connection file
include __DIR__ . '/connection.php';
require_once __DIR__ . '/attendance_helpers.php';
require_once __DIR__ . '/attendance_calculations.php';
require_once __DIR__ . '/system_audit.php';
require_once __DIR__ . '/leave_helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$raw_employee_id = $_POST['employee_id'] ?? '';
$action_type = $_POST['action_type'] ?? '';
$postedCsrfToken = (string) ($_POST['csrf_token'] ?? '');
$nowTime = date('H:i:s');
$timeInClosed = $nowTime >= '17:01:00' || $nowTime < '05:01:00';
$attendanceActions = ['time_in', 'time_out', 'time_in_2', 'time_out_2', 'submit'];

// Sanitize & validate action types
$allowed_actions = ['time_in', 'time_out', 'time_in_2', 'time_out_2', 'lookup', 'submit'];
if (empty($raw_employee_id) || !in_array($action_type, $allowed_actions, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
    exit;
}
if (
    empty($_SESSION['attendance_csrf'])
    || !hash_equals((string) $_SESSION['attendance_csrf'], $postedCsrfToken)
) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Attendance session expired. Refresh the page and try again.']);
    exit;
}

if ($timeInClosed && in_array($action_type, $attendanceActions, true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Attendance is closed from 5:01 PM to 5:00 AM. It opens again at 5:01 AM.',
        'next_action' => null
    ]);
    exit;
}

$isSequentialSubmit = $action_type === 'submit';

if ($action_type === 'lookup') {
    if (!ctype_digit((string) $raw_employee_id) || (int) $raw_employee_id < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid Employee ID.']);
        exit;
    }

    $lookupQuery = $conn->prepare('SELECT employee_id, username FROM employee WHERE employee_id = ?');
    if (!$lookupQuery) {
        throw new RuntimeException('Unable to prepare attendance employee lookup: ' . $conn->error);
    }
    $lookupEmployeeId = (int) $raw_employee_id;
    $lookupQuery->bind_param("i", $lookupEmployeeId);
    if (!$lookupQuery->execute()) {
        $error = $lookupQuery->error;
        $lookupQuery->close();
        throw new RuntimeException('Unable to look up attendance employee: ' . $error);
    }
    $lookupResult = $lookupQuery->get_result();
    $employeeRow = $lookupResult->fetch_assoc();
    $lookupQuery->close();

    if (!$employeeRow) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID not found.']);
        exit;
    }

    $today = date('Y-m-d');
    finalizeDailyAttendance($conn, $today, $nowTime);
    $attendanceQuery = $conn->prepare("SELECT time_in, time_out, time_in_2, time_out_2 FROM attendance WHERE employee_id = ? AND attendance_date = ? ORDER BY attendance_id DESC LIMIT 1");
    $attendanceQuery->bind_param("is", $lookupEmployeeId, $today);
    $attendanceQuery->execute();
    $attendanceRow = $attendanceQuery->get_result()->fetch_assoc();
    $isLogistics = isLogisticsEmployee($conn, $lookupEmployeeId);
    $nextAction = getNextAttendanceActionForSession($attendanceRow, $isLogistics, $nowTime);
    $lookupMessage = null;
    if ($timeInClosed) {
        $nextAction = null;
        $lookupMessage = 'Attendance is closed from 5:01 PM to 5:00 AM. It opens again at 5:01 AM.';
    } elseif (
        !$isLogistics
        && $nowTime < '12:00:00'
        && !empty($attendanceRow['time_in'])
        && $attendanceRow['time_in'] !== '00:00:00'
        && !empty($attendanceRow['time_out'])
        && $attendanceRow['time_out'] !== '00:00:00'
    ) {
        $lookupMessage = 'AM session complete. PM session opens at 12:00 PM.';
    }

    $lookupResponse = [
        'status' => 'success',
        'employee_name' => $employeeRow['username'],
        'next_action' => $nextAction
    ];
    if ($lookupMessage !== null) {
        $lookupResponse['message'] = $lookupMessage;
    }
    echo json_encode($lookupResponse);
    exit;
}

$employee_id = null;
$user_id = null;
$username = '';
$isAuthenticatedUserPunch = false;

// Check kung galing sa system account (USER_ prefix)
if (preg_match('/^USER_([1-9][0-9]*)$/', (string) $raw_employee_id, $userIdMatch) === 1) {
    $userId = (int) $userIdMatch[1];
    if (!isset($_SESSION['id']) || (int) $_SESSION['id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You can only submit attendance for your signed-in account.']);
        exit;
    }
    
    // Hanapin sa users table
    $userQuery = $conn->prepare("SELECT id, name, is_active FROM users WHERE id = ?");
    if (!$userQuery) {
        throw new RuntimeException('Unable to prepare signed-in attendance account lookup: ' . $conn->error);
    }
    $userQuery->bind_param("i", $userId);
    if (!$userQuery->execute()) {
        $error = $userQuery->error;
        $userQuery->close();
        throw new RuntimeException('Unable to look up signed-in attendance account: ' . $error);
    }
    $userRes = $userQuery->get_result();

    if ($userRow = $userRes->fetch_assoc()) {
        $userQuery->close();
        if ((int) $userRow['is_active'] !== 1) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'This account is inactive.']);
            exit;
        }
        $isAuthenticatedUserPunch = true;
        $user_id = $userRow['id'];
        $username = $userRow['name'];
        
        // Hanapin kung may kaugnay na record sa employee table
        $empCheck = $conn->prepare("SELECT employee_id FROM employee WHERE username = ? OR employee_id = ?");
        $empCheck->bind_param("si", $username, $user_id);
        $empCheck->execute();
        $empRes = $empCheck->get_result();
        
        if ($empRow = $empRes->fetch_assoc()) {
            $employee_id = $empRow['employee_id'];
        } else {
            // Auto-create employee record para sa system account
            $insertEmp = $conn->prepare("INSERT INTO employee (username, position) VALUES (?, 'System User')");
            $insertEmp->bind_param("s", $username);
            $insertEmp->execute();
            $employee_id = $conn->insert_id;
        }
    } else {
        $userQuery->close();
    }
} else {
    // Normal Employee ID Search
    if (!ctype_digit((string) $raw_employee_id) || (int) $raw_employee_id < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid Employee ID.']);
        exit;
    }
    $empIdInt = (int) $raw_employee_id;
    $empCheck = $conn->prepare('SELECT employee_id, username FROM employee WHERE employee_id = ?');
    if (!$empCheck) {
        throw new RuntimeException('Unable to prepare attendance employee check: ' . $conn->error);
    }
    $empCheck->bind_param("i", $empIdInt);
    if (!$empCheck->execute()) {
        $error = $empCheck->error;
        $empCheck->close();
        throw new RuntimeException('Unable to check attendance employee: ' . $error);
    }
    $empRes = $empCheck->get_result();
    
    if ($empRow = $empRes->fetch_assoc()) {
        $employee_id = $empRow['employee_id'];
        $username = $empRow['username'];
    }
    $empCheck->close();
}

if (!$employee_id && !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found!']);
    exit;
}

// Tamang Oras at Petsa para sa Pilipinas
$today = date('Y-m-d');
$isLogistics = isLogisticsEmployee($conn, (int) ($employee_id ?? 0), $user_id);
ensureLeaveSchema($conn);
if (
    $employee_id
    && employeeHasLeaveOnDate($conn, (int) $employee_id, $today)
    && in_array($action_type, ['time_in', 'time_out', 'time_in_2', 'time_out_2', 'submit'], true)
) {
    echo json_encode([
        'status' => 'error',
        'message' => 'A full-day leave is recorded for today. HR must cancel or correct the leave before attendance can be punched.'
    ]);
    exit;
}
$attendanceLockKey = 'tasktrack_att_' . ($employee_id ? 'e' . (int) $employee_id : 'u' . (int) $user_id) . '_' . str_replace('-', '', $today);
$lockStatement = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
if (!$lockStatement) {
    throw new RuntimeException('Unable to prepare attendance submission lock: ' . $conn->error);
}
$lockStatement->bind_param('s', $attendanceLockKey);
if (!$lockStatement->execute()) {
    $error = $lockStatement->error;
    $lockStatement->close();
    throw new RuntimeException('Unable to acquire attendance submission lock: ' . $error);
}
$lockAcquired = (int) ($lockStatement->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
$lockStatement->close();
if (!$lockAcquired) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Attendance is being submitted. Wait a moment and try again.']);
    exit;
}
register_shutdown_function(static function () use ($conn, $attendanceLockKey): void {
    $releaseStatement = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($releaseStatement) {
        $releaseStatement->bind_param('s', $attendanceLockKey);
        if (!$releaseStatement->execute()) {
            error_log('Unable to release attendance submission lock: ' . $releaseStatement->error);
        } else {
            $released = $releaseStatement->get_result()->fetch_row()[0] ?? null;
            if ((int) $released !== 1) {
                error_log('Attendance submission lock was not owned at request shutdown.');
            }
        }
        $releaseStatement->close();
    } else {
        error_log('Unable to prepare attendance submission lock release: ' . $conn->error);
    }
});
finalizeDailyAttendance($conn, $today, $nowTime);

// 1. Hanapin kung may attendance record na ngayong araw
$checkAtt = $conn->prepare("SELECT attendance_id, time_in, time_out, time_in_2, time_out_2, status, is_late FROM attendance WHERE (employee_id = ? OR user_id = ?) AND attendance_date = ? ORDER BY attendance_id DESC LIMIT 1");
$checkAtt->bind_param("iis", $employee_id, $user_id, $today);
$checkAtt->execute();
$attRes = $checkAtt->get_result();
$attRow = $attRes->fetch_assoc();

if ($action_type === 'submit') {
    $action_type = getNextAttendanceActionForSession($attRow, $isLogistics, $nowTime);
    if ($action_type === null) {
        echo json_encode(['status' => 'error', 'message' => "{$username}'s attendance is already complete for today.", 'next_action' => null]);
        exit;
    }
} elseif ($action_type !== 'lookup') {
    $expectedAction = getNextAttendanceActionForSession($attRow, $isLogistics, $nowTime);
    if ($action_type !== $expectedAction) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Attendance action is out of order. Refresh the employee lookup and submit the displayed next action.',
            'next_action' => $expectedAction
        ]);
        exit;
    }
}

if ($attRow) {
    // Update existing attendance record
    $attendance_id = $attRow['attendance_id'];
    $isFirstTimeIn = $action_type === 'time_in'
        && (empty($attRow['time_in']) || $attRow['time_in'] === '00:00:00');
    $attendanceIsLate = $isFirstTimeIn
        ? ($nowTime > '08:00:00' ? 1 : 0)
        : (int) $attRow['is_late'];
    $attendanceStatus = $isFirstTimeIn
        ? ($nowTime > '08:00:00' ? 'Late' : 'Present')
        : $attRow['status'];
    $updateQuery = $conn->prepare("UPDATE attendance SET {$action_type} = ?, status = ?, is_late = ? WHERE attendance_id = ?");
    $updateQuery->bind_param("ssii", $nowTime, $attendanceStatus, $attendanceIsLate, $attendance_id);
    
    if ($updateQuery->execute()) {
        recordSystemAudit(
            $conn,
            $user_id ? (int) $user_id : (isset($_SESSION['id']) ? (int) $_SESSION['id'] : null),
            (string) ($_SESSION['name'] ?? $username),
            'attendance_' . $action_type,
            'employee',
            (int) $employee_id,
            attendanceActionLabel($action_type) . ' recorded for ' . $username . ' on ' . $today . '.'
        );
        $nextAction = null;
        if ($isSequentialSubmit) {
            $updatedAttendance = array_merge($attRow, [$action_type => $nowTime]);
            $nextAction = getNextAttendanceActionForSession($updatedAttendance, $isLogistics, $nowTime);
        }
        $nextActionMessage = !$isLogistics
            && $nextAction === null
            && $nowTime < '12:00:00'
            ? 'AM session complete. PM session opens at 12:00 PM.'
            : null;
        finalizeDailyAttendance($conn, $today, $nowTime);
        $finalStatus = getAttendanceStatus($conn, $attendance_id);
        $statusNotices = getAttendanceStatusNotices($finalStatus, $attendanceIsLate, $isFirstTimeIn);
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully recorded " . attendanceActionLabel($action_type) . " for {$username}!{$statusNotices}",
            'next_action' => $nextAction,
            'next_action_message' => $nextActionMessage
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update attendance.']);
    }   
} else {
    // Insert new attendance record
    $attendanceIsLate = $action_type === 'time_in' && $nowTime > '08:00:00' ? 1 : 0;
    $attendanceStatus = $attendanceIsLate
        ? 'Late'
        : 'Present';
    $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, user_id, username, attendance_date, {$action_type}, status, is_late) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertQuery->bind_param("iissssi", $employee_id, $user_id, $username, $today, $nowTime, $attendanceStatus, $attendanceIsLate);
    
    if ($insertQuery->execute()) {
        recordSystemAudit(
            $conn,
            $user_id ? (int) $user_id : (isset($_SESSION['id']) ? (int) $_SESSION['id'] : null),
            (string) ($_SESSION['name'] ?? $username),
            'attendance_' . $action_type,
            'employee',
            (int) $employee_id,
            attendanceActionLabel($action_type) . ' recorded for ' . $username . ' on ' . $today . '.'
        );
        $nextAction = $isSequentialSubmit
            ? getNextAttendanceActionForSession([$action_type => $nowTime], $isLogistics, $nowTime)
            : null;
        $nextActionMessage = !$isLogistics
            && $nextAction === null
            && $nowTime < '12:00:00'
            ? 'AM session complete. PM session opens at 12:00 PM.'
            : null;
        $attendanceId = $conn->insert_id;
        finalizeDailyAttendance($conn, $today, $nowTime);
        $finalStatus = getAttendanceStatus($conn, $attendanceId);
        $statusNotices = getAttendanceStatusNotices($finalStatus, $attendanceIsLate, $action_type === 'time_in');
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully recorded " . attendanceActionLabel($action_type) . " for {$username}!{$statusNotices}",
            'next_action' => $nextAction,
            'next_action_message' => $nextActionMessage
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to record attendance.']);
    }
}

function attendanceActionLabel($action)
{
    $labels = [
        'time_in' => 'AM Time In',
        'time_out' => 'AM Time Out',
        'time_in_2' => 'PM Time In',
        'time_out_2' => 'PM Time Out'
    ];

    return $labels[$action] ?? $action;
}

function getAttendanceStatus(mysqli $conn, int $attendanceId): string
{
    $statement = $conn->prepare("SELECT status FROM attendance WHERE attendance_id = ?");
    $statement->bind_param("i", $attendanceId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return $row['status'] ?? '';
}

function getAttendanceStatusNotices(string $status, int $isLate, bool $isFirstTimeIn): string
{
    $notices = [];
    if ($isFirstTimeIn && $isLate) {
        $notices[] = 'You are late.';
    }
    if ($status === 'Half Day') {
        $notices[] = $isLate
            ? 'Your attendance is marked as Half Day (Late).'
            : 'Your attendance is marked as Half Day.';
    }

    return $notices ? ' ' . implode(' ', $notices) : '';
}
?>