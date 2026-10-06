<?php

include("config/connection.php");
include("config/registerBE.php");

// ============================================================
// EMPLOYEES WITHOUT SYSTEM ACCOUNT
// ============================================================

$empQuery = mysqli_query($conn, "
    SELECT e.employee_id, e.username, e.email, e.position
    FROM employee e
    LEFT JOIN users u ON e.email = u.email
    WHERE u.id IS NULL
    ORDER BY e.username ASC
");


// ============================================================
// REGISTERED SYSTEM ACCOUNTS
// ============================================================

$usersQuery = mysqli_query($conn, "
    SELECT * FROM users
    ORDER BY id ASC
");

$records = [];
$count = 0;

if ($usersQuery) {

    $count = mysqli_num_rows($usersQuery);

    while ($userRow = mysqli_fetch_assoc($usersQuery)) {
        $records[] = $userRow;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/Register.css">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Applicants - TASKTRACK</title>

    <!-- SAME HR CSS -->
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
                    Human Resource
                </div>

            </div>

        </div>


        <div class="sidebar-nav">

            <!-- EMPLOYEE -->

            <a
                href="HR.php"
                class="side-btn"
            >
                <span>EMPLOYEE</span>
            </a>


            <!-- REGISTER USER -->

            <a
                href="register.php"
                class="side-btn active"
            >
                <span>REGISTER USER</span>
            </a>


            <!-- ATTENDANCE -->

            <a
                href="atten.php"
                class="side-btn"
            >
                <span>ATTENDANCE</span>
            </a>


            <!-- APPLICANT -->

            <a
                href="appli_form.php"
                class="side-btn"
            >
                <span>APPLICANT</span>
            </a>


            <!-- INTERVIEW -->

            <a
                href="interview_sched.php"
                class="side-btn"
            >
                <span>INTERVIEW</span>
            </a>

        </div>

    </div>


    <!-- LOGOUT -->

    <a href="logout.php">

        <button
            type="button"
            class="logout-btn"
        >
            LOG OUT
        </button>

    </a>

</div>



<!-- ============================================================
     MAIN
============================================================ -->

<div class="main">


    <!-- ========================================================
         TOPBAR
    ========================================================= -->

    <div class="topbar">

        <div>

            <h1>
                APPLICANTS
            </h1>

            <p class="topbar-subtitle">
                Create and manage employee system accounts
            </p>

        </div>

    </div>



    <!-- ========================================================
         CONTENT
    ========================================================= -->

    <div class="content">


        <!-- ====================================================
             TABS / SEARCH
        ===================================================== -->

        <div class="register-toolbar">


            <div class="register-tabs">

                <button
                    type="button"
                    class="register-tab active"
                    id="listTab"
                >
                    List
                </button>


                <button
                    type="button"
                    class="register-tab"
                    id="scheduledTab"
                >
                    Scheduled
                </button>

            </div>


            <div class="register-search">

                <span>
                    ⌕
                </span>

                <input
                    type="text"
                    id="applicantSearch"
                    placeholder="Search name, email, address, ID..."
                    autocomplete="off"
                >

            </div>


            <button
                type="button"
                class="register-reset"
                id="resetSearch"
            >
                Reset
            </button>

        </div>



        <!-- ====================================================
             MESSAGE
        ===================================================== -->

        <?php if (!empty($msg)): ?>

            <div class="message success-message">

                <?= htmlspecialchars($msg); ?>

            </div>

        <?php endif; ?>



        <!-- ====================================================
             MAIN REGISTER LAYOUT
        ===================================================== -->

        <div class="applicant-layout">


            <!-- =================================================
                 LEFT SIDE - EMPLOYEE LIST
            ================================================== -->

            <div class="applicant-list-panel">


                <div class="applicant-list-header">

                    <div>
                        <span>Email</span>
                    </div>

                    <div>
                        <span>Name</span>
                    </div>

                    <div>
                        <span>ID</span>
                    </div>

                </div>


                <div
                    class="applicant-list"
                    id="employeeListContainer"
                >


                    <?php

                    if (
                        $empQuery &&
                        mysqli_num_rows($empQuery) > 0
                    ):

                        while (
                            $emp = mysqli_fetch_assoc($empQuery)
                        ):

                            $displayText =
                                $emp['username'] .
                                " (" .
                                $emp['position'] .
                                ")";

                    ?>


                        <button
                            type="button"
                            class="applicant-row"

                            data-name="<?= htmlspecialchars(
                                strtolower(
                                    $emp['username']
                                )
                            ); ?>"

                            data-email="<?= htmlspecialchars(
                                strtolower(
                                    $emp['email']
                                )
                            ); ?>"

                            data-id="<?= htmlspecialchars(
                                $emp['employee_id']
                            ); ?>"

                            data-position="<?= htmlspecialchars(
                                strtolower(
                                    $emp['position']
                                )
                            ); ?>"

                            data-display="<?= htmlspecialchars(
                                $displayText
                            ); ?>"
                        >


                            <span class="applicant-email">

                                <?= htmlspecialchars(
                                    $emp['email']
                                ); ?>

                            </span>


                            <span class="applicant-name">

                                <?= htmlspecialchars(
                                    $emp['username']
                                ); ?>

                            </span>


                            <span class="applicant-id">

                                <?= htmlspecialchars(
                                    $emp['employee_id']
                                ); ?>

                            </span>


                        </button>


                    <?php

                        endwhile;

                    else:

                    ?>


                        <div class="applicant-empty">

                            No employees available.

                        </div>


                    <?php endif; ?>


                </div>


                <div
                    id="noSearchResult"
                    class="applicant-empty"
                    style="display:none;"
                >
                    No matching employee found.
                </div>


            </div>



            <!-- =================================================
                 RIGHT SIDE - ACCOUNT REGISTRATION
            ================================================== -->

            <div class="account-registration-panel">


                <div class="account-registration-header">

                    <h2>
                        ACCOUNT REGISTRATION:
                    </h2>

                    <p>
                        Create a system account for the selected employee.
                    </p>

                </div>



                <!-- FORM -->

                <form
                    action="register.php"
                    method="post"
                    id="registrationForm"
                    class="account-registration-form"
                >


                    <!-- ========================================
                         EMPLOYEE SEARCH
                    ========================================= -->

                    <div class="account-form-group">

                        <label for="employeeSearch">
                            Employee
                        </label>


                        <input
                            type="text"
                            id="employeeSearch"
                            list="employeeDatalist"
                            placeholder="Select employee..."
                            autocomplete="off"
                            class="account-input <?= (!empty($name_err)) ? 'input-error' : '' ?>"
                            oninput="handleEmployeeSelect()"
                            required
                        >


                        <input
                            type="hidden"
                            name="employee_id"
                            id="employee_id"
                        >


                        <datalist id="employeeDatalist">

                            <?php

                            mysqli_data_seek(
                                $empQuery,
                                0
                            );

                            if (
                                $empQuery &&
                                mysqli_num_rows($empQuery) > 0
                            ) {

                                while (
                                    $emp = mysqli_fetch_assoc($empQuery)
                                ) {

                                    $displayText =
                                        $emp['username'] .
                                        " (" .
                                        $emp['position'] .
                                        ")";

                            ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $displayText
                                        ); ?>"

                                        data-id="<?= htmlspecialchars(
                                            $emp['employee_id']
                                        ); ?>"

                                        data-email="<?= htmlspecialchars(
                                            $emp['email']
                                        ); ?>"
                                    ></option>

                            <?php

                                }

                            }

                            ?>

                        </datalist>


                        <?php if (!empty($name_err)): ?>

                            <small class="register-error">

                                <?= htmlspecialchars(
                                    $name_err
                                ); ?>

                            </small>

                        <?php endif; ?>

                    </div>



                    <!-- ========================================
                         EMAIL
                    ========================================= -->

                    <div class="account-form-group">

                        <label for="emailInput">
                            Email
                        </label>


                        <input
                            type="email"
                            name="email"
                            id="emailInput"
                            placeholder="Employee email"
                            class="account-input <?= (!empty($email_err)) ? 'input-error' : '' ?>"
                            value="<?= htmlspecialchars(
                                $_POST['email'] ?? ''
                            ); ?>"
                            readonly
                            required
                        >


                        <?php if (!empty($email_err)): ?>

                            <small class="register-error">

                                <?= htmlspecialchars(
                                    $email_err
                                ); ?>

                            </small>

                        <?php endif; ?>

                    </div>



                    <!-- ========================================
                         ROLE
                    ========================================= -->

                    <div class="account-form-group">

                        <label for="role">
                            Role
                        </label>


                        <select
                            name="role"
                            id="role"
                            class="account-input"
                        >

                            <option
                                value="HR"
                                <?= (
                                    isset($_POST['role']) &&
                                    $_POST['role'] === 'HR'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >
                                HR
                            </option>


                            <option
                                value="payroll"
                                <?= (
                                    isset($_POST['role']) &&
                                    $_POST['role'] === 'payroll'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >
                                Payroll
                            </option>


                            <option
                                value="super"
                                <?= (
                                    isset($_POST['role']) &&
                                    $_POST['role'] === 'super'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >
                                Supervisor
                            </option>


                            <option
                                value="log"
                                <?= (
                                    isset($_POST['role']) &&
                                    $_POST['role'] === 'log'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >
                                Logistics
                            </option>


                            <option
                                value="pro"
                                <?= (
                                    isset($_POST['role']) &&
                                    $_POST['role'] === 'pro'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >
                                Production
                            </option>

                        </select>

                    </div>



                    <!-- ========================================
                         PASSWORD
                    ========================================= -->

                    <div class="account-form-group">

                        <label for="password">
                            Password
                        </label>


                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter password"
                            class="account-input <?= (!empty($password_err)) ? 'input-error' : '' ?>"
                            required
                        >


                        <?php if (!empty($password_err)): ?>

                            <small class="register-error">

                                <?= htmlspecialchars(
                                    $password_err
                                ); ?>

                            </small>

                        <?php endif; ?>

                    </div>



                    <!-- ========================================
                         CONFIRM PASSWORD
                    ========================================= -->

                    <div class="account-form-group">

                        <label for="cpassword">
                            Confirm Password
                        </label>


                        <input
                            type="password"
                            name="cpassword"
                            id="cpassword"
                            placeholder="Confirm password"
                            class="account-input <?= (!empty($cpassword_err)) ? 'input-error' : '' ?>"
                            required
                        >


                        <?php if (!empty($cpassword_err)): ?>

                            <small class="register-error">

                                <?= htmlspecialchars(
                                    $cpassword_err
                                ); ?>

                            </small>

                        <?php endif; ?>

                    </div>



                    <!-- ========================================
                         CREATE BUTTON
                    ========================================= -->

                    <button
                        type="submit"
                        name="register_user"
                        class="account-create-btn"
                    >
                        CREATE
                    </button>


                </form>

            </div>

        </div>



        <!-- ====================================================
             REGISTERED ACCOUNTS
        ===================================================== -->

        <div class="registered-section">


            <div class="employee-section-header">

                <div>

                    <h2>
                        Registered System Accounts
                    </h2>

                    <p>
                        Existing employee system accounts
                    </p>

                </div>

            </div>



            <?php if (
                isset($count) &&
                $count > 0
            ): ?>


                <div class="registered-table-wrapper">

                    <table class="registered-table">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Name
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Role
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $records as $row
                            ): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['id']
                                        ); ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['name']
                                        ); ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['email']
                                        ); ?>
                                    </td>


                                    <td>

                                        <span class="role-badge">

                                            <?= htmlspecialchars(
                                                $row['role']
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            action="register.php"
                                            method="POST"
                                        >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= htmlspecialchars(
                                                    $row['id']
                                                ); ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="delete_user"
                                                class="account-delete-btn"

                                                onclick="return confirm(
                                                    'Are you sure you want to delete this user account?'
                                                );"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="applicant-empty">

                    No system accounts found.

                </div>


            <?php endif; ?>


        </div>


    </div>

</div>



<!-- ============================================================
     JAVASCRIPT
============================================================ -->

<script>

/* ============================================================
   EMPLOYEE SELECTION
============================================================ */

function handleEmployeeSelect() {

    const input =
        document.getElementById(
            'employeeSearch'
        );

    const hiddenId =
        document.getElementById(
            'employee_id'
        );

    const email =
        document.getElementById(
            'emailInput'
        );

    const options =
        document.querySelectorAll(
            '#employeeDatalist option'
        );

    let matched = false;


    options.forEach(option => {

        if (
            option.value === input.value
        ) {

            hiddenId.value =
                option.getAttribute(
                    'data-id'
                );

            email.value =
                option.getAttribute(
                    'data-email'
                );

            matched = true;

        }

    });


    if (!matched) {

        hiddenId.value = '';

        email.value = '';

    }

}


/* ============================================================
   APPLICANT LIST
============================================================ */

const applicantRows =
    document.querySelectorAll(
        '.applicant-row'
    );

const searchInput =
    document.getElementById(
        'applicantSearch'
    );

const resetButton =
    document.getElementById(
        'resetSearch'
    );

const noSearchResult =
    document.getElementById(
        'noSearchResult'
    );


function filterApplicants() {

    const searchValue =
        searchInput.value
            .toLowerCase()
            .trim();

    let visible = 0;


    applicantRows.forEach(row => {

        const name =
            row.dataset.name || '';

        const email =
            row.dataset.email || '';

        const id =
            row.dataset.id || '';

        const position =
            row.dataset.position || '';


        const match =
            name.includes(searchValue) ||
            email.includes(searchValue) ||
            id.includes(searchValue) ||
            position.includes(searchValue);


        if (match) {

            row.style.display = '';

            visible++;

        } else {

            row.style.display = 'none';

        }

    });


    noSearchResult.style.display =
        visible === 0
            ? 'block'
            : 'none';

}


if (searchInput) {

    searchInput.addEventListener(
        'input',
        filterApplicants
    );

}


if (resetButton) {

    resetButton.addEventListener(
        'click',
        function () {

            searchInput.value = '';

            filterApplicants();

        }
    );

}


/* ============================================================
   CLICK EMPLOYEE FROM LIST
============================================================ */

applicantRows.forEach(row => {

    row.addEventListener(
        'click',
        function () {

            const display =
                row.dataset.display;

            const id =
                row.dataset.id;

            const email =
                row.dataset.email;


            document.getElementById(
                'employeeSearch'
            ).value = display;


            document.getElementById(
                'employee_id'
            ).value = id;


            document.getElementById(
                'emailInput'
            ).value = email;


            applicantRows.forEach(item => {

                item.classList.remove(
                    'selected'
                );

            });


            row.classList.add(
                'selected'
            );

        }
    );

});


/* ============================================================
   TABS
============================================================ */

const listTab =
    document.getElementById(
        'listTab'
    );

const scheduledTab =
    document.getElementById(
        'scheduledTab'
    );


scheduledTab.addEventListener(
    'click',
    function () {

        scheduledTab.classList.add(
            'active'
        );

        listTab.classList.remove(
            'active'
        );

    }
);


listTab.addEventListener(
    'click',
    function () {

        listTab.classList.add(
            'active'
        );

        scheduledTab.classList.remove(
            'active'
        );

    }
);

</script>


</body>

</html>