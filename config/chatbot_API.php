<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

function chatbotRespond(string $reply, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['email'], $_SESSION['role'])) {
    chatbotRespond('Mag-login muna para magamit ang assistant.', 401);
}

$role = (string) $_SESSION['role'];
$validatedUserId = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT);
$userId = $validatedUserId === false ? null : $validatedUserId;
$allowedRoles = ['super', 'pro', 'log', 'HR', 'payroll'];

if (!in_array($role, $allowedRoles, true)) {
    chatbotRespond('Hindi available ang assistant para sa account na ito.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatbotRespond('Gumamit ng POST request para magtanong.', 405);
}

$request = json_decode(file_get_contents('php://input'), true);
$message = $request['message'] ?? '';

if (!is_string($message) || trim($message) === '' || mb_strlen($message) > 300) {
    chatbotRespond('Mag-type ng tanong na hanggang 300 characters.', 400);
}

$message = mb_strtolower(trim($message), 'UTF-8');

if (preg_match('/\b(assign|update|delete|remove|mark|create|add|save|change|i-assign|i-update|burahin)\b/u', $message)) {
    chatbotRespond('Read-only ang assistant. Maaari itong sumagot tungkol sa live records pero hindi ito gumagawa o nagbabago ng records.');
}

include __DIR__ . '/connection.php';
$conn->set_charset('utf8mb4');

function chatbotFetchRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $bindValues = [$types];
        foreach ($params as $index => $value) {
            $bindValues[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindValues);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function chatbotScalar(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    return chatbotFetchRows($conn, $sql, $types, $params)[0] ?? [];
}

function chatbotFormatDetails(string $title, array $fields): string
{
    $reply = $title . ":\n";
    foreach ($fields as $label => $value) {
        if ($value === null || $value === '') {
            $value = 'N/A';
        }
        $reply .= $label . ': ' . $value . "\n";
    }
    return trim($reply);
}

function chatbotRecordDetails(
    mysqli $conn,
    string $recordType,
    string $recordId,
    string $role,
    ?int $employeeId,
    ?int $userId
): string {
    if ($recordType === 'production') {
        if (!in_array($role, ['super', 'pro'], true)) {
            return 'Hindi available ang production data sa role mo.';
        }
        if ($role === 'pro' && $employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }

        $sql = "SELECT p.production_id, p.product_name, p.target_pcs, p.quantity,
                       p.Stock_number, p.due_date, p.product_status,
                       pa.work_type, e.username AS assigned_employee
                FROM production p
                LEFT JOIN production_assignments pa ON pa.production_id = CAST(p.production_id AS CHAR)
                LEFT JOIN employee e ON e.employee_id = pa.employee_id
                WHERE p.production_id = ?";
        $types = 's';
        $params = [$recordId];
        if ($role === 'pro') {
            $sql .= ' AND pa.employee_id = ?';
            $types .= 'i';
            $params[] = $employeeId;
        }
        $row = chatbotScalar($conn, $sql . ' LIMIT 1', $types, $params);
        if (!$row) {
            return 'Walang production record na nakita para sa ID na iyon.';
        }
        $fields = [
            'Product' => $row['product_name'],
            'Status' => $row['product_status'] ?: 'Pending',
            'Work area' => $row['work_type'],
            'Quantity' => $row['quantity'],
            'Target PCS' => $row['target_pcs'],
            'Stock number' => $row['Stock_number'],
            'Due date' => $row['due_date'],
        ];
        if ($role === 'super') {
            $fields['Assigned employee'] = $row['assigned_employee'];
        }
        return chatbotFormatDetails('Production #' . $row['production_id'], $fields);
    }

    if ($recordType === 'delivery') {
        if (!in_array($role, ['super', 'log'], true)) {
            return 'Hindi available ang delivery data sa role mo.';
        }
        if ($role === 'log' && $userId === null) {
            return 'Hindi ma-verify ang naka-login na delivery account.';
        }

        $sql = "SELECT d.delivery_id, d.route, d.pieces, d.stock, d.delivery_date,
                       d.status, p.product_name, u.name AS driver_name
                FROM delivery d
                LEFT JOIN production p ON p.production_id = d.production_id
                LEFT JOIN users u ON u.id = d.driver_id
                WHERE d.delivery_id = ?";
        $types = 'i';
        $params = [(int) $recordId];
        if ($role === 'log') {
            $sql .= ' AND d.driver_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }
        $row = chatbotScalar($conn, $sql . ' LIMIT 1', $types, $params);
        if (!$row) {
            return 'Walang delivery record na nakita para sa ID na iyon.';
        }
        $fields = [
            'Product' => $row['product_name'],
            'Route' => $row['route'],
            'Status' => $row['status'] ?: 'Pending',
            'Pieces' => $row['pieces'],
            'Stock number' => $row['stock'],
            'Delivery date' => $row['delivery_date'],
        ];
        if ($role === 'super') {
            $fields['Driver'] = $row['driver_name'];
        }
        return chatbotFormatDetails('Delivery #' . $row['delivery_id'], $fields);
    }

    if ($recordType === 'employee') {
        if (!in_array($role, ['super', 'HR'], true)) {
            return 'Hindi available ang employee details sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT employee_id, username, department, position, contact_number, email, address
             FROM employee
             WHERE employee_id = ?
             LIMIT 1",
            'i',
            [(int) $recordId]
        );
        if (!$row) {
            return 'Walang employee record na nakita para sa ID na iyon.';
        }
        return chatbotFormatDetails('Employee #' . $row['employee_id'], [
            'Name' => $row['username'],
            'Department' => $row['department'],
            'Position' => $row['position'],
            'Contact number' => $row['contact_number'],
            'Email' => $row['email'],
            'Address' => $row['address'],
        ]);
    }

    if ($recordType === 'applicant') {
        if (!in_array($role, ['super', 'HR'], true)) {
            return 'Hindi available ang applicant details sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT applicant_id, firstname, middlename, lastname, contact_number, facebook,
                    email, house_number, street, barangay, city, province, position_applied,
                    company_name, position, date_of_start, date_of_end, education, start_date,
                    status, interview_type, interview_mode, interview_date
             FROM applicant
             WHERE applicant_id = ?
             LIMIT 1",
            'i',
            [(int) $recordId]
        );
        if (!$row) {
            return 'Walang applicant record na nakita para sa ID na iyon.';
        }
        return chatbotFormatDetails('Applicant #' . $row['applicant_id'], [
            'Name' => trim(implode(' ', array_filter([
                $row['firstname'],
                $row['middlename'],
                $row['lastname'],
            ]))),
            'Contact number' => $row['contact_number'],
            'Email' => $row['email'],
            'Facebook' => $row['facebook'],
            'Address' => trim(implode(' ', array_filter([
                $row['house_number'],
                $row['street'],
                $row['barangay'],
                $row['city'],
                $row['province'],
            ]))),
            'Position applied' => $row['position_applied'],
            'Previous company' => $row['company_name'],
            'Previous position' => $row['position'],
            'Previous employment dates' => trim(($row['date_of_start'] ?? '') . ' - ' . ($row['date_of_end'] ?? '')),
            'Education' => $row['education'],
            'Available start date' => $row['start_date'],
            'Status' => $row['status'],
            'Interview stage' => $row['interview_type'],
            'Interview mode' => $row['interview_mode'],
            'Interview date' => $row['interview_date'],
        ]);
    }

    if ($recordType === 'payroll') {
        if (!in_array($role, ['super', 'payroll'], true)) {
            return 'Hindi available ang payroll details sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT p.payroll_id, p.pay_period, p.pay_date, p.status,
                    p.regular_pay, p.paid_leaves, p.daily_allowance, p.reimbursement,
                    p.gross_pay, p.late_deduction, p.sss, p.pagibig, p.philhealth,
                    p.calamity_loan, p.hmo, p.total_deductions, p.total_credited,
                    e.username AS employee_name
             FROM payroll p
             INNER JOIN employee e ON e.employee_id = p.employee_id
             WHERE p.payroll_id = ?
             LIMIT 1",
            'i',
            [(int) $recordId]
        );
        if (!$row) {
            return 'Walang payroll record na nakita para sa ID na iyon.';
        }
        $money = static function ($amount): string {
            return '₱' . number_format((float) ($amount ?? 0), 2);
        };
        return chatbotFormatDetails('Payroll #' . $row['payroll_id'], [
            'Employee' => $row['employee_name'],
            'Pay period' => $row['pay_period'],
            'Pay date' => $row['pay_date'],
            'Status' => $row['status'],
            'Regular pay' => $money($row['regular_pay']),
            'Paid leaves' => $money($row['paid_leaves']),
            'Daily allowance' => $money($row['daily_allowance']),
            'Reimbursement' => $money($row['reimbursement']),
            'Gross pay' => $money($row['gross_pay']),
            'Late deduction' => $money($row['late_deduction']),
            'SSS' => $money($row['sss']),
            'Pag-IBIG' => $money($row['pagibig']),
            'PhilHealth' => $money($row['philhealth']),
            'Calamity loan' => $money($row['calamity_loan']),
            'HMO' => $money($row['hmo']),
            'Total deductions' => $money($row['total_deductions']),
            'Net credited' => $money($row['total_credited']),
        ]);
    }

    if ($recordType === 'attendance') {
        if (!in_array($role, ['super', 'HR', 'payroll'], true)) {
            return 'Hindi available ang attendance details sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT a.attendance_id, a.employee_id, a.attendance_date,
                    a.time_in, a.time_out, a.time_in_2, a.time_out_2, a.status,
                    COALESCE(u.name, e.username, a.username, 'N/A') AS display_name
             FROM attendance a
             LEFT JOIN employee e ON a.employee_id = e.employee_id
             LEFT JOIN users u ON a.user_id = u.id OR e.username = u.name
             WHERE a.attendance_id = ?
             LIMIT 1",
            'i',
            [(int) $recordId]
        );
        if (!$row) {
            return 'Walang attendance record na nakita para sa ID na iyon.';
        }
        return chatbotFormatDetails('Attendance #' . $row['attendance_id'], [
            'Employee' => $row['display_name'],
            'Employee ID' => $row['employee_id'],
            'Date' => $row['attendance_date'],
            'Status' => $row['status'],
            'Time in 1' => $row['time_in'],
            'Time out 1' => $row['time_out'],
            'Time in 2' => $row['time_in_2'],
            'Time out 2' => $row['time_out_2'],
        ]);
    }

    return 'Hindi suportado ang ganitong record lookup.';
}

function chatbotNormalizeRecordType(string $type): string
{
    $normalized = mb_strtolower(trim($type), 'UTF-8');
    $aliases = [
        'productions' => 'production',
        'deliveries' => 'delivery',
        'shipment' => 'delivery',
        'shipments' => 'delivery',
        'employees' => 'employee',
        'staff' => 'employee',
        'applicants' => 'applicant',
        'salaries' => 'payroll',
        'payslips' => 'payroll',
        'attendances' => 'attendance',
    ];
    return $aliases[$normalized] ?? $normalized;
}

function chatbotListRecords(
    mysqli $conn,
    string $recordType,
    string $role,
    ?int $employeeId,
    ?int $userId,
    int $page,
    string $search = ''
): string {
    $allowed = [
        'production' => ['super', 'pro'],
        'delivery' => ['super', 'log'],
        'employee' => ['super', 'HR'],
        'applicant' => ['super', 'HR'],
        'payroll' => ['super', 'payroll'],
        'attendance' => ['super', 'HR', 'payroll'],
    ];
    if (!isset($allowed[$recordType]) || !in_array($role, $allowed[$recordType], true)) {
        return 'Hindi available ang record type na iyan sa role mo.';
    }
    if ($recordType === 'production' && $role === 'pro' && $employeeId === null) {
        return 'Hindi makita ang employee record na naka-link sa production account mo.';
    }
    if ($recordType === 'delivery' && $role === 'log' && $userId === null) {
        return 'Hindi ma-verify ang naka-login na delivery account.';
    }

    $pageSize = 10;
    $page = max(1, min($page, 10000));
    $offset = ($page - 1) * $pageSize;
    $searchTerm = '%' . $search . '%';
    $types = '';
    $params = [];

    switch ($recordType) {
        case 'production':
            $sql = "SELECT p.production_id AS record_id, p.product_name AS item,
                           COALESCE(NULLIF(p.product_status, ''), 'Pending') AS status,
                           p.due_date AS date_value, p.quantity, pa.work_type
                    FROM production p
                    LEFT JOIN production_assignments pa ON pa.production_id = CAST(p.production_id AS CHAR)";
            if ($role === 'pro') {
                $sql .= ' WHERE pa.employee_id = ?';
                $types .= 'i';
                $params[] = $employeeId;
                if ($search !== '') {
                    $sql .= ' AND (p.product_name LIKE ? OR p.product_status LIKE ? OR pa.work_type LIKE ?)';
                    $types .= 'sss';
                    array_push($params, $searchTerm, $searchTerm, $searchTerm);
                }
            } elseif ($search !== '') {
                $sql .= ' WHERE p.product_name LIKE ? OR p.product_status LIKE ? OR pa.work_type LIKE ?';
                $types .= 'sss';
                array_push($params, $searchTerm, $searchTerm, $searchTerm);
            }
            $sql .= ' ORDER BY p.production_id DESC';
            $title = 'Production';
            break;

        case 'delivery':
            $sql = "SELECT d.delivery_id AS record_id, d.route AS item,
                           COALESCE(NULLIF(d.status, ''), 'Pending') AS status,
                           d.delivery_date AS date_value, p.product_name, d.pieces
                    FROM delivery d
                    LEFT JOIN production p ON p.production_id = d.production_id";
            if ($role === 'log') {
                $sql .= ' WHERE d.driver_id = ?';
                $types .= 'i';
                $params[] = $userId;
                if ($search !== '') {
                    $sql .= ' AND (d.route LIKE ? OR d.status LIKE ? OR p.product_name LIKE ?)';
                    $types .= 'sss';
                    array_push($params, $searchTerm, $searchTerm, $searchTerm);
                }
            } elseif ($search !== '') {
                $sql .= ' WHERE d.route LIKE ? OR d.status LIKE ? OR p.product_name LIKE ?';
                $types .= 'sss';
                array_push($params, $searchTerm, $searchTerm, $searchTerm);
            }
            $sql .= ' ORDER BY d.delivery_id DESC';
            $title = 'Delivery';
            break;

        case 'employee':
            $sql = "SELECT employee_id AS record_id, username AS item,
                           COALESCE(NULLIF(department, ''), 'Unassigned') AS detail_one,
                           position AS detail_two
                    FROM employee";
            if ($search !== '') {
                $sql .= ' WHERE username LIKE ? OR department LIKE ? OR position LIKE ?';
                $types = 'sss';
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            $sql .= ' ORDER BY employee_id DESC';
            $title = 'Employee';
            break;

        case 'applicant':
            $sql = "SELECT applicant_id AS record_id,
                                  TRIM(CONCAT_WS(' ', firstname, middlename, lastname)) AS item,
                           COALESCE(NULLIF(status, ''), 'Unspecified') AS status,
                           position_applied AS detail_one, interview_date AS date_value
                    FROM applicant";
            if ($search !== '') {
                $sql .= ' WHERE firstname LIKE ? OR middlename LIKE ? OR lastname LIKE ? OR status LIKE ? OR position_applied LIKE ?';
                $types = 'sssss';
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            $sql .= ' ORDER BY applicant_id DESC';
            $title = 'Applicant';
            break;

        case 'payroll':
            $sql = "SELECT p.payroll_id AS record_id, e.username AS item,
                           p.status, p.pay_period AS detail_one, p.pay_date AS date_value,
                           p.total_credited
                    FROM payroll p
                    INNER JOIN employee e ON e.employee_id = p.employee_id";
            if ($search !== '') {
                $sql .= ' WHERE e.username LIKE ? OR p.status LIKE ? OR p.pay_period LIKE ?';
                $types = 'sss';
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            $sql .= ' ORDER BY p.payroll_id DESC';
            $title = 'Payroll';
            break;

        case 'attendance':
            $sql = "SELECT a.attendance_id AS record_id,
                           COALESCE(u.name, e.username, a.username, 'N/A') AS item,
                           COALESCE(NULLIF(a.status, ''), 'Unspecified') AS status,
                           a.attendance_date AS date_value, a.time_in
                    FROM attendance a
                    LEFT JOIN employee e ON a.employee_id = e.employee_id
                    LEFT JOIN users u ON a.user_id = u.id OR e.username = u.name";
            if ($search !== '') {
                $sql .= ' WHERE u.name LIKE ? OR e.username LIKE ? OR a.username LIKE ? OR a.status LIKE ?';
                $types = 'ssss';
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            $sql .= ' ORDER BY a.attendance_date DESC, a.attendance_id DESC';
            $title = 'Attendance';
            break;

        default:
            return 'Hindi suportado ang record type na iyan.';
    }

    $rows = chatbotFetchRows(
        $conn,
        $sql . ' LIMIT ' . ($pageSize + 1) . ' OFFSET ' . $offset,
        $types,
        $params
    );
    $hasMore = count($rows) > $pageSize;
    if ($hasMore) {
        array_pop($rows);
    }
    if (!$rows) {
        return $search === ''
            ? 'Walang ' . strtolower($title) . ' record na nakita sa page ' . $page . '.'
            : 'Walang ' . strtolower($title) . ' record na tumugma sa paghahanap na "' . $search . '".';
    }

    $reply = ($search === '' ? 'Mga ' : 'Search results sa ') . $title;
    if ($search !== '') {
        $reply .= ' para sa "' . $search . '"';
    }
    $reply .= ' (page ' . $page . "):\n";

    foreach ($rows as $row) {
        $reply .= '#' . $row['record_id'] . ' ' . $row['item'];
        if (isset($row['status'])) {
            $reply .= ' | ' . $row['status'];
        }
        if (!empty($row['work_type'])) {
            $reply .= ' | ' . $row['work_type'];
        }
        if (!empty($row['product_name'])) {
            $reply .= ' | ' . $row['product_name'];
        }
        if (isset($row['quantity'])) {
            $reply .= ' | Qty: ' . $row['quantity'];
        }
        if (isset($row['pieces'])) {
            $reply .= ' | Pieces: ' . $row['pieces'];
        }
        if (!empty($row['detail_one'])) {
            $reply .= ' | ' . $row['detail_one'];
        }
        if (!empty($row['detail_two'])) {
            $reply .= ' | ' . $row['detail_two'];
        }
        if (!empty($row['total_credited'])) {
            $reply .= ' | Net: ₱' . number_format((float) $row['total_credited'], 2);
        }
        if (!empty($row['date_value'])) {
            $reply .= ' | ' . $row['date_value'];
        }
        if (!empty($row['time_in'])) {
            $reply .= ' | Time in: ' . $row['time_in'];
        }
        $reply .= "\n";
    }
    if ($hasMore) {
        if ($search !== '') {
            $nextCommand = 'search ' . $recordType . ' ' . $search . ' page ' . ($page + 1);
        } else {
            $nextCommand = 'page ' . ($page + 1) . ' ' . $recordType;
        }
        $reply .= 'May kasunod pang records. I-type ang "' . $nextCommand . '".';
    } else {
        $reply .= 'Wala nang kasunod na records.';
    }
    return trim($reply);
}

function chatbotProduction(mysqli $conn, string $role, ?int $employeeId): string
{
    if ($role === 'pro') {
        if ($employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }
        $rows = chatbotFetchRows(
            $conn,
            "SELECT p.production_id, p.product_name, p.product_status, p.due_date, pa.work_type
             FROM production_assignments pa
             INNER JOIN production p ON p.production_id = pa.production_id
             WHERE pa.employee_id = ?
             ORDER BY p.production_id DESC
             LIMIT 5",
            'i',
            [$employeeId]
        );
        $title = 'Mga production task mo';
    } else {
        $rows = chatbotFetchRows(
            $conn,
            "SELECT production_id, product_name, product_status, due_date
             FROM production
             ORDER BY production_id DESC
             LIMIT 5"
        );
        $title = 'Pinakabagong production tasks';
    }

    if (!$rows) {
        return 'Walang production record na nakita.';
    }

    $reply = $title . ":\n";
    foreach ($rows as $row) {
        $reply .= sprintf(
            "#%s %s | %s%s\n",
            $row['production_id'],
            $row['product_name'],
            $row['product_status'] ?: 'Pending',
            empty($row['work_type']) ? '' : ' | ' . $row['work_type']
        );
    }
    return trim($reply);
}

function chatbotDelivery(mysqli $conn, string $role, ?int $userId): string
{
    if ($role === 'log') {
        if ($userId === null) {
            return 'Hindi ma-verify ang naka-login na delivery account.';
        }
        $rows = chatbotFetchRows(
            $conn,
            "SELECT delivery_id, route, delivery_date, status
             FROM delivery
             WHERE driver_id = ?
             ORDER BY delivery_id DESC
             LIMIT 5",
            'i',
            [$userId]
        );
        $title = 'Mga delivery mo';
    } else {
        $rows = chatbotFetchRows(
            $conn,
            "SELECT d.delivery_id, d.route, d.delivery_date, d.status, u.name AS driver_name
             FROM delivery d
             LEFT JOIN users u ON u.id = d.driver_id
             ORDER BY d.delivery_id DESC
             LIMIT 5"
        );
        $title = 'Pinakabagong delivery tasks';
    }

    if (!$rows) {
        return 'Walang delivery record na nakita.';
    }

    $reply = $title . ":\n";
    foreach ($rows as $row) {
        $reply .= sprintf(
            "Delivery #%s | %s | %s | %s%s\n",
            $row['delivery_id'],
            $row['route'],
            $row['status'] ?: 'Pending',
            $row['delivery_date'],
            empty($row['driver_name']) ? '' : ' | Driver: ' . $row['driver_name']
        );
    }
    return trim($reply);
}

function chatbotEmployeeSummary(mysqli $conn): string
{
    $totals = chatbotScalar($conn, 'SELECT COUNT(*) AS total FROM employee');
    $departments = chatbotFetchRows(
        $conn,
        "SELECT COALESCE(NULLIF(department, ''), 'Unassigned') AS department, COUNT(*) AS total
         FROM employee
         GROUP BY department
         ORDER BY total DESC
         LIMIT 8"
    );

    $reply = 'Employee records: ' . (int) ($totals['total'] ?? 0);
    if ($departments) {
        $reply .= "\nBy department:\n";
        foreach ($departments as $row) {
            $reply .= $row['department'] . ': ' . (int) $row['total'] . "\n";
        }
    }
    return trim($reply);
}

function chatbotApplicantSummary(mysqli $conn): string
{
    $rows = chatbotFetchRows(
        $conn,
        "SELECT COALESCE(NULLIF(status, ''), 'Unspecified') AS status, COUNT(*) AS total
         FROM applicant
         GROUP BY status
         ORDER BY total DESC"
    );
    if (!$rows) {
        return 'Walang applicant records na nakita.';
    }

    $reply = "Applicant records ayon sa status:\n";
    foreach ($rows as $row) {
        $reply .= $row['status'] . ': ' . (int) $row['total'] . "\n";
    }
    return trim($reply);
}

function chatbotAttendanceSummary(mysqli $conn): string
{
    $rows = chatbotFetchRows(
        $conn,
        "SELECT COALESCE(NULLIF(status, ''), 'Unspecified') AS status, COUNT(*) AS total
         FROM attendance
         WHERE DATE(
             CASE
                 WHEN attendance_date LIKE '%-%' THEN attendance_date
                 ELSE STR_TO_DATE(attendance_date, '%b %d, %Y')
             END
         ) = CURDATE()
         GROUP BY status
         ORDER BY total DESC"
    );
    if (!$rows) {
        return 'Wala pang attendance records ngayong araw.';
    }

    $reply = "Attendance ngayong araw:\n";
    foreach ($rows as $row) {
        $reply .= $row['status'] . ': ' . (int) $row['total'] . "\n";
    }
    return trim($reply);
}

function chatbotPayrollSummary(mysqli $conn): string
{
    $row = chatbotScalar(
        $conn,
        "SELECT COUNT(*) AS total_records,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'Paid' THEN total_credited ELSE 0 END) AS paid_total,
                SUM(CASE WHEN status = 'Pending' THEN total_credited ELSE 0 END) AS pending_total
         FROM payroll"
    );
    return sprintf(
        "Payroll summary:\nRecords: %d\nPaid: %d (₱%s)\nPending: %d (₱%s)",
        (int) ($row['total_records'] ?? 0),
        (int) ($row['paid_count'] ?? 0),
        number_format((float) ($row['paid_total'] ?? 0), 2),
        (int) ($row['pending_count'] ?? 0),
        number_format((float) ($row['pending_total'] ?? 0), 2)
    );
}

function chatbotOverview(mysqli $conn, string $role, ?int $employeeId, ?int $userId): string
{
    if ($role === 'super') {
        $production = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN product_status = 'product done' THEN 1 ELSE 0 END) AS completed
             FROM production"
        );
        $delivery = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS delivered
             FROM delivery"
        );
        $employees = chatbotScalar($conn, 'SELECT COUNT(*) AS total FROM employee');
        $payroll = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending
             FROM payroll"
        );
        return sprintf(
            "Company overview:\nProduction: %d total, %d completed\nDeliveries: %d total, %d delivered\nEmployees: %d\nPayroll: %d records, %d pending",
            (int) ($production['total'] ?? 0),
            (int) ($production['completed'] ?? 0),
            (int) ($delivery['total'] ?? 0),
            (int) ($delivery['delivered'] ?? 0),
            (int) ($employees['total'] ?? 0),
            (int) ($payroll['total'] ?? 0),
            (int) ($payroll['pending'] ?? 0)
        );
    }
    if ($role === 'pro') {
        if ($employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN p.product_status = 'product done' THEN 1 ELSE 0 END) AS completed
             FROM production_assignments pa
             INNER JOIN production p ON p.production_id = pa.production_id
             WHERE pa.employee_id = ?",
            'i',
            [$employeeId]
        );
        return sprintf(
            "Production overview mo:\nAssigned tasks: %d\nCompleted: %d\nIn progress: %d",
            (int) ($row['total'] ?? 0),
            (int) ($row['completed'] ?? 0),
            max(0, (int) ($row['total'] ?? 0) - (int) ($row['completed'] ?? 0))
        );
    }
    if ($role === 'log') {
        if ($userId === null) {
            return 'Hindi ma-verify ang naka-login na delivery account.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS delivered
             FROM delivery
             WHERE driver_id = ?",
            'i',
            [$userId]
        );
        return sprintf(
            "Delivery overview mo:\nAssigned deliveries: %d\nDelivered: %d\nPending: %d",
            (int) ($row['total'] ?? 0),
            (int) ($row['delivered'] ?? 0),
            max(0, (int) ($row['total'] ?? 0) - (int) ($row['delivered'] ?? 0))
        );
    }
    if ($role === 'HR') {
        return chatbotEmployeeSummary($conn) . "\n\n" . chatbotApplicantSummary($conn) . "\n\n" . chatbotAttendanceSummary($conn);
    }
    return chatbotPayrollSummary($conn);
}

try {
    $employeeId = null;
    if ($role === 'pro') {
        $employee = chatbotScalar(
            $conn,
            "SELECT e.employee_id
             FROM users u
             INNER JOIN employee e ON (e.username = u.name OR (e.email IS NOT NULL AND e.email = u.email))
             WHERE u.id = ?
             ORDER BY (e.email = u.email) DESC, e.employee_id ASC
             LIMIT 1",
            'i',
            [$userId]
        );
        if (isset($employee['employee_id'])) {
            $employeeId = (int) $employee['employee_id'];
        }
    }

    $detailMatch = [];
    $hasRecordDetails = preg_match(
        '/\b(?:details?|show|info(?:rmation)?)\s+(?:me\s+)?(?:details?\s+)?(?:of\s+|for\s+|about\s+)?(production|delivery|employee|applicant|payroll|attendance)(?:\s+(?:record|task))?(?:\s+id)?\s+#?([a-z0-9-]+)\b/u',
        $message,
        $detailMatch
    );
    if (!$hasRecordDetails) {
        $hasRecordDetails = preg_match(
            '/\b(production|delivery|employee|applicant|payroll|attendance)\s+(?:(?:record|task)\s+)?(?:id\s*)?#?([a-z0-9-]+)\s+(?:details?|info)\b/u',
            $message,
            $detailMatch
        );
    }
    if ($hasRecordDetails) {
        chatbotRespond(
            chatbotRecordDetails(
                $conn,
                $detailMatch[1],
                $detailMatch[2],
                $role,
                $employeeId,
                $userId
            )
        );
    }

    if (in_array($message, ['help', 'tulong', 'commands'], true)) {
        $help = [
            'super' => implode("\n", [
                'Maaari kang magtanong tungkol sa lahat ng business modules.',
                '',
                'List records:',
                'list production',
                'list delivery',
                'list employees',
                'list applicants',
                'list payroll',
                'list attendance',
                '',
                'Search:',
                'search production turmeric',
                'search employee Ana',
                '',
                'Record details:',
                'details production 123',
                '',
                'Next page:',
                'page 2 production',
            ]),
            'pro' => implode("\n", [
                'Maaari kang magtanong tungkol sa sarili mong production tasks.',
                '',
                'List records:',
                'list production',
                '',
                'Search:',
                'search production turmeric',
                '',
                'Record details:',
                'details production 123',
                '',
                'Next page:',
                'page 2 production',
            ]),
            'log' => implode("\n", [
                'Maaari kang magtanong tungkol sa deliveries na naka-assign sa iyo.',
                '',
                'List records:',
                'list delivery',
                '',
                'Search:',
                'search delivery Manila',
                '',
                'Record details:',
                'details delivery 45',
                '',
                'Next page:',
                'page 2 delivery',
            ]),
            'HR' => implode("\n", [
                'Maaari kang magtanong tungkol sa employees, applicants, at attendance.',
                '',
                'List records:',
                'list employees',
                'list applicants',
                'list attendance',
                '',
                'Search:',
                'search employee Ana',
                '',
                'Record details:',
                'details employee 12',
            ]),
            'payroll' => implode("\n", [
                'Maaari kang magtanong tungkol sa payroll at attendance.',
                '',
                'List records:',
                'list payroll',
                'list attendance',
                '',
                'Search:',
                'search payroll Pending',
                '',
                'Record details:',
                'details payroll 9',
            ]),
        ];
        chatbotRespond($help[$role]);
    }

    $recordTypesPattern = 'production|deliver(?:y|ies)|shipments?|employees?|staff|applicants?|payroll|payslips?|attendances?';
    $commandMatch = [];
    if (preg_match(
        '/^\s*(?:search|find|hanap|lookup)\s+(' . $recordTypesPattern . ')\s+(.+?)(?:\s+page\s+(\d+))?\s*[?.!]*\s*$/u',
        $message,
        $commandMatch
    )) {
        $recordType = chatbotNormalizeRecordType($commandMatch[1]);
        $search = trim($commandMatch[2], " \t\n\r\0\x0B?!.\"");
        $page = isset($commandMatch[3]) ? (int) $commandMatch[3] : 1;
        if ($search === '') {
            chatbotRespond('Maglagay ng keyword pagkatapos ng record type, halimbawa: “search employee Ana”.', 400);
        }
        chatbotRespond(chatbotListRecords($conn, $recordType, $role, $employeeId, $userId, $page, $search));
    }

    if (preg_match(
        '/^\s*(?:page|pahina)\s+(\d+)\s+(' . $recordTypesPattern . ')\s*[?.!]*\s*$/u',
        $message,
        $commandMatch
    )) {
        chatbotRespond(
            chatbotListRecords(
                $conn,
                chatbotNormalizeRecordType($commandMatch[2]),
                $role,
                $employeeId,
                $userId,
                (int) $commandMatch[1]
            )
        );
    }

    if (preg_match(
        '/^\s*(?:(?:please\s+)?(?:list|show|view|see|all|latest|recent|lahat|ipakita|ilista|tingnan)\b.*?\b(' . $recordTypesPattern . ')\b|(' . $recordTypesPattern . ')\s+(?:list|records))(?:(?:\s+|.*?\b)page\s+(\d+))?\s*[?.!]*\s*$/u',
        $message,
        $commandMatch
    )) {
        $recordType = chatbotNormalizeRecordType($commandMatch[1] !== '' ? $commandMatch[1] : $commandMatch[2]);
        $page = isset($commandMatch[3]) ? (int) $commandMatch[3] : 1;
        chatbotRespond(chatbotListRecords($conn, $recordType, $role, $employeeId, $userId, $page));
    }

    if (preg_match('/\b(?:all|lahat|list|show)\s+(?:business\s+)?records?\b/u', $message)) {
        $available = [
            'super' => 'production, delivery, employees, applicants, payroll, attendance',
            'pro' => 'production',
            'log' => 'delivery',
            'HR' => 'employees, applicants, attendance',
            'payroll' => 'payroll, attendance',
        ];
        chatbotRespond(
            'Piliin ang record type na gusto mong makita: ' . $available[$role] .
            '. Halimbawa: “list ' . explode(', ', $available[$role])[0] . '”.'
        );
    }

    if (preg_match('/\b(production|production task|production status|my tasks|task status)\b/u', $message)) {
        if (!in_array($role, ['super', 'pro'], true)) {
            chatbotRespond('Hindi available ang production data sa role mo.');
        }
        chatbotRespond(chatbotProduction($conn, $role, $employeeId));
    }

    if (preg_match('/\b(delivery|deliveries|shipment|shipments|my deliveries)\b/u', $message)) {
        if (!in_array($role, ['super', 'log'], true)) {
            chatbotRespond('Hindi available ang delivery data sa role mo.');
        }
        chatbotRespond(chatbotDelivery($conn, $role, $userId));
    }

    if (preg_match('/\b(applicant|applicants|recruitment|interview)\b/u', $message)) {
        if (!in_array($role, ['super', 'HR'], true)) {
            chatbotRespond('Hindi available ang applicant data sa role mo.');
        }
        chatbotRespond(chatbotApplicantSummary($conn));
    }

    if (preg_match('/\b(attendance|present|absent|late|time in)\b/u', $message)) {
        if (!in_array($role, ['super', 'HR', 'payroll'], true)) {
            chatbotRespond('Hindi available ang attendance summary sa role mo.');
        }
        chatbotRespond(chatbotAttendanceSummary($conn));
    }

    if (preg_match('/\b(employee|employees|staff|headcount|department)\b/u', $message)) {
        if (!in_array($role, ['super', 'HR'], true)) {
            chatbotRespond('Hindi available ang employee summary sa role mo.');
        }
        chatbotRespond(chatbotEmployeeSummary($conn));
    }

    if (preg_match('/\b(payroll|salary|salaries|payslip|paid|pending pay|disbursed)\b/u', $message)) {
        if (!in_array($role, ['super', 'payroll'], true)) {
            chatbotRespond('Hindi available ang payroll summary sa role mo.');
        }
        chatbotRespond(chatbotPayrollSummary($conn));
    }

    if (in_array($message, ['overview', 'summary', 'dashboard', 'status', 'report', 'kumusta', 'kamusta'], true)) {
        chatbotRespond(chatbotOverview($conn, $role, $employeeId, $userId));
    }

    chatbotRespond("Hindi ko pa alam sagutin iyan. Mag-type ng “help” para makita ang mga paksang available sa role mo.");
} catch (mysqli_sql_exception $exception) {
    error_log('Chatbot database error: ' . $exception->getMessage());
    chatbotRespond('Hindi makuha ang live data sa ngayon. Pakisubukan ulit mamaya.', 500);
}