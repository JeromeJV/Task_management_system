<?php
// -----------------------------------------------------
//               Database Connection
// -----------------------------------------------------
include('config/connection.php');

// Siguraduhing tama ang timezone
date_default_timezone_set('Asia/Manila');

$message = "";
$status_message = "";
$delete_message = "";
$hire_message = "";

$username_err = '';$contact_number_err = '';

// Helper function para i-connect o i-register sa attendance table
function registerEmployeeToAttendance($conn,$employee_id, $username) {$today = date('Y-m-d');
    
    // Tignan kung may attendance record na para sa araw na ito
    $check =$conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $check->bind_param("is", $employee_id, $today);$check->execute();
    $res =$check->get_result();

    if ($res->num_rows === 0) {
        // Kung wala pa, mag-insert ng nakahandang record (Status: Absent o Pending)
        $stmt =$conn->prepare("INSERT INTO attendance (employee_id, username, attendance_date, status) VALUES (?, ?, ?, 'Absent')");
        $stmt->bind_param("iss", $employee_id,$username, $today);$stmt->execute();
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
    $fetch_app_res = mysqli_query($conn,$fetch_app_sql);

    if ($fetch_app_res && mysqli_num_rows($fetch_app_res) > 0) {
        $app_data = mysqli_fetch_assoc($fetch_app_res);
        $app_status = strtolower(trim($app_data['status'] ?? ''));

        if ($app_status === 'hired' || $app_status === 'passed') {$fullname = trim(($app_data['firstname'] ?? '') . ' ' . ($app_data['lastname'] ?? ''));
            if (empty($fullname)) {$fullname = $app_data['username'] ?? $app_data['name'] ?? 'New Employee';
            }

            $dept    = !empty($app_data['department']) ?$app_data['department'] : 'General';
            $pos     = !empty($app_data['position_applied']) ? $app_data['position_applied'] : ($app_data['position'] ?? 'Employee');
            $contact =$app_data['contact_number'] ?? $app_data['phone'] ?? '';$address = trim(($app_data['house_number'] ?? '') . ' ' . ($app_data['street'] ?? '') . ' ' . ($app_data['barangay'] ?? '') . ' ' . ($app_data['city'] ?? ''));
            if (empty(trim($address))) {
                $address =$app_data['address'] ?? '';
            }
            $email   =$app_data['email'] ?? '';

            $safe_name    = mysqli_real_escape_string($conn, $fullname);$safe_dept    = mysqli_real_escape_string($conn,$dept);
            $safe_pos     = mysqli_real_escape_string($conn, $pos);$safe_contact = mysqli_real_escape_string($conn,$contact);
            $safe_addr    = mysqli_real_escape_string($conn, $address);$safe_email   = mysqli_real_escape_string($conn,$email);

            $hire_sql = "INSERT INTO employee (username, department, position, contact_number, address, email) 
                         VALUES ('$safe_name', '$safe_dept', '$safe_pos', '$safe_contact', '$safe_addr', '$safe_email')
                         ON DUPLICATE KEY UPDATE 
                            department = VALUES(department),
                            position = VALUES(position),
                            contact_number = VALUES(contact_number),
                            address = VALUES(address),
                            email = VALUES(email)";

            if (mysqli_query($conn,$hire_sql)) {
                $new_emp_id = mysqli_insert_id($conn);
                if (!$new_emp_id) {
                    $get_emp = mysqli_query($conn, "SELECT employee_id FROM employee WHERE username = '$safe_name'");
                    if ($r = mysqli_fetch_assoc($get_emp)) {
                        $new_emp_id =$r['employee_id'];
                    }
                }
                
                if ($new_emp_id) {
                    registerEmployeeToAttendance($conn, $new_emp_id,$fullname);
                }

                $hire_message = "Applicant successfully hired, added to Employee database, and connected to Attendance.";
            } else {
                $hire_message = "Error transferring applicant: " . mysqli_error($conn);
            }
        } else {
            $hire_message = "Applicant status is '$app_status'. Must be 'Hired' or 'Passed' to proceed.";
        }
    } else {
        $hire_message = "Applicant record not found.";
    }
}

// -----------------------------------------------------
// 1. Manual Insert / Registration ng Employee Account
// -----------------------------------------------------
if (isset($_POST['submit']) && !isset($_POST['is_update'])) { 
    $username       =$_POST['username'] ?? '';
    $department     =$_POST['department'] ?? '';
    $position       =$_POST['position'] ?? '';
    $contact_number =$_POST['contact_number'] ?? '';
    $address        =$_POST['address'] ?? '';
    $email          =$_POST['email'] ?? '';

    $isValid = true;

    if (!empty($username) && !preg_match("/^[a-zA-Z0-9 ]*$/", $username)) {$username_err = "Please use only letters and spaces for your username.";
        $isValid = false;
    }

    if (!empty($contact_number) && !preg_match("/^[0-9 ]*$/", $contact_number)) {$contact_number_err = "Please use only numbers for your Contact Number.";
        $isValid = false;
    }

    if (!empty($contact_number) && strlen($contact_number) < 4) {$contact_number_err = "Your Contact Number must be at least 4 characters long.";
        $isValid = false;
    }

    if ($isValid) {$safe_user_name      = mysqli_real_escape_string($conn,$username);
        $safe_department     = mysqli_real_escape_string($conn, $department);$safe_position       = mysqli_real_escape_string($conn,$position);
        $safe_contact_number = mysqli_real_escape_string($conn, $contact_number);$safe_address        = mysqli_real_escape_string($conn,$address);
        $safe_email          = mysqli_real_escape_string($conn, $email);$sql = "INSERT INTO employee (username, department, position, contact_number, address, email) 
                VALUES ('$safe_user_name', '$safe_department', '$safe_position', '$safe_contact_number', '$safe_address', '$safe_email')";
        
        $query = mysqli_query($conn,$sql);

        if ($query) {
            $inserted_emp_id = mysqli_insert_id($conn);
            registerEmployeeToAttendance($conn,$inserted_emp_id, $username);$message = "New Employee added successfully and registered to Attendance system.";
        } else {
            $message = "Error: " . mysqli_error($conn);
        }
    }
}

// -----------------------------------------------------
// 2. User Account Registration (Mula sa Employee Selection)
// -----------------------------------------------------
$msg = '';
$name_err = '';$email_err = '';
$password_err = '';$cpassword_err = '';

if (isset($_POST['register_user'])) {
    $employee_id =$_POST['employee_id'] ?? '';
    $password    =$_POST['password'] ?? '';
    $cpassword   =$_POST['cpassword'] ?? '';
    $role        =$_POST['role'] ?? 'user';

    $isValid = true;

    if (empty($employee_id)) {$name_err = "Please select an employee.";
        $isValid = false;
    } else {
        // Kunin ang detalye ng napiling Employee
        $safe_emp_id = mysqli_real_escape_string($conn,$employee_id);
        $emp_query = mysqli_query($conn, "SELECT username, email FROM employee WHERE employee_id = '$safe_emp_id'");
        
        if ($emp_row = mysqli_fetch_assoc($emp_query)) {
            $name  =$emp_row['username'];
            $email =$emp_row['email'];
        } else {
            $name_err = "Selected employee not found.";
            $isValid = false;
        }
    }

    // Validation para sa Email duplicate check sa users table
    if ($isValid && !empty($email)) {$safe_email = mysqli_real_escape_string($conn,$email);
        $select_user = mysqli_query($conn, "SELECT id FROM `users` WHERE email = '$safe_email'");
        if (mysqli_num_rows($select_user) > 0) {$email_err = "This employee already has an active system account!";
            $isValid = false;
        }
    }

    // Validation para sa Password
    if (mb_strlen($password) < 8) {$password_err = "Your password must be at least 8 characters long.";
        $isValid = false;   
    } else if (!preg_match("#[0-9]+#", $password)) {$password_err = "Your password must contain at least one number.";
        $isValid = false;
    }

    // Validation para sa Confirm password
    if ($password !== $cpassword) {$cpassword_err = "Passwords do not match.";
        $isValid = false;
    }

    // I-save sa `users` table
    if ($isValid) {
        $safe_name     = mysqli_real_escape_string($conn, $name);$safe_email    = mysqli_real_escape_string($conn,$email);
        $safe_password = mysqli_real_escape_string($conn, $password);$safe_role     = mysqli_real_escape_string($conn,$role);

        $insert_user = "INSERT INTO `users` (`name`, `email`, `password`, `role`) 
                        VALUES ('$safe_name', '$safe_email', '$safe_password', '$safe_role')";
        
        if (mysqli_query($conn, $insert_user)) {$msg = "System User Account successfully created and linked to " . htmlspecialchars($name) . "!";
        } else {
            $msg = "Something went wrong: " . mysqli_error($conn);
        }
    }
}

// -----------------------------------------------------
// 3. Edit / Delete / Update para sa Employee o Users
// -----------------------------------------------------
$passid = $_POST['idno'] ?? $_POST['user_id'] ?? null;
$view_data = null;

// A. DELETE SYSTEM USER ACCOUNT (Mula sa Table sa Ibaba)
if (isset($_POST['delete_user']) && !empty($_POST['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);$delete_sql = "DELETE FROM `users` WHERE id = '$user_id'";
    if (mysqli_query($conn, $delete_sql)) {$delete_message = "User Account Deleted Successfully.";
    } else {
        $delete_message = "Error deleting record: " . mysqli_error($conn);
    }

// B. DELETE EMPLOYEE RECORD
} elseif (isset($_POST['del']) && !empty($passid)) {
    $safe_passid = mysqli_real_escape_string($conn, $passid);$sql = "DELETE FROM employee WHERE employee_id = '$safe_passid'";
    $result = mysqli_query($conn, $sql);$delete_message = "Employee Record Deleted Successfully.";

// C. FETCH EMPLOYEE FOR UPDATE
} elseif (isset($_POST['upd']) && !empty($passid)) {
    $safe_passid = mysqli_real_escape_string($conn, $passid);$sql = "SELECT * FROM employee WHERE employee_id = '$safe_passid'";
    $result = mysqli_query($conn,$sql);
    $row = mysqli_fetch_assoc($result);

    if ($row) {$view_data = [
            'employee_id'    => $safe_passid,
            'username'       => $row['username'],
            'department'     => $row['department'],
            'position'       => $row['position'],
            'contact_number' => $row['contact_number'],
            'address'        => $row['address'],
            'email'          => $row['email']  
        ];
    }
}

if (isset($_POST['submit']) && isset($_POST['is_update'])) {
    $employee_id    =$_POST['employee_id'] ?? '';
    $username       =$_POST['username'] ?? '';
    $department     =$_POST['department'] ?? '';
    $position       =$_POST['position'] ?? '';
    $contact_number =$_POST['contact_number'] ?? '';
    $address        =$_POST['address'] ?? '';
    $email          =$_POST['email'] ?? '';

    $safe_employee_id    = mysqli_real_escape_string($conn,$employee_id);
    $safe_user_name      = mysqli_real_escape_string($conn, $username);$safe_department     = mysqli_real_escape_string($conn,$department);
    $safe_position       = mysqli_real_escape_string($conn, $position);$safe_contact_number = mysqli_real_escape_string($conn,$contact_number);
    $safe_address        = mysqli_real_escape_string($conn, $address);$safe_email          = mysqli_real_escape_string($conn,$email);

    $sql = "UPDATE employee 
            SET username = '$safe_user_name', 
                department = '$safe_department',
                position = '$safe_position',
                contact_number = '$safe_contact_number',
                address = '$safe_address',
                email = '$safe_email'
            WHERE employee_id = '$safe_employee_id'";

    if (mysqli_query($conn,$sql)) {
        // I-update din sa attendance
        $updateAtt =$conn->prepare("UPDATE attendance SET username = ? WHERE employee_id = ?");
        $updateAtt->bind_param("si", $safe_user_name, $safe_employee_id);$updateAtt->execute();

        // I-update din ang pangalan sa users table kung pareho ang email
        $updateUser =$conn->prepare("UPDATE users SET name = ? WHERE email = ?");
        $updateUser->bind_param("ss", $safe_user_name, $safe_email);$updateUser->execute();

        $status_message = "Update Successful";
    } else {
        $status_message = "Update Failed: " . mysqli_error($conn);
    }
}

// -----------------------------------------------------
// 4. Fetch Data para sa Dropdown at Table
// -----------------------------------------------------

// A. KUNIN ANG UNREGISTERED EMPLOYEES (Para sa Dropdown Selection)
// Kukunin lang ang employees na WALA PANG account sa `users` table
$unregistered_employees = mysqli_query($conn, "
    SELECT e.employee_id, e.username, e.email, e.position 
    FROM employee e 
    LEFT JOIN users u ON e.email = u.email 
    WHERE u.id IS NULL 
    ORDER BY e.username ASC
");

// B. KUNIN ANG MGA REGISTERED SYSTEM USERS (Para sa Table sa Ibaba)
$sql = "SELECT * FROM `users` ORDER BY id ASC";
$result = mysqli_query($conn,$sql);

$records = [];$count = 0;

if ($result) {$count = mysqli_num_rows($result); // Dito tinukoy ang$count para hindi mag "No records"
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] =$row;
    }
}
?>