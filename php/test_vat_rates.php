<?php
require 'config.php';

echo "<h1>Test BTW Tarieven Systeem</h1>\n";

// Check if vat_rates table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vat_rates'");
    $vatRatesTableExists = $stmt->rowCount() > 0;
    echo "<p>vat_rates tabel bestaat: " . ($vatRatesTableExists ? "JA" : "NEE") . "</p>\n";
    
    if ($vatRatesTableExists) {
        // Show all VAT rates
        $stmt = $pdo->query("SELECT * FROM vat_rates ORDER BY effective_from, rate DESC");
        $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Alle BTW-tarieven in database:</h2>\n";
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>ID</th><th>Tarief</th><th>Naam</th><th>Vanaf</th><th>Tot</th><th>Actief</th></tr>\n";
        foreach ($rates as $rate) {
            echo "<tr>\n";
            echo "<td>" . $rate['id'] . "</td>\n";
            echo "<td>" . $rate['rate'] . "%</td>\n";
            echo "<td>" . htmlspecialchars($rate['name']) . "</td>\n";
            echo "<td>" . $rate['effective_from'] . "</td>\n";
            echo "<td>" . ($rate['effective_to'] ?: 'heden') . "</td>\n";
            echo "<td>" . ($rate['is_active'] ? 'Ja' : 'Nee') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Test get_vat_rates.php API
        echo "<h2>Test get_vat_rates.php API:</h2>\n";
        $testDates = [
            '2015-01-01' => 'Voor 2019 (verlaagd tarief 6%)',
            '2019-06-15' => 'Na 2019 (verlaagd tarief 9%)',
            '2024-12-31' => 'Eind 2024 (hoog tarief 21%)',
            date('Y-m-d') => 'Vandaag'
        ];
        
        foreach ($testDates as $testDate => $description) {
            echo "<h3>$description ($testDate):</h3>\n";
            
            // Direct database query
            $stmt = $pdo->prepare("
                SELECT 
                    rate,
                    name,
                    description
                FROM vat_rates 
                WHERE is_active = TRUE
                  AND effective_from <= ?
                  AND (effective_to IS NULL OR effective_to >= ?)
                GROUP BY rate, name
                ORDER BY rate DESC
            ");
            $stmt->execute([$testDate, $testDate]);
            $applicableRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applicableRates)) {
                echo "<p>Geen tarieven gevonden voor deze datum</p>\n";
            } else {
                echo "<ul>\n";
                foreach ($applicableRates as $rate) {
                    echo "<li>" . $rate['rate'] . "% - " . htmlspecialchars($rate['name']) . 
                         " (" . htmlspecialchars($rate['description']) . ")</li>\n";
                }
                echo "</ul>\n";
            }
        }
        
        // Test function get_vat_rate_for_date if it exists
        echo "<h2>Test database functie get_vat_rate_for_date:</h2>\n";
        try {
            $stmt = $pdo->query("SHOW FUNCTION STATUS WHERE Db = DATABASE() AND Name = 'get_vat_rate_for_date'");
            $functionExists = $stmt->rowCount() > 0;
            
            if ($functionExists) {
                echo "<p>Functie get_vat_rate_for_date bestaat.</p>\n";
                
                // Test the function
                $testTypes = ['Hoog tarief', 'Verlaagd tarief', 'Vrijgesteld'];
                $testDates = ['2015-01-01', '2019-06-15', '2024-12-31'];
                
                echo "<table border='1' cellpadding='5'>\n";
                echo "<tr><th>Type</th>";
                foreach ($testDates as $date) {
                    echo "<th>$date</th>";
                }
                echo "</tr>\n";
                
                foreach ($testTypes as $type) {
                    echo "<tr><td>$type</td>";
                    foreach ($testDates as $date) {
                        $stmt = $pdo->prepare("SELECT get_vat_rate_for_date(?, ?) as rate");
                        $stmt->execute([$type, $date]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<td>" . $result['rate'] . "%</td>";
                    }
                    echo "</tr>\n";
                }
                echo "</table>\n";
            } else {
                echo "<p>Functie get_vat_rate_for_date bestaat niet (niet kritisch).</p>\n";
            }
        } catch (Exception $e) {
            echo "<p>Fout bij testen functie: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p>Fout bij testen: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test transactions with VAT
echo "<h2>Test transacties met BTW:</h2>\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'vat_percentage'");
    $vatColumnsExist = $stmt->rowCount() > 0;
    echo "<p>BTW kolommen in transacties tabel: " . ($vatColumnsExist ? "JA" : "NEE") . "</p>\n";
    
    if ($vatColumnsExist) {
        // Show some sample transactions with VAT
        $stmt = $pdo->query("
            SELECT 
                id,
                date,
                description,
                amount,
                type,
                vat_percentage,
                vat_included,
                vat_deductible
            FROM transactions 
            WHERE vat_percentage > 0
            ORDER BY date DESC
            LIMIT 10
        ");
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($transactions)) {
            echo "<p>Geen transacties met BTW gevonden.</p>\n";
        } else {
            echo "<table border='1' cellpadding='5'>\n";
            echo "<tr><th>ID</th><th>Datum</th><th>Omschrijving</th><th>Bedrag</th><th>Type</th><th>BTW %</th><th>BTW incl.</th><th>Aftrekbaar</th></tr>\n";
            foreach ($transactions as $t) {
                echo "<tr>\n";
                echo "<td>" . $t['id'] . "</td>\n";
                echo "<td>" . $t['date'] . "</td>\n";
                echo "<td>" . htmlspecialchars($t['description']) . "</td>\n";
                echo "<td>€" . number_format($t['amount'], 2) . "</td>\n";
                echo "<td>" . $t['type'] . "</td>\n";
                echo "<td>" . $t['vat_percentage'] . "%</td>\n";
                echo "<td>" . ($t['vat_included'] ? 'Ja' : 'Nee') . "</td>\n";
                echo "<td>" . ($t['vat_deductible'] ? 'Ja' : 'Nee') . "</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            // Test VAT calculation
            echo "<h3>BTW berekening test:</h3>\n";
            foreach ($transactions as $t) {
                $vatAmount = 0;
                $baseAmount = 0;
                
                if ($t['vat_included'] && $t['vat_percentage'] > 0) {
                    $vatAmount = $t['amount'] - ($t['amount'] / (1 + ($t['vat_percentage'] / 100)));
                    $baseAmount = $t['amount'] / (1 + ($t['vat_percentage'] / 100));
                } elseif (!$t['vat_included'] && $t['vat_percentage'] > 0) {
                    $vatAmount = $t['amount'] * ($t['vat_percentage'] / 100);
                    $baseAmount = $t['amount'];
                } else {
                    $vatAmount = 0;
                    $baseAmount = $t['amount'];
                }
                
                echo "<p>Transactie #" . $t['id'] . ": €" . number_format($t['amount'], 2) . 
                     " (BTW " . $t['vat_percentage'] . "%, " . ($t['vat_included'] ? 'incl.' : 'excl.') . ") = " .
                     "Basis: €" . number_format($baseAmount, 2) . ", BTW: €" . number_format($vatAmount, 2) . "</p>\n";
            }
        }
    }
} catch (Exception $e) {
    echo "<p>Fout bij testen transacties: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test links to other pages
echo "<h2>Navigatie:</h2>\n";
echo "<ul>\n";
echo "<li><a href='vat_rates_admin.php'>BTW Tarieven Beheer</a></li>\n";
echo "<li><a href='add.php'>Nieuwe Transactie (test BTW dropdown)</a></li>\n";
echo "<li><a href='btw_kwartaal.php'>BTW Kwartaal Overzicht</a></li>\n";
echo "<li><a href='profit_loss.php'>Kosten Baten Overzicht</a></li>\n";
echo "<li><a href='balans.php'>Balans Overzicht</a></li>\n";
echo "</ul>\n";

echo "<h2>Conclusie:</h2>\n";
echo "<p>Het BTW-tarieven systeem is geïmplementeerd met:</p>\n";
echo "<ol>\n";
echo "<li>Historische tarieven met ingangsdatums</li>\n";
echo "<li>Admin interface voor beheer</li>\n";
echo "<li>Dynamische tariefselectie op transactiedatum</li>\n";
echo "<li>Bijgewerkte financiële berekeningen (excl. BTW)</li>\n";
echo "<li>BTW-kwartaaloverzicht met historische tarieven</li>\n";
echo "</ol>\n";
?>