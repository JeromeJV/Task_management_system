(() => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) return;

    const dockToggle = () => {
        const targets = [
            '.topbar-actions',
            '.topbar .assign',
            '.nav-controls',
            '.payroll-app-layout header > div:last-child',
            '.employee-topbar',
            '.admin-header',
            '.page-topbar',
            '.hr-greeting',
            '.topbar',
            '.header-actions',
            'body > .header',
            'body > header:not(.tt-chatbot-header)'
        ];
        const target = targets.map((selector) => document.querySelector(selector)).find(Boolean);

        if (target) {
            target.append(toggle);
            toggle.classList.add('is-docked');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', dockToggle, { once: true });
    } else {
        dockToggle();
    }

    const storageKey = 'tasktrack-theme';
    let theme = 'light';
    try {
        theme = localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light';
    } catch (error) {
        console.warn('TaskTrack could not read the saved theme preference.', error);
    }

    const applyTheme = (selectedTheme) => {
        document.documentElement.dataset.theme = selectedTheme;
        toggle.setAttribute('aria-checked', String(selectedTheme === 'dark'));
        toggle.setAttribute('aria-label', `${selectedTheme === 'dark' ? 'Dark' : 'Light'} mode`);
        toggle.title = `Switch to ${selectedTheme === 'dark' ? 'light' : 'dark'} mode`;
        document.dispatchEvent(new CustomEvent('tasktrack-theme-change', { detail: { theme: selectedTheme } }));
    };

    applyTheme(theme);
    toggle.addEventListener('click', () => {
        theme = theme === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            console.warn('TaskTrack could not save the theme preference.', error);
        }
    });
})();
