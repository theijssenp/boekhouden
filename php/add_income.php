<?php
/**
 * Verkoop Boeken - Nieuwe Inkomst/Omzet Toevoegen
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

// Get active debiteuren (only for current user or admin)
if ($is_admin) {
    $stmt_relations = $pdo->query("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('debiteur', 'beide')
          AND is_active = TRUE
        ORDER BY company_name
    ");
} else {
    $stmt_relations = $pdo->prepare("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('debiteur', 'beide')
          AND is_active = TRUE
          AND (user_id = ? OR user_id IS NULL)
        ORDER BY company_name
    ");
    $stmt_relations->execute([$user_id]);
}
$debiteuren = $stmt_relations->fetchAll(PDO::FETCH_ASSOC);

// Get VAT rates for the default date (today)
$default_date = date('Y-m-d');

// Function to get applicable VAT rates for a specific date
function get_vat_rates_for_date($pdo, $date) {
    $stmt = $pdo->prepare("
        SELECT
            rate,
            name,
            MAX(description) as description
        FROM vat_rates
        WHERE is_active = TRUE
          AND effective_from <= ?
          AND (effective_to IS NULL OR effective_to >= ?)
        GROUP BY rate, name
        ORDER BY rate DESC
    ");
    $stmt->execute([$date, $date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$vat_rates = get_vat_rates_for_date($pdo, $default_date);

// If no VAT rates found, use defaults
if (empty($vat_rates)) {
    $vat_rates = [
        ['rate' => 21, 'name' => 'Hoog tarief', 'description' => 'Standaard BTW tarief'],
        ['rate' => 9, 'name' => 'Verlaagd tarief', 'description' => 'Verlaagd BTW tarief'],
        ['rate' => 0, 'name' => 'Vrijgesteld', 'description' => 'Geen BTW']
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $type = 'inkomst'; // Always income for this form
    $category_id = 1; // Always "Inkomsten" category
    $vat_percentage = $_POST['vat_percentage'] ?? 0;
    $vat_included = isset($_POST['vat_included']) ? 1 : 0;
    $vat_deductible = 0; // Never deductible for income
    $invoice_number = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null;
    $relation_id = !empty($_POST['relation_id']) ? $_POST['relation_id'] : null;

    // Auto-generate invoice number for income if not provided
    if (empty($invoice_number)) {
        $invoice_number = generate_next_invoice_number($user_id);
    }

    // Add user_id and relation_id to the transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (date, description, amount, type, category_id, vat_percentage, vat_included, vat_deductible, invoice_number, user_id, relation_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $user_id, $relation_id]);

    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verkoop Boeken - Boekhouden</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Relation add link styling */
        .relation-add-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .relation-add-link:hover {
            color: #2c3e50;
            text-decoration: underline;
        }
        
        .relation-add-link i {
            margin-right: 3px;
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
                <h1>Verkoop Boeken</h1>
                <p>Nieuwe omzet/verkoop registreren met automatische facturering</p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Overzicht</a></li>
            <li><a href="add_income.php" class="active">Verkoop Boeken</a></li>
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
        <h2 class="section-title">Verkoopgegevens</h2>
        
        <form method="post" class="transaction-form">
            <div class="card">
                <h3 class="card-title">Basisgegevens</h3>
                
                <div class="form-group">
                    <label for="date">Datum *</label>
                    <input type="date" id="date" name="date" class="form-control" required 
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Omschrijving *</label>
                    <input type="text" id="description" name="description" class="form-control"
                           placeholder="Bijv. Verkoop product, Omzet dienstverlening" required>
                </div>
                
                <div class="form-group">
                    <label for="relation_id">
                        <i class="fas fa-address-book"></i> Debiteur (Klant)
                    </label>
                    <select id="relation_id" name="relation_id" class="form-control">
                        <option value="">Diverse Klanten (geen vaste relatie)</option>
                        <?php foreach ($debiteuren as $debiteur): ?>
                        <option value="<?php echo $debiteur['id']; ?>"
                                title="<?php echo htmlspecialchars($debiteur['email'] ?? ''); ?> - <?php echo htmlspecialchars($debiteur['city'] ?? ''); ?>">
                            <?php echo htmlspecialchars($debiteur['relation_code']); ?> - <?php echo htmlspecialchars($debiteur['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">
                        Optioneel: Koppel deze verkoop aan een klant.
                        <a href="add_relation.php?type=debiteur&return=add_income.php" class="relation-add-link">
                            <i class="fas fa-plus-circle"></i> Nieuwe debiteur toevoegen
                        </a>
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="invoice_number">Factuurnummer <span id="invoice_number_label_auto">(automatisch gegenereerd)</span></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           placeholder="Wordt automatisch gegenereerd..." readonly
                           style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="form-text">Factuurnummer wordt automatisch gegenereerd bij opslaan</small>
                </div>
                
                <div class="form-group">
                    <label for="amount">Bedrag excl. BTW (€) *</label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           step="0.01" placeholder="0.00" required>
                    <small class="form-text">Voer het bedrag exclusief BTW in (BTW wordt berekend)</small>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">BTW Instellingen</h3>
                
                <div class="form-group">
                    <label for="vat_percentage">BTW Percentage</label>
                    <select id="vat_percentage" name="vat_percentage" class="form-control">
                        <?php foreach ($vat_rates as $rate): ?>
                        <option value="<?php echo $rate['rate']; ?>"
                                <?php echo $rate['rate'] == 21 ? 'selected' : ''; ?>
                                title="<?php echo htmlspecialchars($rate['description']); ?>">
                            <?php echo $rate['rate']; ?>% (<?php echo htmlspecialchars($rate['name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">BTW tarieven gelden voor de geselecteerde datum</small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="vat_included" name="vat_included" value="1">
                    <label for="vat_included">Bedrag is inclusief BTW</label>
                </div>
                
                <div class="alert alert-info">
                    <strong>Let op:</strong> Voor verkopen is BTW altijd af te dragen aan de Belastingdienst.
                </div>
                
                <div id="vatCalculationDisplay" class="alert alert-info" style="display: none;">
                    <strong>BTW berekening:</strong> <span id="vatCalculationText">Voer bedrag en BTW percentage in</span>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    Verkoop Opslaan
                </button>
                <a href="../index.php" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
        
        <div class="alert alert-info" style="margin-top: 2rem;">
            <strong>BTW Berekeningswijze:</strong><br>
            - <strong>Inclusief BTW:</strong> BTW wordt berekend van het ingevoerde bedrag<br>
            - <strong>Exclusief BTW:</strong> BTW wordt opgeteld bij het ingevoerde bedrag (aanbevolen)<br>
            - <strong>Verkoop BTW:</strong> BTW is altijd verschuldigd aan de Belastingdienst
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            const vatPercentageSelect = document.getElementById('vat_percentage');
            const invoiceNumberInput = document.getElementById('invoice_number');
            
            // Update VAT rates based on selected date
            async function updateVatRatesForDate(selectedDate) {
                try {
                    const response = await fetch(`get_vat_rates.php?date=${selectedDate}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    
                    const rates = await response.json();
                    
                    // Clear existing options
                    vatPercentageSelect.innerHTML = '';
                    
                    // Add new options
                    rates.forEach(rate => {
                        const option = document.createElement('option');
                        option.value = rate.rate;
                        option.textContent = `${rate.rate}% (${rate.name})`;
                        option.title = rate.description;
                        
                        // Select 21% by default, or first rate if 21% not available
                        if (rate.rate == 21 || (rates.length > 0 && rates[0].rate == rate.rate)) {
                            option.selected = true;
                        }
                        
                        vatPercentageSelect.appendChild(option);
                    });
                    
                    // Show notification if rates changed
                    if (rates.length > 0) {
                        console.log(`VAT rates updated for ${selectedDate}`);
                    }
                } catch (error) {
                    console.error('Error fetching VAT rates:', error);
                    // Keep existing rates if fetch fails
                }
            }
            
            // Calculate VAT on the fly and update display
            function updateVatCalculation() {
                const amountInput = document.getElementById('amount');
                const vatPercentageSelect = document.getElementById('vat_percentage');
                const vatIncludedCheckbox = document.getElementById('vat_included');
                const vatCalculationDisplay = document.getElementById('vatCalculationDisplay');
                const vatCalculationText = document.getElementById('vatCalculationText');
                
                const amount = parseFloat(amountInput.value) || 0;
                const vatRate = parseFloat(vatPercentageSelect.value) || 0;
                const vatIncluded = vatIncludedCheckbox.checked;
                
                if (vatRate > 0 && amount !== 0) {
                    let vatAmount, baseAmount, totalAmount;
                    let calculationText = '';
                    
                    if (vatIncluded) {
                        baseAmount = amount / (1 + (vatRate / 100));
                        vatAmount = amount - baseAmount;
                        calculationText = `€${amount.toFixed(2)} inclusief ${vatRate}% BTW = €${baseAmount.toFixed(2)} excl. BTW + €${vatAmount.toFixed(2)} BTW`;
                    } else {
                        vatAmount = amount * (vatRate / 100);
                        totalAmount = amount + vatAmount;
                        calculationText = `€${amount.toFixed(2)} excl. ${vatRate}% BTW = €${totalAmount.toFixed(2)} incl. BTW (€${amount.toFixed(2)} + €${vatAmount.toFixed(2)} BTW)`;
                    }
                    
                    vatCalculationText.textContent = calculationText;
                    vatCalculationDisplay.style.display = 'block';
                } else {
                    vatCalculationDisplay.style.display = 'none';
                }
            }
            
            // Fetch next invoice number on page load
            async function fetchNextInvoiceNumber() {
                try {
                    const response = await fetch('get_next_invoice_number.php');
                    if (response.ok) {
                        const data = await response.json();
                        invoiceNumberInput.value = data.invoice_number;
                    }
                } catch (error) {
                    console.error('Error fetching invoice number:', error);
                    // Fallback: generate client-side placeholder
                    const year = new Date().getFullYear();
                    invoiceNumberInput.value = `${year}-0001`;
                }
            }
            
            // Event listeners
            dateInput.addEventListener('change', function() {
                updateVatRatesForDate(this.value);
            });
            
            // Add event listeners for VAT calculation
            document.getElementById('amount').addEventListener('input', updateVatCalculation);
            document.getElementById('vat_percentage').addEventListener('change', updateVatCalculation);
            document.getElementById('vat_included').addEventListener('change', updateVatCalculation);
            
            // Set today's date as default and update VAT rates
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            updateVatRatesForDate(today);
            
            // Fetch next invoice number
            fetchNextInvoiceNumber();
        });
        
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
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
