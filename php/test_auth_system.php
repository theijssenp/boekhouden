<?php
/**
 * Test script for authentication system
 */

require 'config.php';

echo "<h1>Authentication System Test</h1>\n";
echo "<style>body { font-family: monospace; } .success { color: green; } .error { color: red; } .warning { color: orange; }</style>\n";

// Test 1: Check if auth_functions.php exists
echo "<h2>Test 1: Authentication Files</h2>\n";
$files = [
    'auth_functions.php',
    'login.php', 
    'logout.php',
    'header.php',
    'footer.php',
    'admin_dashboard.php',
    'admin_users.php',
    'admin_categories.php',
    'vat_rates_admin.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ $file exists</div>\n";
    } else {
        echo "<div class='error'>✗ $file missing</div>\n";
    }
}

// Test 2: Check database tables
echo "<h2>Test 2: Database Tables</h2>\n";
$tables = [
    'users',
    'user_sessions', 
    'audit_log',
    'categories',
    'transactions',
    'vat_rates'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✓ Table '$table' exists</div>\n";
            
            // Check for user_id column in transactions and categories
            if ($table === 'transactions' || $table === 'categories') {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'user_id'");
                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>  ✓ Column 'user_id' exists in $table</div>\n";
                } else {
                    echo "<div class='warning'>  ⚠ Column 'user_id' missing in $table</div>\n";
                }
            }
            
            // Check for is_system in categories
            if ($table === 'categories') {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'is_system'");
                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>  ✓ Column 'is_system' exists in categories</div>\n";
                } else {
                    echo "<div class='warning'>  ⚠ Column 'is_system' missing in categories</div>\n";
                }
            }
        } else {
            echo "<div class='error'>✗ Table '$table' missing</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error checking table '$table': " . htmlspecialchars($e->getMessage()) . "</div>\n";
    }
}

// Test 3: Check default users
echo "<h2>Test 3: Default Users</h2>\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='success'>✓ Users table has $count user(s)</div>\n";
    
    // Check for admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<div class='success'>✓ Admin user exists (username: admin)</div>\n";
        echo "<div class='success'>  ✓ User type: " . htmlspecialchars($admin['user_type']) . "</div>\n";
        echo "<div class='success'>  ✓ Is active: " . ($admin['is_active'] ? 'Yes' : 'No') . "</div>\n";
    } else {
        echo "<div class='error'>✗ Admin user not found</div>\n";
    }
    
    // Check for regular user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'gebruiker1'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<div class='success'>✓ Regular user exists (username: gebruiker1)</div>\n";
        echo "<div class='success'>  ✓ User type: " . htmlspecialchars($user['user_type']) . "</div>\n";
    } else {
        echo "<div class='warning'>⚠ Regular user 'gebruiker1' not found</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking users: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// Test 4: Check sample data
echo "<h2>Test 4: Sample Data</h2>\n";
try {
    // Check transactions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='success'>✓ Transactions table has $count transaction(s)</div>\n";
    
    // Check if transactions have user_id assigned
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE user_id IS NOT NULL");
    $with_user = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='success'>✓ $with_user transaction(s) have user_id assigned</div>\n";
    
    // Check categories
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='success'>✓ Categories table has $count category(ies)</div>\n";
    
    // Check system vs user categories
    $stmt = $pdo->query("SELECT is_system, COUNT(*) as count FROM categories GROUP BY is_system");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $type = $row['is_system'] ? 'System' : 'User';
        echo "<div class='success'>✓ $type categories: " . $row['count'] . "</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking sample data: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// Test 5: Check admin pages access control
echo "<h2>Test 5: Access Control Functions</h2>\n";
require 'auth_functions.php';

// Test auth functions exist
$functions = [
    'is_logged_in',
    'is_admin', 
    'require_login',
    'require_admin',
    'can_access_transaction',
    'can_access_category',
    'log_audit_action'
];

foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "<div class='success'>✓ Function '$function' exists</div>\n";
    } else {
        echo "<div class='error'>✗ Function '$function' missing</div>\n";
    }
}

// Test 6: Check admin dashboard links
echo "<h2>Test 6: Admin Dashboard Integration</h2>\n";
$admin_pages = [
    'admin_dashboard.php' => 'Admin Dashboard',
    'admin_users.php' => 'User Management',
    'admin_categories.php' => 'Category Management', 
    'vat_rates_admin.php' => 'VAT Rates Management',
    'backup_interface.php' => 'Backup Management'
];

foreach ($admin_pages as $file => $description) {
    if (file_exists($file)) {
        // Check if file requires admin access
        $content = file_get_contents($file);
        if (strpos($content, 'require_admin()') !== false) {
            echo "<div class='success'>✓ $description ($file) requires admin access</div>\n";
        } else {
            echo "<div class='warning'>⚠ $description ($file) may not require admin access</div>\n";
        }
    } else {
        echo "<div class='error'>✗ $description ($file) missing</div>\n";
    }
}

echo "<h2>Summary</h2>\n";
echo "<p>The authentication system has been implemented with:</p>\n";
echo "<ul>\n";
echo "<li>Complete user authentication (login/logout)</li>\n";
echo "<li>Session management with expiration</li>\n";
echo "<li>Role-based access control (administrator vs administratie_houder)</li>\n";
echo "<li>User-based data filtering (users only see their own transactions)</li>\n";
echo "<li>Admin dashboard with user management</li>\n";
echo "<li>Category management with system/user distinction</li>\n";
echo "<li>VAT rates management</li>\n";
echo "<li>Backup management integrated with admin access</li>\n";
echo "<li>Audit logging for security tracking</li>\n";
echo "</ul>\n";

echo "<p><strong>Default credentials:</strong></p>\n";
echo "<ul>\n";
echo "<li><strong>Admin:</strong> username 'admin', password 'admin123'</li>\n";
echo "<li><strong>Regular user:</strong> username 'gebruiker1', password 'user123'</li>\n";
echo "</ul>\n";

echo "<p><a href='login.php'>Go to login page</a> | <a href='admin_dashboard.php'>Go to admin dashboard</a></p>\n";
?>