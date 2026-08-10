<?php
/**
 * Sjabloon voor php/config.php
 *
 * ./setup.sh vult de waarden hieronder in en schrijft het resultaat naar
 * php/config.php. Doe je het met de hand, kopieer dit bestand dan naar
 * php/config.php en vervang de vier {{...}} waarden.
 *
 * php/config.php staat in .gitignore en hoort daar te blijven.
 *
 * @author P. Theijssen
 */

// Map waarin de bonnetjes worden opgeslagen. Laat dit staan voor de
// standaard (<project>/storage/receipts), of geef een absoluut pad op als
// je de bestanden buiten de webroot wilt hebben. De map moet schrijfbaar
// zijn voor de gebruiker waaronder PHP draait.
// define('RECEIPT_DIR', '/var/lib/boekhouden/receipts');

defined('DB_HOST') or define('DB_HOST', '{{DB_HOST}}');
defined('DB_NAME') or define('DB_NAME', '{{DB_NAME}}');
defined('DB_USER') or define('DB_USER', '{{DB_USER}}');
defined('DB_PASS') or define('DB_PASS', '{{DB_PASS}}');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Geen verbinding met de database. Draai ./setup.sh of controleer php/config.php.');
}
