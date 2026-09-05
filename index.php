<?php
include 'config/connection.php';
//session_start(); 
include 'config/loginBE.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskTrack - Login</title>
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Separate CSS File -->
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

    <div class="main-container">
        <!-- Header Navigation -->
        <header>
            <div class="brand"> 
                    <img class="gear-brand" src="img/task_icon.png" alt="Task Icon">
                <span>TaskTrack</span>
            </div>
            <nav>
                <a href="#" class="active">Login</a>
                <a href="TaskTrackWeb.php">Website</a>
                <a href="register.php">Register</a>
            </nav>
        </header>

        <!-- Main Content Split -->
        <div class="content">
            <!-- Left Side: Form -->
            <div class="login-section">
                <h1>LOGIN</h1>

                <!-- Dynamic PHP Error Message -->
                <?php if (!empty($msg)): ?>
                    <div class="alert-danger">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" 
                               placeholder="Example@gmail.com" 
                               class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <?php if (!empty($email_err)): ?>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" 
                               placeholder="Enter Your Password" 
                               class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                        <?php if (!empty($password_err)): ?>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="forgot-link">
                        Forgot your? <a href="forgot-pass.php">Password</a>
                    </div>

                    <button type="submit" name="Submit" class="btn-submit">SUBMIT</button>
                </form>
            </div>

            <!-- Right Side: Info Text -->
            <div class="welcome-section">
                <h2>Welcome to Tasktrack</h2>
                <p>
                    TaskTrack is a system that helps companies to track their workflow 
                    in their company. The goal is to make teams more productive by tracking 
                    their work.
                </p>
            </div>
        </div>

        <!-- Gear Logo Watermark -->
        <div class="gear-watermark">
            <img src="img/task_icon.png" alt="Task Icon">
        </div>
    </div>

</body>
</html>