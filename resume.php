<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';

if (!isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'HR') {
    http_response_code(403);
    exit('Access denied.');
}
requireActiveAccount($conn, true);

$applicantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$applicantId || $applicantId < 1) {
    http_response_code(400);
    exit('Invalid applicant.');
}

$statement = $conn->prepare('SELECT resume_path FROM applicant WHERE applicant_id = ? LIMIT 1');
if (!$statement) {
    throw new RuntimeException('Unable to prepare resume lookup: ' . $conn->error);
}
$statement->bind_param('i', $applicantId);
if (!$statement->execute()) {
    throw new RuntimeException('Unable to retrieve resume record: ' . $statement->error);
}
$resumePath = (string) ($statement->get_result()->fetch_assoc()['resume_path'] ?? '');
$statement->close();

$filename = basename(str_replace('\\', '/', $resumePath));
$uploadsDirectory = realpath(__DIR__ . '/uploads');
$filePath = $uploadsDirectory ? realpath($uploadsDirectory . DIRECTORY_SEPARATOR . $filename) : false;
if ($filename === '' || !$uploadsDirectory || !$filePath
    || !str_starts_with($filePath, $uploadsDirectory . DIRECTORY_SEPARATOR)
    || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Resume not found.');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];
if (!isset($contentTypes[$extension])) {
    http_response_code(415);
    exit('Unsupported resume format.');
}

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . (string) filesize($filePath));
if (($_GET['preview'] ?? '') === '1' && $extension === 'pdf') {
    header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
