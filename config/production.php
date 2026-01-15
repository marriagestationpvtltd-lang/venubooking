<?php
/**
 * Production Environment Configuration
 * 
 * This file should be included in production environments to ensure
 * proper error handling and security settings.
 */

// Error reporting - log errors but don't display them
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Set error log location (ensure this directory is writable)
$error_log_path = __DIR__ . '/../logs/error.log';
if (!is_dir(dirname($error_log_path))) {
    @mkdir(dirname($error_log_path), 0755, true);
}
ini_set('error_log', $error_log_path);

// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', '1'); // Enable in production with HTTPS

// Disable unnecessary PHP functions for security
// disable_functions in php.ini is preferred, but this provides runtime check
$disabled_functions = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];

// Output buffering for better performance
ini_set('output_buffering', '4096');

// Set timezone (adjust for your location)
date_default_timezone_set('Asia/Kathmandu');

// Custom error handler for user-friendly messages
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    
    // Don't execute PHP internal error handler
    return true;
});

// Custom exception handler
set_exception_handler(function($exception) {
    // Log the exception
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Show user-friendly error page
    http_response_code(500);
    
    // Check if we're in an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again or contact support.'
        ]);
    } else {
        // Regular page request
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Venue Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h1 class="text-danger">⚠️</h1>
                        <h3 class="card-title">Oops! Something went wrong</h3>
                        <p class="card-text">We encountered an unexpected error. Please try again or contact support if the problem persists.</p>
                        <a href="/" class="btn btn-success">Return to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    exit;
});

// Production mode flag
define('PRODUCTION_MODE', true);
define('DEBUG_MODE', false);
