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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    header('Location: index.php');
    exit;
}

$vendor_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($vendor_id <= 0) {
    $_SESSION['error_message'] = 'Invalid vendor ID.';
    header('Location: index.php');
    exit;
}

try {
    // Prevent deletion if vendor has assignments
    $stmt = $db->prepare("SELECT COUNT(*) FROM booking_vendor_assignments WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $assignment_count = (int)$stmt->fetchColumn();

    if ($assignment_count > 0) {
        $_SESSION['error_message'] = "Cannot delete this vendor: they have {$assignment_count} booking assignment(s). Remove the assignments first.";
        header('Location: index.php');
        exit;
    }

    $stmt = $db->prepare("SELECT name FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        $_SESSION['error_message'] = 'Vendor not found.';
        header('Location: index.php');
        exit;
    }

    $stmt = $db->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);

    logActivity($current_user['id'], 'Deleted vendor', 'vendors', $vendor_id, "Deleted vendor: {$vendor['name']}");

    $_SESSION['success_message'] = 'Vendor deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Failed to delete vendor. Please try again.';
}

header('Location: index.php');
exit;
