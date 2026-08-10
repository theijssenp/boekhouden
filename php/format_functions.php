<?php
/**
 * Gedeelde opmaak- en BTW-functies.
 *
 * Wordt ingeladen via auth_functions.php, zodat elke pagina er automatisch
 * over beschikt. Bedragen en BTW horen overal in de applicatie op dezelfde
 * manier berekend en getoond te worden - een boekhouding waarin twee
 * schermen een ander bedrag tonen is onbruikbaar.
 *
 * @author P. Theijssen
 */

/**
 * Bedrag in Nederlandse notatie: 1234.5 -> "1.234,50"
 */
function format_amount($amount, $decimals = 2) {
    return number_format((float)$amount, $decimals, ',', '.');
}

/**
 * Bedrag met euroteken: 1234.5 -> "€ 1.234,50"
 */
function format_euro($amount, $decimals = 2) {
    return '€ ' . format_amount($amount, $decimals);
}

/**
 * Percentage in Nederlandse notatie: 21 -> "21%", 9.5 -> "9,5%"
 */
function format_percentage($percentage) {
    $value = (float)$percentage;
    $decimals = (floor($value) == $value) ? 0 : 1;
    return format_amount($value, $decimals) . '%';
}

/*
 * De BTW-splitsing (excl. / BTW / incl.) zit NIET in PHP maar in de
 * database: transactions.amount_excl, .vat_amount en .amount_incl zijn
 * gegenereerde kolommen. Zie sql/schema.sql.
 *
 * Reden: die berekening stond eerder op drie plaatsen tegelijk (een view,
 * losse SQL in het BTW-overzicht en PHP-code) en kon dus uit elkaar gaan
 * lopen. Voeg hier alsjeblieft geen eigen BTW-berekening toe - lees de
 * kolommen.
 */
