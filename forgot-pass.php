<?php
include 'config/connection.php';

$resetMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    include 'config/forgot-password.php';
    $resetMessage = trim(ob_get_clean());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | TaskTrack</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .forgot-page {
            display: flex;
            min-height: 100vh;
            min-height: 100svh;
            flex-direction: column;
            color: #fff;
            background:
                linear-gradient(135deg, rgba(38, 70, 75, 0.9), rgba(20, 35, 40, 0.82)),
                url('https://i.pinimg.com/1200x/fc/02/64/fc026433a20db53bc4447d4e41f8f830.jpg') center / cover no-repeat;
        }
        .forgot-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            width: 100%;
            padding: 22px clamp(20px, 5.5vw, 80px);
        }
        .forgot-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-decoration: none;
            text-transform: uppercase;
        }
        .forgot-brand img {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            object-fit: cover;
        }
        .forgot-header-link {
            color: #e2e8f0;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
        }
        .forgot-header-link:hover {
            color: #7dd3fc;
        }
        .forgot-main {
            display: grid;
            flex: 1;
            place-items: center;
            padding: 24px 20px 64px;
        }
        .forgot-card {
            width: min(100%, 470px);
            padding: clamp(26px, 6vw, 42px);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 20px;
            background: rgba(12, 32, 37, 0.82);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(14px);
        }
        .forgot-icon {
            display: grid;
            width: 54px;
            height: 54px;
            margin-bottom: 22px;
            place-items: center;
            border: 1px solid rgba(125, 211, 252, 0.35);
            border-radius: 16px;
            background: rgba(41, 182, 246, 0.14);
            color: #7dd3fc;
            font-size: 25px;
        }
        .forgot-card h1 {
            margin: 0 0 10px;
            color: #fff;
            font-size: clamp(1.7rem, 5vw, 2.1rem);
            line-height: 1.2;
        }
        .forgot-intro {
            margin: 0 0 26px;
            color: #cbd5e1;
            font-size: 0.98rem;
            line-height: 1.65;
        }
        .forgot-form label {
            display: block;
            margin-bottom: 8px;
            color: #f1f5f9;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .forgot-form input {
            width: 100%;
            min-height: 50px;
            padding: 12px 14px;
            border: 1px solid rgba(203, 213, 225, 0.35);
            border-radius: 10px;
            outline: none;
            background: rgba(8, 24, 28, 0.72);
            color: #fff;
            font: inherit;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }
        .forgot-form input::placeholder {
            color: #9aafb3;
        }
        .forgot-form input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.18);
        }
        .forgot-submit {
            width: 100%;
            min-height: 50px;
            margin-top: 16px;
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #168447, #116334);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            transition: transform 160ms ease, filter 160ms ease;
        }
        .forgot-submit:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
        }
        .forgot-submit:focus-visible,
        .forgot-header-link:focus-visible,
        .forgot-back:focus-visible {
            outline: 3px solid #7dd3fc;
            outline-offset: 3px;
        }
        .forgot-message {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid rgba(134, 239, 172, 0.4);
            border-radius: 10px;
            background: rgba(22, 101, 52, 0.28);
            color: #dcfce7;
            font-size: 0.9rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }
        .forgot-back-wrap {
            margin: 22px 0 0;
            text-align: center;
        }
        .forgot-back {
            color: #bae6fd;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
        }
        .forgot-back:hover {
            color: #fff;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .forgot-header {
                padding: 16px 18px;
            }
            .forgot-brand {
                gap: 9px;
                font-size: 1.1rem;
            }
            .forgot-brand img {
                width: 44px;
                height: 44px;
            }
            .forgot-header-link {
                font-size: 0.85rem;
            }
            .forgot-main {
                align-items: start;
                padding: 28px 16px 36px;
            }
            .forgot-card {
                border-radius: 16px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .forgot-form input,
            .forgot-submit {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-page">
        <header class="forgot-header">
            <a class="forgot-brand" href="index.php">
                <img src="img/task_icon.png" alt="">
                <span>TaskTrack</span>
            </a>
            <a class="forgot-header-link" href="index.php">Back to login</a>
        </header>

        <main class="forgot-main">
            <section class="forgot-card" aria-labelledby="forgotTitle">
                <h1 id="forgotTitle">Forgot your password?</h1>
                <p class="forgot-intro">Enter the email address linked to your account. If it exists, we’ll send you a secure password reset link.</p>

                <?php if ($resetMessage !== ''): ?>
                    <div class="forgot-message" role="status" aria-live="polite">
                        <?= htmlspecialchars($resetMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form class="forgot-form" method="POST" action="forgot-pass.php">
                    <label for="resetEmail">Email address</label>
                    <input
                        type="email"
                        id="resetEmail"
                        name="email"
                        placeholder="name@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        autocomplete="email"
                        required
                    >
                    <button class="forgot-submit" type="submit">Send Reset Link</button>
                </form>

                <p class="forgot-back-wrap"><a class="forgot-back" href="index.php">← Return to login</a></p>
            </section>
        </main>
    </div>
</body>
</html>
