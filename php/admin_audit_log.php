<?php
/**
 * Audit Log Viewer for Boekhouden
 */

require 'config.php';
require 'auth_functions.php';

// Require admin access
require_admin();

// Set page title for header
$page_title = 'Audit Log';
$show_nav = true;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "al.action_details LIKE ?";
    $params[] = '%' . $_GET['search'] . '%';
}

if (isset($_GET['action_type']) && !empty($_GET['action_type'])) {
    $where_conditions[] = "al.action_type = ?";
    $params[] = $_GET['action_type'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM audit_log al $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get audit log entries
$sql = "
    SELECT al.*, u.username, u.full_name, u.user_type
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$param_index = 0;
foreach ($params as $param) {
    $stmt->bindValue(++$param_index, $param);
}
$stmt->bindValue(++$param_index, $limit, PDO::PARAM_INT);
$stmt->bindValue(++$param_index, $offset, PDO::PARAM_INT);
$stmt->execute();
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Action type translations
$action_types = [
    'login' => 'Inloggen',
    'logout' => 'Uitloggen',
    'user_create' => 'Gebruiker aanmaken',
    'user_update' => 'Gebruiker bijwerken',
    'user_delete' => 'Gebruiker verwijderen',
    'transaction_create' => 'Transactie aanmaken',
    'transaction_update' => 'Transactie bijwerken',
    'transaction_delete' => 'Transactie verwijderen',
    'backup_create' => 'Backup maken',
    'backup_restore' => 'Backup herstellen',
    'vat_rate_add' => 'BTW tarief toevoegen',
    'vat_rate_edit' => 'BTW tarief bijwerken',
    'vat_rate_delete' => 'BTW tarief verwijderen',
    'category_update' => 'Categorie bijwerken',
];

// Get icons for action types
function get_action_icon($action_type) {
    $icons = [
        'login' => '🔐',
        'logout' => '🚪',
        'user_create' => '👤➕',
        'user_update' => '👤✏️',
        'user_delete' => '👤❌',
        'transaction_create' => '💰➕',
        'transaction_update' => '💰✏️',
        'transaction_delete' => '💰❌',
        'backup_create' => '💾➕',
        'backup_restore' => '💾↩️',
        'vat_rate_add' => '📊➕',
        'vat_rate_edit' => '📊✏️',
        'vat_rate_delete' => '📊❌',
        'category_update' => '🏷️✏️',
    ];
    
    return $icons[$action_type] ?? '📝';
}

include 'header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1><i class="fas fa-history"></i> Audit Log</h1>
        <p class="admin-subtitle">Overzicht van alle systeemactiviteiten</p>
        
        <div class="admin-navigation">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin_users.php" class="btn btn-secondary btn-sm"><i class="fas fa-users"></i> Gebruikers</a>
            <a href="vat_rates_admin.php" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i> BTW Tarieven</a>
            <a href="../index.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Transacties</a>
        </div>
    </div>

    <div class="stats-card">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($total_records, 0, ',', '.'); ?></div>
                <div class="stat-label">Totaal log entries</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $total_pages; ?></div>
                <div class="stat-label">Pagina's</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo count($audit_logs); ?></div>
                <div class="stat-label">Op deze pagina</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo date('d-m-Y'); ?></div>
                <div class="stat-label">Vandaag</div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="get" class="filter-form">
            <div class="form-group">
                <input type="text" name="search" placeholder="Zoek in details..." class="form-control" 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="form-group">
                <select name="action_type" class="form-control">
                    <option value="">Alle actietypen</option>
                    <?php foreach ($action_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo (isset($_GET['action_type']) && $_GET['action_type'] == $key) ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="date" name="date_from" class="form-control" 
                       value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>"
                       placeholder="Vanaf datum">
            </div>
            <div class="form-group">
                <input type="date" name="date_to" class="form-control" 
                       value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>"
                       placeholder="Tot datum">
            </div>
            <button type="submit" class="btn btn-primary">Filteren</button>
            <a href="admin_audit_log.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title">Audit Log Entries</h3>
        
        <?php if (empty($audit_logs)): ?>
        <div class="message info" style="padding: 1rem; background: #d1ecf1; color: #0c5460; border-radius: 4px; margin: 1rem 0;">
            Geen audit log entries gevonden.
        </div>
        <?php else: ?>
        
        <table class="audit-log-table">
            <thead>
                <tr>
                    <th width="150">Tijdstip</th>
                    <th width="100">Actie</th>
                    <th>Gebruiker</th>
                    <th>Details</th>
                    <th width="120">IP Adres</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit_logs as $log): ?>
                <tr>
                    <td class="timestamp">
                        <?php echo date('d-m-Y H:i', strtotime($log['created_at'])); ?>
                    </td>
                    <td>
                        <span class="action-icon"><?php echo get_action_icon($log['action_type']); ?></span>
                        <span class="action-type"><?php echo $action_types[$log['action_type']] ?? $log['action_type']; ?></span>
                    </td>
                    <td>
                        <?php if ($log['user_id']): ?>
                        <div class="user-info">
                            <?php echo htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'Onbekend'); ?>
                            <?php if ($log['user_type']): ?>
                            <span class="user-type"><?php echo htmlspecialchars($log['user_type']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="neutral">ID: <?php echo $log['user_id']; ?></div>
                        <?php else: ?>
                        <div class="neutral">Systeem</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($log['action_details'] ?? 'Geen details'); ?>
                        <?php if ($log['user_agent']): ?>
                        <div class="neutral" style="margin-top: 0.25rem; font-size: 0.75rem;">
                            <?php echo htmlspecialchars(substr($log['user_agent'], 0, 80)); ?>...
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="ip-address">
                        <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['action_type']) ? '&action_type=' . urlencode($_GET['action_type']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . urlencode($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . urlencode($_GET['date_to']) : ''; ?>">
                &laquo; Vorige
            </a>
            <?php else: ?>
            <span class="disabled">&laquo; Vorige</span>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['action_type']) ? '&action_type=' . urlencode($_GET['action_type']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . urlencode($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . urlencode($_GET['date_to']) : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['action_type']) ? '&action_type=' . urlencode($_GET['action_type']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . urlencode($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . urlencode($_GET['date_to']) : ''; ?>">
                Volgende &raquo;
            </a>
            <?php else: ?>
            <span class="disabled">Volgende &raquo;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<style>
    .audit-log-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        font-size: 0.9rem;
    }
    .audit-log-table th,
    .audit-log-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
    }
    .audit-log-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }
    .audit-log-table tr:hover {
        background-color: #f8f9fa;
    }
    .action-icon {
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }
    .user-info {
        font-weight: 500;
        color: #2c3e50;
    }
    .user-type {
        display: inline-block;
        padding: 0.1rem 0.4rem;
        border-radius: 3px;
        font-size: 0.75rem;
        background-color: #e9ecef;
        color: #495057;
        margin-left: 0.5rem;
    }
    .action-type {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
        background-color: #e3f2fd;
        color: #1565c0;
        margin-right: 0.5rem;
    }
    .timestamp {
        color: #6c757d;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    .ip-address {
        font-family: monospace;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin: 2rem 0;
    }
    .pagination a,
    .pagination span {
        padding: 0.5rem 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
        color: #3498db;
    }
    .pagination a:hover {
        background-color: #f8f9fa;
    }
    .pagination .current {
        background-color: #3498db;
        color: white;
        border-color: #3498db;
    }
    .pagination .disabled {
        color: #6c757d;
        pointer-events: none;
        opacity: 0.6;
    }
    .stats-card {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    .stat-item {
        text-align: center;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.875rem;
        color: #6c757d;
    }
    .filter-bar {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
    }
    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .form-group {
        margin: 0;
    }
    .form-control {
        padding: 0.5rem;
        border: 1px solid