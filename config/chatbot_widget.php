<?php
if (empty($_SESSION['chatbot_csrf'])) {
    $_SESSION['chatbot_csrf'] = bin2hex(random_bytes(32));
}
$chatbotRole = (string) ($_SESSION['role'] ?? '');
$chatbotPlaceholder = match ($chatbotRole) {
    'super' => 'Hal. alerts / assign production 123 Cooking to employee 45',
    'admin' => 'Hal. alerts / attendance report this week',
    'log' => 'Hal. my deliveries / delivery report this week',
    'pro' => 'Hal. my production tasks / production report this week',
    'cooking' => 'Hal. my Cooking tasks / alerts',
    'packaging' => 'Hal. my Packaging tasks / alerts',
    'HR' => 'Hal. applicants / attendance report this week',
    'payroll' => 'Hal. pending payroll / payroll report this month',
    'employee' => 'Hal. what should I handle / my tasks',
    default => 'Magtanong tungkol sa features na available sa role mo',
};
?>
<style>
    .tt-chatbot {
        position: fixed;
        right: 88px;
        bottom: 24px;
        z-index: 3000;
        font-family: Inter, "Segoe UI", Arial, sans-serif;
    }
    .tt-chatbot-toggle {
        width: 58px;
        height: 58px;
        border: 0;
        border-radius: 50%;
        background: linear-gradient(145deg, #168447, #116334);
        color: #fff;
        box-shadow: 0 8px 22px rgba(20, 83, 45, 0.3);
        cursor: pointer;
        font-size: 24px;
        transition: transform 160ms ease, box-shadow 160ms ease;
    }
    .tt-chatbot-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 11px 26px rgba(20, 83, 45, 0.36);
    }
    .tt-chatbot-toggle:focus-visible,
    .tt-chatbot-close:focus-visible,
    .tt-chatbot-input:focus-visible,
    .tt-chatbot-send:focus-visible {
        outline: 3px solid #86efac;
        outline-offset: 2px;
    }
    .tt-chatbot-panel {
        position: absolute;
        right: 0;
        bottom: 72px;
        display: flex;
        width: min(390px, calc(100vw - 32px));
        height: min(540px, calc(100vh - 110px));
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #dbe4df;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.2);
        animation: tt-chatbot-open 180ms ease-out;
    }
    .tt-chatbot-panel[hidden] {
        display: none;
    }
    @keyframes tt-chatbot-open {
        from { opacity: 0; transform: translateY(8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .tt-chatbot-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: linear-gradient(120deg, #14532d, #17683b);
        color: #fff;
    }
    .tt-chatbot-brand {
        min-width: 0;
    }
    .tt-chatbot-title {
        display: block;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.1px;
    }
    .tt-chatbot-subtitle {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        color: #d1fae5;
        font-size: 11px;
        font-weight: 400;
    }
    .tt-chatbot-status {
        width: 7px;
        height: 7px;
        flex: 0 0 7px;
        border-radius: 50%;
        background: #86efac;
        box-shadow: 0 0 0 3px rgba(134, 239, 172, 0.15);
    }
    .tt-chatbot-close {
        display: grid;
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        place-items: center;
        border: 0;
        background: transparent;
        color: inherit;
        cursor: pointer;
        font-size: 24px;
        line-height: 1;
        border-radius: 8px;
    }
    .tt-chatbot-close:hover {
        background: rgba(255, 255, 255, 0.14);
    }
    .tt-chatbot-messages {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 12px;
        overflow-y: auto;
        padding: 16px 14px;
        background: #f4f7f6;
        scroll-behavior: smooth;
        scrollbar-color: #b8c8be transparent;
        scrollbar-width: thin;
    }
    .tt-chatbot-message {
        max-width: 94%;
        padding: 12px 14px;
        border-radius: 15px;
        color: #1e293b;
        font-size: 14px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }
    .tt-chatbot-message.bot {
        align-self: flex-start;
        border: 1px solid #e1e9e4;
        border-bottom-left-radius: 5px;
        background: #fff;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.035);
    }
    .tt-chatbot-message.user {
        align-self: flex-end;
        border: 1px solid #b7e4c7;
        border-bottom-right-radius: 5px;
        background: #dcfce7;
        color: #14532d;
        white-space: pre-wrap;
    }
    .tt-chatbot-heading {
        display: block;
        margin-bottom: 9px;
        color: #14532d;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.4;
    }
    .tt-chatbot-record {
        margin-top: 8px;
        padding: 10px;
        border: 1px solid #e3eae6;
        border-radius: 11px;
        background: #f8faf9;
    }
    .tt-chatbot-record:first-of-type {
        margin-top: 0;
    }
    .tt-chatbot-record-title {
        display: block;
        margin-bottom: 7px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 700;
    }
    .tt-chatbot-record-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .tt-chatbot-tag {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 999px;
        background: #e7efea;
        color: #334b3d;
        font-size: 11px;
        line-height: 1.35;
    }
    .tt-chatbot-tag.is-pending,
    .tt-chatbot-tag.is-in-production {
        border: 1px solid #fecaca;
        background: #fee2e2;
        color: #b91c1c;
        font-weight: 700;
    }
    .tt-chatbot-details dd.is-pending,
    .tt-chatbot-details dd.is-in-production {
        color: #b91c1c;
        font-weight: 700;
    }
    .tt-chatbot-details {
        display: grid;
        grid-template-columns: minmax(82px, 0.75fr) minmax(0, 1.25fr);
        gap: 8px 12px;
        margin: 0;
    }
    .tt-chatbot-details dt {
        color: #64756a;
        font-size: 12px;
        line-height: 1.45;
    }
    .tt-chatbot-details dd {
        min-width: 0;
        margin: 0;
        color: #1e293b;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .tt-chatbot-note {
        display: block;
        margin-top: 10px;
        color: #64756a;
        font-size: 11px;
        font-style: italic;
        line-height: 1.45;
    }
    .tt-chatbot-paragraph {
        margin: 0;
    }
    .tt-chatbot-paragraph + .tt-chatbot-paragraph {
        margin-top: 8px;
    }
    .tt-chatbot-help-group {
        margin-top: 11px;
        padding: 10px;
        border: 1px solid #e4ece6;
        border-radius: 11px;
        background: #f8faf9;
    }
    .tt-chatbot-help-title {
        display: block;
        margin-bottom: 7px;
        color: #52665a;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .tt-chatbot-command-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .tt-chatbot-command {
        display: inline-block;
        max-width: 100%;
        padding: 5px 8px;
        border: 1px solid #dce8df;
        border-radius: 7px;
        background: #fff;
        color: #14532d;
        font-family: Consolas, "Courier New", monospace;
        font-size: 11px;
        line-height: 1.45;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .tt-chatbot-form {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 12px;
        border-top: 1px solid #e5ebe7;
        background: #fff;
    }
    .tt-chatbot-input {
        min-width: 0;
        flex: 1;
        padding: 11px 13px;
        border: 1px solid #cbd8cf;
        border-radius: 11px;
        color: #1e293b;
        font-size: 13px;
        transition: border-color 160ms ease, box-shadow 160ms ease;
    }
    .tt-chatbot-input::placeholder {
        color: #829087;
    }
    .tt-chatbot-input:focus {
        border-color: #15803d;
        box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
        outline: 0;
    }
    .tt-chatbot-send {
        min-height: 42px;
        padding: 0 16px;
        border: 0;
        border-radius: 10px;
        background: #15803d;
        color: #fff;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        transition: background 160ms ease, transform 160ms ease;
    }
    .tt-chatbot-send:hover:not(:disabled) {
        transform: translateY(-1px);
        background: #166534;
    }
    .tt-chatbot-send:disabled {
        cursor: wait;
        opacity: 0.65;
    }
    .tt-chatbot-confirm-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin-top: 12px;
    }
    .tt-chatbot-confirm-actions .tt-chatbot-cancel {
        min-height: 42px;
        padding: 0 14px;
        border: 1px solid #d6dfd9;
        border-radius: 10px;
        background: #fff;
        color: #526158;
        cursor: pointer;
        font-size: 13px;
        font-weight: 650;
    }
    .tt-chatbot-confirm-actions .tt-chatbot-cancel:hover:not(:disabled) {
        background: #f3f7f4;
    }
    .tt-chatbot-confirm-actions button:disabled {
        cursor: wait;
        opacity: 0.65;
    }
    html[data-theme="dark"] body .tt-chatbot-panel {
        border-color: #303b40 !important;
        background: #20282c !important;
        color: #e1e8e4 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-messages {
        background: #151b1e !important;
        scrollbar-color: #52615a transparent;
    }
    html[data-theme="dark"] body .tt-chatbot-message.bot {
        border-color: #364146 !important;
        background: #20282c !important;
        color: #e1e8e4 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-message.user {
        border-color: #315b42 !important;
        background: #203a2d !important;
        color: #bde8cd !important;
    }
    html[data-theme="dark"] body .tt-chatbot-message :is(p, strong, span, dt, dd) {
        color: inherit !important;
    }
    html[data-theme="dark"] body .tt-chatbot-message .tt-chatbot-heading {
        color: #8be0aa !important;
    }
    html[data-theme="dark"] body .tt-chatbot-record,
    html[data-theme="dark"] body .tt-chatbot-help-group {
        border-color: #364146 !important;
        background: #182126 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-record-title {
        color: #e6ece9 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-details dt,
    html[data-theme="dark"] body .tt-chatbot-note,
    html[data-theme="dark"] body .tt-chatbot-help-title {
        color: #aab7b1 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-details dd {
        color: #e1e8e4 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-tag {
        border-color: #465258 !important;
        background: #2a3439 !important;
        color: #d1dbd5 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-tag.is-pending,
    html[data-theme="dark"] body .tt-chatbot-tag.is-in-production {
        border-color: #654442 !important;
        background: #3a2928 !important;
        color: #ffc2bc !important;
    }
    html[data-theme="dark"] body .tt-chatbot-details dd.is-pending,
    html[data-theme="dark"] body .tt-chatbot-details dd.is-in-production {
        color: #ffc2bc !important;
    }
    html[data-theme="dark"] body .tt-chatbot-command {
        border-color: #465a4c !important;
        background: #20282c !important;
        color: #bde8cd !important;
    }
    html[data-theme="dark"] body .tt-chatbot-form {
        border-color: #303b40 !important;
        background: #20282c !important;
    }
    html[data-theme="dark"] body .tt-chatbot-input {
        border-color: #465258 !important;
        background: #182126 !important;
        color: #e1e8e4 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-input::placeholder {
        color: #aab7b1 !important;
    }
    html[data-theme="dark"] body .tt-chatbot-confirm-actions .tt-chatbot-cancel {
        border-color: #465258 !important;
        background: #20282c !important;
        color: #e1e8e4 !important;
    }
    @media (max-width: 480px) {
        .tt-chatbot {
            right: 15px;
            bottom: 76px;
        }
        .tt-chatbot-panel {
            right: 0;
            bottom: 68px;
            width: min(390px, calc(100vw - 30px));
            height: min(560px, calc(100dvh - 160px));
            border-radius: 16px;
        }
        .tt-chatbot-messages {
            padding: 14px 12px;
        }
        .tt-chatbot-form {
            gap: 7px;
            padding: 10px;
        }
        .tt-chatbot-input {
            padding: 10px;
            font-size: 12px;
        }
        .tt-chatbot-send {
            min-height: 40px;
            padding: 0 13px;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .tt-chatbot-panel,
        .tt-chatbot-toggle,
        .tt-chatbot-input,
        .tt-chatbot-send {
            animation: none;
            scroll-behavior: auto;
            transition: none;
        }
    }
</style>

<div class="tt-chatbot" id="ttChatbot" data-csrf="<?= htmlspecialchars($_SESSION['chatbot_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
    <section class="tt-chatbot-panel" id="ttChatbotPanel" aria-label="TaskTrack assistant" hidden>
        <header class="tt-chatbot-header">
            <div class="tt-chatbot-brand">
                <span class="tt-chatbot-title">TaskTrack Assistant</span>
                <span class="tt-chatbot-subtitle"><span class="tt-chatbot-status" aria-hidden="true"></span>Tasks, alerts and workflow help</span>
            </div>
            <button class="tt-chatbot-close" id="ttChatbotClose" type="button" aria-label="Close chat">&times;</button>
        </header>
        <div class="tt-chatbot-messages" id="ttChatbotMessages" aria-live="polite">
            <div class="tt-chatbot-message bot">
                <strong class="tt-chatbot-heading">Kumusta! 👋</strong>
                <p class="tt-chatbot-paragraph">Magtanong tungkol sa tasks, alerts, reports, o system process. Mag-type ng <strong>help</strong> para makita ang commands.</p>
                <p class="tt-chatbot-paragraph">Makikita mo lang ang impormasyon, alerts, at reports na pinapayagan ng role mo. <?= $chatbotRole === 'super' ? 'Supervisor lang ang makakapag-save ng task changes; may preview bago kumpirmahin.' : 'Hindi makakapag-save ng task changes ang chatbot sa role mo.'; ?></p>
            </div>
        </div>
        <form class="tt-chatbot-form" id="ttChatbotForm">
            <input class="tt-chatbot-input" id="ttChatbotInput" type="text" maxlength="300" placeholder="<?= htmlspecialchars($chatbotPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required>
            <button class="tt-chatbot-send" id="ttChatbotSend" type="submit">Send</button>
        </form>
    </section>
    <button class="tt-chatbot-toggle" id="ttChatbotToggle" type="button" aria-label="Open TaskTrack assistant" aria-expanded="false">💬</button>
</div>

<script>
    (function () {
        const panel = document.getElementById('ttChatbotPanel');
        const toggle = document.getElementById('ttChatbotToggle');
        const close = document.getElementById('ttChatbotClose');
        const messages = document.getElementById('ttChatbotMessages');
        const form = document.getElementById('ttChatbotForm');
        const input = document.getElementById('ttChatbotInput');
        const send = document.getElementById('ttChatbotSend');
        const csrfToken = document.getElementById('ttChatbot').dataset.csrf;

        function addMessage(text, sender, confirmation) {
            const message = document.createElement('div');
            message.className = 'tt-chatbot-message ' + sender;
            if (sender === 'bot') {
                renderBotReply(message, text);
                if (confirmation && typeof confirmation.token === 'string') {
                    const actions = document.createElement('div');
                    actions.className = 'tt-chatbot-confirm-actions';
                    const confirmButton = document.createElement('button');
                    confirmButton.type = 'button';
                    confirmButton.className = 'tt-chatbot-send';
                    confirmButton.textContent = 'Confirm and save';
                    confirmButton.addEventListener('click', function () {
                        submitAction({ confirm_action: confirmation.token }, actions, confirmButton);
                    });
                    const cancelButton = document.createElement('button');
                    cancelButton.type = 'button';
                    cancelButton.className = 'tt-chatbot-cancel';
                    cancelButton.textContent = 'Cancel';
                    cancelButton.addEventListener('click', function () {
                        submitAction({ cancel_action: confirmation.token }, actions, cancelButton);
                    });
                    actions.append(confirmButton, cancelButton);
                    message.appendChild(actions);
                }
            } else {
                message.textContent = text;
            }
            messages.appendChild(message);
            messages.scrollTop = messages.scrollHeight;
        }

        async function submitAction(payload, actions, trigger) {
            if (trigger.disabled) {
                return;
            }
            actions.querySelectorAll('button').forEach(function (button) {
                button.disabled = true;
            });
            try {
                const response = await fetch('config/chatbot_API.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({ csrf: csrfToken }, payload))
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.reply || 'Hindi makontak ang assistant. Pakisubukan ulit.');
                }
                actions.remove();
                addMessage(typeof result.reply === 'string' ? result.reply : 'Walang sagot mula sa assistant.', 'bot', result.confirmation);
            } catch (error) {
                actions.querySelectorAll('button').forEach(function (button) {
                    button.disabled = false;
                });
                addMessage(error instanceof Error ? error.message : 'Nagkaroon ng error sa pagkuha ng sagot.', 'bot');
            }
        }

        function renderBotReply(container, text) {
            const lines = text.split(/\r?\n/).map(function (line) {
                return line.trim();
            }).filter(Boolean);

            if (lines.length === 0) {
                container.textContent = text;
                return;
            }

            const heading = document.createElement('strong');
            heading.className = 'tt-chatbot-heading';

            const helpSectionPattern = /^(List records|Search|Record details|Next page|Action items|Reports|Process help|Safe changes):$/i;
            const firstHelpSection = lines.findIndex(function (line) {
                return helpSectionPattern.test(line);
            });
            if (firstHelpSection !== -1) {
                lines.slice(0, firstHelpSection).forEach(function (line) {
                    const paragraph = document.createElement('p');
                    paragraph.className = 'tt-chatbot-paragraph';
                    paragraph.textContent = line;
                    container.appendChild(paragraph);
                });

                let index = firstHelpSection;
                while (index < lines.length) {
                    const sectionMatch = lines[index].match(helpSectionPattern);
                    if (!sectionMatch) {
                        const note = document.createElement('span');
                        note.className = 'tt-chatbot-note';
                        note.textContent = lines[index];
                        container.appendChild(note);
                        index += 1;
                        continue;
                    }

                    const group = document.createElement('section');
                    group.className = 'tt-chatbot-help-group';
                    const label = document.createElement('strong');
                    label.className = 'tt-chatbot-help-title';
                    label.textContent = sectionMatch[1];
                    group.appendChild(label);

                    const commandList = document.createElement('div');
                    commandList.className = 'tt-chatbot-command-list';
                    index += 1;
                    while (index < lines.length && !helpSectionPattern.test(lines[index])) {
                        const command = document.createElement('code');
                        command.className = 'tt-chatbot-command';
                        command.textContent = lines[index];
                        commandList.appendChild(command);
                        index += 1;
                    }
                    group.appendChild(commandList);
                    container.appendChild(group);
                }
                return;
            }

            const detailRows = lines.slice(1).map(function (line) {
                return line.match(/^([^:]{1,50}):\s*(.*)$/);
            });
            const isDetailReply = lines.length > 1
                && /:\s*$/.test(lines[0])
                && detailRows.every(Boolean);

            if (isDetailReply) {
                heading.textContent = lines[0].replace(/:\s*$/, '');
                container.appendChild(heading);

                const details = document.createElement('dl');
                details.className = 'tt-chatbot-details';
                lines.slice(1).forEach(function (line) {
                    const match = line.match(/^([^:]{1,50}):\s*(.*)$/);
                    const label = document.createElement('dt');
                    label.textContent = match[1];
                    const value = document.createElement('dd');
                    value.textContent = match[2] || 'N/A';
                    if (/^pending$/i.test(match[2].trim())) {
                        value.classList.add('is-pending');
                    } else if (/^in production$/i.test(match[2].trim())) {
                        value.classList.add('is-in-production');
                    }
                    details.append(label, value);
                });
                container.appendChild(details);
                return;
            }

            const firstLine = lines[0];
            const hasRecordLines = lines.some(function (line) {
                return /^#\d+\s/.test(line);
            });
            if (hasRecordLines) {
                heading.textContent = firstLine;
                container.appendChild(heading);

                lines.slice(1).forEach(function (line) {
                    const recordMatch = line.match(/^#(\d+)\s+(.+)$/);
                    if (!recordMatch) {
                        const note = document.createElement('span');
                        note.className = 'tt-chatbot-note';
                        note.textContent = line;
                        container.appendChild(note);
                        return;
                    }

                    const parts = recordMatch[2].split(/\s+\|\s+/);
                    const card = document.createElement('div');
                    card.className = 'tt-chatbot-record';

                    const title = document.createElement('strong');
                    title.className = 'tt-chatbot-record-title';
                    title.textContent = '#' + recordMatch[1] + ' ' + parts.shift();
                    card.appendChild(title);

                    if (parts.length > 0) {
                        const metadata = document.createElement('div');
                        metadata.className = 'tt-chatbot-record-meta';
                        parts.forEach(function (part) {
                            const tag = document.createElement('span');
                            tag.className = 'tt-chatbot-tag';
                            if (/^pending$/i.test(part.trim())) {
                                tag.classList.add('is-pending');
                            } else if (/^in production$/i.test(part.trim())) {
                                tag.classList.add('is-in-production');
                            }
                            tag.textContent = part;
                            metadata.appendChild(tag);
                        });
                        card.appendChild(metadata);
                    }
                    container.appendChild(card);
                });
                return;
            }

            lines.forEach(function (line, index) {
                if (index === 0 && /:\s*$/.test(line)) {
                    heading.textContent = line.replace(/:\s*$/, '');
                    container.appendChild(heading);
                    return;
                }

                const keyValue = line.match(/^([^:]{1,50}):\s*(.*)$/);
                if (keyValue) {
                    const paragraph = document.createElement('p');
                    paragraph.className = 'tt-chatbot-paragraph';
                    const label = document.createElement('strong');
                    label.textContent = keyValue[1] + ': ';
                    paragraph.append(label, document.createTextNode(keyValue[2]));
                    container.appendChild(paragraph);
                    return;
                }

                const paragraph = document.createElement('p');
                paragraph.className = 'tt-chatbot-paragraph';
                paragraph.textContent = line;
                container.appendChild(paragraph);
            });
        }

        function setOpen(isOpen) {
            panel.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                input.focus();
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(panel.hidden);
        });
        close.addEventListener('click', function () {
            setOpen(false);
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const message = input.value.trim();
            if (!message || send.disabled) {
                return;
            }

            addMessage(message, 'user');
            input.value = '';
            send.disabled = true;

            try {
                const response = await fetch('config/chatbot_API.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message, csrf: csrfToken })
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.reply || 'Hindi makontak ang assistant. Pakisubukan ulit.');
                }
                addMessage(typeof result.reply === 'string' ? result.reply : 'Walang sagot mula sa assistant.', 'bot', result.confirmation);
            } catch (error) {
                addMessage(error instanceof Error ? error.message : 'Nagkaroon ng error sa pagkuha ng sagot.', 'bot');
            } finally {
                send.disabled = false;
                input.focus();
            }
        });
    })();
</script>
