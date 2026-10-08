<?php
//session_start(); 
include 'config/loginBE.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskTrack - Login</title>
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Separate CSS File -->
    <link rel="stylesheet" href="css/login.css?v=20261007-centered-nav">
    <style>
        .login-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 24px;
            background: rgba(43, 62, 66, 0.94);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .login-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .gear-spin-container {
            position: relative;
            width: clamp(64px, 20vw, 90px);
            height: clamp(64px, 20vw, 90px);
            margin-bottom: 20px;
        }
        .gear-main {
            width: 100%;
            height: 100%;
            overflow: visible;
        }
        .gear-wheel {
            transform-origin: 50px 50px;
        }
        .login-overlay.active .gear-wheel {
            animation: spinGear 1.2s cubic-bezier(0.4, 0, 0.2, 1) 1 both;
        }
        .login-overlay.zoom .gear-wheel {
            animation: zoomGear 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes spinGear {
            to { transform: rotate(360deg); }
        }
        @keyframes zoomGear {
            to { transform: rotate(360deg) scale(12); opacity: 0; }
        }
        .welcome-word {
            position: absolute;
            left: 50%;
            max-width: calc(100vw - 32px);
            color: #fff;
            font-size: clamp(28px, 8vw, 64px);
            font-weight: 800;
            letter-spacing: clamp(2px, 1.2vw, 8px);
            white-space: nowrap;
            opacity: 0;
            transform: translate(-50%, 8px) scale(0.8);
            transition: opacity 0.35s ease, transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .welcome-word span {
            display: inline-block;
            opacity: 0;
            transform: translateY(14px) scale(0.8);
        }
        .login-overlay.welcome .welcome-word {
            transform: translate(-50%, 0) scale(1);
            opacity: 1;
        }
        .login-overlay.welcome .welcome-word span {
            animation: welcomeLetter 0.4s ease-out forwards;
        }
        .login-overlay.welcome .welcome-word span:nth-child(2) { animation-delay: 0.06s; }
        .login-overlay.welcome .welcome-word span:nth-child(3) { animation-delay: 0.12s; }
        .login-overlay.welcome .welcome-word span:nth-child(4) { animation-delay: 0.18s; }
        .login-overlay.welcome .welcome-word span:nth-child(5) { animation-delay: 0.24s; }
        .login-overlay.welcome .welcome-word span:nth-child(6) { animation-delay: 0.3s; }
        .login-overlay.welcome .welcome-word span:nth-child(7) { animation-delay: 0.36s; }
        @keyframes welcomeLetter {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .overlay-status {
            max-width: calc(100vw - 40px);
            color: #e2e8f0;
            font-size: clamp(13px, 3.5vw, 15px);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-align: center;
            transition: opacity 0.25s ease;
        }
        .login-overlay.zoom .overlay-status {
            opacity: 0;
        }
        .login-overlay.leaving .welcome-word {
            opacity: 0;
            transform: translate(-50%, -8px) scale(1.08);
            transition-duration: 0.3s;
        }
        .login-feedback[hidden] {
            display: none;
        }
        @media (prefers-reduced-motion: reduce) {
            .login-overlay.active .gear-wheel,
            .login-overlay.zoom .gear-wheel {
                animation-duration: 0.01ms;
                animation-iteration-count: 1;
            }
            .overlay-status,
            .login-overlay.welcome .welcome-word,
            .login-overlay.welcome .welcome-word span {
                transition-duration: 0.01ms;
                animation-duration: 0.01ms;
            }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Header Navigation -->
        <header>
            <div class="brand"> 
                    <img class="gear-brand" src="img/task_icon.png" alt="Task Icon">
                <span>TaskTrack</span>
            </div>
            <nav>
                <a href="#" class="active">Login</a>
                <a href="TaskTrackWeb.php">Website</a>
                <a href="register.php">Register</a>
                <a href="attendance_form.php">ATTENDANCE</a>
            </nav>
        </header>

        <!-- Main Content Split -->
        <div class="content">
            <!-- Left Side: Form -->
            <div class="login-section">
                <h1>LOGIN</h1>

                <!-- Dynamic PHP Error Message -->
                <?php if (!empty($msg)): ?>
                    <div class="alert-danger">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <div id="loginFeedback" class="alert-danger login-feedback" role="alert" hidden></div>
                <form id="loginForm" action="" method="post">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" 
                               placeholder="Example@gmail.com" 
                               class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <?php if (!empty($email_err)): ?>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" 
                               placeholder="Enter Your Password" 
                               class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                        <?php if (!empty($password_err)): ?>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="forgot-link">
                        Forgot your? <a href="forgot-pass.php">Password</a>
                    </div>

                    <button type="submit" name="Submit" class="btn-submit">SUBMIT</button>
                </form>
            </div>

            <!-- Right Side: Info Text -->
            <div class="welcome-section">
                <h2>Welcome to Tasktrack</h2>
                <p>
                    TaskTrack is a system that helps companies to track their workflow 
                    in their company. The goal is to make teams more productive by tracking 
                    their work.
                </p>
            </div>
        </div>

        <!-- Gear Logo Watermark -->
        <div class="gear-watermark">
            <img src="img/task_icon.png" alt="Task Icon">
        </div>
    </div>

    <div id="loginOverlay" class="login-overlay" role="status" aria-live="polite" aria-hidden="true">
        <div class="gear-spin-container" aria-hidden="true">
            <svg class="gear-main" viewBox="0 0 100 100">
                <g class="gear-wheel" fill="#ffffff">
                    <circle cx="50" cy="50" r="31" />
                    <circle cx="50" cy="50" r="17" fill="#2b3e42" />
                    <rect x="44" y="4" width="12" height="18" rx="2" />
                    <rect x="44" y="78" width="12" height="18" rx="2" />
                    <rect x="4" y="44" width="18" height="12" rx="2" />
                    <rect x="78" y="44" width="18" height="12" rx="2" />
                    <rect x="15" y="15" width="17" height="12" rx="2" transform="rotate(-45 23.5 21)" />
                    <rect x="68" y="15" width="17" height="12" rx="2" transform="rotate(45 76.5 21)" />
                    <rect x="15" y="73" width="17" height="12" rx="2" transform="rotate(45 23.5 79)" />
                    <rect x="68" y="73" width="17" height="12" rx="2" transform="rotate(-45 76.5 79)" />
                </g>
            </svg>
        </div>
        <div class="welcome-word" aria-hidden="true"><span>W</span><span>E</span><span>L</span><span>C</span><span>O</span><span>M</span><span>E</span></div>
        <div id="statusLabel" class="overlay-status">Authenticating...</div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const loginFeedback = document.getElementById('loginFeedback');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        function setFieldError(input, message) {
            const group = input.closest('.form-group');
            let errorElement = group.querySelector('.ajax-invalid-feedback');

            input.classList.toggle('is-invalid', Boolean(message));
            if (!message) {
                if (errorElement) {
                    errorElement.remove();
                }
                return;
            }

            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback ajax-invalid-feedback';
                group.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }

        function playLoginAnimation() {
            const overlay = document.getElementById('loginOverlay');
            const statusLabel = document.getElementById('statusLabel');

            overlay.className = 'login-overlay active';
            overlay.setAttribute('aria-hidden', 'false');
            statusLabel.textContent = 'Authenticating user...';

            return new Promise(function (resolve) {
                window.setTimeout(function () {
                    statusLabel.textContent = 'Loading TASKTRACK workspace...';
                    overlay.classList.add('zoom');
                }, 1150);

                window.setTimeout(function () {
                    overlay.classList.add('welcome');
                    statusLabel.textContent = 'Welcome to TASKTRACK';
                }, 2050);

                window.setTimeout(function () {
                    overlay.classList.add('leaving');
                }, 2650);

                window.setTimeout(resolve, 3000);
            });
        }

        loginForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (loginForm.dataset.verifying === 'true') {
                return;
            }

            loginForm.dataset.verifying = 'true';
            loginFeedback.hidden = true;
            loginFeedback.textContent = '';
            setFieldError(emailInput, '');
            setFieldError(passwordInput, '');

            const formData = new FormData(loginForm);
            formData.set('Submit', '1');
            formData.set('ajax_login', '1');

            try {
                const response = await fetch(loginForm.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error('The login service returned an error. Please try again.');
                }

                if (result.success !== true) {
                    loginFeedback.textContent = result.message || 'Unable to log in. Please check your credentials.';
                    loginFeedback.hidden = false;
                    setFieldError(emailInput, result.email_error || '');
                    setFieldError(passwordInput, result.password_error || '');
                    loginForm.dataset.verifying = 'false';
                    return;
                }

                if (typeof result.redirect !== 'string') {
                    throw new Error('The login service returned an invalid redirect.');
                }

                const destination = new URL(result.redirect, window.location.href);
                if (destination.origin !== window.location.origin) {
                    throw new Error('The login service returned an unsafe redirect.');
                }

                await playLoginAnimation();
                window.location.assign(destination.href);
            } catch (error) {
                loginFeedback.textContent = error instanceof Error
                    ? error.message
                    : 'Unable to verify login. Please try again.';
                loginFeedback.hidden = false;
                loginForm.dataset.verifying = 'false';
            }
        });
    </script>
</body>
</html>