<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection.php';

session_start();

// Security Check: Ensure user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login first.'
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? ($method === 'GET' ? 'get_all' : '');

// =================================================================
// 1. GET ALL PAYROLL RECORDS, SINGLE RECORD, OR ATTENDANCE COUNT
// =================================================================
if ($method === 'GET') {
    
    // ACTION: GET ATTENDANCE DAYS WORKED FOR A PAY PERIOD
    if ($action === 'get_attendance') {
        $employee_id = intval($_GET['employee_id'] ?? 0);
        $pay_period = $_GET['pay_period'] ?? '1st Half';
        $pay_date = $_GET['pay_date'] ?? date('Y-m-d');
        
        // I-parse ang Pay Date para makuha ang Target Month at Year
        $timestamp = strtotime($pay_date);
        if (!$timestamp) {
            // Support para sa MM/DD/YYYY format mula sa HTML date input
            $parts = explode('/', $pay_date);
            if (count($parts) === 3) {
                $timestamp = strtotime("{$parts[2]}-{$parts[0]}-{$parts[1]}");
            }
        }
        
        $target_year = $timestamp ? date('Y', $timestamp) : date('Y');
        $target_month = $timestamp ? date('m', $timestamp) : date('m');

        // Pagtukoy sa Day Range (1st Half vs 2nd Half)
        if (strpos($pay_period, '1st') !== false || strpos($pay_period, 'Kinsenas') !== false) {
            $start_day = 1;
            $end_day = 15;
        } else {
            $start_day = 16;
            $end_day = 31;
        }

        /*
         * Correct Column: attendance_date
         * Hina-handle pareho ang YYYY-MM-DD at 'Oct 01, 2026' string formats
         */
        $query = $conn->prepare("
            SELECT COUNT(DISTINCT 
                CASE 
                    WHEN attendance_date LIKE '%-%' THEN attendance_date
                    ELSE STR_TO_DATE(attendance_date, '%b %d, %Y')
                END
            ) as days_worked 
            FROM attendance 
            WHERE (employee_id = ? OR user_id = ?)
              AND LOWER(status) = 'present' 
              AND (
                  DAY(CASE WHEN attendance_date LIKE '%-%' THEN attendance_date ELSE STR_TO_DATE(attendance_date, '%b %d, %Y') END) BETWEEN ? AND ?
              )
              AND MONTH(CASE WHEN attendance_date LIKE '%-%' THEN attendance_date ELSE STR_TO_DATE(attendance_date, '%b %d, %Y') END) = ?
              AND YEAR(CASE WHEN attendance_date LIKE '%-%' THEN attendance_date ELSE STR_TO_DATE(attendance_date, '%b %d, %Y') END) = ?
        ");

        if ($query) {
            $query->bind_param("iiiiii", $employee_id, $employee_id, $start_day, $end_day, $target_month, $target_year);
            $query->execute();
            $res = $query->get_result()->fetch_assoc();
            
            echo json_encode([
                'status' => 'success',
                'days_worked' => intval($res['days_worked'] ?? 0),
                'debug' => [
                    'employee_id' => $employee_id,
                    'month' => $target_month,
                    'year' => $target_year,
                    'start_day' => $start_day,
                    'end_day' => $end_day
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'days_worked' => 0, 
                'error' => $conn->error
            ]);
        }
        exit();
    }

    if ($action === 'get_all') {
        $sql = "SELECT p.*, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.department, e.username 
                FROM payroll p 
                INNER JOIN employee e ON p.employee_id = e.employee_id 
                ORDER BY p.payroll_id DESC";
        
        $result = $conn->query($sql);
        $payrolls = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payrolls[] = $row;
            }
            echo json_encode([
                'status' => 'success',
                'count' => count($payrolls),
                'data' => $payrolls
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database Query Failed: ' . $conn->error
            ]);
        }
        exit();
    }

    if ($action === 'get_single' && isset($_GET['payroll_id'])) {
        $payroll_id = intval($_GET['payroll_id']);
        
        $stmt = $conn->prepare("SELECT p.*, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.department, e.username 
                                FROM payroll p 
                                INNER JOIN employee e ON p.employee_id = e.employee_id 
                                WHERE p.payroll_id = ?");
        $stmt->bind_param("i", $payroll_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'status' => 'success',
                'data' => $row
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Payroll record not found.'
            ]);
        }
        exit();
    }
}

// =================================================================
// 2. CREATE OR COMPUTE NEW PAYROLL
// =================================================================
if ($method === 'POST' && $action === 'create_payroll') {
    $employee_id   = intval($_POST['employee_id'] ?? 0);
    $attendance_id = !empty($_POST['attendance_id']) ? intval($_POST['attendance_id']) : NULL;
    $pay_period    = $_POST['pay_period'] ?? 'Kinsenas';
    $pay_date      = $_POST['pay_date'] ?? date('Y-m-d');
    $status        = $_POST['status'] ?? 'Pending';

    // Earnings
    $regular_pay     = floatval($_POST['regular_pay'] ?? 0.00);
    $paid_leaves     = floatval($_POST['paid_leaves'] ?? 0.00);
    $daily_allowance = floatval($_POST['daily_allowance'] ?? 0.00);
    $reimbursement   = floatval($_POST['reimbursement'] ?? 0.00);

    // Deductions
    $late_deduction = floatval($_POST['late_deduction'] ?? 0.00);
    $sss            = floatval($_POST['sss'] ?? 0.00);
    $pagibig        = floatval($_POST['pagibig'] ?? 0.00);
    $philhealth     = floatval($_POST['philhealth'] ?? 0.00);
    $calamity_loan  = floatval($_POST['calamity_loan'] ?? 0.00);
    $hmo            = floatval($_POST['hmo'] ?? 0.00);

    // Automatic Computations
    $gross_pay       = $regular_pay + $paid_leaves + $daily_allowance;
    $total_deductions = $late_deduction + $sss + $pagibig + $philhealth + $calamity_loan + $hmo;
    $total_credited   = ($gross_pay - $total_deductions) + $reimbursement;

    $stmt = $conn->prepare("
        INSERT INTO payroll (
            employee_id, attendance_id, pay_period, pay_date, status, 
            regular_pay, paid_leaves, daily_allowance, reimbursement, 
            gross_pay, late_deduction, sss, pagibig, philhealth, 
            calamity_loan, hmo, total_deductions, total_credited
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisssddddddddddddd",
        $employee_id, $attendance_id, $pay_period, $pay_date, $status,
        $regular_pay, $paid_leaves, $daily_allowance, $reimbursement,
        $gross_pay, $late_deduction, $sss, $pagibig, $philhealth,
        $calamity_loan, $hmo, $total_deductions, $total_credited
    );

    if ($stmt->execute()) {
        header("Location: ../payroll.php?success=1");
        exit();
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save payroll: ' . $stmt->error
        ]);
        exit();
    }
}

// =================================================================
// 3. TOGGLE PAYROLL STATUS
// =================================================================
if ($method === 'POST' && $action === 'toggle_status') {
    $payroll_id = intval($_POST['payroll_id'] ?? 0);
    $status     = $_POST['status'] ?? 'Pending';

    $stmt = $conn->prepare("UPDATE payroll SET status = ? WHERE payroll_id = ?");
    $stmt->bind_param("si", $status, $payroll_id);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Status updated successfully.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update status: ' . $stmt->error
        ]);
    }
    exit();
}

// Default Fallback
http_response_code(400);
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid API Action or Method.'
]);
exit();