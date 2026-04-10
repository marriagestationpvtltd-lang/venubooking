<?php
/**
 * api/call/poll.php
 *
 * Unified polling endpoint used by BOTH the customer browser and the admin panel.
 *
 * Customer (unauthenticated) polls with ?token=<session_token>
 *   – returns current call status + admin's SDP answer + admin ICE candidates
 *
 * Admin (authenticated) polls with no token
 *   – returns list of pending / active calls with full caller details
 *   – also accepts ?call_id=N to fetch a single call's ICE updates
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    // ── Customer mode: poll by session token ───────────────────────────────
    $token = trim($_GET['token'] ?? '');
    if ($token !== '') {
        $stmt = $db->prepare(
            "SELECT id, status, answer_sdp, admin_ice
             FROM call_sessions
             WHERE session_token = ?
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Call session not found']);
            exit;
        }

        echo json_encode([
            'success'    => true,
            'call_id'    => (int)$row['id'],
            'status'     => $row['status'],
            'answer_sdp' => $row['answer_sdp'],
            'admin_ice'  => $row['admin_ice'] ? json_decode($row['admin_ice'], true) : [],
        ]);
        exit;
    }

    // ── Admin mode: requires login ──────────────────────────────────────────
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Optional: fetch a single call for ICE update
    $call_id = isset($_GET['call_id']) ? (int)$_GET['call_id'] : 0;
    if ($call_id > 0) {
        $stmt = $db->prepare(
            "SELECT id, status, offer_sdp, caller_ice, answer_sdp, admin_ice
             FROM call_sessions
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$call_id]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Call not found']);
            exit;
        }

        echo json_encode([
            'success'    => true,
            'call_id'    => (int)$row['id'],
            'status'     => $row['status'],
            'offer_sdp'  => $row['offer_sdp'],
            'caller_ice' => $row['caller_ice'] ? json_decode($row['caller_ice'], true) : [],
            'answer_sdp' => $row['answer_sdp'],
            'admin_ice'  => $row['admin_ice'] ? json_decode($row['admin_ice'], true) : [],
        ]);
        exit;
    }

    // ── Fetch all pending + active calls for admin dashboard ───────────────
    // Auto-expire calls older than 5 minutes that are still pending (missed)
    $db->exec(
        "UPDATE call_sessions
         SET status = 'missed'
         WHERE status = 'pending'
           AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );

    $stmt = $db->query(
        "SELECT cs.id, cs.session_token, cs.caller_name, cs.caller_phone,
                cs.account_type, cs.last_booking_number, cs.last_package_name,
                cs.status, cs.accepted_by, cs.created_at,
                u.full_name AS accepted_by_name
         FROM call_sessions cs
         LEFT JOIN users u ON u.id = cs.accepted_by
         WHERE cs.status IN ('pending', 'active')
         ORDER BY cs.created_at ASC"
    );
    $calls = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Format for JSON
    $result = [];
    foreach ($calls as $c) {
        $result[] = [
            'call_id'             => (int)$c['id'],
            'caller_name'         => $c['caller_name'],
            'caller_phone'        => $c['caller_phone'],
            'account_type'        => $c['account_type'],
            'last_booking_number' => $c['last_booking_number'],
            'last_package_name'   => $c['last_package_name'],
            'status'              => $c['status'],
            'accepted_by'         => $c['accepted_by'] ? (int)$c['accepted_by'] : null,
            'accepted_by_name'    => $c['accepted_by_name'],
            'waiting_seconds'     => max(0, time() - strtotime($c['created_at'])),
        ];
    }

    echo json_encode(['success' => true, 'calls' => $result]);
} catch (\Throwable $e) {
    error_log('call/poll.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
