<?php
require_once __DIR__ . '/../includes/header.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$hall_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($hall_id <= 0) {
    $_SESSION['error_message'] = 'Invalid hall ID.';
    header('Location: index.php');
    exit;
}

// Fetch hall details
$stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
$stmt->execute([$hall_id]);
$hall = $stmt->fetch();

if (!$hall) {
    $_SESSION['error_message'] = 'Hall not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if hall has bookings
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE hall_id = ?");
    $check_stmt->execute([$hall_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete hall. It has ' . $result['count'] . ' associated booking(s). You can set it to inactive instead.';
        header('Location: index.php');
        exit;
    }
    
    // Start transaction for atomic deletion
    $db->beginTransaction();
    
    // Get hall images paths before deletion
    $images_stmt = $db->prepare("SELECT image_path FROM hall_images WHERE hall_id = ?");
    $images_stmt->execute([$hall_id]);
    $images = $images_stmt->fetchAll();
    
    // Delete hall images records
    $db->prepare("DELETE FROM hall_images WHERE hall_id = ?")->execute([$hall_id]);
    
    // Delete hall_menus associations
    $db->prepare("DELETE FROM hall_menus WHERE hall_id = ?")->execute([$hall_id]);
    
    // Delete the hall
    $stmt = $db->prepare("DELETE FROM halls WHERE id = ?");
    if ($stmt->execute([$hall_id])) {
        // Commit transaction
        $db->commit();
        
        // Delete physical image files after successful commit
        foreach ($images as $image) {
            deleteUploadedFile($image['image_path']);
        }
        
        // Log activity
        logActivity($current_user['id'], 'Deleted hall', 'halls', $hall_id, "Deleted hall: {$hall['name']}");
        
        $_SESSION['success_message'] = 'Hall deleted successfully!';
    } else {
        $db->rollBack();
        $_SESSION['error_message'] = 'Failed to delete hall. Please try again.';
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
