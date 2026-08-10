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

// Beschikbare boekjaren ophalen (alleen de jaren die de gebruiker mag zien)
if ($is_admin) {
    $year_stmt = $pdo->query("SELECT DISTINCT YEAR(date) AS jaar FROM transactions ORDER BY jaar DESC");
} else {
    $year_stmt = $pdo->prepare("SELECT DISTINCT YEAR(date) AS jaar FROM transactions WHERE user_id = ? ORDER BY jaar DESC");
    $year_stmt->execute([$user_id]);
}
$available_years = array_map('intval', $year_stmt->fetchAll(PDO::FETCH_COLUMN));

// Boekjaarfilter: standaard het meest recente jaar met boekingen.
// Een boekhouder werkt vrijwel altijd binnen één boekjaar; alle jaren
// door elkaar is zelden wat je wilt zien.
$year = $_GET['jaar'] ?? ($available_years[0] ?? '');
if ($year !== 'alle') {
    $year = in_array((int)$year, $available_years, true) ? (int)$year : ($available_years[0] ?? 'alle');
}

// Determine sorting parameters. Standaard nieuwste boeking bovenaan.
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

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
    // Sorteer op wat er in de kolom staat: het bedrag inclusief BTW
    'amount' => 't.amount_incl',
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

// Add boekjaar filter
if ($year !== 'alle') {
    $where_conditions[] = "YEAR(t.date) = ?";
    $where_params[] = $year;
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

/*
 * De kolom `amount` betekent afhankelijk van `vat_included` iets anders
 * (excl. of incl. BTW). De database splitst dat per boeking uit in
 * amount_excl / vat_amount / amount_incl - zie sql/schema.sql. Hier wordt
 * dus alleen nog opgeteld, niet gerekend: er is precies een plek waar de
 * BTW-splitsing is gedefinieerd.
 *
 * Omdat de splitsing per boeking op de cent is afgerond, telt het totaal
 * hier exact op tot de bedragen die in de tabel per regel getoond worden.
 */
$nettoInkomsten = 0;   // omzet, exclusief BTW  -> dit is de W&V-regel
$nettoUitgaven  = 0;   // kosten, exclusief BTW
$brutoInkomsten = 0;
$brutoUitgaven  = 0;
$btwInkomsten   = 0;   // af te dragen BTW
$btwVoorbelasting = 0; // terug te vorderen BTW (alleen aftrekbare)
$zonderBon = 0;        // uitgaven zonder bonnetje

foreach ($transactions as $t) {
    if ($t['type'] == 'inkomst') {
        $nettoInkomsten += $t['amount_excl'];
        $brutoInkomsten += $t['amount_incl'];
        $btwInkomsten   += $t['vat_amount'];
    } else {
        $nettoUitgaven += $t['amount_excl'];
        $brutoUitgaven += $t['amount_incl'];
        // Niet-aftrekbare BTW telt niet mee als voorbelasting
        if (!empty($t['vat_deductible'])) {
            $btwVoorbelasting += $t['vat_amount'];
        }
        if (empty($t['receipt_path'])) {
            $zonderBon++;
        }
    }
}

$resultaat = $nettoInkomsten - $nettoUitgaven;   // resultaat excl. BTW
$btwSaldo  = $btwInkomsten - $btwVoorbelasting;

/**
 * Genereert een sorteerbare kolomkop.
 *
 * Gebruikt een echte <a> in plaats van th[onclick], zodat de kop
 * bereikbaar is met het toetsenbord, in een nieuw tabblad geopend kan
 * worden, en via aria-sort door schermlezers wordt voorgelezen.
 */
function sort_header($column, $label, $current_column, $current_order, $tab = 'alle', $year = 'alle') {
    $is_active = ($column === $current_column);
    $next_order = ($is_active && $current_order === 'asc') ? 'desc' : 'asc';
    $url = 'index.php?tab=' . urlencode($tab) . '&jaar=' . urlencode((string)$year)
         . '&sort=' . urlencode($column) . '&order=' . $next_order;

    if ($is_active) {
        $aria_sort = ($current_order === 'asc') ? 'ascending' : 'descending';
        $indicator = ($current_order === 'asc') ? '↑' : '↓';
        $hint = ($current_order === 'asc') ? 'oplopend' : 'aflopend';
        $title = "Gesorteerd op $label ($hint) - klik om de volgorde om te draaien";
    } else {
        $aria_sort = 'none';
        $indicator = '↕';
        $title = "Sorteer op $label";
    }

    printf(
        '<th class="sortable-header%s" aria-sort="%s" scope="col">' .
        '<a href="%s" title="%s">%s<span class="sort-indicator" aria-hidden="true">%s</span></a></th>',
        $is_active ? ' active-sort' : '',
        $aria_sort,
        htmlspecialchars($url),
        htmlspecialchars($title),
        htmlspecialchars($label),
        $indicator
    );
}
$page_title = 'Boekhouding Applicatie';
$page_subtitle = 'Overzicht van alle financiële transacties';
$page_css = <<<CSS
/* De kop is een echte link; de <th> zelf krijgt geen padding meer
   zodat het klikvlak de hele cel beslaat. */
.sortable-header {
    padding: 0 !important;
}

.sortable-header a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding: 0.6rem 0.45rem;
    color: inherit;
    text-decoration: none;
    user-select: none;
    transition: background-color 0.15s ease;
}

.sortable-header a:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

.sortable-header a:focus-visible {
    outline: 2px solid #fff;
    outline-offset: -2px;
    box-shadow: none;
}

.sort-indicator {
    font-weight: bold;
    opacity: 0.35;
}

.active-sort {
    background-color: rgba(255, 255, 255, 0.15);
}

.active-sort .sort-indicator {
    opacity: 1;
}

.table-info {
    background-color: var(--gray-light);
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.table-info strong {
    color: var(--secondary-color);
}

.section-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.section-head .section-title {
    margin-bottom: 0;
    border-bottom: none;
    flex: 1;
}

.period-picker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.period-picker label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.period-picker select {
    width: auto;
    min-width: 8rem;
}

/* BTW-regel onder het bedrag: houdt de kolom smal maar toont
   de informatie waar een boekhouder als eerste naar kijkt. */
.amount-vat {
    display: block;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--text-secondary);
    white-space: nowrap;
    letter-spacing: -0.01em;
}

.table-total td {
    background-color: var(--gray-light);
    font-weight: 700;
    border-top: 2px solid var(--border-color);
    border-bottom: none;
}

.receipt-missing {
    color: var(--warning-color);
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
        <div class="section-head">
            <h2 class="section-title">Transactie Overzicht</h2>
            <?php if (!empty($available_years)): ?>
            <div class="period-picker">
                <label for="yearSelect"><i class="fas fa-calendar" aria-hidden="true"></i> Boekjaar</label>
                <select id="yearSelect" class="form-control form-control-sm" onchange="switchYear(this.value)">
                    <?php foreach ($available_years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ((string)$year === (string)$y) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endforeach; ?>
                    <option value="alle" <?php echo ($year === 'alle') ? 'selected' : ''; ?>>Alle jaren</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

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
                    'amount' => 'Bedrag incl. BTW',
                    'type' => 'Type',
                    'category' => 'Categorie'
                ];
                if ($is_admin) {
                    $column_names['user'] = 'Gebruiker';
                }
                echo $column_names[$sort_column] . ' ' . ($sort_order == 'asc' ? '(oplopend)' : '(aflopend)');
            ?>
            <span>
                <a href="index.php" class="btn btn-secondary btn-sm">Standaard sortering</a>
            </span>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title"><i class="fas fa-arrow-trend-up" style="color: var(--success-color); margin-right: 0.5rem;"></i>Omzet</h3>
                <div class="positive amount"><?php echo format_euro($nettoInkomsten); ?></div>
                <div class="card-sub">
                    excl. BTW &middot; incl. BTW <?php echo format_euro($brutoInkomsten); ?>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title"><i class="fas fa-arrow-trend-down" style="color: var(--danger-color); margin-right: 0.5rem;"></i>Kosten</h3>
                <div class="negative amount"><?php echo format_euro($nettoUitgaven); ?></div>
                <div class="card-sub">
                    excl. BTW &middot; incl. BTW <?php echo format_euro($brutoUitgaven); ?>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title"><i class="fas fa-scale-balanced" style="color: var(--secondary-color); margin-right: 0.5rem;"></i>Resultaat<?php echo ($year !== 'alle') ? ' ' . htmlspecialchars((string)$year) : ''; ?></h3>
                <div class="amount <?php echo $resultaat >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo format_euro($resultaat); ?>
                </div>
                <div class="card-sub">
                    BTW-saldo <strong><?php echo format_euro(abs($btwSaldo)); ?></strong>
                    <?php echo $btwSaldo >= 0 ? 'af te dragen' : 'terug te vorderen'; ?>
                </div>
            </div>
        </div>

        <?php if ($zonderBon > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-receipt" aria-hidden="true"></i>
            <strong><?php echo $zonderBon; ?></strong>
            <?php echo $zonderBon === 1 ? 'uitgave heeft' : 'uitgaven hebben'; ?> geen bonnetje.
            Zonder bewijsstuk is de BTW niet aftrekbaar bij een controle.
        </div>
        <?php endif; ?>

        <?php if (!empty($transactions)): ?>
        <div class="table-toolbar">
            <div class="table-search">
                <i class="fas fa-search"></i>
                <input type="text" id="transactionSearch" placeholder="Zoek op omschrijving, factuurnummer, relatie of categorie&hellip;" autocomplete="off">
            </div>
            <span class="table-search-count" id="transactionSearchCount"></span>
        </div>
        <?php endif; ?>

        <div class="table-container table-container--bleed">
            <table class="data-table data-table--stacked data-table--dense" id="transactionTable">
                <thead>
                    <tr>
                        <?php if ($is_admin) sort_header('user', 'Gebruiker', $sort_column, $sort_order, $tab, $year); ?>
                        <?php sort_header('date', 'Datum', $sort_column, $sort_order, $tab, $year); ?>
                        <?php sort_header('invoice_number', 'Factuurnr.', $sort_column, $sort_order, $tab, $year); ?>
                        <th scope="col">Relatie</th>
                        <?php sort_header('description', 'Omschrijving', $sort_column, $sort_order, $tab, $year); ?>
                        <?php sort_header('amount', 'Bedrag incl.', $sort_column, $sort_order, $tab, $year); ?>
                        <?php sort_header('type', 'Type', $sort_column, $sort_order, $tab, $year); ?>
                        <?php sort_header('category', 'Categorie', $sort_column, $sort_order, $tab, $year); ?>
                        <th scope="col">Bon</th>
                        <th scope="col">Acties</th>
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
                        <td data-label="Gebruiker">
                            <?php if (!empty($t['username'])): ?>
                                <span class="user-badge" title="<?php echo htmlspecialchars($t['user_full_name'] ?? $t['username']); ?>">
                                    <?php echo htmlspecialchars($t['username']); ?>
                                </span>
                            <?php else: ?>
                                <span class="neutral" style="font-style: italic; color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td data-label="Datum"><?php echo date('d-m-Y', strtotime($t['date'])); ?></td>
                        <td data-label="Factuurnr.">
                            <?php if (!empty($t['invoice_number'])): ?>
                            <span class="invoice-number" title="Factuurnummer: <?php echo htmlspecialchars($t['invoice_number']); ?>">
                                <?php echo htmlspecialchars($t['invoice_number']); ?>
                            </span>
                            <?php else: ?>
                            <span class="neutral" style="font-style: italic; color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Relatie">
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
                        <td data-label="Omschrijving"><?php echo htmlspecialchars($t['description']); ?></td>
                        <td data-label="Bedrag" class="<?php
                            // Determine styling based on amount and type
                            if ($t['type'] == 'inkomst') {
                                echo $t['amount'] >= 0 ? 'positive' : 'negative';
                            } else {
                                echo $t['amount'] >= 0 ? 'negative' : 'positive';
                            }
                        ?>">
                            <?php echo format_euro($t['amount_incl']); ?>
                            <?php $rowVat = (float)$t['vat_amount']; if ($rowVat != 0): ?>
                            <span class="amount-vat" title="BTW <?php echo format_percentage($t['vat_percentage']); ?><?php
                                echo empty($t['vat_deductible']) && $t['type'] === 'uitgave' ? ' (niet aftrekbaar)' : ''; ?>">
                                <?php echo format_percentage($t['vat_percentage']); ?> &middot; <?php echo format_euro($rowVat); ?>
                                <?php if (empty($t['vat_deductible']) && $t['type'] === 'uitgave'): ?>
                                <i class="fas fa-ban" aria-hidden="true" title="Niet aftrekbaar"></i>
                                <?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="amount-vat">geen BTW</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Type">
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
                        <td data-label="Categorie"><?php echo htmlspecialchars($t['category'] ?: 'Geen categorie'); ?></td>
                        <td data-label="Bon">
                            <?php if (!empty($t['receipt_path'])): ?>
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
                        <td data-label="Acties">
                            <div class="btn-group btn-group--actions">
                                <?php if (can_access_transaction($t['id'])): ?>
                                    <a href="php/edit.php?id=<?php echo $t['id']; ?>"
                                       class="btn-icon" title="Bewerken" aria-label="Transactie bewerken">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($t['type'] == 'inkomst'): ?>
                                    <a href="pdf/invoice_pdf.php?id=<?php echo $t['id']; ?>"
                                       class="btn-icon" target="_blank"
                                       title="Factuur als PDF" aria-label="Factuur als PDF openen">
                                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="php/delete.php?id=<?php echo $t['id']; ?>"
                                       class="btn-icon btn-icon--danger"
                                       title="Verwijderen" aria-label="Transactie verwijderen"
                                       onclick="return confirm('Weet je zeker dat je deze transactie wilt verwijderen?')">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="btn-icon disabled" title="Geen toegang" aria-label="Bewerken niet toegestaan">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </span>
                                    <?php if ($t['type'] == 'inkomst'): ?>
                                    <span class="btn-icon disabled" title="Geen toegang" aria-label="Factuur niet toegestaan">
                                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                    </span>
                                    <?php endif; ?>
                                    <span class="btn-icon disabled" title="Geen toegang" aria-label="Verwijderen niet toegestaan">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($transactions)): ?>
                <tfoot>
                    <tr class="table-total">
                        <td colspan="<?php echo $is_admin ? 5 : 4; ?>">
                            Totaal <?php echo count($transactions); ?>
                            <?php echo count($transactions) === 1 ? 'boeking' : 'boekingen'; ?>
                            <?php echo ($year === 'alle') ? '(alle jaren)' : '(boekjaar ' . htmlspecialchars((string)$year) . ')'; ?>
                        </td>
                        <td class="<?php echo $resultaat >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo format_euro($resultaat); ?>
                            <span class="amount-vat">excl. BTW</span>
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <div class="btn-group">
            <a href="php/add_income.php" class="btn btn-primary">Verkoop Boeken</a>
            <a href="php/add_expense.php" class="btn btn-primary">Inkoop Boeken</a>
            <a href="php/profit_loss.php" class="btn btn-secondary">Winst & Verlies</a>
            <a href="php/btw_kwartaal.php" class="btn btn-secondary">BTW Overzicht</a>
            <a href="php/balans.php" class="btn btn-secondary">Balans</a>
        </div>
        
    </main>

    <script>
        // Wissel van tab of boekjaar met behoud van de overige keuzes
        function navigateWith(changes) {
            const p = new URLSearchParams(window.location.search);
            Object.entries(changes).forEach(([k, v]) => p.set(k, v));
            if (!p.has('tab')) p.set('tab', 'alle');
            window.location.href = 'index.php?' + p.toString();
        }

        function switchTab(tab) {
            navigateWith({tab: tab});
        }

        function switchYear(jaar) {
            navigateWith({jaar: jaar});
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
            
            // Live client-side search across the visible transaction table
            const searchInput = document.getElementById('transactionSearch');
            const searchCount = document.getElementById('transactionSearchCount');
            const table = document.getElementById('transactionTable');
            if (searchInput && table) {
                const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => !row.querySelector('td[colspan]'));
                const totalCount = rows.length;
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    let visible = 0;
                    rows.forEach(row => {
                        const matches = !query || row.textContent.toLowerCase().includes(query);
                        row.classList.toggle('js-no-match', !matches);
                        if (matches) visible++;
                    });
                    searchCount.textContent = query ? `${visible} van ${totalCount} transacties` : '';
                });
            }
        });
    </script>
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: var(--text-secondary); font-size: 12px; border-top: 1px solid var(--border-color);">
        powered by P. Theijssen
    </footer>
<?php require 'php/theme_toggle.php'; ?>
</body>
</html>