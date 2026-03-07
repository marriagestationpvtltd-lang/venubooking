<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$current_user = getCurrentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    header('Location: index.php');
    exit;
}

$task_id = intval($_POST['task_id'] ?? 0);
$plan_id = intval($_POST['plan_id'] ?? 0);

if ($task_id <= 0 || $plan_id <= 0) {
    $_SESSION['error_message'] = 'Invalid task.';
    header("Location: view.php?id=$plan_id");
    exit;
}

try {
    // Ensure the task belongs to the given plan
    $stmt = $db->prepare("SELECT task_name FROM plan_tasks WHERE id = ? AND plan_id = ?");
    $stmt->execute([$task_id, $plan_id]);
    $task = $stmt->fetch();

    if (!$task) {
        $_SESSION['error_message'] = 'Task not found.';
        header("Location: view.php?id=$plan_id");
        exit;
    }

    $stmt = $db->prepare("DELETE FROM plan_tasks WHERE id = ? AND plan_id = ?");
    $stmt->execute([$task_id, $plan_id]);

    logActivity($current_user['id'], 'Deleted plan task', 'plan_tasks', $task_id, "Deleted task: {$task['task_name']}");

    $_SESSION['success_message'] = 'Task deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Failed to delete task. Please try again.';
}

header("Location: view.php?id=$plan_id");
exit;
