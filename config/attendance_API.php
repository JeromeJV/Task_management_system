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

    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $display_time = date('h:i:s A'); // Para sa notification response

    // =========================================================
    // 1. AUTOMATIC CHECKING FOR ABSENT EMPLOYEES (5:01 PM ONWARDS)
    // =========================================================
    if ($current_time >= '17:01:00') {
        $markAbsentStmt = $conn->prepare("
            INSERT INTO attendance (employee_id, username, attendance_date, status)
            SELECT 
                e.employee_id, 
                COALESCE(NULLIF(e.username, ''), CONCAT('Employee #', e.employee_id)), 
                ?, 
                'Absent'
            FROM employee e
            WHERE e.employee_id NOT IN (
                SELECT employee_id 
                FROM attendance 
                WHERE attendance_date = ?
            )
        ");
        if ($markAbsentStmt) {
            $markAbsentStmt->bind_param('ss', $current_date, $current_date);
            $markAbsentStmt->execute();
            $markAbsentStmt->close();
        }
    }

    // =========================================================
    // HELPER FUNCTION PARA SA STATUS COMPUTATION
    // =========================================================
    function calculateStatus($time_string) {
        if ($time_string >= '06:00:00' && $time_string <= '08:00:00') {
            return 'Present';
        } elseif ($time_string > '08:00:00' && $time_string <= '17:00:00') {
            return 'Late';
        } else {
            return 'Absent';
        }
    }

    // =========================================================
    // 2. PROCESS ATTENDANCE ACTION (POST REQUEST)
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
        $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';

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
            $getEmpStmt->close();
        } else {
            $getEmpStmt->close();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Employee not found!']);
            exit();
        }

        // CHECK IF ATTENDANCE RECORD EXISTS FOR TODAY
        $checkStmt = $conn->prepare('SELECT attendance_id, time_in, status FROM attendance WHERE employee_id = ? AND attendance_date = ?');
        $checkStmt->bind_param('is', $employee_id, $current_date);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $existingRecord = $checkResult->fetch_assoc();
            $checkStmt->close();

            $allowed_columns = ['time_in', 'time_out', 'time_in_2', 'time_out_2'];

            if (in_array($action_type, $allowed_columns, true)) {
                
                // Kapag nag-Time In ulit o nire-overwrite ang Auto-Absent status
                if ($action_type === 'time_in') {
                    $new_status = calculateStatus($current_time);
                    $updateStmt = $conn->prepare("UPDATE attendance SET time_in = ?, username = ?, status = ? WHERE employee_id = ? AND attendance_date = ?");
                    $updateStmt->bind_param('sssis', $current_time, $empUsername, $new_status, $employee_id, $current_date);
                } else {
                    // Signal error kung wala pang time_in kahit may auto-absent record
                    if (empty($existingRecord['time_in']) || $existingRecord['time_in'] === '00:00:00') {
                        ob_end_clean();
                        echo json_encode(['status' => 'error', 'message' => 'You must record Time In 1 first for today!']);
                        exit();
                    }

                    $new_status = $existingRecord['status'];
                    $updateStmt = $conn->prepare("UPDATE attendance SET {$action_type} = ?, username = ? WHERE employee_id = ? AND attendance_date = ?");
                    $updateStmt->bind_param('ssis', $current_time, $empUsername, $employee_id, $current_date);
                }

                $updateStmt->execute();
                $updateStmt->close();

                $action_label = str_replace('_', ' ', strtoupper($action_type));
                ob_end_clean();
                echo json_encode([
                    'status' => 'success', 
                    'message' => "Recorded $action_label ($display_time) for $empUsername." . ($action_type === 'time_in' ? " Status: $new_status" : "")
                ]);
            } else {
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'Invalid action type!']);
            }
        } else {
            $checkStmt->close();

            if ($action_type === 'time_in') {
                $attendance_status = calculateStatus($current_time);

                $insertStmt = $conn->prepare("INSERT INTO attendance (employee_id, username, attendance_date, time_in, status) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->bind_param('issss', $employee_id, $empUsername, $current_date, $current_time, $attendance_status);
                $insertStmt->execute();
                $insertStmt->close();

                ob_end_clean();
                echo json_encode([
                    'status' => 'success', 
                    'message' => "Time In 1 recorded ($display_time) for $empUsername. Status: $attendance_status"
                ]);
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