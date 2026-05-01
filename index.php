<?php
/**
 * Boekhouden - Transactie Overzicht
 *
 * @author P. Theijssen
 */
require 'php/auth_functions.php';
require_login();

// Get user info and admin status early
$user_id = get_current_user_id();
$is_admin = is_admin();

// Determine tab filter
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'alle';
$allowed_tabs = ['alle', 'inkomsten', 'uitgaven'];
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'alle';
}

// Determine sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort column
$allowed_columns = ['date', 'invoice_number', 'description', 'amount', 'type', 'category'];
if ($is_admin) {
    $allowed_columns[] = 'user';
}
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'date';
}

// Validate sort order
$sort_order = strtolower($sort_order);
if (!in_array($sort_order, ['asc', 'desc'])) {
    $sort_order = 'asc';
}

// Map sort column to database column
$db_column_map = [
    'date' => 't.date',
    'invoice_number' => 't.invoice_number',
    'description' => 't.description',
    'amount' => 't.amount',
    'type' => 't.type',
    'category' => 'c.name'
];
if ($is_admin) {
    $db_column_map['user'] = 'u.username';
}

$db_column = $db_column_map[$sort_column] ?? 't.date';

// Build WHERE clause based on tab filter
$where_conditions = [];
$where_params = [];

// Add tab filter
if ($tab === 'inkomsten') {
    $where_conditions[] = "t.type = 'inkomst'";
} elseif ($tab === 'uitgaven') {
    $where_conditions[] = "t.type = 'uitgave'";
}

// Build SQL query with sorting, user filtering, tab filtering, and relation info
if ($is_admin) {
    // Admin can see all transactions
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $sql = "SELECT t.*, c.name as category, u.username, u.full_name as user_full_name,
                   r.relation_code, r.company_name as relation_name, r.relation_type
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN relations r ON t.relation_id = r.id
            $where_clause
            ORDER BY $db_column $sort_order, t.id $sort_order";
    
    if (!empty($where_params)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where_params);
    } else {
        $stmt = $pdo->query($sql);
    }
} else {
    // Regular users can only see their own transactions
    $where_conditions[] = "t.user_id = ?";
    $where_params[] = $user_id;
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    $sql = "SELECT t.*, c.name as category,
                   r.relation_code, r.company_name as relation_name, r.relation_type
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN relations r ON t.relation_id = r.id
            $where_clause
            ORDER BY $db_column $sort_order, t.id $sort_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($where_params);
}
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalInkomsten = 0;
$totalUitgaven = 0;
foreach ($transactions as $t) {
    if ($t['type'] == 'inkomst') {
        $totalInkomsten += $t['amount'];
    } else {
        $totalUitgaven += $t['amount'];
    }
}
$balans = $totalInkomsten - $totalUitgaven;

// Function to generate sort URL (preserve tab)
function sort_url($column, $current_column, $current_order, $tab = 'alle') {
    $order = 'asc';
    if ($column == $current_column && $current_order == 'asc') {
        $order = 'desc';
    }
    return "index.php?tab=$tab&sort=$column&order=$order";
}

// Function to get sort indicator
function sort_indicator($column, $current_column, $current_order) {
    if ($column == $current_column) {
        return $current_order == 'asc' ? '↑' : '↓';
    }
    return '';
}
$page_title = 'Boekhouding Applicatie';
$page_subtitle = 'Overzicht van alle financiële transacties';
$page_css = <<<CSS
.sortable-header {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 20px !important;
}

.sortable-header:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.sort-indicator {
    position: absolute;
    right: 5px;
    font-weight: bold;
}

.sortable-header .sort-indicator {
    opacity: 1;
}

.sortable-header:not(.active-sort) .sort-indicator {
    opacity: 0.3;
}

.active-sort {
    background-color: rgba(255, 255, 255, 0.3);
}

.table-info {
    background-color: var(--gray-light);
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    font-size: 0.9rem;
    color: var(--gray-dark);
}

.table-info strong {
    color: var(--primary-color);
}

.username {
    font-weight: 500;
}

.badge-admin {
    background: #ff6b6b;
    color: var(--text-inverse);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
CSS;
include 'php/page_header.php';
?>
    <main class="main-content">
        <h2 class="section-title">Transactie Overzicht</h2>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $tab === 'alle' ? 'active' : ''; ?>"
                    onclick="switchTab('alle')">
                Alle Transacties
                <span class="tab-badge"><?php echo count($transactions); ?></span>
            </button>
            <button class="tab-button <?php echo $tab === 'inkomsten' ? 'active' : ''; ?>"
                    onclick="switchTab('inkomsten')">
                Inkomsten
                <span class="tab-badge"><?php
                    echo count(array_filter($transactions, function($t) { return $t['type'] == 'inkomst'; }));
                ?></span>
            </button>
            <button class="tab-button <?php echo $tab === 'uitgaven' ? 'active' : ''; ?>"
                    onclick="switchTab('uitgaven')">
                Uitgaven
                <span class="tab-badge"><?php
                    echo count(array_filter($transactions, function($t) { return $t['type'] == 'uitgave'; }));
                ?></span>
            </button>
        </div>
        
        <div class="table-info">
            <strong>Gesorteerd op:</strong> <?php 
                $column_names = [
                    'date' => 'Datum',
                    'invoice_number' => 'Factuurnummer',
                    'description' => 'Omschrijving',
                    'amount' => 'Bedrag',
                    'type' => 'Type',
                    'category' => 'Categorie'
                ];
                if ($is_admin) {
                    $column_names['user'] = 'Gebruiker';
                }
                echo $column_names[$sort_column] . ' ' . ($sort_order == 'asc' ? '(oplopend)' : '(aflopend)');
            ?>
            <span style="margin-left: 1rem;">
                <a href="index.php" class="btn btn-secondary btn-sm">Standaard sortering</a>
            </span>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Totaal Inkomsten</h3>
                <div class="positive amount">€<?php echo number_format($totalInkomsten, 2); ?></div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Totaal Uitgaven</h3>
                <div class="negative amount">€<?php echo number_format($totalUitgaven, 2); ?></div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Balans</h3>
                <div class="amount <?php echo $balans >= 0 ? 'positive' : 'negative'; ?>">
                    €<?php echo number_format($balans, 2); ?>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                        <th class="sortable-header <?php echo $sort_column == 'user' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('user', $sort_column, $sort_order, $tab); ?>'">
                            Gebruiker
                            <span class="sort-indicator"><?php echo sort_indicator('user', $sort_column, $sort_order); ?></span>
                        </th>
                        <?php endif; ?>
                        <th class="sortable-header <?php echo $sort_column == 'date' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('date', $sort_column, $sort_order, $tab); ?>'">
                            Datum
                            <span class="sort-indicator"><?php echo sort_indicator('date', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'invoice_number' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('invoice_number', $sort_column, $sort_order, $tab); ?>'">
                            Factuurnr.
                            <span class="sort-indicator"><?php echo sort_indicator('invoice_number', $sort_column, $sort_order); ?></span>
                        </th>
                        <th>Relatie</th>
                        <th class="sortable-header <?php echo $sort_column == 'description' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('description', $sort_column, $sort_order, $tab); ?>'">
                            Omschrijving
                            <span class="sort-indicator"><?php echo sort_indicator('description', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'amount' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('amount', $sort_column, $sort_order, $tab); ?>'">
                            Bedrag
                            <span class="sort-indicator"><?php echo sort_indicator('amount', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'type' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('type', $sort_column, $sort_order, $tab); ?>'">
                            Type
                            <span class="sort-indicator"><?php echo sort_indicator('type', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'category' ? 'active-sort' : ''; ?>"
                            onclick="window.location.href='<?php echo sort_url('category', $sort_column, $sort_order, $tab); ?>'">
                            Categorie
                            <span class="sort-indicator"><?php echo sort_indicator('category', $sort_column, $sort_order); ?></span>
                        </th>
                        <th>Bon</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?php echo $is_admin ? '10' : '9'; ?>" style="text-align: center; padding: 2rem;">
                            <div class="alert alert-info">
                                Geen transacties gevonden. <a href="php/add_income.php">Verkoop boeken</a> of <a href="php/add_expense.php">Inkoop boeken</a>.
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <?php if ($is_admin): ?>
                        <td>
                            <?php if (!empty($t['username'])): ?>
                                <span class="user-badge" title="<?php echo htmlspecialchars($t['user_full_name'] ?? $t['username']); ?>">
                                    <?php echo htmlspecialchars($t['username']); ?>
                                </span>
                            <?php else: ?>
                                <span class="neutral" style="font-style: italic; color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo date('d-m-Y', strtotime($t['date'])); ?></td>
                        <td>
                            <?php if (!empty($t['invoice_number'])): ?>
                            <span class="invoice-number" title="Factuurnummer: <?php echo htmlspecialchars($t['invoice_number']); ?>">
                                <?php echo htmlspecialchars($t['invoice_number']); ?>
                            </span>
                            <?php else: ?>
                            <span class="neutral" style="font-style: italic; color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($t['relation_name'])): ?>
                            <a href="php/relations.php?id=<?php echo $t['relation_id']; ?>"
                               title="<?php echo htmlspecialchars($t['relation_code']); ?> - Klik voor details"
                               style="text-decoration: none; color: var(--secondary-color);">
                                <i class="fas fa-address-book" style="margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($t['relation_name']); ?>
                            </a>
                            <?php else: ?>
                            <span class="neutral" style="font-style: italic; color: var(--text-secondary);">
                                <?php echo $t['type'] == 'inkomst' ? 'Diverse Klanten' : 'Diverse Leveranciers'; ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['description']); ?></td>
                        <td class="<?php
                            // Determine styling based on amount and type
                            if ($t['type'] == 'inkomst') {
                                echo $t['amount'] >= 0 ? 'positive' : 'negative';
                            } else {
                                echo $t['amount'] >= 0 ? 'negative' : 'positive';
                            }
                        ?>">
                            €<?php echo number_format($t['amount'], 2); ?>
                        </td>
                        <td>
                            <span class="<?php
                                if ($t['type'] == 'inkomst') {
                                    echo $t['amount'] >= 0 ? 'positive' : 'negative';
                                } else {
                                    echo $t['amount'] >= 0 ? 'negative' : 'positive';
                                }
                            ?>">
                                <?php
                                // Show special label for credit notes
                                if ($t['type'] == 'inkomst' && $t['amount'] < 0) {
                                    echo 'Creditnota (Inkomst)';
                                } elseif ($t['type'] == 'uitgave' && $t['amount'] < 0) {
                                    echo 'Credit (Uitgave)';
                                } else {
                                    echo ucfirst($t['type']);
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($t['category'] ?: 'Geen categorie'); ?></td>
                        <td>
                            <?php if (!empty($t['receipt_blob'])): ?>
                            <a href="php/view_receipt.php?id=<?php echo $t['id']; ?>"
                               target="_blank"
                               title="Bonnetje bekijken: <?php echo htmlspecialchars($t['receipt_original_name'] ?? ''); ?>"
                               class="receipt-icon-active">
                                <i class="fas fa-receipt"></i>
                            </a>
                            <?php else: ?>
                            <span class="receipt-icon-inactive" title="Geen bonnetje">
                                <i class="fas fa-receipt"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if (can_access_transaction($t['id'])): ?>
                                    <a href="php/edit.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                    <?php if ($t['type'] == 'inkomst'): ?>
                                    <a href="pdf/invoice_pdf.php?id=<?php echo $t['id']; ?>"
                                       class="btn btn-primary btn-sm"
                                       target="_blank"
                                       title="Factuur als PDF printen">
                                        <i class="fas fa-file-pdf"></i> Factuur
                                    </a>
                                    <?php endif; ?>
                                    <a href="php/delete.php?id=<?php echo $t['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Weet je zeker dat je deze transactie wilt verwijderen?')">
                                        Verwijderen
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm disabled" title="Geen toegang">Bewerken</span>
                                    <?php if ($t['type'] == 'inkomst'): ?>
                                    <span class="btn btn-primary btn-sm disabled" title="Geen toegang"><i class="fas fa-file-pdf"></i> Factuur</span>
                                    <?php endif; ?>
                                    <span class="btn btn-danger btn-sm disabled" title="Geen toegang">Verwijderen</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="btn-group">
            <a href="php/add_income.php" class="btn btn-primary">Verkoop Boeken</a>
            <a href="php/add_expense.php" class="btn btn-primary">Inkoop Boeken</a>
            <a href="php/profit_loss.php" class="btn btn-secondary">Winst & Verlies</a>
            <a href="php/btw_kwartaal.php" class="btn btn-secondary">BTW Overzicht</a>
            <a href="php/balans.php" class="btn btn-secondary">Balans</a>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-title">Sortering instructies</h3>
            <p><strong>Klik op een kolomkop</strong> om te sorteren op die kolom:</p>
            <ul>
                <li><strong>Eerste klik:</strong> Sorteer oplopend (A-Z, 0-9, oud-nieuw)</li>
                <li><strong>Tweede klik:</strong> Sorteer aflopend (Z-A, 9-0, nieuw-oud)</li>
            </ul>
            <p>Gebruik de knop "Standaard sortering" om terug te keren naar de standaardweergave. De huidige sortering wordt weergegeven met een pijl (↑ oplopend, ↓ aflopend).</p>
        </div>
    </main>

    <script>
        // Tab switching function
        function switchTab(tab) {
            // Preserve current sorting parameters
            const urlParams = new URLSearchParams(window.location.search);
            const sort = urlParams.get('sort') || 'date';
            const order = urlParams.get('order') || 'asc';
            
            // Navigate to new tab with preserved sorting
            window.location.href = `index.php?tab=${tab}&sort=${sort}&order=${order}`;
        }
        
        // Simple confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('a[href*="delete.php"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Weet je zeker dat je deze transactie wilt verwijderen?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Add hover effects to sortable headers
            const sortableHeaders = document.querySelectorAll('.sortable-header');
            sortableHeaders.forEach(header => {
                header.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
                });
                header.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active-sort')) {
                        this.style.backgroundColor = '';
                    }
                });
            });
        });
    </script>
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'php/theme_toggle.php'; ?>
</body>
</html>