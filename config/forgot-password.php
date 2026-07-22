<?php
include 'config/connection.php'; 

// Manu-manong i-load ang PHPMailer files mula sa folder natin
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Tinitignan dto kung nag eexist ung gmail na pag sesendan
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email); 
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Nag lalagay ng token
        // Note: ung token is parang id na nag papatunay na ung owner ng account siya ung nag papalit ng pass
        $updateStmt = mysqli_prepare($conn, "UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
        mysqli_stmt_bind_param($updateStmt, "sss", $token, $expires, $email);
        mysqli_stmt_execute($updateStmt);

        // Dto ung link papuntang rest-password.php
        $resetLink = "http://localhost/Task%20system/reset-password.php?token=" . $token; 
        
        $mail = new PHPMailer(true);

        try {
            // Configuration para sa Gmail SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'harveyjeromev@gmail.com'; // Acc na ginagamit para mag send ng Verification
            $mail->Password   = 'kuefhwhbwaulrryh';      // Pass sa Google app
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Para hindi harangin ni xammp ung pass na galing Google
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Sino ang nag-send at sino ang makakatanggap
            $mail->setFrom('harveyjeromev@gmail.com', 'Task Management System'); // Gmail ng nag send
            $mail->addAddress($email); 

            // Eto ung formal sa gmail
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "<h3>Forgot your password?</h3>
                              <p>click the link below to change password. you only have 30 mins.</p>
                              <p><a href='{$resetLink}'>{$resetLink}</a></p>";

            $mail->send();
            echo "A reset link has been sent to your email.";
            
        } catch (Exception $e) {
            echo "email not sent. Mailer Error: {$mail->ErrorInfo}";
        }

    } else {
        echo "If that email exists in our system, a reset link has been sent.";
    }
}
?>

