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

    <h1>Delivery record</h1>

    <?php if ($count > 0): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Peaces</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="Supervisor_action.php" method="post">
                            <input type="hidden" name="idno" value="<?php echo $row['id']; ?>">
                            <td><?php echo $row['route']; ?></td>
                            <td><?php echo $row['peaces']; ?></td>
                            <td><?php echo $row['stock']; ?></td>
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