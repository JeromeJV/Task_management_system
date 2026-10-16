<?php

function attendancePunchExists(array $punches, string $field): bool
{
    $value = $punches[$field] ?? null;
    return $value !== null && $value !== '' && $value !== '00:00:00';
}

function attendanceSessionIsComplete(array $punches, string $timeInField, string $timeOutField): bool
{
    return attendancePunchExists($punches, $timeInField)
        && attendancePunchExists($punches, $timeOutField);
}

function calculateAttendanceStatusFromPunches(array $punches, bool $isLogistics, bool $isLate): string
{
    if ($isLogistics) {
        if (!attendancePunchExists($punches, 'time_in')) {
            return 'Absent';
        }
        if (!attendancePunchExists($punches, 'time_out')) {
            return 'Half Day';
        }
        return $isLate ? 'Late' : 'Present';
    }

    $completeSessions = (int) attendanceSessionIsComplete($punches, 'time_in', 'time_out')
        + (int) attendanceSessionIsComplete($punches, 'time_in_2', 'time_out_2');
    $hasAnyPunch = attendancePunchExists($punches, 'time_in')
        || attendancePunchExists($punches, 'time_out')
        || attendancePunchExists($punches, 'time_in_2')
        || attendancePunchExists($punches, 'time_out_2');

    if (!$hasAnyPunch) {
        return 'Absent';
    }
    if ($completeSessions === 2) {
        return $isLate ? 'Late' : 'Present';
    }
    if ($completeSessions === 1) {
        return 'Half Day';
    }
    return 'Undertime';
}

function calculateAttendanceWorkFraction(array $punches, bool $isLogistics): float
{
    $hasTimeIn = attendancePunchExists($punches, 'time_in');
    $hasTimeOut = attendancePunchExists($punches, 'time_out');
    if ($isLogistics) {
        return $hasTimeIn ? ($hasTimeOut ? 1.0 : 0.5) : 0.0;
    }

    if ($hasTimeIn && (string) $punches['time_in'] > '12:00:00') {
        return $hasTimeOut ? 0.5 : 0.0;
    }

    return (attendanceSessionIsComplete($punches, 'time_in', 'time_out') ? 0.5 : 0.0)
        + (attendanceSessionIsComplete($punches, 'time_in_2', 'time_out_2') ? 0.5 : 0.0);
}

function getNextAttendanceActionForSession(?array $attendance, bool $isLogistics, string $currentTime): ?string
{
    if ($isLogistics) {
        $actions = ['time_in', 'time_out'];
    } elseif ($currentTime < '12:00:00') {
        $actions = ['time_in', 'time_out'];
    } elseif (
        attendancePunchExists($attendance ?? [], 'time_in')
        && !attendancePunchExists($attendance ?? [], 'time_out')
    ) {
        return 'time_out';
    } else {
        $actions = ['time_in_2', 'time_out_2'];
    }

    foreach ($actions as $action) {
        if (!attendancePunchExists($attendance ?? [], $action)) {
            return $action;
        }
    }
    return null;
}
