<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = (string) ($_SESSION['role'] ?? '');
if ($role === 'admin') {
    header('Location: admin.php?section=accounts');
} elseif ($role === 'HR') {
    header('Location: HR.php');
} else {
    header('Location: index.php');
}
exit();
