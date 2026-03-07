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

$plan_id = intval($_POST['id'] ?? 0);
if ($plan_id <= 0) {
    $_SESSION['error_message'] = 'Invalid plan ID.';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $db->prepare("SELECT title FROM event_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $_SESSION['error_message'] = 'Plan not found.';
        header('Location: index.php');
        exit;
    }

    // plan_tasks deleted via ON DELETE CASCADE
    $stmt = $db->prepare("DELETE FROM event_plans WHERE id = ?");
    $stmt->execute([$plan_id]);

    logActivity($current_user['id'], 'Deleted event plan', 'event_plans', $plan_id, "Deleted plan: {$plan['title']}");

    $_SESSION['success_message'] = 'Plan deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Failed to delete plan. Please try again.';
}

header('Location: index.php');
exit;
