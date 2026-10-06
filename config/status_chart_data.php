<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include('connection.php');


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !$conn) {

    echo json_encode([
        "error" => "Database Connection Failed"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CREATE 12 MONTH ARRAYS
|--------------------------------------------------------------------------
*/

$completed = array_fill(1, 12, 0);
$pending   = array_fill(1, 12, 0);
$overdue   = array_fill(1, 12, 0);


/*
|--------------------------------------------------------------------------
| COMPLETED
|--------------------------------------------------------------------------
*/

$sql_completed = "
    SELECT
        MONTH(CAST(due_date AS DATE)) AS month_num,
        SUM(CAST(quantity AS UNSIGNED)) AS total_qty
    FROM production
    WHERE product_status = 'product done'
      AND due_date IS NOT NULL
      AND due_date != ''
    GROUP BY MONTH(CAST(due_date AS DATE))
";

$res_completed = mysqli_query(
    $conn,
    $sql_completed
);


if ($res_completed) {

    while ($row = mysqli_fetch_assoc($res_completed)) {

        $month = (int)$row['month_num'];

        if ($month >= 1 && $month <= 12) {

            $completed[$month] =
                (int)$row['total_qty'];

        }
    }
}


/*
|--------------------------------------------------------------------------
| PENDING
|--------------------------------------------------------------------------
*/

$sql_pending = "
    SELECT
        MONTH(CAST(due_date AS DATE)) AS month_num,
        SUM(CAST(quantity AS UNSIGNED)) AS total_qty
    FROM production
    WHERE (
        product_status != 'product done'
        OR product_status IS NULL
    )
    AND due_date IS NOT NULL
    AND due_date != ''
    AND CAST(due_date AS DATE) >= CURDATE()
    GROUP BY MONTH(CAST(due_date AS DATE))
";

$res_pending = mysqli_query(
    $conn,
    $sql_pending
);


if ($res_pending) {

    while ($row = mysqli_fetch_assoc($res_pending)) {

        $month = (int)$row['month_num'];

        if ($month >= 1 && $month <= 12) {

            $pending[$month] =
                (int)$row['total_qty'];

        }
    }
}


/*
|--------------------------------------------------------------------------
| OVERDUE
|--------------------------------------------------------------------------
*/

$sql_overdue = "
    SELECT
        MONTH(CAST(due_date AS DATE)) AS month_num,
        SUM(CAST(quantity AS UNSIGNED)) AS total_qty
    FROM production
    WHERE (
        product_status != 'product done'
        OR product_status IS NULL
    )
    AND due_date IS NOT NULL
    AND due_date != ''
    AND CAST(due_date AS DATE) < CURDATE()
    GROUP BY MONTH(CAST(due_date AS DATE))
";

$res_overdue = mysqli_query(
    $conn,
    $sql_overdue
);


if ($res_overdue) {

    while ($row = mysqli_fetch_assoc($res_overdue)) {

        $month = (int)$row['month_num'];

        if ($month >= 1 && $month <= 12) {

            $overdue[$month] =
                (int)$row['total_qty'];

        }
    }
}


/*
|--------------------------------------------------------------------------
| RETURN JSON
|--------------------------------------------------------------------------
*/

echo json_encode([

    "completed" => array_values($completed),

    "pending" => array_values($pending),

    "overdue" => array_values($overdue)

]);

exit;

?>