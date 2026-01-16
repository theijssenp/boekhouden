<?php
require 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'download_backup') {
        // Redirect to the backup script
        header('Location: backup_database.php');
        exit;
    } elseif ($_POST['action'] === 'save_to_file') {
        // Generate backup and save to file
        $backupDir = 'backups/';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = $backupDir . 'boekhouden_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        try {
            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            $sqlContent = "-- Boekhouden Database Backup\n";
            $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- Database: " . DB_NAME . "\n";
            $sqlContent .= "-- Host: " . DB_HOST . "\n";
            $sqlContent .= "\n";
            $sqlContent .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $sqlContent .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $sqlContent .= "SET TIME_ZONE = '+00:00';\n";
            $sqlContent .= "\n";
            
            foreach ($tables as $table) {
                $sqlContent .= "--\n";
                $sqlContent .= "-- Table structure for table `$table`\n";
                $sqlContent .= "--\n\n";
                
                $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sqlContent .= $createTable['Create Table'] . ";\n\n";
                
                $sqlContent .= "--\n";
                $sqlContent .= "-- Dumping data for table `$table`\n";
                $sqlContent .= "--\n\n";
                
                // Get column information
                $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN, 0);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                // Get all rows
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columns as $column) {
                            if ($row[$column] === null) {
                                $values[] = 'NULL';
                            } else {
                                // Escape special characters
                                $value = str_replace(
                                    ["\\", "'", "\0", "\n", "\r", "\x1a"],
                                    ["\\\\", "''", "\\0", "\\n", "\\r", "\\Z"],
                                    $row[$column]
                                );
                                $values[] = "'" . $value . "'";
                            }
                        }
                        
                        $sqlContent .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sqlContent .= "\n";
                } else {
                    $sqlContent .= "-- Table `$table` is empty\n\n";
                }
            }
            
            $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            $sqlContent .= "-- End of backup\n";
            
            // Write to file
            if (file_put_contents($filename, $sqlContent) !== false) {
                $message = "Backup succesvol opgeslagen als: " . basename($filename);
            } else {
                $error = "Fout bij opslaan backup bestand.";
            }
            
        } catch (Exception $e) {
            $error = "Fout bij maken backup: " . $e->getMessage();
        }
    }
}

// Get existing backup files
$backupFiles = [];
$backupDir = 'backups/';
if (file_exists($backupDir)) {
    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backupDir . $file;
            $backupFiles[] = [
                'name' => $file,
                'path' => $filepath,
                'size' => filesize($filepath),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Boekhouden</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .backup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .backup-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .backup-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .backup-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .backup-card p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .btn-backup {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        .btn-backup:hover {
            background: #2980b9;
        }
        .btn-backup.download {
            background: #27ae60;
        }
        .btn-backup.download:hover {
            background: #219653;
        }
        .btn-backup.save {
            background: #f39c12;
        }
        .btn-backup.save:hover {
            background: #e67e22;
        }
        .backup-list {
            margin-top: 2rem;
        }
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }
        .backup-item:last-child {
            border-bottom: none;
        }
        .backup-info {
            flex: 1;
        }
        .backup-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .backup-meta {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .backup-actions-small {
            display: flex;
            gap: 0.5rem;
        }
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 3px;
            text-decoration: none;
            color: white;
            background: #7f8c8d;
        }
        .btn-small.download {
            background: #3498db;
        }
        .btn-small.delete {
            background: #e74c3c;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
            background: #f8f9fa;
            border-radius: 8px;
        }
        form {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Database Backup</h1>
        <p>Maak een backup van de boekhouden database</p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
            <li><a href="balans.php">Balans</a></li>
            <li><a href="vat_rates_admin.php">BTW Tarieven</a></li>
            <li><a href="backup_interface.php" class="active">Backup</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="backup-container">
            <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <p><strong>Database informatie:</strong></p>
                <ul>
                    <li><strong>Database:</strong> <?php echo DB_NAME; ?></li>
                    <li><strong>Host:</strong> <?php echo DB_HOST; ?></li>
                    <li><strong>Backup datum:</strong> <?php echo date('d-m-Y H:i:s'); ?></li>
                </ul>
                <p>Een backup bevat alle tabellen en gegevens in SQL-formaat. Dit kan worden gebruikt om de database te herstellen.</p>
            </div>
            
            <div class="backup-actions">
                <div class="backup-card">
                    <h3>Direct Downloaden</h3>
                    <p>Download de backup direct als SQL-bestand naar uw computer.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="download_backup">
                        <button type="submit" class="btn-backup download">Download Backup</button>
                    </form>
                </div>
                
                <div class="backup-card">
                    <h3>Opslaan op Server</h3>
                    <p>Sla de backup op in de 'backups/' map op de server voor later gebruik.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="save_to_file">
                        <button type="submit" class="btn-backup save">Opslaan op Server</button>
                    </form>
                </div>
            </div>
            
            <div class="backup-list">
                <h2>Bestaande Backups</h2>
                
                <?php if (empty($backupFiles)): ?>
                <div class="empty-state">
                    <p>Nog geen backups beschikbaar.</p>
                    <p>Maak uw eerste backup met een van de opties hierboven.</p>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Bestandsnaam</th>
                                    <th>Grootte</th>
                                    <th>Datum</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupFiles as $backup): ?>
                                <tr>
                                    <td>
                                        <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                        <div class="backup-meta"><?php echo $backup['path']; ?></div>
                                    </td>
                                    <td><?php echo formatBytes($backup['size']); ?></td>
                                    <td><?php echo $backup['modified']; ?></td>
                                    <td>
                                        <div class="backup-actions-small">
                                            <a href="<?php echo $backup['path']; ?>" download class="btn-small download">Download</a>
                                            <a href="delete_backup.php?file=<?php echo urlencode($backup['name']); ?>" 
                                               class="btn-small delete"
                                               onclick="return confirm('Weet u zeker dat u deze backup wilt verwijderen?')">Verwijderen</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="alert alert-warning" style="margin-top: 2rem;">
                <p><strong>Belangrijke informatie:</strong></p>
                <ul>
                    <li>Backups worden gemaakt in SQL-formaat en kunnen worden geïmporteerd met MySQL/MariaDB</li>
                    <li>Voor een complete restore: <code>mysql -u gebruikersnaam -p database_naam < backup_bestand.sql</code></li>
                    <li>Backups op de server worden opgeslagen in de 'backups/' map</li>
                    <li>Verwijder oude backups regelmatig om schijfruimte te besparen</li>
                </ul>
            </div>
            
            <div class="btn-group">
                <a href="index.php" class="btn btn-primary">Terug naar Transacties</a>
                <a href="vat_rates_admin.php" class="btn btn-secondary">BTW Tarieven Beheer</a>
            </div>
        </div>
    </main>
</body>
</html>

<?php
// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>