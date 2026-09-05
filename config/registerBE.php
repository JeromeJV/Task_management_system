<?php
include("config/connection.php");

$msg = '';
$name_err = '';
$email_err = '';
$password_err = '';
$cpassword_err = '';

if (isset($_POST['submit'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    $isValid = true;

    //Mga validation
    
    // Para sa pangalan
    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $name)) {
        $name_err = "Please use only letters and spaces for your name.";
        $isValid = false;
    }

    // Para sa email
    if (!preg_match("/^[a-zA-Z0-9@. ]*$/", $email)) {
        $email_err = "Please enter a valid email format.";
        $isValid = false;
    } else {
        $safe_email = mysqli_real_escape_string($conn, $email);
        $select1 = "SELECT id FROM `users` WHERE email = '$safe_email'";
        $select_user = mysqli_query($conn, $select1);
        if (mysqli_num_rows($select_user) > 0) {
            $email_err = "This email is already registered!";
            $isValid = false;
        }
    }

    // Para sa Password
    if (mb_strlen($password) < 8) {
        $password_err = "Your password must be at least 8 characters long.";
        $isValid = false;   
    } else if (!preg_match("#[0-9]+#", $password)) {
        $password_err = "Your password must contain at least one number.";
        $isValid = false;
    }

    // Para sa Confirm pass
    if ($password !== $cpassword) {
        $cpassword_err = "Passwords do not match.";
        $isValid = false;
    }

    // KUNG LAHAT AY VALID, I-SAVE NA SA DATABASE
    if ($isValid) {
        // Safe na pag-hash ng password
       

        $safe_name     = mysqli_real_escape_string($conn, $name);
        $safe_email    = mysqli_real_escape_string($conn, $email);
        $safe_password = mysqli_real_escape_string($conn, $password);
        $safe_role     = mysqli_real_escape_string($conn, $role);

        $insert1 = "INSERT INTO `users`(`name`, `email`, `password`, `role`) VALUES ('$safe_name','$safe_email','$safe_password','$safe_role')";
        
        if (mysqli_query($conn, $insert1)) {
            header('Location: HR.php');
            exit();
        } else {
            $msg = "Something went wrong. Please try again later.";
        }
    }
}
// -----------------------------------------------------
//                       Edit
// -----------------------------------------------------

$passid = $_POST['idno'] ?? null;
$view_data = null;
$delete_message = "";

if (isset($_POST['del'])) {
    $sql    = "DELETE FROM users WHERE id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $delete_message = "Record Deleted Successfully. <br><a href='register.php'>View Records</a>";

} elseif (isset($_POST['upd'])) {
    $sql    = "SELECT * FROM users WHERE id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $row    = mysqli_fetch_assoc($result);

    if ($row) {
        $view_data = [
            'id' => $passid,
            'name'  => $row['name'],
            'email'    => $row['email'],
            'role'      => $row['role'],
            'password'  => $row['password']
        ];
    }
}

// -----------------------------------------------------
//                      Update
// -----------------------------------------------------

$status_message = "";

// Ginamitan ng 'is_update' check para sa Update form lang mag-trigger
if (isset($_POST['submit']) && isset($_POST['is_update'])) {
    $id = $_POST['id'] ?? '';
    $name  = $_POST['name'] ?? '';
    $email    = $_POST['email'] ?? '';
    $role      = $_POST['role'] ?? '';

    $safe_id = mysqli_real_escape_string($conn, $id);
    $safe_name  = mysqli_real_escape_string($conn, $name);
    $safe_email    = mysqli_real_escape_string($conn, $email);
    $safe_role      = mysqli_real_escape_string($conn, $role);



    $sql   = "UPDATE users SET name = '$safe_name', email = '$safe_email', role = '$safe_role' WHERE id = '$safe_id'";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $status_message = "<br>Update Successful<br><br><a href='register.php'><input type='button' name='back' value='View Records'></a>";
    }
} elseif (isset($_POST['can'])) {
    header("Location: register.php");
    exit();
}

// -----------------------------------------------------
//                      View
// -----------------------------------------------------
$sql    = "SELECT * FROM users ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>
