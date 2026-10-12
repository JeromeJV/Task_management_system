<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
require_once __DIR__ . '/config/system_audit.php';
require_once __DIR__ . '/config/task_notifications.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['email'], $_SESSION['role']) || !in_array($_SESSION['role'], ['super', 'HR'], true)) {
    header('Location: index.php');
    exit();
}
$currentUserId = (int) ($_SESSION['id'] ?? 0);
if ($currentUserId < 1) {
    http_response_code(403);
    exit('A valid signed-in account is required to assign tasks.');
}
$isSupervisor = strtolower(trim((string) $_SESSION['role'])) === 'super';
$currentEmployeeId = 0;
if ($isSupervisor) {
    $currentEmployeeStmt = $conn->prepare(
        "SELECT e.employee_id
         FROM users u
         JOIN employee e
           ON (
               LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
               OR EXISTS (
                   SELECT 1
                   FROM attendance a
                   WHERE a.employee_id = e.employee_id
                     AND a.user_id = u.id
               )
           )
          AND (
              SELECT COUNT(*)
              FROM employee matched_employee
              WHERE LOWER(TRIM(matched_employee.email)) = LOWER(TRIM(u.email))
                 OR EXISTS (
                     SELECT 1
                     FROM attendance matched_attendance
                     WHERE matched_attendance.employee_id = matched_employee.employee_id
                       AND matched_attendance.user_id = u.id
                 )
          ) = 1
         WHERE u.id = ?
         LIMIT 1"
    );
    if (!$currentEmployeeStmt) {
        throw new RuntimeException('Unable to prepare the supervisor profile lookup: ' . $conn->error);
    }
    $currentEmployeeStmt->bind_param('i', $currentUserId);
    if (!$currentEmployeeStmt->execute()) {
        $error = $currentEmployeeStmt->error;
        $currentEmployeeStmt->close();
        throw new RuntimeException('Unable to load the supervisor profile: ' . $error);
    }
    $currentEmployeeId = (int) ($currentEmployeeStmt->get_result()->fetch_assoc()['employee_id'] ?? 0);
    $currentEmployeeStmt->close();
}

if (empty($_SESSION['office_task_csrf'])) {
    $_SESSION['office_task_csrf'] = bin2hex(random_bytes(32));
}

$flashMessage = $_SESSION['office_task_flash'] ?? '';
unset($_SESSION['office_task_flash']);
$errorMessage = '';
$allowedCategories = ['General', 'HR', 'Payroll', 'IT'];
$maxActiveTasks = 8;
$presentAttendanceStatuses = ['present', 'early', 'late', 'overtime', 'undertime', 'half day'];
ensureTaskNotificationSchema($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (isset($_POST['create_task']) || isset($_POST['update_task']))) {
    $isUpdate = isset($_POST['update_task']);
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
    $officeId = filter_var($_POST['office_id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $allowedPriorities = ['low', 'medium', 'high', 'urgent'];

    if (!hash_equals($_SESSION['office_task_csrf'], $postedToken)) {
        $errorMessage = 'Your session token expired. Refresh the page and try again.';
    } elseif (!$employeeId || $employeeId < 1) {
        $errorMessage = 'Choose a valid employee for this task.';
    } elseif ($isUpdate && (!$officeId || $officeId < 1)) {
        $errorMessage = 'The selected task could not be identified.';
    } elseif ($title === '' || mb_strlen($title) > 150) {
        $errorMessage = 'Enter a task title of no more than 150 characters.';
    } elseif ($description === '') {
        $errorMessage = 'Enter a description for the task.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $errorMessage = 'Select a valid task category.';
    } elseif (!in_array($priority, $allowedPriorities, true)) {
        $errorMessage = 'Select a valid priority.';
    } else {
        $dateObject = DateTime::createFromFormat('!Y-m-d', $dueDate);
        $validDueDate = $dateObject && $dateObject->format('Y-m-d') === $dueDate;
        if (!$validDueDate || (!$isUpdate && $dueDate < date('Y-m-d'))) {
            $errorMessage = 'Choose a valid due date' . ($isUpdate ? '.' : ' that is today or later.');
        } else {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start the task assignment transaction: ' . $conn->error);
            }

            $employeeStmt = $conn->prepare(
                "SELECT e.employee_id
                 FROM employee e
                 WHERE e.employee_id = ?
                   AND EXISTS (
                       SELECT 1
                       FROM users u
                       WHERE LOWER(TRIM(u.role)) IN ('hr', 'payroll', 'super', 'employee')
                         AND (
                             LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
                             OR EXISTS (
                                 SELECT 1
                                 FROM attendance a
                                 WHERE a.employee_id = e.employee_id
                                   AND a.user_id = u.id
                             )
                         )
                         AND (
                             SELECT COUNT(*)
                             FROM employee matched_employee
                             WHERE LOWER(TRIM(matched_employee.email)) = LOWER(TRIM(u.email))
                                OR EXISTS (
                                    SELECT 1
                                    FROM attendance matched_attendance
                                    WHERE matched_attendance.employee_id = matched_employee.employee_id
                                      AND matched_attendance.user_id = u.id
                                )
                         ) = 1
                   )
                 LIMIT 1
                 FOR UPDATE"
            );
            if (!$employeeStmt) {
                throw new RuntimeException('Unable to prepare the employee lookup: ' . $conn->error);
            }
            $employeeStmt->bind_param('i', $employeeId);
            if (!$employeeStmt->execute()) {
                $error = $employeeStmt->error;
                $employeeStmt->close();
                throw new RuntimeException('Unable to load the selected employee: ' . $error);
            }
            $assignedEmployee = $employeeStmt->get_result()->fetch_assoc();
            $employeeStmt->close();

            if (!$assignedEmployee) {
                $conn->rollback();
                $errorMessage = 'The selected employee could not be found. Choose another employee.';
            } elseif ($isSupervisor && $employeeId === $currentEmployeeId) {
                $conn->rollback();
                $errorMessage = 'Supervisors cannot assign tasks to themselves. Choose another employee.';
            } else {
                $attendanceStmt = $conn->prepare(
                    'SELECT status
                     FROM attendance
                     WHERE employee_id = ? AND attendance_date = CURDATE()
                     ORDER BY attendance_id DESC
                     LIMIT 1'
                );
                if (!$attendanceStmt) {
                    throw new RuntimeException('Unable to prepare the attendance check: ' . $conn->error);
                }
                $attendanceStmt->bind_param('i', $employeeId);
                if (!$attendanceStmt->execute()) {
                    $error = $attendanceStmt->error;
                    $attendanceStmt->close();
                    throw new RuntimeException('Unable to check attendance: ' . $error);
                }
                $attendanceRecord = $attendanceStmt->get_result()->fetch_assoc();
                $attendanceStmt->close();
                $isPresent = in_array(
                    strtolower(trim((string) ($attendanceRecord['status'] ?? 'absent'))),
                    $presentAttendanceStatuses,
                    true
                );

                $excludedOfficeId = $isUpdate ? (int) $officeId : 0;
                $activeCountStmt = $conn->prepare(
                    "SELECT COUNT(*) AS active_count
                     FROM office
                     WHERE employee_id = ?
                       AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                       AND (? = 0 OR office_id <> ?)"
                );
                if (!$activeCountStmt) {
                    throw new RuntimeException('Unable to prepare the active task count: ' . $conn->error);
                }
                $activeCountStmt->bind_param('iii', $employeeId, $excludedOfficeId, $excludedOfficeId);
                if (!$activeCountStmt->execute()) {
                    $error = $activeCountStmt->error;
                    $activeCountStmt->close();
                    throw new RuntimeException('Unable to count active tasks: ' . $error);
                }
                $activeTaskCount = (int) ($activeCountStmt->get_result()->fetch_assoc()['active_count'] ?? 0);
                $activeCountStmt->close();

                if (!$isPresent) {
                    $conn->rollback();
                    $errorMessage = 'This employee is absent today. Tasks can only be assigned when they are present.';
                } elseif ($activeTaskCount >= $maxActiveTasks) {
                    $conn->rollback();
                    $errorMessage = 'This employee already has the maximum of 8 active tasks.';
                } elseif ($isUpdate) {
                    $activeTaskStmt = $conn->prepare(
                        "SELECT office_id, employee_id
                         FROM office
                         WHERE office_id = ?
                           AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                         LIMIT 1"
                    );
                    if (!$activeTaskStmt) {
                        throw new RuntimeException('Unable to prepare the task lookup: ' . $conn->error);
                    }
                    $activeTaskStmt->bind_param('i', $officeId);
                    if (!$activeTaskStmt->execute()) {
                        $error = $activeTaskStmt->error;
                        $activeTaskStmt->close();
                        throw new RuntimeException('Unable to verify the task: ' . $error);
                    }
                    $existingOfficeTask = $activeTaskStmt->get_result()->fetch_assoc();
                    $taskIsEditable = $existingOfficeTask !== null;
                    $activeTaskStmt->close();

                    if (!$taskIsEditable) {
                        $conn->rollback();
                        $errorMessage = 'This task could not be found or has already been completed.';
                    } else {
                        $updateStmt = $conn->prepare(
                            "UPDATE office
                             SET employee_id = ?, data_type = ?, title = ?, content = ?,
                                 priority_level = ?, due_date = ?
                             WHERE office_id = ?
                               AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')"
                        );
                        if (!$updateStmt) {
                            throw new RuntimeException('Unable to prepare the task update: ' . $conn->error);
                        }
                        $updateStmt->bind_param(
                            'isssssi',
                            $employeeId,
                            $category,
                            $title,
                            $description,
                            $priority,
                            $dueDate,
                            $officeId
                        );
                        if (!$updateStmt->execute()) {
                            $error = $updateStmt->error;
                            $updateStmt->close();
                            throw new RuntimeException('Unable to update the task: ' . $error);
                        }
                        $updateStmt->close();

                        $verifyStmt = $conn->prepare(
                            "SELECT office_id
                             FROM office
                             WHERE office_id = ? AND employee_id = ?
                               AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                             LIMIT 1"
                        );
                        if (!$verifyStmt) {
                            throw new RuntimeException('Unable to prepare task update verification: ' . $conn->error);
                        }
                        $verifyStmt->bind_param('ii', $officeId, $employeeId);
                        if (!$verifyStmt->execute()) {
                            $error = $verifyStmt->error;
                            $verifyStmt->close();
                            throw new RuntimeException('Unable to verify the task update: ' . $error);
                        }
                        $taskWasUpdated = $verifyStmt->get_result()->num_rows > 0;
                        $verifyStmt->close();

                        if (!$taskWasUpdated) {
                            $conn->rollback();
                            $errorMessage = 'This task could not be updated because it may have been completed.';
                        } else {
                            $previousEmployeeId = (int) $existingOfficeTask['employee_id'];
                            if ($previousEmployeeId !== $employeeId) {
                                notifyEmployeeTaskAssignee(
                                    $conn,
                                    $previousEmployeeId,
                                    $currentUserId,
                                    'office_task_reassigned',
                                    'Task reassigned',
                                    'The task "' . $title . '" has been assigned to another employee.',
                                    'office_employee.php',
                                    'office',
                                    (string) $officeId
                                );
                                notifyEmployeeTaskAssignee(
                                    $conn,
                                    $employeeId,
                                    $currentUserId,
                                    'office_task_assigned',
                                    'Task assigned to you',
                                    'A new task, "' . $title . '", has been assigned to you.',
                                    'office_employee.php?task_id=' . (int) $officeId,
                                    'office',
                                    (string) $officeId
                                );
                            }
                            recordSystemAudit(
                                $conn,
                                $currentUserId,
                                (string) ($_SESSION['name'] ?? 'TaskTrack user'),
                                'office_task_updated',
                                'office_task',
                                (int) $officeId,
                                'Updated task "' . $title . '" assigned to employee #' . $employeeId . '.'
                            );
                            if (!$conn->commit()) {
                                throw new RuntimeException('Unable to save the task update: ' . $conn->error);
                            }
                            $_SESSION['office_task_flash'] = 'Task updated and reassigned successfully.';
                            header('Location: employee.php?employee_id=' . $employeeId);
                            exit();
                        }
                    }
                } else {
                    $insertStmt = $conn->prepare(
                        "INSERT INTO office
                            (supervisor_id, employee_id, data_type, title, content, status, priority_level, due_date)
                         VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)"
                    );
                    if (!$insertStmt) {
                        throw new RuntimeException('Unable to prepare the task assignment: ' . $conn->error);
                    }
                    $insertStmt->bind_param(
                        'iisssss',
                        $currentUserId,
                        $employeeId,
                        $category,
                        $title,
                        $description,
                        $priority,
                        $dueDate
                    );
                    if (!$insertStmt->execute()) {
                        $error = $insertStmt->error;
                        $insertStmt->close();
                        throw new RuntimeException('Unable to assign task: ' . $error);
                    }
                    $insertStmt->close();
                    $assignedOfficeId = (int) $conn->insert_id;
                    notifyEmployeeTaskAssignee(
                        $conn,
                        $employeeId,
                        $currentUserId,
                        'office_task_assigned',
                        'Task assigned to you',
                        'A new task, "' . $title . '", has been assigned to you.',
                        'office_employee.php?task_id=' . $assignedOfficeId,
                        'office',
                        (string) $assignedOfficeId
                    );
                    recordSystemAudit(
                        $conn,
                        $currentUserId,
                        (string) ($_SESSION['name'] ?? 'TaskTrack user'),
                        'office_task_created',
                        'office_task',
                        $assignedOfficeId,
                        'Assigned task "' . $title . '" to employee #' . $employeeId . '.'
                    );
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the task assignment: ' . $conn->error);
                    }
                    $_SESSION['office_task_flash'] = 'Task assigned successfully.';
                    header('Location: employee.php?employee_id=' . $employeeId);
                    exit();
                }
            }
        }
    }
}

$employees = [];
$employeeResult = $conn->query(
    "SELECT u.id AS account_id, u.name AS username, u.email AS account_email, u.role AS account_role,
            e.employee_id, e.position, e.contact_number, e.email, e.address,
            (
                SELECT a.status
                FROM attendance a
                WHERE a.employee_id = e.employee_id
                  AND a.attendance_date = CURDATE()
                ORDER BY a.attendance_id DESC
                LIMIT 1
            ) AS today_attendance_status,
            (
                SELECT COUNT(*)
                FROM office o
                WHERE o.employee_id = e.employee_id
                  AND LOWER(TRIM(COALESCE(o.status, 'pending'))) NOT IN ('completed', 'complete', 'done')
            ) AS active_task_count
     FROM users u
     LEFT JOIN employee e
       ON (
           LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
           OR EXISTS (
               SELECT 1
               FROM attendance a
               WHERE a.employee_id = e.employee_id
                 AND a.user_id = u.id
           )
       )
      AND (
          SELECT COUNT(*)
          FROM employee matched_employee
          WHERE LOWER(TRIM(matched_employee.email)) = LOWER(TRIM(u.email))
             OR EXISTS (
                 SELECT 1
                 FROM attendance matched_attendance
                 WHERE matched_attendance.employee_id = matched_employee.employee_id
                   AND matched_attendance.user_id = u.id
             )
      ) = 1
     WHERE LOWER(TRIM(u.role)) IN ('hr', 'payroll', 'super', 'employee')
     ORDER BY u.name ASC, u.id ASC"
);
if (!$employeeResult) {
    throw new RuntimeException('Unable to load user accounts: ' . $conn->error);
}
while ($employee = $employeeResult->fetch_assoc()) {
    $employees[] = $employee;
}
$selectedAccountId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: 0;
$selectedEmployeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
if ($errorMessage !== '' && isset($_POST['employee_id'])) {
    $selectedEmployeeId = filter_var($_POST['employee_id'], FILTER_VALIDATE_INT);
}
$selectedAccount = null;
if ($selectedAccountId > 0) {
    foreach ($employees as $account) {
        if ((int) $account['account_id'] === $selectedAccountId) {
            $selectedAccount = $account;
            break;
        }
    }
}
if (!$selectedAccount && $selectedEmployeeId) {
    foreach ($employees as $account) {
        if ((int) ($account['employee_id'] ?? 0) === $selectedEmployeeId) {
            $selectedAccount = $account;
            break;
        }
    }
}
if (!$selectedAccount) {
    $selectedAccount = $employees[0] ?? null;
}
$selectedEmployee = null;
if ($selectedAccount && !empty($selectedAccount['employee_id'])) {
    $selectedEmployee = $selectedAccount;
    $selectedEmployeeId = (int) $selectedAccount['employee_id'];
} else {
    $selectedEmployeeId = 0;
}
$selectedAttendanceStatus = strtolower(trim((string) ($selectedEmployee['today_attendance_status'] ?? 'absent')));
$selectedIsPresent = in_array($selectedAttendanceStatus, $presentAttendanceStatuses, true);
$selectedActiveTaskCount = (int) ($selectedEmployee['active_task_count'] ?? 0);
$selectedIsCurrentSupervisor = $isSupervisor
    && $currentEmployeeId > 0
    && $selectedEmployeeId === $currentEmployeeId;
$canAssignTask = $selectedEmployee !== null
    && !$selectedIsCurrentSupervisor
    && $selectedIsPresent
    && $selectedActiveTaskCount < $maxActiveTasks;

$tasks = ['completed' => [], 'pending' => [], 'overdue' => []];
if ($selectedEmployee) {
    $taskStmt = $conn->prepare(
        'SELECT office_id, data_type, title, content, status, priority_level, due_date
         FROM office
         WHERE employee_id = ?
         ORDER BY due_date IS NULL ASC, due_date ASC, submission_date DESC, office_id DESC'
    );
    if (!$taskStmt) {
        throw new RuntimeException('Unable to prepare the employee task list: ' . $conn->error);
    }
    $taskStmt->bind_param('i', $selectedEmployeeId);
    if (!$taskStmt->execute()) {
        $error = $taskStmt->error;
        $taskStmt->close();
        throw new RuntimeException('Unable to load employee tasks: ' . $error);
    }
    $taskResult = $taskStmt->get_result();
    while ($task = $taskResult->fetch_assoc()) {
        $normalizedStatus = strtolower(trim((string) ($task['status'] ?? 'pending')));
        if (in_array($normalizedStatus, ['completed', 'complete', 'done'], true)) {
            $tasks['completed'][] = $task;
        } elseif (!empty($task['due_date']) && $task['due_date'] < date('Y-m-d')) {
            $tasks['overdue'][] = $task;
        } else {
            $tasks['pending'][] = $task;
        }
    }
    $taskStmt->close();
}

$formTitle = (string) ($_POST['title'] ?? '');
$formDescription = (string) ($_POST['description'] ?? '');
$formCategory = (string) ($_POST['category'] ?? '');
$formPriority = (string) ($_POST['priority'] ?? 'medium');
$formDueDate = (string) ($_POST['due_date'] ?? date('Y-m-d'));
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$backUrl = $_SESSION['role'] === 'HR' ? 'HR.php' : 'supervisor.php';
$roleLabel = $_SESSION['role'] === 'HR' ? 'Human Resources' : 'Operations Supervisor';
$accountRoleLabels = [
    'hr' => 'HR',
    'payroll' => 'Payroll',
    'super' => 'Supervisor',
    'employee' => 'Employee',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Tasks | TaskTrack</title>
    <link rel="stylesheet" href="css/employee.css?v=<?= filemtime(__DIR__ . '/css/employee.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <link rel="stylesheet" href="css/task-form-controls.css?v=<?= filemtime(__DIR__ . '/css/task-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
</head>
<body>
<div class="employee-app">
    <?php $tasktrackActive = 'employee'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>

    <main class="employee-main">
        <header class="page-topbar">
            <div class="date-stamp"><?= $escape(date('M j, Y')); ?><span><?= $escape(date('g:i A')); ?></span></div>
            <div class="account-chip"><span class="account-dot"></span><span><strong><?= $escape($_SESSION['name'] ?? 'TaskTrack User'); ?></strong><small><?= $escape($roleLabel); ?></small></span><a href="logout.php" aria-label="Log out">⌄</a></div>
        </header>

        <?php if ($flashMessage !== ''): ?>
            <div class="notice is-success" role="status"><?= $escape($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="notice is-error" role="alert"><?= $escape($errorMessage); ?></div>
        <?php endif; ?>

        <div class="workspace">
            <aside class="employee-directory">
                <div class="directory-heading">
                    <div><span class="eyebrow">USER ACCOUNTS</span><h1>User Accounts</h1></div>
                    <span class="employee-total"><?= count($employees); ?></span>
                </div>
                <label class="employee-search">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" id="employeeSearch" placeholder="Search user accounts" autocomplete="off" aria-label="Search user accounts">
                </label>
                <div class="directory-columns"><span>NAME</span><span>ACCOUNT ROLE</span></div>
                <nav class="employee-list" id="employeeList" aria-label="User accounts">
                    <?php foreach ($employees as $account): ?>
                        <?php
                            $employeeName = trim((string) ($account['username'] ?? 'Unnamed account'));
                            $accountRole = strtolower(trim((string) ($account['account_role'] ?? '')));
                            $accountRoleLabel = $accountRoleLabels[$accountRole] ?? ucfirst($accountRole);
                            $hasEmployeeProfile = !empty($account['employee_id']);
                            $accountAttendanceStatus = strtolower(trim((string) ($account['today_attendance_status'] ?? 'absent')));
                            $accountIsPresent = $hasEmployeeProfile
                                && in_array($accountAttendanceStatus, $presentAttendanceStatuses, true);
                            $isSelected = $selectedAccount && (int) $account['account_id'] === (int) $selectedAccount['account_id'];
                        ?>
                        <a
                            class="employee-list-item<?= $isSelected ? ' is-selected' : ''; ?>"
                            href="employee.php?user_id=<?= (int) $account['account_id']; ?>"
                            data-search="<?= $escape(strtolower($employeeName . ' ' . ($account['position'] ?? '') . ' ' . $accountRoleLabel)); ?>"
                            <?= $isSelected ? 'aria-current="page"' : ''; ?>
                        >
                            <span class="list-avatar"><?= $escape(strtoupper(mb_substr($employeeName, 0, 1))); ?></span>
                            <span class="list-name">
                                <?= $escape($employeeName); ?>
                                <small><?= $hasEmployeeProfile ? $escape($account['position'] ?? 'Employee') : 'Profile link needs review'; ?></small>
                                <?php if ($hasEmployeeProfile): ?>
                                    <small class="account-attendance <?= $accountIsPresent ? 'is-present' : 'is-absent'; ?>">
                                        <?= $accountIsPresent ? 'Present' : 'Absent'; ?>
                                        · <?= (int) $account['active_task_count']; ?>/<?= $maxActiveTasks; ?> active tasks
                                    </small>
                                <?php endif; ?>
                            </span>
                            <span class="list-position"><?= $escape($accountRoleLabel); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <p class="directory-empty" id="directoryEmpty" <?= $employees !== [] ? 'hidden' : ''; ?>>
                        <?= $employees === [] ? 'No matching user accounts are on record.' : 'No accounts match your search.'; ?>
                    </p>
                </nav>
            </aside>

            <section class="employee-detail">
                <?php if ($selectedEmployee): ?>
                    <?php
                        $selectedName = trim((string) ($selectedEmployee['username'] ?? 'Unnamed employee'));
                    ?>
                    <section class="profile-card" aria-label="Employee profile">
                        <div class="profile-avatar"><?= $escape(strtoupper(mb_substr($selectedName, 0, 1))); ?></div>
                        <div class="profile-info">
                            <h2><?= $escape($selectedName); ?></h2>
                            <p><?= $escape($selectedEmployee['position'] ?? 'Employee'); ?> <span class="profile-divider">·</span> ID #<?= (int) $selectedEmployee['employee_id']; ?></p>
                            <div class="employee-assignment-status">
                                <span class="attendance-badge <?= $selectedIsPresent ? 'is-present' : 'is-absent'; ?>">
                                    <?= $selectedIsPresent ? 'Present today' : 'Absent today'; ?>
                                </span>
                                <span class="active-task-count">
                                    <?= $selectedActiveTaskCount; ?> / <?= $maxActiveTasks; ?> active tasks
                                </span>
                            </div>
                        </div>
                        <dl class="profile-facts">
                            <div><dt>Location</dt><dd><?= $escape($selectedEmployee['address'] ?? 'Not provided'); ?></dd></div>
                            <div><dt>Email</dt><dd><?= $escape($selectedEmployee['email'] ?? 'Not provided'); ?></dd></div>
                            <div><dt>Phone</dt><dd><?= $escape($selectedEmployee['contact_number'] ?? 'Not provided'); ?></dd></div>
                        </dl>
                    </section>

                    <div class="task-heading">
                        <div><span class="eyebrow">WORK OVERVIEW</span><h2>Task progress</h2></div>
                        <button
                            class="assign-button"
                            id="openTaskModal"
                            type="button"
                            <?= !$canAssignTask ? 'disabled title="' . ($selectedIsCurrentSupervisor ? 'Supervisors cannot assign tasks to themselves.' : (!$selectedIsPresent ? 'Employee must be present today to receive tasks.' : 'The 8 active-task limit has been reached.')) . '"' : ''; ?>
                        >＋ Assign a task</button>
                    </div>
                    <?php if (!$canAssignTask): ?>
                        <p class="assignment-limit-message" role="status">
                            <?= $selectedIsCurrentSupervisor
                                ? 'Supervisors cannot assign tasks to themselves. Select another employee to create an assignment.'
                                : (!$selectedIsPresent
                                    ? 'Task assignment is unavailable because this employee is absent today.'
                                    : 'Task assignment is unavailable because this employee already has 8 active tasks.'); ?>
                        </p>
                    <?php endif; ?>

                    <section class="task-columns" aria-label="Employee task status">
                        <?php
                        $columnLabels = [
                            'completed' => ['Completed', 'is-completed'],
                            'pending' => ['Pending', 'is-pending'],
                            'overdue' => ['Overdue', 'is-overdue'],
                        ];
                        foreach ($columnLabels as $group => [$label, $colorClass]):
                        ?>
                            <article class="task-column <?= $colorClass; ?>">
                                <header class="task-column-header"><h3><?= $escape($label); ?></h3><span><?= count($tasks[$group]); ?></span></header>
                                <div class="task-list">
                                    <?php if ($tasks[$group] === []): ?>
                                        <p class="task-empty">No <?= strtolower($label); ?> tasks.</p>
                                    <?php else: ?>
                                        <?php foreach ($tasks[$group] as $task): ?>
                                            <?php $taskIsEditable = $group !== 'completed'; ?>
                                            <article class="task-card">
                                                <span class="task-marker" aria-hidden="true"></span>
                                                <div class="task-copy">
                                                    <h4><?= $escape($task['title'] ?? 'Untitled task'); ?></h4>
                                                    <p><?= $escape($task['content'] ?? ''); ?></p>
                                                    <div class="task-meta"><span><?= $escape($task['due_date'] ?: 'No due date'); ?></span><span class="priority-tag priority-<?= $escape(strtolower($task['priority_level'] ?? 'medium')); ?>"><?= $escape(ucfirst($task['priority_level'] ?? 'Medium')); ?></span></div>
                                                    <?php if ($taskIsEditable && !$selectedIsCurrentSupervisor): ?>
                                                        <button
                                                            class="task-edit-button"
                                                            type="button"
                                                            data-office-id="<?= (int) $task['office_id']; ?>"
                                                            data-title="<?= $escape($task['title'] ?? ''); ?>"
                                                            data-content="<?= $escape($task['content'] ?? ''); ?>"
                                                            data-category="<?= $escape($task['data_type'] ?? 'General'); ?>"
                                                            data-priority="<?= $escape(strtolower($task['priority_level'] ?? 'medium')); ?>"
                                                            data-due-date="<?= $escape($task['due_date'] ?? ''); ?>"
                                                        >Edit / reassign</button>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php else: ?>
                    <section class="no-selection">
                        <span class="no-selection-mark">♙</span>
                        <?php if ($selectedAccount): ?>
                            <h2>Employee profile not linked</h2>
                            <p><?= $escape($selectedAccount['username'] ?? 'This user account'); ?> has a <?= $escape($accountRoleLabels[strtolower((string) $selectedAccount['account_role'])] ?? 'system'); ?> account without a unique employee profile link. Ask HR to resolve the employee record before assigning tasks.</p>
                        <?php else: ?>
                            <h2>Select a user account</h2>
                            <p>Choose an account from the directory to view its employee profile and task progress.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php if ($selectedEmployee): ?>
<dialog class="task-dialog" id="taskDialog" aria-labelledby="taskDialogTitle">
    <form method="post" class="task-form">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['office_task_csrf']); ?>">
        <input type="hidden" name="employee_id" value="<?= (int) $selectedEmployeeId; ?>">
        <div class="dialog-heading">
            <div><span class="eyebrow">NEW ASSIGNMENT</span><h2 id="taskDialogTitle">Task information</h2></div>
            <button class="dialog-close" id="closeTaskModal" type="button" aria-label="Close">×</button>
        </div>
        <p class="assigning-to">Assigning to <strong><?= $escape($selectedName); ?></strong></p>
        <label class="form-field">Task title<input type="text" name="title" maxlength="150" placeholder="Enter task title" value="<?= $escape($formTitle); ?>" required></label>
        <label class="form-field">Description<textarea name="description" rows="4" placeholder="Describe the task" required><?= $escape($formDescription); ?></textarea></label>
        <div class="form-grid">
            <label class="form-field">Category
                <select name="category" required>
                    <option value="" disabled <?= $formCategory === '' ? 'selected' : ''; ?>>Select category</option>
                    <?php foreach ($allowedCategories as $categoryOption): ?>
                        <option value="<?= $escape($categoryOption); ?>" <?= $formCategory === $categoryOption ? 'selected' : ''; ?>><?= $escape($categoryOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">Due date<input type="date" class="calendar-control" name="due_date" min="<?= $escape(date('Y-m-d')); ?>" value="<?= $escape($formDueDate); ?>" required></label>
        </div>
        <fieldset class="priority-field">
            <legend>Priority</legend>
            <div class="priority-options">
                <label><input type="radio" name="priority" value="low" <?= $formPriority === 'low' ? 'checked' : ''; ?>><span>Low</span></label>
                <label><input type="radio" name="priority" value="medium" <?= $formPriority === 'medium' ? 'checked' : ''; ?>><span>Medium</span></label>
                <label><input type="radio" name="priority" value="high" <?= $formPriority === 'high' ? 'checked' : ''; ?>><span>High</span></label>
                <label><input type="radio" name="priority" value="urgent" <?= $formPriority === 'urgent' ? 'checked' : ''; ?>><span>Urgent</span></label>
            </div>
        </fieldset>
        <div class="dialog-actions">
            <button class="cancel-button" id="cancelTaskModal" type="button">Cancel</button>
            <button class="submit-button" type="submit" name="create_task" value="1">Create task</button>
        </div>
    </form>
</dialog>
<dialog class="task-dialog" id="editTaskDialog" aria-labelledby="editTaskDialogTitle">
    <form method="post" class="task-form">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['office_task_csrf']); ?>">
        <input type="hidden" name="office_id" id="editOfficeId">
        <div class="dialog-heading">
            <div><span class="eyebrow">UPDATE ASSIGNMENT</span><h2 id="editTaskDialogTitle">Edit task</h2></div>
            <button class="dialog-close" id="closeEditTaskModal" type="button" aria-label="Close">×</button>
        </div>
        <label class="form-field">Assigned employee
            <select name="employee_id" id="editEmployeeId" required>
                <option value="" disabled>Select employee</option>
                <?php foreach ($employees as $employee): ?>
                    <?php if (!$isSupervisor || (int) ($employee['employee_id'] ?? 0) !== $currentEmployeeId): ?>
                        <option value="<?= (int) $employee['employee_id']; ?>"><?= $escape($employee['username'] ?? 'Unnamed employee'); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Task title<input type="text" name="title" id="editTaskTitle" maxlength="150" required></label>
        <label class="form-field">Description<textarea name="description" id="editTaskDescription" rows="4" required></textarea></label>
        <div class="form-grid">
            <label class="form-field">Category
                <select name="category" id="editTaskCategory" required>
                    <?php foreach ($allowedCategories as $categoryOption): ?>
                        <option value="<?= $escape($categoryOption); ?>"><?= $escape($categoryOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">Due date<input type="date" class="calendar-control" name="due_date" id="editTaskDueDate" required></label>
        </div>
        <fieldset class="priority-field">
            <legend>Priority</legend>
            <div class="priority-options">
                <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $priorityValue => $priorityLabel): ?>
                    <label><input type="radio" name="priority" value="<?= $priorityValue; ?>" required><span><?= $priorityLabel; ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="dialog-actions">
            <button class="cancel-button" id="cancelEditTaskModal" type="button">Cancel</button>
            <button class="submit-button" type="submit" name="update_task" value="1">Save changes</button>
        </div>
    </form>
</dialog>
<script>
    const taskDialog = document.getElementById('taskDialog');
    const editTaskDialog = document.getElementById('editTaskDialog');
    document.getElementById('openTaskModal').addEventListener('click', () => taskDialog.showModal());
    document.getElementById('closeTaskModal').addEventListener('click', () => taskDialog.close());
    document.getElementById('cancelTaskModal').addEventListener('click', () => taskDialog.close());
    taskDialog.addEventListener('click', (event) => {
        if (event.target === taskDialog) taskDialog.close();
    });
    document.querySelectorAll('.task-edit-button').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('editOfficeId').value = button.dataset.officeId;
            const editEmployeeSelect = document.getElementById('editEmployeeId');
            editEmployeeSelect.value = '<?= (int) $selectedEmployeeId; ?>';
            document.getElementById('editTaskTitle').value = button.dataset.title;
            document.getElementById('editTaskDescription').value = button.dataset.content;
            document.getElementById('editTaskCategory').value = button.dataset.category;
            document.getElementById('editTaskDueDate').value = button.dataset.dueDate;
            document.querySelectorAll('#editTaskDialog input[name="priority"]').forEach((input) => {
                input.checked = input.value === button.dataset.priority;
            });
            document.getElementById('editTaskDueDate').dispatchEvent(new Event('change', { bubbles: true }));
            editTaskDialog.showModal();
        });
    });
    document.getElementById('closeEditTaskModal').addEventListener('click', () => editTaskDialog.close());
    document.getElementById('cancelEditTaskModal').addEventListener('click', () => editTaskDialog.close());
    editTaskDialog.addEventListener('click', (event) => {
        if (event.target === editTaskDialog) editTaskDialog.close();
    });
    if (<?= json_encode($errorMessage !== '' && isset($_POST['create_task'])); ?>) taskDialog.showModal();
    if (<?= json_encode($errorMessage !== '' && isset($_POST['update_task'])); ?>) {
        document.getElementById('editOfficeId').value = <?= json_encode((string) ($_POST['office_id'] ?? '')); ?>;
        document.getElementById('editEmployeeId').value = <?= json_encode((string) ($_POST['employee_id'] ?? '')); ?>;
        document.getElementById('editTaskTitle').value = <?= json_encode((string) ($_POST['title'] ?? '')); ?>;
        document.getElementById('editTaskDescription').value = <?= json_encode((string) ($_POST['description'] ?? '')); ?>;
        document.getElementById('editTaskCategory').value = <?= json_encode((string) ($_POST['category'] ?? 'General')); ?>;
        document.getElementById('editTaskDueDate').value = <?= json_encode((string) ($_POST['due_date'] ?? '')); ?>;
        document.querySelectorAll('#editTaskDialog input[name="priority"]').forEach((input) => {
            input.checked = input.value === <?= json_encode((string) ($_POST['priority'] ?? 'medium')); ?>;
        });
        editTaskDialog.showModal();
    }

</script>
<?php endif; ?>
<script>
    const employeeSearch = document.getElementById('employeeSearch');
    const employeeItems = [...document.querySelectorAll('.employee-list-item')];
    const directoryEmpty = document.getElementById('directoryEmpty');
    const filterEmployees = () => {
        const query = employeeSearch.value.trim().toLowerCase();
        let visibleCount = 0;
        employeeItems.forEach((item) => {
            const visible = item.dataset.search.includes(query);
            item.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        if (employeeItems.length > 0) {
            directoryEmpty.hidden = visibleCount !== 0;
            if (visibleCount === 0) directoryEmpty.textContent = 'No employees match these filters.';
        }
    };
    employeeSearch.addEventListener('input', filterEmployees);
</script>
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
</body>
</html>
