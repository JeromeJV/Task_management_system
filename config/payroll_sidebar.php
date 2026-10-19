<?php
$payrollSidebarOnTasks = $payrollSidebarOnTasks ?? false;
$payrollSidebarActive = $payrollSidebarActive ?? 'dashboard';
$payrollName = explode('@', (string) ($_SESSION['email'] ?? 'Payroll Manager'))[0];
$payrollProfilePicture = null;
if (isset($conn, $_SESSION['id']) && $conn instanceof mysqli) {
    $payrollProfilePictureStmt = $conn->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
    if (!$payrollProfilePictureStmt) {
        throw new RuntimeException('Unable to prepare the payroll sidebar profile picture lookup: ' . $conn->error);
    }
    $payrollUserId = (int) $_SESSION['id'];
    $payrollProfilePictureStmt->bind_param('i', $payrollUserId);
    if (!$payrollProfilePictureStmt->execute()) {
        $error = $payrollProfilePictureStmt->error;
        $payrollProfilePictureStmt->close();
        throw new RuntimeException('Unable to load the payroll sidebar profile picture: ' . $error);
    }
    $payrollProfilePicture = $payrollProfilePictureStmt->get_result()->fetch_assoc()['profile_picture'] ?? null;
    $payrollProfilePictureStmt->close();
    if (
        !is_string($payrollProfilePicture)
        || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $payrollProfilePicture)
    ) {
        $payrollProfilePicture = null;
    }
}
$payrollInitial = mb_strtoupper(mb_substr(trim($payrollName), 0, 1));

$payrollSidebarItems = [
    'dashboard' => ['Dashboard', 'fa-house', 'dashboard'],
    'employees' => ['Employee Masterlist', 'fa-user', 'employees'],
    'calculator' => ['Calculate Payroll', 'fa-calculator', 'calculator'],
];
?>
<aside class="payroll-sidebar bg-white border-r border-zinc-200 flex flex-col justify-between h-screen sticky top-0 z-30 select-none" id="payrollSidebar">
    <div class="flex flex-col w-full">
        <div class="p-5 border-b border-zinc-200 flex items-center gap-3">
            <div class="payroll-sidebar-avatar w-10 h-10 rounded-xs shrink-0">
                <?php if ($payrollProfilePicture !== null): ?>
                    <img src="uploads/profile-pictures/<?= htmlspecialchars($payrollProfilePicture, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($payrollInitial !== '' ? $payrollInitial : 'U', ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
            <div class="overflow-hidden leading-tight">
                <h4 class="font-bold text-sm text-zinc-900 truncate"><?= htmlspecialchars($payrollName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h4>
                <p class="text-[11px] text-zinc-400 capitalize truncate">Payroll Manager</p>
            </div>
        </div>
        <nav class="flex flex-col pt-4 w-full">
            <?php foreach ($payrollSidebarItems as $key => [$label, $icon, $tab]): ?>
                <?php if ($payrollSidebarOnTasks): ?>
                    <a
                        href="payroll.php?tab=<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        title="<?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative<?= $payrollSidebarActive === $key ? ' active' : ''; ?>"
                        <?= $payrollSidebarActive === $key ? 'aria-current="page"' : ''; ?>
                    >
                <?php else: ?>
                    <button
                        type="button"
                        onclick="switchTab('<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>')"
                        aria-label="<?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        title="<?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        id="nav-<?= $key === 'calculator' ? 'calc' : $key; ?>"
                        class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative<?= $payrollSidebarActive === $key ? ' active' : ''; ?>"
                    >
                <?php endif; ?>
                    <i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> text-sm w-5 text-center"></i>
                    <span><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                <?php if ($payrollSidebarOnTasks): ?>
                    </a>
                <?php else: ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>

            <a
                href="office_employee.php"
                aria-label="My Tasks"
                title="My Tasks"
                class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative<?= $payrollSidebarActive === 'tasks' ? ' active' : ''; ?>"
                <?= $payrollSidebarActive === 'tasks' ? 'aria-current="page"' : ''; ?>
            >
                <i class="fa-solid fa-list-check text-sm w-5 text-center"></i>
                <span>My Tasks</span>
            </a>
        </nav>
    </div>
    <div class="p-4 border-t border-zinc-100 w-full">
        <a href="logout.php" aria-label="Log out" title="Log out" class="w-full px-3 py-2.5 flex items-center gap-4 text-zinc-600 hover:text-red-600 transition text-xs font-semibold">
            <i class="fa-solid fa-right-from-bracket text-sm w-5 text-center"></i>
            <span>Log out</span>
        </a>
    </div>
</aside>
<button class="payroll-mobile-menu-toggle" type="button" data-payroll-menu-toggle aria-controls="payrollSidebar" aria-expanded="false" aria-label="Open Payroll navigation" title="Open Payroll navigation">
    <span></span><span></span><span></span>
</button>
<div class="payroll-mobile-menu-backdrop" data-payroll-menu-backdrop hidden></div>
<script src="js/payroll-mobile-menu.js?v=<?= filemtime(__DIR__ . '/../js/payroll-mobile-menu.js'); ?>"></script>
