<?php

function acquireProductionStockNumberLock(mysqli $conn, int $timeoutSeconds = 10): void
{
    $statement = $conn->prepare('SELECT GET_LOCK(?, ?) AS lock_acquired');
    if (!$statement) {
        throw new RuntimeException('Unable to prepare the production stock-number lock: ' . $conn->error);
    }
    $lockName = 'tasktrack_production_stock_number';
    $statement->bind_param('si', $lockName, $timeoutSeconds);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to acquire the production stock-number lock: ' . $error);
    }
    $acquired = (int) ($statement->get_result()->fetch_assoc()['lock_acquired'] ?? 0) === 1;
    $statement->close();
    if (!$acquired) {
        throw new RuntimeException('Stock-number generation is busy. Please try again.');
    }
}

function releaseProductionStockNumberLock(mysqli $conn): void
{
    $statement = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if (!$statement) {
        error_log('TaskTrack could not prepare production stock-number lock release.');
        return;
    }
    $lockName = 'tasktrack_production_stock_number';
    $statement->bind_param('s', $lockName);
    if (!$statement->execute()) {
        error_log('TaskTrack could not release the production stock-number lock.');
    }
    $statement->close();
}

function productionStockNumberExists(mysqli $conn, int $stockNumber, ?int $exceptProductionId = null): bool
{
    $sql = $exceptProductionId === null
        ? 'SELECT production_id FROM production WHERE Stock_number = ? LIMIT 1'
        : 'SELECT production_id FROM production WHERE Stock_number = ? AND production_id <> ? LIMIT 1';
    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Unable to prepare production stock-number check: ' . $conn->error);
    }
    if ($exceptProductionId === null) {
        $statement->bind_param('i', $stockNumber);
    } else {
        $statement->bind_param('ii', $stockNumber, $exceptProductionId);
    }
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to check production stock-number availability: ' . $error);
    }
    $exists = $statement->get_result()->num_rows > 0;
    $statement->close();
    return $exists;
}

function getProductionStockNumber(mysqli $conn, int $productionId): ?int
{
    $statement = $conn->prepare('SELECT Stock_number FROM production WHERE production_id = ? LIMIT 1');
    if (!$statement) {
        throw new RuntimeException('Unable to prepare current production stock-number lookup: ' . $conn->error);
    }
    $statement->bind_param('i', $productionId);
    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('Unable to load the current production stock number: ' . $error);
    }
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();
    return $row === null ? null : (int) $row['Stock_number'];
}

function generateUniqueProductionStockNumber(mysqli $conn, array $reservedNumbers = []): int
{
    for ($attempt = 0; $attempt < 100; $attempt++) {
        try {
            $candidate = random_int(1000, 9999);
        } catch (Throwable $error) {
            throw new RuntimeException('Unable to generate a production stock number.', 0, $error);
        }
        if (isset($reservedNumbers[$candidate])) {
            continue;
        }
        if (!productionStockNumberExists($conn, $candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException('Unable to generate an available 4-digit stock number. Please try again.');
}