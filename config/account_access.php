<?php

function requireActiveAccount(mysqli $conn, bool $jsonResponse = false): void
{
    if (!isset($_SESSION['id'])) {
        return;
    }

    $userId = (int) $_SESSION['id'];
    $stmt = $conn->prepare('SELECT role, is_active FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Unable to verify account access: ' . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to verify account access: ' . $error);
    }
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        $account
        && (int) $account['is_active'] === 1
        && (string) $account['role'] === (string) ($_SESSION['role'] ?? '')
    ) {
        return;
    }

    $accountIsActive = $account && (int) $account['is_active'] === 1;
    session_unset();
    session_destroy();
    http_response_code(403);
    if ($jsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        $message = $accountIsActive
            ? 'Your account access changed. Please sign in again.'
            : 'This account has been disabled. Contact an administrator.';
        echo json_encode(['status' => 'error', 'message' => $message]);
    } else {
        header('Location: index.php?error=' . ($accountIsActive ? 'account_changed' : 'account_disabled'));
    }
    exit();
}
