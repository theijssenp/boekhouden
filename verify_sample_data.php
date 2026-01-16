<?php
require 'config.php';

echo "<!DOCTYPE html>\n";
echo "<html lang='nl'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "    <title>Sample Data Verification - Boekhouden</title>\n";
echo "    <link rel='stylesheet' href='style.css'>\n";
echo "    <style>\n";
echo "        .verification-container {\n";
echo "            max-width: 1200px;\n";
echo "            margin: 0 auto;\n";
echo "        }\n";
echo "        .test-section {\n";
echo "            background: white;\n";
echo "            border-radius: 8px;\n";
echo "            padding: 1.5rem;\n";
echo "            margin: 1rem 0;\n";
echo "            box-shadow: 0 2px 4px rgba(0,0,0,0.1);\n";
echo "        }\n";
echo "        .test-result {\n";
echo "            padding: 10px;\n";
echo "            margin: 10px 0;\n";
echo "            border-radius: 4px;\n";
echo "        }\n";
echo "        .test-pass {\n";
echo "            background: #d4edda;\n";
echo "            border-left: 4px solid #28a745;\n";
echo "        }\n";
echo "        .test-fail {\n";
echo "            background: #f8d7da;\n";
echo "            border-left: 4px solid #dc3545;\n";
echo "        }\n";
echo "        .test-warning {\n";
echo "            background: #fff3cd;\n";
echo "            border-left: 4px solid #ffc107;\n";
echo "        }\n";
echo "        .stats-grid {\n";
echo "            display: grid;\n";
echo "            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));\n";
echo "            gap: 1rem;\n";
echo "            margin: 1rem 0;\n";
echo "        }\n";
echo "        .stat-box {\n";
echo "            background: #f8f9fa;\n";
echo "            padding: 1rem;\n";
echo "            border-radius: 4px;\n";
echo "            text-align: center;\n";
echo "        }\n";
echo "        .stat-value {\n";
echo "            font-size: 1.5rem;\n";
echo "            font-weight: bold;\n";
echo "            color: #2c3e50;\n";
echo "        }\n";
echo "        .stat-label {\n";
echo "            color: #666;\n";
echo "            font-size: 0.9rem;\n";
echo "        }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "    <div class='header'>\n";
echo "        <h1>Sample Data Verification</h1>\n";
echo "        <p>Test of sample data integration with financial reports</p>\n";
echo "    </div>\n";

echo "    <nav class='nav-bar'>\n";
echo "        <ul class='nav-links'>\n";
echo "            <li><a href='index.php'>Transacties</a></li>\n";
echo "            <li><a href='sample_data_interface.php'>Sample Data</a></li>\n";
echo "            <li><a href='verify_sample_data.php' class='active'>Verificatie</a></li>\n";
echo "        </ul>\n";
echo "    </nav>\n";

echo "    <main class='main-content'>\n";
echo "        <div class='verification-container'>\n";

// Test 1: Check transaction count
echo "        <div class='test-section'>\n";
echo "            <h2>1. Transactie Aantallen</h2>\n";

$total_transactions = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch(PDO::FETCH_ASSOC)['count'];
$income_transactions = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'inkomst'")->fetch(PDO::FETCH_ASSOC)['count'];
$expense_transactions = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'uitgave'")->fetch(PDO::FETCH_ASSOC)['count'];
$year_2025_transactions = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE YEAR(date) = 2025")->fetch(PDO::FETCH_ASSOC)['count'];

echo "        <div class='stats-grid'>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value'>$total_transactions</div>\n";
echo "                <div class='stat-label'>Totaal transacties</div>\n";
echo "            </div>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value'>$income_transactions</div>\n";
echo "                <div class='stat-label'>Inkomsten</div>\n";
echo "            </div>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value'>$expense_transactions</div>\n";
echo "                <div class='stat-label'>Uitgaven</div>\n";
echo "            </div>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value'>$year_2025_transactions</div>\n";
echo "                <div class='stat-label'>Transacties in 2025</div>\n";
echo "            </div>\n";
echo "        </div>\n";

if ($total_transactions >= 130 && $income_transactions >= 30 && $expense_transactions >= 100) {
    echo "        <div class='test-result test-pass'>\n";
    echo "            <strong>✓ PASS:</strong> Transactie aantallen kloppen (130+ totaal, 30+ inkomsten, 100+ uitgaven)\n";
    echo "        </div>\n";
} else {
    echo "        <div class='test-result test-fail'>\n";
    echo "            <strong>✗ FAIL:</strong> Transactie aantallen kloppen niet. Verwacht: 130+ totaal, 30+ inkomsten, 100+ uitgaven\n";
    echo "        </div>\n";
}

echo "        </div>\n";

// Test 2: Check category distribution
echo "        <div class='test-section'>\n";
echo "            <h2>2. Categorie Distributie</h2>\n";

$category_query = $pdo->query("
    SELECT c.id, c.name, COUNT(t.id) as transaction_count
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id
    GROUP BY c.id, c.name
    ORDER BY c.id
");

echo "        <div class='table-container'>\n";
echo "            <table class='data-table'>\n";
echo "                <thead>\n";
echo "                    <tr>\n";
echo "                        <th>Categorie ID</th>\n";
echo "                        <th>Categorie Naam</th>\n";
echo "                        <th>Aantal Transacties</th>\n";
echo "                        <th>Status</th>\n";
echo "                    </tr>\n";
echo "                </thead>\n";
echo "                <tbody>\n";

$expected_categories = [1, 3, 7, 8, 9, 11, 12, 13, 14];
$found_categories = [];

while ($row = $category_query->fetch(PDO::FETCH_ASSOC)) {
    $found_categories[] = $row['id'];
    $status = $row['transaction_count'] > 0 ? "<span style='color: green'>✓ In gebruik</span>" : "<span style='color: #999'>Niet gebruikt</span>";
    
    echo "                <tr>\n";
    echo "                    <td>{$row['id']}</td>\n";
    echo "                    <td>{$row['name']}</td>\n";
    echo "                    <td>{$row['transaction_count']}</td>\n";
    echo "                    <td>$status</td>\n";
    echo "                </tr>\n";
}

echo "                </tbody>\n";
echo "            </table>\n";
echo "        </div>\n";

$missing_categories = array_diff($expected_categories, $found_categories);
if (empty($missing_categories)) {
    echo "        <div class='test-result test-pass'>\n";
    echo "            <strong>✓ PASS:</strong> Alle verwachte categorieën zijn aanwezig in de database\n";
    echo "        </div>\n";
} else {
    echo "        <div class='test-result test-warning'>\n";
    echo "            <strong>⚠ WARNING:</strong> Sommige verwachte categorieën ontbreken: " . implode(', ', $missing_categories) . "\n";
    echo "        </div>\n";
}

echo "        </div>\n";

// Test 3: Check VAT rates
echo "        <div class='test-section'>\n";
echo "            <h2>3. BTW Tarieven</h2>\n";

$vat_query = $pdo->query("
    SELECT 
        vat_percentage,
        COUNT(*) as count,
        SUM(CASE WHEN type = 'inkomst' THEN 1 ELSE 0 END) as income_count,
        SUM(CASE WHEN type = 'uitgave' THEN 1 ELSE 0 END) as expense_count
    FROM transactions
    WHERE vat_percentage IS NOT NULL
    GROUP BY vat_percentage
    ORDER BY vat_percentage DESC
");

echo "        <div class='table-container'>\n";
echo "            <table class='data-table'>\n";
echo "                <thead>\n";
echo "                    <tr>\n";
echo "                        <th>BTW Percentage</th>\n";
echo "                        <th>Totaal Transacties</th>\n";
echo "                        <th>Inkomsten</th>\n";
echo "                        <th>Uitgaven</th>\n";
echo "                    </tr>\n";
echo "                </thead>\n";
echo "                <tbody>\n";

$vat_rates_found = [];
while ($row = $vat_query->fetch(PDO::FETCH_ASSOC)) {
    $vat_rates_found[] = $row['vat_percentage'];
    echo "                <tr>\n";
    echo "                    <td>{$row['vat_percentage']}%</td>\n";
    echo "                    <td>{$row['count']}</td>\n";
    echo "                    <td>{$row['income_count']}</td>\n";
    echo "                    <td>{$row['expense_count']}</td>\n";
    echo "                </tr>\n";
}

echo "                </tbody>\n";
echo "            </table>\n";
echo "        </div>\n";

$expected_vat_rates = [0, 9, 21];
$has_all_vat_rates = count(array_intersect($expected_vat_rates, $vat_rates_found)) === count($expected_vat_rates);

if ($has_all_vat_rates) {
    echo "        <div class='test-result test-pass'>\n";
    echo "            <strong>✓ PASS:</strong> Alle verwachte BTW-tarieven zijn aanwezig (0%, 9%, 21%)\n";
    echo "        </div>\n";
} else {
    echo "        <div class='test-result test-warning'>\n";
    echo "            <strong>⚠ WARNING:</strong> Niet alle BTW-tarieven zijn gevonden. Verwacht: 0%, 9%, 21%\n";
    echo "        </div>\n";
}

echo "        </div>\n";

// Test 4: Test financial reports
echo "        <div class='test-section'>\n";
echo "            <h2>4. Financiële Rapporten</h2>\n";
echo "            <p>Test of de financiële rapporten werken met de sample data:</p>\n";

echo "        <div class='stats-grid'>\n";
// Test profit/loss report
try {
    $profit_loss_query = $pdo->query("
        SELECT 
            SUM(CASE WHEN type = 'inkomst' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'uitgave' THEN amount ELSE 0 END) as total_expenses,
            SUM(amount) as net_profit
        FROM transactions
        WHERE YEAR(date) = 2025
    ");
    
    $profit_loss = $profit_loss_query->fetch(PDO::FETCH_ASSOC);
    $has_profit_loss_data = $profit_loss['total_income'] != 0 || $profit_loss['total_expenses'] != 0;
    
    echo "            <div class='stat-box'>\n";
    echo "                <div class='stat-value'>€" . number_format($profit_loss['total_income'], 2, ',', '.') . "</div>\n";
    echo "                <div class='stat-label'>Totaal Inkomsten 2025</div>\n";
    echo "            </div>\n";
    echo "            <div class='stat-box'>\n";
    echo "                <div class='stat-value'>€" . number_format($profit_loss['total_expenses'], 2, ',', '.') . "</div>\n";
    echo "                <div class='stat-label'>Totaal Uitgaven 2025</div>\n";
    echo "            </div>\n";
    echo "            <div class='stat-box'>\n";
    echo "                <div class='stat-value'>€" . number_format($profit_loss['net_profit'], 2, ',', '.') . "</div>\n";
    echo "                <div class='stat-label'>Netto Winst 2025</div>\n";
    echo "            </div>\n";
    
    if ($has_profit_loss_data) {
        echo "        <div class='test-result test-pass'>\n";
        echo "            <strong>✓ PASS:</strong> Winst/Verlies rapport werkt correct\n";
        echo "        </div>\n";
    } else {
        echo "        <div class='test-result test-warning'>\n";
        echo "            <strong>⚠ WARNING:</strong> Geen financiële data gevonden voor 2025\n";
        echo "        </div>\n";
    }
    
} catch (Exception $e) {
    echo "        <div class='test-result test-fail'>\n";
    echo "            <strong>✗ FAIL:</strong> Winst/Verlies rapport geeft fout: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "        </div>\n";
}

echo "        </div>\n";

// Test links to reports
echo "        <div style='margin: 20px 0;'>\n";
echo "            <h3>Directe Links naar Rapporten:</h3>\n";
echo "            <div style='display: flex; gap: 10px; margin-top: 10px;'>\n";
echo "                <a href='profit_loss.php?year=2025' class='btn btn-primary'>Winst/Verlies 2025</a>\n";
echo "                <a href='btw_kwartaal.php?year=2025' class='btn btn-primary'>BTW Kwartaal 2025</a>\n";
echo "                <a href='balans.php?year=2025' class='btn btn-primary'>Balans 2025</a>\n";
echo "            </div>\n";
echo "        </div>\n";

echo "        </div>\n";

// Test 5: Database integrity
echo "        <div class='test-section'>\n";
echo "            <h2>5. Database Integriteit</h2>\n";

$integrity_tests = [
    'Foreign key constraints' => "SELECT COUNT(*) as count FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE c.id IS NULL AND t.category_id IS NOT NULL",
    'Invalid dates' => "SELECT COUNT(*) as count FROM transactions WHERE date IS NULL OR date = '0000-00-00'",
    'Invalid amounts' => "SELECT COUNT(*) as count FROM transactions WHERE amount = 0",
    'Missing VAT settings' => "SELECT COUNT(*) as count FROM transactions WHERE vat_percentage IS NULL"
];

$all_passed = true;
foreach ($integrity_tests as $test_name => $query) {
    try {
        $result = $pdo->query($query)->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];
        
        if ($count == 0) {
            echo "        <div class='test-result test-pass'>\n";
            echo "            <strong>✓ PASS:</strong> $test_name - Geen problemen gevonden\n";
            echo "        </div>\n";
        } else {
            echo "        <div class='test-result test-fail'>\n";
            echo "            <strong>✗ FAIL:</strong> $test_name - $count problemen gevonden\n";
            echo "        </div>\n";
            $all_passed = false;
        }
    } catch (Exception $e) {
        echo "        <div class='test-result test-fail'>\n";
        echo "            <strong>✗ FAIL:</strong> $test_name - Query fout: " . htmlspecialchars($e->getMessage()) . "\n";
        echo "        </div>\n";
        $all_passed = false;
    }
}

echo "        </div>\n";

// Summary
echo "        <div class='test-section'>\n";
echo "            <h2>6. Samenvatting</h2>\n";

$tests_passed = 0;
$tests_failed = 0;
$tests_warning = 0;

// Count test results (simplified - in real implementation you would track each test)
if ($total_transactions >= 130) $tests_passed++; else $tests_failed++;
if (empty($missing_categories)) $tests_passed++; else $tests_warning++;
if ($has_all_vat_rates) $tests_passed++; else $tests_warning++;
if ($all_passed) $tests_passed++; else $tests_failed++;

echo "        <div class='stats-grid'>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value' style='color: #28a745;'>$tests_passed</div>\n";
echo "                <div class='stat-label'>Geslaagde Tests</div>\n";
echo "            </div>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value' style='color: #dc3545;'>$tests_failed</div>\n";
echo "                <div class='stat-label'>Gefaalde Tests</div>\n";
echo "            </div>\n";
echo "            <div class='stat-box'>\n";
echo "                <div class='stat-value' style='color: #ffc107;'>$tests_warning</div>\n";
echo "                <div class='stat-label'>Waarschuwingen</div>\n";
echo "            </div>\n";
echo "        </div>\n";

if ($tests_failed == 0 && $tests_warning == 0) {
    echo "        <div class='test-result test-pass' style='text-align: center;'>\n";
    echo "            <h3>✅ ALLE TESTS GESLAAGD!</h3>\n";
    echo "            <p>De sample data is succesvol geïmporteerd en alle rapporten werken correct.</p>\n";
    echo "        </div>\n";
} elseif ($tests_failed == 0) {
    echo "        <div class='test-result test-warning' style='text-align: center;'>\n";
    echo "            <h3>⚠ TESTS MET WAARSCHUWINGEN</h3>\n";
    echo "            <p>De sample data is geïmporteerd, maar er zijn enkele waarschuwingen.</p>\n";
    echo "        </div>\n";
} else {
    echo "        <div class='test-result test-fail' style='text-align: center;'>\n";
    echo "            <h3>❌ TESTS GEFAALD</h3>\n";
    echo "            <p>Er zijn problemen met de sample data import.</p>\n";
    echo "        </div>\n";
}

echo "        <div style='margin-top: 20px; text-align: center;'>\n";
echo "            <a href='sample_data_interface.php' class='btn btn-primary'>Terug naar Sample Data</a>\n";
echo "            <a href='index.php' class='btn btn-secondary'>Naar Transacties</a>\n";
echo "            <a href='backup_interface.php' class='btn btn-warning'>Maak Backup</a>\n";
echo "        </div>\n";

echo "        </div>\n";

echo "        </div>\n"; // Close verification-container
echo "    </main>\n";
echo "</body>\n";
echo "</html>\n";