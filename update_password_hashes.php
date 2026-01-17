<?php
/**
 * Script to update password hashes in the database
 */

require 'config.php';

echo "<h1>Updating Password Hashes</h1>\n";
echo "<style>body { font-family: monospace; } .success { color: green; } .error { color: red; }</style>\n";

// Generate new password hashes
$admin_password = 'admin123';
$user_password = 'user123';

$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
$user_hash = password_hash($user_password, PASSWORD_DEFAULT);

echo "<p>Generated hashes:</p>\n";
echo "<ul>\n";
echo "<li>admin123: " . htmlspecialchars($admin_hash) . "</li>\n";
echo "<li>user123: " . htmlspecialchars($user_hash) . "</li>\n";
echo "</ul>\n";

// Update the database
try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$admin_hash]);
    $admin_updated = $stmt->rowCount();
    
    // Update gebruiker1 password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'gebruiker1'");
    $stmt->execute([$user_hash]);
    $user_updated = $stmt->rowCount();
    
    echo "<div class='success'>✓ Updated admin password hash: $admin_updated row(s) affected</div>\n";
    echo "<div class='success'>✓ Updated gebruiker1 password hash: $user_updated row(s) affected</div>\n";
    
    // Verify the updates
    echo "<h2>Verification</h2>\n";
    
    $stmt = $pdo->query("SELECT username, password_hash FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "<p><strong>" . htmlspecialchars($user['username']) . ":</strong> " . htmlspecialchars(substr($user['password_hash'], 0, 50)) . "...</p>\n";
    }
    
    // Test password verification
    echo "<h2>Password Verification Test</h2>\n";
    
    // Test admin password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin_db_hash = $stmt->fetchColumn();
    
    if (password_verify($admin_password, $admin_db_hash)) {
        echo "<div class='success'>✓ Admin password verification successful</div>\n";
    } else {
        echo "<div class='error'>✗ Admin password verification failed</div>\n";
    }
    
    // Test user password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'gebruiker1'");
    $stmt->execute();
    $user_db_hash = $stmt->fetchColumn();
    
    if (password_verify($user_password, $user_db_hash)) {
        echo "<div class='success'>✓ User password verification successful</div>\n";
    } else {
        echo "<div class='error'>✗ User password verification failed</div>\n";
    }
    
    echo "<h2>Next Steps</h2>\n";
    echo "<p>Password hashes have been updated. You can now log in with:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Admin:</strong> username 'admin', password 'admin123'</li>\n";
    echo "<li><strong>Regular user:</strong> username 'gebruiker1', password 'user123'</li>\n";
    echo "</ul>\n";
    echo "<p><a href='login.php'>Go to login page</a></p>\n";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error updating password hashes: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
?>