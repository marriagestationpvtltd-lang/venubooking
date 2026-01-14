<?php
/**
 * Configuration File
 * Loads environment variables and defines application constants
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    session_start();
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load .env file
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/../.env.example';
}
loadEnv($envPath);

// Helper function to get environment variable
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Define application constants
define('APP_NAME', env('APP_NAME', 'Venue Booking System'));
define('APP_URL', env('APP_URL', 'http://localhost'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kathmandu'));

// Database constants
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'venubooking'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// File upload constants
define('MAX_UPLOAD_SIZE', env('MAX_UPLOAD_SIZE', 5242880)); // 5MB
define('ALLOWED_IMAGE_TYPES', env('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif'));
define('UPLOAD_PATH_VENUES', env('UPLOAD_PATH_VENUES', 'uploads/venues/'));
define('UPLOAD_PATH_HALLS', env('UPLOAD_PATH_HALLS', 'uploads/halls/'));
define('UPLOAD_PATH_MENUS', env('UPLOAD_PATH_MENUS', 'uploads/menus/'));

// Currency and tax constants
define('CURRENCY', env('CURRENCY', 'NPR'));
define('CURRENCY_SYMBOL', env('CURRENCY_SYMBOL', 'Rs. '));
define('TAX_RATE', env('TAX_RATE', 13));

// Booking constants
define('ADVANCE_PAYMENT_PERCENTAGE', env('ADVANCE_PAYMENT_PERCENTAGE', 30));
define('BOOKING_BUFFER_DAYS', env('BOOKING_BUFFER_DAYS', 1));
define('MAX_ADVANCE_BOOKING_DAYS', env('MAX_ADVANCE_BOOKING_DAYS', 365));

// Security constants
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 7200));
define('CSRF_TOKEN_NAME', env('CSRF_TOKEN_NAME', 'csrf_token'));

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
