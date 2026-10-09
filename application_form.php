<?php
session_start();
require_once __DIR__ . '/config/connection.php';
$application_list_mode = true;
include('config/application_API.php');

$applicants = isset($records) && is_array($records) ? $records : [];

$totalApplicants = count($applicants);
$scheduled_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS scheduled_count
     FROM applicant
     WHERE interview_date IS NOT NULL
       AND interview_date != ''
       AND interview_date != '0000-00-00 00:00:00'
       AND COALESCE(LOWER(TRIM(status)), '') != 'hired'
       AND COALESCE(LOWER(TRIM(interview_type)), '') != 'hired'"
);
if (!$scheduled_result) {
    throw new RuntimeException('Unable to load scheduled applicant count: ' . mysqli_error($conn));
}
$scheduledCount = (int) mysqli_fetch_assoc($scheduled_result)['scheduled_count'];
$pendingCount = 0;
$completedCount = 0;
foreach ($applicants as $applicant) {
    $status = strtolower(trim((string)($applicant['status'] ?? '')));
    if ($status === 'pending' || $status === '') {
        $pendingCount++;
    }
    if ($status === 'passed' || $status === 'failed') {
        $completedCount++;
    }
}

$pageMessage = $_SESSION['applicant_message'] ?? '';
$pageError = $_SESSION['applicant_error'] ?? '';
unset($_SESSION['applicant_message'], $_SESSION['applicant_error']);
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$styleVersion = file_exists('css/applicant_admin.css') ? filemtime('css/applicant_admin.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applicants | TaskTrack</title>
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
            <a class="active" href="application_form.php" aria-current="page"><span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">Applicants</span></a>
            <a href="interview_sched.php"><span class="nav-icon" aria-hidden="true">▣</span><span class="nav-label">Interview Schedule</span></a>
        </nav>
        <a class="logout-link" href="logout.php"><span class="nav-icon" aria-hidden="true">↪</span><span class="nav-label">Log out</span></a>
    </aside>

    <main class="app-main">
        <header class="page-header">
            <div>
                <p class="eyebrow">TALENT MANAGEMENT</p>
                <h1>Applicants</h1>
                <p class="page-subtitle">Review applications, manage interviews, and track candidate progress.</p>
            </div>
        </header>

        <?php if ($pageMessage !== ''): ?>
            <div class="notice success-notice" role="status"><?= $escape($pageMessage) ?></div>
        <?php endif; ?>
        <?php if ($pageError !== ''): ?>
            <div class="notice error-notice" role="alert"><?= $escape($pageError) ?></div>
        <?php endif; ?>
        <?php if (!empty($delete_message)): ?>
            <div class="notice <?= strpos($delete_message, 'Unable') === 0 || strpos($delete_message, 'No applicant') === 0 ? 'error-notice' : 'success-notice' ?>" role="status">
                <?= $escape($delete_message) ?>
            </div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Applicant summary">
            <article class="stat-card">
                <span class="stat-icon icon-blue">▤</span>
                <div><span class="stat-label">Applicants to review</span><strong><?= $totalApplicants ?></strong></div>
                <span class="stat-foot">Applications without an interview date</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-violet">▣</span>
                <div><span class="stat-label">Interviews scheduled</span><strong><?= $scheduledCount ?></strong></div>
                <span class="stat-foot">Candidates with an interview date</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-amber">◷</span>
                <div><span class="stat-label">Pending review</span><strong><?= $pendingCount ?></strong></div>
                <span class="stat-foot">Awaiting a final outcome</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-green">✓</span>
                <div><span class="stat-label">Completed</span><strong><?= $completedCount ?></strong></div>
                <span class="stat-foot">Passed or failed outcomes</span>
            </article>
        </section>

        <section class="applicant-panel">
            <div class="panel-heading">
                <div>
                    <h2>Applicants to review</h2>
                    <p>Applicants move to Interview Schedule after an interview is set.</p>
                </div>
                <div class="view-tabs" role="tablist" aria-label="Applicant views">
                    <button class="view-tab selected" type="button" role="tab" aria-selected="true">Unscheduled</button>
                    <a class="view-tab" href="interview_sched.php" role="tab">Scheduled</a>
                </div>
            </div>

            <div class="filter-bar">
                <label class="search-box">
                    <span aria-hidden="true">⌕</span>
                    <input id="applicantSearch" type="search" placeholder="Search name, email, or position..." autocomplete="off">
                </label>
                <label class="status-filter">
                    <span>Status</span>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="hired">Hired</option>
                    </select>
                </label>
                <button class="reset-button" id="resetFilters" type="button">Reset filters</button>
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
                    <tbody id="applicantRows">
                    <?php foreach ($applicants as $applicant):
                        $id = (int)($applicant['applicant_id'] ?? 0);
                        $firstName = trim((string)($applicant['firstname'] ?? ''));
                        $lastName = trim((string)($applicant['lastname'] ?? ''));
                        $middleName = trim((string)($applicant['middlename'] ?? ''));
                        $fullName = trim($firstName . ' ' . ($middleName !== '' ? $middleName . ' ' : '') . $lastName);
                        $status = trim((string)($applicant['status'] ?? 'Pending'));
                        $interviewDate = trim((string)($applicant['interview_date'] ?? ''));
                        $resumeName = basename((string)($applicant['resume_path'] ?? ''));
                        $resumeUrl = $resumeName !== '' ? 'uploads/' . rawurlencode($resumeName) : '';
                        $position = trim((string)($applicant['position_applied'] ?? ''));
                        $contact = trim((string)($applicant['contact_number'] ?? ''));
                        $email = trim((string)($applicant['email'] ?? ''));
                        $searchText = strtolower($fullName . ' ' . $email . ' ' . $position . ' ' . $contact);
                        $statusKey = strtolower($status);
                        $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusKey);
                        $record = [
                            'id' => $id,
                            'name' => $fullName,
                            'firstname' => $firstName,
                            'lastname' => $lastName,
                            'middlename' => $middleName,
                            'email' => $email,
                            'contact' => $contact,
                            'facebook' => $applicant['facebook'] ?? '',
                            'position' => $position,
                            'address' => trim(implode(', ', array_filter([
                                $applicant['house_number'] ?? '',
                                $applicant['street'] ?? '',
                                $applicant['barangay'] ?? '',
                                $applicant['city'] ?? '',
                                $applicant['province'] ?? '',
                                $applicant['region'] ?? ''
                            ]))),
                            'status' => $status,
                            'interviewType' => $applicant['interview_type'] ?? '',
                            'interviewMode' => $applicant['interview_mode'] ?? '',
                            'interviewDate' => $interviewDate,
                            'resume' => $resumeUrl
                        ];
                        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                    ?>
                        <tr data-search="<?= $escape($searchText) ?>" data-status="<?= $escape($statusKey !== '' ? $statusKey : ($interviewDate !== '' ? 'scheduled' : 'pending')) ?>">
                            <td>
                                <button class="applicant-name-button" type="button" data-applicant="<?= $escape(json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
                                    <span class="avatar"><?= $escape($initials !== '' ? $initials : 'A') ?></span>
                                    <span><strong><?= $escape($fullName !== '' ? $fullName : 'Unnamed applicant') ?></strong><small>Applicant #<?= $id ?></small></span>
                                </button>
                            </td>
                            <td><span class="cell-primary"><?= $escape($contact !== '' ? $contact : '—') ?></span><small class="cell-secondary"><?= $escape($email !== '' ? $email : 'No email') ?></small></td>
                            <td><?= $escape($position !== '' ? $position : '—') ?></td>
                            <td>
                                <?php if ($resumeUrl !== ''): ?>
                                    <button class="resume-link" type="button" data-resume="<?= $escape($resumeUrl) ?>" data-resume-name="<?= $escape($resumeName) ?>">View resume</button>
                                <?php else: ?><span class="muted">Not provided</span><?php endif; ?>
                            </td>
                            <td><span class="status-pill status-<?= $escape($statusClass !== '' ? $statusClass : 'pending') ?>"><?= $escape($status !== '' ? $status : 'Pending') ?></span></td>
                            <td>
                                <?php if ($interviewDate !== ''): ?>
                                    <span class="cell-primary"><?= $escape(date('M j, Y · g:i A', strtotime($interviewDate))) ?></span>
                                    <small class="cell-secondary"><?= $escape(trim((string)($applicant['interview_type'] ?? '')) ?: 'Interview') ?><?= !empty($applicant['interview_mode']) ? ' · ' . $escape($applicant['interview_mode']) : '' ?></small>
                                <?php else: ?><span class="muted">Not scheduled</span><?php endif; ?>
                            </td>
                            <td class="row-actions">
                                <button class="icon-button details-action" type="button" title="View applicant" aria-label="View <?= $escape($fullName) ?>" data-applicant="<?= $escape(json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">View</button>
                                <?php if ($statusKey === 'failed'): ?>
                                    <span class="muted">Closed</span>
                                <?php else: ?>
                                    <button class="icon-button schedule-action" type="button" title="Schedule interview" aria-label="Schedule interview for <?= $escape($fullName) ?>" data-applicant="<?= $escape(json_encode($record, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">Schedule</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($totalApplicants === 0): ?>
                        <tr><td colspan="7" class="empty-state">No unscheduled applicants to review.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div id="noResults" class="empty-state hidden">No applicants match your search and filters.</div>
            </div>
            <footer class="table-footer"><span>Showing <strong id="visibleCount"><?= $totalApplicants ?></strong> of <?= $totalApplicants ?> unscheduled applicants</span></footer>
        </section>
    </main>
</div>

<div class="modal-backdrop hidden" id="detailsModal" role="dialog" aria-modal="true" aria-labelledby="detailsTitle">
    <section class="modal-card details-card">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <div class="modal-kicker">APPLICANT PROFILE</div>
        <h2 id="detailsTitle">Applicant information</h2>
        <p class="modal-subtitle">Review candidate details and application materials.</p>
        <div class="profile-heading">
            <span class="avatar large-avatar" id="detailAvatar">AP</span>
            <div><h3 id="detailName">Applicant name</h3><span id="detailPosition" class="muted"></span></div>
            <span id="detailStatus" class="status-pill"></span>
        </div>
        <div class="details-grid">
            <div><span>Email address</span><strong id="detailEmail"></strong></div>
            <div><span>Contact number</span><strong id="detailContact"></strong></div>
            <div><span>Address</span><strong id="detailAddress"></strong></div>
            <div><span>Facebook</span><strong id="detailFacebook"></strong></div>
            <div><span>Interview</span><strong id="detailInterview"></strong></div>
        </div>
        <div class="resume-section">
            <div class="resume-heading"><div><h3>Resume / CV</h3><span id="resumeFileName">No resume attached</span></div><a id="resumeDownload" class="secondary-button hidden" href="#" download>Download</a></div>
            <object id="resumePreview" class="resume-preview hidden" type="application/pdf" data=""><p class="preview-fallback">Preview is not available. Download the attached file to view it.</p></object>
            <div id="resumeEmpty" class="resume-empty">No resume was attached to this application.</div>
        </div>
        <div class="modal-actions">
            <button class="danger-button" id="openReject" type="button">Reject application</button>
            <button class="primary-button" id="openSchedule" type="button">Schedule interview</button>
        </div>
    </section>
</div>

<div class="modal-backdrop hidden" id="rejectModal" role="dialog" aria-modal="true" aria-labelledby="rejectTitle">
    <section class="modal-card compact-modal">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <span class="confirm-icon">!</span>
        <h2 id="rejectTitle">Reject this application?</h2>
        <p id="rejectDescription">This applicant will be marked as Failed. This action can be changed later by updating their status.</p>
        <form method="post" class="modal-actions centered-actions">
            <input type="hidden" name="reject_applicant_id" id="rejectApplicantId">
            <button class="secondary-button" type="button" data-close>Cancel</button>
            <button class="danger-button" type="submit" name="reject_applicant" value="1">Confirm rejection</button>
        </form>
    </section>
</div>

<div class="modal-backdrop hidden" id="scheduleModal" role="dialog" aria-modal="true" aria-labelledby="scheduleTitle">
    <section class="modal-card compact-modal schedule-card">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <div class="modal-kicker">INTERVIEW</div>
        <h2 id="scheduleTitle">Schedule interview</h2>
        <p class="modal-subtitle">Set an interview date and details for <strong id="scheduleApplicantName"></strong>.</p>
        <form method="post" class="schedule-form">
            <input type="hidden" name="applicant_id" id="scheduleApplicantId">
            <input type="hidden" name="username" id="scheduleApplicantHiddenName">
            <input type="hidden" name="email" id="scheduleApplicantEmail">
            <label>Interview date and time<input type="datetime-local" name="interview_date" required></label>
            <div class="form-row">
                <label>Interview type
                    <select name="interview_type" required><option value="">Choose type</option><option value="Initial Interview">Initial Interview</option></select>
                </label>
                <label>Interview mode
                    <select name="interview_mode" required><option value="">Choose mode</option><option value="On-site">On-site</option><option value="Online">Online</option></select>
                </label>
            </div>
            <label>Application status
                <select name="status" required><option value="Scheduled">Scheduled</option><option value="Pending">Pending</option><option value="Passed">Passed</option></select>
            </label>
            <div class="modal-actions">
                <button class="secondary-button" type="button" data-close>Cancel</button>
                <button class="primary-button" type="submit" name="save_interview">Save schedule</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop hidden" id="resumeModal" role="dialog" aria-modal="true" aria-labelledby="resumeTitle">
    <section class="modal-card resume-modal-card">
        <button class="modal-close" type="button" data-close aria-label="Close">×</button>
        <div class="resume-heading"><div><div class="modal-kicker">APPLICATION MATERIAL</div><h2 id="resumeTitle">Resume preview</h2><span id="resumeModalFileName"></span></div><a id="resumeModalDownload" class="secondary-button" href="#" download>Download</a></div>
        <object id="resumeModalPreview" class="resume-modal-preview" type="application/pdf" data=""><p class="preview-fallback">Preview is not available. Use Download to view the file.</p></object>
    </section>
</div>

<?php if ($pageMessage !== ''): ?>
<div class="modal-backdrop" id="scheduleSuccessModal" role="dialog" aria-modal="true" aria-labelledby="scheduleSuccessTitle">
    <section class="modal-card compact-modal success-modal">
        <span class="success-icon" aria-hidden="true">✓</span>
        <h2 id="scheduleSuccessTitle">Interview scheduled</h2>
        <p><?= $escape($pageMessage) ?></p>
        <div class="modal-actions"><button class="primary-button" type="button" data-close>Done</button></div>
    </section>
</div>
<?php endif; ?>

<script>
(() => {
    const rows = Array.from(document.querySelectorAll('#applicantRows tr[data-search]'));
    const search = document.getElementById('applicantSearch');
    const statusFilter = document.getElementById('statusFilter');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noResults');
    let activeApplicant = null;

    const showModal = (id) => document.getElementById(id).classList.remove('hidden');
    const hideModal = (modal) => modal.classList.add('hidden');
    const setText = (id, value) => { document.getElementById(id).textContent = value || '—'; };
    const applicantFrom = (button) => {
        try { return JSON.parse(button.dataset.applicant || '{}'); }
        catch (error) { console.error('Unable to read applicant details.', error); return null; }
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

    const setResume = (url, fileName) => {
        const preview = document.getElementById('resumePreview');
        const download = document.getElementById('resumeDownload');
        const empty = document.getElementById('resumeEmpty');
        const hasResume = Boolean(url);
        document.getElementById('resumeFileName').textContent = fileName || 'No resume attached';
        preview.data = hasResume ? url : '';
        preview.classList.toggle('hidden', !hasResume);
        download.href = hasResume ? url : '#';
        download.download = fileName || '';
        download.classList.toggle('hidden', !hasResume);
        empty.classList.toggle('hidden', hasResume);
    };

    const fillSchedule = (applicant) => {
        document.getElementById('scheduleApplicantId').value = applicant.id || '';
        document.getElementById('scheduleApplicantHiddenName').value = applicant.name || '';
        document.getElementById('scheduleApplicantEmail').value = applicant.email || '';
        document.getElementById('scheduleApplicantName').textContent = applicant.name || 'this applicant';
    };

    document.getElementById('resetFilters').addEventListener('click', () => {
        search.value = '';
        statusFilter.value = '';
        filterRows();
    });
    search.addEventListener('input', filterRows);
    statusFilter.addEventListener('change', filterRows);

    document.querySelectorAll('[data-applicant]').forEach((button) => {
        button.addEventListener('click', () => {
            const applicant = applicantFrom(button);
            if (!applicant) return;
            activeApplicant = applicant;
            setText('detailName', applicant.name);
            setText('detailPosition', applicant.position);
            setText('detailEmail', applicant.email);
            setText('detailContact', applicant.contact);
            setText('detailAddress', applicant.address);
            setText('detailFacebook', applicant.facebook);
            setText('detailInterview', applicant.interviewDate ? `${applicant.interviewDate} · ${applicant.interviewType || 'Interview'} · ${applicant.interviewMode || 'Mode not set'}` : 'Not scheduled');
            document.getElementById('detailAvatar').textContent = (applicant.firstname || 'A').slice(0, 1) + (applicant.lastname || '').slice(0, 1);
            const status = document.getElementById('detailStatus');
            status.textContent = applicant.status || 'Pending';
            status.className = `status-pill status-${(applicant.status || 'pending').toLowerCase().replace(/[^a-z0-9-]/g, '')}`;
            const isFailed = (applicant.status || '').trim().toLowerCase() === 'failed';
            const rejectButton = document.getElementById('openReject');
            rejectButton.disabled = isFailed;
            rejectButton.textContent = isFailed ? 'Already rejected' : 'Reject application';
            document.getElementById('openSchedule').disabled = isFailed;
            const resumeName = applicant.resume ? decodeURIComponent(applicant.resume.split('/').pop()) : '';
            setResume(applicant.resume, resumeName);
            showModal('detailsModal');
        });
    });

    document.querySelectorAll('.schedule-action').forEach((button) => {
        button.addEventListener('click', () => {
            const applicant = applicantFrom(button);
            if (!applicant) return;
            activeApplicant = applicant;
            fillSchedule(applicant);
            showModal('scheduleModal');
        });
    });

    document.querySelectorAll('.resume-link').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.dataset.resume;
            const fileName = button.dataset.resumeName || '';
            document.getElementById('resumeModalFileName').textContent = fileName;
            document.getElementById('resumeModalPreview').data = url;
            const download = document.getElementById('resumeModalDownload');
            download.href = url;
            download.download = fileName;
            showModal('resumeModal');
        });
    });

    document.getElementById('openReject').addEventListener('click', () => {
        if (!activeApplicant) return;
        document.getElementById('rejectApplicantId').value = activeApplicant.id;
        document.getElementById('rejectDescription').textContent = `Reject ${activeApplicant.name || 'this applicant'}? Their application will be marked as Failed.`;
        hideModal(document.getElementById('detailsModal'));
        showModal('rejectModal');
    });

    document.getElementById('openSchedule').addEventListener('click', () => {
        if (!activeApplicant) return;
        fillSchedule(activeApplicant);
        hideModal(document.getElementById('detailsModal'));
        showModal('scheduleModal');
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => hideModal(button.closest('.modal-backdrop')));
    });
    document.querySelectorAll('.modal-backdrop').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) hideModal(modal);
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(hideModal);
    });
})();
</script>
</body>
</html>
