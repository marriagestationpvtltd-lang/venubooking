<?php
require_once __DIR__ . '/../includes/header.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$venue_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($venue_id <= 0) {
    $_SESSION['error_message'] = 'Invalid venue ID.';
    header('Location: index.php');
    exit;
}

// Fetch venue details
$stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    $_SESSION['error_message'] = 'Venue not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if venue has halls
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM halls WHERE venue_id = ?");
    $check_stmt->execute([$venue_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete venue. It has ' . $result['count'] . ' associated hall(s). Please delete the halls first.';
        header('Location: index.php');
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM venues WHERE id = ?");
    if ($stmt->execute([$venue_id])) {
        // Log activity
        logActivity($current_user['id'], 'Deleted venue', 'venues', $venue_id, "Deleted venue: {$venue['name']}");
        
        // Delete the venue image if exists (after successful DB delete)
        if (!empty($venue['image'])) {
            if (!deleteUploadedFile($venue['image'])) {
                error_log("Failed to delete venue image file: " . $venue['image']);
            }
        }
        
        $_SESSION['success_message'] = 'Venue deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete venue. Please try again.';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
