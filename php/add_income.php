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

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf_token();
    $date = $_POST['date'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];

    // I2: Validate input
    $errors = validate_transaction_input([
        'date' => $date,
        'description' => $description,
        'amount' => $amount,
        'invoice_number' => $_POST['invoice_number'] ?? null
    ]);

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    } else {
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
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <h2 class="section-title">Verkoopgegevens</h2>

        <form method="post" class="transaction-form">
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