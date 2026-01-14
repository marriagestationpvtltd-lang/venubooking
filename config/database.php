<?php
/**
 * Database Configuration
 */

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

// Application configuration
define('CURRENCY', $_ENV['CURRENCY'] ?? 'NPR');
define('TAX_RATE', $_ENV['TAX_RATE'] ?? 13);

// Base URL configuration
// Calculate the base URL by finding the application root directory
$scriptPath = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($scriptPath);

// Remove /admin or any subdirectory from the path to get the application root
// This handles cases where script is in /admin/dashboard.php or /admin/venues/index.php
$basePath = preg_replace('#/admin(/.*)?$#', '', $scriptDir);
$basePath = preg_replace('#/api(/.*)?$#', '', $basePath);

define('BASE_URL', rtrim($basePath, '/'));
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}
