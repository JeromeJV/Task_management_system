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
    <title>HR Management - TASKTRACK</title>
</head>
<body>
    <div class="sidebar">
  <div class="sidebar-header">
    <div class="avatar">🐐</div>
    <div class="titles">
      <div class="name">SANTOS</div>
      <div class="sub">JASMIN GAIL BALCITA</div>
      <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
    </div>
  </div>
  <div class="sidebar-nav">
    <button id="navEmployee" class="side-btn active" onclick="showPage('employee')">EMPLOYEE</button>
    <button id="navApplicants" class="side-btn" onclick="showPage('applicants')">APPLICANTS</button>
  </div>
  <a href="logout.php" style="text-decoration: none;"><button class="logout-btn">LOG OUT</button></a>
</div>
 
<div class="main">
  <div class="topbar">
    <h1>TASKTRACK</h1>
  </div>
 
  <div class="content">
 
    <!-- EMPLOYEES PAGE -->
    <div id="page-employee" class="page active">
      <div class="page-title">
        <h2>EMPLOYEES</h2>
        <p>Manage and view all employee information</p>
      </div>
 
      <div class="search-row">
        <div class="search-bar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24371f" stroke-width="2.5">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="employeeSearch" placeholder="SEARCH" oninput="renderEmployees()">
        </div>
        <button class="filter-btn">DEPARTMENTS</button>
      </div>
 
      <div class="layout">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>EMPLOYEE</th>
                <th>DEPARTMENT</th>
              </tr>
            </thead>
            <tbody id="employeeBody"></tbody>
          </table>
        </div>
        <div class="detail-panel" id="employeeDetail">
          <div class="empty">Select an employee to view details</div>
        </div>
      </div>
    </div>
 
    <!-- APPLICANTS PAGE -->
    <div id="page-applicants" class="page">
      <div class="page-title">
        <h2>APPLICANTS</h2>
        <p>WHOM SHALL I ACCEPT AND REJECT</p>
      </div>
 
      <div class="search-row">
        <div class="search-bar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24371f" stroke-width="2.5">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="applicantSearch" placeholder="SEARCH" oninput="renderApplicants()">
        </div>
      </div>
 
      <div class="layout">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>EMPLOYEE</th>
                <th>APPLIED DATE</th>
                <th>STATUS</th>
              </tr>
            </thead>
            <tbody id="applicantBody"></tbody>
          </table>
        </div>
        <div class="detail-panel" id="applicantDetail">
          <div class="empty">Select an applicant to view details</div>
        </div>
      </div>
    </div>
 
  </div>
</div>
<script src="script.js"></script>
</body>
</html>