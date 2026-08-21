<?php
    include('config/connection.php');

    session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskTrack</title>
<link rel="stylesheet" href="../css/Employee.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div>
            <div class="sidebar-header">
                <div>
                    <div class="name">DULATRE</div>
                    <div class="sub">DOMINIC</div>
                </div>
            </div>
            <div class="sidebar-nav">
                <button id="navDashboard" class="side-btn" onclick="showPage('dashboard')">DASHBOARD</button>
                <button id="navTask" class="side-btn" onclick="showPage('task')">TASK</button>
            </div>
        </div>
        <button class="logout-btn">LOG OUT</button>
    </div>

    <!-- Main -->
    <div class="main">
        <div class="topbar">
            <h1>TASKTRACK</h1>
        </div>

        <div class="content">

            <!-- DASHBOARD -->
            <div id="page-dashboard" class="page">
                <h2 class="section-title">DASHBOARD</h2>

                <div class="stat-row">
                    <div class="stat-card">
                        <div class="num" id="overdueCount">0</div>
                        <div class="label">Overdue</div>
                    </div>
                    <div class="stat-card">
                        <div class="num" id="pendingCount">0</div>
                        <div class="label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="num" id="completedCount">0</div>
                        <div class="label">Completed</div>
                    </div>
                </div>

                <div class="dash-lower">
                    <div class="recent-panel">
                        <h3>Recent Tasks</h3>
                        <div id="recentTasks"></div>
                    </div>
                </div>
            </div>

            <!-- TASK -->
            <div id="page-task" class="page">
                <h2 class="section-title">ALL TASKS</h2>

                <!-- Overdue Section -->
                <div class="task-section overdue">
                    <div class="task-section-header">
                        <h3>OVERDUE</h3>
                    </div>
                    <div class="card-panel">
                        <div class="card-scroll" id="overdueTaskBody"></div>
                    </div>
                </div>

                <!-- Pending Section -->
                <div class="task-section pending">
                    <div class="task-section-header">
                        <h3>PENDING</h3>
                    </div>
                    <div class="card-panel">
                        <div class="card-scroll" id="pendingTaskBody"></div>
                    </div>
                </div>

                <!-- Completed Section -->
                <div class="task-section completed">
                    <div class="task-section-header">
                        <h3>COMPLETED</h3>
                    </div>
                    <div class="card-panel">
                        <div class="card-scroll" id="completedTaskBody"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Task detail modal -->
    <div class="modal-overlay" id="taskModalOverlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeTaskModal()">&times;</button>
            <h3 id="modalTaskTitle">Task title</h3>
            <div class="modal-row">
                <span class="label">Assigned by</span>
                <span class="value" id="modalWho"></span>
            </div>
            <div class="modal-row">
                <span class="label">Department</span>
                <span class="value" id="modalDept"></span>
            </div>
            <div class="modal-row">
                <span class="label">Due date</span>
                <span class="value" id="modalDue"></span>
            </div>
            <div class="modal-row">
                <span class="label">Status</span>
                <span class="value"><span class="modal-status-pill" id="modalStatusPill"></span></span>
            </div>
            <div class="modal-row" style="display:block;">
                <span class="label">Details</span>
                <p id="modalDescription" style="margin-top:8px; color:#333; font-weight:400; text-align:left;"></p>
            </div>

            <div class="modal-row" style="display:block;">
                <div class="progress-head">
                    <span class="label">Progress</span>
                    <span id="modalProgressValue" class="progress-value">0%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="modalProgressFill"></div>
                </div>
                <input type="range" min="0" max="100" step="5" id="modalProgressSlider"
                       class="progress-slider" oninput="onProgressSliderInput(this.value)">
                <div class="progress-warning" id="modalProgressWarning"></div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn secondary" id="modalUpdateBtn" onclick="saveTaskProgress()">Update progress</button>
                <button class="modal-btn primary" id="modalCompleteBtn" onclick="markTaskComplete()">Mark as completed</button>
            </div>
        </div>
    </div>

    <script src="../js/Employee.js"></script>
    <script>
        // Show dashboard by default
        window.addEventListener("DOMContentLoaded", () => showPage("dashboard"));
        // Close modal on background click
        document.getElementById("taskModalOverlay").addEventListener("click", (e) => {
            if (e.target.id === "taskModalOverlay") closeTaskModal();
        });
    </script>
</body>
</html>