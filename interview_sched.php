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
    <title>Applicant Management - Interview Schedule</title>
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
            <table border="0" cellpadding="5" cellspacing="0">
                <thead>
                    <tr>
                        <th>Applicant ID</th>
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Email address</th>
                        <th>Address</th>
                        <th>Resume</th>
                        <th>Interview</th>
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
                            <td><?= htmlspecialchars(($row['house_number'] ?? '') . ' ' . ($row['street'] ?? '') . ' ' . ($row['barangay'] ?? '') . ' ' . ($row['city'] ?? '') . ' ' . ($row['province'] ?? '')); ?></td>
                            
                            <td style="text-align: center;">
                                <?php if (!empty($row['resume_path'])): ?>
                                    <a href="view_resume.php?file=<?= urlencode($row['resume_path']); ?>" target="_blank">View Resume</a>
                                <?php else: ?>
                                    <span style="color: #888;">No File</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Ipasa ang ID, Pangalan, at Email gamit ang JS function -->
                                <button type="button" onclick="openModal('<?= $row['applicant_id']; ?>', '<?= htmlspecialchars(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')); ?>', '<?= htmlspecialchars($row['email'] ?? ''); ?>')">
                                    Set Schedule
                                </button>
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

    <!-- ================= ISANG POP-UP MODAL SA LABAS NG LOOP ================= -->
    <div id="inputModal" class="modal">
        <div class="modal-content">
            <h3>Set Interview Schedule</h3>
            
            <!-- BINAGO: action="" para mag-submit sa mismong interview_sched.php -->
            <form action="" method="POST">
                <!-- Hidden Input para maipasa ang Applicant ID sa PHP -->
                <input type="hidden" name="applicant_id" id="modal_applicant_id">

                <label>Interview Type:</label><br>
                <select name="interview_type" required>
                    <option value="">--Type Of Interview--</option>
                    <option value="Initial Interview" <?= (isset($_POST['interview_type']) && $_POST['interview_type'] == 'Initial Interview') ? 'selected' : '' ?>>Initial Interview</option>
                    <option value="Technical Interview" <?= (isset($_POST['interview_type']) && $_POST['interview_type'] == 'Technical Interview') ? 'selected' : '' ?>>Technical Interview</option>
                    <option value="Final Interview" <?= (isset($_POST['interview_type']) && $_POST['interview_type'] == 'Final Interview') ? 'selected' : '' ?>>Final Interview</option>
                    <option value="hired" <?= (isset($_POST['interview_type']) && $_POST['interview_type'] == 'hired') ? 'selected' : '' ?>>Hired</option>
                </select>
                
                <br><br>

                <label>Interview Mode:</label><br>
                <select name="interview_mode" required>
                    <option value="Online" <?= (isset($_POST['interview_mode']) && $_POST['interview_mode'] == 'Online') ? 'selected' : '' ?>>Online (Google Meet)</option>
                    <option value="On-site" <?= (isset($_POST['interview_mode']) && $_POST['interview_mode'] == 'On-site') ? 'selected' : '' ?>>Face-to-Face (Office)</option>
                </select>
                
                <br><br>

                <label>Update Status:</label><br>
                <select name="status" required>
                    <option value="Scheduled" <?= (isset($_POST['status']) && $_POST['status'] == 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
                    <option value="Pending" <?= (isset($_POST['status']) && $_POST['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="Passed" <?= (isset($_POST['status']) && $_POST['status'] == 'Passed') ? 'selected' : '' ?>>Passed</option>
                    <option value="Failed" <?= (isset($_POST['status']) && $_POST['status'] == 'Failed') ? 'selected' : '' ?>>Failed</option>
                </select>
                
                <br><br>

                <label>Name:</label><br>
                <input type="text" name="username" id="modal_username" readonly><br><br>

                <label>Email:</label><br>
                <input type="email" name="email" id="modal_email" readonly><br><br>

                <label>Interview Date & Time:</label><br>
                <input type="datetime-local" name="interview_date" required><br><br>
                
                <button type="submit" name="save_interview">I-save Schedule</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- CSS Style ng Modal -->
    <style>
    .modal {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
    }
    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        width: 320px;
        border-radius: 8px;
        text-align: center;
    }
    </style>

    <!-- JavaScript sa Labas ng Loop -->
    <script>
    function openModal(id, name, email) {
        document.getElementById("modal_applicant_id").value = id;
        document.getElementById("modal_username").value = name;
        document.getElementById("modal_email").value = email;
        
        document.getElementById("inputModal").style.display = "block";
    }

    function closeModal() {
        document.getElementById("inputModal").style.display = "none";
    }
    </script>
</body>
</html>