<?php
// -----------------------------------------------------
//               Database Connection & Session
// -----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('config/connection.php');
require_once __DIR__ . '/account_access.php';
require_once __DIR__ . '/leave_helpers.php';
requireActiveAccount($conn);
ensureLeaveSchema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['HR', 'super'], true)) {
        http_response_code(403);
        exit('You are not authorized to manage employee records.');
    }
}

// Siguraduhing tama ang timezone
date_default_timezone_set('Asia/Manila');

$message = "";
$status_message = "";
$delete_message = "";
$hire_message = "";

$username_err = '';
$contact_number_err = '';

// Helper function para i-connect o i-register sa attendance table
function registerEmployeeToAttendance($conn, $employee_id, $username) {
    $today = date('Y-m-d');
    
    // Tignan kung may attendance record na para sa araw na ito
    $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $check->bind_param("is", $employee_id, $today);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        // Kung wala pa, mag-insert ng nakahandang record (Status: Absent o Pending)
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, username, attendance_date, status) VALUES (?, ?, ?, 'Absent')");
        $stmt->bind_param("iss", $employee_id, $username, $today);
        $stmt->execute();
    }
}

// -----------------------------------------------------
// 0. Auto-Hire / Transfer mula sa Applicant (Hired / Passed)
// -----------------------------------------------------
if (isset($_POST['hire_applicant_id']) || isset($_POST['applicant_id'])) {
    $applicant_id = $_POST['hire_applicant_id'] ?? $_POST['applicant_id'];
    $safe_applicant_id = mysqli_real_escape_string($conn, $applicant_id);

    // Kunin ang data ng applicant
    $fetch_app_sql = "SELECT * FROM applicant WHERE applicant_id = '$safe_applicant_id'";
    $fetch_app_res = mysqli_query($conn, $fetch_app_sql);

    if ($fetch_app_res && mysqli_num_rows($fetch_app_res) > 0) {
        $app_data = mysqli_fetch_assoc($fetch_app_res);
        $app_status = strtolower(trim($app_data['status'] ?? ''));

        if ($app_status === 'hired' || $app_status === 'passed') {
            
            $fullname = trim(($app_data['firstname'] ?? '') . ' ' . ($app_data['lastname'] ?? ''));
            if (empty($fullname)) {
                $fullname = $app_data['username'] ?? $app_data['name'] ?? 'New Employee';
            }

            $pos     = !empty($app_data['position_applied']) ? $app_data['position_applied'] : ($app_data['position'] ?? 'Employee');
            $contact = $app_data['contact_number'] ?? $app_data['phone'] ?? '';
            $address = trim(($app_data['house_number'] ?? '') . ' ' . ($app_data['street'] ?? '') . ' ' . ($app_data['barangay'] ?? '') . ' ' . ($app_data['city'] ?? ''));
            if (empty(trim($address))) {
                $address = $app_data['address'] ?? '';
            }
            $email   = $app_data['email'] ?? '';

            $safe_name    = mysqli_real_escape_string($conn, $fullname);
            $safe_pos     = mysqli_real_escape_string($conn, $pos);
            $safe_contact = mysqli_real_escape_string($conn, $contact);
            $safe_addr    = mysqli_real_escape_string($conn, $address);
            $safe_email   = mysqli_real_escape_string($conn, $email);

            $hire_sql = "INSERT INTO employee (username, position, contact_number, address, email, hire_date)
                         VALUES ('$safe_name', '$safe_pos', '$safe_contact', '$safe_addr', '$safe_email', CURDATE())
                         ON DUPLICATE KEY UPDATE 
                            position = VALUES(position),
                            contact_number = VALUES(contact_number),
                            address = VALUES(address),
                            email = VALUES(email),
                            hire_date = COALESCE(hire_date, VALUES(hire_date))";

            if (mysqli_query($conn, $hire_sql)) {
                $new_emp_id = mysqli_insert_id($conn);
                if (!$new_emp_id) {
                    $get_emp = mysqli_query($conn, "SELECT employee_id FROM employee WHERE username = '$safe_name'");
                    if ($r = mysqli_fetch_assoc($get_emp)) {
                        $new_emp_id = $r['employee_id'];
                    }
                }
                
                if ($new_emp_id) {
                    registerEmployeeToAttendance($conn, $new_emp_id, $fullname);
                }

                $_SESSION['msg'] = "Applicant successfully hired, added to Employee database, and connected to Attendance.";
                header("Location: HR.php");
                exit();
            } else {
                $_SESSION['err'] = "Error transferring applicant: " . mysqli_error($conn);
                header("Location: HR.php");
                exit();
            }
        } else {
            $_SESSION['err'] = "Applicant status is '$app_status'. Must be 'Hired' or 'Passed' to proceed.";
            header("Location: HR.php");
            exit();
        }
    } else {
        $_SESSION['err'] = "Applicant record not found.";
        header("Location: HR.php");
        exit();
    }
}

// -----------------------------------------------------
// 1. Manual Insert ng Employee
// -----------------------------------------------------
if (isset($_POST['submit']) && !isset($_POST['is_update'])) { 
    $username       = $_POST['username'] ?? '';
    $position       = $_POST['position'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $address        = $_POST['address'] ?? '';
    $email          = $_POST['email'] ?? '';

    $isValid = true;

    if (!empty($username) && !preg_match("/^[a-zA-Z0-9 ]*$/", $username)) {
        $username_err = "Please use only letters and spaces for your username.";
        $isValid = false;
    }

    if (!empty($contact_number) && !preg_match("/^[0-9 ]*$/", $contact_number)) {
        $contact_number_err = "Please use only numbers for your Contact Number.";
        $isValid = false;
    }

    if (!empty($contact_number) && strlen($contact_number) < 4) {
        $contact_number_err = "Your Contact Number must be at least 4 characters long.";
        $isValid = false;
    }

    if ($isValid) {
        $safe_user_name      = mysqli_real_escape_string($conn, $username);
        $safe_position       = mysqli_real_escape_string($conn, $position);
        $safe_contact_number = mysqli_real_escape_string($conn, $contact_number);
        $safe_address        = mysqli_real_escape_string($conn, $address);
        $safe_email          = mysqli_real_escape_string($conn, $email);
        
        $sql = "INSERT INTO employee (username, position, contact_number, address, email, hire_date)
                VALUES ('$safe_user_name', '$safe_position', '$safe_contact_number', '$safe_address', '$safe_email', CURDATE())";
        
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $inserted_emp_id = mysqli_insert_id($conn);
            registerEmployeeToAttendance($conn, $inserted_emp_id, $username);

            $_SESSION['msg'] = "New Employee added successfully and registered to Attendance system.";
            header("Location: HR.php");
            exit();
        } else {
            $_SESSION['err'] = "Error: " . mysqli_error($conn);
            header("Location: HR.php");
            exit();
        }
    } else {
        $_SESSION['err'] = $username_err !== ''
            ? $username_err
            : ($contact_number_err !== '' ? $contact_number_err : 'Check the employee details and try again.');
        header("Location: HR.php");
        exit();
    }
}

// -----------------------------------------------------
// 2. Delete at Update Action (Lahat Babalik sa HR.php)
// -----------------------------------------------------
$passid = $_POST['idno'] ?? $_GET['edit_id'] ?? null;
$view_data = null;

// KAPAG PININDOT ANG DELETE BUTTON SA HR.PHP
if (isset($_POST['del']) && !empty($passid)) {
    $safe_passid = mysqli_real_escape_string($conn, $passid);
    
    $sql    = "DELETE FROM employee WHERE employee_id = '$safe_passid'";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $_SESSION['msg'] = "Record Deleted Successfully.";
    } else {
        $_SESSION['err'] = "Failed to delete record: " . mysqli_error($conn);
    }

    // DIREKTA BABALIK SA HR.PHP
    header("Location: HR.php");
    exit();

// KAPAG PININDOT ANG UPDATE BUTTON SA HR.PHP
} elseif (isset($_POST['upd']) && !empty($passid)) {
    $_SESSION['err'] = 'Open the employee profile and choose Edit Details to update this record.';
    header("Location: HR.php");
    exit();
}

// -----------------------------------------------------
// 3. Update Employee Save Execution
// -----------------------------------------------------
if (isset($_POST['submit']) && isset($_POST['is_update'])) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['HR', 'super'], true)) {
        http_response_code(403);
        exit('You are not authorized to update employee records.');
    }
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (
        empty($_SESSION['employee_edit_csrf'])
        || !hash_equals((string) $_SESSION['employee_edit_csrf'], $posted_token)
    ) {
        $_SESSION['err'] = 'Your session expired. Refresh the HR page and try again.';
        header('Location: HR.php');
        exit();
    }

    $employee_id = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
    $username = trim((string) ($_POST['username'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $contact_number = trim((string) ($_POST['contact_number'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $hire_date = trim((string) ($_POST['hire_date'] ?? ''));
    $validation_error = '';

    if (!$employee_id || $employee_id < 1) {
        $validation_error = 'Invalid employee record.';
    } elseif ($username === '' || mb_strlen($username) > 100) {
        $validation_error = 'Enter an employee name up to 100 characters.';
    } elseif (mb_strlen($position) > 100) {
        $validation_error = 'Position must be 100 characters or fewer.';
    } elseif (mb_strlen($contact_number) > 20 || ($contact_number !== '' && !preg_match('/^[0-9 ]+$/', $contact_number))) {
        $validation_error = 'Enter a valid contact number using digits and spaces only.';
    } elseif (mb_strlen($address) > 255) {
        $validation_error = 'Address must be 255 characters or fewer.';
    } elseif (mb_strlen($email) > 255 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $validation_error = 'Enter a valid email address up to 255 characters.';
    } elseif (
        $hire_date !== ''
        && (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)
            || !checkdate((int) substr($hire_date, 5, 2), (int) substr($hire_date, 8, 2), (int) substr($hire_date, 0, 4))
        )
    ) {
        $validation_error = 'Enter a valid hire date.';
    }

    if ($validation_error !== '') {
        $_SESSION['err'] = $validation_error;
    } else {
        $conn->begin_transaction();
        $employee_exists = $conn->prepare('SELECT employee_id FROM employee WHERE employee_id = ? FOR UPDATE');
        if (!$employee_exists) {
            $conn->rollback();
            throw new RuntimeException('Unable to verify employee record: ' . $conn->error);
        }
        $employee_exists->bind_param('i', $employee_id);
        if (!$employee_exists->execute()) {
            $error = $employee_exists->error;
            $employee_exists->close();
            $conn->rollback();
            throw new RuntimeException('Unable to verify employee record: ' . $error);
        }
        $employee_found = $employee_exists->get_result()->num_rows === 1;
        $employee_exists->close();

        if (!$employee_found) {
            $conn->rollback();
            $_SESSION['err'] = 'The employee record could not be found.';
        } else {
            $update_employee = $conn->prepare(
                'UPDATE employee
                 SET username = ?, position = ?, contact_number = ?, address = ?, email = ?, hire_date = NULLIF(?, \'\')
                 WHERE employee_id = ?'
            );
            if (!$update_employee) {
                $conn->rollback();
                throw new RuntimeException('Unable to prepare employee update: ' . $conn->error);
            }
            $update_employee->bind_param(
                'ssssssi',
                $username,
                $position,
                $contact_number,
                $address,
                $email,
                $hire_date,
                $employee_id
            );

            if (!$update_employee->execute()) {
                $error = $update_employee->error;
                $update_employee->close();
                $conn->rollback();
                $_SESSION['err'] = 'Employee update failed: ' . $error;
            } else {
                $update_employee->close();
                $update_attendance = $conn->prepare('UPDATE attendance SET username = ? WHERE employee_id = ?');
                if (!$update_attendance) {
                    $conn->rollback();
                    throw new RuntimeException('Unable to prepare attendance name update: ' . $conn->error);
                }
                $update_attendance->bind_param('si', $username, $employee_id);
                if (!$update_attendance->execute()) {
                    $error = $update_attendance->error;
                    $update_attendance->close();
                    $conn->rollback();
                    $_SESSION['err'] = 'Employee details were not saved because attendance names could not be updated: ' . $error;
                } else {
                    $update_attendance->close();
                    if (!$conn->commit()) {
                        $conn->rollback();
                        $_SESSION['err'] = 'Employee changes could not be committed.';
                    } else {
                        $_SESSION['msg'] = 'Employee updated successfully.';
                    }
                }
            }
        }
    }

    header("Location: HR.php");
    exit();

} elseif (isset($_POST['can'])) {
    header("Location: HR.php");
    exit();
}

// -----------------------------------------------------
// 4. View / Fetch All Records
// -----------------------------------------------------
$sql    = "SELECT e.*, u.profile_picture AS account_profile_picture
           FROM employee e
           LEFT JOIN users u ON u.employee_id = e.employee_id
           ORDER BY e.employee_id ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>