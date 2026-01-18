<?php
require 'config.php';
require 'auth_functions.php';

// Require admin access
require_admin();

// Check if vat_rates table exists
$vatRatesTableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vat_rates'");
    $vatRatesTableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $vatRatesTableExists = false;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $rate = (float)$_POST['rate'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $effective_from = $_POST['effective_from'];
                $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Validate
                if (empty($name) || empty($effective_from)) {
                    throw new Exception('Naam en ingangsdatum zijn verplicht');
                }
                
                if ($effective_to && $effective_to < $effective_from) {
                    throw new Exception('Einddatum moet na ingangsdatum liggen');
                }
                
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO vat_rates (rate, name, description, effective_from, effective_to, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$rate, $name, $description, $effective_from, $effective_to, $is_active]);
                    $message = 'BTW tarief succesvol toegevoegd';
                    $messageType = 'success';
                    
                    // Log audit action
                    log_audit_action('vat_rate_add', "Added VAT rate: $name ($rate%)");
                } else {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("
                        UPDATE vat_rates
                        SET rate = ?, name = ?, description = ?, effective_from = ?, effective_to = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$rate, $name, $description, $effective_from, $effective_to, $is_active, $id]);
                    $message = 'BTW tarief succesvol bijgewerkt';
                    $messageType = 'success';
                    
                    // Log audit action
                    log_audit_action('vat_rate_edit', "Updated VAT rate ID: $id");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM vat_rates WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'BTW tarief succesvol verwijderd';
                $messageType = 'success';
                
                // Log audit action
                log_audit_action('vat_rate_delete', "Deleted VAT rate ID: $id");
            }
        } catch (Exception $e) {
            $message = 'Fout: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all VAT rates
$vatRates = [];
if ($vatRatesTableExists) {
    $stmt = $pdo->query("
        SELECT * FROM vat_rates
        ORDER BY effective_from DESC, rate DESC
    ");
    $vatRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get VAT rate for editing
$editRate = null;
if (isset($_GET['edit']) && $vatRatesTableExists) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM vat_rates WHERE id = ?");
    $stmt->execute([$id]);
    $editRate = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Set page title for header
$page_title = 'BTW Tarieven Beheer';
$show_nav = true;
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="admin-header">
        <h1><i class="fas fa-chart-line"></i> BTW Tarieven Beheer</h1>
        <p class="admin-subtitle">Configureer historische BTW-tarieven met ingangsdatums</p>
        
        <div class="admin-navigation">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin_users.php" class="btn btn-secondary btn-sm"><i class="fas fa-users"></i> Gebruikers</a>
            <a href="backup_interface.php" class="btn btn-secondary btn-sm"><i class="fas fa-database"></i> Backups</a>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Transacties</a>
        </div>
    </div>

    <style>
        .vat-rates-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .vat-rates-table th,
        .vat-rates-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .vat-rates-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .vat-rates-table tr:hover {
            background-color: #f8f9fa;
        }
        .active-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .active-badge.active {
            background-color: #d4edda;
            color: #155724;
        }
        .active-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .date-range {
            font-size: 0.875rem;
            color: #666;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .form-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out;
        }
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background-color 0.15s ease-in-out;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .admin-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        .admin-subtitle {
            color: #6c757d;
            margin-top: 0.5rem;
        }
        .admin-navigation {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .section-title {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #2c3e50;
            font-size: 1.25rem;
        }
        .neutral {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        .card-title {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
    </style>

        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$vatRatesTableExists): ?>
        <div class="message info">
            <strong>Let op:</strong> De BTW-tarieven tabel bestaat nog niet in de database.
            <p>Voer eerst het migratiescript uit om de tabel aan te maken:</p>
            <pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px; margin: 1rem 0;">
mysql -u root -p boekhouden < migrate_vat_rates.sql</pre>
            <p>Of voer het SQL-script handmatig uit vanuit <code>schema_vat_rates.sql</code>.</p>
        </div>
        <?php else: ?>
        
        <div class="form-grid">
            <div class="form-card">
                <h2 class="section-title"><?php echo $editRate ? 'BTW Tarief Bewerken' : 'Nieuw BTW Tarief'; ?></h2>
                <form method="post">
                    <?php if ($editRate): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $editRate['id']; ?>">
                    <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="rate">BTW Percentage *</label>
                        <input type="number" id="rate" name="rate" class="form-control" 
                               step="0.01" min="0" max="100" required
                               value="<?php echo $editRate ? $editRate['rate'] : '21'; ?>">
                        <small class="neutral">Bijvoorbeeld: 21 voor 21%, 9 voor 9%, 0 voor 0%</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Naam *</label>
                        <input type="text" id="name" name="name" class="form-control" required
                               value="<?php echo $editRate ? htmlspecialchars($editRate['name']) : 'Hoog tarief'; ?>"
                               placeholder="Bijv. Hoog tarief, Verlaagd tarief, Vrijgesteld">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Omschrijving</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Optionele beschrijving van dit tarief"><?php echo $editRate ? htmlspecialchars($editRate['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="effective_from">Ingangsdatum *</label>
                        <input type="date" id="effective_from" name="effective_from" class="form-control" required
                               value="<?php echo $editRate ? $editRate['effective_from'] : date('Y-m-d'); ?>">
                        <small class="neutral">Vanaf welke datum is dit tarief van toepassing?</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="effective_to">Einddatum (optioneel)</label>
                        <input type="date" id="effective_to" name="effective_to" class="form-control"
                               value="<?php echo $editRate ? $editRate['effective_to'] : ''; ?>">
                        <small class="neutral">Laat leeg als dit tarief nog steeds geldig is</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo (!$editRate || $editRate['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active">Actief tarief</label>
                        </div>
                        <small class="neutral">Inactieve tarieven worden niet gebruikt bij nieuwe transacties</small>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editRate ? 'Bijwerken' : 'Toevoegen'; ?>
                        </button>
                        <?php if ($editRate): ?>
                        <a href="vat_rates_admin.php" class="btn btn-secondary">Annuleren</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="form-card">
                <h2 class="section-title">Huidige BTW Tarieven</h2>
                <p class="neutral">Overzicht van alle geconfigureerde BTW-tarieven, gesorteerd op ingangsdatum.</p>
                
                <?php if (empty($vatRates)): ?>
                <div class="message info">
                    Nog geen BTW-tarieven geconfigureerd. Voeg het eerste tarief toe.
                </div>
                <?php else: ?>
                <table class="vat-rates-table">
                    <thead>
                        <tr>
                            <th>Percentage</th>
                            <th>Naam</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vatRates as $rate): ?>
                        <tr>
                            <td><strong><?php echo $rate['rate']; ?>%</strong></td>
                            <td>
                                <?php echo htmlspecialchars($rate['name']); ?>
                                <?php if ($rate['description']): ?>
                                <br><small class="neutral"><?php echo htmlspecialchars($rate['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="date-range">
                                <?php echo date('d-m-Y', strtotime($rate['effective_from'])); ?>
                                <?php if ($rate['effective_to']): ?>
                                <br>t/m <?php echo date('d-m-Y', strtotime($rate['effective_to'])); ?>
                                <?php else: ?>
                                <br>t/m heden
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="active-badge <?php echo $rate['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $rate['is_active'] ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit=<?php echo $rate['id']; ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Weet u zeker dat u dit BTW-tarief wilt verwijderen?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $rate['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Verwijderen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <div class="alert alert-info" style="margin-top: 1rem;">
                    <p><strong>Belangrijke informatie:</strong></p>
                    <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                        <li>BTW-tarieven worden automatisch geselecteerd op basis van de transactiedatum</li>
                        <li>Voor elke datum wordt het meest recente actieve tarief gebruikt</li>
                        <li>Tariefwijzigingen (bijv. 21% → 22% in 2026) kunnen worden geconfigureerd met overlappende datums</li>
                        <li>Inactieve tarieven worden niet getoond in dropdowns bij nieuwe transacties</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">BTW Tarief Voorbeelden</h3>
            <div class="alert alert-info">
                <p><strong>Standaard Nederlandse BTW-tarieven:</strong></p>
                <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                    <li><strong>21% - Hoog tarief:</strong> Voor de meeste goederen en diensten</li>
                    <li><strong>9% - Verlaagd tarief:</strong> Voor voedingsmiddelen, boeken, kunst, medicijnen</li>
                    <li><strong>0% - Vrijgesteld:</strong> Voor gezondheidszorg, onderwijs, financiële diensten</li>
                </ul>
                <p><strong>Historische tariefwijzigingen:</strong></p>
                <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                    <li>Vanaf 1 januari 2026: Hoog tarief van 21% naar 22% (voorbeeld)</li>
                    <li>Vanaf 1 juli 2025: Verlaagd tarief van 9% naar 10% (voorbeeld)</li>
                </ul>
                <p>Configureer tariefwijzigingen door een nieuw tarief toe te voegen met de juiste ingangsdatum.</p>
            </div>
        </div>
        
        <div class="btn-group">
            <a href="index.php" class="btn btn-primary">Terug naar Transacties</a>
            <a href="btw_kwartaal.php" class="btn btn-secondary">BTW Kwartaal Overzicht</a>
        </div>
        
        <?php endif; ?>
    </div> <!-- Close container div -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default effective_to to empty if not set
            const effectiveToInput = document.getElementById('effective_to');
            if (effectiveToInput && !effectiveToInput.value) {
                effectiveToInput.value = '';
            }
            
            // Validate date ranges
            const effectiveFromInput = document.getElementById('effective_from');
            const effectiveToInput2 = document.getElementById('effective_to');
            
            function validateDates() {
                if (effectiveFromInput.value && effectiveToInput2.value) {
                    const fromDate = new Date(effectiveFromInput.value);
                    const toDate = new Date(effectiveToInput2.value);
                    
                    if (toDate < fromDate) {
                        alert('Einddatum moet na ingangsdatum liggen');
                        effectiveToInput2.value = '';
                        return false;
                    }
                }
                return true;
            }
            
            effectiveFromInput.addEventListener('change', validateDates);
            effectiveToInput2.addEventListener('change', validateDates);
            
            // Form submission validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateDates()) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>

<?php include 'footer.php'; ?>