<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$package_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($package_id <= 0) {
    $_SESSION['error_message'] = 'Invalid package ID.';
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM service_packages WHERE id = ?");
$stmt->execute([$package_id]);
$package = $stmt->fetch();

if (!$package) {
    $_SESSION['error_message'] = 'Package not found.';
    header('Location: index.php');
    exit;
}

try {
    // Cascade delete handled by FK ON DELETE CASCADE for features
    $db->prepare("DELETE FROM service_packages WHERE id = ?")->execute([$package_id]);

    logActivity($current_user['id'], 'Deleted service package', 'service_packages', $package_id, "Deleted package: {$package['name']}");

    $_SESSION['success_message'] = 'Package deleted successfully!';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
