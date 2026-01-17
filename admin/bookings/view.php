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
    } elseif ($action === 'toggle_advance_payment') {
        // Handle advance payment status toggle
        $new_advance_status = isset($_POST['advance_payment_received']) ? 1 : 0;
        $old_advance_status = $booking['advance_payment_received'];
        
        try {
            $stmt = $db->prepare("UPDATE bookings SET advance_payment_received = ? WHERE id = ?");
            $stmt->execute([$new_advance_status, $booking_id]);
            
            $status_text = $new_advance_status ? 'received' : 'not received';
            logActivity($current_user['id'], 'Updated advance payment status', 'bookings', $booking_id, "Advance payment marked as {$status_text} for booking: {$booking['booking_number']}");
            
            $success_message = "Advance payment status updated successfully to: " . ucfirst($status_text);
            
            // Re-fetch booking to get updated status
            $booking = getBookingDetails($booking_id);
        } catch (Exception $e) {
            $error_message = 'Failed to update advance payment status. Please try again.';
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

<!-- Print-Only Invoice Layout -->
<?php
// Calculate payment details for invoice
$total_paid = 0;
$payment_transactions = getBookingPayments($booking_id);
if (!empty($payment_transactions)) {
    $total_paid = array_sum(array_column($payment_transactions, 'paid_amount'));
}

// Calculate advance payment (used in both print invoice and UI display)
$advance = calculateAdvancePayment($booking['grand_total']);

// Calculate balance due: Grand Total - Total Paid (actual payments)
$balance_due = $booking['grand_total'] - $total_paid;

// Company details from settings - use company-specific or fallback to general
// Note: getSetting() caches results, but we check primary first to avoid unnecessary fallback queries
$company_name = getSetting('company_name');
if (empty($company_name)) {
    $company_name = getSetting('site_name', 'Wedding Venue Booking');
}

$company_address = getSetting('company_address');
if (empty($company_address)) {
    $company_address = getSetting('contact_address', 'Nepal');
}

$company_phone = getSetting('company_phone');
if (empty($company_phone)) {
    $company_phone = getSetting('contact_phone', 'N/A');
}

$company_email = getSetting('company_email');
if (empty($company_email)) {
    $company_email = getSetting('contact_email', '');
}

$company_logo = getCompanyLogo(); // Returns validated logo info or null

// Get payment mode from latest transaction
$payment_mode = 'Not specified';
if (!empty($payment_transactions)) {
    $latest_payment = $payment_transactions[0];
    $payment_mode = !empty($latest_payment['payment_method_name']) ? $latest_payment['payment_method_name'] : 'Not specified';
}

// Get invoice content from settings
$invoice_title = getSetting('invoice_title', 'Wedding Booking Confirmation & Partial Payment Receipt');
$cancellation_policy = getSetting('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.');
$invoice_disclaimer = getSetting('invoice_disclaimer', 'Note: This is a computer-generated estimate bill. Please create a complete invoice yourself.');
$package_label = getSetting('invoice_package_label', 'Marriage Package');
$additional_items_label = getSetting('invoice_additional_items_label', 'Additional Items');
$currency = getSetting('currency', 'NPR');
?>

<div class="print-invoice-only" style="display: none;">
    <div class="invoice-container">
        <!-- Header Section -->
        <div class="invoice-header">
            <div class="header-content">
                <div class="company-info">
                    <h1 class="company-name"><?php echo htmlspecialchars($company_name); ?></h1>
                    <p class="company-details">
                        <?php echo htmlspecialchars($company_address); ?><br>
                        Phone: <?php echo htmlspecialchars($company_phone); ?>
                        <?php if ($company_email): ?>
                            <br>Email: <?php echo htmlspecialchars($company_email); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="company-logo-space">
                    <?php if ($company_logo !== null): ?>
                        <img src="<?php echo $company_logo['url']; ?>" 
                             alt="<?php echo htmlspecialchars($company_name); ?>" 
                             class="company-logo-img">
                    <?php else: ?>
                        <div class="logo-placeholder"><?php echo htmlspecialchars($company_name); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="invoice-title">
                <h2><?php echo nl2br(htmlspecialchars($invoice_title)); ?></h2>
            </div>
        </div>

        <!-- Invoice Details Bar -->
        <div class="invoice-details-bar">
            <div class="invoice-detail-item">
                <strong>Invoice Date:</strong> <?php echo date('F d, Y', strtotime($booking['created_at'])); ?>
            </div>
            <div class="invoice-detail-item">
                <strong>Booking Date:</strong> <?php echo date('F d, Y', strtotime($booking['event_date'])); ?>
            </div>
            <div class="invoice-detail-item">
                <strong>Booking No:</strong> <?php echo htmlspecialchars($booking['booking_number']); ?>
            </div>
        </div>

        <!-- Customer Details Section -->
        <div class="customer-section">
            <h3>Customer Details</h3>
            <div class="customer-info-grid">
                <div class="info-row">
                    <span class="info-label">Booked By:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mobile Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                </div>
                <?php if ($booking['email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Event Type:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Event Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?> (<?php echo ucfirst($booking['shift']); ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Venue:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['venue_name']); ?> - <?php echo htmlspecialchars($booking['hall_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Number of Guests:</span>
                    <span class="info-value"><?php echo $booking['number_of_guests']; ?></span>
                </div>
            </div>
        </div>

        <!-- Booking Details Table -->
        <div class="booking-table-section">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Amount (<?php echo htmlspecialchars($currency); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Hall/Venue -->
                    <tr>
                        <td><strong><?php echo htmlspecialchars($package_label); ?></strong> - <?php echo htmlspecialchars($booking['hall_name']); ?></td>
                        <td class="text-center">1</td>
                        <td class="text-right"><?php echo number_format($booking['hall_price'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($booking['hall_price'], 2); ?></td>
                    </tr>
                    
                    <!-- Menus -->
                    <?php if (!empty($booking['menus'])): ?>
                        <?php foreach ($booking['menus'] as $menu): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($menu['menu_name']); ?></td>
                            <td class="text-center"><?php echo $menu['number_of_guests']; ?></td>
                            <td class="text-right"><?php echo number_format($menu['price_per_person'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($menu['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Services / Additional Items -->
                    <?php if (!empty($booking['services'])): ?>
                        <?php foreach ($booking['services'] as $service): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($additional_items_label); ?></strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">1</td>
                            <?php $service_price = floatval($service['price'] ?? 0); ?>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted"><em>No additional services selected</em></td>
                        </tr>
                    <?php endif; ?>
                    
                    <!-- Subtotal -->
                    <tr class="subtotal-row">
                        <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($booking['subtotal'], 2); ?></strong></td>
                    </tr>
                    
                    <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                    <!-- Tax -->
                    <tr>
                        <td colspan="3" class="text-right">Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</td>
                        <td class="text-right"><?php echo number_format($booking['tax_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Grand Total -->
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>GRAND TOTAL:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($booking['grand_total'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment Calculation Section -->
        <div class="payment-calculation-section">
            <table class="payment-table">
                <tr>
                    <td class="payment-label">Advance Payment Required (<?php echo $advance['percentage']; ?>%):</td>
                    <td class="payment-value"><?php echo formatCurrency($advance['amount']); ?></td>
                </tr>
                <tr>
                    <td class="payment-label">Advance Payment Received:</td>
                    <td class="payment-value"><?php 
                        // Display advance amount only if marked as received by admin
                        if (!empty($booking['advance_payment_received'])) {
                            echo formatCurrency($advance['amount']);
                        } else {
                            echo formatCurrency(0);
                        }
                    ?></td>
                </tr>
                <tr class="due-amount-row">
                    <td class="payment-label"><strong>Balance Due Amount:</strong></td>
                    <td class="payment-value"><strong><?php echo formatCurrency($balance_due); ?></strong></td>
                </tr>
                <tr>
                    <td class="payment-label">Amount in Words:</td>
                    <td class="payment-value-words"><?php echo numberToWords($booking['grand_total']); ?> Only</td>
                </tr>
                <tr>
                    <td class="payment-label">Payment Mode:</td>
                    <td class="payment-value"><?php echo htmlspecialchars($payment_mode); ?></td>
                </tr>
            </table>
        </div>

        <!-- Important Note Section -->
        <div class="note-section">
            <h3>Important - Cancellation Policy</h3>
            <ul>
                <?php 
                // Split cancellation policy by lines and display as list items
                $policy_lines = array_filter(array_map('trim', explode("\n", $cancellation_policy)));
                foreach ($policy_lines as $line): 
                ?>
                    <li><?php echo htmlspecialchars($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Footer Section -->
        <div class="invoice-footer">
            <div class="signature-section">
                <div class="signature-line">
                    <p>_____________________</p>
                    <p><strong><?php echo htmlspecialchars($company_name); ?></strong></p>
                    <p>Authorized Signature</p>
                </div>
            </div>
            <div class="thank-you-section">
                <p><strong>Thank you for choosing <?php echo htmlspecialchars($company_name); ?>!</strong></p>
                <p>For any queries, please contact us at: <?php echo htmlspecialchars($company_phone); ?></p>
                <?php if ($company_email): ?>
                    <p>Email: <?php echo htmlspecialchars($company_email); ?></p>
                <?php endif; ?>
            </div>
            <div class="disclaimer-note">
                <p><?php echo nl2br(htmlspecialchars($invoice_disclaimer)); ?></p>
            </div>
        </div>
    </div>
</div>

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
                
                <!-- Advance Payment Status Toggle -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="quick-action-section">
                            <h6 class="fw-bold mb-3 text-dark">
                                <i class="fas fa-money-check-alt text-primary me-2"></i>
                                Advance Payment Status
                            </h6>
                            <form method="POST" action="" class="advance-payment-form">
                                <input type="hidden" name="action" value="toggle_advance_payment">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-8">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="advance_payment_received" name="advance_payment_received" 
                                                   value="1" <?php echo ($booking['advance_payment_received'] == 1) ? 'checked' : ''; ?>
                                                   style="width: 3em; height: 1.5em; cursor: pointer;">
                                            <label class="form-check-label fw-semibold ms-2" for="advance_payment_received" style="cursor: pointer;">
                                                <strong>Advance Payment Received</strong>
                                                <small class="text-muted d-block">Check this box if the customer has paid the advance payment</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-success w-100 px-4">
                                            <i class="fas fa-save me-2"></i> Save Status
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Current Status: 
                                    <?php if (!empty($booking['advance_payment_received'])): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Received
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times-circle me-1"></i> Not Received
                                        </span>
                                    <?php endif; ?>
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
        <?php 
        $services_count = count($booking['services']);
        if ($services_count > 0): 
            // Calculate total services cost
            $services_total_display = array_sum(array_column($booking['services'], 'price'));
        ?>
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
                                <td class="fw-semibold service-info-cell">
                                    <div>
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                        <?php if (!empty($service['category'])): ?>
                                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($service['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($service['description'])): ?>
                                        <small class="service-description"><?php echo htmlspecialchars($service['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-primary service-price-cell"><?php echo formatCurrency($service['price']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($services_count > 1): ?>
                        <tfoot>
                            <tr class="table-light border-top border-2">
                                <td class="text-end fw-bold">Total Additional Services:</td>
                                <td class="text-end">
                                    <strong class="text-success fs-5"><?php echo formatCurrency($services_total_display); ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
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
                        <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted">Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                        </div>
                        <?php endif; ?>
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
                        
                        <?php if (!empty($booking['advance_payment_received'])): ?>
                        <div class="alert alert-success mt-2 mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block fw-semibold mb-1">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Advance Payment Received
                                    </small>
                                </div>
                                <h5 class="mb-0 fw-bold"><?php echo formatCurrency($advance['amount']); ?></h5>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger mt-2 mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block fw-semibold mb-1">
                                        <i class="fas fa-times-circle me-1"></i>
                                        Advance Payment Not Received
                                    </small>
                                </div>
                                <h5 class="mb-0 fw-bold"><?php echo formatCurrency(0); ?></h5>
                            </div>
                        </div>
                        <?php endif; ?>
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

/* Service Description Styles */
.service-description {
    display: block;
    margin-top: 0.5rem;
    margin-left: 2rem;
    font-size: 0.875rem;
    color: #6c757d;
    line-height: 1.4;
}

.service-description-print {
    font-weight: 500;
    color: #666;
    font-size: 8.5px;
    line-height: 1.2;
}

.service-info-cell {
    vertical-align: top;
}

.service-price-cell {
    vertical-align: top;
}

/* Print Invoice Styles - Enhanced for Better Visibility & One-Page Layout */
.print-invoice-only {
    display: none;
}

.invoice-container {
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    line-height: 1.2;
    font-weight: 500;
}

.invoice-header {
    border-bottom: 3px solid #4CAF50;
    padding-bottom: 4px;
    margin-bottom: 6px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 3px;
}

.company-logo-space {
    text-align: right;
    flex-shrink: 0;
    margin-left: 10px;
}

.company-logo-img {
    max-width: 150px;
    max-height: 50px;
    object-fit: contain;
}

.logo-placeholder {
    border: 2px solid #4CAF50;
    padding: 8px 20px;
    display: inline-block;
    font-weight: 900;
    font-size: 14px;
    color: #2E7D32;
    background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
}

.company-info {
    text-align: left;
    flex: 1;
}

.company-name {
    font-size: 18px;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0px;
    color: #1B5E20;
    line-height: 1.2;
}

.company-details {
    font-size: 10px;
    margin: 2px 0;
    font-weight: 600;
    color: #2E7D32;
    line-height: 1.3;
}

.invoice-title {
    text-align: center;
    border-top: 2px solid #4CAF50;
    border-bottom: 2px solid #4CAF50;
    padding: 3px 0;
    margin-top: 3px;
    background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
}

.invoice-title h2 {
    font-size: 12px;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #1B5E20;
}

.invoice-details-bar {
    display: flex;
    justify-content: space-between;
    background: linear-gradient(135deg, #FFF9C4 0%, #FFF59D 100%);
    padding: 4px 8px;
    margin-bottom: 5px;
    border: 1px solid #F57C00;
}

.invoice-detail-item {
    font-size: 10px;
    font-weight: 700;
    color: #E65100;
}

.invoice-detail-item strong {
    font-weight: 900;
}

.customer-section {
    margin-bottom: 5px;
    border: 1px solid #42A5F5;
    padding: 5px;
    background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
}

.customer-section h3 {
    font-size: 10px;
    font-weight: 900;
    margin: 0 0 4px 0;
    padding-bottom: 2px;
    border-bottom: 1px solid #1976D2;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #0D47A1;
}

.customer-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px;
}

.info-row {
    display: flex;
    font-size: 10px;
    font-weight: 500;
    line-height: 1.3;
}

.info-label {
    font-weight: 900;
    min-width: 90px;
    color: #0D47A1;
}

.info-value {
    flex: 1;
    font-weight: 600;
    color: #000;
}

.booking-table-section {
    margin-bottom: 5px;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    font-weight: 600;
    line-height: 1.3;
}

.invoice-table th {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: #fff;
    padding: 3px 4px;
    text-align: left;
    font-weight: 900;
    border: 1px solid #2E7D32;
    font-size: 10px;
}

.invoice-table td {
    padding: 3px 4px;
    border: 1px solid #2E7D32;
    font-weight: 600;
    color: #000;
}

.invoice-table td strong {
    font-weight: 900;
}

.invoice-table .text-center {
    text-align: center;
}

.invoice-table .text-right {
    text-align: right;
}

.invoice-table .subtotal-row td {
    background: linear-gradient(135deg, #FFF9C4 0%, #FFF59D 100%);
    font-weight: 900;
    font-size: 10px;
    color: #E65100;
}

.invoice-table .total-row td {
    background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
    color: #fff;
    font-weight: 900;
    font-size: 10px;
    padding: 4px;
}

.payment-calculation-section {
    margin-bottom: 5px;
    border: 2px solid #7E57C2;
    padding: 5px;
    background: linear-gradient(135deg, #F3E5F5 0%, #E1BEE7 100%);
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table td {
    padding: 2px 0;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.3;
}

.payment-label {
    width: 50%;
    font-weight: 900;
    color: #4A148C;
}

.payment-value {
    text-align: right;
    font-size: 10px;
    font-weight: 900;
    color: #6A1B9A;
}

.payment-value-words {
    text-align: right;
    font-style: italic;
    font-weight: 700;
}

.due-amount-row td {
    border-top: 2px solid #7E57C2;
    padding-top: 4px;
    font-size: 10px;
    font-weight: 900;
}

.note-section {
    background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
    border: 1px solid #EF6C00;
    padding: 4px;
    margin-bottom: 5px;
}

.note-section h3 {
    font-size: 10px;
    font-weight: 900;
    margin: 0 0 3px 0;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #E65100;
}

.note-section ul {
    margin: 0;
    padding-left: 12px;
    font-size: 8.5px;
    line-height: 1.3;
    font-weight: 600;
    color: #BF360C;
}

.note-section li {
    margin-bottom: 1px;
}

.invoice-footer {
    border-top: 2px solid #4CAF50;
    padding-top: 4px;
}

.signature-section {
    margin-bottom: 4px;
}

.signature-line {
    text-align: right;
    font-size: 9px;
    font-weight: 700;
}

.signature-line p {
    margin: 1px 0;
}

.signature-line strong {
    font-weight: 900;
}

.thank-you-section {
    text-align: center;
    font-size: 9px;
    font-weight: 600;
}

.thank-you-section p {
    margin: 1px 0;
}

.thank-you-section strong {
    font-weight: 900;
}

.disclaimer-note {
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px solid #F57C00;
    text-align: center;
    font-size: 8.5px;
    font-weight: 600;
    color: #E65100;
    font-style: italic;
}

/* Print Styles - Optimized for Single Page Output with Readable Font Sizes */
@media print {
    /* Hide all non-invoice content */
    body * {
        visibility: hidden;
    }
    
    .print-invoice-only,
    .print-invoice-only * {
        visibility: visible;
    }
    
    .print-invoice-only {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    /* A4 Page Settings - Optimized margins for single-page readable layout */
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }
    
    body {
        margin: 0;
        padding: 0;
        color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    .invoice-container {
        width: 100%;
        max-width: 186mm;
        margin: 0 auto;
        padding: 0;
        font-size: 10pt;
        line-height: 1.3;
    }
    
    /* Critical: Prevent page breaks and keep on one page */
    .invoice-container {
        page-break-after: avoid !important;
        page-break-before: avoid !important;
        break-inside: avoid-page !important;
        break-after: avoid-page !important;
        break-before: avoid-page !important;
    }
    
    .invoice-header,
    .invoice-details-bar,
    .customer-section,
    .booking-table-section,
    .payment-calculation-section,
    .note-section,
    .invoice-footer {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    
    /* Header - Compact but readable */
    .invoice-header {
        padding-bottom: 4px;
        margin-bottom: 5px;
        border-bottom: 2px solid #333 !important;
    }
    
    .header-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 3px;
    }
    
    .company-logo-space {
        text-align: center;
        margin-bottom: 5px;
        order: -1;
    }
    
    .company-logo-img {
        max-width: 180px;
        max-height: 80px;
    }
    
    .logo-placeholder {
        padding: 8px 20px;
        font-size: 14pt;
        border: 1px solid #333;
    }
    
    .company-info {
        text-align: center;
        width: 100%;
    }
    
    .company-name {
        font-size: 16pt;
        margin: 0;
        font-weight: bold;
        letter-spacing: 0px;
        line-height: 1.2;
    }
    
    .company-details {
        font-size: 9pt;
        margin: 2px 0;
        line-height: 1.3;
    }
    
    .invoice-title {
        padding: 3px 0;
        margin-top: 3px;
        border-top: 1px solid #333;
        border-bottom: 1px solid #333;
        background: #f5f5f5 !important;
    }
    
    .invoice-title h2 {
        font-size: 11pt;
        margin: 0;
        font-weight: bold;
    }
    
    /* Invoice details bar - readable */
    .invoice-details-bar {
        padding: 4px 8px;
        margin-bottom: 5px;
        border: 1px solid #666 !important;
        background: #f9f9f9 !important;
        display: flex;
        justify-content: space-between;
    }
    
    .invoice-detail-item {
        font-size: 9pt;
        font-weight: bold;
    }
    
    /* Customer section - readable and compact */
    .customer-section {
        margin-bottom: 5px;
        padding: 5px;
        border: 1px solid #666 !important;
        background: #f9f9f9 !important;
    }
    
    .customer-section h3 {
        font-size: 10pt;
        margin: 0 0 3px 0;
        padding-bottom: 2px;
        border-bottom: 1px solid #999;
        font-weight: bold;
    }
    
    .customer-info-grid {
        gap: 2px;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    
    .info-row {
        font-size: 9pt;
        line-height: 1.4;
    }
    
    .info-label {
        min-width: 85px;
        font-weight: bold;
    }
    
    .info-value {
        font-weight: normal;
    }
    
    /* Booking table - larger, readable fonts */
    .booking-table-section {
        margin-bottom: 5px;
    }
    
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }
    
    .invoice-table th {
        padding: 4px 5px;
        font-size: 9pt;
        border: 1px solid #333 !important;
        background: #e0e0e0 !important;
        font-weight: bold;
        text-align: left;
    }
    
    .invoice-table td {
        padding: 3px 5px;
        border: 1px solid #666 !important;
        font-size: 9pt;
        line-height: 1.3;
    }
    
    .invoice-table .subtotal-row td {
        font-size: 9pt;
        background: #f5f5f5 !important;
        font-weight: bold;
    }
    
    .invoice-table .total-row td {
        font-size: 10pt;
        padding: 4px 5px;
        background: #e0e0e0 !important;
        font-weight: bold;
    }
    
    /* Payment calculation - readable */
    .payment-calculation-section {
        margin-bottom: 5px;
        padding: 5px;
        border: 1px solid #666 !important;
        background: #f9f9f9 !important;
    }
    
    .payment-table {
        width: 100%;
    }
    
    .payment-table td {
        padding: 2px 0;
        font-size: 9pt;
        line-height: 1.4;
    }
    
    .payment-label {
        font-weight: bold;
    }
    
    .payment-value {
        font-size: 9pt;
        font-weight: bold;
        text-align: right;
    }
    
    .payment-value-words {
        font-size: 9pt;
        font-style: italic;
    }
    
    .due-amount-row td {
        padding-top: 3px;
        font-size: 10pt;
        border-top: 1px solid #666;
        font-weight: bold;
    }
    
    /* Note section - concise but readable */
    .note-section {
        padding: 4px;
        margin-bottom: 5px;
        border: 1px solid #666 !important;
        background: #fffbf0 !important;
    }
    
    .note-section h3 {
        font-size: 9pt;
        margin: 0 0 2px 0;
        font-weight: bold;
    }
    
    .note-section ul {
        padding-left: 15px;
        font-size: 8pt;
        line-height: 1.3;
        margin: 2px 0;
    }
    
    .note-section li {
        margin-bottom: 1px;
    }
    
    /* Footer - readable */
    .invoice-footer {
        padding-top: 4px;
        border-top: 2px solid #333 !important;
    }
    
    .signature-section {
        margin-bottom: 3px;
    }
    
    .signature-line {
        font-size: 9pt;
        text-align: right;
    }
    
    .signature-line p {
        margin: 2px 0;
        line-height: 1.3;
    }
    
    .thank-you-section {
        font-size: 8pt;
        text-align: center;
        line-height: 1.3;
    }
    
    .thank-you-section p {
        margin: 2px 0;
    }
    
    .disclaimer-note {
        margin-top: 3px;
        padding-top: 3px;
        font-size: 8pt;
        border-top: 1px solid #ccc;
        text-align: center;
        line-height: 1.3;
    }
    
    /* Service description in print - readable */
    .service-description-print {
        font-size: 8pt;
        line-height: 1.3;
        color: #666 !important;
    }
    
    /* Remove decorative elements to save space */
    .invoice-header,
    .customer-section,
    .booking-table-section,
    .payment-calculation-section,
    .note-section,
    .invoice-footer {
        box-shadow: none !important;
        text-shadow: none !important;
    }
    
    /* Ensure text is black for good contrast */
    .invoice-container,
    .invoice-container p,
    .invoice-container td,
    .invoice-container th,
    .invoice-container span,
    .invoice-container strong,
    .invoice-container h1,
    .invoice-container h2,
    .invoice-container h3 {
        color: #000 !important;
    }
    
    /* Simplified backgrounds for print */
    .invoice-title,
    .invoice-details-bar,
    .customer-section,
    .note-section,
    .payment-calculation-section,
    .subtotal-row td,
    .total-row td,
    .invoice-table th {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
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
