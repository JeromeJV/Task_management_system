<?php
declare(strict_types=1);

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/account_access.php';
require_once __DIR__ . '/task_notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function notificationRespond(array $payload, int $status = 200): void
{
    $unexpectedOutput = ob_get_contents();
    if (is_string($unexpectedOutput) && trim($unexpectedOutput) !== '') {
        error_log('Notifications API discarded unexpected output to preserve its JSON response.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

$userId = (int) ($_SESSION['id'] ?? 0);
if ($userId < 1 || !isset($_SESSION['role'])) {
    notificationRespond(['error' => 'Sign in to view notifications.'], 401);
}
requireActiveAccount($conn, true);
ensureTaskNotificationSchema($conn);

$action = (string) ($_REQUEST['action'] ?? 'list');
if ($action === 'list' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $conn->prepare(
        'SELECT notification_id, notification_type, title, message, target_url, is_read, created_at
         FROM task_notifications
         WHERE user_id = ?
         ORDER BY created_at DESC, notification_id DESC
         LIMIT 12'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare notification list: ' . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to load notifications: ' . $error);
    }
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();

    $countStmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM task_notifications WHERE user_id = ? AND is_read = 0');
    if (!$countStmt) {
        throw new RuntimeException('Unable to prepare unread notification count: ' . $conn->error);
    }
    $countStmt->bind_param('i', $userId);
    if (!$countStmt->execute()) {
        $error = $countStmt->error;
        $countStmt->close();
        throw new RuntimeException('Unable to load unread notification count: ' . $error);
    }
    $unreadCount = (int) ($countStmt->get_result()->fetch_assoc()['unread_count'] ?? 0);
    $countStmt->close();
    notificationRespond(['notifications' => $notifications, 'unreadCount' => $unreadCount]);
}

if ($action === 'read' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['notification_csrf']) || !hash_equals((string) $_SESSION['notification_csrf'], $postedToken)) {
        notificationRespond(['error' => 'Your session token expired. Refresh the page and try again.'], 403);
    }
    $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$notificationId || $notificationId < 1) {
        notificationRespond(['error' => 'A valid notification is required.'], 400);
    }
    $stmt = $conn->prepare(
        'UPDATE task_notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP
         WHERE notification_id = ? AND user_id = ? AND is_read = 0'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare notification update: ' . $conn->error);
    }
    $stmt->bind_param('ii', $notificationId, $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to mark notification as read: ' . $error);
    }
    $stmt->close();
    notificationRespond(['success' => true]);
}

if ($action === 'read_all' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['notification_csrf']) || !hash_equals((string) $_SESSION['notification_csrf'], $postedToken)) {
        notificationRespond(['error' => 'Your session token expired. Refresh the page and try again.'], 403);
    }
    $stmt = $conn->prepare(
        'UPDATE task_notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP
         WHERE user_id = ? AND is_read = 0'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare notification update: ' . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to mark notifications as read: ' . $error);
    }
    $stmt->close();
    notificationRespond(['success' => true]);
}

notificationRespond(['error' => 'Unsupported notification action.'], 400);
