<?php
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$sub_service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sub_service_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT ss.*, s.name AS service_name, ss.service_id FROM service_sub_services ss JOIN additional_services s ON s.id = ss.service_id WHERE ss.id = ?");
$stmt->execute([$sub_service_id]);
$sub_service = $stmt->fetch();

if (!$sub_service) {
    header('Location: index.php');
    exit;
}

$service_id = $sub_service['service_id'];

try {
    $db->prepare("DELETE FROM service_sub_services WHERE id = ?")->execute([$sub_service_id]);
    logActivity($current_user['id'], 'Deleted sub-service', 'service_sub_services', $sub_service_id, "Deleted sub-service '{$sub_service['name']}'");
    $_SESSION['success_message'] = 'Sub-service deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error deleting sub-service: ' . $e->getMessage();
}

header('Location: view.php?id=' . $service_id);
exit;
