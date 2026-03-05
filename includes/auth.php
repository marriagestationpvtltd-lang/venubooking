<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Maximum number of failed login attempts before lockout
 */
define('LOGIN_MAX_ATTEMPTS', 5);

/**
 * Lockout duration in seconds (15 minutes)
 */
define('LOGIN_LOCKOUT_SECONDS', 900);

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_user_id']);
}

/**
 * Check whether the current IP is locked out due to too many failed logins.
 * Returns true when locked out, false otherwise.
 */
function isLoginLockedOut() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // NOTE: If the app is behind a trusted reverse proxy, replace REMOTE_ADDR
    // with the validated client IP from HTTP_X_FORWARDED_FOR or HTTP_X_REAL_IP.
    $key_attempts = 'login_attempts_' . md5($ip);
    $key_time     = 'login_lockout_until_' . md5($ip);

    if (isset($_SESSION[$key_time]) && time() < $_SESSION[$key_time]) {
        return true;
    }

    // Clear expired lockout
    if (isset($_SESSION[$key_time]) && time() >= $_SESSION[$key_time]) {
        unset($_SESSION[$key_time], $_SESSION[$key_attempts]);
    }

    return false;
}

/**
 * Record a failed login attempt and lock out the IP if the threshold is reached.
 */
function recordFailedLogin() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key_attempts = 'login_attempts_' . md5($ip);
    $key_time     = 'login_lockout_until_' . md5($ip);

    $_SESSION[$key_attempts] = ($_SESSION[$key_attempts] ?? 0) + 1;

    if ($_SESSION[$key_attempts] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION[$key_time] = time() + LOGIN_LOCKOUT_SECONDS;
        error_log("Login lockout triggered for IP: $ip after " . $_SESSION[$key_attempts] . " failed attempts.");
    }
}

/**
 * Clear the failed login counter for the current IP (called on successful login).
 */
function clearLoginAttempts() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key_attempts = 'login_attempts_' . md5($ip);
    $key_time     = 'login_lockout_until_' . md5($ip);
    unset($_SESSION[$key_attempts], $_SESSION[$key_time]);
}

/**
 * Login user
 */
function login($username, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Clear failed login counter
        clearLoginAttempts();

        // Set session variables
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_full_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role'];
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Log activity
        logActivity($user['id'], 'User logged in', 'users', $user['id']);
        
        return true;
    }

    // Record failed attempt
    recordFailedLogin();
    
    return false;
}

/**
 * Logout user
 */
function logout() {
    if (isset($_SESSION['admin_user_id'])) {
        logActivity($_SESSION['admin_user_id'], 'User logged out', 'users', $_SESSION['admin_user_id']);
    }
    
    session_unset();
    session_destroy();
}

/**
 * Require login
 */
function requireLogin($redirect = null) {
    if (!isLoggedIn()) {
        // Default redirect to admin login page with BASE_URL support
        if ($redirect === null) {
            $redirect = BASE_URL . '/admin/login.php';
        }
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_user_id'],
        'username' => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_full_name'],
        'role' => $_SESSION['admin_role']
    ];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}
