(() => {
    const pending = new WeakSet();

    const dialog = document.createElement('dialog');
    dialog.className = 'action-confirmation';
    dialog.setAttribute('aria-labelledby', 'actionConfirmationTitle');
    dialog.innerHTML = `
        <div class="action-confirmation-card">
            <span class="action-confirmation-kicker">PLEASE CONFIRM</span>
            <h2 id="actionConfirmationTitle">Are you sure?</h2>
            <p class="action-confirmation-message"></p>
            <div class="action-confirmation-actions">
                <button type="button" class="action-confirmation-cancel">Cancel</button>
                <button type="button" class="action-confirmation-accept">Continue</button>
            </div>
        </div>
    `;
    document.body.appendChild(dialog);

    const message = dialog.querySelector('.action-confirmation-message');
    const cancelButton = dialog.querySelector('.action-confirmation-cancel');
    const acceptButton = dialog.querySelector('.action-confirmation-accept');
    let resolveConfirmation = null;

    const confirmAction = (text, actionLabel = 'Continue') => new Promise((resolve) => {
        resolveConfirmation = resolve;
        message.textContent = text;
        acceptButton.textContent = actionLabel;
        dialog.showModal();
        cancelButton.focus();
    });

    const finishConfirmation = (accepted) => {
        if (!resolveConfirmation) return;
        const resolve = resolveConfirmation;
        resolveConfirmation = null;
        dialog.close();
        resolve(accepted);
    };

    cancelButton.addEventListener('click', () => finishConfirmation(false));
    acceptButton.addEventListener('click', () => finishConfirmation(true));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) finishConfirmation(false);
    });
    dialog.addEventListener('close', () => {
        if (resolveConfirmation) finishConfirmation(false);
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (pending.has(form)) {
            pending.delete(form);
            return;
        }

        const submitter = event.submitter;
        const submitterMessage = submitter?.dataset.confirm;
        const confirmationMessage = submitterMessage || form.dataset.confirm;
        if (!confirmationMessage) return;

        event.preventDefault();
        const submitLabel = submitter instanceof HTMLInputElement
            ? submitter.value
            : submitter?.textContent?.trim() || '';
        const actionLabel = submitter?.dataset.confirmAction
            || (/delete|remove|cancel/i.test(submitLabel) ? submitLabel : 'Continue');
        if (!await confirmAction(confirmationMessage, actionLabel)) return;

        pending.add(form);
        if (submitter instanceof HTMLElement && submitter.form === form) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    }, true);

    window.showActionConfirmation = confirmAction;
})();
