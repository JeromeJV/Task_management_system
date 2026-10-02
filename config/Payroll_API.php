<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connection.php';

session_start();

// Security Check: Ensure user is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'payroll') {
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
// 1. GET ALL PAYROLL RECORDS OR SINGLE RECORD
// =================================================================
if ($method === 'GET') {
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