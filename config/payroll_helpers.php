<?php
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
        SELECT attendance_date, status, time_in
        FROM attendance
        WHERE (employee_id = ? OR user_id = ?)
          AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date ASC, attendance_id ASC
    ");
    if (!$statement) {
        throw new RuntimeException('Failed to prepare payroll attendance query: ' . $conn->error);
    }

    $statement->bind_param('iiss', $employeeId, $employeeId, $startDate, $endDate);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Failed to read payroll attendance: ' . $error);
    }

    $result = $statement->get_result();
    $dailyAttendance = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['attendance_date'];
        $status = strtolower(trim($row['status'] ?? ''));
        $dayFraction = match ($status) {
            'present', 'late' => 1.0,
            'half day' => 0.5,
            default => 0.0
        };

        if (!isset($dailyAttendance[$date])) {
            $dailyAttendance[$date] = [
                'date' => $date,
                'days_worked' => $dayFraction,
                'time_in_after_noon' => false,
                'late_minutes' => 0,
                'raw_late_minutes' => 0
            ];
        } else {
            $dailyAttendance[$date]['days_worked'] = max($dailyAttendance[$date]['days_worked'], $dayFraction);
        }

        $timeIn = $row['time_in'] ?? '';
        if ($timeIn !== '' && $timeIn !== '00:00:00' && $timeIn > '12:00:00') {
            $dailyAttendance[$date]['time_in_after_noon'] = true;
        }

        if ($timeIn !== '' && $timeIn !== '00:00:00' && $timeIn > '08:01:00') {
            $lateSeconds = strtotime($timeIn) - strtotime('08:01:00');
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

    $daysWorked = 0.0;
    $lateMinutes = 0;
    $excessiveLateDays = [];
    foreach ($dailyAttendance as $day) {
        $daysWorked += $day['time_in_after_noon'] ? 0.5 : $day['days_worked'];
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
        'late_minutes' => $lateMinutes,
        'late_deduction' => calculateLateDeduction($lateMinutes, $dailyRate),
        'excessive_late_days' => $excessiveLateDays
    ];
}
?>
