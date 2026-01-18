<?php
/**
 * Apply user authentication schema to database
 */

require 'config.php';

echo "<!DOCTYPE html>
<html lang='nl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Schema Toepassen</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .button { 
            background: #007bff; color: white; padding: 10px 20px; 
            border: none; border-radius: 5px; cursor: pointer; text-decoration: none;
            display: inline-block; margin: 10px 5px;
        }
        .button:hover { background: #0056b3; }
        .button-danger { background: #dc3545; }
        .button-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Database Schema Toepassen</h1>
    <p>Deze pagina past het gebruikersauthenticatie schema toe op de database.</p>
";

// Check if schema has already been applied
$check_query = "SHOW TABLES LIKE 'users'";
$has_users_table = false;

try {
    $stmt = $pdo->query($check_query);
    $has_users_table = $stmt->rowCount() > 0;
    
    if ($has_users_table) {
        echo "<div class='warning'>Waarschuwing: De gebruikers tabel bestaat al. Het toepassen van het schema kan bestaande gegevens overschrijven.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Fout bij controleren database: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply') {
        echo "<h2>Schema toepassen...</h2>";
        
        // Read schema file
        $schema_file = 'schema_users.sql';
        if (!file_exists($schema_file)) {
            echo "<div class='error'>Schema bestand niet gevonden: $schema_file</div>";
        } else {
            $schema_content = file_get_contents($schema_file);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema_content)));
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($statements as $statement) {
                if (empty($statement)) {
                    continue;
                }
                
                // Skip comments
                if (strpos($statement, '--') === 0) {
                    continue;
                }
                
                try {
                    $pdo->exec($statement);
                    echo "<div class='success'>✓ Uitgevoerd: " . htmlspecialchars(substr($statement, 0, 100)) . "...</div>";
                    $success_count++;
                } catch (Exception $e) {
                    echo "<div class='error'>✗ Fout bij uitvoeren: " . htmlspecialchars($e->getMessage()) . "<br>";
                    echo "Statement: " . htmlspecialchars(substr($statement, 0, 200)) . "...</div>";
                    $error_count++;
                }
            }
            
            echo "<div class='info'>Klaar! $success_count statements succesvol uitgevoerd, $error_count fouten.</div>";
            
            // Test the schema
            echo "<h3>Schema testen...</h3>";
            
            $tests = [
                'users' => "SELECT COUNT(*) as count FROM users",
                'user_sessions' => "SELECT COUNT(*) as count FROM user_sessions",
                'audit_log' => "SELECT COUNT(*) as count FROM audit_log",
                'transactions_with_user' => "SELECT COUNT(*) as count FROM transactions WHERE user_id IS NOT NULL",
                'categories_with_user' => "SELECT COUNT(*) as count FROM categories WHERE user_id IS NOT NULL",
            ];
            
            foreach ($tests as $name => $query) {
                try {
                    $stmt = $pdo->query($query);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo "<div class='success'>✓ $name: " . $result['count'] . " rijen gevonden</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>✗ $name: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            
            // Check default users
            echo "<h3>Standaard gebruikers controleren...</h3>";
            try {
                $stmt = $pdo->query("SELECT username, user_type, is_active FROM users ORDER BY id");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($users as $user) {
                    $status = $user['is_active'] ? 'actief' : 'inactief';
                    echo "<div class='info'>Gebruiker: {$user['username']} ({$user['user_type']}) - $status</div>";
                }
                
                echo "<div class='success'>✓ " . count($users) . " gebruikers gevonden</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>✗ Fout bij ophalen gebruikers: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } elseif ($action === 'reset') {
        echo "<h2>Database resetten...</h2>";
        echo "<div class='warning'>Deze actie verwijdert alle gebruikersgegevens en sessies. Weet u het zeker?</div>";
        
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            $tables = ['user_sessions', 'audit_log', 'users'];
            
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS $table");
                    echo "<div class='success'>✓ Tabel $table verwijderd</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>✗ Fout bij verwijderen $table: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            
            // Also remove user_id columns
            try {
                $pdo->exec("ALTER TABLE transactions DROP FOREIGN KEY IF EXISTS fk_transactions_user_id");
                $pdo->exec("ALTER TABLE transactions DROP COLUMN IF EXISTS user_id");
                echo "<div class='success'>✓ user_id kolom verwijderd van transactions</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Fout bij verwijderen user_id van transactions: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            
            try {
                $pdo->exec("ALTER TABLE categories DROP FOREIGN KEY IF EXISTS fk_categories_user_id");
                $pdo->exec("ALTER TABLE categories DROP COLUMN IF EXISTS user_id");
                $pdo->exec("ALTER TABLE categories DROP COLUMN IF EXISTS is_system");
                echo "<div class='success'>✓ user_id en is_system kolommen verwijderd van categories</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Fout bij verwijderen kolommen van categories: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Show current database status
echo "<h2>Huidige database status</h2>";

$tables_to_check = ['users', 'user_sessions', 'audit_log', 'transactions', 'categories'];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Get column info for users table
            if ($table === 'users') {
                $stmt = $pdo->query("DESCRIBE users");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $column_list = implode(', ', $columns);
                echo "<div class='success'>✓ Tabel $table bestaat (kolommen: $column_list)</div>";
            } else {
                echo "<div class='success'>✓ Tabel $table bestaat</div>";
            }
        } else {
            echo "<div class='warning'>✗ Tabel $table bestaat niet</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Fout bij controleren $table: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Show form
echo "
    <h2>Acties</h2>
    
    <form method='POST' style='margin-bottom: 20px;'>
        <input type='hidden' name='action' value='apply'>
        <button type='submit' class='button'>Schema toepassen</button>
        <p><small>Past het gebruikersauthenticatie schema toe op de database. Dit voegt gebruikers, sessies en audit logging toe.</small></p>
    </form>
    
    <form method='POST' style='margin-bottom: 20px;'>
        <input type='hidden' name='action' value='reset'>
        <div style='margin: 10px 0;'>
            <label>
                <input type='checkbox' name='confirm' value='yes' required>
                Ik bevestig dat ik alle gebruikersgegevens wil verwijderen
            </label>
        </div>
        <button type='submit' class='button button-danger'>Database resetten</button>
        <p><small>Verwijdert alle gebruikersgerelateerde tabellen en kolommen. Gebruik met zorg!</small></p>
    </form>
    
    <h2>Volgende stappen</h2>
    <ul>
        <li><a href='login.php' class='button'>Test inloggen</a> - Test de inlogfunctionaliteit</li>
        <li><a href='../index.php' class='button'>Naar hoofdapplicatie</a> - Ga naar de boekhouding</li>
        <li><a href='admin_dashboard.php' class='button'>Admin dashboard</a> - Beheer gebruikers (na inloggen als admin)</li>
    </ul>
    
    <h2>Standaard inloggegevens</h2>
    <ul>
        <li><strong>Administrator:</strong> admin / admin123</li>
        <li><strong>Administratie houder:</strong> gebruiker1 / user123</li>
    </ul>
    
    <p><em>Let op: Wijzig deze wachtwoorden in productie!</em></p>
</body>
</html>";