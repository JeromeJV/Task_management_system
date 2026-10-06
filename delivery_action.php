<?php
include'config/connection.php';
include'config/Supervisor_API.php';

// Sinusure lg ung mga variable na existing sila
$message = $message ?? '';
$route_err = $route_err ?? '';
$pieces_err = $pieces_err ?? '';
$stock_err = $stock_err ?? '';
$delivery_date_err = $delivery_date_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Action Page</title>
</head>
<body>

    <!-- Show Delete Confirmation if Deleted -->
    <?php if (!empty($delete_message)): ?>
        <p><?php echo $delete_message; ?></p>
    <?php endif; ?>

    <!-- Show Update Form if Edit was clicked -->
    <?php if ($view_data): ?>
        <form action="delivery_update.php" method="post">
            <input type="hidden" name="newid" value="<?php echo $view_data['delivery_id']; ?>">
            
            <table border="1">
                <tr>
                    <td>Destination:</td>
                    <td><input type="text" name="route" placeholder="Enter destination" class="form-control <?= (!empty($route_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['route'] ?? $view_data['route'] ?? '') ?>" required></td>
                    <?php if (!empty($route_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($route_err) ?></div><?php endif; ?>
                </tr>
                <tr>
                    <td>pieces:</td>
                    <td><input type="text" name="pieces" placeholder="Enter pieces" class="form-control <?= (!empty($pieces_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['pieces'] ?? $view_data['pieces'] ?? '') ?>" required></td>
                    <?php if (!empty($pieces_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($pieces_err) ?></div><?php endif; ?>
                </tr>
                <tr>
                    <td>stock:</td>
                    <td><input type="text" name="stock" placeholder="Enter stock" class="form-control <?= (!empty($stock_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['stock'] ?? $view_data['stock'] ?? '') ?>" required></td>
                    <?php if (!empty($stock_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($stock_err) ?></div><?php endif; ?>
                </tr>
                <tr>
                    <td>Delivery Date:</td>
                    <td><input type="date" name="delivery_date" class="form-control <?= (!empty($delivery_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['delivery_date'] ?? $view_data['delivery_date'] ?? '') ?>" required></td>
                    <?php if (!empty($delivery_date_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($delivery_date_err) ?></div><?php endif; ?>
                </tr>
            </table>
            <br>
            <input type="submit" name="submit" value="Update">&nbsp;
            <input type="reset" value="Reset">&nbsp;
            <input type="submit" name="can" value="Cancel">
        </form>
    <?php endif; ?>

</body>
</html>