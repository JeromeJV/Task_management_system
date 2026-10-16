<?php
session_start();
include('config/autoLog.php');
$interview_schedule_mode = true;
include('config/application_API.php');

$scheduledApplicants = isset($records) && is_array($records) ? $records : [];
$directoryCount = count($scheduledApplicants);
$scheduledCount = 0;
$pendingCount = 0;
$passedCount = 0;
$failedCount = 0;
foreach ($scheduledApplicants as $applicant) {
    $interviewDate = trim((string)($applicant['interview_date'] ?? ''));
    $type = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)($applicant['interview_type'] ?? '')))));
    if ($type === 'technical interview') {
        $type = 'training';
    }
    $status = strtolower(trim((string)($applicant['status'] ?? '')));
    if ($status === 'passed' && in_array($type, ['initial interview', 'training'], true)) {
        continue;
    }
    if ($interviewDate === '' || $interviewDate === '0000-00-00 00:00:00') {
        continue;
    }
    $scheduledCount++;
    if ($status === 'pending') {
        $pendingCount++;
    } elseif ($status === 'passed' || $status === 'hired') {
        $passedCount++;
    } elseif ($status === 'failed') {
        $failedCount++;
    }
}

$pageMessage = $_SESSION['interview_schedule_message'] ?? '';
$pageError = $_SESSION['interview_schedule_error'] ?? '';
$outcome = $_SESSION['interview_schedule_outcome'] ?? '';
$outcomeName = $_SESSION['interview_schedule_outcome_name'] ?? '';
unset(
    $_SESSION['interview_schedule_message'],
    $_SESSION['interview_schedule_error'],
    $_SESSION['interview_schedule_outcome'],
    $_SESSION['interview_schedule_outcome_name']
);
$outcome = in_array($outcome, ['passed', 'rejected'], true) ? $outcome : '';
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$styleVersion = file_exists('css/applicant_admin.css') ? filemtime('css/applicant_admin.css') : time();
$selectedStage = $selected_status ?? 'all';
if (in_array($selectedStage, ['Technical_Interview', 'Technical Interview'], true)) {
    $selectedStage = 'Training';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interview Schedule | TaskTrack</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <link rel="stylesheet" href="css/applicant_admin.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
</head>
<body>
<div class="hr-shell">
    <aside class="hr-sidebar" aria-label="HR navigation">
        <a class="hr-brand" href="HR.php" aria-label="TaskTrack HR home">
            <span class="hr-brand-mark" aria-hidden="true">T</span>
            <span class="hr-brand-name">TASKTRACK</span>
        </a>
        <nav class="hr-nav">
            <a class="hr-nav-link" href="HR.php">
                <span class="hr-nav-icon" aria-hidden="true">▦</span>
                <span class="hr-nav-label">Employees</span>
            </a>
            <a class="hr-nav-link" href="atten.php">
                <span class="hr-nav-icon" aria-hidden="true">◷</span>
                <span class="hr-nav-label">Attendance</span>
            </a>
            <a class="hr-nav-link" href="leave_management.php">
                <span class="hr-nav-icon" aria-hidden="true">✚</span>
                <span class="hr-nav-label">Leave Management</span>
            </a>
            <a class="hr-nav-link" href="application_form.php">
                <span class="hr-nav-icon" aria-hidden="true">▤</span>
                <span class="hr-nav-label">Applicants</span>
            </a>
            <a class="hr-nav-link is-active" href="interview_sched.php" aria-current="page">
                <span class="hr-nav-icon" aria-hidden="true">▣</span>
                <span class="hr-nav-label">Interview Schedule</span>
            </a>
            <a class="hr-nav-link" href="office_employee.php">
                <span class="hr-nav-icon" aria-hidden="true">☑</span>
                <span class="hr-nav-label">My Tasks</span>
            </a>
        </nav>
        <a class="hr-nav-link hr-logout" href="logout.php">
            <span class="hr-nav-icon" aria-hidden="true">↪</span>
            <span class="hr-nav-label">Log out</span>
        </a>
    </aside>
    <?php include __DIR__ . '/config/notification_bell.php'; ?>

    <main class="hr-main">
        <header class="page-header">
            <div>
                <p class="eyebrow">TALENT MANAGEMENT</p>
                <h1>Interview Schedule</h1>
                <p class="page-subtitle">Review upcoming interviews and manage candidate schedules.</p>
            </div>
            <a class="primary-button" href="application_form.php">▤ <span>View applicants</span></a>
        </header>

        <?php if ($pageMessage !== '' && $outcome === ''): ?>
            <div class="notice success-notice" role="status"><?= $escape($pageMessage) ?></div>
        <?php endif; ?>
        <?php if ($pageError !== ''): ?>
            <div class="notice error-notice" role="alert"><?= $escape($pageError) ?></div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Interview schedule summary">
            <article class="stat-card">
                <span class="stat-icon icon-blue">▣</span>
                <div><span class="stat-label">Scheduled interviews</span><strong><?= $scheduledCount ?></strong></div>
                <span class="stat-foot">Candidates with interview dates</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-amber">◷</span>
                <div><span class="stat-label">Pending</span><strong><?= $pendingCount ?></strong></div>
                <span class="stat-foot">Awaiting interview outcome</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-green">✓</span>
                <div><span class="stat-label">Passed</span><strong><?= $passedCount ?></strong></div>
                <span class="stat-foot">Successful interview outcomes</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-red">×</span>
                <div><span class="stat-label">Failed</span><strong><?= $failedCount ?></strong></div>
                <span class="stat-foot">Unsuccessful interview outcomes</span>
            </article>
        </section>

        <section class="applicant-panel">
            <div class="panel-heading">
                <div>
                    <h2>Interview directory</h2>
                    <p>View scheduled interviews and candidates ready to book their next stage.</p>
                </div>
                <div class="view-tabs" role="tablist" aria-label="Applicant views">
                    <a class="view-tab" href="application_form.php" role="tab">All applicants</a>
                    <a class="view-tab selected" href="interview_sched.php" role="tab" aria-selected="true">Interview queue</a>
                </div>
            </div>

            <div class="filter-bar">
                <label class="search-box">
                    <span aria-hidden="true">⌕</span>
                    <input id="scheduleSearch" type="search" placeholder="Search name, email, or position..." autocomplete="off">
                </label>
                <form class="stage-filter-form" method="get" action="interview_sched.php">
                    <label class="status-filter">
                        <span>Interview stage</span>
                        <select name="interview_status" id="stageFilter" onchange="this.form.submit()">
                            <option value="all" <?= $selectedStage === 'all' ? 'selected' : '' ?>>All stages</option>
                            <option value="Initial_Interview" <?= in_array($selectedStage, ['Initial_Interview', 'Initial Interview'], true) ? 'selected' : '' ?>>Initial Interview</option>
                            <option value="Training" <?= in_array($selectedStage, ['Training', 'Training_Interview', 'Technical_Interview', 'Technical Interview'], true) ? 'selected' : '' ?>>Training</option>
                            <option value="Final_Interview" <?= in_array($selectedStage, ['Final_Interview', 'Final Interview'], true) ? 'selected' : '' ?>>Final Interview</option>
                        </select>
                    </label>
                </form>
                <label class="status-filter">
                    <span>Status</span>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="pending">Pending</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="hired">Hired</option>
                    </select>
                </label>
                <a class="reset-button" href="interview_sched.php">Reset filters</a>
            </div>

            <div class="table-wrap table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>APPLICANT</th>
                        <th>CONTACT</th>
                        <th>POSITION APPLIED</th>
                        <th>RESUME</th>
                        <th>STATUS</th>
                        <th>INTERVIEW</th>
                        <th class="actions-heading">ACTIONS</th>
                    </tr>
                    </thead>
                    <tbody id="scheduleRows">
                    <?php foreach ($scheduledApplicants as $applicant):
                        $id = (int)($applicant['applicant_id'] ?? 0);
                        $firstName = trim((string)($applicant['firstname'] ?? ''));
                        $lastName = trim((string)($applicant['lastname'] ?? ''));
                        $middleName = trim((string)($applicant['middlename'] ?? ''));
                        $fullName = trim($firstName . ' ' . ($middleName !== '' ? $middleName . ' ' : '') . $lastName);
                        $status = trim((string)($applicant['status'] ?? 'Pending'));
                        $interviewDate = trim((string)($applicant['interview_date'] ?? ''));
                        $timestamp = strtotime($interviewDate);
                        $hasInterviewDate = $interviewDate !== '' && $interviewDate !== '0000-00-00 00:00:00' && $timestamp !== false;
                        $interviewType = trim((string)($applicant['interview_type'] ?? ''));
                        $normalizedType = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $interviewType))));
                        if ($normalizedType === 'technical interview') {
                            $interviewType = 'Training';
                            $normalizedType = 'training';
                        }
                        $readyForNextStage = false;
                        if (strtolower($status) === 'passed' && $normalizedType === 'initial interview') {
                            $interviewType = 'Training';
                            $status = 'Pending';
                            $interviewDate = '';
                            $normalizedType = 'training';
                            $readyForNextStage = true;
                        } elseif (strtolower($status) === 'passed' && $normalizedType === 'training') {
                            $interviewType = 'Final Interview';
                            $status = 'Pending';
                            $interviewDate = '';
                            $normalizedType = 'final interview';
                            $readyForNextStage = true;
                        }
                        $hasInterviewDate = $hasInterviewDate && !$readyForNextStage;
                        $statusKey = strtolower($status);
                        $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusKey);
                        $timestamp = $hasInterviewDate ? strtotime($interviewDate) : false;
                        $resumeName = basename((string)($applicant['resume_path'] ?? ''));
                        $resumeUrl = $resumeName !== '' ? 'uploads/' . rawurlencode($resumeName) : '';
                        $position = trim((string)($applicant['position_applied'] ?? ''));
                        $contact = trim((string)($applicant['contact_number'] ?? ''));
                        $email = trim((string)($applicant['email'] ?? ''));
                        $interviewMode = trim((string)($applicant['interview_mode'] ?? ''));
                        if ($readyForNextStage) {
                            $interviewMode = '';
                        }
                        $searchText = strtolower($fullName . ' ' . $email . ' ' . $position . ' ' . $contact);
                        $record = [
                            'id' => $id,
                            'name' => $fullName,
                            'email' => $email,
                            'status' => $status,
                            'interviewType' => $interviewType,
                            'interviewMode' => $interviewMode,
                            'interviewDate' => $interviewDate,
                            'dateInput' => $timestamp ? date('Y-m-d\TH:i', $timestamp) : '',
                        ];
                        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                    ?>
                        <tr data-search="<?= $escape($searchText) ?>" data-status="<?= $escape($statusKey) ?>">
                            <td>
                                <span class="applicant-name">
                                    <span class="avatar"><?= $escape($initials !== '' ? $initials : 'A') ?></span>
                                    <span><strong><?= $escape($fullName !== '' ? $fullName : 'Unnamed applicant') ?></strong><small>Applicant #<?= $id ?></small></span>
                                </span>
                            </td>
                            <td><span class="cell-primary"><?= $escape($contact !== '' ? $contact : '—') ?></span><small class="cell-secondary"><?= $escape($email !== '' ? $email : 'No email') ?></small></td>
                            <td><?= $escape($position !== '' ? $position : '—') ?></td>
                            <td>
                                <?php if ($resumeUrl !== ''): ?>
                                    <a class="resume-link" href="view_resume.php?file=<?= rawurlencode($resumeName) ?>" target="_blank" rel="noopener">View resume</a>
                                <?php else: ?><span class="muted">Not provided</span><?php endif; ?>
                            </td>
                            <td><span class="status-pill status-<?= $escape($statusClass !== '' ? $statusClass : 'pending') ?>"><?= $escape($status !== '' ? $status : 'Pending') ?></span></td>
                            <td>
                                <span class="cell-primary"><?= $timestamp ? $escape(date('M j, Y · g:i A', $timestamp)) : 'Ready to schedule' ?></span>
                                <small class="cell-secondary"><?= $escape($interviewType !== '' ? $interviewType : 'Interview') ?><?= $interviewMode !== '' ? ' · ' . $escape($interviewMode) : '' ?></small>
                            </td>
                            <td class="row-actions">
                                <?php if (in_array($statusKey, ['failed', 'hired'], true)): ?>
                                    <span class="muted">Closed</span>
                                <?php elseif ($timestamp): ?>
                                    <form method="post" class="outcome-form" data-outcome="Passed" data-applicant-name="<?= $escape($fullName) ?>">
                                        <input type="hidden" name="save_interview" value="1">
                                        <input type="hidden" name="applicant_id" value="<?= $id ?>">
                                        <input type="hidden" name="username" value="<?= $escape($fullName) ?>">
                                        <input type="hidden" name="email" value="<?= $escape($email) ?>">
                                        <input type="hidden" name="interview_type" value="<?= $escape($interviewType) ?>">
                                        <input type="hidden" name="interview_mode" value="<?= $escape($interviewMode) ?>">
                                        <input type="hidden" name="interview_date" value="<?= $escape($interviewDate) ?>">
                                        <input type="hidden" name="status" value="Passed">
                                        <button class="outcome-action passed-action" type="submit">
                                            Passed
                                        </button>
                                    </form>
                                    <form method="post" class="outcome-form" data-outcome="Failed" data-applicant-name="<?= $escape($fullName) ?>">
                                        <input type="hidden" name="save_interview" value="1">
                                        <input type="hidden" name="applicant_id" value="<?= $id ?>">
                                        <input type="hidden" name="username" value="<?= $escape($fullName) ?>">
                                        <input type="hidden" name="email" value="<?= $escape($email) ?>">
                                        <input type="hidden" name="interview_type" value="<?= $escape($interviewType) ?>">
                                        <input type="hidden" name="interview_mode" value="<?= $escape($interviewMode) ?>">
                                        <input type="hidden" name="interview_date" value="<?= $escape($interviewDate) ?>">
                                        <input type="hidden" name="status" value="Failed">
                                        <button class="outcome-action failed-action" type="submit">
                                            Failed
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="icon-button schedule-action" type="button" data-schedule="<?= $escape(json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">Ready to Schedule</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($directoryCount === 0): ?>
                        <tr><td colspan="7" class="empty-state">No interviews or candidates are waiting in this queue.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div id="noScheduleResults" class="empty-state hidden">No interviews match your search and filters.</div>
            </div>
            <footer class="table-footer"><span>Showing <strong id="visibleCount"><?= $directoryCount ?></strong> of <?= $directoryCount ?> candidates in the interview queue</span></footer>
        </section>
    </main>
</div>

<?php if ($outcome !== ''): ?>
<div class="modal-backdrop outcome-backdrop" id="outcomeModal" role="dialog" aria-modal="true" aria-labelledby="outcomeTitle">
    <section class="modal-card outcome-modal">
        <div class="outcome-stamp <?= $outcome === 'passed' ? 'stamp-passed' : 'stamp-rejected' ?>" aria-hidden="true">
            <?= $outcome === 'passed' ? 'PASSED' : 'REJECTED' ?>
        </div>
        <h2 id="outcomeTitle"><?= $outcome === 'passed' ? 'Interview Passed' : 'Applicant Rejected' ?></h2>
        <p class="outcome-applicant"><?= $escape($outcomeName !== '' ? $outcomeName : 'Applicant') ?></p>
        <p class="outcome-description"><?= $escape($pageMessage) ?></p>
        <button class="primary-button" type="button" data-outcome-close>Done</button>
    </section>
</div>
<?php endif; ?>

<div class="modal-backdrop hidden" id="outcomeConfirmModal" role="dialog" aria-modal="true" aria-labelledby="outcomeConfirmTitle">
    <section class="modal-card outcome-confirm-card">
        <div class="modal-kicker">CONFIRM INTERVIEW RESULT</div>
        <h2 id="outcomeConfirmTitle">Confirm decision</h2>
        <p class="modal-subtitle" id="outcomeConfirmMessage"></p>
        <div class="modal-actions">
            <button class="secondary-button" type="button" id="cancelOutcomeButton">Cancel</button>
            <button class="primary-button" type="button" id="confirmOutcomeButton">Confirm result</button>
        </div>
    </section>
</div>

<div class="submission-overlay hidden" id="submissionOverlay" role="status" aria-live="polite" aria-hidden="true">
    <div class="submission-card">
        <span class="submission-spinner" aria-hidden="true"></span>
        <strong id="submissionMessage">Saving interview result...</strong>
        <span>Please wait while we save your changes.</span>
    </div>
</div>

<div class="modal-backdrop hidden" id="scheduleModal" role="dialog" aria-modal="true" aria-labelledby="scheduleTitle">
    <section class="modal-card compact-modal schedule-card">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <div class="modal-kicker">INTERVIEW</div>
        <h2 id="scheduleTitle">Set interview schedule</h2>
        <p class="modal-subtitle">Update interview details for <strong id="scheduleApplicantName"></strong>.</p>
        <form method="post" class="schedule-form" id="interviewScheduleForm">
            <input type="hidden" name="applicant_id" id="scheduleApplicantId">
            <input type="hidden" name="username" id="scheduleApplicantHiddenName">
            <input type="hidden" name="email" id="scheduleApplicantEmail">
            <input type="hidden" name="interview_date" id="scheduleDateTime">
            <div class="schedule-datetime-control">
                <label class="schedule-date-field">Interview date
                    <input type="date" class="calendar-control" id="scheduleDate" required>
                </label>
                <div class="schedule-time-fields">
                    <label>Hour
                        <select id="scheduleHour" required>
                            <option value="">Hour</option>
                            <?php for ($hour = 1; $hour <= 12; $hour++): ?>
                                <option value="<?= sprintf('%02d', $hour) ?>"><?= $hour ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>Minute
                        <select id="scheduleMinute" required>
                            <option value="">Minute</option>
                            <?php for ($minute = 0; $minute < 60; $minute++): ?>
                                <option value="<?= sprintf('%02d', $minute) ?>"><?= sprintf('%02d', $minute) ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>Period
                        <select id="schedulePeriod" required>
                            <option value="">AM/PM</option>
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="form-row">
                <label>Interview type
                    <select name="interview_type" id="scheduleType" required>
                        <option value="">Choose type</option><option value="Initial Interview">Initial Interview</option><option value="Training">Training</option><option value="Final Interview">Final Interview</option><option value="Hired">Hired</option>
                    </select>
                </label>
                <label>Interview mode
                    <select name="interview_mode" id="scheduleMode" required><option value="">Choose mode</option><option value="On-site">On-site</option><option value="Online">Online</option></select>
                </label>
            </div>
            <label>Application status
                <select name="status" id="scheduleStatus" required><option value="Scheduled">Scheduled</option><option value="Pending">Pending</option><option value="Hired">Hired</option></select>
            </label>
            <p class="stage-guidance" id="stageGuidance" aria-live="polite"></p>
            <div class="modal-actions">
                <button class="secondary-button" type="button" data-close>Cancel</button>
                <button class="primary-button" type="submit" name="save_interview">Save schedule</button>
            </div>
        </form>
    </section>
</div>

<script>
(() => {
    const rows = Array.from(document.querySelectorAll('#scheduleRows tr[data-search]'));
    const search = document.getElementById('scheduleSearch');
    const statusFilter = document.getElementById('statusFilter');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noScheduleResults');
    const modal = document.getElementById('scheduleModal');
    const outcomeConfirmModal = document.getElementById('outcomeConfirmModal');
    const submissionOverlay = document.getElementById('submissionOverlay');
    let pendingOutcomeForm = null;
    const normalizeStage = (value) => {
        const stage = (value || '').trim().replace(/_/g, ' ').replace(/\s+/g, ' ').toLowerCase();
        return stage === 'technical interview' ? 'training' : stage;
    };

    const filterRows = () => {
        const term = search.value.trim().toLowerCase();
        const selectedStatus = statusFilter.value;
        let shown = 0;
        rows.forEach((row) => {
            const matches = row.dataset.search.includes(term) &&
                (!selectedStatus || row.dataset.status === selectedStatus);
            row.classList.toggle('filtered-out', !matches);
            if (matches) shown++;
        });
        visibleCount.textContent = String(shown);
        noResults.classList.toggle('hidden', shown !== 0 || rows.length === 0);
    };

    search.addEventListener('input', filterRows);
    statusFilter.addEventListener('change', filterRows);
    document.querySelectorAll('.outcome-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed !== 'true') {
                event.preventDefault();
                pendingOutcomeForm = form;
                const applicantName = form.dataset.applicantName || 'this applicant';
                const outcome = form.dataset.outcome || 'this outcome';
                document.getElementById('outcomeConfirmTitle').textContent =
                    outcome === 'Passed' ? 'Mark interview as passed?' : 'Reject this applicant?';
                document.getElementById('outcomeConfirmMessage').textContent =
                    outcome === 'Passed'
                        ? `${applicantName} will be marked as Passed for this interview.`
                        : `${applicantName} will be marked as Failed and removed from the active interview queue.`;
                outcomeConfirmModal.classList.remove('hidden');
                return;
            }
            form.dataset.confirmed = 'false';
            document.getElementById('submissionMessage').textContent =
                `Saving ${form.dataset.outcome === 'Passed' ? 'passed' : 'rejected'} result...`;
            submissionOverlay.classList.remove('hidden');
            submissionOverlay.setAttribute('aria-hidden', 'false');
            form.querySelector('button[type="submit"]').disabled = true;
        });
    });
    const scheduleForm = document.getElementById('interviewScheduleForm');
    scheduleForm.addEventListener('submit', (event) => {
        const date = document.getElementById('scheduleDate');
        const hour = document.getElementById('scheduleHour');
        const minute = document.getElementById('scheduleMinute');
        const period = document.getElementById('schedulePeriod');
        const requiredSelects = [
            document.getElementById('scheduleType'),
            document.getElementById('scheduleMode'),
            document.getElementById('scheduleStatus')
        ];
        const pickerTrigger = date.closest('.calendar-picker')?.querySelector('.calendar-picker__trigger');
        const missingSelect = requiredSelects.find((select) => !select.value);
        if (!date.value || !hour.value || !minute.value || !period.value || missingSelect) {
            event.preventDefault();
            if (!date.value) {
                pickerTrigger?.setAttribute('aria-invalid', 'true');
                pickerTrigger?.focus();
            } else if (!hour.value || !minute.value || !period.value) {
                (!hour.value ? hour : !minute.value ? minute : period).focus();
            } else {
                const trigger = missingSelect.closest('.select-control')?.querySelector('.select-control__trigger');
                (trigger || missingSelect).focus();
            }
            return;
        }
        let hour24 = Number(hour.value) % 12;
        if (period.value === 'PM') hour24 += 12;
        document.getElementById('scheduleDateTime').value =
            `${date.value} ${String(hour24).padStart(2, '0')}:${minute.value}:00`;
        document.getElementById('submissionMessage').textContent = 'Saving interview schedule...';
        submissionOverlay.classList.remove('hidden');
        submissionOverlay.setAttribute('aria-hidden', 'false');
    });
    document.getElementById('cancelOutcomeButton').addEventListener('click', () => {
        pendingOutcomeForm = null;
        outcomeConfirmModal.classList.add('hidden');
    });
    document.getElementById('confirmOutcomeButton').addEventListener('click', () => {
        if (!pendingOutcomeForm) return;
        const form = pendingOutcomeForm;
        pendingOutcomeForm = null;
        form.dataset.confirmed = 'true';
        outcomeConfirmModal.classList.add('hidden');
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dataset.confirmed = 'false';
            document.getElementById('submissionMessage').textContent =
                `Saving ${form.dataset.outcome === 'Passed' ? 'passed' : 'rejected'} result...`;
            submissionOverlay.classList.remove('hidden');
            submissionOverlay.setAttribute('aria-hidden', 'false');
            form.submit();
        }
    });

    let activeApplicant = null;
    const stageOptions = Array.from(document.getElementById('scheduleType').options);
    const hiredStatusOption = document.querySelector('#scheduleStatus option[value="Hired"]');

    const updateStageChoices = () => {
        if (!activeApplicant) return;
        const currentType = normalizeStage(activeApplicant.interviewType);
        const currentStatus = (activeApplicant.status || '').trim().toLowerCase();
        const passed = currentStatus === 'passed';
        const sameStage = (value) => value.toLowerCase() === currentType;
        const allowed = (value) => {
            const stage = value.toLowerCase();
            if (stage === 'initial interview') return !currentType || sameStage(value);
            if (stage === 'training') return sameStage(value) || (currentType === 'initial interview' && passed);
            if (stage === 'final interview') return sameStage(value) || (currentType === 'training' && passed);
            if (stage === 'hired') return sameStage(value) || (currentType === 'final interview' && passed);
            return true;
        };

        stageOptions.forEach((option) => {
            if (option.value) option.disabled = !allowed(option.value);
        });
        const canHire = currentType === 'final interview' && passed;
        hiredStatusOption.disabled = !canHire && currentStatus !== 'hired';
        document.getElementById('stageGuidance').textContent = canHire
            ? 'Final Interview passed. You may now mark this applicant as Hired.'
            : currentType === 'initial interview' && passed
                ? 'Initial Interview passed. Training is now available.'
                : currentType === 'training' && passed
                    ? 'Training passed. Final Interview is now available.'
                    : 'Pass the current interview stage to unlock the next stage.';

        if (document.getElementById('scheduleType').selectedOptions[0]?.disabled) {
            document.getElementById('scheduleType').value = currentType === 'hired' ? 'Hired' :
                currentType === 'initial interview' ? 'Initial Interview' :
                currentType === 'training' ? 'Training' :
                currentType === 'final interview' ? 'Final Interview' : '';
        }
    };

    document.getElementById('scheduleType').addEventListener('change', (event) => {
        if (event.target.value.toLowerCase() === 'hired') {
            document.getElementById('scheduleStatus').value = 'Hired';
        } else if (normalizeStage(event.target.value) !== normalizeStage(activeApplicant?.interviewType)) {
            document.getElementById('scheduleStatus').value = 'Scheduled';
        } else if (document.getElementById('scheduleStatus').value === 'Hired') {
            document.getElementById('scheduleStatus').value = 'Scheduled';
        }
    });

    document.querySelectorAll('[data-schedule]').forEach((button) => {
        button.addEventListener('click', () => {
            let applicant;
            try {
                applicant = JSON.parse(button.dataset.schedule || '{}');
            } catch (error) {
                console.error('Unable to read interview details.', error);
                return;
            }
            activeApplicant = applicant;
            document.getElementById('scheduleApplicantId').value = applicant.id || '';
            document.getElementById('scheduleApplicantHiddenName').value = applicant.name || '';
            document.getElementById('scheduleApplicantEmail').value = applicant.email || '';
            document.getElementById('scheduleApplicantName').textContent = applicant.name || 'this applicant';
            document.getElementById('scheduleTitle').textContent = applicant.dateInput ? 'Update interview schedule' : 'Set interview schedule';
            const [scheduleDay, scheduleTime] = (applicant.dateInput || '').split('T');
            document.getElementById('scheduleDate').value = scheduleDay || '';
            document.getElementById('scheduleDate').dispatchEvent(new Event('change', { bubbles: true }));
            const [hour24, minute] = (scheduleTime || '').split(':');
            if (hour24 && minute) {
                const numericHour = Number(hour24);
                document.getElementById('scheduleHour').value = String(numericHour % 12 || 12).padStart(2, '0');
                document.getElementById('scheduleMinute').value = minute;
                document.getElementById('schedulePeriod').value = numericHour >= 12 ? 'PM' : 'AM';
            } else {
                document.getElementById('scheduleHour').value = '';
                document.getElementById('scheduleMinute').value = '';
                document.getElementById('schedulePeriod').value = '';
            }
            document.getElementById('scheduleType').value = applicant.interviewType || '';
            document.getElementById('scheduleMode').value = applicant.interviewMode || '';
            document.getElementById('scheduleStatus').value = ['Scheduled', 'Pending', 'Hired'].includes(applicant.status) ? applicant.status : 'Scheduled';
            const currentType = normalizeStage(applicant.interviewType);
            const passed = (applicant.status || '').trim().toLowerCase() === 'passed';
            if (passed && currentType === 'initial interview') {
                document.getElementById('scheduleType').value = 'Training';
                document.getElementById('scheduleStatus').value = 'Scheduled';
            } else if (passed && currentType === 'training') {
                document.getElementById('scheduleType').value = 'Final Interview';
                document.getElementById('scheduleStatus').value = 'Scheduled';
            }
            updateStageChoices();
            document.getElementById('scheduleType').dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('scheduleMode').dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('scheduleStatus').dispatchEvent(new Event('change', { bubbles: true }));
            modal.classList.remove('hidden');
        });
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => modal.classList.add('hidden'));
    });
    const outcomeModal = document.getElementById('outcomeModal');
    if (outcomeModal) {
        outcomeModal.querySelectorAll('[data-outcome-close]').forEach((button) => {
            button.addEventListener('click', () => outcomeModal.remove());
        });
        outcomeModal.addEventListener('click', (event) => {
            if (event.target === outcomeModal) outcomeModal.remove();
        });
    }
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.classList.add('hidden');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') modal.classList.add('hidden');
        if (event.key === 'Escape' && outcomeModal) outcomeModal.remove();
        if (event.key === 'Escape' && !outcomeConfirmModal.classList.contains('hidden')) {
            pendingOutcomeForm = null;
            outcomeConfirmModal.classList.add('hidden');
        }
    });
})();
</script>
<script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
</body>
</html>
