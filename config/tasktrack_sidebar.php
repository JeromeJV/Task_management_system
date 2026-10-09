<?php
$tasktrackRole = (string) ($_SESSION['role'] ?? '');
$tasktrackActive = $tasktrackActive ?? '';
$tasktrackName = (string) ($_SESSION['name'] ?? 'TaskTrack User');
$tasktrackEmail = (string) ($_SESSION['email'] ?? '');
$tasktrackDashboard = match ($tasktrackRole) {
    'HR' => 'HR.php',
    'super' => 'supervisor.php',
    'pro' => 'factory_main.php',
    'log' => 'delivery_main.php',
    default => 'index.php',
};
$tasktrackRoleLabel = match ($tasktrackRole) {
    'HR' => 'Human Resources',
    'super' => 'Supervisor',
    'pro' => 'Production Staff',
    'log' => 'Logistics',
    default => 'Employee',
};
$tasktrackNavItems = [
    'dashboard' => ['Dashboard', $tasktrackDashboard, 'dashboard'],
    'employee' => ['Employee', 'employee.php', 'employee'],
    'production' => ['Production', 'factory_main.php', 'production'],
    'logistics' => ['Logistics', 'delivery_main.php', 'logistics'],
];
if (in_array($tasktrackRole, ['HR', 'payroll', 'super', 'employee'], true)) {
    $tasktrackNavItems['my-tasks'] = ['My Tasks', 'office_employee.php', 'tasks'];
}
$tasktrackIcons = [
    'dashboard' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
    'employee' => '<circle cx="12" cy="8" r="3.2"/><path d="M5.5 20v-1.2a6.5 6.5 0 0 1 13 0V20z"/>',
    'production' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="m4.3 7.6 7.7 4.5 7.7-4.5M12 12v9"/>',
    'logistics' => '<path d="M3 6h11v11H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
    'tasks' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/>',
    'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
];
?>
<aside class="tasktrack-sidebar" id="sidebar" aria-label="Main navigation">
    <a class="tasktrack-brand" href="<?= htmlspecialchars($tasktrackDashboard, ENT_QUOTES, 'UTF-8'); ?>" aria-label="TaskTrack dashboard">T</a>
    <div class="tasktrack-account">
        <strong>TASKTRACK</strong>
        <span><?= htmlspecialchars($tasktrackRoleLabel . ': ' . $tasktrackName, ENT_QUOTES, 'UTF-8'); ?></span>
        <span><?= htmlspecialchars($tasktrackEmail, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <nav class="tasktrack-nav">
        <?php foreach ($tasktrackNavItems as $key => [$label, $url, $icon]): ?>
            <?php $isActive = $tasktrackActive === $key; ?>
            <a
                class="tasktrack-nav-link<?= $isActive ? ' is-active' : ''; ?>"
                href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                <?= $isActive ? 'aria-current="page"' : ''; ?>
            >
                <span class="tasktrack-icon-cell" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><?= $tasktrackIcons[$icon]; ?></svg></span>
                <span class="tasktrack-nav-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="tasktrack-logout" href="logout.php">
        <span class="tasktrack-icon-cell" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><?= $tasktrackIcons['logout']; ?></svg></span>
        <span class="tasktrack-nav-label">LOG OUT</span>
    </a>
</aside>
