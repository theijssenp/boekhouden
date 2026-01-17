<?php
/**
 * Command-line script to update password hashes in the database
 */

require 'config.php';

echo "Updating Password Hashes...\n";

// Generate new password hashes
$admin_password = 'admin123';
$user_password = 'user123';

$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
$user_hash = password_hash($user_password, PASSWORD_DEFAULT);

echo "Generated hashes:\n";
echo "- admin123: " . $admin_hash . "\n";
echo "- user123: " . $user_hash . "\n";

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
    
    echo "✓ Updated admin password hash: $admin_updated row(s) affected\n";
    echo "✓ Updated gebruiker1 password hash: $user_updated row(s) affected\n";
    
    // Verify the updates
    echo "\nVerification:\n";
    
    $stmt = $pdo->query("SELECT username, password_hash FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "- " . $user['username'] . ": " . substr($user['password_hash'], 0, 50) . "...\n";
    }
    
    // Test password verification
    echo "\nPassword Verification Test:\n";
    
    // Test admin password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin_db_hash = $stmt->fetchColumn();
    
    if (password_verify($admin_password, $admin_db_hash)) {
        echo "✓ Admin password verification successful\n";
    } else {
        echo "✗ Admin password verification failed\n";
    }
    
    // Test user password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'gebruiker1'");
    $stmt->execute();
    $user_db_hash = $stmt->fetchColumn();
    
    if (password_verify($user_password, $user_db_hash)) {
        echo "✓ User password verification successful\n";
    } else {
        echo "✗ User password verification failed\n";
    }
    
    echo "\nNext Steps:\n";
    echo "Password hashes have been updated. You can now log in with:\n";
    echo "- Admin: username 'admin', password 'admin123'\n";
    echo "- Regular user: username 'gebruiker1', password 'user123'\n";
    
} catch (Exception $e) {
    echo "✗ Error updating password hashes: " . $e->getMessage() . "\n";
}
?>