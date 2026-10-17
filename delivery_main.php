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
$message = (string) ($message ?? '');
$status_message = (string) ($status_message ?? '');
$delivery_error = $_SESSION['delivery_error'] ?? '';
unset($_SESSION['delivery_error']);
$delivery_update_error = '';
if ($action === 'update' && isset($_POST['submit'])) {
    $delivery_update_error = (string) (
        $errors['delivery_id']
        ?? $errors['driver_id']
        ?? $errors['route']
        ?? $errors['delivery_date']
        ?? ''
    );
}
$delivery_review_success = $_SESSION['delivery_success'] ?? '';
unset($_SESSION['delivery_success']);
$active_delivery_section = isset($_GET['report_month']) || isset($_GET['report_year']) ? 'reports' : 'pending';
$delivery_assignment_result = null;
$delivery_assignment_feedback = '';
if ($action === 'insert' && $message !== '') {
    $delivery_assignment_result = 'success';
    $delivery_assignment_feedback = $message;
} elseif ($action === 'insert' && (isset($errors['assignments']) || isset($errors['production_id']))) {
    $delivery_assignment_result = 'error';
    $delivery_assignment_feedback = (string) ($errors['assignments'] ?? $errors['production_id']);
}
$posted_delivery_groups = $_POST['assignments'] ?? [];
if (!is_array($posted_delivery_groups)) {
    $posted_delivery_groups = [];
}

$delivery_drivers = [];
$delivery_team_result = mysqli_query($conn, "
    SELECT u.id,
           u.name,
           u.profile_picture,
           CASE
               WHEN EXISTS (
                   SELECT 1
                   FROM attendance a
                   LEFT JOIN employee e ON e.employee_id = a.employee_id
                   WHERE DATE(a.attendance_date) = CURDATE()
                     AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                     AND a.time_in IS NOT NULL
                     AND a.time_in != '00:00:00'
                     AND (
                         a.user_id = u.id
                         OR e.username = u.name
                         OR (e.email IS NOT NULL AND e.email != '' AND e.email = u.email)
                         OR EXISTS (
                             SELECT 1
                             FROM attendance driver_user_link
                             WHERE driver_user_link.employee_id = e.employee_id
                               AND driver_user_link.user_id = u.id
                         )
                     )
               ) THEN 'Present'
               ELSE 'Absent'
           END AS attendance_status
    FROM users u
    WHERE u.role = 'log'
    ORDER BY u.name
");
if (!$delivery_team_result) {
    throw new RuntimeException('Unable to load delivery drivers: ' . mysqli_error($conn));
}
while ($delivery_driver = mysqli_fetch_assoc($delivery_team_result)) {
    $delivery_drivers[] = $delivery_driver;
}
$present_delivery_count = count(array_filter(
    $delivery_drivers,
    static fn ($delivery_driver) => $delivery_driver['attendance_status'] === 'Present'
));
$absent_delivery_count = count($delivery_drivers) - $present_delivery_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistic Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/delivery_main.css?v=<?= filemtime(__DIR__ . '/css/delivery_main.css'); ?>">
    <link rel="stylesheet" href="css/action-confirmation.css?v=<?= filemtime(__DIR__ . '/css/action-confirmation.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
    <link rel="stylesheet" href="css/task-review-dialog.css?v=<?= filemtime(__DIR__ . '/css/task-review-dialog.css'); ?>">
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
        .delivery-review-success {
            margin: 15px 0;
            padding: 10px;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
        }
        .delivery-proof-preview {
            display: block;
            width: min(100%, 560px);
            max-height: 360px;
            margin: 12px 0 16px;
            border: 1px solid #dce8e0;
            border-radius: 10px;
            object-fit: contain;
            background: #f8fbf9;
        }
        .delivery-proof-preview[hidden] {
            display: none;
        }
        .delivery-product-status-note {
            display: block;
            margin-top: 6px;
            color: #647168;
            font-size: 0.8em;
        }
        .modal .delivery-assignment-modal {
            width: min(760px, 94vw);
            max-width: 760px;
            max-height: 94vh;
            margin: 2vh auto 20px;
            overflow-y: auto;
            padding: 22px;
        }
        .delivery-assignment-intro {
            margin: -10px 0 18px;
            color: #647168;
            font-size: 13px;
            line-height: 1.5;
        }
        .delivery-assignment-groups {
            display: grid;
            gap: 16px;
        }
        .delivery-assignment-review {
            padding: 16px;
            border: 1px solid #dce8e0;
            border-radius: 12px;
            background: #f8fbf9;
        }
        .delivery-assignment-review h4 {
            margin: 0 0 5px;
            color: #244b35;
            font-size: 16px;
        }
        .delivery-assignment-review > p {
            margin: 0 0 14px;
            color: #647168;
            font-size: 12px;
            line-height: 1.5;
        }
        .delivery-review-groups {
            display: grid;
            gap: 12px;
        }
        .delivery-review-group {
            overflow: hidden;
            border: 1px solid #dce8e0;
            border-radius: 10px;
            background: #fff;
        }
        .delivery-review-group h5 {
            margin: 0;
            padding: 11px 13px;
            background: #edf6ef;
            color: #244b35;
            font-size: 13px;
        }
        .delivery-review-table-wrap {
            overflow-x: auto;
        }
        .delivery-review-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .delivery-review-table th,
        .delivery-review-table td {
            padding: 9px 10px;
            border-top: 1px solid #edf0ee;
            text-align: left;
            vertical-align: top;
        }
        .delivery-review-table th {
            color: #647168;
            font-size: 10px;
            white-space: nowrap;
        }
        .delivery-review-table td { color: #334155; }
        .delivery-assignment-group {
            padding: 18px;
            border: 1px solid #dce8e0;
            border-radius: 14px;
            background: linear-gradient(145deg, #fff 0%, #f8fbf9 100%);
            box-shadow: 0 5px 16px rgba(31, 78, 54, .06);
        }
        .delivery-assignment-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .delivery-assignment-group-header h4 {
            margin: 0;
            color: #244b35;
            font-size: 15px;
        }
        .delivery-group-remove,
        .delivery-add-group {
            min-height: 40px;
            padding: 9px 13px;
            border: 1px solid #cfe0d5;
            border-radius: 9px;
            background: #fff;
            color: #276543;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .delivery-group-remove {
            min-height: 34px;
            padding: 6px 10px;
            color: #a3312a;
            border-color: #f1d1cf;
        }
        .delivery-product-picker-toggle {
            min-height: 34px;
            padding: 6px 10px;
            border: 1px solid #cfe0d5;
            border-radius: 8px;
            background: #fff;
            color: #276543;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .delivery-add-group {
            width: 100%;
            margin-top: 14px;
            border-style: dashed;
            background: #f5faf6;
        }
        .delivery-assignment-group .form-group {
            margin-bottom: 14px;
        }
        .delivery-assignment-group select,
        .delivery-assignment-group input:not([type="checkbox"]):not([type="hidden"]) {
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cbd8d0;
            border-radius: 9px;
            background: #fff;
            color: #26352d;
            font: inherit;
        }
        #deliveryModal .delivery-assignment-group .form-group select:required:invalid {
            color: #7a877f;
        }
        #deliveryModal .delivery-assignment-group .form-group select option {
            color: #27372e;
        }
        #deliveryModal .delivery-assignment-group .form-group input:not([type="checkbox"]):not([type="hidden"]),
        #deliveryModal .delivery-assignment-group .form-group select {
            box-sizing: border-box;
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d5e0d9;
            border-radius: 10px;
            background: #fff;
            color: #27372e;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
            box-shadow: 0 2px 7px rgba(32, 65, 46, .045);
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }
        #deliveryModal .delivery-assignment-group .form-group input:not([type="checkbox"]):not([type="hidden"]):focus,
        #deliveryModal .delivery-assignment-group .form-group select:focus {
            outline: 3px solid rgba(31, 110, 74, .14);
            outline-offset: 1px;
            border-color: #28794f;
            box-shadow: 0 0 0 1px #28794f;
        }
        #deliveryModal .delivery-assignment-group .form-group input::placeholder {
            color: #7a877f;
            font-weight: 400;
            opacity: 1;
        }
        .delivery-product-picker {
            overflow: hidden;
            border: 1px solid #cbd8d0;
            border-radius: 11px;
            background: #fff;
        }
        .delivery-product-picker-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 13px;
            background: #f5faf6;
            color: #466251;
            font-size: 12px;
            font-weight: 650;
        }
        .delivery-product-search {
            width: 100%;
            min-height: 42px;
            padding: 10px 13px;
            border: 0;
            border-top: 1px solid #e5ece7;
            border-radius: 0;
            background: #fff;
            color: #27372e;
            font: inherit;
            font-size: 13px;
            box-shadow: none;
        }
        .delivery-product-search::placeholder {
            color: #7a877f;
            opacity: 1;
        }
        .delivery-product-search:focus {
            outline: none;
            box-shadow: inset 0 0 0 2px #28794f;
        }
        #deliveryModal .delivery-assignment-group .calendar-picker {
            width: 100%;
        }
        #deliveryModal .delivery-assignment-group .calendar-picker__trigger {
            box-sizing: border-box;
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d5e0d9;
            border-radius: 10px;
            background: linear-gradient(135deg, #fff 0%, #f7faf8 100%);
            color: #27372e;
            font: inherit;
            font-size: 13px;
            box-shadow: 0 2px 7px rgba(32, 65, 46, .045);
        }
        #deliveryModal .delivery-assignment-group .calendar-picker__trigger.is-placeholder {
            color: #7a877f;
        }
        #deliveryModal .delivery-assignment-group .calendar-picker__trigger:hover:not(:disabled) {
            border-color: #78a58b;
            background: #f4f9f5;
        }
        #deliveryModal .delivery-assignment-group .calendar-picker__popover {
            z-index: 1350;
            border-color: #dce8e0;
            border-radius: 14px;
        }
        .delivery-product-options {
            display: grid;
            max-height: 190px;
            overflow-y: auto;
            padding: 6px;
        }
        .delivery-product-options[hidden],
        .delivery-product-search[hidden] {
            display: none !important;
        }
        .modal .delivery-assignment-group .delivery-product-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            margin: 0;
            border-radius: 8px;
            cursor: pointer;
        }
        .delivery-product-option:hover { background: #f1f7f3; }
        .modal .delivery-assignment-group .delivery-product-option input[type="checkbox"] {
            display: inline-block;
            box-sizing: border-box;
            flex: 0 0 17px;
            width: 17px;
            height: 17px;
            min-height: 17px;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            accent-color: #1f6e4a;
            box-shadow: none;
        }
        .delivery-product-option-copy {
            display: grid;
            gap: 3px;
            color: #293b30;
            font-size: 13px;
        }
        .delivery-product-option-meta {
            color: #78857c;
            font-size: 11px;
        }
        .delivery-product-stock-badge {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 5px;
            padding: 3px 7px;
            border-radius: 999px;
            background: #edf6ef;
            color: #276543;
            font-size: 10px;
            font-weight: 700;
        }
        .delivery-product-details {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }
        .delivery-product-detail-card {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(150px, .8fr);
            gap: 8px 12px;
            padding: 11px;
            border: 1px solid #e2e8e4;
            border-radius: 10px;
            background: #fff;
        }
        .delivery-product-detail-card h5 {
            grid-column: 1 / -1;
            margin: 0;
            color: #334155;
            font-size: 13px;
        }
        .delivery-product-detail-stock {
            grid-column: 1 / -1;
            margin-top: -5px;
        }
        .delivery-product-detail-card .form-group { margin: 0; }
        .delivery-product-detail-card label {
            display: grid;
            gap: 6px;
            font-size: 12px;
        }
        .delivery-product-picker-empty {
            padding: 14px;
            color: #748078;
            font-size: 12px;
            text-align: center;
        }
        .delivery-driver-overview {
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #d9e7dc;
            border-radius: 10px;
            background: #f4f9f5;
        }
        .delivery-driver-overview h5 {
            margin: 0 0 8px;
            color: #244b35;
            font-size: 12px;
        }
        .delivery-driver-overview-count {
            margin: 0 0 8px;
            color: #5e7064;
            font-size: 11px;
        }
        .delivery-driver-overview-list {
            display: grid;
            gap: 6px;
            max-height: 170px;
            overflow-y: auto;
        }
        .delivery-driver-overview-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 4px 10px;
            padding: 8px 9px;
            border: 1px solid #e2ebe4;
            border-radius: 8px;
            background: #fff;
            font-size: 11px;
        }
        .delivery-driver-overview-item strong { color: #2e4435; }
        .delivery-driver-overview-meta {
            grid-column: 1 / -1;
            color: #718077;
        }
        .delivery-driver-overview-new {
            color: #1f6e4a;
            font-size: 10px;
            font-weight: 700;
        }
        @media (max-width: 640px) {
            .modal .delivery-assignment-modal {
                width: 94vw;
                max-height: 90vh;
                padding: 16px;
            }
            .delivery-product-detail-card { grid-template-columns: 1fr; }
            .delivery-product-detail-card h5 { grid-column: auto; }
            .delivery-assignment-group { padding: 14px; }
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
        .delivery-error-alert,
        .delivery-report-error {
            margin: 12px 0;
            padding: 11px 14px;
            border-radius: 8px;
            background: #f8d7da;
            color: #842029;
        }
        .delivery-filters,
        .delivery-report-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            margin: 0 0 20px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .delivery-filters label,
        .delivery-report-filters label {
            display: flex;
            min-width: 145px;
            flex: 1 1 145px;
            flex-direction: column;
            gap: 6px;
            color: #59655d;
            font-size: 11px;
            font-weight: 700;
        }
        .delivery-filters input,
        .delivery-filters select,
        .delivery-report-filters input {
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid #d9e0db;
            border-radius: 8px;
            background: #fff;
            font: inherit;
        }
        .delivery-filters > button {
            min-height: 38px;
            padding: 8px 13px;
            border: 1px solid #cfe2d5;
            border-radius: 8px;
            background: #f8faf8;
            color: #1f6e4a;
            font-weight: 700;
            cursor: pointer;
        }
        .delivery-task-row.is-overdue { background: #fff7f6; }
        .delivery-task-row.is-due-soon { background: #fffcf2; }
        .delivery-deadline-badge {
            display: inline-flex;
            margin-left: 5px;
            padding: 4px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }
        .delivery-deadline-badge.is-overdue { background: #fde8e7; color: #a3312a; }
        .delivery-deadline-badge.is-due-soon { background: #fff1c7; color: #8a6200; }
        .status-pill.status-overdue { background: #fde8e7; color: #a3312a; }
        .delivery-filter-empty,
        .delivery-report-note {
            margin: 10px 0 16px;
            color: #6b7280;
            font-size: 12px;
        }
        .delivery-report-table { min-width: 620px !important; }
        .delivery-report-filters label { max-width: 240px; }
        #deliveryActivityModal {
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(5px);
        }
        #deliveryActivityModal .delivery-activity-modal {
            width: min(100%, 620px);
            max-height: min(84vh, 740px);
            margin: 0;
            padding: 0;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
            animation: delivery-activity-enter 180ms ease-out;
        }
        .delivery-activity-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 26px 17px;
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
            text-align: left;
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
        }
        #deliveryActivityModal .delivery-activity-close:hover {
            border-color: #c8dfd0;
            background: #f0f8f2;
            color: #1f6e4a;
        }
        .delivery-activity-context {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 26px 0;
            padding: 12px 14px;
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
            padding: 20px 26px 25px;
        }
        .delivery-activity-list { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
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
        .delivery-activity-message.is-error { border-color: #f0cccc; background: #fff7f7; color: #9f3434; }
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
            #deliveryActivityModal .delivery-activity-modal { max-height: 84vh; border-radius: 18px; }
            .delivery-activity-header { padding: 20px 18px 15px; }
            .delivery-activity-context { margin: 14px 18px 0; }
            #deliveryActivityContent { max-height: 58vh; padding: 18px; }
        }
        @media (prefers-reduced-motion: reduce) {
            #deliveryActivityModal .delivery-activity-modal { animation: none; }
            .delivery-activity-spinner { animation-duration: 1.4s; }
        }
        @media (max-width: 640px) {
            .delivery-filters, .delivery-report-filters { align-items: stretch; flex-direction: column; }
            .delivery-filters label, .delivery-report-filters label { width: 100%; }
            .delivery-filters > button, .delivery-report-filters .btn-submit { width: 100%; }
        }
        /* ============================================================
           DELIVERY TABS
        ============================================================ */
        .delivery-tabs {
            display: flex;
            flex-wrap: wrap;
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
        .assignment-loading-overlay.is-success .assignment-loading-card {
            border: 1px solid #22c55e;
        }
        .assignment-loading-overlay.is-success .assignment-loading-card h2 {
            color: #86efac;
        }
        .assignment-loading-overlay.is-error .assignment-loading-card {
            border: 1px solid #f87171;
        }
        .assignment-loading-overlay.is-error .assignment-loading-card h2 {
            color: #fca5a5;
        }
        .assignment-result-dismiss {
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #334155;
            color: #f8fafc;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }
        .assignment-result-dismiss:hover {
            background: #475569;
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
    <link rel="stylesheet" href="css/task-form-controls.css?v=<?= filemtime(__DIR__ . '/css/task-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
</head>
<body>
<div class="app">
    <!-- ============================================================
         SIDEBAR
    ============================================================ -->
    <?php $tasktrackActive = 'logistics'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>
    <!-- ============================================================
         MAIN CONTENT
    ============================================================ -->
    <main class="main">
        <!-- ========================================================
             TOPBAR
        ======================================================== -->
        <div class="topbar">
            <h1>Logistic Management</h1>
            <div class="topbar-actions">
                <div class="assign">
                    <button type="button" onclick="openDeliveryModal()">Add Delivery</button>
                </div>
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

        <?php if ($delivery_error !== ''): ?>
            <div role="alert" class="delivery-error-alert"><?= htmlspecialchars($delivery_error); ?></div>
        <?php endif; ?>
        <?php if ($delivery_update_error !== ''): ?>
            <div role="alert" class="delivery-error-alert"><?= htmlspecialchars($delivery_update_error); ?></div>
        <?php endif; ?>
        <?php if ($delivery_review_success !== ''): ?>
            <div role="status" class="delivery-review-success"><?= htmlspecialchars($delivery_review_success); ?></div>
        <?php endif; ?>

        <!-- ========================================================
             CONTENT
        ======================================================== -->

        <div class="content">

            <div class="page active">

                <section class="delivery-team-panel" aria-labelledby="deliveryDriversTitle">
                    <div class="delivery-team-heading">
                        <div>
                            <p class="delivery-team-eyebrow">TEAM OVERVIEW</p>
                            <h2 id="deliveryDriversTitle" class="section-title">Delivery Drivers</h2>
                            <p>Driver roster and attendance for <?= htmlspecialchars(date('F j, Y')); ?>.</p>
                        </div>
                        <div class="delivery-team-actions">
                            <button
                                type="button"
                                class="delivery-staff-toggle"
                                id="deliveryStaffToggle"
                                aria-expanded="false"
                                aria-controls="deliveryStaffRoster"
                                onclick="toggleDeliveryStaff()"
                            >
                                View Staff
                            </button>
                            <div class="delivery-team-summary" aria-label="Delivery team attendance summary">
                                <div class="delivery-team-stat is-total">
                                    <span class="delivery-team-stat-value"><?= count($delivery_drivers); ?></span>
                                    <span class="delivery-team-stat-label">Total staff</span>
                                </div>
                                <div class="delivery-team-stat is-present">
                                    <span class="delivery-team-stat-value"><?= $present_delivery_count; ?></span>
                                    <span class="delivery-team-stat-label">Present</span>
                                </div>
                                <div class="delivery-team-stat is-absent">
                                    <span class="delivery-team-stat-value"><?= $absent_delivery_count; ?></span>
                                    <span class="delivery-team-stat-label">Absent</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="deliveryStaffRoster" hidden>
                        <?php if (!empty($delivery_drivers)): ?>
                            <div class="delivery-driver-list">
                                <?php foreach ($delivery_drivers as $delivery_driver): ?>
                                    <?php $is_present = $delivery_driver['attendance_status'] === 'Present'; ?>
                                    <article class="delivery-driver-item">
                                        <span class="delivery-driver-avatar" aria-hidden="true">
                                            <?php if (!empty($delivery_driver['profile_picture']) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $delivery_driver['profile_picture'])): ?>
                                                <img src="uploads/profile-pictures/<?= htmlspecialchars($delivery_driver['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                            <?php else: ?>
                                                <?= htmlspecialchars(strtoupper(substr($delivery_driver['name'] ?? 'D', 0, 1))); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="delivery-driver-details">
                                            <span class="delivery-driver-name">
                                                <?= htmlspecialchars($delivery_driver['name'] ?? 'Unnamed delivery driver'); ?>
                                            </span>
                                            <span class="delivery-driver-role">Delivery team member</span>
                                        </span>
                                        <span class="delivery-attendance <?= $is_present ? 'is-present' : 'is-absent'; ?>">
                                            <span class="delivery-attendance-dot" aria-hidden="true"></span>
                                            <?= htmlspecialchars($delivery_driver['attendance_status']); ?>
                                        </span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-message">No delivery drivers found.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="delivery-filters" aria-label="Filter delivery tasks">
                    <label>
                        <span>Search</span>
                        <input type="search" id="deliverySearch" placeholder="Delivery ID, product, destination, driver">
                    </label>
                    <label>
                        <span>Driver</span>
                        <select id="deliveryDriverFilter">
                            <option value="">All drivers</option>
                            <?php foreach ($delivery_drivers as $driver): ?>
                                <option value="<?= htmlspecialchars(strtolower($driver['name']), ENT_QUOTES); ?>"><?= htmlspecialchars($driver['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select id="deliveryStatusFilter">
                            <option value="">All statuses</option>
                            <option value="overdue">Overdue</option>
                            <?php foreach (array_unique(array_map(
                                static fn($record) => strtolower((string) ($record['status'] ?? 'pending')),
                                array_merge($pending_records, $history_records)
                            )) as $delivery_filter_status): ?>
                                <option value="<?= htmlspecialchars($delivery_filter_status, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(ucfirst($delivery_filter_status), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Due from</span>
                        <input type="date" id="deliveryDueFrom" class="calendar-control">
                    </label>
                    <label>
                        <span>Due to</span>
                        <input type="date" id="deliveryDueTo" class="calendar-control">
                    </label>
                    <button type="button" id="resetDeliveryFilters">Reset filters</button>
                </section>

                <!-- ==================================================
                     DELIVERY TABS
                ================================================== -->

                <div class="delivery-tabs">

                    <button
                        type="button"
                        class="delivery-tab <?= $active_delivery_section === 'pending' ? 'active' : ''; ?>"
                        id="pendingTab"
                        onclick="showDeliverySection('pending')"
                    >
                        Pending Deliveries
                    </button>

                    <button
                        type="button"
                        class="delivery-tab <?= $active_delivery_section === 'history' ? 'active' : ''; ?>"
                        id="historyTab"
                        onclick="showDeliverySection('history')"
                    >
                        Delivery History (Delivered)
                    </button>

                    <button
                        type="button"
                        class="delivery-tab <?= $active_delivery_section === 'reports' ? 'active' : ''; ?>"
                        id="deliveryReportsTab"
                        onclick="showDeliverySection('reports')"
                    >
                        Delivery Reports
                    </button>

                </div>


                <!-- ==================================================
                     PENDING DELIVERIES
                ================================================== -->

                <div
                    id="pendingSection"
                    class="delivery-section <?= $active_delivery_section === 'pending' ? 'active' : ''; ?>"
                >

                    <h2 class="section-title">
                        Pending Deliveries
                    </h2>


                    <?php if (!empty($pending_records)): ?>

                        <div class="table-wrap table-scroll">

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
                                        <?php
                                        $delivery_due_date = (string) ($row['delivery_date'] ?? '');
                                        $delivery_days_until_due = null;
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $delivery_due_date)) {
                                            $delivery_days_until_due = (int) (new DateTimeImmutable(date('Y-m-d')))
                                                ->diff(new DateTimeImmutable($delivery_due_date))
                                                ->format('%r%a');
                                        }
                                        $delivery_is_overdue = $delivery_days_until_due !== null && $delivery_days_until_due < 0;
                                        $delivery_row_status = strtolower((string) ($row['status'] ?? 'pending'));
                                        $delivery_is_cancelled = in_array($delivery_row_status, ['cancelled', 'canceled'], true);
                                        $delivery_is_overdue = $delivery_is_overdue && !$delivery_is_cancelled;
                                        $delivery_is_due_soon = $delivery_days_until_due !== null && $delivery_days_until_due >= 0 && $delivery_days_until_due <= 3;
                                        $delivery_driver_name = (string) ($row['driver_name'] ?? 'Unassigned');
                                        $delivery_is_awaiting_review = ($row['completion_review_status'] ?? '') === 'pending_review';
                                        $delivery_can_review = ($_SESSION['role'] ?? '') === 'super'
                                            && $delivery_is_awaiting_review
                                            && (empty($row['assigned_by']) || (int) $row['assigned_by'] === (int) ($_SESSION['id'] ?? 0));
                                        ?>
                                        <tr
                                            class="delivery-task-row <?= $delivery_is_overdue ? 'is-overdue' : ($delivery_is_due_soon ? 'is-due-soon' : ''); ?>"
                                            data-status="<?= $delivery_is_overdue ? 'overdue' : htmlspecialchars($delivery_row_status, ENT_QUOTES); ?>"
                                            data-raw-status="<?= htmlspecialchars($delivery_row_status, ENT_QUOTES); ?>"
                                            data-driver="<?= htmlspecialchars(strtolower($delivery_driver_name), ENT_QUOTES); ?>"
                                            data-due-date="<?= htmlspecialchars($delivery_due_date, ENT_QUOTES); ?>"
                                        >

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
                                                <?= htmlspecialchars($delivery_due_date); ?>
                                                <?php if ($delivery_is_overdue): ?>
                                                    <span class="delivery-deadline-badge is-overdue">Overdue</span>
                                                <?php elseif ($delivery_is_due_soon): ?>
                                                    <span class="delivery-deadline-badge is-due-soon">Due soon</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>

                                                <span class="status-pill status-<?= htmlspecialchars($delivery_is_overdue ? 'overdue' : $delivery_row_status, ENT_QUOTES); ?>">
                                                    <?= htmlspecialchars(ucfirst($row['status'] ?: 'Pending')); ?>
                                                </span>

                                            </td>

                                            <td class="action-cell">
                                                <button
                                                    type="button"
                                                    class="edit-btn"
                                                    data-activity-id="<?= (int) $row['delivery_id']; ?>"
                                                    data-activity-product="<?= htmlspecialchars($row['product_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                                >Activity</button>
                                                <?php if ($delivery_can_review): ?>
                                                    <button
                                                        type="button"
                                                        class="delivery-review-open-button edit-btn"
                                                        data-delivery-id="<?= (int) $row['delivery_id']; ?>"
                                                        data-route="<?= htmlspecialchars((string) ($row['product_name'] ?? 'this delivery') . ' to ' . (string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-proof-url="<?= !empty($row['delivery_proof_image']) ? 'delivery_proof.php?id=' . (int) $row['delivery_id'] : ''; ?>"
                                                    >Review submission</button>
                                                <?php endif; ?>

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
                                                        class="edit-btn delivery-update-button"
                                                        data-delivery-id="<?= (int) $row['delivery_id']; ?>"
                                                        data-route="<?= htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-pieces="<?= (int) ($row['pieces'] ?? 0); ?>"
                                                        data-stock="<?= (int) ($row['stock'] ?? 0); ?>"
                                                        data-delivery-date="<?= htmlspecialchars((string) ($row['delivery_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-driver-id="<?= (int) ($row['driver_id'] ?? 0); ?>"
                                                        data-driver-name="<?= htmlspecialchars((string) ($row['driver_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        Update
                                                    </button>

                                                    <input
                                                        type="submit"
                                                        name="del"
                                                        value="Delete"
                                                        class="remove-btn"
                                                        data-confirm="Are you sure you want to delete this delivery record?"
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
                    <p class="delivery-filter-empty" id="pendingDeliveryFilterEmpty" hidden>No deliveries match the selected filters.</p>

                </div>


                <!-- ==================================================
                     DELIVERY HISTORY
                ================================================== -->

                <div
                    id="historySection"
                    class="delivery-section <?= $active_delivery_section === 'history' ? 'active' : ''; ?>"
                >

                    <h2 class="section-title">
                        Delivery History (Delivered)
                    </h2>


                    <?php if (!empty($history_records)): ?>

                        <div class="table-wrap table-scroll">

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
                                        <?php $delivery_due_date = (string) ($row['delivery_date'] ?? ''); ?>
                                        <tr
                                            class="delivery-task-row"
                                            data-status="delivered"
                                            data-raw-status="delivered"
                                            data-driver="<?= htmlspecialchars(strtolower((string) ($row['driver_name'] ?? 'Unassigned')), ENT_QUOTES); ?>"
                                            data-due-date="<?= htmlspecialchars($delivery_due_date, ENT_QUOTES); ?>"
                                        >

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
                                                <?= htmlspecialchars($delivery_due_date); ?>
                                            </td>

                                            <td>

                                                <span class="status-pill status-delivered">
                                                    Delivered
                                                </span>

                                            </td>

                                            <td class="action-cell">
                                                <button
                                                    type="button"
                                                    class="edit-btn"
                                                    data-activity-id="<?= (int) $row['delivery_id']; ?>"
                                                    data-activity-product="<?= htmlspecialchars($row['product_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                                >Activity</button>

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
                                                        data-confirm="Are you sure you want to delete this delivery record?"
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
                    <p class="delivery-filter-empty" id="historyDeliveryFilterEmpty" hidden>No deliveries match the selected filters.</p>

                </div>

                <section
                    id="deliveryReportsSection"
                    class="delivery-section <?= $active_delivery_section === 'reports' ? 'active' : ''; ?>"
                    aria-labelledby="deliveryReportsTitle"
                >
                    <h2 id="deliveryReportsTitle" class="section-title">Delivery Reports</h2>
                    <form class="delivery-report-filters" method="get" action="delivery_main.php">
                        <label>
                            <span>Report month</span>
                            <input
                                type="month"
                                id="deliveryReportDate"
                                class="calendar-control"
                                name="report_month"
                                value="<?= htmlspecialchars($delivery_report_month, ENT_QUOTES); ?>"
                                required
                            >
                        </label>
                        <button type="submit" class="btn-submit">View Report</button>
                    </form>
                    <?php if ($delivery_report_error !== ''): ?>
                        <p role="alert" class="delivery-report-error"><?= htmlspecialchars($delivery_report_error); ?></p>
                    <?php endif; ?>
                    <p class="delivery-report-note">
                        Showing delivery counts for scheduled deliveries in <?= htmlspecialchars($delivery_report_label); ?>.
                    </p>
                    <div class="table-wrap table-scroll">
                        <table class="delivery-report-table">
                            <thead><tr><th>Month</th><th>Total</th><th>Completed</th><th>Pending</th><th>Overdue</th></tr></thead>
                            <tbody><tr>
                                <td><?= htmlspecialchars($delivery_report_label); ?></td>
                                <td><?= $delivery_report['total']; ?></td>
                                <td><?= $delivery_report['completed']; ?></td>
                                <td><?= $delivery_report['pending']; ?></td>
                                <td><?= $delivery_report['overdue']; ?></td>
                            </tr></tbody>
                        </table>
                    </div>
                </section>

            </div>

        </div>

    </main>

</div>



<div id="deliveryActivityModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="deliveryActivityTitle">
    <div class="modal-content delivery-activity-modal" tabindex="-1">
        <div class="delivery-activity-header">
            <div class="delivery-activity-heading">
                <span class="delivery-activity-kicker">SHIPMENT TIMELINE</span>
                <h3 id="deliveryActivityTitle">Delivery Activity</h3>
            </div>
            <button type="button" class="delivery-activity-close" aria-label="Close delivery activity" onclick="closeDeliveryActivity()">×</button>
        </div>
        <div class="delivery-activity-context">
            <span class="delivery-activity-context-icon" aria-hidden="true">↗</span>
            <p id="deliveryActivityProduct"></p>
        </div>
        <div id="deliveryActivityContent" aria-live="polite" aria-busy="false"></div>
    </div>
</div>


<!-- ============================================================
     ADD DELIVERY MODAL
============================================================ -->

<div
    id="deliveryModal"
    class="modal"
    style="<?= !empty($errors) && $action === 'insert' ? 'display:block;' : '' ?>"
>

    <div class="modal-content delivery-assignment-modal">

        <h3>
            Add Delivery Assignments
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
            <input
                type="hidden"
                name="submit"
                value="Confirm and assign"
            >


            <div id="deliveryAssignmentEditor">
                <p class="delivery-assignment-intro">
                    Add one group per driver. Choose products in each group, then set a separate destination and delivery date for every product.
                </p>

                <?php if (isset($errors['assignments']) || isset($errors['production_id'])): ?>
                    <div class="error-message delivery-error-alert" role="alert">
                        <?= htmlspecialchars($errors['assignments'] ?? $errors['production_id'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div id="deliveryAssignmentGroups" class="delivery-assignment-groups"></div>
                <button type="button" id="addDeliveryGroup" class="delivery-add-group">
                    + Add another driver group
                </button>

                <script type="application/json" id="deliveryAssignmentData"><?=
                json_encode([
                    'products' => array_map(static function ($product) {
                        return [
                            'id' => (string) $product['production_id'],
                            'name' => (string) $product['product_name'],
                            'stock' => (string) $product['Stock_number'],
                            'quantity' => (string) $product['quantity'],
                        ];
                    }, $production_items),
                    'drivers' => array_map(static function ($driver) {
                        return ['id' => (string) $driver['id'], 'name' => (string) $driver['name']];
                    }, $present_drivers),
                    'pendingDeliveries' => array_values(array_map(static function ($delivery) {
                        return [
                            'driverId' => (string) ($delivery['driver_id'] ?? ''),
                            'productName' => (string) ($delivery['product_name'] ?? 'Unknown product'),
                            'stock' => (string) ($delivery['stock'] ?? ''),
                            'quantity' => (string) ($delivery['pieces'] ?? ''),
                            'route' => (string) ($delivery['route'] ?? ''),
                            'deliveryDate' => (string) ($delivery['delivery_date'] ?? ''),
                            'status' => (string) ($delivery['status'] ?? 'Pending'),
                        ];
                    }, array_filter($pending_records, static function ($delivery) {
                        return !in_array(strtolower((string) ($delivery['status'] ?? '')), ['cancelled', 'canceled'], true);
                    }))),
                    'groups' => array_values($posted_delivery_groups),
                    'errors' => $errors['assignments'] ?? '',
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?></script>
            </div>

            <section id="deliveryAssignmentReview" class="delivery-assignment-review" hidden aria-labelledby="deliveryReviewTitle">
                <h4 id="deliveryReviewTitle">Review delivery assignments</h4>
                <p>Check the products, stock, quantities, destinations, dates, and drivers before confirming.</p>
                <div id="deliveryReviewGroups" class="delivery-review-groups"></div>
            </section>


            <!-- MODAL ACTIONS -->

            <div class="modal-actions">

                <button
                    type="submit"
                    name="submit"
                    id="reviewDeliveryAssignments"
                    class="btn-submit"
                >
                    Create Deliveries
                </button>

                <button
                    type="button"
                    id="backToDeliveryAssignments"
                    class="btn-cancel"
                    hidden
                >
                    Back to edit
                </button>

                <button
                    type="submit"
                    name="submit"
                    id="confirmDeliveryAssignments"
                    class="btn-submit"
                    hidden
                >
                    Confirm and assign
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
                    class="assignment-select"
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
                                (Active shift)
                            </option>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <option
                            value=""
                            disabled
                        >
                             No drivers currently timed in for an active shift
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


            <!-- DELIVERY DATE -->

            <div class="form-group">

                <label for="edit_delivery_date">
                    Delivery Date:
                </label>

                <input
                    type="date"
                    id="edit_delivery_date"
                    class="calendar-control"
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

<dialog class="task-review-dialog" id="deliveryReviewDialog" aria-labelledby="deliveryReviewTitle">
    <form method="post" action="delivery_main.php" class="task-review-dialog-card">
        <input type="hidden" name="task_review_csrf" value="<?= htmlspecialchars($_SESSION['task_review_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="review_return" value="delivery_main.php">
        <input type="hidden" name="delivery_id" id="deliveryReviewId">
        <span class="task-review-dialog-kicker">LOGISTICS TASK</span>
        <h2 id="deliveryReviewTitle">Review delivery submission</h2>
        <p class="task-review-dialog-description" id="deliveryReviewDescription"></p>
        <img id="deliveryReviewProof" class="delivery-proof-preview" alt="Photo proof of delivery" hidden>
        <p id="deliveryReviewNoProof" class="task-review-dialog-hint" hidden>No delivery proof photo was attached.</p>
        <label class="task-review-note-label" for="deliveryReviewNote">Reason for requesting changes</label>
        <textarea id="deliveryReviewNote" name="review_note" maxlength="1000" rows="4" placeholder="Explain what needs to be changed"></textarea>
        <p class="task-review-dialog-hint">A reason is required only when you request changes.</p>
        <div class="task-review-dialog-actions">
            <button class="task-review-cancel-button" type="button" id="closeDeliveryReview">Cancel</button>
            <button class="task-reject-button" type="submit" name="review_delivery" value="reject">Request changes</button>
            <button class="task-approve-button" type="submit" name="review_delivery" value="approve">Approve delivery</button>
        </div>
    </form>
</dialog>


<div
    id="assignmentLoading"
    class="assignment-loading-overlay<?= $delivery_assignment_result !== null ? ' active is-' . $delivery_assignment_result : ''; ?>"
    role="status"
    aria-live="polite"
    aria-busy="false"
    aria-hidden="<?= $delivery_assignment_result === null ? 'true' : 'false'; ?>"
>
    <div class="assignment-loading-card">
        <h2><?= $delivery_assignment_result === 'success' ? 'Delivery assigned!' : ($delivery_assignment_result === 'error' ? 'Assignment not completed' : 'Assigning Deliveries'); ?></h2>
        <p><?= $delivery_assignment_result !== null
            ? htmlspecialchars($delivery_assignment_feedback, ENT_QUOTES, 'UTF-8')
            : 'Please wait while the delivery tasks are being saved.'; ?></p>
        <?php if ($delivery_assignment_result === 'error'): ?>
            <button type="button" class="assignment-result-dismiss" id="dismissAssignmentResult">Back to assignments</button>
        <?php elseif ($delivery_assignment_result !== 'success'): ?>
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
        <?php endif; ?>
    </div>
</div>


<!-- ============================================================
     JAVASCRIPT
============================================================ -->

<script>
    const deliveryReviewDialog = document.getElementById('deliveryReviewDialog');
    const deliveryReviewNote = document.getElementById('deliveryReviewNote');
    const deliveryReviewProof = document.getElementById('deliveryReviewProof');
    const deliveryReviewNoProof = document.getElementById('deliveryReviewNoProof');
    document.querySelectorAll('.delivery-review-open-button').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('deliveryReviewId').value = button.dataset.deliveryId;
            document.getElementById('deliveryReviewDescription').textContent =
                `Choose whether to approve "${button.dataset.route}" or request changes from the driver.`;
            const proofUrl = button.dataset.proofUrl || '';
            deliveryReviewProof.hidden = !proofUrl;
            deliveryReviewNoProof.hidden = Boolean(proofUrl);
            deliveryReviewProof.removeAttribute('src');
            if (proofUrl) {
                deliveryReviewProof.src = proofUrl;
            }
            deliveryReviewNote.value = '';
            deliveryReviewNote.required = false;
            deliveryReviewDialog.showModal();
        });
    });
    deliveryReviewDialog.querySelector('[value="reject"]').addEventListener('click', () => {
        deliveryReviewNote.required = true;
    });
    deliveryReviewDialog.querySelector('[value="approve"]').addEventListener('click', () => {
        deliveryReviewNote.required = false;
    });
    document.getElementById('closeDeliveryReview').addEventListener('click', () => deliveryReviewDialog.close());
    deliveryReviewDialog.addEventListener('click', (event) => {
        if (event.target === deliveryReviewDialog) deliveryReviewDialog.close();
    });



/* ============================================================
   DELIVERY TAB SWITCHING
============================================================ */

function showDeliverySection(section) {
    const sections = {
        pending: document.getElementById('pendingSection'),
        history: document.getElementById('historySection'),
        reports: document.getElementById('deliveryReportsSection')
    };
    const tabs = {
        pending: document.getElementById('pendingTab'),
        history: document.getElementById('historyTab'),
        reports: document.getElementById('deliveryReportsTab')
    };
    Object.entries(sections).forEach(([name, element]) => {
        element.classList.toggle('active', name === section);
        tabs[name].classList.toggle('active', name === section);
    });
}

function filterDeliveryRows() {
    const search = document.getElementById('deliverySearch').value.trim().toLowerCase();
    const driver = document.getElementById('deliveryDriverFilter').value;
    const status = document.getElementById('deliveryStatusFilter').value;
    const dueFrom = document.getElementById('deliveryDueFrom').value;
    const dueTo = document.getElementById('deliveryDueTo').value;

    [['pendingSection', 'pendingDeliveryFilterEmpty'], ['historySection', 'historyDeliveryFilterEmpty']]
        .forEach(([sectionId, emptyId]) => {
            const rows = document.querySelectorAll(`#${sectionId} .delivery-task-row`);
            let visibleCount = 0;
            rows.forEach((row) => {
                const matches =
                    (!search || row.textContent.toLowerCase().includes(search)) &&
                    (!driver || row.dataset.driver === driver) &&
                    (!status || row.dataset.status === status || row.dataset.rawStatus === status) &&
                    (!dueFrom || row.dataset.dueDate >= dueFrom) &&
                    (!dueTo || row.dataset.dueDate <= dueTo);
                row.hidden = !matches;
                if (matches) visibleCount += 1;
            });
            const emptyMessage = document.getElementById(emptyId);
            emptyMessage.hidden = rows.length === 0 || visibleCount > 0;
        });
}

const assignmentData = JSON.parse(document.getElementById('deliveryAssignmentData').textContent);
const assignmentGroups = document.getElementById('deliveryAssignmentGroups');
const assignmentForm = document.querySelector('#deliveryModal .assignment-form');
const assignmentEditor = document.getElementById('deliveryAssignmentEditor');
const assignmentReview = document.getElementById('deliveryAssignmentReview');
const reviewGroups = document.getElementById('deliveryReviewGroups');
const reviewButton = document.getElementById('reviewDeliveryAssignments');
const backToAssignmentsButton = document.getElementById('backToDeliveryAssignments');
const confirmAssignmentsButton = document.getElementById('confirmDeliveryAssignments');
let nextDeliveryGroupId = 0;
let assignmentsConfirmed = false;

function productDetailsFor(group, productId) {
    const products = group.products;
    if (!products || typeof products !== 'object') return {};
    if (Array.isArray(products)) {
        return products.find((product) => String(product.production_id) === productId) || {};
    }
    return products[productId] || {};
}

function productsAssignedElsewhere(groupElement) {
    const assigned = new Set();
    assignmentGroups.querySelectorAll('.delivery-assignment-group').forEach((group) => {
        if (group === groupElement) return;
        group.selectedProductIds.forEach((productId) => assigned.add(productId));
    });
    return assigned;
}

function renderGroupProducts(groupElement) {
    const groupIndex = groupElement.dataset.groupIndex;
    const options = groupElement.querySelector('.delivery-product-options');
    const detailsContainer = groupElement.querySelector('.delivery-product-details');
    const search = groupElement.querySelector('.delivery-product-search').value.trim().toLowerCase();
    const selectedIds = groupElement.selectedProductIds || new Set();
    const unavailableIds = productsAssignedElsewhere(groupElement);
    options.replaceChildren();
    detailsContainer.replaceChildren();

    assignmentData.products.forEach((product) => {
        const productId = String(product.id);
        const productText = `${product.name} ${product.stock} ${product.quantity}`.toLowerCase();
        if (search && !productText.includes(search)) return;

        const label = document.createElement('label');
        label.className = 'delivery-product-option';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'delivery-product-checkbox';
        checkbox.value = productId;
        checkbox.checked = selectedIds.has(productId);
        checkbox.disabled = unavailableIds.has(productId) && !selectedIds.has(productId);
        checkbox.addEventListener('change', () => {
            const nextSelectedIds = new Set(groupElement.selectedProductIds);
            if (checkbox.checked) {
                nextSelectedIds.add(productId);
            } else {
                nextSelectedIds.delete(productId);
            }
            groupElement.selectedProductIds = nextSelectedIds;
            const selectionError = groupElement.querySelector('.delivery-group-validation');
            selectionError.hidden = groupElement.selectedProductIds.size > 0;
            renderAllGroupProducts();
        });

        const copy = document.createElement('span');
        copy.className = 'delivery-product-option-copy';
        const title = document.createElement('strong');
        title.textContent = product.name;
        const meta = document.createElement('span');
        meta.className = 'delivery-product-option-meta';
        meta.textContent = `Stock no. ${product.stock} · Quantity ${product.quantity}`;
        copy.append(title, meta);
        label.append(checkbox, copy);
        options.appendChild(label);
    });

    if (!options.children.length) {
        const empty = document.createElement('div');
        empty.className = 'delivery-product-picker-empty';
        empty.textContent = assignmentData.products.length
            ? 'No products match your search.'
            : 'No unassigned finished products are available.';
        options.appendChild(empty);
    }

    const summary = groupElement.querySelector('.delivery-product-picker-summary span');
    summary.textContent = `${selectedIds.size} product${selectedIds.size === 1 ? '' : 's'} selected`;
    const pickerButton = groupElement.querySelector('.delivery-product-picker-summary button');
    pickerButton.setAttribute('aria-expanded', String(!options.hidden));

    Array.from(selectedIds).forEach((productId, productIndex) => {
        const product = assignmentData.products.find((item) => String(item.id) === productId);
        const productValues = groupElement.productValues.get(productId) || {};
        const card = document.createElement('section');
        card.className = 'delivery-product-detail-card';
        card.dataset.productId = productId;

        const heading = document.createElement('h5');
        heading.textContent = product ? product.name : `Unavailable product (${productId})`;
        card.appendChild(heading);
        if (!product) {
            const unavailable = document.createElement('div');
            unavailable.className = 'delivery-product-picker-empty';
            unavailable.textContent = 'This product is no longer available for assignment. Remove it from this group to continue.';
            const removeProduct = document.createElement('button');
            removeProduct.type = 'button';
            removeProduct.className = 'delivery-group-remove';
            removeProduct.textContent = 'Remove product';
            removeProduct.addEventListener('click', () => {
                groupElement.selectedProductIds.delete(productId);
                renderAllGroupProducts();
            });
            card.append(unavailable, removeProduct);
            detailsContainer.appendChild(card);
            return;
        }

        const stockBadge = document.createElement('span');
        stockBadge.className = 'delivery-product-stock-badge delivery-product-detail-stock';
        stockBadge.textContent = `Stock no. ${product.stock} · Product qty ${product.quantity}`;
        card.appendChild(stockBadge);

        const routeGroup = document.createElement('div');
        routeGroup.className = 'form-group';
        const routeLabel = document.createElement('label');
        routeLabel.textContent = 'Destination';
        const routeInput = document.createElement('input');
        routeInput.type = 'text';
        routeInput.name = `assignments[${groupIndex}][products][${productIndex}][route]`;
        routeInput.value = typeof productValues.route === 'string' ? productValues.route : '';
        routeInput.pattern = '[A-Za-z0-9 ]+';
        routeInput.required = true;
        routeInput.addEventListener('input', () => {
            groupElement.productValues.set(productId, {
                ...groupElement.productValues.get(productId),
                route: routeInput.value
            });
            renderDriverOverview(groupElement);
        });
        routeLabel.appendChild(routeInput);
        routeGroup.appendChild(routeLabel);

        const hiddenId = document.createElement('input');
        hiddenId.type = 'hidden';
        hiddenId.name = `assignments[${groupIndex}][products][${productIndex}][production_id]`;
        hiddenId.value = productId;
        routeGroup.appendChild(hiddenId);
        card.appendChild(routeGroup);

        const dateGroup = document.createElement('div');
        dateGroup.className = 'form-group';
        const dateLabel = document.createElement('label');
        dateLabel.textContent = 'Delivery date';
        const dateInput = document.createElement('input');
        dateInput.type = 'date';
        dateInput.className = 'calendar-control';
        dateInput.name = `assignments[${groupIndex}][products][${productIndex}][delivery_date]`;
        dateInput.value = typeof productValues.delivery_date === 'string' ? productValues.delivery_date : '';
        dateInput.required = true;
        dateInput.addEventListener('change', () => {
            groupElement.productValues.set(productId, {
                ...groupElement.productValues.get(productId),
                delivery_date: dateInput.value
            });
            renderDriverOverview(groupElement);
        });
        dateLabel.appendChild(dateInput);
        dateGroup.appendChild(dateLabel);
        card.appendChild(dateGroup);
        detailsContainer.appendChild(card);
    });

    renderDriverOverview(groupElement);
}

function renderDriverOverview(groupElement) {
    const overview = groupElement.querySelector('.delivery-driver-overview');
    const driverId = groupElement.querySelector('.delivery-driver-select').value;
    overview.replaceChildren();
    if (!driverId) {
        overview.hidden = true;
        return;
    }

    const driver = assignmentData.drivers.find((item) => String(item.id) === driverId);
    const currentDeliveries = assignmentData.pendingDeliveries.filter((delivery) => delivery.driverId === driverId);
    const driverGroups = Array.from(assignmentGroups.querySelectorAll('.delivery-assignment-group'))
        .filter((group) => group.querySelector('.delivery-driver-select').value === driverId);
    const newDeliveries = driverGroups.flatMap((group) =>
        Array.from(group.selectedProductIds, (productId) => ({ group, productId }))
    );
    const title = document.createElement('h5');
    title.textContent = `Assignment overview${driver ? ` · ${driver.name}` : ''}`;
    const count = document.createElement('p');
    count.className = 'delivery-driver-overview-count';
    count.textContent = `${currentDeliveries.length} current task${currentDeliveries.length === 1 ? '' : 's'} · ${newDeliveries.length} new task${newDeliveries.length === 1 ? '' : 's'}`;
    const list = document.createElement('div');
    list.className = 'delivery-driver-overview-list';

    currentDeliveries.forEach((delivery) => {
        const item = document.createElement('div');
        item.className = 'delivery-driver-overview-item';
        const product = document.createElement('strong');
        product.textContent = delivery.productName;
        const status = document.createElement('span');
        status.textContent = delivery.status || 'Pending';
        const meta = document.createElement('span');
        meta.className = 'delivery-driver-overview-meta';
        meta.textContent = `Stock no. ${delivery.stock || 'N/A'} · Qty ${delivery.quantity || 'N/A'} · ${delivery.route || 'No destination'} · Due ${delivery.deliveryDate || 'No date'}`;
        item.append(product, status, meta);
        list.appendChild(item);
    });

    newDeliveries.forEach(({ group, productId }) => {
        const product = assignmentData.products.find((item) => String(item.id) === productId);
        if (!product) return;
        const values = group.productValues.get(productId) || {};
        const item = document.createElement('div');
        item.className = 'delivery-driver-overview-item';
        const productName = document.createElement('strong');
        productName.textContent = product.name;
        const badge = document.createElement('span');
        badge.className = 'delivery-driver-overview-new';
        badge.textContent = 'NEW';
        const meta = document.createElement('span');
        meta.className = 'delivery-driver-overview-meta';
        meta.textContent = `Stock no. ${product.stock} · Qty ${product.quantity} · ${values.route || 'Destination not set'} · Due ${values.delivery_date || 'Date not set'}`;
        item.append(productName, badge, meta);
        list.appendChild(item);
    });

    if (!list.children.length) {
        const empty = document.createElement('div');
        empty.className = 'delivery-product-picker-empty';
        empty.textContent = 'No current or newly selected tasks for this driver yet.';
        list.appendChild(empty);
    }
    overview.append(title, count, list);
    overview.hidden = false;
}

function renderAllGroupProducts() {
    assignmentGroups.querySelectorAll('.delivery-assignment-group').forEach((group) => {
        renderGroupProducts(group);
    });
}

function renderDeliveryReview() {
    reviewGroups.replaceChildren();
    assignmentGroups.querySelectorAll('.delivery-assignment-group').forEach((group) => {
        const driverSelect = group.querySelector('.delivery-driver-select');
        const driverName = driverSelect.selectedOptions[0]?.textContent.trim() || 'Driver not selected';
        const reviewGroup = document.createElement('section');
        reviewGroup.className = 'delivery-review-group';
        const heading = document.createElement('h5');
        heading.textContent = driverName;

        const tableWrap = document.createElement('div');
        tableWrap.className = 'delivery-review-table-wrap';
        const table = document.createElement('table');
        table.className = 'delivery-review-table';
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['Product', 'Stock no.', 'Quantity', 'Destination', 'Delivery date'].forEach((label) => {
            const header = document.createElement('th');
            header.scope = 'col';
            header.textContent = label;
            headerRow.appendChild(header);
        });
        thead.appendChild(headerRow);
        const tbody = document.createElement('tbody');

        Array.from(group.selectedProductIds).forEach((productId) => {
            const product = assignmentData.products.find((item) => String(item.id) === productId);
            if (!product) return;
            const values = group.productValues.get(productId) || {};
            const row = document.createElement('tr');
            [
                product.name,
                product.stock,
                product.quantity,
                values.route || '—',
                values.delivery_date || '—'
            ].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });

        table.append(thead, tbody);
        tableWrap.appendChild(table);
        reviewGroup.append(heading, tableWrap);
        reviewGroups.appendChild(reviewGroup);
    });
}

function createDeliveryGroup(initialGroup = {}) {
    const groupIndex = String(nextDeliveryGroupId++);
    const groupElement = document.createElement('section');
    groupElement.className = 'delivery-assignment-group';
    groupElement.dataset.groupIndex = groupIndex;
    groupElement.initialGroup = initialGroup;
    groupElement.productValues = new Map();

    const header = document.createElement('div');
    header.className = 'delivery-assignment-group-header';
    const title = document.createElement('h4');
    title.textContent = `Driver group ${Number(groupIndex) + 1}`;
    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'delivery-group-remove';
    removeButton.textContent = 'Remove group';
    removeButton.addEventListener('click', () => {
        groupElement.remove();
        renderAllGroupProducts();
        updateGroupLabels();
    });
    header.append(title, removeButton);
    groupElement.appendChild(header);

    const driverGroup = document.createElement('div');
    driverGroup.className = 'form-group';
    const driverLabel = document.createElement('label');
    driverLabel.textContent = 'Assign to active driver';
    const driverSelect = document.createElement('select');
    driverSelect.name = `assignments[${groupIndex}][driver_id]`;
    driverSelect.className = 'delivery-driver-select';
    driverSelect.required = true;
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Select driver --';
    driverSelect.appendChild(placeholder);
    assignmentData.drivers.forEach((driver) => {
        const option = document.createElement('option');
        option.value = String(driver.id);
        option.textContent = driver.name;
        driverSelect.appendChild(option);
    });
    driverSelect.value = String(initialGroup.driver_id || '');
    driverSelect.addEventListener('change', renderAllGroupProducts);
    driverLabel.appendChild(driverSelect);
    driverGroup.appendChild(driverLabel);
    groupElement.appendChild(driverGroup);

    const productGroup = document.createElement('div');
    productGroup.className = 'form-group';
    const productLabel = document.createElement('label');
    productLabel.textContent = 'Products for this driver';
    productGroup.appendChild(productLabel);

    const picker = document.createElement('div');
    picker.className = 'delivery-product-picker';
    const pickerSummary = document.createElement('div');
    pickerSummary.className = 'delivery-product-picker-summary';
    const selectedCount = document.createElement('span');
    const pickerToggle = document.createElement('button');
    pickerToggle.type = 'button';
    pickerToggle.className = 'delivery-product-picker-toggle';
    pickerToggle.textContent = 'Choose products';
    pickerToggle.setAttribute('aria-expanded', 'false');
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'delivery-product-search';
    search.placeholder = 'Search product name or stock number...';
    search.setAttribute('aria-label', 'Search products');
    const options = document.createElement('div');
    options.className = 'delivery-product-options';
    options.hidden = true;
    search.hidden = true;
    const details = document.createElement('div');
    details.className = 'delivery-product-details';
    const selectionError = document.createElement('div');
    selectionError.className = 'error-message delivery-group-validation';
    selectionError.textContent = 'Choose at least one product for this driver group.';
    selectionError.hidden = true;

    pickerToggle.addEventListener('click', () => {
        options.hidden = !options.hidden;
        search.hidden = options.hidden;
        pickerToggle.textContent = options.hidden ? 'Change products' : 'Hide products';
        pickerToggle.setAttribute('aria-expanded', String(!options.hidden));
    });
    search.addEventListener('input', () => renderGroupProducts(groupElement));
    pickerSummary.append(selectedCount, pickerToggle);
    picker.append(pickerSummary, search, options);
    productGroup.append(picker, selectionError, details);
    groupElement.appendChild(productGroup);

    const overview = document.createElement('aside');
    overview.className = 'delivery-driver-overview';
    overview.hidden = true;
    groupElement.appendChild(overview);
    assignmentGroups.appendChild(groupElement);

    const groupProducts = initialGroup.products;
    const selectedProductIds = Array.isArray(groupProducts)
        ? groupProducts.map((product) => String(product.production_id))
        : Object.keys(groupProducts || {});
    selectedProductIds.forEach((productId) => {
        groupElement.productValues.set(productId, productDetailsFor(initialGroup, productId));
    });
    groupElement.selectedProductIds = new Set(selectedProductIds);
    renderGroupProducts(groupElement);
    updateGroupLabels();
}

function updateGroupLabels() {
    assignmentGroups.querySelectorAll('.delivery-assignment-group-header h4').forEach((heading, index) => {
        heading.textContent = `Driver group ${index + 1}`;
    });
    const onlyOneGroup = assignmentGroups.querySelectorAll('.delivery-assignment-group').length === 1;
    assignmentGroups.querySelectorAll('.delivery-group-remove').forEach((button) => {
        if (button.textContent === 'Remove group') button.hidden = onlyOneGroup;
    });
}

document.getElementById('addDeliveryGroup').addEventListener('click', () => {
    createDeliveryGroup();
    renderAllGroupProducts();
});

const initialGroups = Array.isArray(assignmentData.groups) ? assignmentData.groups : [];
if (initialGroups.length) {
    initialGroups.forEach((group) => createDeliveryGroup(group));
} else {
    createDeliveryGroup();
}
renderAllGroupProducts();

assignmentForm.addEventListener('submit', (event) => {
    if (assignmentsConfirmed) {
        event.preventDefault();
        const loadingOverlay = document.getElementById('assignmentLoading');
        loadingOverlay.classList.add('active');
        loadingOverlay.setAttribute('aria-busy', 'true');
        loadingOverlay.setAttribute('aria-hidden', 'false');
        confirmAssignmentsButton.disabled = true;
        window.setTimeout(() => {
            try {
                HTMLFormElement.prototype.submit.call(assignmentForm);
            } catch (error) {
                loadingOverlay.classList.remove('active');
                loadingOverlay.setAttribute('aria-busy', 'false');
                loadingOverlay.setAttribute('aria-hidden', 'true');
                confirmAssignmentsButton.disabled = false;
                assignmentsConfirmed = false;
                window.alert('Delivery assignment could not be submitted. Please try again.');
            }
        }, 2800);
        return;
    }
    const groups = assignmentGroups.querySelectorAll('.delivery-assignment-group');
    const hasUnassignedProduct = Array.from(groups).some((group) => group.selectedProductIds.size === 0);
    if (hasUnassignedProduct) {
        event.preventDefault();
        const emptyGroup = Array.from(groups).find((group) => group.selectedProductIds.size === 0);
        emptyGroup.querySelector('.delivery-group-validation').hidden = false;
        emptyGroup.querySelector('.delivery-product-picker').scrollIntoView({ behavior: 'smooth', block: 'center' });
        emptyGroup.querySelector('.delivery-product-picker-summary button').focus();
        return;
    }

    event.preventDefault();
    renderDeliveryReview();
    assignmentEditor.hidden = true;
    assignmentReview.hidden = false;
    reviewButton.hidden = true;
    backToAssignmentsButton.hidden = false;
    confirmAssignmentsButton.hidden = false;
    assignmentForm.noValidate = true;
});

backToAssignmentsButton.addEventListener('click', () => {
    assignmentsConfirmed = false;
    assignmentEditor.hidden = false;
    assignmentReview.hidden = true;
    reviewButton.hidden = false;
    backToAssignmentsButton.hidden = true;
    confirmAssignmentsButton.hidden = true;
    assignmentForm.noValidate = false;
});

confirmAssignmentsButton.addEventListener('click', () => {
    assignmentsConfirmed = true;
});

['deliverySearch', 'deliveryDriverFilter', 'deliveryStatusFilter', 'deliveryDueFrom', 'deliveryDueTo']
    .forEach((id) => {
        const eventName = id.includes('Filter') ? 'change' : 'input';
        document.getElementById(id).addEventListener(eventName, filterDeliveryRows);
    });
document.getElementById('resetDeliveryFilters').addEventListener('click', () => {
    ['deliverySearch', 'deliveryDriverFilter', 'deliveryStatusFilter', 'deliveryDueFrom', 'deliveryDueTo']
        .forEach((id) => {
            const control = document.getElementById(id);
            control.value = '';
            if (control.classList.contains('calendar-picker__native')) {
                control.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    filterDeliveryRows();
});

async function openDeliveryActivity(deliveryId, productName) {
    const modal = document.getElementById('deliveryActivityModal');
    const content = document.getElementById('deliveryActivityContent');
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

    try {
        const response = await fetch(`delivery_main.php?activity_log=${encodeURIComponent(deliveryId)}`, {
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
        result.activities.forEach((activity) => {
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
            const messages = {
                delivery_assigned: `${activity.actor_name} created and assigned the delivery to ${activity.to_driver_name || 'a driver'}.`,
                driver_reassigned: `${activity.actor_name} reassigned the delivery from ${activity.from_driver_name || 'a driver'} to ${activity.to_driver_name || 'a driver'}.`,
                delivery_updated: `${activity.actor_name} updated the delivery details.`,
                delivery_submitted_for_review: `${activity.actor_name} submitted this delivery for supervisor approval.`,
                delivery_approved: `${activity.actor_name} approved this delivery.`,
                delivery_changes_requested: `${activity.actor_name} requested changes for this delivery.`,
                delivery_completed: `${activity.actor_name} confirmed the delivery.`
            };
            summary.textContent = messages[activity.event_type] || `${activity.actor_name} updated this delivery.`;
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
}

document.querySelectorAll('[data-activity-id]').forEach((button) => {
    button.addEventListener('click', () => {
        openDeliveryActivity(button.dataset.activityId, button.dataset.activityProduct);
    });
});

function toggleDeliveryStaff() {
    const roster = document.getElementById("deliveryStaffRoster");
    const toggle = document.getElementById("deliveryStaffToggle");
    const isExpanded = toggle.getAttribute("aria-expanded") === "true";

    roster.hidden = isExpanded;
    toggle.setAttribute("aria-expanded", String(!isExpanded));
    toggle.textContent = isExpanded ? "View Staff" : "Hide Staff";
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
    date,
    driverId,
    driverName
) {

    document.getElementById("edit_delivery_id").value = id;

    document.getElementById("edit_route").value = route;

    document.getElementById("edit_delivery_date").value = date;
    document.getElementById("edit_delivery_date").dispatchEvent(
        new Event("change", { bubbles: true })
    );


    const driverSelect = document.getElementById("edit_driver_id");
    driverSelect.querySelectorAll("option[data-current-assignment]").forEach(option => option.remove());
    if (driverId && !Array.from(driverSelect.options).some(option => option.value === String(driverId))) {
        const currentDriverOption = document.createElement("option");
        currentDriverOption.value = driverId;
        currentDriverOption.textContent = `${driverName || `Driver #${driverId}`} (currently assigned)`;
        currentDriverOption.dataset.currentAssignment = "true";
        driverSelect.appendChild(currentDriverOption);
    }
    driverSelect.value = driverId || "";
    driverSelect.dispatchEvent(new Event("change", { bubbles: true }));


    document.getElementById("editDeliveryModal").style.display =
        "block";

}

document.querySelectorAll(".delivery-update-button").forEach(button => {
    button.addEventListener("click", () => {
        openEditDeliveryModal(
            button.dataset.deliveryId,
            button.dataset.route,
            button.dataset.deliveryDate,
            button.dataset.driverId,
            button.dataset.driverName
        );
    });
});


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


document.querySelectorAll("#deliveryModal .assignment-form").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (event.defaultPrevented) {
            return;
        }
        const loadingOverlay = document.getElementById("assignmentLoading");
        loadingOverlay.classList.add("active");
        loadingOverlay.setAttribute("aria-busy", "true");
        loadingOverlay.setAttribute("aria-hidden", "false");
    });
});

<?php if ($delivery_assignment_result === 'success'): ?>
window.setTimeout(() => {
    window.location.replace('delivery_main.php');
}, 2500);
<?php elseif ($delivery_assignment_result === 'error'): ?>
document.getElementById('dismissAssignmentResult').addEventListener('click', () => {
    const overlay = document.getElementById('assignmentLoading');
    overlay.classList.remove('active');
    overlay.setAttribute('aria-busy', 'false');
    overlay.setAttribute('aria-hidden', 'true');
});
<?php endif; ?>

</script>

<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
<script src="js/assignment-dropdown.js?v=<?= filemtime(__DIR__ . '/js/assignment-dropdown.js'); ?>"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
<script src="js/action-confirmation.js?v=<?= filemtime(__DIR__ . '/js/action-confirmation.js'); ?>"></script>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>