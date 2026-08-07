<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');

// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

// Sinusure lg ung mga variable na existing sila
$message = $message ?? '';
$route_err = $route_err ?? '';
$pieces_err = $pieces_err ?? '';
$stock_err = $stock_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Page</title>
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
        <input type="text" name="route" placeholder="Enter route" class="form-control <?= (!empty($route_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['route'] ?? '') ?>" required>
        <?php if (!empty($route_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($route_err) ?></div><?php endif; ?>

        <br>

        <label>Pieces:</label>
        <input type="text" name="pieces" placeholder="Enter pieces of Product" class="form-control <?= (!empty($pieces_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['pieces'] ?? '') ?>" required>
        <?php if (!empty($pieces_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($pieces_err) ?></div><?php endif; ?>

        <br>

        <label>Stock:</label>
        <input type="text" name="stock" placeholder="Enter Stock number" class="form-control <?= (!empty($stock_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" required>
        <?php if (!empty($stock_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($stock_err) ?></div><?php endif; ?>

        <br>

        <input type="submit" name="submit" value="Submit">
        <input type="reset" value="Reset">
    </form>

    <h1>Delivery Record</h1>

    <?php if ($count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Pieces</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="Supervisor_action.php" method="post">
                            <td><?= htmlspecialchars($row['route']); ?></td>
                            <td><?= htmlspecialchars($row['pieces']); ?></td>
                            <td><?= htmlspecialchars($row['stock']); ?></td>
                            <td>
                                <input type="submit" name="del" value="Delete" onclick="return confirm('You want to delete this record?');">
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