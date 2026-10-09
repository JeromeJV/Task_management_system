<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
date_default_timezone_set('Asia/Manila');

$allowedRoles = ['HR', 'payroll', 'super', 'employee'];
if (!isset($_SESSION['id'], $_SESSION['email'], $_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
    header('Location: index.php');
    exit();
}

$userId = (int) $_SESSION['id'];
$userEmail = trim((string) $_SESSION['email']);
$userName = trim((string) ($_SESSION['name'] ?? ''));
$accountRole = (string) $_SESSION['role'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');

if (empty($_SESSION['office_employee_csrf'])) {
    $_SESSION['office_employee_csrf'] = bin2hex(random_bytes(32));
}

$accountLinkError = '';
$employee = null;
$matchStmt = $conn->prepare(
    "SELECT e.employee_id, e.username, e.department, e.position,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM attendance a
                    WHERE a.employee_id = e.employee_id AND a.user_id = ?
                ) THEN 0
                WHEN e.email IS NOT NULL AND e.email <> '' AND LOWER(TRIM(e.email)) = LOWER(TRIM(?)) THEN 1
                ELSE 2
            END AS match_priority
     FROM employee e
     WHERE EXISTS (
            SELECT 1 FROM attendance a
            WHERE a.employee_id = e.employee_id AND a.user_id = ?
        )
        OR (e.email IS NOT NULL AND e.email <> '' AND LOWER(TRIM(e.email)) = LOWER(TRIM(?)))
        OR (? <> '' AND LOWER(TRIM(e.username)) = LOWER(TRIM(?)))
     ORDER BY match_priority ASC, e.employee_id ASC
     LIMIT 2"
);
if (!$matchStmt) {
    throw new RuntimeException('Unable to prepare your employee profile lookup: ' . $conn->error);
}
$matchStmt->bind_param('isisss', $userId, $userEmail, $userId, $userEmail, $userName, $userName);
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
} elseif (count($matches) > 1 && (int) $matches[0]['match_priority'] === (int) $matches[1]['match_priority']) {
    $accountLinkError = 'More than one employee profile matches this account. Please ask HR to resolve the duplicate employee record before viewing tasks.';
} else {
    $employee = $matches[0];
}

$message = '';
$errorMessage = '';
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'pending', 'completed', 'overdue'], true)) {
    $statusFilter = 'all';
}
$periodFilter = strtolower(trim((string) ($_GET['period'] ?? 'week')));
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
        if ($progress === false || $progress < 0 || $progress > 99 && $action === 'save_progress') {
            $errorMessage = 'Progress must be between 0 and 99%. Use Mark as complete to finish the task.';
        } else {
            $newStatus = $action === 'mark_complete' ? 'completed' : 'pending';
            $updateStmt = $conn->prepare(
                "UPDATE office
                 SET progress = ?, status = ?
                 WHERE office_id = ? AND employee_id = ?
                   AND LOWER(TRIM(COALESCE(status, 'pending'))) NOT IN ('completed', 'complete', 'done')"
            );
            if (!$updateStmt) {
                throw new RuntimeException('Unable to prepare the task update: ' . $conn->error);
            }
            $updateStmt->bind_param('isii', $progress, $newStatus, $taskId, $employee['employee_id']);
            if (!$updateStmt->execute()) {
                $error = $updateStmt->error;
                $updateStmt->close();
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
                     LIMIT 1"
                );
                if (!$verifyStmt) {
                    throw new RuntimeException('Unable to verify the task update: ' . $conn->error);
                }
                $verifyStmt->bind_param('ii', $taskId, $employee['employee_id']);
                if (!$verifyStmt->execute()) {
                    $error = $verifyStmt->error;
                    $verifyStmt->close();
                    throw new RuntimeException('Unable to verify the task update: ' . $error);
                }
                $taskStillActive = $verifyStmt->get_result()->num_rows > 0;
                $verifyStmt->close();
            }
            if (!$taskStillActive) {
                $errorMessage = 'This task is not assigned to your account or has already been completed.';
            } else {
                $_SESSION['office_employee_flash'] = $action === 'mark_complete'
                    ? 'Task marked as completed.'
                    : 'Task progress updated.';
                header('Location: office_employee.php?status=' . rawurlencode($statusFilter)
                    . '&period=' . rawurlencode($periodFilter) . '&task_id=' . $taskId);
                exit();
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
        'SELECT office_id, data_type, title, content, department, status, priority_level,
                due_date, progress, submission_date, updated_at
         FROM office
         WHERE employee_id = ?
         ORDER BY due_date IS NULL ASC, due_date ASC, submission_date DESC, office_id DESC'
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
        $task['progress'] = (int) ($task['progress'] ?? 0);
        $normalizedStatus = strtolower(trim((string) ($task['status'] ?? 'pending')));
        if (in_array($normalizedStatus, ['completed', 'complete', 'done'], true)) {
            $task['display_status'] = 'completed';
            $task['progress'] = 100;
        } elseif (!empty($task['due_date']) && $task['due_date'] < $today) {
            $task['display_status'] = 'overdue';
        } else {
            $task['display_status'] = 'pending';
        }
        $tasks[] = $task;
    }
    $taskStmt->close();
}

$counts = ['overdue' => 0, 'pending' => 0, 'completed' => 0];
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
$selectedTask = null;
foreach ($tasks as $task) {
    if ((int) $task['office_id'] === $selectedTaskId) {
        $selectedTask = $task;
        break;
    }
}
$department = trim((string) ($employee['department'] ?? ''));
$position = trim((string) ($employee['position'] ?? ''));
if ($department === '') {
    $department = $position !== '' ? $position : strtoupper($accountRole);
}
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
    <link rel="stylesheet" href="css/office_employee.css?v=<?= filemtime(__DIR__ . '/css/office_employee.css'); ?>">
</head>
<body>
<header class="employee-topbar">
    <div class="employee-identity">
        <span class="employee-avatar"><?= $escape($initials); ?></span>
        <span class="employee-name"><?= $escape($displayName); ?><small><?= $escape($department); ?></small></span>
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
            <article class="summary-card is-overdue">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">◷</span><span>Overdue</span></div>
                <strong><?= $counts['overdue']; ?></strong>
                <span class="summary-caption"><?= $counts['overdue'] === 1 ? '1 urgent task is currently past its due date.' : $counts['overdue'] . ' tasks are currently past their due date.'; ?></span>
            </article>
            <article class="summary-card is-pending">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">!</span><span>Pending</span></div>
                <strong><?= $counts['pending']; ?></strong>
                <span class="summary-caption"><?= $counts['pending']; ?> <?= $counts['pending'] === 1 ? 'task is' : 'tasks are'; ?> waiting for your review and action.</span>
            </article>
            <article class="summary-card is-completed">
                <div class="summary-label"><span class="summary-icon" aria-hidden="true">✓</span><span>Completed</span></div>
                <strong><?= $counts['completed']; ?></strong>
                <span class="summary-caption"><?= $counts['completed']; ?> <?= $counts['completed'] === 1 ? 'task has' : 'tasks have'; ?> been finished and are ready for review.</span>
            </article>
        </section>

        <section class="tasks-panel">
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
                            <?= $escape(ucfirst($statusFilter === 'all' ? 'All tasks' : $statusFilter)); ?>
                        </summary>
                        <div class="filter-options">
                            <?php foreach (['all' => 'All tasks', 'pending' => 'Pending', 'completed' => 'Completed', 'overdue' => 'Overdue'] as $filterValue => $filterLabel): ?>
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
                        <tr><td class="empty-row" colspan="7"><?= $tasks === [] ? 'Wala ka pang assigned task.' : 'Walang task na tugma sa napiling filter.'; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($visibleTasks as $task): ?>
                            <?php
                            $status = $task['display_status'];
                            $priority = strtolower(trim((string) ($task['priority_level'] ?? 'medium')));
                            $progress = max(0, min(100, (int) $task['progress']));
                            $dueDate = $task['due_date'] ? date('D, d M', strtotime($task['due_date'])) : 'No due date';
                            $taskUrl = '?period=' . rawurlencode($periodFilter) . '&status=' . rawurlencode($statusFilter)
                                . '&task_id=' . (int) $task['office_id'];
                            ?>
                            <tr class="task-row is-<?= $escape($status); ?>" data-task-id="<?= (int) $task['office_id']; ?>">
                                <td class="task-title-cell">
                                    <a class="task-open" href="<?= $escape($taskUrl); ?>"><?= $escape($task['title'] ?: 'Untitled task'); ?></a>
                                    <small><span class="category-dot is-<?= $escape(strtolower((string) ($task['data_type'] ?? 'general'))); ?>"></span><?= $escape($task['data_type'] ?: 'General'); ?></small>
                                </td>
                                <td class="due-cell<?= $status === 'overdue' ? ' is-late' : ''; ?>"><?= $escape($dueDate); ?></td>
                                <td><span class="status-pill is-<?= $escape($status); ?>"><i></i><?= $escape(ucfirst($status)); ?></span></td>
                                <td><span class="priority-text is-<?= $escape(in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium'); ?>"><i></i><?= $escape(ucfirst($priority)); ?></span></td>
                                <td><span class="owner"><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></span></td>
                                <td>
                                    <div class="progress-label"><strong><?= $progress; ?>%</strong><span>complete</span></div>
                                    <div class="progress-track"><span style="width: <?= $progress; ?>%"></span></div>
                                </td>
                                <td>
                                    <?php if ($status === 'completed'): ?>
                                        <span class="done-button">Done ✓</span>
                                    <?php else: ?>
                                        <a class="open-button" href="<?= $escape($taskUrl); ?>"><?= $status === 'overdue' ? 'Urgent!' : 'Open →'; ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if ($selectedTask !== null && $employee !== null && $selectedTask['display_status'] !== 'completed'): ?>
<dialog class="task-dialog" id="taskDialog" aria-labelledby="dialogTitle">
    <form method="post" class="dialog-content">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['office_employee_csrf']); ?>">
        <input type="hidden" name="office_id" value="<?= (int) $selectedTask['office_id']; ?>">
        <div class="dialog-topline"><span>SELECTED TASK</span><button type="button" class="dialog-close" data-close-dialog aria-label="Close">×</button></div>
        <h2 id="dialogTitle"><?= $escape($selectedTask['title'] ?: 'Untitled task'); ?></h2>
        <div class="dialog-badges">
            <span class="status-pill is-<?= $escape($selectedTask['display_status']); ?>"><i></i><?= $escape(ucfirst($selectedTask['display_status'])); ?></span>
            <span class="priority-badge"><?= $escape(ucfirst($selectedTask['priority_level'] ?? 'medium')); ?> priority</span>
        </div>
        <div class="dialog-facts">
            <div><small>TASK OWNER</small><strong><b><?= $escape($initials); ?></b><?= $escape($displayName); ?></strong></div>
            <div><small>DUE DATE</small><strong><?= $escape($selectedTask['due_date'] ? date('D, d M Y', strtotime($selectedTask['due_date'])) : 'Not set'); ?></strong></div>
            <div><small>DEPARTMENT</small><strong><?= $escape($selectedTask['department'] ?: $department); ?></strong></div>
        </div>
        <div class="description-box"><small>DESCRIPTION</small><p><?= nl2br($escape($selectedTask['content'] ?? 'No description provided.')); ?></p></div>
        <section class="progress-update">
            <div class="progress-update-heading"><strong>Progress update</strong><output id="progressOutput"><?= (int) $selectedTask['progress']; ?>%</output></div>
            <input type="range" id="progressInput" name="progress" min="0" max="99" value="<?= (int) $selectedTask['progress']; ?>" aria-label="Task progress">
            <div class="progress-limits"><span>0%</span><span>99%</span></div>
            <button class="save-progress-button" type="submit" name="task_action" value="save_progress">Save progress</button>
        </section>
        <div class="dialog-bottom">
            <span class="updated-at">Last updated <?= $escape(date('M j, g:i A', strtotime($selectedTask['updated_at']))); ?></span>
            <div><button class="close-button" type="button" data-close-dialog>Close</button><button class="complete-button" type="submit" name="task_action" value="mark_complete">Mark as complete</button></div>
        </div>
    </form>
</dialog>
<script>
    const taskDialog = document.getElementById('taskDialog');
    const progressInput = document.getElementById('progressInput');
    const progressOutput = document.getElementById('progressOutput');
    progressInput.addEventListener('input', () => { progressOutput.value = `${progressInput.value}%`; });
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
        <span class="completed-mark">✓</span><h2>Task completed</h2>
        <p><?= $escape($selectedTask['title']); ?> is marked as completed.</p>
        <button type="button" data-close-completed>Return to my tasks</button>
    </div>
</dialog>
<script>
    const completedDialog = document.getElementById('completedDialog');
    document.querySelector('[data-close-completed]').addEventListener('click', () => {
        completedDialog.close();
        history.replaceState(null, '', 'office_employee.php');
    });
    completedDialog.showModal();
</script>
<?php endif; ?>
</body>
</html>
