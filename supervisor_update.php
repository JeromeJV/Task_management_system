<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include'config/connection.php';
include'config/Supervisor_API.php';


?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Status</title>
</head>
<body>
    <?php echo $status_message; ?>

</body>
</html>