<?php
/**
 * Nieuwe Transactie Toevoegen - Boekhouden
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

// Get categories accessible to the current user
if ($is_admin) {
    // Admin can see all categories
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Regular users can see system categories and their own categories
    $stmt = $pdo->prepare("
        SELECT * FROM categories
        WHERE is_system = 1 OR user_id = ?
        ORDER BY name
    ");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get VAT rates for the default date (today)
$default_date = date('Y-m-d');

// get_vat_rates_for_date() staat in auth_functions.php

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
    $type = $_POST['type'];
    $category_id = $_POST['category_id'];
    $vat_percentage = $_POST['vat_percentage'] ?? 0;
    $vat_included = isset($_POST['vat_included']) ? 1 : 0;
    $vat_deductible = isset($_POST['vat_deductible']) ? 1 : 0;
    $invoice_number = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null;

    // Validate category based on transaction type
    if ($type === 'inkomst') {
        // For income transactions, always use "Inkomsten" category (ID 1)
        $category_id = 1;

        // Auto-generate invoice number for income if not provided
        if (empty($invoice_number)) {
            $invoice_number = generate_next_invoice_number($user_id);
        }
    } elseif ($type === 'uitgave' && $category_id == 1) {
        // For expense transactions, cannot use "Inkomsten" category
        // Reset to empty (no category)
        $category_id = '';
    }

    // Add user_id to the transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (date, description, amount, type, category_id, vat_percentage, vat_included, vat_deductible, invoice_number, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $user_id]);

    header('Location: ../index.php');
    exit;
}

$page_title = 'Nieuwe Transactie Toevoegen';
$page_subtitle = 'Voeg een nieuwe financiële transactie toe aan het systeem';

include 'page_header.php';
?>
    <main class="main-content">
        <h2 class="section-title">Transactiegegevens</h2>

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
                           placeholder="Bijv. Verkoop product, Betaling leverancier" required>
                </div>

                <div class="form-group">
                    <label for="invoice_number">Factuurnummer <span id="invoice_number_label_optional">(optioneel)</span><span id="invoice_number_label_auto" style="display: none;">(automatisch gegenereerd)</span></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           placeholder="Bijv. FACT-2024-001, INV-12345" maxlength="50">
                    <small class="form-text" id="invoice_number_help">Voer het factuurnummer in voor betere administratie</small>
                </div>

                <div class="form-group">
                    <label for="amount">Bedrag (€) *</label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           step="0.01" placeholder="0.00" required>
                    <small class="form-text">Positief voor normale transacties, negatief voor creditnota's</small>
                </div>

                <div class="form-group">
                    <label for="type">Type *</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="">Selecteer type</option>
                        <option value="inkomst">Inkomst</option>
                        <option value="uitgave">Uitgave</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id">Categorie</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Geen categorie</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
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

                <div class="checkbox-group">
                    <input type="checkbox" id="vat_deductible" name="vat_deductible" value="1">
                    <label for="vat_deductible">BTW is aftrekbaar (alleen voor uitgaven)</label>
                </div>

                <div class="alert alert-info">
                    <strong>Let op:</strong> BTW is alleen aftrekbaar voor zakelijke uitgaven.
                    Voor inkomsten is BTW altijd af te dragen.
                </div>

                <div id="vatCalculationDisplay" class="alert alert-info" style="display: none;">
                    <strong>BTW berekening:</strong> <span id="vatCalculationText">Voer bedrag en BTW percentage in</span>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg">
                    Transactie Toevoegen
                </button>
                <a href="../index.php" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>

        <div class="alert alert-info" style="margin-top: 2rem;">
            <strong>BTW Berekeningswijze:</strong><br>
            - <strong>Inclusief BTW:</strong> BTW wordt berekend van het ingevoerde bedrag<br>
            - <strong>Exclusief BTW:</strong> BTW wordt opgeteld bij het ingevoerde bedrag<br>
            - <strong>Aftrekbaar:</strong> BTW kan worden verrekend met af te dragen BTW
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const vatDeductible = document.getElementById('vat_deductible');
            const vatDeductibleLabel = document.querySelector('label[for="vat_deductible"]');
            const dateInput = document.getElementById('date');
            const vatPercentageSelect = document.getElementById('vat_percentage');

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

                    // Nederlandse notatie, gelijk aan format_euro() in PHP
                    const eur = (v) => '€ ' + v.toLocaleString('nl-NL', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    });

                    if (vatIncluded) {
                        baseAmount = amount / (1 + (vatRate / 100));
                        vatAmount = amount - baseAmount;
                        calculationText = `${eur(amount)} inclusief ${vatRate}% BTW = ${eur(baseAmount)} basisbedrag + ${eur(vatAmount)} BTW`;
                    } else {
                        vatAmount = amount * (vatRate / 100);
                        totalAmount = amount + vatAmount;
                        calculationText = `${eur(amount)} exclusief ${vatRate}% BTW = ${eur(totalAmount)} totaal (${eur(amount)} + ${eur(vatAmount)} BTW)`;
                    }

                    vatCalculationText.textContent = calculationText;
                    vatCalculationDisplay.style.display = 'block';
                } else {
                    vatCalculationDisplay.style.display = 'none';
                }
            }

            // Function to handle invoice number field based on transaction type
            async function updateInvoiceNumberField() {
                const invoiceNumberInput = document.getElementById('invoice_number');
                const invoiceNumberLabelOptional = document.getElementById('invoice_number_label_optional');
                const invoiceNumberLabelAuto = document.getElementById('invoice_number_label_auto');
                const invoiceNumberHelp = document.getElementById('invoice_number_help');
                const selectedType = typeSelect.value;

                if (selectedType === 'inkomst') {
                    // For income: fetch next invoice number and make field readonly
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

                    invoiceNumberInput.readOnly = true;
                    invoiceNumberInput.style.backgroundColor = '#f5f5f5';
                    invoiceNumberInput.style.cursor = 'not-allowed';
                    invoiceNumberLabelOptional.style.display = 'none';
                    invoiceNumberLabelAuto.style.display = 'inline';
                    invoiceNumberHelp.textContent = 'Factuurnummer wordt automatisch gegenereerd voor inkomsten';
                } else {
                    // For expenses: make field editable and clear
                    invoiceNumberInput.value = '';
                    invoiceNumberInput.readOnly = false;
                    invoiceNumberInput.style.backgroundColor = '';
                    invoiceNumberInput.style.cursor = '';
                    invoiceNumberLabelOptional.style.display = 'inline';
                    invoiceNumberLabelAuto.style.display = 'none';
                    invoiceNumberHelp.textContent = 'Voer het factuurnummer in voor betere administratie';
                }
            }

            // Function to handle category dropdown based on transaction type
            function updateCategoryBasedOnType() {
                const categorySelect = document.getElementById('category_id');
                const selectedType = typeSelect.value;

                if (selectedType === 'inkomst') {
                    // Set to "Inkomsten" (ID 1) and disable
                    categorySelect.value = '1';
                    categorySelect.disabled = true;
                    categorySelect.style.backgroundColor = '#f5f5f5';
                    categorySelect.style.color = '#999';
                    categorySelect.style.cursor = 'not-allowed';

                    // Hide the "Inkomsten" option if it's hidden (should be visible)
                    const inkomstOption = categorySelect.querySelector('option[value="1"]');
                    if (inkomstOption) {
                        inkomstOption.style.display = 'block';
                    }
                } else if (selectedType === 'uitgave') {
                    // Enable dropdown
                    categorySelect.disabled = false;
                    categorySelect.style.backgroundColor = '';
                    categorySelect.style.color = '';
                    categorySelect.style.cursor = '';

                    // Hide the "Inkomsten" option (ID 1)
                    const inkomstOption = categorySelect.querySelector('option[value="1"]');
                    if (inkomstOption) {
                        inkomstOption.style.display = 'none';
                    }

                    // If currently selected is "Inkomsten", reset to empty
                    if (categorySelect.value === '1') {
                        categorySelect.value = '';
                    }
                } else {
                    // No type selected, enable and show all options
                    categorySelect.disabled = false;
                    categorySelect.style.backgroundColor = '';
                    categorySelect.style.color = '';
                    categorySelect.style.cursor = '';

                    // Show all options
                    const allOptions = categorySelect.querySelectorAll('option');
                    allOptions.forEach(option => {
                        option.style.display = 'block';
                    });
                }
            }

            // Event listeners
            typeSelect.addEventListener('change', function() {
                updateVatDeductible();
                updateCategoryBasedOnType();
                updateInvoiceNumberField();
            });
            dateInput.addEventListener('change', function() {
                updateVatRatesForDate(this.value);
            });

            // Add event listeners for VAT calculation
            document.getElementById('amount').addEventListener('input', updateVatCalculation);
            document.getElementById('vat_percentage').addEventListener('change', updateVatCalculation);
            document.getElementById('vat_included').addEventListener('change', updateVatCalculation);

            // Initial calls
            updateVatDeductible();
            updateCategoryBasedOnType();

            // Set today's date as default and update VAT rates
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            updateVatRatesForDate(today);
        });
    </script>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>