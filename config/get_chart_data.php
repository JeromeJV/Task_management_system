<?php
header('Content-Type: application/json');
include 'config/connection.php';

$sql = "SELECT COALESCE(status, 'Pending') AS status, COUNT(*) AS total 
        FROM delivery 
        WHERE status != 'Delivered' OR status IS NULL 
        GROUP BY status";

$result = mysqli_query($conn, $sql);

$labels = [];
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['status'];
    $data[] = (int)$row['total'];
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
?>