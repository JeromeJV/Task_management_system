<?php
include'config/connection.php';
session_start(); 

$msg = '';          
$email_err = '';    
$password_err = ''; 
$is_ajax_login = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_login']);

// Dto na pupunta ung auto logout pag inactive
if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $msg = "You have been logged out due to inactivity.";
}

if (isset($_POST['Submit'])) { 
    $email = trim($_POST['email'] ?? '');
    $plain_password = $_POST['password'] ?? '';

    $isValid = true;
    //Validation for email
    if (!preg_match("/^[a-zA-Z0-9@. ]*$/", $email)) {
        $email_err = "Please use only letters, numbers, and standard email characters.";
        $isValid = false;
    }

    if (mb_strlen($plain_password) < 8) {
        $password_err = "Your password must be at least 8 characters long.";
        $isValid = false;
    }

    if ($isValid) {
        $email = mysqli_real_escape_string($conn, $email);

        $select_query = "SELECT * FROM `users` WHERE email = '$email'";
        $result = mysqli_query($conn, $select_query);

        if (mysqli_num_rows($result) > 0) {
            $row1 = mysqli_fetch_assoc($result);
            $user_id = $row1['id'];
            $current_time = date('Y-m-d H:i:s');

            // i-check kung naka lockout ung user
            if ($row1['lockout_time'] !== null && strtotime($row1['lockout_time']) > strtotime($current_time)) {
                $remaining_seconds = strtotime($row1['lockout_time']) - strtotime($current_time);
                $remaining_minutes = ceil($remaining_seconds / 60);
                $msg = "Your account is temporarily locked. Please try again after $remaining_minutes minutes.";
            } 
            else {
                // Dto mag veverify kung hindi naka lockout ung acc
                
                //I-verify ung password
                if (password_verify($plain_password, $row1['password']) || $plain_password === $row1['password']) {

                    // Pag tama ung pass may rereset ung attempt's
                    mysqli_query($conn, "UPDATE `users` SET login_attempts = 0, lockout_time = NULL WHERE id = $user_id");

                    // I-save sa session at i-redirect
                    $_SESSION['id'] = $row1['id'];
                    $_SESSION['email'] = $row1['email'];
                    $_SESSION['name'] = $row1['name'];
                    $_SESSION['role'] = $row1['role']; 
                    $_SESSION['last_activity'] = time();

                    switch ($row1['role']) {
                        case 'HR': 
                            $redirect_url = 'HR.php';
                            break;
                        case 'payroll': 
                            $redirect_url = 'payroll.php';
                            break;
                        case 'pro': 
                            $redirect_url = 'production.php';
                            break;
                        case 'log': 
                            $redirect_url = 'logistic.php';
                            break;
                        case 'super': 
                            $redirect_url = 'supervisor.php';
                            break;
                        case 'employee':
                            $redirect_url = 'employee.php';
                            break;
                        default: 
                            $redirect_url = 'employee.php';
                            break;
                    }

                    if ($is_ajax_login) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => true,
                            'redirect' => $redirect_url,
                        ]);
                    } else {
                        header('Location: ' . $redirect_url);
                    }
                    exit(); 
                } 
                else {
                    // Pag mali ung pass mababawasan ung attemps
                    $new_attempts = $row1['login_attempts'] + 1;
                    
                    if ($new_attempts >= 5) {
                        // Kung umabot sa 5 maling subok, mag lo-lock sa loob ng 10 minuto
                        $lockout_until = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        mysqli_query($conn, "UPDATE `users` SET login_attempts = $new_attempts, lockout_time = '$lockout_until' WHERE id = $user_id");
                        $msg = "Too many failed attempts. Your account has been locked for 10 minutes.";
                    } else {
                        // Kung wala pa sa 5, mag u-update ung bilang ng attempts
                        mysqli_query($conn, "UPDATE `users` SET login_attempts = $new_attempts WHERE id = $user_id");
                        $remaining_attempts = 5 - $new_attempts;
                        $msg = "Incorrect password! You have $remaining_attempts remaining attempt(s).";
                    }
                }
            } 
        } else {
            $msg = "Incorrect email or password!";
        }
    }
}

if ($is_ajax_login) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'email_error' => $email_err,
        'password_error' => $password_err,
    ]);
    exit();
}
?>