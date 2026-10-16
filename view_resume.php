<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';

if (!isset($_SESSION['id'], $_SESSION['email'], $_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    exit('HR access is required to view resumes.');
}
requireActiveAccount($conn);

$filename = basename((string) ($_GET['file'] ?? ''));
if ($filename === '') {
    http_response_code(400);
    exit('A valid resume is required.');
}
$statement = $conn->prepare('SELECT applicant_id FROM applicant WHERE resume_path = ? LIMIT 1');
if (!$statement) {
    throw new RuntimeException('Unable to prepare resume lookup: ' . $conn->error);
}
$statement->bind_param('s', $filename);
if (!$statement->execute()) {
    $error = $statement->error;
    $statement->close();
    throw new RuntimeException('Unable to find resume record: ' . $error);
}
$applicantId = (int) ($statement->get_result()->fetch_assoc()['applicant_id'] ?? 0);
$statement->close();
if ($applicantId < 1) {
    http_response_code(404);
    exit('Resume not found.');
}

header('Location: resume.php?id=' . $applicantId . '&preview=1', true, 303);
exit();