<?php
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

// Determine period (default: current year)
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Calculate quarters
$quarters = [
    1 => ['start' => "$year-01-01", 'end' => "$year-03-31", 'name' => "Q1 ($year)"],
    2 => ['start' => "$year-04-01", 'end' => "$year-06-30", 'name' => "Q2 ($year)"],
    3 => ['start' => "$year-07-01", 'end' => "$year-09-30", 'name' => "Q3 ($year)"],
    4 => ['start' => "$year-10-01", 'end' => "$year-12-31", 'name' => "Q4 ($year)"]
];

// Check if VAT columns exist
$vatColumnsExist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'vat_percentage'");
    $vatColumnsExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $vatColumnsExist = false;
}

// Get total income and expenses for the year (using base amounts excluding VAT)
// Add user-based filtering for non-admin users
if ($is_admin) {
    if ($vatColumnsExist) {
        // Calculate with VAT adjustments - admin sees all transactions
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount / (1 + (vat_percentage / 100))
                        ELSE amount
                    END
                ) as total,
                COUNT(*) as count
            FROM transactions
            WHERE YEAR(date) = ?
            GROUP BY type
        ");
    } else {
        // Without VAT columns - admin sees all transactions
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(amount) as total,
                COUNT(*) as count
            FROM transactions
            WHERE YEAR(date) = ?
            GROUP BY type
        ");
    }
    $stmt->execute([$year]);
} else {
    // Regular user - only see their own transactions
    if ($vatColumnsExist) {
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount / (1 + (vat_percentage / 100))
                        ELSE amount
                    END
                ) as total,
                COUNT(*) as count
            FROM transactions
            WHERE YEAR(date) = ? AND user_id = ?
            GROUP BY type
        ");
        $stmt->execute([$year, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(amount) as total,
                COUNT(*) as count
            FROM transactions
            WHERE YEAR(date) = ? AND user_id = ?
            GROUP BY type
        ");
        $stmt->execute([$year, $user_id]);
    }
}
$yearTotals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalIncome = 0;
$totalExpenses = 0;
foreach ($yearTotals as $row) {
    if ($row['type'] == 'inkomst') {
        $totalIncome = $row['total'];
    } else {
        $totalExpenses = $row['total'];
    }
}
$profit = $totalIncome - $totalExpenses;

// Get quarterly breakdown
$quarterlyData = [];
foreach ($quarters as $q => $quarter) {
    if ($is_admin) {
        if ($vatColumnsExist) {
            // Calculate with VAT adjustments - admin sees all transactions
            $stmt = $pdo->prepare("
                SELECT
                    type,
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount / (1 + (vat_percentage / 100))
                            ELSE amount
                        END
                    ) as total
                FROM transactions
                WHERE date BETWEEN ? AND ?
                GROUP BY type
            ");
        } else {
            // Without VAT columns - admin sees all transactions
            $stmt = $pdo->prepare("
                SELECT
                    type,
                    SUM(amount) as total
                FROM transactions
                WHERE date BETWEEN ? AND ?
                GROUP BY type
            ");
        }
        $stmt->execute([$quarter['start'], $quarter['end']]);
    } else {
        // Regular user - only see their own transactions
        if ($vatColumnsExist) {
            $stmt = $pdo->prepare("
                SELECT
                    type,
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount / (1 + (vat_percentage / 100))
                            ELSE amount
                        END
                    ) as total
                FROM transactions
                WHERE date BETWEEN ? AND ? AND user_id = ?
                GROUP BY type
            ");
            $stmt->execute([$quarter['start'], $quarter['end'], $user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    type,
                    SUM(amount) as total
                FROM transactions
                WHERE date BETWEEN ? AND ? AND user_id = ?
                GROUP BY type
            ");
            $stmt->execute([$quarter['start'], $quarter['end'], $user_id]);
        }
    }
    $qData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $qIncome = 0;
    $qExpenses = 0;
    foreach ($qData as $row) {
        if ($row['type'] == 'inkomst') {
            $qIncome = $row['total'];
        } else {
            $qExpenses = $row['total'];
        }
    }
    
    $quarterlyData[$q] = [
        'name' => $quarter['name'],
        'income' => $qIncome,
        'expenses' => $qExpenses,
        'profit' => $qIncome - $qExpenses
    ];
}

// Get category breakdown
if ($is_admin) {
    if ($vatColumnsExist) {
        $stmt = $pdo->prepare("
            SELECT
                c.name as category,
                t.type,
                SUM(
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END
                ) as total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE YEAR(t.date) = ?
            GROUP BY c.name, t.type
            ORDER BY t.type DESC, total DESC
        ");
        $stmt->execute([$year]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                c.name as category,
                t.type,
                SUM(t.amount) as total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE YEAR(t.date) = ?
            GROUP BY c.name, t.type
            ORDER BY t.type DESC, total DESC
        ");
        $stmt->execute([$year]);
    }
} else {
    // Regular user - only see their own transactions
    if ($vatColumnsExist) {
        $stmt = $pdo->prepare("
            SELECT
                c.name as category,
                t.type,
                SUM(
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END
                ) as total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE YEAR(t.date) = ? AND t.user_id = ?
            GROUP BY c.name, t.type
            ORDER BY t.type DESC, total DESC
        ");
        $stmt->execute([$year, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                c.name as category,
                t.type,
                SUM(t.amount) as total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE YEAR(t.date) = ? AND t.user_id = ?
            GROUP BY c.name, t.type
            ORDER BY t.type DESC, total DESC
        ");
        $stmt->execute([$year, $user_id]);
    }
}
$categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kosten Baten Overzicht - Boekhouden</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
                <h1>Kosten Baten Overzicht</h1>
                <p>Winst- en verliesrekening per jaar en kwartaal</p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php" class="active">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
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
        <h2 class="section-title">Jaaroverzicht <?php echo $year; ?></h2>
        
        <div class="filter-bar">
            <form method="get" class="filter-form" style="display: flex; gap: 1rem; align-items: center;">
                <div class="form-group" style="margin: 0;">
                    <label for="year" style="margin-right: 0.5rem;">Jaar:</label>
                    <select id="year" name="year" class="form-control form-control-sm">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Toon Jaar</button>
            </form>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Totale Inkomsten</h3>
                <div class="positive amount">€<?php echo number_format($totalIncome, 2); ?></div>
                <p class="neutral">Alle inkomsten in <?php echo $year; ?></p>
            </div>
            
            <div class="card">
                <h3 class="card-title">Totale Uitgaven</h3>
                <div class="negative amount">€<?php echo number_format($totalExpenses, 2); ?></div>
                <p class="neutral">Alle uitgaven in <?php echo $year; ?></p>
            </div>
            
            <div class="card" style="grid-column: span 2; background: linear-gradient(135deg, #2c3e50, #3498db); color: white;">
                <h3 class="card-title" style="color: white;">Jaarresultaat</h3>
                <div class="amount" style="font-size: 2.5rem; color: white;">
                    €<?php echo number_format($profit, 2); ?>
                </div>
                <p style="font-size: 1.2rem; margin-top: 10px;">
                    <?php if ($profit > 0): ?>
                    <strong>Winst in <?php echo $year; ?></strong>
                    <?php elseif ($profit < 0): ?>
                    <strong>Verlies in <?php echo $year; ?></strong>
                    <?php else: ?>
                    <strong>Break-even in <?php echo $year; ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">Per Kwartaal</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kwartaal</th>
                            <th>Inkomsten</th>
                            <th>Uitgaven</th>
                            <th>Resultaat</th>
                            <th>Marge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quarterlyData as $qData): ?>
                        <?php 
                        $margin = $qData['income'] > 0 ? ($qData['profit'] / $qData['income']) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo $qData['name']; ?></strong></td>
                            <td class="positive">€<?php echo number_format($qData['income'], 2); ?></td>
                            <td class="negative">€<?php echo number_format($qData['expenses'], 2); ?></td>
                            <td class="<?php echo $qData['profit'] >= 0 ? 'positive' : 'negative'; ?>">
                                <strong>€<?php echo number_format($qData['profit'], 2); ?></strong>
                            </td>
                            <td class="<?php echo $margin >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo number_format($margin, 1); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td><strong>Totaal <?php echo $year; ?></strong></td>
                            <td class="positive">€<?php echo number_format($totalIncome, 2); ?></td>
                            <td class="negative">€<?php echo number_format($totalExpenses, 2); ?></td>
                            <td class="<?php echo $profit >= 0 ? 'positive' : 'negative'; ?>">
                                €<?php echo number_format($profit, 2); ?>
                            </td>
                            <td class="<?php echo ($totalIncome > 0 ? ($profit / $totalIncome) * 100 : 0) >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $totalIncome > 0 ? number_format(($profit / $totalIncome) * 100, 1) : '0.0'; ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">Per Categorie</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Categorie</th>
                            <th>Type</th>
                            <th>Bedrag</th>
                            <th>Aandeel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryData as $row): ?>
                        <?php 
                        $percentage = 0;
                        if ($row['type'] == 'inkomst' && $totalIncome > 0) {
                            $percentage = ($row['total'] / $totalIncome) * 100;
                        } elseif ($row['type'] == 'uitgave' && $totalExpenses > 0) {
                            $percentage = ($row['total'] / $totalExpenses) * 100;
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category'] ?: 'Geen categorie'); ?></td>
                            <td>
                                <span class="<?php
                                    if ($row['type'] == 'inkomst') {
                                        echo $row['total'] >= 0 ? 'positive' : 'negative';
                                    } else {
                                        echo $row['total'] >= 0 ? 'negative' : 'positive';
                                    }
                                ?>">
                                    <?php
                                    // Show special label for credit notes
                                    if ($row['type'] == 'inkomst' && $row['total'] < 0) {
                                        echo 'Creditnota (Inkomst)';
                                    } elseif ($row['type'] == 'uitgave' && $row['total'] < 0) {
                                        echo 'Credit (Uitgave)';
                                    } else {
                                        echo ucfirst($row['type']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="<?php
                                if ($row['type'] == 'inkomst') {
                                    echo $row['total'] >= 0 ? 'positive' : 'negative';
                                } else {
                                    echo $row['total'] >= 0 ? 'negative' : 'positive';
                                }
                            ?>">
                                €<?php echo number_format($row['total'], 2); ?>
                            </td>
                            <td class="neutral">
                                <?php echo number_format($percentage, 1); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categoryData)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">
                                <div class="alert alert-info">
                                    Geen categoriedata beschikbaar voor <?php echo $year; ?>.
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">Financiële Analyse</h3>
            <div class="alert alert-info">
                <p><strong>Financiële gezondheid indicatoren:</strong></p>
                <ul>
                    <li><strong>Winstmarge:</strong> <?php echo $totalIncome > 0 ? number_format(($profit / $totalIncome) * 100, 1) : '0.0'; ?>% 
                        (<?php echo ($profit / $totalIncome) * 100 >= 10 ? 'Goed' : (($profit / $totalIncome) * 100 >= 5 ? 'Redelijk' : 'Laag'); ?>)</li>
                    <li><strong>Kostenratio:</strong> <?php echo $totalIncome > 0 ? number_format(($totalExpenses / $totalIncome) * 100, 1) : '0.0'; ?>% 
                        (<?php echo ($totalExpenses / $totalIncome) * 100 <= 80 ? 'Goed' : (($totalExpenses / $totalIncome) * 100 <= 90 ? 'Redelijk' : 'Hoog'); ?>)</li>
                    <li><strong>Groei potentieel:</strong> 
                        <?php 
                        $bestQuarter = 0;
                        $bestQuarterProfit = 0;
                        foreach ($quarterlyData as $q => $qData) {
                            if ($qData['profit'] > $bestQuarterProfit) {
                                $bestQuarterProfit = $qData['profit'];
                                $bestQuarter = $q;
                            }
                        }
                        echo $bestQuarter > 0 ? "Beste kwartaal: Q$bestQuarter" : "Geen groei gegevens";
                        ?>
                    </li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <p><strong>Aanbevelingen:</strong></p>
                <ul>
                    <?php if ($profit < 0): ?>
                    <li>Focus op kostenreductie in hoogste uitgavecategorieën</li>
                    <li>Overweeg inkomstenbronnen te diversifiëren</li>
                    <li>Analyseer kwartalen met grootste verliezen</li>
                    <?php elseif (($profit / $totalIncome) * 100 < 5): ?>
                    <li>Winstmarge is laag - optimaliseer operationele efficiëntie</li>
                    <li>Overweeg prijsaanpassingen of volume-uitbreiding</li>
                    <?php else: ?>
                    <li>Financiële gezondheid is goed - focus op groei en consolidatie</li>
                    <li>Overweeg investeringen in meest winstgevende gebieden</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="btn-group">
            <a href="btw_kwartaal.php?year=<?php echo $year; ?>" class="btn btn-secondary">BTW per Kwartaal</a>
            <a href="balans.php?date=<?php echo $year; ?>-12-31" class="btn btn-secondary">Jaareind Balans</a>
            <a href="../index.php" class="btn btn-primary">Terug naar Transacties</a>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when year changes on mobile
            const yearSelect = document.getElementById('year');
            
            function checkAndSubmit() {
                // On mobile, auto-submit for better UX
                if (window.innerWidth < 768) {
                    document.querySelector('.filter-form').submit();
                }
            }
            
            yearSelect.addEventListener('change', checkAndSubmit);
            
            // Add some interactivity to the table rows
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(52, 152, 219, 0.05)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
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
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #666; font-size: 12px; border-top: 1px solid #eee;">
        powered by P. Theijssen
    </footer>
</body>
</html>