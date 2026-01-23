<?php
/**
 * Insert BOEK!N Company Relation
 * Eenmalig script om de BOEK!N bedrijfsrelatie toe te voegen
 * 
 * @author P. Theijssen
 */

require 'config.php';

try {
    $sql = "INSERT INTO relations (
        relation_type,
        relation_code,
        company_name,
        contact_person,
        street,
        postal_code,
        city,
        country,
        vat_number,
        coc_number,
        email,
        phone,
        website,
        iban,
        payment_term,
        default_vat_rate,
        currency,
        language,
        notes,
        is_active,
        user_id,
        created_by
    ) VALUES (
        'debiteur',
        'BOEK!N-001',
        'BOEK!N',
        'P. Theijssen',
        'Hoofdstraat 123',
        '1234 AB',
        'Amsterdam',
        'Nederland',
        'NL123456789B01',
        '12345678',
        'info@boekn.nl',
        '+31 20 123 4567',
        'https://boekn.nl',
        'NL12BANK0123456789',
        14,
        21.00,
        'EUR',
        'nl',
        'Bedrijfsrelatie voor BOEK!N - gebruikt als afzender voor facturen',
        TRUE,
        NULL,
        1
    )
    ON DUPLICATE KEY UPDATE
        company_name = VALUES(company_name),
        street = VALUES(street),
        postal_code = VALUES(postal_code),
        city = VALUES(city),
        vat_number = VALUES(vat_number),
        coc_number = VALUES(coc_number),
        email = VALUES(email),
        phone = VALUES(phone),
        website = VALUES(website),
        iban = VALUES(iban)";
    
    $pdo->exec($sql);
    
    echo "✓ BOEK!N bedrijfsrelatie succesvol toegevoegd/bijgewerkt!\n";
    echo "\nRelatie code: BOEK!N-001\n";
    echo "Bedrijfsnaam: BOEK!N\n";
    echo "BTW-nummer: NL123456789B01\n";
    echo "KvK-nummer: 12345678\n";
    echo "IBAN: NL12BANK0123456789\n";
    echo "\nDit bedrijf wordt gebruikt als afzender op alle facturen.\n";
    
} catch (PDOException $e) {
    die("✗ Fout bij toevoegen BOEK!N relatie: " . $e->getMessage() . "\n");
}
?>
