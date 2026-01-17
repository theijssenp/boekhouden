<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Boekhouden</title>
    <link rel="stylesheet" href="style.css">
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
    </style>
</head>
<body>
    <?php
    require 'auth_functions.php';
    
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
                
                // Redirect after short delay
                $redirect = $_GET['redirect'] ?? (is_admin() ? 'admin_dashboard.php' : 'index.php');
                header("Refresh: 2; URL=$redirect");
            } else {
                $error = $result['message'];
            }
        }
    }
    ?>
    
    <div class="login-container">
        <div class="login-header">
            <h1>Boekhouden</h1>
            <p>Log in om toegang te krijgen tot uw administratie</p>
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
    </script>
</body>
</html>