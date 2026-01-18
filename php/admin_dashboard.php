<?php
/**
 * Admin Dashboard for Boekhouden
 */

require 'auth_functions.php';
require_admin();

$page_title = "Admin Dashboard";
$show_nav = true;
include 'header.php';

// Get statistics
global $pdo;

// User statistics
$user_stats = [
    'total_users' => 0,
    'active_users' => 0,
    'administrators' => 0,
    'administratie_houders' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $user_stats['active_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'administrator'");
    $user_stats['administrators'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'administratie_houder'");
    $user_stats['administratie_houders'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore errors for now
}

// Transaction statistics
$transaction_stats = [
    'total_transactions' => 0,
    'transactions_today' => 0,
    'total_income' => 0,
    'total_expenses' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions");
    $transaction_stats['total_transactions'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE DATE(date) = CURDATE()");
    $transaction_stats['transactions_today'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'inkomst'");
    $transaction_stats['total_income'] = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'uitgave'");
    $transaction_stats['total_expenses'] = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    // Ignore errors for now
}

// System statistics
$system_stats = [
    'active_sessions' => 0,
    'audit_log_entries' => 0,
    'backup_count' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_sessions WHERE expires_at > NOW()");
    $system_stats['active_sessions'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_log");
    $system_stats['audit_log_entries'] = $stmt->fetchColumn();
    
    // Count backup files
    $backup_dir = 'backups/';
    if (is_dir($backup_dir)) {
        $backup_files = glob($backup_dir . '*.sql');
        $system_stats['backup_count'] = count($backup_files);
    }
} catch (Exception $e) {
    // Ignore errors for now
}
?>

<div class="container">
    <h1>Admin Dashboard</h1>
    
    <div class="welcome-message">
        <p>Welkom, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>! Je bent ingelogd als administrator.</p>
    </div>
    
    <!-- Quick Stats -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3><?php echo $user_stats['total_users']; ?></h3>
                <p>Totaal gebruikers</p>
                <small><?php echo $user_stats['active_users']; ?> actief</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3><?php echo $transaction_stats['total_transactions']; ?></h3>
                <p>Transacties</p>
                <small><?php echo $transaction_stats['transactions_today']; ?> vandaag</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <h3>€<?php echo number_format($transaction_stats['total_income'] - $transaction_stats['total_expenses'], 2, ',', '.'); ?></h3>
                <p>Netto resultaat</p>
                <small>Ink: €<?php echo number_format($transaction_stats['total_income'], 2, ',', '.'); ?> | Uit: €<?php echo number_format($transaction_stats['total_expenses'], 2, ',', '.'); ?></small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🔒</div>
            <div class="stat-content">
                <h3><?php echo $system_stats['active_sessions']; ?></h3>
                <p>Actieve sessies</p>
                <small><?php echo $system_stats['backup_count']; ?> backups</small>
            </div>
        </div>
    </div>
    
    <!-- Admin Actions Grid -->
    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2><i class="fas fa-users"></i> Gebruikersbeheer</h2>
            <div class="action-list">
                <a href="admin_users.php" class="action-button">
                    <i class="fas fa-user-plus"></i>
                    <span>Nieuwe gebruiker aanmaken</span>
                </a>
                <a href="admin_users.php?action=list" class="action-button">
                    <i class="fas fa-list"></i>
                    <span>Alle gebruikers bekijken</span>
                </a>
                <a href="admin_users.php?action=search" class="action-button">
                    <i class="fas fa-search"></i>
                    <span>Gebruiker zoeken</span>
                </a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2><i class="fas fa-database"></i> Database Beheer</h2>
            <div class="action-list">
                <a href="backup_interface.php" class="action-button">
                    <i class="fas fa-download"></i>
                    <span>Database backup maken</span>
                </a>
                <a href="backup_interface.php?action=list" class="action-button">
                    <i class="fas fa-archive"></i>
                    <span>Backups beheren</span>
                </a>
                <a href="test_backup_restore.php" class="action-button">
                    <i class="fas fa-redo"></i>
                    <span>Backup testen/herstellen</span>
                </a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2><i class="fas fa-percentage"></i> BTW & Categorieën</h2>
            <div class="action-list">
                <a href="vat_rates_admin.php" class="action-button">
                    <i class="fas fa-chart-line"></i>
                    <span>BTW tarieven beheren</span>
                </a>
                <a href="admin_categories.php" class="action-button">
                    <i class="fas fa-tags"></i>
                    <span>Categorieën beheren</span>
                </a>
                <a href="btw_kwartaal.php" class="action-button">
                    <i class="fas fa-file-invoice"></i>
                    <span>BTW overzicht bekijken</span>
                </a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2><i class="fas fa-chart-bar"></i> Rapportages</h2>
            <div class="action-list">
                <a href="profit_loss.php" class="action-button">
                    <i class="fas fa-chart-pie"></i>
                    <span>Winst & Verlies</span>
                </a>
                <a href="balans.php" class="action-button">
                    <i class="fas fa-balance-scale"></i>
                    <span>Balans</span>
                </a>
                <a href="admin_audit_log.php" class="action-button">
                    <i class="fas fa-history"></i>
                    <span>Audit log bekijken</span>
                </a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2><i class="fas fa-cogs"></i> Systeem Instellingen</h2>
            <div class="action-list">
                <a href="admin_settings.php" class="action-button">
                    <i class="fas fa-sliders-h"></i>
                    <span>Systeem instellingen</span>
                </a>
                <a href="apply_user_schema.php" class="action-button">
                    <i class="fas fa-database"></i>
                    <span>Database schema bijwerken</span>
                </a>
                <a href="sample_data_interface.php" class="action-button">
                    <i class="fas fa-vial"></i>
                    <span>Testdata genereren</span>
                </a>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2><i class="fas fa-shield-alt"></i> Beveiliging</h2>
            <div class="action-list">
                <a href="admin_sessions.php" class="action-button">
                    <i class="fas fa-user-clock"></i>
                    <span>Actieve sessies</span>
                </a>
                <a href="admin_password_reset.php" class="action-button">
                    <i class="fas fa-key"></i>
                    <span>Wachtwoord resetten</span>
                </a>
                <a href="../logout.php" class="action-button">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Uitloggen</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="recent-activity">
        <h2><i class="fas fa-history"></i> Recente Activiteit</h2>
        
        <?php
        try {
            $stmt = $pdo->query("
                SELECT al.*, u.username, u.full_name 
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT 10
            ");
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($activities) > 0) {
                echo '<div class="activity-list">';
                foreach ($activities as $activity) {
                    $time_ago = time_ago($activity['created_at']);
                    $user_name = $activity['full_name'] ?: $activity['username'] ?: 'Systeem';
                    
                    echo '<div class="activity-item">';
                    echo '<div class="activity-icon">' . get_activity_icon($activity['action_type']) . '</div>';
                    echo '<div class="activity-content">';
                    echo '<strong>' . htmlspecialchars($user_name) . '</strong> ' . htmlspecialchars($activity['action_details'] ?? $activity['action_type']);
                    echo '<div class="activity-time">' . $time_ago . '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p>Geen recente activiteit gevonden.</p>';
            }
        } catch (Exception $e) {
            echo '<p>Kon recente activiteit niet laden: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <!-- System Status -->
    <div class="system-status">
        <h2><i class="fas fa-server"></i> Systeem Status</h2>
        <div class="status-grid">
            <div class="status-item status-ok">
                <i class="fas fa-database"></i>
                <span>Database</span>
                <small>Verbonden</small>
            </div>
            <div class="status-item status-ok">
                <i class="fas fa-user-shield"></i>
                <span>Authenticatie</span>
                <small>Actief</small>
            </div>
            <div class="status-item status-ok">
                <i class="fas fa-file-export"></i>
                <span>Backups</span>
                <small><?php echo $system_stats['backup_count']; ?> beschikbaar</small>
            </div>
            <div class="status-item status-ok">
                <i class="fas fa-clock"></i>
                <span>Sessies</span>
                <small><?php echo $system_stats['active_sessions']; ?> actief</small>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'zojuist';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "$minutes minuten geleden";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours uur geleden";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "$days dagen geleden";
    } else {
        return date('d-m-Y H:i', $time);
    }
}

function get_activity_icon($action_type) {
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
        'vat_update' => '📊✏️',
        'category_update' => '🏷️✏️',
    ];
    
    return $icons[$action_type] ?? '📝';
}
?>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 40px;
    margin-right: 20px;
    opacity: 0.8;
}

.stat-content h3 {
    margin: 0;
    font-size: 28px;
    color: #2c3e50;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #7f8c8d;
    font-weight: 500;
}

.stat-content small {
    color: #95a5a6;
    font-size: 12px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin: 40px 0;
}

.dashboard-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.dashboard-section h2 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.dashboard-section h2 i {
    color: #3498db;
}

.action-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 15px;
}

.action-button {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s;
    border: 1px solid #e9ecef;
}

.action-button:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
    transform: translateX(5px);
}

.action-button i {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.action-button span {
    flex: 1;
    font-weight: 500;
}

.recent-activity {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin: 40px 0;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.recent-activity h2 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.recent-activity h2 i {
    color: #3498db;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #3498db;
}

.activity-icon {
    font-size: 20px;
    margin-top: 2px;
}

.activity-content {
    flex: 1;
}

.activity-content strong {
    color: #2c3e50;
}

.activity-time {
    font-size: 12px;
    color: #95a5a6;
    margin-top: 5px;
}

.system-status {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin: 40px 0;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.system-status h2 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.system-status h2 i {
    color: #3498db;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.status-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    background: #f8f9fa;
}

.status-item i {
    font-size: 30px;
    margin-bottom: 10px;
    color: #3498db;
}

.status-item span {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.status-item small {
    color: #7f8c8d;
    font-size: 12px;
}

.status-ok {
    border-top: 4px solid #28a745;
}

.status-warning {
    border-top: 4px solid #ffc107;
}

.status-error {
    border-top: 4px solid #dc3545;
}

.welcome-message {
    background: #e3f2fd;
    border-radius: 8px;
    padding: 15px 20px;
    margin: 20px 0;
    border-left: 4px solid #3498db;
}

.welcome-message p {
    margin: 0;
    color: #2c3e50;
    font-weight: 500;
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .status-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'footer.php'; ?>