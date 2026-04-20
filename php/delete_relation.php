<?php
/**
 * Relatie Verwijderen - Boekhouden
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

// Check if relation is linked to any transactions
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE relation_id = ?");
$stmt->execute([$id]);
$transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $delete_type = $_POST['delete_type'] ?? 'soft';
    
    try {
        if ($delete_type === 'hard') {
            // Hard delete: remove from database
            // First, unlink from transactions (set relation_id to NULL)
            $stmt = $pdo->prepare("UPDATE transactions SET relation_id = NULL WHERE relation_id = ?");
            $stmt->execute([$id]);
            
            // Then delete the relation
            if ($is_admin) {
                $stmt = $pdo->prepare("DELETE FROM relations WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM relations WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            }
            
            header('Location: relations.php?success=' . urlencode('Relatie permanent verwijderd'));
            exit;
        } else {
            // Soft delete: set is_active to FALSE
            if ($is_admin) {
                $stmt = $pdo->prepare("UPDATE relations SET is_active = FALSE WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE relations SET is_active = FALSE WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            }
            
            header('Location: relations.php?success=' . urlencode('Relatie gedeactiveerd'));
            exit;
        }
    } catch (PDOException $e) {
        $error_message = 'Fout bij verwijderen: ' . $e->getMessage();
    }
}

$page_title = 'Relatie Verwijderen';
$page_subtitle = 'Verwijder ' . htmlspecialchars($relation['company_name']);
$active_nav = 'relations';
$page_css = <<<CSS
        .delete-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .delete-option {
            position: relative;
        }

        .delete-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .delete-option-label {
            display: block;
            padding: 2rem;
            border: 2px solid var(--gray-medium);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            background-color: var(--bg-card);
            height: 100%;
        }

        .delete-option-label:hover {
            border-color: var(--secondary-color);
            background-color: var(--gray-light);
        }

        .delete-option input[type="radio"]:checked + .delete-option-label {
            border-color: var(--danger-color);
            background-color: rgba(231, 76, 60, 0.05);
        }

        .delete-option-label i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .delete-option-label.soft i {
            color: var(--warning-color);
        }

        .delete-option-label.hard i {
            color: var(--danger-color);
        }

        .delete-option-label .option-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .delete-option-label .option-description {
            font-size: 0.95rem;
            color: var(--gray-dark);
            line-height: 1.6;
        }

        .relation-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .relation-info h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .relation-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .relation-info-item {
            display: flex;
            flex-direction: column;
        }

        .relation-info-label {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-bottom: 0.25rem;
        }

        .relation-info-value {
            font-weight: 600;
            color: var(--primary-color);
        }
CSS;
include 'page_header.php';
?>
    <main class="main-content">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-triangle"></i> Waarschuwing:</strong> 
            Je staat op het punt een relatie te verwijderen. Dit kan niet ongedaan worden gemaakt zonder een database backup.
        </div>
        
        <h2 class="section-title">Relatie Informatie</h2>
        
        <div class="relation-info">
            <h3><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($relation['company_name']); ?></h3>
            <div class="relation-info-grid">
                <div class="relation-info-item">
                    <span class="relation-info-label">Relatiecode</span>
                    <span class="relation-info-value"><?php echo htmlspecialchars($relation['relation_code']); ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Type</span>
                    <span class="relation-info-value"><?php echo ucfirst($relation['relation_type']); ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Contactpersoon</span>
                    <span class="relation-info-value"><?php echo $relation['contact_person'] ? htmlspecialchars($relation['contact_person']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">E-mail</span>
                    <span class="relation-info-value"><?php echo $relation['email'] ? htmlspecialchars($relation['email']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">Plaats</span>
                    <span class="relation-info-value"><?php echo $relation['city'] ? htmlspecialchars($relation['city']) : '-'; ?></span>
                </div>
                <div class="relation-info-item">
                    <span class="relation-info-label">BTW-nummer</span>
                    <span class="relation-info-value"><?php echo $relation['vat_number'] ? htmlspecialchars($relation['vat_number']) : '-'; ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($transaction_count > 0): ?>
            <div class="alert alert-warning">
                <strong><i class="fas fa-link"></i> Let op:</strong> 
                Deze relatie is gekoppeld aan <strong><?php echo $transaction_count; ?></strong> transactie(s). 
                Bij een harde verwijdering worden deze koppelingen verbroken (relation_id wordt NULL).
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Kies Verwijderingsoptie</h2>
        
        <form method="post">
            <div class="delete-options">
                <div class="delete-option">
                    <input type="radio" id="soft_delete" name="delete_type" value="soft" checked>
                    <label for="soft_delete" class="delete-option-label soft">
                        <i class="fas fa-eye-slash"></i>
                        <div class="option-name">Deactiveren (Aanbevolen)</div>
                        <div class="option-description">
                            De relatie wordt gemarkeerd als inactief en verborgen in overzichten. 
                            De relatie blijft in de database bestaan en kan later weer geactiveerd worden. 
                            Koppelingen met transacties blijven intact.
                        </div>
                    </label>
                </div>
                
                <div class="delete-option">
                    <input type="radio" id="hard_delete" name="delete_type" value="hard">
                    <label for="hard_delete" class="delete-option-label hard">
                        <i class="fas fa-trash-alt"></i>
                        <div class="option-name">Permanent Verwijderen</div>
                        <div class="option-description">
                            De relatie wordt permanent uit de database verwijderd. 
                            Deze actie kan niet ongedaan worden gemaakt. 
                            Transacties blijven bestaan, maar de koppeling naar deze relatie wordt verbroken.
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-lightbulb"></i> Tip:</strong> 
                Kies voor "Deactiveren" als je de relatie misschien later nog nodig hebt of als er historische transacties aan gekoppeld zijn.
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Weet je het zeker? Deze actie verwijdert de relatie.')">
                    <i class="fas fa-trash"></i> Relatie Verwijderen
                </button>
                <a href="relations.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuleren
                </a>
                <a href="edit_relation.php?id=<?php echo $relation['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bewerken
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
