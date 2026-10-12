<?php

function ensureProductionAssignmentSchema(mysqli $conn): void
{
    $create_table_sql = "CREATE TABLE IF NOT EXISTS production_assignments (
                            production_id VARCHAR(64) NOT NULL,
                            employee_id INT NOT NULL,
                            work_type ENUM('Cooking', 'Packaging') NOT NULL,
                            work_status ENUM('Pending', 'Done') NOT NULL DEFAULT 'Pending',
                            completed_at DATETIME DEFAULT NULL,
                            assigned_by INT DEFAULT NULL,
                            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (production_id, work_type),
                            KEY idx_production_assignments_employee (employee_id)
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $create_table_sql)) {
        throw new RuntimeException('Unable to initialize production task assignments: ' . mysqli_error($conn));
    }

    $status_column = mysqli_query($conn, "SHOW COLUMNS FROM production_assignments LIKE 'work_status'");
    if (!$status_column) {
        throw new RuntimeException('Unable to inspect production assignment status: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($status_column) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE production_assignments ADD COLUMN work_status ENUM('Pending', 'Done') NOT NULL DEFAULT 'Pending' AFTER work_type")) {
            throw new RuntimeException('Unable to add production assignment status: ' . mysqli_error($conn));
        }
        if (!mysqli_query($conn, "UPDATE production_assignments pa
                                  INNER JOIN production p ON p.production_id = pa.production_id
                                  SET pa.work_status = 'Done'
                                  WHERE p.product_status = 'product done'")) {
            throw new RuntimeException('Unable to migrate completed production assignments: ' . mysqli_error($conn));
        }
    }
    mysqli_free_result($status_column);

    $completed_at_column = mysqli_query($conn, "SHOW COLUMNS FROM production_assignments LIKE 'completed_at'");
    if (!$completed_at_column) {
        throw new RuntimeException('Unable to inspect production assignment completion time: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($completed_at_column) === 0) {
        if (!mysqli_query($conn, "ALTER TABLE production_assignments ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER work_status")) {
            throw new RuntimeException('Unable to add production assignment completion time: ' . mysqli_error($conn));
        }
    }
    mysqli_free_result($completed_at_column);

    $primary_key_result = mysqli_query($conn, "SHOW INDEX FROM production_assignments WHERE Key_name = 'PRIMARY'");
    if (!$primary_key_result) {
        throw new RuntimeException('Unable to inspect production assignment key: ' . mysqli_error($conn));
    }
    $primary_key_columns = [];
    while ($index = mysqli_fetch_assoc($primary_key_result)) {
        $primary_key_columns[(int) $index['Seq_in_index']] = $index['Column_name'];
    }
    mysqli_free_result($primary_key_result);
    ksort($primary_key_columns);

    if (array_values($primary_key_columns) !== ['production_id', 'work_type']) {
        $key_change = $primary_key_columns
            ? 'DROP PRIMARY KEY, ADD PRIMARY KEY (production_id, work_type)'
            : 'ADD PRIMARY KEY (production_id, work_type)';
        if (!mysqli_query($conn, "ALTER TABLE production_assignments $key_change")) {
            throw new RuntimeException('Unable to enable separate Cooking and Packaging assignments: ' . mysqli_error($conn));
        }
    }

    function ensureProductionActivitySchema(mysqli $conn): void
    {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS production_activity (
                                activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                production_id VARCHAR(64) NOT NULL,
                                actor_user_id INT DEFAULT NULL,
                                actor_name VARCHAR(255) NOT NULL,
                                event_type VARCHAR(40) NOT NULL,
                                work_type ENUM('Cooking', 'Packaging') DEFAULT NULL,
                                from_employee_id INT DEFAULT NULL,
                                from_employee_name VARCHAR(255) DEFAULT NULL,
                                to_employee_id INT DEFAULT NULL,
                                to_employee_name VARCHAR(255) DEFAULT NULL,
                                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                PRIMARY KEY (activity_id),
                                KEY idx_production_activity_task_time (production_id, occurred_at)
                             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if (!mysqli_query($conn, $create_table_sql)) {
            throw new RuntimeException('Unable to initialize production activity history: ' . mysqli_error($conn));
        }

        $backfill_sql = "INSERT INTO production_activity
                            (production_id, actor_user_id, actor_name, event_type, work_type,
                             to_employee_id, to_employee_name, occurred_at)
                         SELECT pa.production_id,
                                pa.assigned_by,
                                COALESCE(NULLIF(u.name, ''), 'Recorded assignment'),
                                'stage_assigned',
                                pa.work_type,
                                pa.employee_id,
                                e.username,
                                pa.assigned_at
                         FROM production_assignments pa
                         LEFT JOIN users u ON u.id = pa.assigned_by
                         LEFT JOIN employee e ON e.employee_id = pa.employee_id
                         WHERE NOT EXISTS (
                             SELECT 1
                             FROM production_activity activity
                             WHERE activity.production_id = pa.production_id
                               AND activity.work_type = pa.work_type
                         )";
        if (!mysqli_query($conn, $backfill_sql)) {
            throw new RuntimeException('Unable to backfill existing production assignments into activity history: ' . mysqli_error($conn));
        }
    }

    function getProductionEmployeeName(mysqli $conn, int $employee_id): ?string
    {
        $stmt = $conn->prepare("SELECT username FROM employee WHERE employee_id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare production employee lookup: ' . $conn->error);
        }
        $stmt->bind_param('i', $employee_id);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to load production employee: ' . $error);
        }
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $employee['username'] ?? null;
    }

    function recordProductionActivity(
        mysqli $conn,
        string $production_id,
        int $actor_user_id,
        string $actor_name,
        string $event_type,
        ?string $work_type = null,
        ?int $from_employee_id = null,
        ?string $from_employee_name = null,
        ?int $to_employee_id = null,
        ?string $to_employee_name = null
    ): void {
        $stmt = $conn->prepare(
            "INSERT INTO production_activity
                (production_id, actor_user_id, actor_name, event_type, work_type,
                 from_employee_id, from_employee_name, to_employee_id, to_employee_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare production activity history: ' . $conn->error);
        }
        $stmt->bind_param(
            'sisssisis',
            $production_id,
            $actor_user_id,
            $actor_name,
            $event_type,
            $work_type,
            $from_employee_id,
            $from_employee_name,
            $to_employee_id,
            $to_employee_name
        );
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to record production activity: ' . $error);
        }
        $stmt->close();
    }
}
