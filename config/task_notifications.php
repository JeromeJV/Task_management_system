<?php
declare(strict_types=1);

function ensureTaskNotificationSchema(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS task_notifications (
                notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                actor_user_id INT DEFAULT NULL,
                notification_type VARCHAR(40) NOT NULL,
                title VARCHAR(180) NOT NULL,
                message VARCHAR(500) NOT NULL,
                target_url VARCHAR(500) NOT NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id VARCHAR(80) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME DEFAULT NULL,
                PRIMARY KEY (notification_id),
                KEY idx_task_notifications_user_unread (user_id, is_read, created_at),
                KEY idx_task_notifications_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize task notifications: ' . $conn->error);
    }
}

function createTaskNotification(
    mysqli $conn,
    int $userId,
    ?int $actorUserId,
    string $type,
    string $title,
    string $message,
    string $targetUrl,
    string $entityType,
    string $entityId
): void {
    if ($userId < 1 || ($actorUserId !== null && $actorUserId === $userId)) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO task_notifications
            (user_id, actor_user_id, notification_type, title, message, target_url, entity_type, entity_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare task notification: ' . $conn->error);
    }
    $stmt->bind_param(
        'iissssss',
        $userId,
        $actorUserId,
        $type,
        $title,
        $message,
        $targetUrl,
        $entityType,
        $entityId
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to create task notification: ' . $error);
    }
    $stmt->close();
}

function getTaskNotificationUserIdsForEmployee(mysqli $conn, int $employeeId): array
{
    if ($employeeId < 1) {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT DISTINCT u.id
         FROM users u
        WHERE u.employee_id = ?
           OR (
            u.employee_id IS NULL
            AND (
                EXISTS (
                    SELECT 1
                    FROM employee e
                    WHERE e.employee_id = ?
                      AND e.email IS NOT NULL AND e.email <> ''
                      AND LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
                )
                OR EXISTS (
                    SELECT 1 FROM attendance a
                    WHERE a.employee_id = ? AND a.user_id = u.id
                )
            )
        )"
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare employee notification recipient lookup: ' . $conn->error);
    }
    $stmt->bind_param('iii', $employeeId, $employeeId, $employeeId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to find the employee notification recipient: ' . $error);
    }
    $result = $stmt->get_result();
    $userIds = [];
    while ($row = $result->fetch_assoc()) {
        $userIds[] = (int) $row['id'];
    }
    $stmt->close();

    return $userIds;
}

function notifyEmployeeTaskAssignee(
    mysqli $conn,
    int $employeeId,
    ?int $actorUserId,
    string $type,
    string $title,
    string $message,
    string $targetUrl,
    string $entityType,
    string $entityId
): void {
    foreach (getTaskNotificationUserIdsForEmployee($conn, $employeeId) as $userId) {
        createTaskNotification(
            $conn,
            $userId,
            $actorUserId,
            $type,
            $title,
            $message,
            $targetUrl,
            $entityType,
            $entityId
        );
    }
}

function emailEmployeeTaskAssignees(
    mysqli $conn,
    int $employeeId,
    string $subject,
    string $message
): void {
    require_once __DIR__ . '/mailer.php';
    $userIds = getTaskNotificationUserIdsForEmployee($conn, $employeeId);
    if ($userIds === []) {
        throw new RuntimeException('No linked employee account is available for task email delivery.');
    }
    $deliveryFailed = false;
    foreach ($userIds as $userId) {
        try {
            sendTaskTrackUserEmail($conn, $userId, $subject, $message);
        } catch (RuntimeException $exception) {
            error_log('Task email delivery failed for linked employee account ' . $userId . ': ' . $exception->getMessage());
            $deliveryFailed = true;
        }
    }
    if ($deliveryFailed) {
        throw new RuntimeException('One or more linked employee accounts could not receive the task email.');
    }
}
