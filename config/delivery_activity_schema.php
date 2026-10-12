<?php

function ensureDeliveryActivitySchema(mysqli $conn): void
{
    $delivered_at_column = mysqli_query($conn, "SHOW COLUMNS FROM delivery LIKE 'delivered_at'");
    if (!$delivered_at_column) {
        throw new RuntimeException('Unable to inspect delivery completion time: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($delivered_at_column) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE delivery ADD COLUMN delivered_at DATETIME DEFAULT NULL AFTER status")) {
            throw new RuntimeException('Unable to add delivery completion time: ' . mysqli_error($conn));
        }
    }
    mysqli_free_result($delivered_at_column);

    $create_table_sql = "CREATE TABLE IF NOT EXISTS delivery_activity (
                            activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            delivery_id INT NOT NULL,
                            actor_user_id INT DEFAULT NULL,
                            actor_name VARCHAR(255) NOT NULL,
                            event_type VARCHAR(40) NOT NULL,
                            from_driver_id INT DEFAULT NULL,
                            from_driver_name VARCHAR(255) DEFAULT NULL,
                            to_driver_id INT DEFAULT NULL,
                            to_driver_name VARCHAR(255) DEFAULT NULL,
                            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (activity_id),
                            KEY idx_delivery_activity_task_time (delivery_id, occurred_at)
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $create_table_sql)) {
        throw new RuntimeException('Unable to initialize delivery activity history: ' . mysqli_error($conn));
    }
}

function getDeliveryDriverName(mysqli $conn, int $driver_id): ?string
{
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'log' LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare delivery driver lookup: ' . $conn->error);
    }
    $stmt->bind_param('i', $driver_id);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to load delivery driver: ' . $error);
    }
    $driver = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $driver['name'] ?? null;
}

function recordDeliveryActivity(
    mysqli $conn,
    int $delivery_id,
    ?int $actor_user_id,
    string $actor_name,
    string $event_type,
    ?int $from_driver_id = null,
    ?string $from_driver_name = null,
    ?int $to_driver_id = null,
    ?string $to_driver_name = null
): void {
    $stmt = $conn->prepare(
        "INSERT INTO delivery_activity
            (delivery_id, actor_user_id, actor_name, event_type,
             from_driver_id, from_driver_name, to_driver_id, to_driver_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare delivery activity history: ' . $conn->error);
    }
    $stmt->bind_param(
        'iissisis',
        $delivery_id,
        $actor_user_id,
        $actor_name,
        $event_type,
        $from_driver_id,
        $from_driver_name,
        $to_driver_id,
        $to_driver_name
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to record delivery activity: ' . $error);
    }
    $stmt->close();
}
