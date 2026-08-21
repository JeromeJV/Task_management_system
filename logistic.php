<?php
    include('config/connection.php');

    session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css"> 
    <title>Logistic Management - TASKTRACK</title>
</head>
<body>

    <div class="sidebar">
  <div>
    <div class="sidebar-header">
      <div class="avatar">🐐</div>
      <div>
        <div class="name">SAGON</div>
        <div class="sub">BERNARD JOSEPH</div>
        <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
      </div>
    </div>
    <div class="sidebar-nav">
      <button id="navDashboard" class="side-btn active" onclick="showPage('dashboard')">DASHBOARD</button>
      <button id="navTask" class="side-btn" onclick="showPage('task')">TASK</button>
      <button id="navEmployee" class="side-btn" onclick="showPage('employee')">EMPLOYEE</button>
      <button id="navProduction" class="side-btn" onclick="showPage('production')">PRODUCTION</button>
      <button id="navLogistic" class="side-btn" onclick="showPage('logistic')">LOGISTIC</button>
    </div>
  </div>
  <a href="logout.php" style="text-decoration: none;"><button class="logout-btn">LOG OUT</button></a>
</div>

<div class="main">
  <div class="topbar"><h1>TASKTRACK</h1></div>

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
          <div class="wave-wrap" id="waveWrap">
            <svg id="waveSvg" viewBox="0 0 700 260" preserveAspectRatio="none">
              <defs>
                <linearGradient id="waveGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#14b8a6" stop-opacity="0.35"/>
                  <stop offset="100%" stop-color="#14b8a6" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <path id="waveArea" fill="url(#waveGrad)" stroke="none"></path>
              <path id="waveLine" fill="none" stroke="#14b8a6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
              <g id="wavePoints"></g>
            </svg>
            <div class="wave-tooltip" id="waveTooltip"></div>
          </div>
          <div class="wave-months">
            <span>JAN</span><span>FEB</span><span>MAR</span><span>APR</span><span>MAY</span><span>JUN</span>
            <span>JUL</span><span>AUG</span><span>SEP</span><span>OCT</span><span>NOV</span><span>DEC</span>
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
    <div id="page-task" class="page">
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
    <div id="page-employee" class="page">
      <div class="employee-layout">
        <div class="employee-list">
          <h4>Employee's</h4>
          <div id="employeeCards"></div>
        </div>
        <div class="employee-detail" id="employeeDetail"></div>
      </div>
    </div>

    <!-- PRODUCTION -->
    <div id="page-production" class="page">
      <div class="action-header"><button class="assign-btn">ASSIGN TASK</button></div>
      <div class="stat-row">
        <div class="stat-card green">
          <div class="label" style="font-size:15px;opacity:0.9;">Avg. efficiency</div>
          <div class="num" style="margin-top:4px;">70%</div>
          <div class="progress-track" style="background:rgba(255,255,255,0.4);"><div class="progress-fill" style="width:70%;"></div></div>
        </div>
        <div class="stat-card green">
          <div class="label" style="font-size:15px;opacity:0.9;">Active Factory</div>
        <div class="num" style="margin-top:4px;">2</div>
          <div class="sub">out of 3</div>
        </div>
      </div>
      <div class="green-table-panel">
        <table class="green-table">
          <thead><tr><th>Product ID</th><th>Product Name</th><th>Status</th><th>Output</th><th>Target</th><th>Action</th></tr></thead>
          <tbody>
            <tr><td>GFCLBRZN1</td><td>Batangas</td><td><span class="pill running">Running</span></td><td>1249</td><td>1500</td><td><button class="action-btn">Edit</button></td></tr>
            <tr><td>GFNCR1</td><td>Valenzuela</td><td><span class="pill maintenance">Maintenance</span></td><td>432</td><td>1000</td><td><button class="action-btn">Edit</button></td></tr>
            <tr><td>GFRIII1</td><td>Bulacan</td><td><span class="pill running">Running</span></td><td>365</td><td>800</td><td><button class="action-btn">Edit</button></td></tr>
          </tbody>
        </table>
      </div>
      <div class="bottom-row">
        <div class="info-panel">
          <h4>Alerts</h4>
          <div class="alert-item"><div class="a-title">PL-003 in Maintenance</div><div class="a-sub">Scheduled maintenance ongoing</div></div>
        </div>
        <div class="info-panel">
          <h4>Materials</h4>
          <div class="info-row"><span>Raw Materials:</span><span>85% Stock</span></div>
          <div class="info-row"><span>Packaging:</span><span>92% Stock</span></div>
        </div>
        <div class="info-panel">
          <h4>Quality</h4>
          <div class="info-row"><span>Pass Rate:</span><span>98.8%</span></div>
          <div class="info-row"><span>Inspected:</span><span>2,150 units</span></div>
        </div>
      </div>
    </div>

    <!-- LOGISTIC -->
    <div id="page-logistic" class="page">
      <div class="action-header"><button class="assign-btn">ASSIGN TASK</button></div>
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
      <div class="green-table-panel">
        <table class="green-table">
          <thead><tr><th>Shipment ID</th><th>Destination</th><th>Status</th><th>Driver</th><th>ETA</th><th>Action</th></tr></thead>
          <tbody>
            <tr><td>G20260501</td><td>Calamba, Laguna</td><td><span class="pill transit">In transit</span></td><td>Marlon Francisco</td><td>2h 30m</td><td><button class="action-btn">Track</button></td></tr>
            <tr><td>G20260503</td><td>Caloocan City</td><td><span class="pill loading">Loading</span></td><td>David Larzon</td><td>4h 15m</td><td><button class="action-btn">Track</button></td></tr>
            <tr><td>G20260408</td><td>Valenzuela</td><td><span class="pill delivered">Delivered</span></td><td>Larry Lee</td><td>Completed</td><td><button class="action-btn">Track</button></td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
    <script src="script.js"></script>
</body>
</html>