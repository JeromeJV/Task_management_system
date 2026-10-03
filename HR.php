<?php
    session_start();
    include('config/connection.php');
    include('config/autoLog.php');
    include('config/employee_API.php'); // Dito dapat nanggagaling ang $records at $count

    // Authorization: Tiyaking naka-login at HR ang role
    if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
        header("Location: index.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management Dashboard</title>
</head>
<body>
    <div class="user-page">
        <h2>Welcome to human resource page!</h2>
        <p>Human Resource : <span><?php echo htmlspecialchars($_SESSION['name'] ?? 'HR'); ?></span></p>
        
        <!-- Navigation Buttons -->
        <button><a href="register.php">REGISTER USER</a></button>
        <button><a href="atten.php">ATTENDANCE</a></button>
        <button><a href="appli_form.php">APPLICANT</a></button>
        <button><a href="interview_sched.php">INTERVIEW SCHEDULE</a></button>
        <a href="logout.php"><button type="button">Logout</button></a>

        <!-- Display Session Messages (Delete/Update Notifications) -->
        <?php if (isset($_SESSION['msg'])): ?>
            <p style="color: green; font-weight: bold;"><?= htmlspecialchars($_SESSION['msg']); ?></p>
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['err'])): ?>
            <p style="color: red; font-weight: bold;"><?= htmlspecialchars($_SESSION['err']); ?></p>
            <?php unset($_SESSION['err']); ?>
        <?php endif; ?>

        <!-- Employee Table -->
        <?php if (isset($count) && $count > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Position</th>
                    <th>Contact Number</th>
                    <th>Address</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <form action="HR.php" method="post">
                            <input type="hidden" name="idno" value="<?= htmlspecialchars($row['employee_id']); ?>">
                            <td><?= htmlspecialchars($row['employee_id']); ?></td>
                            <td><?= htmlspecialchars($row['username']); ?></td>
                            <td><?= htmlspecialchars($row['position']); ?></td>
                            <td><?= htmlspecialchars($row['contact_number']); ?></td>
                            <td><?= htmlspecialchars($row['address']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td>
                                <input type="submit" name="del" value="Delete" onclick="return confirm('Are you sure you want to delete this record?');">
                                <input type="submit" name="upd" value="Update">
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No records found.</p>
        <?php endif; ?>
    </div>
</body>
</html>