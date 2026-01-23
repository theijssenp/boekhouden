<?php
/**
 * PDF Factuur Generatie
 * Genereert een professionele factuur voor verkoop transacties
 *
 * @author P. Theijssen
 */

// Start output buffering to prevent any output before PDF
ob_start();

// Suppress all errors/warnings for clean PDF output
error_reporting(0);

require '../php/auth_functions.php';
require_login();

require '../php/config.php';
require 'fpdf.php';

// Clean any output that might have been generated
ob_end_clean();

// Helper function to convert UTF-8 to ISO-8859-1 for FPDF (replacement for deprecated utf8_decode)
function convert_encoding($text) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
    // Fallback: simple character replacement
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}

// Get transaction ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Geen transactie ID opgegeven');
}

$transaction_id = $_GET['id'];
$user_id = get_current_user_id();
$is_admin = is_admin();

// Get transaction data with relation info
if ($is_admin) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name,
               r.relation_code, r.company_name, r.contact_person,
               r.street, r.postal_code, r.city, r.country,
               r.email as relation_email, r.phone as relation_phone,
               r.vat_number as relation_vat, r.notes as relation_notes,
               r.payment_term as relation_payment_term
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN relations r ON t.relation_id = r.id
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name,
               r.relation_code, r.company_name, r.contact_person,
               r.street, r.postal_code, r.city, r.country,
               r.email as relation_email, r.phone as relation_phone,
               r.vat_number as relation_vat, r.notes as relation_notes,
               r.payment_term as relation_payment_term
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN relations r ON t.relation_id = r.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
}

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die('Transactie niet gevonden of geen toegang');
}

// Check if transaction is income type
if ($transaction['type'] !== 'inkomst') {
    die('Kan alleen facturen genereren voor inkomst transacties');
}

// Get BOEK!N company data (relation_code = 'BOEK!N-001')
$stmt = $pdo->prepare("
    SELECT * FROM relations 
    WHERE relation_code = 'BOEK!N-001' 
    LIMIT 1
");
$stmt->execute();
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die('BOEK!N bedrijfsgegevens niet gevonden. Voer eerst php/insert_boekn_company.php uit.');
}

// Calculate VAT amounts
$amount = abs($transaction['amount']); // Use absolute value
$vat_rate = floatval($transaction['vat_percentage']);
$vat_included = $transaction['vat_included'];

if ($vat_rate > 0 && $vat_included) {
    // Amount includes VAT
    $base_amount = $amount / (1 + ($vat_rate / 100));
    $vat_amount = $amount - $base_amount;
    $total_amount = $amount;
} else if ($vat_rate > 0 && !$vat_included) {
    // Amount excludes VAT
    $base_amount = $amount;
    $vat_amount = $amount * ($vat_rate / 100);
    $total_amount = $amount + $vat_amount;
} else {
    // No VAT
    $base_amount = $amount;
    $vat_amount = 0;
    $total_amount = $amount;
}

// Create PDF
class InvoicePDF extends FPDF {
    private $company;
    private $transaction;
    
    function __construct($company, $transaction) {
        parent::__construct();
        $this->company = $company;
        $this->transaction = $transaction;
    }
    
    function Header() {
        // Colors
        $primary_color = [44, 62, 80];    // #2c3e50
        
        // Header bar - smaller
        $this->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
        $this->Rect(0, 0, 210, 25, 'F');
        
        // FACTUUR label - centered
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 28);
        $this->SetXY(0, 8);
        $this->Cell(210, 10, 'FACTUUR', 0, 1, 'C');
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
        
        // Line break
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Footer line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);
        
        // Company info in footer
        $footer_text = $this->company['company_name'] . ' | ';
        $footer_text .= 'KvK: ' . $this->company['coc_number'] . ' | ';
        $footer_text .= 'BTW: ' . $this->company['vat_number'] . ' | ';
        $footer_text .= 'IBAN: ' . $this->company['iban'];
        
        $this->SetX(15);
        $this->MultiCell(180, 4, $footer_text, 0, 'C');
        
        // Page number
        $this->SetY(-15);
        $this->SetX(15);
        $this->Cell(0, 5, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Create PDF instance
$pdf = new InvoicePDF($company, $transaction);
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Company details (sender) - left side
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 6, 'Van:', 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 6, $company['company_name'], 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 5, $company['street'], 0, 1);
$pdf->Cell(95, 5, $company['postal_code'] . ' ' . $company['city'], 0, 1);
$pdf->Cell(95, 5, $company['country'], 0, 1);
$pdf->Ln(2);
$pdf->Cell(95, 5, 'T: ' . $company['phone'], 0, 1);
$pdf->Cell(95, 5, 'E: ' . $company['email'], 0, 1);
if (!empty($company['website'])) {
    $pdf->Cell(95, 5, 'W: ' . $company['website'], 0, 1);
}

// Invoice info - right side
$pdf->SetXY(115, 55);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(80, 6, 'Factuurnummer:', 0, 1);
$pdf->SetX(115);
$pdf->SetFont('Arial', '', 10);
$invoice_number = !empty($transaction['invoice_number']) ? $transaction['invoice_number'] : 'CONCEPT-' . $transaction['id'];
$pdf->Cell(80, 5, $invoice_number, 0, 1);

$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(80, 6, 'Factuurdatum:', 0, 1);
$pdf->SetX(115);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(80, 5, date('d-m-Y', strtotime($transaction['date'])), 0, 1);

$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(80, 6, 'Betalingstermijn:', 0, 1);
$pdf->SetX(115);
$pdf->SetFont('Arial', '', 10);
// Use debtor's payment term if available, otherwise company default
$payment_term = !empty($transaction['relation_payment_term']) ? $transaction['relation_payment_term'] : $company['payment_term'];
$pdf->Cell(80, 5, $payment_term . ' dagen', 0, 1);

// Calculate due date using debtor's payment term
$due_date = date('d-m-Y', strtotime($transaction['date'] . ' + ' . $payment_term . ' days'));
$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(80, 6, 'Vervaldatum:', 0, 1);
$pdf->SetX(115);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(80, 5, $due_date, 0, 1);

// Customer details
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 6, 'Aan:', 0, 1);

if (!empty($transaction['company_name'])) {
    // Specific customer
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 6, $transaction['company_name'], 0, 1);
    
    if (!empty($transaction['contact_person'])) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(95, 5, 't.a.v. ' . $transaction['contact_person'], 0, 1);
    }
    
    $pdf->SetFont('Arial', '', 10);
    if (!empty($transaction['street'])) {
        $pdf->Cell(95, 5, $transaction['street'], 0, 1);
    }
    if (!empty($transaction['postal_code']) || !empty($transaction['city'])) {
        $pdf->Cell(95, 5, trim($transaction['postal_code'] . ' ' . $transaction['city']), 0, 1);
    }
    if (!empty($transaction['country'])) {
        $pdf->Cell(95, 5, $transaction['country'], 0, 1);
    }
    
    if (!empty($transaction['relation_vat'])) {
        $pdf->Ln(1);
        $pdf->Cell(95, 5, 'BTW-nr: ' . $transaction['relation_vat'], 0, 1);
    }
} else {
    // Generic customer
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(95, 6, 'Diverse Klanten', 0, 1);
}

// Separator line
$pdf->Ln(8);
$pdf->SetDrawColor(44, 62, 80);
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// Invoice items table header
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(90, 8, 'Omschrijving', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Prijs', 1, 0, 'R', true);
$pdf->Cell(20, 8, 'BTW %', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Totaal', 1, 1, 'R', true);

// Reset text color
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);

// Invoice item
$pdf->Cell(90, 8, convert_encoding($transaction['description']), 1, 0, 'L');
$pdf->Cell(35, 8, 'EUR ' . number_format($base_amount, 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(20, 8, number_format($vat_rate, 0) . '%', 1, 0, 'C');
$pdf->Cell(35, 8, 'EUR ' . number_format($base_amount, 2, ',', '.'), 1, 1, 'R');

// Totals - align with "Totaal" column (starts at 15 + 90 + 35 = 140)
$pdf->Ln(3);

// Subtotal
$pdf->SetX(140);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(20, 6, 'Subtotaal:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 6, 'EUR ' . number_format($base_amount, 2, ',', '.'), 0, 1, 'R');

// VAT
if ($vat_amount > 0) {
    $pdf->SetX(140);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(20, 6, 'BTW ' . number_format($vat_rate, 0) . '%:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(35, 6, 'EUR ' . number_format($vat_amount, 2, ',', '.'), 0, 1, 'R');
}

// Total line
$pdf->SetLineWidth(0.3);
$pdf->Line(140, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);
$pdf->Ln(4);

// Total amount
$pdf->SetX(140);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(20, 8, 'Totaal:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(35, 8, 'EUR ' . number_format($total_amount, 2, ',', '.'), 0, 1, 'R');

// Payment info
$pdf->Ln(15);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'Betalingsinformatie', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, 'Gelieve het totaalbedrag binnen ' . $payment_term . ' dagen over te maken naar:');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'Rekeningnummer:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $company['iban'], 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'T.n.v.:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $company['company_name'], 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'Onder vermelding van:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $invoice_number, 0, 1);

// Notes section (if debtor has notes)
if (!empty($transaction['relation_notes'])) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Opmerkingen', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 4, convert_encoding($transaction['relation_notes']));
}

// Thank you message
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 5, 'Hartelijk dank voor uw opdracht. Wij vertrouwen erop u hiermee naar tevredenheid te hebben geholpen.');

// Output PDF - clean buffer first
ob_clean();
$filename = 'Factuur_' . $invoice_number . '_' . date('Y-m-d', strtotime($transaction['date'])) . '.pdf';
$pdf->Output('I', $filename);
exit();
