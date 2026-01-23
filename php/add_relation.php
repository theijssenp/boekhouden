<?php
/**
 * Nieuwe Relatie Toevoegen - Boekhouden
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    $company_name = trim($_POST['company_name'] ?? '');
    $relation_type = $_POST['relation_type'] ?? '';
    
    if (empty($company_name)) {
        $error_message = 'Bedrijfsnaam is verplicht';
    } elseif (!in_array($relation_type, ['debiteur', 'crediteur', 'beide'])) {
        $error_message = 'Ongeldig relatietype';
    } else {
        // Collect all form data
        $contact_person = trim($_POST['contact_person'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? 'Nederland');
        $vat_number = trim($_POST['vat_number'] ?? '');
        $coc_number = trim($_POST['coc_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $iban = trim($_POST['iban'] ?? '');
        $payment_term = intval($_POST['payment_term'] ?? 30);
        $credit_limit = floatval($_POST['credit_limit'] ?? 0);
        $default_vat_rate = floatval($_POST['default_vat_rate'] ?? 21);
        $currency = trim($_POST['currency'] ?? 'EUR');
        $language = trim($_POST['language'] ?? 'nl');
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Ongeldig e-mailadres';
        }
        // Validate VAT number format if provided (basic NL format: NL123456789B01)
        elseif (!empty($vat_number) && !preg_match('/^[A-Z]{2}[0-9A-Z]{2,}$/i', $vat_number)) {
            $error_message = 'BTW-nummer heeft een ongeldig formaat (bijv. NL123456789B01)';
        } else {
            try {
                // Insert new relation
                $stmt = $pdo->prepare("
                    INSERT INTO relations (
                        relation_type, company_name, contact_person,
                        street, postal_code, city, country,
                        vat_number, coc_number,
                        email, phone, website,
                        iban, payment_term, credit_limit,
                        default_vat_rate, currency, language,
                        notes, user_id, created_by
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?
                    )
                ");
                
                $stmt->execute([
                    $relation_type, $company_name, $contact_person,
                    $street, $postal_code, $city, $country,
                    $vat_number, $coc_number,
                    $email, $phone, $website,
                    $iban, $payment_term, $credit_limit,
                    $default_vat_rate, $currency, $language,
                    $notes, $user_id, $user_id
                ]);
                
                // Redirect to relations overview with success message
                header('Location: relations.php?success=' . urlencode('Relatie succesvol toegevoegd'));
                exit;
            } catch (PDOException $e) {
                $error_message = 'Fout bij opslaan: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Nieuwe Relatie';
$page_subtitle = 'Voeg een nieuwe debiteur of crediteur toe';
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-grid-full {
            grid-column: 1 / -1;
        }
        
        .relation-type-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .relation-type-option {
            position: relative;
        }
        
        .relation-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .relation-type-label {
            display: block;
            padding: 1.5rem;
            border: 2px solid var(--gray-medium);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background-color: white;
        }
        
        .relation-type-label:hover {
            border-color: var(--secondary-color);
            background-color: var(--gray-light);
        }
        
        .relation-type-option input[type="radio"]:checked + .relation-type-label {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            font-weight: 600;
        }
        
        .relation-type-label i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .relation-type-label .type-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .relation-type-label .type-description {
            font-size: 0.85rem;
            color: var(--gray-dark);
        }
        
        .field-hint {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-top: 0.25rem;
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
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Nieuwe Relatie Toevoegen</h2>
        
        <form method="post" class="transaction-form">
            <!-- Relation Type Selection -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-tag"></i> Relatietype *</h3>
                <div class="relation-type-selector">
                    <div class="relation-type-option">
                        <input type="radio" id="type_debiteur" name="relation_type" value="debiteur" required>
                        <label for="type_debiteur" class="relation-type-label">
                            <i class="fas fa-user-tie"></i>
                            <div class="type-name">Debiteur</div>
                            <div class="type-description">Klant (inkomsten)</div>
                        </label>
                    </div>
                    <div class="relation-type-option">
                        <input type="radio" id="type_crediteur" name="relation_type" value="crediteur">
                        <label for="type_crediteur" class="relation-type-label">
                            <i class="fas fa-building"></i>
                            <div class="type-name">Crediteur</div>
                            <div class="type-description">Leverancier (uitgaven)</div>
                        </label>
                    </div>
                    <div class="relation-type-option">
                        <input type="radio" id="type_beide" name="relation_type" value="beide">
                        <label for="type_beide" class="relation-type-label">
                            <i class="fas fa-exchange-alt"></i>
                            <div class="type-name">Beide</div>
                            <div class="type-description">Klant & Leverancier</div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Basic Information -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> Bedrijfsgegevens</h3>
                <div class="form-grid">
                    <div class="form-group form-grid-full">
                        <label for="company_name">Bedrijfsnaam *</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" 
                               placeholder="Bijv. ABC BV" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contactpersoon</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" 
                               placeholder="Bijv. Jan Jansen">
                    </div>
                    
                    <div class="form-group">
                        <label for="coc_number">KvK-nummer</label>
                        <input type="text" id="coc_number" name="coc_number" class="form-control" 
                               placeholder="Bijv. 12345678">
                    </div>
                    
                    <div class="form-group">
                        <label for="vat_number">BTW-nummer</label>
                        <input type="text" id="vat_number" name="vat_number" class="form-control" 
                               placeholder="Bijv. NL123456789B01" pattern="[A-Z]{2}[0-9A-Z]{2,}">
                        <small class="field-hint">Format: NL123456789B01</small>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> Adresgegevens</h3>
                <div class="form-grid">
                    <div class="form-group form-grid-full">
                        <label for="street">Straat + Huisnummer</label>
                        <input type="text" id="street" name="street" class="form-control" 
                               placeholder="Bijv. Hoofdstraat 123">
                    </div>
                    
                    <div class="form-group">
                        <label for="postal_code">Postcode</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control" 
                               placeholder="Bijv. 1234 AB">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Plaats</label>
                        <input type="text" id="city" name="city" class="form-control" 
                               placeholder="Bijv. Amsterdam">
                    </div>
                    
                    <div class="form-group form-grid-full">
                        <label for="country">Land</label>
                        <input type="text" id="country" name="country" class="form-control" 
                               value="Nederland">
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-envelope"></i> Contactgegevens</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">E-mailadres</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="info@bedrijf.nl">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telefoonnummer</label>
                        <input type="text" id="phone" name="phone" class="form-control" 
                               placeholder="Bijv. 020-1234567">
                    </div>
                    
                    <div class="form-group form-grid-full">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" class="form-control" 
                               placeholder="https://www.bedrijf.nl">
                    </div>
                </div>
            </div>
            
            <!-- Financial Information -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-euro-sign"></i> Financiële gegevens</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="iban">IBAN</label>
                        <input type="text" id="iban" name="iban" class="form-control" 
                               placeholder="NL12BANK0123456789" maxlength="34">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_term">Betalingstermijn (dagen)</label>
                        <input type="number" id="payment_term" name="payment_term" class="form-control" 
                               value="30" min="0" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label for="credit_limit">Kredietlimiet (€)</label>
                        <input type="number" id="credit_limit" name="credit_limit" class="form-control" 
                               step="0.01" value="0.00" min="0">
                        <small class="field-hint">Voor debiteuren</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_vat_rate">Standaard BTW-tarief (%)</label>
                        <select id="default_vat_rate" name="default_vat_rate" class="form-control">
                            <option value="21" selected>21% (Hoog tarief)</option>
                            <option value="9">9% (Laag tarief)</option>
                            <option value="0">0% (Vrijgesteld)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Valuta</label>
                        <select id="currency" name="currency" class="form-control">
                            <option value="EUR" selected>EUR (€)</option>
                            <option value="USD">USD ($)</option>
                            <option value="GBP">GBP (£)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="language">Taalvoorkeur</label>
                        <select id="language" name="language" class="form-control">
                            <option value="nl" selected>Nederlands</option>
                            <option value="en">Engels</option>
                            <option value="de">Duits</option>
                            <option value="fr">Frans</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-sticky-note"></i> Opmerkingen</h3>
                <div class="form-group">
                    <label for="notes">Notities</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4" 
                              placeholder="Eventuele opmerkingen of notities over deze relatie..."></textarea>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Relatie Opslaan
                </button>
                <a href="relations.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuleren
                </a>
            </div>
        </form>
        
        <div class="alert alert-info" style="margin-top: 2rem;">
            <strong><i class="fas fa-info-circle"></i> Let op:</strong> Velden met een * zijn verplicht. De relatiecode wordt automatisch gegenereerd.
        </div>
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
