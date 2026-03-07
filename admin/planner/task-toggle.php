<?php
/**
 * AJAX endpoint: toggle a plan task between completed / pending.
 * Returns JSON: {"success": true/false, "new_status": "completed"|"pending"}
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$task_id = intval($_POST['task_id'] ?? 0);
$plan_id = intval($_POST['plan_id'] ?? 0);

if ($task_id <= 0 || $plan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$db = getDB();

try {
    $stmt = $db->prepare("SELECT status FROM plan_tasks WHERE id = ? AND plan_id = ?");
    $stmt->execute([$task_id, $plan_id]);
    $task = $stmt->fetch();

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }

    $new_status = $task['status'] === 'completed' ? 'pending' : 'completed';

    $stmt = $db->prepare("UPDATE plan_tasks SET status = ? WHERE id = ? AND plan_id = ?");
    $stmt->execute([$new_status, $task_id, $plan_id]);

    echo json_encode(['success' => true, 'new_status' => $new_status]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
exit;
