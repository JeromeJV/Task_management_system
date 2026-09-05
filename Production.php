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
    <title>Production Form</title>
</head>
<body>
    <div class="user-page">
        <h2>Welcome to production page!</h2>
        <p>Production: <span><?= htmlspecialchars($_SESSION['email'] ?? ''); ?></span></p>
        <a href="logout.php"><button type="button">Logout</button></a>
    </div>

    <br>

    <?php if ($count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Production ID</th>
                    <th>Product Name</th>
                    <th>Target PCS</th>
                    <th>Stock Number</th>
                    <th>Quantity</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['production_id']); ?></td>
                        <td><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                        <td><?= htmlspecialchars($row['target_pcs']); ?></td>
                        <td><?= htmlspecialchars($row['Stock_number']); ?></td>
                        <td><?= htmlspecialchars($row['quantity']); ?></td>
                        <td><?= htmlspecialchars($row['due_date']); ?></td>
                        
                        <!-- Status Column -->
                        <td>
                            <?php if (($row['product_status'] ?? '') === 'product done'): ?>
                                <span style="color: green; background-color: #e6ffe6; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                                     Product Done
                                </span>
                            <?php else: ?>
                                <span style="color: orange; background-color: #fff3cd; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                                     In Production
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Action Column -->
                        <td>
                            <?php if (($row['product_status'] ?? '') !== 'product done'): ?>
                                <form action="production.php" method="post">
                                    <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id']); ?>">
                                    <input type="submit" name="mark_done" value="Mark as Done" onclick="return confirm('Tapos na ba ang produksyon ng item na ito?');">
                                </form>
                            <?php else: ?>
                                <span style="color: gray; font-style: italic;">Completed</span>
                            <?php endif; ?>
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