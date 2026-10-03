<?php
include 'config/connection.php'; 
include 'config/forgot-password.php'; 
?>

<!-- HTML Form -->
<form method="POST">
    <input type="email" name="email" placeholder="Enter your email" required>
    <button type="submit">Send Reset Link</button>
</form>