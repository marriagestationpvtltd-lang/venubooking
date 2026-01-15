<?php
$page_title = 'View Booking Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch booking details (only once)
$booking = getBookingDetails($booking_id);

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Helper variables for consistent status display formatting
$booking_status_display = ucfirst(str_replace('_', ' ', $booking['booking_status']));
$booking_status_color = $booking['booking_status'] == 'confirmed' ? 'success' : 
    ($booking['booking_status'] == 'pending' ? 'warning' : 
    ($booking['booking_status'] == 'cancelled' ? 'danger' : 
    ($booking['booking_status'] == 'completed' ? 'primary' : 'info')));

$payment_status_display = ucfirst($booking['payment_status']);
$payment_status_color = $booking['payment_status'] == 'paid' ? 'success' : 
    ($booking['payment_status'] == 'partial' ? 'warning' : 'danger');
$payment_status_icon = $booking['payment_status'] == 'paid' ? 'fa-check-circle' : 
    ($booking['payment_status'] == 'partial' ? 'fa-clock' : 'fa-exclamation-circle');


// Handle payment request actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'send_payment_request_email') {
        // Send payment request via email
        if (!empty($booking['email'])) {
            $result = sendBookingNotification($booking_id, 'payment_request');
            if ($result['user']) {
                $success_message = 'Payment request sent successfully via email to ' . htmlspecialchars($booking['email']);
                logActivity($current_user['id'], 'Sent payment request via email', 'bookings', $booking_id, "Payment request email sent for booking: {$booking['booking_number']}");
            } else {
                $error_message = 'Failed to send payment request email. Please check email settings.';
            }
        } else {
            $error_message = 'Customer email not found. Cannot send email.';
        }
    } elseif ($action === 'send_payment_request_whatsapp') {
        // Send payment request via WhatsApp
        if (!empty($booking['phone'])) {
            $success_message = 'Opening WhatsApp to send payment request...';
            logActivity($current_user['id'], 'Initiated WhatsApp payment request', 'bookings', $booking_id, "WhatsApp payment request initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Customer phone number not found. Cannot send WhatsApp message.';
        }
    } elseif ($action === 'update_status') {
        // Handle quick status update
        $new_booking_status = trim($_POST['booking_status'] ?? '');
        $old_booking_status = trim($_POST['old_booking_status'] ?? '');
        
        // Validate booking status
        $valid_statuses = ['pending', 'payment_submitted', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($new_booking_status, $valid_statuses)) {
            $error_message = 'Invalid booking status.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
                $stmt->execute([$new_booking_status, $booking_id]);
                
                // Send email notification about status change
                sendBookingNotification($booking_id, 'update', $old_booking_status);
                
                logActivity($current_user['id'], 'Updated booking status', 'bookings', $booking_id, "Status changed from {$old_booking_status} to {$new_booking_status}");
                
                $success_message = "Booking status updated successfully from " . ucfirst($old_booking_status) . " to " . ucfirst($new_booking_status);
                
                // Re-fetch booking to get updated status
                $booking = getBookingDetails($booking_id);
            } catch (Exception $e) {
                $error_message = 'Failed to update booking status. Please try again.';
            }
        }
    }
}
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h3 class="mb-1 text-primary">
                            <i class="fas fa-calendar-check me-2"></i>
                            Booking #<?php echo htmlspecialchars($booking['booking_number']); ?>
                        </h3>
                        <p class="text-muted mb-0 small">
                            <i class="far fa-clock me-1"></i>
                            Created on <?php echo date('F d, Y \a\t h:i A', strtotime($booking['created_at'])); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <a href="edit.php?id=<?php echo $booking_id; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-gradient-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Quick Actions</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Payment Request Buttons -->
                    <div class="col-lg-6">
                        <div class="quick-action-section">
                            <h6 class="fw-bold mb-3 text-dark">
                                <i class="fas fa-paper-plane text-primary me-2"></i>
                                Send Payment Request
                            </h6>
                        <form method="POST" action="" style="display: inline-block;" class="me-2">
                            <input type="hidden" name="action" value="send_payment_request_email">
                            <button type="submit" class="btn btn-primary px-4" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                <i class="fas fa-envelope me-2"></i> Email Request
                            </button>
                        </form>
                        <?php 
                        // Prepare WhatsApp data
                        $clean_phone = !empty($booking['phone']) ? preg_replace('/[^0-9]/', '', $booking['phone']) : '';
                        
                        // Calculate advance payment based on configured percentage
                        $advance = calculateAdvancePayment($booking['grand_total']);
                        
                        // Get payment methods for this booking
                        $whatsapp_payment_methods = getBookingPaymentMethods($booking_id);
                        
                        $whatsapp_text = "Dear " . $booking['full_name'] . ",\n\n" .
                            "Your booking (ID: " . $booking['booking_number'] . ") for " . $booking['venue_name'] . " on " . date('F d, Y', strtotime($booking['event_date'])) . " is almost confirmed.\n\n" .
                            "💰 Total Amount: " . formatCurrency($booking['grand_total']) . "\n" .
                            "💵 Advance Payment (" . $advance['percentage'] . "%): " . formatCurrency($advance['amount']) . "\n\n";
                        
                        if (!empty($whatsapp_payment_methods)) {
                            $whatsapp_text .= "📱 Payment Methods:\n\n";
                            foreach ($whatsapp_payment_methods as $idx => $method) {
                                $whatsapp_text .= ($idx + 1) . ". " . $method['name'] . "\n";
                                if (!empty($method['bank_details'])) {
                                    $whatsapp_text .= $method['bank_details'] . "\n";
                                }
                                $whatsapp_text .= "\n";
                            }
                            $whatsapp_text .= "After making payment, please contact us with your booking number to confirm.\n\n";
                        } else {
                            $whatsapp_text .= "Please contact us for payment details.\n\n";
                        }
                        
                        $whatsapp_text .= "Thank you!";
                        ?>
                        <form method="POST" action="" style="display: inline-block;" id="whatsappForm">
                            <input type="hidden" name="action" value="send_payment_request_whatsapp">
                            <button type="submit" class="btn btn-success px-4" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                <i class="fab fa-whatsapp me-2"></i> WhatsApp Request
                            </button>
                        </form>
                        <?php if (empty($booking['email']) && empty($booking['phone'])): ?>
                            <small class="text-muted d-block mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Customer contact information is not available
                            </small>
                        <?php elseif (empty($booking['email'])): ?>
                            <small class="text-muted d-block mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Email not available
                            </small>
                        <?php elseif (empty($booking['phone'])): ?>
                            <small class="text-muted d-block mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Phone number not available
                            </small>
                        <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Status Update -->
                    <div class="col-lg-6">
                        <div class="quick-action-section">
                            <h6 class="fw-bold mb-3 text-dark">
                                <i class="fas fa-sync-alt text-primary me-2"></i>
                                Update Booking Status
                            </h6>
                            <form method="POST" action="" class="status-update-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="old_booking_status" value="<?php echo $booking['booking_status']; ?>">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="booking_status" class="form-label fw-semibold">Select New Status</label>
                                        <select class="form-select form-select-lg" id="booking_status" name="booking_status">
                                            <option value="pending" <?php echo ($booking['booking_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="payment_submitted" <?php echo ($booking['booking_status'] == 'payment_submitted') ? 'selected' : ''; ?>>Payment Submitted</option>
                                            <option value="confirmed" <?php echo ($booking['booking_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="cancelled" <?php echo ($booking['booking_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="completed" <?php echo ($booking['booking_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-info w-100">
                                            <i class="fas fa-check me-2"></i> Update
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Current Status: 
                                    <span class="badge bg-<?php echo $booking_status_color; ?>"><?php echo $booking_status_display; ?></span>
                                </small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Booking Details -->
    <div class="col-lg-8">
        <!-- Customer Information -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-info text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i> Customer Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Full Name</label>
                            <p class="mb-0 fw-bold text-dark fs-6">
                                <i class="fas fa-user-circle text-primary me-2"></i>
                                <?php echo htmlspecialchars($booking['full_name']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Phone Number</label>
                            <p class="mb-0">
                                <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>" class="text-decoration-none">
                                    <i class="fas fa-phone text-success me-2"></i>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($booking['phone']); ?></span>
                                </a>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Email Address</label>
                            <p class="mb-0">
                                <?php if ($booking['email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope text-danger me-2"></i>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($booking['email']); ?></span>
                                    </a>
                                <?php else: ?>
                                    <em class="text-muted">Not provided</em>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Address</label>
                            <p class="mb-0">
                                <i class="fas fa-map-marker-alt text-warning me-2"></i>
                                <?php echo $booking['address'] ? '<span class="fw-semibold">' . htmlspecialchars($booking['address']) . '</span>' : '<em class="text-muted">Not provided</em>'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Details -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-success text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Event Details</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Venue</label>
                            <p class="mb-0 fw-bold text-dark fs-6">
                                <i class="fas fa-building text-primary me-2"></i>
                                <?php echo htmlspecialchars($booking['venue_name']); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Hall</label>
                            <p class="mb-0 fw-bold text-dark fs-6">
                                <i class="fas fa-door-open text-info me-2"></i>
                                <?php echo htmlspecialchars($booking['hall_name']); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i>
                                Capacity: <?php echo $booking['capacity']; ?> guests
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Event Date</label>
                            <p class="mb-0 fw-bold text-dark">
                                <i class="far fa-calendar text-danger me-2"></i>
                                <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Shift</label>
                            <p class="mb-0 fw-bold text-dark">
                                <i class="far fa-clock text-warning me-2"></i>
                                <?php echo ucfirst($booking['shift']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Event Type</label>
                            <p class="mb-0 fw-bold text-dark">
                                <i class="fas fa-tag text-success me-2"></i>
                                <?php echo htmlspecialchars($booking['event_type']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-1">Number of Guests</label>
                            <p class="mb-0">
                                <span class="badge bg-primary fs-6 px-3 py-2">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <?php echo $booking['number_of_guests']; ?> Guests
                                </span>
                            </p>
                        </div>
                    </div>
                    <?php if ($booking['special_requests']): ?>
                    <div class="col-12">
                        <div class="info-item">
                            <label class="text-muted small fw-semibold mb-2">Special Requests</label>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-comment-dots me-2"></i>
                                <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Menus -->
        <?php if (count($booking['menus']) > 0): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-warning">
                <h5 class="mb-0 text-white"><i class="fas fa-utensils me-2"></i> Selected Menus</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Menu</th>
                                <th class="fw-semibold text-end">Price per Person</th>
                                <th class="fw-semibold text-center">Guests</th>
                                <th class="fw-semibold text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['menus'] as $menu): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <i class="fas fa-plate-wheat text-warning me-2"></i>
                                    <?php echo htmlspecialchars($menu['menu_name']); ?>
                                    <?php if (!empty($menu['items'])): ?>
                                        <?php $safeMenuId = intval($menu['menu_id']); ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#menu-items-<?php echo $safeMenuId; ?>" 
                                                aria-expanded="false"
                                                aria-controls="menu-items-<?php echo $safeMenuId; ?>">
                                            <i class="fas fa-list"></i> View Items
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold text-success"><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $menu['number_of_guests']; ?></span>
                                </td>
                                <td class="text-end fw-bold text-primary"><?php echo formatCurrency($menu['total_price']); ?></td>
                            </tr>
                            <?php if (!empty($menu['items'])): ?>
                            <tr class="collapse" id="menu-items-<?php echo $safeMenuId; ?>">
                                <td colspan="4" class="bg-light">
                                    <div class="p-2">
                                        <strong class="small">Menu Items:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php 
                                            $items_by_category = [];
                                            foreach ($menu['items'] as $item) {
                                                $category = !empty($item['category']) ? $item['category'] : 'Other';
                                                $items_by_category[$category][] = $item;
                                            }
                                            
                                            foreach ($items_by_category as $category => $items): 
                                            ?>
                                                <?php if (count($items_by_category) > 1): ?>
                                                    <li class="small"><strong><?php echo htmlspecialchars($category); ?>:</strong>
                                                        <ul>
                                                            <?php foreach ($items as $item): ?>
                                                                <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </li>
                                                <?php else: ?>
                                                    <?php foreach ($items as $item): ?>
                                                        <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Services -->
        <?php if (count($booking['services']) > 0): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-concierge-bell me-2"></i> Additional Services</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Service</th>
                                <th class="fw-semibold text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['services'] as $service): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                </td>
                                <td class="text-end fw-bold text-primary"><?php echo formatCurrency($service['price']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Methods -->
        <?php 
        $booking_payment_methods = getBookingPaymentMethods($booking_id);
        if (count($booking_payment_methods) > 0): 
        ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-primary text-white">
                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i> Payment Methods</h5>
            </div>
            <div class="card-body p-4">
                <?php foreach ($booking_payment_methods as $method): ?>
                <div class="payment-method-item mb-4 pb-4 <?php echo ($method !== end($booking_payment_methods)) ? 'border-bottom' : ''; ?>">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fas fa-money-check-alt text-primary me-2"></i>
                        <?php echo htmlspecialchars($method['name']); ?>
                    </h6>
                    
                    <div class="row g-3">
                        <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                        <div class="col-md-4">
                            <div class="qr-code-container">
                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>" 
                                     alt="<?php echo htmlspecialchars($method['name']); ?> QR Code" 
                                     class="img-fluid rounded shadow-sm"
                                     style="max-width: 200px; border: 2px solid #dee2e6; padding: 10px; background: white;">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($method['bank_details'])): ?>
                        <div class="<?php echo !empty($method['qr_code']) ? 'col-md-8' : 'col-12'; ?>">
                            <div class="alert alert-light mb-0 border">
                                <small class="text-muted fw-semibold d-block mb-2">Bank Details:</small>
                                <pre class="mb-0 text-dark" style="font-family: 'Courier New', monospace; font-size: 0.875rem; white-space: pre-wrap;"><?php echo htmlspecialchars($method['bank_details']); ?></pre>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Transactions -->
        <?php 
        $payment_transactions = getBookingPayments($booking_id);
        if (count($payment_transactions) > 0): 
        ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-success text-white">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Payment Transactions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Date</th>
                                <th class="fw-semibold">Payment Method</th>
                                <th class="fw-semibold">Transaction ID</th>
                                <th class="fw-semibold text-end">Amount</th>
                                <th class="fw-semibold text-center">Status</th>
                                <th class="fw-semibold text-center">Payment Slip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_transactions as $payment): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <i class="far fa-calendar-alt text-primary me-2"></i>
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                </td>
                                <td><?php echo !empty($payment['payment_method_name']) ? htmlspecialchars($payment['payment_method_name']) : '<em class="text-muted">N/A</em>'; ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo !empty($payment['transaction_id']) ? htmlspecialchars($payment['transaction_id']) : 'N/A'; ?>
                                    </span>
                                    <?php if (!empty($payment['notes'])): ?>
                                        <br><small class="text-muted mt-1 d-block"><?php echo htmlspecialchars($payment['notes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success fs-6"><?php echo formatCurrency($payment['paid_amount']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $payment['payment_status'] == 'verified' ? 'success' : 
                                            ($payment['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($payment['payment_slip']) && validateUploadedFilePath($payment['payment_slip'])): ?>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#slipModal<?php echo $payment['id']; ?>">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                        
                                        <!-- Modal for Payment Slip -->
                                        <div class="modal fade" id="slipModal<?php echo $payment['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-receipt me-2"></i>
                                                            Payment Slip
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center p-4">
                                                        <div class="mb-3">
                                                            <span class="badge bg-secondary">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>" 
                                                             alt="Payment Slip" 
                                                             class="img-fluid rounded shadow"
                                                             style="max-height: 70vh;">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <a href="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>" 
                                                           download 
                                                           class="btn btn-success">
                                                            <i class="fas fa-download me-1"></i> Download
                                                        </a>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light border-top border-2">
                                <td colspan="3" class="text-end fw-bold">Total Paid:</td>
                                <td colspan="3" class="text-end">
                                    <strong class="text-success fs-4">
                                        <?php 
                                        $total_paid = array_sum(array_column($payment_transactions, 'paid_amount'));
                                        echo formatCurrency($total_paid); 
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3" class="text-end">Grand Total:</td>
                                <td colspan="3" class="text-end">
                                    <strong class="fs-5"><?php echo formatCurrency($booking['grand_total']); ?></strong>
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3" class="text-end">Balance Due:</td>
                                <td colspan="3" class="text-end">
                                    <strong class="text-danger fs-4">
                                        <?php 
                                        $balance_due = $booking['grand_total'] - $total_paid;
                                        echo formatCurrency($balance_due); 
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <!-- Booking Status Card -->
        <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px;">
            <div class="card-header bg-gradient-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Booking Overview</h5>
            </div>
            <div class="card-body p-4">
                <!-- Status Badges -->
                <div class="mb-4 pb-4 border-bottom">
                    <div class="mb-3">
                        <label class="small text-muted fw-semibold mb-2 d-block">Booking Status</label>
                        <h5 class="mb-0">
                            <span class="badge bg-<?php echo $booking_status_color; ?> px-3 py-2">
                                <i class="fas fa-circle-dot me-2"></i>
                                <?php echo $booking_status_display; ?>
                            </span>
                        </h5>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-semibold mb-2 d-block">Payment Status</label>
                        <h5 class="mb-0">
                            <span class="badge bg-<?php echo $payment_status_color; ?> px-3 py-2">
                                <i class="fas <?php echo $payment_status_icon; ?> me-2"></i>
                                <?php echo $payment_status_display; ?>
                            </span>
                        </h5>
                    </div>
                    <div>
                        <label class="small text-muted fw-semibold mb-2 d-block">
                            <i class="far fa-calendar-plus me-1"></i>
                            Booked On
                        </label>
                        <p class="mb-0 fw-semibold">
                            <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                            <br>
                            <small class="text-muted"><?php echo date('h:i A', strtotime($booking['created_at'])); ?></small>
                        </p>
                    </div>
                </div>
                
                <!-- Payment Summary -->
                <div>
                    <h6 class="fw-bold mb-3 text-dark">
                        <i class="fas fa-calculator text-primary me-2"></i>
                        Payment Summary
                    </h6>
                    
                    <div class="payment-breakdown">
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted small">Hall Price:</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['hall_price']); ?></strong>
                        </div>
                        <?php if ($booking['menu_total'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted small">Menu Total:</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['menu_total']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['services_total'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted small">Services Total:</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['services_total']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted">Subtotal:</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['subtotal']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted">Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                        </div>
                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">Grand Total:</h5>
                            <h4 class="mb-0 text-success fw-bold"><?php echo formatCurrency($booking['grand_total']); ?></h4>
                        </div>
                        
                        <?php 
                        // Calculate advance payment
                        $advance = calculateAdvancePayment($booking['grand_total']);
                        ?>
                        <div class="alert alert-warning mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block fw-semibold mb-1">
                                        <i class="fas fa-hand-holding-usd me-1"></i>
                                        Advance Required
                                    </small>
                                    <small class="text-muted">(<?php echo htmlspecialchars($advance['percentage']); ?>%)</small>
                                </div>
                                <h5 class="mb-0 fw-bold"><?php echo formatCurrency($advance['amount']); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Booking View Styles */
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075) !important;
}

.info-item {
    padding: 0.75rem;
    border-radius: 6px;
    background: #f8f9fa;
    transition: background 0.2s ease;
}

.info-item:hover {
    background: #e9ecef;
}

.quick-action-section {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.status-update-form .form-select {
    border: 2px solid #dee2e6;
}

.status-update-form .form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.payment-breakdown {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.table-hover tbody tr {
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.payment-method-item {
    transition: all 0.2s ease;
}

.payment-method-item:hover {
    background: #f8f9fa;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
    border-radius: 8px;
}

.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}

.badge {
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Print Styles */
@media print {
    .btn, .card-header, nav, footer, .alert, .quick-action-section {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .shadow-sm {
        box-shadow: none !important;
    }
    .col-lg-8, .col-lg-4 {
        width: 100% !important;
    }
    .sticky-top {
        position: relative !important;
        top: 0 !important;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .quick-action-section {
        margin-bottom: 1rem;
    }
    
    .status-update-form .col-md-4 {
        margin-top: 0.5rem;
    }
    
    .payment-breakdown {
        font-size: 0.9rem;
    }
}
</style>

<script>
// Handle WhatsApp form submission
(function() {
    const WHATSAPP_REDIRECT_DELAY = 500; // milliseconds
    const whatsappForm = document.getElementById('whatsappForm');
    
    if (whatsappForm) {
        whatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Open WhatsApp with properly escaped and encoded values
            const phone = <?php echo json_encode($clean_phone); ?>;
            const message = <?php echo json_encode($whatsapp_text); ?>;
            const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
            window.open(whatsappUrl, '_blank');
            
            // Submit the form to log the activity
            setTimeout(function() {
                whatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
