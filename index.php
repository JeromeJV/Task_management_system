<?php
include'config/connection.php';
//session_start(); 
include'config/loginBE.php';
?>
 

<!DOCTYPE html>
<html lang="en">
<head
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div class="form">
        <form action="" method="post" class="p-4 border rounded bg-light style-form-container">
            <h2>Login</h2>
            
            <!-- Overall Error Message-->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="text" name="email" id="email" placeholder="Enter your Email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <?php if (!empty($email_err)): ?>
                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your Password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                <?php if (!empty($password_err)): ?>
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary btn_font w-100 mb-3" name="Submit">Login</button>
            <p class="text-center"><a href="forgot-pass.php">Forgot your password</a></p>
            <p class="text-center">Don't have an account? <a href="register.php">Register</a></p>
        </form>
    </div>
</body>
</html>