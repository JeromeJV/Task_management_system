<?php
require_once __DIR__ . '/config/reset-password.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/password-visibility.css?v=<?= filemtime(__DIR__ . '/css/password-visibility.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/config/theme_toggle.php'; ?>
    <?php include __DIR__ . '/config/mobile_refresh.php'; ?>
    <h2>Create New Password</h2>

    <!-- Dto lahat ng error msg -->
    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <?php foreach ($errors as $error) echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="color: green;">
            Password successfully updated! You can now <a href="index.php">log in</a>.
        </div>
    <?php elseif (!$tokenIsValid): ?>
        <p>Please request a new password reset link.</p>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars((string) $token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            
            <div>
                <div class="password-input-wrap">
                    <input type="password" name="password" id="newPassword" placeholder="New Password" required>
                    <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="newPassword" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
            </div>
            <br>
            <div>
                <div class="password-input-wrap">
                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm New Password" required>
                    <button class="password-visibility-toggle" type="button" data-password-toggle aria-controls="confirmPassword" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
            </div>
            <br>
            <button type="submit">Update Password</button>
        </form>
    <?php endif; ?>
    <script src="js/password-visibility.js?v=<?= filemtime(__DIR__ . '/js/password-visibility.js'); ?>"></script>
</body>
</html>