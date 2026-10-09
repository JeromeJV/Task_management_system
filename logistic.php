<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
$_REQUEST['module'] = 'delivery';
include('config/Supervisor_API.php');

// Tiyaking Driver / Logistic user ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'log') {
    header("Location: index.php");
    exit();
}

// Fallback arrays kung sakaling walang laman
$pending_records = $pending_records ?? [];
$history_records = $history_records ?? [];

// Calculate Dashboard Stats
$completed_today_count = 0;
$active_shipments_count = 0;
$delayed_count = 0;
$cancelled_count = 0;

$today_date = date('Y-m-d');

foreach ($pending_records as $rec) {
    $st = strtolower($rec['status'] ?? 'pending');
    if ($st === 'delayed') {
        $delayed_count++;
    } elseif ($st === 'cancelled') {
        $cancelled_count++;
    } else {
        $active_shipments_count++;
    }
}

foreach ($history_records as $hrec) {
    $h_date = date('Y-m-d', strtotime($hrec['delivery_date'] ?? 'now'));
    if ($h_date === $today_date || strtolower($hrec['status'] ?? '') === 'delivered') {
        $completed_today_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistic Portal - Driver Dashboard</title>
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <style>
        :root {
            --bg-color: #f4f6f5;
            --card-bg: #ffffff;
            --primary-green: #2d8a54;
            --primary-hover: #236c42;
            --accent-sage: #9bb7a0;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --border-color: #e2e8f0;
            --badge-green-bg: #d4edda;
            --badge-green-text: #155724;
            --badge-yellow-bg: #fff3cd;
            --badge-yellow-text: #856404;
            --badge-red-bg: #f8d7da;
            --badge-red-text: #721c24;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-dark);
            padding: 15px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        /* Top Bar Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: #ffffff;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title h2 {
            font-size: 1.2rem;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-btn {
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }

        .tab-btn.active {
            background: var(--accent-sage);
            color: #ffffff;
            border-color: var(--accent-sage);
        }

        .logout-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 1.2rem;
            text-decoration: none;
        }

        /* Hero Banner */
        .hero-section {
            padding: 20px 20px 10px 20px;
        }

        .hero-section h1 {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .hero-section p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-top: 4px;
            word-break: break-word;
        }

        /* Metric Cards Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        .metric-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            position: relative;
        }

        .metric-title {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 8px 0 5px 0;
        }

        .metric-subtitle {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .metric-subtitle.green { color: var(--primary-green); }
        .metric-subtitle.orange { color: #d97706; }
        .metric-subtitle.red { color: #dc2626; }

        /* Main Section Controls */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px 15px 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .filter-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            width: 100%;
            max-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
        }

        /* Shipment Cards Grid */
        .shipments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 15px;
            padding: 0 20px 20px 20px;
            max-height: 520px;
            overflow-y: auto;
        }

        .shipment-card {
            border: 1px solid var(--border-color);
            border-top: 4px solid var(--primary-green);
            border-radius: 8px;
            padding: 16px;
            background: #ffffff;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .shipment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.06);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .shipment-id {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .status-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .status-badge.in-transit, .status-badge.delivered, .status-badge.completed {
            background-color: var(--badge-green-bg);
            color: var(--badge-green-text);
        }

        .status-badge.pending, .status-badge.delayed {
            background-color: var(--badge-yellow-bg);
            color: var(--badge-yellow-text);
        }

        .card-body {
            font-size: 0.85rem;
            color: #4a5568;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .card-body div {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .btn-view-details {
            width: 100%;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 9px 0;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-view-details:hover {
            background: var(--primary-hover);
        }

        /* History Table Styling */
        .table-container {
            padding: 0 20px 20px 20px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
            min-width: 600px;
        }

        .custom-table th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-delete:hover { background: #dc2626; }

        .btn-table-view {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Modal Overlay Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 15px;
        }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .modal-grid label {
            color: var(--text-muted);
            display: block;
            font-size: 0.75rem;
        }

        .modal-grid span {
            font-weight: 600;
            color: var(--text-dark);
            word-break: break-word;
        }

        /* Timeline Progress Indicator */
        .timeline-container {
            margin: 15px 0;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
        }

        .timeline-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .timeline-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 8px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .step-item.active {
            color: var(--primary-green);
            font-weight: 700;
        }

        .btn-confirm-delivery {
            width: 100%;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .btn-confirm-delivery:hover { background: var(--primary-hover); }

        .delivery-sequence-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.84);
        }

        .delivery-sequence-overlay.active {
            display: flex;
        }

        .delivery-sequence-card {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            border-radius: 16px;
            background: #1e293b;
            color: #f8fafc;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .delivery-sequence-card h2 {
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }

        .delivery-sequence-card p {
            margin-bottom: 1.5rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .delivery-sequence-stage {
            position: relative;
            height: 120px;
            overflow: hidden;
            border-bottom: 3px solid #475569;
        }

        .delivery-sequence-status {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            color: #38bdf8;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .delivery-sequence-svg {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 90px;
        }

        @keyframes deliveryTruckIdle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }

        @keyframes unloadDeliveryBox1 {
            0% { transform: translate(0, 0); opacity: 1; }
            50% { transform: translate(-30px, -15px); opacity: 1; }
            100% { transform: translate(-45px, 20px); opacity: 1; }
        }

        @keyframes unloadDeliveryBox2 {
            0% { transform: translate(0, 0); opacity: 1; }
            50% { transform: translate(-55px, -15px); opacity: 1; }
            100% { transform: translate(-90px, 20px); opacity: 1; }
        }

        @keyframes deliveryTruckDriveAway {
            to { transform: translateX(350px); }
        }

        @keyframes deliveryWheelSpin {
            to { transform: rotate(360deg); }
        }

        .delivery-sequence-overlay.unloading .delivery-truck-body {
            animation: deliveryTruckIdle 0.4s infinite ease-in-out;
        }

        .delivery-sequence-overlay.unloading .delivery-box-1 {
            animation: unloadDeliveryBox1 0.7s ease-in-out forwards;
        }

        .delivery-sequence-overlay.unloading .delivery-box-2 {
            animation: unloadDeliveryBox2 0.7s ease-in-out 0.4s forwards;
        }

        .delivery-sequence-overlay.driving .delivery-truck-group {
            animation: deliveryTruckDriveAway 1.2s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .delivery-sequence-overlay.driving .delivery-wheel {
            transform-origin: center;
            transform-box: fill-box;
            animation: deliveryWheelSpin 0.3s linear infinite;
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            line-height: 1;
        }

        /* Alert Success Overlay */
        .success-box {
            text-align: center;
            padding: 10px;
        }

        .success-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px auto;
        }

        .btn-dark {
            background: #111827;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
        }

        .hidden { display: none !important; }

        /* Mobile Adjustments (Smartphones) */
        @media (max-width: 576px) {
            body {
                padding: 8px;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 15px;
            }

            .nav-tabs {
                width: 100%;
                justify-content: space-between;
            }

            .tab-btn {
                flex: 1;
                text-align: center;
                padding: 8px 12px;
            }

            .hero-section {
                padding: 15px;
            }

            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 15px;
            }

            .filter-controls {
                max-width: 100%;
            }

            .shipments-grid {
                grid-template-columns: 1fr;
                padding: 0 15px 15px 15px;
            }

            .table-container {
                padding: 0 15px 15px 15px;
            }

            .modal-grid {
                grid-template-columns: 1fr;
            }

            .timeline-steps {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <header>
        <div class="header-title">
            <h2>JANUARY 2026</h2>
        </div>
        <div class="nav-tabs">
            <button class="tab-btn active" id="tabMainBtn" onclick="switchTab('main')">Main</button>
            <button class="tab-btn" id="tabHistoryBtn" onclick="switchTab('history')">HISTORY</button>
            <a href="logout.php" class="logout-icon-btn" title="Logout">
                &#x21AA;
            </a>
        </div>
    </header>

    <!-- Welcome Hero Section -->
    <div class="hero-section">
        <h1>Hello, Welcome to the logistics portal</h1>
        <p>"Delivering faster than ever" &bull; Logged in as: <strong><?= htmlspecialchars($_SESSION['email']); ?></strong></p>
    </div>

    <!-- Dashboard Metrics Top Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-title">Completed Today</div>
            <div class="metric-value"><?= $completed_today_count; ?></div>
            <div class="metric-subtitle green">&#10003; Delivered &amp; confirmed</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Active Shipments</div>
            <div class="metric-value"><?= $active_shipments_count; ?></div>
            <div class="metric-subtitle green">&#9679; Currently on route</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Delayed</div>
            <div class="metric-value"><?= $delayed_count; ?></div>
            <div class="metric-subtitle orange">&#9888; Requires attention</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Cancelled</div>
            <div class="metric-value"><?= $cancelled_count; ?></div>
            <div class="metric-subtitle red">&#10060; This month</div>
        </div>
    </div>

    <!-- MAIN VIEW: ACTIVE SHIPMENTS -->
    <div id="mainView">
        <div class="section-header">
            <div class="section-title">Active Shipments</div>
            <div class="filter-controls">
                <input type="text" id="cardSearchInput" class="search-input" placeholder="Search shipments..." onkeyup="filterCards()">
            </div>
        </div>

        <?php if (!empty($pending_records)): ?>
            <div class="shipments-grid" id="shipmentsGrid">
                <?php foreach ($pending_records as $row): 
                    $del_id = htmlspecialchars($row['delivery_id'] ?? '');
                    $prod_name = htmlspecialchars($row['product_name'] ?? 'N/A');
                    $route = htmlspecialchars($row['route'] ?? 'N/A');
                    $pieces = htmlspecialchars($row['pieces'] ?? '0');
                    $stock = htmlspecialchars($row['stock'] ?? 'N/A');
                    $date = htmlspecialchars($row['delivery_date'] ?? 'N/A');
                    $status = htmlspecialchars($row['status'] ?? 'Pending');
                ?>
                    <div class="shipment-card" 
                         data-id="<?= $del_id; ?>"
                         data-product="<?= $prod_name; ?>"
                         data-route="<?= $route; ?>"
                         data-pieces="<?= $pieces; ?>"
                         data-stock="<?= $stock; ?>"
                         data-date="<?= $date; ?>"
                         data-status="<?= $status; ?>"
                         onclick="openDetailModal(this)">
                        
                        <div class="card-header">
                            <span class="shipment-id">#SHP-IN-<?= $del_id; ?></span>
                            <span class="status-badge <?= strtolower($status) === 'delivered' ? 'delivered' : 'in-transit'; ?>">
                                <?= $status === 'Pending' ? 'IN TRANSIT' : strtoupper($status); ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <div><strong>&bull; Product:</strong> <?= $prod_name; ?></div>
                            <div><strong>&bull; Destination:</strong> <?= $route; ?></div>
                            <div><strong>&bull; Date:</strong> <?= $date; ?></div>
                        </div>

                        <button class="btn-view-details" type="button" onclick="event.stopPropagation(); openDetailModal(this.parentElement)">View Details</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="padding: 0 20px 20px; color: var(--text-muted);">No active shipment records found.</p>
        <?php endif; ?>
    </div>

    <!-- HISTORY VIEW: TABLE -->
    <div id="historyView" class="hidden">
        <div class="section-header">
            <div class="section-title">History Records</div>
            <div class="filter-controls">
                <input type="text" id="tableSearchInput" class="search-input" placeholder="Search history..." onkeyup="filterTable()">
            </div>
        </div>

        <div class="table-container table-scroll">
            <?php if (!empty($history_records)): ?>
                <table class="custom-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>Shipment ID</th>
                            <th>Product</th>
                            <th>Destination</th>
                            <th>Delivery Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_records as $row): 
                            $del_id = htmlspecialchars($row['delivery_id'] ?? '');
                            $prod_name = htmlspecialchars($row['product_name'] ?? 'N/A');
                            $route = htmlspecialchars($row['route'] ?? 'N/A');
                            $pieces = htmlspecialchars($row['pieces'] ?? '0');
                            $stock = htmlspecialchars($row['stock'] ?? 'N/A');
                            $date = htmlspecialchars($row['delivery_date'] ?? 'N/A');
                        ?>
                            <tr>
                                <td><strong>#SHP-IN-<?= $del_id; ?></strong></td>
                                <td><?= $prod_name; ?></td>
                                <td><?= $route; ?></td>
                                <td><?= $date; ?></td>
                                <td>
                                    <span class="status-badge completed">COMPLETED</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" class="btn-table-view" 
                                                data-id="<?= $del_id; ?>"
                                                data-product="<?= $prod_name; ?>"
                                                data-route="<?= $route; ?>"
                                                data-pieces="<?= $pieces; ?>"
                                                data-stock="<?= $stock; ?>"
                                                data-date="<?= $date; ?>"
                                                data-status="Completed"
                                                onclick="openDetailModal(this, true)">View Details</button>
                                        
                                        <form action="delivery_main.php" method="post" onsubmit="return confirm('Are you sure you want to delete this history record?');">
                                            <input type="hidden" name="idno" value="<?= $del_id; ?>">
                                            <button type="submit" name="del" class="btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: var(--text-muted);">No delivery history yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL 1: SHIPMENT INFO MODAL -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span class="modal-title" id="modalShipmentId">Shipment #SHP-IN-0000</span>
                <div style="font-size: 0.75rem; color: var(--text-muted);" id="modalCategory">Logistics - Delivery Item</div>
            </div>
            <button class="btn-close-modal" onclick="closeModal('detailModal')">&times;</button>
        </div>

        <div class="modal-grid">
            <div>
                <label>Product</label>
                <span id="modalProduct">Item Name</span>
            </div>
            <div>
                <label>Quantity</label>
                <span id="modalQuantity">0 pcs</span>
            </div>
            <div>
                <label>Stock Number</label>
                <span id="modalStock">N/A</span>
            </div>
            <div>
                <label>Destination</label>
                <span id="modalDestination">Route Location</span>
            </div>
            <div>
                <label>Driver</label>
                <span><?= htmlspecialchars($_SESSION['email']); ?></span>
            </div>
            <div>
                <label>Delivery Date</label>
                <span id="modalDate">Jan 01, 2026</span>
            </div>
            <div>
                <label>Status</label>
                <span id="modalStatusText" style="color: var(--primary-green);">IN TRANSIT</span>
            </div>
        </div>

        <div class="timeline-container">
            <div class="timeline-title">Progress</div>
            <div class="timeline-steps">
                <div class="step-item active">&#10003; Product Checked</div>
                <div class="step-item active">&#10003; Product Received</div>
                <div class="step-item active">&#10003; On Route</div>
                <div class="step-item" id="stepDelivered">&#9675; Delivered</div>
            </div>
        </div>

        <form id="deliveryForm" action="logistic.php" method="post">
            <input type="hidden" name="idno" id="modalFormId">
            <button type="button" class="btn-confirm-delivery" id="modalSubmitBtn" onclick="openConfirmModal()">Confirm Delivery</button>
        </form>
    </div>
</div>

<!-- MODAL 2: CONFIRMATION PROMPT MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-card" style="max-width: 380px; text-align: center;">
        <h3 style="font-size: 1.1rem; margin-bottom: 10px;">Confirm Delivery</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">Are you sure this product has been successfully delivered?</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button type="button" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: white; cursor: pointer; font-size: 0.85rem;" onclick="closeModal('confirmModal')">Cancel</button>
            <button type="button" class="btn-confirm-delivery" style="width: auto; padding: 8px 16px; margin-top: 0;" onclick="submitDelivery()">Confirm Delivery</button>
        </div>
    </div>
</div>

<!-- MODAL 3: SUCCESS CONFIRMATION MODAL -->
<div class="modal-overlay" id="successModal">
    <div class="modal-card" style="max-width: 360px;">
        <div class="success-box">
            <div class="success-icon">&#10003;</div>
            <h3>A Shipment has been completed</h3>
            <button type="button" class="btn-dark" onclick="closeModal('successModal')">Got it</button>
        </div>
    </div>
</div>

<div
    class="delivery-sequence-overlay"
    id="deliverySequence"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="delivery-sequence-card">
        <h2>Delivery Arrival &amp; Drop-off</h2>
        <p>Please wait while the packages are unloaded and the delivery is completed.</p>
        <div class="delivery-sequence-stage">
            <div class="delivery-sequence-status" id="deliverySequenceStatus">Truck arrived...</div>
            <svg class="delivery-sequence-svg" viewBox="0 0 300 80" aria-hidden="true">
                <defs>
                    <symbol id="deliveryPackageArt" viewBox="0 0 20 20">
                        <rect x="0.5" y="0.5" width="19" height="19" rx="2" fill="currentColor" stroke="#78350f" stroke-width="1" />
                        <path d="M8 1H12V19H8Z" fill="#fde68a" opacity="0.9" />
                        <path d="M1 6.5H19" stroke="#b45309" stroke-width="0.8" opacity="0.8" />
                        <rect x="11.5" y="8" width="6.5" height="7" rx="0.6" fill="#fff7ed" />
                        <path d="M12.5 10H17M12.5 11.5H17M12.5 13H15.5" stroke="#475569" stroke-width="0.7" />
                        <path d="M2 3L5 2" stroke="#fff7ed" stroke-width="0.7" opacity="0.75" />
                    </symbol>
                </defs>
                <g aria-hidden="true">
                    <use id="deliveryDroppedBox1" href="#deliveryPackageArt" x="95" y="45" width="20" height="20" class="delivery-dropped-box-1" color="#f59e0b" opacity="0" />
                    <use id="deliveryDroppedBox2" href="#deliveryPackageArt" x="120" y="45" width="20" height="20" class="delivery-dropped-box-2" color="#d97706" opacity="0" />
                </g>
                <g class="delivery-truck-group" id="deliveryTruckGroup">
                    <g class="delivery-truck-body">
                        <path d="M 140 30 L 230 30 L 230 65 L 140 65 Z" fill="#334155" />
                        <g>
                            <use href="#deliveryPackageArt" x="150" y="45" width="20" height="20" class="delivery-box-1" color="#f59e0b" />
                            <use href="#deliveryPackageArt" x="175" y="45" width="20" height="20" class="delivery-box-2" color="#d97706" />
                        </g>
                        <path d="M 135 25 L 235 25 L 235 65 L 135 65 Z" fill="none" stroke="#64748b" stroke-width="3" />
                        <path d="M 235 35 L 265 35 L 280 50 L 280 65 L 235 65 Z" fill="#3b82f6" />
                        <path d="M 250 38 L 263 38 L 273 48 L 250 48 Z" fill="#93c5fd" />
                        <rect x="280" y="60" width="6" height="5" fill="#94a3b8" />
                    </g>
                    <g class="delivery-wheel">
                        <circle cx="165" cy="65" r="10" fill="#1e293b" stroke="#94a3b8" stroke-width="2" />
                        <circle cx="165" cy="65" r="4" fill="#f8fafc" />
                    </g>
                    <g class="delivery-wheel">
                        <circle cx="255" cy="65" r="10" fill="#1e293b" stroke="#94a3b8" stroke-width="2" />
                        <circle cx="255" cy="65" r="4" fill="#f8fafc" />
                    </g>
                </g>
            </svg>
        </div>
    </div>
</div>

<script>
    // Tab Switcher
    function switchTab(tabName) {
        const mainView = document.getElementById('mainView');
        const historyView = document.getElementById('historyView');
        const tabMainBtn = document.getElementById('tabMainBtn');
        const tabHistoryBtn = document.getElementById('tabHistoryBtn');

        if (tabName === 'main') {
            mainView.classList.remove('hidden');
            historyView.classList.add('hidden');
            tabMainBtn.classList.add('active');
            tabHistoryBtn.classList.remove('active');
        } else {
            mainView.classList.add('hidden');
            historyView.classList.remove('hidden');
            tabHistoryBtn.classList.add('active');
            tabMainBtn.classList.remove('active');
        }
    }

    // Dynamic Modal Opener
    function openDetailModal(element, isHistory = false) {
        const id = element.getAttribute('data-id');
        const product = element.getAttribute('data-product');
        const route = element.getAttribute('data-route');
        const pieces = element.getAttribute('data-pieces');
        const stock = element.getAttribute('data-stock');
        const date = element.getAttribute('data-date');
        const status = element.getAttribute('data-status');

        document.getElementById('modalShipmentId').innerText = 'Shipment #SHP-IN-' + id;
        document.getElementById('modalProduct').innerText = product;
        document.getElementById('modalQuantity').innerText = pieces + ' pcs';
        document.getElementById('modalStock').innerText = stock;
        document.getElementById('modalDestination').innerText = route;
        document.getElementById('modalDate').innerText = date;
        document.getElementById('modalFormId').value = id;

        const submitBtn = document.getElementById('modalSubmitBtn');
        const stepDelivered = document.getElementById('stepDelivered');
        const statusText = document.getElementById('modalStatusText');

        if (isHistory || status === 'Delivered' || status === 'Completed') {
            submitBtn.style.display = 'none';
            stepDelivered.classList.add('active');
            stepDelivered.innerHTML = '&#10003; Delivered';
            statusText.innerText = 'COMPLETED';
        } else {
            submitBtn.style.display = 'block';
            stepDelivered.classList.remove('active');
            stepDelivered.innerHTML = '&#9675; Delivered';
            statusText.innerText = 'IN TRANSIT';
        }

        document.getElementById('detailModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function openConfirmModal() {
        document.getElementById('confirmModal').style.display = 'flex';
    }

    // INAYOS NA CONFIRM / SUBMIT FUNCTION
    function submitDelivery() {
        const form = document.getElementById('deliveryForm');
        if (form.dataset.deliverySubmitting === 'true') {
            return;
        }

        form.dataset.deliverySubmitting = 'true';
        closeModal('confirmModal');
        closeModal('detailModal');

        let existingInput = document.getElementById('markDeliveredInput');
        if (existingInput) {
            existingInput.remove();
        }

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'markDeliveredInput';
        hiddenInput.name = 'mark_delivered';
        hiddenInput.value = '1';
        
        form.appendChild(hiddenInput);

        const confirmButton = document.querySelector('#confirmModal .btn-confirm-delivery');
        confirmButton.disabled = true;
        confirmButton.textContent = 'Processing...';

        const sequence = document.getElementById('deliverySequence');
        const droppedBox1 = document.getElementById('deliveryDroppedBox1');
        const droppedBox2 = document.getElementById('deliveryDroppedBox2');
        const truckBox1 = document.querySelector('.delivery-box-1');
        const truckBox2 = document.querySelector('.delivery-box-2');
        const status = document.getElementById('deliverySequenceStatus');

        droppedBox1.style.opacity = '0';
        droppedBox2.style.opacity = '0';
        truckBox1.style.opacity = '1';
        truckBox2.style.opacity = '1';
        sequence.classList.remove('driving');
        sequence.classList.add('active', 'unloading');
        sequence.setAttribute('aria-hidden', 'false');

        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                truckBox1.style.opacity = '0';
                truckBox2.style.opacity = '0';
                droppedBox1.style.opacity = '1';
                droppedBox2.style.opacity = '1';
                status.textContent = 'Delivered! Departure in progress...';
                sequence.classList.remove('unloading');
                sequence.classList.add('driving');
            }, 1500);

            window.setTimeout(() => {
                status.textContent = 'Order completed successfully!';
                HTMLFormElement.prototype.submit.call(form);
            }, 2800);
        });
    }

    // Filter Logic para sa Active Cards
    function filterCards() {
        const searchValue = document.getElementById('cardSearchInput').value.toLowerCase();
        const cards = document.querySelectorAll('#shipmentsGrid .shipment-card');

        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            if (text.includes(searchValue)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Filter Logic para sa History Table
    function filterTable() {
        const searchValue = document.getElementById('tableSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#historyTable tbody tr');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(searchValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>
<!-- try -->
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>