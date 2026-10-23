<div class="mobile-app-navbar">
    <button class="hr-mobile-menu-toggle" type="button" data-hr-menu-toggle aria-controls="hrSidebar" aria-expanded="false" aria-label="Open HR navigation" title="Open HR navigation">
        <span></span><span></span><span></span>
    </button>
    <?php $mobileRefreshInNavbar = true; ?>
    <?php include __DIR__ . '/mobile_refresh.php'; ?>
    <?php unset($mobileRefreshInNavbar); ?>
</div>
<div class="hr-mobile-menu-backdrop" data-hr-menu-backdrop hidden></div>
<script src="js/hr-mobile-menu.js?v=<?= filemtime(__DIR__ . '/../js/hr-mobile-menu.js'); ?>"></script>