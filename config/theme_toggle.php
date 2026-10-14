<button
    class="task-theme-toggle"
    type="button"
    role="switch"
    aria-label="Dark mode"
    aria-checked="false"
    title="Switch to dark mode"
    data-theme-toggle
>
    <span class="task-theme-toggle__track" aria-hidden="true">
        <span class="task-theme-toggle__icon task-theme-toggle__icon--moon">☾</span>
        <span class="task-theme-toggle__icon task-theme-toggle__icon--sun">☀</span>
        <span class="task-theme-toggle__thumb"></span>
    </span>
</button>
<link rel="stylesheet" href="css/theme.css?v=<?= md5_file(__DIR__ . '/../css/theme.css'); ?>">
<script src="js/theme.js?v=<?= filemtime(__DIR__ . '/../js/theme.js'); ?>"></script>
