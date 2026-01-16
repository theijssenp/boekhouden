<?php
/**
 * Generate sample data SQL file for boekhouden database
 * Run from command line: php generate_sample_data_sql.php > sample_data_2025.sql
 * Or access via web: generate_sample_data_sql.php?download=true
 */

// Sample descriptions for different categories
$income_descriptions = [
    'Factuur klant {COMPANY}',
    'Betaling project {PROJECT}',
    'Consultancy diensten {MONTH}',
    'Software licentie {CLIENT}',
    'Onderhoudscontract {YEAR}',
    'Training {TOPIC}',
    'Webdevelopment {WEBSITE}',
    'Advies {INDUSTRY}',
    'Hosting {DOMAIN}',
    'Support {SERVICE}'
];

$expense_descriptions = [
    'Benzine tankbeurt',
    'OV abonnement',
    'Parkeerkosten {LOCATION}',
    'Treinreis {DESTINATION}',
    'Taxi {FROM}-{TO}',
    'Kantoorartikelen {SUPPLIER}',
    'Printer inkt',
    'Postzegels',
    'Envelopes',
    'Notitieboeken',
    'Hotel overnachting {CITY}',
    'Conferentie {EVENT}',
    'Vliegticket {DESTINATION}',
    'Telefoonrekening {PROVIDER}',
    'Internet abonnement',
    'Mobiele data',
    'Cloud storage {PROVIDER}',
    'Webhosting {DOMAIN}',
    'Software licentie {SOFTWARE}',
    'Koffie apparatuur',
    'Water cooler',
    'Schoonmaakmiddelen',
    'Kantoormeubilair',
    'Stroomverbruik {MONTH}',
    'Gasrekening {MONTH}',
    'Waterrekening {MONTH}'
];

// Company names for placeholders
$companies = ['ABC BV', 'XYZ NV', 'TechSolutions', 'InnovateCorp', 'DigitalWorks', 'SmartSystems', 'FutureTech', 'CloudExperts'];
$projects = ['Website redesign', 'ERP implementatie', 'Mobile app', 'Database migration', 'Security audit', 'Cloud migration'];
$clients = ['Ministerie', 'Gemeente', 'Ziekenhuis', 'School', 'Universiteit', 'MKB bedrijf'];
$months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$topics = ['Security', 'Cloud', 'DevOps', 'Agile', 'Scrum', 'React', 'Vue.js', 'Laravel'];
$websites = ['voorbeeld.nl', 'testwebsite.com', 'demo-app.org', 'prototype.io'];
$industries = ['Zorg', 'Onderwijs', 'Overheid', 'Financieel', 'Retail', 'Logistiek'];
$services = ['24/7 support', 'Managed services', 'Monitoring', 'Backup'];
$locations = ['Amsterdam', 'Rotterdam', 'Utrecht', 'Den Haag', 'Eindhoven', 'Groningen'];
$suppliers = ['Staples', 'Office Depot', 'Bol.com', 'Amazon', 'Local supplier'];
$events = ['Tech Conference', 'Developer Days', 'Cloud Summit', 'Security Expo'];
$providers = ['KPN', 'Ziggo', 'Vodafone', 'T-Mobile', 'Microsoft', 'Google', 'AWS'];
$software = ['Microsoft Office', 'Adobe Creative Cloud', 'Figma', 'Slack', 'Zoom'];

// VAT rates for 2025 (21% is standard, 9% for reduced, 0% for exempt)
$vat_rates = [
    ['rate' => 21, 'name' => 'Hoog tarief'],
    ['rate' => 9, 'name' => 'Verlaagd tarief'],
    ['rate' => 0, 'name' => 'Vrijgesteld']
];

// Category mapping for expenses
$expense_categories = [
    7 => 'Transportkosten',
    8 => 'Administratiekosten',
    9 => 'Hotelkosten',
    11 => 'Andere kosten',
    12 => 'Communicatiekosten',
    13 => 'Cloud diensten',
    14 => 'Kantoorkosten',
    3 => 'Overig'
];

// Income category is always 1
$income_category_id = 1;

// Set headers for download
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="sample_data_2025.sql"');
}

// Generate SQL output
echo "-- Sample data for boekhouden database\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- 100 expense rows and 30 income rows for 2025\n";
echo "-- Categories based on existing category IDs\n";
echo "\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "\n";

// Function to replace placeholders in descriptions
function replace_placeholders($template) {
    global $companies, $projects, $clients, $months, $topics, $websites, $industries, $services, $locations, $suppliers, $events, $providers, $software;
    
    $replacements = [
        '{COMPANY}' => $companies[array_rand($companies)],
        '{PROJECT}' => $projects[array_rand($projects)],
        '{CLIENT}' => $clients[array_rand($clients)],
        '{MONTH}' => $months[array_rand($months)],
        '{TOPIC}' => $topics[array_rand($topics)],
        '{WEBSITE}' => $websites[array_rand($websites)],
        '{INDUSTRY}' => $industries[array_rand($industries)],
        '{SERVICE}' => $services[array_rand($services)],
        '{LOCATION}' => $locations[array_rand($locations)],
        '{DESTINATION}' => $locations[array_rand($locations)],
        '{FROM}' => $locations[array_rand($locations)],
        '{TO}' => $locations[array_rand($locations)],
        '{SUPPLIER}' => $suppliers[array_rand($suppliers)],
        '{CITY}' => $locations[array_rand($locations)],
        '{EVENT}' => $events[array_rand($events)],
        '{PROVIDER}' => $providers[array_rand($providers)],
        '{DOMAIN}' => $websites[array_rand($websites)],
        '{SOFTWARE}' => $software[array_rand($software)],
        '{YEAR}' => '2025'
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

// Generate expense transactions (100 rows)
echo "-- Expense transactions (uitgave)\n";
for ($i = 1; $i <= 100; $i++) {
    $date = sprintf('2025-%02d-%02d', rand(1, 12), rand(1, 28));
    $description = replace_placeholders($expense_descriptions[array_rand($expense_descriptions)]);
    $amount = round((rand(500, 50000) / 100) * -1, 2); // Negative for expenses
    $type = 'uitgave';
    
    // Random category from expense categories
    $category_keys = array_keys($expense_categories);
    $category_id = $category_keys[array_rand($category_keys)];
    
    // Random VAT settings
    $vat_rate_info = $vat_rates[array_rand($vat_rates)];
    $vat_percentage = $vat_rate_info['rate'];
    $vat_included = rand(0, 1); // 50% chance VAT is included
    $vat_deductible = ($vat_percentage > 0 && rand(0, 1)) ? 1 : 0; // 50% chance deductible if VAT > 0
    
    // Sometimes add invoice number
    $invoice_number = (rand(0, 3) === 0) ? sprintf('FACT-2025-%04d', rand(1, 9999)) : 'NULL';
    
    $sql = sprintf(
        "INSERT INTO `transactions` (`date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `invoice_number`, `created_at`) VALUES ('%s', '%s', %.2f, '%s', %d, %d, %d, %d, %s, NOW());",
        $date,
        addslashes($description),
        $amount,
        $type,
        $category_id,
        $vat_percentage,
        $vat_included,
        $vat_deductible,
        $invoice_number === 'NULL' ? 'NULL' : "'" . addslashes($invoice_number) . "'"
    );
    
    echo $sql . "\n";
}

echo "\n-- Income transactions (inkomst)\n";
for ($i = 1; $i <= 30; $i++) {
    $date = sprintf('2025-%02d-%02d', rand(1, 12), rand(1, 28));
    $description = replace_placeholders($income_descriptions[array_rand($income_descriptions)]);
    $amount = round(rand(10000, 500000) / 100, 2); // Positive for income
    $type = 'inkomst';
    $category_id = $income_category_id;
    
    // Income VAT: usually 21%, sometimes 9% or 0%
    $vat_chance = rand(1, 100);
    if ($vat_chance <= 70) {
        $vat_percentage = 21; // 70% standard rate
    } elseif ($vat_chance <= 90) {
        $vat_percentage = 9; // 20% reduced rate
    } else {
        $vat_percentage = 0; // 10% exempt
    }
    
    $vat_included = rand(0, 1); // 50% chance VAT is included
    $vat_deductible = 0; // Income VAT is never deductible
    
    // Income usually has invoice numbers
    $invoice_number = sprintf('INV-2025-%04d', rand(1000, 9999));
    
    $sql = sprintf(
        "INSERT INTO `transactions` (`date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `invoice_number`, `created_at`) VALUES ('%s', '%s', %.2f, '%s', %d, %d, %d, %d, '%s', NOW());",
        $date,
        addslashes($description),
        $amount,
        $type,
        $category_id,
        $vat_percentage,
        $vat_included,
        $vat_deductible,
        addslashes($invoice_number)
    );
    
    echo $sql . "\n";
}

echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";
echo "-- End of sample data\n";
echo "-- Total: 130 transactions (100 expenses, 30 income)\n";
?>