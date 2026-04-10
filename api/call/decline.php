<?php
/**
 * api/call/decline.php
 *
 * Admin declines (or ends) a call session.
 * Also used by customer to cancel a pending call they initiated.
 *
 * POST params:
 *   call_id (int)     – required; ID from call_sessions
 *   token   (string)  – optional; caller token (allows unauthenticated cancel)
 *   reason  (string)  – optional; 'declined' | 'ended' (default: 'declined')
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

$call_id = isset($_POST['call_id']) ? (int)$_POST['call_id'] : 0;
$token   = trim($_POST['token']  ?? '');
$reason  = in_array($_POST['reason'] ?? '', ['ended', 'declined']) ? $_POST['reason'] : 'declined';

if ($call_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'call_id is required']);
    exit;
}

try {
    $db = getDB();

    // Authorise: either admin session OR correct caller token
    $is_admin = isLoggedIn();
    $is_owner = false;

    if (!$is_admin && $token !== '') {
        $chk = $db->prepare("SELECT id FROM call_sessions WHERE id = ? AND session_token = ?");
        $chk->execute([$call_id, $token]);
        $is_owner = (bool)$chk->fetch();
    }

    if (!$is_admin && !$is_owner) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE call_sessions SET status = ?
         WHERE id = ? AND status IN ('pending', 'active')"
    );
    $stmt->execute([$reason, $call_id]);

    if ($is_admin) {
        $current_user = getCurrentUser();
        logActivity($current_user['id'], 'Call ' . $reason, 'call_sessions', $call_id);
    }

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    error_log('call/decline.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
