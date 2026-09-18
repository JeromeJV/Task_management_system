<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');


// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

// Sinusure lg ung mga variable na existing sila
$message = $message ?? '';
//----------- Logistic variables ---------------------
$route_err = $route_err ?? '';
$pieces_err = $pieces_err ?? '';
$stock_err = $stock_err ?? '';
$delivery_date_err = $delivery_date_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);
// ---------- Production variables ---------------------
$product_name_err = $product_name_err ?? '';
$target_pcs_err = $target_pcs_err ?? '';
$due_date_err = $due_date_err ?? '';
$Stock_number_err = $Stock_number_err ?? '';
$quantity_err = $quantity_err ?? '';    
$count = $count ?? count($records);
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
      <button id="navEmployee" class="side-btn" onclick="showPage('employee')">EMPLOYEE</button>
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
      <h2 class="section-title">MONTH OF APRIL</h2>
      <div class="stat-row">
        <div class="stat-card"><div class="num" id="statCompleted">0</div><div class="label">COMPLETED</div></div>
        <div class="stat-card"><div class="num" id="statPending">0</div><div class="label">PENDING</div></div>
        <div class="stat-card"><div class="num" id="statOverdue">0</div><div class="label">OVERDUE</div></div>
      </div>
      <div class="dash-lower">
        <div class="chart-panel">
          <h3>DEPARTMENT PROGRESS</h3>
          <!-- Canvas Container para sa Chart.js -->
          <div style="position: relative; height: 260px; width: 100%;">
             <canvas id="myChart"></canvas>
          </div>
        </div>

        <div class="recent-panel">
          <h3>RECENT TASK</h3>
          <div class="task-item">
            <div class="who">RALA, IRISH MARIE</div>
            <div class="what">Write blog/article about product/service</div>
          </div>
          <div class="task-item">
            <div class="who">RALA, IRISH MARIE</div>
            <div class="what">Launch Facebook & Instagram ad campaign</div>
          </div>
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
<!-- hi -->
  </div>
</div>
</body>
</html>