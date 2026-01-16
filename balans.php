<?php
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
    <link rel="stylesheet" href="style.css">
    <style>
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
        <h1>Balans Overzicht</h1>
        <p>Activa, passiva en eigen vermogen per datum</p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
            <li><a href="balans.php" class="active">Balans</a></li>
        </ul>
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
            <a href="profit_loss.php?year=<?php echo date('Y', strtotime($date)); ?>" class="btn btn-secondary">Kosten Baten Overzicht</a>
            <a href="btw_kwartaal.php?year=<?php echo date('Y', strtotime($date)); ?>" class="btn btn-secondary">BTW per Kwartaal</a>
            <a href="index.php" class="btn btn-primary">Terug naar Transacties</a>
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
        });
    </script>
</body>
</html>