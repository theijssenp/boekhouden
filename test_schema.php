<?php
require 'config.php';

echo "Testing database schema...\n\n";

// Check if users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists\n";
        
        // Check columns
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "  Columns: " . implode(', ', $columns) . "\n";
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Total users: " . $result['count'] . "\n";
        
        // List users
        $stmt = $pdo->query("SELECT id, username, user_type, is_active FROM users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            echo "    - {$user['username']} ({$user['user_type']}) - ID: {$user['id']}\n";
        }
    } else {
        echo "✗ Users table does not exist\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking users table: " . $e->getMessage() . "\n";
}

echo "\n";

// Check if transactions table has user_id column
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'user_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Transactions table has user_id column\n";
        
        // Count transactions with user_id
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE user_id IS NOT NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Transactions with user_id: " . $result['count'] . "\n";
        
        // Count transactions without user_id
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE user_id IS NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Transactions without user_id: " . $result['count'] . "\n";
    } else {
        echo "✗ Transactions table does not have user_id column\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking transactions table: " . $e->getMessage() . "\n";
}

echo "\n";

// Check if categories table has user_id and is_system columns
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Categories table has user_id column\n";
        
        $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'is_system'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Categories table has is_system column\n";
            
            // Count system vs user categories
            $stmt = $pdo->query("SELECT is_system, COUNT(*) as count FROM categories GROUP BY is_system");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $type = $row['is_system'] ? 'system' : 'user';
                echo "  {$type} categories: " . $row['count'] . "\n";
            }
        } else {
            echo "✗ Categories table does not have is_system column\n";
        }
    } else {
        echo "✗ Categories table does not have user_id column\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking categories table: " . $e->getMessage() . "\n";
}

echo "\n";

// Check other authentication tables
$tables = ['user_sessions', 'audit_log'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ $table table exists\n";
        } else {
            echo "✗ $table table does not exist\n";
        }
    } catch (Exception $e) {
        echo "✗ Error checking $table table: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test foreign key constraints
echo "Testing foreign key constraints...\n";
try {
    // Try to insert a transaction with invalid user_id
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, type, date, category_id) VALUES (999, 'Test', 100, 'uitgave', NOW(), 1)");
    try {
        $stmt->execute();
        echo "✗ Foreign key constraint not working (should have failed)\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            echo "✓ Foreign key constraint working correctly\n";
        } else {
            echo "  Error (expected): " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error testing foreign key: " . $e->getMessage() . "\n";
}

echo "\nSchema test complete!\n";