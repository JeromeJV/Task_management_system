<?php
include 'config/connection.php';

// Ung token para sa pag reset ng pass
$token = $_GET['token'] ?? $_POST['token'] ?? null;

if (!$token) {
    die("Invalid request. Missing token.");
}

// Vinavalidate ung token dto kung expired na o hindi pa
$stmt = mysqli_prepare($conn, "SELECT id, reset_expires_at FROM users WHERE reset_token = ?");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("This link is invalid or has expired.");
}

$errors = [];
$success = false;

// Dto ung Sa new password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (strlen($newPassword) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    } else {

        $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        mysqli_stmt_bind_param($updateStmt, "si", $newPassword, $user['id']); // $newPassword na ang ginamit sa halip na $hashedPassword
        
        if (mysqli_stmt_execute($updateStmt)) {
            $success = true;
        } else {
            $errors[] = "Something went wrong updating your password.";
        }
    }
}
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