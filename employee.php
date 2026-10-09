<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
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

if (empty($_SESSION['office_task_csrf'])) {
    $_SESSION['office_task_csrf'] = bin2hex(random_bytes(32));
}

$flashMessage = $_SESSION['office_task_flash'] ?? '';
unset($_SESSION['office_task_flash']);
$errorMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['create_task'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $allowedCategories = ['General', 'Production', 'Logistics', 'HR', 'Payroll', 'IT'];
    $allowedPriorities = ['low', 'medium', 'high', 'urgent'];

    if (!hash_equals($_SESSION['office_task_csrf'], $postedToken)) {
        $errorMessage = 'Your session token expired. Refresh the page and try again.';
    } elseif (!$employeeId || $employeeId < 1) {
        $errorMessage = 'Select an employee to assign this task to.';
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
        if (!$validDueDate || $dueDate < date('Y-m-d')) {
            $errorMessage = 'Choose a valid due date that is today or later.';
        } else {
            $employeeStmt = $conn->prepare(
                'SELECT department FROM employee WHERE employee_id = ? LIMIT 1'
            );
            $employeeStmt->bind_param('i', $employeeId);
            $employeeStmt->execute();
            $employeeResult = $employeeStmt->get_result();
            $assignedEmployee = $employeeResult->fetch_assoc();
            $employeeStmt->close();

            if (!$assignedEmployee) {
                $errorMessage = 'The selected employee could not be found.';
            } else {
                $employeeDepartment = trim((string) ($assignedEmployee['department'] ?? ''));
                $insertStmt = $conn->prepare(
                    "INSERT INTO office
                        (supervisor_id, employee_id, data_type, title, content, department, status, priority_level, due_date)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)"
                );
                $insertStmt->bind_param(
                    'iissssss',
                    $currentUserId,
                    $employeeId,
                    $category,
                    $title,
                    $description,
                    $employeeDepartment,
                    $priority,
                    $dueDate
                );
                if ($insertStmt->execute()) {
                    $_SESSION['office_task_flash'] = 'Task assigned successfully.';
                    $insertStmt->close();
                    header('Location: employee.php?employee_id=' . $employeeId);
                    exit();
                }
                $insertStmt->close();
                throw new RuntimeException('Unable to assign task: ' . $conn->error);
            }
        }
    }
}

$employees = [];
$employeeResult = $conn->query(
    "SELECT employee_id, username, department, position, contact_number, email, address
     FROM employee
     ORDER BY username ASC, employee_id ASC"
);
if (!$employeeResult) {
    throw new RuntimeException('Unable to load employees: ' . $conn->error);
}
while ($employee = $employeeResult->fetch_assoc()) {
    $employees[] = $employee;
}

$selectedEmployeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$selectedEmployeeId = $selectedEmployeeId ?: (int) ($employees[0]['employee_id'] ?? 0);
$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int) $employee['employee_id'] === $selectedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}
if (!$selectedEmployee && $employees !== []) {
    $selectedEmployee = $employees[0];
    $selectedEmployeeId = (int) $selectedEmployee['employee_id'];
}

$tasks = ['completed' => [], 'pending' => [], 'overdue' => []];
if ($selectedEmployee) {
    $taskStmt = $conn->prepare(
        'SELECT office_id, data_type, title, content, status, priority_level, due_date
         FROM office
         WHERE employee_id = ?
         ORDER BY due_date IS NULL ASC, due_date ASC, submission_date DESC, office_id DESC'
    );
    $taskStmt->bind_param('i', $selectedEmployeeId);
    $taskStmt->execute();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Tasks | TaskTrack</title>
    <link rel="stylesheet" href="css/employee.css?v=<?= filemtime(__DIR__ . '/css/employee.css'); ?>">
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
                    <div><span class="eyebrow">TEAM DIRECTORY</span><h1>Employees</h1></div>
                    <span class="employee-total"><?= count($employees); ?></span>
                </div>
                <label class="employee-search">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" id="employeeSearch" placeholder="Search employees" autocomplete="off" aria-label="Search employees">
                </label>
                <div class="directory-columns"><span>NAME</span><span>DEPARTMENT</span></div>
                <nav class="employee-list" id="employeeList" aria-label="Employees">
                    <?php foreach ($employees as $employee): ?>
                        <?php
                            $employeeName = trim((string) ($employee['username'] ?? 'Unnamed employee'));
                            $employeeDepartment = trim((string) ($employee['department'] ?? ''));
                            $isSelected = (int) $employee['employee_id'] === $selectedEmployeeId;
                        ?>
                        <a
                            class="employee-list-item<?= $isSelected ? ' is-selected' : ''; ?>"
                            href="employee.php?employee_id=<?= (int) $employee['employee_id']; ?>"
                            data-search="<?= $escape(strtolower($employeeName . ' ' . $employeeDepartment . ' ' . ($employee['position'] ?? ''))); ?>"
                            <?= $isSelected ? 'aria-current="page"' : ''; ?>
                        >
                            <span class="list-avatar"><?= $escape(strtoupper(mb_substr($employeeName, 0, 1))); ?></span>
                            <span class="list-name"><?= $escape($employeeName); ?><small><?= $escape($employee['position'] ?? 'Employee'); ?></small></span>
                            <span class="list-department"><?= $escape($employeeDepartment !== '' ? $employeeDepartment : '—'); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <p class="directory-empty" id="directoryEmpty" <?= $employees !== [] ? 'hidden' : ''; ?>>
                        <?= $employees === [] ? 'No employees are on record yet.' : 'No employees match your search.'; ?>
                    </p>
                </nav>
            </aside>

            <section class="employee-detail">
                <?php if ($selectedEmployee): ?>
                    <?php
                        $selectedName = trim((string) ($selectedEmployee['username'] ?? 'Unnamed employee'));
                        $department = trim((string) ($selectedEmployee['department'] ?? ''));
                    ?>
                    <section class="profile-card" aria-label="Employee profile">
                        <div class="profile-avatar"><?= $escape(strtoupper(mb_substr($selectedName, 0, 1))); ?></div>
                        <div class="profile-info">
                            <h2><?= $escape($selectedName); ?></h2>
                            <p><?= $escape($selectedEmployee['position'] ?? 'Employee'); ?> <span class="profile-divider">·</span> ID #<?= (int) $selectedEmployee['employee_id']; ?></p>
                        </div>
                        <dl class="profile-facts">
                            <div><dt>Department</dt><dd><?= $escape($department !== '' ? $department : 'Not assigned'); ?></dd></div>
                            <div><dt>Location</dt><dd><?= $escape($selectedEmployee['address'] ?? 'Not provided'); ?></dd></div>
                            <div><dt>Email</dt><dd><?= $escape($selectedEmployee['email'] ?? 'Not provided'); ?></dd></div>
                            <div><dt>Phone</dt><dd><?= $escape($selectedEmployee['contact_number'] ?? 'Not provided'); ?></dd></div>
                        </dl>
                    </section>

                    <div class="task-heading">
                        <div><span class="eyebrow">WORK OVERVIEW</span><h2>Task progress</h2></div>
                        <button class="assign-button" id="openTaskModal" type="button">＋ Assign a task</button>
                    </div>

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
                                            <article class="task-card">
                                                <span class="task-marker" aria-hidden="true"></span>
                                                <div class="task-copy">
                                                    <h4><?= $escape($task['title'] ?? 'Untitled task'); ?></h4>
                                                    <p><?= $escape($task['content'] ?? ''); ?></p>
                                                    <div class="task-meta"><span><?= $escape($task['due_date'] ?: 'No due date'); ?></span><span class="priority-tag priority-<?= $escape(strtolower($task['priority_level'] ?? 'medium')); ?>"><?= $escape(ucfirst($task['priority_level'] ?? 'Medium')); ?></span></div>
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
                        <h2>Select an employee</h2>
                        <p>Choose a team member from the directory to view task progress and assign work.</p>
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
                    <?php foreach (['General', 'Production', 'Logistics', 'HR', 'Payroll', 'IT'] as $categoryOption): ?>
                        <option value="<?= $escape($categoryOption); ?>" <?= $formCategory === $categoryOption ? 'selected' : ''; ?>><?= $escape($categoryOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">Due date<input type="date" name="due_date" min="<?= $escape(date('Y-m-d')); ?>" value="<?= $escape($formDueDate); ?>" required></label>
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
<script>
    const taskDialog = document.getElementById('taskDialog');
    document.getElementById('openTaskModal').addEventListener('click', () => taskDialog.showModal());
    document.getElementById('closeTaskModal').addEventListener('click', () => taskDialog.close());
    document.getElementById('cancelTaskModal').addEventListener('click', () => taskDialog.close());
    taskDialog.addEventListener('click', (event) => {
        if (event.target === taskDialog) taskDialog.close();
    });
    if (<?= json_encode($errorMessage !== ''); ?>) taskDialog.showModal();
</script>
<?php endif; ?>
<script>
    const employeeSearch = document.getElementById('employeeSearch');
    const employeeItems = [...document.querySelectorAll('.employee-list-item')];
    const directoryEmpty = document.getElementById('directoryEmpty');
    employeeSearch.addEventListener('input', () => {
        const query = employeeSearch.value.trim().toLowerCase();
        let visibleCount = 0;
        employeeItems.forEach((item) => {
            const visible = item.dataset.search.includes(query);
            item.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        if (employeeItems.length > 0) {
            directoryEmpty.hidden = visibleCount !== 0;
            if (visibleCount === 0) directoryEmpty.textContent = 'No employees match your search.';
        }
    });
</script>
</body>
</html>
