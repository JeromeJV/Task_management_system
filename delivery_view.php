<?php
include'config/connection.php';
include'config/Supervisor_API.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Information Records</title>
</head>
<body>

    <div class="user-page">
        <h2>Welcome to supervisor page!</h2>
        <p>Supervisor : <span><?= htmlspecialchars($_SESSION['name'] ?? ''); ?></span></p>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>

    <hr>
    <button><a href="supervisor_task.php">Add task</a></button>

    <h1>Delivery record</h1>

    <?php if ($count > 0): ?>
        <table border="1">
            <thead>
                <tr>
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
                            <input type="hidden" name="idno" value="<?php echo $row['delivery_id']; ?>">
                            <td><?php echo $row['route']; ?></td>
                            <td><?php echo $row['pieces']; ?></td>
                            <td><?php echo $row['stock']; ?></td>
                            <td><?php echo $row['delivery_date']; ?></td>
                            <td>
                                <input type="submit" name="del" value="Delete">
                                <input type="submit" name="upd" value="Update">
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <a href="supervisor.php">Back to Homepage</a>

    <?php else: ?>
        <p>Fill in all textboxes <br> <a href="supervisor.php">Back to Homepage</a></p>
    <?php endif; ?>

</body>
</html>