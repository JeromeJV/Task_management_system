(function () {
    const selector = 'select:not([multiple]):not([size]):not(.assignment-select):not(.production-assignment-select)';
    let nextId = 0;

    function enhance(select) {
        if (select.dataset.selectEnhanced === 'true') return;
        select.dataset.selectEnhanced = 'true';

        const wasRequired = select.required;
        select.required = false;
        select.tabIndex = -1;
        select.classList.add('select-control__native');
        select.setAttribute('aria-hidden', 'true');

        const wrapper = document.createElement('div');
        wrapper.className = 'select-control';
        if (select.classList.contains('hr-filter-select')) {
            wrapper.classList.add('select-control--hr-filter');
        }
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'select-control__trigger';
        trigger.id = `select-control-${++nextId}`;
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-required', String(wasRequired));

        const value = document.createElement('span');
        value.className = 'select-control__value';
        const chevron = document.createElement('span');
        chevron.className = 'select-control__chevron';
        chevron.setAttribute('aria-hidden', 'true');
        trigger.append(value, chevron);

        const panel = document.createElement('div');
        panel.className = 'select-control__panel';
        if (select.closest('.status-filter')) {
            panel.classList.add('select-control__panel--status');
        }
        panel.id = `${trigger.id}-panel`;
        wrapper.dataset.selectPanelId = panel.id;
        panel.hidden = true;
        const list = document.createElement('div');
        list.className = 'select-control__options';
        list.id = `${trigger.id}-options`;
        list.setAttribute('role', 'listbox');
        panel.appendChild(list);
        wrapper.appendChild(trigger);
        document.body.appendChild(panel);
        trigger.setAttribute('aria-controls', list.id);

        if (select.id) {
            const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
            if (label) {
                trigger.setAttribute('aria-label', label.textContent.trim());
                label.htmlFor = trigger.id;
            }
        }
        if (select.getAttribute('aria-label')) {
            trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
        } else if (select.labels?.length) {
            const labelText = Array.from(select.labels)
                .map((label) => {
                    const span = label.querySelector('span');
                    if (span) return span.textContent.trim();
                    const copy = label.cloneNode(true);
                    copy.querySelectorAll('select, .select-control').forEach((element) => element.remove());
                    return copy.textContent.trim();
                })
                .find(Boolean);
            if (labelText) trigger.setAttribute('aria-label', labelText);
        }

        let optionButtons = [];

        function closeOthers() {
            document.querySelectorAll('.select-control.is-open').forEach((other) => {
                if (other === wrapper) return;
                other.classList.remove('is-open', 'is-above');
                document.getElementById(other.dataset.selectPanelId).hidden = true;
                other.querySelector('.select-control__trigger').setAttribute('aria-expanded', 'false');
            });
        }

        function renderOptions() {
            list.replaceChildren();
            optionButtons = [];

            Array.from(select.children).forEach((child) => {
                if (child.tagName === 'OPTGROUP') {
                    const groupLabel = document.createElement('div');
                    groupLabel.className = 'select-control__group';
                    groupLabel.textContent = child.label;
                    list.appendChild(groupLabel);
                    Array.from(child.children).forEach(addOption);
                } else if (child.tagName === 'OPTION') {
                    addOption(child);
                }
            });

            const selected = select.selectedOptions[0];
            value.textContent = selected?.textContent.trim() || 'Choose an option';
            trigger.classList.toggle('is-placeholder', !selected || selected.value === '');
            trigger.disabled = select.disabled;
            trigger.setAttribute('aria-disabled', String(select.disabled));
        }

        function addOption(option) {
            const button = document.createElement('button');
            button.type = 'button';
            button.tabIndex = -1;
            button.className = 'select-control__option';
            button.textContent = option.textContent.trim();
            button.disabled = option.disabled
                || (option.parentElement.tagName === 'OPTGROUP' && option.parentElement.disabled)
                || select.disabled;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', String(option.value === select.value));
            if (option.value === select.value) button.classList.add('is-selected');
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                wrapper.classList.remove('is-invalid');
                trigger.removeAttribute('aria-invalid');
                close();
                trigger.focus();
            });
            list.appendChild(button);
            if (!button.disabled) optionButtons.push(button);
        }

        function open(focusSelected) {
            if (trigger.disabled) return;
            renderOptions();
            closeOthers();
            wrapper.classList.add('is-open');
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            wrapper.classList.toggle('is-above', spaceBelow < 280 && rect.top > spaceBelow);
            const panelWidth = Math.max(rect.width, select.closest('.status-filter') ? 180 : 0);
            const left = Math.max(8, Math.min(rect.left, window.innerWidth - panelWidth - 8));
            panel.style.left = `${left}px`;
            panel.style.width = `${panelWidth}px`;
            if (spaceBelow < 280 && rect.top > spaceBelow) {
                panel.style.top = 'auto';
                panel.style.bottom = `${window.innerHeight - rect.top + 7}px`;
            } else {
                panel.style.top = `${rect.bottom + 7}px`;
                panel.style.bottom = 'auto';
            }
            if (focusSelected) {
                (optionButtons.find((button) => button.getAttribute('aria-selected') === 'true')
                    || optionButtons[0]
                    || trigger).focus();
            }
        }

        function close() {
            wrapper.classList.remove('is-open', 'is-above');
            panel.hidden = true;
            panel.style.left = '';
            panel.style.top = '';
            panel.style.bottom = '';
            panel.style.width = '';
            trigger.setAttribute('aria-expanded', 'false');
        }

        function moveFocus(direction) {
            const current = optionButtons.indexOf(document.activeElement);
            const next = Math.max(0, Math.min(optionButtons.length - 1, current + direction));
            optionButtons[next]?.focus();
        }

        function moveTabFocus(backwards) {
            const focusable = Array.from(document.querySelectorAll(
                'a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'
            )).filter((element) => element !== select && element.getClientRects().length);
            const triggerIndex = focusable.indexOf(trigger);
            close();
            focusable[triggerIndex + (backwards ? -1 : 1)]?.focus();
        }

        trigger.addEventListener('click', () => {
            if (panel.hidden) open(false);
            else close();
        });
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                open(true);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        list.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveFocus(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                close();
                trigger.focus();
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                document.activeElement?.click();
            } else if (event.key === 'Tab') {
                event.preventDefault();
                moveTabFocus(event.shiftKey);
            }
        });
        select.addEventListener('change', () => {
            renderOptions();
            wrapper.classList.remove('is-invalid');
            trigger.removeAttribute('aria-invalid');
        });
        select.form?.addEventListener('reset', () => window.setTimeout(renderOptions, 0));
        new MutationObserver(renderOptions).observe(select, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['disabled', 'label', 'value']
        });

        if (wasRequired && select.form) {
            select.form.addEventListener('submit', (event) => {
                if (event.defaultPrevented || select.form.noValidate || select.value) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                wrapper.classList.add('is-invalid');
                trigger.setAttribute('aria-invalid', 'true');
                trigger.focus();
                open(false);
            }, true);
        }

        document.addEventListener('pointerdown', (event) => {
            if (!wrapper.contains(event.target) && !panel.contains(event.target)) close();
        });
        document.addEventListener('scroll', (event) => {
            if (!panel.contains(event.target)) close();
        }, true);
        window.addEventListener('resize', close);
        renderOptions();
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
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) initialize(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
