<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');
include('config/Production_API.php');

// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

    // Sinusure lg ung mga variable na existing sila
    $message = $message ?? '';
    //----------- Logistic variables ---------------------
    $route_err = $route_err ?? '';
    $pieces_err = $pieces_err ?? '';
    $stock_err = $stock_err ?? '';
    $delivery_date_err = $delivery_date_err ?? '';
    $records = $records ?? [];
    $count = $count ?? count($records);
    // ---------- Production variables ---------------------
    $product_name_err = $product_name_err ?? '';
    $target_pcs_err = $target_pcs_err ?? '';
    $due_date_err = $due_date_err ?? '';
    $Stock_number_err = $Stock_number_err ?? '';
    $quantity_err = $quantity_err ?? '';    
    $count = $count ?? count($records);
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
        <a href="logout.php"><button class="">Logout</button></a>
    </div>

    <hr>
    <button><a href="supervisor_task.php">Add task</a></button>

    <h1>Delivery Record</h1>

    <?php if ($count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Delivery ID</th>
                    <th>Destination</th>
                    <th>Pieces</th>
                    <th>Stock</th>
                    <th>Delivery Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="Supervisor_action.php" method="post">
                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['delivery_id']); ?>">
                            <td><?php echo $row['delivery_id']; ?></td>
                            <td><?= htmlspecialchars($row['route']); ?></td>
                            <td><?= htmlspecialchars($row['pieces']); ?></td>
                            <td><?= htmlspecialchars($row['stock']); ?></td>
                            <td><?= htmlspecialchars($row['delivery_date']); ?></td>
                            <td>
                                <input type="submit" name="del" value="Delete" onclick="return confirm('Sigurado ka bang buburahin ito?');">
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
    <hr>

</body>
</html>