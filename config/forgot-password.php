<?php
include 'config/connection.php'; 
require_once __DIR__ . '/mailer.php';

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
        $resetLink = "http://localhost/task%20management%20system/reset-pass.php?token=" . $token; 
        
        try {
            $htmlBody = "<h3>Forgot your password?</h3>
                              <p>click the link below to change password. you only have 30 mins.</p>
                              <p><a href='{$resetLink}'>{$resetLink}</a></p>";
            sendTaskTrackEmail(
                $email,
                'Password Reset Request',
                $htmlBody,
                "Click this link to reset your password within 30 minutes:\n{$resetLink}"
            );
            echo "A reset link has been sent to your email.";
            
        } catch (RuntimeException $mailError) {
            echo "Email not sent. " . htmlspecialchars($mailError->getMessage(), ENT_QUOTES, 'UTF-8');
        }

    } else {
        echo "If that email exists in our system, a reset link has been sent.";
    }
}
?>
