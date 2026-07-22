<?php
    $conn = mysqli_connect("localhost","root","","task_management_system");
    if(!($conn)) {
        echo "Connection not established";
    }
?>