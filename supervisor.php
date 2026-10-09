<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');

// Authorization check: Siguraduhing tamang role ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

// -----------------------------------------------------
// 1. CALCULATE COMPLETED, PENDING, AND OVERDUE TASKS
// -----------------------------------------------------

// Query para sa Production tasks
$prod_counts_query = "
    SELECT 
        SUM(CASE WHEN product_status = 'product done' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (product_status != 'product done' OR product_status IS NULL) AND due_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN (product_status != 'product done' OR product_status IS NULL) AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM production";

$prod_res = mysqli_query($conn, $prod_counts_query);
$prod_data = $prod_res ? mysqli_fetch_assoc($prod_res) : ['completed' => 0, 'pending' => 0, 'overdue' => 0];

// Query para sa Delivery tasks
$del_counts_query = "
    SELECT 
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (status != 'Delivered' OR status IS NULL) AND delivery_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN (status != 'Delivered' OR status IS NULL) AND delivery_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM delivery";

$del_res = mysqli_query($conn, $del_counts_query);
$del_data = $del_res ? mysqli_fetch_assoc($del_res) : ['completed' => 0, 'pending' => 0, 'overdue' => 0];

// Sum/Pagsasamahin ang galing sa Production at Delivery
$total_completed = ($prod_data['completed'] ?? 0) + ($del_data['completed'] ?? 0);
$total_pending   = ($prod_data['pending'] ?? 0) + ($del_data['pending'] ?? 0);
$total_overdue   = ($prod_data['overdue'] ?? 0) + ($del_data['overdue'] ?? 0);

// Ensure production assignment storage exists before loading the dashboard task list.
$assignment_table_sql = "CREATE TABLE IF NOT EXISTS production_assignments (
                            production_id VARCHAR(64) NOT NULL,
                            employee_id INT NOT NULL,
                            work_type ENUM('Cooking', 'Packaging') NOT NULL,
                            assigned_by INT DEFAULT NULL,
                            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (production_id),
                            KEY idx_production_assignments_employee (employee_id)
                         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!mysqli_query($conn, $assignment_table_sql)) {
    throw new RuntimeException('Unable to initialize production task assignments: ' . mysqli_error($conn));
}

// Active assigned production tasks
$active_tasks = [];
$production_tasks_query = mysqli_query($conn, "
    SELECT p.product_name AS task_name,
           e.username AS assigned_to,
           p.due_date AS due_date,
           COALESCE(NULLIF(p.product_status, ''), 'Pending') AS task_status,
           'Production' AS department
    FROM production p
    INNER JOIN production_assignments pa
        ON pa.production_id = CAST(p.production_id AS CHAR)
    INNER JOIN employee e ON e.employee_id = pa.employee_id
    WHERE (p.product_status != 'product done' OR p.product_status IS NULL)
    ORDER BY p.due_date ASC, p.production_id DESC
");
if (!$production_tasks_query) {
    throw new RuntimeException('Unable to load active production assignments: ' . mysqli_error($conn));
}
while ($task = mysqli_fetch_assoc($production_tasks_query)) {
    $active_tasks[] = $task;
}

// Active assigned logistics tasks
$logistics_tasks_query = mysqli_query($conn, "
    SELECT CONCAT(COALESCE(NULLIF(p.product_name, ''), CONCAT('Production #', d.production_id)),
                  ' - ', d.route) AS task_name,
           u.name AS assigned_to,
           d.delivery_date AS due_date,
           COALESCE(NULLIF(d.status, ''), 'Pending') AS task_status,
           'Logistics' AS department
    FROM delivery d
    INNER JOIN users u ON u.id = d.driver_id
    LEFT JOIN production p ON p.production_id = d.production_id
    WHERE d.driver_id IS NOT NULL
      AND (d.status != 'Delivered' OR d.status IS NULL)
    ORDER BY d.delivery_date ASC, d.delivery_id DESC
");
if (!$logistics_tasks_query) {
    throw new RuntimeException('Unable to load active logistics assignments: ' . mysqli_error($conn));
}
while ($task = mysqli_fetch_assoc($logistics_tasks_query)) {
    $active_tasks[] = $task;
}

usort($active_tasks, static function ($first, $second) {
    return strcmp((string) $first['due_date'], (string) $second['due_date']);
});

// Sinisiguro ang iba pang variables
$message = $message ?? '';
$route_err = $route_err ?? '';
$pieces_err = $pieces_err ?? '';
$stock_err = $stock_err ?? '';
$delivery_date_err = $delivery_date_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);

$product_name_err = $product_name_err ?? '';
$target_pcs_err = $target_pcs_err ?? '';
$due_date_err = $due_date_err ?? '';
$Stock_number_err = $Stock_number_err ?? '';
$quantity_err = $quantity_err ?? '';    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Form</title>
    <link rel="stylesheet" href="css/supervisor.css?v=20261009-logistics-route">
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php $tasktrackActive = 'dashboard'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>Supervisor</h1></div>

  <div class="content">

    <!-- DASHBOARD -->
    <div id="page-dashboard" class="page active">
      <div class="stat-row">
        <div class="stat-card">
          <div class="num" id="statCompleted"><?= $total_completed ?></div>
          <div class="label">COMPLETED</div>
        </div>
        <div class="stat-card">
          <div class="num" id="statPending"><?= $total_pending ?></div>
          <div class="label">PENDING</div>
        </div>
        <div class="stat-card">
          <div class="num" id="statOverdue"><?= $total_overdue ?></div>
          <div class="label">OVERDUE</div>
        </div>
      </div>
      <div class="dash-lower">
        <div class="charts-grid">
          <div class="chart-panel">
            <div class="dashboard-panel-heading">
              <div>
                <h3>COMPLETED PRODUCT PROGRESS</h3>
                <p>Completed quantity by due month for the selected year</p>
              </div>
              <label class="pie-month-filter">
                <span>Year</span>
                <select id="chartYearFilter" aria-label="Select chart year">
                  <option value="<?= (int) date('Y'); ?>"><?= (int) date('Y'); ?></option>
                </select>
              </label>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="myChart"></canvas>
            </div>
          </div>

          <div class="chart-panel">
            <div class="dashboard-panel-heading">
              <div>
                <h3>MOST PRODUCED PRODUCTS</h3>
                <p>Production records by product for the selected year and month</p>
              </div>
              <label class="pie-month-filter">
                <span>Month</span>
                <select id="pieMonthFilter" aria-label="Filter most produced products by month">
                  <option value="all">All months</option>
                  <option value="1">January</option>
                  <option value="2">February</option>
                  <option value="3">March</option>
                  <option value="4">April</option>
                  <option value="5">May</option>
                  <option value="6">June</option>
                  <option value="7">July</option>
                  <option value="8">August</option>
                  <option value="9">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
              </label>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="productPieChart"></canvas>
              <p id="pieChartEmpty" class="chart-empty-message" hidden>No product records available.</p>
            </div>
          </div>
        </div>
        <div class="active-task-panel">
          <div class="active-task-heading">
            <div>
              <h3>ACTIVE TASKS</h3>
              <p>Currently assigned tasks for Production and Logistics</p>
            </div>
            <span class="active-task-count"><?= count($active_tasks); ?> active</span>
          </div>

          <?php if ($active_tasks): ?>
            <div class="active-task-list">
              <?php foreach ($active_tasks as $task): ?>
                <?php $department_class = strtolower($task['department']) === 'logistics' ? 'is-logistics' : 'is-production'; ?>
                <article class="active-task-card <?= $department_class; ?>">
                  <div class="active-task-card-main">
                    <span class="active-task-card-mark" aria-hidden="true">
                      <?= $department_class === 'is-logistics' ? '↗' : '✓'; ?>
                    </span>
                    <div class="active-task-card-copy">
                      <h4><?= htmlspecialchars($task['task_name'] ?? 'N/A'); ?></h4>
                      <p>Assigned to <strong><?= htmlspecialchars($task['assigned_to'] ?? 'N/A'); ?></strong></p>
                    </div>
                  </div>
                  <div class="active-task-card-meta">
                    <span class="active-task-department <?= $department_class; ?>"><?= htmlspecialchars($task['department']); ?></span>
                    <span class="active-task-date">Due <?= htmlspecialchars($task['due_date'] ?? 'N/A'); ?></span>
                    <span class="active-task-status"><?= htmlspecialchars($task['task_status'] ?? 'Pending'); ?></span>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="active-task-empty">No active tasks are currently assigned to Production or Logistics.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TASK -->
    <div id="page-task" class="page" style="display:none;">
      <h2 class="section-title">TASKS</h2>
      <div class="green-table-panel table-scroll">
        <table class="green-table">
          <thead>
            <tr><th>Employee</th><th>Task</th><th>Department</th><th>Due date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($active_tasks): ?>
              <?php foreach ($active_tasks as $task): ?>
                <tr>
                  <td><?= htmlspecialchars($task['assigned_to'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['task_name'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['department'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['due_date'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['task_status'] ?? 'Pending'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5">No active assigned tasks.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- EMPLOYEE -->
    <div id="page-employee" class="page" style="display:none;">
      <div class="employee-layout">
        <div class="employee-list">
          <h4>Employee's</h4>
          <div id="employeeCards"></div>
        </div>
        <div class="employee-detail" id="employeeDetail"></div>
      </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
<script src="js/supervisor.js?v=20261009-supervisor-fixes"></script>
</body>
</html>