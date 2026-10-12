<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/account_access.php';
require_once __DIR__ . '/task_notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$userId = (int) ($_SESSION['id'] ?? 0);
if ($userId < 1 || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in to view notifications.']);
    exit();
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
    echo json_encode(['notifications' => $notifications, 'unreadCount' => $unreadCount]);
    exit();
}

if ($action === 'read' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['notification_csrf']) || !hash_equals((string) $_SESSION['notification_csrf'], $postedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Your session token expired. Refresh the page and try again.']);
        exit();
    }
    $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$notificationId || $notificationId < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'A valid notification is required.']);
        exit();
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
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'read_all' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['notification_csrf']) || !hash_equals((string) $_SESSION['notification_csrf'], $postedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Your session token expired. Refresh the page and try again.']);
        exit();
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
    echo json_encode(['success' => true]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Unsupported notification action.']);
