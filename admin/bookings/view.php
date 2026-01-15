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
        $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
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

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-check"></i> Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></h4>
            <div>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $booking_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Booking
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Card -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Payment Request Buttons -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="text-muted mb-3">Send Payment Request</h6>
                        <form method="POST" action="" style="display: inline-block;" class="me-2">
                            <input type="hidden" name="action" value="send_payment_request_email">
                            <button type="submit" class="btn btn-primary" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                <i class="fas fa-envelope"></i> Request Payment (Email)
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
                            <button type="submit" class="btn btn-success" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                <i class="fab fa-whatsapp"></i> Request Payment (WhatsApp)
                            </button>
                        </form>
                        <?php if (empty($booking['email']) && empty($booking['phone'])): ?>
                            <small class="text-muted d-block mt-2">Customer contact information is not available</small>
                        <?php elseif (empty($booking['email'])): ?>
                            <small class="text-muted d-block mt-2">Email not available</small>
                        <?php elseif (empty($booking['phone'])): ?>
                            <small class="text-muted d-block mt-2">Phone number not available</small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Status Update -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Update Booking Status</h6>
                        <form method="POST" action="" class="d-flex align-items-end gap-2">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="old_booking_status" value="<?php echo $booking['booking_status']; ?>">
                            <div class="flex-grow-1">
                                <label for="booking_status" class="form-label mb-1">Status</label>
                                <select class="form-select" id="booking_status" name="booking_status">
                                    <option value="pending" <?php echo ($booking['booking_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo ($booking['booking_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo ($booking['booking_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo ($booking['booking_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-sync-alt"></i> Update Status
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">Current: <span class="badge bg-<?php 
                            echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                ($booking['booking_status'] == 'pending' ? 'warning' : 
                                ($booking['booking_status'] == 'cancelled' ? 'danger' : 'info')); 
                        ?>"><?php echo ucfirst($booking['booking_status']); ?></span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Booking Details -->
    <div class="col-md-8">
        <!-- Customer Information -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($booking['full_name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Phone:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>">
                            <?php echo htmlspecialchars($booking['phone']); ?>
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong><br>
                        <?php if ($booking['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>">
                                <?php echo htmlspecialchars($booking['email']); ?>
                            </a>
                        <?php else: ?>
                            <em class="text-muted">Not provided</em>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Address:</strong><br>
                        <?php echo $booking['address'] ? htmlspecialchars($booking['address']) : '<em class="text-muted">Not provided</em>'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Details -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Event Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Venue:</strong><br>
                        <?php echo htmlspecialchars($booking['venue_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Hall:</strong><br>
                        <?php echo htmlspecialchars($booking['hall_name']); ?><br>
                        <small class="text-muted">Capacity: <?php echo $booking['capacity']; ?> guests</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <strong>Event Date:</strong><br>
                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <strong>Shift:</strong><br>
                        <?php echo ucfirst($booking['shift']); ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <strong>Event Type:</strong><br>
                        <?php echo htmlspecialchars($booking['event_type']); ?>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Number of Guests:</strong><br>
                    <?php echo $booking['number_of_guests']; ?> guests
                </div>
                <?php if ($booking['special_requests']): ?>
                <div class="mb-3">
                    <strong>Special Requests:</strong><br>
                    <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Menus -->
        <?php if (count($booking['menus']) > 0): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Selected Menus</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Price per Person</th>
                                <th>Guests</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['menus'] as $menu): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($menu['menu_name']); ?>
                                    <?php if (!empty($menu['items'])): ?>
                                        <?php $safeMenuId = intval($menu['menu_id']); ?>
                                        <button class="btn btn-sm btn-link p-0 ms-2" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#menu-items-<?php echo $safeMenuId; ?>" 
                                                aria-expanded="false"
                                                aria-controls="menu-items-<?php echo $safeMenuId; ?>">
                                            <i class="fas fa-list"></i> View Items
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                <td><?php echo $menu['number_of_guests']; ?></td>
                                <td><?php echo formatCurrency($menu['total_price']); ?></td>
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
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> Additional Services</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['services'] as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td><?php echo formatCurrency($service['price']); ?></td>
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
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Methods</h5>
            </div>
            <div class="card-body">
                <?php foreach ($booking_payment_methods as $method): ?>
                <div class="mb-4 pb-3 border-bottom">
                    <h6 class="mb-2"><?php echo htmlspecialchars($method['name']); ?></h6>
                    
                    <?php if (!empty($method['qr_code'])): ?>
                    <div class="mb-3">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>" 
                             alt="<?php echo htmlspecialchars($method['name']); ?> QR Code" 
                             style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px; padding: 8px;">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($method['bank_details'])): ?>
                    <div style="background-color: #f8f9fa; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 0.875rem; white-space: pre-wrap;">
                        <?php echo htmlspecialchars($method['bank_details']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Booking Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Booking Status:</strong><br>
                    <span class="badge bg-<?php 
                        echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                            ($booking['booking_status'] == 'pending' ? 'warning' : 
                            ($booking['booking_status'] == 'cancelled' ? 'danger' : 'info')); 
                    ?> fs-6">
                        <?php echo ucfirst($booking['booking_status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Payment Status:</strong><br>
                    <span class="badge bg-<?php 
                        echo $booking['payment_status'] == 'paid' ? 'success' : 
                            ($booking['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                    ?> fs-6">
                        <?php echo ucfirst($booking['payment_status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Booked On:</strong><br>
                    <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calculator"></i> Payment Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Hall Price:</span>
                    <strong><?php echo formatCurrency($booking['hall_price']); ?></strong>
                </div>
                <?php if ($booking['menu_total'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Menu Total:</span>
                    <strong><?php echo formatCurrency($booking['menu_total']); ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($booking['services_total'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Services Total:</span>
                    <strong><?php echo formatCurrency($booking['services_total']); ?></strong>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <strong><?php echo formatCurrency($booking['subtotal']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                    <strong><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <h5>Grand Total:</h5>
                    <h5 class="text-success"><?php echo formatCurrency($booking['grand_total']); ?></h5>
                </div>
                <?php 
                // Calculate advance payment
                $advance = calculateAdvancePayment($booking['grand_total']);
                ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Advance Required (<?php echo htmlspecialchars($advance['percentage']); ?>%):</strong></span>
                        <strong class="fs-5"><?php echo formatCurrency($advance['amount']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .card-header, nav, footer, .alert {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
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
