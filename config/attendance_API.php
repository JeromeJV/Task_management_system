<?php
header('Content-Type: application/json');

// 1. I-set ang Timezone sa Asia/Manila para 6:00 PM ang ma-record
date_default_timezone_set('Asia/Manila');

// Siguraduhing tama ang path ng connection file
include __DIR__ . '/connection.php';

$raw_employee_id = $_POST['employee_id'] ?? '';
$action_type = $_POST['action_type'] ?? '';

// Sanitize & validate action types
$allowed_actions = ['time_in', 'time_out', 'time_in_2', 'time_out_2'];
if (empty($raw_employee_id) || !in_array($action_type, $allowed_actions)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
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

// 1. Hanapin kung may attendance record na ngayong araw
$checkAtt = $conn->prepare("SELECT attendance_id FROM attendance WHERE (employee_id = ? OR user_id = ?) AND attendance_date = ?");
$checkAtt->bind_param("iis", $employee_id, $user_id, $today);
$checkAtt->execute();
$attRes = $checkAtt->get_result();

if ($attRow = $attRes->fetch_assoc()) {
    // Update existing attendance record
    $attendance_id = $attRow['attendance_id'];
    $updateQuery = $conn->prepare("UPDATE attendance SET {$action_type} = ?, status = 'Present' WHERE attendance_id = ?");
    $updateQuery->bind_param("si", $nowTime, $attendance_id);
    
    if ($updateQuery->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Successfully recorded {$action_type} for {$username}!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update attendance.']);
    }
} else {
    // Insert new attendance record
    $insertQuery = $conn->prepare("INSERT INTO attendance (employee_id, user_id, username, attendance_date, {$action_type}, status) VALUES (?, ?, ?, ?, ?, 'Present')");
    $insertQuery->bind_param("iisss", $employee_id, $user_id, $username, $today, $nowTime);
    
    if ($insertQuery->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Successfully recorded {$action_type} for {$username}!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to record attendance.']);
    }
}
?>