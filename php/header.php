<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Boekhouden'); ?></title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Additional styles for admin pages */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-role {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: capitalize;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        
        .nav-links a {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        /* Profile dropdown styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .profile-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-icon:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
        }
        
        .dropdown-content.show {
            display: block;
        }
        
        .dropdown-header {
            padding: 15px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
        }
        
        .dropdown-header .user-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 3px;
        }
        
        .dropdown-header .user-email {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .dropdown-header .user-role {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-top: 5px;
        }
        
        .dropdown-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .dropdown-menu li {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-menu li:last-child {
            border-bottom: none;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-menu a i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .dropdown-menu .logout-link {
            color: #e74c3c !important;
        }
        
        .dropdown-menu .logout-link:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        .dropdown-menu .logout-link i {
            color: #e74c3c;
        }
        
        .user-info-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            color: white;
            font-size: 0.9rem;
            position: relative;
        }
        
        /* Header styles */
        .header {
            background: linear-gradient(135deg, var(--primary-color, #2c3e50), var(--accent-color, #2980b9));
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius, 8px);
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow, 0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        /* Navigation styles */
        .nav-bar {
            background-color: white;
            border-radius: var(--border-radius, 8px);
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow, 0 4px 6px rgba(0, 0, 0, 0.1));
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            list-style: none;
        }
        
        .nav-links a {
            color: var(--primary-color, #2c3e50);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius, 8px);
            background-color: var(--gray-light, #f8f9fa);
            transition: var(--transition, all 0.3s ease);
            font-weight: 500;
        }
        
        .nav-links a:hover {
            background-color: var(--secondary-color, #3498db);
            color: white;
            transform: translateY(-2px);
        }
        
        .nav-links a.active {
            background-color: var(--secondary-color, #3498db);
            color: white;
        }
    </style>
</head>
<body>
    <?php if (isset($show_nav) && $show_nav !== false): ?>
    <div class="header">
        <div class="header-logo-container">
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
                    <text x="70" y="30" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" font-size="22" font-weight="600" fill="white">BOEK!N</text>
                </svg>
            </div>
            <div class="header-text">
                <h1><?php echo htmlspecialchars($page_title ?? 'Boekhouden'); ?></h1>
                <p><?php echo htmlspecialchars($page_subtitle ?? 'Overzicht'); ?></p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Overzicht</a></li>
            <li><a href="add_income.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_income.php' ? 'active' : ''; ?>">Verkoop Boeken</a></li>
            <li><a href="add_expense.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_expense.php' ? 'active' : ''; ?>">Inkoop Boeken</a></li>
            <li><a href="relations.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['relations.php', 'add_relation.php', 'edit_relation.php', 'delete_relation.php']) ? 'active' : ''; ?>"><i class="fas fa-address-book"></i> Relaties</a></li>
            <li><a href="profit_loss.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profit_loss.php' ? 'active' : ''; ?>">Winst & Verlies</a></li>
            <li><a href="btw_kwartaal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'btw_kwartaal.php' ? 'active' : ''; ?>">BTW Overzicht</a></li>
            <li><a href="balans.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'balans.php' ? 'active' : ''; ?>">Balans</a></li>
            <?php if (is_admin()): ?>
                <li><a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">Admin Dashboard</a></li>
            <?php endif; ?>
        </ul>
        <?php if (is_logged_in()): ?>
        <div class="user-info-nav">
            <div class="profile-dropdown">
                <?php
                $user = get_current_user_data();
                $user_initial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));
                $user_name = $user['full_name'] ?? $user['username'] ?? 'Gebruiker';
                $user_email = $user['email'] ?? '';
                $user_role = $user['user_type'] ?? 'gebruiker';
                $role_display = ($user_role === 'administrator') ? 'Administrator' : 'Gebruiker';
                ?>
                <div class="profile-icon" id="profileIcon">
                    <?php echo $user_initial; ?>
                </div>
                <div class="dropdown-content" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <?php if ($user_email): ?>
                        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        <?php endif; ?>
                        <div class="user-role"><?php echo htmlspecialchars($role_display); ?></div>
                    </div>
                    <ul class="dropdown-menu">
                        <li><a href="../index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <?php if (is_admin()): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Gebruikersbeheer</a></li>
                        <?php endif; ?>
                        <li><a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Uitloggen</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    
    <?php if (isset($error_message) && $error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message) && $success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>