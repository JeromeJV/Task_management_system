<?php
include 'config/connection.php';
include 'config/reset-password.php';
include 'config/PHPMailer';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Create New Password</h2>

    <!-- Dto lahat ng error msg -->
    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <?php foreach ($errors as $error) echo htmlspecialchars($error) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="color: green;">
            Password successfully updated! You can now <a href="index.php">log in</a>.
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div>
                <input type="password" name="password" placeholder="New Password" required>
            </div>
            <br>
            <div>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            </div>
            <br>
            <button type="submit">Update Password</button>
        </form>
    <?php endif; ?>
</body>
</html>