<?php
require_once __DIR__ . '/connection.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$errors = [];
$success = false;
$tokenIsValid = false;
$userId = 0;

if (!is_string($token) || $token === '') {
    $errors[] = 'Invalid request. The reset link is missing its token.';
} else {
    $lookup = $conn->prepare(
        'SELECT id
         FROM users
         WHERE reset_token = ?
           AND reset_expires_at > NOW()
         LIMIT 1'
    );
    if (!$lookup) {
        throw new RuntimeException('Unable to prepare password reset token lookup: ' . $conn->error);
    }
    $lookup->bind_param('s', $token);
    if (!$lookup->execute()) {
        $error = $lookup->error;
        $lookup->close();
        throw new RuntimeException('Unable to verify password reset token: ' . $error);
    }
    $user = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    if ($user) {
        $tokenIsValid = true;
        $userId = (int) $user['id'];
    } else {
        $errors[] = 'This link is invalid or has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenIsValid) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($newPassword) || strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!is_string($confirmPassword) || $newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            throw new RuntimeException('Unable to securely hash the new password.');
        }

        $update = $conn->prepare(
            'UPDATE users
             SET password = ?, reset_token = NULL, reset_expires_at = NULL,
                 login_attempts = 0, lockout_time = NULL
             WHERE id = ?
               AND reset_token = ?
               AND reset_expires_at > NOW()'
        );
        if (!$update) {
            throw new RuntimeException('Unable to prepare password reset update: ' . $conn->error);
        }
        $update->bind_param('sis', $hashedPassword, $userId, $token);
        if (!$update->execute()) {
            $error = $update->error;
            $update->close();
            throw new RuntimeException('Unable to update password: ' . $error);
        }
        $success = $update->affected_rows === 1;
        $update->close();

        if (!$success) {
            $tokenIsValid = false;
            $errors[] = 'This link is invalid or has expired.';
        }
    }
}
?>