<?php
/**
 * Inloggen - Boekhouden
 *
 * @author P. Theijssen
 */
require 'php/auth_functions.php';

// If already logged in, redirect to appropriate page
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: php/admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vul zowel gebruikersnaam als wachtwoord in';
    } else {
        $result = login_user($username, $password);
        
        if ($result['success']) {
            $success = 'Succesvol ingelogd!';
            
            // Immediate redirect - validate redirect URL against whitelist
            $redirect_raw = $_GET['redirect'] ?? 'index.php';
            $allowed_redirects = ['index.php', 'php/admin_dashboard.php', 'php/admin_users.php', 'php/relations.php', 'php/profit_loss.php', 'php/btw_kwartaal.php', 'php/balans.php'];
            if (!empty($redirect_raw) && $redirect_raw !== 'index.php') {
                $redirect_path = parse_url($redirect_raw, PHP_URL_PATH) ?: $redirect_raw;
                $redirect_path = ltrim($redirect_path, '/');
                $redirect = in_array($redirect_path, $allowed_redirects) ? $redirect_path : 'index.php';
            } else {
                $redirect = 'index.php';
            }
            header("Location: $redirect");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Boekhouden</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0;
            padding: 2.5rem;
            background: var(--bg-card);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-raised);
            animation: fadeIn 0.4s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .login-header h1 {
            color: var(--text-primary);
            margin-top: 0.5rem;
            margin-bottom: 0.4rem;
            font-size: 1.7rem;
        }

        .login-header p {
            color: var(--text-secondary);
        }

        .login-container .form-group input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--bg-input);
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
        }

        .login-container .form-group input:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .login-button {
            width: 100%;
            padding: 0.8rem;
            background: var(--secondary-color);
            color: var(--text-inverse);
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .login-button:hover {
            background: var(--accent-color);
            transform: translateY(-1px);
        }

        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.25rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .login-footer a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            background: var(--gray-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1.25rem;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .demo-credentials h4 {
            margin-top: 0;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .demo-credentials ul {
            margin: 0.6rem 0 0 0;
            padding-left: 1.25rem;
        }

        .demo-credentials li {
            margin-bottom: 0.3rem;
        }

        .demo-credentials code {
            background: var(--bg-hover);
            color: var(--text-primary);
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0.3rem;
        }

        /* Zandloper (hourglass) spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 15, 26, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .spinner-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .zandloper {
            width: 60px;
            height: 60px;
            position: relative;
            animation: rotate 2s linear infinite;
            margin: 0 auto;
        }

        .zandloper:before,
        .zandloper:after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-style: solid;
        }

        .zandloper:before {
            top: 0;
            border-width: 0 25px 30px 25px;
            border-color: transparent transparent var(--secondary-color) transparent;
        }

        .zandloper:after {
            bottom: 0;
            border-width: 30px 25px 0 25px;
            border-color: var(--secondary-color) transparent transparent transparent;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner-text {
            margin-top: 20px;
            color: var(--text-inverse);
            font-size: 16px;
            font-weight: 500;
            text-align: center;
        }
    </style>
    <?php require 'php/theme_init.php'; ?>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="header-logo-container" style="justify-content: center; margin-bottom: 0;">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="180" height="54">
                        <defs>
                            <linearGradient id="header-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:var(--primary-color);stop-opacity:1" />
                                <stop offset="100%" style="stop-color:var(--secondary-color);stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <rect x="5" y="5" width="50" height="50" rx="10" ry="10" fill="url(#header-gradient)" stroke="var(--primary-color)" stroke-width="1.5"/>
                        <rect x="15" y="15" width="30" height="30" rx="3" ry="3" fill="white" opacity="0.9"/>
                        <rect x="15" y="15" width="5" height="30" rx="1" ry="1" fill="var(--primary-color)"/>
                        <line x1="25" y1="20" x2="40" y2="20" stroke="var(--secondary-color)" stroke-width="1"/>
                        <line x1="25" y1="25" x2="40" y2="25" stroke="var(--secondary-color)" stroke-width="1"/>
                        <line x1="25" y1="30" x2="40" y2="30" stroke="var(--secondary-color)" stroke-width="1"/>
                        <line x1="25" y1="35" x2="40" y2="35" stroke="var(--secondary-color)" stroke-width="1"/>
                        <line x1="25" y1="40" x2="40" y2="40" stroke="var(--secondary-color)" stroke-width="1"/>
                        <text x="32" y="38" text-anchor="middle" fill="var(--primary-color)" font-family="Arial, sans-serif" font-weight="bold" font-size="14">€</text>
                        <text x="70" y="30" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" font-size="22" font-weight="600" fill="var(--text-primary)">BOEK!N</text>
                    </svg>
                </div>
            </div>
            <h1>Boekhouden</h1>
            <p>Log in om toegang te krijgen tot uw administratie</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <p style="margin: 0.25rem 0 0;">U wordt doorgestuurd&hellip;</p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Gebruikersnaam of e-mail</label>
                <input type="text" id="username" name="username" required
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                </div>
            </div>

            <button type="submit" class="login-button">Inloggen</button>
        </form>

        <!-- Spinner overlay -->
        <div class="spinner-overlay" id="spinnerOverlay">
            <div class="spinner-content">
                <div class="zandloper"></div>
                <div class="spinner-text">Inloggen...</div>
            </div>
        </div>

        <div class="demo-credentials">
            <h4><i class="fas fa-flask"></i> Demo inloggegevens</h4>
            <ul>
                <li><strong>Administrator:</strong> <code>admin</code> / <code>admin123</code></li>
                <li><strong>Administratie houder:</strong> <code>gebruiker1</code> / <code>user123</code></li>
            </ul>
        </div>

        <div class="login-footer">
            <p>Problemen met inloggen? Neem contact op met de administrator.</p>
            <p><a href="index.php">Terug naar startpagina</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();

            // Show/hide password
            const passwordInput = document.getElementById('password');
            const passwordField = passwordInput.closest('.password-field');
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.className = 'password-toggle';
            toggleButton.setAttribute('aria-label', 'Wachtwoord tonen/verbergen');
            toggleButton.textContent = '👁️';
            passwordField.appendChild(toggleButton);

            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggleButton.textContent = type === 'password' ? '👁️' : '🙈';
            });

            // Show spinner on submit
            const loginForm = document.querySelector('form');
            const loginButton = document.querySelector('.login-button');
            const spinnerOverlay = document.getElementById('spinnerOverlay');

            loginForm.addEventListener('submit', function(event) {
                const username = document.getElementById('username').value.trim();
                const password = passwordInput.value;

                if (username && password) {
                    spinnerOverlay.classList.add('active');
                    loginButton.disabled = true;
                    loginButton.textContent = 'Inloggen...';
                }
            });
        });
    </script>

    <footer style="text-align: center; padding: 20px; margin-top: 20px; color: var(--text-secondary); font-size: 12px;">
        powered by P. Theijssen
    </footer>
</body>
</html>