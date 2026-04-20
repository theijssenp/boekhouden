<?php
/**
 * Admin Page Header Wrapper
 *
 * Wrapper rond page_header.php met admin auth en extra admin CSS.
 * Bestaande admin pagina's die include 'header.php' doen blijven werken.
 */

$require_auth = 'admin';

// Admin-specifieke CSS (container, page-header layout)
$admin_css = <<<CSS
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}

.page-header h1 {
    margin: 0;
    color: var(--text-primary);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--secondary-color);
    color: var(--text-inverse);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-details {
    text-align: right;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
}

.user-role {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: capitalize;
}
CSS;

// Merge met eventuele bestaande page_css
if (!empty($page_css)) {
    $page_css = $admin_css . "\n" . $page_css;
} else {
    $page_css = $admin_css;
}

$show_nav = $show_nav ?? true;

include 'page_header.php';