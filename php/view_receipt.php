<?php
/**
 * View receipt - serveert een bonnetje van het filesystem, na rechtencontrole.
 *
 * De opslagmap is niet direct via de webserver bereikbaar; dit is de enige
 * weg naar de bestanden.
 *
 * @author P. Theijssen
 */

require 'auth_functions.php';
require 'receipt_functions.php';
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Ongeldig ID.');
}

$sql = $is_admin
    ? "SELECT receipt_path, receipt_original_name, receipt_mime_type FROM transactions WHERE id = ?"
    : "SELECT receipt_path, receipt_original_name, receipt_mime_type FROM transactions WHERE id = ? AND user_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute($is_admin ? [$id] : [$id, $user_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction || empty($transaction['receipt_path'])) {
    http_response_code(404);
    die('Bonnetje niet gevonden.');
}

$path = receipt_absolute_path($transaction['receipt_path']);

if ($path === false || !is_file($path) || !is_readable($path)) {
    error_log('Bonnetje ontbreekt op schijf voor transactie ' . $id . ': ' . $transaction['receipt_path']);
    http_response_code(404);
    die('Bonnetje niet gevonden.');
}

$mime = $transaction['receipt_mime_type'] ?? 'application/octet-stream';
$name = $transaction['receipt_original_name'] ?? 'bonnetje';

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($name) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($path);
exit;
