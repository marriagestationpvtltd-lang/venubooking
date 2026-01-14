<?php
require_once __DIR__ . '/../includes/header.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$menu_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($menu_id <= 0) {
    $_SESSION['error_message'] = 'Invalid menu ID.';
    header('Location: index.php');
    exit;
}

// Fetch menu details
$stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$menu_id]);
$menu = $stmt->fetch();

if (!$menu) {
    $_SESSION['error_message'] = 'Menu not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if menu is used in any bookings
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_menus WHERE menu_id = ?");
    $check_stmt->execute([$menu_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete menu. It is associated with existing bookings. You can set it to inactive instead.';
        header('Location: index.php');
        exit;
    }
    
    // Start transaction for atomic deletion
    $db->beginTransaction();
    
    // Delete menu items first
    $stmt = $db->prepare("DELETE FROM menu_items WHERE menu_id = ?");
    $stmt->execute([$menu_id]);
    
    // Delete hall_menus associations
    $stmt = $db->prepare("DELETE FROM hall_menus WHERE menu_id = ?");
    $stmt->execute([$menu_id]);
    
    // Delete the menu image if exists
    if (!empty($menu['image'])) {
        deleteUploadedFile($menu['image']);
    }
    
    // Delete the menu
    $stmt = $db->prepare("DELETE FROM menus WHERE id = ?");
    if ($stmt->execute([$menu_id])) {
        // Commit transaction
        $db->commit();
        
        // Log activity
        logActivity($current_user['id'], 'Deleted menu', 'menus', $menu_id, "Deleted menu: {$menu['name']}");
        
        $_SESSION['success_message'] = 'Menu deleted successfully!';
    } else {
        $db->rollBack();
        $_SESSION['error_message'] = 'Failed to delete menu. Please try again.';
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
