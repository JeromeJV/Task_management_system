<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kaya siya 900 kase seconds siya so bali 15 * 60 is 900
$inactivity_limit = 900;

if (isset($_SESSION['id'])) {
    if (isset($_SESSION['last_activity'])) {
        // Iti-track neto kung kelan naging active ung nasa dashboard
        $elapsed_time = time() - $_SESSION['last_activity'];

        if ($elapsed_time > $inactivity_limit) {
            // Dto na ung mag lo-logout
            session_unset();
            session_destroy();
            
            // babalit na siya sa login.php
            header("Location: index.php?error=session_expired");
            exit();
        }
    }
    
    $_SESSION['last_activity'] = time();
}
?>