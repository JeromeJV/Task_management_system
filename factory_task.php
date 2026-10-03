<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Production_API.php');

// Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}

// Sinusure lg ung mga variable na existing sila
// ---------- Production variables ---------------------
        $message = $message ?? '';
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
    <title>Document</title>
</head>
<body> 
        <h1>Record System</h1>

    <!-- Display Backend Response Message -->
    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
        <label>Product Name:</label>
        <input type="text" name="product_name" placeholder="Enter product name" class="form-control <?= (!empty($product_name_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['product_name'] ?? $view_data['product_name'] ?? '') ?>" required>
        <?php if (!empty($product_name_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($product_name_err) ?></div><?php endif; ?>

        <br><br>

        <label>Target PCS:</label>
        <input type="text" name="target_pcs" placeholder="Enter target pieces" class="form-control <?= (!empty($target_pcs_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['target_pcs'] ?? $view_data['target_pcs'] ?? '') ?>" required>
        <?php if (!empty($target_pcs_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($target_pcs_err) ?></div><?php endif; ?>

        <br><br>

        <label>Stock:</label>
        <input type="text" name="Stock_number" placeholder="Enter Stock number" class="form-control <?= (!empty($Stock_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['Stock_number'] ?? $view_data['Stock_number'] ?? '') ?>" required>
        <?php if (!empty($Stock_number_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($Stock_number_err) ?></div><?php endif; ?>

        <br><br>

        <label>Quantity:</label>
        <input type="text" name="quantity" placeholder="Enter quantity" class="form-control <?= (!empty($quantity_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['quantity'] ?? $view_data['quantity'] ?? '') ?>" required>
        <?php if (!empty($quantity_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($quantity_err) ?></div><?php endif; ?>

        <br><br>

        <label>Due Date:</label>
        <input type="date" name="due_date" class="form-control <?= (!empty($due_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['due_date'] ?? $view_data['due_date'] ?? '') ?>" required>
        <?php if (!empty($due_date_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($due_date_err) ?></div><?php endif; ?>

        <br><br>

        <input type="submit" name="submit" value="Submit">
        <input type="reset" value="Reset">
    </form>

    <button><a href="factory_main.php">Back</a></button>
</body>
</html>