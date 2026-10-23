<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/account_access.php';

if (!isset($_SESSION['email']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'payroll') {
    header('Location: index.php');
    exit();
}
requireActiveAccount($conn, true);
if (empty($_SESSION['payroll_csrf'])) {
    $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));
}

$payrollId = filter_input(INPUT_GET, 'payroll_id', FILTER_VALIDATE_INT) ?: 0;
$statement = $conn->prepare(
    'SELECT p.*, e.username
     FROM payroll p
     INNER JOIN employee e ON e.employee_id = p.employee_id
     WHERE p.payroll_id = ?
     LIMIT 1'
);
if (!$statement) {
    throw new RuntimeException('Unable to prepare payroll correction lookup: ' . $conn->error);
}
$statement->bind_param('i', $payrollId);
if (!$statement->execute()) {
    throw new RuntimeException('Unable to retrieve payroll correction record: ' . $statement->error);
}
$record = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$record || $record['status'] !== 'Paid') {
    http_response_code($record ? 409 : 404);
    exit($record ? 'Only payroll records marked Paid can be corrected here.' : 'Payroll record not found.');
}

$amountFields = [
    'regular_pay' => 'Regular pay',
    'paid_leaves' => 'Paid leaves',
    'reimbursement' => 'Reimbursement',
    'late_deduction' => 'Late deduction',
    'sss' => 'SSS',
    'pagibig' => 'Pag-IBIG',
    'philhealth' => 'PhilHealth',
    'calamity_loan' => 'Calamity loan',
    'hmo' => 'HMO'
];
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Correct Paid Payroll | TaskTrack</title>
    <link rel="stylesheet" href="css/role-form-controls.css">
    <style>
        body { margin: 0; padding: 24px; background: #f4f7fb; color: #172235; font: 15px Arial, sans-serif; }
        main { max-width: 820px; margin: auto; }
        .card { background: white; border: 1px solid #e1e7ef; border-radius: 12px; padding: 22px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        label { display: grid; gap: 6px; font-weight: 600; }
        input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
        .button { display: inline-block; padding: 10px 14px; border: 0; border-radius: 8px; background: #155eef; color: white; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #e8eef7; color: #203047; }
        .notice { padding: 12px; margin: 14px 0; border-radius: 8px; background: #fff8e6; color: #754f00; }
        .error { background: #fff0f0; color: #a61b1b; }
        .actions { display: flex; gap: 10px; margin-top: 18px; }
        @media (max-width: 620px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/config/mobile_refresh.php'; ?>
<main>
    <h1>Correct Paid Payroll</h1>
    <p>Employee: <strong><?= $escape($record['username']); ?></strong> · Payroll #<?= (int) $record['payroll_id']; ?></p>
    <div class="notice">Corrections keep this record marked Paid. Each change and its reason are saved in audit history; this status is not proof of an actual payout.</div>
    <div id="message" class="notice error" role="alert" hidden></div>
    <form class="card" id="correctionForm">
        <input type="hidden" name="action" value="edit_paid">
        <input type="hidden" name="payroll_id" value="<?= (int) $record['payroll_id']; ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['payroll_csrf']); ?>">
        <div class="grid">
            <?php foreach ($amountFields as $field => $label): ?>
                <label for="<?= $field; ?>"><?= $escape($label); ?> (₱)
                    <input id="<?= $field; ?>" name="<?= $field; ?>" type="number" min="0" step="0.01" required value="<?= $escape(number_format((float) $record[$field], 2, '.', '')); ?>">
                </label>
            <?php endforeach; ?>
            <label for="reason">Correction reason
                <input id="reason" name="reason" type="text" maxlength="500" required>
            </label>
        </div>
        <div class="actions">
            <button class="button" type="submit">Save correction</button>
            <a class="button secondary" href="payroll.php?tab=employees">Cancel</a>
        </div>
    </form>
</main>
<script>
document.getElementById('correctionForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const message = document.getElementById('message');
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        const response = await fetch('config/Payroll_API.php', {
            method: 'POST',
            body: new FormData(form)
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'Unable to save correction.');
        }
        window.location.href = 'payroll.php?tab=employees&corrected=1';
    } catch (error) {
        message.textContent = error.message || 'Unable to save correction.';
        message.hidden = false;
    } finally {
        submit.disabled = false;
    }
});
</script>
</body>
</html>
