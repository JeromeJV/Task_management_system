<?php
require_once 'config/connection.php';
require_once 'config/attendance_helpers.php';
require_once __DIR__ . '/config/leave_helpers.php';

// Set Timezone sa Philippine Time
date_default_timezone_set('Asia/Manila');

$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');
ensureLeaveSchema($conn);
finalizeDailyAttendance($conn, $currentDate, $currentTime);

// Get GET parameters
$selectedDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$searchName   = isset($_GET['search']) ? trim($_GET['search']) : '';

$records = [];
$count = 0;

// Counter variables for summary cards
$totalPresent = 0;
$totalLate    = 0;
$totalAbsent  = 0;
$totalHalfDay = 0;
$totalUndertime = 0;
$totalLeave = 0;

// =========================================================
// FETCH ATTENDANCE WITH USER & EMPLOYEE DETAILS + SEARCH
// =========================================================
$query = "
    SELECT 
        a.attendance_id, 
        a.employee_id, 
        a.attendance_date, 
        a.time_in, 
        a.time_out, 
        a.time_in_2, 
        a.time_out_2, 
        a.status,
        a.is_late,
        COALESCE(u.name, e.username, a.username, 'N/A') AS display_name,
        COALESCE(u.email, e.email, '') AS user_email,
        COALESCE(u.role, e.position, 'N/A') AS user_role
    FROM attendance a
    LEFT JOIN employee e ON a.employee_id = e.employee_id
    LEFT JOIN users u ON a.user_id = u.id OR e.username = u.name
    WHERE 1=1
";

$params = [];
$types  = "";

// Date Filter
if (!empty($selectedDate)) {
    $query .= " AND a.attendance_date = ?";
    $params[] = $selectedDate;
    $types .= "s";
}

// Name/Email Filter
if (!empty($searchName)) {
    $query .= " AND (u.name LIKE ? OR e.username LIKE ? OR a.username LIKE ? OR u.email LIKE ? OR e.email LIKE ?)";
    $searchTerm = "%" . $searchName . "%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssss";
}

$query .= " ORDER BY a.attendance_date DESC, a.attendance_id DESC";

$stmt = $conn->prepare($query);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $rawRecords = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Process status for each record
        foreach ($rawRecords as $rec) {
            $st = strtolower(trim($rec['status'] ?? ''));

            // Kung walang status sa DB pero may Time In
            if (empty($st) && !empty($rec['time_in']) && $rec['time_in'] !== '00:00:00') {
                $st = (strtotime($rec['time_in']) > strtotime('08:00:00')) ? 'late' : 'present';
            }

            $isLate = !empty($rec['is_late']) || $st === 'late';
            $rec['display_status'] = !empty($st) ? ucfirst($st) : 'N/A';
            if ($st === 'half day' && $isLate) {
                $rec['display_status'] = 'Half Day (Late)';
            }

            // Increment summary counts
            if ($st === 'present') {
                $totalPresent++;
            } elseif ($st === 'absent') {
                $totalAbsent++;
            } elseif ($st === 'half day') {
                $totalHalfDay++;
            } elseif ($st === 'undertime') {
                $totalUndertime++;
            } elseif (in_array($st, ['paid leave', 'unpaid leave'], true)) {
                $totalLeave++;
            }
            if ($isLate) {
                $totalLate++;
            }

            $records[] = $rec;
        }

        $count = count($records);
    }
}

// Helper function to format time (e.g., 08:30 AM)
function formatTime($timeStr) {
    return (!empty($timeStr) && $timeStr !== '00:00:00') ? date('h:i A', strtotime($timeStr)) : '-';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <style>
        .attendance-main {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-card {
            display: grid;
            width: min(100%, 720px);
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr) auto;
            align-items: end;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            gap: 10px;
        }
        .filter-card .attendance-filter-field {
            display: grid;
            gap: 6px;
            min-width: 0;
        }
        .filter-card .attendance-filter-field > span {
            font-weight: bold;
            font-size: 14px;
        }
        .filter-card .calendar-picker {
            width: 100%;
        }
        .attendance-filter-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .attendance-filter-actions .btn-clear-placeholder {
            visibility: hidden;
            pointer-events: none;
        }
        @media (max-width: 720px) {
            .filter-card {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .attendance-filter-actions {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 480px) {
            .filter-card {
                grid-template-columns: minmax(0, 1fr);
            }
            .attendance-filter-actions {
                grid-column: auto;
                flex-wrap: wrap;
            }
        }
        input[type="date"], input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 7px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-primary { background-color: #0d6efd; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-outline { background-color: transparent; border: 1px solid #6c757d; color: #333; }
        .btn:hover { opacity: 0.9; }
        
        /* Summary Cards */
        .summary-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .summary-card {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 130px;
            border-left: 5px solid #6c757d;
        }
        .summary-card.present { border-left-color: #198754; }
        .summary-card.late { border-left-color: #ffc107; }
        .summary-card.absent { border-left-color: #dc3545; }
        .summary-card.half-day { border-left-color: #0dcaf0; }
        .summary-card.undertime { border-left-color: #6c757d; }
        .summary-card h4 { margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; color: #6c757d; }
        .summary-card .number { font-size: 22px; font-weight: bold; margin: 0; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 14px;
            border: 1px solid #dee2e6;
            text-align: center;
            font-size: 14px;
        }
        th { background-color: #f1f3f5; font-weight: 600; }
        
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .status-present { background-color: #d1e7dd; color: #0f5132; }
        .status-absent { background-color: #f8d7da; color: #842029; }
        .status-late { background-color: #fff3cd; color: #664d03; }
        .status-half-day { background-color: #cff4fc; color: #055160; }
        .status-undertime { background-color: #e2e3e5; color: #41464b; }
        .status-leave { background-color: #fff3cd; color: #664d03; }
        .status-default { background-color: #e2e3e5; color: #41464b; }
        
        .sub-text {
            display: block;
            font-size: 11px;
            color: #6c757d;
        }
    </style>
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
</head>
<body>
    <div class="hr-shell">
        <aside class="hr-sidebar" aria-label="HR navigation">
            <a class="hr-brand" href="HR.php" aria-label="TaskTrack HR home">
                <span class="hr-brand-mark" aria-hidden="true">T</span>
                <span class="hr-brand-name">TASKTRACK</span>
            </a>
            <nav class="hr-nav">
                <a class="hr-nav-link" href="HR.php">
                    <span class="hr-nav-icon" aria-hidden="true">▦</span>
                    <span class="hr-nav-label">Employees</span>
                </a>
                <a class="hr-nav-link is-active" href="atten.php" aria-current="page">
                    <span class="hr-nav-icon" aria-hidden="true">◷</span>
                    <span class="hr-nav-label">Attendance</span>
                </a>
                <a class="hr-nav-link" href="leave_management.php">
                    <span class="hr-nav-icon" aria-hidden="true">✚</span>
                    <span class="hr-nav-label">Leave Management</span>
                </a>
                <a class="hr-nav-link" href="application_form.php">
                    <span class="hr-nav-icon" aria-hidden="true">▤</span>
                    <span class="hr-nav-label">Applicants</span>
                </a>
                <a class="hr-nav-link" href="interview_sched.php">
                    <span class="hr-nav-icon" aria-hidden="true">▣</span>
                    <span class="hr-nav-label">Interview Schedule</span>
                </a>
                <a class="hr-nav-link" href="office_employee.php">
                    <span class="hr-nav-icon" aria-hidden="true">☑</span>
                    <span class="hr-nav-label">My Tasks</span>
                </a>
            </nav>
            <a class="hr-nav-link hr-logout" href="logout.php">
                <span class="hr-nav-icon" aria-hidden="true">↪</span>
                <span class="hr-nav-label">Log out</span>
            </a>
        </aside>
        <?php include __DIR__ . '/config/notification_bell.php'; ?>

        <main class="hr-main attendance-main">
    <div class="header-actions">
        <a href="attendance_corrections.php" class="btn btn-outline">Correct Attendance</a>
        <!-- Filter & Search Form -->
        <form method="GET" action="" class="filter-card">
            <label class="attendance-filter-field attendance-search-field" for="search">
                <span>Search Employee:</span>
                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Name or Email..."
                    value="<?= htmlspecialchars($searchName); ?>"
                >
            </label>

            <label class="attendance-filter-field attendance-date-field" for="date">
                <span>Date:</span>
                <input
                    type="date"
                    class="calendar-control"
                    id="date"
                    name="date"
                    value="<?= htmlspecialchars($selectedDate); ?>"
                >
            </label>

            <div class="attendance-filter-actions">
                <button type="submit" class="btn btn-primary">Search / Filter</button>
                <?php $hasAttendanceFilter = !empty($selectedDate) || !empty($searchName); ?>
                <a
                    href="atten.php"
                    class="btn btn-outline<?= $hasAttendanceFilter ? '' : ' btn-clear-placeholder'; ?>"
                    <?= $hasAttendanceFilter ? '' : 'aria-hidden="true" tabindex="-1"'; ?>
                >Clear Filter</a>
            </div>
        </form>
    </div>

    <!-- Dynamic Title Header -->
    <h2>
        <?php 
            $titleParts = [];
            if (!empty($searchName)) {
                $titleParts[] = 'Results for "' . htmlspecialchars($searchName) . '"';
            }
            if (!empty($selectedDate)) {
                $titleParts[] = 'Date: ' . date('F j, Y', strtotime($selectedDate));
            }

            echo !empty($titleParts) 
                ? 'Attendance - ' . implode(' | ', $titleParts) 
                : 'All Attendance Records'; 
        ?>
    </h2>

    <!-- Summary Cards -->
    <div class="summary-container">
        <div class="summary-card">
            <h4>Total Records</h4>
            <p class="number"><?= $count; ?></p>
        </div>
        <div class="summary-card present">
            <h4>Present</h4>
            <p class="number" style="color: #198754;"><?= $totalPresent; ?></p>
        </div>
        <div class="summary-card late">
            <h4>Late</h4>
            <p class="number" style="color: #b58100;"><?= $totalLate; ?></p>
        </div>
        <div class="summary-card absent">
            <h4>Absent</h4>
            <p class="number" style="color: #dc3545;"><?= $totalAbsent; ?></p>
        </div>
        <div class="summary-card half-day">
            <h4>Half Day</h4>
            <p class="number" style="color: #055160;"><?= $totalHalfDay; ?></p>
        </div>
        <div class="summary-card undertime">
            <h4>Incomplete Session</h4>
            <p class="number" style="color: #41464b;"><?= $totalUndertime; ?></p>
        </div>
        <div class="summary-card">
            <h4>On Leave</h4>
            <p class="number" style="color: #8a5a00;"><?= $totalLeave; ?></p>
        </div>
    </div>

    <?php if ($count > 0): ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Attendance ID</th>
                    <th>System Account (Name / Email)</th>
                    <th>Role</th>
                    <th>Employee ID</th>
                    <th>Attendance Date</th>
                    <th>AM Time In</th>
                    <th>AM Time Out</th>
                    <th>PM Time In</th>
                    <th>PM Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['attendance_id']); ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['display_name']); ?></strong>
                            <?php if (!empty($row['user_email'])): ?>
                                <span class="sub-text"><?= htmlspecialchars($row['user_email']); ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['user_role'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['employee_id'] ?? 'N/A'); ?></td>
                        <td><?= date('M d, Y', strtotime($row['attendance_date'])); ?></td>
                        <td><?= formatTime($row['time_in']); ?></td>
                        <td><?= formatTime($row['time_out']); ?></td>
                        <td><?= formatTime($row['time_in_2']); ?></td>
                        <td><?= formatTime($row['time_out_2']); ?></td>
                        <td>
                            <?php 
                                $statusKey = strtolower($row['display_status']);
                                $statusClass = match ($statusKey) {
                                    'present' => 'status-present',
                                    'absent'  => 'status-absent',
                                    'late'    => 'status-late',
                                    'half day', 'half day (late)' => 'status-half-day',
                                    'undertime' => 'status-undertime',
                                    'paid leave', 'unpaid leave' => 'status-leave',
                                    default   => 'status-default',
                                };
                            ?>
                            <span class="badge <?= $statusClass; ?>">
                                <?= htmlspecialchars($row['display_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            No attendance records found.
        </p>
    <?php endif; ?>
        </main>
    </div>
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
</body> 
</html>