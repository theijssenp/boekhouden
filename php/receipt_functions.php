<?php
/**
 * Receipt upload functions for Boekhouden
 *
 * @author P. Theijssen
 */

define('RECEIPT_MAX_SIZE', 5 * 1024 * 1024);
define('RECEIPT_MAX_WIDTH', 1200);
define('RECEIPT_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);

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

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, RECEIPT_ALLOWED_MIMES, true)) {
        return ['valid' => false, 'error' => 'Ongeldig bestandstype. Toegestaan: JPG, PNG, GIF, PDF.'];
    }

    return ['valid' => true, 'error' => ''];
}

/**
 * Process an uploaded receipt: resize images and return as BLOB
 * @return array|false ['receipt_blob' => string, 'receipt_original_name' => string, 'receipt_mime_type' => string]
 */
function process_receipt_upload(array $file) {
    $original_name = basename($file['name']);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif']) && function_exists('imagecreatetruecolor')) {
        $blob = resize_image_to_blob($file['tmp_name'], $mime);
        if ($blob === false) {
            return false;
        }
    } else {
        $blob = file_get_contents($file['tmp_name']);
    }

    return [
        'receipt_blob'          => $blob,
        'receipt_original_name' => $original_name,
        'receipt_mime_type'     => $mime,
    ];
}

/**
 * Resize an image and return as BLOB string
 */
function resize_image_to_blob(string $path, string $mime): string|false {
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

    ob_start();
    match ($mime) {
        'image/jpeg' => imagejpeg($source, null, 80),
        'image/png'  => imagepng($source, null, 7),
        'image/gif'  => imagegif($source),
        default      => null,
    };
    $blob = ob_get_clean();
    imagedestroy($source);

    return $blob ?: false;
}

/**
 * Save receipt data to a transaction row
 */
function save_receipt_to_transaction(PDO $pdo, int $transaction_id, array $receipt_data, int $user_id, bool $is_admin): bool {
    $sql = $is_admin
        ? "UPDATE transactions SET receipt_blob = ?, receipt_original_name = ?, receipt_mime_type = ? WHERE id = ?"
        : "UPDATE transactions SET receipt_blob = ?, receipt_original_name = ?, receipt_mime_type = ? WHERE id = ? AND user_id = ?";

    $params = $is_admin
        ? [$receipt_data['receipt_blob'], $receipt_data['receipt_original_name'], $receipt_data['receipt_mime_type'], $transaction_id]
        : [$receipt_data['receipt_blob'], $receipt_data['receipt_original_name'], $receipt_data['receipt_mime_type'], $transaction_id, $user_id];

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Remove receipt from a transaction (set columns to NULL)
 */
function remove_receipt_from_transaction(PDO $pdo, int $transaction_id, int $user_id, bool $is_admin): bool {
    $sql = $is_admin
        ? "UPDATE transactions SET receipt_blob = NULL, receipt_original_name = NULL, receipt_mime_type = NULL WHERE id = ?"
        : "UPDATE transactions SET receipt_blob = NULL, receipt_original_name = NULL, receipt_mime_type = NULL WHERE id = ? AND user_id = ?";

    $params = $is_admin
        ? [$transaction_id]
        : [$transaction_id, $user_id];

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}