<?php
ob_start();
header('Content-Type: application/json');
set_exception_handler(static function (Throwable $error): void {
    error_log('Payroll API failure: ' . $error);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'The payroll request failed on the server. Please retry; if it continues, contact the system administrator.'
    ]);
});
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    error_log('Payroll API fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'The payroll request failed on the server. Please retry; if it continues, contact the system administrator.'
    ]);
});
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/attendance_helpers.php';
require_once __DIR__ . '/payroll_helpers.php';
require_once __DIR__ . '/system_audit.php';
require_once __DIR__ . '/account_access.php';
require_once __DIR__ . '/leave_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Security Check: Ensure user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login first.'
    ]);
    exit();
}
requireActiveAccount($conn, true);
if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'payroll') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Payroll access is required.']);
    exit();
}
ensureLeaveSchema($conn);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? ($method === 'GET' ? 'get_all' : '');
if ($method === 'POST'
    && (empty($_SESSION['payroll_csrf'])
        || !hash_equals((string) $_SESSION['payroll_csrf'], (string) ($_POST['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request token. Refresh the payroll page and try again.']);
    exit();
}

// =================================================================
// 1. GET ALL PAYROLL RECORDS, SINGLE RECORD, OR ATTENDANCE COUNT
// =================================================================
if ($method === 'GET') {
    
    if ($action === 'get_available_employees') {
        $pay_period = $_GET['pay_period'] ?? 'Kinsenas';
        $pay_date = $_GET['pay_date'] ?? date('Y-m-d');

        try {
            getPayrollPeriodDates($pay_period, $pay_date);
            $monthStart = substr($pay_date, 0, 7) . '-01';
            $monthEnd = (new DateTimeImmutable($pay_date))->modify('last day of this month')->format('Y-m-d');
        } catch (Throwable $error) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $error->getMessage()
            ]);
            exit();
        }

        $statement = $conn->prepare("
            SELECT e.employee_id, e.username
            FROM employee e
            WHERE NOT EXISTS (
                SELECT 1
                FROM payroll p
                WHERE p.employee_id = e.employee_id
                  AND p.pay_period = ?
                  AND p.pay_date BETWEEN ? AND ?
            )
            ORDER BY e.username ASC
        ");
        if (!$statement) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare employee eligibility query: ' . $conn->error
            ]);
            exit();
        }

        $statement->bind_param('sss', $pay_period, $monthStart, $monthEnd);
        if (!$statement->execute()) {
            $error = $statement->error;
            $statement->close();
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to read eligible employees: ' . $error
            ]);
            exit();
        }

        $employees = [];
        $result = $statement->get_result();
        while ($employee = $result->fetch_assoc()) {
            $employees[] = [
                'employee_id' => (int) $employee['employee_id'],
                'username' => $employee['username'],
                'daily_rate' => 755
            ];
        }
        $statement->close();

        echo json_encode([
            'status' => 'success',
            'employees' => $employees
        ]);
        exit();
    }

    // ACTION: GET ATTENDANCE DAYS WORKED FOR A PAY PERIOD
    if ($action === 'get_attendance') {
        date_default_timezone_set('Asia/Manila');
        finalizeDailyAttendance($conn, date('Y-m-d'), date('H:i:s'));

        $employee_id = intval($_GET['employee_id'] ?? 0);
        $pay_period = $_GET['pay_period'] ?? '1st Half';
        $pay_date = $_GET['pay_date'] ?? date('Y-m-d');
        $daily_rate = max(0, floatval($_GET['daily_rate'] ?? 755));

        try {
            [$startDate, $endDate] = getPayrollPeriodDates($pay_period, $pay_date);
            $summary = getPayrollAttendanceSummary($conn, $employee_id, $startDate, $endDate, $daily_rate);
            echo json_encode([
                'status' => 'success',
                'days_worked' => $summary['days_worked'],
                'paid_leave_days' => $summary['paid_leave_days'],
                'unpaid_leave_days' => $summary['unpaid_leave_days'],
                'paid_leaves' => $summary['paid_leaves'],
                'late_minutes' => $summary['late_minutes'],
                'late_deduction' => $summary['late_deduction'],
                'excessive_late_days' => $summary['excessive_late_days']
            ]);
        } catch (Throwable $error) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $error->getMessage()
            ]);
        }
        exit();
    }

    if ($action === 'get_all') {
        $sql = "SELECT p.*, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.username
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
        
        $stmt = $conn->prepare("SELECT p.*, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.username
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
    $employeeIdValue = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
    $employee_id = $employeeIdValue === false || $employeeIdValue === null ? 0 : (int) $employeeIdValue;
    $attendance_id = null;
    if (isset($_POST['attendance_id']) && $_POST['attendance_id'] !== '') {
        $attendanceIdValue = filter_var($_POST['attendance_id'], FILTER_VALIDATE_INT);
        $attendance_id = $attendanceIdValue === false || $attendanceIdValue < 1 ? 0 : (int) $attendanceIdValue;
    }
    $pay_period = is_string($_POST['pay_period'] ?? null) ? $_POST['pay_period'] : '';
    $pay_date = is_string($_POST['pay_date'] ?? null) ? trim($_POST['pay_date']) : '';
    $status        = 'Pending';
    $dailyRateInput = $_POST['daily_rate'] ?? '755';
    $daily_rate = is_scalar($dailyRateInput) && is_numeric($dailyRateInput) ? (float) $dailyRateInput : NAN;
    $moneyFields = ['reimbursement', 'sss', 'pagibig', 'philhealth', 'calamity_loan', 'hmo'];
    $moneyValues = [];
    $invalidAmount = !is_finite($daily_rate) || $daily_rate < 0 || $daily_rate > 99999999.99;
    $invalidAmountField = $invalidAmount ? 'daily rate' : null;
    foreach ($moneyFields as $field) {
        $rawAmount = $_POST[$field] ?? '0';
        if (!is_scalar($rawAmount)) {
            $invalidAmount = true;
            $invalidAmountField ??= $field;
            continue;
        }
        $normalizedAmount = is_string($rawAmount) ? trim($rawAmount) : $rawAmount;
        if ($normalizedAmount === '') {
            $normalizedAmount = '0';
        }
        if (!is_numeric($normalizedAmount)) {
            $invalidAmount = true;
            $invalidAmountField ??= $field;
            continue;
        }
        $amount = (float) $normalizedAmount;
        if (!is_finite($amount) || $amount < 0 || $amount > 99999999.99) {
            $invalidAmount = true;
            $invalidAmountField ??= $field;
            continue;
        }
        $moneyValues[$field] = round($amount, 2);
    }
    $validationMessage = null;
    if ($employee_id < 1) {
        $validationMessage = 'Select an employee before saving payroll.';
    } elseif (!in_array($pay_period, ['Kinsenas', 'Katapusan'], true)) {
        $validationMessage = 'Select a valid pay period.';
    } elseif ($pay_date === '') {
        $validationMessage = 'Enter a pay date.';
    } elseif ($attendance_id !== null && $attendance_id < 1) {
        $validationMessage = 'The attendance record is invalid.';
    } elseif ($invalidAmount) {
        $validationMessage = 'Enter a valid non-negative amount for ' . $invalidAmountField . '.';
    }
    if ($validationMessage !== null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $validationMessage]);
        exit();
    }

    $employeeExists = $conn->prepare("
        SELECT employee_id,
               COALESCE(NULLIF(username, ''), CONCAT('Employee #', employee_id)) AS employee_name
        FROM employee
        WHERE employee_id = ?
        LIMIT 1
    ");
    if (!$employeeExists) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to verify the selected employee.']);
        exit();
    }
    $employeeExists->bind_param('i', $employee_id);
    if (!$employeeExists->execute()) {
        $employeeExists->close();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to verify the selected employee.']);
        exit();
    }
    $employee = $employeeExists->get_result()->fetch_assoc();
    if (!$employee) {
        $employeeExists->close();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Choose an existing employee.']);
        exit();
    }
    $employee_name = (string) $employee['employee_name'];
    $employeeExists->close();
    if ($attendance_id !== null) {
        $attendanceBelongsToEmployee = $conn->prepare('SELECT attendance_id FROM attendance WHERE attendance_id = ? AND employee_id = ? LIMIT 1');
        if (!$attendanceBelongsToEmployee) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to verify the attendance record.']);
            exit();
        }
        $attendanceBelongsToEmployee->bind_param('ii', $attendance_id, $employee_id);
        $validAttendance = $attendanceBelongsToEmployee->execute()
            && $attendanceBelongsToEmployee->get_result()->num_rows === 1;
        $attendanceBelongsToEmployee->close();
        if (!$validAttendance) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'The attendance record does not belong to the selected employee.']);
            exit();
        }
    }

    try {
        [$startDate, $endDate] = getPayrollPeriodDates($pay_period, $pay_date);
        $attendanceSummary = getPayrollAttendanceSummary($conn, $employee_id, $startDate, $endDate, $daily_rate);
        $monthStart = substr($pay_date, 0, 7) . '-01';
        $monthEnd = (new DateTimeImmutable($pay_date))->modify('last day of this month')->format('Y-m-d');
    } catch (Throwable $error) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $error->getMessage()
        ]);
        exit();
    }

    $payrollLockName = 'tasktrack_payroll_' . substr(hash('sha256', $employee_id . ':' . $pay_period . ':' . $monthStart), 0, 32);
    $payrollLock = $conn->prepare('SELECT GET_LOCK(?, 10) AS lock_acquired');
    if (!$payrollLock) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to lock payroll creation.']);
        exit();
    }
    $payrollLock->bind_param('s', $payrollLockName);
    $lockAcquired = $payrollLock->execute()
        && (int) ($payrollLock->get_result()->fetch_assoc()['lock_acquired'] ?? 0) === 1;
    $payrollLock->close();
    if (!$lockAcquired) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Payroll is being created for this employee and period. Please try again.']);
        exit();
    }
    $releasePayrollLock = static function () use ($conn, $payrollLockName): void {
        $releaseStatement = $conn->prepare('SELECT RELEASE_LOCK(?)');
        if ($releaseStatement) {
            $releaseStatement->bind_param('s', $payrollLockName);
            $releaseStatement->execute();
            $releaseStatement->close();
        }
    };

    $paidPayrollCheck = $conn->prepare("
        SELECT payroll_id
        FROM payroll
        WHERE employee_id = ?
          AND pay_period = ?
          AND pay_date BETWEEN ? AND ?
        LIMIT 1
    ");
    if (!$paidPayrollCheck) {
        $releasePayrollLock();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare duplicate payroll check: ' . $conn->error
        ]);
        exit();
    }

    $paidPayrollCheck->bind_param('isss', $employee_id, $pay_period, $monthStart, $monthEnd);
    if (!$paidPayrollCheck->execute()) {
        $error = $paidPayrollCheck->error;
        $paidPayrollCheck->close();
        $releasePayrollLock();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to check existing paid payroll: ' . $error
        ]);
        exit();
    }
    $hasPaidPayroll = $paidPayrollCheck->get_result()->num_rows > 0;
    $paidPayrollCheck->close();
    if ($hasPaidPayroll) {
        $releasePayrollLock();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'A payroll record already exists for this employee and pay period. Update its status from the payroll list instead of creating a duplicate.'
        ]);
        exit();
    }

    // Earnings
    $regular_pay     = round($attendanceSummary['days_worked'] * $daily_rate, 2);
    $paid_leaves     = $attendanceSummary['paid_leaves'];
    $daily_allowance = 0.0;
    $reimbursement   = $moneyValues['reimbursement'];

    // Deductions
    $late_deduction = $attendanceSummary['late_deduction'];
    $sss            = $moneyValues['sss'];
    $pagibig        = $moneyValues['pagibig'];
    $philhealth     = $moneyValues['philhealth'];
    $calamity_loan  = $moneyValues['calamity_loan'];
    $hmo            = $moneyValues['hmo'];

    // Automatic Computations
    $gross_pay       = $regular_pay + $paid_leaves;
    $total_deductions = $late_deduction + $sss + $pagibig + $philhealth + $calamity_loan + $hmo;
    $total_credited   = ($gross_pay - $total_deductions) + $reimbursement;
    if (!is_finite($total_credited) || $total_credited < 0) {
        $releasePayrollLock();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payroll deductions cannot exceed gross pay plus reimbursement.']);
        exit();
    }

    if (!$conn->begin_transaction()) {
        $releasePayrollLock();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to start payroll creation.']);
        exit();
    }
    $stmt = $conn->prepare("
        INSERT INTO payroll (
            employee_id, attendance_id, employee_name, pay_period, pay_date, status,
            regular_pay, paid_leaves, daily_allowance, reimbursement, 
            gross_pay, late_deduction, sss, pagibig, philhealth, 
            calamity_loan, hmo, total_deductions, total_credited
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        $conn->rollback();
        $releasePayrollLock();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to prepare payroll creation.']);
        exit();
    }
    $stmt->bind_param(
        "iissssddddddddddddd",
        $employee_id, $attendance_id, $employee_name, $pay_period, $pay_date, $status,
        $regular_pay, $paid_leaves, $daily_allowance, $reimbursement,
        $gross_pay, $late_deduction, $sss, $pagibig, $philhealth,
        $calamity_loan, $hmo, $total_deductions, $total_credited
    );

    try {
        $payrollSaved = $stmt->execute();
        $payrollSaveError = $payrollSaved ? null : $stmt->error;
    } catch (mysqli_sql_exception $error) {
        $payrollSaved = false;
        $payrollSaveError = $error->getMessage();
    }

    if ($payrollSaved) {
        recordSystemAudit(
            $conn,
            (int) ($_SESSION['id'] ?? 0) ?: null,
            (string) ($_SESSION['name'] ?? 'Payroll user'),
            'payroll_created',
            'payroll',
            (int) $conn->insert_id,
            'Created payroll for employee #' . $employee_id . ' for ' . $pay_period . ' (' . $pay_date . ').'
        );
        if (!$conn->commit()) {
            $conn->rollback();
            $releasePayrollLock();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to commit payroll creation.']);
            exit();
        }
        $stmt->close();
        $releasePayrollLock();
        header("Location: ../payroll.php?success=1");
        exit();
    } else {
        $stmt->close();
        $conn->rollback();
        $releasePayrollLock();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save payroll: ' . $payrollSaveError
        ]);
        exit();
    }
}

// =================================================================
// 3. TOGGLE PAYROLL STATUS
// =================================================================
if ($method === 'POST' && $action === 'mark_paid') {
    $payroll_id = intval($_POST['payroll_id'] ?? 0);
    if ($payroll_id < 1 || ($_POST['review_confirmed'] ?? '') !== '1') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Confirm the payroll review before marking it Paid.']);
        exit();
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE payroll SET status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE payroll_id = ? AND status = 'Pending'");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare review confirmation: ' . $conn->error);
        }
        $stmt->bind_param('i', $payroll_id);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to confirm payroll review: ' . $stmt->error);
        }
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$changed) {
            $conn->rollback();
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Payroll was not pending or could not be found.']);
            exit();
        }
        recordSystemAudit(
            $conn,
            (int) ($_SESSION['id'] ?? 0) ?: null,
            (string) ($_SESSION['name'] ?? 'Payroll user'),
            'payroll_review_confirmed',
            'payroll',
            $payroll_id,
            'Confirmed payroll review and marked payroll #' . $payroll_id . ' as Paid. This status is not a payout receipt.'
        );
        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Payroll review confirmed.'
        ]);
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Payroll review confirmation failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unable to confirm payroll review.'
        ]);
    }
    exit();
}

if ($method === 'POST' && $action === 'edit_paid') {
    $payrollId = filter_input(INPUT_POST, 'payroll_id', FILTER_VALIDATE_INT) ?: 0;
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $fields = [
        'regular_pay', 'paid_leaves', 'reimbursement',
        'late_deduction', 'sss', 'pagibig', 'philhealth', 'calamity_loan', 'hmo'
    ];
    $amounts = [];
    $invalid = $payrollId < 1 || $reason === '' || strlen($reason) > 500;
    foreach ($fields as $field) {
        $rawValue = trim((string) ($_POST[$field] ?? ''));
        if ($rawValue === '' || !is_numeric($rawValue) || !is_finite((float) $rawValue) || (float) $rawValue < 0) {
            $invalid = true;
            break;
        }
        $amounts[$field] = round((float) $rawValue, 2);
    }
    if ($invalid) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Enter a correction reason and valid non-negative payroll amounts.']);
        exit();
    }

    $conn->begin_transaction();
    try {
        $lookup = $conn->prepare('SELECT * FROM payroll WHERE payroll_id = ? FOR UPDATE');
        if (!$lookup) {
            throw new RuntimeException('Unable to prepare paid payroll lookup: ' . $conn->error);
        }
        $lookup->bind_param('i', $payrollId);
        if (!$lookup->execute()) {
            throw new RuntimeException('Unable to read paid payroll record: ' . $lookup->error);
        }
        $oldRecord = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$oldRecord || $oldRecord['status'] !== 'Paid') {
            $conn->rollback();
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Only payroll records marked Paid can be corrected here.']);
            exit();
        }

        $grossPay = round($amounts['regular_pay'] + $amounts['paid_leaves'], 2);
        $totalDeductions = round(
            $amounts['late_deduction'] + $amounts['sss'] + $amounts['pagibig']
            + $amounts['philhealth'] + $amounts['calamity_loan'] + $amounts['hmo'],
            2
        );
        $totalCredited = round($grossPay - $totalDeductions + $amounts['reimbursement'], 2);
        if ($totalCredited < 0) {
            throw new InvalidArgumentException('The correction cannot result in a negative net credited amount.');
        }

        $update = $conn->prepare(
            'UPDATE payroll
             SET regular_pay = ?, paid_leaves = ?, daily_allowance = 0, reimbursement = ?, gross_pay = ?,
                 late_deduction = ?, sss = ?, pagibig = ?, philhealth = ?, calamity_loan = ?, hmo = ?,
                 total_deductions = ?, total_credited = ?
             WHERE payroll_id = ? AND status = \'Paid\''
        );
        if (!$update) {
            throw new RuntimeException('Unable to prepare paid payroll correction: ' . $conn->error);
        }
        $update->bind_param(
            'ddddddddddddi',
            $amounts['regular_pay'],
            $amounts['paid_leaves'],
            $amounts['reimbursement'],
            $grossPay,
            $amounts['late_deduction'],
            $amounts['sss'],
            $amounts['pagibig'],
            $amounts['philhealth'],
            $amounts['calamity_loan'],
            $amounts['hmo'],
            $totalDeductions,
            $totalCredited,
            $payrollId
        );
        if (!$update->execute()) {
            throw new RuntimeException('Unable to save paid payroll correction: ' . $update->error);
        }
        $update->close();

        $oldValues = [];
        foreach ($fields as $field) {
            $oldValues[$field] = (float) $oldRecord[$field];
        }
        $oldValues['daily_allowance'] = (float) $oldRecord['daily_allowance'];
        $oldValues['gross_pay'] = (float) $oldRecord['gross_pay'];
        $oldValues['total_deductions'] = (float) $oldRecord['total_deductions'];
        $oldValues['total_credited'] = (float) $oldRecord['total_credited'];
        $newValues = ['daily_allowance' => 0.0] + $amounts + [
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'total_credited' => $totalCredited
        ];
        recordSystemAudit(
            $conn,
            (int) ($_SESSION['id'] ?? 0) ?: null,
            (string) ($_SESSION['name'] ?? 'Payroll user'),
            'paid_payroll_corrected',
            'payroll',
            $payrollId,
            json_encode(['old' => $oldValues, 'new' => $newValues, 'reason' => $reason], JSON_THROW_ON_ERROR)
        );
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Paid payroll corrected; the record remains marked Paid.']);
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Paid payroll correction failed: ' . $error->getMessage());
        http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
        echo json_encode([
            'status' => 'error',
            'message' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to correct paid payroll.'
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