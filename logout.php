<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (
        empty($_SESSION['logout_csrf'])
        || !hash_equals((string) $_SESSION['logout_csrf'], $postedToken)
    ) {
        http_response_code(403);
        exit('Your session expired. Return to the previous page and try again.');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookieParameters['path'],
            $cookieParameters['domain'],
            $cookieParameters['secure'],
            $cookieParameters['httponly']
        );
    }
    session_destroy();

    header('Location: index.php');
    exit();
}

$_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
$csrfToken = htmlspecialchars($_SESSION['logout_csrf'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm logout - TaskTrack</title>
    <style>
        * { box-sizing: border-box; }
        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            place-items: center;
            background: rgba(20, 83, 45, .28);
            color: #17251d;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .confirm-card {
            width: min(100%, 420px);
            padding: 32px;
            border: 1px solid #dce8e0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 36px rgba(23, 37, 29, .1);
            text-align: center;
        }
        h1 { margin: 0 0 10px; font-size: 23px; }
        p { margin: 0 0 26px; color: #5b6b60; line-height: 1.5; }
        .actions { display: flex; justify-content: center; gap: 12px; }
        button, .cancel-link {
            min-width: 112px;
            padding: 11px 18px;
            border: 0;
            border-radius: 8px;
            font: inherit;
            font-weight: 650;
            cursor: pointer;
            text-decoration: none;
        }
        .cancel-link { background: #edf2ee; color: #26382c; }
        .logout-button { background: #b42318; color: #fff; }
        .cancel-link:hover { background: #e1e9e3; }
        .logout-button:hover { background: #912018; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/config/mobile_refresh.php'; ?>
    <main class="confirm-card">
        <h1>Are you sure you want to log out?</h1>
        <p>You will need to sign in again to access your account.</p>
        <div class="actions">
            <a class="cancel-link" href="javascript:history.back()">Cancel</a>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <button class="logout-button" type="submit">Log out</button>
            </form>
        </div>
    </main>
</body>
</html>
