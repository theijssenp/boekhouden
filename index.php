<?php
require 'auth_functions.php';
require_login();

// Get user info and admin status early
$user_id = get_current_user_id();
$is_admin = is_admin();

// Determine sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort column
$allowed_columns = ['date', 'invoice_number', 'description', 'amount', 'type', 'category'];
if ($is_admin) {
    $allowed_columns[] = 'user';
}
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'date';
}

// Validate sort order
$sort_order = strtolower($sort_order);
if (!in_array($sort_order, ['asc', 'desc'])) {
    $sort_order = 'asc';
}

// Map sort column to database column
$db_column_map = [
    'date' => 't.date',
    'invoice_number' => 't.invoice_number',
    'description' => 't.description',
    'amount' => 't.amount',
    'type' => 't.type',
    'category' => 'c.name'
];
if ($is_admin) {
    $db_column_map['user'] = 'u.username';
}

$db_column = $db_column_map[$sort_column] ?? 't.date';

// Build SQL query with sorting and user filtering
if ($is_admin) {
    // Admin can see all transactions
    $sql = "SELECT t.*, c.name as category, u.username, u.full_name as user_full_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY $db_column $sort_order, t.id $sort_order";
    $stmt = $pdo->query($sql);
} else {
    // Regular users can only see their own transactions
    $sql = "SELECT t.*, c.name as category
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ?
            ORDER BY $db_column $sort_order, t.id $sort_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalInkomsten = 0;
$totalUitgaven = 0;
foreach ($transactions as $t) {
    if ($t['type'] == 'inkomst') {
        $totalInkomsten += $t['amount'];
    } else {
        $totalUitgaven += $t['amount'];
    }
}
$balans = $totalInkomsten - $totalUitgaven;

// Function to generate sort URL
function sort_url($column, $current_column, $current_order) {
    $order = 'asc';
    if ($column == $current_column && $current_order == 'asc') {
        $order = 'desc';
    }
    return "index.php?sort=$column&order=$order";
}

// Function to get sort indicator
function sort_indicator($column, $current_column, $current_order) {
    if ($column == $current_column) {
        return $current_order == 'asc' ? '↑' : '↓';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boekhouden - Transactie Overzicht</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sortable-header {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 20px !important;
        }
        
        .sortable-header:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sort-indicator {
            position: absolute;
            right: 5px;
            font-weight: bold;
        }
        
        .sortable-header .sort-indicator {
            opacity: 1;
        }
        
        .sortable-header:not(.active-sort) .sort-indicator {
            opacity: 0.3;
        }
        
        .active-sort {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .table-info {
            background-color: var(--gray-light);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--gray-dark);
        }
        
        .table-info strong {
            color: var(--primary-color);
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
        
        .username {
            font-weight: 500;
        }
        
        .badge-admin {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .logout-link {
            color: #ff6b6b !important;
            font-weight: 500;
        }
        
        .logout-link:hover {
            background-color: rgba(255, 107, 107, 0.1) !important;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Boekhouding Applicatie</h1>
        <p>Overzicht van alle financiële transacties</p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="index.php" class="active">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
            <li><a href="balans.php">Balans</a></li>
            <?php if (is_admin()): ?>
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
                        <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <?php if (is_admin()): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Gebruikersbeheer</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Uitloggen</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <h2 class="section-title">Transactie Overzicht</h2>
        
        <div class="table-info">
            <strong>Gesorteerd op:</strong> <?php 
                $column_names = [
                    'date' => 'Datum',
                    'invoice_number' => 'Factuurnummer',
                    'description' => 'Omschrijving',
                    'amount' => 'Bedrag',
                    'type' => 'Type',
                    'category' => 'Categorie'
                ];
                if ($is_admin) {
                    $column_names['user'] = 'Gebruiker';
                }
                echo $column_names[$sort_column] . ' ' . ($sort_order == 'asc' ? '(oplopend)' : '(aflopend)');
            ?>
            <span style="margin-left: 1rem;">
                <a href="index.php" class="btn btn-secondary btn-sm">Standaard sortering</a>
            </span>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Totaal Inkomsten</h3>
                <div class="positive amount">€<?php echo number_format($totalInkomsten, 2); ?></div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Totaal Uitgaven</h3>
                <div class="negative amount">€<?php echo number_format($totalUitgaven, 2); ?></div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Balans</h3>
                <div class="amount <?php echo $balans >= 0 ? 'positive' : 'negative'; ?>">
                    €<?php echo number_format($balans, 2); ?>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                        <th class="sortable-header <?php echo $sort_column == 'user' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('user', $sort_column, $sort_order); ?>'">
                            Gebruiker
                            <span class="sort-indicator"><?php echo sort_indicator('user', $sort_column, $sort_order); ?></span>
                        </th>
                        <?php endif; ?>
                        <th class="sortable-header <?php echo $sort_column == 'date' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('date', $sort_column, $sort_order); ?>'">
                            Datum
                            <span class="sort-indicator"><?php echo sort_indicator('date', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'invoice_number' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('invoice_number', $sort_column, $sort_order); ?>'">
                            Factuurnr.
                            <span class="sort-indicator"><?php echo sort_indicator('invoice_number', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'description' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('description', $sort_column, $sort_order); ?>'">
                            Omschrijving
                            <span class="sort-indicator"><?php echo sort_indicator('description', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'amount' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('amount', $sort_column, $sort_order); ?>'">
                            Bedrag
                            <span class="sort-indicator"><?php echo sort_indicator('amount', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'type' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('type', $sort_column, $sort_order); ?>'">
                            Type
                            <span class="sort-indicator"><?php echo sort_indicator('type', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'category' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('category', $sort_column, $sort_order); ?>'">
                            Categorie
                            <span class="sort-indicator"><?php echo sort_indicator('category', $sort_column, $sort_order); ?></span>
                        </th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?php echo $is_admin ? '8' : '7'; ?>" style="text-align: center; padding: 2rem;">
                            <div class="alert alert-info">
                                Geen transacties gevonden. <a href="add.php">Voeg uw eerste transactie toe</a>.
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <?php if ($is_admin): ?>
                        <td>
                            <?php if (!empty($t['username'])): ?>
                                <span class="user-badge" title="<?php echo htmlspecialchars($t['user_full_name'] ?? $t['username']); ?>">
                                    <?php echo htmlspecialchars($t['username']); ?>
                                </span>
                            <?php else: ?>
                                <span class="neutral" style="font-style: italic; color: #666;">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo date('d-m-Y', strtotime($t['date'])); ?></td>
                        <td>
                            <?php if (!empty($t['invoice_number'])): ?>
                            <span class="invoice-number" title="Factuurnummer: <?php echo htmlspecialchars($t['invoice_number']); ?>">
                                <?php echo htmlspecialchars($t['invoice_number']); ?>
                            </span>
                            <?php else: ?>
                            <span class="neutral" style="font-style: italic; color: #666;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['description']); ?></td>
                        <td class="<?php
                            // Determine styling based on amount and type
                            if ($t['type'] == 'inkomst') {
                                echo $t['amount'] >= 0 ? 'positive' : 'negative';
                            } else {
                                echo $t['amount'] >= 0 ? 'negative' : 'positive';
                            }
                        ?>">
                            €<?php echo number_format($t['amount'], 2); ?>
                        </td>
                        <td>
                            <span class="<?php
                                if ($t['type'] == 'inkomst') {
                                    echo $t['amount'] >= 0 ? 'positive' : 'negative';
                                } else {
                                    echo $t['amount'] >= 0 ? 'negative' : 'positive';
                                }
                            ?>">
                                <?php
                                // Show special label for credit notes
                                if ($t['type'] == 'inkomst' && $t['amount'] < 0) {
                                    echo 'Creditnota (Inkomst)';
                                } elseif ($t['type'] == 'uitgave' && $t['amount'] < 0) {
                                    echo 'Credit (Uitgave)';
                                } else {
                                    echo ucfirst($t['type']);
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($t['category'] ?: 'Geen categorie'); ?></td>
                        <td>
                            <div class="btn-group">
                                <?php if (can_access_transaction($t['id'])): ?>
                                    <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                    <a href="delete.php?id=<?php echo $t['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Weet je zeker dat je deze transactie wilt verwijderen?')">
                                        Verwijderen
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm disabled" title="Geen toegang">Bewerken</span>
                                    <span class="btn btn-danger btn-sm disabled" title="Geen toegang">Verwijderen</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="btn-group">
            <a href="add.php" class="btn btn-primary">Nieuwe Transactie Toevoegen</a>
            <a href="profit_loss.php" class="btn btn-secondary">Kosten Baten Overzicht</a>
            <a href="btw_kwartaal.php" class="btn btn-secondary">BTW per Kwartaal</a>
            <a href="balans.php" class="btn btn-secondary">Balans Overzicht</a>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-title">Sortering instructies</h3>
            <p><strong>Klik op een kolomkop</strong> om te sorteren op die kolom:</p>
            <ul>
                <li><strong>Eerste klik:</strong> Sorteer oplopend (A-Z, 0-9, oud-nieuw)</li>
                <li><strong>Tweede klik:</strong> Sorteer aflopend (Z-A, 9-0, nieuw-oud)</li>
                <li><strong>Derde klik:</strong> Terug naar standaard sortering (datum oplopend)</li>
            </ul>
            <p>De huidige sortering wordt weergegeven boven de tabel met een pijl (↑ oplopend, ↓ aflopend).</p>
        </div>
    </main>

    <script>
        // Simple confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('a[href*="delete.php"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Weet je zeker dat je deze transactie wilt verwijderen?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Add hover effects to sortable headers
            const sortableHeaders = document.querySelectorAll('.sortable-header');
            sortableHeaders.forEach(header => {
                header.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
                });
                header.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active-sort')) {
                        this.style.backgroundColor = '';
                    }
                });
            });
            
            // Profile dropdown functionality
            const profileIcon = document.getElementById('profileIcon');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (profileIcon && profileDropdown) {
                // Toggle dropdown on click
                profileIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });
                
                // Close dropdown when clicking on a link inside it
                const dropdownLinks = profileDropdown.querySelectorAll('a');
                dropdownLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        profileDropdown.classList.remove('show');
                    });
                });
            }
        });
    </script>
</body>
</html>