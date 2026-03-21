<?php
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($design_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch design with its parent service (direct design: service_id set)
$stmt = $db->prepare(
    "SELECT d.*, s.id AS service_id
     FROM service_designs d
     JOIN additional_services s ON s.id = d.service_id
     WHERE d.id = ? AND d.service_id IS NOT NULL"
);
$stmt->execute([$design_id]);
$design = $stmt->fetch();

if (!$design) {
    header('Location: index.php');
    exit;
}

$service_id = $design['service_id'];

// Delete photo file if exists
if (!empty($design['photo'])) {
    deleteUploadedFile($design['photo']);
}

// Delete design record
$delete_stmt = $db->prepare("DELETE FROM service_designs WHERE id = ?");
$delete_stmt->execute([$design_id]);

logActivity($current_user['id'], 'Deleted design', 'service_designs', $design_id, "Deleted design '{$design['name']}'");

$_SESSION['success_message'] = 'Design deleted successfully.';
header('Location: view.php?id=' . $service_id);
exit;
