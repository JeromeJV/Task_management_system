<!-- Sunod na aayusin is ung sa attendance registration at tracking -->
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
        
        <button><a href="dashboard_form.php">DASHBOARD</a></button> <!--NAKA LAGAY DTO UNG MGA REGULAR EMPLOYEE-->
        <button><a href="register.php">REGISTER USER</a></button>
        <button><a href="attendance_form.php">ATTENDANCE</a></button>
        <button><a href="appli_form.php">APPLICANT</a></button>
        <button><a href="interview_sched.php">INTERVIEW SCHEDULE</a></button>
        <a href="logout.php"><button class="">Logout</button></a>
    </div>
</body>
</html>