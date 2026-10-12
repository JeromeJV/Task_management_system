<?php
function isLogisticsEmployee(mysqli $conn, int $employeeId, ?int $userId = null): bool
{
    $statement = $conn->prepare("
        SELECT (
            EXISTS (
                SELECT 1
                FROM employee e
                WHERE e.employee_id = ?
                  AND (
                      LOWER(TRIM(COALESCE(e.position, ''))) IN ('log', 'logistic', 'logistics', 'driver', 'delivery driver')
                      OR EXISTS (
                          SELECT 1
                          FROM users u
                          WHERE u.role = 'log'
                            AND (
                                (? > 0 AND u.id = ?)
                                OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.username))
                            )
                      )
                  )
            )
            OR EXISTS (
                SELECT 1
                FROM users u
                WHERE ? > 0
                  AND u.id = ?
                  AND u.role = 'log'
            )
        ) AS is_logistics
    ");
    if (!$statement) {
        throw new RuntimeException('Failed to prepare Logistics attendance lookup: ' . $conn->error);
    }

    $resolvedUserId = $userId ?? 0;
    $statement->bind_param(
        'iiiii',
        $employeeId,
        $resolvedUserId,
        $resolvedUserId,
        $resolvedUserId,
        $resolvedUserId
    );
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Failed to identify Logistics attendance: ' . $error);
    }
    $isLogistics = (bool) ($statement->get_result()->fetch_assoc()['is_logistics'] ?? false);
    $statement->close();

    return $isLogistics;
}

function finalizeDailyAttendance(mysqli $conn, string $currentDate, string $currentTime): int
{
    if ($currentTime < '17:01:00') {
        return 0;
    }

    $absentStatement = $conn->prepare("
        INSERT INTO attendance (employee_id, username, attendance_date, status)
        SELECT
            e.employee_id,
            COALESCE(NULLIF(e.username, ''), CONCAT('Employee #', e.employee_id)),
            ?,
            'Absent'
        FROM employee e
        WHERE NOT EXISTS (
            SELECT 1
            FROM attendance a
            WHERE a.employee_id = e.employee_id
              AND a.attendance_date = ?
        )
    ");

    if (!$absentStatement) {
        throw new RuntimeException('Failed to prepare automatic absent update: ' . $conn->error);
    }

    $absentStatement->bind_param('ss', $currentDate, $currentDate);
    if (!$absentStatement->execute()) {
        $error = $absentStatement->error;
        $absentStatement->close();
        throw new RuntimeException('Failed to mark absent attendance: ' . $error);
    }

    $absentRows = $absentStatement->affected_rows;
    $absentStatement->close();

    $statusStatement = $conn->prepare("
        UPDATE attendance a
        LEFT JOIN employee e ON e.employee_id = a.employee_id
        SET a.is_late = CASE WHEN a.time_in > '08:00:00' THEN 1 ELSE 0 END,
            a.status = CASE
                WHEN a.time_in IS NULL OR a.time_in = '00:00:00' THEN 'Absent'
                WHEN (
                    LOWER(TRIM(COALESCE(e.position, ''))) IN ('log', 'logistic', 'logistics', 'driver', 'delivery driver')
                    OR EXISTS (
                        SELECT 1
                        FROM users u
                        WHERE u.role = 'log'
                          AND (
                              u.id = a.user_id
                              OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.username))
                          )
                    )
                ) THEN
                    CASE
                        WHEN a.time_out IS NULL OR a.time_out = '00:00:00' THEN 'Half Day'
                        WHEN a.time_in > '08:00:00' THEN 'Late'
                        ELSE 'Present'
                    END
                WHEN a.time_in_2 IS NULL OR a.time_in_2 = '00:00:00' THEN 'Half Day'
                WHEN a.time_in > '08:00:00' THEN 'Late'
                ELSE 'Present'
            END
        WHERE a.attendance_date = ?
    ");

    if (!$statusStatement) {
        throw new RuntimeException('Failed to prepare daily attendance status update: ' . $conn->error);
    }

    $statusStatement->bind_param('s', $currentDate);
    if (!$statusStatement->execute()) {
        $error = $statusStatement->error;
        $statusStatement->close();
        throw new RuntimeException('Failed to finalize daily attendance statuses: ' . $error);
    }

    $statusRows = $statusStatement->affected_rows;
    $statusStatement->close();

    return $absentRows + $statusRows;
}
?>
