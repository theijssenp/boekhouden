<?php
/**
 * User Management for Administrators
 */

require 'auth_functions.php';
require_admin();

$page_title = "Gebruikersbeheer";
$show_nav = true;
include 'header.php';

// Handle actions
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    
    if ($post_action === 'create') {
        // Create new user
        $user_data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'full_name' => trim($_POST['full_name'] ?? ''),
            'user_type' => $_POST['user_type'] ?? 'administratie_houder',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $result = create_user($user_data);
        if ($result['success']) {
            $message = "Gebruiker '{$user_data['username']}' succesvol aangemaakt!";
            $action = 'list'; // Switch to list view
        } else {
            $error = $result['message'];
        }
    } elseif ($post_action === 'update') {
        // Update existing user
        $update_id = $_POST['user_id'] ?? 0;
        $user_data = [];
        
        if (!empty($_POST['full_name'])) $user_data['full_name'] = trim($_POST['full_name']);
        if (!empty($_POST['email'])) $user_data['email'] = trim($_POST['email']);
        if (!empty($_POST['user_type'])) $user_data['user_type'] = $_POST['user_type'];
        if (isset($_POST['is_active'])) $user_data['is_active'] = $_POST['is_active'] ? 1 : 0;
        if (!empty($_POST['password'])) $user_data['password'] = $_POST['password'];
        
        if (!empty($user_data)) {
            $result = update_user($update_id, $user_data);
            if ($result['success']) {
                $message = "Gebruiker succesvol bijgewerkt!";
                $action = 'list';
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($post_action === 'delete') {
        // Delete user
        $delete_id = $_POST['user_id'] ?? 0;
        $result = delete_user($delete_id);
        if ($result['success']) {
            $message = "Gebruiker succesvol verwijderd!";
            $action = 'list';
        } else {
            $error = $result['message'];
        }
    }
}

// Get user data for edit view
$edit_user = null;
if ($user_id && ($action === 'edit' || $action === 'view')) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_user) {
            $error = "Gebruiker niet gevonden.";
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = "Fout bij ophalen gebruiker: " . $e->getMessage();
        $action = 'list';
    }
}
?>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Action Tabs -->
    <div class="tabs">
        <a href="?action=list" class="tab <?php echo $action === 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> Alle gebruikers
        </a>
        <a href="?action=create" class="tab <?php echo $action === 'create' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i> Nieuwe gebruiker
        </a>
        <?php if ($edit_user): ?>
            <a href="?action=edit&id=<?php echo $user_id; ?>" class="tab <?php echo $action === 'edit' ? 'active' : ''; ?>">
                <i class="fas fa-edit"></i> Bewerk gebruiker
            </a>
            <a href="?action=view&id=<?php echo $user_id; ?>" class="tab <?php echo $action === 'view' ? 'active' : ''; ?>">
                <i class="fas fa-eye"></i> Bekijk gebruiker
            </a>
        <?php endif; ?>
        <a href="?action=search" class="tab <?php echo $action === 'search' ? 'active' : ''; ?>">
            <i class="fas fa-search"></i> Zoeken
        </a>
    </div>
    
    <!-- Content based on action -->
    <div class="tab-content">
        <?php if ($action === 'list'): ?>
            <!-- User List -->
            <h2>Alle gebruikers</h2>
            
            <?php
            $users = get_all_users();
            if (count($users) > 0):
            ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gebruikersnaam</th>
                                <th>Naam</th>
                                <th>E-mail</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Aangemaakt</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['id'] == get_current_user_id()): ?>
                                            <span class="badge badge-info">Jij</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['user_type'] === 'administrator' ? 'danger' : 'primary'; ?>">
                                            <?php echo $user['user_type'] === 'administrator' ? 'Admin' : 'Gebruiker'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['is_active'] ? 'Actief' : 'Inactief'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="?action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Bekijken">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Bewerken">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != get_current_user_id()): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    title="Verwijderen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="summary">
                    <p>Totaal: <?php echo count($users); ?> gebruikers (<?php 
                        $active_count = array_filter($users, fn($u) => $u['is_active']);
                        echo count($active_count); ?> actief)
                    </p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users fa-3x"></i>
                    <h3>Geen gebruikers gevonden</h3>
                    <p>Er zijn nog geen gebruikers aangemaakt.</p>
                    <a href="?action=create" class="btn btn-primary">Eerste gebruiker aanmaken</a>
                </div>
            <?php endif; ?>
            
        <?php elseif ($action === 'create'): ?>
            <!-- Create User Form -->
            <h2>Nieuwe gebruiker aanmaken</h2>
            
            <form method="POST" class="form-card">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Gebruikersnaam *</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="bijv. janjansen" maxlength="50">
                        <small>Unieke gebruikersnaam voor inloggen</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-mailadres *</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="bijv. jan@voorbeeld.nl">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Volledige naam *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               placeholder="bijv. Jan Jansen">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Wachtwoord *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Minimaal 8 tekens">
                        <small>Wachtwoord moet minimaal 8 tekens bevatten</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_type">Gebruikerstype *</label>
                        <select id="user_type" name="user_type" required>
                            <option value="administratie_houder" selected>Administratie houder</option>
                            <option value="administrator">Administrator</option>
                        </select>
                        <small>Administrators hebben volledige toegang tot het systeem</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="is_active">Account status</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label for="is_active">Account actief</label>
                        </div>
                        <small>Inactieve accounts kunnen niet inloggen</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Gebruiker aanmaken
                    </button>
                    <a href="?action=list" class="btn btn-secondary">Annuleren</a>
                </div>
            </form>
            
        <?php elseif ($action === 'edit' && $edit_user): ?>
            <!-- Edit User Form -->
            <h2>Gebruiker bewerken: <?php echo htmlspecialchars($edit_user['username']); ?></h2>
            
            <form method="POST" class="form-card">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Gebruikersnaam</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" disabled>
                        <small>Gebruikersnaam kan niet worden gewijzigd</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-mailadres *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Volledige naam *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($edit_user['full_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Nieuw wachtwoord</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Laat leeg om niet te wijzigen">
                        <small>Alleen invullen als je het wachtwoord wilt wijzigen</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_type">Gebruikerstype *</label>
                        <select id="user_type" name="user_type" required>
                            <option value="administratie_houder" <?php echo $edit_user['user_type'] === 'administratie_houder' ? 'selected' : ''; ?>>Administratie houder</option>
                            <option value="administrator" <?php echo $edit_user['user_type'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="is_active">Account status</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">Account actief</label>
                        </div>
                    </div>
                </div>
                
                <div class="user-info">
                    <h4>Account informatie</h4>
                    <ul>
                        <li>Aangemaakt op: <?php echo date('d-m-Y H:i', strtotime($edit_user['created_at'])); ?></li>
                        <li>Laatste login: <?php echo $edit_user['last_login'] ? date('d-m-Y H:i', strtotime($edit_user['last_login'])) : 'Nooit'; ?></li>
                        <li>Aangemaakt door: <?php 
                            if ($edit_user['created_by']) {
                                try {
                                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                    $stmt->execute([$edit_user['created_by']]);
                                    $creator = $stmt->fetchColumn();
                                    echo htmlspecialchars($creator ?: 'Onbekend');
                                } catch (Exception $e) {
                                    echo 'Onbekend';
                                }
                            } else {
                                echo 'Systeem';
                            }
                        ?></li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Wijzigingen opslaan
                    </button>
                    <a href="?action=list" class="btn btn-secondary">Annuleren</a>
                    <?php if ($edit_user['id'] != get_current_user_id()): ?>
                        <button type="button" class="btn btn-danger" 
                                onclick="confirmDelete(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')">
                            <i class="fas fa-trash"></i> Verwijderen
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
        <?php elseif ($action === 'view' && $edit_user): ?>
            <!-- View User Details -->
            <h2>Gebruiker: <?php echo htmlspecialchars($edit_user['full_name']); ?></h2>
            
            <div class="user-profile">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($edit_user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($edit_user['full_name']); ?></h3>
                        <p class="profile-username">@<?php echo htmlspecialchars($edit_user['username']); ?></p>
                        <div class="profile-badges">
                            <span class="badge badge-<?php echo $edit_user['user_type'] === 'administrator' ? 'danger' : 'primary'; ?>">
                                <?php echo $edit_user['user_type'] === 'administrator' ? 'Administrator' : 'Administratie houder'; ?>
                            </span>
                            <span class="badge badge-<?php echo $edit_user['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $edit_user['is_active'] ? 'Actief' : 'Inactief'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <h4>Contact informatie</h4>
                        <ul>
                            <li><strong>E-mail:</strong> <?php echo htmlspecialchars($edit_user['email']); ?></li>
                            <li><strong>Gebruikersnaam:</strong> <?php echo htmlspecialchars($edit_user['username']); ?></li>
                            <li><strong>Account type:</strong> <?php echo $edit_user['user_type'] === 'administrator' ? 'Administrator' : 'Administratie houder'; ?></li>
                            <li><strong>Status:</strong> <?php echo $edit_user['is_active'] ? 'Actief' : 'Inactief'; ?></li>
                            <li><strong>Aangemaakt op:</strong> <?php echo date('d-m-Y H:i', strtotime($edit_user['created_at'])); ?></li>
                            <li><strong>Laatste login:</strong> <?php echo $edit_user['last_login'] ? date('d-m-Y H:i', strtotime($edit_user['last_login'])) : 'Nooit'; ?></li>
                            <?php if ($edit_user['created_by']): ?>
                            <li><strong>Aangemaakt door:</strong>
                                <?php
                                    try {
                                        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$edit_user['created_by']]);
                                        $creator = $stmt->fetchColumn();
                                        echo htmlspecialchars($creator ?: 'Onbekend');
                                    } catch (Exception $e) {
                                        echo 'Onbekend';
                                    }
                                ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="profile-actions">
                        <a href="?action=edit&id=<?php echo $edit_user['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Bewerken
                        </a>
                        <a href="?action=list" class="btn btn-secondary">Terug naar overzicht</a>
                        <?php if ($edit_user['id'] != get_current_user_id()): ?>
                            <button type="button" class="btn btn-danger"
                                    onclick="confirmDelete(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')">
                                <i class="fas fa-trash"></i> Verwijderen
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php elseif ($action === 'search'): ?>
                <!-- Search Users -->
                <h2>Zoeken naar gebruikers</h2>
                
                <form method="GET" class="form-card">
                    <input type="hidden" name="action" value="search">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search_query">Zoekterm</label>
                            <input type="text" id="search_query" name="q"
                                   placeholder="Naam, gebruikersnaam of e-mail"
                                   value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="user_type_filter">Type filter</label>
                            <select id="user_type_filter" name="type">
                                <option value="">Alle types</option>
                                <option value="administrator" <?php echo ($_GET['type'] ?? '') === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="administratie_houder" <?php echo ($_GET['type'] ?? '') === 'administratie_houder' ? 'selected' : ''; ?>>Administratie houder</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status_filter">Status filter</label>
                            <select id="status_filter" name="status">
                                <option value="">Alle status</option>
                                <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Actief</option>
                                <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactief</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Zoeken
                        </button>
                        <a href="?action=list" class="btn btn-secondary">Terug naar overzicht</a>
                    </div>
                </form>
                
                <?php if (isset($_GET['q']) || isset($_GET['type']) || isset($_GET['status'])): ?>
                    <?php
                    // Build search query
                    $search_query = $_GET['q'] ?? '';
                    $type_filter = $_GET['type'] ?? '';
                    $status_filter = $_GET['status'] ?? '';
                    
                    $search_results = search_users($search_query, $type_filter, $status_filter);
                    ?>
                    
                    <h3>Zoekresultaten (<?php echo count($search_results); ?>)</h3>
                    
                    <?php if (count($search_results) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Gebruikersnaam</th>
                                        <th>Naam</th>
                                        <th>E-mail</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['user_type'] === 'administrator' ? 'danger' : 'primary'; ?>">
                                                    <?php echo $user['user_type'] === 'administrator' ? 'Admin' : 'Gebruiker'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['is_active'] ? 'Actief' : 'Inactief'; ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <a href="?action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Bekijken">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Bewerken">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Geen gebruikers gevonden die aan de zoekcriteria voldoen.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-error">
                    Ongeldige actie of gebruiker niet gevonden.
                </div>
                <a href="?action=list" class="btn btn-primary">Terug naar overzicht</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Gebruiker verwijderen</h3>
            <p>Weet je zeker dat je gebruiker "<span id="deleteUsername"></span>" wilt verwijderen?</p>
            <p class="text-warning"><strong>Waarschuwing:</strong> Deze actie kan niet ongedaan worden gemaakt!</p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">Ja, verwijderen</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
    
    <?php include 'footer.php'; ?>
