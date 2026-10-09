<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Real-Time Auto Track Attendance</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; display: flex; flex-direction: column; align-items: center; background-color: #f8f9fa; }
        .clock-card { width: 450px; padding: 25px; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; background: white; }
        #digital-clock { font-size: 32px; font-weight: bold; color: #2c3e50; margin: 15px 0; background: #eef2f7; padding: 10px; border-radius: 6px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; }
        .btn-grid { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 15px; }
        button { padding: 12px; border: none; border-radius: 6px; color: white; font-weight: bold; cursor: pointer; transition: 0.2s; }
        button:disabled { cursor: not-allowed; opacity: 0.55; }
        .btn-in { background-color: #28a745; }
        .btn-out { background-color: #dc3545; }
        .btn-in2 { background-color: #17a2b8; }
        .btn-out2 { background-color: #ffc107; color: #333; }
        button:hover { opacity: 0.9; }
        #alert-msg { margin-top: 15px; font-weight: bold; }
        .btn-back { margin-top: 20px; padding: 8px 16px; background-color: #6c757d; border-radius: 4px; text-decoration: none; color: white; display: inline-block; }
    </style>
</head>
<body>

<div class="clock-card">
    <h2>Real-Time Attendance</h2>
    
    <div id="digital-clock">00:00:00 AM</div>
    <div id="current-date" style="color: #666; font-size: 14px; margin-bottom: 20px;"></div>

    <div class="form-group">
        <label for="employee_id">Employee ID:</label>
        <input type="text" name="employee_id" id="employee_id" inputmode="numeric" pattern="[0-9]+" autocomplete="off" placeholder="Enter Employee ID" required>
        <div id="employee-name" aria-live="polite" style="margin-top: 8px; min-height: 18px; color: #666;"></div>
    </div>

    <div class="btn-grid">
        <button type="button" id="submit-attendance" class="btn-in" onclick="recordAttendance()" disabled>Submit</button>
    </div>

    <div id="alert-msg"></div>
</div>

<a href="index.php" class="btn-back">&larr; BACK TO INDEX</a>

<script>
    const employeeIdInput = document.getElementById('employee_id');
    const employeeName = document.getElementById('employee-name');
    const alertMsg = document.getElementById('alert-msg');
    const submitButton = document.getElementById('submit-attendance');
    const actionLabels = {
        time_in: 'Time In 1',
        time_out: 'Time Out 1',
        time_in_2: 'Time In 2',
        time_out_2: 'Time Out 2'
    };
    let lookupTimer;
    let lookupRequest = 0;
    let resolvedEmployeeId = '';
    let currentEmployeeName = '';
    let nextAction = null;

    function renderNextAction(action) {
        nextAction = action;
        if (action && actionLabels[action]) {
            submitButton.innerText = 'Submit';
            submitButton.disabled = false;
            employeeName.innerText = `Name: ${currentEmployeeName} | Next: ${actionLabels[action]}`;
        } else {
            submitButton.innerText = 'Submit';
            submitButton.disabled = true;
            employeeName.innerText = `Name: ${currentEmployeeName} | Attendance is complete for today.`;
        }
    }

    employeeIdInput.addEventListener('input', () => {
        clearTimeout(lookupTimer);
        lookupRequest++;
        resolvedEmployeeId = '';
        currentEmployeeName = '';
        nextAction = null;
        submitButton.innerText = 'Submit';
        submitButton.disabled = true;
        employeeName.innerText = '';
        alertMsg.innerText = '';

        const employeeId = employeeIdInput.value.trim();
        if (!/^[0-9]+$/.test(employeeId) || Number(employeeId) < 1) {
            employeeName.innerText = employeeId ? 'Enter a valid Employee ID.' : '';
            return;
        }

        employeeName.innerText = 'Looking up employee...';
        lookupTimer = setTimeout(() => lookupEmployee(employeeId), 300);
    });

    async function lookupEmployee(employeeId) {
        const requestId = ++lookupRequest;
        const formData = new FormData();
        formData.append('employee_id', employeeId);
        formData.append('action_type', 'lookup');

        try {
            const response = await fetch('config/attendance_API.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (requestId !== lookupRequest || employeeIdInput.value.trim() !== employeeId) {
                return;
            }

            if (data.status === 'success') {
                resolvedEmployeeId = employeeId;
                currentEmployeeName = data.employee_name;
                renderNextAction(data.next_action);
            } else {
                employeeName.innerText = data.message;
            }
        } catch (error) {
            if (requestId !== lookupRequest) {
                return;
            }
            console.error(error);
            employeeName.innerText = 'Could not retrieve the employee name. Please try again.';
        }
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('digital-clock').innerText = now.toLocaleTimeString('en-US');
        document.getElementById('current-date').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);
    updateClock();

    async function recordAttendance() {
        const employeeId = employeeIdInput.value.trim();
        if (!employeeId || employeeId !== resolvedEmployeeId || !nextAction) {
            alertMsg.style.color = "red";
            alertMsg.innerText = "Enter and verify an Employee ID, or this employee's attendance is already complete.";
            return;
        }

        const formData = new FormData();
        formData.append('employee_id', employeeId);
        formData.append('action_type', 'submit');
        submitButton.disabled = true;

        try {
            const response = await fetch('config/attendance_API.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (employeeIdInput.value.trim() !== employeeId) {
                return;
            }

            if (data.status === 'success') {
                alertMsg.style.color = "green";
                alertMsg.innerText = data.message;
                renderNextAction(data.next_action);
            } else {
                alertMsg.style.color = "red";
                alertMsg.innerText = data.message;
                if (data.next_action !== undefined) {
                    renderNextAction(data.next_action);
                }
            }
        } catch (error) {
            console.error(error);
            alertMsg.style.color = "red";
            alertMsg.innerText = "System error. Please check the browser console or try again.";
        } finally {
            if (employeeIdInput.value.trim() === employeeId && resolvedEmployeeId === employeeId && nextAction) {
                submitButton.disabled = false;
            }
        }
    }
</script>

</body>
</html>