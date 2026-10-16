<?php
session_start();
include('config/connection.php');
include('config/autoLog.php');
require_once __DIR__ . '/config/leave_helpers.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
    header("Location: index.php");
    exit();
}
ensureLeaveSchema($conn);
include('config/employee_API.php');

if (empty($_SESSION['employee_edit_csrf'])) {
    $_SESSION['employee_edit_csrf'] = bin2hex(random_bytes(32));
}

$applicant_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS applicant_count
     FROM applicant
     WHERE COALESCE(LOWER(TRIM(status)), '') != 'hired'
       AND COALESCE(LOWER(TRIM(interview_type)), '') != 'hired'"
);
if (!$applicant_result) {
    throw new RuntimeException('Unable to load applicant count: ' . mysqli_error($conn));
}
$applicant_count = (int) mysqli_fetch_assoc($applicant_result)['applicant_count'];

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
    throw new RuntimeException('Unable to load interview schedule count: ' . mysqli_error($conn));
}
$scheduled_count = (int) mysqli_fetch_assoc($scheduled_result)['scheduled_count'];

$trainee_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS trainee_count
     FROM applicant
     WHERE LOWER(REPLACE(TRIM(COALESCE(interview_type, '')), '_', ' ')) = 'training'
       AND COALESCE(LOWER(TRIM(status)), '') NOT IN ('failed', 'hired')
       AND COALESCE(LOWER(TRIM(interview_type)), '') != 'hired'"
);
if (!$trainee_result) {
    throw new RuntimeException('Unable to load trainee count: ' . mysqli_error($conn));
}
$trainee_count = (int) mysqli_fetch_assoc($trainee_result)['trainee_count'];

$attendance_by_employee = [];
$attendance_result = mysqli_query(
    $conn,
    "SELECT a.employee_id, a.status
     FROM attendance a
     INNER JOIN (
         SELECT employee_id, MAX(attendance_id) AS latest_attendance_id
         FROM attendance
         WHERE attendance_date = CURDATE()
         GROUP BY employee_id
     ) latest ON latest.latest_attendance_id = a.attendance_id"
);
if (!$attendance_result) {
    throw new RuntimeException('Unable to load employee attendance: ' . mysqli_error($conn));
}
while ($attendance_row = mysqli_fetch_assoc($attendance_result)) {
    $attendance_status = strtolower(trim($attendance_row['status'] ?? ''));
    $attendance_by_employee[(string) $attendance_row['employee_id']] = in_array(
        $attendance_status,
        ['present', 'early', 'late', 'overtime', 'undertime', 'half day'],
        true
    ) ? 'present' : (in_array($attendance_status, ['paid leave', 'unpaid leave'], true) ? 'leave' : 'absent');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management Dashboard - TASKTRACK</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
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
                <a class="hr-nav-link is-active" href="HR.php" aria-current="page">
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
                <a class="hr-nav-link" href="interview_sched.php">
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
            <header class="hr-greeting">
                <div>
                    <p class="hr-greeting-kicker">HUMAN RESOURCES</p>
                    <h1>Hello, good morning <?= htmlspecialchars($_SESSION['name'] ?? 'HR'); ?></h1>
                    <p class="hr-greeting-quote">“By leading the others with integrity and teamwork accomplish greatness”</p>
                </div>
                <span class="hr-greeting-date"><?= htmlspecialchars(date('l, F j, Y')); ?></span>
            </header>

            <?php if (isset($_SESSION['msg'])): ?>
                <div class="hr-alert is-success" role="status"><?= htmlspecialchars($_SESSION['msg']); ?></div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['err'])): ?>
                <div class="hr-alert is-error" role="alert"><?= htmlspecialchars($_SESSION['err']); ?></div>
                <?php unset($_SESSION['err']); ?>
            <?php endif; ?>

            <section class="hr-overview" aria-label="HR dashboard overview">
                <div class="hr-stat-grid">
                    <article class="hr-stat-card is-employees">
                        <span class="hr-stat-label">Regular Employees</span>
                        <strong class="hr-stat-value"><?= (int) $count; ?></strong>
                        <span class="hr-stat-caption">Employees on record</span>
                    </article>
                    <article class="hr-stat-card is-applicants">
                        <span class="hr-stat-label">Applicants</span>
                        <strong class="hr-stat-value"><?= $applicant_count; ?></strong>
                        <span class="hr-stat-caption">Applications received</span>
                    </article>
                    <article class="hr-stat-card is-scheduled">
                        <span class="hr-stat-label">Scheduled</span>
                        <strong class="hr-stat-value"><?= $scheduled_count; ?></strong>
                        <span class="hr-stat-caption">Interviews scheduled</span>
                    </article>
                    <article class="hr-stat-card is-trainees">
                        <span class="hr-stat-label">Trainees</span>
                        <strong class="hr-stat-value"><?= $trainee_count; ?></strong>
                        <span class="hr-stat-caption">Currently in training</span>
                    </article>
                </div>

                <div class="hr-filters" aria-label="Filter employees">
                    <label class="hr-search">
                        <span class="hr-search-icon" aria-hidden="true">⌕</span>
                        <span class="visually-hidden">Search employees</span>
                        <input type="search" id="employeeSearch" placeholder="Search employee name" autocomplete="off">
                    </label>
                    <label class="visually-hidden" for="attendanceFilter">Filter by attendance</label>
                    <select id="attendanceFilter" class="hr-filter-select">
                        <option value="">All attendance</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="leave">On leave</option>
                    </select>
                </div>
            </section>

            <section class="hr-employee-section" aria-labelledby="employeeDirectoryTitle">
                <div class="hr-section-heading">
                    <div>
                        <p class="hr-section-kicker">TEAM DIRECTORY</p>
                        <h2 id="employeeDirectoryTitle">Employees</h2>
                    </div>
                    <span class="hr-result-count" id="employeeResultCount"><?= (int) $count; ?> employees</span>
                </div>

                <?php if (!empty($records)): ?>
                    <div class="hr-employee-grid" id="employeeGrid">
                        <?php foreach ($records as $row): ?>
                            <?php
                                $employee_id = (string) ($row['employee_id'] ?? '');
                                $attendance_status = $attendance_by_employee[$employee_id] ?? 'absent';
                                $search_text = strtolower(implode(' ', [
                                    $row['username'] ?? '',
                                    $row['position'] ?? '',
                                    $row['contact_number'] ?? '',
                                    $row['email'] ?? '',
                                    $row['address'] ?? '',
                                ]));
                            ?>
                            <article
                                class="hr-employee-card"
                                data-search="<?= htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?>"
                                data-attendance="<?= htmlspecialchars($attendance_status, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <div class="hr-employee-card-top">
                                    <span class="hr-employee-avatar" aria-hidden="true">
                                        <?php if (!empty($row['account_profile_picture']) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $row['account_profile_picture'])): ?>
                                            <img src="uploads/profile-pictures/<?= htmlspecialchars($row['account_profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                        <?php else: ?>
                                            <?= htmlspecialchars(strtoupper(substr($row['username'] ?? 'E', 0, 1))); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="hr-employee-status <?= $attendance_status === 'present' ? 'is-present' : ($attendance_status === 'leave' ? 'is-leave' : 'is-absent'); ?>">
                                        <span class="hr-status-dot" aria-hidden="true"></span>
                                        <?= htmlspecialchars(ucfirst($attendance_status)); ?>
                                    </span>
                                </div>
                                <h3><?= htmlspecialchars($row['username'] ?? 'Unnamed employee'); ?></h3>
                                <p class="hr-employee-position">
                                    <span>Position</span>
                                    <?= htmlspecialchars(($row['position'] ?? '') !== '' ? $row['position'] : 'Not specified'); ?>
                                </p>

                                <details class="hr-employee-details">
                                    <summary>Contact details</summary>
                                    <dl>
                                        <dt>Phone</dt>
                                        <dd><?= htmlspecialchars(($row['contact_number'] ?? '') !== '' ? $row['contact_number'] : 'Not provided'); ?></dd>
                                        <dt>Email</dt>
                                        <dd><?= htmlspecialchars(($row['email'] ?? '') !== '' ? $row['email'] : 'Not provided'); ?></dd>
                                        <dt>Address</dt>
                                        <dd><?= htmlspecialchars(($row['address'] ?? '') !== '' ? $row['address'] : 'Not provided'); ?></dd>
                                    </dl>
                                </details>

                                <form class="hr-employee-actions" action="HR.php" method="post">
                                    <input type="hidden" name="idno" value="<?= htmlspecialchars($employee_id, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button
                                        type="button"
                                        class="hr-action-button is-view"
                                        data-employee-id="<?= htmlspecialchars($employee_id, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-employee-name="<?= htmlspecialchars($row['username'] ?? 'Unnamed employee', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-position="<?= htmlspecialchars($row['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-contact="<?= htmlspecialchars($row['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-hire-date="<?= htmlspecialchars($row['hire_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-attendance="<?= htmlspecialchars(ucfirst($attendance_status), ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="openEmployeeModal(this)"
                                    >View</button>
                                    <button
                                        type="submit"
                                        class="hr-action-button is-delete"
                                        name="del"
                                        value="Delete"
                                        onclick="return confirm('Are you sure you want to delete this record?');"
                                    >Delete</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="hr-no-results" id="noEmployeeResults" hidden>No employees match your search or filters.</p>
                <?php else: ?>
                    <div class="hr-empty-state">
                        <span class="hr-empty-icon" aria-hidden="true">＋</span>
                        <h3>No employee records yet</h3>
                        <p>Hire an applicant to add an employee profile. An administrator creates system accounts.</p>
                    </div>
                <?php endif; ?>
            </section>

            <div class="hr-modal-backdrop" id="employeeModal" hidden>
                <section
                    class="hr-employee-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="employeeModalTitle"
                    aria-describedby="employeeModalSubtitle"
                >
                    <button type="button" class="hr-modal-close" aria-label="Close employee information" onclick="closeEmployeeModal()">×</button>
                    <h2 id="employeeModalTitle">Employee Information</h2>
                    <div class="hr-modal-identity">
                        <h3 id="modalEmployeeName"></h3>
                        <span class="hr-employee-status" id="modalAttendanceStatus">
                            <span class="hr-status-dot" aria-hidden="true"></span>
                            <span id="modalAttendanceText"></span>
                        </span>
                        <p>Employee ID: <span id="modalEmployeeId"></span></p>
                    </div>
                    <dl class="hr-profile-grid">
                        <div>
                            <dt>Email Address</dt>
                            <dd id="modalEmployeeEmail"></dd>
                        </div>
                        <div>
                            <dt>Contact Number</dt>
                            <dd id="modalEmployeeContact"></dd>
                        </div>
                        <div>
                            <dt>Current Position</dt>
                            <dd id="modalEmployeePosition"></dd>
                        </div>
                        <div class="hr-profile-address">
                            <dt>Address</dt>
                            <dd id="modalEmployeeAddress"></dd>
                        </div>
                    </dl>
                    <div class="hr-modal-actions">
                        <button type="button" class="hr-action-button is-edit" id="editEmployeeButton">Edit Details</button>
                        <button type="button" class="hr-action-button is-close" onclick="closeEmployeeModal()">Close</button>
                    </div>
                </section>
            </div>

            <div class="hr-modal-backdrop" id="editEmployeeModal" hidden>
                <section
                    class="hr-employee-modal hr-edit-employee-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="editEmployeeTitle"
                >
                    <button type="button" class="hr-modal-close" aria-label="Close employee editor" onclick="closeEditEmployeeModal()">×</button>
                    <h2 id="editEmployeeTitle">Edit Employee Details</h2>
                    <form action="HR.php" method="post" class="hr-edit-employee-form">
                        <input type="hidden" name="employee_id" id="editEmployeeId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['employee_edit_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="is_update" value="1">
                        <input type="hidden" name="submit" value="1">
                        <label>
                            <span>Full name</span>
                            <input type="text" name="username" id="editEmployeeName" maxlength="100" required>
                        </label>
                        <label>
                            <span>Position</span>
                            <input type="text" name="position" id="editEmployeePosition" maxlength="100">
                        </label>
                        <label>
                            <span>Contact number</span>
                            <input type="text" name="contact_number" id="editEmployeeContact" maxlength="20" inputmode="numeric">
                        </label>
                        <label>
                            <span>Email address</span>
                            <input type="email" name="email" id="editEmployeeEmail" maxlength="255">
                        </label>
                        <label>
                            <span>Hire date (for SIL eligibility)</span>
                            <input type="date" name="hire_date" id="editEmployeeHireDate">
                        </label>
                        <label class="hr-edit-address">
                            <span>Address</span>
                            <input type="text" name="address" id="editEmployeeAddress" maxlength="255">
                        </label>
                        <div class="hr-modal-actions hr-edit-actions">
                            <button type="button" class="hr-action-button is-close" onclick="closeEditEmployeeModal()">Cancel</button>
                            <button type="submit" class="hr-action-button is-view">Save Changes</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        const employeeModal = document.getElementById('employeeModal');
        const editEmployeeModal = document.getElementById('editEmployeeModal');
        const editEmployeeButton = document.getElementById('editEmployeeButton');
        let selectedEmployeeButton = null;

        function openEmployeeModal(button) {
            selectedEmployeeButton = button;
            document.getElementById('modalEmployeeName').textContent = button.dataset.employeeName;
            document.getElementById('modalEmployeeId').textContent = button.dataset.employeeId;
            document.getElementById('modalEmployeeEmail').textContent = button.dataset.email || 'Not provided';
            document.getElementById('modalEmployeeContact').textContent = button.dataset.contact || 'Not provided';
            document.getElementById('modalEmployeePosition').textContent = button.dataset.position || 'Not specified';
            document.getElementById('modalEmployeeAddress').textContent = button.dataset.address || 'Not provided';
            document.getElementById('modalAttendanceText').textContent = button.dataset.attendance;
            document.getElementById('modalAttendanceStatus').classList.toggle(
                'is-present',
                button.dataset.attendance.toLowerCase() === 'present'
            );
            document.getElementById('modalAttendanceStatus').classList.toggle(
                'is-absent',
                !['present', 'leave'].includes(button.dataset.attendance.toLowerCase())
            );
            document.getElementById('modalAttendanceStatus').classList.toggle(
                'is-leave',
                button.dataset.attendance.toLowerCase() === 'leave'
            );
            employeeModal.hidden = false;
            document.body.classList.add('hr-modal-open');
            document.querySelector('.hr-modal-close').focus();
        }

        function openEditEmployeeModal() {
            if (!selectedEmployeeButton) return;
            const employee = selectedEmployeeButton.dataset;
            document.getElementById('editEmployeeId').value = employee.employeeId;
            document.getElementById('editEmployeeName').value = employee.employeeName;
            document.getElementById('editEmployeePosition').value = employee.position;
            document.getElementById('editEmployeeContact').value = employee.contact;
            document.getElementById('editEmployeeEmail').value = employee.email;
            document.getElementById('editEmployeeAddress').value = employee.address;
            document.getElementById('editEmployeeHireDate').value = employee.hireDate;
            employeeModal.hidden = true;
            editEmployeeModal.hidden = false;
            document.querySelector('#editEmployeeModal .hr-modal-close').focus();
        }

        function closeEditEmployeeModal() {
            editEmployeeModal.hidden = true;
            document.body.classList.remove('hr-modal-open');
        }

        editEmployeeButton.addEventListener('click', openEditEmployeeModal);

        function closeEmployeeModal() {
            employeeModal.hidden = true;
            document.body.classList.remove('hr-modal-open');
        }

        employeeModal.addEventListener('click', (event) => {
            if (event.target === employeeModal) closeEmployeeModal();
        });
        editEmployeeModal.addEventListener('click', (event) => {
            if (event.target === editEmployeeModal) closeEditEmployeeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !editEmployeeModal.hidden) closeEditEmployeeModal();
            else if (event.key === 'Escape' && !employeeModal.hidden) closeEmployeeModal();
        });

        const employeeSearch = document.getElementById('employeeSearch');
        const attendanceFilter = document.getElementById('attendanceFilter');
        const employeeCards = Array.from(document.querySelectorAll('.hr-employee-card'));
        const employeeResultCount = document.getElementById('employeeResultCount');
        const noEmployeeResults = document.getElementById('noEmployeeResults');

        function filterEmployees() {
            const searchTerm = employeeSearch.value.trim().toLowerCase();
            const selectedAttendance = attendanceFilter.value;
            let visibleCount = 0;

            employeeCards.forEach((card) => {
                const matchesSearch = card.dataset.search.includes(searchTerm);
                const matchesAttendance = !selectedAttendance || card.dataset.attendance === selectedAttendance;
                const isVisible = matchesSearch && matchesAttendance;

                card.hidden = !isVisible;
                if (isVisible) visibleCount += 1;
            });

            employeeResultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'employee' : 'employees'}`;
            if (noEmployeeResults) {
                noEmployeeResults.hidden = visibleCount !== 0;
            }
        }

        employeeSearch.addEventListener('input', filterEmployees);
        attendanceFilter.addEventListener('change', filterEmployees);
    </script>
    <script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
    <?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>
