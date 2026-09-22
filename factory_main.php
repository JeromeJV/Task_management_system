<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');

// Itakda ang module at isama ang Backend API Logic
$module = 'factory';
$_REQUEST['module'] = 'factory';
include('config/Supervisor_API.php');

// Security Check
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

$pending_records = $pending_records ?? [];
$history_records = $history_records ?? [];
$message = $message ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Management - TASKTRACK</title>
    <link rel="stylesheet" href="css/factory_main.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 450px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .modal-content h3 {
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .modal-actions {
            text-align: right;
            margin-top: 20px;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="app">

    <!-- ================= SIDEBAR ================= -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="avatar">🚚</div>
            <div class="titles">
                <div class="name">TASKTRACK</div>
                <div class="sub">Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></div>
                <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="supervisor.php" class="side-btn">DASHBOARD</a>
            <a href="task.php" class="side-btn">TASK</a>
            <a href="employee.php" class="side-btn">EMPLOYEE</a>
            <a href="factory_main.php" class="side-btn active">PRODUCTION</a>
            <a href="delivery_main.php" class="side-btn">LOGISTIC</a>
        </nav>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ================= MAIN CONTENT ================= -->
    <main class="main">

        <div class="topbar">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <h1>Production Management</h1>

            <div class="topbar-actions">
                <button type="button" class="btn-header" onclick="openProductModal()">Add Product</button>
            </div>
        </div>

        <!-- Notification Message -->
        <?php if (!empty($message)): ?>
            <div style="padding: 10px; background-color: #d4edda; color: #155724; margin: 15px 0; border-radius: 4px;">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="content">

            <!-- 1. PENDING TASKS TABLE -->
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
                                    <td><span class="status-pill status-pending">In Production</span></td>
                                    <td class="action-cell">
                                        <button type="button" class="edit-btn" 
                                            onclick="openUpdateModal(
                                                '<?= htmlspecialchars($row['production_id'] ?? '', ENT_QUOTES); ?>',
                                                '<?= htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES); ?>',
                                                '<?= htmlspecialchars($row['target_pcs'] ?? '', ENT_QUOTES); ?>',
                                                '<?= htmlspecialchars($row['Stock_number'] ?? '', ENT_QUOTES); ?>',
                                                '<?= htmlspecialchars($row['quantity'] ?? '', ENT_QUOTES); ?>',
                                                '<?= htmlspecialchars($row['due_date'] ?? '', ENT_QUOTES); ?>'
                                            )">Update</button>

                                        <form action="factory_main.php" method="post" style="display:inline;">
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
                <p class="empty-message">No pending production records found.</p>
            <?php endif; ?>


            <!-- 2. HISTORY TABLE -->
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
                                    <td><span class="status-pill status-delivered">Product Done</span></td>
                                    <td class="action-cell">
                                        <form action="factory_main.php" method="post">
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

<!-- ================= ADD MODAL ================= -->
<!-- ================= ADD MODAL ================= -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <h3>Add New Production Task</h3>
        <form action="factory_main.php" method="POST">
            <input type="hidden" name="action" value="insert">
            <input type="hidden" name="module" value="factory">

            <div class="form-group">
                <label>Product Name:</label>
                <select name="product_name" class="form-select" required>
                    <option value="" disabled selected>-- Select Product --</option>
                    <option value="Ginga Turmeric Brew">Ginga Turmeric Brew</option>
                    <option value="Ginga Turmeric w/ Guyabano">Ginga Turmeric w/ Guyabano</option>
                    <option value="Ginga Turmeric w/ Lemon">Ginga Turmeric w/ Lemon</option>
                    <option value="Ginga Ginger - Regural Pouch">Ginga Ginger - Regural Pouch</option>
                    <option value="Ginga Ginger Brew with Turmeric And Lemon">Ginga Ginger Brew with Turmeric And Lemon</option>
                    <option value="Ginga Ginger - Strong">Ginga Ginger - Strong</option>
                    <option value="Ginga Ginger - Regural">Ginga Ginger - Regural</option>
                    <option value="Ginga Ginger Pure Tea">Ginga Ginger Pure Tea</option>
                    <option value="Ginga Turmeric Pure Tea">Ginga Turmeric Pure Tea</option>
                    <option value="Ginga Mangosteen Pure Tea">Ginga Mangosteen Pure Tea</option>
                    <option value="Ginga Guyabano Pure Tea">Ginga Guyabano Pure Tea</option>
                    <option value="Ginga Butterfly Pea Tea">Ginga Butterfly Pea Tea</option>
                    <option value="Herbal Green Tea">Herbal Green Tea</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target PCS:</label>
                <input type="text" name="target_pcs" placeholder="Enter target pieces" required>
            </div>
            <div class="form-group">
                <label>Stock Number:</label>
                <input type="text" name="Stock_number" placeholder="Enter stock number" required>
            </div>
            <div class="form-group">
                <label>Quantity:</label>
                <input type="text" name="quantity" placeholder="Enter quantity" required>
            </div>
            <div class="form-group">
                <label>Due Date:</label>
                <input type="date" name="due_date" required>
            </div>

            <div class="modal-actions">
                <button type="submit" name="submit" class="btn-submit">Submit</button>
                <button type="button" class="btn-cancel" onclick="closeProductModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= UPDATE MODAL ================= -->
<!-- ================= UPDATE MODAL ================= -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <h3>Update Production Task</h3>
        <form action="factory_main.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="module" value="factory">
            <input type="hidden" name="production_id" id="update_id">

            <div class="form-group">
                <label>Product Name:</label>
                <select name="product_name" id="update_product_name" class="form-select" required>
                    <option value="" disabled>-- Select Product --</option>
                    <option value="Ginga Turmeric Brew">Ginga Turmeric Brew</option>
                    <option value="Ginga Turmeric w/ Guyabano">Ginga Turmeric w/ Guyabano</option>
                    <option value="Ginga Turmeric w/ Lemon">Ginga Turmeric w/ Lemon</option>
                    <option value="Ginga Ginger - Regural Pouch">Ginga Ginger - Regural Pouch</option>
                    <option value="Ginga Ginger Brew with Turmeric And Lemon">Ginga Ginger Brew with Turmeric And Lemon</option>
                    <option value="Ginga Ginger - Strong">Ginga Ginger - Strong</option>
                    <option value="Ginga Ginger - Regural">Ginga Ginger - Regural</option>
                    <option value="Ginga Ginger Pure Tea">Ginga Ginger Pure Tea</option>
                    <option value="Ginga Turmeric Pure Tea">Ginga Turmeric Pure Tea</option>
                    <option value="Ginga Mangosteen Pure Tea">Ginga Mangosteen Pure Tea</option>
                    <option value="Ginga Guyabano Pure Tea">Ginga Guyabano Pure Tea</option>
                    <option value="Ginga Butterfly Pea Tea">Ginga Butterfly Pea Tea</option>
                    <option value="Herbal Green Tea">Herbal Green Tea</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target PCS:</label>
                <input type="text" name="target_pcs" id="update_target_pcs" required>
            </div>
            <div class="form-group">
                <label>Stock Number:</label>
                <input type="text" name="Stock_number" id="update_stock_number" required>
            </div>
            <div class="form-group">
                <label>Quantity:</label>
                <input type="text" name="quantity" id="update_quantity" required>
            </div>
            <div class="form-group">
                <label>Due Date:</label>
                <input type="date" name="due_date" id="update_due_date" required>
            </div>

            <div class="modal-actions">
                <button type="submit" name="submit" class="btn-submit">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="closeUpdateModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

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
    })();

    function openProductModal() {
        document.getElementById("productModal").style.display = "block";
    }

    function closeProductModal() {
        document.getElementById("productModal").style.display = "none";
    }

    function openUpdateModal(id, name, target, stock, qty, due) {
        document.getElementById("update_id").value = id;
        document.getElementById("update_product_name").value = name;
        document.getElementById("update_target_pcs").value = target;
        document.getElementById("update_stock_number").value = stock;
        document.getElementById("update_quantity").value = qty;
        document.getElementById("update_due_date").value = due;

        document.getElementById("updateModal").style.display = "block";
    }

    function closeUpdateModal() {
        document.getElementById("updateModal").style.display = "none";
    }

    window.onclick = function(event) {
        var addModal = document.getElementById("productModal");
        var updateModal = document.getElementById("updateModal");

        if (event.target == addModal) addModal.style.display = "none";
        if (event.target == updateModal) updateModal.style.display = "none";
    }
</script>

</body>
</html>