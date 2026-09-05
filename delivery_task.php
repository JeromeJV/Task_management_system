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
$delivery_date_err = $delivery_date_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);
?>
    
<!DOCTYPE html>
<html lang="en">
<head>    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body> 
        <h1>Record System</h1>

    <!-- Display Backend Response Message -->
    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
    <!-- Mahalaga ito kailangan ito para basahin ng backend ($action === 'insert') -->
    <input type="hidden" name="action" value="<?= isset($view_data) ? 'update' : 'insert'; ?>">
    <input type="hidden" name="module" value="delivery">

    <label>Product:</label> <br>
    <select name="production_id" required>
        <option value="">Product</option>
        <?php foreach ($production_items as $item): ?>
            <option value="<?= htmlspecialchars($item['production_id']); ?>">
                <?= htmlspecialchars($item['product_name'] . " (stock: " . $item['Stock_number'] . " | Qty: " . $item['quantity'] . ")"); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label>Destination:</label> <br>
    <input type="text" name="route" placeholder="Enter destination" class="form-control <?= (!empty($route_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['route'] ?? $view_data['route'] ?? '') ?>" required>
    <?php if (!empty($route_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($route_err) ?></div><?php endif; ?>

    <br><br>

    <label>Delivery Date:</label> <br>
    <input type="date" name="delivery_date" class="form-control <?= (!empty($delivery_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['delivery_date'] ?? $view_data['delivery_date'] ?? '') ?>" required>
    <?php if (!empty($delivery_date_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($delivery_date_err) ?></div><?php endif; ?>

    <br><br>

    <input type="submit" name="submit" value="Submit">
    <input type="reset" value="Reset">
</form>

    <button><a href="delivery_main.php">Back</a></button>
</body>
</html>