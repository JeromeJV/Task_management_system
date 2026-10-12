(function () {
    const selector = '.assignment-select, .production-assignment-select';

    function enhanceSelect(select) {
        if (select.dataset.dropdownEnhanced === 'true') {
            return;
        }

        select.dataset.dropdownEnhanced = 'true';
        const wasRequired = select.required;
        select.required = false;
        select.classList.add('assignment-dropdown__native');

        const wrapper = document.createElement('div');
        wrapper.className = 'assignment-dropdown';
        wrapper.classList.add(
            select.classList.contains('production-assignment-select')
                ? 'assignment-dropdown--production'
                : 'assignment-dropdown--logistics'
        );
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'assignment-dropdown__trigger';
        trigger.id = `${select.id || 'assignment'}-dropdown-trigger`;
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-required', String(wasRequired));

        const valueText = document.createElement('span');
        valueText.className = 'assignment-dropdown__value';
        const triggerIcon = document.createElement('span');
        triggerIcon.className = 'assignment-dropdown__chevron';
        triggerIcon.setAttribute('aria-hidden', 'true');
        trigger.append(valueText, triggerIcon);

        const panel = document.createElement('div');
        panel.className = 'assignment-dropdown__panel';
        panel.hidden = true;
        const panelHeader = document.createElement('div');
        panelHeader.className = 'assignment-dropdown__panel-header';
        const heading = document.createElement('strong');
        heading.className = 'assignment-dropdown__heading';
        const resultCount = document.createElement('span');
        resultCount.className = 'assignment-dropdown__count';
        panelHeader.append(heading, resultCount);

        const list = document.createElement('div');
        list.className = 'assignment-dropdown__options';
        list.id = `${select.id || 'assignment'}-dropdown-options`;
        list.setAttribute('role', 'listbox');
        const empty = document.createElement('p');
        empty.className = 'assignment-dropdown__empty';
        empty.hidden = true;

        panel.append(panelHeader, list, empty);
        wrapper.append(trigger, panel);
        trigger.setAttribute('aria-controls', list.id);

        const label = select.id
            ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`)
            : null;
        if (label) {
            label.htmlFor = trigger.id;
        }

        let optionButtons = [];

        function availableOptions() {
            return Array.from(select.options).filter((option) =>
                option.value && !option.disabled && !option.hidden
            );
        }

        function optionPresentation(option) {
            const text = option.textContent.trim();
            const details = [];
            let title = text;
            const parenthetical = text.match(/\(([^()]*)\)\s*$/);
            if (parenthetical) {
                const detailText = parenthetical[1].trim();
                if (/^(Stock|Qty|Status):/i.test(detailText)) {
                    title = text.slice(0, parenthetical.index).trim();
                    detailText.split('|').forEach((part) => {
                        const separator = part.indexOf(':');
                        details.push(separator < 0
                            ? { label: '', value: part.trim() }
                            : {
                                label: part.slice(0, separator).trim(),
                                value: part.slice(separator + 1).trim()
                            });
                    });
                } else if (/active shift/i.test(detailText)) {
                    title = text.slice(0, parenthetical.index).trim();
                    details.push({ label: '', value: detailText });
                }
            }
            return { title, details };
        }

        function render() {
            const options = availableOptions();
            const selected = select.selectedOptions[0];
            const selectedOption = selected && selected.value ? selected : null;

            valueText.textContent = selectedOption
                ? selectedOption.textContent.trim()
                : (selected?.textContent.trim() || 'Choose an option');
            trigger.classList.toggle('is-placeholder', !selectedOption);
            trigger.disabled = select.disabled;

            heading.textContent = select.closest('.form-group')?.querySelector('label')?.textContent.trim()
                || 'Choose an option';
            resultCount.textContent = `${options.length} ${options.length === 1 ? 'option' : 'options'}`;
            list.replaceChildren();
            optionButtons = [];

            options.forEach((option) => {
                const { title, details } = optionPresentation(option);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'assignment-dropdown__option';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', String(option.value === select.value));

                const copy = document.createElement('span');
                copy.className = 'assignment-dropdown__option-copy';
                const titleElement = document.createElement('span');
                titleElement.className = 'assignment-dropdown__option-title';
                titleElement.textContent = title;
                copy.appendChild(titleElement);

                if (details.length) {
                    const meta = document.createElement('span');
                    meta.className = 'assignment-dropdown__meta';
                    details.forEach(({ label: detailLabel, value }) => {
                        const chip = document.createElement('span');
                        chip.className = 'assignment-dropdown__chip';
                        if (/status/i.test(detailLabel) || /active shift/i.test(value)) {
                            chip.classList.add('assignment-dropdown__chip--status');
                        }
                        chip.textContent = detailLabel ? `${detailLabel}: ${value}` : value;
                        meta.appendChild(chip);
                    });
                    copy.appendChild(meta);
                }

                const check = document.createElement('span');
                check.className = 'assignment-dropdown__check';
                check.setAttribute('aria-hidden', 'true');
                check.textContent = '✓';
                button.append(copy, check);
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
                optionButtons.push(button);
            });

            const noOptions = Array.from(select.options).find((option) =>
                option.disabled && !option.hidden && option.textContent.trim()
            );
            empty.textContent = options.length
                ? ''
                : (noOptions?.textContent.trim() || 'No options are currently available.');
            empty.hidden = options.length > 0;
        }

        function open(focusFirst = false) {
            if (trigger.disabled) return;
            document.querySelectorAll('.assignment-dropdown.is-open').forEach((openWrapper) => {
                if (openWrapper !== wrapper) {
                    openWrapper.classList.remove('is-open');
                    openWrapper.querySelector('.assignment-dropdown__panel').hidden = true;
                    openWrapper.querySelector('.assignment-dropdown__trigger').setAttribute('aria-expanded', 'false');
                }
            });
            wrapper.classList.add('is-open');
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const panelRect = panel.getBoundingClientRect();
            const triggerRect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - triggerRect.bottom;
            const spaceAbove = triggerRect.top;
            wrapper.classList.toggle(
                'is-above',
                panelRect.height > spaceBelow && spaceAbove > spaceBelow
            );
            if (focusFirst) {
                (optionButtons[0] || trigger).focus();
            }
        }

        function close() {
            wrapper.classList.remove('is-open');
            wrapper.classList.remove('is-above');
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', () => {
            if (panel.hidden) open();
            else close();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(true);
            } else if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                close();
            }
        });
        list.addEventListener('keydown', (event) => {
            const currentIndex = optionButtons.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                let index = currentIndex + direction;
                if (optionButtons[index]) optionButtons[index].focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                close();
                trigger.focus();
            } else if (event.key === 'Tab') {
                close();
            }
        });
        select.addEventListener('change', () => {
            render();
            wrapper.classList.remove('is-invalid');
            trigger.removeAttribute('aria-invalid');
        });
        new MutationObserver(render).observe(select, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['disabled', 'hidden']
        });

        const form = select.form;
        if (wasRequired && form) {
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                if (!select.value) {
                    event.preventDefault();
                    wrapper.classList.add('is-invalid');
                    trigger.setAttribute('aria-invalid', 'true');
                    trigger.focus();
                    open();
                }
            });
        }

        document.addEventListener('pointerdown', (event) => {
            if (!wrapper.contains(event.target)) close();
        });
        render();
    }

    function initialize() {
        document.querySelectorAll(selector).forEach(enhanceSelect);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
