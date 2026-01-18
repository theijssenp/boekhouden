<?php
/**
 * Admin Settings Page (Placeholder)
 *
 * @author P. Theijssen
 */

require 'auth_functions.php';
require_admin();

$page_title = "Systeem Instellingen";
$show_nav = true;
include 'header.php';
?>

<div class="container">
    <h1>Systeem Instellingen</h1>
    
    <div class="alert alert-info">
        <p><strong>Let op:</strong> Deze pagina is nog in ontwikkeling.</p>
        <p>De systeem instellingen functionaliteit wordt momenteel ontwikkeld en zal in een toekomstige release beschikbaar komen.</p>
    </div>
    
    <div class="card">
        <h3 class="card-title">Geplande functionaliteit</h3>
        <ul>
            <li>Systeem configuratie aanpassen</li>
            <li>E-mail instellingen</li>
            <li>Backup instellingen</li>
            <li>Gebruikersrechten beheren</li>
            <li>BTW tarieven configureren</li>
        </ul>
        
        <div class="btn-group" style="margin-top: 20px;">
            <a href="admin_dashboard.php" class="btn btn-primary">Terug naar Admin Dashboard</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>