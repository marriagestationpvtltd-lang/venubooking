<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$service_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($service_id <= 0) {
    $_SESSION['error_message'] = 'Invalid service ID.';
    header('Location: index.php');
    exit;
}

// Fetch service details
$stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    $_SESSION['error_message'] = 'Service not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if service is used in any bookings
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_services WHERE service_id = ?");
    $check_stmt->execute([$service_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete service. It is associated with existing bookings. You can set it to inactive instead.';
        header('Location: index.php');
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM additional_services WHERE id = ?");
    if ($stmt->execute([$service_id])) {
        // Log activity
        logActivity($current_user['id'], 'Deleted service', 'additional_services', $service_id, "Deleted service: {$service['name']}");
        
        $_SESSION['success_message'] = 'Service deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete service. Please try again.';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
