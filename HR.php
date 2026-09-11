<?php
    session_start();
    include('config/connection.php');
    include('config/autoLog.php');

    // Authorization sa pag lologin kung tamang role pa ung nag login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin form</title>
</head>
<body>
    <div class="user-page">
        <h2>Welcome to human resource page!</h2>
        <p>Human Resource : <span><?php echo $_SESSION['name']; ?></span></p>
         
        <a href="logout.php"><button class="">Logout</button></a>
        <button><a href="register.php">Register User</a></button>
        <button><a href="appli_form.php">Applicant</a></button>
    </div>
</body>
</html>