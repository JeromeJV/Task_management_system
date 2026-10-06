<?php

session_start();
/* ============================================================
   CONNECTION & INITIALIZATION
============================================================ */
include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');
$module = $_REQUEST['module'] ?? 'delivery';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = "";
$status_message = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistic Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/delivery_main.css">
    <style>
        /* ============================================================
           MODAL
        ============================================================ */
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
        /* ============================================================
           FORM
        ============================================================ */
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

        /* ============================================================
           STATUS TAGS
        ============================================================ */
        .prod-status-tag {
            font-size: 0.85em;
            padding: 2px 6px;
            border-radius: 3px;
            background-color: #e9ecef;
            color: #495057;
            font-weight: bold;
        }
        .driver-tag {
            color: #0d6efd;
            font-weight: 600;
        }
        /* ============================================================
           DELIVERY TABS
        ============================================================ */
        .delivery-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }
        .delivery-tab {
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
        .delivery-tab:hover {
            background: #edf5ef;
            color: #1f6e4a;
            border-color: #b9d3c1;
        }
        .delivery-tab.active {
            background: #1f6e4a;
            color: #ffffff;
            border-color: #1f6e4a;
        }
        /* ============================================================
           DELIVERY SECTIONS
        ============================================================ */
        .delivery-section {
            display: none;
        }
        .delivery-section.active {
            display: block;
        }
        .assignment-loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.82);
        }
        .assignment-loading-overlay.active {
            display: flex;
        }
        .assignment-loading-card {
            width: 100%;
            max-width: 400px;
            padding: 28px 24px;
            border-radius: 14px;
            background: #1e293b;
            color: #f8fafc;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .assignment-loading-card h2 {
            margin-bottom: 8px;
            font-size: 1.35rem;
        }
        .assignment-loading-card p {
            margin-bottom: 18px;
            color: #cbd5e1;
            font-size: 0.95rem;
        }
        .assignment-loading-stage {
            position: relative;
            height: 110px;
            overflow: hidden;
            border-bottom: 3px solid #475569;
        }
        .assignment-loading-status {
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
        .assignment-loading-truck {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 85px;
        }
        @keyframes assignmentLoadBox {
            0% { transform: translateY(-35px); opacity: 0; }
            30%, 100% { transform: translateY(0); opacity: 1; }
        }
        @keyframes assignmentTruckIdle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        @keyframes assignmentDriveAway {
            to { transform: translateX(350px); }
        }
        @keyframes assignmentSpinWheel {
            to { transform: rotate(360deg); }
        }
        .assignment-loading-overlay.active .assignment-box-1 {
            animation: assignmentLoadBox 0.6s ease-in-out forwards;
        }
        .assignment-loading-overlay.active .assignment-box-2 {
            animation: assignmentLoadBox 0.6s ease-in-out 0.4s forwards;
        }
        .assignment-loading-overlay.active .assignment-truck-body {
            animation: assignmentTruckIdle 0.4s infinite ease-in-out;
        }
        .assignment-loading-overlay.active .assignment-truck-group {
            animation: assignmentDriveAway 1.2s cubic-bezier(0.4, 0, 0.2, 1) 1.4s forwards;
        }
        .assignment-loading-overlay.active .assignment-wheel {
            transform-origin: center;
            transform-box: fill-box;
            animation: assignmentSpinWheel 0.3s linear 1.4s infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .assignment-loading-overlay.active .assignment-box-1,
            .assignment-loading-overlay.active .assignment-box-2,
            .assignment-loading-overlay.active .assignment-truck-body,
            .assignment-loading-overlay.active .assignment-truck-group,
            .assignment-loading-overlay.active .assignment-wheel {
                animation-duration: 0.01ms;
                animation-iteration-count: 1;
            }
        }
    </style>
</head>
<body>
<div class="app">
    <!-- ============================================================
         SIDEBAR
    ============================================================ -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="avatar">
                🚚
            </div>
            <div class="titles">
                <div class="name">
                    TASKTRACK
                </div>
                <div class="sub">
                    Supervisor:
                    <span>
                        <?= htmlspecialchars($_SESSION['name'] ?? ''); ?>
                    </span>
                </div>
                <div class="sub">
                    <span>
                        <?= htmlspecialchars($_SESSION['email'] ?? ''); ?>
                    </span>
                </div>
            </div>
        </div>
        <!-- ========================================================
             SIDEBAR NAVIGATION
        ======================================================== -->
        <nav class="sidebar-nav">
            <a href="supervisor.php" class="side-btn">DASHBOARD</a>
            <a href="task.php" class="side-btn">TASK</a>
            <a href="employee.php" class="side-btn">EMPLOYEE</a>
            <a href="factory_main.php" class="side-btn">PRODUCTION</a>
            <a href="delivery_main.php" class="side-btn active">LOGISTIC</a>
            <a href="logout.php" style="text-decoration: none;">
                <button class="logout-btn">LOG OUT</button>
            </a>
        </nav>
    </aside>
    <!-- ============================================================
         MAIN CONTENT
    ============================================================ -->
    <main class="main">
        <!-- ========================================================
             TOPBAR
        ======================================================== -->
        <div class="topbar">
            <h1>Logistic Management</h1>
            <div class="assign">
                <button type="button" onclick="openDeliveryModal()">Add Delivery</button>
            </div>
        </div>
        <!-- ========================================================
             SUCCESS MESSAGE
        ======================================================== -->

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


        <!-- ========================================================
             CONTENT
        ======================================================== -->

        <div class="content">

            <div class="page active">


                <!-- ==================================================
                     DELIVERY TABS
                ================================================== -->

                <div class="delivery-tabs">

                    <button
                        type="button"
                        class="delivery-tab active"
                        id="pendingTab"
                        onclick="showDeliverySection('pending')"
                    >
                        Pending Deliveries
                    </button>

                    <button
                        type="button"
                        class="delivery-tab"
                        id="historyTab"
                        onclick="showDeliverySection('history')"
                    >
                        Delivery History (Delivered)
                    </button>

                </div>


                <!-- ==================================================
                     PENDING DELIVERIES
                ================================================== -->

                <div
                    id="pendingSection"
                    class="delivery-section active"
                >

                    <h2 class="section-title">
                        Pending Deliveries
                    </h2>


                    <?php if (!empty($pending_records)): ?>

                        <div class="table-wrap">

                            <table>

                                <thead>

                                    <tr>

                                        <th>Delivery ID</th>
                                        <th>Product Name</th>
                                        <th>Product Status</th>
                                        <th>Assigned Driver</th>
                                        <th>Destination</th>
                                        <th>Quantity</th>
                                        <th>Stock Number</th>
                                        <th>Delivery Date</th>
                                        <th>Delivery Status</th>
                                        <th>Actions</th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($pending_records as $row): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars($row['delivery_id']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?>
                                            </td>

                                            <td>

                                                <span class="prod-status-tag">

                                                    <?= htmlspecialchars(
                                                        !empty($row['product_status'])
                                                            ? $row['product_status']
                                                            : 'In Production'
                                                    ); ?>

                                                </span>

                                            </td>

                                            <td class="driver-tag">

                                                <?= htmlspecialchars(
                                                    $row['driver_name'] ?? 'Unassigned'
                                                ); ?>

                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['route']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['pieces']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['stock']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['delivery_date']); ?>
                                            </td>

                                            <td>

                                                <span class="status-pill status-pending">
                                                    Pending
                                                </span>

                                            </td>

                                            <td class="action-cell">

                                                <form
                                                    action="delivery_main.php"
                                                    method="post"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="idno"
                                                        value="<?= htmlspecialchars($row['delivery_id']); ?>"
                                                    >

                                                    <button
                                                        type="button"
                                                        class="edit-btn"
                                                        onclick="openEditDeliveryModal(
                                                            '<?= htmlspecialchars($row['delivery_id']); ?>',
                                                            '<?= htmlspecialchars($row['route']); ?>',
                                                            '<?= htmlspecialchars($row['pieces']); ?>',
                                                            '<?= htmlspecialchars($row['stock']); ?>',
                                                            '<?= htmlspecialchars($row['delivery_date']); ?>',
                                                            '<?= htmlspecialchars($row['driver_id'] ?? ''); ?>'
                                                        )"
                                                    >
                                                        Update
                                                    </button>

                                                    <input
                                                        type="submit"
                                                        name="del"
                                                        value="Delete"
                                                        class="remove-btn"
                                                        onclick="return confirm('Are you sure?');"
                                                    >

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <p>
                            No pending deliveries.
                        </p>

                    <?php endif; ?>

                </div>


                <!-- ==================================================
                     DELIVERY HISTORY
                ================================================== -->

                <div
                    id="historySection"
                    class="delivery-section"
                >

                    <h2 class="section-title">
                        Delivery History (Delivered)
                    </h2>


                    <?php if (!empty($history_records)): ?>

                        <div class="table-wrap">

                            <table>

                                <thead>

                                    <tr>

                                        <th>Delivery ID</th>
                                        <th>Product Name</th>
                                        <th>Product Status</th>
                                        <th>Assigned Driver</th>
                                        <th>Destination</th>
                                        <th>Quantity</th>
                                        <th>Stock Number</th>
                                        <th>Delivery Date</th>
                                        <th>Delivery Status</th>
                                        <th>Actions</th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($history_records as $row): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars($row['delivery_id']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?>
                                            </td>

                                            <td>

                                                <span class="prod-status-tag">

                                                    <?= htmlspecialchars(
                                                        !empty($row['product_status'])
                                                            ? $row['product_status']
                                                            : 'product done'
                                                    ); ?>

                                                </span>

                                            </td>

                                            <td class="driver-tag">

                                                <?= htmlspecialchars(
                                                    $row['driver_name'] ?? 'N/A'
                                                ); ?>

                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['route']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['pieces']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['stock']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['delivery_date']); ?>
                                            </td>

                                            <td>

                                                <span class="status-pill status-delivered">
                                                    Delivered
                                                </span>

                                            </td>

                                            <td class="action-cell">

                                                <form
                                                    action="delivery_main.php"
                                                    method="post"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="idno"
                                                        value="<?= htmlspecialchars($row['delivery_id']); ?>"
                                                    >

                                                    <input
                                                        type="submit"
                                                        name="del"
                                                        value="Delete"
                                                        class="remove-btn"
                                                        onclick="return confirm('Are you sure?');"
                                                    >

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <p>
                            No delivery history yet.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>



<!-- ============================================================
     ADD DELIVERY MODAL
============================================================ -->

<div
    id="deliveryModal"
    class="modal"
    style="<?= !empty($errors) && $action === 'insert' ? 'display:block;' : '' ?>"
>

    <div class="modal-content">

        <h3>
            Add New Delivery Task
        </h3>


        <form
            action="delivery_main.php"
            method="POST"
            class="assignment-form"
        >

            <input
                type="hidden"
                name="module"
                value="delivery"
            >

            <input
                type="hidden"
                name="action"
                value="insert"
            >


            <!-- PRODUCT -->

            <div class="form-group">

                <label for="production_id">
                    Select Product:
                </label>

                <select
                    name="production_id"
                    id="production_id"
                    required
                >

                    <option value="">
                        -- Select Completed Product --
                    </option>


                    <?php if (!empty($production_items)): ?>

                        <?php foreach ($production_items as $prod): ?>

                            <option
                                value="<?= htmlspecialchars($prod['production_id']); ?>"
                            >
                                <?= htmlspecialchars($prod['product_name']); ?>

                                (Stock:
                                <?= htmlspecialchars($prod['Stock_number']); ?>

                                | Qty:
                                <?= htmlspecialchars($prod['quantity']); ?>)
                            </option>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <option
                            value=""
                            disabled
                        >
                            No available finished products for delivery
                        </option>

                    <?php endif; ?>

                </select>


                <?php if (isset($errors['production_id'])): ?>

                    <div class="error-message">
                        <?= htmlspecialchars($errors['production_id']); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- DRIVER -->

            <div class="form-group">

                <label for="driver_id">
                    Assign to Active Driver (Timed In Today):
                </label>

                <select
                    name="driver_id"
                    id="driver_id"
                    required
                >

                    <option value="">
                        -- Select Driver --
                    </option>


                    <?php if (!empty($present_drivers)): ?>

                        <?php foreach ($present_drivers as $drv): ?>

                            <option
                                value="<?= htmlspecialchars($drv['id']); ?>"
                            >
                                <?= htmlspecialchars($drv['name']); ?>
                                (Present)
                            </option>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <option
                            value=""
                            disabled
                        >
                            ❌ No drivers are currently timed in today
                        </option>

                    <?php endif; ?>

                </select>


                <?php if (isset($errors['driver_id'])): ?>

                    <div class="error-message">
                        <?= htmlspecialchars($errors['driver_id']); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- ROUTE -->

            <div class="form-group">

                <label for="route">
                    Destination (Route):
                </label>

                <input
                    type="text"
                    id="route"
                    name="route"
                    value="<?= htmlspecialchars($_POST['route'] ?? ''); ?>"
                    required
                >


                <?php if (isset($errors['route'])): ?>

                    <div class="error-message">
                        <?= htmlspecialchars($errors['route']); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- DELIVERY DATE -->

            <div class="form-group">

                <label for="delivery_date">
                    Delivery Date:
                </label>

                <input
                    type="date"
                    id="delivery_date"
                    name="delivery_date"
                    value="<?= htmlspecialchars($_POST['delivery_date'] ?? ''); ?>"
                    required
                >


                <?php if (isset($errors['delivery_date'])): ?>

                    <div class="error-message">
                        <?= htmlspecialchars($errors['delivery_date']); ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- MODAL ACTIONS -->

            <div class="modal-actions">

                <button
                    type="submit"
                    name="submit"
                    class="btn-submit"
                >
                    Create Task
                </button>

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeDeliveryModal()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>



<!-- ============================================================
     EDIT DELIVERY MODAL
============================================================ -->

<div
    id="editDeliveryModal"
    class="modal"
>

    <div class="modal-content">

        <h3>
            Update Delivery Record
        </h3>


        <form
            action="delivery_main.php"
            method="POST"
            class="assignment-form"
        >

            <input
                type="hidden"
                name="action"
                value="update"
            >

            <input
                type="hidden"
                name="module"
                value="delivery"
            >

            <input
                type="hidden"
                id="edit_delivery_id"
                name="delivery_id"
            >


            <!-- DRIVER -->

            <div class="form-group">

                <label for="edit_driver_id">
                    Reassign Driver:
                </label>

                <select
                    name="driver_id"
                    id="edit_driver_id"
                    required
                >

                    <option value="">
                        -- Select Driver --
                    </option>


                    <?php if (!empty($present_drivers)): ?>

                        <?php foreach ($present_drivers as $drv): ?>

                            <option
                                value="<?= htmlspecialchars($drv['id']); ?>"
                            >
                                <?= htmlspecialchars($drv['name']); ?>
                                (Present)
                            </option>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <option
                            value=""
                            disabled
                        >
                             No active drivers present today
                        </option>

                    <?php endif; ?>

                </select>

            </div>


            <!-- ROUTE -->

            <div class="form-group">

                <label for="edit_route">
                    Destination (Route):
                </label>

                <input
                    type="text"
                    id="edit_route"
                    name="route"
                    required
                >

            </div>


            <!-- QUANTITY -->

            <div class="form-group">

                <label for="edit_pieces">
                    Quantity (Pieces):
                </label>

                <input
                    type="number"
                    id="edit_pieces"
                    name="pieces"
                    required
                >

            </div>


            <!-- STOCK -->

            <div class="form-group">

                <label for="edit_stock">
                    Stock Number:
                </label>

                <input
                    type="text"
                    id="edit_stock"
                    name="stock"
                    required
                >

            </div>


            <!-- DELIVERY DATE -->

            <div class="form-group">

                <label for="edit_delivery_date">
                    Delivery Date:
                </label>

                <input
                    type="date"
                    id="edit_delivery_date"
                    name="delivery_date"
                    required
                >

            </div>


            <!-- MODAL ACTIONS -->

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
                    onclick="closeEditDeliveryModal()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>


<div
    id="assignmentLoading"
    class="assignment-loading-overlay"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="assignment-loading-card">
        <h2>Assigning Delivery</h2>
        <p>Please wait while the delivery task is being saved.</p>
        <div class="assignment-loading-stage">
            <div class="assignment-loading-status">Loading cargo...</div>
            <svg class="assignment-loading-truck" viewBox="0 0 300 80" aria-hidden="true">
                <defs>
                    <symbol id="assignmentPackageArt" viewBox="0 0 20 20">
                        <rect x="0.5" y="0.5" width="19" height="19" rx="2" fill="currentColor" stroke="#78350f" stroke-width="1" />
                        <path d="M8 1H12V19H8Z" fill="#fde68a" opacity="0.9" />
                        <path d="M1 6.5H19" stroke="#b45309" stroke-width="0.8" opacity="0.8" />
                        <rect x="11.5" y="8" width="6.5" height="7" rx="0.6" fill="#fff7ed" />
                        <path d="M12.5 10H17M12.5 11.5H17M12.5 13H15.5" stroke="#475569" stroke-width="0.7" />
                        <path d="M2 3L5 2" stroke="#fff7ed" stroke-width="0.7" opacity="0.75" />
                    </symbol>
                </defs>
                <g class="assignment-truck-group">
                    <g class="assignment-truck-body">
                        <path d="M 40 30 L 130 30 L 130 65 L 40 65 Z" fill="#334155" />
                        <use href="#assignmentPackageArt" x="50" y="45" width="20" height="20" class="assignment-box-1" color="#f59e0b" opacity="0" />
                        <use href="#assignmentPackageArt" x="75" y="45" width="20" height="20" class="assignment-box-2" color="#d97706" opacity="0" />
                        <path d="M 35 25 L 135 25 L 135 65 L 35 65 Z" fill="none" stroke="#64748b" stroke-width="3" />
                        <path d="M 135 35 L 165 35 L 180 50 L 180 65 L 135 65 Z" fill="#3b82f6" />
                        <path d="M 150 38 L 163 38 L 173 48 L 150 48 Z" fill="#93c5fd" />
                        <rect x="180" y="60" width="6" height="5" fill="#94a3b8" />
                    </g>
                    <g class="assignment-wheel">
                        <circle cx="65" cy="65" r="10" fill="#1e293b" stroke="#94a3b8" stroke-width="2" />
                        <circle cx="65" cy="65" r="4" fill="#f8fafc" />
                    </g>
                    <g class="assignment-wheel">
                        <circle cx="155" cy="65" r="10" fill="#1e293b" stroke="#94a3b8" stroke-width="2" />
                        <circle cx="155" cy="65" r="4" fill="#f8fafc" />
                    </g>
                </g>
            </svg>
        </div>
    </div>
</div>


<!-- ============================================================
     JAVASCRIPT
============================================================ -->

<script>


/* ============================================================
   DELIVERY TAB SWITCHING
============================================================ */

function showDeliverySection(section) {

    const pendingSection =
        document.getElementById("pendingSection");

    const historySection =
        document.getElementById("historySection");

    const pendingTab =
        document.getElementById("pendingTab");

    const historyTab =
        document.getElementById("historyTab");


    if (section === "history") {

        pendingSection.classList.remove("active");
        historySection.classList.add("active");

        pendingTab.classList.remove("active");
        historyTab.classList.add("active");

    } else {

        historySection.classList.remove("active");
        pendingSection.classList.add("active");

        historyTab.classList.remove("active");
        pendingTab.classList.add("active");

    }

}



/* ============================================================
   ADD DELIVERY MODAL
============================================================ */

function openDeliveryModal() {

    document.getElementById("deliveryModal").style.display = "block";

}


function closeDeliveryModal() {

    document.getElementById("deliveryModal").style.display = "none";

}



/* ============================================================
   EDIT DELIVERY MODAL
============================================================ */

function openEditDeliveryModal(
    id,
    route,
    pieces,
    stock,
    date,
    driverId
) {

    document.getElementById("edit_delivery_id").value = id;

    document.getElementById("edit_route").value = route;

    document.getElementById("edit_pieces").value = pieces;

    document.getElementById("edit_stock").value = stock;

    document.getElementById("edit_delivery_date").value = date;


    if (driverId) {

        document.getElementById("edit_driver_id").value = driverId;

    }


    document.getElementById("editDeliveryModal").style.display =
        "block";

}


function closeEditDeliveryModal() {

    document.getElementById("editDeliveryModal").style.display =
        "none";

}



/* ============================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
============================================================ */

window.onclick = function(event) {

    const addModal =
        document.getElementById("deliveryModal");

    const editModal =
        document.getElementById("editDeliveryModal");


    if (event.target === addModal) {

        addModal.style.display = "none";

    }


    if (event.target === editModal) {

        editModal.style.display = "none";

    }

};


document.querySelectorAll(".assignment-form").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (form.dataset.submitting === "true") {
            if (form.dataset.readyToSubmit === "true") {
                return;
            }

            event.preventDefault();
            return;
        }

        event.preventDefault();
        form.dataset.submitting = "true";
        const submitter = event.submitter;
        const loadingOverlay = document.getElementById("assignmentLoading");
        loadingOverlay.classList.add("active");
        loadingOverlay.setAttribute("aria-hidden", "false");

        window.setTimeout(() => {
            form.dataset.readyToSubmit = "true";

            if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
                form.requestSubmit(submitter);
                return;
            }

            form.requestSubmit();
        }, 2700);
    });
});

</script>


</body>
</html>