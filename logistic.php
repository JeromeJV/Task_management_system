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
$delivery_error = $_SESSION['delivery_error'] ?? '';
unset($_SESSION['delivery_error']);
$delivery_success = $_SESSION['delivery_success'] ?? '';
unset($_SESSION['delivery_success']);

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
    $completed_at = $hrec['delivered_at'] ?? null;
    if ($completed_at && date('Y-m-d', strtotime($completed_at)) === $today_date) {
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
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
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

        .status-badge.awaiting-approval {
            border: 1px solid #fecaca;
            background: #fff0f0;
            color: #b4232f;
        }

        .shipment-card.is-awaiting-approval {
            border-top-color: #dc3545;
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

        .shipment-attribution {
            display: grid;
            gap: 3px;
            margin: 10px 0 12px;
            color: var(--text-muted);
            font-size: 0.76rem;
        }

        .shipment-attribution strong {
            color: #475569;
        }

        .shipment-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .shipment-actions .btn-view-details {
            grid-column: 1 / -1;
        }

        .shipment-actions .delivery-activity-btn {
            grid-column: 1 / -1;
        }

        .feedback-button {
            padding: 7px 10px;
            border: 1px solid #fecaca;
            border-radius: 6px;
            background: #fff7f7;
            color: #b4232f;
            cursor: pointer;
            font-weight: 600;
        }

        .feedback-message {
            margin: 0;
            padding: 14px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff7f7;
            color: #7f1d1d;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
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

        .delivery-proof-label {
            display: block;
            margin: 16px 0 8px;
            color: var(--text-dark);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .delivery-proof-hint {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 400;
        }

        .delivery-proof-picker {
            display: flex;
            min-height: 92px;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 13px;
            padding: 16px;
            border: 2px dashed #b7cbbd;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fbf9, #eff7f1);
            color: #245b38;
            cursor: pointer;
            font: inherit;
            text-align: left;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }

        .delivery-proof-picker:hover,
        .delivery-proof-picker:focus-visible {
            border-color: var(--primary-green);
            background: #edf7ef;
            box-shadow: 0 0 0 3px rgba(45, 138, 84, 0.12);
        }

        .delivery-proof-picker:focus-visible {
            outline: 3px solid rgba(45, 138, 84, 0.2);
            outline-offset: 2px;
        }

        .delivery-proof-picker-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            place-items: center;
            border-radius: 12px;
            background: #dcefe1;
            color: #247144;
        }

        .delivery-proof-picker-icon svg {
            width: 23px;
            height: 23px;
        }

        .delivery-proof-picker-copy {
            display: grid;
            gap: 3px;
            text-align: left;
        }

        .delivery-proof-picker-copy strong {
            font-size: 0.88rem;
        }

        .delivery-proof-picker-copy span {
            color: var(--text-muted);
            font-size: 0.74rem;
        }

        .delivery-proof-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            clip-path: inset(50%);
            border: 0;
            opacity: 0;
        }

        .photo-source-overlay {
            z-index: 1100;
            background: rgba(15, 23, 42, 0.62);
            backdrop-filter: blur(4px);
        }

        .photo-source-card {
            width: min(100%, 430px);
            padding: 24px;
            border: 1px solid #e5ece7;
            border-radius: 18px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.24);
        }

        .photo-source-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .photo-source-header h3 {
            margin: 0 0 6px;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .photo-source-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .photo-source-close {
            display: grid;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid var(--border-color);
            border-radius: 9px;
            background: #fff;
            color: #66756b;
            font-size: 21px;
            cursor: pointer;
        }

        .photo-source-options {
            display: grid;
            gap: 10px;
        }

        .photo-source-option {
            display: flex;
            min-height: 70px;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            border: 1px solid #e0e9e3;
            border-radius: 12px;
            background: #fff;
            color: var(--text-dark);
            text-align: left;
            cursor: pointer;
            transition: border-color 150ms ease, background 150ms ease, transform 150ms ease;
        }

        .photo-source-option:hover,
        .photo-source-option:focus-visible {
            transform: translateY(-1px);
            border-color: #80b291;
            background: #f4faf5;
        }

        .photo-source-option:focus-visible,
        .photo-source-close:focus-visible {
            outline: 3px solid rgba(45, 138, 84, 0.2);
            outline-offset: 2px;
        }

        .photo-source-option-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 11px;
            background: #e7f3ea;
            color: #247144;
        }

        .photo-source-option-icon svg {
            width: 22px;
            height: 22px;
        }

        .photo-source-option-copy {
            display: grid;
            gap: 4px;
        }

        .photo-source-option-copy strong {
            font-size: 0.88rem;
        }

        .photo-source-option-copy span {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .photo-source-cancel {
            width: 100%;
            min-height: 40px;
            margin-top: 15px;
            border: 1px solid var(--border-color);
            border-radius: 9px;
            background: #fff;
            color: #526157;
            font-weight: 650;
            cursor: pointer;
        }

        .photo-source-cancel:hover {
            background: #f6f8f6;
        }

        @media (max-width: 576px) {
            .photo-source-card {
                padding: 20px;
                border-radius: 16px;
            }
        }

        .delivery-proof-selection {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-top: 10px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: #fff;
        }

        .delivery-proof-preview {
            display: block;
            width: 58px;
            height: 58px;
            flex: 0 0 58px;
            border-radius: 7px;
            background: #f1f5f2;
            object-fit: cover;
        }

        .delivery-proof-filename {
            min-width: 0;
            overflow: hidden;
            color: var(--text-dark);
            font-size: 0.78rem;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .delivery-proof-selection[hidden],
        .delivery-proof-preview[hidden] {
            display: none;
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
        .delivery-error { margin: 16px 20px; padding: 12px 16px; border: 1px solid #f1aeb5; border-radius: 8px; background: #f8d7da; color: #842029; }
        .delivery-success { margin: 16px 20px; padding: 12px 16px; border: 1px solid #a3cfbb; border-radius: 8px; background: #d1e7dd; color: #0f5132; }
        .delivery-filters { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 20px 18px; }
        .delivery-filters input, .delivery-filters select { min-height: 40px; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 6px; }
        .shipment-filter-controls {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
        }
        .history-filter-controls {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
        }
        .shipment-filter-controls > input,
        .history-filter-controls > input,
        .shipment-filter-controls > .select-control,
        .history-filter-controls > .select-control,
        .shipment-filter-controls > .calendar-picker,
        .history-filter-controls > .calendar-picker {
            width: 100%;
            min-width: 0;
            height: 46px;
            min-height: 46px;
            margin: 0;
        }
        .shipment-filter-controls > input,
        .history-filter-controls > input,
        .shipment-filter-controls > .select-control .select-control__trigger,
        .history-filter-controls > .select-control .select-control__trigger,
        .shipment-filter-controls > .calendar-picker .calendar-picker__trigger,
        .history-filter-controls > .calendar-picker .calendar-picker__trigger {
            width: 100%;
            height: 46px;
            min-height: 46px;
            border: 1px solid #d5e0d9;
            border-radius: 11px;
            background: linear-gradient(135deg, #ffffff 0%, #f7faf8 100%);
            color: #27372e;
            font-size: 13px;
            box-shadow: 0 2px 7px rgba(32, 65, 46, 0.045);
        }
        .shipment-filter-controls > input,
        .history-filter-controls > input {
            padding: 10px 13px;
        }
        .shipment-filter-controls > .select-control .select-control__trigger,
        .history-filter-controls > .select-control .select-control__trigger {
            padding: 10px 13px;
        }
        .shipment-filter-controls > .calendar-picker,
        .history-filter-controls > .calendar-picker {
            flex: initial;
        }
        .shipment-filter-controls > .calendar-picker .calendar-picker__trigger,
        .history-filter-controls > .calendar-picker .calendar-picker__trigger {
            padding: 10px 13px;
        }
        .container .delivery-filters .calendar-picker {
            width: auto;
            min-width: 170px;
            flex: 0 1 190px;
        }

        .container .delivery-filters .calendar-picker__trigger {
            min-height: 44px;
            padding: 10px 13px;
            border-color: #d9e5dc;
            border-radius: 11px;
            background: #ffffff;
            color: #263b2e;
            box-shadow: 0 3px 10px rgba(31, 76, 47, 0.06);
        }

        .container .delivery-filters .calendar-picker__trigger:hover:not(:disabled) {
            border-color: #7eae8d;
            background: #f6faf7;
        }

        .container .delivery-filters .calendar-picker__icon {
            border-color: var(--primary-green);
        }

        .container .delivery-filters .calendar-picker__icon::before {
            background: var(--primary-green);
        }

        .container .delivery-filters .calendar-picker__icon::after {
            border-color: var(--primary-green);
        }

        .container .delivery-filters .calendar-picker__popover {
            z-index: 1350;
            width: min(340px, calc(100vw - 32px));
            padding: 16px;
            border-color: #dce8df;
            border-radius: 17px;
            box-shadow: 0 20px 48px rgba(22, 54, 33, 0.2), 0 4px 12px rgba(22, 54, 33, 0.08);
        }

        .container .delivery-filters .calendar-picker__header {
            gap: 10px;
            margin: -2px -2px 13px;
            padding: 9px;
            border-radius: 12px;
            background: linear-gradient(135deg, #236b42, #358a55);
        }

        .container .delivery-filters .calendar-picker__title {
            color: #ffffff;
            font-size: 14px;
        }

        .container .delivery-filters .calendar-picker__nav {
            border-color: rgba(255, 255, 255, 0.24);
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        .container .delivery-filters .calendar-picker__nav:hover,
        .container .delivery-filters .calendar-picker__nav:focus-visible {
            border-color: rgba(255, 255, 255, 0.55);
            background: rgba(255, 255, 255, 0.22);
        }

        .container .delivery-filters .calendar-picker__grid {
            gap: 5px;
        }

        .container .delivery-filters .calendar-picker__weekday {
            height: 29px;
            color: #7c8a80;
            font-size: 10px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .container .delivery-filters .calendar-picker__cell {
            height: 37px;
            border-radius: 10px;
            font-size: 12px;
        }

        .container .delivery-filters .calendar-picker__cell:hover:not(:disabled),
        .container .delivery-filters .calendar-picker__cell:focus-visible {
            border-color: #c5ddcc;
            background: #edf6ef;
            color: #236b42;
        }

        .container .delivery-filters .calendar-picker__cell.is-today {
            border-color: #7eae8d;
            background: #f1f8f3;
            color: #236b42;
        }

        .container .delivery-filters .calendar-picker__cell[aria-pressed="true"] {
            border-color: #236b42;
            background: linear-gradient(135deg, #236b42, #358a55);
            color: #ffffff;
            box-shadow: 0 4px 9px rgba(35, 107, 66, 0.22);
        }

        .container .delivery-filters .calendar-picker__footer {
            margin-top: 13px;
            padding-top: 11px;
            border-color: #e8eee9;
        }

        .container .delivery-filters .calendar-picker__text-action {
            padding: 7px 9px;
            border-radius: 8px;
        }

        .container .delivery-filters .calendar-picker__text-action--primary {
            background: #edf6ef;
            color: #236b42;
        }

        .container .delivery-filters .calendar-picker--month .calendar-picker__cell {
            height: 40px;
        }

        .container .shipment-filter-controls > .calendar-picker,
        .container .history-filter-controls > .calendar-picker {
            width: 100%;
            min-width: 0;
            flex: initial;
        }

        .container .shipment-filter-controls > .calendar-picker .calendar-picker__trigger,
        .container .history-filter-controls > .calendar-picker .calendar-picker__trigger {
            height: 46px;
            min-height: 46px;
            border-color: #d5e0d9;
            background: linear-gradient(135deg, #ffffff 0%, #f7faf8 100%);
            box-shadow: 0 2px 7px rgba(32, 65, 46, 0.045);
        }

        .shipment-filter-controls > input:focus-visible,
        .history-filter-controls > input:focus-visible {
            outline: 3px solid rgba(31, 110, 74, 0.18);
            outline-offset: 2px;
            border-color: #28794f;
        }

        .delivery-due-badge { display: inline-block; margin-top: 7px; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
        .delivery-due-overdue { background: var(--badge-red-bg); color: var(--badge-red-text); }
        .delivery-due-soon { background: var(--badge-yellow-bg); color: var(--badge-yellow-text); }
        .delivery-due-normal { background: #e9ecef; color: #495057; }
        .delivery-activity-btn { padding: 7px 10px; border: 1px solid var(--primary-green); border-radius: 6px; background: white; color: var(--primary-green); cursor: pointer; }
        #deliveryActivityModal {
            background: rgba(15, 23, 42, 0.56);
            backdrop-filter: blur(5px);
        }
        #deliveryActivityModal .delivery-activity-modal {
            width: min(100%, 620px);
            max-height: min(82vh, 720px);
            padding: 0;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
            animation: delivery-activity-enter 180ms ease-out;
        }
        #deliveryActivityModal .delivery-activity-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin: 0;
            padding: 24px 26px 18px;
            border-bottom: 1px solid #e8edf2;
        }
        .delivery-activity-heading { display: grid; gap: 5px; }
        .delivery-activity-kicker {
            color: #258152;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.13em;
        }
        #deliveryActivityModal #deliveryActivityTitle {
            margin: 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 750;
        }
        #deliveryActivityModal .delivery-activity-close {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid #e3e8ee;
            border-radius: 10px;
            background: #fff;
            color: #64748b;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
        }
        #deliveryActivityModal .delivery-activity-close:hover {
            border-color: #c8dfd0;
            background: #f0f8f2;
            color: #1f6e4a;
        }
        #deliveryActivityModal .delivery-activity-close:focus-visible {
            outline: 3px solid rgba(37, 129, 82, 0.25);
            outline-offset: 2px;
        }
        .delivery-activity-context {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 26px 0;
            padding: 13px 15px;
            border: 1px solid #e1eee5;
            border-radius: 12px;
            background: linear-gradient(135deg, #f4faf5, #f8fbf9);
        }
        .delivery-activity-context-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 10px;
            background: #e3f2e8;
            color: #23784c;
            font-size: 1.1rem;
            font-weight: 800;
        }
        #deliveryActivityProduct {
            min-width: 0;
            margin: 0;
            color: #334155;
            font-size: 0.92rem;
            font-weight: 650;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }
        #deliveryActivityContent {
            max-height: min(55vh, 470px);
            overflow-y: auto;
            padding: 22px 26px 26px;
        }
        .delivery-activity-list {
            display: grid;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .delivery-activity-item {
            position: relative;
            display: grid;
            grid-template-columns: 32px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }
        .delivery-activity-item:not(:last-child)::before {
            position: absolute;
            top: 30px;
            bottom: -15px;
            left: 15px;
            width: 2px;
            background: #e4eee7;
            content: "";
        }
        .delivery-activity-marker {
            z-index: 1;
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border: 1px solid #cfe6d6;
            border-radius: 50%;
            background: #eff8f1;
            color: #23784c;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .delivery-activity-entry {
            min-width: 0;
            padding: 13px 15px;
            border: 1px solid #e8edf1;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 7px rgba(15, 23, 42, 0.035);
        }
        .delivery-activity-entry strong {
            display: block;
            color: #334155;
            font-size: 0.9rem;
            font-weight: 650;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }
        .delivery-activity-entry time {
            display: block;
            margin-top: 7px;
            color: #7b8794;
            font-size: 0.76rem;
            font-weight: 550;
            font-variant-numeric: tabular-nums;
        }
        .delivery-activity-message {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 0;
            padding: 18px;
            border: 1px dashed #d7e2db;
            border-radius: 12px;
            background: #f8fbf9;
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.55;
        }
        .delivery-activity-message.is-error {
            border-color: #f0cccc;
            background: #fff7f7;
            color: #9f3434;
        }
        .delivery-activity-spinner {
            width: 17px;
            height: 17px;
            flex: 0 0 auto;
            border: 2px solid #d8e9dd;
            border-top-color: #258152;
            border-radius: 50%;
            animation: delivery-activity-spin 700ms linear infinite;
        }
        @keyframes delivery-activity-spin { to { transform: rotate(360deg); } }
        @keyframes delivery-activity-enter {
            from { opacity: 0; transform: translateY(8px) scale(0.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @media (max-width: 576px) {
            #deliveryActivityModal { align-items: flex-end; padding: 10px; }
            #deliveryActivityModal .delivery-activity-modal {
                max-height: 84vh;
                border-radius: 18px;
            }
            #deliveryActivityModal .delivery-activity-header { padding: 20px 18px 15px; }
            .delivery-activity-context { margin: 14px 18px 0; }
            #deliveryActivityContent { max-height: 58vh; padding: 18px; }
        }
        @media (prefers-reduced-motion: reduce) {
            #deliveryActivityModal .delivery-activity-modal { animation: none; }
            .delivery-activity-spinner { animation-duration: 1.4s; }
        }
        @media (max-width: 767px) {
            .shipment-filter-controls,
            .history-filter-controls {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

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

            .container .delivery-filters .calendar-picker {
                min-width: min(100%, 170px);
                flex: 1 1 145px;
            }
            .container .shipment-filter-controls > .calendar-picker,
            .container .history-filter-controls > .calendar-picker {
                min-width: 0;
                flex: initial;
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
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/action-confirmation.css?v=<?= filemtime(__DIR__ . '/css/action-confirmation.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/config/notification_bell.php'; ?>

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
    <?php if ($delivery_error !== ''): ?>
        <div class="delivery-error" role="alert"><?= htmlspecialchars($delivery_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($delivery_success !== ''): ?>
        <div class="delivery-success" role="status"><?= htmlspecialchars($delivery_success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- Dashboard Metrics Top Cards -->
    <div class="metrics-grid" id="dashboardMetrics">
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
        </div>
        <div class="delivery-filters shipment-filter-controls">
            <input type="search" id="cardSearchInput" placeholder="Search ID, product, destination..." oninput="filterCards()">
            <select id="cardStatusFilter" onchange="filterCards()">
                <option value="">All statuses</option>
                <option value="awaiting approval">Waiting for approval</option>
                <option value="changes requested">Changes requested</option>
                <?php foreach (array_unique(array_map(static fn($record) => (string) ($record['status'] ?? 'Pending'), array_filter($pending_records, static fn($record) => !in_array(($record['completion_review_status'] ?? ''), ['pending_review', 'rejected'], true)))) as $status_option): ?>
                    <option value="<?= htmlspecialchars(strtolower($status_option), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="cardDueFrom" class="calendar-control" aria-label="Due from" onchange="filterCards()">
            <input type="date" id="cardDueTo" class="calendar-control" aria-label="Due to" onchange="filterCards()">
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
                    $status_label = match ($row['completion_review_status'] ?? '') {
                        'pending_review' => 'Awaiting Approval',
                        'rejected' => 'Changes Requested',
                        default => (string) ($row['status'] ?? 'Pending'),
                    };
                    $status = htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8');
                    $review_note = (string) ($row['completion_review_note'] ?? '');
                    $assigned_by_name = trim((string) ($row['assigned_by_name'] ?? ''));
                    $reviewed_by_name = trim((string) ($row['completion_reviewed_by_name'] ?? ''));
                    $due_date = $row['delivery_date'] ?? '';
                    $days_until_due = $due_date ? (int) floor((strtotime($due_date) - strtotime($today_date)) / 86400) : null;
                    $due_label = $days_until_due === null ? '' : ($days_until_due < 0 ? 'Overdue by ' . abs($days_until_due) . ' day(s)' : ($days_until_due <= 2 ? ($days_until_due === 0 ? 'Due today' : 'Due in ' . $days_until_due . ' day(s)') : 'Scheduled'));
                    $due_class = $days_until_due !== null && $days_until_due < 0 ? 'delivery-due-overdue' : ($days_until_due !== null && $days_until_due <= 2 ? 'delivery-due-soon' : 'delivery-due-normal');
                ?>
                    <div class="shipment-card <?= in_array(strtolower($status_label), ['awaiting approval', 'changes requested'], true) ? 'is-awaiting-approval' : ''; ?>"
                         data-id="<?= $del_id; ?>"
                         data-product="<?= $prod_name; ?>"
                         data-route="<?= $route; ?>"
                         data-pieces="<?= $pieces; ?>"
                         data-stock="<?= $stock; ?>"
                         data-date="<?= $date; ?>"
                         data-status="<?= htmlspecialchars(strtolower($status_label), ENT_QUOTES, 'UTF-8'); ?>"
                         data-review-note="<?= htmlspecialchars($review_note, ENT_QUOTES, 'UTF-8'); ?>"
                         data-assigned-by="<?= htmlspecialchars($assigned_by_name !== '' ? $assigned_by_name : 'Not recorded', ENT_QUOTES, 'UTF-8'); ?>"
                         data-reviewed-by="<?= htmlspecialchars($reviewed_by_name, ENT_QUOTES, 'UTF-8'); ?>"
                         data-reviewed-at="<?= htmlspecialchars((string) ($row['completion_reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                         data-due-date="<?= htmlspecialchars((string) $due_date, ENT_QUOTES, 'UTF-8'); ?>"
                         onclick="openDetailModal(this)">
                        
                        <div class="card-header">
                            <span class="shipment-id">#SHP-IN-<?= $del_id; ?></span>
                            <span class="status-badge <?= in_array(strtolower($status_label), ['awaiting approval', 'changes requested'], true) ? 'awaiting-approval' : (strtolower($status_label) === 'delivered' ? 'delivered' : 'in-transit'); ?>">
                                <?= $status_label === 'Pending' ? 'IN TRANSIT' : (strtolower($status_label) === 'awaiting approval' ? 'REQUEST SENT · WAITING FOR APPROVAL' : strtoupper($status_label)); ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <div><strong>&bull; Product:</strong> <?= $prod_name; ?></div>
                            <div><strong>&bull; Destination:</strong> <?= $route; ?></div>
                            <div><strong>&bull; Date:</strong> <?= $date; ?></div>
                        </div>
                        <div class="shipment-attribution">
                            <span><strong>Assigned:</strong> <?= htmlspecialchars($assigned_by_name !== '' ? $assigned_by_name : 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (in_array(($row['completion_review_status'] ?? ''), ['approved', 'rejected'], true)): ?>
                                <span><strong><?= ($row['completion_review_status'] ?? '') === 'approved' ? 'Approved:' : 'Changes by:'; ?></strong>
                                    <?= htmlspecialchars($reviewed_by_name !== '' ? $reviewed_by_name : 'Not recorded', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($row['completion_reviewed_at'])): ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $row['completion_reviewed_at'])), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($due_label !== ''): ?><span class="delivery-due-badge <?= $due_class; ?>"><?= htmlspecialchars($due_label, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        </div>

                        <div class="shipment-actions">
                            <button class="btn-view-details" type="button" onclick="event.stopPropagation(); openDetailModal(this.closest('.shipment-card'))">View Details</button>
                            <?php if (trim($review_note) !== ''): ?>
                                <button class="feedback-button" type="button"
                                        data-feedback="<?= htmlspecialchars($review_note, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-reviewer="<?= htmlspecialchars($reviewed_by_name !== '' ? $reviewed_by_name : 'Not recorded', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-reviewed-at="<?= htmlspecialchars((string) ($row['completion_reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="event.stopPropagation(); openReviewFeedback(this)">Supervisor feedback</button>
                            <?php endif; ?>
                            <button class="delivery-activity-btn" type="button" data-activity-id="<?= $del_id; ?>" data-activity-product="<?= $prod_name; ?>">Activity</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p id="activeFilterEmpty" hidden style="padding: 0 20px 20px; color: var(--text-muted);">No shipments match these filters.</p>
        <?php else: ?>
            <p style="padding: 0 20px 20px; color: var(--text-muted);">No active shipment records found.</p>
        <?php endif; ?>
    </div>

    <!-- HISTORY VIEW: TABLE -->
    <div id="historyView" class="hidden">
        <div class="section-header">
            <div class="section-title">History Records</div>
        </div>
        <div class="delivery-filters history-filter-controls">
            <input type="search" id="tableSearchInput" placeholder="Search ID, product, destination..." oninput="filterTable()">
            <input type="date" id="historyDueFrom" class="calendar-control" aria-label="Delivery date from" onchange="filterTable()">
            <input type="date" id="historyDueTo" class="calendar-control" aria-label="Delivery date to" onchange="filterTable()">
        </div>

        <div class="table-container table-scroll">
            <?php if (!empty($history_records)): ?>
                <table class="custom-table" id="historyTable">
                    <thead>
                        <tr data-delivery-date="<?= htmlspecialchars((string) ($row['delivery_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
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
                                                data-assigned-by="<?= htmlspecialchars(trim((string) ($row['assigned_by_name'] ?? '')) ?: 'Not recorded', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-reviewed-by="<?= htmlspecialchars(trim((string) ($row['completion_reviewed_by_name'] ?? '')) ?: 'Not recorded', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-reviewed-at="<?= htmlspecialchars((string) ($row['completion_reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="openDetailModal(this, true)">View Details</button>
                                        <button type="button" class="delivery-activity-btn" data-activity-id="<?= $del_id; ?>" data-activity-product="<?= $prod_name; ?>">Activity</button>
                                        
                                        <form action="delivery_main.php" method="post" data-confirm="Are you sure you want to delete this delivery history record?">
                                            <input type="hidden" name="idno" value="<?= $del_id; ?>">
                                            <button type="submit" name="del" class="btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p id="historyFilterEmpty" hidden style="color: var(--text-muted);">No history records match these filters.</p>
            <?php else: ?>
                <p style="color: var(--text-muted);">No delivery history yet.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="modal-overlay" id="deliveryActivityModal" role="dialog" aria-modal="true" aria-labelledby="deliveryActivityTitle" onclick="if (event.target === this) closeDeliveryActivity()">
    <div class="modal-card delivery-activity-modal" tabindex="-1">
        <div class="delivery-activity-header">
            <div class="delivery-activity-heading">
                <span class="delivery-activity-kicker">SHIPMENT TIMELINE</span>
                <h3 id="deliveryActivityTitle">Delivery Activity</h3>
            </div>
            <button type="button" class="delivery-activity-close" onclick="closeDeliveryActivity()" aria-label="Close activity history">&times;</button>
        </div>
        <div class="delivery-activity-context">
            <span class="delivery-activity-context-icon" aria-hidden="true">↗</span>
            <p id="deliveryActivityProduct"></p>
        </div>
        <div id="deliveryActivityContent" aria-live="polite" aria-busy="false"></div>
    </div>
</div>

<div class="modal-overlay" id="reviewFeedbackModal" role="dialog" aria-modal="true" aria-labelledby="reviewFeedbackTitle" onclick="if (event.target === this) closeModal('reviewFeedbackModal')">
    <div class="modal-card" style="max-width: 440px;">
        <div class="modal-header">
            <h3 id="reviewFeedbackTitle">Supervisor feedback</h3>
            <button type="button" class="close-btn" onclick="closeModal('reviewFeedbackModal')" aria-label="Close">&times;</button>
        </div>
        <p id="reviewFeedbackMeta" style="margin: 0 0 12px; color: var(--text-muted); font-size: 0.85rem;"></p>
        <p id="reviewFeedbackMessage" class="feedback-message"></p>
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
            <div id="modalAssignmentReview" style="grid-column: 1 / -1; white-space: pre-line; color: var(--text-muted); font-size: 0.85rem;"></div>
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

        <form id="deliveryForm" action="logistic.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="idno" id="modalFormId">
            <div class="delivery-proof-label">
                Delivery proof photo
                <span class="delivery-proof-hint">JPG, PNG, or WebP · up to 5 MB</span>
            </div>
            <button type="button" class="delivery-proof-picker" id="openPhotoSourceButton" aria-haspopup="dialog" aria-controls="photoSourceModal">
                <span class="delivery-proof-picker-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H9l1.4-1.5h3.2L15 5h2.5A2.5 2.5 0 0 1 20 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <circle cx="12" cy="12.5" r="3.5" stroke="currentColor" stroke-width="1.7"/>
                    </svg>
                </span>
                <span class="delivery-proof-picker-copy">
                    <strong>Choose a photo or take one</strong>
                    <span>Tap here to add delivery proof</span>
                </span>
            </button>
            <input type="file" class="delivery-proof-input" id="deliveryProofInput" name="delivery_proof" accept="image/jpeg,image/png,image/webp" required>
            <div class="delivery-proof-selection" id="deliveryProofSelection" hidden>
                <img class="delivery-proof-preview" id="deliveryProofPreview" alt="Selected delivery proof preview" hidden>
                <span class="delivery-proof-filename" id="deliveryProofFilename"></span>
            </div>
            <button type="button" class="btn-confirm-delivery" id="modalSubmitBtn" onclick="openConfirmModal()">Confirm Delivery</button>
        </form>
    </div>
</div>

<div class="modal-overlay photo-source-overlay" id="photoSourceModal" role="dialog" aria-modal="true" aria-labelledby="photoSourceTitle" aria-describedby="photoSourceDescription" onclick="if (event.target === this) closePhotoSourceModal()">
    <section class="modal-card photo-source-card">
        <div class="photo-source-header">
            <div>
                <h3 id="photoSourceTitle">Add delivery proof</h3>
                <p id="photoSourceDescription">Choose how you want to add the delivery photo.</p>
            </div>
            <button type="button" class="photo-source-close" id="closePhotoSourceButton" aria-label="Close photo options">&times;</button>
        </div>
        <div class="photo-source-options">
            <button type="button" class="photo-source-option" id="takeDeliveryPhotoButton" data-photo-source="camera">
                <span class="photo-source-option-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H9l1.4-1.5h3.2L15 5h2.5A2.5 2.5 0 0 1 20 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <circle cx="12" cy="12.5" r="3.5" stroke="currentColor" stroke-width="1.7"/>
                    </svg>
                </span>
                <span class="photo-source-option-copy">
                    <strong>Take a photo</strong>
                    <span>Open your device camera</span>
                </span>
            </button>
            <button type="button" class="photo-source-option" id="chooseDeliveryPhotoButton" data-photo-source="gallery">
                <span class="photo-source-option-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="3.5" y="4.5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.7"/>
                        <circle cx="9" cy="10" r="1.7" stroke="currentColor" stroke-width="1.5"/>
                        <path d="m5 17 4.5-4.5 3 3 2.5-2.5 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="photo-source-option-copy">
                    <strong>Choose from gallery</strong>
                    <span>Select an existing photo from your device</span>
                </span>
            </button>
        </div>
        <button type="button" class="photo-source-cancel" id="cancelPhotoSourceButton">Cancel</button>
    </section>
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
        const dashboardMetrics = document.getElementById('dashboardMetrics');
        const tabMainBtn = document.getElementById('tabMainBtn');
        const tabHistoryBtn = document.getElementById('tabHistoryBtn');
        mainView.classList.toggle('hidden', tabName !== 'main');
        historyView.classList.toggle('hidden', tabName !== 'history');
        dashboardMetrics.classList.toggle('hidden', tabName !== 'main');
        tabMainBtn.classList.toggle('active', tabName === 'main');
        tabHistoryBtn.classList.toggle('active', tabName === 'history');
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
        const assignedBy = element.getAttribute('data-assigned-by') || 'Not recorded';
        const reviewedBy = element.getAttribute('data-reviewed-by') || '';
        const reviewedAt = element.getAttribute('data-reviewed-at') || '';

        document.getElementById('modalShipmentId').innerText = 'Shipment #SHP-IN-' + id;
        document.getElementById('modalProduct').innerText = product;
        document.getElementById('modalQuantity').innerText = pieces + ' pcs';
        document.getElementById('modalStock').innerText = stock;
        document.getElementById('modalDestination').innerText = route;
        document.getElementById('modalDate').innerText = date;
        document.getElementById('modalFormId').value = id;

        const deliveryForm = document.getElementById('deliveryForm');
        const proofInput = document.getElementById('deliveryProofInput');
        const proofSelection = document.getElementById('deliveryProofSelection');
        const proofPreview = document.getElementById('deliveryProofPreview');
        const proofFilename = document.getElementById('deliveryProofFilename');
        if (proofPreview.dataset.objectUrl) {
            URL.revokeObjectURL(proofPreview.dataset.objectUrl);
            delete proofPreview.dataset.objectUrl;
        }
        proofInput.value = '';
        proofInput.removeAttribute('capture');
        proofSelection.hidden = true;
        proofPreview.hidden = true;
        proofPreview.removeAttribute('src');
        proofFilename.textContent = '';
        deliveryForm.dataset.deliverySubmitting = 'false';
        const confirmButton = document.querySelector('#confirmModal .btn-confirm-delivery');
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirm Delivery';

        const submitBtn = document.getElementById('modalSubmitBtn');
        const stepDelivered = document.getElementById('stepDelivered');
        const statusText = document.getElementById('modalStatusText');

        const assignmentReviewElement = document.getElementById('modalAssignmentReview');
        assignmentReviewElement.textContent = 'Assigned by: ' + assignedBy
            + (reviewedBy ? '\n' + (status === 'changes requested' ? 'Changes requested by: ' : 'Reviewed by: ') + reviewedBy : '')
            + (reviewedAt ? ' · ' + new Date(reviewedAt.replace(' ', 'T')).toLocaleString() : '');

        if (isHistory || status === 'delivered' || status === 'completed') {
            deliveryForm.style.display = 'none';
            submitBtn.style.display = 'none';
            stepDelivered.classList.add('active');
            stepDelivered.innerHTML = '&#10003; Delivered';
            statusText.innerText = 'COMPLETED';
        } else if (status === 'awaiting approval') {
            deliveryForm.style.display = 'none';
            submitBtn.style.display = 'none';
            stepDelivered.classList.remove('active');
            stepDelivered.innerHTML = '&#9675; Awaiting Approval';
            statusText.innerText = 'AWAITING SUPERVISOR APPROVAL';
        } else {
            deliveryForm.style.display = 'block';
            submitBtn.style.display = 'block';
            stepDelivered.classList.remove('active');
            stepDelivered.innerHTML = '&#9675; Delivered';
            statusText.innerText = 'IN TRANSIT';
        }

        document.getElementById('detailModal').style.display = 'flex';
    }

    const photoSourceModal = document.getElementById('photoSourceModal');
    const openPhotoSourceButton = document.getElementById('openPhotoSourceButton');
    const deliveryProofInput = document.getElementById('deliveryProofInput');
    let photoSourceTrigger = null;

    openPhotoSourceButton.addEventListener('click', () => {
        photoSourceTrigger = openPhotoSourceButton;
        photoSourceModal.style.display = 'flex';
        document.getElementById('takeDeliveryPhotoButton').focus();
    });

    function closePhotoSourceModal(restoreFocus = true) {
        photoSourceModal.style.display = 'none';
        if (restoreFocus) {
            photoSourceTrigger?.focus();
        }
    }

    document.getElementById('closePhotoSourceButton').addEventListener('click', () => closePhotoSourceModal());
    document.getElementById('cancelPhotoSourceButton').addEventListener('click', () => closePhotoSourceModal());

    photoSourceModal.querySelectorAll('[data-photo-source]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.photoSource === 'camera') {
                deliveryProofInput.setAttribute('capture', 'environment');
            } else {
                deliveryProofInput.removeAttribute('capture');
            }
            closePhotoSourceModal(false);
            deliveryProofInput.click();
        });
    });

    photoSourceModal.addEventListener('click', (event) => {
        if (event.target === photoSourceModal) {
            closePhotoSourceModal();
        }
    });

    deliveryProofInput.addEventListener('change', (event) => {
        const input = event.currentTarget;
        const selection = document.getElementById('deliveryProofSelection');
        const preview = document.getElementById('deliveryProofPreview');
        const filename = document.getElementById('deliveryProofFilename');
        const file = input.files && input.files[0];

        if (preview.dataset.objectUrl) {
            URL.revokeObjectURL(preview.dataset.objectUrl);
            delete preview.dataset.objectUrl;
        }
        if (!file) {
            selection.hidden = true;
            preview.hidden = true;
            preview.removeAttribute('src');
            filename.textContent = '';
            return;
        }

        filename.textContent = file.name;
        selection.hidden = false;
        if (file.type.startsWith('image/')) {
            const objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.dataset.objectUrl = objectUrl;
            preview.hidden = false;
        } else {
            preview.hidden = true;
            preview.removeAttribute('src');
        }
    });

    function openReviewFeedback(button) {
        const feedback = button.dataset.feedback || '';
        const reviewer = button.dataset.reviewer || 'Not recorded';
        const reviewedAt = button.dataset.reviewedAt || '';
        document.getElementById('reviewFeedbackMeta').textContent = 'Changes requested by: ' + reviewer
            + (reviewedAt ? ' · ' + new Date(reviewedAt.replace(' ', 'T')).toLocaleString() : '');
        document.getElementById('reviewFeedbackMessage').textContent = feedback;
        document.getElementById('reviewFeedbackModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function openConfirmModal() {
        const proofInput = document.getElementById('deliveryProofInput');
        if (!validateDeliveryProof(proofInput)) {
            return;
        }
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function validateDeliveryProof(input) {
        const file = input.files && input.files[0];
        let message = '';
        if (!file) {
            message = 'Choose a delivery proof photo before submitting.';
        } else if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            message = 'Choose a JPG, PNG, or WebP photo.';
        } else if (file.size < 1) {
            message = 'The selected photo is empty. Choose another photo.';
        } else if (file.size > 5 * 1024 * 1024) {
            message = 'The delivery proof photo must be no larger than 5 MB.';
        }

        input.setCustomValidity(message);
        if (message) {
            input.reportValidity();
            return false;
        }
        return true;
    }

    // INAYOS NA CONFIRM / SUBMIT FUNCTION
    function submitDelivery() {
        const form = document.getElementById('deliveryForm');
        if (form.dataset.deliverySubmitting === 'true') {
            return;
        }
        if (!validateDeliveryProof(document.getElementById('deliveryProofInput'))) {
            closeModal('confirmModal');
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
        const searchValue = document.getElementById('cardSearchInput').value.trim().toLowerCase();
        const statusValue = document.getElementById('cardStatusFilter').value;
        const dueFrom = document.getElementById('cardDueFrom').value;
        const dueTo = document.getElementById('cardDueTo').value;
        const cards = document.querySelectorAll('#shipmentsGrid .shipment-card');
        let visibleCount = 0;
        cards.forEach(card => {
            const dueDate = card.dataset.dueDate || '';
            const visible = card.innerText.toLowerCase().includes(searchValue)
                && (!statusValue || card.dataset.status.toLowerCase() === statusValue)
                && (!dueFrom || dueDate >= dueFrom)
                && (!dueTo || dueDate <= dueTo);
            card.style.display = visible ? 'block' : 'none';
            if (visible) visibleCount++;
        });
        const emptyMessage = document.getElementById('activeFilterEmpty');
        emptyMessage.hidden = cards.length === 0 || visibleCount > 0;
    }

    // Filter Logic para sa History Table
    function filterTable() {
        const searchValue = document.getElementById('tableSearchInput').value.trim().toLowerCase();
        const dueFrom = document.getElementById('historyDueFrom').value;
        const dueTo = document.getElementById('historyDueTo').value;
        const rows = document.querySelectorAll('#historyTable tbody tr');
        let visibleCount = 0;
        rows.forEach(row => {
            const dueDate = row.dataset.deliveryDate || '';
            const visible = row.innerText.toLowerCase().includes(searchValue)
                && (!dueFrom || dueDate >= dueFrom)
                && (!dueTo || dueDate <= dueTo);
            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });
        const emptyMessage = document.getElementById('historyFilterEmpty');
        emptyMessage.hidden = rows.length === 0 || visibleCount > 0;
    }

    let deliveryActivityTrigger = null;
    document.querySelectorAll('[data-activity-id]').forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            deliveryActivityTrigger = button;
            openDeliveryActivity(button.dataset.activityId, button.dataset.activityProduct);
        });
    });

    async function openDeliveryActivity(deliveryId, productName) {
        const modal = document.getElementById('deliveryActivityModal');
        const content = document.getElementById('deliveryActivityContent');
        const closeButton = modal.querySelector('.delivery-activity-close');
        document.getElementById('deliveryActivityProduct').textContent =
            `Delivery #${deliveryId} — ${productName}`;
        content.setAttribute('aria-busy', 'true');
        const loadingMessage = document.createElement('p');
        loadingMessage.className = 'delivery-activity-message';
        const spinner = document.createElement('span');
        spinner.className = 'delivery-activity-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        loadingMessage.append(spinner, document.createTextNode('Loading shipment activity...'));
        content.replaceChildren(loadingMessage);
        modal.style.display = 'flex';
        closeButton.focus();

        try {
            const response = await fetch(`logistic.php?activity_log=${encodeURIComponent(deliveryId)}`, {
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || result.error) {
                throw new Error(result.error || 'Unable to load activity history.');
            }
            content.replaceChildren();
            if (result.activities.length === 0) {
                const emptyMessage = document.createElement('p');
                emptyMessage.className = 'delivery-activity-message';
                emptyMessage.textContent = 'No activity has been recorded for this delivery yet.';
                content.appendChild(emptyMessage);
                return;
            }
            const list = document.createElement('ol');
            list.className = 'delivery-activity-list';
            result.activities.forEach(activity => {
                const item = document.createElement('li');
                item.className = 'delivery-activity-item';
                const marker = document.createElement('span');
                marker.className = 'delivery-activity-marker';
                marker.setAttribute('aria-hidden', 'true');
                const markerIcons = {
                    delivery_assigned: '↗',
                    driver_reassigned: '↻',
                    delivery_updated: '✎',
                    delivery_submitted_for_review: '↑',
                    delivery_approved: '✓',
                    delivery_changes_requested: '!',
                    delivery_completed: '✓'
                };
                marker.textContent = markerIcons[activity.event_type] || '•';
                const entry = document.createElement('div');
                entry.className = 'delivery-activity-entry';
                const summary = document.createElement('strong');
                const eventMessages = {
                    delivery_assigned: `${activity.actor_name} assigned this delivery${activity.to_driver_name ? ` to ${activity.to_driver_name}` : ''}.`,
                    driver_reassigned: `${activity.actor_name} reassigned this delivery${activity.from_driver_name ? ` from ${activity.from_driver_name}` : ''}${activity.to_driver_name ? ` to ${activity.to_driver_name}` : ''}.`,
                    delivery_updated: `${activity.actor_name} updated the delivery details.`,
                    delivery_submitted_for_review: `${activity.actor_name} submitted this delivery for supervisor approval.`,
                    delivery_approved: `${activity.actor_name} approved this delivery.`,
                    delivery_changes_requested: `${activity.actor_name} requested changes for this delivery.`,
                    delivery_completed: `${activity.actor_name} confirmed delivery.`
                };
                summary.textContent = eventMessages[activity.event_type] || `${activity.actor_name} recorded an update for this delivery.`;
                const time = document.createElement('time');
                time.dateTime = activity.occurred_at.replace(' ', 'T');
                const occurredAt = new Date(activity.occurred_at.replace(' ', 'T'));
                time.textContent = Number.isNaN(occurredAt.getTime())
                    ? activity.occurred_at
                    : new Intl.DateTimeFormat('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }).format(occurredAt);
                entry.append(summary, time);
                item.append(marker, entry);
                list.appendChild(item);
            });
            content.appendChild(list);
        } catch (error) {
            const errorMessage = document.createElement('p');
            errorMessage.className = 'delivery-activity-message is-error';
            errorMessage.setAttribute('role', 'alert');
            errorMessage.textContent = error.message || 'Unable to load activity history.';
            content.replaceChildren(errorMessage);
        } finally {
            content.setAttribute('aria-busy', 'false');
        }
    }

    function closeDeliveryActivity() {
        document.getElementById('deliveryActivityModal').style.display = 'none';
        deliveryActivityTrigger?.focus();
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && photoSourceModal.style.display === 'flex') {
            closePhotoSourceModal();
            return;
        }
        const modal = document.getElementById('deliveryActivityModal');
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            closeDeliveryActivity();
        }
    });

</script>
<!-- try -->
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
<script src="js/action-confirmation.js?v=<?= filemtime(__DIR__ . '/js/action-confirmation.js'); ?>"></script>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>