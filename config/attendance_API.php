<?php
header('Content-Type: application/json');

// 1. I-set ang Timezone sa Asia/Manila para 6:00 PM ang ma-record
date_default_timezone_set('Asia/Manila');

// Siguraduhing tama ang path ng connection file
include __DIR__ . '/connection.php';
require_once __DIR__ . '/attendance_helpers.php';

$raw_employee_id = $_POST['employee_id'] ?? '';
$action_type = $_POST['action_type'] ?? '';

// Sanitize & validate action types
$allowed_actions = ['time_in', 'time_out', 'time_in_2', 'time_out_2', 'lookup', 'submit'];
if (empty($raw_employee_id) || !in_array($action_type, $allowed_actions, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
    exit;
}

$isSequentialSubmit = $action_type === 'submit';

if ($action_type === 'lookup') {
    if (!ctype_digit((string) $raw_employee_id) || (int) $raw_employee_id < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid Employee ID.']);
        exit;
    }

    $lookupQuery = $conn->prepare("SELECT employee_id, username FROM employee WHERE employee_id = ?");
    $lookupEmployeeId = (int) $raw_employee_id;
    $lookupQuery->bind_param("i", $lookupEmployeeId);
    $lookupQuery->execute();
    $lookupResult = $lookupQuery->get_result();
    $employeeRow = $lookupResult->fetch_assoc();

    if (!$employeeRow) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID not found.']);
        exit;
    }

    $today = date('Y-m-d');
    finalizeDailyAttendance($conn, $today, date('H:i:s'));
    $attendanceQuery = $conn->prepare("SELECT time_in, time_out, time_in_2, time_out_2 FROM attendance WHERE employee_id = ? AND attendance_date = ? ORDER BY attendance_id DESC LIMIT 1");
    $attendanceQuery->bind_param("is", $lookupEmployeeId, $today);
    $attendanceQuery->execute();
    $attendanceRow = $attendanceQuery->get_result()->fetch_assoc();
    $nextAction = getNextAttendanceAction($attendanceRow);

    echo json_encode([
        'status' => 'success',
        'employee_name' => $employeeRow['username'],
        'next_action' => $nextAction
    ]);
    exit;
}

$employee_id = null;
$user_id = null;
$username = '';

// Check kung galing sa system account (USER_ prefix)
if (strpos($raw_employee_id, 'USER_') === 0) {
    $userId = (int) str_replace('USER_', '', $raw_employee_id);
    
    // Hanapin sa users table
    $userQuery = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
    $userQuery->bind_param("i", $userId);
    $userQuery->execute();
    $userRes = $userQuery->get_result();

    if ($userRow = $userRes->fetch_assoc()) {
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
    }
} else {
    // Normal Employee ID Search
    $empIdInt = (int) $raw_employee_id;
    $empCheck = $conn->prepare("SELECT employee_id, username FROM employee WHERE employee_id = ?");
    $empCheck->bind_param("i", $empIdInt);
    $empCheck->execute();
    $empRes = $empCheck->get_result();
    
    if ($empRow = $empRes->fetch_assoc()) {
        $employee_id = $empRow['employee_id'];
        $username = $empRow['username'];
    }
}

if (!$employee_id && !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found!']);
    exit;
}

// Tamang Oras at Petsa para sa Pilipinas
$today = date('Y-m-d');
$nowTime = date('H:i:s');
finalizeDailyAttendance($conn, $today, $nowTime);

// 1. Hanapin kung may attendance record na ngayong araw
$checkAtt = $conn->prepare("SELECT attendance_id, time_in, time_out, time_in_2, time_out_2, status, is_late FROM attendance WHERE (employee_id = ? OR user_id = ?) AND attendance_date = ? ORDER BY attendance_id DESC LIMIT 1");
$checkAtt->bind_param("iis", $employee_id, $user_id, $today);
$checkAtt->execute();
$attRes = $checkAtt->get_result();
$attRow = $attRes->fetch_assoc();

if ($action_type === 'submit') {
    $action_type = getNextAttendanceAction($attRow);
    if ($action_type === null) {
        echo json_encode(['status' => 'error', 'message' => "{$username}'s attendance is already complete for today.", 'next_action' => null]);
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
        $nextAction = null;
        if ($isSequentialSubmit) {
            $updatedAttendance = array_merge($attRow, [$action_type => $nowTime]);
            $nextAction = getNextAttendanceAction($updatedAttendance);
        }
        finalizeDailyAttendance($conn, $today, $nowTime);
        $finalStatus = getAttendanceStatus($conn, $attendance_id);
        $statusNotices = getAttendanceStatusNotices($finalStatus, $attendanceIsLate, $isFirstTimeIn);
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully recorded " . attendanceActionLabel($action_type) . " for {$username}!{$statusNotices}",
            'next_action' => $nextAction
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
        $nextAction = $isSequentialSubmit ? 'time_out' : null;
        $attendanceId = $conn->insert_id;
        finalizeDailyAttendance($conn, $today, $nowTime);
        $finalStatus = getAttendanceStatus($conn, $attendanceId);
        $statusNotices = getAttendanceStatusNotices($finalStatus, $attendanceIsLate, $action_type === 'time_in');
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully recorded " . attendanceActionLabel($action_type) . " for {$username}!{$statusNotices}",
            'next_action' => $nextAction
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to record attendance.']);
    }
}

function getNextAttendanceAction($attendance)
{
    $actions = ['time_in', 'time_out', 'time_in_2', 'time_out_2'];
    if (!$attendance) {
        return $actions[0];
    }

    foreach ($actions as $action) {
        if (empty($attendance[$action]) || $attendance[$action] === '00:00:00') {
            return $action;
        }
    }

    return null;
}

function attendanceActionLabel($action)
{
    $labels = [
        'time_in' => 'Time In 1',
        'time_out' => 'Time Out 1',
        'time_in_2' => 'Time In 2',
        'time_out_2' => 'Time Out 2'
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