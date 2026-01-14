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
$booking_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($booking_id <= 0) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: index.php');
    exit;
}

// Fetch booking details
$stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['error_message'] = 'Booking not found.';
    header('Location: index.php');
    exit;
}

try {
    // Start transaction for atomic deletion
    $db->beginTransaction();
    
    // Delete related records first
    $db->prepare("DELETE FROM booking_menus WHERE booking_id = ?")->execute([$booking_id]);
    $db->prepare("DELETE FROM booking_services WHERE booking_id = ?")->execute([$booking_id]);
    
    // Delete the booking
    $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
    if ($stmt->execute([$booking_id])) {
        // Commit transaction
        $db->commit();
        
        logActivity($current_user['id'], 'Deleted booking', 'bookings', $booking_id, "Deleted booking: {$booking['booking_number']}");
        
        $_SESSION['success_message'] = 'Booking deleted successfully!';
    } else {
        $db->rollBack();
        $_SESSION['error_message'] = 'Failed to delete booking. Please try again.';
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // Log detailed error for debugging (server-side only)
    error_log('Booking deletion error for booking ID ' . $booking_id . ': ' . $e->getMessage());
    // Show generic error to user
    $_SESSION['error_message'] = 'Error deleting booking. Please try again or contact support.';
}

header('Location: index.php');
exit;
?>
