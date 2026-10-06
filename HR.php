<?php

session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/employee_API.php');

// Authorization
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
    header("Location: index.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>HR Management - Employee</title>

    <link rel="stylesheet" href="css/hr.css">
</head>

<body>

<!-- ============================================================
     SIDEBAR
============================================================ -->

<div class="sidebar">

    <div>

        <div class="sidebar-header">

            <div class="avatar"></div>

            <div>

                <div class="name">
                    TASKTRACK
                </div>

                <div class="sub">
                    Human Resource :
                    <span>
                        <?= htmlspecialchars($_SESSION['name'] ?? 'HR'); ?>
                    </span>
                </div>

                <div class="sub">
                    <span>
                        <?= htmlspecialchars($_SESSION['email'] ?? ''); ?>
                    </span>
                </div>

            </div>

        </div>


        <div class="sidebar-nav">

            <!-- EMPLOYEE -->
            <a class="side-btn active" href="HR.php">
                <span>EMPLOYEE</span>
            </a>

            <!-- REGISTER USER -->
            <a class="side-btn" href="register.php">
                <span>REGISTER USER</span>
            </a>

            <!-- ATTENDANCE -->
            <a class="side-btn" href="atten.php">
                <span>ATTENDANCE</span>
            </a>

            <!-- APPLICANT -->
            <a class="side-btn" href="appli_form.php">
                <span>APPLICANT</span>
            </a>

            <!-- INTERVIEW -->
            <a class="side-btn" href="interview_sched.php">
                <span>INTERVIEW</span>
            </a>

        </div>

    </div>


    <!-- LOGOUT -->

    <a href="logout.php">

        <button
            class="logout-btn"
            type="button"
        >
            LOG OUT
        </button>

    </a>

</div>


<!-- ============================================================
     MAIN CONTENT
============================================================ -->

<div class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                Employee Management
            </h1>

            <p class="topbar-subtitle">
                Manage and monitor your employees
            </p>

        </div>

    </div>


    <div class="content">


        <!-- ====================================================
             SUCCESS MESSAGE
        ===================================================== -->

        <?php if (isset($_SESSION['msg'])): ?>

            <div class="message success-message">

                <?= htmlspecialchars($_SESSION['msg']); ?>

            </div>

            <?php unset($_SESSION['msg']); ?>

        <?php endif; ?>


        <!-- ====================================================
             ERROR MESSAGE
        ===================================================== -->

        <?php if (isset($_SESSION['err'])): ?>

            <div class="message error-message">

                <?= htmlspecialchars($_SESSION['err']); ?>

            </div>

            <?php unset($_SESSION['err']); ?>

        <?php endif; ?>


        <!-- ====================================================
             EMPLOYEE STATISTICS
        ===================================================== -->

        <div class="employee-stat-row">


            <!-- REGULAR EMPLOYEES -->

            <div class="employee-stat-card">

                <div class="employee-stat-icon">
                    👥
                </div>

                <div>

                    <div class="employee-stat-number">
                        <?= (int)($count ?? 0); ?>
                    </div>

                    <div class="employee-stat-label">
                        REGULAR EMPLOYEES
                    </div>

                </div>

            </div>


            <!-- APPLICANTS -->

            <div class="employee-stat-card">

                <div class="employee-stat-icon">
                    📄
                </div>

                <div>

                    <div class="employee-stat-number">
                        0
                    </div>

                    <div class="employee-stat-label">
                        APPLICANTS
                    </div>

                </div>

            </div>


            <!-- SCHEDULED -->

            <div class="employee-stat-card">

                <div class="employee-stat-icon">
                    📅
                </div>

                <div>

                    <div class="employee-stat-number">
                        0
                    </div>

                    <div class="employee-stat-label">
                        SCHEDULED
                    </div>

                </div>

            </div>


        </div>


        <!-- ====================================================
             SEARCH AND FILTER
        ===================================================== -->

        <div class="employee-toolbar">


            <!-- SEARCH -->

            <div class="employee-search">

                <span class="search-icon">
                    ⌕
                </span>

                <input
                    type="text"
                    id="employeeSearch"
                    placeholder="Search Employee Name"
                    autocomplete="off"
                >

            </div>


            <!-- POSITION FILTER -->

            <div class="employee-filter">

                <select id="positionFilter">

                    <option value="">
                        All Positions
                    </option>


                    <?php

                    $positions = [];

                    if (!empty($records)) {

                        foreach ($records as $row) {

                            $position = trim(
                                $row['position'] ?? ''
                            );

                            if ($position !== '') {

                                $positions[$position] = true;

                            }

                        }

                    }


                    foreach (array_keys($positions) as $position):

                    ?>

                        <option
                            value="<?= htmlspecialchars(strtolower($position)); ?>"
                        >

                            <?= htmlspecialchars($position); ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


        </div>


        <!-- ====================================================
             EMPLOYEE SECTION
        ===================================================== -->

        <div class="employee-section">


            <!-- SECTION HEADER -->

            <div class="employee-section-header">

                <div>

                    <h2>
                        Employees
                    </h2>

                    <p>
                        List of registered employees
                    </p>

                </div>


                <a
                    href="register.php"
                    class="add-employee-btn"
                >
                    + Register Employee
                </a>

            </div>


            <!-- =================================================
                 EMPLOYEE RECORDS
            ================================================== -->

            <?php if (isset($count) && $count > 0): ?>


                <div
                    class="employee-grid"
                    id="employeeGrid"
                >


                    <?php foreach ($records as $row): ?>


                        <div
                            class="employee-card"

                            data-name="<?= htmlspecialchars(
                                strtolower(
                                    $row['username'] ?? ''
                                )
                            ); ?>"

                            data-position="<?= htmlspecialchars(
                                strtolower(
                                    $row['position'] ?? ''
                                )
                            ); ?>"
                        >


                            <!-- =================================
                                 EMPLOYEE CARD HEADER
                            ================================== -->

                            <div class="employee-card-top">


                                <div class="employee-avatar">

                                    <?= strtoupper(
                                        substr(
                                            $row['username'] ?? 'U',
                                            0,
                                            1
                                        )
                                    ); ?>

                                </div>


                                <div class="employee-card-info">

                                    <h3>

                                        <?= htmlspecialchars(
                                            $row['username']
                                            ?? 'Unknown'
                                        ); ?>

                                    </h3>


                                    <p>

                                        <?= htmlspecialchars(
                                            $row['position']
                                            ?? 'No position'
                                        ); ?>

                                    </p>

                                </div>


                            </div>


                            <!-- DIVIDER -->

                            <div class="employee-card-divider"></div>


                            <!-- =================================
                                 EMPLOYEE DETAILS
                            ================================== -->

                            <div class="employee-card-details">


                                <!-- EMPLOYEE ID -->

                                <div class="employee-detail-row">

                                    <span class="detail-label">
                                        Employee ID
                                    </span>

                                    <span class="detail-value">

                                        <?= htmlspecialchars(
                                            $row['employee_id']
                                            ?? ''
                                        ); ?>

                                    </span>

                                </div>


                                <!-- CONTACT -->

                                <div class="employee-detail-row">

                                    <span class="detail-label">
                                        Contact
                                    </span>

                                    <span class="detail-value">

                                        <?= htmlspecialchars(
                                            $row['contact_number']
                                            ?? ''
                                        ); ?>

                                    </span>

                                </div>


                                <!-- EMAIL -->

                                <div class="employee-detail-row">

                                    <span class="detail-label">
                                        Email
                                    </span>

                                    <span class="detail-value email-value">

                                        <?= htmlspecialchars(
                                            $row['email']
                                            ?? ''
                                        ); ?>

                                    </span>

                                </div>


                            </div>


                            <!-- =================================
                                 ACTION BUTTONS
                            ================================== -->

                            <div class="employee-card-actions">


                                <form
                                    action="HR.php"
                                    method="post"
                                >


                                    <input
                                        type="hidden"
                                        name="idno"
                                        value="<?= htmlspecialchars(
                                            $row['employee_id']
                                        ); ?>"
                                    >


                                    <!-- UPDATE -->

                                    <button
                                        type="submit"
                                        name="upd"
                                        class="employee-update-btn"
                                    >
                                        Update
                                    </button>


                                    <!-- DELETE -->

                                    <button
                                        type="submit"
                                        name="del"
                                        class="employee-delete-btn"

                                        onclick="return confirm(
                                            'Are you sure you want to delete this record?'
                                        );"
                                    >
                                        Delete
                                    </button>


                                </form>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


                <!-- NO SEARCH RESULT -->

                <div
                    id="noEmployeeResult"
                    class="no-employee-result"
                    style="display:none;"
                >
                    No employee found.
                </div>


            <?php else: ?>


                <!-- NO EMPLOYEE RECORD -->

                <div class="no-employee-result">

                    No records found.

                </div>


            <?php endif; ?>


        </div>


    </div>

</div>


<!-- ============================================================
     SEARCH / FILTER
============================================================ -->

<script>

const searchInput =
    document.getElementById('employeeSearch');

const positionFilter =
    document.getElementById('positionFilter');

const employeeCards =
    document.querySelectorAll('.employee-card');

const noEmployeeResult =
    document.getElementById('noEmployeeResult');


function filterEmployees() {

    const searchValue =
        searchInput
            ? searchInput.value.toLowerCase().trim()
            : '';

    const positionValue =
        positionFilter
            ? positionFilter.value.toLowerCase()
            : '';

    let visibleCount = 0;


    employeeCards.forEach(card => {

        const name =
            card.dataset.name || '';

        const position =
            card.dataset.position || '';


        const nameMatch =
            name.includes(searchValue);


        const positionMatch =
            !positionValue ||
            position === positionValue;


        if (nameMatch && positionMatch) {

            card.style.display = '';

            visibleCount++;

        } else {

            card.style.display = 'none';

        }

    });


    if (noEmployeeResult) {

        noEmployeeResult.style.display =
            visibleCount === 0
                ? 'block'
                : 'none';

    }

}


if (searchInput) {

    searchInput.addEventListener(
        'input',
        filterEmployees
    );

}


if (positionFilter) {

    positionFilter.addEventListener(
        'change',
        filterEmployees
    );

}

</script>


</body>
</html>