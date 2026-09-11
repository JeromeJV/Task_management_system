<?php
// -----------------------------------------------------
//                         Insert
// -----------------------------------------------------
include('config/connection.php');

$message = "";

$lastname_err = '';
$firstname_err = '';
$middlename_err = '';
$contact_number_err = '';
$email_err = '';
$facebook_err = '';

$region_err = '';
$province_err = '';
$city_err = '';
$barangay_err = '';
$house_number_err = '';
$street_err = '';

$position_applied_err = '';
$company_name_err = '';
$position_err = '';
$year_of_start_err = '';
$date_of_start_err = '';
$date_of_end_err = '';

$education_err = '';
$start_date_err = '';
$resume_err = '';

if (isset($_POST['submit'])) {
    $lastname       = $_POST['lastname'] ?? '';
    $firstname      = $_POST['firstname'] ?? '';
    $middlename     = $_POST['middlename'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $email          = $_POST['email'] ?? '';
    $facebook       = $_POST['facebook'] ?? '';

    $region       = $_POST['region'] ?? '';
    $house_number = $_POST['house_number'] ?? '';
    $street       = $_POST['street'] ?? '';
    $barangay     = $_POST['barangay'] ?? '';
    $city         = $_POST['city'] ?? '';
    $province     = $_POST['province'] ?? '';

    $position_applied = $_POST['position_applied'] ?? '';
    $company_name     = $_POST['company_name'] ?? '';
    $position         = $_POST['position'] ?? '';
    $date_of_start    = $_POST['date_of_start'] ?? '';
    $date_of_end      = $_POST['date_of_end'] ?? '';

    $education  = $_POST['education'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $resume     = $_POST['resume'] ?? '';

    $isValid = true;

    // Validation Checks
    if (!preg_match("/^[a-zA-Z ]*$/", $lastname)) {
        $lastname_err = "Please use only letters and spaces for your lastname.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z ]*$/", $firstname)) {
        $firstname_err = "Please use only letters and spaces for your firstname.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z ]*$/", $middlename)) {
        $middlename_err = "Please use only letters and spaces for your middlename.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $contact_number)) {  
        $contact_number_err = "Please use only numbers for your Contact Number.";
        $isValid = false;
    }
    
    if (mb_strlen($contact_number) < 11) {
        $contact_number_err = "Your contact number must be at least 11 characters long.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z0-9@. ]*$/", $email)) {
        $email_err = "Please use only letters, numbers, and standard email characters.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $house_number)) {
        $house_number_err = "Please use only numbers for your House Number.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $street)) {
        $street_err = "Please use only letters and spaces for your street.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $barangay)) {
        $barangay_err = "Please use only letters and spaces for your barangay.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $city)) {
        $city_err = "Please use only letters and spaces for your city.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z ]*$/", $position_applied)) {
        $position_applied_err = "Please use only letters and spaces for your Position Applied.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z ]*$/", $company_name)) {
        $company_name_err = "Please use only letters and spaces for your Company Name.";
        $isValid = false;
    }

    if (!preg_match("/^[a-zA-Z ]*$/", $position)) {
        $position_err = "Please use only letters and spaces for your Position.";
        $isValid = false;
    }

    // File Upload Handling
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
            $new_resume_name = time() . '_' . $fileName;
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
        $resume_err = "Please upload your resume image or document.";
        $isValid = false;
    }

    // Database Insertion gamit ang Prepared Statement
    if ($isValid) {
        $stmt = $conn->prepare("INSERT INTO applicant (
            lastname, 
            firstname, 
            middlename, 
            contact_number, 
            facebook, 
            email, 
            house_number, 
            street, 
            barangay, 
            city, 
            province, 
            position_applied, 
            company_name,
            position,
            date_of_start, 
            date_of_end, 
            education, 
            start_date, 
            resume_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param(
                "sssssssssssssssssss", 
                $lastname, 
                $firstname, 
                $middlename, 
                $contact_number, 
                $facebook, 
                $email, 
                $house_number, 
                $street, 
                $barangay, 
                $city, 
                $province, 
                $position_applied, 
                $company_name, 
                $position, 
                $date_of_start, 
                $date_of_end, 
                $education, 
                $start_date, 
                $new_resume_name
            );

            if ($stmt->execute()) {
                $message = "Your Application was successfully sent.";
            } else {
                echo "Execution Error: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Prepare Error: " . $conn->error;
        }
    }
}

// -----------------------------------------------------
//                         Edit
// -----------------------------------------------------

$passid = $_POST['idno'] ?? null;
$view_data = null;
$delete_message = "";

if (isset($_POST['del'])) {
    $sql    = "DELETE FROM applicant WHERE applicant_id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $delete_message = "Record Deleted Successfully. <br><a href='application_form.php'>View Records</a>";

} elseif (isset($_POST['upd'])) {
    $sql    = "SELECT * FROM applicant WHERE applicant_id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $row    = mysqli_fetch_assoc($result);

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

// -----------------------------------------------------
//                        Update
// -----------------------------------------------------

$status_message = "";

if (isset($_POST['update_submit'])) {
    $applicant_id     = $_POST['applicant_id'] ?? '';
    $lastname         = $_POST['lastname'] ?? '';
    $firstname        = $_POST['firstname'] ?? '';
    $middlename       = $_POST['middlename'] ?? '';
    $contact_number   = $_POST['contact_number'] ?? '';
    $email            = $_POST['email'] ?? '';
    $house_number     = $_POST['house_number'] ?? '';
    $street           = $_POST['street'] ?? '';
    $barangay         = $_POST['barangay'] ?? '';
    $city             = $_POST['city'] ?? '';
    $position_applied = $_POST['position_applied'] ?? '';
    $position         = $_POST['position'] ?? '';
    $company_name     = $_POST['company_name'] ?? '';
    $resume_path      = $_POST['resume_path'] ?? '';

    $sql = "UPDATE applicant SET 
        lastname = '$lastname', 
        firstname = '$firstname', 
        middlename = '$middlename', 
        contact_number = '$contact_number', 
        email = '$email', 
        house_number = '$house_number', 
        street = '$street', 
        barangay = '$barangay', 
        city = '$city', 
        position_applied = '$position_applied', 
        position = '$position', 
        company_name = '$company_name', 
        resume_path = '$resume_path' 
        WHERE applicant_id = '$applicant_id'";
    
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $status_message = "<br>Update Successful<br><br><a href='application_form.php'><input type='button' name='back' value='View Records'></a>";
    }
}

// -----------------------------------------------------
//                         View
// -----------------------------------------------------

$sql    = "SELECT * FROM applicant ORDER BY applicant_id ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>