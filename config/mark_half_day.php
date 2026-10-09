<?php
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/attendance_helpers.php';

try {
    $updatedRecords = finalizeDailyAttendance($conn, date('Y-m-d'), date('H:i:s'));
    $message = sprintf(
        "[%s] Daily attendance finalization completed. Updated records: %d.%s",
        date('Y-m-d H:i:s'),
        $updatedRecords,
        PHP_EOL
    );
    $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'task-management-half-day.log';

    if (file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Could not write the half-day task log.');
    }

    echo $message;
} catch (Throwable $error) {
    $message = sprintf("[%s] Daily attendance finalization failed: %s%s", date('Y-m-d H:i:s'), $error->getMessage(), PHP_EOL);
    error_log($message);
    fwrite(STDERR, $message);
    exit(1);
}
?>
