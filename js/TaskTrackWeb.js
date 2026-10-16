const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');
const navItems = document.querySelectorAll('.nav-link');
const pageSections = document.querySelectorAll('main > section[id]');

function closeMenu() {
    navLinks.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open navigation menu');
}

navToggle.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
    navToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
});

navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeMenu);
});

document.addEventListener('click', event => {
    if (!navLinks.contains(event.target) && !navToggle.contains(event.target)) {
        closeMenu();
    }
});

const sectionObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        navItems.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`);
        });
    });
}, { rootMargin: '-30% 0px -55% 0px' });

pageSections.forEach(section => sectionObserver.observe(section));
