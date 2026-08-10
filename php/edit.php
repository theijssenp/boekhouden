<?php
/**
 * Transactie Bewerken - Boekhouden
 *
 * @author P. Theijssen
 */
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
    header('Location: ../index.php');
    exit;
}

// Get active debiteuren (only for current user or admin)
if ($is_admin) {
    $stmt_debtors = $pdo->query("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('debiteur', 'beide')
          AND is_active = TRUE
        ORDER BY company_name
    ");
} else {
    $stmt_debtors = $pdo->prepare("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('debiteur', 'beide')
          AND is_active = TRUE
          AND (user_id = ? OR user_id IS NULL)
        ORDER BY company_name
    ");
    $stmt_debtors->execute([$user_id]);
}
$debiteuren = $stmt_debtors->fetchAll(PDO::FETCH_ASSOC);

// Get active crediteuren (only for current user or admin)
if ($is_admin) {
    $stmt_creditors = $pdo->query("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('crediteur', 'beide')
          AND is_active = TRUE
        ORDER BY company_name
    ");
} else {
    $stmt_creditors = $pdo->prepare("
        SELECT id, relation_code, company_name, email, city
        FROM relations
        WHERE relation_type IN ('crediteur', 'beide')
          AND is_active = TRUE
          AND (user_id = ? OR user_id IS NULL)
        ORDER BY company_name
    ");
    $stmt_creditors->execute([$user_id]);
}
$crediteuren = $stmt_creditors->fetchAll(PDO::FETCH_ASSOC);

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

// get_vat_rates_for_date() staat in auth_functions.php

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
    require_csrf_token();
    $date = $_POST['date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $type = $_POST['type'];
    $category_id = $_POST['category_id'];
    $vat_percentage = $_POST['vat_percentage'] ?? 0;
    $vat_included = isset($_POST['vat_included']) ? 1 : 0;
    $vat_deductible = isset($_POST['vat_deductible']) ? 1 : 0;
    $invoice_number = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null;
    $relation_id = !empty($_POST['relation_id']) ? $_POST['relation_id'] : null;

    // Update transaction with user_id check for non-admin users
    if ($is_admin) {
        $stmt = $pdo->prepare("UPDATE transactions SET date = ?, description = ?, amount = ?, type = ?, category_id = ?, vat_percentage = ?, vat_included = ?, vat_deductible = ?, invoice_number = ?, relation_id = ? WHERE id = ?");
        $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $relation_id, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE transactions SET date = ?, description = ?, amount = ?, type = ?, category_id = ?, vat_percentage = ?, vat_included = ?, vat_deductible = ?, invoice_number = ?, relation_id = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$date, $description, $amount, $type, $category_id, $vat_percentage, $vat_included, $vat_deductible, $invoice_number, $relation_id, $id, $user_id]);
    }

    // Handle receipt operations
    require 'receipt_functions.php';

    // Remove receipt if checkbox is checked
    if (isset($_POST['remove_receipt']) && $_POST['remove_receipt'] == 1) {
        if (!empty($transaction['receipt_path'])) {
            remove_receipt_from_transaction($pdo, $id, $user_id, $is_admin);
        }
    }

    // Upload new receipt (overrides remove if both are submitted)
    if (!empty($_FILES['receipt']['name'])) {
        $validation = validate_receipt_upload($_FILES['receipt']);
        if ($validation['valid']) {
            $receipt_data = store_receipt_file($_FILES['receipt']);
            if ($receipt_data) {
                save_receipt_to_transaction($pdo, $id, $receipt_data, $user_id, $is_admin);
            }
        }
    }

    // AJAX request: return JSON for auto-opening invoice
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'transaction_id' => $id]);
        exit;
    }

    header('Location: ../index.php');
    exit;
}

$page_title = 'Transactie Bewerken';
$page_subtitle = 'Bewerk transactie #' . $transaction['id'] . ' van ' . date('d-m-Y', strtotime($transaction['date']));
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
        <h2 class="section-title">Transactiegegevens Bewerken</h2>

        <div class="alert alert-info">
            <strong>Transactie ID:</strong> #<?php echo $transaction['id']; ?> |
            <strong>Aangemaakt:</strong> <?php echo date('d-m-Y H:i', strtotime($transaction['created_at'])); ?>
        </div>

        <form method="post" class="transaction-form" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
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

                <div class="form-group" id="debiteur-group" style="display: none;">
                    <label for="relation_id_debiteur">
                        <i class="fas fa-address-book"></i> Debiteur (Klant)
                    </label>
                    <select id="relation_id_debiteur" name="relation_id" class="form-control">
                        <option value="">Diverse Klanten (geen vaste relatie)</option>
                        <?php foreach ($debiteuren as $debiteur): ?>
                        <option value="<?php echo $debiteur['id']; ?>"
                                <?php echo (isset($transaction['relation_id']) && $transaction['relation_id'] == $debiteur['id']) ? 'selected' : ''; ?>
                                title="<?php echo htmlspecialchars($debiteur['email'] ?? ''); ?> - <?php echo htmlspecialchars($debiteur['city'] ?? ''); ?>">
                            <?php echo htmlspecialchars($debiteur['relation_code']); ?> - <?php echo htmlspecialchars($debiteur['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">
                        Optioneel: Koppel deze verkoop aan een klant.
                        <a href="add_relation.php?type=debiteur&return=edit.php?id=<?php echo $id; ?>" class="relation-add-link">
                            <i class="fas fa-plus-circle"></i> Nieuwe debiteur toevoegen
                        </a>
                    </small>
                </div>

                <div class="form-group" id="crediteur-group" style="display: none;">
                    <label for="relation_id_crediteur">
                        <i class="fas fa-address-book"></i> Crediteur (Leverancier)
                    </label>
                    <select id="relation_id_crediteur" name="relation_id" class="form-control">
                        <option value="">Diverse Leveranciers (geen vaste relatie)</option>
                        <?php foreach ($crediteuren as $crediteur): ?>
                        <option value="<?php echo $crediteur['id']; ?>"
                                <?php echo (isset($transaction['relation_id']) && $transaction['relation_id'] == $crediteur['id']) ? 'selected' : ''; ?>
                                title="<?php echo htmlspecialchars($crediteur['email'] ?? ''); ?> - <?php echo htmlspecialchars($crediteur['city'] ?? ''); ?>">
                            <?php echo htmlspecialchars($crediteur['relation_code']); ?> - <?php echo htmlspecialchars($crediteur['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">
                        Optioneel: Koppel deze inkoop aan een leverancier.
                        <a href="add_relation.php?type=crediteur&return=edit.php?id=<?php echo $id; ?>" class="relation-add-link">
                            <i class="fas fa-plus-circle"></i> Nieuwe crediteur toevoegen
                        </a>
                    </small>
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

                <div class="form-group">
                    <label for="receipt">
                        <i class="fas fa-receipt"></i> Bonnetje <span>(optioneel)</span>
                    </label>
                    <?php if (!empty($transaction['receipt_path'])): ?>
                    <div class="receipt-preview">
                        <a href="view_receipt.php?id=<?php echo $transaction['id']; ?>"
                           target="_blank"
                           class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i>
                            <?php echo htmlspecialchars($transaction['receipt_original_name']); ?>
                        </a>
                        <label class="receipt-remove-label">
                            <input type="checkbox" name="remove_receipt" value="1">
                            Bonnetje verwijderen
                        </label>
                    </div>
                    <small class="form-text" style="margin-bottom: 0.5rem;">
                        Upload een nieuw bestand om het huidige bonnetje te vervangen.
                    </small>
                    <?php endif; ?>
                    <input type="file" id="receipt" name="receipt"
                           class="form-control"
                           accept="image/*,application/pdf"
                           <?php echo empty($transaction['receipt_path']) ? 'capture="environment"' : ''; ?>>
                    <small class="form-text">
                        Toegestane formaten: JPG, PNG, GIF, PDF (max 5MB).
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
                <?php if ($transaction['type'] == 'inkomst'): ?>
                <button type="button" id="invoicePrintBtn"
                   class="btn btn-primary"
                   title="Opslaan en factuur als PDF printen">
                    <i class="fas fa-file-pdf"></i> Factuur Printen
                </button>
                <?php endif; ?>
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
            // Save + print invoice: submit form via fetch, then open PDF
            const invoicePrintBtn = document.getElementById('invoicePrintBtn');
            if (invoicePrintBtn) {
                invoicePrintBtn.addEventListener('click', async function() {
                    const form = document.querySelector('.transaction-form');
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opslaan...';
                    try {
                        const formData = new FormData(form);
                        const response = await fetch('edit.php?id=<?php echo $id; ?>', {
                            method: 'POST',
                            body: formData,
                            headers: {'X-Requested-With': 'XMLHttpRequest'}
                        });
                        const result = await response.json();
                        if (result.success) {
                            btn.innerHTML = '<i class="fas fa-file-pdf"></i> PDF openen...';
                            window.open('../pdf/invoice_pdf.php?id=' + result.transaction_id, '_blank');
                            window.location.href = '../index.php';
                        } else {
                            throw new Error('Save failed');
                        }
                    } catch (err) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-file-pdf"></i> Factuur Printen';
                        alert('Er is een fout opgetreden bij het opslaan.');
                    }
                });
            }

            const typeSelect = document.getElementById('type');
            const vatDeductible = document.getElementById('vat_deductible');
            const vatDeductibleLabel = document.querySelector('label[for="vat_deductible"]');
            const dateInput = document.getElementById('date');
            const vatPercentageSelect = document.getElementById('vat_percentage');
            const amountInput = document.getElementById('amount');
            const vatIncludedCheckbox = document.getElementById('vat_included');
            const debiteurGroup = document.getElementById('debiteur-group');
            const crediteurGroup = document.getElementById('crediteur-group');
            const debiteurSelect = document.getElementById('relation_id_debiteur');
            const crediteurSelect = document.getElementById('relation_id_crediteur');

            // Update relation dropdown visibility based on transaction type
            function updateRelationDropdown() {
                if (typeSelect.value === 'inkomst') {
                    // Show debiteur, hide crediteur
                    debiteurGroup.style.display = 'block';
                    crediteurGroup.style.display = 'none';
                    // Disable crediteur select to prevent submission
                    crediteurSelect.disabled = true;
                    debiteurSelect.disabled = false;
                } else {
                    // Show crediteur, hide debiteur
                    crediteurGroup.style.display = 'block';
                    debiteurGroup.style.display = 'none';
                    // Disable debiteur select to prevent submission
                    debiteurSelect.disabled = true;
                    crediteurSelect.disabled = false;
                }
            }

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
            typeSelect.addEventListener('change', function() {
                updateVatDeductible();
                updateRelationDropdown();
            });
            dateInput.addEventListener('change', function() {
                updateVatRatesForDate(this.value);
            });
            amountInput.addEventListener('input', updateVatCalculation);
            vatPercentageSelect.addEventListener('change', updateVatCalculation);
            vatIncludedCheckbox.addEventListener('change', updateVatCalculation);

            // Initial calls
            updateVatDeductible();
            updateRelationDropdown();
            updateVatCalculation();

            // Also trigger calculation on page load if there's already VAT data
            if (parseFloat(vatPercentageSelect.value) > 0 && parseFloat(amountInput.value) !== 0) {
                updateVatCalculation();
            }
        });
    </script>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'theme_toggle.php'; ?>
</body>
</html>