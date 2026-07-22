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
            header('Location: login.php');
            exit();
        } else {
            $msg = "Something went wrong. Please try again later.";
        }
    }
}
?>