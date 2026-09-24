<?php
require_once 'config/connection.php';

// Set Timezone sa Philippine Time
date_default_timezone_set('Asia/Manila');

$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// =========================================================
// AUTO-MARK ABSENT TRIGGER PAGPATAK NG 5:01 PM (17:01:00)
// =========================================================
if ($currentTime >= '17:01:00') {
    $autoAbsentStmt = $conn->prepare("
        INSERT INTO attendance (employee_id, username, attendance_date, status)
        SELECT 
            e.employee_id, 
            COALESCE(NULLIF(e.username, ''), CONCAT('Employee #', e.employee_id)), 
            ?, 
            'Absent'
        FROM employee e
        WHERE e.employee_id NOT IN (
            SELECT employee_id 
            FROM attendance 
            WHERE attendance_date = ?
        )
    ");
    if ($autoAbsentStmt) {
        $autoAbsentStmt->bind_param('ss', $currentDate, $currentDate);
        $autoAbsentStmt->execute();
        $autoAbsentStmt->close();
    }
}

// Get selected date from GET parameter if provided
$selectedDate = isset($_GET['date']) ? trim($_GET['date']) : '';

$records = [];
$count = 0;

// Counter variables for summary cards
$totalPresent = 0;
$totalLate    = 0;
$totalAbsent  = 0;

// Fetch Attendance Records using Prepared Statements
if (!empty($selectedDate)) {
    $stmt = $conn->prepare(
        'SELECT attendance_id, username, employee_id, attendance_date, '
        . 'time_in, time_out, time_in_2, time_out_2, status '
        . 'FROM attendance '
        . 'WHERE attendance_date = ? '
        . 'ORDER BY attendance_id DESC'
    );
    $stmt->bind_param('s', $selectedDate);
} else {
    $stmt = $conn->prepare(
        'SELECT attendance_id, username, employee_id, attendance_date, '
        . 'time_in, time_out, time_in_2, time_out_2, status '
        . 'FROM attendance '
        . 'ORDER BY attendance_date DESC, attendance_id DESC'
    );
}

if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $rawRecords = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Process status for each record to handle NULL or empty statuses
    foreach ($rawRecords as $rec) {
        $st = strtolower(trim($rec['status'] ?? ''));

        // Kung walang status sa DB pero may Time In, i-determine base sa Time In
        if (empty($st) && !empty($rec['time_in']) && $rec['time_in'] !== '00:00:00') {
            $st = (strtotime($rec['time_in']) > strtotime('08:00:00')) ? 'late' : 'present';
        }

        $rec['display_status'] = !empty($st) ? ucfirst($st) : 'N/A';

        // Increment summary counts
        if ($st === 'present') {
            $totalPresent++;
        } elseif ($st === 'late') {
            $totalLate++;
        } elseif ($st === 'absent') {
            $totalAbsent++;
        }

        $records[] = $rec;
    }

    $count = count($records);
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
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
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
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-card label {
            font-weight: bold;
            font-size: 14px;
        }
        input[type="date"] {
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
        .status-default { background-color: #e2e3e5; color: #41464b; }
    </style>
</head>
<body>

    <div class="header-actions">
        <!-- Back Button -->
        <a href="HR.php" class="btn btn-secondary">&larr; Back to HR</a>

        <!-- Date Filter Form -->
        <form method="GET" action="" class="filter-card">
            <label for="date">Filter by Date:</label>
            <input 
                type="date" 
                id="date" 
                name="date" 
                value="<?= htmlspecialchars($selectedDate); ?>" 
                required
            >
            <button type="submit" class="btn btn-primary">Filter</button>
            
            <?php if (!empty($selectedDate)): ?>
                <a href="atten.php" class="btn btn-outline">Show All Records</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Title Header -->
    <h2>
        <?= !empty($selectedDate) 
            ? 'Attendance for ' . date('F j, Y', strtotime($selectedDate)) 
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
    </div>

    <?php if ($count > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Attendance ID</th>
                    <th>Username</th>
                    <th>Employee ID</th>
                    <th>Attendance Date</th>
                    <th>1st Time In</th>
                    <th>1st Time Out</th>
                    <th>2nd Time In</th>
                    <th>2nd Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['attendance_id']); ?></td>
                        <td><?= htmlspecialchars($row['username']); ?></td>
                        <td><?= htmlspecialchars($row['employee_id']); ?></td>
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
            No attendance records found <?= !empty($selectedDate) ? 'for this date' : ''; ?>.
        </p>
    <?php endif; ?>

</body> 
</html>