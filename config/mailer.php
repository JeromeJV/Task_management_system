<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function sendTaskTrackEmail(string $recipientEmail, string $subject, string $htmlBody, string $textBody): void
{
    $senderEmail = getenv('TASKTRACK_SMTP_USERNAME');
    $senderPassword = getenv('TASKTRACK_SMTP_APP_PASSWORD');
    if (!is_string($senderEmail) || $senderEmail === '' || !is_string($senderPassword) || $senderPassword === '') {
        throw new RuntimeException(
            'Gmail delivery is not configured. Set TASKTRACK_SMTP_USERNAME and TASKTRACK_SMTP_APP_PASSWORD in the web server environment.'
        );
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $senderEmail;
        $mail->Password = $senderPassword;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom($senderEmail, 'TaskTrack');
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $error) {
        throw new RuntimeException('Gmail could not deliver the email. Check the SMTP settings and try again.');
    }
}

function sendTaskTrackAccountPassword(string $email, string $name, string $role, string $password): void
{
    $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedPassword = htmlspecialchars($password, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $htmlBody = '<h2>TaskTrack sign-in details for ' . $escapedName . '</h2>'
            . '<p>Use these details to sign in to your account:</p>'
            . '<p><strong>Email:</strong> ' . $escapedEmail . '<br>'
            . '<strong>Role:</strong> ' . $escapedRole . '<br>'
            . '<strong>Password:</strong> ' . $escapedPassword . '</p>'
            . '<p>Please keep this email private. You can change your password anytime in your account settings.</p>';
    $textBody = "TaskTrack sign-in details for {$name}.\n"
        . "Email: {$email}\nRole: {$role}\nPassword: {$password}\n"
        . 'Please keep this email private. You can change your password anytime in your account settings.';
    sendTaskTrackEmail($email, 'Your TaskTrack account details', $htmlBody, $textBody);
}
