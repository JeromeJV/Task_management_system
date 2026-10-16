(() => {
    const sidebar = document.getElementById('hrSidebar');
    const toggle = document.querySelector('[data-hr-menu-toggle]');
    const backdrop = document.querySelector('[data-hr-menu-backdrop]');
    if (!sidebar || !toggle || !backdrop) return;

    const mobileQuery = window.matchMedia('(max-width: 600px)');
    const setOpen = (isOpen) => {
        const shouldOpen = mobileQuery.matches && isOpen;
        sidebar.classList.toggle('is-mobile-open', shouldOpen);
        sidebar.inert = mobileQuery.matches && !shouldOpen;
        backdrop.hidden = !shouldOpen;
        toggle.setAttribute('aria-expanded', String(shouldOpen));
        toggle.setAttribute('aria-label', shouldOpen ? 'Close HR navigation' : 'Open HR navigation');
        toggle.title = shouldOpen ? 'Close HR navigation' : 'Open HR navigation';
        document.body.classList.toggle('hr-menu-open', shouldOpen);
    };

    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
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