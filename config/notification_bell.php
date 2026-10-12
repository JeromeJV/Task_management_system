<?php
if (empty($_SESSION['notification_csrf'])) {
    $_SESSION['notification_csrf'] = bin2hex(random_bytes(32));
}
?>
<div class="task-notification" data-notification-root data-csrf="<?= htmlspecialchars((string) $_SESSION['notification_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
    <button class="task-notification__toggle" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="taskNotificationPanel">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
        <span class="task-notification__count" hidden></span>
    </button>
    <section class="task-notification__panel" id="taskNotificationPanel" aria-label="Notifications" hidden>
        <header class="task-notification__header">
            <div><strong>Notifications</strong><span data-notification-summary>Recent task updates</span></div>
            <button type="button" data-notification-read-all>Mark all read</button>
        </header>
        <div class="task-notification__list" data-notification-list aria-live="polite"></div>
    </section>
</div>
<link rel="stylesheet" href="css/task-notifications.css?v=<?= filemtime(__DIR__ . '/../css/task-notifications.css'); ?>">
<script src="js/task-notifications.js?v=<?= filemtime(__DIR__ . '/../js/task-notifications.js'); ?>"></script>
