<?php
/**
 * Inkoop Boeken - Nieuwe Uitgave/Kosten Toevoegen
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

// Get active crediteuren (only for current user or admin)
if ($is_admin) {
    $stmt_relations = $pdo->query("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('crediteur', 'beide')
          AND is_active = TRUE
        ORDER BY company_name
    ");
} else {
    $stmt_relations = $pdo->prepare("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('crediteur', 'beide')
          AND is_active = TRUE
          AND (user_id = ? OR user_id IS NULL)
        ORDER BY company_name
    ");
    $stmt_relations->execute([$user_id]);
}
$crediteuren = $stmt_relations->fetchAll(PDO::FETCH_ASSOC);

// Get categories accessible to the current user (exclude "Inkomsten" category)
$inkomsten_cat_id = get_inkomsten_category_id();
if ($is_admin) {
    // Admin can see all categories except "Inkomsten"
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id != ? ORDER BY name");
    $stmt->execute([$inkomsten_cat_id]);
} else {
    // Regular users can see system categories (except "Inkomsten") and their own categories
    $stmt = $pdo->prepare("
        SELECT * FROM categories
        WHERE (is_system = 1 OR user_id = ?) AND id != ?
        ORDER BY name
    ");
    $stmt->execute([$user_id, $inkomsten_cat_id]);
}
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get VAT rates for the default date (today)
$default_date = date('Y-m-d');

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
    require_csrf_token();
    $date = $_POST['date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $type = 'uitgave'; // Always expense for this form
    $category_id = $_POST['category_id'];
    $vat_percentage = $_POST['vat_percentage'] ?? 0;
    $vat_included = isset($_POST['vat_included']) ? 1 : 0;
    $vat_deductible = isset($_POST['vat_deductible']) ? 1 : 0;
    $invoice_number = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null;
    $relation_id = !empty($_POST['relation_id']) ? $_POST['relation_id'] : null;

    // Validate that category is not "Inkomsten"
    if ($category_id == get_inkomsten_category_id()) {
        $category_id = '';
    }

    // Validate input before saving
    $errors = validate_transaction_input([
        'date' => $date,
        'description' => $description,
        'amount' => $amount,
        'vat_percentage' => $vat_percentage,
        'invoice_number' => $invoice_number
    ]);
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    } else {
        // Add user_id and relation_id to the transaction
        $stmt = $pdo->prepare("INSERT INTO transactions (date, description, amount, type, category_id, vat_percentage, vat_included, vat_deductible, invoice_number, user_id, relation_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $user_id, $relation_id]);

        // Handle receipt upload
        if (!empty($_FILES['receipt']['name'])) {
            require 'receipt_functions.php';
            $transaction_id = $pdo->lastInsertId();

            $validation = validate_receipt_upload($_FILES['receipt']);
            if ($validation['valid']) {
                $receipt_data = process_receipt_upload($_FILES['receipt']);
                if ($receipt_data) {
                    save_receipt_to_transaction($pdo, $transaction_id, $receipt_data, $user_id, $is_admin);
                }
            } else {
                $_SESSION['warning_message'] = 'Inkoop opgeslagen, maar bonnetje kon niet worden verwerkt: ' . $validation['error'];
            }
        }

        header('Location: ../index.php');
        exit;
    }
}

$page_title = 'Inkoop Boeken';
$page_subtitle = 'Nieuwe kosten/inkopen registreren met BTW-aftrek';
$page_css = <<<'CSS'
/* Relation add link styling */
.relation-add-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.relation-add-link:hover {
    color: var(--text-primary);
    text-decoration: underline;
}

.relation-add-link i {
    margin-right: 3px;
}
CSS;

include 'page_header.php';
?>
    <main class="main-content">
        <h2 class="section-title">Inkoopgegevens</h2>

        <form method="post" class="transaction-form" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
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
                           placeholder="Bijv. Inkoop materialen, Betaling leverancier" required>
                </div>

                <div class="form-group">
                    <label for="relation_id">
                        <i class="fas fa-address-book"></i> Crediteur (Leverancier)
                    </label>
                    <select id="relation_id" name="relation_id" class="form-control">
                        <option value="">Diverse Leveranciers (geen vaste relatie)</option>
                        <?php foreach ($crediteuren as $crediteur): ?>
                        <option value="<?php echo $crediteur['id']; ?>"
                                title="<?php echo htmlspecialchars($crediteur['email'] ?? ''); ?> - <?php echo htmlspecialchars($crediteur['city'] ?? ''); ?>">
                            <?php echo htmlspecialchars($crediteur['relation_code']); ?> - <?php echo htmlspecialchars($crediteur['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">
                        Optioneel: Koppel deze inkoop aan een leverancier.
                        <a href="add_relation.php?type=crediteur&return=add_expense.php" class="relation-add-link">
                            <i class="fas fa-plus-circle"></i> Nieuwe crediteur toevoegen
                        </a>
                    </small>
                </div>

                <div class="form-group">
                    <label for="invoice_number">Factuurnummer <span>(optioneel)</span></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           placeholder="Bijv. FACT-2024-001 of leveranciersnummer" maxlength="50">
                    <small class="form-text">Voer het factuurnummer van de leverancier in voor betere administratie</small>
                </div>

                <div class="form-group">
                    <label for="amount">Bedrag (€) *</label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           step="0.01" placeholder="0.00" required>
                    <small class="form-text">Voer het bedrag in zoals op de factuur vermeld</small>
                </div>

                <div class="form-group">
                    <label for="category_id">Categorie</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Geen categorie</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">Kies de kostensoort voor rapportage</small>
                </div>

                <div class="form-group">
                    <label for="receipt">
                        <i class="fas fa-receipt"></i> Bonnetje <span>(optioneel)</span>
                    </label>
                    <input type="file" id="receipt" name="receipt"
                           class="form-control"
                           accept="image/*,application/pdf"
                           capture="environment">
                    <small class="form-text">
                        Maak een foto of upload een scan van het bonnetje. Toegestaan: JPG, PNG, GIF, PDF (max 5MB).
                    </small>
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
                    <input type="checkbox" id="vat_included" name="vat_included" value="1" checked>
                    <label for="vat_included">Bedrag is inclusief BTW</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="vat_deductible" name="vat_deductible" value="1" checked>
                    <label for="vat_deductible">BTW is aftrekbaar</label>
                </div>

                <div class="alert alert-info">
                    <strong>Let op:</strong> BTW is alleen aftrekbaar voor zakelijke uitgaven.
                    Voor privé-uitgaven of niet-aftrekbare kosten, vink "BTW is aftrekbaar" uit.
                </div>

                <div id="vatCalculationDisplay" class="alert alert-info" style="display: none;">
                    <strong>BTW berekening:</strong> <span id="vatCalculationText">Voer bedrag en BTW percentage in</span>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    Inkoop Opslaan
                </button>
                <a href="../index.php" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>

        <div class="alert alert-info" style="margin-top: 2rem;">
            <strong>BTW Berekeningswijze:</strong><br>
            - <strong>Inclusief BTW:</strong> BTW wordt berekend van het ingevoerde bedrag<br>
            - <strong>Exclusief BTW:</strong> BTW wordt opgeteld bij het ingevoerde bedrag<br>
            - <strong>Aftrekbaar:</strong> BTW kan worden verrekend met af te dragen BTW op verkopen
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            const vatPercentageSelect = document.getElementById('vat_percentage');

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

            // Initial calculation
            updateVatCalculation();
        });
    </script>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>