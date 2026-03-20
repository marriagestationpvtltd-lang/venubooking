<?php
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($design_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT d.*, ss.service_id FROM service_designs d
     JOIN service_sub_services ss ON ss.id = d.sub_service_id
     WHERE d.id = ?"
);
$stmt->execute([$design_id]);
$design = $stmt->fetch();

if (!$design) {
    header('Location: index.php');
    exit;
}

$service_id = $design['service_id'];

try {
    // Delete photo file if exists
    if (!empty($design['photo'])) {
        deleteUploadedFile($design['photo']);
    }
    $db->prepare("DELETE FROM service_designs WHERE id = ?")->execute([$design_id]);
    logActivity($current_user['id'], 'Deleted design', 'service_designs', $design_id, "Deleted design '{$design['name']}'");
    $_SESSION['success_message'] = 'Design deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error deleting design: ' . $e->getMessage();
}

header('Location: view.php?id=' . $service_id);
exit;
