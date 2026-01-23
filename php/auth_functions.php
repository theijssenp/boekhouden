<?php
/**
 * Authentication functions for Boekhouden
 *
 * @author P. Theijssen
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

/**
 * Get base URL for redirects (handles both local and production environments)
 */
function get_base_url() {
    // Check if we're running on localhost or production
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the base path (if the application is in a subdirectory)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_path = dirname($script_name);
    
    // Remove trailing slash if present
    if ($base_path === '/') {
        $base_path = '';
    }
    
    return $protocol . $host . $base_path;
}

/**
 * Hash a password for storage
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function get_current_user_data() {
    global $pdo;
    
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = get_current_user_id();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if current user is administrator
 */
function is_admin() {
    $user = get_current_user_data();
    return $user && $user['user_type'] === 'administrator';
}

/**
 * Check if current user is administratie_houder
 */
function is_administratie_houder() {
    $user = get_current_user_data();
    return $user && $user['user_type'] === 'administratie_houder';
}

/**
 * Login user
 */
function login_user($username, $password) {
    global $pdo;
    
    // Find user by username or email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Gebruiker niet gevonden of account is inactief'];
    }
    
    // Verify password
    if (!verify_password($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Ongeldig wachtwoord'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Create session record
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            expires_at = VALUES(expires_at),
            last_activity = NOW()
    ");
    $stmt->execute([$session_id, $user['id'], $ip_address, $user_agent, $expires_at]);
    
    // Log login action
    log_audit_action('login', 'User logged in successfully');
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logout_user() {
    global $pdo;
    
    // Delete session from database
    $session_id = session_id();
    if ($session_id) {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
    }
    
    // Log logout action if user was logged in
    if (isset($_SESSION['user_id'])) {
        log_audit_action('logout', 'User logged out');
    }
    
    // Clear session
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Require login - redirect to login page if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        // Get protocol and host
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get document root path (where index.php lives)
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Calculate base path - go up to root if we're in a subdirectory
        if (strpos($script_name, '/php/') !== false) {
            // We're in /php subdirectory, login.php is in parent directory
            $base_path = dirname(dirname($script_name));
        } else {
            // We're already at root level
            $base_path = dirname($script_name);
        }
        
        // Remove trailing slash if present and normalize
        if ($base_path === '/' || $base_path === '\\') {
            $base_path = '';
        }
        
        // Build redirect URL - login.php is always at root
        $login_url = $protocol . $host . $base_path . '/login.php';
        $redirect_url = $login_url . '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Require admin access - redirect to dashboard if not admin
 */
function require_admin() {
    require_login();
    
    if (!is_admin()) {
        // Get protocol and host
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get document root path
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Calculate base path
        if (strpos($script_name, '/php/') !== false) {
            $base_path = dirname(dirname($script_name));
        } else {
            $base_path = dirname($script_name);
        }
        
        if ($base_path === '/' || $base_path === '\\') {
            $base_path = '';
        }
        
        // index.php is always at root
        $redirect_url = $protocol . $host . $base_path . '/index.php';
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Require administratie_houder access
 */
function require_administratie_houder() {
    require_login();
    
    if (!is_administratie_houder()) {
        // Get protocol and host
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get document root path
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Calculate base path
        if (strpos($script_name, '/php/') !== false) {
            $base_path = dirname(dirname($script_name));
        } else {
            $base_path = dirname($script_name);
        }
        
        if ($base_path === '/' || $base_path === '\\') {
            $base_path = '';
        }
        
        // index.php is always at root
        $redirect_url = $protocol . $host . $base_path . '/index.php';
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Check if user can access transaction (owner or admin)
 */
function can_access_transaction($transaction_id) {
    global $pdo;
    
    if (is_admin()) {
        return true; // Admin can access all transactions
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if user can access category (owner, system category, or admin)
 */
function can_access_category($category_id) {
    global $pdo;
    
    if (is_admin()) {
        return true; // Admin can access all categories
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND (user_id = ? OR is_system = 1)");
    $stmt->execute([$category_id, $user_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Log audit action
 */
function log_audit_action($action_type, $details = null) {
    global $pdo;
    
    $user_id = get_current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, action_type, action_details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $action_type, $details, $ip_address, $user_agent]);
}

/**
 * Get user's transactions (with access control)
 */
function get_user_transactions($filters = []) {
    global $pdo;
    
    $user_id = get_current_user_id();
    $is_admin = is_admin();
    
    $where = [];
    $params = [];
    
    if (!$is_admin) {
        $where[] = "t.user_id = ?";
        $params[] = $user_id;
    }
    
    // Apply filters
    if (!empty($filters['year'])) {
        $where[] = "YEAR(t.date) = ?";
        $params[] = $filters['year'];
    }
    
    if (!empty($filters['month'])) {
        $where[] = "MONTH(t.date) = ?";
        $params[] = $filters['month'];
    }
    
    if (!empty($filters['type'])) {
        $where[] = "t.type = ?";
        $params[] = $filters['type'];
    }
    
    if (!empty($filters['category_id'])) {
        $where[] = "t.category_id = ?";
        $params[] = $filters['category_id'];
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "
        SELECT t.*, c.name as category_name, u.username, u.full_name as user_full_name
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users u ON t.user_id = u.id
        $where_clause
        ORDER BY t.date DESC, t.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's categories (with access control)
 */
function get_user_categories() {
    global $pdo;
    
    $user_id = get_current_user_id();
    $is_admin = is_admin();
    
    if ($is_admin) {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY is_system DESC, name");
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM categories 
            WHERE user_id = ? OR is_system = 1 
            ORDER BY is_system DESC, name
        ");
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a new user (admin only)
 */
function create_user($user_data) {
    global $pdo;
    
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Alleen administrators kunnen gebruikers aanmaken'];
    }
    
    // Validate required fields
    $required = ['username', 'email', 'password', 'full_name', 'user_type'];
    foreach ($required as $field) {
        if (empty($user_data[$field])) {
            return ['success' => false, 'message' => "Veld '$field' is verplicht"];
        }
    }
    
    // Validate user type
    if (!in_array($user_data['user_type'], ['administrator', 'administratie_houder'])) {
        return ['success' => false, 'message' => 'Ongeldig gebruikers type'];
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$user_data['username']]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Gebruikersnaam bestaat al'];
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$user_data['email']]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'E-mailadres bestaat al'];
    }
    
    // Hash password
    $password_hash = hash_password($user_data['password']);
    $created_by = get_current_user_id();
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, full_name, user_type, is_active, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            $user_data['username'],
            $user_data['email'],
            $password_hash,
            $user_data['full_name'],
            $user_data['user_type'],
            $user_data['is_active'] ?? 1,
            $created_by
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Log action
        log_audit_action('user_create', "Created user: {$user_data['username']} ({$user_data['user_type']})");
        
        return ['success' => true, 'user_id' => $user_id];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout bij aanmaken gebruiker: ' . $e->getMessage()];
    }
}

/**
 * Update user (admin only)
 */
function update_user($user_id, $user_data) {
    global $pdo;
    
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Alleen administrators kunnen gebruikers bijwerken'];
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    if (isset($user_data['full_name'])) {
        $updates[] = "full_name = ?";
        $params[] = $user_data['full_name'];
    }
    
    if (isset($user_data['email'])) {
        // Check if email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$user_data['email'], $user_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'E-mailadres bestaat al'];
        }
        $updates[] = "email = ?";
        $params[] = $user_data['email'];
    }
    
    if (isset($user_data['user_type'])) {
        if (!in_array($user_data['user_type'], ['administrator', 'administratie_houder'])) {
            return ['success' => false, 'message' => 'Ongeldig gebruikers type'];
        }
        $updates[] = "user_type = ?";
        $params[] = $user_data['user_type'];
    }
    
    if (isset($user_data['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = $user_data['is_active'] ? 1 : 0;
    }
    
    if (isset($user_data['password']) && !empty($user_data['password'])) {
        $updates[] = "password_hash = ?";
        $params[] = hash_password($user_data['password']);
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'Geen gegevens om bij te werken'];
    }
    
    $params[] = $user_id;
    
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute($params);
        
        // Log action
        log_audit_action('user_update', "Updated user ID: $user_id");
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout bij bijwerken gebruiker: ' . $e->getMessage()];
    }
}

/**
 * Delete user (admin only)
 */
function delete_user($user_id) {
    global $pdo;
    
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Alleen administrators kunnen gebruikers verwijderen'];
    }
    
    // Cannot delete yourself
    if ($user_id == get_current_user_id()) {
        return ['success' => false, 'message' => 'Je kunt je eigen account niet verwijderen'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Log action
        log_audit_action('user_delete', "Deleted user ID: $user_id");
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout bij verwijderen gebruiker: ' . $e->getMessage()];
    }
}

/**
 * Get all users (admin only)
 */
function get_all_users() {
    global $pdo;
    
    if (!is_admin()) {
        return [];
    }
    
    $stmt = $pdo->query("
        SELECT u.*, creator.username as created_by_username 
        FROM users u
        LEFT JOIN users creator ON u.created_by = creator.id
        ORDER BY u.user_type, u.username
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search users (admin only)
 */
function search_users($query = '', $type_filter = '', $status_filter = '') {
    global $pdo;
    
    if (!is_admin()) {
        return [];
    }
    
    $where = [];
    $params = [];
    
    // Search query
    if (!empty($query)) {
        $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_term = "%$query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Type filter
    if (!empty($type_filter)) {
        $where[] = "u.user_type = ?";
        $params[] = $type_filter;
    }
    
    // Status filter
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $where[] = "u.is_active = 1";
        } elseif ($status_filter === 'inactive') {
            $where[] = "u.is_active = 0";
        }
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "
        SELECT u.*, creator.username as created_by_username
        FROM users u
        LEFT JOIN users creator ON u.created_by = creator.id
        $where_clause
        ORDER BY u.user_type, u.username
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate next invoice number for income transactions
 * Format: YEAR-XXXX (e.g., 2026-0001)
 */
function generate_next_invoice_number($user_id = null) {
    global $pdo;
    
    $year = date('Y');
    $is_admin = is_admin();
    
    // Get the highest invoice number for current year
    if ($is_admin && $user_id === null) {
        // Admin without specific user: get global highest
        $stmt = $pdo->prepare("
            SELECT invoice_number
            FROM transactions
            WHERE type = 'inkomst'
              AND invoice_number LIKE ?
            ORDER BY invoice_number DESC
            LIMIT 1
        ");
        $stmt->execute(["$year-%"]);
    } else {
        // Regular user or admin for specific user
        $uid = $user_id ?? get_current_user_id();
        $stmt = $pdo->prepare("
            SELECT invoice_number
            FROM transactions
            WHERE type = 'inkomst'
              AND invoice_number LIKE ?
              AND user_id = ?
            ORDER BY invoice_number DESC
            LIMIT 1
        ");
        $stmt->execute(["$year-%", $uid]);
    }
    
    $lastInvoice = $stmt->fetchColumn();
    
    if ($lastInvoice && preg_match('/^' . $year . '-(\d+)$/', $lastInvoice, $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    } else {
        $nextNumber = 1;
    }
    
    return sprintf("%s-%04d", $year, $nextNumber);
}

/**
 * Validate session on each request
 */
function validate_session() {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    $session_id = session_id();
    $stmt = $pdo->prepare("
        SELECT us.*, u.is_active 
        FROM user_sessions us
        JOIN users u ON us.user_id = u.id
        WHERE us.id = ? AND us.expires_at > NOW() AND u.is_active = 1
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        logout_user();
        return false;
    }
    
    // Update last activity
    $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$session_id]);
    
    return true;
}

// Validate session on each request
validate_session();