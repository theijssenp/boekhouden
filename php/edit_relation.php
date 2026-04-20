<?php
/**
 * Relatie Bewerken - Boekhouden
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
$error_message = '';
$success_message = '';

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
        // Validate VAT number format if provided
        elseif (!empty($vat_number) && !preg_match('/^[A-Z]{2}[0-9A-Z]{2,}$/i', $vat_number)) {
            $error_message = 'BTW-nummer heeft een ongeldig formaat (bijv. NL123456789B01)';
        } else {
            try {
                // Update relation with user isolation check
                if ($is_admin) {
                    $stmt = $pdo->prepare("
                        UPDATE relations SET
                            relation_type = ?, company_name = ?, contact_person = ?,
                            street = ?, postal_code = ?, city = ?, country = ?,
                            vat_number = ?, coc_number = ?,
                            email = ?, phone = ?, website = ?,
                            iban = ?, payment_term = ?, credit_limit = ?,
                            default_vat_rate = ?, currency = ?, language = ?,
                            notes = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $relation_type, $company_name, $contact_person,
                        $street, $postal_code, $city, $country,
                        $vat_number, $coc_number,
                        $email, $phone, $website,
                        $iban, $payment_term, $credit_limit,
                        $default_vat_rate, $currency, $language,
                        $notes, $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE relations SET
                            relation_type = ?, company_name = ?, contact_person = ?,
                            street = ?, postal_code = ?, city = ?, country = ?,
                            vat_number = ?, coc_number = ?,
                            email = ?, phone = ?, website = ?,
                            iban = ?, payment_term = ?, credit_limit = ?,
                            default_vat_rate = ?, currency = ?, language = ?,
                            notes = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    
                    $stmt->execute([
                        $relation_type, $company_name, $contact_person,
                        $street, $postal_code, $city, $country,
                        $vat_number, $coc_number,
                        $email, $phone, $website,
                        $iban, $payment_term, $credit_limit,
                        $default_vat_rate, $currency, $language,
                        $notes, $id, $user_id
                    ]);
                }
                
                // Redirect to relations overview with success message
                header('Location: relations.php?success=' . urlencode('Relatie succesvol bijgewerkt'));
                exit;
            } catch (PDOException $e) {
                $error_message = 'Fout bij opslaan: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Relatie Bewerken';
$page_subtitle = 'Bewerk ' . htmlspecialchars($relation['company_name']);
$active_nav = 'relations';
$page_css = <<<CSS
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
            background-color: var(--bg-card);
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

        .relation-code-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            padding: 0.75rem 1rem;
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            display: inline-block;
        }
CSS;
include 'page_header.php';
?>
    <main class="main-content">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle"></i> Relatiecode:</strong> 
            <span class="relation-code-display"><?php echo htmlspecialchars($relation['relation_code']); ?></span>
            <span style="margin-left: 1rem;">Aangemaakt: <?php echo date('d-m-Y H:i', strtotime($relation['created_at'])); ?></span>
        </div>
        
        <h2 class="section-title">Relatie Bewerken</h2>
        
        <form method="post" class="transaction-form">
            <!-- Relation Type Selection -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-tag"></i> Relatietype *</h3>
                <div class="relation-type-selector">
                    <div class="relation-type-option">
                        <input type="radio" id="type_debiteur" name="relation_type" value="debiteur" 
                               <?php echo $relation['relation_type'] == 'debiteur' ? 'checked' : ''; ?> required>
                        <label for="type_debiteur" class="relation-type-label">
                            <i class="fas fa-user-tie"></i>
                            <div class="type-name">Debiteur</div>
                            <div class="type-description">Klant (inkomsten)</div>
                        </label>
                    </div>
                    <div class="relation-type-option">
                        <input type="radio" id="type_crediteur" name="relation_type" value="crediteur"
                               <?php echo $relation['relation_type'] == 'crediteur' ? 'checked' : ''; ?>>
                        <label for="type_crediteur" class="relation-type-label">
                            <i class="fas fa-building"></i>
                            <div class="type-name">Crediteur</div>
                            <div class="type-description">Leverancier (uitgaven)</div>
                        </label>
                    </div>
                    <div class="relation-type-option">
                        <input type="radio" id="type_beide" name="relation_type" value="beide"
                               <?php echo $relation['relation_type'] == 'beide' ? 'checked' : ''; ?>>
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
                               value="<?php echo htmlspecialchars($relation['company_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contactpersoon</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['contact_person'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="coc_number">KvK-nummer</label>
                        <input type="text" id="coc_number" name="coc_number" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['coc_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="vat_number">BTW-nummer</label>
                        <input type="text" id="vat_number" name="vat_number" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['vat_number'] ?? ''); ?>"
                               pattern="[A-Z]{2}[0-9A-Z]{2,}">
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
                               value="<?php echo htmlspecialchars($relation['street'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="postal_code">Postcode</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['postal_code'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Plaats</label>
                        <input type="text" id="city" name="city" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group form-grid-full">
                        <label for="country">Land</label>
                        <input type="text" id="country" name="country" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['country'] ?? 'Nederland'); ?>">
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
                               value="<?php echo htmlspecialchars($relation['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telefoonnummer</label>
                        <input type="text" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group form-grid-full">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['website'] ?? ''); ?>">
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
                               value="<?php echo htmlspecialchars($relation['iban'] ?? ''); ?>" maxlength="34">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_term">Betalingstermijn (dagen)</label>
                        <input type="number" id="payment_term" name="payment_term" class="form-control" 
                               value="<?php echo htmlspecialchars($relation['payment_term'] ?? 30); ?>" min="0" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label for="credit_limit">Kredietlimiet (€)</label>
                        <input type="number" id="credit_limit" name="credit_limit" class="form-control" 
                               step="0.01" value="<?php echo htmlspecialchars($relation['credit_limit'] ?? 0); ?>" min="0">
                        <small class="field-hint">Voor debiteuren</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_vat_rate">Standaard BTW-tarief (%)</label>
                        <select id="default_vat_rate" name="default_vat_rate" class="form-control">
                            <option value="21" <?php echo ($relation['default_vat_rate'] ?? 21) == 21 ? 'selected' : ''; ?>>21% (Hoog tarief)</option>
                            <option value="9" <?php echo ($relation['default_vat_rate'] ?? 21) == 9 ? 'selected' : ''; ?>>9% (Laag tarief)</option>
                            <option value="0" <?php echo ($relation['default_vat_rate'] ?? 21) == 0 ? 'selected' : ''; ?>>0% (Vrijgesteld)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Valuta</label>
                        <select id="currency" name="currency" class="form-control">
                            <option value="EUR" <?php echo ($relation['currency'] ?? 'EUR') == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                            <option value="USD" <?php echo ($relation['currency'] ?? 'EUR') == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="GBP" <?php echo ($relation['currency'] ?? 'EUR') == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="language">Taalvoorkeur</label>
                        <select id="language" name="language" class="form-control">
                            <option value="nl" <?php echo ($relation['language'] ?? 'nl') == 'nl' ? 'selected' : ''; ?>>Nederlands</option>
                            <option value="en" <?php echo ($relation['language'] ?? 'nl') == 'en' ? 'selected' : ''; ?>>Engels</option>
                            <option value="de" <?php echo ($relation['language'] ?? 'nl') == 'de' ? 'selected' : ''; ?>>Duits</option>
                            <option value="fr" <?php echo ($relation['language'] ?? 'nl') == 'fr' ? 'selected' : ''; ?>>Frans</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-sticky-note"></i> Opmerkingen</h3>
                <div class="form-group">
                    <label for="notes">Notities</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($relation['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Wijzigingen Opslaan
                </button>
                <a href="relations.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuleren
                </a>
                <a href="delete_relation.php?id=<?php echo $relation['id']; ?>"
                   class="btn btn-danger"
                   onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($relation['company_name']); ?> wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')">
                    <i class="fas fa-trash"></i> Verwijderen
                </a>
            </div>
        </form>
    </main>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>