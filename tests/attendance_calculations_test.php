<?php
require_once __DIR__ . '/../config/attendance_calculations.php';

$passed = 0;
$failed = 0;
$assertSame = static function ($expected, $actual, string $case) use (&$passed, &$failed): void {
    if ($expected !== $actual) {
        $failed++;
        fwrite(STDERR, "FAIL: {$case}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        return;
    }
    $passed++;
};

$am = ['time_in' => '08:00:00', 'time_out' => '12:00:00'];
$pm = ['time_in_2' => '13:00:00', 'time_out_2' => '17:00:00'];
$fullDay = $am + $pm;
$incomplete = ['time_in' => '08:00:00'];

$assertSame('Half Day', calculateAttendanceStatusFromPunches($am, false, false), 'complete AM session is half day');
$assertSame('Half Day', calculateAttendanceStatusFromPunches($pm, false, false), 'complete PM session is half day');
$assertSame('Present', calculateAttendanceStatusFromPunches($fullDay, false, false), 'two complete sessions are present');
$assertSame('Late', calculateAttendanceStatusFromPunches($fullDay, false, true), 'late full day stays late');
$assertSame('Undertime', calculateAttendanceStatusFromPunches(['time_in' => '09:00:00'], false, true), 'late AM time in without completed session is not yet half day');
$assertSame('Half Day', calculateAttendanceStatusFromPunches($am, false, true), 'late AM-only attendance becomes half day after the PM session is missed');
$assertSame('Undertime', calculateAttendanceStatusFromPunches($incomplete, false, false), 'incomplete regular session is undertime');
$assertSame('Absent', calculateAttendanceStatusFromPunches([], false, false), 'no regular punches are absent');
$assertSame(0.5, calculateAttendanceWorkFraction($am, false), 'AM session earns half day');
$assertSame(0.5, calculateAttendanceWorkFraction($pm, false), 'PM session earns half day');
$assertSame(1.0, calculateAttendanceWorkFraction($fullDay, false), 'two sessions earn full day');
$assertSame(0.0, calculateAttendanceWorkFraction($incomplete, false), 'incomplete session earns no fraction');
$assertSame('time_in', getNextAttendanceActionForSession(null, false, '09:00:00'), 'AM starts with time in');
$assertSame('time_out', getNextAttendanceActionForSession(['time_in' => '08:00:00'], false, '13:00:00'), 'unfinished AM must be closed first');
$assertSame('time_in_2', getNextAttendanceActionForSession($am, false, '13:00:00'), 'PM session follows completed AM');
$assertSame(null, getNextAttendanceActionForSession($fullDay, false, '16:00:00'), 'complete attendance has no remaining action');
$assertSame('time_in', getNextAttendanceActionForSession(null, true, '13:00:00'), 'logistics keeps single shift');
$assertSame('Half Day', calculateAttendanceStatusFromPunches(['time_in' => '08:00:00'], true, false), 'logistics incomplete shift remains half day');
$assertSame('Half Day', calculateAttendanceStatusFromPunches(['time_in' => '09:00:00'], true, true), 'logistics attendance without time out becomes half day at finalization');
$assertSame(0.5, calculateAttendanceWorkFraction(['time_in' => '08:00:00'], true), 'logistics incomplete shift earns existing half fraction');
$assertSame(1.0, calculateAttendanceWorkFraction(['time_in' => '08:00:00', 'time_out' => '17:00:00'], true), 'complete logistics shift earns full day');

fwrite(STDOUT, "{$passed} passed, {$failed} failed." . PHP_EOL);
exit($failed === 0 ? 0 : 1);
