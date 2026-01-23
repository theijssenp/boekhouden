<?php
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

// Determine date (default: current date)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check if VAT columns exist
$vatColumnsExist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'vat_percentage'");
    $vatColumnsExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $vatColumnsExist = false;
}

// Calculate totals up to the selected date (using base amounts excluding VAT)
// Add user-based filtering for non-admin users
if ($is_admin) {
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
            WHERE date <= ?
            GROUP BY type
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(amount) as total
            FROM transactions
            WHERE date <= ?
            GROUP BY type
        ");
    }
    $stmt->execute([$date]);
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
            WHERE date <= ? AND user_id = ?
            GROUP BY type
        ");
        $stmt->execute([$date, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                type,
                SUM(amount) as total
            FROM transactions
            WHERE date <= ? AND user_id = ?
            GROUP BY type
        ");
        $stmt->execute([$date, $user_id]);
    }
}
$totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAssets = 0;
$totalLiabilities = 0;
foreach ($totals as $row) {
    if ($row['type'] == 'inkomst') {
        $totalAssets += $row['total'];
    } else {
        $totalLiabilities += $row['total'];
    }
}

// Equity = Assets - Liabilities
$equity = $totalAssets - $totalLiabilities;

// Get cash balance (simplified: all transactions affect cash)
$cashBalance = $totalAssets - $totalLiabilities;

// For a real balance sheet, we'd need more detailed accounts
// This is a simplified version
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balans Overzicht - Boekhouden</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
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
        
        .balance-sheet {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        @media (max-width: 768px) {
            .balance-sheet {
                grid-template-columns: 1fr;
            }
        }
        .balance-column {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
        }
        .balance-column.assets {
            border-left: 4px solid var(--success-color);
        }
        .balance-column.liabilities {
            border-left: 4px solid var(--danger-color);
        }
        .balance-section {
            margin-bottom: 1.5rem;
        }
        .balance-section h3 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .balance-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-medium);
        }
        .balance-item.total {
            font-weight: bold;
            border-top: 2px solid var(--primary-color);
            border-bottom: none;
            margin-top: 0.5rem;
            padding-top: 1rem;
            background-color: var(--gray-light);
        }
        .balance-item.subtotal {
            font-weight: 600;
            background-color: var(--gray-light);
        }
        .balance-check {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin: 2rem 0;
            text-align: center;
        }
        .balance-check h3 {
            color: white;
            margin-bottom: 1rem;
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
                <h1>Balans Overzicht</h1>
                <p>Activa, passiva en eigen vermogen per datum</p>
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
            <li><a href="balans.php" class="active">Balans</a></li>
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
        <h2 class="section-title">Balans per <?php echo date('d-m-Y', strtotime($date)); ?></h2>
        
        <div class="filter-bar">
            <form method="get" class="filter-form" style="display: flex; gap: 1rem; align-items: center;">
                <div class="form-group" style="margin: 0;">
                    <label for="date" style="margin-right: 0.5rem;">Datum:</label>
                    <input type="date" id="date" name="date" class="form-control form-control-sm" 
                           value="<?php echo $date; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Toon Balans</button>
            </form>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Totaal Activa</h3>
                <div class="positive amount">€<?php echo number_format($totalAssets, 2); ?></div>
                <p class="neutral">Totale bezittingen</p>
            </div>
            
            <div class="card">
                <h3 class="card-title">Totaal Passiva</h3>
                <div class="negative amount">€<?php echo number_format($totalLiabilities, 2); ?></div>
                <p class="neutral">Totale schulden</p>
            </div>
            
            <div class="card" style="grid-column: span 2; background: linear-gradient(135deg, #2c3e50, #3498db); color: white;">
                <h3 class="card-title" style="color: white;">Eigen Vermogen</h3>
                <div class="amount" style="font-size: 2.5rem; color: white;">
                    €<?php echo number_format($equity, 2); ?>
                </div>
                <p style="font-size: 1.2rem; margin-top: 10px;">
                    <?php if ($equity > 0): ?>
                    <strong>Positief eigen vermogen</strong>
                    <?php elseif ($equity < 0): ?>
                    <strong>Negatief eigen vermogen</strong>
                    <?php else: ?>
                    <strong>Break-even</strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="balance-check">
            <h3>Balans Controle</h3>
            <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">
                Activa (€<?php echo number_format($totalAssets, 2); ?>) = Passiva (€<?php echo number_format($totalLiabilities, 2); ?>) + Eigen Vermogen (€<?php echo number_format($equity, 2); ?>)
            </p>
            <p style="font-size: 1.5rem; font-weight: bold;">
                <?php echo $totalAssets == ($totalLiabilities + $equity) ? '✓ BALANS KLOPT' : '✗ BALANS KLOPT NIET'; ?>
            </p>
            <?php if ($totalAssets != ($totalLiabilities + $equity)): ?>
            <p style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                Verschil: €<?php echo number_format(abs($totalAssets - ($totalLiabilities + $equity)), 2); ?>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="balance-sheet">
            <div class="balance-column assets">
                <div class="balance-section">
                    <h3>Activa (Bezittingen)</h3>
                    
                    <div class="balance-item">
                        <span>Liquide middelen</span>
                        <span class="positive">€<?php echo number_format($cashBalance, 2); ?></span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Vorderingen</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Voorraden</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Vaste activa</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Overige activa</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item total">
                        <span>Totaal Activa</span>
                        <span class="positive">€<?php echo number_format($totalAssets, 2); ?></span>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <p><strong>Toelichting activa:</strong></p>
                    <ul style="margin: 0.5rem 0 0 1rem;">
                        <li>Liquide middelen: contant geld en banktegoeden</li>
                        <li>Vorderingen: nog te ontvangen bedragen</li>
                        <li>Voorraden: grondstoffen, halffabricaten, eindproducten</li>
                        <li>Vaste activa: gebouwen, machines, voertuigen</li>
                    </ul>
                </div>
            </div>
            
            <div class="balance-column liabilities">
                <div class="balance-section">
                    <h3>Passiva & Eigen Vermogen</h3>
                    
                    <h4 style="color: var(--danger-color); margin-top: 1rem;">Schulden (Passiva)</h4>
                    <div class="balance-item">
                        <span>Crediteuren</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Te betalen belastingen</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Leningen</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Overige schulden</span>
                        <span class="negative">€<?php echo number_format($totalLiabilities, 2); ?></span>
                    </div>
                    
                    <div class="balance-item subtotal">
                        <span>Totaal Schulden</span>
                        <span class="negative">€<?php echo number_format($totalLiabilities, 2); ?></span>
                    </div>
                    
                    <h4 style="color: var(--success-color); margin-top: 1.5rem;">Eigen Vermogen</h4>
                    <div class="balance-item">
                        <span>Ingesteld kapitaal</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Agioreserve</span>
                        <span class="neutral">€0,00</span>
                    </div>
                    
                    <div class="balance-item">
                        <span>Winst/Verlies <?php echo date('Y', strtotime($date)); ?></span>
                        <span class="<?php echo $equity >= 0 ? 'positive' : 'negative'; ?>">
                            €<?php echo number_format($equity, 2); ?>
                        </span>
                    </div>
                    
                    <div class="balance-item total">
                        <span>Totaal Passiva & Eigen Vermogen</span>
                        <span class="<?php echo ($totalLiabilities + $equity) >= 0 ? 'positive' : 'negative'; ?>">
                            €<?php echo number_format($totalLiabilities + $equity, 2); ?>
                        </span>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <p><strong>Toelichting passiva:</strong></p>
                    <ul style="margin: 0.5rem 0 0 1rem;">
                        <li>Crediteuren: nog te betalen leveranciers</li>
                        <li>Belastingen: verschuldigde BTW, inkomstenbelasting</li>
                        <li>Leningen: bankleningen, hypotheken</li>
                        <li>Eigen vermogen: eigen middelen in de onderneming</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">Financiële Ratio's</h3>
            <div class="card-grid">
                <div class="card">
                    <h4 class="card-title">Solvabiliteit</h4>
                    <div class="amount" style="font-size: 1.8rem;">
                        <?php 
                        $solvability = $totalAssets > 0 ? ($equity / $totalAssets) * 100 : 0;
                        echo number_format($solvability, 1) . '%';
                        ?>
                    </div>
                    <p class="neutral">
                        <?php 
                        if ($solvability >= 30) {
                            echo 'Goed (≥30%)';
                        } elseif ($solvability >= 20) {
                            echo 'Redelijk (20-30%)';
                        } else {
                            echo 'Laag (<20%)';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="card">
                    <h4 class="card-title">Current Ratio</h4>
                    <div class="amount" style="font-size: 1.8rem;">
                        <?php 
                        $currentAssets = $cashBalance; // Simplified
                        $currentLiabilities = $totalLiabilities; // Simplified
                        $currentRatio = $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 99;
                        echo number_format($currentRatio, 2);
                        ?>
                    </div>
                    <p class="neutral">
                        <?php 
                        if ($currentRatio >= 1.5) {
                            echo 'Goed (≥1.5)';
                        } elseif ($currentRatio >= 1.0) {
                            echo 'Acceptabel (1.0-1.5)';
                        } else {
                            echo 'Risicovol (<1.0)';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="card">
                    <h4 class="card-title">Rentabiliteit</h4>
                    <div class="amount" style="font-size: 1.8rem;">
                        <?php 
                        $profitability = $totalAssets > 0 ? ($equity / $totalAssets) * 100 : 0;
                        echo number_format($profitability, 1) . '%';
                        ?>
                    </div>
                    <p class="neutral">
                        <?php 
                        if ($profitability >= 10) {
                            echo 'Hoog (≥10%)';
                        } elseif ($profitability >= 5) {
                            echo 'Gemiddeld (5-10%)';
                        } else {
                            echo 'Laag (<5%)';
                        }
                        ?>
                    </p>
                </div>
            </div>
            
            <div class="alert alert-warning" style="margin-top: 1.5rem;">
                <p><strong>Interpretatie ratio's:</strong></p>
                <ul>
                    <li><strong>Solvabiliteit:</strong> Mate waarin eigen vermogen de activa dekt (ideaal: ≥30%)</li>
                    <li><strong>Current Ratio:</strong> Kortetermijn liquiditeit (ideaal: ≥1.5)</li>
                    <li><strong>Rentabiliteit:</strong> Rendement op geïnvesteerd vermogen (ideaal: ≥10%)</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">Balans Toelichting</h3>
            <div class="alert alert-info">
                <p><strong>Dit is een vereenvoudigde balans.</strong> In een volledig boekhoudsysteem zouden activa en passiva verder worden onderverdeeld in specifieke rekeningen volgens het grootboek.</p>
                <p>De berekening is gebaseerd op alle transacties tot en met <?php echo date('d-m-Y', strtotime($date)); ?>.</p>
            </div>
            
            <div class="alert alert-warning">
                <p><strong>Belangrijke aantekeningen:</strong></p>
                <ul>
                    <li>Deze balans is gebaseerd op kasstroom (alle transacties beïnvloeden direct liquide middelen)</li>
                    <li>In een volledig systeem worden activa en passiva gescheiden bijgehouden</li>
                    <li>Voor een officiële jaarrekening is aanvullende informatie nodig</li>
                    <li>Controleer altijd of de balans klopt: Activa = Passiva + Eigen Vermogen</li>
                </ul>
            </div>
        </div>
        
        <div class="btn-group">
            <a href="profit_loss.php?year=<?php echo date('Y', strtotime($date)); ?>" class="btn btn-secondary">Winst & Verlies</a>
            <a href="btw_kwartaal.php?year=<?php echo date('Y', strtotime($date)); ?>" class="btn btn-secondary">BTW Overzicht</a>
            <a href="../index.php" class="btn btn-primary">Terug naar Transacties</a>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date to today if not set
            const dateInput = document.getElementById('date');
            if (!dateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.value = today;
            }
            
            // Auto-submit form when date changes on mobile
            function checkAndSubmit() {
                // On mobile, auto-submit for better UX
                if (window.innerWidth < 768) {
                    document.querySelector('.filter-form').submit();
                }
            }
            
            dateInput.addEventListener('change', checkAndSubmit);
            
            // Add interactivity to balance items
            const balanceItems = document.querySelectorAll('.balance-item');
            balanceItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(0, 0, 0, 0.03)';
                });
                item.addEventListener('mouseleave', function() {
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