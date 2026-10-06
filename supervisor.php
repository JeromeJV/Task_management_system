<?php

session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');


// =====================================================
// AUTHORIZATION
// =====================================================

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}


// =====================================================
// 1. CALCULATE COMPLETED, PENDING, AND OVERDUE TASKS
// =====================================================


// =====================================================
// PRODUCTION TASKS
// =====================================================

$prod_counts_query = "
    SELECT 
        SUM(
            CASE 
                WHEN product_status = 'product done'
                THEN 1
                ELSE 0
            END
        ) AS completed,

        SUM(
            CASE 
                WHEN (
                    product_status != 'product done'
                    OR product_status IS NULL
                )
                AND due_date >= CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE 
                WHEN (
                    product_status != 'product done'
                    OR product_status IS NULL
                )
                AND due_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS overdue

    FROM production
";


$prod_res = mysqli_query(
    $conn,
    $prod_counts_query
);


$prod_data = $prod_res
    ? mysqli_fetch_assoc($prod_res)
    : [
        'completed' => 0,
        'pending' => 0,
        'overdue' => 0
    ];


// =====================================================
// DELIVERY / LOGISTICS TASKS
// =====================================================

$del_counts_query = "
    SELECT 
        SUM(
            CASE 
                WHEN status = 'Delivered'
                THEN 1
                ELSE 0
            END
        ) AS completed,

        SUM(
            CASE 
                WHEN (
                    status != 'Delivered'
                    OR status IS NULL
                )
                AND delivery_date >= CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE 
                WHEN (
                    status != 'Delivered'
                    OR status IS NULL
                )
                AND delivery_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS overdue

    FROM delivery
";


$del_res = mysqli_query(
    $conn,
    $del_counts_query
);


$del_data = $del_res
    ? mysqli_fetch_assoc($del_res)
    : [
        'completed' => 0,
        'pending' => 0,
        'overdue' => 0
    ];


// =====================================================
// COMBINE TOTALS
// =====================================================

$total_completed =
    ($prod_data['completed'] ?? 0) +
    ($del_data['completed'] ?? 0);


$total_pending =
    ($prod_data['pending'] ?? 0) +
    ($del_data['pending'] ?? 0);


$total_overdue =
    ($prod_data['overdue'] ?? 0) +
    ($del_data['overdue'] ?? 0);


// =====================================================
// 2. LATEST PROGRESS REPORTS
// =====================================================

$progress_reports = [];


// =====================================================
// PRODUCTION REPORTS
// =====================================================
//
// IMPORTANT:
// production.completed_by -> users.id -> users.name
//
// Kapag completed na ng Production Worker ang task,
// ang pangalan niya ay manggagaling dito.
// =====================================================

$production_reports_query = "
    SELECT
        p.production_id,
        p.product_name,
        p.target_pcs,
        p.Stock_number,
        p.quantity,
        p.product_status,
        p.due_date,

        u.name AS employee_name

    FROM production p

    LEFT JOIN users u
        ON p.completed_by = u.id

    ORDER BY p.production_id DESC
    LIMIT 10
";


$production_reports_result = mysqli_query(
    $conn,
    $production_reports_query
);


if ($production_reports_result) {

    while (
        $row = mysqli_fetch_assoc(
            $production_reports_result
        )
    ) {

        $is_completed =
            strtolower(
                trim(
                    $row['product_status'] ?? ''
                )
            ) === 'product done';


        $progress_reports[] = [

            'source' => 'Production',

            // ACTUAL PRODUCTION EMPLOYEE
            'employee_name' =>
                !empty($row['employee_name'])
                    ? $row['employee_name']
                    : 'Unassigned',

            'product_id' =>
                (int)$row['production_id'],

            'source_id' =>
                (int)$row['production_id'],

            'title' =>
                $row['product_name']
                ?? 'Unnamed Product',

            'target_pcs' =>
                $row['target_pcs']
                ?? 0,

            'stock_number' =>
                $row['Stock_number']
                ?? '',

            'quantity' =>
                $row['quantity']
                ?? 0,

            'due_date' =>
                !empty($row['due_date'])
                    ? $row['due_date']
                    : '-',

            'status' =>
                $is_completed
                    ? 'Completed'
                    : 'Pending',

            'status_class' =>
                $is_completed
                    ? 'completed'
                    : 'pending',

            'icon_class' =>
                'production-icon'
        ];
    }
}


// =====================================================
// DELIVERY / LOGISTICS REPORTS
// =====================================================
//
// driver_id -> users.id -> users.name
// Ito ang actual employee/driver name.
// =====================================================

$delivery_reports_query = "
    SELECT
        d.delivery_id,
        d.production_id,
        d.route,
        d.pieces,
        d.stock,
        d.driver_id,
        d.delivery_date,
        d.status,

        p.product_name,
        p.target_pcs,
        p.Stock_number,
        p.quantity,
        p.due_date,

        u.name AS employee_name

    FROM delivery d

    LEFT JOIN production p
        ON d.production_id = p.production_id

    LEFT JOIN users u
        ON d.driver_id = u.id

    ORDER BY d.delivery_id DESC
    LIMIT 10
";


$delivery_reports_result = mysqli_query(
    $conn,
    $delivery_reports_query
);


if ($delivery_reports_result) {

    while (
        $row = mysqli_fetch_assoc(
            $delivery_reports_result
        )
    ) {

        $is_delivered =
            strtolower(
                trim(
                    $row['status'] ?? ''
                )
            ) === 'delivered';


        $report_title =
            !empty($row['product_name'])
                ? $row['product_name']
                : (
                    !empty($row['route'])
                        ? $row['route']
                        : 'Delivery #' .
                            $row['delivery_id']
                );


        $progress_reports[] = [

            'source' => 'Logistics',

            // ACTUAL EMPLOYEE / DRIVER NAME
            'employee_name' =>
                !empty($row['employee_name'])
                    ? $row['employee_name']
                    : 'Unassigned',

            'product_id' =>
                !empty($row['production_id'])
                    ? (int)$row['production_id']
                    : '-',

            'source_id' =>
                (int)$row['delivery_id'],

            'title' =>
                $report_title,

            'target_pcs' =>
                !empty($row['target_pcs'])
                    ? $row['target_pcs']
                    : '-',

            'stock_number' =>
                !empty($row['stock'])
                    ? $row['stock']
                    : (
                        !empty($row['Stock_number'])
                            ? $row['Stock_number']
                            : '-'
                    ),

            'quantity' =>
                !empty($row['pieces'])
                    ? $row['pieces']
                    : (
                        !empty($row['quantity'])
                            ? $row['quantity']
                            : '-'
                    ),

            'due_date' =>
                !empty($row['due_date'])
                    ? $row['due_date']
                    : (
                        !empty($row['delivery_date'])
                            ? $row['delivery_date']
                            : '-'
                    ),

            'status' =>
                $is_delivered
                    ? 'Delivered'
                    : 'Pending',

            'status_class' =>
                $is_delivered
                    ? 'delivered'
                    : 'pending',

            'icon_class' =>
                'logistics-icon'
        ];
    }
}


// =====================================================
// SORT PROGRESS REPORTS
// =====================================================

usort(
    $progress_reports,
    function ($a, $b) {

        return
            $b['source_id']
            <=>
            $a['source_id'];
    }
);


$progress_reports =
    array_slice(
        $progress_reports,
        0,
        8
    );


// =====================================================
// 3. LATEST DELIVERY REPORTS
// =====================================================

$latest_delivery_reports = [];


$latest_delivery_query = "
    SELECT
        delivery_id,
        route,
        pieces,
        stock,
        delivery_date
    FROM delivery
    ORDER BY delivery_id DESC
    LIMIT 8
";


$latest_delivery_result = mysqli_query(
    $conn,
    $latest_delivery_query
);


if ($latest_delivery_result) {

    while (
        $delivery =
            mysqli_fetch_assoc(
                $latest_delivery_result
            )
    ) {

        $latest_delivery_reports[] = $delivery;
    }
}


// =====================================================
// 4. OTHER VARIABLES
// =====================================================

$message =
    $message ?? '';

$route_err =
    $route_err ?? '';

$pieces_err =
    $pieces_err ?? '';

$stock_err =
    $stock_err ?? '';

$delivery_date_err =
    $delivery_date_err ?? '';

$records =
    $records ?? [];

$count =
    $count ?? count($records);

$product_name_err =
    $product_name_err ?? '';

$target_pcs_err =
    $target_pcs_err ?? '';

$due_date_err =
    $due_date_err ?? '';

$Stock_number_err =
    $Stock_number_err ?? '';

$quantity_err =
    $quantity_err ?? '';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Supervisor Form</title>

    <link
        rel="stylesheet"
        href="css/supervisor.css"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>

</head>


<script>

window.supervisorProgressReports =
    <?= json_encode(
        $progress_reports,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ); ?>;

</script>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <div>

        <div class="sidebar-header">

            <div class="avatar">
                🐐
            </div>

            <div>

                <div class="name">
                    TASKTRACK
                </div>

                <div class="sub">

                    Supervisor :

                    <span>
                        <?= htmlspecialchars(
                            $_SESSION['name'] ?? ''
                        ); ?>
                    </span>

                </div>

                <div class="sub">

                    <span>

                        <?= isset($_SESSION['email'])
                            ? htmlspecialchars(
                                $_SESSION['email']
                            )
                            : '';
                        ?>

                    </span>

                </div>

            </div>

        </div>


        <!-- =================================================
             NAVIGATION
        ================================================== -->

        <div class="sidebar-nav">


            <button
                id="navDashboard"
                class="side-btn active"
                onclick="showPage('dashboard')"
            >

                DASHBOARD

            </button>


            <button
                id="navTask"
                class="side-btn"
                onclick="showPage('task')"
            >

                TASK

            </button>


            <button
                class="side-btn"
                type="button"
            >

                <a href="factory_main.php">

                    PRODUCTION

                </a>

            </button>


            <button
                class="side-btn"
                type="button"
            >

                <a href="delivery_main.php">

                    LOGISTIC

                </a>

            </button>


        </div>

    </div>


    <!-- =================================================
         LOGOUT
    ================================================== -->

    <a
        href="logout.php"
        style="text-decoration:none;"
    >

        <button class="logout-btn">

            LOG OUT

        </button>

    </a>


</div>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main">


    <!-- =================================================
         TOPBAR
    ================================================== -->

    <div class="topbar">

        <h1>
            Supervisor
        </h1>

    </div>


    <div class="content">


        <!-- =================================================
             DASHBOARD PAGE
        ================================================== -->

        <div
            id="page-dashboard"
            class="page active"
        >


            <!-- =================================================
                 STAT CARDS
            ================================================== -->

            <div class="stat-row">


                <!-- COMPLETED -->

                <div
                    class="
                        stat-card
                        status-card
                        completed-card
                    "
                    id="cardCompleted"
                    onclick="
                        focusGraphStatus('completed')
                    "
                >

                    <div
                        class="num"
                        id="statCompleted"
                    >

                        <?= (int)$total_completed ?>

                    </div>


                    <div class="label">

                        COMPLETED

                    </div>

                </div>


                <!-- PENDING -->

                <div
                    class="
                        stat-card
                        status-card
                        pending-card
                    "
                    id="cardPending"
                    onclick="
                        focusGraphStatus('pending')
                    "
                >

                    <div
                        class="num"
                        id="statPending"
                    >

                        <?= (int)$total_pending ?>

                    </div>


                    <div class="label">

                        PENDING

                    </div>

                </div>


                <!-- OVERDUE -->

                <div
                    class="
                        stat-card
                        status-card
                        overdue-card
                    "
                    id="cardOverdue"
                    onclick="
                        focusGraphStatus('overdue')
                    "
                >

                    <div
                        class="num"
                        id="statOverdue"
                    >

                        <?= (int)$total_overdue ?>

                    </div>


                    <div class="label">

                        OVERDUE

                    </div>

                </div>


            </div>


            <!-- =================================================
                 PRODUCT PROGRESS GRAPH
            ================================================== -->

            <div class="dash-lower">

                <div
                    class="chart-panel"
                    id="productProgressPanel"
                >

                    <h3>

                        PRODUCT PROGRESS

                    </h3>


                    <div
                        id="graphStatusLabel"
                        class="graph-status-label"
                    ></div>


                    <div class="chart-container">

                        <canvas
                            id="myChart"
                        ></canvas>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 LATEST PROGRESS REPORTS
            ================================================== -->

            <div class="progress-report-panel">


                <!-- REPORT HEADER -->

                <div class="progress-report-header">

                    <div class="progress-report-heading">

                        <h3>

                            LATEST PROGRESS REPORTS

                        </h3>


                        <p>

                            Latest updates from your
                            Production and Logistics teams.

                        </p>

                    </div>


                    <div class="progress-report-count">

                        <?= count($progress_reports); ?>

                        REPORTS

                    </div>

                </div>


                <!-- =================================================
                     REPORT TABLE
                ================================================== -->

                <div class="progress-report-table-wrap">

                    <table class="progress-report-table">


                        <thead>

                            <tr>

                                <th>
                                    Employee Name
                                </th>

                                <th>
                                    Product Name
                                </th>

                                <th>
                                    Target PCS
                                </th>

                                <th>
                                    Stock Number
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Due Date
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (
                                !empty(
                                    $progress_reports
                                )
                            ): ?>


                                <?php foreach (
                                    $progress_reports
                                    as $report
                                ): ?>


                                    <?php

                                    $employee_name =
                                        $report['employee_name']
                                        ?? 'Unassigned';


                                    $title =
                                        $report['title']
                                        ?? 'Untitled';


                                    $target_pcs =
                                        $report['target_pcs']
                                        ?? '-';


                                    $stock_number =
                                        $report['stock_number']
                                        ?? '-';


                                    $quantity =
                                        $report['quantity']
                                        ?? '-';


                                    $due_date =
                                        $report['due_date']
                                        ?? '-';


                                    $status =
                                        $report['status']
                                        ?? 'Pending';


                                    $status_class =
                                        $report['status_class']
                                        ?? 'pending';

                                    ?>


                                    <tr>


                                        <!-- EMPLOYEE NAME -->

                                        <td
                                            class="report-employee-name"
                                        >

                                            <?= htmlspecialchars(
                                                $employee_name
                                            ); ?>

                                        </td>


                                        <!-- PRODUCT NAME -->

                                        <td
                                            class="report-product-name"
                                        >

                                            <?= htmlspecialchars(
                                                $title
                                            ); ?>

                                        </td>


                                        <!-- TARGET PCS -->

                                        <td>

                                            <?= htmlspecialchars(
                                                (string)$target_pcs
                                            ); ?>

                                        </td>


                                        <!-- STOCK NUMBER -->

                                        <td>

                                            <?= htmlspecialchars(
                                                (string)$stock_number
                                            ); ?>

                                        </td>


                                        <!-- QUANTITY -->

                                        <td>

                                            <?= htmlspecialchars(
                                                (string)$quantity
                                            ); ?>

                                        </td>


                                        <!-- DUE DATE -->

                                        <td>

                                            <?= htmlspecialchars(
                                                (string)$due_date
                                            ); ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="
                                                    report-table-status
                                                    <?= htmlspecialchars(
                                                        $status_class
                                                    ); ?>
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $status
                                                ); ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>




                            <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>


        </div>


        <!-- =================================================
             TASK PAGE
        ================================================== -->

        <div
            id="page-task"
            class="page"
            style="display:none;"
        >

            <h2 class="section-title">

                TASKS

            </h2>


            <div class="green-table-panel">

                <table class="green-table">

                    <thead>

                        <tr>

                            <th>
                                Employee
                            </th>

                            <th>
                                Task
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Due Date
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody id="taskBody">

                        <!--
                            JS WILL LOAD TASKS HERE
                        -->

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =================================================
             EMPLOYEE PAGE
        ================================================== -->

        <div
            id="page-employee"
            class="page"
            style="display:none;"
        >

            <div class="employee-layout">

                <div class="employee-list">

                    <h4>

                        Employee's

                    </h4>

                    <div
                        id="employeeCards"
                    ></div>

                </div>


                <div
                    class="employee-detail"
                    id="employeeDetail"
                ></div>

            </div>

        </div>


        <!-- =================================================
             LOGISTIC PAGE
        ================================================== -->

        <div
            id="page-logistic"
            class="page"
            style="display:none;"
        >

            <div class="assign">

                <button>

                    <a
                        href="supervisor.php"
                    >

                        BACK

                    </a>

                </button>

            </div>


            <div class="stat-row">

                <div class="stat-card green">

                    <div class="label">

                        Total delivery

                    </div>

                    <div
                        class="num"
                        style="margin-top:4px;"
                    >

                        3

                    </div>

                </div>


                <div class="stat-card green">

                    <div class="label">

                        Active Shipments

                    </div>

                    <div
                        class="num"
                        style="margin-top:4px;"
                    >

                        3

                    </div>

                    <div class="sub">

                        5 total shipments

                    </div>

                </div>

            </div>


            <div
                class="bottom-row"
                style="margin-bottom:24px;"
            >

                <div class="info-panel">

                    <h4>

                        Fleet status

                    </h4>

                    <div class="info-row">

                        <span>
                            Active Vehicles:
                        </span>

                        <span>
                            12/15
                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Available Driver:
                        </span>

                        <span>
                            3
                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Maintenance:
                        </span>

                        <span>
                            0
                        </span>

                    </div>

                </div>


                <div class="info-panel">

                    <h4>

                        Warehouse capacity

                    </h4>


                    <div class="info-row">

                        <span>
                            Warehouse A:
                        </span>

                        <span>
                            78%
                        </span>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-fill"
                            style="width:78%;"
                        ></div>

                    </div>


                    <div
                        class="info-row"
                        style="margin-top:10px;"
                    >

                        <span>
                            Warehouse B:
                        </span>

                        <span>
                            62%
                        </span>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-fill"
                            style="width:62%;"
                        ></div>

                    </div>

                </div>

            </div>

        </div>


    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="js/supervisor.js"></script>


</body>

</html>