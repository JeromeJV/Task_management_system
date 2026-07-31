<?php
    include('config/connection.php');
    
    session_start();
    include ('config/autoLog.php');
    include 'config/Supervisor_API.php';
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
        <h2>Welcome to supervisor page!</h2>
        <p>Supervisor : <span><?php echo $_SESSION['email']; ?></span></p>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>

    <h1>Record System</h1>

    <!-- Display Backend Response Message -->
    <?php if (!empty($message)) echo "<p>$message</p>"; ?>

    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
        <label>Route:</label>
        <!-- <input type="text" name="route" ><br><br> -->
        <input type="text" name="route" placeholder="Enter route" class="form-control <?= (!empty($route_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['route'] ?? '') ?>" required>
        <?php if (!empty($route_err)): ?><div class="invalid-feedback"><?= $route_err ?></div><?php endif; ?>

        <label>Peaces:</label>
        <!-- <input type="text" name="peaces"><br><br> -->
        <input type="text" name="peaces" placeholder="Enter peaces of Product" class="form-control <?= (!empty($peaces_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['peaces'] ?? '') ?>" required>
        <?php if (!empty($peaces_err)): ?><div class="invalid-feedback"><?= $peaces_err ?></div><?php endif; ?>

        <label>Stock:</label>
        <!-- <input type="text" name="stock"><br><br> -->
        <input type="text" name="stock" placeholder="Enter Stock number" class="form-control <?= (!empty($stock_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" required>
        <?php if (!empty($stock_err)): ?><div class="invalid-feedback"><?= $stock_err ?></div><?php endif; ?>


        <input type="submit" name="submit" value="Submit">
        <input type="reset" value="Reset">
    </form>
       

    <h1>Delivery record</h1>

    <?php if ($count > 0) ?>
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
        <a href="supervisor.php"></a>

</body>
</html>