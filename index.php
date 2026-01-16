<?php
require 'config.php';

// Determine sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort column
$allowed_columns = ['date', 'invoice_number', 'description', 'amount', 'type', 'category'];
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

$db_column = $db_column_map[$sort_column] ?? 't.date';

// Build SQL query with sorting
$sql = "SELECT t.*, c.name as category FROM transactions t LEFT JOIN categories c ON t.category_id = c.id ORDER BY $db_column $sort_order, t.id $sort_order";
$stmt = $pdo->query($sql);
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

// Function to generate sort URL
function sort_url($column, $current_column, $current_order) {
    $order = 'asc';
    if ($column == $current_column && $current_order == 'asc') {
        $order = 'desc';
    }
    return "index.php?sort=$column&order=$order";
}

// Function to get sort indicator
function sort_indicator($column, $current_column, $current_order) {
    if ($column == $current_column) {
        return $current_order == 'asc' ? '↑' : '↓';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boekhouden - Transactie Overzicht</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Boekhouding Applicatie</h1>
        <p>Overzicht van alle financiële transacties</p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="index.php" class="active">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
            <li><a href="balans.php">Balans</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <h2 class="section-title">Transactie Overzicht</h2>
        
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
                        <th class="sortable-header <?php echo $sort_column == 'date' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('date', $sort_column, $sort_order); ?>'">
                            Datum
                            <span class="sort-indicator"><?php echo sort_indicator('date', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'invoice_number' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('invoice_number', $sort_column, $sort_order); ?>'">
                            Factuurnr.
                            <span class="sort-indicator"><?php echo sort_indicator('invoice_number', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'description' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('description', $sort_column, $sort_order); ?>'">
                            Omschrijving
                            <span class="sort-indicator"><?php echo sort_indicator('description', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'amount' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('amount', $sort_column, $sort_order); ?>'">
                            Bedrag
                            <span class="sort-indicator"><?php echo sort_indicator('amount', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'type' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('type', $sort_column, $sort_order); ?>'">
                            Type
                            <span class="sort-indicator"><?php echo sort_indicator('type', $sort_column, $sort_order); ?></span>
                        </th>
                        <th class="sortable-header <?php echo $sort_column == 'category' ? 'active-sort' : ''; ?>" 
                            onclick="window.location.href='<?php echo sort_url('category', $sort_column, $sort_order); ?>'">
                            Categorie
                            <span class="sort-indicator"><?php echo sort_indicator('category', $sort_column, $sort_order); ?></span>
                        </th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem;">
                            <div class="alert alert-info">
                                Geen transacties gevonden. <a href="add.php">Voeg uw eerste transactie toe</a>.
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($t['date'])); ?></td>
                        <td>
                            <?php if (!empty($t['invoice_number'])): ?>
                            <span class="invoice-number" title="Factuurnummer: <?php echo htmlspecialchars($t['invoice_number']); ?>">
                                <?php echo htmlspecialchars($t['invoice_number']); ?>
                            </span>
                            <?php else: ?>
                            <span class="neutral" style="font-style: italic; color: #666;">-</span>
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
                            <div class="btn-group">
                                <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                <a href="delete.php?id=<?php echo $t['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Weet je zeker dat je deze transactie wilt verwijderen?')">
                                    Verwijderen
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="btn-group">
            <a href="add.php" class="btn btn-primary">Nieuwe Transactie Toevoegen</a>
            <a href="profit_loss.php" class="btn btn-secondary">Kosten Baten Overzicht</a>
            <a href="btw_kwartaal.php" class="btn btn-secondary">BTW per Kwartaal</a>
            <a href="balans.php" class="btn btn-secondary">Balans Overzicht</a>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-title">Sortering instructies</h3>
            <p><strong>Klik op een kolomkop</strong> om te sorteren op die kolom:</p>
            <ul>
                <li><strong>Eerste klik:</strong> Sorteer oplopend (A-Z, 0-9, oud-nieuw)</li>
                <li><strong>Tweede klik:</strong> Sorteer aflopend (Z-A, 9-0, nieuw-oud)</li>
                <li><strong>Derde klik:</strong> Terug naar standaard sortering (datum oplopend)</li>
            </ul>
            <p>De huidige sortering wordt weergegeven boven de tabel met een pijl (↑ oplopend, ↓ aflopend).</p>
        </div>
    </main>

    <script>
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
</body>
</html>