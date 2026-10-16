<?php
require_once __DIR__ . '/attendance_calculations.php';

function getPayrollPeriodDates(string $payPeriod, string $payDate): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $payDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        throw new InvalidArgumentException('Invalid payroll date.');
    }

    $isFirstHalf = strpos($payPeriod, '1st') !== false || strpos($payPeriod, 'Kinsenas') !== false;
    $startDate = $isFirstHalf
        ? $date->modify('first day of this month')
        : $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 16);
    $endDate = $isFirstHalf ? $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 15) : $date->modify('last day of this month');

    return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
}

function calculateLateDeduction(int $lateMinutes, float $dailyRate): float
{
    if ($lateMinutes <= 0) {
        return 0.0;
    }

    $standardDailyRate = 755.00;
    $tableAmounts = [
        1 => 1.57,
        5 => 7.86,
        15 => 23.60,
        30 => 47.19,
        45 => 70.79,
        60 => 94.38
    ];

    if (round($dailyRate, 2) === $standardDailyRate && isset($tableAmounts[$lateMinutes])) {
        return $tableAmounts[$lateMinutes];
    }

    $minuteRate = round($dailyRate / 8 / 60, 3);
    return round($lateMinutes * $minuteRate, 2);
}

function getPayrollAttendanceSummary(mysqli $conn, int $employeeId, string $startDate, string $endDate, float $dailyRate): array
{
    if ($dailyRate < 0 || !is_finite($dailyRate)) {
        throw new InvalidArgumentException('Daily rate must be a valid non-negative amount.');
    }

    $statement = $conn->prepare("
        SELECT a.attendance_date,
               a.status,
               a.time_in,
               a.time_out,
               a.time_in_2,
               a.time_out_2,
               CASE
                   WHEN LOWER(TRIM(COALESCE(e.position, ''))) IN ('log', 'logistic', 'logistics', 'driver', 'delivery driver')
                     OR EXISTS (
                         SELECT 1
                         FROM users u
                         WHERE u.role = 'log'
                           AND (
                               u.id = a.user_id
                               OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.username))
                           )
                     )
                   THEN 1 ELSE 0
               END AS is_logistics
        FROM attendance a
        LEFT JOIN employee e ON e.employee_id = a.employee_id
        WHERE a.employee_id = ?
          AND a.attendance_date BETWEEN ? AND ?
        ORDER BY a.attendance_date ASC, a.attendance_id ASC
    ");
    if (!$statement) {
        throw new RuntimeException('Failed to prepare payroll attendance query: ' . $conn->error);
    }

    $statement->bind_param('iss', $employeeId, $startDate, $endDate);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Failed to read payroll attendance: ' . $error);
    }

    $result = $statement->get_result();
    $dailyAttendance = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['attendance_date'];
        $isLogistics = (bool) ($row['is_logistics'] ?? false);
        $timeIn = $row['time_in'] ?? '';
        $hasTimeIn = attendancePunchExists($row, 'time_in');
        $dayFraction = calculateAttendanceWorkFraction($row, $isLogistics);

        if (!isset($dailyAttendance[$date])) {
            $dailyAttendance[$date] = [
                'date' => $date,
                'days_worked' => $dayFraction,
                'late_minutes' => 0,
                'raw_late_minutes' => 0
            ];
        } else {
            $dailyAttendance[$date]['days_worked'] = max($dailyAttendance[$date]['days_worked'], $dayFraction);
        }

        if ($hasTimeIn && $timeIn > '08:00:00') {
            $lateSeconds = strtotime($timeIn) - strtotime('08:00:00');
            $rawLateMinutes = intdiv($lateSeconds, 60);
            $dailyAttendance[$date]['raw_late_minutes'] = max($dailyAttendance[$date]['raw_late_minutes'], $rawLateMinutes);

            if ($timeIn <= '12:00:00') {
                $dailyAttendance[$date]['late_minutes'] = max(
                    $dailyAttendance[$date]['late_minutes'],
                    $rawLateMinutes
                );
            }
        }
    }
    $statement->close();

    $leaveStatement = $conn->prepare(
        "SELECT start_date, end_date, leave_type
         FROM employee_leave_requests
         WHERE employee_id = ? AND status = 'active'
           AND start_date <= ? AND end_date >= ?"
    );
    if (!$leaveStatement) {
        throw new RuntimeException('Failed to prepare payroll leave query: ' . $conn->error);
    }
    $leaveStatement->bind_param('iss', $employeeId, $endDate, $startDate);
    if (!$leaveStatement->execute()) {
        $error = $leaveStatement->error;
        $leaveStatement->close();
        throw new RuntimeException('Failed to read payroll leave records: ' . $error);
    }
    $leaveResult = $leaveStatement->get_result();
    while ($leave = $leaveResult->fetch_assoc()) {
        $leaveStart = max($startDate, (string) $leave['start_date']);
        $leaveEnd = min($endDate, (string) $leave['end_date']);
        for (
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $leaveStart);
            $date && $date->format('Y-m-d') <= $leaveEnd;
            $date = $date->modify('+1 day')
        ) {
            if ((int) $date->format('N') > 5) {
                continue;
            }
            $key = $date->format('Y-m-d');
            if (!isset($dailyAttendance[$key])) {
                $dailyAttendance[$key] = [
                    'date' => $key,
                    'days_worked' => 0.0,
                    'late_minutes' => 0,
                    'raw_late_minutes' => 0,
                    'paid_leave_days' => 0.0,
                    'unpaid_leave_days' => 0.0
                ];
            }
            $leaveKey = $leave['leave_type'] === 'SIL' ? 'paid_leave_days' : 'unpaid_leave_days';
            $dailyAttendance[$key][$leaveKey] = min(
                1.0,
                (float) ($dailyAttendance[$key][$leaveKey] ?? 0.0) + 1.0
            );
        }
    }
    $leaveStatement->close();

    $daysWorked = 0.0;
    $paidLeaveDays = 0.0;
    $unpaidLeaveDays = 0.0;
    $lateMinutes = 0;
    $excessiveLateDays = [];
    foreach ($dailyAttendance as $day) {
        $workFraction = $day['days_worked'];
        $unpaidLeave = min(1.0, (float) ($day['unpaid_leave_days'] ?? 0.0));
        $paidLeave = min(1.0 - $unpaidLeave, (float) ($day['paid_leave_days'] ?? 0.0));
        $daysWorked += min($workFraction, max(0.0, 1.0 - $unpaidLeave - $paidLeave));
        $paidLeaveDays += $paidLeave;
        $unpaidLeaveDays += $unpaidLeave;
        $lateMinutes += $day['late_minutes'];
        if ($day['raw_late_minutes'] > 240) {
            $excessiveLateDays[] = [
                'date' => $day['date'] ?? '',
                'late_minutes' => $day['raw_late_minutes']
            ];
        }
    }

    return [
        'days_worked' => $daysWorked,
        'paid_leave_days' => $paidLeaveDays,
        'unpaid_leave_days' => $unpaidLeaveDays,
        'paid_leaves' => round($paidLeaveDays * $dailyRate, 2),
        'late_minutes' => $lateMinutes,
        'late_deduction' => calculateLateDeduction($lateMinutes, $dailyRate),
        'excessive_late_days' => $excessiveLateDays
    ];
}
?>
