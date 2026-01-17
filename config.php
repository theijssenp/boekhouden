<?php
// Database configuration - only define if not already defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root'); // Change to your MySQL username
if (!defined('DB_PASS')) define('DB_PASS', 'Vr1lijkeN11t!'); // Change to your MySQL password
if (!defined('DB_NAME')) define('DB_NAME', 'boekhouden');

// Only create PDO connection if it doesn't exist
if (!isset($pdo)) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>