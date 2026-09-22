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
    <link rel="stylesheet" href="css/supervisor.css">
    <!-- Chart.js Library sa HEAD -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="sidebar">
  <div>
    <div class="sidebar-header">
      <div class="avatar">🐐</div>
      <div>
        <div class="name">TASKTRACK</div>
        <div class="sub">Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></div>
        <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
      </div>
    </div>
    
    <div class="sidebar-nav">
      <button id="navDashboard" class="side-btn active" onclick="showPage('dashboard')">DASHBOARD</button>
      <button id="navTask" class="side-btn" onclick="showPage('task')">TASK</button>      
      <button class="side-btn" type="button"><a href="factory_main.php">PRODUCTION</a></button>
      <button class="side-btn" type="button"><a href="delivery_main.php">LOGISTIC</a></button>    
    </div>
  </div>
  <a href="logout.php" style="text-decoration: none;"><button class="logout-btn">LOG OUT</button></a>
</div>

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
        <div class="chart-panel">
          <h3>PRODUCT PROGRESS</h3>

          <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="myChart"></canvas>
          </div>
          
          <script src="js/supervisor.js"></script>
        </div>
      </div>
    </div>

    <!-- TASK -->
    <div id="page-task" class="page" style="display:none;">
      <h2 class="section-title">TASKS</h2>
      <div class="green-table-panel">
        <table class="green-table">
          <thead>
            <tr><th>Employee</th><th>Task</th><th>Department</th><th>Due date</th><th>Status</th></tr>
          </thead>
          <tbody id="taskBody"></tbody>
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

    <!-- LOGISTIC -->
    <div id="page-logistic" class="page" style="display:none;">
      <div class="assign"> <button><a href="delivery_task.php">Add Delivery Record</a></button></div>
      <div class="assign"> <button><a href="supervisor.php">BACK</a></button></div>
      <div class="stat-row">
        <div class="stat-card green">
          <div class="label" style="font-size:15px;opacity:0.9;">Total delivery</div>
          <div class="num" style="margin-top:4px;">3</div>
        </div>
        <div class="stat-card green">
          <div class="label" style="font-size:15px;opacity:0.9;">Active Shipments</div>
          <div class="num" style="margin-top:4px;">3</div>
          <div class="sub">5 total shipments</div>
        </div>
      </div>
      <div class="bottom-row" style="margin-bottom:24px;">
        <div class="info-panel">
          <h4>Fleet status</h4>
          <div class="info-row"><span>Active Vehicles:</span><span>12/15</span></div>
          <div class="info-row"><span>Available Driver:</span><span>3</span></div>
          <div class="info-row"><span>Maintenance:</span><span>0</span></div>
        </div>
        <div class="info-panel">
          <h4>Warehouse capacity</h4>
          <div class="info-row"><span>Warehouse A:</span><span>78%</span></div>
          <div class="progress-track"><div class="progress-fill" style="width:78%;"></div></div>
          <div class="info-row" style="margin-top:10px;"><span>Warehouse B:</span><span>62%</span></div>
          <div class="progress-track"><div class="progress-fill" style="width:62%;"></div></div>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>