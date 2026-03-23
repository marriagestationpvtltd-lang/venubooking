<?php
/**
 * Session Keep-Alive Endpoint
 *
 * Called periodically by the client (e.g. during long file uploads) to
 * refresh the admin session so the idle-timeout counter does not expire
 * while the browser is waiting for a large upload to complete.
 *
 * Returns JSON: { "alive": true } when the session is valid,
 *               { "alive": false } when the user is not authenticated.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Only accept GET or POST requests (ignore others silently)
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['alive' => false]);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['alive' => false]);
    exit;
}

// Refresh the idle-timeout counter and release the session lock immediately
// so other concurrent requests are not delayed.
$_SESSION['last_activity'] = time();
session_write_close();

echo json_encode(['alive' => true]);
