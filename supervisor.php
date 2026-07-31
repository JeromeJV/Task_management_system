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
$peaces_err = $peaces_err ?? '';
$stock_err = $stock_err ?? '';
$records = $records ?? [];
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
        <p>Supervisor : <span><?= htmlspecialchars($_SESSION['email'] ?? ''); ?></span></p>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>

    <h1>Record System</h1>

    <!-- Display Backend Response Message -->
    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
        <label>Route:</label>
        <input type="text" name="route" placeholder="Enter route" class="form-control <?= (!empty($route_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['route'] ?? '') ?>" required>
        <?php if (!empty($route_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($route_err) ?></div><?php endif; ?>

        <br><br>

        <label>Pieces:</label>
        <input type="text" name="peaces" placeholder="Enter pieces of Product" class="form-control <?= (!empty($peaces_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['peaces'] ?? '') ?>" required>
        <?php if (!empty($peaces_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($peaces_err) ?></div><?php endif; ?>

        <br><br>

        <label>Stock:</label>
        <input type="text" name="stock" placeholder="Enter Stock number" class="form-control <?= (!empty($stock_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" required>
        <?php if (!empty($stock_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($stock_err) ?></div><?php endif; ?>

        <br><br>

        <input type="submit" name="submit" value="Submit">
        <input type="reset" value="Reset">
    </form>
        
    <hr>

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
                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['id']); ?>">
                            <td><?= htmlspecialchars($row['route']); ?></td>
                            <td><?= htmlspecialchars($row['peaces']); ?></td>
                            <td><?= htmlspecialchars($row['stock']); ?></td>
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

</body>
</html>