<?php
$page_title = 'View Booking Details';
// Require PHP utilities before any HTML output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$current_user = getCurrentUser();

$db = getDB();
$success_message = '';
$error_message = '';
$new_vendor_wa_url = '';
$new_vendor_email_sent = false;
$is_vendor_flash = false;

// Display flash message from previous redirect (e.g., after creating a booking)
if (!empty($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    $is_vendor_flash = true;
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    $is_vendor_flash = true;
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_vendor_wa_url'])) {
    $new_vendor_wa_url = $_SESSION['flash_vendor_wa_url'];
    unset($_SESSION['flash_vendor_wa_url']);
}
if (!empty($_SESSION['flash_vendor_email_sent'])) {
    $new_vendor_email_sent = $_SESSION['flash_vendor_email_sent'];
    unset($_SESSION['flash_vendor_email_sent']);
}

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
$status_vars = calculateBookingStatusVariables($booking);
extract($status_vars); // Extract variables: booking_status_display, booking_status_color, payment_status_display, payment_status_color, payment_status_icon


// Handle payment request actions
$initial_tab = 'tab-overview'; // default active tab
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_admin_service') {
        // Handle adding admin service
        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $price = max(0, floatval($_POST['price'] ?? 0));
        
        if (empty($service_name)) {
            $error_message = 'Service name is required.';
        } elseif ($price <= 0) {
            $error_message = 'Price must be greater than 0.';
        } else {
            $service_id = addAdminService($booking_id, $service_name, $description, $quantity, $price);
            
            if ($service_id) {
                logActivity($current_user['id'], 'Added admin service', 'bookings', $booking_id, "Added service: {$service_name} (Qty: {$quantity}, Price: {$price})");
                $success_message = 'Admin service added successfully!';
                $initial_tab = 'tab-services';
                
                // Re-fetch booking to get updated services and totals
                $booking = getBookingDetails($booking_id);
                $status_vars = calculateBookingStatusVariables($booking);
                extract($status_vars);
            } else {
                $error_message = 'Failed to add admin service. Please check error logs or run fix_admin_services.php to update database schema.';
                $initial_tab = 'tab-services';
            }
        }
    } elseif ($action === 'delete_admin_service') {
        // Handle deleting admin service
        $service_id = intval($_POST['service_id'] ?? 0);
        
        if ($service_id > 0) {
            if (deleteAdminService($service_id)) {
                logActivity($current_user['id'], 'Deleted admin service', 'bookings', $booking_id, "Deleted admin service ID: {$service_id}");
                $success_message = 'Admin service deleted successfully!';
                $initial_tab = 'tab-services';
                
                // Re-fetch booking to get updated services and totals
                $booking = getBookingDetails($booking_id);
                $status_vars = calculateBookingStatusVariables($booking);
                extract($status_vars);
            } else {
                $error_message = 'Failed to delete admin service. Please try again.';
                $initial_tab = 'tab-services';
            }
        } else {
            $error_message = 'Invalid service ID.';
            $initial_tab = 'tab-services';
        }
    } elseif ($action === 'send_payment_request_email') {
        // Send payment request via email
        if (!empty($booking['email'])) {
            $result = sendBookingNotification($booking_id, 'payment_request');
            if ($result['user']) {
                $success_message = 'Payment request sent successfully via email to ' . htmlspecialchars($booking['email']);
                logActivity($current_user['id'], 'Sent payment request via email', 'bookings', $booking_id, "Payment request email sent for booking: {$booking['booking_number']}");
            } else {
                $error_message = 'Failed to send payment request email. Please <a href="' . BASE_URL . '/admin/settings/index.php#email" class="alert-link">check email settings</a>.';
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
    } elseif ($action === 'send_booking_confirmation_whatsapp') {
        // Send booking confirmation via WhatsApp (after advance payment received)
        if (!empty($booking['phone'])) {
            $success_message = 'Opening WhatsApp to send booking confirmation...';
            logActivity($current_user['id'], 'Initiated WhatsApp booking confirmation', 'bookings', $booking_id, "WhatsApp booking confirmation initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Customer phone number not found. Cannot send WhatsApp message.';
        }
    } elseif ($action === 'send_venue_provider_whatsapp') {
        // Notify venue provider about confirmed booking via WhatsApp
        if (!empty($booking['venue_contact_phone'])) {
            $success_message = 'Opening WhatsApp to notify venue provider...';
            logActivity($current_user['id'], 'Initiated WhatsApp venue provider notification', 'bookings', $booking_id, "WhatsApp venue provider notification initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Venue contact phone number not found. Please add a contact phone to the venue.';
        }
    } elseif ($action === 'send_booking_confirmation_email') {
        // Send booking confirmation via email (after advance payment received)
        if (!empty($booking['email'])) {
            $result = sendBookingNotification($booking_id, 'confirmed');
            if ($result['user']) {
                $success_message = 'Booking confirmation sent successfully via email to ' . htmlspecialchars($booking['email']);
                logActivity($current_user['id'], 'Sent booking confirmation via email', 'bookings', $booking_id, "Booking confirmation email sent for booking: {$booking['booking_number']}");
            } else {
                $error_message = 'Failed to send booking confirmation email. Please <a href="' . BASE_URL . '/admin/settings/index.php#email" class="alert-link">check email settings</a>.';
            }
        } else {
            $error_message = 'Customer email not found. Cannot send email.';
        }
    } elseif ($action === 'add_vendor_assignment') {
        $vendor_id_input    = intval($_POST['vendor_id'] ?? 0);
        $task_description   = trim($_POST['task_description'] ?? '');
        $assigned_amount    = max(0, floatval($_POST['assigned_amount'] ?? 0));
        $assignment_notes   = trim($_POST['assignment_notes'] ?? '');

        if ($vendor_id_input <= 0) {
            $_SESSION['flash_error'] = 'Please select a vendor.';
        } else {
            $assignment_id = addVendorAssignment($booking_id, $vendor_id_input, $task_description, $assigned_amount, $assignment_notes);
            if ($assignment_id) {
                logActivity($current_user['id'], 'Added vendor assignment', 'booking_vendor_assignments', $booking_id, "Assigned vendor ID {$vendor_id_input}: {$task_description}");
                $_SESSION['flash_success'] = 'Vendor assigned successfully!';
                $new_vendor = getVendor($vendor_id_input);
                if ($new_vendor && !empty($new_vendor['phone'])) {
                    $_SESSION['flash_vendor_wa_url'] = buildVendorAssignmentWhatsAppUrl($new_vendor['name'], $new_vendor['phone'], $booking);
                }
                if ($new_vendor && !empty($new_vendor['email'])) {
                    $_SESSION['flash_vendor_email_sent'] = sendVendorAssignmentEmail($new_vendor['name'], $new_vendor['email'], $booking);
                }
            } else {
                $_SESSION['flash_error'] = 'Failed to add vendor assignment. Please try again.';
            }
        }
        header('Location: view.php?id=' . urlencode($booking_id) . '#vendor-assignments');
        exit;
    } elseif ($action === 'update_vendor_assignment_status') {
        $assignment_id     = intval($_POST['assignment_id'] ?? 0);
        $assignment_status = trim($_POST['assignment_status'] ?? '');

        if ($assignment_id > 0 && updateVendorAssignmentStatus($assignment_id, $assignment_status)) {
            logActivity($current_user['id'], 'Updated vendor assignment status', 'booking_vendor_assignments', $booking_id, "Assignment {$assignment_id} status set to {$assignment_status}");
            $_SESSION['flash_success'] = 'Vendor assignment status updated.';
        } else {
            $_SESSION['flash_error'] = 'Failed to update vendor assignment status.';
        }
        header('Location: view.php?id=' . urlencode($booking_id) . '#vendor-assignments');
        exit;
    } elseif ($action === 'delete_vendor_assignment') {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);

        if ($assignment_id > 0 && deleteVendorAssignment($assignment_id)) {
            logActivity($current_user['id'], 'Deleted vendor assignment', 'booking_vendor_assignments', $booking_id, "Deleted assignment ID {$assignment_id}");
            $_SESSION['flash_success'] = 'Vendor assignment removed.';
        } else {
            $_SESSION['flash_error'] = 'Failed to remove vendor assignment.';
        }
        header('Location: view.php?id=' . urlencode($booking_id) . '#vendor-assignments');
        exit;
    }
}

// Include the HTML header only after all PHP processing (and potential redirects)
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success_message && !$is_vendor_flash): ?>
    <div class="alert alert-success alert-dismissible fade show" id="flash-success-alert" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message && !$is_vendor_flash): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Print-Only Invoice Layout -->
<?php
// Calculate payment details using centralized function (single source of truth)
$payment_summary = calculatePaymentSummary($booking_id);
$total_paid = $payment_summary['total_paid'];
$balance_due = $payment_summary['due_amount'];
$advance = [
    'amount' => $payment_summary['advance_amount'],
    'percentage' => $payment_summary['advance_percentage']
];

// Calculate vendors total for display in the payment breakdown
$vendors_total = $payment_summary['vendors_total'];

// Get vendor assignments for print invoice and display
$vendor_assignments = getBookingVendorAssignments($booking_id);

// Get available vendors for the assignment form (used in Quick Check panel)
$all_vendors = getAvailableVendors($booking['event_date']);

// Get payment transactions for display
$payment_transactions = getBookingPayments($booking_id);

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

// Separate user and admin services for display in print invoice
// Note: This logic is duplicated later for the screen view section (around line 930)
// to maintain separation of concerns between print and screen displays
$user_services = [];
$admin_services = [];
if (!empty($booking['services']) && is_array($booking['services'])) {
    foreach ($booking['services'] as $service) {
        if (isset($service['added_by']) && $service['added_by'] === 'admin') {
            $admin_services[] = $service;
        } else {
            $user_services[] = $service;
        }
    }
}
$admin_services_total = 0;
foreach ($admin_services as $_svc) {
    $admin_services_total += floatval($_svc['price'] ?? 0) * intval($_svc['quantity'] ?? 1);
}

// Resolve display time – prefer saved start/end times; fall back to shift defaults so that
// the booking time is always visible in both the screen view and the print invoice.
$shift_default_times  = getShiftDefaultTimes($booking['shift']);
$display_start_time   = !empty($booking['start_time']) ? $booking['start_time'] : $shift_default_times['start'];
$display_end_time     = !empty($booking['end_time'])   ? $booking['end_time']   : $shift_default_times['end'];
$has_display_time     = !empty($display_start_time) && !empty($display_end_time);
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
                <small class="text-muted">(<?php echo convertToNepaliDate($booking['created_at']); ?>)</small>
            </div>
            <div class="invoice-detail-item">
                <strong>Booking Date:</strong> <?php echo date('F d, Y', strtotime($booking['event_date'])); ?>
                <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small>
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
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?> <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small> — <?php echo ucfirst($booking['shift']); ?></span>
                </div>
                <?php if ($has_display_time): ?>
                <div class="info-row">
                    <span class="info-label">Event Time:</span>
                    <span class="info-value">
                        <?php echo formatBookingTime($display_start_time); ?> – <?php echo formatBookingTime($display_end_time); ?>
                    </span>
                </div>
                <?php endif; ?>
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
                            <td>
                                <?php echo htmlspecialchars($menu['menu_name']); ?>
                                <?php if (!empty($menu['items'])): ?>
                                    <?php
                                    $items_by_cat = [];
                                    foreach ($menu['items'] as $item) {
                                        $cat = !empty($item['category']) ? htmlspecialchars($item['category']) : '';
                                        $items_by_cat[$cat][] = htmlspecialchars($item['item_name']);
                                    }
                                    $cat_parts = [];
                                    foreach ($items_by_cat as $cat => $names) {
                                        $part = ($cat !== '' ? '<strong>' . $cat . ':</strong> ' : '') . implode(', ', $names);
                                        $cat_parts[] = $part;
                                    }
                                    ?>
                                    <br><span class="menu-items-print"><?php echo implode(' | ', $cat_parts); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $menu['number_of_guests']; ?></td>
                            <td class="text-right"><?php echo number_format($menu['price_per_person'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($menu['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- User Services / Additional Items -->
                    <?php if (!empty($user_services)): ?>
                        <?php foreach ($user_services as $service): ?>
                        <?php 
                            $service_price = floatval($service['price'] ?? 0);
                            $service_qty = intval($service['quantity'] ?? 1);
                            $service_total = $service_price * $service_qty;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($additional_items_label); ?></strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                <?php if (!empty($service['category'])): ?>
                                    <span class="service-category-print">[<?php echo htmlspecialchars($service['category']); ?>]</span>
                                <?php endif; ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $service_qty; ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo number_format($service_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Admin Added Services -->
                    <?php if (!empty($admin_services)): ?>
                        <?php foreach ($admin_services as $service): ?>
                        <?php 
                            $service_price = floatval($service['price'] ?? 0);
                            $service_qty = intval($service['quantity'] ?? 1);
                            $service_total = $service_price * $service_qty;
                        ?>
                        <tr>
                            <td>
                                <strong>Admin Service</strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $service_qty; ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo number_format($service_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($user_services) && empty($admin_services)): ?>
                        <tr class="no-services-row">
                            <td colspan="4" class="text-center text-muted"><em>No additional services selected</em></td>
                        </tr>
                    <?php endif; ?>
                    
                    <!-- Vendor Assignments (included in subtotal) -->
                    <?php
                    $invoice_vendors_total = 0;
                    $active_vendor_assignments = [];
                    if (!empty($vendor_assignments)) {
                        foreach ($vendor_assignments as $va) {
                            if (floatval($va['assigned_amount']) > 0 && $va['status'] !== 'cancelled') {
                                $active_vendor_assignments[] = $va;
                                $invoice_vendors_total += floatval($va['assigned_amount']);
                            }
                        }
                    }
                    $has_invoice_vendors = !empty($active_vendor_assignments);
                    ?>
                    <?php if ($has_invoice_vendors): ?>
                        <?php foreach ($active_vendor_assignments as $va): ?>
                        <tr>
                            <td>
                                <strong>Vendors</strong> - <?php echo htmlspecialchars(getVendorTypeLabel($va['vendor_type'] ?? '')); ?>
                                <?php if (!empty($va['task_description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($va['task_description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-right"><?php echo number_format(floatval($va['assigned_amount']), 2); ?></td>
                            <td class="text-right"><?php echo number_format(floatval($va['assigned_amount']), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Subtotal (hall + menus + services + vendors) -->
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
                <?php if ($has_invoice_vendors): ?>
                <tr>
                    <td class="payment-label">Vendors Total:</td>
                    <td class="payment-value"><?php echo formatCurrency($invoice_vendors_total); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="payment-label">Advance Payment Required (<?php echo $advance['percentage']; ?>%):</td>
                    <td class="payment-value"><?php echo formatCurrency($advance['amount']); ?></td>
                </tr>
                <tr>
                    <td class="payment-label">Advance Payment Received:</td>
                    <td class="payment-value"><?php 
                        // Display advance amount only if marked as received by admin
                        if ($booking['advance_payment_received'] === 1) {
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
                    <td class="payment-value-words"><?php echo numberToWords($balance_due); ?> Only</td>
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

<!-- Quick Check Panel -->
<?php 
// Prepare WhatsApp data
$clean_phone = !empty($booking['phone']) ? preg_replace('/[^0-9]/', '', $booking['phone']) : '';

// Calculate advance payment based on configured percentage
$advance = calculateAdvancePayment($booking['grand_total']);

// Get payment methods for this booking
$whatsapp_payment_methods = getBookingPaymentMethods($booking_id);

$whatsapp_shift_time = getBookingShiftTimeDisplay($booking);
$whatsapp_text = "Dear " . $booking['full_name'] . ",\n\n" .
    "Your booking (ID: " . $booking['booking_number'] . ") for " . $booking['venue_name'] . " on " . convertToNepaliDate($booking['event_date']) . " is almost confirmed.\n\n" .
    "🕐 Shift / Time: " . $whatsapp_shift_time . "\n" .
    "💰 Total Amount: " . formatCurrency($booking['grand_total']) . "\n" .
    "💵 Advance Payment (" . $advance['percentage'] . "%): " . formatCurrency($advance['amount']) . "\n\n" .
    "📍 Venue Location: " . strip_tags($booking['location']) . "\n";
if (!empty($booking['venue_address'])) {
    $whatsapp_text .= "🏠 Full Address: " . strip_tags($booking['venue_address']) . "\n";
}
if (!empty($booking['map_link'])) {
    $whatsapp_text .= "🗺️ Google Map: " . strip_tags($booking['map_link']) . "\n";
}
$whatsapp_text .= "\n";

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

$whatsapp_text .= "Thank you!\n\nWarm regards,\n*" . strip_tags($company_name) . "*";

// Build booking confirmation WhatsApp message (shown after advance payment is received)
$booking_confirmation_vendors = $vendor_assignments;
$site_name_wa = !empty($company_name) ? $company_name : getSetting('site_name', 'Venue Booking System');

$confirmation_text = "✅ *Booking Confirmation*\n\n";
$confirmation_text .= "Dear " . strip_tags($booking['full_name']) . ",\n\n";
$confirmation_text .= "We are pleased to confirm your booking with " . strip_tags($site_name_wa) . ". Please find your booking details below:\n\n";
$confirmation_text .= "Booking Status: *Confirmed* ✅\n";
$confirmation_text .= "Booking Number: " . strip_tags($booking['booking_number']) . "\n";
$confirmation_text .= "Booking Date: " . convertToNepaliDate($booking['created_at']) . "\n";
$confirmation_text .= "Program Date: " . convertToNepaliDate($booking['event_date']) . "\n";
$confirmation_text .= "Shift / Time: " . getBookingShiftTimeDisplay($booking) . "\n";
$confirmation_text .= "Event Type: " . strip_tags($booking['event_type']) . "\n\n";
$confirmation_text .= "🏛️ *Venue Details*\n";
$confirmation_text .= "Venue Name: " . strip_tags($booking['venue_name']) . "\n";
$confirmation_text .= "Venue Location: " . strip_tags($booking['location']) . "\n";
if (!empty($booking['venue_address'])) {
    $confirmation_text .= "Full Address: " . strip_tags($booking['venue_address']) . "\n";
}
if (!empty($booking['map_link'])) {
    $confirmation_text .= "Google Map: " . strip_tags($booking['map_link']) . "\n";
}
if (!empty($booking_confirmation_vendors)) {
    $confirmation_text .= "\n👥 *Assigned Vendors*\n";
    foreach ($booking_confirmation_vendors as $va) {
        $confirmation_text .= getVendorTypeLabel($va['vendor_type']) . " Name: " . strip_tags($va['vendor_name']) . "\n";
        if (!empty($va['vendor_phone'])) {
            $confirmation_text .= getVendorTypeLabel($va['vendor_type']) . " Phone: " . strip_tags($va['vendor_phone']) . "\n";
        }
    }
}
$confirmation_text .= "\nWarm regards,\n*" . strip_tags($site_name_wa) . "*";

// Build venue provider WhatsApp URL
$venue_provider_wa_url = buildVenueProviderWhatsAppUrl($booking);
$clean_venue_phone = preg_replace('/[^0-9]/', '', $booking['venue_contact_phone'] ?? '');
// Pre-compute variables for tabbed layout
// (admin_services and user_services already computed above for print invoice)
$user_services_count = count($user_services);
$user_services_total = 0;
foreach ($user_services as $_svc) {
    $user_services_total += floatval($_svc['price'] ?? 0) * intval($_svc['quantity'] ?? 1);
}
$booking_payment_methods = getBookingPaymentMethods($booking_id);
$tab_services_count = count($user_services) + count($booking['menus'] ?? []);
$tab_payments_count = count($payment_transactions);
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Quick Check</h5>
                <small class="opacity-75">Manage statuses &amp; send requests in one place</small>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">

                    <!-- Booking Status -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-circle-dot text-primary me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Booking Status</span>
                                <span class="badge bg-<?php echo $booking_status_color; ?> ms-auto" id="booking-status-badge">
                                    <?php echo $booking_status_display; ?>
                                </span>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-lock me-1"></i>
                                <strong>Updated – Read Only.</strong> Auto-set by Payment Status.
                            </small>
                        </div>
                    </div>

                    <!-- Advance Payment Status (auto-managed by Payment Status) -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-money-check-alt text-success me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Advance Payment</span>
                                <?php if ($booking['advance_payment_received'] === 1): ?>
                                    <span class="badge bg-success ms-auto" id="advance-payment-badge"><i class="fas fa-check-circle me-1"></i>Received</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-auto" id="advance-payment-badge"><i class="fas fa-times-circle me-1"></i>Not Received</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-lock me-1"></i>
                                <strong>Updated – Read Only.</strong> Auto-managed by Payment Status.
                            </small>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-credit-card text-info me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Payment Status</span>
                                <span class="badge bg-<?php echo $payment_status_color; ?> ms-auto" id="payment-status-badge">
                                    <?php echo $payment_status_display; ?>
                                </span>
                            </div>
                            <div class="payment-status-container">
                                <select class="form-select form-select-sm payment-status-select"
                                    id="payment-status-select"
                                    data-booking-id="<?php echo (int)$booking['id']; ?>"
                                    data-current-status="<?php echo htmlspecialchars($booking['payment_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <option value="pending" <?php echo ($booking['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo ($booking['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo ($booking['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo ($booking['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <small class="text-muted d-block mt-1">Flow: Pending → Partial → Paid. Auto-updates Booking &amp; Advance Payment.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Send Payment Request / Booking Confirmation -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <?php
                            // Show "Booking Confirmation" only when advance payment is received AND payment status is not pending
                            $show_confirmation = ($booking['advance_payment_received'] === 1 && strtolower($booking['payment_status']) !== 'pending');
                            ?>
                            <!-- Booking Confirmation (shown after advance payment received and payment status is not pending) -->
                            <div id="booking-confirmation-section" <?php echo $show_confirmation ? '' : 'style="display:none"'; ?>>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Booking Confirmation</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="" class="flex-fill">
                                        <input type="hidden" name="action" value="send_booking_confirmation_email">
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-envelope me-1"></i> Email
                                        </button>
                                    </form>
                                    <form method="POST" action="" id="confirmationWhatsappForm" class="flex-fill">
                                        <input type="hidden" name="action" value="send_booking_confirmation_whatsapp">
                                        <button type="submit" class="btn btn-success btn-sm w-100" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                            <i class="fab fa-whatsapp me-1"></i> ✅ Booking Confirmation
                                        </button>
                                    </form>
                                </div>
                                <?php if (empty($booking['phone']) && empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> No contact info available
                                    </small>
                                <?php elseif (empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Phone not available
                                    </small>
                                <?php elseif (empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Email not available
                                    </small>
                                <?php endif; ?>
                            </div>
                            <!-- Send Payment Request (shown when payment status is pending or advance payment not yet received) -->
                            <div id="payment-request-section" <?php echo $show_confirmation ? 'style="display:none"' : ''; ?>>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-paper-plane text-info me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Send Payment Request</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="" class="flex-fill">
                                        <input type="hidden" name="action" value="send_payment_request_email">
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-envelope me-1"></i> Email
                                        </button>
                                    </form>
                                    <form method="POST" action="" id="whatsappForm" class="flex-fill">
                                        <input type="hidden" name="action" value="send_payment_request_whatsapp">
                                        <button type="submit" class="btn btn-outline-success btn-sm w-100" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                            <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                        </button>
                                    </form>
                                </div>
                                <?php if (empty($booking['email']) && empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> No contact info available
                                    </small>
                                <?php elseif (empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Email not available
                                    </small>
                                <?php elseif (empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Phone not available
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Venue Provider Notification -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-building text-warning me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Notify Venue Provider</span>
                            </div>
                            <form method="POST" action="" id="venueProviderWhatsappForm" class="d-grid">
                                <input type="hidden" name="action" value="send_venue_provider_whatsapp">
                                <button type="submit" class="btn btn-warning btn-sm w-100"
                                    <?php echo empty($booking['venue_contact_phone']) ? 'disabled' : ''; ?>>
                                    <i class="fab fa-whatsapp me-1"></i> Notify Venue via WhatsApp
                                </button>
                            </form>
                            <?php if (empty($booking['venue_contact_phone'])): ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No venue contact phone. <a href="<?php echo BASE_URL; ?>/admin/venues/" class="alert-link">Update venue</a>.
                                </small>
                            <?php else: ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-phone me-1"></i>
                                    <?php echo htmlspecialchars($booking['venue_contact_phone']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Vendor Assignments -->
                <?php
                // Build grouped vendor data for JS two-step selection (only types with available vendors)
                $vendors_by_type = [];
                foreach ($all_vendors as $v) {
                    $vendors_by_type[$v['type']][] = [
                        'id'          => $v['id'],
                        'name'        => $v['name'],
                        'description' => $v['short_description'] ?? '',
                        'city'        => $v['city_name'] ?? '',
                    ];
                }
                // Filter vendor types list to only those that have available vendors
                $vendor_types_available = array_filter(getVendorTypes(), function($vt) use ($vendors_by_type) {
                    return isset($vendors_by_type[$vt['slug']]);
                });
                ?>
                <div class="border-top mt-3 pt-2" id="vendor-assignments">
                    <!-- Section Header -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-user-tie text-secondary" style="font-size:.85rem;"></i>
                            <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.06em;">Vendor Assignments</span>
                            <?php if (!empty($vendor_assignments)): ?>
                                <span class="badge bg-secondary" style="font-size:.65rem;"><?php echo count($vendor_assignments); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($is_vendor_flash && $success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2 px-3 small" id="vendor-flash-success-alert" role="alert">
                        <i class="fas fa-check-circle me-1"></i><?php echo $success_message; ?>
                        <?php if (!empty($new_vendor_wa_url)): ?>
                            <a href="<?php echo htmlspecialchars($new_vendor_wa_url); ?>" target="_blank" rel="noopener noreferrer"
                               class="btn btn-sm btn-success ms-2 py-0 px-2">
                                <i class="fab fa-whatsapp me-1"></i>Notify
                            </a>
                        <?php endif; ?>
                        <?php if ($new_vendor_email_sent): ?>
                            <span class="badge bg-info ms-1"><i class="fas fa-envelope me-1"></i>Email sent</span>
                        <?php endif; ?>
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_vendor_flash && $error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" id="vendor-flash-error-alert" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($vendor_assignments)): ?>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered mb-0 align-middle" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap">Vendor</th>
                                    <th class="text-nowrap">Type</th>
                                    <th>Task</th>
                                    <th class="text-nowrap">Amt (<?php echo htmlspecialchars(getSetting('currency', 'NPR')); ?>)</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendor_assignments as $assignment): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <span class="fw-semibold"><?php echo htmlspecialchars($assignment['vendor_name']); ?></span>
                                        <?php if (!empty($assignment['vendor_phone'])): ?>
                                            <span class="text-muted ms-1" style="font-size:.75rem;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($assignment['vendor_phone']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap text-muted"><?php echo htmlspecialchars(getVendorTypeLabel($assignment['vendor_type'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($assignment['task_description']); ?>
                                        <?php if (!empty($assignment['notes'])): ?>
                                            <span class="text-muted ms-1" title="<?php echo htmlspecialchars($assignment['notes']); ?>"><i class="fas fa-sticky-note" style="font-size:.7rem;"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?php echo formatCurrency($assignment['assigned_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getVendorAssignmentStatusColor($assignment['status']); ?>" style="font-size:.7rem;">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <?php if (!empty($assignment['vendor_phone'])): ?>
                                        <?php $va_wa_url = buildVendorAssignmentWhatsAppUrl($assignment['vendor_name'], $assignment['vendor_phone'], $booking); ?>
                                        <?php if (!empty($va_wa_url)): ?>
                                        <a href="<?php echo htmlspecialchars($va_wa_url); ?>" target="_blank" rel="noopener noreferrer"
                                           class="btn btn-sm btn-outline-success py-0 px-1 me-1" title="Notify via WhatsApp" style="font-size:.75rem;">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline-block;" class="me-1">
                                            <input type="hidden" name="action" value="update_vendor_assignment_status">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <select name="assignment_status" class="form-select form-select-sm d-inline-block w-auto py-0"
                                                    style="font-size:.75rem;" onchange="this.form.submit()">
                                                <?php foreach (['assigned', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                                                    <option value="<?php echo $s; ?>" <?php echo ($assignment['status'] === $s) ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($s); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="POST" style="display:inline-block;"
                                              onsubmit="return confirm('Remove this vendor assignment?');">
                                            <input type="hidden" name="action" value="delete_vendor_assignment">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove" style="font-size:.75rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i>No vendors assigned yet.</p>
                    <?php endif; ?>

                    <!-- Add Vendor Assignment Form -->
                    <?php if (!empty($all_vendors)): ?>
                    <div class="border-top pt-2">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-plus-circle text-success me-1" style="font-size:.8rem;"></i>
                            <span class="fw-semibold text-muted" style="font-size:.78rem;">Assign a Vendor</span>
                        </div>
                        <form id="addVendorAssignmentForm" method="POST" action="">
                            <input type="hidden" name="action" value="add_vendor_assignment">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Type <span class="text-danger">*</span></label>
                                    <select id="vendorTypeSelect" class="form-select form-select-sm" style="min-width:130px;">
                                        <option value="">— Type —</option>
                                        <?php foreach ($vendor_types_available as $vt): ?>
                                            <option value="<?php echo htmlspecialchars($vt['slug']); ?>">
                                                <?php echo htmlspecialchars($vt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto d-none" id="vendorSelectWrapper">
                                    <label class="form-label mb-1 small fw-semibold">Vendor <span class="text-danger">*</span></label>
                                    <div>
                                        <select name="vendor_id" id="vendorSelect" class="form-select form-select-sm" style="min-width:150px;">
                                            <option value="">— Vendor —</option>
                                        </select>
                                        <div id="vendorInfoDisplay" class="d-none">
                                            <small id="vendorLocationInfo" class="text-muted" style="font-size:.72rem;"></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Task</label>
                                    <input type="text" name="task_description" id="taskDescriptionInput" class="form-control form-control-sm"
                                           style="min-width:140px;" placeholder="e.g., Photography">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Amount</label>
                                    <input type="number" name="assigned_amount" class="form-control form-control-sm"
                                           style="width:90px;" min="0" step="0.01" placeholder="0.00" value="0">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Notes</label>
                                    <input type="text" name="assignment_notes" class="form-control form-control-sm"
                                           style="min-width:130px;" placeholder="Instructions…">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus me-1"></i>Assign
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <script>
                    (function() {
                        var vendorsByType = <?php echo json_encode($vendors_by_type, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                        var typeSelect    = document.getElementById('vendorTypeSelect');
                        var vendorWrapper = document.getElementById('vendorSelectWrapper');
                        var vendorSelect  = document.getElementById('vendorSelect');
                        var assignForm    = document.getElementById('addVendorAssignmentForm');
                        var taskDescInput = document.getElementById('taskDescriptionInput');
                        var vendorInfoDiv = document.getElementById('vendorInfoDisplay');
                        var vendorLocInfo = document.getElementById('vendorLocationInfo');

                        typeSelect.addEventListener('change', function() {
                            var type = this.value;
                            vendorSelect.innerHTML = '<option value="">— Vendor —</option>';
                            vendorWrapper.classList.add('d-none');
                            vendorInfoDiv.classList.add('d-none');
                            vendorLocInfo.textContent = '';
                            taskDescInput.value = '';

                            if (type && vendorsByType[type]) {
                                vendorsByType[type].forEach(function(v) {
                                    var opt = document.createElement('option');
                                    opt.value = v.id;
                                    opt.textContent = v.name;
                                    opt.dataset.description = v.description || '';
                                    opt.dataset.city = v.city || '';
                                    vendorSelect.appendChild(opt);
                                });
                                vendorWrapper.classList.remove('d-none');
                            }
                        });

                        vendorSelect.addEventListener('change', function() {
                            var selectedOpt = this.options[this.selectedIndex];
                            var description = selectedOpt.dataset.description || '';
                            var city        = selectedOpt.dataset.city || '';

                            taskDescInput.value = description || '';

                            if (city) {
                                vendorLocInfo.textContent = '';
                                var icon2 = document.createElement('i');
                                icon2.className = 'fas fa-map-marker-alt me-1';
                                vendorLocInfo.appendChild(icon2);
                                vendorLocInfo.appendChild(document.createTextNode(city));
                                vendorInfoDiv.classList.remove('d-none');
                            } else {
                                vendorInfoDiv.classList.add('d-none');
                                vendorLocInfo.textContent = '';
                            }
                        });

                        assignForm.addEventListener('submit', function(e) {
                            if (!typeSelect.value) {
                                e.preventDefault();
                                typeSelect.focus();
                                alert('Please select a vendor type first.');
                                return;
                            }
                            if (!vendorSelect.value) {
                                e.preventDefault();
                                vendorSelect.focus();
                                alert('Please select a vendor.');
                            }
                        });
                    })();
                    </script>
                    <?php else: ?>
                    <p class="text-muted small mb-0">
                        <i class="fas fa-exclamation-triangle me-1 text-warning"></i>
                        No active vendors found. <a href="<?php echo BASE_URL; ?>/admin/vendors/add.php">Add a vendor</a> first.
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Admin Added Services -->
                <div class="border-top mt-3 pt-2" id="admin-added-services">
                    <!-- Section Header -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-shield-alt text-secondary" style="font-size:.85rem;"></i>
                            <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.06em;">Admin Added Services</span>
                            <?php if (!empty($admin_services)): ?>
                                <span class="badge bg-dark" style="font-size:.65rem;"><?php echo count($admin_services); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($admin_services) > 0): ?>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Service</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admin_services as $service):
                                    $service_price = floatval($service['price'] ?? 0);
                                    $service_qty   = intval($service['quantity'] ?? 1);
                                    $service_total = $service_price * $service_qty;
                                ?>
                                <tr>
                                    <td class="service-info-cell">
                                        <i class="fas fa-shield-alt text-warning me-2"></i>
                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                        <?php if (!empty($service['description'])): ?>
                                            <small class="service-description"><?php echo htmlspecialchars($service['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-info"><?php echo $service_qty; ?></span></td>
                                    <td class="text-end fw-bold text-primary"><?php echo formatCurrency($service_price); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatCurrency($service_total); ?></td>
                                    <td class="text-center">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                                            <input type="hidden" name="action" value="delete_admin_service">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm py-0 px-1" title="Delete">
                                                <i class="fas fa-trash small"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end fw-bold small">Total Admin Services:</td>
                                    <td colspan="2" class="text-end"><strong class="text-success"><?php echo formatCurrency($admin_services_total); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i> No admin services added yet.</p>
                    <?php endif; ?>

                    <!-- Add Service Form -->
                    <div class="border-top pt-2">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-plus-circle text-success me-1" style="font-size:.85rem;"></i>
                            <span class="fw-semibold text-muted" style="font-size:.8rem;">Add a Service</span>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_admin_service">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label form-label-sm mb-1" style="font-size:.75rem;">Service Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="service_name"
                                           placeholder="Service Name" required>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label form-label-sm mb-1" style="font-size:.75rem;">Description</label>
                                    <input type="text" class="form-control form-control-sm" name="description"
                                           placeholder="Description (optional)">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label form-label-sm mb-1" style="font-size:.75rem;">Qty <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" name="quantity"
                                           min="1" value="1" style="width:70px;" required>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label form-label-sm mb-1" style="font-size:.75rem;">Price <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" name="price"
                                           min="0" step="0.01" style="width:100px;" required>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- Main Tabbed Content -->
        <div class="card shadow border-0 booking-detail-tabs">
            <!-- Tab Navigation -->
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs border-0 px-3" id="bookingDetailTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold px-3" id="tab-overview-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-overview"
                                type="button" role="tab" aria-controls="tab-overview" aria-selected="true">
                            <i class="fas fa-id-card me-1 text-info"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold px-3" id="tab-services-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-services"
                                type="button" role="tab" aria-controls="tab-services" aria-selected="false">
                            <i class="fas fa-concierge-bell me-1 text-warning"></i> Services
                            <?php if ($tab_services_count > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $tab_services_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold px-3" id="tab-payments-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-payments"
                                type="button" role="tab" aria-controls="tab-payments" aria-selected="false">
                            <i class="fas fa-credit-card me-1 text-success"></i> Payments
                            <?php if ($tab_payments_count > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo $tab_payments_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="bookingDetailTabContent">

                <!-- ===== OVERVIEW TAB ===== -->
                <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                    <div class="row g-0">
                        <!-- Customer Information -->
                        <div class="col-md-6 border-end-md">
                            <div class="p-4">
                                <div class="section-label-premium mb-3">
                                    <span class="section-dot bg-info"></span>
                                    <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Customer Information</span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-user-circle text-primary me-1"></i> Name</span>
                                    <span class="compact-field-value fw-semibold"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-phone text-success me-1"></i> Phone</span>
                                    <span class="compact-field-value">
                                        <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>" class="text-decoration-none fw-semibold text-dark">
                                            <?php echo htmlspecialchars($booking['phone']); ?>
                                        </a>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-envelope text-danger me-1"></i> Email</span>
                                    <span class="compact-field-value">
                                        <?php if ($booking['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="text-decoration-none fw-semibold text-dark">
                                                <?php echo htmlspecialchars($booking['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <em class="text-muted small">Not provided</em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-map-marker-alt text-warning me-1"></i> Address</span>
                                    <span class="compact-field-value">
                                        <?php if ($booking['address']): ?>
                                            <?php echo htmlspecialchars($booking['address']); ?>
                                        <?php else: ?>
                                            <em class="text-muted small">Not provided</em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Event Details -->
                        <div class="col-md-6">
                            <div class="p-4">
                                <div class="section-label-premium mb-3">
                                    <span class="section-dot bg-success"></span>
                                    <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Event Details</span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-building text-primary me-1"></i> Venue</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo htmlspecialchars($booking['venue_name']); ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($booking['location']); ?></small>
                                        <?php if (!empty($booking['map_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($booking['map_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none small">
                                                <i class="fas fa-map-pin text-danger me-1"></i>View Map
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-door-open text-info me-1"></i> Hall</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo htmlspecialchars($booking['hall_name']); ?>
                                        <small class="text-muted">(<?php echo $booking['capacity']; ?> capacity)</small>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="far fa-calendar text-danger me-1"></i> Date</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                                        <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="far fa-clock text-warning me-1"></i> Shift</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo ucfirst($booking['shift']); ?>
                                        <?php if ($has_display_time): ?>
                                            <small class="text-muted"><?php echo formatBookingTime($display_start_time); ?> – <?php echo formatBookingTime($display_end_time); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-tag text-success me-1"></i> Event</span>
                                    <span class="compact-field-value fw-semibold"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-users text-primary me-1"></i> Guests</span>
                                    <span class="compact-field-value">
                                        <span class="badge bg-primary px-2 py-1"><?php echo $booking['number_of_guests']; ?> Guests</span>
                                    </span>
                                </div>
                                <?php if ($booking['special_requests']): ?>
                                <div class="compact-field align-items-start mt-1">
                                    <span class="compact-field-label pt-1"><i class="fas fa-comment-dots text-info me-1"></i> Notes</span>
                                    <span class="compact-field-value">
                                        <span class="d-block text-muted small border rounded px-2 py-1 bg-light"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== SERVICES TAB ===== -->
                <div class="tab-pane fade" id="tab-services" role="tabpanel">
                    <div class="p-3">

                        <!-- Menus -->
                        <?php if (count($booking['menus']) > 0): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-2">
                                <span class="section-dot bg-warning"></span>
                                <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Selected Menus</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fw-semibold">Menu</th>
                                            <th class="fw-semibold text-end">Price/Person</th>
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
                                                    <button class="btn btn-sm btn-outline-secondary ms-2 py-0 px-1" type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#menu-items-<?php echo $safeMenuId; ?>"
                                                            aria-expanded="false">
                                                        <i class="fas fa-list small"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold text-success"><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                            <td class="text-center"><span class="badge bg-info"><?php echo $menu['number_of_guests']; ?></span></td>
                                            <td class="text-end fw-bold text-primary"><?php echo formatCurrency($menu['total_price']); ?></td>
                                        </tr>
                                        <?php if (!empty($menu['items'])): ?>
                                        <tr class="collapse" id="menu-items-<?php echo $safeMenuId; ?>">
                                            <td colspan="4" class="bg-light">
                                                <div class="p-2">
                                                    <strong class="small">Menu Items:</strong>
                                                    <ul class="mb-0 mt-1">
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
                        <?php endif; ?>

                        <!-- User-Selected Services -->
                        <?php if ($user_services_count > 0): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-2">
                                <span class="section-dot bg-secondary"></span>
                                <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Customer Selected Services</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fw-semibold">Service</th>
                                            <th class="fw-semibold text-center">Qty</th>
                                            <th class="fw-semibold text-end">Price</th>
                                            <th class="fw-semibold text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_services as $service):
                                            $service_price = floatval($service['price'] ?? 0);
                                            $service_qty   = intval($service['quantity'] ?? 1);
                                            $service_total = $service_price * $service_qty;
                                        ?>
                                        <tr>
                                            <td class="service-info-cell">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                                <?php if (!empty($service['category'])): ?>
                                                    <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($service['category']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($service['description'])): ?>
                                                    <small class="service-description"><?php echo htmlspecialchars($service['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $service_qty; ?></td>
                                            <td class="text-end fw-bold text-primary service-price-cell"><?php echo formatCurrency($service_price); ?></td>
                                            <td class="text-end fw-bold text-success"><?php echo formatCurrency($service_total); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <?php if ($user_services_count > 1): ?>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td colspan="3" class="text-end fw-bold small">Total:</td>
                                            <td class="text-end"><strong class="text-success"><?php echo formatCurrency($user_services_total); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- ===== PAYMENTS TAB ===== -->
                <div class="tab-pane fade" id="tab-payments" role="tabpanel">
                    <div class="p-3">

                        <!-- Payment Methods -->
                        <?php if (count($booking_payment_methods) > 0): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-3">
                                <span class="section-dot bg-primary"></span>
                                <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Payment Methods</span>
                            </div>
                            <?php foreach ($booking_payment_methods as $method): ?>
                            <div class="payment-method-item mb-3 pb-3 <?php echo ($method !== end($booking_payment_methods)) ? 'border-bottom' : ''; ?>">
                                <h6 class="fw-bold text-dark mb-2">
                                    <i class="fas fa-money-check-alt text-primary me-2"></i>
                                    <?php echo htmlspecialchars($method['name']); ?>
                                </h6>
                                <div class="row g-3">
                                    <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                                    <div class="col-md-4">
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>"
                                             alt="<?php echo htmlspecialchars($method['name']); ?> QR Code"
                                             class="img-fluid rounded shadow-sm"
                                             style="max-width: 180px; border: 2px solid #dee2e6; padding: 8px; background: white;">
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['bank_details'])): ?>
                                    <div class="<?php echo !empty($method['qr_code']) ? 'col-md-8' : 'col-12'; ?>">
                                        <div class="alert alert-light mb-0 border">
                                            <small class="text-muted fw-semibold d-block mb-1">Bank Details:</small>
                                            <pre class="mb-0 text-dark" style="font-family: monospace; font-size: 0.82rem; white-space: pre-wrap;"><?php echo htmlspecialchars($method['bank_details']); ?></pre>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Transactions -->
                        <?php if ($tab_payments_count > 0): ?>
                        <div class="section-label-premium mb-3">
                            <span class="section-dot bg-success"></span>
                            <span class="fw-bold text-uppercase text-muted" style="font-size:.72rem;letter-spacing:.09em;">Payment Transactions</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 border rounded">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold">Date</th>
                                        <th class="fw-semibold">Method</th>
                                        <th class="fw-semibold">Txn ID</th>
                                        <th class="fw-semibold text-end">Amount</th>
                                        <th class="fw-semibold text-center">Status</th>
                                        <th class="fw-semibold text-center">Slip</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_transactions as $payment): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold small"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                            <br><small class="text-muted"><?php echo convertToNepaliDate($payment['payment_date']); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                        </td>
                                        <td class="small"><?php echo !empty($payment['payment_method_name']) ? htmlspecialchars($payment['payment_method_name']) : '<em class="text-muted">N/A</em>'; ?></td>
                                        <td>
                                            <span class="badge bg-secondary small">
                                                <?php echo !empty($payment['transaction_id']) ? htmlspecialchars($payment['transaction_id']) : 'N/A'; ?>
                                            </span>
                                            <?php if (!empty($payment['notes'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo formatCurrency($payment['paid_amount']); ?></strong>
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
                                                <button type="button" class="btn btn-sm btn-info py-0 px-2"
                                                        data-bs-toggle="modal" data-bs-target="#slipModal<?php echo $payment['id']; ?>">
                                                    <i class="fas fa-eye small"></i>
                                                </button>
                                                <!-- Payment Slip Modal -->
                                                <div class="modal fade" id="slipModal<?php echo $payment['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Payment Slip</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-center p-4">
                                                                <div class="mb-3">
                                                                    <span class="badge bg-secondary">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></span>
                                                                </div>
                                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>"
                                                                     alt="Payment Slip" class="img-fluid rounded shadow" style="max-height: 70vh;">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <a href="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>"
                                                                   download class="btn btn-success">
                                                                    <i class="fas fa-download me-1"></i> Download
                                                                </a>
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light border-top border-2">
                                        <td colspan="3" class="text-end fw-bold small">Total Paid:</td>
                                        <td colspan="3" class="text-end">
                                            <strong class="text-success fs-6"><?php echo formatCurrency($total_paid); ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end small">Grand Total:</td>
                                        <td colspan="3" class="text-end">
                                            <strong><?php echo formatCurrency($booking['grand_total']); ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end small">Balance Due:</td>
                                        <td colspan="3" class="text-end">
                                            <strong class="text-danger"><?php echo formatCurrency($balance_due); ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-receipt fa-2x mb-2 d-block opacity-50"></i>
                            <small>No payment transactions yet.</small>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div><!-- /tab-content -->
        </div><!-- /card -->
    </div><!-- /col-lg-8 -->

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <!-- Booking Overview Card -->
        <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px;">
            <div class="card-header bg-gradient-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Booking Overview</h5>
            </div>
            <div class="card-body p-4">
                <!-- Status Summary Row -->
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Booking Status</span>
                        <span class="badge bg-<?php echo $booking_status_color; ?>">
                            <i class="fas fa-circle-dot me-1"></i><?php echo $booking_status_display; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Payment Status</span>
                        <span class="badge bg-<?php echo $payment_status_color; ?>">
                            <i class="fas <?php echo $payment_status_icon; ?> me-1"></i><?php echo $payment_status_display; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">
                            <i class="far fa-calendar-plus me-1"></i>Booked On
                        </span>
                        <span class="small fw-semibold">
                            <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                        </span>
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
                        <?php if ($vendors_total > 0): ?>
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted small">Vendors Total:</span>
                            <strong class="text-dark"><?php echo formatCurrency($vendors_total); ?></strong>
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
                        // $advance already calculated before the Quick Check Panel section
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
                        
                        <?php if ($booking['advance_payment_received'] === 1): ?>
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
                        
                        <!-- Balance Due Amount -->
                        <div class="alert alert-info mt-2 mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block fw-semibold mb-1">
                                        <i class="fas fa-dollar-sign me-1"></i>
                                        Balance Due Amount
                                    </small>
                                    <small class="text-muted">
                                        <?php if ($booking['advance_payment_received'] === 1): ?>
                                            (After advance deduction)
                                        <?php else: ?>
                                            (Full amount)
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <h5 class="mb-0 fw-bold text-danger"><?php echo formatCurrency($balance_due); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ====================================================
   PREMIUM BOOKING DETAIL - Enhanced Styles
   ==================================================== */

/* Gradient Utilities */
.bg-gradient-primary  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success  { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.bg-gradient-info     { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.bg-gradient-warning  { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-gradient-secondary{ background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

/* Cards */
.card { transition: box-shadow 0.2s ease; }
.shadow-sm { box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.075) !important; }

/* Quick Check */
.quick-check-item {
    padding: .875rem 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: background .15s ease, border-color .15s ease;
}
.quick-check-item:hover {
    background: #f1f3f5;
    border-color: #ced4da;
}

/* Status Update Form */
.status-update-form .form-select {
    border: 2px solid #dee2e6;
    font-size: .85rem;
}
.status-update-form .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .2rem rgba(13,110,253,.15);
}

/* ─── Booking Detail Tabs Card ─── */
.booking-detail-tabs {
    border: 1px solid #e0e6ed !important;
    box-shadow: 0 2px 16px rgba(0,0,0,.07) !important;
    border-radius: 12px !important;
    overflow: hidden;
}

.booking-detail-tabs .card-header {
    border-radius: 0 !important;
    padding: 0 !important;
}

/* Nav tabs container: remove Bootstrap's border-bottom and reset gap */
.booking-detail-tabs .nav-tabs {
    border-bottom: none;
    gap: 0;
    flex-wrap: nowrap;
    overflow-x: auto;
}

/* Nav tab links: higher specificity (.card-header added) overrides Bootstrap's
   .nav-tabs .nav-link (030) and .nav-tabs .nav-link.active (030) without !important */
.booking-detail-tabs .card-header .nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    margin-bottom: 0;           /* neutralise Bootstrap's -1px overlap trick */
    padding: .7rem 1.25rem;
    font-size: .875rem;
    font-weight: 500;
    line-height: 1.4;
    white-space: nowrap;
    background: transparent;
    transition: color .15s ease, border-color .15s ease;
}
.booking-detail-tabs .card-header .nav-tabs .nav-link:hover {
    color: #0d6efd;
    border-bottom-color: #b0c4de; /* muted blue — softer than the active #0d6efd */
    background: rgba(13,110,253,.04);
}
.booking-detail-tabs .card-header .nav-tabs .nav-link.active {
    color: #0d6efd;
    font-weight: 600;
    border-bottom: 2px solid #0d6efd;
    background: transparent;
}

/* ─── Section Label ─── */
.section-label-premium {
    display: flex;
    align-items: center;
    gap: .45rem;
}
.section-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ─── Compact Field Rows (Overview Tab) ─── */
.compact-field {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    padding: .42rem 0;
    border-bottom: 1px solid #f0f2f5;
    font-size: .875rem;
    line-height: 1.45;
}
.compact-field:last-child { border-bottom: none; }
.compact-field-label {
    flex: 0 0 80px;
    color: #8a93a2;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
    padding-top: .05rem;
}
.compact-field-value {
    flex: 1;
    color: #1a202c;
    word-break: break-word;
}

/* Border between two columns on md+ */
@media (min-width: 768px) {
    .border-end-md { border-right: 1px solid #e9ecef !important; }
}

/* ─── Payment Breakdown in Sidebar ─── */
.payment-breakdown {
    background: #f8f9fa;
    padding: .875rem 1rem;
    border-radius: 8px;
}

/* ─── Service Description ─── */
.service-description {
    display: block;
    margin-top: .3rem;
    margin-left: 1.5rem;
    font-size: .8rem;
    color: #6c757d;
    line-height: 1.35;
}
.service-info-cell { vertical-align: top; }
.service-price-cell { vertical-align: top; }

/* ─── Payment method item ─── */
.payment-method-item { transition: background .15s ease; }
.payment-method-item:hover { background: #f8f9fa; border-radius: 8px; }

/* ─── Badges ─── */
.badge { font-weight: 500; letter-spacing: .04em; }

/* ─── Table tweaks ─── */
.table-hover tbody tr { transition: background .15s ease; }

/* Print styles */
.print-invoice-only { display: none; }

/* Print Invoice Styles */
.service-description-print { font-weight: 500; color: #666; font-size: 8.5px; line-height: 1.2; }
.service-category-print    { font-weight: 600; color: #444; font-size: 9px; margin-left: 4px; }
.menu-items-print          { font-weight: normal; color: #555; font-size: 8pt; line-height: 1.2; }

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
    /* Force all elements to print with exact colors (backgrounds, text, borders) */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Remove non-invoice elements from document flow to prevent blank pages.
       Using display:none (not visibility:hidden) so elements take up no space. */
    .sidebar,
    .top-navbar {
        display: none !important;
    }

    .main-content > *:not(.print-invoice-only) {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }

    .print-invoice-only {
        display: block !important;
        position: static !important;
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
        print-color-adjust: exact;
    }
    
    .invoice-container {
        width: 100%;
        max-width: 186mm;
        margin: 0 auto;
        padding: 0;
        font-size: 10pt;
        line-height: 1.3;
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
    
    /* Menu items list in print - small text below menu name */
    .menu-items-print {
        font-size: 8pt;
        line-height: 1.3;
        color: #555 !important;
    }
    
    /* Service category in print - readable */
    .service-category-print {
        font-size: 9px;
        font-weight: 600;
        color: #444 !important;
        margin-left: 4px;
    }
    
    /* Hide "no services" row when printing */
    .no-services-row {
        display: none !important;
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
// Tab persistence using URL hash or PHP-injected initial tab
(function() {
    'use strict';

    var tabButtons = document.querySelectorAll('#bookingDetailTabs [data-bs-toggle="tab"]');
    var initialTabId = <?php echo json_encode($initial_tab); ?>;

    // Determine which tab to activate (PHP-set > hash > sessionStorage > default overview)
    var activeTabId = initialTabId !== 'tab-overview'
        ? initialTabId
        : (window.location.hash.replace('#', '') || sessionStorage.getItem('bookingViewTab_<?php echo $booking_id; ?>'));

    if (activeTabId && activeTabId !== 'tab-overview') {
        var btn = document.querySelector('#bookingDetailTabs [data-bs-target="#' + activeTabId + '"]');
        if (btn) {
            var bsTab = new bootstrap.Tab(btn);
            bsTab.show();
        }
    }

    // Save active tab when switching
    tabButtons.forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            var target = e.target.dataset.bsTarget.replace('#', '');
            sessionStorage.setItem('bookingViewTab_<?php echo $booking_id; ?>', target);
            history.replaceState(null, '', window.location.pathname + window.location.search + '#' + target);
        });
    });
})();


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

    // Handle Booking Confirmation WhatsApp form submission
    const confirmationWhatsappForm = document.getElementById('confirmationWhatsappForm');
    if (confirmationWhatsappForm) {
        confirmationWhatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const phone = <?php echo json_encode($clean_phone); ?>;
            const message = <?php echo json_encode($confirmation_text); ?>;
            const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
            window.open(whatsappUrl, '_blank');

            setTimeout(function() {
                confirmationWhatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }

    // Handle Venue Provider WhatsApp form submission
    const venueProviderWhatsappForm = document.getElementById('venueProviderWhatsappForm');
    if (venueProviderWhatsappForm) {
        venueProviderWhatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const waUrl = <?php echo json_encode($venue_provider_wa_url); ?>;
            if (waUrl) {
                window.open(waUrl, '_blank');
            }

            setTimeout(function() {
                venueProviderWhatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }

    // Handle payment status change from the View Details dropdown
    const paymentStatusSelect = document.getElementById('payment-status-select');
    if (paymentStatusSelect) {
        paymentStatusSelect.addEventListener('change', function() {
            const bookingId = this.dataset.bookingId;
            const newStatus = this.value;
            const oldStatus = this.dataset.currentStatus;
            const selectElement = this;

            if (!confirm('Are you sure you want to change payment status from "' + oldStatus + '" to "' + newStatus + '"?')) {
                this.value = oldStatus;
                return;
            }

            selectElement.disabled = true;

            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('payment_status', newStatus);

            fetch('update-payment-status.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                selectElement.disabled = false;

                if (data.success) {
                    selectElement.dataset.currentStatus = newStatus;

                    // Update the badge color and label
                    const badge = document.getElementById('payment-status-badge');
                    if (badge) {
                        const colorMap = {paid: 'success', partial: 'warning', pending: 'danger', cancelled: 'secondary'};
                        const labelMap = {paid: 'Paid', partial: 'Partial', pending: 'Pending', cancelled: 'Cancelled'};
                        badge.className = 'badge bg-' + (colorMap[newStatus] || 'secondary') + ' ms-auto';
                        badge.textContent = labelMap[newStatus] || newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }

                    // Auto-update Booking Status badge
                    if (data.booking_status) {
                        const bookingBadge = document.getElementById('booking-status-badge');
                        if (bookingBadge) {
                            const bsColorMap = {pending: 'warning', confirmed: 'success', cancelled: 'danger', completed: 'primary', payment_submitted: 'info'};
                            const bsLabelMap = {pending: 'Pending', confirmed: 'Confirmed', cancelled: 'Cancelled', completed: 'Completed', payment_submitted: 'Payment submitted'};
                            bookingBadge.className = 'badge bg-' + (bsColorMap[data.booking_status] || 'info') + ' ms-auto';
                            bookingBadge.textContent = bsLabelMap[data.booking_status] || data.booking_status;
                        }
                    }

                    // Auto-update Advance Payment badge
                    const advanceBadge = document.getElementById('advance-payment-badge');
                    if (advanceBadge && typeof data.advance_payment_received !== 'undefined') {
                        if (data.advance_payment_received === 1) {
                            advanceBadge.className = 'badge bg-success ms-auto';
                            advanceBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Received';
                        } else {
                            advanceBadge.className = 'badge bg-danger ms-auto';
                            advanceBadge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Not Received';
                        }
                    }

                    // Update button sections based on new payment status
                    // Show "Booking Confirmation" only when advance payment is received AND status is not pending
                    const newAdvanceReceived = (typeof data.advance_payment_received !== 'undefined')
                        ? (data.advance_payment_received === 1)
                        : <?php echo ($booking['advance_payment_received'] === 1) ? 'true' : 'false'; ?>;
                    const confirmSection = document.getElementById('booking-confirmation-section');
                    const requestSection = document.getElementById('payment-request-section');
                    if (confirmSection && requestSection) {
                        const showConfirmation = newAdvanceReceived && newStatus.toLowerCase() !== 'pending';
                        confirmSection.style.display = showConfirmation ? '' : 'none';
                        requestSection.style.display = showConfirmation ? 'none' : '';
                    }

                    var successMsg = 'Payment status updated successfully.';
                    if (data.is_backward) {
                        successMsg += '\n\nNote: You moved the payment status backward in the flow.';
                    }
                    alert(successMsg);
                } else {
                    selectElement.value = oldStatus;
                    // Use a safe static message to avoid displaying unescaped server content
                    alert('Failed to update payment status. Please try again.');
                }
            })
            .catch(function(error) {
                selectElement.disabled = false;
                selectElement.value = oldStatus;
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
    }
})();


</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
