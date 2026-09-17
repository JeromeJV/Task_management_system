<?php

session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');

// Authorization sa pag-login kung tamang role pa yung nag-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");

    $message = $message ?? '';

    // Logistic variables
    $route_err = $route_err ?? '';
    $pieces_err = $pieces_err ?? '';
    $stock_err = $stock_err ?? '';
    $delivery_date_err = $delivery_date_err ?? '';
    $records = $records ?? [];
    $count = $count ?? count($records);

    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Logistic Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/delivery_main.css">

</head>

<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->

    <aside class="sidebar">

        <div>

            <!-- SIDEBAR HEADER -->

            <div class="sidebar-header">

                <div class="avatar">
                    🚚
                </div>

                <div class="titles">

                    <div class="name">
                        <div class="sub">Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></div>
        <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
                        
                    </div>

                    <div class="sub">
                    
                        
                    </div>

                </div>

            </div>


            <!-- ================= SIDEBAR NAVIGATION ================= -->

            <div class="sidebar-nav">

                <a href="dashboard.php" class="side-btn">
                    DASHBOARD
                </a>

                <a href="task.php" class="side-btn">
                    TASK
                </a>

                <a href="employee.php" class="side-btn">
                    EMPLOYEE
                </a>

                <a href="production.php" class="side-btn active">
                    PRODUCTION
                </a>

                <a href="delivery_main.php" class="side-btn active">
                    LOGISTIC
                </a>

            </div>

        </div>

    </aside>


    <!-- ================= MAIN CONTENT ================= -->

    <main class="main">


        <!-- ================= TOPBAR ================= -->

        <div class="topbar">

            <div>

                <h1>
                    Logistic Management
                </h1>

            </div>


            <div class="assign">

                <button>

                    <a href="delivery_task.php">
                        Add Delivery
                    </a>

                </button>

            </div>

        </div>


        <!-- ================= CONTENT ================= -->

        <div class="content">

            <div class="page active">

                <!-- ================= PENDING DELIVERIES ================= -->

                <h2 class="section-title">
                    Pending Deliveries
                </h2>


                <?php if (!empty($pending_records)): ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Delivery ID
                                    </th>

                                    <th>
                                        Product Name
                                    </th>

                                    <th>
                                        Destination
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Stock Number
                                    </th>

                                    <th>
                                        Delivery Date
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($pending_records as $row): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($row['delivery_id']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['route']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['pieces']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['stock']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['delivery_date']); ?>
                                        </td>

                                        <td>

                                            <span class="status-pill status-pending">
                                                Pending
                                            </span>

                                        </td>

                                        <td class="action-cell">

                                            <form
                                                action="delivery_main.php"
                                                method="post"
                                            >
                                            

                                                <input
                                                    type="hidden"
                                                    name="idno"
                                                    value="<?= htmlspecialchars($row['delivery_id']); ?>"
                                                >

                                                <input
                                                    type="submit"
                                                    name="del"
                                                    value="Delete"
                                                    class="remove-btn"
                                                    onclick="return confirm('Are you sure?');"
                                                >

                                                <input
                                                    type="submit"
                                                    name="upd"
                                                    value="Update"
                                                    class="edit-btn"
                                                >

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p>
                        No pending deliveries.
                    </p>

                <?php endif; ?>


                <!-- ================= DELIVERY HISTORY ================= -->

                <h2
                    class="section-title"
                    style="margin-top: 30px;"
                >
                    Delivery History (Delivered)
                </h2>


                <?php if (!empty($history_records)): ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Delivery ID
                                    </th>

                                    <th>
                                        Product Name
                                    </th>

                                    <th>
                                        Destination
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Stock Number
                                    </th>

                                    <th>
                                        Delivery Date
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($history_records as $row): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($row['delivery_id']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['route']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['pieces']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['stock']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['delivery_date']); ?>
                                        </td>

                                        <td>

                                            <span class="status-pill status-delivered">
                                                Delivered
                                            </span>

                                        </td>

                                        <td class="action-cell">

                                            <form
                                                action="delivery_main.php"
                                                method="post"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="idno"
                                                    value="<?= htmlspecialchars($row['delivery_id']); ?>"
                                                >

                                                <input
                                                    type="submit"
                                                    name="del"
                                                    value="Delete"
                                                    class="remove-btn"
                                                    onclick="return confirm('Are you sure?');"
                                                >

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p>
                        No delivery history yet.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div> 

</body>

</html>