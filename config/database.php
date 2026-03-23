<?php
/**
 * Database Configuration
 */

// Load production settings first (suppresses errors, sets secure handlers)
require_once __DIR__ . '/production.php';

// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'venubooking');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Base URL configuration
// If APP_URL is set in .env, use it directly (recommended on shared hosting to
// prevent "Image unavailable" caused by incorrect dynamic URL detection).
if (!empty($_ENV['APP_URL'])) {
    $baseUrl = rtrim($_ENV['APP_URL'], '/');
} else {
    // Dynamically detect the application root from the current request path.
    // This works on most Apache/mod_php setups but can fail on some
    // Nginx + PHP-FPM or reverse-proxy configurations.
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
    $scriptDir = dirname($scriptPath);

    // Remove /admin or /api subdirectories from the path to get the application root.
    // This handles cases where script is in /admin/dashboard.php or /admin/venues/index.php.
    $basePath = preg_replace('#/(admin|api)(/.*)?$#', '', $scriptDir);
    $baseUrl = rtrim($basePath, '/');
}

define('BASE_URL', $baseUrl);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);   // Reject unrecognized session IDs
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800); // Server-side 30-minute GC lifetime
    ini_set('session.cookie_lifetime', 0);   // Browser-session cookie
    session_start();
}
