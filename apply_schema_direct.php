<?php
/**
 * Direct schema application script
 */

require 'config.php';

echo "Applying user authentication schema...\n";

// Read schema file
$schema_file = 'schema_users.sql';
if (!file_exists($schema_file)) {
    die("Error: Schema file not found: $schema_file\n");
}

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
        echo "✓ Executed: " . substr($statement, 0, 80) . "...\n";
        $success_count++;
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        echo "  Statement: " . substr($statement, 0, 120) . "...\n";
        $error_count++;
    }
}

echo "\nDone! $success_count statements executed successfully, $error_count errors.\n";

// Test the schema
echo "\nTesting schema...\n";

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
        echo "✓ $name: " . $result['count'] . " rows found\n";
    } catch (Exception $e) {
        echo "✗ $name: " . $e->getMessage() . "\n";
    }
}

// Check default users
echo "\nChecking default users...\n";
try {
    $stmt = $pdo->query("SELECT username, user_type, is_active FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $status = $user['is_active'] ? 'active' : 'inactive';
        echo "  User: {$user['username']} ({$user['user_type']}) - $status\n";
    }
    
    echo "✓ " . count($users) . " users found\n";
    
} catch (Exception $e) {
    echo "✗ Error fetching users: " . $e->getMessage() . "\n";
}

echo "\nSchema application complete!\n";
echo "Default login credentials:\n";
echo "  Administrator: admin / admin123\n";
echo "  User: gebruiker1 / user123\n";