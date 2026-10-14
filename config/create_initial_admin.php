<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/connection.php';

$environmentKeys = [
    'TASKTRACK_INITIAL_ADMIN_NAME',
    'TASKTRACK_INITIAL_ADMIN_EMAIL',
    'TASKTRACK_INITIAL_ADMIN_PASSWORD',
];
$values = [];
foreach ($environmentKeys as $key) {
    $value = getenv($key);
    if ($value === false) {
        fwrite(STDERR, "Missing required environment variable: {$key}\n");
        exit(1);
    }
    $values[$key] = $value;
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}

$name = trim($values['TASKTRACK_INITIAL_ADMIN_NAME']);
$email = trim($values['TASKTRACK_INITIAL_ADMIN_EMAIL']);
$password = $values['TASKTRACK_INITIAL_ADMIN_PASSWORD'];
unset($values);

if ($name === '' || mb_strlen($name) > 255) {
    fwrite(STDERR, "Administrator name must contain 1 to 255 characters.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    fwrite(STDERR, "Enter a valid administrator email address.\n");
    exit(1);
}
if (mb_strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
    fwrite(STDERR, "Administrator password must be at least 8 characters and include a number.\n");
    exit(1);
}

$accountResult = $conn->query('SELECT COUNT(*) AS account_count FROM users');
if (!$accountResult) {
    throw new RuntimeException('Unable to verify the initial account setup: ' . $conn->error);
}
$accountCount = (int) ($accountResult->fetch_assoc()['account_count'] ?? 0);
$accountResult->free();
if ($accountCount !== 0) {
    fwrite(STDERR, "Initial administrator setup is allowed only when the users table is empty.\n");
    exit(1);
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
if ($hashedPassword === false) {
    throw new RuntimeException('Unable to hash the initial administrator password.');
}
$stmt = $conn->prepare(
    "INSERT INTO users (name, email, employee_id, password, role)
     VALUES (?, ?, NULL, ?, 'admin')"
);
if (!$stmt) {
    throw new RuntimeException('Unable to prepare initial administrator creation: ' . $conn->error);
}
$stmt->bind_param('sss', $name, $email, $hashedPassword);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    throw new RuntimeException('Unable to create the initial administrator: ' . $error);
}
$stmt->close();

fwrite(STDOUT, "Initial administrator created. Sign in with the configured email address.\n");
