<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
require_once __DIR__ . '/config/task_notifications.php';
date_default_timezone_set('Asia/Manila');

$allowedRoles = ['HR', 'payroll', 'super', 'employee'];
if (!isset($_SESSION['id'], $_SESSION['email'], $_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
    header('Location: index.php');
    exit();
}
$userId = (int) $_SESSION['id'];
$accountRole = (string) $_SESSION['role'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');
ensureTaskNotificationSchema($conn);

if (empty($_SESSION['office_employee_csrf'])) {
    $_SESSION['office_employee_csrf'] = bin2hex(random_bytes(32));
}

$accountLinkError = '';
$employee = null;
$matchStmt = $conn->prepare(
    "SELECT e.employee_id, e.username, e.position
     FROM employee e
     JOIN users u ON u.id = ?
     WHERE (u.employee_id IS NOT NULL AND e.employee_id = u.employee_id)
        OR (
            u.employee_id IS NULL
            AND (
                EXISTS (
                    SELECT 1 FROM attendance a
                    WHERE a.employee_id = e.employee_id AND a.user_id = u.id
                )
                OR (e.email IS NOT NULL AND e.email <> '' AND LOWER(TRIM(e.email)) = LOWER(TRIM(u.email)))
            )
        )
     ORDER BY e.employee_id ASC"
);
if (!$matchStmt) {
    throw new RuntimeException('Unable to prepare your employee profile lookup: ' . $conn->error);
}
$matchStmt->bind_param('i', $userId);
if (!$matchStmt->execute()) {
    $error = $matchStmt->error;
    $matchStmt->close();
    throw new RuntimeException('Unable to look up your employee profile: ' . $error);
}
$matchResult = $matchStmt->get_result();
$matches = [];
while ($match = $matchResult->fetch_assoc()) {
    $matches[] = $match;
}
$matchStmt->close();

if ($matches === []) {
    $accountLinkError = 'This login is not linked to an employee profile yet. Please ask HR to check the employee record and account email.';
} elseif (count($matches) > 1) {
    $accountLinkError = 'More than one employee profile is linked to this account. Please ask HR to resolve the employee account link before viewing tasks.';
} else {
    $employee = $matches[0];
}

$message = '';
$errorMessage = '';
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'pending', 'review', 'changes_requested', 'completed', 'overdue'], true)) {
    $statusFilter = 'all';
}
$periodFilter = strtolower(trim((string) ($_GET['period'] ?? 'all')));
if (!in_array($periodFilter, ['week', 'all'], true)) {
    $periodFilter = 'week';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $employee !== null) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $taskId = filter_var($_POST['office_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string) ($_POST['task_action'] ?? '');

    if (!hash_equals($_SESSION['office_employee_csrf'], $postedToken)) {
        $errorMessage = 'Your session token expired. Refresh the page and try again.';
    } elseif (!$taskId || $taskId < 1) {
        $errorMessage = 'The selected task could not be identified.';
    } elseif (!in_array($action, ['save_progress', 'mark_complete'], true)) {
        $errorMessage = 'Choose a valid task action.';
    } else {
        $progress = $action === 'mark_complete'
            ? 100
            : filter_var($_POST['progress'] ?? null, FILTER_VALIDATE_INT);
        if ($progress === false || $progress < 0 || ($action === 'save_progress' && $progress > 100)) {
            $errorMessage = 'Progress must be between 0 and 100%. Use Mark as complete when the task is finished.';
        } else {
            $taskContextStmt = $conn->prepare(
                "SELECT supervisor_id, progress, title, completion_review_status
                 FROM office
                 WHERE office_id = ? AND employee_id = ?
                   AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                   AND completion_review_status <> 'pending_review'
                 LIMIT 1"
            );
            if (!$taskContextStmt) {
                throw new RuntimeException('Unable to prepare task notification context: ' . $conn->error);
            }
            $taskContextStmt->bind_param('ii', $taskId, $employee['employee_id']);
            if (!$taskContextStmt->execute()) {
                $error = $taskContextStmt->error;
                $taskContextStmt->close();
                throw new RuntimeException('Unable to load task notification context: ' . $error);
            }
            $taskContext = $taskContextStmt->get_result()->fetch_assoc();
            $taskContextStmt->close();
            $supervisorId = (int) ($taskContext['supervisor_id'] ?? 0);
            $previousProgress = (int) ($taskContext['progress'] ?? 0);
            $taskTitle = (string) ($taskContext['title'] ?? 'assigned task');
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start task update: ' . $conn->error);
            }
            $newStatus = 'pending';
            $newReviewStatus = $action === 'mark_complete' ? 'pending_review' : 'none';
            $updateStmt = $conn->prepare(
                "UPDATE office
                 SET progress = ?, status = ?, completion_review_status = ?,
                     completion_review_note = NULL, completion_reviewed_by = NULL,
                     completion_reviewed_at = NULL
                 WHERE office_id = ? AND employee_id = ?
                   AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                   AND completion_review_status <> 'pending_review'"
            );
            if (!$updateStmt) {
                $conn->rollback();
                throw new RuntimeException('Unable to prepare the task update: ' . $conn->error);
            }
            $updateStmt->bind_param('issii', $progress, $newStatus, $newReviewStatus, $taskId, $employee['employee_id']);
            if (!$updateStmt->execute()) {
                $error = $updateStmt->error;
                $updateStmt->close();
                $conn->rollback();
                throw new RuntimeException('Unable to update the task: ' . $error);
            }
            $changed = $updateStmt->affected_rows;
            $updateStmt->close();
            $taskStillActive = $changed > 0;
            if (!$taskStillActive) {
                $verifyStmt = $conn->prepare(
                    "SELECT office_id
                     FROM office
                     WHERE office_id = ? AND employee_id = ?
                       AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')
                       AND completion_review_status <> 'pending_review'
                     LIMIT 1"
                );
                if (!$verifyStmt) {
                    $conn->rollback();
                    throw new RuntimeException('Unable to verify the task update: ' . $conn->error);
                }
                $verifyStmt->bind_param('ii', $taskId, $employee['employee_id']);
                if (!$verifyStmt->execute()) {
                    $error = $verifyStmt->error;
                    $verifyStmt->close();
                    $conn->rollback();
                    throw new RuntimeException('Unable to verify the task update: ' . $error);
                }
                $taskStillActive = $verifyStmt->get_result()->num_rows > 0;
                $verifyStmt->close();
            }
            if (!$taskStillActive) {
                $conn->rollback();
                $errorMessage = 'This task is not assigned to your account, is waiting for review, or has already been completed.';
            } else {
                try {
                    if ($supervisorId > 0 && ($action === 'mark_complete' || $previousProgress !== $progress)) {
                        createTaskNotification(
                            $conn,
                            $supervisorId,
                            $userId,
                            $action === 'mark_complete' ? 'office_task_review_requested' : 'office_task_progress',
                            $action === 'mark_complete' ? 'Task submitted for review' : 'Task progress updated',
                            ((string) ($_SESSION['name'] ?? 'The assigned employee')) . ($action === 'mark_complete'
                                ? ' submitted "' . $taskTitle . '" for supervisor review.'
                                : ' updated "' . $taskTitle . '" to ' . $progress . '%.'),
                            'employee.php?employee_id=' . (int) $employee['employee_id'],
                            'office',
                            (string) $taskId
                        );
                    }
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the task update: ' . $conn->error);
                    }
                    $_SESSION['office_employee_flash'] = $action === 'mark_complete'
                        ? 'Task submitted for supervisor review.'
                        : 'Task progress updated.';
                    header('Location: office_employee.php?status=' . rawurlencode($statusFilter)
                        . '&period=' . rawurlencode($periodFilter) . '&task_id=' . $taskId);
                    exit();
                } catch (RuntimeException $exception) {
                    $conn->rollback();
                    throw $exception;
                }
            }
        }
    }
}

$message = (string) ($_SESSION['office_employee_flash'] ?? '');
unset($_SESSION['office_employee_flash']);
$tasks = [];
$selectedTaskId = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT) ?: 0;
if ($employee !== null) {
    $taskStmt = $conn->prepare(
        'SELECT office_id, data_type, title, content, status, priority_level,
                due_date, progress, completion_review_status, completion_review_note,
                completion_reviewed_at, assigned_user.name AS assigned_by_name,
                reviewed_user.name AS completion_reviewed_by_name,
                submission_date, updated_at
         FROM office
         LEFT JOIN users assigned_user ON assigned_user.id = office.supervisor_id
         LEFT JOIN users reviewed_user ON reviewed_user.id = office.completion_reviewed_by
         WHERE office.employee_id = ?
         ORDER BY office.due_date IS NULL ASC, office.due_date ASC, office.submission_date DESC, office.office_id DESC'
    );
    if (!$taskStmt) {
        throw new RuntimeException('Unable to prepare your task list: ' . $conn->error);
    }
    $taskStmt->bind_param('i', $employee['employee_id']);
    if (!$taskStmt->execute()) {
        $error = $taskStmt->error;
        $taskStmt->close();
        throw new RuntimeException('Unable to load your tasks: ' . $error);
    }
    $taskResult = $taskStmt->get_result();
    while ($task = $taskResult->fetch_assoc()) {
        $task['progress'] = max(0, min(100, (int) ($task['progress'] ?? 0)));
        $normalizedStatus = strtolower(trim((string) ($task['status'] ?? 'pending')));
        if (in_array($normalizedStatus, ['completed', 'complete', 'done'], true)) {
            $task['display_status'] = 'completed';
            $task['progress'] = 100;
        } elseif (($task['completion_review_status'] ?? '') === 'pending_review') {
            $task['display_status'] = 'review';
        } elseif (($task['completion_review_status'] ?? '') === 'rejected') {
            $task['display_status'] = 'changes_requested';
        } elseif (!empty($task['due_date']) && $task['due_date'] < $today) {
            $task['display_status'] = 'overdue';
        } else {
            $task['display_status'] = 'pending';
        }
        $tasks[] = $task;
    }
    $taskStmt->close();

    $historyStmt = $conn->prepare(
        "SELECT audit.subject_id AS office_id, audit.actor_name, audit.action_type,
                audit.details, audit.occurred_at
         FROM system_audit_log audit
         INNER JOIN office ON office.office_id = audit.subject_id
         WHERE audit.subject_type = 'office_task'
           AND office.employee_id = ?
         ORDER BY audit.occurred_at ASC, audit.audit_id ASC"
    );
    if (!$historyStmt) {
        throw new RuntimeException('Unable to prepare task history: ' . $conn->error);
    }
    $historyStmt->bind_param('i', $employee['employee_id']);
    if (!$historyStmt->execute()) {
        $error = $historyStmt->error;
        $historyStmt->close();
        throw new RuntimeException('Unable to load task history: ' . $error);
    }
    $historyResult = $historyStmt->get_result();
    $taskHistory = [];
    while ($event = $historyResult->fetch_assoc()) {
        $taskHistory[(int) $event['office_id']][] = $event;
    }
    $historyStmt->close();

    foreach ($tasks as &$task) {
        $officeId = (int) $task['office_id'];
        $events = $taskHistory[$officeId] ?? [];
        $task['review_history'] = array_values(array_filter(
            $events,
            static fn (array $event): bool => in_array(
                $event['action_type'],
                ['office_task_approved', 'office_task_changes_requested'],
                true
            )
        ));
        foreach ($task['review_history'] as &$reviewEvent) {
            $reviewEvent['review_label'] = $reviewEvent['action_type'] === 'office_task_approved'
                ? 'Approved by'
                : 'Changes requested by';
            $reviewEvent['review_note'] = null;
            $noteMarker = 'Review note: ';
            $notePosition = strpos((string) $reviewEvent['details'], $noteMarker);
            if ($notePosition !== false) {
                $reviewEvent['review_note'] = substr(
                    (string) $reviewEvent['details'],
                    $notePosition + strlen($noteMarker)
                );
            }
        }
        unset($reviewEvent);
    }
    unset($task);
}

$counts = ['overdue' => 0, 'pending' => 0, 'review' => 0, 'changes_requested' => 0, 'completed' => 0];
foreach ($tasks as $task) {
    $counts[$task['display_status']]++;
}
$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$weekEnd = (new DateTimeImmutable('sunday this week'))->format('Y-m-d');
$visibleTasks = array_values(array_filter($tasks, static function (array $task) use ($statusFilter, $periodFilter, $weekStart, $weekEnd): bool {
    if ($statusFilter !== 'all' && $task['display_status'] !== $statusFilter) {
        return false;
    }
    if ($periodFilter === 'week' && !empty($task['due_date']) && ($task['due_date'] < $weekStart || $task['due_date'] > $weekEnd)) {
        return false;
    }
    return $periodFilter === 'all' || !empty($task['due_date']);
}));
$historyTasks = array_values(array_filter(
    $visibleTasks,
    static fn (array $task): bool => $task['display_status'] === 'completed'
));
$visibleTasks = array_values(array_filter(
    $visibleTasks,
    static fn (array $task): bool => $task['display_status'] !== 'completed'
));
$selectedTask = null;
foreach ($tasks as $task) {
    if ((int) $task['office_id'] === $selectedTaskId) {
        $selectedTask = $task;
        break;
    }
}
$activeTaskView = $selectedTask !== null && $selectedTask['display_status'] === 'completed' ? 'history' : 'recent';
$position = trim((string) ($employee['position'] ?? ''));
$position = $position !== '' ? $position : strtoupper($accountRole);
$displayName = trim((string) ($employee['username'] ?? $_SESSION['name'] ?? 'Office Employee'));
$initials = '';
foreach (preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $namePart) {
    $initials .= mb_strtoupper(mb_substr($namePart, 0, 1));
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
if ($initials === '') {
    $initials = 'OE';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks | TaskTrack</title>
    <?php if ($accountRole === 'payroll'): ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/payroll-sidebar.css?v=<?= filemtime(__DIR__ . '/css/payroll-sidebar.css'); ?>">
    <?php endif; ?>
    <?php if ($accountRole === 'HR'): ?>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
    <link rel="stylesheet" href="css/office_employee.css?v=<?= filemtime(__DIR__ . '/css/office_employee.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <?php if ($accountRole === 'HR'): ?>
    <style>
        .hr-main.employee-main {
            width: min(100% - 72px, 1700px);
            margin-left: 72px;
            padding: 0;
        }
        @media (max-width: 600px) {
            .hr-main.employee-main {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body>
<?php if ($accountRole === 'HR'): ?>
<div class="hr-shell">
    <aside class="hr-sidebar" id="hrSidebar" aria-label="HR navigation">
        <a class="hr-brand" href="HR.php" aria-label="TaskTrack HR home">
            <span class="hr-brand-mark" aria-hidden="true">T</span>
            <span class="hr-brand-name">TASKTRACK</span>
        </a>
        <nav class="hr-nav">
            <a class="hr-nav-link" href="HR.php">
                <span class="hr-nav-icon" aria-hidden="true">▦</span>
                <span class="hr-nav-label">Employees</span>
            </a>
            <a class="hr-nav-link" href="atten.php">
                <span class="hr-nav-icon" aria-hidden="true">◷</span>
                <span class="hr-nav-label">Attendance</span>
            </a>
            <?php if ($accountRole === 'HR'): ?>
                <a class="hr-nav-link" href="leave_management.php">
                    <span class="hr-nav-icon" aria-hidden="true">✚</span>
                    <span class="hr-nav-label">Leave Management</span>
                </a>
            <?php endif; ?>
            <a class="hr-nav-link" href="application_form.php">
                <span class="hr-nav-icon" aria-hidden="true">▤</span>
                <span class="hr-nav-label">Applicants</span>
            </a>
            <a class="hr-nav-link" href="interview_sched.php">
                <span class="hr-nav-icon" aria-hidden="true">▣</span>
                <span class="hr-nav-label">Interview Schedule</span>
            </a>
            <a class="hr-nav-link is-active" href="office_employee.php" aria-current="page">
                <span class="hr-nav-icon" aria-hidden="true">☑</span>
                <span class="hr-nav-label">My Tasks</span>
            </a>
        </nav>
        <a class="hr-nav-link hr-logout" href="logout.php">
            <span class="hr-nav-icon" aria-hidden="true">↪</span>
            <span class="hr-nav-label">Log out</span>
        </a>
    </aside>
    <?php include __DIR__ . '/config/hr_mobile_navigation.php'; ?>
    <?php include __DIR__ . '/config/notification_bell.php'; ?>
    <div class="hr-main employee-main">
<?php elseif ($accountRole === 'payroll'): ?>
<div class="payroll-tasks-layout">
    <?php $payrollSidebarOnTasks = true; $payrollSidebarActive = 'tasks'; include __DIR__ . '/config/payroll_sidebar.php'; ?>
    <?php include __DIR__ . '/config/notification_bell.php'; ?>
    <div class="employee-main payroll-tasks-main">
<?php else: ?>
<?php $tasktrackActive = 'my-tasks'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>
<div class="employee-main">
<?php endif; ?>
<header class="employee-topbar">
    <div class="employee-identity">
        <span class="employee-avatar">
            <?php if (is_string($tasktrackProfilePicture ?? $_SESSION['profile_picture'] ?? null) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) ($tasktrackProfilePicture ?? $_SESSION['profile_picture']))): ?>
                <img src="uploads/profile-pictures/<?= $escape($tasktrackProfilePicture ?? $_SESSION['profile_picture']); ?>" alt="">
            <?php else: ?>
                <?= $escape($initials); ?>
            <?php endif; ?>
        </span>
        <span class="employee-name"><?= $escape($displayName); ?><small><?= $escape($position); ?></small></span>
    </div>
    <a class="logout-link" href="logout.php" aria-label="Log out" title="Log out">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
    </a>
</header>

<main class="employee-page">
    <?php if ($accountLinkError !== ''): ?>
        <div class="notice is-error" role="alert"><?= $escape($accountLinkError); ?></div>
    <?php else: ?>
        <?php if ($message !== ''): ?><div class="notice is-success" role="status"><?= $escape($message); ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="notice is-error" role="alert"><?= $escape($errorMessage); ?></div><?php endif; ?>

        <section class="summary-grid" aria-label="Task summary">
            <article class="summary-card is-completed">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">✓</span><span>Completed</span></div>
                <strong><?= $counts['completed']; ?></strong>
                <span class="summary-caption"><?= $counts['completed']; ?> <?= $counts['completed'] === 1 ? 'task has' : 'tasks have'; ?> been reviewed and approved.</span>
            </article>
            <article class="summary-card is-pending">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">!</span><span>Pending</span></div>
                <strong><?= $counts['pending']; ?></strong>
                <span class="summary-caption"><?= $counts['pending']; ?> <?= $counts['pending'] === 1 ? 'task is' : 'tasks are'; ?> waiting for your review and action.</span>
            </article>
            <article class="summary-card is-review">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">!</span><span>Request sent</span></div>
                <strong><?= $counts['review']; ?></strong>
                <span class="summary-caption"><?= $counts['review']; ?> <?= $counts['review'] === 1 ? 'task is' : 'tasks are'; ?> waiting for supervisor approval.</span>
            </article>
            <article class="summary-card is-overdue">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">◷</span><span>Overdue</span></div>
                <strong><?= $counts['overdue']; ?></strong>
                <span class="summary-caption"><?= $counts['overdue'] === 1 ? '1 urgent task is currently past its due date.' : $counts['overdue'] . ' tasks are currently past their due date.'; ?></span>
            </article>
        </section>

        <nav class="task-view-tabs" aria-label="Task views">
            <button
                type="button"
                class="task-view-tab<?= $activeTaskView === 'recent' ? ' is-active' : ''; ?>"
                data-task-view-tab="recent"
                aria-controls="recentTasksPanel"
                aria-pressed="<?= $activeTaskView === 'recent' ? 'true' : 'false'; ?>"
            >My Tasks</button>
            <button
                type="button"
                class="task-view-tab<?= $activeTaskView === 'history' ? ' is-active' : ''; ?>"
                data-task-view-tab="history"
                aria-controls="taskHistoryPanel"
                aria-pressed="<?= $activeTaskView === 'history' ? 'true' : 'false'; ?>"
            >History (Completed)</button>
        </nav>

        <section class="tasks-panel task-view-panel" id="recentTasksPanel" aria-label="My tasks"<?= $activeTaskView !== 'recent' ? ' hidden' : ''; ?>>
            <div class="tasks-heading">
                <div>
                    <h1>Recent tasks</h1>
                    <p>Clear list of all tasks with emphasis on pending deadlines.</p>
                </div>
                <div class="task-filters">
                    <a class="filter-button<?= $periodFilter === 'week' ? ' is-selected' : ''; ?>" href="?period=<?= $periodFilter === 'week' ? 'all' : 'week'; ?>&amp;status=<?= $escape($statusFilter); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                        <?= $periodFilter === 'week' ? 'This week' : 'All dates'; ?>
                    </a>
                    <details class="filter-menu">
                        <summary class="filter-button is-dark">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6.5 7.5v5l-3 1v-6z"/></svg>
                            <?= $escape(match ($statusFilter) {
                                'all' => 'All tasks',
                                'review' => 'Waiting for approval',
                                'changes_requested' => 'Changes requested',
                                default => ucfirst($statusFilter),
                            }); ?>
                        </summary>
                        <div class="filter-options">
                            <?php foreach (['all' => 'All tasks', 'pending' => 'Pending', 'review' => 'Waiting for approval', 'changes_requested' => 'Changes requested', 'completed' => 'Completed', 'overdue' => 'Overdue'] as $filterValue => $filterLabel): ?>
                                <a href="?period=<?= $escape($periodFilter); ?>&amp;status=<?= $escape($filterValue); ?>"><?= $escape($filterLabel); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </div>
            </div>

            <div class="task-table-wrap">
                <table class="task-table">
                    <thead><tr><th>Task</th><th>Due date</th><th>Status</th><th>Priority</th><th>Owner</th><th>Progress</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if ($visibleTasks === []): ?>
                        <tr><td class="empty-row" colspan="7"><?= $statusFilter === 'completed' ? 'Open History to view your completed tasks.' : ($tasks === [] ? 'Wala ka pang assigned task.' : 'Walang task na tugma sa napiling filter.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($visibleTasks as $task): ?>
                            <?php
                            $status = $task['display_status'];
                            $statusLabel = match ($status) {
                                'review' => 'Request sent · waiting for approval',
                                'changes_requested' => 'Changes requested · resubmit',
                                default => ucfirst($status),
                            };
                            $priority = strtolower(trim((string) ($task['priority_level'] ?? 'medium')));
                            $progress = max(0, min(100, (int) $task['progress']));
                            $dueDate = $task['due_date'] ? date('D, d M', strtotime($task['due_date'])) : 'No due date';
                            $overdueDays = $status === 'overdue'
                                ? (int) (new DateTimeImmutable((string) $task['due_date']))->diff(new DateTimeImmutable($today))->days
                                : 0;
                            $taskUrl = '?period=' . rawurlencode($periodFilter) . '&status=' . rawurlencode($statusFilter)
                                . '&task_id=' . (int) $task['office_id'];
                            ?>
                            <tr class="task-row is-<?= $escape($status); ?>" data-task-id="<?= (int) $task['office_id']; ?>">
                                <td class="task-title-cell">
                                    <a class="task-open" href="<?= $escape($taskUrl); ?>"><?= $escape($task['title'] ?: 'Untitled task'); ?></a>
                                    <small><span class="category-dot is-<?= $escape(strtolower((string) ($task['data_type'] ?? 'general'))); ?>"></span><?= $escape($task['data_type'] ?: 'General'); ?></small>
                                    <small>Assigned by: <?= $escape(trim((string) ($task['assigned_by_name'] ?? '')) ?: 'Not recorded'); ?></small>
                                </td>
                                <td class="due-cell<?= $status === 'overdue' ? ' is-late' : ''; ?>">
                                    <span><?= $escape($dueDate); ?></span>
                                    <?php if ($status === 'overdue'): ?>
                                        <small class="overdue-detail">Overdue by <?= $overdueDays; ?> <?= $overdueDays === 1 ? 'day' : 'days'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-pill is-<?= $escape($status); ?>"><i></i><?= $escape($statusLabel); ?></span></td>
                                <td><span class="priority-text is-<?= $escape(in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium'); ?>"><i></i><?= $escape(ucfirst($priority)); ?></span></td>
                                <td><span class="owner"><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></span></td>
                                <td>
                                    <div class="progress-label"><strong><?= $progress; ?>%</strong><span>complete</span></div>
                                    <div class="progress-track"><span style="width: <?= $progress; ?>%"></span></div>
                                </td>
                                <td>
                                    <div class="task-row-actions">
                                        <a class="open-button" href="<?= $escape($taskUrl); ?>">Open task →</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="tasks-panel task-history-panel task-view-panel" id="taskHistoryPanel" aria-label="Completed task history"<?= $activeTaskView !== 'history' ? ' hidden' : ''; ?>>
            <div class="tasks-heading">
                <div>
                    <h1>History</h1>
                    <p>Completed tasks are collected here.</p>
                </div>
                <span class="history-count"><?= count($historyTasks); ?> completed</span>
            </div>
            <div class="task-table-wrap">
                    <table class="task-table">
                        <thead><tr><th>Task</th><th>Due date</th><th>Status</th><th>Priority</th><th>Owner</th><th>Progress</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if ($historyTasks === []): ?>
                            <tr><td class="empty-row" colspan="7">No completed tasks yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($historyTasks as $task): ?>
                                <?php
                                $priority = strtolower(trim((string) ($task['priority_level'] ?? 'medium')));
                                $dueDate = $task['due_date'] ? date('D, d M', strtotime($task['due_date'])) : 'No due date';
                                $taskUrl = '?period=' . rawurlencode($periodFilter) . '&status=' . rawurlencode($statusFilter)
                                    . '&task_id=' . (int) $task['office_id'];
                                ?>
                                <tr class="task-row is-completed" data-task-id="<?= (int) $task['office_id']; ?>">
                                    <td class="task-title-cell">
                                        <a class="task-open" href="<?= $escape($taskUrl); ?>"><?= $escape($task['title'] ?: 'Untitled task'); ?></a>
                                        <small><span class="category-dot is-<?= $escape(strtolower((string) ($task['data_type'] ?? 'general'))); ?>"></span><?= $escape($task['data_type'] ?: 'General'); ?></small>
                                        <small>Assigned by: <?= $escape(trim((string) ($task['assigned_by_name'] ?? '')) ?: 'Not recorded'); ?></small>
                                    </td>
                                    <td class="due-cell"><?= $escape($dueDate); ?></td>
                                    <td><span class="status-pill is-completed"><i></i>Completed</span></td>
                                    <td><span class="priority-text is-<?= $escape(in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium'); ?>"><i></i><?= $escape(ucfirst($priority)); ?></span></td>
                                    <td><span class="owner"><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></span></td>
                                    <td>
                                        <div class="progress-label"><strong>100%</strong><span>complete</span></div>
                                        <div class="progress-track"><span style="width: 100%"></span></div>
                                    </td>
                                    <td><a class="open-button" href="<?= $escape($taskUrl); ?>">View details →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</div>
<?php if ($accountRole === 'HR'): ?>
</div>
<?php elseif ($accountRole === 'payroll'): ?>
</div>
<?php endif; ?>

<?php if ($selectedTask !== null && $employee !== null && $selectedTask['display_status'] !== 'completed'): ?>
<dialog class="task-dialog" id="taskDialog" aria-labelledby="dialogTitle">
    <form method="post" class="dialog-content">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['office_employee_csrf']); ?>">
        <input type="hidden" name="office_id" value="<?= (int) $selectedTask['office_id']; ?>">
        <div class="dialog-topline"><span>SELECTED TASK</span><button type="button" class="dialog-close" data-close-dialog aria-label="Close">×</button></div>
        <h2 id="dialogTitle"><?= $escape($selectedTask['title'] ?: 'Untitled task'); ?></h2>
        <div class="dialog-badges">
            <span class="status-pill is-<?= $escape($selectedTask['display_status']); ?>"><i></i><?= $escape(match ($selectedTask['display_status']) {
                'review' => 'Request sent · waiting for approval',
                'changes_requested' => 'Changes requested · resubmit',
                default => ucfirst($selectedTask['display_status']),
            }); ?></span>
            <span class="priority-badge"><?= $escape(ucfirst($selectedTask['priority_level'] ?? 'medium')); ?> priority</span>
        </div>
        <div class="dialog-facts">
            <div><small>TASK OWNER</small><strong><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></strong></div>
            <div><small>ASSIGNED BY</small><strong><?= $escape(trim((string) ($selectedTask['assigned_by_name'] ?? '')) ?: 'Not recorded'); ?></strong></div>
            <div>
                <small>DUE DATE</small>
                <strong><?= $escape($selectedTask['due_date'] ? date('D, d M Y', strtotime($selectedTask['due_date'])) : 'Not set'); ?></strong>
                <?php if ($selectedTask['display_status'] === 'overdue'): ?>
                    <?php $selectedOverdueDays = (int) (new DateTimeImmutable((string) $selectedTask['due_date']))->diff(new DateTimeImmutable($today))->days; ?>
                    <span class="dialog-overdue-detail">Overdue by <?= $selectedOverdueDays; ?> <?= $selectedOverdueDays === 1 ? 'day' : 'days'; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <section class="task-review-history" aria-label="Approval and change request history">
            <strong>Approval / request history</strong>
            <?php if ($selectedTask['review_history'] !== []): ?>
                <?php foreach ($selectedTask['review_history'] as $reviewEvent): ?>
                    <article>
                        <span><?= $escape($reviewEvent['review_label']); ?> <?= $escape(trim((string) ($reviewEvent['actor_name'] ?? '')) ?: 'Not recorded'); ?></span>
                        <time><?= $escape(date('M j, Y g:i A', strtotime((string) $reviewEvent['occurred_at']))); ?></time>
                        <?php if (!empty($reviewEvent['review_note'])): ?><small><?= $escape($reviewEvent['review_note']); ?></small><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php elseif (!empty($selectedTask['completion_reviewed_by_name'])): ?>
                <article>
                    <span><?= ($selectedTask['completion_review_status'] ?? '') === 'approved' ? 'Approved by' : 'Changes requested by'; ?> <?= $escape($selectedTask['completion_reviewed_by_name']); ?></span>
                    <?php if (!empty($selectedTask['completion_reviewed_at'])): ?><time><?= $escape(date('M j, Y g:i A', strtotime((string) $selectedTask['completion_reviewed_at']))); ?></time><?php endif; ?>
                    <?php if (($selectedTask['completion_review_status'] ?? '') === 'rejected' && trim((string) ($selectedTask['completion_review_note'] ?? '')) !== ''): ?><small><?= $escape($selectedTask['completion_review_note']); ?></small><?php endif; ?>
                </article>
            <?php else: ?>
                <p class="task-review-history-empty">
                    <?= ($selectedTask['completion_review_status'] ?? '') === 'pending_review'
                        ? 'Submitted for review. No supervisor decision has been recorded yet.'
                        : 'No approval or change request yet. Submit the task for review to get a supervisor decision.'; ?>
                </p>
            <?php endif; ?>
        </section>
        <div class="description-box"><small>DESCRIPTION</small><p><?= nl2br($escape($selectedTask['content'] ?? 'No description provided.')); ?></p></div>
        <?php if (($selectedTask['completion_review_status'] ?? '') === 'rejected' && trim((string) ($selectedTask['completion_review_note'] ?? '')) !== ''): ?>
            <div class="review-feedback"><strong>Supervisor feedback</strong><p><?= nl2br($escape($selectedTask['completion_review_note'])); ?></p></div>
        <?php endif; ?>
        <?php if ($selectedTask['display_status'] === 'review'): ?>
            <div class="review-pending-notice" role="status">Request sent. Your supervisor will review it; you can update the task again after the review.</div>
        <?php else: ?>
        <section class="progress-update">
            <div class="progress-update-heading"><strong>Progress update</strong><output id="progressOutput"><?= (int) $selectedTask['progress']; ?>%</output></div>
            <input type="range" id="progressInput" name="progress" min="0" max="100" value="<?= (int) $selectedTask['progress']; ?>" aria-label="Task progress">
            <div class="progress-limits"><span>0%</span><span>100%</span></div>
            <p class="progress-help">Saving 100% updates progress only. Submit the task when it is ready for supervisor review.</p>
            <button class="save-progress-button" type="submit" name="task_action" value="save_progress">Save progress</button>
        </section>
        <?php endif; ?>
        <div class="dialog-bottom">
            <span class="updated-at">Last updated <?= $escape(date('M j, g:i A', strtotime($selectedTask['updated_at']))); ?></span>
            <div><button class="close-button" type="button" data-close-dialog>Close</button><?php if ($selectedTask['display_status'] !== 'review'): ?><button class="complete-button" type="submit" name="task_action" value="mark_complete"><?= $selectedTask['display_status'] === 'changes_requested' ? 'Resubmit for review' : 'Submit for review'; ?></button><?php endif; ?></div>
        </div>
    </form>
</dialog>
<script>
    const taskDialog = document.getElementById('taskDialog');
    const progressInput = document.getElementById('progressInput');
    const progressOutput = document.getElementById('progressOutput');
    if (progressInput && progressOutput) {
        progressInput.addEventListener('input', () => { progressOutput.value = `${progressInput.value}%`; });
    }
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => taskDialog.close());
    });
    taskDialog.addEventListener('click', (event) => {
        if (event.target === taskDialog) taskDialog.close();
    });
    taskDialog.showModal();
</script>
<?php elseif ($selectedTask !== null && $selectedTask['display_status'] === 'completed'): ?>
<dialog class="task-dialog completed-dialog" id="completedDialog">
    <div class="completed-content">
        <div class="dialog-topline"><span>TASK DETAILS</span><button type="button" class="dialog-close" data-close-completed aria-label="Close">×</button></div>
        <span class="completed-mark">✓</span><h2><?= $escape($selectedTask['title'] ?: 'Untitled task'); ?></h2>
        <div class="dialog-badges">
            <span class="status-pill is-completed"><i></i>Completed</span>
            <span class="priority-badge"><?= $escape(ucfirst($selectedTask['priority_level'] ?? 'medium')); ?> priority</span>
        </div>
        <div class="dialog-facts">
            <div><small>TASK OWNER</small><strong><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></strong></div>
            <div><small>ASSIGNED BY</small><strong><?= $escape(trim((string) ($selectedTask['assigned_by_name'] ?? '')) ?: 'Not recorded'); ?></strong></div>
            <div><small>DUE DATE</small><strong><?= $escape($selectedTask['due_date'] ? date('D, d M Y', strtotime($selectedTask['due_date'])) : 'Not set'); ?></strong></div>
        </div>
        <section class="task-review-history" aria-label="Approval and change request history">
            <strong>Approval / request history</strong>
            <?php if ($selectedTask['review_history'] !== []): ?>
                <?php foreach ($selectedTask['review_history'] as $reviewEvent): ?>
                    <article>
                        <span><?= $escape($reviewEvent['review_label']); ?> <?= $escape(trim((string) ($reviewEvent['actor_name'] ?? '')) ?: 'Not recorded'); ?></span>
                        <time><?= $escape(date('M j, Y g:i A', strtotime((string) $reviewEvent['occurred_at']))); ?></time>
                        <?php if (!empty($reviewEvent['review_note'])): ?><small><?= $escape($reviewEvent['review_note']); ?></small><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php elseif (!empty($selectedTask['completion_reviewed_by_name'])): ?>
                <article>
                    <span><?= ($selectedTask['completion_review_status'] ?? '') === 'approved' ? 'Approved by' : 'Changes requested by'; ?> <?= $escape($selectedTask['completion_reviewed_by_name']); ?></span>
                    <?php if (!empty($selectedTask['completion_reviewed_at'])): ?><time><?= $escape(date('M j, Y g:i A', strtotime((string) $selectedTask['completion_reviewed_at']))); ?></time><?php endif; ?>
                    <?php if (($selectedTask['completion_review_status'] ?? '') === 'rejected' && trim((string) ($selectedTask['completion_review_note'] ?? '')) !== ''): ?><small><?= $escape($selectedTask['completion_review_note']); ?></small><?php endif; ?>
                </article>
            <?php else: ?>
                <p class="task-review-history-empty">
                    <?= ($selectedTask['completion_review_status'] ?? '') === 'pending_review'
                        ? 'Submitted for review. No supervisor decision has been recorded yet.'
                        : 'No approval or change request yet. Submit the task for review to get a supervisor decision.'; ?>
                </p>
            <?php endif; ?>
        </section>
        <div class="description-box"><small>DESCRIPTION</small><p><?= nl2br($escape($selectedTask['content'] ?? 'No description provided.')); ?></p></div>
        <p class="completed-progress">Progress <strong>100%</strong></p>
        <button type="button" class="completed-return" data-close-completed>Return to my tasks</button>
    </div>
</dialog>
<script>
    const completedDialog = document.getElementById('completedDialog');
    document.querySelectorAll('[data-close-completed]').forEach((button) => button.addEventListener('click', () => {
        completedDialog.close();
        history.replaceState(null, '', 'office_employee.php');
    }));
    completedDialog.showModal();
</script>
<?php endif; ?>
<script>
    const taskViewTabs = document.querySelectorAll('[data-task-view-tab]');
    const taskViewPanels = {
        recent: document.getElementById('recentTasksPanel'),
        history: document.getElementById('taskHistoryPanel'),
    };
    taskViewTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const selectedView = tab.dataset.taskViewTab;
            taskViewTabs.forEach((viewTab) => {
                const isSelected = viewTab === tab;
                viewTab.classList.toggle('is-active', isSelected);
                viewTab.setAttribute('aria-pressed', String(isSelected));
            });
            Object.entries(taskViewPanels).forEach(([view, panel]) => {
                if (panel) panel.hidden = view !== selectedView;
            });
        });
    });
</script>
<?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>
