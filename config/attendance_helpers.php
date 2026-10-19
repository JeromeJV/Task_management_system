<?php
require_once __DIR__ . '/attendance_calculations.php';

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
        WHERE (e.hire_date IS NULL OR e.hire_date <= ?)
          AND NOT EXISTS (
            SELECT 1
            FROM attendance a
            WHERE a.employee_id = e.employee_id
              AND a.attendance_date = ?
        )
          AND NOT EXISTS (
            SELECT 1
            FROM employee_leave_requests l
            WHERE l.employee_id = e.employee_id
              AND l.status = 'active'
              AND l.start_date <= ?
              AND l.end_date >= ?
        )
    ");

    if (!$absentStatement) {
        throw new RuntimeException('Failed to prepare automatic absent update: ' . $conn->error);
    }

    $absentStatement->bind_param('sssss', $currentDate, $currentDate, $currentDate, $currentDate, $currentDate);
    if (!$absentStatement->execute()) {
        $error = $absentStatement->error;
        $absentStatement->close();
        throw new RuntimeException('Failed to mark absent attendance: ' . $error);
    }

    $absentRows = $absentStatement->affected_rows;
    $absentStatement->close();

    $statusQuery = $conn->prepare("
        SELECT a.attendance_id, a.user_id, a.time_in, a.time_out, a.time_in_2, a.time_out_2,
               l.leave_type,
               CASE
                   WHEN LOWER(TRIM(COALESCE(e.position, ''))) IN ('log', 'logistic', 'logistics', 'driver', 'delivery driver')
                     OR EXISTS (
                         SELECT 1 FROM users u
                         WHERE u.role = 'log'
                           AND (u.id = a.user_id OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.username)))
                     )
                   THEN 1 ELSE 0
               END AS is_logistics
        FROM attendance a
        LEFT JOIN employee e ON e.employee_id = a.employee_id
        LEFT JOIN employee_leave_requests l
          ON l.employee_id = a.employee_id
         AND l.status = 'active'
         AND l.start_date <= a.attendance_date
         AND l.end_date >= a.attendance_date
        WHERE a.attendance_date = ?
          AND a.status NOT IN ('Paid Leave', 'Unpaid Leave')
    ");
    if (!$statusQuery) {
        throw new RuntimeException('Failed to prepare daily attendance status lookup: ' . $conn->error);
    }
    $statusQuery->bind_param('s', $currentDate);
    if (!$statusQuery->execute()) {
        $error = $statusQuery->error;
        $statusQuery->close();
        throw new RuntimeException('Failed to load daily attendance statuses: ' . $error);
    }
    $statusRows = 0;
    $statusResult = $statusQuery->get_result();
    while ($row = $statusResult->fetch_assoc()) {
        $isLate = attendancePunchExists($row, 'time_in') && $row['time_in'] > '08:00:00';
        $status = $row['leave_type'] !== null
            ? ($row['leave_type'] === 'SIL' ? 'Paid Leave' : 'Unpaid Leave')
            : calculateAttendanceStatusFromPunches($row, (bool) $row['is_logistics'], $isLate);
        $updateStatus = $conn->prepare('UPDATE attendance SET status = ?, is_late = ? WHERE attendance_id = ?');
        if (!$updateStatus) {
            $statusQuery->close();
            throw new RuntimeException('Failed to prepare attendance status update: ' . $conn->error);
        }
        $attendanceId = (int) $row['attendance_id'];
        $isLateFlag = (int) $isLate;
        $updateStatus->bind_param('sii', $status, $isLateFlag, $attendanceId);
        if (!$updateStatus->execute()) {
            $error = $updateStatus->error;
            $updateStatus->close();
            $statusQuery->close();
            throw new RuntimeException('Failed to finalize attendance status: ' . $error);
        }
        $statusRows += $updateStatus->affected_rows;
        $updateStatus->close();
    }
    $statusQuery->close();

    return $absentRows + $statusRows;
}
?>
