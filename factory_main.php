<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');
include('config/Production_API.php');
// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
        // ---------- Production variables ---------------------
        $product_name_err = $product_name_err ?? '';
        $target_pcs_err = $target_pcs_err ?? '';
        $due_date_err = $due_date_err ?? '';
        $Stock_number_err = $Stock_number_err ?? '';
        $quantity_err = $quantity_err ?? '';    
        $count = $count ?? count($records);
    exit();
    }

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
    <button><a href="factory_task.php">Add product Record</a></button>
    <button><a href="supervisor.php">Back</a></button>

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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="factory_task.php" method="post">
                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['production_id']); ?>">
                            <td><?php echo $row['production_id']; ?></td>
                            <td><?= htmlspecialchars($row['product_name']); ?></td>
                            <td><?= htmlspecialchars($row['target_pcs']); ?></td>
                            <td><?= htmlspecialchars($row['Stock_number']); ?></td>
                            <td><?= htmlspecialchars($row['quantity']); ?></td>
                            <td><?= htmlspecialchars($row['due_date']); ?></td>
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

    

</body>
</html>