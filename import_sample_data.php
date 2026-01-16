<?php
require 'config.php';

echo "<h1>Sample Data Import</h1>\n";

// Check if sample data file exists
$sql_file = 'sample_data_2025.sql';
if (!file_exists($sql_file)) {
    die("Error: Sample data file '$sql_file' not found. Please generate it first.");
}

echo "<p>Found sample data file: " . filesize($sql_file) . " bytes</p>\n";

// Get current transaction count
$current_count = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Current transactions in database: $current_count</p>\n";

// Ask user if they want to clear existing data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clear_existing = isset($_POST['clear_existing']) && $_POST['clear_existing'] === 'yes';
    
    if ($clear_existing) {
        echo "<p>Clearing existing transactions...</p>\n";
        $pdo->exec("DELETE FROM transactions");
        echo "<p>Existing transactions cleared.</p>\n";
    }
    
    // Read SQL file
    $sql_content = file_get_contents($sql_file);
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql_content);
    $inserted_count = 0;
    $error_count = 0;
    
    echo "<p>Importing sample data...</p>\n";
    echo "<ul>\n";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && str_starts_with($statement, 'INSERT INTO')) {
            try {
                $pdo->exec($statement);
                $inserted_count++;
                if ($inserted_count % 20 === 0) {
                    echo "<li>Imported $inserted_count transactions...</li>\n";
                }
            } catch (Exception $e) {
                $error_count++;
                echo "<li style='color: red'>Error importing statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</li>\n";
            }
        }
    }
    
    echo "</ul>\n";
    
    // Get new transaction count
    $new_count = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>Import Complete!</h3>\n";
    echo "<p>Successfully imported: $inserted_count transactions</p>\n";
    echo "<p>Errors: $error_count</p>\n";
    echo "<p>Total transactions in database: $new_count</p>\n";
    echo "</div>\n";
    
    echo "<p><a href='index.php'>View Transactions</a> | <a href='sample_data_interface.php'>Back to Sample Data Interface</a></p>\n";
    
} else {
    // Show form
    ?>
    <form method="post" style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
        <h3>Import Options</h3>
        
        <div style="margin: 15px 0;">
            <input type="checkbox" id="clear_existing" name="clear_existing" value="yes">
            <label for="clear_existing"><strong>Clear existing transactions before import</strong></label>
            <p style="margin-left: 25px; color: #666; font-size: 0.9em;">
                This will delete all existing transactions before importing the sample data.
            </p>
        </div>
        
        <div style="margin: 15px 0;">
            <p><strong>Sample data summary:</strong></p>
            <ul>
                <li>100 expense transactions (uitgave)</li>
                <li>30 income transactions (inkomst)</li>
                <li>All transactions dated in 2025</li>
                <li>Random amounts and VAT rates</li>
                <li>Realistic Dutch descriptions</li>
            </ul>
        </div>
        
        <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;"
                onclick="return confirm('Weet u zeker dat u sample data wilt importeren?')">
            Import Sample Data
        </button>
        
        <a href="sample_data_interface.php" style="margin-left: 10px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
            Cancel
        </a>
    </form>
    <?php
}
?>