<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

try {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $new_payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';
    
    // Validate booking ID
    if ($booking_id <= 0) {
        throw new Exception('Invalid booking ID');
    }
    
    // Validate payment status
    $valid_statuses = ['pending', 'unpaid', 'partial', 'paid', 'cancelled'];
    if (!in_array($new_payment_status, $valid_statuses)) {
        throw new Exception('Invalid payment status');
    }
    
    // Get current booking details
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    $old_payment_status = $booking['payment_status'];
    
    // Validate status flow (optional - can be strict or flexible)
    // For now, we'll allow flexible updates but log them
    $status_order = ['pending' => 0, 'unpaid' => 0, 'partial' => 1, 'paid' => 2];
    $is_backward = false;
    
    if ($new_payment_status !== 'cancelled' && 
        isset($status_order[$old_payment_status]) && 
        isset($status_order[$new_payment_status]) &&
        $status_order[$new_payment_status] < $status_order[$old_payment_status]) {
        $is_backward = true;
    }
    
    // Update the payment status
    $stmt = $db->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
    $stmt->execute([$new_payment_status, $booking_id]);
    
    // Log the activity
    $action_details = "Payment status changed from {$old_payment_status} to {$new_payment_status}";
    if ($is_backward) {
        $action_details .= " (backward flow)";
    }
    
    logActivity(
        $current_user['id'], 
        'Updated payment status', 
        'bookings', 
        $booking_id, 
        $action_details . " for booking: {$booking['booking_number']}"
    );
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully',
        'old_status' => $old_payment_status,
        'new_status' => $new_payment_status,
        'is_backward' => $is_backward
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
