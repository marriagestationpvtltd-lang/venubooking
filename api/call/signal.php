<?php
/**
 * api/call/signal.php
 *
 * Append ICE candidates for WebRTC signaling.
 * Both caller (no auth, uses token) and admin (session auth) post here.
 *
 * POST params:
 *   call_id   (int)    – call_sessions.id
 *   token     (string) – caller session token (for unauthenticated callers)
 *   role      (string) – 'caller' | 'admin'
 *   candidate (string) – JSON-encoded RTCIceCandidate object
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$call_id   = isset($_POST['call_id'])   ? (int)$_POST['call_id'] : 0;
$token     = trim($_POST['token']       ?? '');
$role      = trim($_POST['role']        ?? '');
$candidate = trim($_POST['candidate']   ?? '');

if ($call_id <= 0 || !in_array($role, ['caller', 'admin']) || empty($candidate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'call_id, role (caller|admin), and candidate are required']);
    exit;
}

try {
    $db = getDB();

    // Authorise
    if ($role === 'admin') {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    } else {
        // Caller must supply valid token
        $chk = $db->prepare("SELECT id FROM call_sessions WHERE id = ? AND session_token = ?");
        $chk->execute([$call_id, $token]);
        if (!$chk->fetch()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid session token']);
            exit;
        }
    }

    // Determine which ICE column to update – use explicit conditionals, never interpolate
    if ($role === 'admin') {
        $sel = $db->prepare("SELECT admin_ice FROM call_sessions WHERE id = ?");
    } else {
        $sel = $db->prepare("SELECT caller_ice FROM call_sessions WHERE id = ?");
    }
    $sel->execute([$call_id]);
    $row = $sel->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Call session not found']);
        exit;
    }

    $colKey   = ($role === 'admin') ? 'admin_ice' : 'caller_ice';
    $existing = $row[$colKey] ? json_decode($row[$colKey], true) : [];
    if (!is_array($existing)) {
        $existing = [];
    }

    // Validate candidate is valid JSON before appending
    $decoded = json_decode($candidate, true);
    if ($decoded === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid candidate JSON']);
        exit;
    }
    $existing[] = $decoded;

    if ($role === 'admin') {
        $upd = $db->prepare("UPDATE call_sessions SET admin_ice = ? WHERE id = ?");
    } else {
        $upd = $db->prepare("UPDATE call_sessions SET caller_ice = ? WHERE id = ?");
    }
    $upd->execute([json_encode($existing), $call_id]);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    error_log('call/signal.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
