<?php
/**
 * Gedeelde Page Header voor Boekhouden
 *
 * Verplichte variabelen (instellen voor include):
 *   $page_title    - Paginatitel (gebruikt in <title> en <h1>)
 *
 * Optionele variabelen:
 *   $page_subtitle - Subtitel tekst
 *   $page_css      - Inline CSS string voor pagina-specifieke stijlen
 *   $active_nav    - Override actieve nav detectie (bijv. 'relations')
 *   $require_auth  - 'login' (standaard) of 'admin'
 *   $show_nav      - true (standaard) of false - toon nav/header
 */

// Auth check
if (!isset($require_auth) || $require_auth !== 'admin') {
    require_login();
} else {
    require_admin();
}

// Pad-prefix berekening (../ voor php/ subdirectory, leeg voor root)
$_page_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$_path_prefix = ($_page_dir === 'php') ? '../' : '';

// Standaardwaarden
$page_title = $page_title ?? 'Boekhouden';
$page_subtitle = $page_subtitle ?? '';
$show_nav = $show_nav ?? true;
$_current_script = basename($_SERVER['PHP_SELF']);
$_active_nav = $active_nav ?? $_current_script;

// Zorg dat $is_admin beschikbaar is
if (!isset($is_admin)) {
    $is_admin = is_admin();
}

// Nav actieve detectie helper
function is_nav_active($match, $_active_nav) {
    if (is_array($match)) {
        return in_array($_active_nav, $match) ? 'active' : '';
    }
    return $_active_nav === $match ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Boekhouden</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $_path_prefix; ?>favicon.svg">
    <link rel="stylesheet" href="<?php echo $_path_prefix; ?>css/style.css">
    <?php // Font Awesome 6.4.0 - lokaal gehost (zie vendor/fontawesome/README.md).
          // Alleen de 'solid' variant; die is de enige die de app gebruikt. ?>
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="<?php echo $_path_prefix; ?>vendor/fontawesome/webfonts/fa-solid-900.woff2">
    <link rel="stylesheet" href="<?php echo $_path_prefix; ?>vendor/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?php echo $_path_prefix; ?>vendor/fontawesome/css/solid.min.css">
    <?php if (!empty($page_css)): ?>
    <style>
        /* Pagina-specifieke CSS injectie.
           Alle gedeelde componenten (header, nav, alerts, profielmenu,
           thema-schakelaar) staan in css/style.css - voeg ze hier niet
           opnieuw toe, want deze regels overschrijven het ontwerpsysteem. */
        <?php echo $page_css; ?>
    </style>
    <?php endif; ?>
    <?php require $_path_prefix . 'php/theme_init.php'; ?>
</head>
<body>
<?php if ($show_nav): ?>
    <div class="header">
        <div class="header-logo-container">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
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
                    <text x="70" y="30" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" font-size="22" font-weight="600" fill="white">BOEK!N</text>
                </svg>
            </div>
            <div class="header-text">
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <?php if ($page_subtitle): ?>
                <p><?php echo htmlspecialchars($page_subtitle); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="<?php echo $_path_prefix; ?>index.php" class="<?php echo is_nav_active('index.php', $_active_nav); ?>">Overzicht</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/add_income.php" class="<?php echo is_nav_active('add_income.php', $_active_nav); ?>">Verkoop Boeken</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/add_expense.php" class="<?php echo is_nav_active('add_expense.php', $_active_nav); ?>">Inkoop Boeken</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/relations.php" class="<?php echo is_nav_active(['relations.php', 'add_relation.php', 'edit_relation.php', 'delete_relation.php'], $_active_nav); ?>"><i class="fas fa-address-book"></i> Relaties</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/profit_loss.php" class="<?php echo is_nav_active('profit_loss.php', $_active_nav); ?>">Winst & Verlies</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/btw_kwartaal.php" class="<?php echo is_nav_active('btw_kwartaal.php', $_active_nav); ?>">BTW Overzicht</a></li>
            <li><a href="<?php echo $_path_prefix; ?>php/balans.php" class="<?php echo is_nav_active('balans.php', $_active_nav); ?>">Balans</a></li>
            <?php if ($is_admin): ?>
                <li><a href="<?php echo $_path_prefix; ?>php/admin_dashboard.php" class="<?php echo is_nav_active('admin_dashboard.php', $_active_nav); ?>">Admin Dashboard</a></li>
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
                        <li><a href="<?php echo $_path_prefix; ?>index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <?php if ($is_admin): ?>
                        <li><a href="<?php echo $_path_prefix; ?>php/admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                        <li><a href="<?php echo $_path_prefix; ?>php/admin_users.php"><i class="fas fa-users"></i> Gebruikersbeheer</a></li>
                        <?php endif; ?>
                        <li><button class="theme-toggle" onclick="toggleTheme()"><i class="fas fa-moon" id="themeIcon"></i> <span id="themeLabel">Donker thema</span></button></li>
                        <li><a href="<?php echo $_path_prefix; ?>logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Uitloggen</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </nav>
<?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var profileIcon = document.getElementById('profileIcon');
        var profileDropdown = document.getElementById('profileDropdown');

        if (profileIcon && profileDropdown) {
            profileIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });

            var dropdownLinks = profileDropdown.querySelectorAll('a');
            dropdownLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    profileDropdown.classList.remove('show');
                });
            });
        }
    });
    </script>

    <?php if (isset($error_message) && $error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (isset($success_message) && $success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning_message']) && $_SESSION['warning_message']): ?>
        <?php $warning_message = $_SESSION['warning_message']; unset($_SESSION['warning_message']); ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($warning_message); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message']) && $_SESSION['success_message']): ?>
        <?php $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>