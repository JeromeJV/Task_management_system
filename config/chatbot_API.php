<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
date_default_timezone_set('Asia/Manila');

function chatbotRespond(string $reply, int $status = 200, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['reply' => $reply], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['email'], $_SESSION['role'])) {
    chatbotRespond('Mag-login muna para magamit ang assistant.', 401);
}
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/account_access.php';
requireActiveAccount($conn, true);
require_once __DIR__ . '/leave_helpers.php';
require_once __DIR__ . '/task_notifications.php';
require_once __DIR__ . '/system_audit.php';
require_once __DIR__ . '/delivery_activity_schema.php';
require_once __DIR__ . '/production_assignment_schema.php';

$role = (string) $_SESSION['role'];
$validatedUserId = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT);
$userId = $validatedUserId === false ? null : $validatedUserId;
$productionRoles = ['pro', 'cooking', 'packaging'];
$allowedRoles = array_merge(['admin', 'super', 'log', 'HR', 'payroll', 'employee'], $productionRoles);

if (!in_array($role, $allowedRoles, true)) {
    chatbotRespond('Hindi available ang assistant para sa account na ito.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatbotRespond('Gumamit ng POST request para magtanong.', 405);
}

$request = json_decode(file_get_contents('php://input'), true);
$request = is_array($request) ? $request : [];
$message = $request['message'] ?? '';
$isActionRequest = isset($request['confirm_action']) || isset($request['cancel_action']);
if (
    empty($_SESSION['chatbot_csrf'])
    || !hash_equals((string) $_SESSION['chatbot_csrf'], (string) ($request['csrf'] ?? ''))
) {
    chatbotRespond('Nag-expire o hindi valid ang security token. I-refresh ang page at subukan ulit.', 403);
}
$conn->set_charset('utf8mb4');

if (!$isActionRequest && (!is_string($message) || trim($message) === '' || mb_strlen($message) > 300)) {
    chatbotRespond('Mag-type ng tanong na hanggang 300 characters.', 400);
}

$rawMessage = is_string($message) ? trim($message) : '';
$message = mb_strtolower($rawMessage, 'UTF-8');

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

function chatbotIsProductionRole(string $role): bool
{
    return in_array($role, ['pro', 'cooking', 'packaging'], true);
}

function chatbotProductionWorkType(string $role): ?string
{
    return match ($role) {
        'cooking' => 'Cooking',
        'packaging' => 'Packaging',
        default => null,
    };
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
        if (!in_array($role, ['admin', 'super', 'pro', 'cooking', 'packaging'], true)) {
            return 'Hindi available ang production data sa role mo.';
        }
        if (chatbotIsProductionRole($role) && $employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }

        $sql = "SELECT p.production_id, p.product_name, p.target_pcs, p.quantity,
                       p.Stock_number, p.due_date, p.product_status,
                       (SELECT GROUP_CONCAT(DISTINCT CONCAT(stage.work_type, ': ', assigned.username, ' (', stage.work_status, ')')
                                            ORDER BY stage.work_type SEPARATOR ', ')
                        FROM production_assignments stage
                        LEFT JOIN employee assigned ON assigned.employee_id = stage.employee_id
                        WHERE stage.production_id = CAST(p.production_id AS CHAR)) AS assignment_summary,
                       (SELECT work_status FROM production_assignments
                        WHERE production_id = CAST(p.production_id AS CHAR) AND work_type = 'Cooking' LIMIT 1) AS cooking_status,
                       (SELECT work_status FROM production_assignments
                        WHERE production_id = CAST(p.production_id AS CHAR) AND work_type = 'Packaging' LIMIT 1) AS packaging_status,
                       pa.work_type, pa.work_status, e.username AS assigned_employee
                FROM production p
                LEFT JOIN production_assignments pa ON pa.production_id = CAST(p.production_id AS CHAR)
                LEFT JOIN employee e ON e.employee_id = pa.employee_id
                WHERE p.production_id = ?";
        $types = 's';
        $params = [$recordId];
        if (chatbotIsProductionRole($role)) {
            $sql .= ' AND pa.employee_id = ?';
            $types .= 'i';
            $params[] = $employeeId;
            if ($work_type = chatbotProductionWorkType($role)) {
                $sql .= ' AND pa.work_type = ?';
                $types .= 's';
                $params[] = $work_type;
            }
        }
        $row = chatbotScalar($conn, $sql . ' LIMIT 1', $types, $params);
        if (!$row) {
            return 'Walang production record na nakita para sa ID na iyon.';
        }
        $fields = [
            'Product' => $row['product_name'],
            'Status' => $row['product_status'] ?: 'Pending',
            'Work area' => chatbotIsProductionRole($role) ? $row['work_type'] : 'Cooking then Packaging',
            'Work area status' => chatbotIsProductionRole($role) ? $row['work_status'] : ($row['product_status'] ?: 'Pending'),
            'Quantity' => $row['quantity'],
            'Target PCS' => $row['target_pcs'],
            'Stock number' => $row['Stock_number'],
            'Due date' => $row['due_date'],
            'Next step' => strtolower((string) ($row['product_status'] ?? '')) === 'product done'
                ? 'Ready for delivery assignment'
                : (($row['packaging_status'] !== null && ($row['cooking_status'] ?? '') !== 'Done')
                    ? 'Packaging is blocked until Cooking is marked done'
                    : 'Complete the current production stage; Packaging follows completed Cooking'),
        ];
        if (in_array($role, ['admin', 'super'], true)) {
            $fields['Stage assignments'] = $row['assignment_summary'];
        }
        return chatbotFormatDetails('Production #' . $row['production_id'], $fields);
    }

    if ($recordType === 'delivery') {
        if (!in_array($role, ['admin', 'super', 'log'], true)) {
            return 'Hindi available ang delivery data sa role mo.';
        }
        if ($role === 'log' && $userId === null) {
            return 'Hindi ma-verify ang naka-login na delivery account.';
        }

        $sql = "SELECT d.delivery_id, d.route, d.pieces, d.stock, d.delivery_date,
                       d.status, d.completion_review_status, p.product_name, u.name AS driver_name
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
            'Next step' => strtolower((string) ($row['status'] ?? '')) === 'delivered'
                ? 'Delivery is completed'
                : (($row['completion_review_status'] ?? '') === 'pending_review'
                    ? 'Waiting for supervisor review'
                    : 'Driver submits the delivery; supervisor reviews it when required'),
        ];
        if (in_array($role, ['admin', 'super'], true)) {
            $fields['Driver'] = $row['driver_name'];
        }
        return chatbotFormatDetails('Delivery #' . $row['delivery_id'], $fields);
    }

    if ($recordType === 'employee') {
        if (!in_array($role, ['admin', 'super', 'HR'], true)) {
            return 'Hindi available ang employee details sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT employee_id, username, position, contact_number, email, address
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
            'Position' => $row['position'],
            'Contact number' => $row['contact_number'],
            'Email' => $row['email'],
            'Address' => $row['address'],
        ]);
    }

    if ($recordType === 'applicant') {
        if (!in_array($role, ['admin', 'super', 'HR'], true)) {
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
        if (!in_array($role, ['admin', 'super', 'payroll'], true)) {
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
        if (!in_array($role, ['admin', 'super', 'HR', 'payroll'], true)) {
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

    if ($recordType === 'office' || $recordType === 'office task') {
        if (!in_array($role, ['admin', 'super', 'employee'], true)) {
            return 'Hindi available ang office task details sa role mo.';
        }
        $sql = "SELECT o.office_id, o.title, o.content, o.status, o.progress, o.due_date,
                       o.completion_review_status, o.completion_review_note, e.username AS employee_name
                FROM office o
                LEFT JOIN employee e ON e.employee_id = o.employee_id
                WHERE o.office_id = ?";
        $types = 'i';
        $params = [(int) $recordId];
        if ($role === 'employee') {
            if ($employeeId === null) {
                return 'Hindi makita ang employee profile na naka-link sa account mo.';
            }
            $sql .= ' AND o.employee_id = ?';
            $types .= 'i';
            $params[] = $employeeId;
        }
        $row = chatbotScalar($conn, $sql . ' LIMIT 1', $types, $params);
        if (!$row) {
            return 'Walang office task na nakita para sa ID na iyon o wala kang access dito.';
        }
        return chatbotFormatDetails('Office task #' . $row['office_id'], [
            'Title' => $row['title'],
            'Assigned employee' => $row['employee_name'],
            'Status' => $row['completion_review_status'] === 'pending_review'
                ? 'Awaiting review'
                : ($row['completion_review_status'] === 'rejected' ? 'Changes requested' : $row['status']),
            'Progress' => (int) $row['progress'] . '%',
            'Due date' => $row['due_date'],
            'Next step' => in_array(strtolower((string) $row['status']), ['completed', 'complete', 'done'], true)
                ? 'Task is complete'
                : ($row['completion_review_status'] === 'pending_review' ? 'Wait for supervisor review' : 'Continue the task and update its progress'),
            'Instructions' => $row['content'],
            'Review note' => $row['completion_review_note'],
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
        'office tasks' => 'office',
        'office task' => 'office',
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
        'production' => ['admin', 'super', 'pro', 'cooking', 'packaging'],
        'delivery' => ['admin', 'super', 'log'],
        'office' => ['admin', 'super', 'employee'],
        'employee' => ['admin', 'super', 'HR'],
        'applicant' => ['admin', 'super', 'HR'],
        'payroll' => ['admin', 'super', 'payroll'],
        'attendance' => ['admin', 'super', 'HR', 'payroll'],
    ];
    if (!isset($allowed[$recordType]) || !in_array($role, $allowed[$recordType], true)) {
        return 'Hindi available ang record type na iyan sa role mo.';
    }
    if ($recordType === 'production' && chatbotIsProductionRole($role) && $employeeId === null) {
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
                           COALESCE(NULLIF(pa.work_status, ''), NULLIF(p.product_status, ''), 'Pending') AS status,
                           p.due_date AS date_value, p.quantity, pa.work_type
                    FROM production p
                    LEFT JOIN production_assignments pa ON pa.production_id = CAST(p.production_id AS CHAR)";
            if (chatbotIsProductionRole($role)) {
                $sql .= ' WHERE pa.employee_id = ?';
                $types .= 'i';
                $params[] = $employeeId;
                if ($work_type = chatbotProductionWorkType($role)) {
                    $sql .= ' AND pa.work_type = ?';
                    $types .= 's';
                    $params[] = $work_type;
                }
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

        case 'office':
            $sql = "SELECT o.office_id AS record_id,
                           COALESCE(NULLIF(o.title, ''), o.data_type, 'Task') AS item,
                           CASE
                               WHEN o.completion_review_status = 'pending_review' THEN 'Awaiting review'
                               WHEN o.completion_review_status = 'rejected' THEN 'Changes requested'
                               ELSE COALESCE(NULLIF(o.status, ''), 'Pending')
                           END AS status,
                           o.due_date AS date_value,
                           e.username AS detail_one
                    FROM office o
                    LEFT JOIN employee e ON e.employee_id = o.employee_id";
            if ($role === 'employee') {
                if ($employeeId === null) {
                    return 'Hindi makita ang employee profile na naka-link sa account mo.';
                }
                $sql .= ' WHERE o.employee_id = ?';
                $types = 'i';
                $params = [$employeeId];
                if ($search !== '') {
                    $sql .= ' AND (o.title LIKE ? OR o.content LIKE ? OR o.status LIKE ?)';
                    $types .= 'sss';
                    array_push($params, $searchTerm, $searchTerm, $searchTerm);
                }
            } elseif ($search !== '') {
                $sql .= ' WHERE o.title LIKE ? OR o.content LIKE ? OR o.status LIKE ? OR e.username LIKE ?';
                $types = 'ssss';
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            $sql .= ' ORDER BY o.due_date IS NULL ASC, o.due_date ASC, o.office_id DESC';
            $title = 'Office task';
            break;

        case 'employee':
            $sql = "SELECT employee_id AS record_id, username AS item,
                           position AS detail_two
                    FROM employee";
            if ($search !== '') {
                $sql .= ' WHERE username LIKE ? OR position LIKE ?';
                $types = 'ss';
                $params = [$searchTerm, $searchTerm];
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
    if (chatbotIsProductionRole($role)) {
        if ($employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }
        $workTypeFilter = chatbotProductionWorkType($role);
        $rows = chatbotFetchRows(
            $conn,
            "SELECT p.production_id, p.product_name, p.product_status, p.due_date, pa.work_type, pa.work_status
             FROM production_assignments pa
             INNER JOIN production p ON p.production_id = pa.production_id
             WHERE pa.employee_id = ?" . ($workTypeFilter === null ? '' : " AND pa.work_type = ?") . "
             ORDER BY p.production_id DESC
             LIMIT 5",
            $workTypeFilter === null ? 'i' : 'is',
            $workTypeFilter === null ? [$employeeId] : [$employeeId, $workTypeFilter]
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
            $row['work_status'] ?: ($row['product_status'] ?: 'Pending'),
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
    $reply = 'Employee records: ' . (int) ($totals['total'] ?? 0);
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

function chatbotOfficeTasks(mysqli $conn, ?int $employeeId, bool $summaryOnly = false): string
{
    if ($employeeId === null) {
        return 'Hindi makita ang employee profile na naka-link sa account mo. Ipa-check ito sa HR.';
    }

    if ($summaryOnly) {
        $row = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('completed', 'complete', 'done') THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN completion_review_status = 'pending_review' THEN 1 ELSE 0 END) AS awaiting_review
             FROM office
             WHERE employee_id = ?",
            'i',
            [$employeeId]
        );
        return sprintf(
            "Office task overview mo:\nAssigned tasks: %d\nCompleted: %d\nAwaiting review: %d\nIn progress: %d",
            (int) ($row['total'] ?? 0),
            (int) ($row['completed'] ?? 0),
            (int) ($row['awaiting_review'] ?? 0),
            max(0, (int) ($row['total'] ?? 0) - (int) ($row['completed'] ?? 0))
        );
    }

    $rows = chatbotFetchRows(
        $conn,
        "SELECT office_id, COALESCE(NULLIF(title, ''), data_type, 'Task') AS item,
                CASE
                    WHEN completion_review_status = 'pending_review' THEN 'Awaiting review'
                    WHEN completion_review_status = 'rejected' THEN 'Changes requested'
                    ELSE COALESCE(NULLIF(status, ''), 'Pending')
                END AS status,
                due_date, progress
         FROM office
         WHERE employee_id = ?
         ORDER BY due_date IS NULL ASC, due_date ASC, office_id DESC
         LIMIT 10",
        'i',
        [$employeeId]
    );
    if (!$rows) {
        return 'Wala kang assigned office task sa ngayon.';
    }

    $reply = "Mga assigned task mo:\n";
    foreach ($rows as $row) {
        $reply .= sprintf(
            "#%d %s | %s | %d%% progress | Due: %s\n",
            (int) $row['office_id'],
            $row['item'],
            $row['status'],
            (int) $row['progress'],
            $row['due_date'] ?: 'N/A'
        );
    }
    return trim($reply);
}

function chatbotOverview(mysqli $conn, string $role, ?int $employeeId, ?int $userId): string
{
    if (in_array($role, ['admin', 'super'], true)) {
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
    if (chatbotIsProductionRole($role)) {
        if ($employeeId === null) {
            return 'Hindi makita ang employee record na naka-link sa production account mo.';
        }
        $workTypeFilter = chatbotProductionWorkType($role);
        $row = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                   SUM(CASE WHEN pa.work_status = 'Done' THEN 1 ELSE 0 END) AS completed
             FROM production_assignments pa
             INNER JOIN production p ON p.production_id = pa.production_id
             WHERE pa.employee_id = ?" . ($workTypeFilter === null ? '' : " AND pa.work_type = ?"),
            $workTypeFilter === null ? 'i' : 'is',
            $workTypeFilter === null ? [$employeeId] : [$employeeId, $workTypeFilter]
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
    if ($role === 'employee') {
        return chatbotOfficeTasks($conn, $employeeId, true);
    }
    return chatbotPayrollSummary($conn);
}

function chatbotActionItems(mysqli $conn, string $role, ?int $employeeId, ?int $userId): string
{
    $items = [];
    $addRows = static function (array $rows, string $label) use (&$items): void {
        foreach ($rows as $row) {
            $items[] = $label . ': ' . $row['item'] . ' (Due: ' . ($row['due_date'] ?: 'N/A') . ')';
        }
    };

    if (in_array($role, ['admin', 'super'], true)) {
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Production #', p.production_id, ' ', p.product_name) AS item, p.due_date
             FROM production p
             WHERE LOWER(TRIM(COALESCE(p.product_status, ''))) <> 'product done'
               AND p.due_date < CURDATE()
             ORDER BY p.due_date ASC LIMIT 3"
        ), 'Overdue');
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Production #', p.production_id, ' ', p.product_name) AS item, p.due_date
             FROM production p
             WHERE LOWER(TRIM(COALESCE(p.product_status, ''))) <> 'product done'
               AND p.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY p.due_date ASC LIMIT 3"
        ), 'Due soon');
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Delivery #', d.delivery_id, ' ', COALESCE(NULLIF(p.product_name, ''), d.route)) AS item,
                    d.delivery_date AS due_date
             FROM delivery d LEFT JOIN production p ON p.production_id = d.production_id
             WHERE COALESCE(d.status, 'Pending') <> 'Delivered'
               AND d.delivery_date < CURDATE()
             ORDER BY d.delivery_date ASC LIMIT 3"
        ), 'Overdue');
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Delivery #', d.delivery_id, ' ', COALESCE(NULLIF(p.product_name, ''), d.route)) AS item,
                    d.delivery_date AS due_date
             FROM delivery d LEFT JOIN production p ON p.production_id = d.production_id
             WHERE COALESCE(d.status, 'Pending') <> 'Delivered'
               AND d.delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY d.delivery_date ASC LIMIT 3"
        ), 'Due soon');
        $reviewCounts = chatbotScalar(
            $conn,
            "SELECT
                (SELECT COUNT(*) FROM production_assignments WHERE completion_review_status = 'pending_review') AS production_reviews,
                (SELECT COUNT(*) FROM delivery WHERE completion_review_status = 'pending_review') AS delivery_reviews,
                (SELECT COUNT(*) FROM office WHERE completion_review_status = 'pending_review') AS office_reviews"
        );
        foreach ([
            'Production approvals' => (int) ($reviewCounts['production_reviews'] ?? 0),
            'Delivery approvals' => (int) ($reviewCounts['delivery_reviews'] ?? 0),
            'Office task approvals' => (int) ($reviewCounts['office_reviews'] ?? 0),
        ] as $label => $count) {
            if ($count > 0) {
                $items[] = $label . ' waiting: ' . $count;
            }
        }
    } elseif (chatbotIsProductionRole($role)) {
        if ($employeeId === null) {
            return 'Hindi makita ang employee profile na naka-link sa production account mo. Ipa-check ito sa supervisor.';
        }
        $workType = chatbotProductionWorkType($role);
        $sql = "SELECT CONCAT('Production #', p.production_id, ' ', p.product_name, ' — ', pa.work_type) AS item,
                       p.due_date
                FROM production_assignments pa
                INNER JOIN production p ON p.production_id = pa.production_id
                WHERE pa.employee_id = ? AND pa.work_status <> 'Done'
                  AND LOWER(TRIM(COALESCE(p.product_status, ''))) <> 'product done'
                  AND (p.due_date < CURDATE() OR p.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))";
        $types = 'i';
        $params = [$employeeId];
        if ($workType !== null) {
            $sql .= ' AND pa.work_type = ?';
            $types .= 's';
            $params[] = $workType;
        }
        $sql .= ' ORDER BY p.due_date ASC LIMIT 5';
        $addRows(chatbotFetchRows($conn, $sql, $types, $params), 'Due soon / overdue');
    } elseif ($role === 'log') {
        if ($userId === null) {
            return 'Hindi ma-verify ang naka-login na delivery account.';
        }
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Delivery #', delivery_id, ' to ', route) AS item, delivery_date AS due_date
             FROM delivery
             WHERE driver_id = ? AND COALESCE(status, 'Pending') <> 'Delivered'
               AND delivery_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY delivery_date ASC LIMIT 5",
            'i',
            [$userId]
        ), 'Due soon / overdue');
    } elseif ($role === 'employee' && $employeeId === null) {
        return 'Hindi makita ang employee profile na naka-link sa account mo. Ipa-check ito sa HR.';
    } elseif ($role === 'employee') {
        $addRows(chatbotFetchRows(
            $conn,
            "SELECT CONCAT('Office task #', office_id, ' ', COALESCE(NULLIF(title, ''), 'Task')) AS item,
                    due_date
             FROM office
             WHERE employee_id = ?
               AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
               AND (due_date < CURDATE() OR due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                    OR completion_review_status = 'rejected')
             ORDER BY due_date ASC LIMIT 5",
            'i',
            [$employeeId]
        ), 'Needs attention');
    } elseif ($role === 'payroll') {
        $pending = (int) (chatbotScalar($conn, "SELECT COUNT(*) AS total FROM payroll WHERE status = 'Pending'")['total'] ?? 0);
        if ($pending > 0) {
            $items[] = 'Payroll records pending: ' . $pending;
        }
    } elseif ($role === 'HR') {
        $applicants = (int) (chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total FROM applicant WHERE LOWER(TRIM(COALESCE(status, ''))) NOT IN ('hired', 'rejected', 'failed')"
        )['total'] ?? 0);
        if ($applicants > 0) {
            $items[] = 'Applicants still in process: ' . $applicants;
        }
        $officeReviews = (int) (chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total FROM office WHERE completion_review_status = 'pending_review'"
        )['total'] ?? 0);
        if ($officeReviews > 0) {
            $items[] = 'Office task submissions waiting for review: ' . $officeReviews;
        }
    }

    return $items
        ? "Mga kailangan mong asikasuhin:\n- " . implode("\n- ", $items)
        : 'Walang nakitang overdue, due-soon, o naghihintay ng aksiyon sa mga record na pinapayagan ng role mo.';
}

function chatbotResolveDateRange(string $message): ?array
{
    $today = new DateTimeImmutable('today');
    if (preg_match('/\b(?:from|between)\s+(\d{4}-\d{2}-\d{2})\s+(?:to|and|until)\s+(\d{4}-\d{2}-\d{2})\b/u', $message, $match)) {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $match[2]);
        if (!$start || !$end || $start->format('Y-m-d') !== $match[1] || $end->format('Y-m-d') !== $match[2] || $start > $end) {
            return ['error' => 'Hindi valid ang date range. Gamitin ang YYYY-MM-DD at tiyaking hindi nauuna ang simula sa katapusan.'];
        }
        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'label' => $start->format('M j, Y') . '–' . $end->format('M j, Y')];
    }
    if (preg_match('/\b(\d{4})-(\d{2})\b/u', $message, $match)) {
        $month = DateTimeImmutable::createFromFormat('!Y-m', $match[0]);
        if (!$month || $month->format('Y-m') !== $match[0]) {
            return ['error' => 'Hindi valid ang month. Gamitin ang YYYY-MM.'];
        }
        return [
            'start' => $month->format('Y-m-01'),
            'end' => $month->modify('last day of this month')->format('Y-m-d'),
            'label' => $month->format('F Y'),
        ];
    }
    if (preg_match('/\b(today|ngayong araw)\b/u', $message)) {
        return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'today'];
    }
    if (preg_match('/\b(yesterday|kahapon)\b/u', $message)) {
        $day = $today->modify('-1 day');
        return ['start' => $day->format('Y-m-d'), 'end' => $day->format('Y-m-d'), 'label' => 'yesterday'];
    }
    if (preg_match('/\b(last 7 days|past 7 days|nakaraang 7 araw)\b/u', $message)) {
        return ['start' => $today->modify('-6 days')->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'the last 7 days'];
    }
    if (preg_match('/\b(last week|nakaraang linggo)\b/u', $message)) {
        $start = $today->modify('monday last week');
        return ['start' => $start->format('Y-m-d'), 'end' => $start->modify('+6 days')->format('Y-m-d'), 'label' => 'last week'];
    }
    if (preg_match('/\b(this week|ngayong linggo)\b/u', $message)) {
        $start = $today->modify('monday this week');
        return ['start' => $start->format('Y-m-d'), 'end' => $start->modify('+6 days')->format('Y-m-d'), 'label' => 'this week'];
    }
    if (preg_match('/\b(last month|nakaraang buwan)\b/u', $message)) {
        $month = $today->modify('first day of last month');
        return ['start' => $month->format('Y-m-d'), 'end' => $month->modify('last day of this month')->format('Y-m-d'), 'label' => $month->format('F Y')];
    }
    if (preg_match('/\b(this month|ngayong buwan)\b/u', $message)) {
        $month = $today->modify('first day of this month');
        return ['start' => $month->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'label' => 'this month'];
    }
    if (preg_match('/\b(this year|ngayong taon)\b/u', $message)) {
        return ['start' => $today->format('Y-01-01'), 'end' => $today->format('Y-m-d'), 'label' => 'this year'];
    }
    return null;
}

function chatbotDateReport(mysqli $conn, string $message, string $role, ?int $employeeId, ?int $userId): ?string
{
    $hasDate = chatbotResolveDateRange($message) !== null;
    $isReportQuestion = preg_match('/\b(report|how many|count|ilan)\b/u', $message) === 1;
    $hasReportType = preg_match('/\b(attendance|production|delivery|deliveries|shipment|shipments|payroll|salary|payslip|office task|tasks?)\b/u', $message) === 1;
    if (!$hasReportType || (!$isReportQuestion && !$hasDate)) {
        return null;
    }
    $range = chatbotResolveDateRange($message);
    if ($range === null) {
        return "Anong period ang gusto mong i-report? Halimbawa: “How many deliveries this week?” o “attendance report 2026-10”. Maaari ka ring magbigay ng date range na “from 2026-10-01 to 2026-10-16”.";
    }
    if (isset($range['error'])) {
        return $range['error'];
    }
    $recordType = match (true) {
        preg_match('/\b(attendance|present|absent|late)\b/u', $message) === 1 => 'attendance',
        preg_match('/\b(delivery|deliveries|shipment|shipments)\b/u', $message) === 1 => 'delivery',
        preg_match('/\b(production)\b/u', $message) === 1 => 'production',
        preg_match('/\b(payroll|salary|payslip)\b/u', $message) === 1 => 'payroll',
        preg_match('/\b(office task|my tasks?|tasks?)\b/u', $message) === 1 => 'office',
        default => null,
    };
    if ($recordType === null) {
        return "Anong data ang gusto mong i-report? Pumili ng production, delivery, attendance, payroll, o office tasks at magbigay ng period gaya ng “this week” o “2026-10”.";
    }

    $start = $range['start'];
    $end = $range['end'];
    $types = 'ss';
    $params = [$start, $end];
    if ($recordType === 'delivery') {
        if (!in_array($role, ['admin', 'super', 'log'], true)) {
            return 'Hindi available ang delivery reports sa role mo.';
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN COALESCE(status, 'Pending') <> 'Delivered'
                                     AND delivery_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
                FROM delivery WHERE delivery_date BETWEEN ? AND ?";
        if ($role === 'log') {
            if ($userId === null) {
                return 'Hindi ma-verify ang naka-login na delivery account.';
            }
            $sql .= ' AND driver_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }
        $row = chatbotScalar($conn, $sql, $types, $params);
        return sprintf('Delivery report (%s): %d total, %d delivered, %d overdue.', $range['label'], (int) ($row['total'] ?? 0), (int) ($row['completed'] ?? 0), (int) ($row['overdue'] ?? 0));
    }
    if ($recordType === 'production') {
        if (!in_array($role, ['admin', 'super', 'pro', 'cooking', 'packaging'], true)) {
            return 'Hindi available ang production reports sa role mo.';
        }
        $sql = "SELECT COUNT(DISTINCT p.production_id) AS total,
                       SUM(CASE WHEN LOWER(TRIM(COALESCE(p.product_status, ''))) = 'product done' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN LOWER(TRIM(COALESCE(p.product_status, ''))) <> 'product done'
                                     AND p.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
                FROM production p";
        if (chatbotIsProductionRole($role)) {
            if ($employeeId === null) {
                return 'Hindi makita ang employee profile na naka-link sa production account mo.';
            }
            $sql .= ' INNER JOIN production_assignments pa ON pa.production_id = p.production_id AND pa.employee_id = ?';
            $types = 'i' . $types;
            array_unshift($params, $employeeId);
            if ($workType = chatbotProductionWorkType($role)) {
                $sql .= ' AND pa.work_type = ?';
                $types .= 's';
                array_splice($params, 1, 0, [$workType]);
            }
        }
        $sql .= ' WHERE p.due_date BETWEEN ? AND ?';
        $row = chatbotScalar($conn, $sql, $types, $params);
        return sprintf('Production report (%s): %d products due, %d completed, %d overdue.', $range['label'], (int) ($row['total'] ?? 0), (int) ($row['completed'] ?? 0), (int) ($row['overdue'] ?? 0));
    }
    if ($recordType === 'attendance') {
        if (!in_array($role, ['admin', 'super', 'HR', 'payroll'], true)) {
            return 'Hindi available ang attendance reports sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN LOWER(TRIM(status)) IN (\'present\', \'early\', \'late\', \'overtime\', \'undertime\', \'half day\') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN LOWER(TRIM(status)) = \'absent\' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN LOWER(TRIM(status)) = \'late\' THEN 1 ELSE 0 END) AS late
             FROM attendance WHERE attendance_date BETWEEN ? AND ?',
            'ss',
            [$start, $end]
        );
        return sprintf('Attendance report (%s): %d records, %d present/working, %d absent, %d late.', $range['label'], (int) ($row['total'] ?? 0), (int) ($row['present'] ?? 0), (int) ($row['absent'] ?? 0), (int) ($row['late'] ?? 0));
    }
    if ($recordType === 'payroll') {
        if (!in_array($role, ['admin', 'super', 'payroll'], true)) {
            return 'Hindi available ang payroll reports sa role mo.';
        }
        $row = chatbotScalar(
            $conn,
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'Paid' THEN total_credited ELSE 0 END) AS paid_total
             FROM payroll WHERE pay_date BETWEEN ? AND ?",
            'ss',
            [$start, $end]
        );
        return sprintf('Payroll report (%s): %d records, %d paid, %d pending, paid total ₱%s.', $range['label'], (int) ($row['total'] ?? 0), (int) ($row['paid'] ?? 0), (int) ($row['pending'] ?? 0), number_format((float) ($row['paid_total'] ?? 0), 2));
    }
    if ($recordType === 'office') {
        if (!in_array($role, ['admin', 'super', 'employee'], true)) {
            return 'Hindi available ang office-task reports sa role mo.';
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('completed', 'complete', 'done') THEN 1 ELSE 0 END) AS completed
                FROM office WHERE due_date BETWEEN ? AND ?";
        $officeTypes = 'ss';
        $officeParams = [$start, $end];
        if ($role === 'employee') {
            if ($employeeId === null) {
                return 'Hindi makita ang employee profile na naka-link sa account mo.';
            }
            $sql .= ' AND employee_id = ?';
            $officeTypes .= 'i';
            $officeParams[] = $employeeId;
        }
        $row = chatbotScalar(
            $conn,
            $sql,
            $officeTypes,
            $officeParams
        );
        return sprintf('Your office-task report (%s): %d tasks due, %d completed, %d still open.', $range['label'], (int) ($row['total'] ?? 0), (int) ($row['completed'] ?? 0), max(0, (int) ($row['total'] ?? 0) - (int) ($row['completed'] ?? 0)));
    }
    return null;
}

function chatbotProcessHelp(string $message, string $role): ?string
{
    if (!preg_match('/\b(how to|how do i|paano|steps for|help with)\b/u', $message)) {
        return null;
    }
    if (preg_match('/\b(delivery|proof|photo)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'log'], true)) {
            return "Hindi available ang delivery workflow sa role mo.\n1. Buksan ang Logistics o ang task page na naka-assign sa iyo.\n2. Kung wala kang access, makipag-ugnayan sa supervisor.";
        }
        if (preg_match('/\b(proof|photo|picture|camera)\b/u', $message)) {
            if ($role === 'super') {
                return "Pag-review ng delivery proof:\n1. Buksan ang Logistics.\n2. Hanapin ang delivery na naghihintay ng review at piliin ang Review submission.\n3. Suriin ang proof photo at delivery details.\n4. Piliin ang Approve o Request changes; maglagay ng dahilan kapag humihingi ng pagbabago.\n5. Isumite ang review.";
            }
            if ($role === 'admin') {
                return "Pagtingin ng delivery proof:\n1. Buksan ang Logistics.\n2. Hanapin ang delivery record at piliin ang Review submission kung available.\n3. Suriin ang larawan at status.\n4. Para sa approval o request for changes, ipasa ang record sa supervisor.";
            }
            return "Pagsumite ng delivery proof:\n1. Buksan ang Logistics at piliin ang delivery na naka-assign sa iyo.\n2. Piliin ang Take a photo para kumuha ng larawan o Choose from gallery para pumili ng larawan.\n3. I-check ang preview; JPG, PNG, o WebP na hanggang 5 MB ang tinatanggap.\n4. Piliin ang submit action at kumpirmahin ang pagpapadala.\n5. Hintayin ang supervisor review. Kung may pagbabago, basahin ang feedback at isumite ulit ang proof.";
        }
        if ($role === 'super') {
            return "Pag-assign o pag-update ng delivery:\n1. Tiyaking kumpleto na ang production bago gumawa ng delivery assignment.\n2. I-type ang command na may production ID, driver ID, route, at petsa, halimbawa: assign delivery production 123 to driver 9 route \"Manila\" due 2026-10-20.\n3. Suriin ang preview at ang driver, route, at delivery date.\n4. Piliin ang Confirm and save para ituloy, o Cancel para walang baguhin.\n5. Para mag-update ng route o petsa, gamitin: update delivery 12 route \"Quezon City\" due 2026-10-21.";
        }
        return $role === 'log'
            ? "Pagtingin sa delivery assignment:\n1. Buksan ang Logistics.\n2. Hanapin ang deliveries na naka-assign sa iyo.\n3. Piliin ang delivery para makita ang route, due date, at status.\n4. Pagkatapos maihatid, itanong ang “How to submit delivery proof?” para sa susunod na hakbang."
            : "Pagtingin ng delivery status:\n1. Buksan ang Logistics.\n2. Hanapin ang delivery record at tingnan ang status at due date.\n3. Ang pag-assign o pag-update ng delivery sa chatbot ay supervisor-only.";
    }
    if (preg_match('/\b(leave|vacation|day off)\b/u', $message)) {
        if ($role === 'HR') {
            return "Pag-record ng employee leave:\n1. Buksan ang Leave Management.\n2. Piliin ang employee at leave type: paid SIL o unpaid LWOP.\n3. Itakda ang start date at end date.\n4. Ilagay ang dahilan at tiyaking tama ang mga detalye.\n5. Piliin ang Record Leave.\n6. Suriin ang active leave records. Weekdays lang ang nire-record; may dagdag na eligibility rules ang SIL.";
        }
        if (in_array($role, ['employee', 'pro', 'cooking', 'packaging', 'log'], true)) {
            return "Pag-asikaso ng leave:\n1. Makipag-ugnayan sa HR o sa supervisor para i-report ang mga petsa at dahilan.\n2. Ibigay ang anumang dokumentong kailangan ng inyong workplace.\n3. Hintaying maitala at makumpirma ng HR ang leave.\n4. Hindi nagsusumite o nag-aapruba ng leave ang chatbot.";
        }
        return "Pagtingin o pag-record ng leave:\n1. HR ang namamahala ng employee leave records sa Leave Management.\n2. Makipag-ugnayan sa HR para sa request o pagbabago.\n3. Hindi nagsusumite o nag-aapruba ng leave ang chatbot.";
    }
    if (preg_match('/\b(review|approve|approval)\b/u', $message)) {
        if ($role === 'super') {
            return "Pag-review ng task submission:\n1. Buksan ang Production, Logistics, o Employees ayon sa uri ng task.\n2. Hanapin ang task na Awaiting Approval o naghihintay ng review.\n3. Buksan ang submission at tingnan ang detalye o proof.\n4. Piliin ang Approve kung kumpleto, o Request changes kung may kailangang ayusin.\n5. Maglagay ng malinaw na paliwanag kapag humihingi ng pagbabago at isumite ang desisyon.";
        }
        if ($role === 'HR') {
            return "Pag-review ng office task:\n1. Buksan ang Employees.\n2. Piliin ang employee at hanapin ang task na Awaiting Approval.\n3. Piliin ang View para basahin ang task at submission.\n4. Piliin ang Approve o Request changes.\n5. Maglagay ng paliwanag kung humihingi ng pagbabago at isumite ang review. Hindi kasama sa HR review ang production o delivery approvals.";
        }
        if (chatbotIsProductionRole($role) || $role === 'log' || $role === 'employee') {
            return "Pag-check ng task review:\n1. Buksan ang task mo sa Production, Logistics, o My Tasks.\n2. Tingnan kung Awaiting Approval, Approved, o Changes requested ang status.\n3. Kung may feedback o Changes requested, basahin ito at ayusin ang submission.\n4. Isumite ulit ang task para sa review. Ikaw ay hindi maaaring mag-apruba ng sarili mong submission.";
        }
        return "Pag-review ng task:\n1. Ang production at delivery submission ay sinusuri ng supervisor.\n2. Ang office task ay sinusuri ng HR o supervisor.\n3. Ang admin at payroll role ay hindi reviewer sa mga workflow na ito.";
    }
    if (preg_match('/\b(payroll|payslip|salary)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'payroll', 'employee'], true)) {
            return "Hindi available ang payroll information sa role mo.\n1. Makipag-ugnayan sa payroll staff kung kailangan mo ng payroll assistance.";
        }
        if ($role === 'employee') {
            return "Payroll assistance:\n1. Para sa sariling payslip o pay question, makipag-ugnayan sa payroll staff o HR.\n2. Ibigay ang pay period na tinutukoy mo.\n3. Huwag ipadala sa chatbot ang password o sensitibong detalye ng bank account.";
        }
        if ($role === 'payroll') {
            return "Pag-check at pagproseso ng payroll:\n1. Buksan ang Payroll.\n2. Hanapin ang employee at piliin ang tamang pay period.\n3. Suriin ang attendance, earnings, deductions, at net credited amount.\n4. Tiyaking tama ang record bago markahan o i-update ang payroll status.\n5. I-save ang pagbabago sa payroll page.";
        }
        return "Pagtingin ng payroll information:\n1. Buksan ang Payroll o gamitin ang chatbot payroll report para sa napiling period.\n2. Hanapin ang record gamit ang payroll ID o pay period.\n3. Para sa pagwawasto o pagbabago ng status, makipag-ugnayan sa payroll staff.";
    }
    if (preg_match('/\b(production|cooking|packaging)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'pro', 'cooking', 'packaging'], true)) {
            return "Hindi available ang production workflow sa role mo.\n1. Tingnan ang help para sa mga task na pinapayagan ng role mo.\n2. Kung kailangan mo ng production access, makipag-ugnayan sa supervisor.";
        }
        if (chatbotIsProductionRole($role)) {
            $workType = chatbotProductionWorkType($role);
            $stage = $workType === null ? 'assigned stage mo' : $workType;
            $sequence = $workType === 'Packaging'
                ? "4. Kapag kumpleto na ang Cooking at aktibo na ang Packaging task mo, simulan ang Packaging."
                : "4. Kung ikaw ang naka-assign sa Cooking, tapusin muna ang Cooking bago makapagsimula ang Packaging.";
            return "Pagkumpleto ng production task mo:\n1. Buksan ang Production at piliin ang task na naka-assign sa iyo.\n2. Suriin ang product details at due date.\n3. Kumpletuhin ang {$stage} stage at tiyaking tama ang output.\n{$sequence}\n5. Piliin ang Submit for supervisor review at kumpirmahin.\n6. Tingnan ang status; kung may changes requested, basahin ang feedback, ayusin ang task, at isumite ulit.";
        }
        if ($role === 'super') {
            return "Pag-manage ng production tasks:\n1. Buksan ang Production para tingnan ang task, stage, assignee, at due date.\n2. Tiyaking Cooking ay kumpleto bago mag-assign ng Packaging.\n3. Para mag-assign sa chatbot, ilagay ang production ID, stage, at employee ID, halimbawa: assign production 123 Cooking to employee 45.\n4. Suriin ang preview at piliin ang Confirm and save, o Cancel.\n5. Para mag-review ng natapos na stage, buksan ang submission na naghihintay ng approval.";
        }
        return "Pagtingin ng production status:\n1. Buksan ang Production.\n2. Hanapin ang production record at tingnan ang status at due date.\n3. Para sa assignment o approval, makipag-ugnayan sa supervisor.";
    }
    if (preg_match('/\b(office task|task)\b/u', $message)) {
        if ($role === 'employee') {
            return "Pag-update ng office task mo:\n1. Buksan ang My Tasks at hanapin ang task.\n2. Piliin ang task para mabasa ang instructions, due date, at feedback.\n3. I-adjust ang progress at piliin ang Save progress.\n4. Kapag tapos na, piliin ang Submit for review.\n5. Tingnan ang status. Kung Changes requested ang status, basahin ang supervisor feedback, ayusin ang task, at piliin ang Resubmit for review.";
        }
        if (in_array($role, ['super', 'HR'], true)) {
            return $role === 'super'
                ? "Pag-assign ng office task:\n1. Buksan ang Employees at piliin ang employee account.\n2. Piliin ang create task o buksan ang task na ia-update.\n3. Ilagay ang title, instructions, due date, at iba pang required na detalye.\n4. Sa chatbot, puwede ring gamitin ang assign office task ... o update office task ... command para makita ang preview.\n5. Suriin ang employee at task details; piliin ang Confirm and save para ituloy o Cancel para walang baguhin."
                : "Pag-manage ng office task:\n1. Buksan ang Employees at piliin ang employee.\n2. Hanapin ang task na kailangan mong tingnan o i-review.\n3. Para sa task na Awaiting Approval, piliin ang View at suriin ang submission.\n4. Piliin ang Approve o Request changes at maglagay ng paliwanag kung kailangan.\n5. Ang task edits sa chatbot ay supervisor-only.";
        }
        return "Office tasks:\n1. Hindi available sa role mo ang office tasks ng ibang employee.\n2. Kung may sarili kang assigned task, buksan ang My Tasks.\n3. Para sa assignment o review, makipag-ugnayan sa HR o supervisor.";
    }
    $topics = match ($role) {
        'super' => 'production at delivery assignments, task reviews, office tasks, at company workflows',
        'admin' => 'production, delivery, employee, attendance, at payroll information',
        'log' => 'iyong assigned deliveries at delivery proof',
        'pro', 'cooking', 'packaging' => 'iyong assigned production tasks',
        'HR' => 'employees, applicants, attendance, leave records, at office-task reviews',
        'payroll' => 'payroll at attendance information',
        'employee' => 'iyong office tasks at payslips',
        default => 'mga workflow na pinapayagan ng role mo',
    };
    return "Makakatulong ako sa {$topics}.\n1. Sabihin kung anong proseso ang gusto mong gawin.\n2. Isama ang module o task na tinutukoy mo.\n3. Halimbawa: “How to submit delivery proof?” o “How to review an office task?”.";
}

function chatbotPrepareAction(mysqli $conn, array $action): string
{
    if (($action['type'] ?? '') === 'production_assignment') {
        $production = chatbotScalar(
            $conn,
            "SELECT product_name, product_status FROM production WHERE production_id = ? LIMIT 1",
            's',
            [(string) $action['production_id']]
        );
        $employee = chatbotScalar(
            $conn,
            'SELECT username FROM employee WHERE employee_id = ? LIMIT 1',
            'i',
            [(int) $action['employee_id']]
        );
        if (!$production || !$employee) {
            throw new RuntimeException('Hindi makita ang production task o employee. Walang nabago.');
        }
        return sprintf(
            "Production assignment preview:\nProduction #%s — %s\nStage: %s\nAssigned employee: %s (#%d)\n\nIse-save lang ito kapag pinindot mo ang Confirm and save.",
            $action['production_id'],
            $production['product_name'],
            $action['work_type'],
            $employee['username'],
            $action['employee_id']
        );
    }
    if (($action['type'] ?? '') === 'delivery_assignment') {
        $production = chatbotScalar(
            $conn,
            "SELECT product_name, product_status FROM production WHERE production_id = ? LIMIT 1",
            's',
            [(string) $action['production_id']]
        );
        $driver = chatbotScalar($conn, "SELECT name FROM users WHERE id = ? AND role = 'log' LIMIT 1", 'i', [(int) $action['driver_id']]);
        if (!$production || !$driver) {
            throw new RuntimeException('Hindi makita ang production item o logistics driver. Walang nabago.');
        }
        return sprintf(
            "Delivery assignment preview:\nProduct #%s — %s\nDriver: %s (#%d)\nRoute: %s\nDelivery date: %s\n\nIse-save lang ito kapag pinindot mo ang Confirm and save.",
            $action['production_id'],
            $production['product_name'],
            $driver['name'],
            $action['driver_id'],
            $action['route'],
            $action['delivery_date']
        );
    }
    if (($action['type'] ?? '') === 'office_task_create') {
        ensureTaskNotificationSchema($conn);
        $employee = chatbotScalar($conn, 'SELECT username FROM employee WHERE employee_id = ? LIMIT 1', 'i', [(int) $action['employee_id']]);
        if (!$employee) {
            throw new RuntimeException('Hindi makita ang employee. Walang nabago.');
        }
        return sprintf(
            "Office task preview:\nTitle: %s\nAssigned employee: %s (#%d)\nDescription: %s\nPriority: %s\nDue date: %s\n\nIse-save lang ito kapag pinindot mo ang Confirm and save.",
            $action['title'],
            $employee['username'],
            $action['employee_id'],
            $action['description'],
            $action['priority'],
            $action['due_date']
        );
    }
    if (($action['type'] ?? '') === 'delivery_update') {
        $delivery = chatbotScalar($conn, 'SELECT route, delivery_date FROM delivery WHERE delivery_id = ? LIMIT 1', 'i', [(int) $action['delivery_id']]);
        if (!$delivery) {
            throw new RuntimeException('Hindi makita ang delivery record. Walang nabago.');
        }
        return sprintf(
            "Delivery update preview:\nDelivery #%d\nRoute: %s → %s\nDate: %s → %s\n\nIse-save lang ito kapag pinindot mo ang Confirm and save.",
            $action['delivery_id'],
            $delivery['route'],
            $action['route'],
            $delivery['delivery_date'],
            $action['delivery_date']
        );
    }
    if (($action['type'] ?? '') === 'office_task_update') {
        $task = chatbotScalar(
            $conn,
            'SELECT title FROM office WHERE office_id = ? AND supervisor_id = ? LIMIT 1',
            'ii',
            [(int) $action['office_id'], (int) $_SESSION['id']]
        );
        if (!$task) {
            throw new RuntimeException('Hindi makita ang task na pag-aari ng supervisor session mo. Walang nabago.');
        }
        return sprintf(
            "Office task update preview:\nTask #%d\nTitle: %s → %s\n\nIse-save lang ito kapag pinindot mo ang Confirm and save.",
            $action['office_id'],
            $task['title'],
            $action['title']
        );
    }
    throw new RuntimeException('Hindi suportado ang chatbot action na ito.');
}

function chatbotStageAction(mysqli $conn, array $action): void
{
    foreach (['delivery_date', 'due_date'] as $dateKey) {
        if (isset($action[$dateKey]) && !chatbotDateIsValid((string) $action[$dateKey])) {
            throw new RuntimeException('Hindi valid ang date. Gamitin ang YYYY-MM-DD.');
        }
    }
    foreach (['route', 'title'] as $requiredText) {
        if (isset($action[$requiredText]) && trim((string) $action[$requiredText]) === '') {
            throw new RuntimeException('Hindi puwedeng blanko ang route o task title.');
        }
    }
    $preview = chatbotPrepareAction($conn, $action);
    $token = bin2hex(random_bytes(32));
    $_SESSION['chatbot_pending_action'] = [
        'token' => $token,
        'action' => $action,
        'expires' => time() + 300,
    ];
    chatbotRespond($preview, 200, ['confirmation' => ['token' => $token]]);
}

function chatbotDateIsValid(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function chatbotDriverIsPresentToday(mysqli $conn, int $driverId): bool
{
    $row = chatbotScalar(
        $conn,
        "SELECT u.id
         FROM users u
         WHERE u.id = ? AND u.role = 'log'
           AND EXISTS (
               SELECT 1 FROM attendance a
               LEFT JOIN employee e ON e.employee_id = a.employee_id
               WHERE DATE(a.attendance_date) = CURDATE()
                 AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                 AND (
                     a.user_id = u.id OR e.username = u.name
                     OR (e.email IS NOT NULL AND e.email <> '' AND e.email = u.email)
                     OR EXISTS (
                         SELECT 1 FROM attendance linked
                         WHERE linked.employee_id = e.employee_id AND linked.user_id = u.id
                     )
                 )
           )
         LIMIT 1",
        'i',
        [$driverId]
    );
    return (bool) $row;
}

function chatbotCommitAction(mysqli $conn, array $action, int $actorId, string $actorName): string
{
    ensureLeaveSchema($conn);
    if (($action['type'] ?? '') === 'production_assignment') {
        ensureProductionAssignmentSchema($conn);
        ensureProductionActivitySchema($conn);
        ensureTaskNotificationSchema($conn);
        $productionId = (string) $action['production_id'];
        $employeeId = (int) $action['employee_id'];
        $workType = (string) $action['work_type'];
        if (!in_array($workType, ['Cooking', 'Packaging'], true)) {
            throw new RuntimeException('Pumili ng valid na Production stage.');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Hindi masimulan ang production assignment: ' . $conn->error);
        }
        try {
            $product = chatbotScalar(
                $conn,
                "SELECT product_name, due_date FROM production
                 WHERE production_id = ? AND LOWER(TRIM(COALESCE(product_status, ''))) <> 'product done'
                 LIMIT 1 FOR UPDATE",
                's',
                [$productionId]
            );
            $requiredRole = $workType === 'Cooking' ? 'cooking' : 'packaging';
            $employee = chatbotScalar(
                $conn,
                "SELECT e.username
                 FROM employee e
                 INNER JOIN users u ON u.role = ?
                   AND (u.employee_id = e.employee_id OR u.name = e.username
                        OR (e.email IS NOT NULL AND e.email <> '' AND u.email = e.email)
                        OR EXISTS (SELECT 1 FROM attendance link WHERE link.employee_id = e.employee_id AND link.user_id = u.id))
                 WHERE e.employee_id = ?
                   AND EXISTS (
                       SELECT 1 FROM attendance a
                       WHERE (a.employee_id = e.employee_id OR a.user_id = u.id)
                         AND DATE(a.attendance_date) = CURDATE()
                         AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                   )
                 LIMIT 1",
                'si',
                [$requiredRole, $employeeId]
            );
            if (!$product || !$employee) {
                throw new RuntimeException('Production item must be open, and the assigned employee must be present with the matching Cooking/Packaging role.');
            }
            if (!empty($product['due_date']) && employeeHasLeaveOnDate($conn, $employeeId, (string) $product['due_date'])) {
                throw new RuntimeException('The employee has recorded leave on the production due date.');
            }
            if ($workType === 'Packaging') {
                $cooking = chatbotScalar($conn, "SELECT work_status FROM production_assignments WHERE production_id = ? AND work_type = 'Cooking' LIMIT 1", 's', [$productionId]);
                if (($cooking['work_status'] ?? '') !== 'Done') {
                    throw new RuntimeException('Packaging can only be assigned after Cooking is marked done.');
                }
            }
            $opposite = $workType === 'Cooking' ? 'Packaging' : 'Cooking';
            $otherStage = chatbotScalar($conn, 'SELECT employee_id FROM production_assignments WHERE production_id = ? AND work_type = ? LIMIT 1', 'ss', [$productionId, $opposite]);
            if ((int) ($otherStage['employee_id'] ?? 0) === $employeeId) {
                throw new RuntimeException('Cooking and Packaging must be assigned to different employees.');
            }
            $existing = chatbotScalar($conn, 'SELECT employee_id, work_status, completion_review_status FROM production_assignments WHERE production_id = ? AND work_type = ? LIMIT 1 FOR UPDATE', 'ss', [$productionId, $workType]);
            if (($existing['work_status'] ?? '') === 'Done' || ($existing['completion_review_status'] ?? '') === 'pending_review') {
                throw new RuntimeException('A completed or review-pending stage cannot be reassigned.');
            }
            if ((int) ($existing['employee_id'] ?? 0) === $employeeId) {
                throw new RuntimeException('This stage is already assigned to that employee. Walang nabago.');
            }
            $stmt = $conn->prepare(
                "INSERT INTO production_assignments (production_id, employee_id, work_type, assigned_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), assigned_by = VALUES(assigned_by),
                    assigned_at = CURRENT_TIMESTAMP, completion_review_status = 'none',
                    completion_review_note = NULL, completion_reviewed_by = NULL,
                    completion_reviewed_at = NULL, completed_at = NULL"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare production assignment: ' . $conn->error);
            }
            $stmt->bind_param('sisi', $productionId, $employeeId, $workType, $actorId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Unable to save production assignment: ' . $stmt->error);
            }
            $stmt->close();
            $previousEmployeeId = (int) ($existing['employee_id'] ?? 0);
            if ($previousEmployeeId !== $employeeId) {
                recordProductionActivity(
                    $conn,
                    $productionId,
                    $actorId,
                    $actorName,
                    $existing ? 'stage_reassigned' : 'stage_assigned',
                    $workType,
                    $previousEmployeeId ?: null,
                    $previousEmployeeId ? getProductionEmployeeName($conn, $previousEmployeeId) : null,
                    $employeeId,
                    $employee['username']
                );
                if ($previousEmployeeId) {
                    notifyEmployeeTaskAssignee($conn, $previousEmployeeId, $actorId, 'production_reassigned', 'Production stage reassigned', 'The ' . $workType . ' stage for production item #' . $productionId . ' has been assigned to another employee.', 'Production.php', 'production', $productionId);
                }
                notifyEmployeeTaskAssignee($conn, $employeeId, $actorId, 'production_assigned', 'Production stage assigned', 'The ' . $workType . ' stage for production item #' . $productionId . ' has been assigned to you.', 'Production.php', 'production', $productionId);
            }
            recordSystemAudit($conn, $actorId, $actorName, 'production_stage_assigned', 'production', (int) $productionId, 'Assigned ' . $workType . ' to employee #' . $employeeId . ' for production #' . $productionId . ' through chatbot.');
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit production assignment: ' . $conn->error);
            }
            return 'Na-save ang ' . $workType . ' assignment para sa Production #' . $productionId . ' at na-notify si ' . $employee['username'] . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }

    if (($action['type'] ?? '') === 'delivery_assignment') {
        ensureDeliveryActivitySchema($conn);
        ensureTaskNotificationSchema($conn);
        require_once __DIR__ . '/task_notifications.php';
        $productionId = (string) $action['production_id'];
        $driverId = (int) $action['driver_id'];
        $route = trim((string) $action['route']);
        $deliveryDate = (string) $action['delivery_date'];
        if ($route === '' || mb_strlen($route) > 255 || !chatbotDateIsValid($deliveryDate)) {
            throw new RuntimeException('Route o delivery date ay invalid.');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Hindi masimulan ang delivery assignment: ' . $conn->error);
        }
        try {
            if (!chatbotDriverIsPresentToday($conn, $driverId)) {
                throw new RuntimeException('Ang driver ay kailangang naka-time in at present ngayon.');
            }
            if (userHasLeaveOnDate($conn, $driverId, $deliveryDate)) {
                throw new RuntimeException('May recorded leave ang driver sa delivery date.');
            }
            $product = chatbotScalar(
                $conn,
                "SELECT product_name, quantity, Stock_number FROM production
                 WHERE production_id = ? AND LOWER(TRIM(COALESCE(product_status, ''))) = 'product done'
                 LIMIT 1 FOR UPDATE",
                's',
                [$productionId]
            );
            $existing = chatbotScalar($conn, 'SELECT delivery_id FROM delivery WHERE production_id = ? LIMIT 1', 's', [$productionId]);
            $driver = chatbotScalar($conn, "SELECT name FROM users WHERE id = ? AND role = 'log' LIMIT 1", 'i', [$driverId]);
            if (!$product || $existing || !$driver) {
                throw new RuntimeException('Product must be completed and not already assigned; select an existing Logistics driver.');
            }
            $stmt = $conn->prepare(
                'INSERT INTO delivery (production_id, route, pieces, stock, driver_id, assigned_by, delivery_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare delivery assignment: ' . $conn->error);
            }
            $pieces = (int) $product['quantity'];
            $stock = (int) $product['Stock_number'];
            $stmt->bind_param('ssiiiis', $productionId, $route, $pieces, $stock, $driverId, $actorId, $deliveryDate);
            if (!$stmt->execute()) {
                throw new RuntimeException('Unable to save delivery assignment: ' . $stmt->error);
            }
            $deliveryId = (int) $conn->insert_id;
            $stmt->close();
            recordDeliveryActivity($conn, $deliveryId, $actorId, $actorName, 'delivery_assigned', null, null, $driverId, $driver['name']);
            createTaskNotification($conn, $driverId, $actorId, 'delivery_assigned', 'Delivery assigned to you', 'A delivery to ' . $route . ' is assigned to you for ' . date('M j, Y', strtotime($deliveryDate)) . '.', 'logistic.php', 'delivery', (string) $deliveryId);
            recordSystemAudit($conn, $actorId, $actorName, 'delivery_assigned', 'delivery', $deliveryId, 'Assigned production #' . $productionId . ' to driver #' . $driverId . ' through chatbot.');
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit delivery assignment: ' . $conn->error);
            }
            return 'Na-save ang Delivery #' . $deliveryId . ' at na-notify si ' . $driver['name'] . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }

    if (($action['type'] ?? '') === 'office_task_create') {
        $employeeId = (int) $action['employee_id'];
        $title = trim((string) $action['title']);
        $description = trim((string) $action['description']);
        $dueDate = (string) $action['due_date'];
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($description) > 2000 || !chatbotDateIsValid($dueDate)) {
            throw new RuntimeException('Invalid ang task title, description, o due date.');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Hindi masimulan ang office task assignment: ' . $conn->error);
        }
        try {
            $employee = chatbotScalar($conn, 'SELECT username FROM employee WHERE employee_id = ? LIMIT 1 FOR UPDATE', 'i', [$employeeId]);
            $attendance = chatbotScalar($conn, "SELECT status FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE() ORDER BY attendance_id DESC LIMIT 1", 'i', [$employeeId]);
            $present = in_array(strtolower(trim((string) ($attendance['status'] ?? ''))), ['present', 'early', 'late', 'overtime', 'undertime', 'half day'], true);
            $activeCount = (int) (chatbotScalar(
                $conn,
                "SELECT COUNT(*) AS total FROM office WHERE employee_id = ?
                 AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')",
                'i',
                [$employeeId]
            )['total'] ?? 0);
            if (!$employee || !$present || $activeCount >= 8 || employeeHasLeaveOnDate($conn, $employeeId, $dueDate)) {
                throw new RuntimeException('Task assignment requires a valid employee who is present, has fewer than 8 active tasks, and has no leave on the due date.');
            }
            $selfAssignment = chatbotScalar(
                $conn,
                "SELECT 1 AS matched
                 FROM users u
                 WHERE u.id = ? AND (
                     u.employee_id = ? OR (
                         u.employee_id IS NULL AND EXISTS (
                             SELECT 1 FROM employee e
                             WHERE e.employee_id = ?
                               AND (e.username = u.name
                                    OR (e.email IS NOT NULL AND e.email <> '' AND e.email = u.email)
                                    OR EXISTS (SELECT 1 FROM attendance a WHERE a.employee_id = e.employee_id AND a.user_id = u.id))
                         )
                     )
                 ) LIMIT 1",
                'iii',
                [$actorId, $employeeId, $employeeId]
            );
            if ($selfAssignment) {
                throw new RuntimeException('Supervisors cannot assign office tasks to themselves.');
            }
            $stmt = $conn->prepare(
                "INSERT INTO office (supervisor_id, employee_id, data_type, title, content, status, priority_level, due_date)
                 VALUES (?, ?, 'General', ?, ?, 'pending', ?, ?)"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare office task assignment: ' . $conn->error);
            }
            $priority = 'Normal';
            $stmt->bind_param('iissss', $actorId, $employeeId, $title, $description, $priority, $dueDate);
            if (!$stmt->execute()) {
                throw new RuntimeException('Unable to save office task: ' . $stmt->error);
            }
            $officeId = (int) $conn->insert_id;
            $stmt->close();
            notifyEmployeeTaskAssignee($conn, $employeeId, $actorId, 'office_task_assigned', 'Task assigned to you', 'A new task, "' . $title . '", has been assigned to you.', 'office_employee.php?task_id=' . $officeId, 'office', (string) $officeId);
            recordSystemAudit($conn, $actorId, $actorName, 'office_task_created', 'office_task', $officeId, 'Created task "' . $title . '" for employee #' . $employeeId . ' through chatbot.');
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit office task: ' . $conn->error);
            }
            return 'Na-save ang Office task #' . $officeId . ' at na-notify si ' . $employee['username'] . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }

    if (($action['type'] ?? '') === 'delivery_update') {
        ensureDeliveryActivitySchema($conn);
        ensureTaskNotificationSchema($conn);
        $deliveryId = (int) $action['delivery_id'];
        $route = trim((string) $action['route']);
        $deliveryDate = (string) $action['delivery_date'];
        if ($route === '' || mb_strlen($route) > 255 || !chatbotDateIsValid($deliveryDate)) {
            throw new RuntimeException('Invalid ang route o delivery date.');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Hindi masimulan ang delivery update: ' . $conn->error);
        }
        try {
            $delivery = chatbotScalar(
                $conn,
                "SELECT route, delivery_date, driver_id FROM delivery
                 WHERE delivery_id = ? AND status <> 'Delivered'
                   AND completion_review_status <> 'pending_review'
                 LIMIT 1 FOR UPDATE",
                'i',
                [$deliveryId]
            );
            if (!$delivery) {
                throw new RuntimeException('Hindi na puwedeng i-update ang delivery na completed o naghihintay ng review.');
            }
            if ((string) $delivery['route'] === $route && (string) $delivery['delivery_date'] === $deliveryDate) {
                $conn->rollback();
                return 'Walang pagbabago sa Delivery #' . $deliveryId . '; pareho na ang route at date.';
            }
            if (userHasLeaveOnDate($conn, (int) $delivery['driver_id'], $deliveryDate)) {
                throw new RuntimeException('May recorded leave ang kasalukuyang driver sa bagong delivery date.');
            }
            $stmt = $conn->prepare(
                "UPDATE delivery SET route = ?, delivery_date = ?, assigned_by = ?,
                    completion_review_status = 'none', completion_review_note = NULL,
                    completion_reviewed_by = NULL, completion_reviewed_at = NULL
                 WHERE delivery_id = ? AND status <> 'Delivered'
                   AND completion_review_status <> 'pending_review'"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare delivery update: ' . $conn->error);
            }
            $stmt->bind_param('ssii', $route, $deliveryDate, $actorId, $deliveryId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('Unable to save delivery update: ' . $stmt->error);
            }
            $stmt->close();
            recordDeliveryActivity($conn, $deliveryId, $actorId, $actorName, 'delivery_updated');
            createTaskNotification(
                $conn,
                (int) $delivery['driver_id'],
                $actorId,
                'delivery_updated',
                'Delivery details updated',
                'The route or delivery date for Delivery #' . $deliveryId . ' has changed to ' . $route . ' on ' . $deliveryDate . '.',
                'logistic.php',
                'delivery',
                (string) $deliveryId
            );
            recordSystemAudit($conn, $actorId, $actorName, 'delivery_updated', 'delivery', $deliveryId, 'Updated route/date through chatbot.');
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit delivery update: ' . $conn->error);
            }
            return 'Na-update ang Delivery #' . $deliveryId . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }

    if (($action['type'] ?? '') === 'office_task_update') {
        ensureTaskNotificationSchema($conn);
        $officeId = (int) $action['office_id'];
        $title = trim((string) $action['title']);
        if ($title === '' || mb_strlen($title) > 180) {
            throw new RuntimeException('Maglagay ng task title na hindi lalampas sa 180 characters.');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Hindi masimulan ang office task update: ' . $conn->error);
        }
        try {
            $existingTask = chatbotScalar(
                $conn,
                "SELECT employee_id, title FROM office
                 WHERE office_id = ? AND supervisor_id = ?
                   AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                   AND completion_review_status <> 'pending_review'
                 LIMIT 1 FOR UPDATE",
                'ii',
                [$officeId, $actorId]
            );
            if (!$existingTask) {
                throw new RuntimeException('Task not found, not assigned by your supervisor account, already complete, or awaiting review.');
            }
            if ((string) $existingTask['title'] === $title) {
                $conn->rollback();
                return 'Walang pagbabago sa Office task #' . $officeId . '; pareho na ang title.';
            }
            $stmt = $conn->prepare(
                "UPDATE office SET title = ?
                 WHERE office_id = ? AND supervisor_id = ?
                   AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                   AND completion_review_status <> 'pending_review'"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare office task update: ' . $conn->error);
            }
            $stmt->bind_param('sii', $title, $officeId, $actorId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Unable to update office task: ' . $stmt->error);
            }
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            if (!$updated) {
                throw new RuntimeException('Task not found, not assigned by your supervisor account, already complete, or awaiting review.');
            }
            notifyEmployeeTaskAssignee(
                $conn,
                (int) $existingTask['employee_id'],
                $actorId,
                'office_task_updated',
                'Office task updated',
                'The title of Office task #' . $officeId . ' has been changed.',
                'office_employee.php?task_id=' . $officeId,
                'office',
                (string) $officeId
            );
            recordSystemAudit($conn, $actorId, $actorName, 'office_task_updated', 'office_task', $officeId, 'Updated task title through chatbot.');
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit office task update: ' . $conn->error);
            }
            return 'Na-update ang title ng Office task #' . $officeId . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            throw $exception;
        }
    }
    throw new RuntimeException('Hindi suportado ang chatbot action na ito.');
}

function chatbotParseWriteCommand(string $message): ?array
{
    if (preg_match('/^\s*assign\s+production\s+#?([a-z0-9-]+)\s+(cooking|packaging)\s+to\s+employee\s+#?(\d+)\s*[.!?]*\s*$/iu', $message, $match)) {
        return ['type' => 'production_assignment', 'production_id' => $match[1], 'work_type' => ucfirst(strtolower($match[2])), 'employee_id' => (int) $match[3]];
    }
    if (preg_match('/^\s*assign\s+delivery\s+(?:from\s+)?production\s+#?([a-z0-9-]+)\s+to\s+driver\s+#?(\d+)\s+route\s+"([^"]{1,255})"\s+due\s+(\d{4}-\d{2}-\d{2})\s*[.!?]*\s*$/iu', $message, $match)) {
        return ['type' => 'delivery_assignment', 'production_id' => $match[1], 'driver_id' => (int) $match[2], 'route' => trim($match[3]), 'delivery_date' => $match[4]];
    }
    if (preg_match('/^\s*assign\s+office\s+task\s+to\s+employee\s+#?(\d+)\s+titled\s+"([^"]{1,180})"\s+due\s+(\d{4}-\d{2}-\d{2})(?:\s+description\s+"([^"]{0,2000})")?\s*[.!?]*\s*$/iu', $message, $match)) {
        return ['type' => 'office_task_create', 'employee_id' => (int) $match[1], 'title' => trim($match[2]), 'due_date' => $match[3], 'description' => trim($match[4] ?? ''), 'priority' => 'Normal'];
    }
    if (preg_match('/^\s*update\s+delivery\s+#?(\d+)\s+route\s+"([^"]{1,255})"\s+due\s+(\d{4}-\d{2}-\d{2})\s*[.!?]*\s*$/iu', $message, $match)) {
        return ['type' => 'delivery_update', 'delivery_id' => (int) $match[1], 'route' => trim($match[2]), 'delivery_date' => $match[3]];
    }
    if (preg_match('/^\s*update\s+office\s+task\s+#?(\d+)\s+title\s+"([^"]{1,180})"\s*[.!?]*\s*$/iu', $message, $match)) {
        return ['type' => 'office_task_update', 'office_id' => (int) $match[1], 'title' => trim($match[2])];
    }
    return null;
}

try {
    if ($isActionRequest) {
        $postedCsrf = (string) ($request['csrf'] ?? '');
        if (empty($_SESSION['chatbot_csrf']) || !hash_equals((string) $_SESSION['chatbot_csrf'], $postedCsrf)) {
            chatbotRespond('Nag-expire o hindi valid ang security token. I-refresh ang page at subukan ulit.', 403);
        }
        $postedToken = (string) ($request['confirm_action'] ?? $request['cancel_action']);
        $pendingAction = $_SESSION['chatbot_pending_action'] ?? null;
        if (
            !is_array($pendingAction)
            || !isset($pendingAction['token'], $pendingAction['expires'], $pendingAction['action'])
            || !is_string($pendingAction['token'])
            || !hash_equals($pendingAction['token'], $postedToken)
            || (int) $pendingAction['expires'] < time()
        ) {
            unset($_SESSION['chatbot_pending_action']);
            chatbotRespond('Wala na o nag-expire na ang pending action. Ihanda ulit ang command para makakuha ng bagong preview.', 400);
        }
        if (isset($request['cancel_action'])) {
            unset($_SESSION['chatbot_pending_action']);
            chatbotRespond('Kinansela ang pagbabago. Walang na-save.');
        }
        if ($role !== 'super' || $userId === null) {
            unset($_SESSION['chatbot_pending_action']);
            chatbotRespond('Supervisor lang ang puwedeng mag-save ng task changes sa chatbot.', 403);
        }
        $pending = $pendingAction['action'];
        unset($_SESSION['chatbot_pending_action']);
        chatbotRespond(chatbotCommitAction(
            $conn,
            $pending,
            $userId,
            trim((string) ($_SESSION['name'] ?? 'Supervisor')) ?: 'Supervisor'
        ));
    }

    $employeeId = null;
    if (chatbotIsProductionRole($role) || $role === 'employee') {
        $employeeMatches = chatbotFetchRows(
            $conn,
            "SELECT DISTINCT e.employee_id
             FROM users u
             INNER JOIN employee e
                 ON ((u.employee_id IS NOT NULL AND e.employee_id = u.employee_id)
                     OR (u.employee_id IS NULL AND (
                         e.username = u.name
                         OR (e.email IS NOT NULL AND e.email = u.email)
                         OR EXISTS (
                             SELECT 1
                             FROM attendance employee_user_link
                             WHERE employee_user_link.employee_id = e.employee_id
                               AND employee_user_link.user_id = u.id
                         )
                     )))
             WHERE u.id = ?
             ORDER BY e.employee_id ASC",
            'i', [$userId]
        );
        if (count($employeeMatches) === 1) {
            $employeeId = (int) $employeeMatches[0]['employee_id'];
        }
    }

    $detailMatch = [];
    $hasRecordDetails = preg_match(
        '/\b(?:details?|show|info(?:rmation)?)\s+(?:me\s+)?(?:details?\s+)?(?:of\s+|for\s+|about\s+)?(office\s+tasks?|production|delivery|employee|applicant|payroll|attendance)(?:\s+(?:record|task))?(?:\s+id)?\s+#?([a-z0-9-]+)\b/u',
        $message,
        $detailMatch
    );
    if (!$hasRecordDetails) {
        $hasRecordDetails = preg_match(
            '/\b(office\s+tasks?|production|delivery|employee|applicant|payroll|attendance)\s+(?:(?:record|task)\s+)?(?:id\s*)?#?([a-z0-9-]+)\s+(?:details?|info)\b/u',
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
            'cooking' => implode("\n", [
                'Maaari kang magtanong tungkol sa sarili mong Cooking tasks.',
                '',
                'List records:',
                'list production',
                '',
                'Search:',
                'search production turmeric',
                '',
                'Record details:',
                'details production 123',
            ]),
            'packaging' => implode("\n", [
                'Maaari kang magtanong tungkol sa sarili mong Packaging tasks.',
                '',
                'List records:',
                'list production',
                '',
                'Search:',
                'search production turmeric',
                '',
                'Record details:',
                'details production 123',
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
            'employee' => implode("\n", [
                'Maaari mong itanong ang status ng sarili mong office tasks.',
                '',
                'Try these:',
                'my tasks',
                'task status',
                'overview',
            ]),
        ];
        $help['admin'] = $help['super'];
        $reportExamples = match ($role) {
            'admin', 'super' => "How many deliveries this week?\nattendance report last month\nproduction report 2026-10",
            'log' => "How many deliveries this week?\ndelivery report from 2026-10-01 to 2026-10-16",
            'pro', 'cooking', 'packaging' => "production report this week\nproduction report 2026-10",
            'HR' => "attendance report last month",
            'payroll' => "payroll report 2026-10\nattendance report last month",
            default => "office tasks this week\nmy tasks",
        };
        $processExamples = match ($role) {
            'super' => "How to review a task?\nHow to assign a production task?\nHow to assign an office task?",
            'admin' => "How to check production status?\nHow to check delivery proof?\nHow to view payroll?",
            'log' => "How to submit delivery proof?",
            'pro', 'cooking', 'packaging' => "How to complete my production task?",
            'HR' => "How to review an office task?\nHow to record leave?",
            'payroll' => "How to view payroll?",
            'employee' => "How to check my office tasks?\nHow to view my payslip?",
            default => 'How can I use the features available to my role?',
        };
        $help[$role] .= "\n\nAction items:\nwhat should I handle\nalerts\n\nReports:\n" . $reportExamples . "\n\nProcess help:\n" . $processExamples;
        chatbotRespond($help[$role]);
    }

    $writeAction = chatbotParseWriteCommand($rawMessage);
    if ($writeAction !== null) {
        if ($role !== 'super') {
            chatbotRespond('Supervisor lang ang maaaring mag-assign o mag-update ng production, delivery, at office tasks sa chatbot.', 403);
        }
        chatbotStageAction($conn, $writeAction);
    }
    if (preg_match('/^\s*(?:assign|create|update|change|i-assign|i-update|gumawa|baguhin)\b/u', $message)) {
        if ($role !== 'super') {
            chatbotRespond('Supervisor lang ang maaaring mag-save ng task changes sa chatbot.', 403);
        }
        chatbotRespond("Hindi kumpleto o hindi suportado ang command. I-type ang help para makita ang eksaktong format. Walang nabago.");
    }

    $processHelp = chatbotProcessHelp($message, $role);
    if ($processHelp !== null) {
        chatbotRespond($processHelp);
    }

    $dateReport = chatbotDateReport($conn, $message, $role, $employeeId, $userId);
    if ($dateReport !== null) {
        chatbotRespond($dateReport);
    }

    if (preg_match('/\b(?:what should i handle|what do i need to do|my priorities|action items|alerts|reminders|overdue|due soon|ano ang kailangan kong asikasuhin|ano ang gagawin ko|paalala|mga alerto)\b/u', $message)) {
        chatbotRespond(chatbotActionItems($conn, $role, $employeeId, $userId));
    }

    if (preg_match('/\b(?:who is (?:working on|assigned to)|sino ang naka-assign sa|sino ang gumagawa ng)\s+(production|delivery|office task)\b/u', $message)
        && !preg_match('/\b#?\d+\b/u', $message)) {
        chatbotRespond('Aling record ang tinutukoy mo? I-type ang record type at ID, halimbawa: “details production 123” o “details delivery 45”.');
    }

    $statusMatch = [];
    if (preg_match('/^\s*(?:status|who is working on|who is assigned to|sino ang naka-assign sa)\s+(production|delivery|office task)\s+#?([a-z0-9-]+)\s*[?.!]*\s*$/u', $message, $statusMatch)) {
        chatbotRespond(chatbotRecordDetails($conn, $statusMatch[1], $statusMatch[2], $role, $employeeId, $userId));
    }
    if (preg_match('/^\s*(?:why (?:is|does)|bakit)\s+(?:production|delivery|office task)\s+#?([a-z0-9-]+)\s+(?:blocked|incomplete|pending|late|overdue|status)\s*[?.!]*\s*$/u', $message, $whyMatch)) {
        $whyType = preg_match('/\bdelivery\b/u', $message) ? 'delivery' : (preg_match('/\boffice task\b/u', $message) ? 'office task' : 'production');
        chatbotRespond(chatbotRecordDetails($conn, $whyType, $whyMatch[1], $role, $employeeId, $userId));
    }
    if (preg_match('/\b(?:who is assigned|who is working on|bakit hindi matapos|why is it blocked)\b/u', $message)
        && !preg_match('/\b#?\d+\b/u', $message)) {
        chatbotRespond('Kailangan ko ng record type at ID para makita ang assignee o dahilan. Halimbawa: “who is working on production 123” o “why is production 123 blocked”.');
    }
    if (
        in_array($message, ['report', 'reports', 'show report', 'status', 'task status', 'check status'], true)
        && !($role === 'employee' && in_array($message, ['status', 'task status'], true))
    ) {
        chatbotRespond('Aling module o record ang gusto mong tingnan? Halimbawa: “attendance report last month”, “delivery report this week”, o “details production 123”.');
    }

    $recordTypesPattern = 'office tasks?|production|deliver(?:y|ies)|shipments?|employees?|staff|applicants?|payroll|payslips?|attendances?';
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
            'admin' => 'production, delivery, employees, applicants, payroll, attendance',
            'super' => 'production, delivery, employees, applicants, payroll, attendance',
            'pro' => 'production',
            'cooking' => 'production',
            'packaging' => 'production',
            'log' => 'delivery',
            'HR' => 'employees, applicants, attendance',
            'payroll' => 'payroll, attendance',
            'employee' => 'your office tasks',
        ];
        chatbotRespond(
            'Piliin ang record type na gusto mong makita: ' . $available[$role] .
            '. Halimbawa: “list ' . explode(', ', $available[$role])[0] . '”.'
        );
    }

    if ($role === 'employee' && preg_match('/\b(?:my tasks?|office tasks?|assigned tasks?|task status|task overview)\b/u', $message)) {
        chatbotRespond(chatbotOfficeTasks($conn, $employeeId));
    }

    if (preg_match('/\b(production|production task|production status|my tasks|task status)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'pro', 'cooking', 'packaging'], true)) {
            chatbotRespond('Hindi available ang production data sa role mo.');
        }
        chatbotRespond(chatbotProduction($conn, $role, $employeeId));
    }

    if (preg_match('/\b(delivery|deliveries|shipment|shipments|my deliveries)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'log'], true)) {
            chatbotRespond('Hindi available ang delivery data sa role mo.');
        }
        chatbotRespond(chatbotDelivery($conn, $role, $userId));
    }

    if (preg_match('/\b(applicant|applicants|recruitment|interview)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'HR'], true)) {
            chatbotRespond('Hindi available ang applicant data sa role mo.');
        }
        chatbotRespond(chatbotApplicantSummary($conn));
    }

    if (preg_match('/\b(attendance|present|absent|late|time in)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'HR', 'payroll'], true)) {
            chatbotRespond('Hindi available ang attendance summary sa role mo.');
        }
        chatbotRespond(chatbotAttendanceSummary($conn));
    }

    if (preg_match('/\b(employee|employees|staff|headcount)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'HR'], true)) {
            chatbotRespond('Hindi available ang employee summary sa role mo.');
        }
        chatbotRespond(chatbotEmployeeSummary($conn));
    }

    if (preg_match('/\b(payroll|salary|salaries|payslip|paid|pending pay|disbursed)\b/u', $message)) {
        if (!in_array($role, ['admin', 'super', 'payroll'], true)) {
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
} catch (RuntimeException $exception) {
    error_log('TaskTrack chatbot operation failed: ' . $exception->getMessage());
    chatbotRespond($exception->getMessage(), 400);
}