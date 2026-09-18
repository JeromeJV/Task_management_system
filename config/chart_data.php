<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Isama ang database connection
include('connection.php');

// Kung walang $conn o nag-fail ang connection
if (!isset($conn) || !$conn) {
    echo json_encode(["error" => "Database Connection Failed: " . mysqli_connect_error()]);
    exit;
}

$monthly_quantity = array_fill(1, 12, 0);

$sql = "SELECT 
            MONTH(CAST(due_date AS DATE)) as month_num, 
            SUM(CAST(quantity AS UNSIGNED)) as total_qty 
        FROM production 
        WHERE LOWER(product_status) LIKE '%done%' 
          AND due_date IS NOT NULL 
          AND due_date != ''
        GROUP BY month_num";

$res = mysqli_query($conn, $sql);

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $m = (int)$row['month_num'];
        if ($m >= 1 && $m <= 12) {
            $monthly_quantity[$m] = (int)$row['total_qty'];
        }
    }
}

// Ibalik ang 12 months array values lamang
echo json_encode(array_values($monthly_quantity));
exit;
?>