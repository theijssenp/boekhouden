<?php
/**
 * Logout page for Boekhouden
 */

require 'auth_functions.php';

// Logout user
logout_user();

// Redirect to login page
header('Location: login.php?message=uitgelogd');
exit;