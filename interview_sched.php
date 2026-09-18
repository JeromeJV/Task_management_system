<?php
        session_start();
        include('config/connection.php');
        include('config/autoLog.php');
        
        // Kinukuha ang $selected_status, $records, $count, at $message mula sa backend API
        include('config/application_API.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Management</title>
</head>
<body>
        <?php if (isset($message) && !empty($message)): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <button><a href="HR.php">Back</a></button>
        <br><br>

        <!-- DROPDOWN FILTER FORM -->
        <div style="margin-bottom: 15px;">
            <form method="GET" action="">
                <label for="interview_status"><strong>Filter by Interview Stage:</strong></label>
                <select name="interview_status" id="interview_status" onchange="this.form.submit()" style="padding: 5px 10px;">
                    <option value="all" <?= (isset($selected_status) && $selected_status == 'all') ? 'selected' : ''; ?>>All Statuses</option>
                    
                    <option value="Initial_Interview" <?= (isset($selected_status) && ($selected_status == 'Initial_Interview' || $selected_status == 'Initial Interview')) ? 'selected' : ''; ?>>Initial Interview</option>
                    <option value="Technical_Interview" <?= (isset($selected_status) && ($selected_status == 'Technical_Interview' || $selected_status == 'Technical Interview')) ? 'selected' : ''; ?>>Technical Interview</option>
                    <option value="Final_Interview" <?= (isset($selected_status) && ($selected_status == 'Final_Interview' || $selected_status == 'Final Interview')) ? 'selected' : ''; ?>>Final Interview</option>
                </select>
            </form>
        </div>

        <div style="overflow-x: auto; max-width: 100%;">
            <?php if (isset($count) && $count > 0): ?>
                <table border="0" cellpadding="2" cellspacing="1">
                    <thead>
                        <tr>
                            <th>Applicant ID</th>
                            <th>Name</th>
                            <th>Contact Number</th>
                            <th>Email address</th>
                            <th>Address</th>
                            <th>Resume</th>
                            <th>Interview Status</th>
                            <th>Interview Type</th>
                            <th>Interview Mode</th>
                            <th>Interview Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['applicant_id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars(($row['firstname'] ?? '') . ' ' . ($row['middlename'] ?? '') . ' ' . ($row['lastname'] ?? '')); ?></td>
                                <td><?= htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['email'] ?? ''); ?></td>
                                <td><?= htmlspecialchars(($row['house_number'] ?? '') . ' ' . ($row['street'] ?? '') . ' ' . ($row['barangay'] ?? ''). ' ' . ($row['city'] ?? '') . ' ' . ($row['province'] ?? '')); ?></td>
                                
                                <td style="text-align: center;">
                                    <?php if (!empty($row['resume_path'])): ?>
                                        <a href="view_resume.php?file=<?= urlencode($row['resume_path']); ?>" target="_blank">View Resume</a>
                                    <?php else: ?>
                                        <span style="color: #888;">No File</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($row['status'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['interview_type'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['interview_mode'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['interview_date'] ?? 'N/A'); ?></td>

                                <td>
                                    <form action="" method="post" style="margin:0;">
                                        <input type="hidden" name="idno" value="<?= htmlspecialchars($row['applicant_id'] ?? ''); ?>">
                                        <input type="submit" name="del" value="Delete" onclick="return confirm('Are you sure you want to delete it?');">
                                    </form>
                                </td>
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