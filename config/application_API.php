<?php
// -----------------------------------------------------
//                      Insert
// -----------------------------------------------------
include('config/connection.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config/PHPMailer/Exception.php';
require_once 'config/PHPMailer/PHPMailer.php';
require_once 'config/PHPMailer/SMTP.php';

$message = "";

$lastname_err = $firstname_err = $middlename_err = '';
$contact_number_err = $email_err = $facebook_err = '';
$region_err = $province_err = $city_err = $barangay_err = $house_number_err = $street_err = '';
$position_applied_err = $company_name_err = $position_err = '';
$year_of_start_err = $date_of_start_err = $date_of_end_err = '';
$education_err = $start_date_err = $resume_err = '';

if (isset($_POST['submit'])) {
    $lastname        = trim($_POST['lastname'] ?? '');
    $firstname       = trim($_POST['firstname'] ?? '');
    $middlename      = trim($_POST['middlename'] ?? '');
    $contact_number  = trim($_POST['contact_number'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $facebook        = trim($_POST['facebook'] ?? '');

    $region          = trim($_POST['region'] ?? '');
    $house_number    = trim($_POST['house_number'] ?? '');
    $street          = trim($_POST['street'] ?? '');
    $barangay        = trim($_POST['barangay'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $province        = trim($_POST['province'] ?? '');

    $position_applied = trim($_POST['position_applied'] ?? '');
    $company_name     = trim($_POST['company_name'] ?? '');
    $position         = trim($_POST['position'] ?? '');
    $date_of_start    = trim($_POST['date_of_start'] ?? '');
    $date_of_end      = trim($_POST['date_of_end'] ?? '');

    $education  = trim($_POST['education'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');

    $isValid = true;

    // --- Validation Checks ---
    if (!preg_match("/^[a-zA-Z\s\-]+$/", $lastname)) {
        $lastname_err = "Please use only letters and spaces for your lastname.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z\s\-]+$/", $firstname)) {
        $firstname_err = "Please use only letters and spaces for your firstname.";
        $isValid = false;
    }

    if (!empty($middlename) && !preg_match("/^[a-zA-Z\s\-]+$/", $middlename)) {
        $middlename_err = "Please use only letters and spaces for your middlename.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9]+$/", $contact_number)) {  
        $contact_number_err = "Please use only numbers for your Contact Number.";
        $isValid = false;
    } elseif (strlen($contact_number) < 11) {
        $contact_number_err = "Your contact number must be at least 11 digits.";
        $isValid = false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
        $isValid = false;
    }

    // --- File Upload Handling ---
    $new_resume_name = "";
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['resume']['tmp_name'];
        $fileName      = $_FILES['resume']['name'];
        $fileSize      = $_FILES['resume']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = array('jpg', 'jpeg', 'png', 'pdf');
        $allowedMimeTypes  = array('image/jpeg', 'image/png', 'application/pdf');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmpPath);

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            $resume_err = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
            $isValid = false;
        } elseif ($fileSize > 10 * 1024 * 1024) { 
            $resume_err = "File size must be less than 10MB.";
            $isValid = false;
        } else {
            $new_resume_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
            $uploadFileDir   = './uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $new_resume_name;
            if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                $resume_err = "Error uploading the file. Please try again.";
                $isValid = false;
            }
        }
    } else {
        $resume_err = "Please upload your resume document.";
        $isValid = false;
    }

    // --- Database Insertion ---
    if ($isValid) {
        $stmt = $conn->prepare("INSERT INTO applicant (
            lastname, firstname, middlename, contact_number, facebook, email, 
            house_number, street, barangay, city, province, 
            position_applied, company_name, position, date_of_start, date_of_end, 
            education, start_date, resume_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param(
                "sssssssssssssssssss", 
                $lastname, $firstname, $middlename, $contact_number, $facebook, $email, 
                $house_number, $street, $barangay, $city, $province, 
                $position_applied, $company_name, $position, $date_of_start, $date_of_end, 
                $education, $start_date, $new_resume_name
            );

            if ($stmt->execute()) {
                $message = "Your Application was successfully sent.";
            } else {
                $message = "Execution Error: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $message = "Prepare Error: " . htmlspecialchars($conn->error);
        }
    }
}

// -----------------------------------------------------
//                      Edit / Delete
// -----------------------------------------------------

$passid = $_POST['idno'] ?? null;
$view_data = null;
$delete_message = "";

if (isset($_POST['del']) && !empty($passid)) {
    $stmt = $conn->prepare("DELETE FROM applicant WHERE applicant_id = ?");
    $stmt->bind_param("i", $passid);
    if ($stmt->execute()) {
        $delete_message = "Record Deleted Successfully. <br><a href='application_form.php'>View Records</a>";
    }
    $stmt->close();

} elseif (isset($_POST['upd']) && !empty($passid)) {
    $stmt = $conn->prepare("SELECT * FROM applicant WHERE applicant_id = ?");
    $stmt->bind_param("i", $passid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $view_data = [
            'applicant_id'    => $passid,
            'lastname'        => $row['lastname'] ?? '',
            'firstname'       => $row['firstname'] ?? '',
            'middlename'      => $row['middlename'] ?? '',
            'contact_number'  => $row['contact_number'] ?? '',
            'email'           => $row['email'] ?? '',
            'house_number'    => $row['house_number'] ?? '',
            'street'          => $row['street'] ?? '',
            'barangay'        => $row['barangay'] ?? '',
            'city'            => $row['city'] ?? '',
            'position_applied'=> $row['position_applied'] ?? '',
            'position'        => $row['position'] ?? '',
            'company_name'    => $row['company_name'] ?? '',
            'resume_path'     => $row['resume_path'] ?? ''
        ];
    }
    $stmt->close();
}

// -----------------------------------------------------
//                      Update
// -----------------------------------------------------

$status_message = "";

if (isset($_POST['update_submit'])) {
    $applicant_id     = $_POST['applicant_id'] ?? '';
    $lastname         = trim($_POST['lastname'] ?? '');
    $firstname        = trim($_POST['firstname'] ?? '');
    $middlename       = trim($_POST['middlename'] ?? '');
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $house_number     = trim($_POST['house_number'] ?? '');
    $street           = trim($_POST['street'] ?? '');
    $barangay         = trim($_POST['barangay'] ?? '');
    $city             = trim($_POST['city'] ?? '');
    $position_applied = trim($_POST['position_applied'] ?? '');
    $position         = trim($_POST['position'] ?? '');
    $company_name     = trim($_POST['company_name'] ?? '');

    $stmt = $conn->prepare("UPDATE applicant SET 
        lastname = ?, 
        firstname = ?, 
        middlename = ?, 
        contact_number = ?, 
        email = ?, 
        house_number = ?, 
        street = ?, 
        barangay = ?, 
        city = ?, 
        position_applied = ?, 
        position = ?, 
        company_name = ? 
        WHERE applicant_id = ?");

    if ($stmt) {
        $stmt->bind_param(
            "ssssssssssssi", 
            $lastname, $firstname, $middlename, $contact_number, $email, 
            $house_number, $street, $barangay, $city, 
            $position_applied, $position, $company_name, $applicant_id
        );

        if ($stmt->execute()) {
            $status_message = "<br>Update Successful<br><br><a href='application_form.php'><input type='button' name='back' value='View Records'></a>";
        } else {
            $status_message = "Error updating record: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}

// -----------------------------------------------------
//          Save / Update Interview Schedule & Auto-Hire
// -----------------------------------------------------

if (isset($_POST['save_interview'])) {
    $applicant_id   = $_POST['applicant_id'] ?? '';
    $interview_type = $_POST['interview_type'] ?? '';
    $interview_mode = $_POST['interview_mode'] ?? '';
    $status         = $_POST['status'] ?? '';
    $interview_date = $_POST['interview_date'] ?? '';
    
    // Kunin ang username at email mula sa modal submission
    $username       = $_POST['username'] ?? 'Applicant';
    $email          = $_POST['email'] ?? '';

    if (!empty($applicant_id) && !empty($interview_date)) {
        $stmt = $conn->prepare("UPDATE applicant SET 
            interview_type = ?, 
            interview_mode = ?, 
            status = ?, 
            interview_date = ? 
            WHERE applicant_id = ?");

        if ($stmt) {
            $stmt->bind_param("ssssi", $interview_type, $interview_mode, $status, $interview_date, $applicant_id);

            if ($stmt->execute()) {
                
                // =========================================================
                // 1. KUNG "PASSED" ANG STATUS -> AUTO-REGISTER SA EMPLOYEE TABLE
                // =========================================================
                if ($status === 'Passed') {
                    // Kunin muna ang buong detalye ng applicant mula sa database
                    $get_app = $conn->prepare("SELECT * FROM applicant WHERE applicant_id = ?");
                    $get_app->bind_param("i", $applicant_id);
                    $get_app->execute();
                    $app_data = $get_app->get_result()->fetch_assoc();
                    $get_app->close();

                    if ($app_data) {
                        $emp_email    = $app_data['email'];
                        $emp_username = strtolower($app_data['firstname'] . '_' . $app_data['lastname']);
                        $emp_contact  = $app_data['contact_number'];
                        $emp_address  = trim($app_data['house_number'] . ' ' . $app_data['street'] . ' ' . $app_data['barangay'] . ' ' . $app_data['city'] . ' ' . $app_data['province']);
                        $emp_position = $app_data['position_applied'];
                        $emp_dept     = 'General'; // Baguhin ayon sa default department ninyo

                        // I-check muna kung na-insert na siya dati para maiwasan ang duplicate
                        $check_emp = $conn->prepare("SELECT employee_id FROM employee WHERE email = ?");
                        $check_emp->bind_param("s", $emp_email);
                        $check_emp->execute();
                        $emp_exists = $check_emp->get_result()->num_rows > 0;
                        $check_emp->close();

                        if (!$emp_exists) {
                            $ins_emp = $conn->prepare("INSERT INTO employee (username, email, contact_number, address, position, department) VALUES (?, ?, ?, ?, ?, ?)");
                            $ins_emp->bind_param("ssssss", $emp_username, $emp_email, $emp_contact, $emp_address, $emp_position, $emp_dept);
                            $ins_emp->execute();
                            $ins_emp->close();
                        }
                    }
                }

                // =========================================================
                // 2. MAGPADALA NG EMAIL NOTIFICATION SA APPLICANT
                // =========================================================
                if (!empty($email)) {
                    $formatted_date = date("F j, Y - g:i A", strtotime($interview_date));
                    $subject = "Interview Schedule Notice - " . $interview_type;
                    $body = "
                        <h3>Magandang araw, {$username}!</h3>
                        <p>Thank you for applying at Ginga! We reviewed your application and we'd love to invite you for an interview:</p>
                        <ul>
                            <li><strong>Interview Type:</strong> {$interview_type}</li>
                            <li><strong>Mode:</strong> {$interview_mode}</li>
                            <li><strong>Date & Time:</strong> {$formatted_date}</li>
                            <li><strong>Status:</strong> {$status}</li>
                        </ul>
                        <p>Salamat at mag-ingat!</p>
                    ";

                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'tasktrack74@gmail.com'; 
                        $mail->Password   = 'wukj ciyu ihsm xpqt'; 
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('tasktrack74@gmail.com', 'HR Team');
                        $mail->addAddress($email, $username);
    
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;

                        $mail->send();
                    } catch (Exception $e) {
                        // Keep page running even if mail sending fails
                    }
                }

                $alert_msg = ($status === 'Passed') 
                    ? 'Schedule updated, email sent, and applicant automatically registered as Employee for Attendance!' 
                    : 'Interview schedule successfully updated and email sent!';

                // BINAGO: Kukunin ang kasalukuyang URL kung saan nanggaling ang request
                $redirect_target = $_SERVER['HTTP_REFERER'] ?? 'interview_sched.php';

                echo "<script>
                        alert('{$alert_msg}');
                        window.location.href = '{$redirect_target}';
                      </script>";
            } else {
                echo "Execution Error: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            echo "Prepare Error: " . htmlspecialchars($conn->error);
        }
    } else {
        echo "<script>alert('Please complete the interview details!');</script>";
    }
}
// -----------------------------------------------------
//                 View & Filter Logic
// -----------------------------------------------------

$selected_status = isset($_GET['interview_status']) ? $_GET['interview_status'] : 'all';

// Paghiwalayin ang filter base sa napiling dropdown status
if ($selected_status === 'all') {
    // Ipakita ang lahat ng WALA PANG interview schedule
    $sql = "SELECT * FROM applicant 
            WHERE (interview_date IS NULL OR interview_date = '0000-00-00 00:00:00' OR interview_date = '')";
} else {
    // Kapag may piniling partikular na stage sa filter dropdown
    $status_clean = mysqli_real_escape_string($conn, $selected_status);
    $sql = "SELECT * FROM applicant 
            WHERE LOWER(REPLACE(interview_type, '_', ' ')) = LOWER(REPLACE('$status_clean', '_', ' '))";
}

$sql .= " ORDER BY applicant_id ASC";

$result = mysqli_query($conn, $sql);
$count  = 0;
$records = [];

if ($result) {
    $count = mysqli_num_rows($result);
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>
