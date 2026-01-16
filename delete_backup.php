<?php
require 'config.php';

$backupDir = 'backups/';
$message = '';
$error = '';

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = $backupDir . $filename;
    
    // Security check: ensure file is in backup directory and is a .sql file
    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        if (unlink($filepath)) {
            $message = "Backup '$filename' succesvol verwijderd.";
        } else {
            $error = "Fout bij verwijderen van backup '$filename'.";
        }
    } else {
        $error = "Ongeldig backup bestand.";
    }
} else {
    $error = "Geen backup bestand opgegeven.";
}

// Redirect back to backup interface
header('Location: backup_interface.php?' . ($message ? 'message=' . urlencode($message) : 'error=' . urlencode($error)));
exit;
?>