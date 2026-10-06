<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "task_management_system"; // Siguraduhing tama ang eksaktong pangalan ng database mo sa phpMyAdmin

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>