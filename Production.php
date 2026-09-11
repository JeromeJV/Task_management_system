<?php

session_start();

include('config/connection.php');
include('config/autoLog.php');

$_REQUEST['module'] = 'factory';

include('config/Supervisor_API.php');

// Tiyaking Production Worker ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'pro') {
    header("Location: index.php");
    exit();
}

$records = $records ?? [];
$count   = count($records);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Production Management</title>

    <!-- Gamitin ang existing CSS mo -->
    <link rel="stylesheet" href="css/Production.css">
</head>

<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    <aside class="sidebar">

        <div>

            <!-- BRAND -->
            <div class="sidebar-header">

                <div class="avatar">🚚</div>

                <div class="titles">
                    <div class="name">TASKTRACK</div>
                    <div class="sub">LOGISTIC<br>MANAGEMENT</div>
                </div>

            </div>


            <!-- NAVIGATION -->
            <nav class="sidebar-nav">

                <button
                    type="button"
                    class="side-btn"
                    onclick="window.location.href='dashboard.php'">
                    DASHBOARD
                </button>

                <button
                    type="button"
                    class="side-btn"
                    onclick="window.location.href='task.php'">
                    TASK
                </button>

                <button
                    type="button"
                    class="side-btn"
                    onclick="window.location.href='employee.php'">
                    EMPLOYEE
                </button>

                <button
                    type="button"
                    class="side-btn active">
                    PRODUCTION
                </button>

                <button
                    type="button"
                    class="side-btn"
                    onclick="window.location.href='logistic.php'">
                    LOGISTIC
                </button>

            </nav>

        </div>


        <!-- LOGOUT -->
        <div>
            <a href="logout.php" style="text-decoration:none;">
                <button type="button" class="logout-btn">
                    LOGOUT
                </button>
            </a>
        </div>

    </aside>


    <!-- ================= MAIN ================= -->
    <main class="main">

        <!-- TOPBAR -->
        <header class="topbar">

            <h1>Production Management</h1>

            <div class="production-user">
                Production:
                <strong>
                    <?= htmlspecialchars($_SESSION['name'] ?? ''); ?>
                </strong>
            </div>

        </header>


        <!-- CONTENT -->
        <section class="content">


            <!-- ================= PENDING ================= -->

            <h2 class="section-title">
                Pending Production Tasks
            </h2>


            <?php if (!empty($pending_records)): ?>

                <div class="table-wrap">

                    <table class="production-table">

                        <thead>
                            <tr>
                                <th>Production<br>ID</th>
                                <th>Product<br>Name</th>
                                <th>Target<br>PCS</th>
                                <th>Stock<br>Number</th>
                                <th>Quantity</th>
                                <th>Due<br>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>


                        <tbody>

                        <?php foreach ($pending_records as $row): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['production_id'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['product_name'] ?? 'N/A'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['target_pcs'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['Stock_number'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['quantity'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['due_date'] ?? ''
                                    ); ?>
                                </td>


                                <!-- STATUS -->
                                <td>

                                    <?php if (
                                        ($row['product_status'] ?? '') === 'product done'
                                    ): ?>

                                        <span class="status-pill status-delivered">
                                            Product Done
                                        </span>

                                    <?php else: ?>

                                        <span class="status-pill status-pending">
                                            In Production
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->
                                <td class="action-cell">

                                    <?php if (
                                        ($row['product_status'] ?? '') !== 'product done'
                                    ): ?>

                                        <form
                                            action="production.php"
                                            method="post"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="idno"
                                                value="<?= htmlspecialchars(
                                                    $row['production_id'] ?? ''
                                                ); ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="mark_done"
                                                class="action-btn mark-done-btn"
                                                onclick="return confirm('Product done?');"
                                            >
                                                Mark as Done
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="completed-text">
                                            Completed
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    No pending production records found.
                </div>

            <?php endif; ?>


            <!-- ================= HISTORY ================= -->

            <div class="production-section-spacer"></div>

            <h2 class="section-title">
                Production History (Completed)
            </h2>


            <?php if (!empty($history_records)): ?>

                <div class="table-wrap">

                    <table class="production-table">

                        <thead>
                            <tr>
                                <th>Production<br>ID</th>
                                <th>Product<br>Name</th>
                                <th>Target<br>PCS</th>
                                <th>Stock<br>Number</th>
                                <th>Quantity</th>
                                <th>Due<br>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>


                        <tbody>

                        <?php foreach ($history_records as $row): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['production_id'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['product_name'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['target_pcs'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['Stock_number'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['quantity'] ?? ''
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['due_date'] ?? ''
                                    ); ?>
                                </td>


                                <!-- STATUS -->
                                <td>

                                    <span class="status-pill status-delivered">
                                        Product Done
                                    </span>

                                </td>


                                <!-- DELETE -->
                                <td class="action-cell">

                                    <form
                                        action="factory_task.php"
                                        method="post"
                                        style="display:inline;"
                                    >

                                        <input
                                            type="hidden"
                                            name="idno"
                                            value="<?= htmlspecialchars(
                                                $row['production_id'] ?? ''
                                            ); ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="del"
                                            class="remove-btn"
                                            onclick="return confirm('Are you sure you want to delete this record?');"
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

                <div class="empty-state">
                    No completed production records found.
                </div>

            <?php endif; ?>


        </section>

    </main>

</div>

</body>
</html>