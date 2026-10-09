<?php
session_start();
include('config/connection.php');
include('config/autoLog.php');
include('config/employee_API.php');

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
    header("Location: index.php");
    exit();
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
    ) ? 'present' : 'absent';
}

$departments = [];
foreach ($records as $row) {
    $department = trim($row['department'] ?? '');
    if ($department !== '') {
        $departments[$department] = $department;
    }
}
natcasesort($departments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management Dashboard - TASKTRACK</title>
    <link rel="stylesheet" href="css/HR.css?v=<?= filemtime(__DIR__ . '/css/HR.css'); ?>">
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
                <a class="hr-nav-link" href="register.php">
                    <span class="hr-nav-icon" aria-hidden="true">＋</span>
                    <span class="hr-nav-label">Register User</span>
                </a>
                <a class="hr-nav-link" href="atten.php">
                    <span class="hr-nav-icon" aria-hidden="true">◷</span>
                    <span class="hr-nav-label">Attendance</span>
                </a>
                <a class="hr-nav-link" href="application_form.php">
                    <span class="hr-nav-icon" aria-hidden="true">▤</span>
                    <span class="hr-nav-label">Applicants</span>
                </a>
                <a class="hr-nav-link" href="interview_sched.php">
                    <span class="hr-nav-icon" aria-hidden="true">▣</span>
                    <span class="hr-nav-label">Interview Schedule</span>
                </a>
                <a class="hr-nav-link" href="employee.php">
                    <span class="hr-nav-icon" aria-hidden="true">✓</span>
                    <span class="hr-nav-label">Assign Tasks</span>
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
                    <label class="visually-hidden" for="departmentFilter">Filter by department</label>
                    <select id="departmentFilter" class="hr-filter-select">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars(strtolower($department), ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars($department); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="visually-hidden" for="attendanceFilter">Filter by attendance</label>
                    <select id="attendanceFilter" class="hr-filter-select">
                        <option value="">All attendance</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
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
                                $department = trim($row['department'] ?? '');
                                $attendance_status = $attendance_by_employee[$employee_id] ?? 'absent';
                                $search_text = strtolower(implode(' ', [
                                    $row['username'] ?? '',
                                    $department,
                                    $row['position'] ?? '',
                                    $row['contact_number'] ?? '',
                                    $row['email'] ?? '',
                                    $row['address'] ?? '',
                                ]));
                            ?>
                            <article
                                class="hr-employee-card"
                                data-search="<?= htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?>"
                                data-department="<?= htmlspecialchars(strtolower($department), ENT_QUOTES, 'UTF-8'); ?>"
                                data-attendance="<?= htmlspecialchars($attendance_status, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <div class="hr-employee-card-top">
                                    <span class="hr-employee-avatar" aria-hidden="true">
                                        <?= htmlspecialchars(strtoupper(substr($row['username'] ?? 'E', 0, 1))); ?>
                                    </span>
                                    <span class="hr-employee-status <?= $attendance_status === 'present' ? 'is-present' : 'is-absent'; ?>">
                                        <span class="hr-status-dot" aria-hidden="true"></span>
                                        <?= htmlspecialchars(ucfirst($attendance_status)); ?>
                                    </span>
                                </div>
                                <h3><?= htmlspecialchars($row['username'] ?? 'Unnamed employee'); ?></h3>
                                <p class="hr-employee-department">
                                    <span>Department</span>
                                    <?= htmlspecialchars($department !== '' ? $department : 'Not specified'); ?>
                                </p>
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
                                        data-department="<?= htmlspecialchars($department !== '' ? $department : 'Not specified', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-position="<?= htmlspecialchars(($row['position'] ?? '') !== '' ? $row['position'] : 'Not specified', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-contact="<?= htmlspecialchars(($row['contact_number'] ?? '') !== '' ? $row['contact_number'] : 'Not provided', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-email="<?= htmlspecialchars(($row['email'] ?? '') !== '' ? $row['email'] : 'Not provided', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-address="<?= htmlspecialchars(($row['address'] ?? '') !== '' ? $row['address'] : 'Not provided', ENT_QUOTES, 'UTF-8'); ?>"
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
                        <p>Register an employee or hire an applicant to see their profile here.</p>
                        <a class="hr-primary-link" href="register.php">Register User</a>
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
                            <dt>Department</dt>
                            <dd id="modalEmployeeDepartment"></dd>
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
                        <a class="hr-action-button is-edit" id="editEmployeeLink" href="#">Edit Details</a>
                        <button type="button" class="hr-action-button is-close" onclick="closeEmployeeModal()">Close</button>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        const employeeModal = document.getElementById('employeeModal');
        const editEmployeeLink = document.getElementById('editEmployeeLink');

        function openEmployeeModal(button) {
            document.getElementById('modalEmployeeName').textContent = button.dataset.employeeName;
            document.getElementById('modalEmployeeId').textContent = button.dataset.employeeId;
            document.getElementById('modalEmployeeEmail').textContent = button.dataset.email;
            document.getElementById('modalEmployeeDepartment').textContent = button.dataset.department;
            document.getElementById('modalEmployeeContact').textContent = button.dataset.contact;
            document.getElementById('modalEmployeePosition').textContent = button.dataset.position;
            document.getElementById('modalEmployeeAddress').textContent = button.dataset.address;
            document.getElementById('modalAttendanceText').textContent = button.dataset.attendance;
            document.getElementById('modalAttendanceStatus').classList.toggle(
                'is-present',
                button.dataset.attendance.toLowerCase() === 'present'
            );
            document.getElementById('modalAttendanceStatus').classList.toggle(
                'is-absent',
                button.dataset.attendance.toLowerCase() !== 'present'
            );
            editEmployeeLink.href = `register.php?edit_id=${encodeURIComponent(button.dataset.employeeId)}`;
            employeeModal.hidden = false;
            document.body.classList.add('hr-modal-open');
            document.querySelector('.hr-modal-close').focus();
        }

        function closeEmployeeModal() {
            employeeModal.hidden = true;
            document.body.classList.remove('hr-modal-open');
        }

        employeeModal.addEventListener('click', (event) => {
            if (event.target === employeeModal) closeEmployeeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !employeeModal.hidden) closeEmployeeModal();
        });

        const employeeSearch = document.getElementById('employeeSearch');
        const departmentFilter = document.getElementById('departmentFilter');
        const attendanceFilter = document.getElementById('attendanceFilter');
        const employeeCards = Array.from(document.querySelectorAll('.hr-employee-card'));
        const employeeResultCount = document.getElementById('employeeResultCount');
        const noEmployeeResults = document.getElementById('noEmployeeResults');

        function filterEmployees() {
            const searchTerm = employeeSearch.value.trim().toLowerCase();
            const selectedDepartment = departmentFilter.value;
            const selectedAttendance = attendanceFilter.value;
            let visibleCount = 0;

            employeeCards.forEach((card) => {
                const matchesSearch = card.dataset.search.includes(searchTerm);
                const matchesDepartment = !selectedDepartment || card.dataset.department === selectedDepartment;
                const matchesAttendance = !selectedAttendance || card.dataset.attendance === selectedAttendance;
                const isVisible = matchesSearch && matchesDepartment && matchesAttendance;

                card.hidden = !isVisible;
                if (isVisible) visibleCount += 1;
            });

            employeeResultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'employee' : 'employees'}`;
            if (noEmployeeResults) {
                noEmployeeResults.hidden = visibleCount !== 0;
            }
        }

        employeeSearch.addEventListener('input', filterEmployees);
        departmentFilter.addEventListener('change', filterEmployees);
        attendanceFilter.addEventListener('change', filterEmployees);
    </script>
    <?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>
