<?php
if (!empty($mobileRefreshAlreadyRendered)) {
    return;
}
$mobileRefreshAlreadyRendered = true;
$mobileRefreshClass = !empty($mobileRefreshInNavbar) ? 'is-in-navbar' : 'is-floating';
?>
<button class="mobile-refresh-button <?= $mobileRefreshClass; ?>" type="button" aria-label="Refresh this page" title="Refresh this page" onclick="window.location.reload()">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M20 7v5h-5M4 17v-5h5"></path>
        <path d="M5.6 9A7 7 0 0 1 18 6l2 2M4 16l2 2a7 7 0 0 0 12.4-3"></path>
    </svg>
    <span>Refresh</span>
</button>
<link rel="stylesheet" href="css/mobile-refresh.css?v=<?= filemtime(__DIR__ . '/../css/mobile-refresh.css'); ?>">
