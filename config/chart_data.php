<?php
header('Content-Type: application/json');

require_once __DIR__ . '/connection.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$monthly_quantity = array_fill(1, 12, 0);
$currentYear = (int) date('Y');
$yearFilter = $_GET['year'] ?? (string) $currentYear;
if (!ctype_digit((string) $yearFilter) || (int) $yearFilter < 2000 || (int) $yearFilter > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year filter.']);
    exit;
}
$yearNumber = (int) $yearFilter;

$monthFilter = $_GET['month'] ?? 'all';
if ($monthFilter !== 'all' && (!ctype_digit((string) $monthFilter) || (int) $monthFilter < 1 || (int) $monthFilter > 12)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid month filter.']);
    exit;
}

$year_statement = $conn->prepare("
    SELECT DISTINCT YEAR(CAST(due_date AS DATE)) AS production_year
    FROM production
    WHERE due_date IS NOT NULL
      AND due_date <> ''
      AND CAST(due_date AS DATE) IS NOT NULL
    ORDER BY production_year DESC
");
if (!$year_statement || !$year_statement->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load available production years.']);
    exit;
}

$available_years = [$currentYear];
$year_result = $year_statement->get_result();
while ($row = $year_result->fetch_assoc()) {
    $available_years[] = (int) $row['production_year'];
}
$year_statement->close();
$available_years = array_values(array_unique($available_years));
rsort($available_years);

$monthly_statement = $conn->prepare("
    SELECT MONTH(CAST(due_date AS DATE)) AS month_num,
           SUM(CAST(quantity AS UNSIGNED)) AS total_qty
    FROM production
    WHERE LOWER(TRIM(product_status)) = 'product done'
      AND due_date IS NOT NULL
      AND due_date <> ''
      AND YEAR(CAST(due_date AS DATE)) = ?
    GROUP BY month_num
");
if (!$monthly_statement) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare monthly production totals.']);
    exit;
}
$monthly_statement->bind_param('i', $yearNumber);
if (!$monthly_statement->execute()) {
    $monthly_statement->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load monthly production totals.']);
    exit;
}

$monthly_result = $monthly_statement->get_result();
while ($row = $monthly_result->fetch_assoc()) {
    $m = (int) $row['month_num'];
    if ($m >= 1 && $m <= 12) {
        $monthly_quantity[$m] = (int) $row['total_qty'];
    }
}
$monthly_statement->close();

$product_frequency = [];
$product_sql = "
    SELECT TRIM(product_name) AS product_name, COUNT(*) AS record_count
    FROM production
    WHERE product_name IS NOT NULL
      AND TRIM(product_name) <> ''
      AND due_date IS NOT NULL
      AND due_date <> ''
      AND YEAR(CAST(due_date AS DATE)) = ?
";
$product_sql .= ($monthFilter === 'all' ? '' : ' AND MONTH(CAST(due_date AS DATE)) = ?')
    . ' GROUP BY TRIM(product_name) ORDER BY record_count DESC, product_name ASC';
$product_statement = $conn->prepare($product_sql);
if (!$product_statement) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load product production counts.']);
    exit;
}

if ($monthFilter !== 'all') {
    $monthNumber = (int) $monthFilter;
    $product_statement->bind_param('ii', $yearNumber, $monthNumber);
} else {
    $product_statement->bind_param('i', $yearNumber);
}
if (!$product_statement->execute()) {
    $product_statement->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load product production counts.']);
    exit;
}

$product_res = $product_statement->get_result();
while ($row = $product_res->fetch_assoc()) {
    $product_frequency[] = [
        'product_name' => $row['product_name'],
        'record_count' => (int) $row['record_count']
    ];
}
$product_statement->close();

echo json_encode([
    'selected_year' => $yearNumber,
    'available_years' => $available_years,
    'monthly_quantity' => array_values($monthly_quantity),
    'product_frequency' => $product_frequency
]);
exit;
?>