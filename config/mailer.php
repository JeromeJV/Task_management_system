<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function sendTaskTrackEmail(string $recipientEmail, string $subject, string $htmlBody, string $textBody): void
{
    $senderEmail = getenv('TASKTRACK_SMTP_USERNAME');
    $senderPassword = getenv('TASKTRACK_SMTP_PASSWORD');
    if (!is_string($senderEmail) || $senderEmail === '' || !is_string($senderPassword) || $senderPassword === '') {
        throw new RuntimeException(
            'Email delivery is not configured. Set TASKTRACK_SMTP_USERNAME and TASKTRACK_SMTP_PASSWORD in the web server environment.'
        );
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('TASKTRACK_SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $senderEmail;
        $mail->Password = $senderPassword;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) (getenv('TASKTRACK_SMTP_PORT') ?: 587);
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom(getenv('TASKTRACK_SMTP_FROM') ?: $senderEmail, 'TaskTrack');
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

function sendTaskTrackPasswordSetupLink(string $email, string $name, string $role, string $token): void
{
    $configuredBaseUrl = getenv('TASKTRACK_BASE_URL');
    if (is_string($configuredBaseUrl) && $configuredBaseUrl !== '') {
        $baseUrl = rtrim($configuredBaseUrl, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            throw new RuntimeException('Set TASKTRACK_BASE_URL to the address used to open TaskTrack.');
        }
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin.php'));
        $pathSegments = array_filter(explode('/', trim($scriptDirectory, '/')), 'strlen');
        $pathSegments = array_map('rawurldecode', $pathSegments);
        $encodedPath = implode('/', array_map('rawurlencode', $pathSegments));
        $baseUrl = $scheme . '://' . $host . ($encodedPath === '' ? '' : '/' . $encodedPath);
    }
    $setupLink = $baseUrl . '/reset-pass.php?token=' . rawurlencode($token);
    $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedSetupLink = htmlspecialchars($setupLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $htmlBody = '<h2>Set up your TaskTrack account, ' . $escapedName . '</h2>'
            . '<p>Your account for ' . $escapedEmail . ' (' . $escapedRole . ') is ready. Use this one-time link to choose your password:</p>'
            . '<p><a href="' . $escapedSetupLink . '">Set your password</a></p>'
            . '<p>This link expires in 30 minutes. Request a new link from Forgot password if it expires.</p>'
            . '<p>If you did not expect this message, contact your administrator.</p>';
    $textBody = "TaskTrack account setup for {$name}.\nEmail: {$email}\nRole: {$role}\n"
        . "Set your password using this one-time link (expires in 30 minutes):\n{$setupLink}\n"
        . 'Request a new link from Forgot password if it expires.';
    sendTaskTrackEmail($email, 'Set up your TaskTrack account', $htmlBody, $textBody);
}
