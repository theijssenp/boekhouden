<?php
/**
 * Relatie Verwijderen - Boekhouden
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

$id = $_GET['id'] ?? 0;

// Check if user can access this relation
if ($is_admin) {
    $stmt = $pdo->prepare("SELECT * FROM relations WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM relations WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}

$relation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$relation) {
    header('Location: relations.php?error=' . urlencode('Relatie niet gevonden of geen toegang'));
    exit;
}

// Check if relation is linked to any transactions
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE relation_id = ?");
$stmt->execute([$id]);
$transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $delete_type = $_POST['delete_type'] ?? 'soft';
    
    try {
        if ($delete_type === 'hard') {
            // Hard delete: remove from database
            // First, unlink from transactions (set relation_id to NULL)
            $stmt = $pdo->prepare("UPDATE transactions SET relation_id = NULL WHERE relation_id = ?");
            $stmt->execute([$id]);
            
            // Then delete the relation
            if ($is_admin) {
                $stmt = $pdo->prepare("DELETE FROM relations WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM relations WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            }
            
            header('Location: relations.php?success=' . urlencode('Relatie permanent verwijderd'));
            exit;
        } else {
            // Soft delete: set is_active to FALSE
            if ($is_admin) {
                $stmt = $pdo->prepare("UPDATE relations SET is_active = FALSE WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE relations SET is_active = FALSE WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            }
            
            header('Location: relations.php?success=' . urlencode('Relatie gedeactiveerd'));
            exit;
        }
    } catch (PDOException $e) {
        $error_message = 'Fout bij verwijderen: ' . $e->getMessage();
    }
}

$page_title = 'Relatie Verwijderen';
$page_subtitle = 'Verwijder ' . htmlspecialchars($relation['company_name']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Boekhouden</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .delete-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .delete-option {
            position: relative;
        }
        
        .delete-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .delete-option-label {
            display: block;
            padding: 2rem;
            border: 2px solid var(--gray-medium);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            background-color: white;
            height: 100%;
        }
        
        .delete-option-label:hover {
            border-color: var(--secondary-color);
            background-color: var(--gray-light);
        }
        
        .delete-option input[type="radio"]:checked + .delete-option-label {
            border-color: var(--danger-color);
            background-color: rgba(231, 76, 60, 0.05);
        }
        
        .delete-option-label i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .delete-option-label.soft i {
            color: var(--warning-color);
        }
        
        .delete-option-label.hard i {
            color: var(--danger-color);
        }
        
        .delete-option-label .option-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .delete-option-label .option-description {
            font-size: 0.95rem;
            color: var(--gray-dark);
            line-height: 1.6;
        }
        
        .relation-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        
        .relation-info h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .relation-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .relation-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .relation-info-label {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-bottom: 0.25rem;
        }
        
        .relation-info-value {
            font-weight: 600;
            color: var(--primary-color);
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
    </style>
</head>
<body>
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
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <p><?php echo $page_subtitle; ?></p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Overzicht</a></li>
            <li><a href="add_income.php">Verkoop Boeken</a></li>
            <li><a href="add_expense.php">Inkoop Boeken</a></li>
            <li><a href="relations.php"><i class="fas fa-address-book"></i> Relaties</a></li>
            <li><a href="profit_loss.php">Winst & Verlies</a></li>
            <li><a href="btw_kwartaal.php">BTW Overzicht</a></li>
            <li><a href="balans.php">Balans</a></li>
            <?php if ($is_admin): ?>
                <li><a href="admin_dashboard.php">Admin Dashboard</a></li>
            <?php endif; ?>
        </ul>
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
                        <?php if ($is_admin): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Gebruikersbeheer</a></li>
                        <?php endif; ?>
                        <li><a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Uitloggen</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-triangle"></i> Waarschuwing:</strong> 
            Je staat op het punt een relatie te verwijderen. Dit kan niet ongedaan worden gemaakt zonder een database backup.
        </div>
        
        <h2 class="section-title">Relatie Informatie</h2>
        
        <div class="relation-info">
            <h3><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($relation['company_name']); ?></h3>
            <div class="relation-info-grid">
                <div class="relation-info-item">
                    <span class="relation-info-label">Relatiecode</span>
                    <span class="relation-info-value"><?php echo htmlspecialchars($relation['relation_code']); ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Type</span>
                    <span class="relation-info-value"><?php echo ucfirst($relation['relation_type']); ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Contactpersoon</span>
                    <span class="relation-info-value"><?php echo $relation['contact_person'] ? htmlspecialchars($relation['contact_person']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">E-mail</span>
                    <span class="relation-info-value"><?php echo $relation['email'] ? htmlspecialchars($relation['email']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Plaats</span>
                    <span class="relation-info-value"><?php echo $relation['city'] ? htmlspecialchars($relation['city']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">BTW-nummer</span>
                    <span class="relation-info-value"><?php echo $relation['vat_number'] ? htmlspecialchars($relation['vat_number']) : '-'; ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($transaction_count > 0): ?>
            <div class="alert alert-warning">
                <strong><i class="fas fa-link"></i> Let op:</strong> 
                Deze relatie is gekoppeld aan <strong><?php echo $transaction_count; ?></strong> transactie(s). 
                Bij een harde verwijdering worden deze koppelingen verbroken (relation_id wordt NULL).
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Kies Verwijderingsoptie</h2>
        
        <form method="post">
            <div class="delete-options">
                <div class="delete-option">
                    <input type="radio" id="soft_delete" name="delete_type" value="soft" checked>
                    <label for="soft_delete" class="delete-option-label soft">
                        <i class="fas fa-eye-slash"></i>
                        <div class="option-name">Deactiveren (Aanbevolen)</div>
                        <div class="option-description">
                            De relatie wordt gemarkeerd als inactief en verborgen in overzichten. 
                            De relatie blijft in de database bestaan en kan later weer geactiveerd worden. 
                            Koppelingen met transacties blijven intact.
                        </div>
                    </label>
                </div>
                
                <div class="delete-option">
                    <input type="radio" id="hard_delete" name="delete_type" value="hard">
                    <label for="hard_delete" class="delete-option-label hard">
                        <i class="fas fa-trash-alt"></i>
                        <div class="option-name">Permanent Verwijderen</div>
                        <div class="option-description">
                            De relatie wordt permanent uit de database verwijderd. 
                            Deze actie kan niet ongedaan worden gemaakt. 
                            Transacties blijven bestaan, maar de koppeling naar deze relatie wordt verbroken.
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-lightbulb"></i> Tip:</strong> 
                Kies voor "Deactiveren" als je de relatie misschien later nog nodig hebt of als er historische transacties aan gekoppeld zijn.
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Weet je het zeker? Deze actie verwijdert de relatie.')">
                    <i class="fas fa-trash"></i> Relatie Verwijderen
                </button>
                <a href="relations.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuleren
                </a>
                <a href="edit_relation.php?id=<?php echo $relation['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bewerken
                </a>
            </div>
        </form>
    </main>

    <script>
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileIcon = document.getElementById('profileIcon');
            const profileDropdown = document.getElementById('profileDropdown');
            
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
                
                const dropdownLinks = profileDropdown.querySelectorAll('a');
                dropdownLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        profileDropdown.classList.remove('show');
                    });
                });
            }
        });
    </script>
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #666; font-size: 12px; border-top: 1px solid #eee;">
        powered by P. Theijssen
    </footer>
</body>
</html>
