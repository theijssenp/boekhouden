<?php
require 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get date from query parameter, default to today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    // Get applicable VAT rates for the specified date
    $stmt = $pdo->prepare("
        SELECT
            rate,
            name,
            MAX(description) as description
        FROM vat_rates
        WHERE is_active = TRUE
          AND effective_from <= ?
          AND (effective_to IS NULL OR effective_to >= ?)
        GROUP BY rate, name
        ORDER BY rate DESC
    ");
    $stmt->execute([$date, $date]);
    $vat_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no VAT rates found, use defaults
    if (empty($vat_rates)) {
        $vat_rates = [
            ['rate' => 21, 'name' => 'Hoog tarief', 'description' => 'Standaard BTW tarief'],
            ['rate' => 9, 'name' => 'Verlaagd tarief', 'description' => 'Verlaagd BTW tarief'],
            ['rate' => 0, 'name' => 'Vrijgesteld', 'description' => 'Geen BTW']
        ];
    }
    
    echo json_encode($vat_rates);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>