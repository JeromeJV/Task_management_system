<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');


// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    
        // Sinusure lg ung mga variable na existing sila
        $message = $message ?? '';
        //----------- Logistic variables ---------------------
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
</head>
<body>

        <div class="sub"><span><?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?></span></div>

        <button><a href="delivery_task.php">Add Delivery</a></button>
        <button><a href="supervisor.php">Back</a></button>
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
                    <th>Status</th>
                    <th>Actions</th>
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
                                    <!-- Lalabas bilang Green Badge kapag Delivered na -->
                                    <span style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 4px 8px; border-radius: 4px;">
                                         Delivered
                                    </span>
                                <?php else: ?>
                                    <!-- Lalabas bilang Orange/Yellow Badge kapag Pending pa -->
                                    <span style="color: red; font-weight: bold; background-color: #fff3cd; padding: 4px 8px; border-radius: 4px;">
                                         Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="submit" name="del" value="Delete" onclick="return confirm('Are you sure you want to delete it?');">
                                <input type="submit" name="upd" value="Update">
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No records.</p>
    <?php endif; ?>

    </div>

  </div>
</div>
</body>
</html>