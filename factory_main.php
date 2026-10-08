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

// Default values
$pending_records = $pending_records ?? [];
$history_records = $history_records ?? [];
$message = $message ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Production Management - TASKTRACK</title>
  <link rel="stylesheet" href="css/Factory_main1.css">
    

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
            animation: productionPress 1.2s ease-in-out infinite;
        }

        .production-loading-overlay.active .production-conveyor-belt {
            animation: productionConveyor 0.45s linear infinite;
        }

        .production-loading-overlay.active .production-indicator {
            animation: productionIndicator 0.8s ease-in-out infinite;
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
</head>


<body>

<div class="app">

    <!-- =========================================================
         SIDEBAR
    ========================================================= -->

    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">

            <div class="avatar">
                🚚
            </div>

            <div class="titles">

                <div class="name">
                    TASKTRACK
                </div>

                <div class="sub">
                    Supervisor :
                    <span>
                        <?= htmlspecialchars($_SESSION['name'] ?? ''); ?>
                    </span>
                </div>

                <div class="sub">
                    <span>
                        <?= isset($_SESSION['email'])
                            ? htmlspecialchars($_SESSION['email'])
                            : ''; ?>
                    </span>
                </div>

            </div>

        </div>


        <nav class="sidebar-nav">

            <a href="supervisor.php" class="side-btn">
                DASHBOARD
            </a>

            <a href="task.php" class="side-btn">
                TASK
            </a>

            <a href="employee.php" class="side-btn">
                EMPLOYEE
            </a>

            <a href="factory_main.php" class="side-btn active">
                PRODUCTION
            </a>

            <a href="delivery_main.php" class="side-btn">
                LOGISTIC
            </a>

            <a href="logout.php" style="text-decoration: none;">
                <button class="logout-btn">
                    LOG OUT
                </button>
            </a>

        </nav>

    </aside>


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


        <!-- =====================================================
             CONTENT
        ====================================================== -->

        <div class="content">


            <!-- =================================================
                 PRODUCTION TABS
            ================================================== -->

            <div class="production-tabs">

                <button
                    type="button"
                    class="production-tab active"
                    id="pendingProductionTab"
                    onclick="showProductionSection('pending')"
                >
                    Pending Production Tasks
                </button>

                <button
                    type="button"
                    class="production-tab"
                    id="historyProductionTab"
                    onclick="showProductionSection('history')"
                >
                    Production History (Completed)
                </button>

            </div>


            <!-- =================================================
                 PENDING PRODUCTION TASKS
            ================================================== -->

            <div
                id="pendingProductionSection"
                class="production-section active"
            >

                <h2 class="section-title">
                    Pending Production Tasks
                </h2>


                <?php if (!empty($pending_records)): ?>

                    <div class="table-wrap">

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

                                    <tr>

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
                                                $row['due_date'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($row['assigned_employee_name'])): ?>
                                                <?= htmlspecialchars($row['assigned_employee_name']); ?>
                                                <small>(<?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?>)</small>
                                            <?php else: ?>
                                                <span>Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-pill status-pending">
                                                In Production
                                            </span>
                                        </td>

                                        <td class="action-cell">

                                            <button
                                                type="button"
                                                class="edit-btn"
                                                data-production-id="<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>"
                                                data-product-name="<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>"
                                                data-assigned-employee="<?= htmlspecialchars($row['assigned_employee_id'] ?? '', ENT_QUOTES); ?>"
                                                data-assigned-work-type="<?= htmlspecialchars($row['assigned_work_type'] ?? '', ENT_QUOTES); ?>"
                                                onclick="openReassignmentModal(this)"
                                            >
                                                <?= empty($row['assigned_employee_id']) ? 'Assign' : 'Reassign'; ?>
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

            </div>


            <!-- =================================================
                 PRODUCTION HISTORY
            ================================================== -->

            <div
                id="historyProductionSection"
                class="production-section"
            >

                <h2 class="section-title history-title">
                    Production History (Completed)
                </h2>


                <?php if (!empty($history_records)): ?>

                    <div class="table-wrap table-wrap-history">

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

                                    <tr>

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
                                                $row['due_date'] ?? ''
                                            ); ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($row['assigned_employee_name'])): ?>
                                                <?= htmlspecialchars($row['assigned_employee_name']); ?>
                                                <small>(<?= htmlspecialchars($row['assigned_work_type'] ?? ''); ?>)</small>
                                            <?php else: ?>
                                                <span>Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-pill status-delivered">
                                                Product Done
                                            </span>
                                        </td>

                                        <td class="action-cell">

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

            </div>

        </div>

    </main>

</div>


<!-- =============================================================
     ADD PRODUCT MODAL
============================================================= -->

<div
    id="productModal"
    class="modal"
    style="<?= !empty($errors) && $action === 'insert' ? 'display:block;' : '' ?>"
>

    <div class="modal-content">

        <h3>
            Add New Production Task
        </h3>


        <form
            id="addProductForm"
            action="factory_main.php"
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="insert"
            >

            <input
                type="hidden"
                name="module"
                value="factory"
            >


            <!-- PRODUCT NAME -->

            <div class="form-group">

                <label>
                    Product Name:
                </label>

                <select
                    name="product_name"
                    class="form-select"
                    required
                >

                    <option
                        value=""
                        disabled
                        selected
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

            <div class="form-group">
                <label for="new_assignment_target">
                    Assign To (Present Employee and Work Area):
                </label>
                <select name="assignment_target" id="new_assignment_target" required>
                    <option value="">-- Select Employee and Work Area --</option>
                    <?php foreach ($present_production_employees as $employee): ?>
                        <?php foreach (['Cooking', 'Packaging'] as $work_type): ?>
                            <?php $assignment_target = $employee['employee_id'] . '|' . $work_type; ?>
                            <option
                                value="<?= htmlspecialchars($assignment_target); ?>"
                                <?= ($_POST['assignment_target'] ?? '') === $assignment_target ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($employee['username'] ?: 'Employee #' . $employee['employee_id']); ?>
                                — <?= htmlspecialchars($work_type); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if (empty($present_production_employees)): ?>
                        <option value="" disabled>No production accounts are currently present</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($errors['assignment_target']) && $action === 'insert'): ?>
                    <div class="error-message"><?= htmlspecialchars($errors['assignment_target']); ?></div>
                <?php endif; ?>
            </div>


            <!-- TARGET PCS -->

            <div class="form-group">

                <label>
                    Target PCS:
                </label>

                <input
                    type="text"
                    name="target_pcs"
                    placeholder="Enter target pieces"
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
                    name="Stock_number"
                    placeholder="Enter stock number"
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
                    placeholder="Enter quantity"
                    required
                >

            </div>


            <!-- DUE DATE -->

            <div class="form-group">

                <label>
                    Due Date:
                </label>

                <input
                    type="date"
                    name="due_date"
                    required
                >

            </div>


            <!-- ACTIONS -->

            <div class="modal-actions">

                <button
                    type="submit"
                    name="submit"
                    class="btn-submit"
                    <?= empty($present_production_employees) ? 'disabled' : ''; ?>
                >
                    Add and Assign Task
                </button>

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeProductModal()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>

<div
    id="productionAssignmentLoading"
    class="production-loading-overlay"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="production-loading-card">
        <h2>Processing Production</h2>
        <p>Please wait while the production task is being prepared.</p>
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
        <h3>Reassign Production Task</h3>

        <form action="factory_main.php" method="POST">
            <input type="hidden" name="module" value="factory">
            <input type="hidden" name="action" value="reassign">
            <input type="hidden" name="production_id" id="reassign_production_id" value="<?= htmlspecialchars($_POST['production_id'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="product_name" id="reassign_product_name_input" value="<?= htmlspecialchars($_POST['product_name'] ?? '', ENT_QUOTES); ?>">

            <div class="form-group">
                <label>Production Task:</label>
                <div id="reassign_product_name"><?= htmlspecialchars($_POST['product_name'] ?? 'Select a task from the list.', ENT_QUOTES); ?></div>
            </div>

            <div class="form-group">
                <label for="reassign_target">Assign To:</label>
                <select name="assignment_target" id="reassign_target" required>
                    <option value="">-- Select Employee and Work Area --</option>
                    <?php foreach ($present_production_employees as $employee): ?>
                        <?php foreach (['Cooking', 'Packaging'] as $work_type): ?>
                            <?php $assignment_target = $employee['employee_id'] . '|' . $work_type; ?>
                            <option
                                value="<?= htmlspecialchars($assignment_target); ?>"
                                <?= ($_POST['assignment_target'] ?? '') === $assignment_target ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($employee['username'] ?: 'Employee #' . $employee['employee_id']); ?>
                                — <?= htmlspecialchars($work_type); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if (empty($present_production_employees)): ?>
                        <option value="" disabled>No production accounts are currently present</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($errors['assignment_target']) && $action === 'reassign'): ?>
                    <div class="error-message"><?= htmlspecialchars($errors['assignment_target']); ?></div>
                <?php endif; ?>
                <?php if (isset($errors['assignment']) && $action === 'reassign'): ?>
                    <div class="error-message"><?= htmlspecialchars($errors['assignment']); ?></div>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button type="submit" name="submit" class="btn-submit" <?= empty($present_production_employees) ? 'disabled' : ''; ?>>
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

                <label>
                    Due Date:
                </label>

                <input
                    type="date"
                    name="due_date"
                    id="update_due_date"
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

    const pendingSection =
        document.getElementById('pendingProductionSection');

    const historySection =
        document.getElementById('historyProductionSection');

    const pendingTab =
        document.getElementById('pendingProductionTab');

    const historyTab =
        document.getElementById('historyProductionTab');

    if (section === 'history') {
        // Hide Pending
        pendingSection.classList.remove('active');
        // Show History
        historySection.classList.add('active');
        // Active tab
        pendingTab.classList.remove('active');
        historyTab.classList.add('active');
    } else {
        // Hide History
        historySection.classList.remove('active');
        // Show Pending
        pendingSection.classList.add('active');
        // Active tab
        historyTab.classList.remove('active');
        pendingTab.classList.add('active');
    }
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
    addProductForm.addEventListener('submit', function (event) {
        if (addProductForm.dataset.submitting === 'true') {
            if (addProductForm.dataset.readyToSubmit === 'true') {
                return;
            }

            event.preventDefault();
            return;
        }

        event.preventDefault();
        addProductForm.dataset.submitting = 'true';
        const submitter = event.submitter;
        const loadingOverlay = document.getElementById('productionAssignmentLoading');
        loadingOverlay.classList.add('active');
        loadingOverlay.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            addProductForm.dataset.readyToSubmit = 'true';
            if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
                addProductForm.requestSubmit(submitter);
                return;
            }
            addProductForm.requestSubmit();
        }, 2700);
    });
}

function openReassignmentModal(button) {
    document.getElementById('reassign_production_id').value =
        button.dataset.productionId || '';
    document.getElementById('reassign_product_name').textContent =
        button.dataset.productName || 'Selected production task';
    document.getElementById('reassign_product_name_input').value =
        button.dataset.productName || '';

    const assignedEmployee = button.dataset.assignedEmployee || '';
    const assignedWorkType = button.dataset.assignedWorkType || '';
    document.getElementById('reassign_target').value =
        assignedEmployee && assignedWorkType
            ? `${assignedEmployee}|${assignedWorkType}`
            : '';
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

    if (event.target === addModal) {
        addModal.style.display = 'none';
    }
    if (event.target === reassignmentModal) {
        reassignmentModal.style.display = 'none';
    }
    if (event.target === updateModal) {
        updateModal.style.display = 'none';
    }

};

</script>

<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>