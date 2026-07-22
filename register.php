<?php
include("config/connection.php");
include("config/registerBE.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
</head>
<body>
    <div class="form">
        <form action="" method="post" class="p-4 border rounded bg-light style-form-container">
            <h2>Register</h2>
            
            <!-- Error Message -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger" role="alert"><?= $msg ?></div>
            <?php endif; ?>

            <div class="form-group mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" placeholder="Enter your Name" class="form-control <?= (!empty($name_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                <?php if (!empty($name_err)): ?><div class="invalid-feedback"><?= $name_err ?></div><?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Email Address</label>
                <input type="text" name="email" placeholder="Enter your Email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                <?php if (!empty($email_err)): ?><div class="invalid-feedback"><?= $email_err ?></div><?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="HR" <?= (isset($_POST['role']) && $_POST['role'] == 'HR') ? 'selected' : '' ?>>HR</option>
                    <option value="payroll" <?= (isset($_POST['role']) && $_POST['role'] == 'payroll') ? 'selected' : '' ?>>Payroll</option>
                    <option value="super" <?= (isset($_POST['role']) && $_POST['role'] == 'super') ? 'selected' : '' ?>>Supervisor</option>
                    <option value="log" <?= (isset($_POST['role']) && $_POST['role'] == 'log') ? 'selected' : '' ?>>Logistics</option>
                    <option value="pro" <?= (isset($_POST['role']) && $_POST['role'] == 'pro') ? 'selected' : '' ?>>Production</option>
                </select>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" placeholder="Enter your Password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : '' ?>" required>
                <?php if (!empty($password_err)): ?><div class="invalid-feedback"><?= $password_err ?></div><?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="cpassword" placeholder="Confirm your Password" class="form-control <?= (!empty($cpassword_err)) ? 'is-invalid' : '' ?>" required>
                <?php if (!empty($cpassword_err)): ?><div class="invalid-feedback"><?= $cpassword_err ?></div><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3 btn_font" name="submit">Register Now</button>
            <p class="text-center">Already have an account? <a href="index.php">Login now</a></p>
        </form>
    </div>
</body> 
</html>

