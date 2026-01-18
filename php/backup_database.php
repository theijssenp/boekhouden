<?php
require 'config.php';

// Set headers for file download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="boekhouden_backup_' . date('Y-m-d_H-i-s') . '.sql"');

// Database connection details from config.php
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

// Extract port from host if specified (format: localhost:8889)
$port = 3306; // default MySQL port
if (strpos($host, ':') !== false) {
    list($host, $port) = explode(':', $host, 2);
}

// Create a temporary file for the dump
$tempFile = tempnam(sys_get_temp_dir(), 'dump_');

try {
    // Use mysqldump command to create backup
    $command = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s --skip-comments --skip-add-drop-table --no-create-info --insert-ignore --complete-insert %s 2>/dev/null',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname)
    );
    
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        throw new Exception('mysqldump command failed');
    }
    
    // Output the SQL dump
    echo "-- Boekhouden Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: " . $dbname . "\n";
    echo "-- Host: " . $host . ":" . $port . "\n";
    echo "\n";
    
    // First, output table creation statements
    echo "-- Table structure\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    echo "SET TIME_ZONE = '+00:00';\n";
    echo "\n";
    
    // Get table creation statements
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $createTable['Create Table'] . ";\n\n";
    }
    
    // Now output data
    echo "-- Data dump\n";
    
    foreach ($tables as $table) {
        echo "--\n";
        echo "-- Dumping data for table `$table`\n";
        echo "--\n\n";
        
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
                
                echo "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
            }
            echo "\n";
        } else {
            echo "-- Table `$table` is empty\n\n";
        }
    }
    
    echo "-- End of backup\n";
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    
} catch (Exception $e) {
    // Fallback: manual dump if mysqldump fails
    echo "-- Boekhouden Database Backup (Manual Fallback)\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Note: Using PHP fallback method\n";
    echo "\n";
    
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    echo "SET TIME_ZONE = '+00:00';\n";
    echo "\n";
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "--\n";
        echo "-- Table structure for table `$table`\n";
        echo "--\n\n";
        
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $createTable['Create Table'] . ";\n\n";
        
        echo "--\n";
        echo "-- Dumping data for table `$table`\n";
        echo "--\n\n";
        
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
                
                echo "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
            }
            echo "\n";
        } else {
            echo "-- Table `$table` is empty\n\n";
        }
    }
    
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "-- End of backup\n";
}

// Clean up
if (isset($tempFile) && file_exists($tempFile)) {
    unlink($tempFile);
}
?>