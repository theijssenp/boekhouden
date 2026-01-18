<?php
require 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'generate_sql') {
            // Redirect to generate SQL file
            header('Location: generate_sample_data_sql.php?download=true');
            exit;
        } elseif ($_POST['action'] === 'insert_data') {
            // Insert data directly into database
            try {
                // First, clear existing transactions (optional)
                if (isset($_POST['clear_existing']) && $_POST['clear_existing'] === 'yes') {
                    $pdo->exec("DELETE FROM transactions");
                    $message .= "Bestaande transacties verwijderd. ";
                }
                
                // Generate and insert sample data
                require 'generate_sample_data_sql.php';
                
                // Since generate_sample_data_sql.php outputs SQL, we need to capture and execute it
                ob_start();
                include 'generate_sample_data_sql.php';
                $sql_content = ob_get_clean();
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $sql_content);
                $inserted_count = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && str_starts_with($statement, 'INSERT INTO')) {
                        $pdo->exec($statement);
                        $inserted_count++;
                    }
                }
                
                $message .= "Succesvol $inserted_count transacties toegevoegd aan de database.";
                
            } catch (Exception $e) {
                $error = "Fout bij invoegen data: " . $e->getMessage();
            }
        }
    }
}

// Get current transaction count
$transaction_count = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Data Generator - Boekhouden</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sample-data-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .data-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .data-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .data-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .data-card p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .btn-data {
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
        .btn-data:hover {
            background: #2980b9;
        }
        .btn-data.generate {
            background: #27ae60;
        }
        .btn-data.generate:hover {
            background: #219653;
        }
        .btn-data.insert {
            background: #f39c12;
        }
        .btn-data.insert:hover {
            background: #e67e22;
        }
        .checkbox-group {
            margin: 1rem 0;
            text-align: left;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        form {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sample Data Generator</h1>
        <p>Genereer testdata voor de boekhouden database</p>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php">BTW Kwartaal</a></li>
            <li><a href="balans.php">Balans</a></li>
            <li><a href="vat_rates_admin.php">BTW Tarieven</a></li>
            <li><a href="backup_interface.php">Backup</a></li>
            <li><a href="sample_data_interface.php" class="active">Sample Data</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="sample-data-container">
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
                <p><strong>Sample data voor 2025:</strong></p>
                <ul>
                    <li><strong>100 uitgaven</strong> (kosten) met willekeurige bedragen tussen €5 en €500</li>
                    <li><strong>30 inkomsten</strong> met willekeurige bedragen tussen €100 en €5000</li>
                    <li>Willekeurige BTW-tarieven (21%, 9%, 0%)</li>
                    <li>Willekeurige categorieën gebaseerd op bestaande categorie IDs</li>
                    <li>Realistische Nederlandse omschrijvingen</li>
                    <li>Factuurnummers voor inkomsten en sommige uitgaven</li>
                </ul>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $transaction_count; ?></div>
                    <div class="stat-label">Huidige transacties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">130</div>
                    <div class="stat-label">Nieuwe transacties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">2025</div>
                    <div class="stat-label">Jaar</div>
                </div>
            </div>
            
            <div class="data-actions">
                <div class="data-card">
                    <h3>Genereer SQL Bestand</h3>
                    <p>Genereer een SQL-bestand met sample data dat u later kunt importeren.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="generate_sql">
                        <button type="submit" class="btn-data generate">Download SQL Bestand</button>
                    </form>
                </div>
                
                <div class="data-card">
                    <h3>Direct Invoegen</h3>
                    <p>Voeg de sample data direct toe aan de database.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="insert_data">
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="clear_existing" name="clear_existing" value="yes">
                            <label for="clear_existing">Verwijder eerst bestaande transacties</label>
                        </div>
                        
                        <button type="submit" class="btn-data insert" onclick="return confirm('Weet u zeker dat u sample data wilt toevoegen aan de database?')">
                            Data Invoegen
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">Categorie Overzicht</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categorie Naam</th>
                                <th>Type</th>
                                <th>Gebruikt voor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Inkomsten</td>
                                <td><span class="positive">Inkomst</span></td>
                                <td>Alle inkomsten transacties</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Overig</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Diverse kosten</td>
                            </tr>
                            <tr>
                                <td>7</td>
                                <td>Transportkosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Benzine, OV, parkeren, taxi</td>
                            </tr>
                            <tr>
                                <td>8</td>
                                <td>Administratiekosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Kantoorartikelen, postzegels</td>
                            </tr>
                            <tr>
                                <td>9</td>
                                <td>Hotelkosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Hotelovernachtingen, conferenties</td>
                            </tr>
                            <tr>
                                <td>11</td>
                                <td>Andere kosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Diverse niet-gecategoriseerde kosten</td>
                            </tr>
                            <tr>
                                <td>12</td>
                                <td>Communicatiekosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Telefoon, internet, mobiele data</td>
                            </tr>
                            <tr>
                                <td>13</td>
                                <td>Cloud diensten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Webhosting, cloud storage, software</td>
                            </tr>
                            <tr>
                                <td>14</td>
                                <td>Kantoorkosten</td>
                                <td><span class="negative">Uitgave</span></td>
                                <td>Meubilair, apparatuur, verbruik</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="alert alert-warning" style="margin-top: 2rem;">
                <p><strong>Belangrijke informatie:</strong></p>
                <ul>
                    <li>Sample data is bedoeld voor test- en demonstratiedoeleinden</li>
                    <li>Data wordt gegenereerd voor het jaar 2025</li>
                    <li>BTW-tarieven zijn gebaseerd op Nederlandse tarieven (21%, 9%, 0%)</li>
                    <li>Bij "Direct Invoegen" kunt u kiezen om bestaande transacties eerst te verwijderen</li>
                    <li>Maak altijd een backup voor u sample data invoegt</li>
                </ul>
            </div>
            
            <div class="btn-group">
                <a href="../index.php" class="btn btn-primary">Terug naar Transacties</a>
                <a href="backup_interface.php" class="btn btn-secondary">Maak Backup</a>
            </div>
        </div>
    </main>
</body>
</html>