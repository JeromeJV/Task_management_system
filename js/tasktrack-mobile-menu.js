(() => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('[data-tasktrack-menu-toggle]');
    const backdrop = document.querySelector('[data-tasktrack-menu-backdrop]');
    if (!sidebar || !toggle || !backdrop) return;

    const mobileQuery = window.matchMedia('(max-width: 768px)');

    const setOpen = (isOpen) => {
        const shouldOpen = mobileQuery.matches && isOpen;
        sidebar.classList.toggle('is-mobile-open', shouldOpen);
        sidebar.inert = mobileQuery.matches && !shouldOpen;
        backdrop.hidden = !shouldOpen;
        toggle.setAttribute('aria-expanded', String(shouldOpen));
        toggle.setAttribute('aria-label', shouldOpen ? 'Close navigation menu' : 'Open navigation menu');
        toggle.title = shouldOpen ? 'Close navigation menu' : 'Open navigation menu';
        document.body.classList.toggle('tasktrack-menu-open', shouldOpen);
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('.tasktrack-nav-link, .tasktrack-logout').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });

    const handleViewportChange = () => setOpen(false);
    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }
    setOpen(false);
})();