<?php
/**
 * Router voor de ingebouwde PHP-webserver:
 *
 *     php -S localhost:8081 router.php
 *
 * De ingebouwde server leest geen .htaccess. Zonder deze router zou de
 * opslagmap met bonnetjes tijdens het ontwikkelen gewoon opvraagbaar zijn,
 * terwijl Apache hem in productie blokkeert. Dit zorgt dat lokaal dezelfde
 * regels gelden als op de server.
 *
 * Voor alle overige verzoeken geeft de router false terug, waarna de
 * ingebouwde server het bestand zelf afhandelt.
 *
 * @author P. Theijssen
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

$blocked = [
    '#^/storage/#i',   // bonnetjes: alleen via php/view_receipt.php
    '#^/sql/#i',
    '#^/\.#',          // .git, .env, .htaccess en andere dotfiles
    '#/\.#',
    '#\.(sql|sh|md|bak|tmp|log)$#i',
    '#^/php/(config|auth_functions|header|footer|page_header|theme_init|theme_toggle|receipt_functions)\.php$#i',
];

foreach ($blocked as $pattern) {
    if (preg_match($pattern, $path)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden\n";
        return true;
    }
}

return false;
