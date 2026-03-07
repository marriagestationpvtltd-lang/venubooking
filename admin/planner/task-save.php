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

$plan_id   = intval($_POST['plan_id'] ?? 0);
$task_id   = intval($_POST['task_id'] ?? 0);
$task_name = trim($_POST['task_name'] ?? '');
$category  = trim($_POST['category']  ?? 'General');
$due_date  = trim($_POST['due_date']  ?? '') ?: null;
$status    = in_array($_POST['status'] ?? '', ['pending', 'in_progress', 'completed'])
             ? $_POST['status'] : 'pending';
$priority  = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'])
             ? $_POST['priority'] : 'medium';
$estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
$actual_cost    = floatval($_POST['actual_cost']    ?? 0);
$description    = trim($_POST['description'] ?? '');

if ($plan_id <= 0) {
    $_SESSION['error_message'] = 'Invalid plan.';
    header('Location: index.php');
    exit;
}

if (empty($task_name)) {
    $_SESSION['error_message'] = 'Task name is required.';
    header("Location: view.php?id=$plan_id");
    exit;
}

// Verify plan exists
$stmt = $db->prepare("SELECT id FROM event_plans WHERE id = ?");
$stmt->execute([$plan_id]);
if (!$stmt->fetch()) {
    $_SESSION['error_message'] = 'Plan not found.';
    header('Location: index.php');
    exit;
}

try {
    if ($task_id > 0) {
        // Update existing task — ensure it belongs to this plan
        $stmt = $db->prepare(
            "UPDATE plan_tasks SET task_name=?, category=?, due_date=?, status=?, priority=?,
             estimated_cost=?, actual_cost=?, description=?
             WHERE id=? AND plan_id=?"
        );
        $stmt->execute([
            $task_name, $category, $due_date, $status, $priority,
            $estimated_cost, $actual_cost, $description,
            $task_id, $plan_id
        ]);
        logActivity($current_user['id'], 'Updated plan task', 'plan_tasks', $task_id, "Updated task: $task_name");
        $_SESSION['success_message'] = 'Task updated successfully.';
    } else {
        // Insert new task
        $stmt = $db->prepare(
            "INSERT INTO plan_tasks (plan_id, task_name, category, due_date, status, priority,
             estimated_cost, actual_cost, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $plan_id, $task_name, $category, $due_date, $status, $priority,
            $estimated_cost, $actual_cost, $description
        ]);
        $new_id = $db->lastInsertId();
        logActivity($current_user['id'], 'Added plan task', 'plan_tasks', $new_id, "Added task: $task_name");
        $_SESSION['success_message'] = 'Task added successfully.';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Failed to save task. Please try again.';
}

header("Location: view.php?id=$plan_id");
exit;
