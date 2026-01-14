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
$customer_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = 'Invalid customer ID.';
    header('Location: index.php');
    exit;
}

// Fetch customer details
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error_message'] = 'Customer not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if customer has bookings
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?");
    $check_stmt->execute([$customer_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete customer. They have existing bookings in the system.';
        header('Location: index.php');
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
    if ($stmt->execute([$customer_id])) {
        // Log activity
        logActivity($current_user['id'], 'Deleted customer', 'customers', $customer_id, "Deleted customer: {$customer['full_name']}");
        
        $_SESSION['success_message'] = 'Customer deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete customer. Please try again.';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
