<?php
// -----------------------------------------------------
//                      Insert Applicant
// -----------------------------------------------------
require_once __DIR__ . '/connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config/PHPMailer/Exception.php';
require_once 'config/PHPMailer/PHPMailer.php';
require_once 'config/PHPMailer/SMTP.php';

$sendApplicantStatusEmail = static function (string $email, string $applicantName, string $subject, string $message): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('Applicant status email not sent: invalid or missing email address.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tasktrack74@gmail.com';
        $mail->Password   = 'wukj ciyu ihsm xpqt';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('tasktrack74@gmail.com', 'TaskTrack HR Team');
        $mail->addAddress($email, $applicantName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $safeName = htmlspecialchars($applicantName, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $mail->Body = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background-color:#f1f5f2;font-family:Arial,Helvetica,sans-serif;color:#1f2a24;">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f1f5f2;padding:32px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e3ebe5;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="padding:24px 32px;background-color:#1f6e4a;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td align="center" width="38" height="38" style="width:38px;height:38px;border-radius:10px;background-color:#ffffff;color:#1f6e4a;font-size:20px;font-weight:bold;">T</td>'
            . '<td style="padding-left:12px;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:1px;">TASKTRACK'
            . '<div style="padding-top:3px;color:#dcefe4;font-size:11px;font-weight:normal;letter-spacing:1.5px;">TALENT MANAGEMENT</div></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:36px 32px 28px;">'
            . '<p style="margin:0 0 10px;color:#1f6e4a;font-size:12px;font-weight:bold;letter-spacing:1.4px;">APPLICATION UPDATE</p>'
            . '<h1 style="margin:0 0 20px;color:#1f2a24;font-size:24px;line-height:1.3;">Application status update</h1>'
            . '<p style="margin:0 0 16px;color:#34443a;font-size:15px;line-height:1.7;">Magandang araw, <strong>' . $safeName . '</strong>.</p>'
            . '<div style="margin:0;padding:18px 20px;border-left:4px solid #1f6e4a;border-radius:6px;background-color:#f3f8f4;color:#34443a;font-size:15px;line-height:1.75;">'
            . $safeMessage
            . '</div>'
            . '<p style="margin:24px 0 0;color:#34443a;font-size:15px;line-height:1.7;">Salamat,<br><strong>TaskTrack HR Team</strong></p>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 32px;border-top:1px solid #e8eee9;background-color:#fafcfb;color:#758278;font-size:12px;line-height:1.6;">'
            . 'This is an automatic notification about your application. If you have questions, please contact the HR team.'
            . '</td></tr></table>'
            . '<p style="margin:16px 0 0;color:#87938a;font-size:11px;">&copy; TaskTrack</p>'
            . '</td></tr></table></body></html>';
        $mail->AltBody = "APPLICATION UPDATE\n\nMagandang araw, {$applicantName}.\n\n{$message}\n\nSalamat,\nTaskTrack HR Team\n\nThis is an automatic notification about your application.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Unable to send applicant status email to ' . $email . ': ' . $e->getMessage());
        return false;
    }
};

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

if (isset($_POST['reject_applicant']) && !empty($_POST['reject_applicant_id'])) {
    $applicant_id = filter_var($_POST['reject_applicant_id'], FILTER_VALIDATE_INT);
    if ($applicant_id === false || $applicant_id === null) {
        $delete_message = "Invalid applicant selected.";
    } else {
        $applicant_stmt = $conn->prepare("SELECT firstname, middlename, lastname, email, status FROM applicant WHERE applicant_id = ?");
        if (!$applicant_stmt) {
            throw new RuntimeException('Unable to load applicant for rejection: ' . $conn->error);
        }
        $applicant_stmt->bind_param("i", $applicant_id);
        if (!$applicant_stmt->execute()) {
            $error = $applicant_stmt->error;
            $applicant_stmt->close();
            throw new RuntimeException('Unable to load applicant for rejection: ' . $error);
        }
        $rejected_applicant = $applicant_stmt->get_result()->fetch_assoc();
        $applicant_stmt->close();

        if (!$rejected_applicant) {
            $delete_message = "No applicant was updated; the record may not exist.";
        } elseif (strtolower(trim((string)($rejected_applicant['status'] ?? ''))) === 'failed') {
            $delete_message = "This applicant is already marked as Failed.";
        } else {
            $stmt = $conn->prepare("UPDATE applicant SET status = 'Failed' WHERE applicant_id = ?");
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare applicant rejection: ' . $conn->error);
            }
            $stmt->bind_param("i", $applicant_id);
            if (!$stmt->execute()) {
                $delete_message = "Unable to reject application: " . $stmt->error;
            } elseif ($stmt->affected_rows > 0) {
                $applicant_name = trim(implode(' ', array_filter([
                    $rejected_applicant['firstname'] ?? '',
                    $rejected_applicant['middlename'] ?? '',
                    $rejected_applicant['lastname'] ?? ''
                ])));
                $email_sent = $sendApplicantStatusEmail(
                    trim((string)($rejected_applicant['email'] ?? '')),
                    $applicant_name,
                    'Application status update',
                    'Your application status has been updated to Failed. Thank you for your interest in joining our team.'
                );
                $delete_message = "Application rejected and marked as Failed."
                    . ($email_sent ? " An email update was sent to the applicant." : " The status was saved, but the email update could not be sent; check the mail settings and server log.");
            } else {
                $delete_message = "No applicant was updated; the record may not exist or may already be marked as Failed.";
            }
            $stmt->close();
        }
    }

} elseif (isset($_POST['del']) && !empty($passid)) {
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
    $status         = $_POST['status'] ?? ''; // e.g., 'Hired', 'Passed', 'Pending'
    $interview_date = str_replace('T', ' ', trim($_POST['interview_date'] ?? ''));
    if (strtolower(trim($interview_type)) === 'hired') {
        $status = 'Hired';
    }
    $normalized_status = strtolower(trim($status));
    $normalized_type = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $interview_type))));
    if ($normalized_type === 'technical interview') {
        $normalized_type = 'training';
        $interview_type = 'Training';
    }
    $transfer_to_employee = $normalized_status === 'hired';
    $is_hiring = $normalized_type === 'hired' || $normalized_status === 'hired';
    $progression_message = '';
    $applicant_email_message = '';
    
    $username       = $_POST['username'] ?? 'Applicant';
    $email          = $_POST['email'] ?? '';

    $is_application_list = !empty($application_list_mode);
    $is_interview_schedule = !empty($interview_schedule_mode);
    $fail_schedule = static function ($error) use ($is_application_list, $is_interview_schedule) {
        if ($is_application_list) {
            $_SESSION['applicant_error'] = $error;
            header("Location: application_form.php");
            exit();
        }
        if ($is_interview_schedule) {
            $_SESSION['interview_schedule_error'] = $error;
            header("Location: interview_sched.php");
            exit();
        }
        echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        exit();
    };

    if (!empty($applicant_id) && !empty($interview_date)) {
        $current_stmt = $conn->prepare("SELECT interview_type, status, firstname, middlename, lastname, email FROM applicant WHERE applicant_id = ?");
        if (!$current_stmt) {
            $fail_schedule("Unable to check the applicant's interview progress: " . $conn->error);
        }
        $current_stmt->bind_param("i", $applicant_id);
        if (!$current_stmt->execute()) {
            $error = $current_stmt->error;
            $current_stmt->close();
            $fail_schedule("Unable to check the applicant's interview progress: " . $error);
        }
        $current_applicant = $current_stmt->get_result()->fetch_assoc();
        $current_stmt->close();
        if (!$current_applicant) {
            $fail_schedule("Applicant was not found.");
        }
        if (strtolower(trim((string)($current_applicant['status'] ?? ''))) === 'failed') {
            $fail_schedule("This applicant is marked as Failed and cannot be moved to Passed or another interview stage.");
        }
        $username = trim(implode(' ', array_filter([
            $current_applicant['firstname'] ?? '',
            $current_applicant['middlename'] ?? '',
            $current_applicant['lastname'] ?? ''
        ]))) ?: 'Applicant';
        $email = trim((string)($current_applicant['email'] ?? ''));

        $current_type = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)($current_applicant['interview_type'] ?? '')))));
        if ($current_type === 'technical interview') {
            $current_type = 'training';
        }
        $current_status = strtolower(trim((string)($current_applicant['status'] ?? '')));
        $current_stage_passed = $current_status === 'passed';
        $is_same_stage = $normalized_type === $current_type;
        $is_initial_pass = $current_type === ''
            && $normalized_type === 'initial interview'
            && $normalized_status === 'passed';
        $can_schedule_training = $current_type === 'initial interview' && $current_stage_passed;
        $can_schedule_final = $current_type === 'training' && $current_stage_passed;
        $can_hire = $current_type === 'final interview' && $current_stage_passed;

        if ($normalized_type === 'training' && !$is_same_stage && !$can_schedule_training) {
            $fail_schedule("The applicant must pass the Initial Interview before scheduling Training.");
        }
        if ($normalized_type === 'final interview' && !$is_same_stage && !$can_schedule_final) {
            $fail_schedule("The applicant must pass Training before scheduling a Final Interview.");
        }
        if ($normalized_type === 'initial interview' && !$is_same_stage && $current_type !== '') {
            $fail_schedule("An applicant cannot return to Initial Interview after progressing to a later stage.");
        }
        if ($is_hiring && (!$can_hire || !in_array($normalized_type, ['hired', 'final interview'], true))) {
            $fail_schedule("The applicant must pass the Final Interview before being marked as Hired.");
        }
        if (!$is_same_stage && !$is_hiring && !$is_initial_pass && !in_array($normalized_status, ['scheduled', 'pending'], true)) {
            $fail_schedule("A new interview stage must start with Scheduled or Pending status.");
        }

        if ($normalized_status === 'passed' && ($is_same_stage || $is_initial_pass)) {
            $stage_to_advance = $current_type !== '' ? $current_type : $normalized_type;
            if ($stage_to_advance === 'initial interview') {
                $progression_message = 'Initial Interview passed. Applicant moved to Training and is ready to schedule.';
                $applicant_email_message = 'Congratulations! You passed the Initial Interview. Your next step is Training. The HR team will contact you to arrange the training schedule.';
            } elseif ($stage_to_advance === 'training') {
                $progression_message = 'Training passed. Applicant moved to Final Interview and is ready to schedule.';
                $applicant_email_message = 'Congratulations! You passed Training. Your next stage is the Final Interview. The HR team will contact you to arrange the schedule.';
            } elseif ($stage_to_advance === 'final interview') {
                $interview_type = 'Hired';
                $status = 'Hired';
                $normalized_status = 'hired';
                $transfer_to_employee = true;
                $progression_message = 'Final Interview passed. Applicant was hired and transferred to the employee directory.';
                $applicant_email_message = 'Congratulations! You passed the Final Interview and have been hired. The HR team will contact you with onboarding details.';
            }
        }

        if ($transfer_to_employee && !$conn->begin_transaction()) {
            $fail_schedule("Unable to start the employee transfer: " . $conn->error);
        }

        $stmt = $conn->prepare("UPDATE applicant SET 
            interview_type = ?, 
            interview_mode = ?, 
            status = ?, 
            interview_date = ? 
            WHERE applicant_id = ?");

        if (!$stmt) {
            if ($transfer_to_employee) {
                $conn->rollback();
            }
            $fail_schedule("Unable to prepare interview schedule: " . $conn->error);
        }

        $stmt->bind_param("ssssi", $interview_type, $interview_mode, $status, $interview_date, $applicant_id);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            if ($transfer_to_employee) {
                $conn->rollback();
            }
            $fail_schedule("Unable to save interview schedule: " . $error);
        }
        $stmt->close();

        if ($transfer_to_employee) {
            $fetch_stmt = $conn->prepare("SELECT * FROM applicant WHERE applicant_id = ?");
            if (!$fetch_stmt) {
                $conn->rollback();
                $fail_schedule("Unable to load applicant for employee transfer: " . $conn->error);
            }
            $fetch_stmt->bind_param("i", $applicant_id);
            if (!$fetch_stmt->execute()) {
                $error = $fetch_stmt->error;
                $fetch_stmt->close();
                $conn->rollback();
                $fail_schedule("Unable to load applicant for employee transfer: " . $error);
            }
            $app_data = $fetch_stmt->get_result()->fetch_assoc();
            $fetch_stmt->close();

            if (!$app_data) {
                $conn->rollback();
                $fail_schedule("Applicant was not found; employee transfer was cancelled.");
            }

            $full_name = trim(($app_data['firstname'] ?? '') . ' ' . ($app_data['lastname'] ?? ''));
            $app_email = trim((string)($app_data['email'] ?? ''));
            $app_contact = $app_data['contact_number'] ?? '';
            $app_position = !empty($app_data['position_applied']) ? $app_data['position_applied'] : ($app_data['position'] ?? 'New Hire');
            $full_address = trim(($app_data['house_number'] ?? '') . ' ' . ($app_data['street'] ?? '') . ' ' . ($app_data['barangay'] ?? '') . ' ' . ($app_data['city'] ?? ''));
            $department = trim((string)($app_data['department'] ?? '')) ?: 'General';

            $emp_stmt = $conn->prepare("INSERT INTO employee (username, department, position, contact_number, address, email)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                department = VALUES(department),
                position = VALUES(position),
                contact_number = VALUES(contact_number),
                address = VALUES(address)");
            if (!$emp_stmt) {
                $conn->rollback();
                $fail_schedule("Unable to prepare employee transfer: " . $conn->error);
            }
            $emp_stmt->bind_param("ssssss", $full_name, $department, $app_position, $app_contact, $full_address, $app_email);
            if (!$emp_stmt->execute()) {
                $error = $emp_stmt->error;
                $emp_stmt->close();
                $conn->rollback();
                $fail_schedule("Unable to transfer applicant to employees: " . $error);
            }
            $emp_stmt->close();

            if (!$conn->commit()) {
                $error = $conn->error;
                $conn->rollback();
                $fail_schedule("Unable to complete employee transfer: " . $error);
            }
        }

        $status_changed = strtolower(trim((string)($current_applicant['status'] ?? ''))) !== $normalized_status;
        $type_changed = $current_type !== $normalized_type;
        $email_message = $applicant_email_message !== '' ? $applicant_email_message : $progression_message;
        if ($email_message === '' && $normalized_status === 'hired') {
            $email_message = 'Congratulations! Your application status has been updated to Hired.';
        } elseif ($email_message === '' && $normalized_status === 'failed') {
            $email_message = 'Your application status has been updated to Failed. Thank you for your interest in joining our team.';
        } elseif ($email_message === '' && $normalized_status === 'passed') {
            $email_message = 'You have passed the ' . $interview_type . '. The HR team will contact you about the next step.';
        } elseif ($email_message === '' && $interview_date !== null && $interview_date !== '') {
            $formatted_date = date("F j, Y - g:i A", strtotime($interview_date));
            $email_message = "Your {$interview_type} status is {$status}. Interview date and time: {$formatted_date}. Mode: {$interview_mode}.";
        } elseif ($email_message === '') {
            $email_message = "Your application status is now {$status}.";
        }

        $notification_sent = null;
        if ($progression_message !== '' || $status_changed || $type_changed) {
            $notification_sent = $sendApplicantStatusEmail(
                $email,
                $username,
                $normalized_status === 'hired' ? 'Congratulations - Application update' : 'Application status update',
                $email_message
            );
        }

        if ($progression_message !== '') {
            $_SESSION['interview_schedule_message'] = $progression_message
                . ($notification_sent ? ' An email update was sent to the applicant.' : ' The status was saved, but the email update could not be sent; check the mail settings and server log.');
            header("Location: interview_sched.php");
            exit();
        }
        if (!empty($application_list_mode)) {
            $_SESSION['applicant_message'] = $progression_message !== ''
                ? $progression_message
                : ($normalized_status === 'hired'
                ? "Applicant hired and transferred to the employee directory."
                : ($transfer_to_employee
                    ? "Applicant transferred to the employee directory."
                    : "Interview schedule saved successfully."));
            if ($notification_sent !== null) {
                $_SESSION['applicant_message'] .= $notification_sent
                    ? ' An email update was sent to the applicant.'
                    : ' The status was saved, but the email update could not be sent; check the mail settings and server log.';
            }
            header("Location: application_form.php");
            exit();
        }
        if (!empty($interview_schedule_mode)) {
            $_SESSION['interview_schedule_message'] = $progression_message !== ''
                ? $progression_message
                : ($normalized_status === 'hired'
                ? "Applicant hired and transferred to the employee directory."
                : ($transfer_to_employee
                    ? "Applicant transferred to the employee directory."
                    : "Interview schedule saved successfully."));
            if ($notification_sent !== null) {
                $_SESSION['interview_schedule_message'] .= $notification_sent
                    ? ' An email update was sent to the applicant.'
                    : ' The status was saved, but the email update could not be sent; check the mail settings and server log.';
            }
            header("Location: interview_sched.php");
            exit();
        }
        echo "<script>alert('Interview schedule updated! If status is Hired/Passed, applicant was transferred to Employee records.'); window.location.href = 'interview_sched.php';</script>";
        exit();
    } else {
        $fail_schedule("Please complete the interview details.");
    }
}

// -----------------------------------------------------
//                 View & Filter Logic
// -----------------------------------------------------

$selected_status = isset($_GET['interview_status']) ? $_GET['interview_status'] : 'all';
if (in_array($selected_status, ['Technical_Interview', 'Technical Interview'], true)) {
    $selected_status = 'Training';
}

if (!empty($application_list_mode)) {
    $sql = "SELECT * FROM applicant
            WHERE (interview_date IS NULL
                   OR interview_date = ''
                   OR interview_date = '0000-00-00 00:00:00')
              AND LOWER(REPLACE(COALESCE(interview_type, ''), '_', ' ')) NOT IN ('technical interview', 'training', 'final interview')
              AND NOT (LOWER(REPLACE(COALESCE(interview_type, ''), '_', ' ')) = 'initial interview'
                       AND LOWER(TRIM(COALESCE(status, ''))) = 'passed')
              AND COALESCE(LOWER(TRIM(status)), '') != 'hired'
              AND COALESCE(LOWER(TRIM(interview_type)), '') != 'hired'";
} elseif (!empty($interview_schedule_mode)) {
    $sql = "SELECT * FROM applicant
            WHERE (
                    (interview_date IS NOT NULL
                     AND interview_date != '0000-00-00 00:00:00'
                     AND interview_date != '')
                    OR (
                        (interview_date IS NULL
                         OR interview_date = '0000-00-00 00:00:00'
                         OR interview_date = '')
                        AND (
                            (LOWER(REPLACE(interview_type, '_', ' ')) IN ('technical interview', 'training', 'final interview')
                             AND LOWER(TRIM(status)) = 'pending')
                            OR (LOWER(REPLACE(interview_type, '_', ' ')) = 'initial interview'
                                AND LOWER(TRIM(status)) = 'passed')
                            OR (LOWER(REPLACE(interview_type, '_', ' ')) IN ('technical interview', 'training')
                                AND LOWER(TRIM(status)) = 'passed')
                        )
                    )
              )
              AND COALESCE(LOWER(TRIM(status)), '') != 'hired'
              AND COALESCE(LOWER(TRIM(interview_type)), '') != 'hired'";
    if ($selected_status !== 'all') {
        $status_clean = mysqli_real_escape_string($conn, str_replace('_', ' ', $selected_status));
        $sql .= " AND (
                    LOWER(REPLACE(interview_type, '_', ' ')) = LOWER('$status_clean')
                    OR (LOWER('$status_clean') = 'training'
                        AND LOWER(REPLACE(interview_type, '_', ' ')) = 'technical interview')
                    OR (LOWER('$status_clean') = 'training'
                        AND LOWER(REPLACE(interview_type, '_', ' ')) = 'initial interview'
                        AND LOWER(TRIM(status)) = 'passed')
                    OR (LOWER('$status_clean') = 'final interview'
                        AND LOWER(REPLACE(interview_type, '_', ' ')) = 'technical interview'
                        AND LOWER(TRIM(status)) = 'passed')
                    OR (LOWER('$status_clean') = 'final interview'
                        AND LOWER(REPLACE(interview_type, '_', ' ')) = 'training'
                        AND LOWER(TRIM(status)) = 'passed')
                  )";
    }
} elseif ($selected_status === 'all') {
    $sql = "SELECT * FROM applicant 
            WHERE (interview_date IS NULL OR interview_date = '0000-00-00 00:00:00' OR interview_date = '')";
} else {
    $status_clean = mysqli_real_escape_string($conn, $selected_status);
    $sql = "SELECT * FROM applicant 
            WHERE LOWER(REPLACE(interview_type, '_', ' ')) = LOWER(REPLACE('$status_clean', '_', ' '))";
}

$sql .= (!empty($application_list_mode) || !empty($interview_schedule_mode))
    ? " ORDER BY interview_date ASC, applicant_id DESC"
    : " ORDER BY applicant_id ASC";

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