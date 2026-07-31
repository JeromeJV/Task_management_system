<?php
    include ('config/connection.php');

    session_start();
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
        <h2>Welcome to production page!</h2>
        <p>Production: <span><?php echo $_SESSION['email']; ?></span></p>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>
</body>
</html>