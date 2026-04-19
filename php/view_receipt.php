<?php
/**
 * View receipt - serves receipt BLOB from database with auth check
 *
 * @author P. Theijssen
 */

require 'auth_functions.php';
require_login();

$user_id = get_current_user_id();
$is_admin = is_admin();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Ongeldig ID.');
}

$sql = $is_admin
    ? "SELECT receipt_blob, receipt_original_name, receipt_mime_type FROM transactions WHERE id = ?"
    : "SELECT receipt_blob, receipt_original_name, receipt_mime_type FROM transactions WHERE id = ? AND user_id = ?";

$stmt = $pdo->prepare($sql);
if ($is_admin) {
    $stmt->execute([$id]);
} else {
    $stmt->execute([$id, $user_id]);
}
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction || empty($transaction['receipt_blob'])) {
    http_response_code(404);
    die('Bonnetje niet gevonden.');
}

$mime = $transaction['receipt_mime_type'] ?? 'application/octet-stream';
$name = $transaction['receipt_original_name'] ?? 'bonnetje';

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($name) . '"');
header('Content-Length: ' . strlen($transaction['receipt_blob']));
header('Cache-Control: private, max-age=3600');
echo $transaction['receipt_blob'];
exit;