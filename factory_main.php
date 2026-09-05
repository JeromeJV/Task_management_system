<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');

// Tiyakin na nakaset ang module bilang 'factory'
$module = 'factory';
$_REQUEST['module'] = 'factory';
include('config/Supervisor_API.php');

// Authorization check
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

// Siguraduhing may laman ang $records at $count
$records = $records ?? [];
$count   = count($records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Form</title>
</head>
<body>
    <div class="user-page">
        <h2>Welcome to supervisor page!</h2>
        <p>Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></p>
        <a href="logout.php"><button type="button">Logout</button></a>
    </div>

    <hr>
    <button type="button"><a href="factory_task.php">Add product Record</a></button>
    <button type="button"><a href="supervisor.php">Back</a></button>

    <h1>Product Record</h1>

    <?php if ($count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
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
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['production_id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['product_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['target_pcs'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['Stock_number'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['quantity'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($row['due_date'] ?? ''); ?></td>
                        
                        <!-- Status Column -->
                        <td>
                            <?php if (($row['product_status'] ?? '') === 'product done'): ?>
                                <span style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 4px 8px; border-radius: 4px;">
                                     Product Done
                                </span>
                            <?php else: ?>
                                <span style="color: #856404; font-weight: bold; background-color: #fff3cd; padding: 4px 8px; border-radius: 4px;">
                                         In Production
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions Column para sa Supervisor -->
                        <td>
                            <form action="factory_task.php" method="post">
                                <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id'] ?? ''); ?>">
                                <input type="submit" name="upd" value="Update">
                                <input type="submit" name="del" value="Delete" onclick="return confirm('Are you sure you want to delete this record?');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No records found.</p>
    <?php endif; ?>

</body>
</html> 