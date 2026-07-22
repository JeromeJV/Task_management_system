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
        <input type="text" name="route"><br><br>

        <label>Peaces:</label>
        <input type="text" name="peaces"><br><br>

        <label>Stock:</label>
        <input type="text" name="stock"><br><br>

        <input type="submit" name="submit" value="Submit">
        <input type="reset" value="Reset">
    </form>
    
</body>
</html>