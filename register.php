<?php
include("config/connection.php");
include("config/registerBE.php");

// 1. Kunin lang ang mga empleyado na WALA PANG nililikhang account sa `users` table
$empQuery = mysqli_query($conn, "
    SELECT e.employee_id, e.username, e.email, e.position 
    FROM employee e 
    LEFT JOIN users u ON e.email = u.email 
    WHERE u.id IS NULL 
    ORDER BY e.username ASC
");

// 2. Kunin ang lahat ng may aktibong system account mula sa `users` table para sa Table
$usersQuery = mysqli_query($conn, "SELECT * FROM `users` ORDER BY id ASC");
$records = [];$count = 0;

if ($usersQuery) {
    $count = mysqli_num_rows($usersQuery);
    while ($userRow = mysqli_fetch_assoc($usersQuery)) {
        $records[] =$userRow;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register System Account</title>
</head>
<body>
    <button type="button" onclick="window.location.href='HR.php'">Back</button>
    
    <div class="form">
        <form action="register.php" method="post" class="p-4 border rounded bg-light style-form-container">
            <h2>Register System Account</h2>
            
            <!-- Error / Success Message -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-info" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Searchable Employee Input using Datalist -->
            <div class="form-group mb-3">
                <label class="form-label">Search / Select Employee</label>
                
                <!-- Visible Search Bar -->
                <input 
                    type="text" 
                    id="employeeSearch" 
                    list="employeeList" 
                    class="form-control <?= (!empty($name_err)) ? 'is-invalid' : '' ?>" 
                    placeholder="Type employee name..." 
                    oninput="handleEmployeeSelect()" 
                    autocomplete="off" 
                    required
                >

                <!-- Hidden Input to send actual employee_id to Backend -->
                <input type="hidden" name="employee_id" id="employee_id">

                <!-- Dropdown / Datalist Suggestions -->
                <datalist id="employeeList">
                    <?php 
                    if ($empQuery && mysqli_num_rows($empQuery) > 0) {
                        while ($emp = mysqli_fetch_assoc($empQuery)) {$displayText = $emp['username'] . " (" . $emp['position'] . ")";
                            echo "<option value='" . htmlspecialchars($displayText) . "' data-id='" . htmlspecialchars($emp['employee_id']) . "' data-email='" . htmlspecialchars($emp['email']) . "'></option>";
                        }
                    }
                    ?>
                </datalist>

                <?php if (!empty($name_err)): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($name_err) ?></div>
                <?php endif; ?>
            </div>

            <!-- Email Address (Auto-filled) -->
            <div class="form-group mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="emailInput" placeholder="Employee Email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" readonly required>
                <?php if (!empty($email_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($email_err) ?></div><?php endif; ?>
            </div>

            <!-- System Role Selection -->
            <div class="form-group mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="HR" <?= (isset($_POST['role']) &&$_POST['role'] == 'HR') ? 'selected' : '' ?>>HR</option>
                    <option value="payroll" <?= (isset($_POST['role']) &&$_POST['role'] == 'payroll') ? 'selected' : '' ?>>Payroll</option>
                    <option value="super" <?= (isset($_POST['role']) &&$_POST['role'] == 'super') ? 'selected' : '' ?>>Supervisor</option>
                    <option value="log" <?= (isset($_POST['role']) &&$_POST['role'] == 'log') ? 'selected' : '' ?>>Logistics</option>
                    <option value="pro" <?= (isset($_POST['role']) &&$_POST['role'] == 'pro') ? 'selected' : '' ?>>Production</option>
                    <option value="employee" <?= (isset($_POST['role']) &&$_POST['role'] == 'employee') ? 'selected' : '' ?>>Employee</option>
                </select>
            </div>

            <!-- Password Fields -->
            <div class="form-group mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" placeholder="Enter Password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : '' ?>" required>
                <?php if (!empty($password_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($password_err) ?></div><?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="cpassword" placeholder="Confirm Password" class="form-control <?= (!empty($cpassword_err)) ? 'is-invalid' : '' ?>" required>
                <?php if (!empty($cpassword_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($cpassword_err) ?></div><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3 btn_font" name="register_user">Register Now</button>
            <p class="text-center">Already have an account? <a href="index.php">Login now</a></p>
        </form>
    </div>

    <br><hr><br>

    <h3>Registered System Accounts</h3>

    <!-- System Users Table -->
    <?php if (isset($count) &&$count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr> 
            </thead>
            <tbody>
                <?php foreach ($records as$row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']); ?></td>
                        <td><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td><?= htmlspecialchars($row['role']); ?></td>
                        <td>
                            <form action="register.php" method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id']); ?>">
                                <input type="submit" name="delete_user" value="Delete" onclick="return confirm('Are you sure you want to delete this user account?');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No system accounts found.</p>
    <?php endif; ?>

    <script>
        function handleEmployeeSelect() {
            const inputVal = document.getElementById('employeeSearch').value;
            const options = document.querySelectorAll('#employeeList option');
            const hiddenIdInput = document.getElementById('employee_id');
            const emailInput = document.getElementById('emailInput');

            let matched = false;

            options.forEach(option => {
                if (option.value === inputVal) {
                    hiddenIdInput.value = option.getAttribute('data-id');
                    emailInput.value = option.getAttribute('data-email');
                    matched = true;
                }
            });

            // Kapag binura o pinalitan ng user ang text na walang match sa listahan
            if (!matched) {
                hiddenIdInput.value = '';
                emailInput.value = '';
            }
        }
    </script>
</body> 
</html>