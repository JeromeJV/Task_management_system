<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
require_once __DIR__ . '/config/system_audit.php';
require_once __DIR__ . '/config/mailer.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

$currentAdminId = (int) $_SESSION['id'];
$adminCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
if (!$adminCheckStmt) {
    throw new RuntimeException('Unable to verify administrator access: ' . $conn->error);
}
$adminCheckStmt->bind_param('i', $currentAdminId);
if (!$adminCheckStmt->execute()) {
    $error = $adminCheckStmt->error;
    $adminCheckStmt->close();
    throw new RuntimeException('Unable to verify administrator access: ' . $error);
}
$isStillAdmin = $adminCheckStmt->get_result()->num_rows === 1;
$adminCheckStmt->close();
if (!$isStillAdmin) {
    session_unset();
    session_destroy();
    http_response_code(403);
    exit('Administrator access is no longer active. Please sign in again.');
}

$roles = [
    'admin' => 'Administrator',
    'HR' => 'Human Resources',
    'payroll' => 'Payroll',
    'super' => 'Supervisor',
    'log' => 'Logistics',
    'pro' => 'Legacy Production',
    'cooking' => 'Cooking',
    'packaging' => 'Packaging',
    'employee' => 'Employee',
];
$section = (string) ($_GET['section'] ?? 'dashboard');
if (!in_array($section, ['dashboard', 'accounts', 'audit', 'reports'], true)) {
    $section = 'dashboard';
}
$errorMessage = '';
$flashMessage = (string) ($_SESSION['admin_flash'] ?? '');
$flashType = (string) ($_SESSION['admin_flash_type'] ?? 'success');
unset($_SESSION['admin_flash']);
unset($_SESSION['admin_flash_type']);

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['admin_csrf'], $postedToken)) {
        $errorMessage = 'Your session token expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create_account') {
            $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
            $role = (string) ($_POST['role'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            if (!$employeeId || $employeeId < 1) {
                $errorMessage = 'Choose a valid employee profile.';
            } elseif (!isset($roles[$role])) {
                $errorMessage = 'Choose a valid account role.';
            } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
                $errorMessage = 'Password must be at least 8 characters and include a number.';
            } else {
                $employeeStmt = $conn->prepare(
                    'SELECT username, email FROM employee WHERE employee_id = ? LIMIT 1'
                );
                if (!$employeeStmt) {
                    throw new RuntimeException('Unable to prepare the employee lookup: ' . $conn->error);
                }
                $employeeStmt->bind_param('i', $employeeId);
                if (!$employeeStmt->execute()) {
                    $error = $employeeStmt->error;
                    $employeeStmt->close();
                    throw new RuntimeException('Unable to load the employee profile: ' . $error);
                }
                $employee = $employeeStmt->get_result()->fetch_assoc();
                $employeeStmt->close();

                if (!$employee || trim((string) $employee['email']) === '') {
                    $errorMessage = 'That employee profile has no email address for an account.';
                } else {
                    if (!$conn->begin_transaction()) {
                        throw new RuntimeException('Unable to start account creation: ' . $conn->error);
                    }
                    $existingStmt = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1 FOR UPDATE');
                    if (!$existingStmt) {
                        throw new RuntimeException('Unable to prepare the duplicate account check: ' . $conn->error);
                    }
                    $employeeEmail = (string) $employee['email'];
                    $existingStmt->bind_param('s', $employeeEmail);
                    if (!$existingStmt->execute()) {
                        $error = $existingStmt->error;
                        $existingStmt->close();
                        throw new RuntimeException('Unable to check for an existing account: ' . $error);
                    }
                    $hasAccount = $existingStmt->get_result()->num_rows > 0;
                    $existingStmt->close();

                    if ($hasAccount) {
                        $conn->rollback();
                        $errorMessage = 'An account already exists for this employee email.';
                    } else {
                        $name = (string) $employee['username'];
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $insertStmt = $conn->prepare(
                            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)'
                        );
                        if (!$insertStmt) {
                            throw new RuntimeException('Unable to prepare the account creation: ' . $conn->error);
                        }
                        $insertStmt->bind_param('ssss', $name, $employeeEmail, $hashedPassword, $role);
                        if (!$insertStmt->execute()) {
                            $error = $insertStmt->error;
                            $insertStmt->close();
                            $conn->rollback();
                            throw new RuntimeException('Unable to create the account: ' . $error);
                        }
                        $newUserId = (int) $conn->insert_id;
                        $insertStmt->close();
                        recordSystemAudit(
                            $conn,
                            $currentAdminId,
                            (string) $_SESSION['name'],
                            'account_created',
                            'user',
                            $newUserId,
                            'Created ' . $role . ' account for ' . $name . ' (' . $employeeEmail . ').'
                        );
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the new account: ' . $conn->error);
                        }
                        try {
                            sendTaskTrackAccountPassword(
                                $employeeEmail,
                                $name,
                                $roles[$role],
                                $password
                            );
                            $_SESSION['admin_flash'] = 'Account created and password email sent to ' . $employeeEmail . '.';
                        } catch (RuntimeException $mailError) {
                            $_SESSION['admin_flash_type'] = 'warning';
                            $_SESSION['admin_flash'] = 'Account created, but the password email was not sent. '
                                . $mailError->getMessage()
                                . ' Use Reset password below to set a new password and try again.';
                        }
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                }
            }
        } elseif ($action === 'update_role') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $role = (string) ($_POST['role'] ?? '');
            if (!$userId || $userId < 1 || !isset($roles[$role])) {
                $errorMessage = 'Choose a valid account and role.';
            } elseif ($userId === $currentAdminId) {
                $errorMessage = 'You cannot change your own administrator role.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the role update: ' . $conn->error);
                }
                $userStmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the account lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the account: ' . $error);
                }
                $user = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                if (!$user) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                } elseif ($user['role'] === 'admin' && $role !== 'admin') {
                    $adminsStmt = $conn->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                    if (!$adminsStmt) {
                        throw new RuntimeException('Unable to verify administrator access: ' . $conn->error);
                    }
                    $hasAnotherAdmin = $adminsStmt->num_rows > 1;
                    $adminsStmt->free();
                    if (!$hasAnotherAdmin) {
                        $conn->rollback();
                        $errorMessage = 'The last administrator account cannot be demoted.';
                    } else {
                        $updateStmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                        if (!$updateStmt) {
                            throw new RuntimeException('Unable to prepare the role update: ' . $conn->error);
                        }
                        $updateStmt->bind_param('si', $role, $userId);
                        if (!$updateStmt->execute()) {
                            $error = $updateStmt->error;
                            $updateStmt->close();
                            throw new RuntimeException('Unable to update the account role: ' . $error);
                        }
                        $updateStmt->close();
                        recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'role_changed', 'user', $userId, 'Changed ' . $user['email'] . ' role from ' . $user['role'] . ' to ' . $role . '.');
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the account role: ' . $conn->error);
                        }
                        $_SESSION['admin_flash'] = 'Account role updated successfully.';
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                } elseif ($user['role'] === $role) {
                    $conn->rollback();
                    $errorMessage = 'The account already has that role.';
                } else {
                    $updateStmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                    if (!$updateStmt) {
                        throw new RuntimeException('Unable to prepare the role update: ' . $conn->error);
                    }
                    $updateStmt->bind_param('si', $role, $userId);
                    if (!$updateStmt->execute()) {
                        $error = $updateStmt->error;
                        $updateStmt->close();
                        throw new RuntimeException('Unable to update the account role: ' . $error);
                    }
                    $updateStmt->close();
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'role_changed', 'user', $userId, 'Changed ' . $user['email'] . ' role from ' . $user['role'] . ' to ' . $role . '.');
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the account role: ' . $conn->error);
                    }
                    $_SESSION['admin_flash'] = 'Account role updated successfully.';
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } elseif ($action === 'toggle_account') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $isActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
            if (!$userId || $userId < 1 || !in_array($isActive, [0, 1], true)) {
                $errorMessage = 'Choose a valid account status.';
            } elseif ($userId === $currentAdminId && $isActive === 0) {
                $errorMessage = 'You cannot disable your own administrator account.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the account status update: ' . $conn->error);
                }
                $userStmt = $conn->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the account status lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the account status: ' . $error);
                }
                $user = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();
                if (!$user) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                } elseif ((int) $user['is_active'] === $isActive) {
                    $conn->rollback();
                    $errorMessage = 'The account already has that status.';
                } elseif ($user['role'] === 'admin' && $isActive === 0) {
                    $adminsStmt = $conn->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                    if (!$adminsStmt) {
                        throw new RuntimeException('Unable to verify active administrator access: ' . $conn->error);
                    }
                    $hasAnotherActiveAdmin = $adminsStmt->num_rows > 1;
                    $adminsStmt->free();
                    if (!$hasAnotherActiveAdmin) {
                        $conn->rollback();
                        $errorMessage = 'The last active administrator account cannot be disabled.';
                    } else {
                        $updateStmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                        if (!$updateStmt) {
                            throw new RuntimeException('Unable to prepare the account status update: ' . $conn->error);
                        }
                        $updateStmt->bind_param('ii', $isActive, $userId);
                        if (!$updateStmt->execute()) {
                            $error = $updateStmt->error;
                            $updateStmt->close();
                            throw new RuntimeException('Unable to update account status: ' . $error);
                        }
                        $updateStmt->close();
                        recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'account_disabled', 'user', $userId, 'Disabled account ' . $user['email'] . '.');
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the account status: ' . $conn->error);
                        }
                        $_SESSION['admin_flash'] = 'Account disabled. Any active session has been blocked.';
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                } else {
                    $updateStmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                    if (!$updateStmt) {
                        throw new RuntimeException('Unable to prepare the account status update: ' . $conn->error);
                    }
                    $updateStmt->bind_param('ii', $isActive, $userId);
                    if (!$updateStmt->execute()) {
                        $error = $updateStmt->error;
                        $updateStmt->close();
                        throw new RuntimeException('Unable to update account status: ' . $error);
                    }
                    $updateStmt->close();
                    $auditAction = $isActive === 1 ? 'account_enabled' : 'account_disabled';
                    $auditDetails = ($isActive === 1 ? 'Enabled account ' : 'Disabled account ') . $user['email'] . '.';
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], $auditAction, 'user', $userId, $auditDetails);
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the account status: ' . $conn->error);
                    }
                    $_SESSION['admin_flash'] = $isActive === 1 ? 'Account enabled successfully.' : 'Account disabled. Any active session has been blocked.';
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } elseif ($action === 'reset_password') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $password = (string) ($_POST['password'] ?? '');
            if (!$userId || $userId < 1 || $userId === $currentAdminId) {
                $errorMessage = 'Choose another valid account to reset.';
            } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
                $errorMessage = 'Password must be at least 8 characters and include a number.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the password reset: ' . $conn->error);
                }
                $targetStmt = $conn->prepare('SELECT name, email, role FROM users WHERE id = ? LIMIT 1');
                if (!$targetStmt) {
                    throw new RuntimeException('Unable to prepare the account email lookup: ' . $conn->error);
                }
                $targetStmt->bind_param('i', $userId);
                if (!$targetStmt->execute()) {
                    $error = $targetStmt->error;
                    $targetStmt->close();
                    throw new RuntimeException('Unable to load the account email: ' . $error);
                }
                $targetAccount = $targetStmt->get_result()->fetch_assoc();
                $targetStmt->close();
                if (!$targetAccount) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                    $targetAccount = null;
                }
                if ($targetAccount) {
                    $resetStmt = $conn->prepare('UPDATE users SET password = ?, login_attempts = 0, lockout_time = NULL WHERE id = ?');
                    if (!$resetStmt) {
                        throw new RuntimeException('Unable to prepare the password reset: ' . $conn->error);
                    }
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $resetStmt->bind_param('si', $hashedPassword, $userId);
                    if (!$resetStmt->execute() || $resetStmt->affected_rows < 1) {
                        $error = $resetStmt->error;
                        $resetStmt->close();
                        $conn->rollback();
                        throw new RuntimeException('Unable to reset the account password: ' . $error);
                    }
                    $resetStmt->close();
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'password_reset', 'user', $userId, 'Reset the password and cleared login lockout for user #' . $userId . '.');
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the password reset: ' . $conn->error);
                    }
                    try {
                        sendTaskTrackAccountPassword(
                            (string) $targetAccount['email'],
                            (string) $targetAccount['name'],
                            $roles[(string) $targetAccount['role']] ?? (string) $targetAccount['role'],
                            $password
                        );
                        $_SESSION['admin_flash'] = 'Password updated and email sent to ' . $targetAccount['email'] . '.';
                    } catch (RuntimeException $mailError) {
                        $_SESSION['admin_flash_type'] = 'warning';
                        $_SESSION['admin_flash'] = 'Password updated, but the email was not sent. '
                            . $mailError->getMessage()
                            . ' Set a new password and try again.';
                    }
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } else {
            $errorMessage = 'Unsupported administrator action.';
        }
    }
}

$employees = [];
$employeeResult = $conn->query(
    "SELECT e.employee_id, e.username, e.email, e.position
     FROM employee e
     LEFT JOIN users u ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
     WHERE u.id IS NULL AND e.email IS NOT NULL AND TRIM(e.email) <> ''
     ORDER BY e.username"
);
if (!$employeeResult) {
    throw new RuntimeException('Unable to load employees without accounts: ' . $conn->error);
}
while ($employee = $employeeResult->fetch_assoc()) {
    $employees[] = $employee;
}
$employeeResult->free();

$accounts = [];
$accountResult = $conn->query('SELECT id, name, email, role, is_active FROM users ORDER BY name, id');
if (!$accountResult) {
    throw new RuntimeException('Unable to load system accounts: ' . $conn->error);
}
while ($account = $accountResult->fetch_assoc()) {
    $accounts[] = $account;
}
$accountResult->free();

$roleCounts = [];
$roleResult = $conn->query('SELECT role, COUNT(*) AS item_count FROM users GROUP BY role ORDER BY role');
if (!$roleResult) {
    throw new RuntimeException('Unable to load account role summary: ' . $conn->error);
}
while ($row = $roleResult->fetch_assoc()) {
    $roleCounts[(string) $row['role']] = (int) $row['item_count'];
}
$roleResult->free();

$taskCounts = [];
$taskResult = $conn->query('SELECT COALESCE(status, "pending") AS item_status, COUNT(*) AS item_count FROM office GROUP BY COALESCE(status, "pending")');
if (!$taskResult) {
    throw new RuntimeException('Unable to load office task summary: ' . $conn->error);
}
while ($row = $taskResult->fetch_assoc()) {
    $taskCounts[(string) $row['item_status']] = (int) $row['item_count'];
}
$taskResult->free();

$attendanceCounts = [];
$attendanceResult = $conn->query('SELECT status, COUNT(*) AS item_count FROM attendance WHERE attendance_date = CURDATE() GROUP BY status');
if (!$attendanceResult) {
    throw new RuntimeException('Unable to load today attendance summary: ' . $conn->error);
}
while ($row = $attendanceResult->fetch_assoc()) {
    $attendanceCounts[(string) ($row['status'] ?? 'Unknown')] = (int) $row['item_count'];
}
$attendanceResult->free();

$payrollCounts = [];
$payrollResult = $conn->query('SELECT status, COUNT(*) AS item_count, COALESCE(SUM(total_credited), 0) AS total_amount FROM payroll GROUP BY status');
if (!$payrollResult) {
    throw new RuntimeException('Unable to load payroll summary: ' . $conn->error);
}
while ($row = $payrollResult->fetch_assoc()) {
    $payrollCounts[(string) ($row['status'] ?? 'Unknown')] = [
        'count' => (int) $row['item_count'],
        'amount' => (float) $row['total_amount'],
    ];
}
$payrollResult->free();

$productionCounts = [];
$productionResult = $conn->query('SELECT work_type, work_status, COUNT(*) AS item_count FROM production_assignments GROUP BY work_type, work_status');
if (!$productionResult) {
    throw new RuntimeException('Unable to load production summary: ' . $conn->error);
}
while ($row = $productionResult->fetch_assoc()) {
    $productionCounts[] = $row;
}
$productionResult->free();

$deliveryCounts = [];
$deliveryResult = $conn->query('SELECT status, COUNT(*) AS item_count FROM delivery GROUP BY status');
if (!$deliveryResult) {
    throw new RuntimeException('Unable to load logistics summary: ' . $conn->error);
}
while ($row = $deliveryResult->fetch_assoc()) {
    $deliveryCounts[(string) ($row['status'] ?? 'Pending')] = (int) $row['item_count'];
}
$deliveryResult->free();

if ($section === 'reports' && ($_GET['download'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tasktrack-system-report-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to create the CSV report.');
    }
    fputcsv($output, ['Area', 'Status / Role', 'Count', 'Amount']);
    foreach ($roleCounts as $role => $count) {
        fputcsv($output, ['Accounts', $role, $count, '']);
    }
    foreach ($attendanceCounts as $status => $count) {
        fputcsv($output, ['Attendance today', $status, $count, '']);
    }
    foreach ($taskCounts as $status => $count) {
        fputcsv($output, ['Office tasks', $status, $count, '']);
    }
    foreach ($payrollCounts as $status => $totals) {
        fputcsv($output, ['Payroll', $status, $totals['count'], number_format($totals['amount'], 2, '.', '')]);
    }
    foreach ($productionCounts as $row) {
        fputcsv($output, ['Production - ' . $row['work_type'], $row['work_status'], $row['item_count'], '']);
    }
    foreach ($deliveryCounts as $status => $count) {
        fputcsv($output, ['Logistics', $status, $count, '']);
    }
    fclose($output);
    exit();
}

$auditEntries = [];
$auditSources = [
    'SELECT occurred_at, actor_name, action_type, subject_id, details FROM system_audit_log',
];
foreach ([
    'production_activity' => "SELECT occurred_at, actor_name, event_type AS action_type, NULL AS subject_id,
                                     CONCAT('Production #', production_id, COALESCE(CONCAT(' · ', work_type), ''), ' activity') AS details
                              FROM production_activity",
    'delivery_activity' => "SELECT occurred_at, actor_name, event_type AS action_type, NULL AS subject_id,
                                   CONCAT('Delivery #', delivery_id, ' activity') AS details
                            FROM delivery_activity",
] as $tableName => $activityQuery) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if (!$tableCheck) {
        throw new RuntimeException('Unable to inspect system activity history: ' . $conn->error);
    }
    if ($tableCheck->num_rows > 0) {
        $auditSources[] = $activityQuery;
    }
    $tableCheck->free();
}
$auditResult = $conn->query(implode(' UNION ALL ', $auditSources) . ' ORDER BY occurred_at DESC LIMIT 100');
if (!$auditResult) {
    throw new RuntimeException('Unable to load administrator audit history: ' . $conn->error);
}
while ($audit = $auditResult->fetch_assoc()) {
    $auditEntries[] = $audit;
}
$auditResult->free();

$employeeCountResult = $conn->query('SELECT COUNT(*) AS total FROM employee');
if (!$employeeCountResult) {
    throw new RuntimeException('Unable to load employee profile count: ' . $conn->error);
}
$employeeCount = (int) ($employeeCountResult->fetch_assoc()['total'] ?? 0);
$employeeCountResult->free();
$userCount = count($accounts);
$activeTaskCount = 0;
foreach ($taskCounts as $status => $count) {
    if (!in_array(strtolower(trim($status)), ['completed', 'complete', 'done'], true)) {
        $activeTaskCount += $count;
    }
}
$todayAttendanceTotal = array_sum($attendanceCounts);
$paidPayroll = $payrollCounts['Paid']['amount'] ?? 0.0;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$sectionTitles = [
    'dashboard' => 'System overview',
    'accounts' => 'Account management',
    'audit' => 'System audit log',
    'reports' => 'System reports',
];
$tasktrackActive = $section === 'dashboard' ? 'dashboard' : $section;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($sectionTitles[$section]); ?> | TaskTrack Admin</title>
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= filemtime(__DIR__ . '/css/admin.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/config/tasktrack_sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-header">
        <div><p class="admin-eyebrow">TASKTRACK ADMINISTRATION</p><h1><?= $escape($sectionTitles[$section]); ?></h1><p>System-wide visibility and account access management.</p></div>
        <div class="admin-user"><strong><?= $escape($_SESSION['name'] ?? 'Administrator'); ?></strong><span><?= $escape(date('M j, Y')); ?></span></div>
    </header>
    <?php if ($flashMessage !== ''): ?><div class="admin-notice is-<?= $escape($flashType); ?>" role="status"><?= $escape($flashMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="admin-notice is-error" role="alert"><?= $escape($errorMessage); ?></div><?php endif; ?>

    <?php if ($section === 'dashboard'): ?>
        <section class="admin-stat-grid" aria-label="System summary">
            <article class="admin-stat"><span>Employee profiles</span><strong><?= $employeeCount; ?></strong></article>
            <article class="admin-stat"><span>System accounts</span><strong><?= $userCount; ?></strong></article>
            <article class="admin-stat"><span>Active office tasks</span><strong><?= $activeTaskCount; ?></strong></article>
            <article class="admin-stat"><span>Attendance records today</span><strong><?= $todayAttendanceTotal; ?></strong></article>
            <article class="admin-stat"><span>Paid payroll total</span><strong>₱<?= number_format((float) $paidPayroll, 2); ?></strong></article>
        </section>
        <section class="admin-panel-grid">
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">ACCESS</p><h2>Accounts by role</h2></div><a href="admin.php?section=accounts">Manage accounts</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Role</th><th>Accounts</th></tr></thead><tbody>
                <?php foreach ($roleCounts as $role => $count): ?><tr><td><?= $escape($roles[$role] ?? ucfirst($role)); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">TODAY</p><h2>Attendance status</h2></div><a href="admin.php?section=reports">All reports</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Records</th></tr></thead><tbody>
                <?php foreach ($attendanceCounts as $status => $count): ?><tr><td><?= $escape(ucfirst($status)); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                <?php if ($attendanceCounts === []): ?><tr><td colspan="2">No attendance records today.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">OPERATIONS</p><h2>Production</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Stage</th><th>Status</th><th>Assignments</th></tr></thead><tbody>
                <?php foreach ($productionCounts as $row): ?><tr><td><?= $escape($row['work_type']); ?></td><td><?= $escape($row['work_status']); ?></td><td><?= (int) $row['item_count']; ?></td></tr><?php endforeach; ?>
                <?php if ($productionCounts === []): ?><tr><td colspan="3">No production assignments.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">OPERATIONS</p><h2>Logistics</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Deliveries</th></tr></thead><tbody>
                <?php foreach ($deliveryCounts as $status => $count): ?><tr><td><?= $escape($status); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                <?php if ($deliveryCounts === []): ?><tr><td colspan="2">No delivery records.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">PAYROLL</p><h2>Payroll status</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Records</th><th>Total</th></tr></thead><tbody>
                <?php foreach ($payrollCounts as $status => $totals): ?><tr><td><?= $escape($status); ?></td><td><?= $totals['count']; ?></td><td>₱<?= number_format($totals['amount'], 2); ?></td></tr><?php endforeach; ?>
                <?php if ($payrollCounts === []): ?><tr><td colspan="3">No payroll records.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
        </section>
    <?php elseif ($section === 'accounts'): ?>
        <section class="admin-panel admin-account-create">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">NEW ACCOUNT</p><h2>Create a system account</h2></div></div>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="action" value="create_account">
                <label>Employee profile
                    <select name="employee_id" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee['employee_id']; ?>"><?= $escape($employee['username'] . ' · ' . $employee['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>System role
                    <select name="role" required>
                        <?php foreach ($roles as $role => $label): ?><option value="<?= $escape($role); ?>"><?= $escape($label); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Password (sent to the employee's email)
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                    <small>At least 8 characters and one number. The employee can change it later in account settings.</small>
                </label>
                <button type="submit">Create account &amp; email password</button>
            </form>
            <?php if ($employees === []): ?><p class="admin-muted">All employee profiles with email addresses already have accounts.</p><?php endif; ?>
        </section>
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">SYSTEM ACCESS</p><h2>Registered accounts</h2></div><span><?= $userCount; ?> accounts</span></div>
            <div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Account status</th><th>Reset password</th></tr></thead><tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><?= $escape($account['name']); ?><?= (int) $account['id'] === $currentAdminId ? ' (you)' : ''; ?></td>
                    <td><?= $escape($account['email']); ?></td>
                    <td>
                        <?php if ((int) $account['id'] === $currentAdminId): ?>
                            <?= $escape($roles[$account['role']] ?? ucfirst($account['role'])); ?>
                        <?php else: ?>
                            <form method="post" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <select name="role" aria-label="Role for <?= $escape($account['name']); ?>">
                                    <?php foreach ($roles as $role => $label): ?><option value="<?= $escape($role); ?>" <?= $account['role'] === $role ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php endforeach; ?>
                                </select>
                                <button type="submit" class="admin-secondary-button">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] === $currentAdminId): ?>
                            <span class="admin-status is-active">Active</span>
                        <?php else: ?>
                            <form method="post" class="admin-inline-form" <?= (int) $account['is_active'] === 1 ? 'onsubmit="return confirm(\'Disable this account? It will be logged out and unable to sign in.\');"' : ''; ?>>
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="toggle_account">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <input type="hidden" name="is_active" value="<?= (int) $account['is_active'] === 1 ? 0 : 1; ?>">
                                <span class="admin-status <?= (int) $account['is_active'] === 1 ? 'is-active' : 'is-disabled'; ?>"><?= (int) $account['is_active'] === 1 ? 'Active' : 'Disabled'; ?></span>
                                <button type="submit" class="admin-secondary-button"><?= (int) $account['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] !== $currentAdminId): ?>
                            <form method="post" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <input type="password" name="password" minlength="8" placeholder="Set new password" required autocomplete="new-password" aria-label="New password for <?= $escape($account['name']); ?>">
                                <button type="submit" class="admin-secondary-button">Set &amp; email</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php elseif ($section === 'audit'): ?>
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">SYSTEM ACTIVITY</p><h2>Recent audit events</h2></div><span>Latest 100</span></div>
            <p class="admin-muted">Includes account, role, task, attendance, payroll, production, and logistics activity.</p>
            <div class="admin-table-wrap"><table><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Record ID</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($auditEntries as $entry): ?>
                <tr><td><?= $escape($entry['occurred_at']); ?></td><td><?= $escape($entry['actor_name']); ?></td><td><?= $escape(str_replace('_', ' ', ucfirst($entry['action_type']))); ?></td><td><?= $escape($entry['subject_id'] ?? '—'); ?></td><td><?= $escape($entry['details']); ?></td></tr>
            <?php endforeach; ?>
            <?php if ($auditEntries === []): ?><tr><td colspan="5">No system activity has been recorded.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php else: ?>
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">CONSOLIDATED REPORT</p><h2>System-wide operational summary</h2></div><a class="admin-primary-link" href="admin.php?section=reports&download=csv" title="Download consolidated system report as a CSV file">Download CSV report</a></div>
            <div class="admin-report-grid">
                <article><h3>Accounts by role</h3><?php foreach ($roleCounts as $role => $count): ?><p><span><?= $escape($roles[$role] ?? ucfirst($role)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?></article>
                <article><h3>Attendance today</h3><?php foreach ($attendanceCounts as $status => $count): ?><p><span><?= $escape(ucfirst($status)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?></article>
                <article><h3>Office tasks</h3><?php foreach ($taskCounts as $status => $count): ?><p><span><?= $escape(ucfirst($status)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?></article>
                <article><h3>Payroll</h3><?php foreach ($payrollCounts as $status => $totals): ?><p><span><?= $escape($status); ?></span><strong><?= $totals['count']; ?> · ₱<?= number_format($totals['amount'], 2); ?></strong></p><?php endforeach; ?></article>
                <article><h3>Production stages</h3><?php foreach ($productionCounts as $row): ?><p><span><?= $escape($row['work_type'] . ' · ' . $row['work_status']); ?></span><strong><?= (int) $row['item_count']; ?></strong></p><?php endforeach; ?></article>
                <article><h3>Deliveries</h3><?php foreach ($deliveryCounts as $status => $count): ?><p><span><?= $escape($status); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?></article>
            </div>
        </section>
    <?php endif; ?>
</main>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
</body>
</html>
