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
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
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
    // Dynamically detect the application root from the current request.
    //
    // Use REQUEST_URI (the browser-visible path) rather than SCRIPT_NAME
    // (the server filesystem path).  When a custom domain is mapped to a
    // sub-directory via cPanel/Plesk "add-on domain", SCRIPT_NAME contains
    // the physical subdirectory prefix (e.g. /jsv8/transfer.php) while the
    // browser only ever sees /transfer.php.  Using REQUEST_URI therefore
    // produces a correct base path regardless of how the domain is mapped.
    // Falls back to SCRIPT_NAME when REQUEST_URI is unavailable (CLI, etc.).
    $requestPath = isset($_SERVER['REQUEST_URI'])
        ? (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/')  // strip query/fragment safely
        : ($_SERVER['SCRIPT_NAME'] ?? '/');
    $requestDir  = dirname($requestPath);

    // Remove /admin or /api subdirectories from the path to get the application root.
    // This handles cases where script is in /admin/dashboard.php or /admin/venues/index.php.
    $basePath = preg_replace('#/(admin|api)(/.*)?$#', '', $requestDir);
    $basePath = rtrim($basePath, '/');

    // Build a fully-qualified base URL (scheme + host + path) so that share
    // links sent to external recipients are clickable absolute URLs.
    // When HTTP_HOST is unavailable (CLI or unit-test runners), fall back to
    // a root-relative path to preserve the existing behaviour.
    // Sanitize HTTP_HOST to contain only valid hostname characters (letters,
    // digits, hyphens, dots, and an optional port) to guard against
    // Host-header injection attacks.
    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $rawHttpHost = $_SERVER['HTTP_HOST'] ?? '';
    $httpHost    = preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*(?::\d+)?$/', $rawHttpHost)
                   ? $rawHttpHost : '';
    $baseUrl     = !empty($httpHost) ? $scheme . '://' . $httpHost . $basePath : $basePath;
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
