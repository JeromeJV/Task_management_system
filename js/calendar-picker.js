(function () {
    const selector = 'input.calendar-control[type="date"], input.calendar-control[type="month"]';
    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    const monthFormatter = new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'long'
    });
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function parseValue(value, type) {
        if (type === 'month' && /^\d{4}-\d{2}$/.test(value)) {
            const [year, month] = value.split('-').map(Number);
            return new Date(year, month - 1, 1);
        }
        if (type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
            const [year, month, day] = value.split('-').map(Number);
            return new Date(year, month - 1, day);
        }
        return null;
    }

    function dateValue(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function monthValue(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    }

    function isWithinRange(value, input) {
        return (!input.min || value >= input.min) && (!input.max || value <= input.max);
    }

    function enhance(input) {
        if (input.dataset.calendarEnhanced === 'true') return;
        input.dataset.calendarEnhanced = 'true';

        const type = input.type;
        const wasRequired = input.required;
        input.required = false;
        input.classList.add('calendar-picker__native');
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');

        const wrapper = document.createElement('div');
        wrapper.className = `calendar-picker calendar-picker--${type}`;
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'calendar-picker__trigger';
        if (input.id) trigger.id = `${input.id}-calendar-trigger`;
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');

        const valueText = document.createElement('span');
        valueText.className = 'calendar-picker__value';
        const icon = document.createElement('span');
        icon.className = 'calendar-picker__icon';
        icon.setAttribute('aria-hidden', 'true');
        trigger.append(valueText, icon);

        const popover = document.createElement('div');
        popover.className = 'calendar-picker__popover';
        popover.hidden = true;
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', type === 'month' ? 'Choose month and year' : 'Choose date');

        const header = document.createElement('div');
        header.className = 'calendar-picker__header';
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'calendar-picker__nav';
        previous.setAttribute('aria-label', type === 'month' ? 'Previous year' : 'Previous month');
        previous.textContent = '‹';
        const title = document.createElement('strong');
        title.className = 'calendar-picker__title';
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'calendar-picker__nav';
        next.setAttribute('aria-label', type === 'month' ? 'Next year' : 'Next month');
        next.textContent = '›';
        header.append(previous, title, next);

        const grid = document.createElement('div');
        grid.className = 'calendar-picker__grid';
        const footer = document.createElement('div');
        footer.className = 'calendar-picker__footer';
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'calendar-picker__text-action';
        clear.textContent = 'Clear';
        clear.hidden = wasRequired;
        const today = document.createElement('button');
        today.type = 'button';
        today.className = 'calendar-picker__text-action calendar-picker__text-action--primary';
        today.textContent = type === 'month' ? 'This month' : 'Today';
        footer.append(clear, today);
        popover.append(header, grid, footer);
        wrapper.append(trigger, popover);

        const label = (input.id
            ? document.querySelector(`label[for="${CSS.escape(input.id)}"]`)
            : null) || input.closest('label');
        const accessibleLabel = input.getAttribute('aria-label') || label?.textContent.trim() || input.id;
        if (label && label.tagName === 'LABEL' && label.htmlFor === input.id) {
            label.htmlFor = trigger.id;
        }

        let viewDate = parseValue(input.value, type) || new Date();

        function updateTrigger() {
            const selected = parseValue(input.value, type);
            valueText.textContent = selected
                ? (type === 'month' ? monthFormatter.format(selected) : dateFormatter.format(selected))
                : (type === 'month' ? 'Select month' : 'Select date');
            trigger.classList.toggle('is-placeholder', !selected);
            trigger.disabled = input.disabled;
            trigger.setAttribute('aria-label', accessibleLabel
                ? `${accessibleLabel}: ${valueText.textContent}`
                : valueText.textContent);
        }

        function setValue(value) {
            if (!isWithinRange(value, input)) return;
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            wrapper.classList.remove('is-invalid');
            trigger.removeAttribute('aria-invalid');
            updateTrigger();
            close();
            trigger.focus();
        }

        function createButton(text, className, value, disabled) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = className;
            button.textContent = text;
            button.disabled = disabled;
            if (value) {
                button.addEventListener('click', () => setValue(value));
            }
            return button;
        }

        function render() {
            grid.replaceChildren();
            if (type === 'month') {
                title.textContent = String(viewDate.getFullYear());
                grid.classList.add('calendar-picker__grid--months');
                for (let month = 0; month < 12; month += 1) {
                    const date = new Date(viewDate.getFullYear(), month, 1);
                    const value = monthValue(date);
                    const button = createButton(
                        new Intl.DateTimeFormat(undefined, { month: 'short' }).format(date),
                        'calendar-picker__cell calendar-picker__month',
                        value,
                        !isWithinRange(value, input)
                    );
                    button.setAttribute('aria-pressed', String(value === input.value));
                    grid.appendChild(button);
                }
                return;
            }

            title.textContent = monthFormatter.format(viewDate);
            grid.classList.remove('calendar-picker__grid--months');
            weekdays.forEach((weekday) => {
                const heading = document.createElement('span');
                heading.className = 'calendar-picker__weekday';
                heading.textContent = weekday;
                grid.appendChild(heading);
            });

            const firstDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            const startOffset = firstDay.getDay();
            const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
            const previousMonthDays = new Date(viewDate.getFullYear(), viewDate.getMonth(), 0).getDate();

            for (let index = 0; index < 42; index += 1) {
                let day;
                let date;
                let outsideMonth = false;
                if (index < startOffset) {
                    day = previousMonthDays - startOffset + index + 1;
                    date = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, day);
                    outsideMonth = true;
                } else if (index >= startOffset + daysInMonth) {
                    day = index - startOffset - daysInMonth + 1;
                    date = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, day);
                    outsideMonth = true;
                } else {
                    day = index - startOffset + 1;
                    date = new Date(viewDate.getFullYear(), viewDate.getMonth(), day);
                }

                const value = dateValue(date);
                const button = createButton(
                    String(day),
                    `calendar-picker__cell${outsideMonth ? ' is-outside' : ''}`,
                    value,
                    !isWithinRange(value, input)
                );
                button.setAttribute('aria-label', dateFormatter.format(date));
                button.setAttribute('aria-pressed', String(value === input.value));
                const current = new Date();
                if (value === dateValue(current)) button.classList.add('is-today');
                grid.appendChild(button);
            }
        }

        function open() {
            if (trigger.disabled) return;
            document.querySelectorAll('.calendar-picker.is-open').forEach((other) => {
                if (other !== wrapper) {
                    other.classList.remove('is-open', 'is-above');
                    other.querySelector('.calendar-picker__popover').hidden = true;
                    other.querySelector('.calendar-picker__trigger').setAttribute('aria-expanded', 'false');
                }
            });
            viewDate = parseValue(input.value, type) || new Date();
            render();
            wrapper.classList.add('is-open');
            popover.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const triggerRect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - triggerRect.bottom;
            wrapper.classList.toggle('is-above', spaceBelow < 330 && triggerRect.top > spaceBelow);
            const selected = grid.querySelector('[aria-pressed="true"]:not(:disabled)');
            (selected || grid.querySelector('button:not(:disabled)'))?.focus();
        }

        function close() {
            wrapper.classList.remove('is-open', 'is-above');
            popover.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        previous.addEventListener('click', () => {
            viewDate = type === 'month'
                ? new Date(viewDate.getFullYear() - 1, viewDate.getMonth(), 1)
                : new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
            render();
        });
        next.addEventListener('click', () => {
            viewDate = type === 'month'
                ? new Date(viewDate.getFullYear() + 1, viewDate.getMonth(), 1)
                : new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
            render();
        });
        trigger.addEventListener('click', () => {
            if (popover.hidden) open();
            else close();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
        grid.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                trigger.focus();
            }
        });
        clear.addEventListener('click', () => setValue(''));
        today.addEventListener('click', () => {
            const now = new Date();
            setValue(type === 'month' ? monthValue(now) : dateValue(now));
        });
        input.addEventListener('change', updateTrigger);
        input.addEventListener('input', updateTrigger);
        document.addEventListener('pointerdown', (event) => {
            if (!wrapper.contains(event.target)) close();
        });

        if (wasRequired && input.form) {
            input.form.addEventListener('submit', (event) => {
                if (event.defaultPrevented || input.value) return;
                event.preventDefault();
                wrapper.classList.add('is-invalid');
                trigger.setAttribute('aria-invalid', 'true');
                trigger.focus();
                open();
            });
        }
        updateTrigger();
    }

    function initialize(root) {
        if (root.matches?.(selector)) enhance(root);
        root.querySelectorAll?.(selector).forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialize(document), { once: true });
    } else {
        initialize(document);
    }

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) initialize(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
