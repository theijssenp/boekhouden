<?php
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

$id = $_GET['id'];

// Check if user can access this transaction
if ($is_admin) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    header('Location: index.php');
    exit;
}

// Get categories accessible to the current user
if ($is_admin) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM categories
        WHERE is_system = 1 OR user_id = ?
        ORDER BY name
    ");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

// Get VAT rates for the transaction date
$transaction_date = $transaction['date'];
$vat_rates = get_vat_rates_for_date($pdo, $transaction_date);

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
    $type = $_POST['type'];
    $category_id = $_POST['category_id'];
    $vat_percentage = $_POST['vat_percentage'] ?? 0;
    $vat_included = isset($_POST['vat_included']) ? 1 : 0;
    $vat_deductible = isset($_POST['vat_deductible']) ? 1 : 0;
    $invoice_number = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null;

    // Update transaction with user_id check for non-admin users
    if ($is_admin) {
        $stmt = $pdo->prepare("UPDATE transactions SET date = ?, description = ?, amount = ?, type = ?, category_id = ?, vat_percentage = ?, vat_included = ?, vat_deductible = ?, invoice_number = ? WHERE id = ?");
        $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE transactions SET date = ?, description = ?, amount = ?, type = ?, category_id = ?, vat_percentage = ?, vat_included = ?, vat_deductible = ?, invoice_number = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $id, $user_id]);
    }

    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactie Bewerken - Boekhouden</title>
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
        <h1>Transactie Bewerken</h1>
        <p>Bewerk transactie #<?php echo $transaction['id']; ?> van <?php echo date('d-m-Y', strtotime($transaction['date'])); ?></p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
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
        <h2 class="section-title">Transactiegegevens Bewerken</h2>
        
        <div class="alert alert-info">
            <strong>Transactie ID:</strong> #<?php echo $transaction['id']; ?> | 
            <strong>Aangemaakt:</strong> <?php echo date('d-m-Y H:i', strtotime($transaction['created_at'])); ?>
        </div>
        
        <form method="post" class="transaction-form">
            <div class="card">
                <h3 class="card-title">Basisgegevens</h3>
                
                <div class="form-group">
                    <label for="date">Datum *</label>
                    <input type="date" id="date" name="date" class="form-control" 
                           value="<?php echo $transaction['date']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Omschrijving *</label>
                    <input type="text" id="description" name="description" class="form-control"
                           value="<?php echo htmlspecialchars($transaction['description']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="invoice_number">Factuurnummer (optioneel)</label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           value="<?php echo isset($transaction['invoice_number']) ? htmlspecialchars($transaction['invoice_number']) : ''; ?>"
                           placeholder="Bijv. FACT-2024-001, INV-12345" maxlength="50">
                    <small class="form-text">Voer het factuurnummer in voor betere administratie</small>
                </div>
                
                <div class="form-group">
                    <label for="amount">Bedrag (€) *</label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           step="0.01" value="<?php echo $transaction['amount']; ?>" required>
                    <small class="form-text">Positief voor normale transacties, negatief voor creditnota's</small>
                </div>
                
                <div class="form-group">
                    <label for="type">Type *</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="inkomst" <?php echo $transaction['type'] == 'inkomst' ? 'selected' : ''; ?>>Inkomst</option>
                        <option value="uitgave" <?php echo $transaction['type'] == 'uitgave' ? 'selected' : ''; ?>>Uitgave</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Categorie</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Geen categorie</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                            <?php echo $transaction['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">BTW Instellingen</h3>
                
                <div class="form-group">
                    <label for="vat_percentage">BTW Percentage</label>
                    <select id="vat_percentage" name="vat_percentage" class="form-control">
                        <?php foreach ($vat_rates as $rate): ?>
                        <option value="<?php echo $rate['rate']; ?>"
                                <?php echo (isset($transaction['vat_percentage']) && $transaction['vat_percentage'] == $rate['rate']) ? 'selected' : ''; ?>
                                title="<?php echo htmlspecialchars($rate['description']); ?>">
                            <?php echo $rate['rate']; ?>% (<?php echo htmlspecialchars($rate['name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">BTW tarieven gelden voor de geselecteerde datum</small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="vat_included" name="vat_included" value="1" 
                        <?php echo (isset($transaction['vat_included']) && $transaction['vat_included']) ? 'checked' : ''; ?>>
                    <label for="vat_included">Bedrag is inclusief BTW</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="vat_deductible" name="vat_deductible" value="1" 
                        <?php echo (isset($transaction['vat_deductible']) && $transaction['vat_deductible']) ? 'checked' : ''; ?>>
                    <label for="vat_deductible">BTW is aftrekbaar (alleen voor uitgaven)</label>
                </div>
                
                <div id="vatCalculationDisplay" class="alert alert-info" style="display: none;">
                    <strong>BTW berekening:</strong> <span id="vatCalculationText">Voer bedrag en BTW percentage in</span>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    Wijzigingen Opslaan
                </button>
                <a href="../index.php" class="btn btn-secondary">Annuleren</a>
                <a href="delete.php?id=<?php echo $transaction['id']; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Weet je zeker dat je deze transactie wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')">
                    Verwijderen
                </a>
            </div>
        </form>
        
        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-title">Transactie Geschiedenis</h3>
            <p><strong>Laatst gewijzigd:</strong> <?php echo date('d-m-Y H:i', strtotime($transaction['created_at'])); ?></p>
            <p><strong>Originele waarden:</strong></p>
            <ul>
                <li>Datum: <?php echo date('d-m-Y', strtotime($transaction['date'])); ?></li>
                <li>Omschrijving: <?php echo htmlspecialchars($transaction['description']); ?></li>
                <li>Bedrag: €<?php echo number_format($transaction['amount'], 2); ?></li>
                <li>Type: <?php echo ucfirst($transaction['type']); ?></li>
                <li>BTW: <?php echo isset($transaction['vat_percentage']) ? $transaction['vat_percentage'] . '%' : '0%'; ?></li>
            </ul>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const vatDeductible = document.getElementById('vat_deductible');
            const vatDeductibleLabel = document.querySelector('label[for="vat_deductible"]');
            const dateInput = document.getElementById('date');
            const vatPercentageSelect = document.getElementById('vat_percentage');
            const amountInput = document.getElementById('amount');
            const vatIncludedCheckbox = document.getElementById('vat_included');
            
            // Update VAT deductible based on transaction type
            function updateVatDeductible() {
                if (typeSelect.value === 'uitgave') {
                    vatDeductible.disabled = false;
                    vatDeductibleLabel.style.opacity = '1';
                } else {
                    vatDeductible.disabled = true;
                    vatDeductible.checked = false;
                    vatDeductibleLabel.style.opacity = '0.6';
                }
            }
            
            // Update VAT rates based on selected date
            async function updateVatRatesForDate(selectedDate) {
                try {
                    const response = await fetch(`get_vat_rates.php?date=${selectedDate}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    
                    const rates = await response.json();
                    
                    // Get current selected rate
                    const currentRate = vatPercentageSelect.value;
                    
                    // Clear existing options
                    vatPercentageSelect.innerHTML = '';
                    
                    // Add new options
                    rates.forEach(rate => {
                        const option = document.createElement('option');
                        option.value = rate.rate;
                        option.textContent = `${rate.rate}% (${rate.name})`;
                        option.title = rate.description;
                        
                        // Select the rate that matches current selection, or first rate
                        if (rate.rate == currentRate || (rates.length > 0 && rates[0].rate == rate.rate && currentRate === '')) {
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
                const amount = parseFloat(amountInput.value) || 0;
                const vatRate = parseFloat(vatPercentageSelect.value) || 0;
                const vatIncluded = vatIncludedCheckbox.checked;
                const vatCalculationDisplay = document.getElementById('vatCalculationDisplay');
                const vatCalculationText = document.getElementById('vatCalculationText');
                
                if (vatRate > 0 && amount !== 0) {
                    let vatAmount, baseAmount, totalAmount;
                    let calculationText = '';
                    
                    if (vatIncluded) {
                        baseAmount = amount / (1 + (vatRate / 100));
                        vatAmount = amount - baseAmount;
                        calculationText = `€${amount.toFixed(2)} inclusief ${vatRate}% BTW = €${baseAmount.toFixed(2)} basisbedrag + €${vatAmount.toFixed(2)} BTW`;
                    } else {
                        vatAmount = amount * (vatRate / 100);
                        totalAmount = amount + vatAmount;
                        calculationText = `€${amount.toFixed(2)} exclusief ${vatRate}% BTW = €${totalAmount.toFixed(2)} totaal (€${amount.toFixed(2)} + €${vatAmount.toFixed(2)} BTW)`;
                    }
                    
                    vatCalculationText.textContent = calculationText;
                    vatCalculationDisplay.style.display = 'block';
                } else {
                    vatCalculationDisplay.style.display = 'none';
                }
            }
            
            // Event listeners
            typeSelect.addEventListener('change', updateVatDeductible);
            dateInput.addEventListener('change', function() {
                updateVatRatesForDate(this.value);
            });
            amountInput.addEventListener('input', updateVatCalculation);
            vatPercentageSelect.addEventListener('change', updateVatCalculation);
            vatIncludedCheckbox.addEventListener('change', updateVatCalculation);
            
            // Initial calls
            updateVatDeductible();
            updateVatCalculation();
            
            // Also trigger calculation on page load if there's already VAT data
            if (parseFloat(vatPercentageSelect.value) > 0 && parseFloat(amountInput.value) !== 0) {
                updateVatCalculation();
            }
            
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