<?php
session_start();


include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');



// Tiyaking Driver ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'log') {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width= , initial-scale=1.0">
    <title>Admin form</title>
</head>
<body>
    <div class="user-page">
        <h2>Welcome to Logistic page!</h2>
        <p>logistic: <span><?php echo $_SESSION['email']; ?></span></p>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>

    <?php if ($count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Delivery ID</th>
                    <th>Product Name</th>
                    <th>Destination</th>
                    <th>Quantity</th>
                    <th>Stock Number</th>
                    <th>Delivery Date</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="delivery_main.php" method="post">
                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['delivery_id']); ?>">
                            <td><?= htmlspecialchars($row['delivery_id']); ?></td>
                            <td><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($row['route']); ?></td>
                            <td><?= htmlspecialchars($row['pieces']); ?></td>
                            <td><?= htmlspecialchars($row['stock']); ?></td>
                            <td><?= htmlspecialchars($row['delivery_date']); ?></td>
                                 <td>
                                    <?php if (($row['status'] ?? 'Pending') === 'Delivered'): ?>
                                        <!-- Green Badge kapag Delivered na -->
                                        <span style="color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                                             Delivered
                                        </span>
                                    <?php else: ?>
                                        <!-- Yellow/Orange Badge kapag Pending pa -->
                                        <span style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                                             Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 2. CONDITIONAL ACTION BUTTON -->
                                <td>
                                    <?php if (($row['status'] ?? 'Pending') === 'Pending'): ?>
                                        <!-- Lalabas LANG ang button kung Pending pa -->
                                        <form action="driver_delivery.php" method="post">
                                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['delivery_id']); ?>">
                                            <input type="submit" name="mark_delivered" value="Mark as Delivered" onclick="return confirm('Sigurado ka bang naideliber na ito?');">
                                        </form>
                                    <?php else: ?>
                                        <!-- Kapag Delivered na, disabled text na lang ang makikita -->
                                        <span style="color: #6c757d; font-style: italic;">Completed</span>
                                    <?php endif; ?>
                                </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No records.</p>
    <?php endif; ?>
</body>
</html>