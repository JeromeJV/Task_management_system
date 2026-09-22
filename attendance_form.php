<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'config/connection.php';

// Fetch all employees for dropdown list
$employees = $conn->query("SELECT employee_id, username, position, user_id FROM employee");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Real-Time Auto Track Attendance</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; display: flex; justify-content: center; }
        .clock-card { width: 420px; padding: 25px; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
        #digital-clock { font-size: 32px; font-weight: bold; color: #2c3e50; margin: 15px 0; background: #f8f9fa; padding: 10px; border-radius: 6px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; }
        .btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        button { padding: 12px; border: none; border-radius: 6px; color: white; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-in { background-color: #28a745; }
        .btn-out { background-color: #dc3545; }
        .btn-in2 { background-color: #17a2b8; }
        .btn-out2 { background-color: #ffc107; color: #333; }
        button:hover { opacity: 0.9; }
        #alert-msg { margin-top: 15px; font-weight: bold; }
    </style>
</head>
<body>

<div class="clock-card">
    <h2>Real-Time Attendance</h2>
    
    <!-- Real Time Live Clock -->
    <div id="digital-clock">00:00:00 AM</div>
    <div id="current-date" style="color: #666; font-size: 14px; margin-bottom: 20px;"></div>

    <div class="form-group">
        <label for="employee_id">Choose Employee:</label>
        <select id="employee_id" required>
            <option value="">-- Select employee --</option>
            <?php
            if ($employees && $employees->num_rows > 0) {
                while ($emp = $employees->fetch_assoc()) {
                    $userLabel = !empty($emp['user_id']) ? ' - User ID: ' . htmlspecialchars($emp['user_id']) : '';
                    echo "<option value='".$emp['employee_id']."'>" . htmlspecialchars($emp['username']) . " (" . htmlspecialchars($emp['position']) . ")" . $userLabel . "</option>";
                }
            }
            ?>
        </select>
    </div>

    <!-- Quick Buttons -->
    <div class="btn-grid">
        <button class="btn-in" onclick="recordAttendance('time_in')">Time In 1</button>
        <button class="btn-out" onclick="recordAttendance('time_out')">Time Out 1</button>
        <button class="btn-in2" onclick="recordAttendance('time_in_2')">Time In 2</button>
        <button class="btn-out2" onclick="recordAttendance('time_out_2')">Time Out 2</button>
    </div>

    <div id="alert-msg"></div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('digital-clock').innerText = now.toLocaleTimeString('en-US');
        document.getElementById('current-date').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);
    updateClock();

    function recordAttendance(actionType) {
        const employeeId = document.getElementById('employee_id').value;
        const alertMsg = document.getElementById('alert-msg');

        if (!employeeId) {
            alertMsg.style.color = "red";
            alertMsg.innerText = "Please choose an employee!";
            return;
        }

        const formData = new FormData();
        formData.append('employee_id', employeeId);
        formData.append('action_type', actionType);

        fetch('config/attendance_API.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Server Raw Response Error:", text);
                throw new Error("Invalid JSON response from server");
            }
        })
        .then(data => {
            if (data.status === 'success') {
                alertMsg.style.color = "green";
                alertMsg.innerText = data.message;
            } else {
                alertMsg.style.color = "red";
                alertMsg.innerText = data.message;
            }
        })
        .catch(error => {
            console.error(error);
            alertMsg.style.color = "red";
            alertMsg.innerText = "System Error! (Check Browser Console F12)";
        });
    }
</script>
<button><a href="index.php">BACK</a></button>
</body>
</html>