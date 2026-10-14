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

        // Naglalagay ng token
        // Note: ung token is parang id na nag papatunay na ung owner ng account siya ung nag papalit ng pass
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET reset_token = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
             WHERE email = ?"
        );
        mysqli_stmt_bind_param($updateStmt, "ss", $token, $email);
        mysqli_stmt_execute($updateStmt);

        try {
            $configuredBaseUrl = getenv('TASKTRACK_BASE_URL');
            if (is_string($configuredBaseUrl) && $configuredBaseUrl !== '') {
                $baseUrl = rtrim($configuredBaseUrl, '/');
            } else {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if ($host === '') {
                    throw new RuntimeException('Set TASKTRACK_BASE_URL to the address used to open TaskTrack.');
                }
                $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/forgot-pass.php'));
                $pathSegments = array_filter(explode('/', trim($scriptDirectory, '/')), 'strlen');
                $pathSegments = array_map('rawurldecode', $pathSegments);
                $encodedPath = implode('/', array_map('rawurlencode', $pathSegments));
                $baseUrl = $scheme . '://' . $host . ($encodedPath === '' ? '' : '/' . $encodedPath);
            }
            $resetLink = $baseUrl . '/reset-pass.php?token=' . rawurlencode($token);
            $escapedResetLink = htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $htmlBody = "<h3>Forgot your password?</h3>
                              <p>click the link below to change password. you only have 30 mins.</p>
                              <p><a href='{$escapedResetLink}'>{$escapedResetLink}</a></p>";
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
