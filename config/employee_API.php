<?php
// -----------------------------------------------------
//               Database Connection & Session
// -----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('config/connection.php');

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

            $dept    = !empty($app_data['department']) ? $app_data['department'] : 'General';
            $pos     = !empty($app_data['position_applied']) ? $app_data['position_applied'] : ($app_data['position'] ?? 'Employee');
            $contact = $app_data['contact_number'] ?? $app_data['phone'] ?? '';
            $address = trim(($app_data['house_number'] ?? '') . ' ' . ($app_data['street'] ?? '') . ' ' . ($app_data['barangay'] ?? '') . ' ' . ($app_data['city'] ?? ''));
            if (empty(trim($address))) {
                $address = $app_data['address'] ?? '';
            }
            $email   = $app_data['email'] ?? '';

            $safe_name    = mysqli_real_escape_string($conn, $fullname);
            $safe_dept    = mysqli_real_escape_string($conn, $dept);
            $safe_pos     = mysqli_real_escape_string($conn, $pos);
            $safe_contact = mysqli_real_escape_string($conn, $contact);
            $safe_addr    = mysqli_real_escape_string($conn, $address);
            $safe_email   = mysqli_real_escape_string($conn, $email);

            $hire_sql = "INSERT INTO employee (username, department, position, contact_number, address, email) 
                         VALUES ('$safe_name', '$safe_dept', '$safe_pos', '$safe_contact', '$safe_addr', '$safe_email')
                         ON DUPLICATE KEY UPDATE 
                            department = VALUES(department),
                            position = VALUES(position),
                            contact_number = VALUES(contact_number),
                            address = VALUES(address),
                            email = VALUES(email)";

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
    $department     = $_POST['department'] ?? '';
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
        $safe_department     = mysqli_real_escape_string($conn, $department);
        $safe_position       = mysqli_real_escape_string($conn, $position);
        $safe_contact_number = mysqli_real_escape_string($conn, $contact_number);
        $safe_address        = mysqli_real_escape_string($conn, $address);
        $safe_email          = mysqli_real_escape_string($conn, $email);
        
        $sql = "INSERT INTO employee (username, department, position, contact_number, address, email) 
                VALUES ('$safe_user_name', '$safe_department', '$safe_position', '$safe_contact_number', '$safe_address', '$safe_email')";
        
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
    // I-redirect sa register.php para doon i-edit ang details sa form
    header("Location: register.php?edit_id=" . urlencode($passid));
    exit();
}

// -----------------------------------------------------
// 3. Update Employee Save Execution
// -----------------------------------------------------
if (isset($_POST['submit']) && isset($_POST['is_update'])) {
    $employee_id    = $_POST['employee_id'] ?? '';
    $username       = $_POST['username'] ?? '';
    $department     = $_POST['department'] ?? '';
    $position       = $_POST['position'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $address        = $_POST['address'] ?? '';
    $email          = $_POST['email'] ?? '';

    $safe_employee_id    = mysqli_real_escape_string($conn, $employee_id);
    $safe_user_name      = mysqli_real_escape_string($conn, $username);
    $safe_department     = mysqli_real_escape_string($conn, $department);
    $safe_position       = mysqli_real_escape_string($conn, $position);
    $safe_contact_number = mysqli_real_escape_string($conn, $contact_number);
    $safe_address        = mysqli_real_escape_string($conn, $address);
    $safe_email          = mysqli_real_escape_string($conn, $email);

    $sql = "UPDATE employee 
            SET username = '$safe_user_name', 
                department = '$safe_department',
                position = '$safe_position',
                contact_number = '$safe_contact_number',
                address = '$safe_address',
                email = '$safe_email'
            WHERE employee_id = '$safe_employee_id'";

    $query = mysqli_query($conn, $sql);

    if ($query) {
        $updateAtt = $conn->prepare("UPDATE attendance SET username = ? WHERE employee_id = ?");
        $updateAtt->bind_param("si", $safe_user_name, $safe_employee_id);
        $updateAtt->execute();

        $_SESSION['msg'] = "Employee updated successfully.";
    } else {
        $_SESSION['err'] = "Update Failed: " . mysqli_error($conn);
    }

    // MATAPOS MAG-UPDATE, BABALIK SA HR.PHP
    header("Location: HR.php");
    exit();

} elseif (isset($_POST['can'])) {
    header("Location: HR.php");
    exit();
}

// -----------------------------------------------------
// 4. View / Fetch All Records
// -----------------------------------------------------
$sql    = "SELECT * FROM employee ORDER BY employee_id ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>