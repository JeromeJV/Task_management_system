<?php
declare(strict_types=1);

function ensureLeaveSchema(mysqli $conn): void
{
    $hireDateColumn = $conn->query("SHOW COLUMNS FROM employee LIKE 'hire_date'");
    if (!$hireDateColumn) {
        throw new RuntimeException('Unable to inspect employee hire-date field: ' . $conn->error);
    }
    if ($hireDateColumn->num_rows === 0 && !$conn->query('ALTER TABLE employee ADD COLUMN hire_date DATE DEFAULT NULL AFTER email')) {
        $hireDateColumn->free();
        throw new RuntimeException('Unable to add employee hire-date field: ' . $conn->error);
    }
    $hireDateColumn->free();

    if (!$conn->query(
        "CREATE TABLE IF NOT EXISTS employee_leave_requests (
            leave_id INT NOT NULL AUTO_INCREMENT,
            employee_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            leave_type ENUM('SIL', 'LWOP') NOT NULL,
            day_fraction DECIMAL(2,1) NOT NULL DEFAULT 1.0,
            reason VARCHAR(500) NOT NULL,
            status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
            recorded_by INT NOT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_by INT DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            PRIMARY KEY (leave_id),
            KEY idx_employee_leave_dates (employee_id, status, start_date, end_date),
            KEY idx_employee_leave_recorder (recorded_by, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    )) {
        throw new RuntimeException('Unable to initialize employee leave records: ' . $conn->error);
    }

    $statusColumn = $conn->query("SHOW COLUMNS FROM attendance LIKE 'status'");
    if (!$statusColumn) {
        throw new RuntimeException('Unable to inspect attendance status field: ' . $conn->error);
    }
    $status = $statusColumn->fetch_assoc();
    $statusColumn->free();
    $statusType = (string) ($status['Type'] ?? '');
    if (!str_contains($statusType, "'Paid Leave'") || !str_contains($statusType, "'Unpaid Leave'")) {
        if (!$conn->query(
            "ALTER TABLE attendance
             MODIFY status ENUM('Present','Early','Late','Overtime','Undertime','Absent','Half Day','Paid Leave','Unpaid Leave')
             DEFAULT 'Absent'"
        )) {
            throw new RuntimeException('Unable to add leave attendance statuses: ' . $conn->error);
        }
    }
}

function employeeHasLeaveOnDate(mysqli $conn, int $employeeId, string $date): bool
{
    $statement = $conn->prepare(
        "SELECT leave_id
         FROM employee_leave_requests
         WHERE employee_id = ? AND status = 'active'
           AND start_date <= ? AND end_date >= ?
         LIMIT 1"
    );
    if (!$statement) {
        throw new RuntimeException('Unable to prepare employee leave lookup: ' . $conn->error);
    }
    $statement->bind_param('iss', $employeeId, $date, $date);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to check employee leave: ' . $error);
    }
    $hasLeave = $statement->get_result()->num_rows > 0;
    $statement->close();

    return $hasLeave;
}

function userHasLeaveOnDate(mysqli $conn, int $userId, string $date): bool
{
    $statement = $conn->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM users u
            INNER JOIN employee e
              ON e.employee_id = u.employee_id
              OR (u.employee_id IS NULL AND (
                  LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
                  OR LOWER(TRIM(e.username)) = LOWER(TRIM(u.name))
                  OR EXISTS (
                      SELECT 1 FROM attendance a
                      WHERE a.employee_id = e.employee_id AND a.user_id = u.id
                  )
              ))
            INNER JOIN employee_leave_requests l
              ON l.employee_id = e.employee_id
             AND l.status = 'active'
             AND l.start_date <= ?
             AND l.end_date >= ?
            WHERE u.id = ?
        ) AS has_leave"
    );
    if (!$statement) {
        throw new RuntimeException('Unable to prepare account leave check: ' . $conn->error);
    }
    $statement->bind_param('ssi', $date, $date, $userId);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to check account leave: ' . $error);
    }
    $hasLeave = (bool) ($statement->get_result()->fetch_assoc()['has_leave'] ?? false);
    $statement->close();

    return $hasLeave;
}

function countLeaveWorkdays(string $startDate, string $endDate): int
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    if (!$start || !$end || $end < $start) {
        throw new InvalidArgumentException('Invalid leave date range.');
    }

    $days = 0;
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        if ((int) $date->format('N') <= 5) {
            $days++;
        }
    }

    return $days;
}

function getServiceYearBounds(string $hireDate, string $date): ?array
{
    $hire = DateTimeImmutable::createFromFormat('!Y-m-d', $hireDate);
    $target = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$hire || !$target || $target < $hire->modify('+1 year')) {
        return null;
    }
    $serviceYears = $hire->diff($target)->y;
    $start = $hire->modify('+' . $serviceYears . ' years');

    return [
        $start->format('Y-m-d'),
        $start->modify('+1 year -1 day')->format('Y-m-d')
    ];
}

function getUsedSILDays(mysqli $conn, int $employeeId, string $serviceStart, string $serviceEnd): float
{
    $statement = $conn->prepare(
        "SELECT start_date, end_date
         FROM employee_leave_requests
         WHERE employee_id = ? AND leave_type = 'SIL' AND status = 'active'
           AND start_date <= ? AND end_date >= ?"
    );
    if (!$statement) {
        throw new RuntimeException('Unable to prepare SIL balance lookup: ' . $conn->error);
    }
    $statement->bind_param('iss', $employeeId, $serviceEnd, $serviceStart);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to read SIL balance: ' . $error);
    }
    $usedDays = 0.0;
    $result = $statement->get_result();
    while ($row = $result->fetch_assoc()) {
        $rangeStart = max($serviceStart, (string) $row['start_date']);
        $rangeEnd = min($serviceEnd, (string) $row['end_date']);
        $usedDays += countLeaveWorkdays($rangeStart, $rangeEnd);
    }
    $statement->close();

    return $usedDays;
}
