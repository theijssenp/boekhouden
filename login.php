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
        header('Location: admin_dashboard.php');
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
            
            // Immediate redirect
            $redirect = $_GET['redirect'] ?? (is_admin() ? 'admin_dashboard.php' : 'index.php');
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .login-button {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .login-button:hover {
            background: #2980b9;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }
        
        .login-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .demo-credentials h4 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .demo-credentials ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .demo-credentials li {
            margin-bottom: 5px;
        }
        
        .demo-credentials code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        /* Zandloper (hourglass) spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
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
            border-color: transparent transparent #3498db transparent;
        }
        
        .zandloper:after {
            bottom: 0;
            border-width: 30px 25px 0 25px;
            border-color: #3498db transparent transparent transparent;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .spinner-text {
            margin-top: 20px;
            color: #2c3e50;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
        }
        
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="header-logo-container" style="justify-content: center; margin-bottom: 20px;">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
                        <defs>
                            <linearGradient id="header-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#2c3e50;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#3498db;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <rect x="5" y="5" width="50" height="50" rx="10" ry="10" fill="url(#header-gradient)" stroke="#2c3e50" stroke-width="1.5"/>
                        <rect x="15" y="15" width="30" height="30" rx="3" ry="3" fill="white" opacity="0.9"/>
                        <rect x="15" y="15" width="5" height="30" rx="1" ry="1" fill="#2c3e50"/>
                        <line x1="25" y1="20" x2="40" y2="20" stroke="#3498db" stroke-width="1"/>
                        <line x1="25" y1="25" x2="40" y2="25" stroke="#3498db" stroke-width="1"/>
                        <line x1="25" y1="30" x2="40" y2="30" stroke="#3498db" stroke-width="1"/>
                        <line x1="25" y1="35" x2="40" y2="35" stroke="#3498db" stroke-width="1"/>
                        <line x1="25" y1="40" x2="40" y2="40" stroke="#3498db" stroke-width="1"/>
                        <text x="32" y="38" text-anchor="middle" fill="#2c3e50" font-family="Arial, sans-serif" font-weight="bold" font-size="14">€</text>
                        <text x="70" y="30" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" font-size="22" font-weight="600" fill="#2c3e50">BOEK!N</text>
                    </svg>
                </div>
            </div>
            <h1 style="text-align: center; color: #2c3e50; margin-top: 10px;">Boekhouden</h1>
            <p style="text-align: center;">Log in om toegang te krijgen tot uw administratie</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <p>U wordt doorgestuurd...</p>
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
                <input type="password" id="password" name="password" required>
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
            <h4>Demo inloggegevens:</h4>
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
        // Focus on username field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Show/hide password functionality
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.textContent = '👁️';
            toggleButton.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                font-size: 16px;
            `;
            
            const passwordGroup = passwordInput.parentElement;
            passwordGroup.style.position = 'relative';
            passwordGroup.appendChild(toggleButton);
            
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggleButton.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
            });
        });
        
        // Show spinner on form submit
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.querySelector('form');
            const loginButton = document.querySelector('.login-button');
            const spinnerOverlay = document.getElementById('spinnerOverlay');
            
            loginForm.addEventListener('submit', function(event) {
                // Validate form inputs
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (username && password) {
                    // Show spinner
                    spinnerOverlay.classList.add('active');
                    // Disable button
                    loginButton.disabled = true;
                    loginButton.textContent = 'Inloggen...';
                }
                // If validation fails, let default form submission show error
                // (spinner won't show)
            });
        });
    </script>
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #666; font-size: 12px;">
        powered by P. Theijssen
    </footer>
</body>
</html>