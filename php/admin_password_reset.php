<?php
/**
 * Admin Password Reset Page (Placeholder)
 *
 * @author P. Theijssen
 */

require 'auth_functions.php';
require_admin();

$page_title = "Wachtwoord Resetten";
$show_nav = true;
include 'header.php';
?>

<div class="container">
    <h1>Wachtwoord Resetten</h1>
    
    <div class="alert alert-info">
        <p><strong>Let op:</strong> Deze pagina is nog in ontwikkeling.</p>
        <p>De wachtwoord reset functionaliteit voor administrators wordt momenteel ontwikkeld en zal in een toekomstige release beschikbaar komen.</p>
    </div>
    
    <div class="card">
        <h3 class="card-title">Huidige functionaliteit</h3>
        <p>Voor nu kunt u wachtwoorden resetten via de <a href="admin_users.php">gebruikersbeheer pagina</a>:</p>
        <ol>
            <li>Ga naar <strong>Gebruikersbeheer</strong></li>
            <li>Zoek de gebruiker</li>
            <li>Klik op "Bewerken"</li>
            <li>Scroll naar het wachtwoord veld en voer een nieuw wachtwoord in</li>
        </ol>
        
        <div class="btn-group" style="margin-top: 20px;">
            <a href="admin_users.php" class="btn btn-primary">Naar Gebruikersbeheer</a>
            <a href="admin_dashboard.php" class="btn btn-secondary">Terug naar Admin Dashboard</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>