<?php
/**
 * Get Next Invoice Number via AJAX
 *
 * @author P. Theijssen
 */
require 'auth_functions.php';
require_login();

// Get user ID
$user_id = get_current_user_id();

// Generate next invoice number
$invoice_number = generate_next_invoice_number($user_id);

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'invoice_number' => $invoice_number
]);
