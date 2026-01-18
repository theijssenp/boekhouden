<?php
require 'config.php';
require 'auth_functions.php';

// Require admin access
require_admin();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $is_system = isset($_POST['is_system']) ? 1 : 0;
                $user_id = $is_system ? 1 : get_current_user_id(); // System categories owned by admin (id: 1)
                
                // Validate
                if (empty($name)) {
                    throw new Exception('Naam is verplicht');
                }
                
                if ($_POST['action'] === 'add') {
                    // Check if category already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Categorie met deze naam bestaat al');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO categories (name, description, user_id, is_system)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $user_id, $is_system]);
                    $message = 'Categorie succesvol toegevoegd';
                    $messageType = 'success';
                    
                    // Log audit action
                    log_audit_action('category_add', "Added category: $name");
                } else {
                    $id = (int)$_POST['id'];
                    
                    // Check if category exists and user has permission
                    if (!can_access_category($id)) {
                        throw new Exception('Geen toegang tot deze categorie');
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE categories 
                        SET name = ?, description = ?, is_system = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $is_system, $id]);
                    $message = 'Categorie succesvol bijgewerkt';
                    $messageType = 'success';
                    
                    // Log audit action
                    log_audit_action('category_edit', "Updated category ID: $id");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = (int)$_POST['id'];
                
                // Check if category exists and user has permission
                if (!can_access_category($id)) {
                    throw new Exception('Geen toegang tot deze categorie');
                }
                
                // Check if category is in use
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Categorie kan niet worden verwijderd omdat deze in gebruik is');
                }
                
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Categorie succesvol verwijderd';
                $messageType = 'success';
                
                // Log audit action
                log_audit_action('category_delete', "Deleted category ID: $id");
            }
        } catch (Exception $e) {
            $message = 'Fout: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all categories with user info
$categories = [];
$stmt = $pdo->query("
    SELECT c.*, u.username, u.full_name as user_full_name
    FROM categories c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.is_system DESC, c.name
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category for editing
$editCategory = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    if (can_access_category($id)) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Set page title for header
$page_title = 'Categorieën Beheer';
$show_nav = true;
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="admin-header">
        <h1><i class="fas fa-tags"></i> Categorieën Beheer</h1>
        <p class="admin-subtitle">Beheer systeem- en gebruikerscategorieën voor transacties</p>
        
        <div class="admin-navigation">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin_users.php" class="btn btn-secondary btn-sm"><i class="fas fa-users"></i> Gebruikers</a>
            <a href="vat_rates_admin.php" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i> BTW Tarieven</a>
            <a href="backup_interface.php" class="btn btn-secondary btn-sm"><i class="fas fa-database"></i> Backups</a>
            <a href="../index.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Transacties</a>
        </div>
    </div>

    <style>
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .categories-table th,
        .categories-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .categories-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .categories-table tr:hover {
            background-color: #f8f9fa;
        }
        .system-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .system-badge.system {
            background-color: #d4edda;
            color: #155724;
        }
        .system-badge.user {
            background-color: #d1ecf1;
            color: #0c5460;
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
    
    <div class="form-grid">
        <div class="form-card">
            <h2 class="section-title"><?php echo $editCategory ? 'Categorie Bewerken' : 'Nieuwe Categorie'; ?></h2>
            <form method="post">
                <?php if ($editCategory): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                <?php else: ?>
                <input type="hidden" name="action" value="add">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Naam *</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>"
                           placeholder="Bijv. Huur, Salaris, Marketing">
                </div>
                
                <div class="form-group">
                    <label for="description">Omschrijving</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Optionele beschrijving van deze categorie"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_system" name="is_system" value="1" 
                               <?php echo ($editCategory && $editCategory['is_system']) ? 'checked' : ''; ?>
                               <?php echo ($editCategory && $editCategory['is_system']) ? 'disabled' : ''; ?>>
                        <label for="is_system">Systeemcategorie</label>
                    </div>
                    <small class="neutral">
                        Systeemcategorieën zijn beschikbaar voor alle gebruikers en worden beheerd door de administrator.
                        <?php if ($editCategory && $editCategory['is_system']): ?>
                        <br><strong>Let op:</strong> Dit is een systeemcategorie en kan niet worden gewijzigd naar gebruikerscategorie.
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editCategory ? 'Bijwerken' : 'Toevoegen'; ?>
                    </button>
                    <?php if ($editCategory): ?>
                    <a href="admin_categories.php" class="btn btn-secondary">Annuleren</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="form-card">
            <h2 class="section-title">Huidige Categorieën</h2>
            <p class="neutral">Overzicht van alle categorieën, gesorteerd op type (systeem eerst).</p>
            
            <?php if (empty($categories)): ?>
            <div class="message info">
                Nog geen categorieën geconfigureerd. Voeg de eerste categorie toe.
            </div>
            <?php else: ?>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Type</th>
                        <th>Eigenaar</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            <?php if (isset($category['description']) && $category['description']): ?>
                            <br><small class="neutral"><?php echo htmlspecialchars($category['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="system-badge <?php echo $category['is_system'] ? 'system' : 'user'; ?>">
                                <?php echo $category['is_system'] ? 'Systeem' : 'Gebruiker'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($category['user_full_name']): ?>
                            <?php echo htmlspecialchars($category['user_full_name']); ?>
                            <?php elseif ($category['username']): ?>
                            <?php echo htmlspecialchars($category['username']); ?>
                            <?php else: ?>
                            <span class="neutral">Onbekend</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if (can_access_category($category['id'])): ?>
                                <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                <?php if (!$category['is_system']): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Weet u zeker dat u deze categorie wilt verwijderen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Verwijderen</button>
                                </form>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="neutral">Geen toegang</span>
                                <?php endif; ?>
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
                    <li><strong>Systeemcategorieën</strong> zijn beschikbaar voor alle gebruikers en kunnen niet worden verwijderd</li>
                    <li><strong>Gebruikerscategorieën</strong> zijn alleen beschikbaar voor de eigenaar en de administrator</li>
                    <li>Categorieën die in gebruik zijn (transacties) kunnen niet worden verwijderd</li>
                    <li>Standaard systeemcategorieën worden automatisch aangemaakt bij installatie</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h3 class="card-title">Standaard Categorie Voorbeelden</h3>
        <div class="alert alert-info">
            <p><strong>Standaard systeemcategorieën (aanbevolen):</strong></p>
            <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                <li><strong>Inkomsten</strong> - Alle inkomstenbronnen (verkoop, diensten, rente)</li>
                <li><strong>Uitgaven</strong> - Algemene bedrijfskosten</li>
                <li><strong>Overig</strong> - Diverse transacties die niet in andere categorieën passen</li>
                <li><strong>Huur</strong> - Huur- en leasekosten</li>
                <li><strong>Salarissen</strong> - Loonkosten en salarissen</li>
                <li><strong>Marketing</strong> - Reclame- en marketingkosten</li>
                <li><strong>Transport</strong> - Vervoerskosten en brandstof</li>
                <li><strong>Kantoor</strong> - Kantoorbenodigdheden en -kosten</li>
            </ul>
            <p><strong>Gebruikerscategorieën:</strong></p>
            <ul style="margin: 0.5rem 0 0.5rem 1.5rem;">
                <li>Gebruikers kunnen hun eigen categorieën aanmaken voor specifieke behoeften</li>
                <li>Elke gebruiker ziet alleen zijn eigen categorieën plus systeemcategorieën</li>
                <li>Administrators kunnen alle categorieën beheren</li>
            </ul>
        </div>
    </div>
    
    <div class="btn-group">
        <a href="admin_dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Terug naar Dashboard</a>
        <a href="../index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Transacties</a>
    </div>
</div> <!-- Close container div -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Disable system checkbox if editing a system category
        const isSystemCheckbox = document.getElementById('is_system');
        if (isSystemCheckbox && isSystemCheckbox.disabled) {
            // Add explanation tooltip
            isSystemCheckbox.title = 'Systeemcategorieën kunnen niet worden gewijzigd naar gebruikerscategorieën';
        }
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const nameInput = document.getElementById('name');
                if (!nameInput.value.trim()) {
                    alert('Naam is verplicht');
                    e.preventDefault();
                    nameInput.focus();
                }
            });
        }
    });
</script>

<?php include 'footer.php'; ?>
