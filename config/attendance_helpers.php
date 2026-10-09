<?php
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
        UPDATE attendance
        SET is_late = CASE WHEN time_in > '08:00:00' THEN 1 ELSE 0 END,
            status = CASE
                WHEN time_in IS NULL OR time_in = '00:00:00' THEN 'Absent'
                WHEN time_in_2 IS NULL OR time_in_2 = '00:00:00' THEN 'Half Day'
                WHEN time_in > '08:00:00' THEN 'Late'
                ELSE 'Present'
            END
        WHERE attendance_date = ?
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
