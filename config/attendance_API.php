<?php
// config/attendance_API.php

ob_start();
header('Content-Type: application/json; charset=utf-8');

// Set Philippine Timezone
date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/connection.php';

    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed!');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
        $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';
        $current_date = date('Y-m-d');
        
        // 12-Hour standard time format (AM/PM)
        $current_time = date('h:i:s A');

        if ($employee_id <= 0 || empty($action_type)) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Please select an employee!']);
            exit();
        }

        // FETCH EMPLOYEE USERNAME FROM EMPLOYEE TABLE
        $empUsername = '';
        $getEmpStmt = $conn->prepare('SELECT username FROM employee WHERE employee_id = ?');
        $getEmpStmt->bind_param('i', $employee_id);
        $getEmpStmt->execute();
        $empResult = $getEmpStmt->get_result();

        if ($empResult && $empResult->num_rows > 0) {
            $empRow = $empResult->fetch_assoc();
            $empUsername = !empty($empRow['username']) ? $empRow['username'] : ('Employee #' . $employee_id);
        } else {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Employee not found!']);
            exit();
        }

        // CHECK IF ATTENDANCE RECORD EXISTS FOR TODAY
        $checkStmt = $conn->prepare('SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?');
        $checkStmt->bind_param('is', $employee_id, $current_date);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $allowed_columns = ['time_in', 'time_out', 'time_in_2', 'time_out_2'];

            if (in_array($action_type, $allowed_columns, true)) {
                // Update time column and username
                $updateStmt = $conn->prepare("UPDATE attendance SET $action_type = ?, username = ? WHERE employee_id = ? AND attendance_date = ?");
                $updateStmt->bind_param('ssis', $current_time, $empUsername, $employee_id, $current_date);
                $updateStmt->execute();

                $action_label = str_replace('_', ' ', strtoupper($action_type));
                ob_end_clean();
                echo json_encode(['status' => 'success', 'message' => "Recorded $action_label ($current_time) for $empUsername"]);
            } else {
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid action type!']);
            }
        } else {
            if ($action_type === 'time_in') {
                // INSERT NEW ATTENDANCE RECORD
                $insertStmt = $conn->prepare("INSERT INTO attendance (employee_id, username, attendance_date, time_in, status) VALUES (?, ?, ?, ?, 'Present')");
                $insertStmt->bind_param('isss', $employee_id, $empUsername, $current_date, $current_time);
                $insertStmt->execute();

                ob_end_clean();
                echo json_encode(['status' => 'success', 'message' => "Time In 1 recorded ($current_time) for $empUsername"]);
            } else {
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'You must record Time In 1 first for today!']);
            }
        }

        exit();
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    exit();
}
?>