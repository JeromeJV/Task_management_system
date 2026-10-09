<?php
include("config/connection.php");
include("config/registerBE.php");

// 1. Kunin lang ang mga empleyado na WALA PANG nililikhang account sa `users` table
$empQuery = mysqli_query($conn, "
    SELECT e.employee_id, e.username, e.email, e.position 
    FROM employee e 
    LEFT JOIN users u ON e.email = u.email 
    WHERE u.id IS NULL 
    ORDER BY e.username ASC
");

// 2. Kunin ang lahat ng may aktibong system account mula sa `users` table para sa Table
$usersQuery = mysqli_query($conn, "SELECT * FROM `users` ORDER BY id ASC");
$records = [];
$count = 0;

if (!$usersQuery) {
    throw new RuntimeException('Unable to load system accounts: ' . mysqli_error($conn));
}
$count = mysqli_num_rows($usersQuery);
while ($userRow = mysqli_fetch_assoc($usersQuery)) {
    $records[] = $userRow;
}

$eligible_employees = [];
if (!$empQuery) {
    throw new RuntimeException('Unable to load employees available for account registration: ' . mysqli_error($conn));
}
while ($employee = mysqli_fetch_assoc($empQuery)) {
    $eligible_employees[] = $employee;
}
$selected_employee_label = '';
foreach ($eligible_employees as $employee) {
    if ((string) ($employee['employee_id'] ?? '') === (string) ($_POST['employee_id'] ?? '')) {
        $selected_employee_label = ($employee['username'] ?? '') . ' (' . ($employee['position'] ?? 'Employee') . ')';
        break;
    }
}

$employee_count_result = mysqli_query($conn, "SELECT COUNT(*) AS employee_count FROM employee");
if (!$employee_count_result) {
    throw new RuntimeException('Unable to load employee count: ' . mysqli_error($conn));
}
$employee_count = (int) mysqli_fetch_assoc($employee_count_result)['employee_count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/register.css?v=<?= filemtime(__DIR__ . '/css/register.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
</head>
<body>
    <div class="registration-shell">
        <aside class="registration-sidebar" aria-label="HR navigation">
            <a class="registration-brand" href="HR.php" aria-label="Back to HR dashboard">
                <span class="registration-brand-mark" aria-hidden="true">T</span>
                <span class="registration-brand-name">TASKTRACK</span>
            </a>
            <nav class="registration-nav">
                <a class="registration-nav-link" href="HR.php"><span aria-hidden="true">▦</span><span>Employees</span></a>
                <a class="registration-nav-link is-active" href="register.php" aria-current="page"><span aria-hidden="true">＋</span><span>Registration</span></a>
                <a class="registration-nav-link" href="atten.php"><span aria-hidden="true">◷</span><span>Attendance</span></a>
                <a class="registration-nav-link" href="application_form.php"><span aria-hidden="true">▤</span><span>Applicants</span></a>
                <a class="registration-nav-link" href="interview_sched.php"><span aria-hidden="true">▣</span><span>Scheduled</span></a>
            </nav>
            <a class="registration-nav-link registration-logout" href="logout.php"><span aria-hidden="true">↪</span><span>Log out</span></a>
        </aside>

        <main class="registration-main">
            <header class="registration-header">
                <div>
                    <p class="registration-eyebrow">HUMAN RESOURCES</p>
                    <h1>Account Registration</h1>
                    <p>Create and manage system access for your employees.</p>
                </div>
                <a class="back-to-hr" href="HR.php">Back to HR</a>
            </header>

            <?php if (!empty($msg)): ?>
                <div class="registration-alert is-info" role="status"><?= htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if (!empty($delete_message)): ?>
                <div class="registration-alert <?= strpos($delete_message, 'Error') === 0 ? 'is-error' : 'is-info'; ?>" role="status">
                    <?= htmlspecialchars($delete_message); ?>
                </div>
            <?php endif; ?>

            <section class="registration-stat-grid" aria-label="Registration summary">
                <article class="registration-stat-card is-employees">
                    <span class="registration-stat-label">Total Employee</span>
                    <strong class="registration-stat-value"><?= $employee_count; ?></strong>
                    <span class="registration-stat-icon" aria-hidden="true">♙</span>
                </article>
                <article class="registration-stat-card is-create">
                    <span class="registration-stat-label">To Be Created</span>
                    <strong class="registration-stat-value"><?= count($eligible_employees); ?></strong>
                    <span class="registration-stat-icon" aria-hidden="true">▣</span>
                </article>
                <article class="registration-stat-card is-users">
                    <span class="registration-stat-label">Users</span>
                    <strong class="registration-stat-value"><?= $count; ?></strong>
                    <span class="registration-stat-icon" aria-hidden="true">✓</span>
                </article>
            </section>

            <div class="registration-tabs" role="search" aria-label="Search and filter system accounts">
                <label class="registration-list-search">
                    <span aria-hidden="true">⌕</span>
                    <span class="visually-hidden">Search system accounts</span>
                    <input id="accountSearch" type="search" placeholder="Search name, email, ID..." autocomplete="off">
                </label>
                <label class="registration-filter">
                    <span>Filter Stage:</span>
                    <select id="roleFilter" aria-label="Filter accounts by role">
                        <option value="">All Roles</option>
                        <?php
                            $roles = array_unique(array_filter(array_map(static fn ($row) => trim($row['role'] ?? ''), $records)));
                            natcasesort($roles);
                        ?>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars(strtolower($role), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(ucfirst($role)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="registration-reset" id="resetAccountFilters" type="button">Reset</button>
            </div>

            <div class="registration-workspace">
                <section class="accounts-panel" aria-labelledby="registeredAccountsTitle">
                    <div class="accounts-panel-heading">
                        <div>
                            <p class="registration-eyebrow">SYSTEM ACCESS</p>
                            <h2 id="registeredAccountsTitle">Registered Accounts</h2>
                        </div>
                        <span class="account-count" id="accountCount"><?= $count; ?> users</span>
                    </div>

                    <?php if (!empty($records)): ?>
                        <div class="accounts-table-wrap table-scroll">
                            <table class="accounts-table">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>ID</th>
                                        <th>Role</th>
                                        <th><span class="visually-hidden">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody id="accountTableBody">
                                    <?php foreach ($records as $row): ?>
                                        <?php
                                            $account_search = strtolower(implode(' ', [
                                                $row['email'] ?? '',
                                                $row['name'] ?? '',
                                                $row['id'] ?? '',
                                                $row['role'] ?? '',
                                            ]));
                                        ?>
                                        <tr
                                            data-search="<?= htmlspecialchars($account_search, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-role="<?= htmlspecialchars(strtolower($row['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <td><a href="mailto:<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($row['email'] ?? ''); ?></a></td>
                                            <td><?= htmlspecialchars($row['name'] ?? ''); ?></td>
                                            <td><?= htmlspecialchars($row['id'] ?? ''); ?></td>
                                            <td><span class="role-pill"><?= htmlspecialchars(ucfirst($row['role'] ?? '')); ?></span></td>
                                            <td>
                                                <form action="register.php" method="POST">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button
                                                        class="account-delete"
                                                        type="submit"
                                                        name="delete_user"
                                                        value="Delete"
                                                        aria-label="Delete account for <?= htmlspecialchars($row['name'] ?? 'user', ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="return confirm('Are you sure you want to delete this user account?');"
                                                    >×</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="accounts-empty-filter" id="noAccountResults" hidden>No accounts match your search or filter.</p>
                    <?php else: ?>
                        <div class="accounts-empty">
                            <span aria-hidden="true">＋</span>
                            <h3>No system accounts yet</h3>
                            <p>Create the first account using the registration form.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="account-form-panel" aria-labelledby="accountFormTitle">
                    <p class="registration-eyebrow">NEW ACCESS</p>
                    <h2 id="accountFormTitle">Account Registration</h2>
                    <p class="account-form-intro">Choose an employee and set up their system login.</p>

                    <form action="register.php" method="post" class="account-registration-form">
                        <div class="registration-field">
                            <label for="employeeSearch">Employee</label>
                            <input
                                type="text"
                                id="employeeSearch"
                                list="employeeList"
                                class="<?= !empty($name_err) ? 'is-invalid' : ''; ?>"
                                placeholder="Search / select employee"
                                value="<?= htmlspecialchars($selected_employee_label, ENT_QUOTES, 'UTF-8'); ?>"
                                oninput="handleEmployeeSelect()"
                                autocomplete="off"
                                required
                            >
                            <input type="hidden" name="employee_id" id="employee_id" value="<?= htmlspecialchars($_POST['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <datalist id="employeeList">
                                <?php foreach ($eligible_employees as $employee): ?>
                                    <?php $display_text = ($employee['username'] ?? '') . ' (' . ($employee['position'] ?? 'Employee') . ')'; ?>
                                    <option
                                        value="<?= htmlspecialchars($display_text, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-id="<?= htmlspecialchars($employee['employee_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-email="<?= htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    ></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (!empty($name_err)): ?><span class="field-error"><?= htmlspecialchars($name_err); ?></span><?php endif; ?>
                            <?php if (empty($eligible_employees)): ?><span class="field-hint">All employees already have system accounts.</span><?php endif; ?>
                        </div>

                        <div class="registration-field">
                            <label for="emailInput">Email</label>
                            <input type="email" name="email" id="emailInput" placeholder="Employee email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                            <?php if (!empty($email_err)): ?><span class="field-error"><?= htmlspecialchars($email_err); ?></span><?php endif; ?>
                        </div>

                        <div class="registration-field">
                            <label for="roleSelect">System Role</label>
                            <select name="role" id="roleSelect">
                                <option value="HR" <?= (($_POST['role'] ?? '') === 'HR') ? 'selected' : ''; ?>>HR</option>
                                <option value="payroll" <?= (($_POST['role'] ?? '') === 'payroll') ? 'selected' : ''; ?>>Payroll</option>
                                <option value="super" <?= (($_POST['role'] ?? '') === 'super') ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="log" <?= (($_POST['role'] ?? '') === 'log') ? 'selected' : ''; ?>>Logistics</option>
                                <option value="pro" <?= (($_POST['role'] ?? '') === 'pro') ? 'selected' : ''; ?>>Production</option>
                                <option value="employee" <?= (($_POST['role'] ?? '') === 'employee') ? 'selected' : ''; ?>>Employee</option>
                            </select>
                            <?php if (!empty($role_err)): ?><span class="field-error"><?= htmlspecialchars($role_err, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        </div>

                        <div class="registration-field">
                            <label for="accountPassword">Password</label>
                            <input type="password" name="password" id="accountPassword" placeholder="At least 8 characters" required>
                            <?php if (!empty($password_err)): ?><span class="field-error"><?= htmlspecialchars($password_err); ?></span><?php endif; ?>
                        </div>

                        <div class="registration-field">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" name="cpassword" id="confirmPassword" placeholder="Re-enter password" required>
                            <?php if (!empty($cpassword_err)): ?><span class="field-error"><?= htmlspecialchars($cpassword_err); ?></span><?php endif; ?>
                        </div>

                        <button type="submit" class="create-account-button" name="register_user" <?= empty($eligible_employees) ? 'disabled' : ''; ?>>Create Account</button>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        function handleEmployeeSelect() {
            const inputVal = document.getElementById('employeeSearch').value;
            const options = document.querySelectorAll('#employeeList option');
            const hiddenIdInput = document.getElementById('employee_id');
            const emailInput = document.getElementById('emailInput');

            let matched = false;

            options.forEach(option => {
                if (option.value === inputVal) {
                    hiddenIdInput.value = option.getAttribute('data-id');
                    emailInput.value = option.getAttribute('data-email');
                    matched = true;
                }
            });

            // Kapag binura o pinalitan ng user ang text na walang match sa listahan
            if (!matched) {
                hiddenIdInput.value = '';
                emailInput.value = '';
            }
        }

        const accountSearch = document.getElementById('accountSearch');
        const roleFilter = document.getElementById('roleFilter');
        const accountRows = Array.from(document.querySelectorAll('#accountTableBody tr'));
        const accountCount = document.getElementById('accountCount');
        const noAccountResults = document.getElementById('noAccountResults');

        function filterAccounts() {
            const searchTerm = accountSearch.value.trim().toLowerCase();
            const selectedRole = roleFilter.value;
            let visibleCount = 0;

            accountRows.forEach((row) => {
                const matchesSearch = row.dataset.search.includes(searchTerm);
                const matchesRole = !selectedRole || row.dataset.role === selectedRole;
                const isVisible = matchesSearch && matchesRole;

                row.hidden = !isVisible;
                if (isVisible) visibleCount += 1;
            });

            accountCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'user' : 'users'}`;
            if (noAccountResults) noAccountResults.hidden = visibleCount !== 0;
        }

        accountSearch.addEventListener('input', filterAccounts);
        roleFilter.addEventListener('change', filterAccounts);
        document.getElementById('resetAccountFilters').addEventListener('click', () => {
            accountSearch.value = '';
            roleFilter.value = '';
            filterAccounts();
            accountSearch.focus();
        });
    </script>
</body> 
</html>