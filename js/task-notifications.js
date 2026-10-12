(() => {
    const root = document.querySelector('[data-notification-root]');
    if (!root) return;

    const toggle = root.querySelector('.task-notification__toggle');
    const panel = root.querySelector('.task-notification__panel');
    const list = root.querySelector('[data-notification-list]');
    const count = root.querySelector('.task-notification__count');
    const summary = root.querySelector('[data-notification-summary]');
    const csrfToken = root.dataset.csrf || '';
    const endpoint = 'config/notifications_API.php';
    let loadPromise = null;
    let isOpen = false;
    let shownNotifications = [];

    function displayDate(value) {
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
    }

    function render(data) {
        const unread = Number(data.unreadCount) || 0;
        count.hidden = unread === 0;
        count.textContent = unread > 99 ? '99+' : String(unread);
        toggle.setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
        summary.textContent = unread ? `${unread} unread ${unread === 1 ? 'notification' : 'notifications'}` : 'You are all caught up';
        shownNotifications = Array.isArray(data.notifications) ? data.notifications : [];
        list.replaceChildren();

        if (!shownNotifications.length) {
            const empty = document.createElement('div');
            empty.className = 'task-notification__empty';
            empty.textContent = 'No task notifications yet. New assignments and task updates will appear here.';
            list.appendChild(empty);
            return;
        }

        shownNotifications.forEach((notification) => {
            const item = document.createElement('a');
            item.className = `task-notification__item${Number(notification.is_read) ? '' : ' is-unread'}`;
            const target = new URL(notification.target_url, window.location.href);
            item.href = target.origin === window.location.origin ? target.href : window.location.href;

            const dot = document.createElement('span');
            dot.className = 'task-notification__dot';
            dot.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            copy.className = 'task-notification__item-copy';
            const title = document.createElement('strong');
            title.textContent = notification.title;
            const message = document.createElement('span');
            message.textContent = notification.message;
            const date = document.createElement('time');
            date.dateTime = notification.created_at.replace(' ', 'T');
            date.textContent = displayDate(notification.created_at);
            copy.append(title, message, date);
            item.append(dot, copy);
            item.addEventListener('click', async (event) => {
                event.preventDefault();
                try {
                    await markRead(notification.notification_id);
                    window.location.assign(item.href);
                } catch (error) {
                    summary.textContent = error.message || 'Unable to mark notification as read.';
                }
            });
            list.appendChild(item);
        });
    }

    function load() {
        if (loadPromise) return loadPromise;
        loadPromise = (async () => {
            try {
                const response = await fetch(`${endpoint}?action=list`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || data.message || 'Unable to load notifications.');
                render(data);
            } catch (error) {
                summary.textContent = error.message || 'Unable to load notifications.';
                const message = document.createElement('div');
                message.className = 'task-notification__error';
                message.textContent = error.message || 'Unable to load notifications. Refresh the page to try again.';
                if (!shownNotifications.length) list.replaceChildren(message);
                else list.prepend(message);
            } finally {
                loadPromise = null;
            }
        })();
        return loadPromise;
    }

    async function post(action, notificationId = '') {
        const body = new URLSearchParams({ action, csrf_token: csrfToken });
        if (notificationId) body.set('notification_id', notificationId);
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || data.message || 'Unable to update notifications.');
        await load();
    }

    async function markRead(notificationId) {
        await post('read', notificationId);
    }

    toggle.addEventListener('click', async () => {
        isOpen = !isOpen;
        panel.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) await load();
    });

    root.querySelector('[data-notification-read-all]').addEventListener('click', async () => {
        try {
            await post('read_all');
        } catch (error) {
            summary.textContent = error.message || 'Unable to mark notifications as read.';
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!root.contains(event.target) && isOpen) {
            isOpen = false;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            isOpen = false;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });

    load();
    window.setInterval(load, 30000);
})();
