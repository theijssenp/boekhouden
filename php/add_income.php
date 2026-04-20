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

$page_title = 'Verkoop Boeken';
$page_subtitle = 'Nieuwe omzet/verkoop registreren met automatische facturering';
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
    </script>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>