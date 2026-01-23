<?php
require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();

require 'config.php';

$id = $_GET['id'];

// Check if user can access this transaction
if ($is_admin) {
    // Admin can delete any transaction
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
} else {
    // Regular users can only delete their own transactions
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        // No transaction deleted (either doesn't exist or user doesn't have access)
        $_SESSION['error_message'] = "Geen toegang om deze transactie te verwijderen of transactie bestaat niet.";
        header('Location: ../index.php');
        exit;
    }
}

header('Location: ../index.php');
exit;
?>