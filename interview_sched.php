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
unset($_SESSION['interview_schedule_message'], $_SESSION['interview_schedule_error']);
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
    <link rel="stylesheet" href="css/applicant_admin.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="HR navigation">
        <a class="app-brand" href="HR.php" aria-label="TaskTrack HR home"><span class="brand-mark" aria-hidden="true">T</span><span class="brand-name">TASKTRACK</span></a>
        <nav class="app-nav" aria-label="HR navigation">
            <a href="HR.php"><span class="nav-icon" aria-hidden="true">▦</span><span class="nav-label">Employees</span></a>
            <a href="register.php"><span class="nav-icon" aria-hidden="true">＋</span><span class="nav-label">Register User</span></a>
            <a href="atten.php"><span class="nav-icon" aria-hidden="true">◷</span><span class="nav-label">Attendance</span></a>
            <a href="application_form.php"><span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">Applicants</span></a>
            <a class="active" href="interview_sched.php" aria-current="page"><span class="nav-icon" aria-hidden="true">▣</span><span class="nav-label">Interview Schedule</span></a>
        </nav>
        <a class="logout-link" href="logout.php"><span class="nav-icon" aria-hidden="true">↪</span><span class="nav-label">Log out</span></a>
    </aside>

    <main class="app-main">
        <header class="page-header">
            <div>
                <p class="eyebrow">TALENT MANAGEMENT</p>
                <h1>Interview Schedule</h1>
                <p class="page-subtitle">Review upcoming interviews and manage candidate schedules.</p>
            </div>
            <a class="primary-button" href="application_form.php">▤ <span>View applicants</span></a>
        </header>

        <?php if ($pageMessage !== ''): ?>
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
                                <?php if ($statusKey === 'failed'): ?>
                                    <span class="muted">Closed</span>
                                <?php else: ?>
                                    <button class="icon-button schedule-action" type="button" data-schedule="<?= $escape(json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"><?= $timestamp ? 'Reschedule' : 'Set schedule' ?></button>
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

<div class="modal-backdrop hidden" id="scheduleModal" role="dialog" aria-modal="true" aria-labelledby="scheduleTitle">
    <section class="modal-card compact-modal schedule-card">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <div class="modal-kicker">INTERVIEW</div>
        <h2 id="scheduleTitle">Set interview schedule</h2>
        <p class="modal-subtitle">Update interview details for <strong id="scheduleApplicantName"></strong>.</p>
        <form method="post" class="schedule-form">
            <input type="hidden" name="applicant_id" id="scheduleApplicantId">
            <input type="hidden" name="username" id="scheduleApplicantHiddenName">
            <input type="hidden" name="email" id="scheduleApplicantEmail">
            <label>Interview date and time<input type="datetime-local" name="interview_date" id="scheduleDate" required></label>
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
                <select name="status" id="scheduleStatus" required><option value="Scheduled">Scheduled</option><option value="Pending">Pending</option><option value="Passed">Passed</option><option value="Failed">Failed</option><option value="Hired">Hired</option></select>
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
            document.getElementById('scheduleDate').value = applicant.dateInput || '';
            document.getElementById('scheduleType').value = applicant.interviewType || '';
            document.getElementById('scheduleMode').value = applicant.interviewMode || '';
            document.getElementById('scheduleStatus').value = ['Scheduled', 'Pending', 'Passed', 'Failed', 'Hired'].includes(applicant.status) ? applicant.status : 'Scheduled';
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
            modal.classList.remove('hidden');
        });
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => modal.classList.add('hidden'));
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.classList.add('hidden');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') modal.classList.add('hidden');
    });
})();
</script>
</body>
</html>
