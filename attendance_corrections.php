<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';
require_once __DIR__ . '/config/attendance_helpers.php';
require_once __DIR__ . '/config/leave_helpers.php';
require_once __DIR__ . '/config/system_audit.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'HR') {
    header('Location: index.php');
    exit();
}

requireActiveAccount($conn, true);
ensureLeaveSchema($conn);

if (empty($_SESSION['attendance_correction_csrf'])) {
    $_SESSION['attendance_correction_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';
$selectedDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
$dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$dateValue || $dateValue->format('Y-m-d') !== $selectedDate
    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
    $selectedDate = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDate = trim((string) ($_POST['filter_date'] ?? $selectedDate));
    $postDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
    $postDateErrors = DateTimeImmutable::getLastErrors();
    $attendanceId = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $token = (string) ($_POST['csrf_token'] ?? '');
    $columns = ['time_in', 'time_out', 'time_in_2', 'time_out_2'];
    $times = [];
    $errors = [];

    if (!$postDate || $postDate->format('Y-m-d') !== $selectedDate
        || ($postDateErrors !== false && ($postDateErrors['warning_count'] > 0 || $postDateErrors['error_count'] > 0))) {
        $errors[] = 'Select a valid attendance date.';
        $selectedDate = date('Y-m-d');
    }
    if (!hash_equals((string) $_SESSION['attendance_correction_csrf'], $token)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if (!$attendanceId || $attendanceId < 1) {
        $errors[] = 'Select a valid attendance record.';
    }
    if ($reason === '' || strlen($reason) > 500) {
        $errors[] = 'Enter a correction reason of up to 500 characters.';
    }

    foreach ($columns as $column) {
        $value = trim((string) ($_POST[$column] ?? ''));
        if ($value === '') {
            $times[$column] = null;
            continue;
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            $errors[] = 'Enter valid times in 24-hour format.';
            break;
        }
        $times[$column] = strlen($value) === 5 ? $value . ':00' : $value;
    }

    $orderedTimes = array_values(array_filter($times, static fn ($time): bool => $time !== null));
    for ($index = 1, $count = count($orderedTimes); $index < $count; $index++) {
        if ($orderedTimes[$index] <= $orderedTimes[$index - 1]) {
            $errors[] = 'Attendance punches must be in chronological order, with each time-out after its time-in.';
            break;
        }
    }

    if ($errors) {
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    } else {
        $conn->begin_transaction();
        try {
            $lookup = $conn->prepare(
                'SELECT a.attendance_id, a.employee_id, a.user_id, a.attendance_date, a.username,
                        a.time_in, a.time_out, a.time_in_2, a.time_out_2, a.status,
                        e.position, e.username AS employee_username
                 FROM attendance a
                 LEFT JOIN employee e ON e.employee_id = a.employee_id
                 WHERE a.attendance_id = ?
                 FOR UPDATE'
            );
            if (!$lookup) {
                throw new RuntimeException('Unable to prepare attendance correction lookup: ' . $conn->error);
            }
            $lookup->bind_param('i', $attendanceId);
            if (!$lookup->execute()) {
                throw new RuntimeException('Unable to load attendance record: ' . $lookup->error);
            }
            $record = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!$record) {
                throw new RuntimeException('The selected attendance record no longer exists.');
            }
            if (in_array($record['status'], ['Paid Leave', 'Unpaid Leave'], true)
                || employeeHasLeaveOnDate($conn, (int) $record['employee_id'], (string) $record['attendance_date'])) {
                throw new RuntimeException('Attendance punches cannot be corrected while the employee is on recorded leave.');
            }

            $isLogistics = isLogisticsEmployee(
                $conn,
                (int) $record['employee_id'],
                isset($record['user_id']) ? (int) $record['user_id'] : null
            );
            $isLate = attendancePunchExists($times, 'time_in') && $times['time_in'] > '08:00:00';
            $status = calculateAttendanceStatusFromPunches($times, $isLogistics, $isLate);
            $update = $conn->prepare(
                'UPDATE attendance
                 SET time_in = ?, time_out = ?, time_in_2 = ?, time_out_2 = ?, status = ?, is_late = ?
                 WHERE attendance_id = ?'
            );
            if (!$update) {
                throw new RuntimeException('Unable to prepare attendance correction: ' . $conn->error);
            }
            $lateFlag = (int) $isLate;
            $update->bind_param(
                'sssssii',
                $times['time_in'],
                $times['time_out'],
                $times['time_in_2'],
                $times['time_out_2'],
                $status,
                $lateFlag,
                $attendanceId
            );
            if (!$update->execute()) {
                throw new RuntimeException('Unable to save attendance correction: ' . $update->error);
            }
            $update->close();

            $changes = [];
            foreach ($columns as $column) {
                $changes[$column] = [
                    'old' => $record[$column],
                    'new' => $times[$column]
                ];
            }
            $changes['status'] = ['old' => $record['status'], 'new' => $status];
            $changes['reason'] = $reason;
            recordSystemAudit(
                $conn,
                isset($_SESSION['id']) || isset($_SESSION['user_id'])
                    ? (int) ($_SESSION['id'] ?? $_SESSION['user_id'])
                    : null,
                (string) ($_SESSION['name'] ?? $_SESSION['email']),
                'attendance_corrected',
                'attendance',
                (int) $attendanceId,
                json_encode($changes, JSON_THROW_ON_ERROR)
            );
            $conn->commit();
            $message = 'Attendance record corrected and added to audit history.';
            $_SESSION['attendance_correction_csrf'] = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Attendance correction failed: ' . $exception->getMessage());
            $message = $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$records = $conn->prepare(
    'SELECT a.attendance_id, a.employee_id, a.attendance_date, a.username, a.time_in, a.time_out,
            a.time_in_2, a.time_out_2, a.status, e.username AS employee_username
     FROM attendance a
     LEFT JOIN employee e ON e.employee_id = a.employee_id
     WHERE a.attendance_date = ?
     ORDER BY a.username, a.attendance_id DESC
     LIMIT 200'
);
if (!$records) {
    throw new RuntimeException('Unable to prepare attendance correction records: ' . $conn->error);
}
$records->bind_param('s', $selectedDate);
if (!$records->execute()) {
    throw new RuntimeException('Unable to load attendance correction records: ' . $records->error);
}
$attendanceRecords = $records->get_result()->fetch_all(MYSQLI_ASSOC);
$records->close();

$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Corrections | TaskTrack</title>
    <link rel="stylesheet" href="css/role-form-controls.css">
    <style>
        body { margin: 0; padding: 24px; background: #f4f7fb; color: #172235; font: 15px Arial, sans-serif; }
        main { max-width: 1100px; margin: auto; }
        .topline, .record { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .topline { margin-bottom: 20px; }
        .record { padding: 18px 0; border-top: 1px solid #e1e7ef; align-items: flex-start; }
        .card { background: #fff; border: 1px solid #e1e7ef; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .punches { display: grid; grid-template-columns: repeat(4, minmax(130px, 1fr)); gap: 12px; }
        label { display: grid; gap: 6px; font-weight: 600; }
        input, button, a { font: inherit; }
        input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .meta { color: #526174; margin-top: 5px; }
        .reason { margin-top: 12px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .button { display: inline-block; border: 0; border-radius: 8px; padding: 10px 14px; background: #155eef; color: white; text-decoration: none; cursor: pointer; }
        .button.secondary { background: #e8eef7; color: #203047; }
        .notice { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .notice.success { background: #e7f8ef; color: #146c43; }
        .notice.error { background: #fff0f0; color: #a61b1b; }
        @media (max-width: 760px) { .record { display: block; } .punches { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }
    </style>
</head>
<body>
<main>
    <div class="topline">
        <div>
            <h1>Attendance Corrections</h1>
            <p>Correct recorded punches with a reason. Every change is saved in the system audit history.</p>
        </div>
        <a class="button secondary" href="atten.php">Back to Attendance</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?= $escape($messageType); ?>" role="<?= $messageType === 'error' ? 'alert' : 'status'; ?>"><?= $escape($message); ?></div>
    <?php endif; ?>

    <form class="card" method="get">
        <label for="date">Attendance date
            <input id="date" name="date" type="date" value="<?= $escape($selectedDate); ?>">
        </label>
        <div class="actions"><button class="button" type="submit">Load records</button></div>
    </form>

    <section class="card" aria-labelledby="records-heading">
        <h2 id="records-heading">Records for <?= $escape(date('F j, Y', strtotime($selectedDate))); ?></h2>
        <?php if (!$attendanceRecords): ?>
            <p>No attendance records found for this date.</p>
        <?php endif; ?>
        <?php foreach ($attendanceRecords as $record): ?>
            <?php $employeeName = $record['employee_username'] ?: ($record['username'] ?: 'Employee #' . $record['employee_id']); ?>
            <form class="record" method="post">
                <div>
                    <strong><?= $escape($employeeName); ?></strong>
                    <div class="meta">Record #<?= (int) $record['attendance_id']; ?> · <?= $escape($record['status']); ?></div>
                </div>
                <div>
                    <div class="punches">
                        <?php foreach (['time_in' => 'AM Time In', 'time_out' => 'AM Time Out', 'time_in_2' => 'PM Time In', 'time_out_2' => 'PM Time Out'] as $field => $label): ?>
                            <label for="<?= $field . '-' . (int) $record['attendance_id']; ?>"><?= $escape($label); ?>
                                <input id="<?= $field . '-' . (int) $record['attendance_id']; ?>" name="<?= $field; ?>" type="time" step="60" value="<?= $escape(substr((string) ($record[$field] ?? ''), 0, 5)); ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="reason" for="reason-<?= (int) $record['attendance_id']; ?>">Correction reason
                        <input id="reason-<?= (int) $record['attendance_id']; ?>" name="reason" type="text" maxlength="500" required>
                    </label>
                    <input type="hidden" name="attendance_id" value="<?= (int) $record['attendance_id']; ?>">
                    <input type="hidden" name="filter_date" value="<?= $escape($selectedDate); ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['attendance_correction_csrf']); ?>">
                    <div class="actions"><button class="button" type="submit">Save correction</button></div>
                </div>
            </form>
        <?php endforeach; ?>
    </section>
</main>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>
