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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

try {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $new_payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';

    // Optional: actual advance amount received (entered manually by admin)
    $advance_amount_raw = isset($_POST['advance_amount']) ? trim($_POST['advance_amount']) : '';
    $is_advance_amount_valid = ($advance_amount_raw !== '' && is_numeric($advance_amount_raw) && floatval($advance_amount_raw) >= 0);
    $advance_amount = $is_advance_amount_valid ? floatval($advance_amount_raw) : null;
    
    // Validate booking ID
    if ($booking_id <= 0) {
        throw new Exception('Invalid booking ID');
    }
    
    // Validate payment status
    $valid_statuses = ['pending', 'partial', 'paid', 'cancelled'];
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
    $status_order = ['pending' => 0, 'partial' => 1, 'paid' => 2];
    $is_backward = false;
    
    if ($new_payment_status !== 'cancelled' && 
        isset($status_order[$old_payment_status]) && 
        isset($status_order[$new_payment_status]) &&
        $status_order[$new_payment_status] < $status_order[$old_payment_status]) {
        $is_backward = true;
    }
    
    // Derive booking_status and advance_payment_received from the new payment_status
    $auto = getAutoStatusByPaymentStatus($new_payment_status);
    $new_booking_status  = $auto['booking_status']          ?? $booking['booking_status'];
    $new_advance_payment = $auto['advance_payment_received'] ?? $booking['advance_payment_received'];

    // Determine advance_amount_received to save:
    // - When setting to 'partial': use provided amount if given, else keep existing
    // - When setting to 'paid': use provided amount if given, else use grand_total
    // - When setting to 'pending' or 'cancelled': reset to 0
    $grand_total = floatval($booking['grand_total']);
    $current_advance_amount = floatval($booking['advance_amount_received'] ?? 0);
    if ($new_payment_status === 'partial') {
        $new_advance_amount = ($advance_amount !== null) ? $advance_amount : $current_advance_amount;
    } elseif ($new_payment_status === 'paid') {
        $new_advance_amount = ($advance_amount !== null) ? $advance_amount : $grand_total;
    } else {
        // pending or cancelled: reset
        $new_advance_amount = 0.0;
    }

    // Update payment_status, booking_status, advance_payment_received, and advance_amount_received atomically
    $stmt = $db->prepare(
        "UPDATE bookings SET payment_status = ?, booking_status = ?, advance_payment_received = ?, advance_amount_received = ? WHERE id = ?"
    );
    $stmt->execute([$new_payment_status, $new_booking_status, $new_advance_payment, $new_advance_amount, $booking_id]);

    // Log the activity
    $action_details = "Payment status changed from {$old_payment_status} to {$new_payment_status}";
    if ($is_backward) {
        $action_details .= " (backward flow)";
    }
    $action_details .= "; booking_status auto-set to {$new_booking_status}; advance_payment_received auto-set to {$new_advance_payment}; advance_amount_received set to {$new_advance_amount}";

    logActivity(
        $current_user['id'],
        'Updated payment status',
        'bookings',
        $booking_id,
        $action_details . " for booking: {$booking['booking_number']}"
    );

    // Return success response including auto-updated fields so the UI can reflect them
    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully',
        'old_status' => $old_payment_status,
        'new_status' => $new_payment_status,
        'booking_status' => $new_booking_status,
        'advance_payment_received' => $new_advance_payment,
        'advance_amount_received' => $new_advance_amount,
        'is_backward' => $is_backward
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
