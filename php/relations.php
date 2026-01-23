<?php
/**
 * Relatiebeheer - Overzicht Debiteuren & Crediteuren
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

// Determine tab filter
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'alle';
$allowed_tabs = ['alle', 'debiteuren', 'crediteuren'];
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'alle';
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause based on filters
$where_conditions = ["r.is_active = 1"];
$where_params = [];

// Add user isolation for non-admin users
if (!$is_admin) {
    $where_conditions[] = "r.user_id = ?";
    $where_params[] = $user_id;
}

// Add tab filter
if ($tab === 'debiteuren') {
    $where_conditions[] = "r.relation_type IN ('debiteur', 'beide')";
} elseif ($tab === 'crediteuren') {
    $where_conditions[] = "r.relation_type IN ('crediteur', 'beide')";
}

// Add search filter
if (!empty($search)) {
    $where_conditions[] = "(r.company_name LIKE ? OR r.relation_code LIKE ? OR r.city LIKE ? OR r.contact_person LIKE ? OR r.email LIKE ?)";
    $search_param = "%$search%";
    $where_params[] = $search_param;
    $where_params[] = $search_param;
    $where_params[] = $search_param;
    $where_params[] = $search_param;
    $where_params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Build SQL query
if ($is_admin) {
    $sql = "SELECT r.*, u.username, u.full_name as user_full_name
            FROM relations r
            LEFT JOIN users u ON r.user_id = u.id
            $where_clause
            ORDER BY r.company_name ASC";
} else {
    $sql = "SELECT r.*
            FROM relations r
            $where_clause
            ORDER BY r.company_name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($where_params);
$relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count relations by type for badges
$count_all = count($relations);
$count_debiteuren = count(array_filter($relations, function($r) {
    return in_array($r['relation_type'], ['debiteur', 'beide']);
}));
$count_crediteuren = count(array_filter($relations, function($r) {
    return in_array($r['relation_type'], ['crediteur', 'beide']);
}));

// Success/error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

$page_title = 'Relatiebeheer';
$page_subtitle = 'Beheer debiteuren en crediteuren';
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
        .tab-navigation {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-medium);
            flex-wrap: wrap;
        }
        
        .tab-button {
            padding: 1rem 2rem;
            background: none;
            border: none;
            color: var(--gray-dark);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            color: var(--secondary-color);
            background-color: var(--gray-light);
        }
        
        .tab-button.active {
            color: var(--secondary-color);
            border-bottom-color: var(--secondary-color);
        }
        
        .tab-badge {
            display: inline-block;
            background-color: var(--secondary-color);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            font-weight: 600;
        }
        
        .tab-button.active .tab-badge {
            background-color: var(--accent-color);
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-bar input {
            flex: 1;
            min-width: 250px;
        }
        
        .relation-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .relation-badge.debiteur {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(39, 174, 96, 0.3);
        }
        
        .relation-badge.crediteur {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .relation-badge.beide {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        .relation-code {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .user-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-dark);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-medium);
            margin-bottom: 1rem;
        }
        
        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
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
                <p><?php echo htmlspecialchars($page_subtitle); ?></p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Overzicht</a></li>
            <li><a href="add_income.php">Verkoop Boeken</a></li>
            <li><a href="add_expense.php">Inkoop Boeken</a></li>
            <li><a href="relations.php" class="active"><i class="fas fa-address-book"></i> Relaties</a></li>
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
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="page-actions">
            <h2 class="section-title">Relaties Overzicht</h2>
            <a href="add_relation.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nieuwe Relatie
            </a>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <input type="text" 
                   id="searchInput" 
                   class="form-control" 
                   placeholder="Zoeken op naam, relatiecode, plaats..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button onclick="performSearch()" class="btn btn-secondary">
                <i class="fas fa-search"></i> Zoeken
            </button>
            <?php if (!empty($search)): ?>
            <a href="relations.php?tab=<?php echo $tab; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Wissen
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $tab === 'alle' ? 'active' : ''; ?>"
                    onclick="switchTab('alle')">
                Alle Relaties
                <span class="tab-badge"><?php echo $count_all; ?></span>
            </button>
            <button class="tab-button <?php echo $tab === 'debiteuren' ? 'active' : ''; ?>"
                    onclick="switchTab('debiteuren')">
                <i class="fas fa-user-tie"></i> Debiteuren
                <span class="tab-badge"><?php echo $count_debiteuren; ?></span>
            </button>
            <button class="tab-button <?php echo $tab === 'crediteuren' ? 'active' : ''; ?>"
                    onclick="switchTab('crediteuren')">
                <i class="fas fa-building"></i> Crediteuren
                <span class="tab-badge"><?php echo $count_crediteuren; ?></span>
            </button>
        </div>
        
        <?php if (empty($relations)): ?>
            <div class="empty-state">
                <i class="fas fa-address-book"></i>
                <h3>Geen relaties gevonden</h3>
                <p>
                    <?php if (!empty($search)): ?>
                        Geen relaties gevonden voor "<?php echo htmlspecialchars($search); ?>". Probeer een andere zoekopdracht.
                    <?php else: ?>
                        Er zijn nog geen relaties toegevoegd. Klik op "Nieuwe Relatie" om te beginnen.
                    <?php endif; ?>
                </p>
                <a href="add_relation.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Nieuwe Relatie Toevoegen
                </a>
            </div>
        <?php else: ?>
        <!-- Relations Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Relatiecode</th>
                        <th>Bedrijfsnaam</th>
                        <th>Type</th>
                        <th>Contactpersoon</th>
                        <th>E-mail</th>
                        <th>Telefoon</th>
                        <th>Plaats</th>
                        <?php if ($is_admin): ?>
                        <th>Gebruiker</th>
                        <?php endif; ?>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relations as $relation): ?>
                    <tr>
                        <td>
                            <span class="relation-code"><?php echo htmlspecialchars($relation['relation_code']); ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($relation['company_name']); ?></strong>
                        </td>
                        <td>
                            <span class="relation-badge <?php echo $relation['relation_type']; ?>">
                                <?php 
                                    $type_labels = [
                                        'debiteur' => 'Debiteur',
                                        'crediteur' => 'Crediteur',
                                        'beide' => 'Beide'
                                    ];
                                    echo $type_labels[$relation['relation_type']];
                                ?>
                            </span>
                        </td>
                        <td><?php echo $relation['contact_person'] ? htmlspecialchars($relation['contact_person']) : '<span class="neutral">-</span>'; ?></td>
                        <td>
                            <?php if ($relation['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($relation['email']); ?>">
                                    <?php echo htmlspecialchars($relation['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="neutral">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $relation['phone'] ? htmlspecialchars($relation['phone']) : '<span class="neutral">-</span>'; ?></td>
                        <td><?php echo $relation['city'] ? htmlspecialchars($relation['city']) : '<span class="neutral">-</span>'; ?></td>
                        <?php if ($is_admin): ?>
                        <td>
                            <?php if (!empty($relation['username'])): ?>
                                <span class="user-badge" title="<?php echo htmlspecialchars($relation['user_full_name'] ?? $relation['username']); ?>">
                                    <?php echo htmlspecialchars($relation['username']); ?>
                                </span>
                            <?php else: ?>
                                <span class="neutral">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <div class="btn-group">
                                <a href="edit_relation.php?id=<?php echo $relation['id']; ?>" 
                                   class="btn btn-secondary btn-sm" 
                                   title="Bewerken">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_relation.php?id=<?php echo $relation['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($relation['company_name']); ?> wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')"
                                   title="Verwijderen">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-title"><i class="fas fa-info-circle"></i> Over Relatiebeheer</h3>
            <p><strong>Debiteuren</strong> zijn klanten aan wie je facturen stuurt (inkomsten).</p>
            <p><strong>Crediteuren</strong> zijn leveranciers van wie je facturen ontvangt (uitgaven).</p>
            <p><strong>Beide</strong> zijn relaties die zowel debiteur als crediteur kunnen zijn.</p>
        </div>
    </main>

    <script>
        // Tab switching function
        function switchTab(tab) {
            const currentSearch = '<?php echo addslashes($search); ?>';
            let url = `relations.php?tab=${tab}`;
            if (currentSearch) {
                url += `&search=${encodeURIComponent(currentSearch)}`;
            }
            window.location.href = url;
        }
        
        // Search function
        function performSearch() {
            const searchValue = document.getElementById('searchInput').value;
            const currentTab = '<?php echo $tab; ?>';
            let url = `relations.php?tab=${currentTab}`;
            if (searchValue) {
                url += `&search=${encodeURIComponent(searchValue)}`;
            }
            window.location.href = url;
        }
        
        // Allow Enter key to trigger search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
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
