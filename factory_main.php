<?php

session_start();

include('config/connection.php');
include('config/autoLog.php');

// Module
$module = 'factory';
$_REQUEST['module'] = 'factory';

include('config/Supervisor_API.php');

// Security Check
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

if (isset($_GET['activity_log'])) {
    header('Content-Type: application/json; charset=utf-8');
    $production_id = filter_var($_GET['activity_log'], FILTER_VALIDATE_INT);
    if (!$production_id) {
        http_response_code(400);
        echo json_encode(['error' => 'A valid production ID is required.']);
        exit();
    }

    $activity_stmt = $conn->prepare(
        "SELECT actor_name, event_type, work_type, from_employee_name, to_employee_name, occurred_at
         FROM production_activity
         WHERE production_id = ?
         ORDER BY occurred_at DESC, activity_id DESC"
    );
    if (!$activity_stmt) {
        throw new RuntimeException('Unable to prepare production activity history: ' . $conn->error);
    }
    $activity_stmt->bind_param('i', $production_id);
    if (!$activity_stmt->execute()) {
        throw new RuntimeException('Unable to load production activity history: ' . $activity_stmt->error);
    }
    $activity_result = $activity_stmt->get_result();
    $activity_rows = [];
    while ($activity_row = $activity_result->fetch_assoc()) {
        $activity_rows[] = $activity_row;
    }
    $activity_stmt->close();
    echo json_encode(['activities' => $activity_rows], JSON_UNESCAPED_UNICODE);
    exit();
}

// Default values
$pending_records = $pending_records ?? [];
$history_records = $history_records ?? [];
$message = $message ?? '';
$production_assignment_result = null;
$production_assignment_feedback = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'insert') {
    if ($message !== '') {
        $production_assignment_result = 'success';
        $production_assignment_feedback = (string) $message;
    } elseif (isset($errors['assignments'])) {
        $production_assignment_result = 'error';
        $production_assignment_feedback = (string) $errors['assignments'];
    }
}
$production_error = $_SESSION['production_error'] ?? '';
unset($_SESSION['production_error']);

$production_users = [];
$production_team_result = mysqli_query($conn, "
    SELECT DISTINCT
           u.id,
           u.name,
           u.role,
           CASE
               WHEN EXISTS (
                   SELECT 1
                   FROM attendance a
                   WHERE DATE(a.attendance_date) = CURDATE()
                     AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                     AND (
                         a.user_id = u.id
                         OR EXISTS (
                             SELECT 1
                             FROM employee e
                             WHERE e.employee_id = a.employee_id
                               AND (
                                   e.username = u.name
                                   OR (e.email IS NOT NULL AND e.email <> '' AND e.email = u.email)
                                   OR EXISTS (
                                       SELECT 1
                                       FROM attendance employee_user_link
                                       WHERE employee_user_link.employee_id = e.employee_id
                                         AND employee_user_link.user_id = u.id
                                   )
                               )
                         )
                     )
               ) THEN 'Present'
               ELSE 'Absent'
           END AS attendance_status
    FROM users u
    WHERE u.role IN ('pro', 'cooking', 'packaging')
    ORDER BY u.name
");
if (!$production_team_result) {
    throw new RuntimeException('Unable to load production users: ' . mysqli_error($conn));
}
while ($production_user = mysqli_fetch_assoc($production_team_result)) {
    $production_users[] = $production_user;
}
$present_production_count = count(array_filter(
    $production_users,
    static fn ($production_user) => $production_user['attendance_status'] === 'Present'
));
$absent_production_count = count($production_users) - $present_production_count;
$present_cooking_count = count(array_filter(
    $present_production_employees,
    static fn ($employee) => ($employee['production_role'] ?? '') === 'cooking'
));
$present_packaging_count = count(array_filter(
    $present_production_employees,
    static fn ($employee) => ($employee['production_role'] ?? '') === 'packaging'
));
$active_cooking_products = [];
$active_packaging_products = [];
foreach ($pending_records as $record) {
    if (($record['assigned_work_status'] ?? '') !== 'Pending') {
        continue;
    }

    $production_id = (string) ($record['production_id'] ?? '');
    if ($production_id === '') {
        continue;
    }

    if (($record['assigned_work_type'] ?? '') === 'Cooking') {
        $active_cooking_products[$production_id] = true;
    } elseif (
        ($record['assigned_work_type'] ?? '') === 'Packaging'
        && !empty($record['assigned_employee_id'])
    ) {
        $active_packaging_products[$production_id] = true;
    }
}
$active_cooking_count = count($active_cooking_products);
$active_packaging_count = count($active_packaging_products);
$today = date('Y-m-d');
$report_month = $_GET['report_month'] ?? date('Y-m');
$report_error = '';
$active_production_section = isset($_GET['report_month'])
    ? 'reports'
    : 'pending';
$is_valid_date = static function ($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
$is_valid_report_month = static function ($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m', $value);
    return $date !== false && $date->format('Y-m') === $value;
};
if (!$is_valid_report_month($report_month)) {
    $report_error = 'Choose a valid month for the stage report.';
    $report_month = date('Y-m');
}
$report_month_date = DateTimeImmutable::createFromFormat('!Y-m', $report_month);
$report_start = $report_month_date->format('Y-m-01');
$report_end = $report_month_date->modify('last day of this month')->format('Y-m-d');
$report_month_label = $report_month_date->format('F Y');
$stage_reports = [
    'Cooking' => ['completed' => 0, 'pending' => 0, 'overdue' => 0],
    'Packaging' => ['completed' => 0, 'pending' => 0, 'overdue' => 0],
];
$report_stmt = $conn->prepare(
    "SELECT stages.work_type,
            SUM(CASE
                WHEN pa.work_status = 'Done'
                  OR (stages.work_type = 'Packaging' AND p.product_status = 'product done')
                THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE
                WHEN (p.product_status != 'product done' OR p.product_status IS NULL)
                  AND (pa.work_status IS NULL OR pa.work_status != 'Done')
                THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE
                WHEN (p.product_status != 'product done' OR p.product_status IS NULL)
                  AND (pa.work_status IS NULL OR pa.work_status != 'Done')
                  AND p.due_date < CURDATE()
                THEN 1 ELSE 0 END) AS overdue_count
     FROM production p
     CROSS JOIN (
         SELECT 'Cooking' AS work_type
         UNION ALL SELECT 'Packaging'
     ) stages
     LEFT JOIN production_assignments pa
       ON pa.production_id = CAST(p.production_id AS CHAR)
      AND pa.work_type = stages.work_type
     WHERE p.due_date BETWEEN ? AND ?
     GROUP BY stages.work_type"
);
if (!$report_stmt) {
    throw new RuntimeException('Unable to prepare production stage report: ' . $conn->error);
}
$report_stmt->bind_param('ss', $report_start, $report_end);
if (!$report_stmt->execute()) {
    throw new RuntimeException('Unable to load production stage report: ' . $report_stmt->error);
}
$report_result = $report_stmt->get_result();
while ($report_row = $report_result->fetch_assoc()) {
    $stage_reports[$report_row['work_type']] = [
        'completed' => (int) $report_row['completed_count'],
        'pending' => (int) $report_row['pending_count'],
        'overdue' => (int) $report_row['overdue_count'],
    ];
}
$report_stmt->close();
$stage_progress = static function (array $row): array {
    $work_type = (string) ($row['assigned_work_type'] ?? 'Production');
    $status = (string) ($row['assigned_work_status'] ?? 'Pending');
    if ($status === 'Done') {
        return ['key' => 'completed', 'label' => 'Completed'];
    }
    if ($status === 'Waiting for Cooking') {
        return ['key' => 'waiting', 'label' => 'Waiting for Cooking'];
    }
    if ($status === 'Waiting for Packaging') {
        return ['key' => 'ready', 'label' => 'Waiting for Packaging'];
    }
    if (!empty($row['assigned_employee_id'])) {
        return ['key' => 'in_progress', 'label' => $work_type . ' in progress'];
    }
    return ['key' => 'unassigned', 'label' => $work_type . ' unassigned'];
};

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Production Management - TASKTRACK</title>
  <link rel="stylesheet" href="css/Factory_main1.css?v=<?= filemtime(__DIR__ . '/css/Factory_main1.css'); ?>">
  <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
    

    <style>
        /* =========================================================
           MODAL
        ========================================================= */

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 450px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            margin-top: 0;
            text-align: center;
        }

        .modal .production-batch-modal {
            width: min(760px, 94vw);
            max-width: 760px;
            max-height: 94vh;
            margin: 2vh auto 20px;
            overflow-y: auto;
        }

        .production-batch-intro,
        .production-batch-note {
            color: #65746a;
            font-size: 13px;
        }

        .production-batch-list,
        .production-review-list {
            display: grid;
            gap: 12px;
        }

        .production-batch-item {
            padding: 16px;
            border: 1px solid #dce8e0;
            border-radius: 12px;
            background: #f8fbf9;
        }

        .production-batch-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .production-batch-item-header h4 {
            margin: 0;
            color: #244b35;
        }

        .production-batch-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 14px;
        }

        .production-batch-fields .form-group {
            margin-bottom: 12px;
        }

        #productModal .production-batch-fields input,
        #productModal .production-batch-fields select {
            box-sizing: border-box;
            width: 100%;
            min-height: 42px;
            padding: 9px 11px;
            border: 1px solid #d5e0d9;
            border-radius: 9px;
            background: #fff;
            color: #27372e;
            font: inherit;
            font-size: 13px;
        }

        #productModal .production-batch-fields input:focus,
        #productModal .production-batch-fields select:focus {
            outline: 3px solid rgba(31, 110, 74, .14);
            outline-offset: 1px;
            border-color: #28794f;
        }

        .production-batch-fields input::placeholder {
            color: #7a877f;
            opacity: 1;
        }

        .production-batch-add,
        .production-batch-remove {
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid #cfe0d5;
            border-radius: 8px;
            background: #fff;
            color: #276543;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .production-batch-add {
            width: 100%;
            margin-top: 12px;
            border-style: dashed;
            background: #f5faf6;
        }

        .production-batch-remove {
            color: #a3312a;
            border-color: #f1d1cf;
        }

        .production-review-card {
            padding: 12px 14px;
            border: 1px solid #e1eae4;
            border-radius: 9px;
            background: #fff;
            font-size: 13px;
        }

        .production-review-card strong {
            color: #244b35;
        }

        .production-review-card p {
            margin: 5px 0 0;
            color: #536158;
        }

        .production-assignment-review h4 {
            margin: 0 0 6px;
            color: #244b35;
        }

        .production-assignment-review > p {
            color: #65746a;
            font-size: 13px;
        }

        #productionAssignmentReview[hidden],
        #backToProductionAssignments[hidden],
        #confirmProductionAssignments[hidden] {
            display: none !important;
        }

        @media (max-width: 640px) {
            .modal .production-batch-modal {
                width: 94vw;
                max-height: 90vh;
                padding: 16px;
            }

            .production-batch-fields {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .modal-actions {
            text-align: right;
            margin-top: 20px;
        }

        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.85em;
            margin-top: 3px;
        }

        .btn-submit:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .production-loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.82);
        }

        .production-loading-overlay.active {
            display: flex;
        }

        .production-loading-card {
            width: 100%;
            max-width: 400px;
            padding: 28px 24px;
            border-radius: 14px;
            background: #1e293b;
            color: #f8fafc;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .production-loading-card h2 {
            margin-bottom: 8px;
            font-size: 1.35rem;
        }

        .production-loading-card p {
            margin-bottom: 18px;
            color: #cbd5e1;
            font-size: 0.95rem;
        }

        .production-loading-overlay.is-success .production-loading-card {
            border: 1px solid #22c55e;
        }

        .production-loading-overlay.is-success .production-loading-card h2 {
            color: #86efac;
        }

        .production-loading-overlay.is-error .production-loading-card {
            border: 1px solid #f87171;
        }

        .production-loading-overlay.is-error .production-loading-card h2 {
            color: #fca5a5;
        }

        .production-result-dismiss {
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #334155;
            color: #f8fafc;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }

        .production-result-dismiss:hover {
            background: #475569;
        }

        .production-loading-stage {
            position: relative;
            height: 110px;
            overflow: hidden;
            border-bottom: 3px solid #475569;
        }

        .production-loading-status {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            color: #38bdf8;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .production-loading-illustration {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 85px;
        }

        @keyframes productionPress {
            0%, 20%, 70%, 100% { transform: translateY(0); }
            40%, 50% { transform: translateY(9px); }
        }

        @keyframes productionConveyor {
            to { stroke-dashoffset: -16; }
        }

        @keyframes productionIndicator {
            0%, 100% { opacity: 0.35; }
            50% { opacity: 1; }
        }

        .production-loading-overlay.active .production-machine-press {
            animation: productionPress 1.2s ease-in-out 2;
        }

        .production-loading-overlay.active .production-conveyor-belt {
            animation: productionConveyor 0.45s linear 6;
        }

        .production-loading-overlay.active .production-indicator {
            animation: productionIndicator 0.8s ease-in-out 3;
        }

        @media (prefers-reduced-motion: reduce) {
            .production-loading-overlay.active .production-machine-press,
            .production-loading-overlay.active .production-conveyor-belt,
            .production-loading-overlay.active .production-indicator {
                animation-duration: 0.01ms;
                animation-iteration-count: 1;
            }
        }


        /* =========================================================
           PRODUCTION TABS
        ========================================================= */

        .production-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .production-tab {
            border: 1px solid #dfe5e1;
            background: #ffffff;
            color: #555;
            padding: 11px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .production-tab:hover {
            background: #edf5ef;
            color: #1f6e4a;
            border-color: #b9d3c1;
        }

        .production-tab.active {
            background: #1f6e4a;
            color: #ffffff;
            border-color: #1f6e4a;
        }


        /* =========================================================
           PRODUCTION SECTIONS
        ========================================================= */

        .production-section {
            display: none;
        }

        .production-section.active {
            display: block;
        }


        /* =========================================================
           RESPONSIVE TABS
        ========================================================= */

        @media (max-width: 600px) {
            .production-tabs {
                flex-wrap: wrap;
            }

            .production-tab {
                flex: 1;
                text-align: center;
                min-width: 150px;
            }
        }
    </style>
    <link rel="stylesheet" href="css/task-form-controls.css?v=<?= filemtime(__DIR__ . '/css/task-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
</head>


<body>

<div class="app">

    <!-- =========================================================
         SIDEBAR
    ========================================================= -->

    <?php $tasktrackActive = 'production'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>


    <div class="sidebar-overlay" id="sidebarOverlay"></div>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================= -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <button
                class="hamburger"
                id="hamburgerBtn"
                aria-label="Toggle menu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <h1>
                Production Management
            </h1>

            <div class="topbar-actions">

                <button
                    type="button"
                    class="btn-header"
                    onclick="openProductModal()"
                >
                    Add Product
                </button>

            </div>

        </div>


        <!-- =====================================================
             NOTIFICATION
        ====================================================== -->

        <?php if (!empty($message)): ?>

            <div
                style="
                    padding: 10px;
                    background-color: #d4edda;
                    color: #155724;
                    margin: 15px 0;
                    border-radius: 4px;
                "
            >
                <?= htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <?php if ($production_error !== ''): ?>
            <div role="alert" style="padding: 10px; background-color: #f8d7da; color: #842029; margin: 15px 0; border-radius: 4px;">
                <?= htmlspecialchars($production_error); ?>
            </div>
        <?php endif; ?>


        <!-- =====================================================
             CONTENT
        ====================================================== -->

        <div class="content">

            <section class="production-team-panel" aria-labelledby="productionUsersTitle">
                <div class="production-team-heading">
                    <div>
                        <p class="production-team-eyebrow">TEAM OVERVIEW</p>
                        <h2 id="productionUsersTitle" class="section-title">Production Staff</h2>
                        <p>Staff roster and attendance for <?= htmlspecialchars(date('F j, Y')); ?>.</p>
                    </div>
                    <div class="production-team-actions">
                        <button
                            type="button"
                            class="production-staff-toggle"
                            id="productionStaffToggle"
                            aria-expanded="false"
                            aria-controls="productionStaffRoster"
                            onclick="toggleProductionStaff()"
                        >
                            View Staff
                        </button>
                        <div class="production-team-summary" aria-label="Production team attendance summary">
                            <div class="production-team-stat is-total">
                                <span class="production-team-stat-value"><?= count($production_users); ?></span>
                                <span class="production-team-stat-label">Total staff</span>
                            </div>
                            <div class="production-team-stat is-present">
                                <span class="production-team-stat-value"><?= $present_production_count; ?></span>
                                <span class="production-team-stat-label">Present</span>
                            </div>
                            <div class="production-team-stat is-absent">
                                <span class="production-team-stat-value"><?= $absent_production_count; ?></span>
                                <span class="production-team-stat-label">Absent</span>
                            </div>
                            <div class="production-team-stat is-cooking">
                                <span class="production-team-stat-value"><?= $active_cooking_count; ?></span>
                                <span class="production-team-stat-label">In Cooking</span>
                            </div>
                            <div class="production-team-stat is-packaging">
                                <span class="production-team-stat-value"><?= $active_packaging_count; ?></span>
                                <span class="production-team-stat-label">In Packaging</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="productionStaffRoster" hidden>
                    <?php if (!empty($production_users)): ?>
                        <div class="production-user-list">
                            <?php foreach ($production_users as $production_user): ?>
                                <?php $is_present = $production_user['attendance_status'] === 'Present'; ?>
                                <article class="production-user-item">
                                    <span class="production-user-avatar" aria-hidden="true">
                                        <?= htmlspecialchars(strtoupper(substr($production_user['name'] ?? 'U', 0, 1))); ?>
                                    </span>
                                    <span class="production-user-details">
                                        <span class="production-user-name">
                                            <?= htmlspecialchars($production_user['name'] ?? 'Unnamed production user'); ?>
                                        </span>
                                        <span class="production-user-role"><?= htmlspecialchars(ucfirst($production_user['role'] ?? 'Production')); ?></span>
                                    </span>
                                    <span class="production-attendance <?= $is_present ? 'is-present' : 'is-absent'; ?>">
                                        <span class="production-attendance-dot" aria-hidden="true"></span>
                                        <?= htmlspecialchars($production_user['attendance_status']); ?>
                                    </span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-message">No production users found.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="production-filters" aria-label="Filter production tasks">
                <label>
                    <span>Search</span>
                    <input type="search" id="productionSearch" placeholder="Product ID, name, worker, or status">
                </label>
                <label>
                    <span>Stage</span>
                    <select id="productionStageFilter">
                        <option value="">All stages</option>
                        <option value="Cooking">Cooking</option>
                        <option value="Packaging">Packaging</option>
                    </select>
                </label>
                <label>
                    <span>Status</span>
                    <select id="productionStatusFilter">
                        <option value="">All statuses</option>
                        <option value="in_progress">In progress</option>
                        <option value="ready">Waiting for Packaging</option>
                        <option value="waiting">Waiting for Cooking</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="completed">Completed</option>
                    </select>
                </label>
                <label>
                    <span>Due from</span>
                    <input type="date" id="productionDueFrom" class="calendar-control">
                </label>
                <label>
                    <span>Due to</span>
                    <input type="date" id="productionDueTo" class="calendar-control">
                </label>
                <button type="button" class="production-filter-reset" id="resetProductionFilters">Reset filters</button>
            </section>


            <!-- =================================================
                 PRODUCTION TABS
            ================================================== -->

            <div class="production-tabs">

                <button
                    type="button"
                    class="production-tab <?= $active_production_section === 'pending' ? 'active' : ''; ?>"
                    id="pendingProductionTab"
                    onclick="showProductionSection('pending')"
                >
                    Pending Production Tasks
                </button>

                <button
                    type="button"
                    class="production-tab <?= $active_production_section === 'history' ? 'active' : ''; ?>"
                    id="historyProductionTab"
                    onclick="showProductionSection('history')"
                >
                    Production History (Completed)
                </button>

                <button
                    type="button"
                    class="production-tab <?= $active_production_section === 'reports' ? 'active' : ''; ?>"
                    id="productionReportsTab"
                    onclick="showProductionSection('reports')"
                >
                    Stage Reports
                </button>

            </div>


            <!-- =================================================
                 PENDING PRODUCTION TASKS
            ================================================== -->

            <div
                id="pendingProductionSection"
                class="production-section <?= $active_production_section === 'pending' ? 'active' : ''; ?>"
            >

                <h2 class="section-title">
                    Pending Production Tasks
                </h2>


                <?php if (!empty($pending_records)): ?>

                    <div class="table-wrap table-scroll">

                        <table>

                            <thead>

                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Target PCS</th>
                                    <th>Stock Number</th>
                                    <th>Quantity</th>
                                    <th>Due Date</th>
                                    <th>Assigned Employee</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($pending_records as $row): ?>
                                    <?php
                                    $progress = $stage_progress($row);
                                    $work_type = (string) ($row['assigned_work_type'] ?? '');
                                    $due_date_value = (string) ($row['due_date'] ?? '');
                                    $deadline_class = '';
                                    if ($progress['key'] !== 'completed' && $is_valid_date($due_date_value)) {
                                        $days_until_due = (int) (new DateTimeImmutable($today))
                                            ->diff(new DateTimeImmutable($due_date_value))
                                            ->format('%r%a');
                                        $deadline_class = $days_until_due < 0
                                            ? 'is-overdue'
                                            : ($days_until_due <= 3 ? 'is-due-soon' : '');
                                    }
                                    ?>
                                    <tr
                                        class="production-task-row <?= $deadline_class; ?>"
                                        data-stage="<?= htmlspecialchars($work_type, ENT_QUOTES); ?>"
                                        data-status="<?= htmlspecialchars($progress['key'], ENT_QUOTES); ?>"
                                        data-due-date="<?= htmlspecialchars($due_date_value, ENT_QUOTES); ?>"
                                    >

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['production_id'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['product_name'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['target_pcs'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['Stock_number'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['quantity'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $due_date_value
                                            ); ?>
                                            <?php if ($deadline_class === 'is-overdue'): ?>
                                                <span class="production-deadline-badge is-overdue">Overdue</span>
                                            <?php elseif ($deadline_class === 'is-due-soon'): ?>
                                                <span class="production-deadline-badge is-due-soon">Due soon</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?>:
                                            <?php if (!empty($row['assigned_employee_name'])): ?>
                                                <?= htmlspecialchars($row['assigned_employee_name']); ?>
                                                <small>(<?= htmlspecialchars($row['assigned_work_status'] ?? 'Pending'); ?>)</small>
                                            <?php else: ?>
                                                <span>Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-pill status-<?= htmlspecialchars($progress['key'], ENT_QUOTES); ?>">
                                                <?= htmlspecialchars($progress['label']); ?>
                                            </span>
                                        </td>

                                        <td class="action-cell">
                                            <button
                                                type="button"
                                                class="edit-btn"
                                                onclick="openProductionActivity('<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>', '<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>')"
                                            >Activity</button>

                                            <button
                                                type="button"
                                                class="edit-btn"
                                                data-production-id="<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>"
                                                data-product-name="<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>"
                                                data-assigned-employee="<?= htmlspecialchars($row['assigned_employee_id'] ?? '', ENT_QUOTES); ?>"
                                                data-assigned-work-type="<?= htmlspecialchars($row['assigned_work_type'] ?? '', ENT_QUOTES); ?>"
                                                data-assigned-work-status="<?= htmlspecialchars($row['assigned_work_status'] ?? 'Pending', ENT_QUOTES); ?>"
                                                onclick="openReassignmentModal(this)"
                                                <?= in_array($row['assigned_work_status'] ?? '', ['Done', 'Waiting for Cooking'], true) ? 'disabled' : ''; ?>
                                            >
                                                <?= ($row['assigned_work_status'] ?? 'Pending') === 'Done'
                                                    ? 'Completed'
                                                    : (($row['assigned_work_status'] ?? '') === 'Waiting for Cooking'
                                                        ? 'Waiting for Cooking'
                                                        : (empty($row['assigned_employee_id']) ? 'Assign' : 'Reassign')); ?>
                                            </button>

                                            <!-- UPDATE -->

                                            <button
                                                type="button"
                                                class="edit-btn"
                                                onclick="openUpdateModal(
                                                    '<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>',
                                                    '<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>',
                                                    '<?= htmlspecialchars($row['target_pcs'] ?? '', ENT_QUOTES); ?>',
                                                    '<?= htmlspecialchars($row['Stock_number'] ?? '', ENT_QUOTES); ?>',
                                                    '<?= htmlspecialchars($row['quantity'] ?? '', ENT_QUOTES); ?>',
                                                    '<?= htmlspecialchars($row['due_date'] ?? '', ENT_QUOTES); ?>'
                                                )"
                                            >
                                                Update
                                            </button>


                                            <!-- DELETE -->

                                            <form
                                                action="factory_main.php"
                                                method="post"
                                                style="display:inline;"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="idno"
                                                    value="<?= htmlspecialchars(
                                                        $row['production_id'] ?? ''
                                                    ); ?>"
                                                >

                                                <input
                                                    type="submit"
                                                    name="del"
                                                    value="Delete"
                                                    class="remove-btn"
                                                    onclick="return confirm(
                                                        'Are you sure you want to delete this record?'
                                                    );"
                                                >

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty-message">
                        No pending production records found.
                    </p>

                <?php endif; ?>
                <p class="empty-message" id="pendingFilterEmpty" hidden>No tasks match the selected filters.</p>

            </div>


            <!-- =================================================
                 PRODUCTION HISTORY
            ================================================== -->

            <div
                id="historyProductionSection"
                class="production-section <?= $active_production_section === 'history' ? 'active' : ''; ?>"
            >

                <h2 class="section-title history-title">
                    Production History (Completed)
                </h2>


                <?php if (!empty($history_records)): ?>

                    <div class="table-wrap table-wrap-history table-scroll">

                        <table>

                            <thead>

                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Target PCS</th>
                                    <th>Stock Number</th>
                                    <th>Quantity</th>
                                    <th>Due Date</th>
                                    <th>Assigned Employee</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($history_records as $row): ?>
                                    <?php
                                    $progress = $stage_progress($row);
                                    $work_type = (string) ($row['assigned_work_type'] ?? '');
                                    $due_date_value = (string) ($row['due_date'] ?? '');
                                    ?>
                                    <tr
                                        class="production-task-row"
                                        data-stage="<?= htmlspecialchars($work_type, ENT_QUOTES); ?>"
                                        data-status="<?= htmlspecialchars($progress['key'], ENT_QUOTES); ?>"
                                        data-due-date="<?= htmlspecialchars($due_date_value, ENT_QUOTES); ?>"
                                    >

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['production_id'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['product_name'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['target_pcs'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['Stock_number'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $row['quantity'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $due_date_value
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?>:
                                            <?php if (!empty($row['assigned_employee_name'])): ?>
                                                <?= htmlspecialchars($row['assigned_employee_name']); ?>
                                                <small>(<?= htmlspecialchars($row['assigned_work_status'] ?? 'Pending'); ?>)</small>
                                            <?php else: ?>
                                                <span>Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-pill status-completed">
                                                <?= htmlspecialchars($progress['label']); ?>
                                            </span>
                                        </td>

                                        <td class="action-cell">
                                            <button
                                                type="button"
                                                class="edit-btn"
                                                onclick="openProductionActivity('<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>', '<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>')"
                                            >Activity</button>

                                            <form
                                                action="factory_main.php"
                                                method="post"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="idno"
                                                    value="<?= htmlspecialchars(
                                                        $row['production_id'] ?? ''
                                                    ); ?>"
                                                >

                                                <input
                                                    type="submit"
                                                    name="del"
                                                    value="Delete"
                                                    class="remove-btn"
                                                    onclick="return confirm(
                                                        'Are you sure you want to delete this record?'
                                                    );"
                                                >

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty-message">
                        No completed production records found.
                    </p>

                <?php endif; ?>
                <p class="empty-message" id="historyFilterEmpty" hidden>No tasks match the selected filters.</p>

            </div>

            <section
                id="productionReportsSection"
                class="production-section <?= $active_production_section === 'reports' ? 'active' : ''; ?>"
                aria-labelledby="productionReportsTitle"
            >
                <h2 id="productionReportsTitle" class="section-title history-title">Stage Reports</h2>
                <form class="production-report-filters" method="get" action="factory_main.php">
                    <label>
                        <span>Report month</span>
                        <input type="month" name="report_month" class="calendar-control" value="<?= htmlspecialchars($report_month, ENT_QUOTES); ?>" required>
                    </label>
                    <button type="submit" class="btn-submit">View Report</button>
                </form>
                <?php if ($report_error !== ''): ?>
                    <p class="production-report-error" role="alert"><?= htmlspecialchars($report_error); ?></p>
                <?php endif; ?>
                <p class="production-report-note">
                    Showing stage counts for products due in <?= htmlspecialchars($report_month_label); ?>.
                </p>
                <div class="table-wrap table-scroll">
                    <table class="production-report-table">
                        <thead>
                            <tr>
                                <th>Stage</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stage_reports as $work_type => $counts): ?>
                                <tr>
                                    <td><?= htmlspecialchars($work_type); ?></td>
                                    <td><?= $counts['completed']; ?></td>
                                    <td><?= $counts['pending']; ?></td>
                                    <td><?= $counts['overdue']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

    </main>

</div>


<div id="productionActivityModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="productionActivityTitle">
    <div class="modal-content production-activity-modal">
        <button type="button" class="production-activity-close" aria-label="Close activity history" onclick="closeProductionActivity()">×</button>
        <h3 id="productionActivityTitle">Production Activity</h3>
        <p id="productionActivityProduct" class="production-activity-product"></p>
        <div id="productionActivityContent" aria-live="polite">
            <p class="production-report-note">Loading activity history...</p>
        </div>
    </div>
</div>


<!-- =============================================================
     ADD PRODUCT MODAL
============================================================= -->

<div
    id="productModal"
    class="modal"
    style="<?= !empty($errors) && $action === 'insert' ? 'display:block;' : '' ?>"
>

    <div class="modal-content production-batch-modal">
        <h3>Add Production Tasks</h3>

        <form id="addProductForm" action="factory_main.php" method="POST">
            <input type="hidden" name="action" value="insert">
            <input type="hidden" name="module" value="factory">

            <div id="productionAssignmentEditor">
                <p class="production-batch-intro">
                    Add the products you want to assign. Each product can have its own Cooking employee and production details.
                </p>
                <?php if (isset($errors['assignments']) && $action === 'insert'): ?>
                    <div class="error-message" role="alert"><?= htmlspecialchars($errors['assignments'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div id="productionBatchItems" class="production-batch-list"></div>
                <button type="button" id="addProductionBatchItem" class="production-batch-add">
                    + Add another product
                </button>
                <p class="production-batch-note">Packaging becomes available for assignment after Cooking is marked done.</p>

                <script type="application/json" id="productionAssignmentData"><?=
                json_encode([
                    'products' => $allowed_products,
                    'employees' => array_values(array_map(static function ($employee) {
                        return [
                            'id' => (string) $employee['employee_id'],
                            'name' => (string) ($employee['username'] ?: 'Employee #' . $employee['employee_id']),
                        ];
                    }, array_filter($present_production_employees, static function ($employee) {
                        return ($employee['production_role'] ?? '') === 'cooking';
                    }))),
                    'assignments' => array_values(is_array($_POST['assignments'] ?? null) ? $_POST['assignments'] : []),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?></script>
            </div>

            <section id="productionAssignmentReview" class="production-assignment-review" hidden aria-labelledby="productionReviewTitle">
                <h4 id="productionReviewTitle">Review production assignments</h4>
                <p>Check each product, stock number, quantity, due date, and Cooking employee before confirming.</p>
                <div id="productionReviewItems" class="production-review-list"></div>
            </section>

            <div class="modal-actions">
                <button type="submit" name="submit" id="reviewProductionAssignments" class="btn-submit"
                    <?= $present_cooking_count === 0 ? 'disabled' : ''; ?>>
                    Review assignments
                </button>
                <button type="button" id="backToProductionAssignments" class="btn-cancel" hidden>
                    Back to edit
                </button>
                <button type="submit" name="submit" id="confirmProductionAssignments" class="btn-submit" hidden>
                    Confirm and assign
                </button>
                <button type="button" class="btn-cancel" onclick="closeProductModal()">Cancel</button>
            </div>
        </form>
    </div>

</div>

<div
    id="productionAssignmentLoading"
    class="production-loading-overlay<?= $production_assignment_result !== null ? ' active is-' . $production_assignment_result : ''; ?>"
    role="status"
    aria-live="polite"
    aria-busy="false"
    aria-hidden="<?= $production_assignment_result === null ? 'true' : 'false'; ?>"
>
    <div class="production-loading-card">
        <h2><?= $production_assignment_result === 'success' ? 'Production assigned!' : ($production_assignment_result === 'error' ? 'Assignment not completed' : 'Processing Production'); ?></h2>
        <p><?= $production_assignment_result !== null
            ? htmlspecialchars($production_assignment_feedback, ENT_QUOTES, 'UTF-8')
            : 'Please wait while the production task is being prepared.'; ?></p>
        <?php if ($production_assignment_result === 'error'): ?>
            <button type="button" class="production-result-dismiss" id="dismissProductionResult">Back to assignments</button>
        <?php elseif ($production_assignment_result !== 'success'): ?>
        <div class="production-loading-stage">
            <div class="production-loading-status">Manufacturing product...</div>
            <svg class="production-loading-illustration" viewBox="0 0 300 80" aria-hidden="true">
                <path d="M25 64H275M25 72H275" stroke="#64748b" stroke-width="3" />
                <path class="production-conveyor-belt" d="M35 68H265" fill="none" stroke="#38bdf8" stroke-width="2" stroke-dasharray="8 8" />
                <g fill="#1e293b" stroke="#94a3b8" stroke-width="2">
                    <circle cx="48" cy="68" r="6" />
                    <circle cx="88" cy="68" r="6" />
                    <circle cx="128" cy="68" r="6" />
                    <circle cx="168" cy="68" r="6" />
                    <circle cx="208" cy="68" r="6" />
                    <circle cx="248" cy="68" r="6" />
                </g>
                <path d="M108 12H192V20H108ZM116 20V59M184 20V59" fill="#475569" stroke="#94a3b8" stroke-width="3" />
                <g class="production-machine-press">
                    <path d="M145 20V34M155 20V34" stroke="#cbd5e1" stroke-width="4" />
                    <path d="M135 34H165V40H135Z" fill="#f59e0b" stroke="#fde68a" stroke-width="2" />
                </g>
                <rect x="138" y="44" width="24" height="18" rx="2" fill="#0ea5e9" stroke="#bae6fd" stroke-width="2" />
                <path d="M150 45V61M139 52H161" stroke="#e0f2fe" stroke-width="2" />
                <circle class="production-indicator" cx="202" cy="16" r="4" fill="#4ade80" />
            </svg>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- =============================================================
     REASSIGN PRODUCTION TASK MODAL
============================================================= -->

<div
    id="reassignmentModal"
    class="modal"
    style="<?= !empty($errors) && $action === 'reassign' ? 'display:block;' : '' ?>"
>
    <div class="modal-content">
        <h3 id="reassignmentTitle">Assign Production Stage</h3>

        <form action="factory_main.php" method="POST">
            <input type="hidden" name="module" value="factory">
            <input type="hidden" name="action" value="reassign">
            <input type="hidden" name="production_id" id="reassign_production_id" value="<?= htmlspecialchars($_POST['production_id'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="work_type" id="reassign_work_type" value="<?= htmlspecialchars($_POST['work_type'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="product_name" id="reassign_product_name_input" value="<?= htmlspecialchars($_POST['product_name'] ?? '', ENT_QUOTES); ?>">

            <div class="form-group">
                <label>Production Task:</label>
                <div id="reassign_product_name"><?= htmlspecialchars($_POST['product_name'] ?? 'Select a task from the list.', ENT_QUOTES); ?></div>
            </div>

            <div class="form-group">
                <label for="reassign_target">Present Employee for <span id="reassign_work_type_label"><?= htmlspecialchars($_POST['work_type'] ?? 'selected stage'); ?></span>:</label>
                <select name="employee_id" id="reassign_target" class="production-assignment-select" required>
                    <option value="">-- Select Employee --</option>
                    <?php foreach ($present_production_employees as $employee): ?>
                        <option
                            value="<?= (int) $employee['employee_id']; ?>"
                            data-role="<?= htmlspecialchars($employee['production_role'] ?? '', ENT_QUOTES); ?>"
                            <?= (string) ($_POST['employee_id'] ?? '') === (string) $employee['employee_id'] ? 'selected' : ''; ?>
                        >
                            <?= htmlspecialchars($employee['username'] ?: 'Employee #' . $employee['employee_id']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($present_cooking_count === 0): ?>
                        <option value="" disabled data-role="cooking-empty" hidden>No Cooking accounts are currently present</option>
                    <?php endif; ?>
                    <?php if ($present_packaging_count === 0): ?>
                        <option value="" disabled data-role="packaging-empty" hidden>No Packaging accounts are currently present</option>
                    <?php endif; ?>
                </select>
                <p id="assignmentEligibilityMessage" class="error-message" role="status" hidden></p>
                <?php if (isset($errors['assignment_target']) && $action === 'reassign'): ?>
                    <div class="error-message"><?= htmlspecialchars($errors['assignment_target']); ?></div>
                <?php endif; ?>
                <?php if (isset($errors['assignment']) && $action === 'reassign'): ?>
                    <div class="error-message"><?= htmlspecialchars($errors['assignment']); ?></div>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button type="submit" name="submit" id="saveAssignmentButton" class="btn-submit" <?= empty($present_production_employees) ? 'disabled' : ''; ?>>
                    Save Assignment
                </button>
                <button type="button" class="btn-cancel" onclick="closeReassignmentModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =============================================================
     UPDATE PRODUCT MODAL
============================================================= -->

<div id="updateModal" class="modal">

    <div class="modal-content">

        <h3>
            Update Production Task
        </h3>


        <form
            action="factory_main.php"
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="update"
            >

            <input
                type="hidden"
                name="module"
                value="factory"
            >

            <input
                type="hidden"
                name="production_id"
                id="update_id"
            >


            <!-- PRODUCT NAME -->

            <div class="form-group">

                <label>
                    Product Name:
                </label>

                <select
                    name="product_name"
                    id="update_product_name"
                    class="form-select"
                    required
                >

                    <option
                        value=""
                        disabled
                    >
                        -- Select Product --
                    </option>

                    <option value="Ginga Turmeric Brew">
                        Ginga Turmeric Brew
                    </option>

                    <option value="Ginga Turmeric w/ Guyabano">
                        Ginga Turmeric w/ Guyabano
                    </option>

                    <option value="Ginga Turmeric w/ Lemon">
                        Ginga Turmeric w/ Lemon
                    </option>

                    <option value="Ginga Ginger - Regural Pouch">
                        Ginga Ginger - Regural Pouch
                    </option>

                    <option value="Ginga Ginger Brew with Turmeric And Lemon">
                        Ginga Ginger Brew with Turmeric And Lemon
                    </option>

                    <option value="Ginga Ginger - Strong">
                        Ginga Ginger - Strong
                    </option>

                    <option value="Ginga Ginger - Regural">
                        Ginga Ginger - Regural
                    </option>

                    <option value="Ginga Ginger Pure Tea">
                        Ginga Ginger Pure Tea
                    </option>

                    <option value="Ginga Turmeric Pure Tea">
                        Ginga Turmeric Pure Tea
                    </option>

                    <option value="Ginga Mangosteen Pure Tea">
                        Ginga Mangosteen Pure Tea
                    </option>

                    <option value="Ginga Guyabano Pure Tea">
                        Ginga Guyabano Pure Tea
                    </option>

                    <option value="Ginga Butterfly Pea Tea">
                        Ginga Butterfly Pea Tea
                    </option>

                    <option value="Herbal Green Tea">
                        Herbal Green Tea
                    </option>

                </select>

            </div>


            <!-- TARGET PCS -->

            <div class="form-group">

                <label>
                    Target PCS:
                </label>

                <input
                    type="text"
                    name="target_pcs"
                    id="update_target_pcs"
                    required
                >

            </div>


            <!-- STOCK NUMBER -->

            <div class="form-group">

                <label>
                    Stock Number:
                </label>

                <input
                    type="text"
                    name="stock_number"
                    id="update_stock_number"
                    required
                >

            </div>


            <!-- QUANTITY -->

            <div class="form-group">

                <label>
                    Quantity:
                </label>

                <input
                    type="text"
                    name="quantity"
                    id="update_quantity"
                    required
                >

            </div>


            <!-- DUE DATE -->

            <div class="form-group">

                <label for="insert_due_date">
                    Due Date:
                </label>

                <input
                    type="date"
                    name="due_date"
                    id="insert_due_date"
                    class="calendar-control"
                    required
                >

            </div>


            <!-- ACTIONS -->

            <div class="modal-actions">

                <button
                    type="submit"
                    name="submit"
                    class="btn-submit"
                >
                    Save Changes
                </button>

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeUpdateModal()"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>

/* =============================================================
   SIDEBAR / HAMBURGER
============================================================= */

(function () {

    const btn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!btn || !sidebar || !overlay) {
        return;
    }

    function toggle() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }

    btn.addEventListener('click', toggle);
    overlay.addEventListener('click', toggle);

})();


/* =============================================================
   PRODUCTION TAB SWITCHING
============================================================= */

function showProductionSection(section) {
    const sections = {
        pending: document.getElementById('pendingProductionSection'),
        history: document.getElementById('historyProductionSection'),
        reports: document.getElementById('productionReportsSection')
    };
    const tabs = {
        pending: document.getElementById('pendingProductionTab'),
        history: document.getElementById('historyProductionTab'),
        reports: document.getElementById('productionReportsTab')
    };

    Object.entries(sections).forEach(([name, element]) => {
        element.classList.toggle('active', name === section);
        tabs[name].classList.toggle('active', name === section);
    });
}

function toggleProductionStaff() {
    const roster = document.getElementById('productionStaffRoster');
    const toggle = document.getElementById('productionStaffToggle');
    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

    roster.hidden = isExpanded;
    toggle.setAttribute('aria-expanded', String(!isExpanded));
    toggle.textContent = isExpanded ? 'View Staff' : 'Hide Staff';
}

function applyProductionFilters() {
    const search = document.getElementById('productionSearch').value.trim().toLowerCase();
    const stage = document.getElementById('productionStageFilter').value;
    const status = document.getElementById('productionStatusFilter').value;
    const dueFrom = document.getElementById('productionDueFrom').value;
    const dueTo = document.getElementById('productionDueTo').value;
    const pendingRows = document.querySelectorAll('#pendingProductionSection .production-task-row');
    const historyRows = document.querySelectorAll('#historyProductionSection .production-task-row');

    const filterRows = (rows, emptyMessage) => {
        let visibleCount = 0;
        rows.forEach((row) => {
            const matches =
                (!search || row.textContent.toLowerCase().includes(search)) &&
                (!stage || row.dataset.stage === stage) &&
                (!status || row.dataset.status === status) &&
                (!dueFrom || row.dataset.dueDate >= dueFrom) &&
                (!dueTo || row.dataset.dueDate <= dueTo);
            row.hidden = !matches;
            if (matches) visibleCount += 1;
        });
        emptyMessage.hidden = rows.length === 0 || visibleCount > 0;
    };

    filterRows(pendingRows, document.getElementById('pendingFilterEmpty'));
    filterRows(historyRows, document.getElementById('historyFilterEmpty'));
}

[
    'productionSearch',
    'productionStageFilter',
    'productionStatusFilter',
    'productionDueFrom',
    'productionDueTo'
].forEach((id) => {
    document.getElementById(id).addEventListener('input', applyProductionFilters);
});
document.getElementById('resetProductionFilters').addEventListener('click', () => {
    document.getElementById('productionSearch').value = '';
    document.getElementById('productionStageFilter').value = '';
    document.getElementById('productionStatusFilter').value = '';
    document.getElementById('productionDueFrom').value = '';
    document.getElementById('productionDueTo').value = '';
    document.getElementById('productionDueFrom').dispatchEvent(new Event('input', { bubbles: true }));
    document.getElementById('productionDueTo').dispatchEvent(new Event('input', { bubbles: true }));
    applyProductionFilters();
});

async function openProductionActivity(productionId, productName) {
    const modal = document.getElementById('productionActivityModal');
    const content = document.getElementById('productionActivityContent');
    document.getElementById('productionActivityProduct').textContent =
        `Product #${productionId} — ${productName}`;
    content.innerHTML = '<p class="production-report-note">Loading activity history...</p>';
    modal.style.display = 'block';

    try {
        const response = await fetch(`factory_main.php?activity_log=${encodeURIComponent(productionId)}`, {
            headers: { Accept: 'application/json' }
        });
        const result = await response.json();
        if (!response.ok || result.error) {
            throw new Error(result.error || 'Unable to load activity history.');
        }

        content.replaceChildren();
        if (result.activities.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'production-report-note';
            empty.textContent = 'No recorded activity for this product yet. New actions will appear here.';
            content.appendChild(empty);
            return;
        }

        const list = document.createElement('ol');
        list.className = 'production-activity-list';
        result.activities.forEach((activity) => {
            const item = document.createElement('li');
            const summary = document.createElement('strong');
            const stageName = activity.work_type ? `${activity.work_type} stage` : 'product';
            const targetName = activity.to_employee_name || 'an employee';
            const fromName = activity.from_employee_name || 'an employee';
            const messages = {
                product_created: `${activity.actor_name} created this product and assigned Cooking to ${targetName}.`,
                stage_assigned: `${activity.actor_name} assigned ${stageName} to ${targetName}.`,
                stage_reassigned: `${activity.actor_name} reassigned ${stageName} from ${fromName} to ${targetName}.`,
                stage_completed: `${activity.actor_name} marked the ${stageName} as done.`,
                product_updated: `${activity.actor_name} updated this product.`
            };
            summary.textContent = messages[activity.event_type] || `${activity.actor_name} updated this production task.`;
            const time = document.createElement('time');
            time.dateTime = activity.occurred_at.replace(' ', 'T');
            time.textContent = activity.occurred_at;
            item.append(summary, time);
            list.appendChild(item);
        });
        content.appendChild(list);
    } catch (error) {
        content.replaceChildren();
        const failure = document.createElement('p');
        failure.className = 'production-report-error';
        failure.textContent = error.message || 'Unable to load activity history.';
        content.appendChild(failure);
    }
}

function closeProductionActivity() {
    document.getElementById('productionActivityModal').style.display = 'none';
}

/* =============================================================
   ADD PRODUCT MODAL
============================================================= */
function openProductModal() {
    document.getElementById('productModal').style.display = 'block';
}
function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
}

const addProductForm = document.getElementById('addProductForm');
if (addProductForm) {
    const assignmentData = JSON.parse(document.getElementById('productionAssignmentData').textContent);
    const assignmentEditor = document.getElementById('productionAssignmentEditor');
    const assignmentItems = document.getElementById('productionBatchItems');
    const assignmentReview = document.getElementById('productionAssignmentReview');
    const reviewItems = document.getElementById('productionReviewItems');
    const reviewButton = document.getElementById('reviewProductionAssignments');
    const backButton = document.getElementById('backToProductionAssignments');
    const confirmButton = document.getElementById('confirmProductionAssignments');

    function reindexProductionBatchItems() {
        assignmentItems.querySelectorAll('.production-batch-item').forEach((item, index) => {
            item.querySelector('h4').textContent = `Product ${index + 1}`;
            item.querySelectorAll('[data-field]').forEach((field) => {
                field.name = `assignments[${index}][${field.dataset.field}]`;
            });
            item.querySelector('.production-batch-remove').hidden =
                assignmentItems.querySelectorAll('.production-batch-item').length === 1;
        });
    }

    function addProductionBatchItem(values = {}) {
        const item = document.createElement('article');
        item.className = 'production-batch-item';
        const header = document.createElement('div');
        header.className = 'production-batch-item-header';
        const heading = document.createElement('h4');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'production-batch-remove';
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', () => {
            item.remove();
            reindexProductionBatchItems();
        });
        header.append(heading, removeButton);
        item.appendChild(header);

        const fields = document.createElement('div');
        fields.className = 'production-batch-fields';
        const definitions = [
            { key: 'product_name', label: 'Product', type: 'select', placeholder: '-- Select Product --', options: assignmentData.products },
            { key: 'cooking_employee_id', label: 'Cooking Employee', type: 'select', placeholder: '-- Assign Cooking --', options: assignmentData.employees },
            { key: 'target_pcs', label: 'Target PCS', type: 'number', placeholder: 'Enter target pieces', min: '0', step: '1' },
            { key: 'Stock_number', label: 'Stock Number', type: 'text', placeholder: '4-digit stock number', inputMode: 'numeric', pattern: '[0-9]{4}', maxLength: 4 },
            { key: 'quantity', label: 'Quantity', type: 'number', placeholder: 'Enter quantity', min: '0', step: '1' },
            { key: 'due_date', label: 'Due Date', type: 'date', calendar: true }
        ];

        definitions.forEach((definition) => {
            const group = document.createElement('div');
            group.className = 'form-group';
            const label = document.createElement('label');
            label.textContent = definition.label;
            const field = definition.type === 'select'
                ? document.createElement('select')
                : document.createElement('input');
            field.dataset.field = definition.key;
            field.required = true;

            if (definition.type === 'select') {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = definition.placeholder;
                field.appendChild(placeholder);
                definition.options.forEach((optionData) => {
                    const option = document.createElement('option');
                    option.value = definition.key === 'product_name' ? optionData : optionData.id;
                    option.textContent = definition.key === 'product_name' ? optionData : optionData.name;
                    field.appendChild(option);
                });
                field.value = String(values[definition.key] || '');
            } else {
                field.type = definition.type;
                field.placeholder = definition.placeholder || '';
                if (definition.min !== undefined) field.min = definition.min;
                if (definition.step !== undefined) field.step = definition.step;
                if (definition.inputMode) field.inputMode = definition.inputMode;
                if (definition.pattern) field.pattern = definition.pattern;
                if (definition.maxLength) field.maxLength = definition.maxLength;
                if (definition.calendar) field.className = 'calendar-control';
                field.value = String(values[definition.key] || '');
            }
            label.appendChild(field);
            group.appendChild(label);
            fields.appendChild(group);
        });

        item.appendChild(fields);
        assignmentItems.appendChild(item);
        reindexProductionBatchItems();
    }

    const initialAssignments = Array.isArray(assignmentData.assignments)
        ? assignmentData.assignments
        : [];
    if (initialAssignments.length) {
        initialAssignments.forEach((assignment) => addProductionBatchItem(assignment));
    } else {
        addProductionBatchItem();
    }

    document.getElementById('addProductionBatchItem').addEventListener('click', () => {
        addProductionBatchItem();
    });

    function validateProductionBatch() {
        const invalidField = Array.from(assignmentItems.querySelectorAll('[data-field]')).find((field) => (
            field.dataset.field === 'due_date'
                ? !field.value
                : !field.checkValidity()
        ));
        if (!invalidField) return true;

        if (invalidField.dataset.field === 'due_date') {
            const picker = invalidField.closest('.calendar-picker');
            const trigger = picker?.querySelector('.calendar-picker__trigger');
            picker?.classList.add('is-invalid');
            trigger?.setAttribute('aria-invalid', 'true');
            trigger?.focus();
            trigger?.click();
        } else {
            invalidField.reportValidity();
        }
        return false;
    }

    function showProductionAssignmentReview() {
        reviewItems.replaceChildren();
        assignmentItems.querySelectorAll('.production-batch-item').forEach((item, index) => {
            const fieldValue = (name) => item.querySelector(`[data-field="${name}"]`).value;
            const selectedEmployee = item.querySelector('[data-field="cooking_employee_id"]');
            const card = document.createElement('article');
            card.className = 'production-review-card';
            const product = document.createElement('strong');
            product.textContent = `${index + 1}. ${fieldValue('product_name')}`;
            const details = document.createElement('p');
            details.textContent = [
                `Cooking: ${selectedEmployee.selectedOptions[0].textContent}`,
                `Stock: ${fieldValue('Stock_number')}`,
                `Target PCS: ${fieldValue('target_pcs')}`,
                `Quantity: ${fieldValue('quantity')}`,
                `Due date: ${fieldValue('due_date')}`
            ].join(' · ');
            card.append(product, details);
            reviewItems.appendChild(card);
        });
        assignmentEditor.hidden = true;
        assignmentReview.hidden = false;
        reviewButton.hidden = true;
        backButton.hidden = false;
        confirmButton.hidden = false;
    }

    backButton.addEventListener('click', () => {
        assignmentReview.hidden = true;
        assignmentEditor.hidden = false;
        reviewButton.hidden = false;
        backButton.hidden = true;
        confirmButton.hidden = true;
    });

    addProductForm.addEventListener('submit', function (event) {
        if (event.submitter === confirmButton && !assignmentReview.hidden) {
            event.preventDefault();
            if (!validateProductionBatch()) {
                assignmentReview.hidden = true;
                assignmentEditor.hidden = false;
                reviewButton.hidden = false;
                backButton.hidden = true;
                confirmButton.hidden = true;
                return;
            }

            const loadingOverlay = document.getElementById('productionAssignmentLoading');
            loadingOverlay.classList.add('active');
            loadingOverlay.setAttribute('aria-busy', 'true');
            loadingOverlay.setAttribute('aria-hidden', 'false');
            confirmButton.disabled = true;
            const animationDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 100
                : 2800;
            window.setTimeout(() => {
                const submitMarker = document.createElement('input');
                submitMarker.type = 'hidden';
                submitMarker.name = 'submit';
                submitMarker.value = 'Confirm and assign';
                addProductForm.appendChild(submitMarker);
                try {
                    HTMLFormElement.prototype.submit.call(addProductForm);
                } catch (error) {
                    submitMarker.remove();
                    loadingOverlay.classList.remove('active');
                    loadingOverlay.setAttribute('aria-busy', 'false');
                    loadingOverlay.setAttribute('aria-hidden', 'true');
                    confirmButton.disabled = false;
                    window.alert('Production assignment could not be submitted. Please try again.');
                }
            }, animationDuration);
            return;
        }

        if (event.submitter !== confirmButton || assignmentReview.hidden) {
            event.preventDefault();
            if (!validateProductionBatch()) return;
            showProductionAssignmentReview();
            return;
        }
    });
}

function openReassignmentModal(button) {
    document.getElementById('reassign_production_id').value =
        button.dataset.productionId || '';
    document.getElementById('reassign_product_name').textContent =
        button.dataset.productName || 'Selected production task';
    document.getElementById('reassign_product_name_input').value =
        button.dataset.productName || '';
    const workType = button.dataset.assignedWorkType || '';
    document.getElementById('reassign_work_type').value = workType;
    document.getElementById('reassign_work_type_label').textContent = workType;
    document.getElementById('reassignmentTitle').textContent =
        `Assign or Reassign ${workType}`;

    const assignedEmployee = button.dataset.assignedEmployee || '';
    const employeeSelect = document.getElementById('reassign_target');
    const expectedRole = workType.toLowerCase();
    const oppositeWorkType = workType === 'Cooking' ? 'Packaging' : 'Cooking';
    const oppositeAssignment = Array.from(document.querySelectorAll('[data-assigned-work-type]'))
        .find((assignmentButton) => (
            assignmentButton.dataset.productionId === button.dataset.productionId
            && assignmentButton.dataset.assignedWorkType === oppositeWorkType
        ));
    const oppositeEmployeeId = oppositeAssignment?.dataset.assignedEmployee || '';
    Array.from(employeeSelect.options).forEach((option) => {
        if (!option.value) {
            if (option.dataset.role && option.dataset.role.endsWith('-empty')) {
                option.hidden = option.dataset.role !== `${expectedRole}-empty`;
            }
            return;
        }
        option.hidden = option.dataset.role !== expectedRole || option.value === oppositeEmployeeId;
        option.disabled = option.hidden;
    });
    const selectedEmployeeOption = Array.from(employeeSelect.options)
        .find((option) => option.value === assignedEmployee && !option.disabled);
    employeeSelect.value = selectedEmployeeOption ? assignedEmployee : '';
    const hasEligibleEmployee = Array.from(employeeSelect.options)
        .some((option) => option.value && !option.disabled);
    document.getElementById('saveAssignmentButton').disabled = !hasEligibleEmployee;
    const eligibilityMessage = document.getElementById('assignmentEligibilityMessage');
    if (!hasEligibleEmployee) {
        const hasPresentEmployeeForRole = Array.from(employeeSelect.options)
            .some((option) => option.value && option.dataset.role === expectedRole);
        eligibilityMessage.textContent = hasPresentEmployeeForRole
            ? `A different present employee is required. Cooking and Packaging cannot be assigned to the same person.`
            : `No present ${workType} employee is available. Record attendance before assigning this stage.`;
    }
    eligibilityMessage.hidden = hasEligibleEmployee;
    document.getElementById('reassignmentModal').style.display = 'block';
}

function closeReassignmentModal() {
    document.getElementById('reassignmentModal').style.display = 'none';
}

/* =============================================================
   UPDATE PRODUCT MODAL
============================================================= */
function openUpdateModal(
    id,
    name,
    target,
    stock,
    qty,
    due
) {
    document.getElementById('update_id').value = id;
    document.getElementById('update_product_name').value = name;
    document.getElementById('update_target_pcs').value = target;
    document.getElementById('update_stock_number').value = stock;
    document.getElementById('update_quantity').value = qty;
    document.getElementById('update_due_date').value = due;
    document.getElementById('update_due_date').dispatchEvent(new Event('change', { bubbles: true }));
    document.getElementById('updateModal').style.display = 'block';
}


function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}

/* =============================================================
   CLOSE MODALS WHEN CLICKING OUTSIDE
============================================================= */
window.onclick = function (event) {
    const addModal = document.getElementById('productModal');
    const reassignmentModal = document.getElementById('reassignmentModal');
    const updateModal = document.getElementById('updateModal');
    const activityModal = document.getElementById('productionActivityModal');

    if (event.target === addModal) {
        addModal.style.display = 'none';
    }
    if (event.target === reassignmentModal) {
        reassignmentModal.style.display = 'none';
    }
    if (event.target === updateModal) {
        updateModal.style.display = 'none';
    }
    if (event.target === activityModal) {
        activityModal.style.display = 'none';
    }

};

<?php if ($production_assignment_result === 'success'): ?>
window.setTimeout(() => {
    window.location.replace('factory_main.php');
}, 2500);
<?php elseif ($production_assignment_result === 'error'): ?>
document.getElementById('dismissProductionResult').addEventListener('click', () => {
    const overlay = document.getElementById('productionAssignmentLoading');
    overlay.classList.remove('active');
    overlay.setAttribute('aria-busy', 'false');
    overlay.setAttribute('aria-hidden', 'true');
});
<?php endif; ?>

</script>

<script src="js/assignment-dropdown.js?v=<?= filemtime(__DIR__ . '/js/assignment-dropdown.js'); ?>"></script>
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>