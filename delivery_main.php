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
    <button><a href="delivery_task.php">Add Delivery Record</a></button>
    <button><a href="supervisor.php">Back</a></button>

</body>
</html>