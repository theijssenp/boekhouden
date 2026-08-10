<?php
/**
 * Receipt upload functions for Boekhouden
 *
 * Bonnetjes worden op het filesystem opgeslagen, buiten de database.
 * In de database staat alleen het relatieve pad, de originele bestandsnaam
 * en het MIME-type. De bestanden zelf staan onder receipt_storage_dir() en
 * zijn niet direct via de webserver op te vragen; ze worden uitsluitend
 * geserveerd door view_receipt.php, dat eerst de rechten controleert.
 *
 * @author P. Theijssen
 */

define('RECEIPT_MAX_SIZE', 5 * 1024 * 1024);
define('RECEIPT_MAX_WIDTH', 1200);
define('RECEIPT_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);

const RECEIPT_EXTENSIONS = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/gif'       => 'gif',
    'application/pdf' => 'pdf',
];

/**
 * Map waarin de bonnetjes staan. Standaard <project>/storage/receipts,
 * te overschrijven met RECEIPT_DIR in config.php.
 */
function receipt_storage_dir(): string {
    $dir = defined('RECEIPT_DIR') && RECEIPT_DIR !== ''
        ? RECEIPT_DIR
        : dirname(__DIR__) . '/storage/receipts';

    return rtrim($dir, '/');
}

/**
 * Zet een relatief pad uit de database om naar een absoluut pad.
 * Weigert alles wat buiten de opslagmap zou kunnen wijzen.
 */
function receipt_absolute_path(string $relative): string|false {
    if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
        return false;
    }
    if ($relative[0] === '/' || preg_match('/^[a-zA-Z]:/', $relative)) {
        return false;
    }

    return receipt_storage_dir() . '/' . $relative;
}

/**
 * Validate an uploaded receipt file
 * @return array ['valid' => bool, 'error' => string]
 */
function validate_receipt_upload(array $file): array {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'error' => 'Geen bestand geselecteerd.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Het bestand is te groot (server limiet).',
            UPLOAD_ERR_FORM_SIZE  => 'Het bestand is te groot (formulier limiet).',
            UPLOAD_ERR_PARTIAL    => 'Het bestand is slechts gedeeltelijk geüpload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Tijdelijke map ontbreekt op de server.',
            UPLOAD_ERR_CANT_WRITE => 'Kon het bestand niet opslaan op de server.',
        ];
        $code = $file['error'];
        return ['valid' => false, 'error' => $errors[$code] ?? 'Onbekende fout bij uploaden.'];
    }

    if ($file['size'] > RECEIPT_MAX_SIZE) {
        return ['valid' => false, 'error' => 'Het bestand is te groot. Maximum is 5MB.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'Ongeldige upload.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, RECEIPT_ALLOWED_MIMES, true)) {
        return ['valid' => false, 'error' => 'Ongeldig bestandstype. Toegestaan: JPG, PNG, GIF, PDF.'];
    }

    return ['valid' => true, 'error' => ''];
}

/**
 * Sla een geuploade bon op het filesystem op. Afbeeldingen worden eerst
 * verkleind. Retourneert de kolomwaarden voor de transactie.
 *
 * @return array|false ['receipt_path' => string, 'receipt_original_name' => string, 'receipt_mime_type' => string]
 */
function store_receipt_file(array $file) {
    $original_name = basename($file['name']);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(RECEIPT_EXTENSIONS[$mime])) {
        return false;
    }

    // Per jaar en maand een submap, zodat de mappen hanteerbaar blijven.
    $relative_dir = date('Y/m');
    $target_dir   = receipt_storage_dir() . '/' . $relative_dir;

    if (!is_dir($target_dir) && !mkdir($target_dir, 0770, true) && !is_dir($target_dir)) {
        error_log('Kon de opslagmap voor bonnetjes niet aanmaken: ' . $target_dir);
        return false;
    }

    $relative_path = $relative_dir . '/' . bin2hex(random_bytes(16)) . '.' . RECEIPT_EXTENSIONS[$mime];
    $target_path   = receipt_storage_dir() . '/' . $relative_path;

    if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true) && function_exists('imagecreatetruecolor')) {
        if (!resize_image_to_file($file['tmp_name'], $target_path, $mime)) {
            return false;
        }
    } elseif (!move_uploaded_file($file['tmp_name'], $target_path)) {
        error_log('Kon het bonnetje niet opslaan: ' . $target_path);
        return false;
    }

    chmod($target_path, 0640);

    return [
        'receipt_path'          => $relative_path,
        'receipt_original_name' => $original_name,
        'receipt_mime_type'     => $mime,
    ];
}

/**
 * Verklein een afbeelding en schrijf hem naar $target_path.
 */
function resize_image_to_file(string $path, string $target_path, string $mime): bool {
    $source = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png'  => imagecreatefrompng($path),
        'image/gif'  => imagecreatefromgif($path),
        default      => false,
    };

    if (!$source) {
        return false;
    }

    $orig_w = imagesx($source);
    $orig_h = imagesy($source);

    if ($orig_w > RECEIPT_MAX_WIDTH) {
        $ratio = RECEIPT_MAX_WIDTH / $orig_w;
        $new_w = RECEIPT_MAX_WIDTH;
        $new_h = (int)($orig_h * $ratio);

        $dest = imagecreatetruecolor($new_w, $new_h);

        if ($mime === 'image/png') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefill($dest, 0, 0, $transparent);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($source);
        $source = $dest;
    }

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($source, $target_path, 80),
        'image/png'  => imagepng($source, $target_path, 7),
        'image/gif'  => imagegif($source, $target_path),
        default      => false,
    };
    imagedestroy($source);

    return (bool)$ok;
}

/**
 * Haal het opgeslagen pad van een transactie op, met dezelfde
 * rechtencontrole als de rest van de app.
 */
function get_receipt_path(PDO $pdo, int $transaction_id, int $user_id, bool $is_admin): ?string {
    $sql = $is_admin
        ? "SELECT receipt_path FROM transactions WHERE id = ?"
        : "SELECT receipt_path FROM transactions WHERE id = ? AND user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($is_admin ? [$transaction_id] : [$transaction_id, $user_id]);
    $path = $stmt->fetchColumn();

    return $path !== false && $path !== null && $path !== '' ? $path : null;
}

/**
 * Verwijder een bonnetjesbestand van het filesystem.
 */
function delete_receipt_file(?string $relative_path): void {
    if ($relative_path === null || $relative_path === '') {
        return;
    }

    $absolute = receipt_absolute_path($relative_path);
    if ($absolute !== false && is_file($absolute)) {
        @unlink($absolute);
    }
}

/**
 * Koppel een opgeslagen bon aan een transactie. Een eventueel eerder
 * bonnetje wordt van het filesystem verwijderd.
 */
function save_receipt_to_transaction(PDO $pdo, int $transaction_id, array $receipt_data, int $user_id, bool $is_admin): bool {
    $previous = get_receipt_path($pdo, $transaction_id, $user_id, $is_admin);

    $sql = $is_admin
        ? "UPDATE transactions SET receipt_path = ?, receipt_original_name = ?, receipt_mime_type = ? WHERE id = ?"
        : "UPDATE transactions SET receipt_path = ?, receipt_original_name = ?, receipt_mime_type = ? WHERE id = ? AND user_id = ?";

    $params = [
        $receipt_data['receipt_path'],
        $receipt_data['receipt_original_name'],
        $receipt_data['receipt_mime_type'],
        $transaction_id,
    ];
    if (!$is_admin) {
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);

    if ($ok && $stmt->rowCount() > 0 && $previous !== null && $previous !== $receipt_data['receipt_path']) {
        delete_receipt_file($previous);
    } elseif (!$ok || $stmt->rowCount() === 0) {
        // De transactie is niet bijgewerkt; laat geen weesbestand achter.
        delete_receipt_file($receipt_data['receipt_path']);
    }

    return $ok;
}

/**
 * Haal het bonnetje van een transactie af: bestand weg, kolommen op NULL.
 */
function remove_receipt_from_transaction(PDO $pdo, int $transaction_id, int $user_id, bool $is_admin): bool {
    $previous = get_receipt_path($pdo, $transaction_id, $user_id, $is_admin);

    $sql = $is_admin
        ? "UPDATE transactions SET receipt_path = NULL, receipt_original_name = NULL, receipt_mime_type = NULL WHERE id = ?"
        : "UPDATE transactions SET receipt_path = NULL, receipt_original_name = NULL, receipt_mime_type = NULL WHERE id = ? AND user_id = ?";

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($is_admin ? [$transaction_id] : [$transaction_id, $user_id]);

    if ($ok) {
        delete_receipt_file($previous);
    }

    return $ok;
}
