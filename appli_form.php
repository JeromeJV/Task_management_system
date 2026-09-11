<?php
    session_start();
    include('config/connection.php');
    include('config/autoLog.php');
    
    // Kunin ang hiwalay na backend file
    include('config/application_API.php'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant List</title>
</head>
<body>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
     <button><a href="HR.php">Back</a></button>
    <div style="overflow-x: auto; max-width: 300%;">
        <?php if ($count > 0): ?>
            <table border="0" cellpadding="5" cellspacing="0">
                <thead>
                    <tr>
                        <th>Applicant ID</th>
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Email address</th>
                        <th>Address</th>
                        <th>Position Applied</th>
                        <th>Previous Company</th>
                        <th>Previous Position</th>
                        <th>Date Of Start</th>
                        <th>Date Of End</th>
                        <th>Highest Educational Attainment</th>
                        <th>Start Date</th>
                        <th>Resume</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <form action="" method="post">
                                <input type="hidden" name="idno" value="<?= htmlspecialchars($row['applicant_id'] ?? ''); ?>">
                                <td><?= htmlspecialchars($row['applicant_id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']); ?></td>
                                <td><?= htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['email'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['house_number'] . ' ' . $row['street'] . ' ' . $row['barangay']. ' ' . $row['city'] . ' ' . $row['province']); ?></td>
                                <td><?= htmlspecialchars($row['position_applied'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['company_name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['position'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['date_of_start'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['date_of_end'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['education'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['start_date'] ?? ''); ?></td>
                                <td style="text-align: center;">
                                <?php if (!empty($row['resume_path'])): ?>
                                    <a href="view_resume.php?file=<?= urlencode($row['resume_path']); ?>" target="_blank">
                                        View Resume
                                    </a>
                                <?php else: ?>
                                    <span style="color: #888;">No File</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="submit" name="del" value="Delete" onclick="return confirm('Are you sure you want to delete it?');">
                            </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
    </div>
    <?php else: ?>
        <p>No records found.</p>
    <?php endif; ?>
</body>
</html>
