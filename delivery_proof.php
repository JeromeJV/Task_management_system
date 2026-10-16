<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';
requireActiveAccount($conn);

$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['id'] ?? 0);
$deliveryId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($role, ['super', 'log'], true) || $userId < 1 || !$deliveryId || $deliveryId < 1) {
    http_response_code(404);
    exit();
}

if ($role === 'super') {
    $stmt = $conn->prepare(
        'SELECT delivery_proof_image FROM delivery
         WHERE delivery_id = ? AND (assigned_by = ? OR assigned_by IS NULL)
         LIMIT 1'
    );
} else {
    $stmt = $conn->prepare(
        'SELECT delivery_proof_image FROM delivery
         WHERE delivery_id = ? AND driver_id = ?
         LIMIT 1'
    );
}
if (!$stmt) {
    throw new RuntimeException('Unable to prepare delivery proof access check: ' . $conn->error);
}
$stmt->bind_param('ii', $deliveryId, $userId);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    throw new RuntimeException('Unable to load delivery proof: ' . $error);
}
$delivery = $stmt->get_result()->fetch_assoc();
$stmt->close();

$filename = (string) ($delivery['delivery_proof_image'] ?? '');
if (
    !$delivery
    || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)
) {
    http_response_code(404);
    exit();
}

$proofDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'delivery-proofs';
$proofPath = realpath($proofDirectory . DIRECTORY_SEPARATOR . $filename);
$resolvedDirectory = realpath($proofDirectory);
if (
    $proofPath === false
    || $resolvedDirectory === false
    || !str_starts_with($proofPath, $resolvedDirectory . DIRECTORY_SEPARATOR)
    || !is_file($proofPath)
) {
    http_response_code(404);
    exit();
}

$contentTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . (string) filesize($proofPath));
header('Content-Disposition: inline; filename="delivery-proof-' . (int) $deliveryId . '.' . $extension . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($proofPath);
