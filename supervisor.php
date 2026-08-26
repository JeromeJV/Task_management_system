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
    <button><a href="delivery_main.php">Logistic</a></button>
    <button><a href="factory_main.php">Production</a></button>
</body>
</html>