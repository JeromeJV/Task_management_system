<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');

$module = 'factory';
$_REQUEST['module'] = 'factory';
include('config/Supervisor_API.php');

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

$pending_records = $pending_records ?? [];
$history_records = $history_records ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/factory_main.css">
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    <aside class="sidebar" id="sidebar">

        <!-- LOGO + TITLE -->
        <div class="sidebar-header">
            <div class="avatar">🚚</div>
            <div class="titles">
                <div class="name">TASKTRACK</div>
                <div class="sub">Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></div>
        <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
            </div>
        </div>

        <!-- NAV -->
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="side-btn">DASHBOARD</a>
            <a href="task.php" class="side-btn">TASK</a>
            <a href="employee.php" class="side-btn">EMPLOYEE</a>
            <a href="factory_main.php" class="side-btn active">PRODUCTION</a>
            <a href="delivery_main.php" class="side-btn active">LOGISTIC</a>
        </nav>

    </aside>

    <!-- OVERLAY (para sa mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ================= MAIN ================= -->
    <main class="main">

        <!-- TOPBAR -->
        <div class="topbar">

            <!-- HAMBURGER (mobile only) -->
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <h1>Production Management</h1>

            <!-- ADD PRODUCT BUTTON -->
            <div class="topbar-actions">
                <a href="factory_task.php" class="btn-header">Add Product</a>
            </div>

        </div>

        <!-- CONTENT -->
        <div class="content">

            <!-- ============================================ -->
            <!-- 1. PENDING PRODUCTION TASKS TABLE -->
            <!-- ============================================ -->
            <h2 class="section-title">Pending Production Tasks</h2>

            <?php if (!empty($pending_records)): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Target PCS</th>
                                <th>Stock Number</th>
                                <th>Quantity</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_records as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['production_id'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['product_name'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['target_pcs'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['Stock_number'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['quantity'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['due_date'] ?? ''); ?></td>

                                    <td>
                                        <span class="status-pill status-pending">
                                            In Production
                                        </span>
                                    </td>

                                    <td class="action-cell">
                                        <form action="factory_task.php" method="post">
                                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id'] ?? ''); ?>">
                                            <input type="submit" name="upd" value="Update" class="edit-btn">
                                            <input type="submit" name="del" value="Delete" class="remove-btn" onclick="return confirm('Are you sure you want to delete this record?');">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-message">No pending production records found.</p>
            <?php endif; ?>


            <!-- ============================================ -->
            <!-- 2. PRODUCTION HISTORY TABLE -->
            <!-- ============================================ -->
            <h2 class="section-title history-title">Production History (Completed)</h2>

            <?php if (!empty($history_records)): ?>
                <div class="table-wrap table-wrap-history">
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Target PCS</th>
                                <th>Stock Number</th>
                                <th>Quantity</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_records as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['production_id'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['product_name'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['target_pcs'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['Stock_number'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['quantity'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['due_date'] ?? ''); ?></td>

                                    <td>
                                        <span class="status-pill status-delivered">
                                            Product Done
                                        </span>
                                    </td>

                                    <td class="action-cell">
                                        <form action="factory_task.php" method="post">
                                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id'] ?? ''); ?>">
                                            <input type="submit" name="del" value="Delete" class="remove-btn" onclick="return confirm('Are you sure you want to delete this record?');">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-message">No completed production records found.</p>
            <?php endif; ?>

        </div>

    </main>

</div>

<!-- ================= JS (HAMBURGER TOGGLE) ================= -->
<script>
    (function () {
        const btn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (!btn || !sidebar || !overlay) return;

        function toggle() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        btn.addEventListener('click', toggle);
        overlay.addEventListener('click', toggle);

        sidebar.querySelectorAll('.side-btn').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            });
        });
    })();
</script>

</body>
</html>