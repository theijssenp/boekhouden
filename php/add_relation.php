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
    require_csrf_token();
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
$active_nav = 'relations';
$page_css = <<<'CSS'
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
CSS;

include 'page_header.php';
?>
    <main class="main-content">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Nieuwe Relatie Toevoegen</h2>

        <form method="post" class="transaction-form">
            <?php echo csrf_field(); ?>
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

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>