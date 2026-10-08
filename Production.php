<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');

$_REQUEST['module'] = 'factory';
include('config/Supervisor_API.php');

// Tiyaking Production Worker ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'pro') {
    header("Location: index.php");
    exit();
}

$records = $records ?? [];
$count   = count($records);

// Tab switching state
$active_tab = $_GET['tab'] ?? 'main';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        body {
            background-color: #f4f5f7;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        /* Top Navigation Header */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #eee;
            background: #fff;
            flex-wrap: wrap;
            gap: 10px;
        }
        .brand {
            font-size: 18px;
            font-weight: 700;
            color: #2b3a4a;
        }
        .nav-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-btn {
            padding: 8px 24px;
            border-radius: 20px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            color: #555;
            transition: all 0.2s ease;
        }
        .nav-btn.active {
            background-color: #8bb396;
            color: white;
            border-color: #8bb396;
        }
        .logout-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            padding: 4px;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        /* Welcome Banner */
        .welcome-banner {
            background: #ffffff;
            margin: 20px 24px;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #eaeaea;
        }
        .welcome-banner h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .welcome-banner p {
            color: #777;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Layout Grid for Main Section */
        .main-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            padding: 0 24px 24px 24px;
        }

        .card-box {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
        }
/* eh */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
        }
        .card-subtitle {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }
        .step-badge {
            font-size: 11px;
            color: #529471;
            background: #eef7f2;
            padding: 4px 8px;
            border-radius: 6px;
        }

        /* Custom Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            text-align: left;
            padding: 10px;
            color: #888;
            font-weight: 500;
            border-bottom: 1px solid #eee;
        }
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #f9f9f9;
            vertical-align: middle;
        }
        
        /* Clickable Row Styling */
        .selectable-row {
            cursor: pointer;
            transition: background 0.15s ease-in-out;
        }
        .selectable-row:hover {
            background-color: #f7faf8;
        }
        .selectable-row.selected-row {
            background-color: #edf5f0;
        }

        .item-icon {
            width: 32px;
            height: 32px;
            background-color: #8bb396;
            color: white;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .prod-name-cell {
            display: flex;
            align-items: center;
        }

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .badge-done {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .badge-pending {
            background-color: #fff3cd;
            color: #664d03;
        }

        /* Right Panel: Task Details */
        .task-detail-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            height: fit-content;
        }
        .detail-item-header {
            background: #f7f9f8;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }
        .detail-box {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
        }
        .detail-box label {
            display: block;
            font-size: 10px;
            color: #888;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .detail-box span {
            font-size: 12px;
            font-weight: 600;
            word-break: break-word;
        }
        .btn-continue {
            width: 100%;
            background-color: #8bb396;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-continue:hover {
            background-color: #769f81;
        }

        /* Modal Overlay Styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 15px;
        }
        .modal-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            width: 100%;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .modal-icon {
            width: 48px;
            height: 48px;
            background: #8bb396;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 20px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-modal-cancel {
            flex: 1;
            background: #f1f1f1;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-modal-submit {
            flex: 1;
            background: #8bb396;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .tea-loading-screen {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.82);
            perspective: 1000px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .tea-loading-screen.active {
            opacity: 1;
            pointer-events: auto;
        }
        .tea-loading-card {
            display: flex;
            width: min(360px, 100%);
            min-height: 230px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            border-radius: 14px;
            background: #1e293b;
            color: #f8fafc;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .tea-box-wrapper {
            position: relative;
            width: 140px;
            height: 110px;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tea-box-body {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 85px;
            border: 2px solid #a36a2e;
            border-radius: 6px;
            background: #c58a4b;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .tea-box-label {
            position: absolute;
            top: 48px;
            left: 13px;
            z-index: 2;
            width: 48px;
            padding: 4px 3px;
            border: 1px solid #ead6b8;
            border-radius: 2px;
            background: #fff8e9;
            color: #684522;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        .tea-box-label-title {
            display: block;
            font-size: 5px;
            font-weight: 800;
            letter-spacing: 0.35px;
        }
        .tea-box-label-code {
            display: block;
            margin-top: 2px;
            padding-top: 2px;
            border-top: 1px dashed #c7a77f;
            font-size: 4px;
            letter-spacing: 0.4px;
        }
        .tea-box-stamp {
            position: absolute;
            top: 49px;
            right: 13px;
            z-index: 2;
            display: flex;
            width: 25px;
            height: 25px;
            justify-content: center;
            align-items: center;
            border: 1px solid rgba(104, 69, 34, 0.7);
            border-radius: 50%;
            color: #684522;
            font-size: 13px;
            font-weight: 700;
            transform: rotate(-12deg);
        }
        .tea-box-lid-left,
        .tea-box-lid-right {
            position: absolute;
            top: 25px;
            z-index: 3;
            width: 50%;
            height: 25px;
            background: #a36a2e;
            transition: transform 0.4s ease;
        }
        .tea-box-lid-left {
            left: 0;
            transform-origin: left top;
            border-radius: 4px 0 0;
        }
        .tea-box-lid-right {
            right: 0;
            transform-origin: right top;
            border-radius: 0 4px 0 0;
        }
        .tea-loading-screen.step-open .tea-box-lid-left {
            transform: rotateZ(-110deg);
        }
        .tea-loading-screen.step-open .tea-box-lid-right {
            transform: rotateZ(110deg);
        }
        .tea-loading-screen.step-close .tea-box-lid-left,
        .tea-loading-screen.step-close .tea-box-lid-right {
            transform: rotateZ(0deg);
        }
        .tea-box-tape {
            position: absolute;
            top: 25px;
            left: 50%;
            z-index: 4;
            width: 0;
            height: 85px;
            border-radius: 1px;
            background: #1a1a1a;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            opacity: 0.9;
            transform: translateX(-50%);
            transition: width 0.4s ease-in-out;
        }
        .tea-loading-screen.step-tape .tea-box-tape {
            width: 24px;
        }
        .tea-loading-screen.step-zoom .tea-box-wrapper {
            transform: scale(4.5);
        }
        .tea-success-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .tea-check-circle {
            position: relative;
            display: flex;
            width: 70px;
            height: 70px;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.7);
            color: #fff;
            font-size: 36px;
        }
        .tea-check-circle::after {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px solid #34d399;
            border-radius: 50%;
            content: '';
            animation: teaLoadingRipple 1.2s infinite ease-out;
        }
        @keyframes teaLoadingRipple {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }
        .tea-loading-screen.step-success .tea-success-overlay {
            transform: translate(-50%, -50%) scale(1);
        }
        .tea-loading-text {
            margin-top: 24px;
            color: #38bdf8;
            font-size: 18px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: opacity 0.3s ease;
        }
        @media (prefers-reduced-motion: reduce) {
            .tea-loading-screen *,
            .tea-loading-screen *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Search input for History Page */
        .search-box {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 12px;
            width: 220px;
            outline: none;
        }
        .search-box:focus {
            border-color: #8bb396;
        }
        .btn-delete {
            background: #ff4d4d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }

        /* Responsive Media Queries */
        @media (max-width: 900px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .welcome-banner {
                margin: 15px;
            }
            .main-layout, div[style*="padding: 0 24px"] {
                padding: 0 15px 15px 15px !important;
            }
            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header Navigation -->
    <div class="top-navbar">
        <div class="brand">Production Portal</div>
        <div class="nav-controls">
            <a href="?tab=main" class="nav-btn <?= $active_tab === 'main' ? 'active' : ''; ?>">Main</a>
            <a href="?tab=history" class="nav-btn <?= $active_tab === 'history' ? 'active' : ''; ?>">HISTORY</a>
            <a href="logout.php" class="logout-btn" title="Logout">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="welcome-banner">
        <h1>Welcome to the production Interface</h1>
        <p>Productivity is the foundation of being successful | User: <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Production Worker'); ?></strong></p>
    </div>

    <?php if (!empty($_SESSION['production_error'])): ?>
        <div role="alert" style="margin: 0 24px 16px; padding: 12px; border-radius: 8px; color: #842029; background: #f8d7da;">
            <?= htmlspecialchars($_SESSION['production_error']); ?>
        </div>
        <?php unset($_SESSION['production_error']); ?>
    <?php endif; ?>

    <?php if ($active_tab === 'main'): ?>
        <!-- MAIN PRODUCTION VIEW -->
        <div class="main-layout">
            <!-- Left Side: Item List -->
            <div class="card-box">
                <div class="card-header">
                    <div>
                        <div class="card-title">ITEM LIST</div>
                        <div class="card-subtitle">Review the selected production task before continuing.</div>
                    </div>
                    <span class="step-badge">Step 1 of 4</span>
                </div>

                <?php if (!empty($pending_records)): ?>
                    <table id="mainProductionTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Item ID</th>
                                <th>Quantity</th>
                                <th>Stock No.</th>
                                <th>Deadline</th>
                                <th>Work Area</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $first_item = reset($pending_records);
                            foreach ($pending_records as $index => $row): 
                                $is_done = (($row['product_status'] ?? '') === 'product done');
                            ?>
                                <tr class="selectable-row <?= $index === 0 ? 'selected-row' : ''; ?>" 
                                    onclick="selectTask(this)"
                                    data-id="<?= htmlspecialchars($row['production_id']); ?>"
                                    data-name="<?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?>"
                                    data-qty="<?= htmlspecialchars($row['quantity']); ?>"
                                    data-target="<?= htmlspecialchars($row['target_pcs']); ?>"
                                    data-due="<?= htmlspecialchars($row['due_date']); ?>"
                                    data-work-type="<?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?>"
                                    data-status="<?= $is_done ? 'done' : 'pending'; ?>">
                                    <td>
                                        <div class="prod-name-cell">
                                            <div class="item-icon">GT</div>
                                            <div>
                                                <strong><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></strong><br>
                                                <small style="color:#aaa; font-size:10px;"><?= $is_done ? 'Completed' : 'Selected for review'; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['production_id']); ?></td>
                                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                                    <td><?= htmlspecialchars($row['Stock_number']); ?></td>
                                    <td><?= htmlspecialchars($row['due_date']); ?></td>
                                    <td><?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($is_done): ?>
                                            <span class="badge badge-done">Done</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 20px 0; color: #888;">No active production records found.</p>
                <?php endif; ?>
            </div>

            <!-- Right Side: Dynamic Task Details Panel -->
            <div class="task-detail-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="font-weight: 700;">Task Details</span>
                    <span class="badge badge-done" style="font-size: 10px;">Selected</span>
                </div>

                <?php if (!empty($pending_records) && isset($first_item)): ?>
                    <div class="detail-item-header">
                        <div class="item-icon" style="width:40px; height:40px;">GT</div>
                        <div>
                            <strong id="detailName"><?= htmlspecialchars($first_item['product_name'] ?? 'N/A'); ?></strong><br>
                            <small style="color:#888;">Item ID: <span id="detailHeaderId"><?= htmlspecialchars($first_item['production_id']); ?></span></small>
                        </div>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-box">
                            <label>Product Name</label>
                            <span id="detailBoxName"><?= htmlspecialchars($first_item['product_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-box">
                            <label>Item ID</label>
                            <span id="detailBoxId"><?= htmlspecialchars($first_item['production_id']); ?></span>
                        </div>
                        <div class="detail-box">
                            <label>Quantity</label>
                            <span id="detailBoxQty"><?= htmlspecialchars($first_item['quantity']); ?></span>
                        </div>
                        <div class="detail-box">
                            <label>Target PCS</label>
                            <span id="detailBoxTarget"><?= htmlspecialchars($first_item['target_pcs']); ?></span>
                        </div>
                        <div class="detail-box">
                            <label>Work Area</label>
                            <span id="detailBoxWorkType"><?= htmlspecialchars($first_item['assigned_work_type'] ?? ''); ?></span>
                        </div>
                        <div class="detail-box" style="grid-column: span 2;">
                            <label>Deadline</label>
                            <span id="detailBoxDue"><?= htmlspecialchars($first_item['due_date']); ?></span>
                        </div>
                    </div>

                    <div id="actionContainer">
                        <?php if (($first_item['product_status'] ?? '') !== 'product done'): ?>
                            <button type="button" class="btn-continue" onclick="openModal()">Continue</button>
                        <?php else: ?>
                            <button type="button" class="btn-continue" style="background:#ccc; cursor:not-allowed;" disabled>Completed</button>
                        <?php endif; ?>
                    </div>

                    <!-- Action Confirmation Modal -->
                    <div class="modal-overlay" id="confirmModal">
                        <div class="modal-card">
                            <div class="modal-icon">?</div>
                            <h3 style="font-size: 16px; margin-bottom: 8px;">Are you sure you want to continue?</h3>
                            <p style="font-size: 12px; color: #666;">This will mark the selected item as completed in the production flow.</p>
                            
                            <form id="productionCompletionForm" action="production.php" method="post">
                                <input type="hidden" name="idno" id="modalInputId" value="<?= htmlspecialchars($first_item['production_id']); ?>">
                                <div class="modal-actions">
                                    <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                                    <button type="submit" name="mark_done" class="btn-modal-submit">Continue</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <p style="font-size: 12px; color: #888;">Select an item to view details.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- HISTORY VIEW WITH REAL-TIME SEARCH -->
        <div style="padding: 0 24px 24px 24px;">
            <div class="card-box">
                <div class="card-header">
                    <div class="card-title" style="color: #529471; border-left: 3px solid #529471; padding-left: 8px;">Production History</div>
                    <input type="text" id="historySearch" placeholder="Search products..." class="search-box" onkeyup="filterHistoryTable()">
                </div>

                <?php if (!empty($history_records)): ?>
                    <table id="historyTable">
                        <thead>
                            <tr>
                                <th>PRODUCT NAME</th>
                                <th>ITEM ID</th>
                                <th>QUANTITY</th>
                                <th>STOCK NO.</th>
                                <th>DEADLINE</th>
                                <th>WORK AREA</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_records as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></strong></td>
                                    <td><?= htmlspecialchars($row['production_id'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['quantity'] ?? ''); ?> units</td>
                                    <td><?= htmlspecialchars($row['Stock_number'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['due_date'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?></td>
                                    <td><span class="badge badge-done">• Completed</span></td>
                                    <td>
                                        <form action="factory_task.php" method="post" style="display:inline;">
                                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id'] ?? ''); ?>">
                                            <button type="submit" name="del" class="btn-delete" onclick="return confirm('Are you sure you want to delete this record?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 20px 0; color: #888;">No completed production records found.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div
    id="teaLoadingPage"
    class="tea-loading-screen"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="tea-loading-card">
        <div class="tea-box-wrapper" aria-hidden="true">
            <div class="tea-box-lid-left"></div>
            <div class="tea-box-lid-right"></div>
            <div class="tea-box-body"></div>
            <div class="tea-box-label">
                <span class="tea-box-label-title">PRODUCTION</span>
                <span class="tea-box-label-code">PACKED WITH CARE</span>
            </div>
            <div class="tea-box-stamp">✓</div>
            <div class="tea-box-tape"></div>
        </div>
        <div id="teaLoadingText" class="tea-loading-text">Preparing production package...</div>
    </div>
    <div class="tea-success-overlay" aria-hidden="true">
        <div class="tea-check-circle">✓</div>
    </div>
</div>

<script>
// 1. Dynamic Row Selection (Pag-pindot ng Item)
function selectTask(rowElement) {
    // Tanggalin ang 'selected-row' class sa lahat ng rows
    const rows = document.querySelectorAll('.selectable-row');
    rows.forEach(r => r.classList.remove('selected-row'));

    // Idagdag ang 'selected-row' sa pinindot na row
    rowElement.classList.add('selected-row');

    // Kunin ang data mula sa attributes ng pinindot na row
    const id = rowElement.getAttribute('data-id');
    const name = rowElement.getAttribute('data-name');
    const qty = rowElement.getAttribute('data-qty');
    const target = rowElement.getAttribute('data-target');
    const due = rowElement.getAttribute('data-due');
    const workType = rowElement.getAttribute('data-work-type');
    const status = rowElement.getAttribute('data-status');

    // i-Update ang Task Details Panel sa Kanan
    document.getElementById('detailName').innerText = name;
    document.getElementById('detailHeaderId').innerText = id;
    document.getElementById('detailBoxName').innerText = name;
    document.getElementById('detailBoxId').innerText = id;
    document.getElementById('detailBoxQty').innerText = qty;
    document.getElementById('detailBoxTarget').innerText = target;
    document.getElementById('detailBoxDue').innerText = due;
    document.getElementById('detailBoxWorkType').innerText = workType;

    // i-Update ang hidden ID sa Modal
    document.getElementById('modalInputId').value = id;

    // i-Update ang Action Button batay sa status
    const actionContainer = document.getElementById('actionContainer');
    if (status === 'done') {
        actionContainer.innerHTML = '<button type="button" class="btn-continue" style="background:#ccc; cursor:not-allowed;" disabled>Completed</button>';
    } else {
        actionContainer.innerHTML = '<button type="button" class="btn-continue" onclick="openModal()">Continue</button>';
    }
}

// 2. Modal Popup Control
function openModal() {
    document.getElementById('confirmModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

const productionCompletionForm = document.getElementById('productionCompletionForm');
if (productionCompletionForm) {
    productionCompletionForm.addEventListener('submit', function (event) {
        if (productionCompletionForm.dataset.submitting === 'true') {
            if (productionCompletionForm.dataset.readyToSubmit === 'true') {
                return;
            }

            event.preventDefault();
            return;
        }

        event.preventDefault();
        productionCompletionForm.dataset.submitting = 'true';
        const submitter = event.submitter;
        const loader = document.getElementById('teaLoadingPage');
        const statusText = document.getElementById('teaLoadingText');

        loader.className = 'tea-loading-screen active step-open';
        loader.setAttribute('aria-hidden', 'false');
        statusText.textContent = 'Preparing production package...';
        statusText.style.opacity = '1';

        window.setTimeout(function () {
            loader.classList.add('step-close');
            statusText.textContent = 'Closing tea box flaps...';
        }, 900);

        window.setTimeout(function () {
            loader.classList.add('step-tape');
            statusText.textContent = 'Taping tea package securely...';
        }, 1400);

        window.setTimeout(function () {
            loader.classList.add('step-zoom');
            statusText.style.opacity = '0';
        }, 1900);

        window.setTimeout(function () {
            loader.classList.add('step-success');
        }, 2300);

        window.setTimeout(function () {
            loader.classList.remove('active');
            loader.setAttribute('aria-hidden', 'true');
            productionCompletionForm.dataset.readyToSubmit = 'true';

            if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
                productionCompletionForm.requestSubmit(submitter);
                return;
            }
            productionCompletionForm.requestSubmit();
        }, 3500);
    });
}

// 3. Dynamic Real-time Search Filter para sa History Page
function filterHistoryTable() {
    const input = document.getElementById('historySearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('historyTable');
    if (!table) return;

    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let rowMatch = false;
        const tdArray = tr[i].getElementsByTagName('td');
        
        for (let j = 0; j < tdArray.length - 1; j++) { // Huwag isama ang Action column
            if (tdArray[j]) {
                const textValue = tdArray[j].textContent || tdArray[j].innerText;
                if (textValue.toLowerCase().indexOf(filter) > -1) {
                    rowMatch = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = rowMatch ? '' : 'none';
    }
}
</script>

</body>
</html>