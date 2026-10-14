<?php
$host = getenv('TASKTRACK_DB_HOST') ?: 'localhost';
$user = getenv('TASKTRACK_DB_USER') ?: 'root';
$password = getenv('TASKTRACK_DB_PASSWORD');
$database = getenv('TASKTRACK_DB_NAME') ?: 'task_management_system';
$password = $password === false ? '' : $password;
$port = getenv('TASKTRACK_DB_PORT');
$port = $port === false || $port === '' ? 3306 : (int) $port;

$conn = mysqli_init();
if (!$conn) {
    throw new RuntimeException('Unable to initialize the TaskTrack database connection.');
}
if (!@$conn->real_connect($host, $user, $password, $database, $port)) {
    error_log('TaskTrack database connection failed: ' . $conn->connect_error);
    throw new RuntimeException('Unable to connect to the TaskTrack database. Check the server configuration and database logs.');
}
if (!$conn->set_charset('utf8mb4')) {
    error_log('TaskTrack database charset initialization failed: ' . $conn->error);
    throw new RuntimeException('Unable to configure the TaskTrack database connection.');
}
?>