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
 * Admin session idle timeout in seconds (30 minutes)
 */
define('SESSION_IDLE_TIMEOUT', 1800);

/**
 * Minimum password length for admin accounts
 */
define('PASSWORD_MIN_LENGTH', 8);

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_user_id']);
}

/**
 * Get the client IP address.
 * NOTE: If the application runs behind a trusted reverse proxy (e.g. Cloudflare,
 * AWS ALB, nginx), consider replacing this with validated X-Forwarded-For logic
 * after whitelisting your proxy IPs. Never trust X-Forwarded-For unconditionally
 * as it can be spoofed by clients.
 *
 * @return string IP address
 */
function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Check whether the current IP is locked out due to too many failed logins.
 * Uses the database login_attempts table for persistent tracking across
 * server restarts and multiple application instances.
 * Returns true when locked out, false otherwise.
 */
function isLoginLockedOut() {
    $ip = getClientIP();
    // Calculate cutoff timestamp in PHP for index-efficient WHERE clause
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SECONDS);

    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > ? AND success = 0"
        );
        $stmt->execute([$ip, $cutoff]);
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0) >= LOGIN_MAX_ATTEMPTS;
    } catch (\Throwable $e) {
        // If the table doesn't exist yet, fall back to session-based tracking
        error_log('login_attempts DB check failed: ' . $e->getMessage());
        $key_attempts = 'login_attempts_' . md5($ip);
        $key_time     = 'login_lockout_until_' . md5($ip);
        if (isset($_SESSION[$key_time]) && time() < $_SESSION[$key_time]) {
            return true;
        }
        if (isset($_SESSION[$key_time]) && time() >= $_SESSION[$key_time]) {
            unset($_SESSION[$key_time], $_SESSION[$key_attempts]);
        }
        return false;
    }
}

/**
 * Record a failed login attempt in the database.
 * Also locks out the IP in the session cache as a fast-path fallback.
 */
function recordFailedLogin() {
    $ip = getClientIP();
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SECONDS);

    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO login_attempts (ip_address, success, attempted_at) VALUES (?, 0, NOW())"
        );
        $stmt->execute([$ip]);

        // Check current count to log when the threshold is crossed
        $count_stmt = $db->prepare(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > ? AND success = 0"
        );
        $count_stmt->execute([$ip, $cutoff]);
        $row = $count_stmt->fetch();
        $attempts = (int)($row['cnt'] ?? 0);

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            error_log("Login lockout triggered for IP: $ip after $attempts failed attempts.");
        }
    } catch (\Throwable $e) {
        // Fall back to session-based tracking if DB unavailable
        error_log('recordFailedLogin DB insert failed: ' . $e->getMessage());
        $key_attempts = 'login_attempts_' . md5($ip);
        $key_time     = 'login_lockout_until_' . md5($ip);
        $_SESSION[$key_attempts] = ($_SESSION[$key_attempts] ?? 0) + 1;
        if ($_SESSION[$key_attempts] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION[$key_time] = time() + LOGIN_LOCKOUT_SECONDS;
        }
    }
}

/**
 * Clear the failed login counter for the current IP (called on successful login).
 */
function clearLoginAttempts() {
    $ip = getClientIP();

    try {
        $db = getDB();
        // Record the successful attempt
        $stmt = $db->prepare(
            "INSERT INTO login_attempts (ip_address, success, attempted_at) VALUES (?, 1, NOW())"
        );
        $stmt->execute([$ip]);
    } catch (\Throwable $e) {
        error_log('clearLoginAttempts DB insert failed: ' . $e->getMessage());
    }

    // Always clear session fallback data
    $key_attempts = 'login_attempts_' . md5($ip);
    $key_time     = 'login_lockout_until_' . md5($ip);
    unset($_SESSION[$key_attempts], $_SESSION[$key_time]);
}

/**
 * Enforce idle session timeout for the admin panel.
 * Call this before any admin page loads. Destroys the session and
 * redirects to login when the user has been inactive for SESSION_IDLE_TIMEOUT seconds.
 */
function enforceSessionTimeout() {
    if (!isLoggedIn()) {
        return;
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        // Session expired — destroy and redirect
        $redirect = BASE_URL . '/admin/login.php?timeout=1';
        session_unset();
        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }
    // Update last activity timestamp on every request
    $_SESSION['last_activity'] = $now;
}

/**
 * Validate password strength.
 * Returns ['valid' => bool, 'error' => string].
 * Requirements: min PASSWORD_MIN_LENGTH chars, at least one uppercase letter,
 * one lowercase letter, one digit, and one special character.
 */
function validatePasswordStrength($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['valid' => false, 'error' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter.'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter.'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one number.'];
    }
    if (!preg_match('/[\W_]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one special character (e.g. @, #, !, $).'];
    }
    return ['valid' => true, 'error' => ''];
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
        // Initialise idle-timeout tracker
        $_SESSION['last_activity'] = time();
        
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

    // Enforce idle session timeout for authenticated sessions
    enforceSessionTimeout();
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
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
