<?php
include 'config/connection.php'; 
include 'config/forgot-password.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="bg-white p-3 d-flex align-items-center justify-content-center min-vh-100 m-0">
        <div class="container" style="max-width: 420px;"> 
            <form action="" method="post" class="p-4 border rounded bg-white shadow-sm style-form-container"> 
                
                <h2 class="text-center mb-4">Forgot Password</h2>
                
                <?php if (!empty($msg)): ?>
                    <div class="alert alert-danger" role="alert"><?= $msg ?></div>
                <?php endif; ?>

                <div class="form-group mb-4">
                    <label for="email" class="form-label">Please enter your registered email to send the link for new password</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                </div>

                <button type="submit" class="btn btn-primary btn_font w-100 mb-3" name="submit">Send Reset Link</button>
                
                <p class="text-center mb-1"><a href="index.php">Remembered your password? Login</a></p>
                <p class="text-center mb-0">Don't have an account? <a href="register.php">Register</a></p>
                
            </form>
        </div>
    </div>
</body>
</html>