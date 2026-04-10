<?php
/**
 * api/call/initiate.php
 *
 * Called by the customer browser to start a new call session.
 * Looks up the customer by phone number to populate caller-ID details
 * (name, account_type, latest booking).  Returns the session token that
 * the caller must include in every subsequent signaling request.
 *
 * POST params:
 *   caller_name  (string)  – name entered by guest / pre-filled for known customer
 *   caller_phone (string)  – phone number used for customer lookup
 *   offer_sdp    (string)  – WebRTC SDP offer created by the caller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$caller_name  = trim($_POST['caller_name']  ?? '');
$caller_phone = trim($_POST['caller_phone'] ?? '');
$offer_sdp    = trim($_POST['offer_sdp']    ?? '');

if (empty($caller_phone) || empty($offer_sdp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'caller_phone and offer_sdp are required']);
    exit;
}

if (empty($caller_name)) {
    $caller_name = 'Guest';
}

try {
    $db = getDB();

    // ── Lookup customer by phone ────────────────────────────────────────────
    $customer_id       = null;
    $account_type      = 'free';
    $last_booking_num  = null;
    $last_package_name = null;

    $stmt = $db->prepare(
        "SELECT c.id, c.full_name, c.account_type,
                b.booking_number,
                COALESCE(sp.name, h.name, b.custom_hall_name) AS package_name
         FROM customers c
         LEFT JOIN bookings b
               ON b.customer_id = c.id
         LEFT JOIN halls h ON b.hall_id = h.id
         LEFT JOIN booking_packages bp ON bp.booking_id = b.id
         LEFT JOIN service_packages sp ON sp.id = bp.package_id
         WHERE c.phone = ?
         ORDER BY b.created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$caller_phone]);
    $row = $stmt->fetch();

    if ($row) {
        $customer_id       = (int)$row['id'];
        $caller_name       = $row['full_name'];   // always use the DB name
        $account_type      = $row['account_type'] ?? 'free';
        $last_booking_num  = $row['booking_number'];
        $last_package_name = $row['package_name'];
    }

    // ── Generate a cryptographically random session token ──────────────────
    $session_token = bin2hex(random_bytes(32));

    // ── Insert call session ─────────────────────────────────────────────────
    $ins = $db->prepare(
        "INSERT INTO call_sessions
             (session_token, customer_id, caller_name, caller_phone, account_type,
              last_booking_number, last_package_name, offer_sdp, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $ins->execute([
        $session_token,
        $customer_id,
        $caller_name,
        $caller_phone,
        $account_type,
        $last_booking_num,
        $last_package_name,
        $offer_sdp,
    ]);

    $call_id = (int)$db->lastInsertId();

    echo json_encode([
        'success'       => true,
        'call_id'       => $call_id,
        'session_token' => $session_token,
        'caller_name'   => $caller_name,
        'account_type'  => $account_type,
    ]);
} catch (\Throwable $e) {
    error_log('call/initiate.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
