<?php
$page_title = 'Edit Booking';
// Require PHP utilities before any HTML output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$current_user = getCurrentUser();

$db = getDB();
$success_message = '';
$error_message = '';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch booking details
$booking = getBookingDetails($booking_id);

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Fetch halls
$halls = $db->query("SELECT h.id, h.name, v.name as venue_name, h.capacity FROM halls h INNER JOIN venues v ON h.venue_id = v.id WHERE h.status = 'active' ORDER BY v.name, h.name")->fetchAll();

// Fetch menus for the currently selected hall
$menus = getMenusForHall($booking['hall_id']);

// Fetch services
$services = $db->query("SELECT id, name, price, category, photo FROM additional_services WHERE status = 'active' ORDER BY category, name")->fetchAll();

// Fetch active payment methods
$payment_methods = getActivePaymentMethods();

// Get currently selected menus
$selected_menus = array_column($booking['menus'], 'menu_id');

// Get currently selected services
$selected_services = array_column($booking['services'], 'service_id');

// Get currently selected payment methods
$selected_payment_methods = array_column(getBookingPaymentMethods($booking_id), 'id');

$recommended_advance = calculateAdvancePayment($booking['grand_total']);
$payment_summary = calculatePaymentSummary($booking_id);
$display_grand_total = floatval($payment_summary['grand_total']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $hall_id = intval($_POST['hall_id']);
    $event_date = $_POST['event_date'];
    $shift = $_POST['shift'];
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time']   ?? '';
    if (empty($start_time) || empty($end_time)) {
        $shift_times = getShiftDefaultTimes($shift);
        if (empty($start_time)) $start_time = $shift_times['start'];
        if (empty($end_time))   $end_time   = $shift_times['end'];
    }
    $event_type = trim($_POST['event_type']);
    $number_of_guests = intval($_POST['number_of_guests']);
    $special_requests = trim($_POST['special_requests']);
    $post_selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
    $post_selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $post_selected_payment_methods = isset($_POST['payment_methods']) ? $_POST['payment_methods'] : [];
    $payment_status = $_POST['payment_status'];
    $advance_amount_input = isset($_POST['advance_amount_received']) ? trim($_POST['advance_amount_received']) : '';
    $advance_amount_value = ($advance_amount_input !== '' && is_numeric($advance_amount_input)) ? floatval($advance_amount_input) : null;

    $auto_status = getAutoStatusByPaymentStatus($payment_status);
    $booking_status = $auto_status['booking_status'] ?? ($payment_status === 'cancelled' ? 'cancelled' : $booking['booking_status']);
    $is_advance_received = $auto_status['advance_payment_received'] ?? ($payment_status === 'cancelled' ? 0 : $booking['advance_payment_received']);

    $current_advance_amount = floatval($booking['advance_amount_received'] ?? 0);
    if ($payment_status === 'partial') {
        $advance_amount_received = ($advance_amount_value !== null) ? $advance_amount_value : $current_advance_amount;
    } elseif ($payment_status === 'paid') {
        $advance_amount_received = ($advance_amount_value !== null) ? $advance_amount_value : floatval($booking['grand_total']);
    } else {
        $advance_amount_received = 0.0;
    }
    
    // Store old status for email notification
    $old_booking_status = $booking['booking_status'];
    $old_payment_status = $booking['payment_status'];
    $status_changed = ($old_booking_status !== $booking_status) || ($old_payment_status !== $payment_status);

    // Validation
    if (empty($full_name) || empty($phone) || $hall_id <= 0 || empty($event_date) || $number_of_guests <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } elseif ($advance_amount_input !== '' && $advance_amount_value === null) {
        $error_message = 'Advance amount must be a valid number.';
    } elseif ($advance_amount_value !== null && $advance_amount_value < 0) {
        $error_message = 'Advance amount cannot be negative.';
    } else {
        // Check availability (excluding current booking)
        $check_sql = "SELECT COUNT(*) as count FROM bookings 
                     WHERE hall_id = ? AND event_date = ? AND shift = ? 
                     AND booking_status != 'cancelled' AND id != ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([$hall_id, $event_date, $shift, $booking_id]);
        $check_result = $check_stmt->fetch();
        
        if ($check_result['count'] > 0) {
            $error_message = 'This hall is not available for the selected date and shift.';
        } else {
            try {
                $db->beginTransaction();
                
                // Update customer info
                $customer_id = $booking['customer_id'];
                $stmt = $db->prepare("UPDATE customers SET full_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$full_name, $phone, $email, $address, $customer_id]);
                
                // Update booking basic info (totals will be recalculated after services are inserted)
                $sql = "UPDATE bookings SET 
                        hall_id = ?, event_date = ?, start_time = ?, end_time = ?, shift = ?, 
                        event_type = ?, number_of_guests = ?, 
                        special_requests = ?, booking_status = ?, payment_status = ?,
                        advance_payment_received = ?, advance_amount_received = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $hall_id,
                    $event_date,
                    $start_time ?: null,
                    $end_time   ?: null,
                    $shift,
                    $event_type,
                    $number_of_guests,
                    $special_requests,
                    $booking_status,
                    $payment_status,
                    $is_advance_received,
                    $advance_amount_received,
                    $booking_id
                ]);
                
                // Delete old menus and user services (preserve admin services)
                $db->prepare("DELETE FROM booking_menus WHERE booking_id = ?")->execute([$booking_id]);
                $db->prepare("DELETE FROM booking_services WHERE booking_id = ? AND added_by = ?")->execute([$booking_id, USER_SERVICE_TYPE]);
                
                // Insert new booking menus
                if (!empty($post_selected_menus)) {
                    foreach ($post_selected_menus as $menu_id) {
                        $stmt = $db->prepare("SELECT price_per_person FROM menus WHERE id = ?");
                        $stmt->execute([$menu_id]);
                        $menu = $stmt->fetch();
                        
                        if ($menu) {
                            $menu_price = $menu['price_per_person'];
                            $menu_total = $menu_price * $number_of_guests;
                            
                            $stmt = $db->prepare("INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$booking_id, $menu_id, $menu_price, $number_of_guests, $menu_total]);
                        }
                    }
                }
                
                // Insert new booking services (user services)
                if (!empty($post_selected_services)) {
                    foreach ($post_selected_services as $service_id) {
                        $stmt = $db->prepare("SELECT name, price, description, category FROM additional_services WHERE id = ?");
                        $stmt->execute([$service_id]);
                        $service = $stmt->fetch();
                        
                        if ($service) {
                            $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$booking_id, $service_id, $service['name'], $service['price'], $service['description'], $service['category'], USER_SERVICE_TYPE, DEFAULT_SERVICE_QUANTITY]);
                        }
                    }
                }
                
                // Recalculate totals to include all services (user + admin)
                recalculateBookingTotals($booking_id);

                // Keep actual received amount in sync when payment status is marked paid
                // and no explicit override amount was entered in the form.
                if ($payment_status === 'paid' && $advance_amount_value === null) {
                    $updated_total_stmt = $db->prepare("SELECT grand_total FROM bookings WHERE id = ?");
                    $updated_total_stmt->execute([$booking_id]);
                    $updated_booking_totals = $updated_total_stmt->fetch();
                    $updated_grand_total = floatval($updated_booking_totals['grand_total'] ?? 0);
                    $db->prepare("UPDATE bookings SET advance_amount_received = ? WHERE id = ?")->execute([$updated_grand_total, $booking_id]);
                }
                
                // Link payment methods to booking
                linkPaymentMethodsToBooking($booking_id, $post_selected_payment_methods);
                
                // Log activity
                logActivity($current_user['id'], 'Updated booking', 'bookings', $booking_id, "Updated booking: {$booking['booking_number']}");
                
                $db->commit();
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Log the error for debugging
                error_log('Booking update error: ' . $e->getMessage());
                $error_message = 'Error updating booking. Please try again or contact support.';
            }
            
            // Post-commit operations: send notification and refresh data
            // These run only when booking was successfully saved (no error)
            if (empty($error_message)) {
                if ($status_changed) {
                    try {
                        sendBookingNotification($booking_id, 'update', $old_booking_status);
                    } catch (Exception $e) {
                        error_log("Booking update notification email failed for booking ID {$booking_id}: " . $e->getMessage());
                    }
                }
                
                $success_message = 'Booking updated successfully!';
                
                // Refresh booking data
                $booking = getBookingDetails($booking_id);
                $selected_menus = array_column($booking['menus'], 'menu_id');
                $selected_services = array_column($booking['services'], 'service_id');
                $selected_payment_methods = array_column(getBookingPaymentMethods($booking_id), 'id');
                $recommended_advance = calculateAdvancePayment($booking['grand_total']);
                $payment_summary = calculatePaymentSummary($booking_id);
                $display_grand_total = floatval($payment_summary['grand_total']);
            }
        }
    }
    } // end CSRF-valid else
}

$display_payment_status = $booking['payment_status'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_status']) && in_array($_POST['payment_status'], ['pending', 'partial', 'paid', 'cancelled'], true)) {
    $display_payment_status = $_POST['payment_status'];
}

$display_auto_status = getAutoStatusByPaymentStatus($display_payment_status);
$display_booking_status = $display_auto_status['booking_status'] ?? ($display_payment_status === 'cancelled' ? 'cancelled' : $booking['booking_status']);

$display_advance_amount = $booking['advance_amount_received'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advance_amount_received'])) {
    $display_advance_amount = trim($_POST['advance_amount_received']);
}

$display_advance_amount_numeric = is_numeric($display_advance_amount) ? floatval($display_advance_amount) : 0.0;
$display_remaining_after_advance = max(0, $display_grand_total - $display_advance_amount_numeric);

$status_badge_map = [
    'confirmed' => 'success',
    'payment_submitted' => 'info',
    'pending' => 'warning',
    'cancelled' => 'danger',
    'completed' => 'primary'
];

$payment_status_ui_map = [
    'pending' => [
        'bookingStatus' => 'pending',
        'label' => getBookingStatusLabel('pending'),
        'badge' => 'warning',
        'advanceRequired' => false
    ],
    'partial' => [
        'bookingStatus' => 'confirmed',
        'label' => getBookingStatusLabel('confirmed'),
        'badge' => 'success',
        'advanceRequired' => true
    ],
    'paid' => [
        'bookingStatus' => 'completed',
        'label' => getBookingStatusLabel('completed'),
        'badge' => 'primary',
        'advanceRequired' => true
    ],
    'cancelled' => [
        'bookingStatus' => 'cancelled',
        'label' => getBookingStatusLabel('cancelled'),
        'badge' => 'danger',
        'advanceRequired' => false
    ]
];

// Include the HTML header only after all PHP processing (and potential redirects)
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></h5>
                <div>
                    <a href="view.php?id=<?php echo $booking_id; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
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

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <h6 class="text-muted border-bottom pb-2 mb-3">Customer Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($booking['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($booking['phone']); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($booking['email']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?php echo htmlspecialchars($booking['address']); ?>">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Event Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hall_id" class="form-label">Hall <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_id" name="hall_id" required>
                                    <?php foreach ($halls as $hall): ?>
                                        <option value="<?php echo $hall['id']; ?>" <?php echo ($booking['hall_id'] == $hall['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hall['venue_name']) . ' - ' . htmlspecialchars($hall['name']) . ' (' . $hall['capacity'] . ' pax)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="event_type" name="event_type" 
                                       value="<?php echo htmlspecialchars($booking['event_type']); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       value="<?php echo $booking['event_date']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="shift" class="form-label">Shift <span class="text-danger">*</span></label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="morning" <?php echo ($booking['shift'] == 'morning') ? 'selected' : ''; ?>>Morning (6:00 AM – 12:00 PM)</option>
                                    <option value="afternoon" <?php echo ($booking['shift'] == 'afternoon') ? 'selected' : ''; ?>>Afternoon (12:00 PM – 6:00 PM)</option>
                                    <option value="evening" <?php echo ($booking['shift'] == 'evening') ? 'selected' : ''; ?>>Evening (6:00 PM – 11:00 PM)</option>
                                    <option value="fullday" <?php echo ($booking['shift'] == 'fullday') ? 'selected' : ''; ?>>Full Day (6:00 AM – 11:00 PM)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="number_of_guests" class="form-label">Number of Guests <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" 
                                       value="<?php echo $booking['number_of_guests']; ?>" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label"><i class="fas fa-hourglass-start text-success me-1"></i>Start Time</label>
                                <?php
                                    // Use saved value; fall back to shift default for old bookings with no time
                                    $edit_default_times = getShiftDefaultTimes($booking['shift']);
                                    $edit_start = !empty($booking['start_time']) ? substr($booking['start_time'], 0, 5) : $edit_default_times['start'];
                                    $edit_end   = !empty($booking['end_time'])   ? substr($booking['end_time'], 0, 5)   : $edit_default_times['end'];
                                ?>
                                <select class="form-select" id="start_time" name="start_time">
                                    <?php echo generateTimeOptions($edit_start); ?>
                                </select>
                                <small class="text-muted">Auto-filled from shift; adjust if needed.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label"><i class="fas fa-hourglass-end text-success me-1"></i>End Time</label>
                                <select class="form-select" id="end_time" name="end_time">
                                    <?php echo generateTimeOptions($edit_end); ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Menus & Services</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Menus (Optional)</label>
                                <div id="menus-container">
                                    <?php if (!empty($menus)): ?>
                                        <?php foreach ($menus as $menu): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="menus[]" value="<?php echo $menu['id']; ?>" 
                                                   id="menu_<?php echo $menu['id']; ?>" 
                                                   <?php echo in_array($menu['id'], $selected_menus) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="menu_<?php echo $menu['id']; ?>">
                                                <?php echo htmlspecialchars($menu['name']) . ' - ' . formatCurrency($menu['price_per_person']) . '/person'; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> No menus are assigned to this hall.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div id="menus-loading" class="d-none">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-success" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="ms-2">Loading menus...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Additional Services (Optional)</label>
                                <?php
                                // Group services by category
                                $services_by_category = [];
                                foreach ($services as $service) {
                                    $cat = !empty($service['category']) ? $service['category'] : 'Other';
                                    $services_by_category[$cat][] = $service;
                                }
                                $categories = array_keys($services_by_category);
                                ?>
                                <?php if (empty($services)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No additional services available.
                                    </div>
                                <?php else: ?>
                                <!-- Category filter -->
                                <select class="form-select form-select-sm mb-2" id="service-category-filter">
                                    <option value="">— All Categories —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Services list grouped by category -->
                                <div class="border rounded p-2" style="max-height:320px;overflow-y:auto;">
                                    <?php foreach ($services_by_category as $cat => $cat_services): ?>
                                    <div class="svc-category-group mb-2" data-category="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>">
                                        <small class="text-muted fw-semibold d-block mb-1"><?php echo htmlspecialchars($cat); ?></small>
                                        <?php foreach ($cat_services as $service): ?>
                                        <div class="form-check d-flex align-items-center gap-2 mb-2">
                                            <input class="form-check-input flex-shrink-0" type="checkbox" name="services[]" value="<?php echo $service['id']; ?>"
                                                   id="service_<?php echo $service['id']; ?>"
                                                   <?php echo in_array($service['id'], $selected_services) ? 'checked' : ''; ?>>
                                            <label class="form-check-label d-flex align-items-center gap-2" for="service_<?php echo $service['id']; ?>" style="cursor:pointer;">
                                                <?php if (!empty($service['photo'])): ?>
                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                     alt="<?php echo htmlspecialchars($service['name']); ?>"
                                                     class="rounded flex-shrink-0"
                                                     style="width:48px;height:48px;object-fit:cover;">
                                                <?php else: ?>
                                                <span class="rounded bg-light border d-flex align-items-center justify-content-center flex-shrink-0"
                                                      style="width:48px;height:48px;">
                                                    <i class="fas fa-concierge-bell text-muted" style="font-size:1.2rem;"></i>
                                                </span>
                                                <?php endif; ?>
                                                <span>
                                                    <span class="fw-medium"><?php echo htmlspecialchars($service['name']); ?></span><br>
                                                    <small class="text-success fw-semibold"><?php echo formatCurrency($service['price']); ?></small>
                                                </span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Get admin services for this booking
                    $admin_services = getAdminServices($booking_id);
                    if (!empty($admin_services)):
                    ?>
                    <div class="alert alert-info mt-3">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-info-circle me-2"></i>
                            Admin Added Services (Cannot be edited here)
                        </h6>
                        <p class="mb-2 small">These services were added by admin and can only be managed from the <a href="view.php?id=<?php echo $booking_id; ?>" class="alert-link">booking view page</a>.</p>
                        <table class="table table-sm table-bordered bg-white mb-0">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th class="text-center" width="100">Quantity</th>
                                    <th class="text-end" width="120">Price</th>
                                    <th class="text-end" width="120">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admin_services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                        <?php if (!empty($service['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($service['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $service['quantity']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($service['price']); ?></td>
                                    <td class="text-end"><strong><?php echo formatCurrency($service['total_price']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="special_requests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"><?php echo htmlspecialchars($booking['special_requests']); ?></textarea>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Payment Methods</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Select Payment Methods (Optional)</label>
                                <small class="text-muted d-block mb-2">Choose which payment methods to offer for this booking</small>
                                <?php if (empty($payment_methods)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No payment methods configured. 
                                        <a href="<?php echo BASE_URL; ?>/admin/payment-methods/index.php">Add payment methods</a> to use this feature.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($payment_methods as $method): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="payment_methods[]" 
                                               value="<?php echo $method['id']; ?>" 
                                               id="payment_method_<?php echo $method['id']; ?>" 
                                               <?php echo in_array($method['id'], $selected_payment_methods) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="payment_method_<?php echo $method['id']; ?>">
                                            <?php echo htmlspecialchars($method['name']); ?>
                                            <?php if (!empty($method['bank_details'])): ?>
                                                <small class="text-muted">(<?php echo substr(htmlspecialchars($method['bank_details']), 0, 50); ?>...)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Booking Status</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-label fw-semibold">Booking Status</div>
                                <div class="form-control-plaintext" id="booking-status-display">
                                    <span class="badge bg-<?php echo htmlspecialchars($status_badge_map[$display_booking_status] ?? 'secondary', ENT_QUOTES, 'UTF-8'); ?> px-2 py-1 fs-6" id="booking-status-badge"><?php echo htmlspecialchars(getBookingStatusLabel($display_booking_status), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <input type="hidden" name="booking_status" value="<?php echo htmlspecialchars($display_booking_status, ENT_QUOTES, 'UTF-8'); ?>" id="booking_status">
                                <small class="text-muted d-block mt-1">Booking status is auto-updated from payment status.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="pending" <?php echo ($display_payment_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo ($display_payment_status == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo ($display_payment_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo ($display_payment_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <small class="text-muted">Flow: Pending → Partial → Paid</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="advance_amount_received" class="form-label">Advance Amount Received</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo htmlspecialchars(getSetting('currency', 'NPR'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="advance_amount_received"
                                        name="advance_amount_received"
                                        min="0"
                                        step="0.01"
                                        value="<?php echo htmlspecialchars((string)$display_advance_amount, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-default-partial="<?php echo htmlspecialchars(number_format($recommended_advance['amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-default-paid="<?php echo htmlspecialchars(number_format($display_grand_total, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </div>
                                <small class="text-muted" id="advance-amount-help">For partial payments, enter the actual advance received. For paid bookings, leaving it blank auto-fills the full total. Pending/cancelled clears the amount.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded bg-light p-3 h-100">
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-muted">Grand Total</span>
                                    <strong><?php echo formatCurrency($display_grand_total); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-muted">Advance Recorded</span>
                                    <strong><?php echo formatCurrency($display_advance_amount_numeric); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Remaining After Advance</span>
                                    <strong><?php echo formatCurrency($display_remaining_after_advance); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Booking
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo $booking_id; ?>&action=delete';
    }
}

// Dynamic menu loading based on hall selection
document.addEventListener('DOMContentLoaded', function() {
    const hallSelect = document.getElementById('hall_id');
    const menusContainer = document.getElementById('menus-container');
    const menusLoading = document.getElementById('menus-loading');
    
    if (hallSelect) {
        // Load menus when hall is changed
        hallSelect.addEventListener('change', function() {
            const hallId = this.value;
            
            if (!hallId) {
                menusContainer.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Please select a hall first to see available menus.</div>';
                return;
            }
            
            // Show loading
            menusContainer.classList.add('d-none');
            menusLoading.classList.remove('d-none');
            
            // Fetch menus for selected hall
            fetch('<?php echo BASE_URL; ?>/api/get-hall-menus.php?hall_id=' + hallId)
                .then(response => response.json())
                .then(data => {
                    menusLoading.classList.add('d-none');
                    menusContainer.classList.remove('d-none');
                    
                    if (data.success && data.menus && data.menus.length > 0) {
                        let html = '';
                        data.menus.forEach(menu => {
                            html += `
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menus[]" 
                                           value="${menu.id}" id="menu_${menu.id}">
                                    <label class="form-check-label" for="menu_${menu.id}">
                                        ${escapeHtml(menu.name)} - ${escapeHtml(menu.price_formatted)}/person
                                    </label>
                                </div>
                            `;
                        });
                        menusContainer.innerHTML = html;
                    } else {
                        menusContainer.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No menus are assigned to this hall. Please assign menus to the hall first.</div>';
                    }
                })
                .catch(error => {
                    menusLoading.classList.add('d-none');
                    menusContainer.classList.remove('d-none');
                    menusContainer.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading menus. Please try again.</div>';
                    console.error('Error fetching menus:', error);
                });
        });
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Service category filter
    const serviceCategoryFilter = document.getElementById('service-category-filter');
    if (serviceCategoryFilter) {
        serviceCategoryFilter.addEventListener('change', function() {
            const selected = this.value;
            document.querySelectorAll('.svc-category-group').forEach(function(group) {
                if (!selected || group.dataset.category === selected) {
                    group.style.display = '';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    }
});
</script>

<script>
// Shift → Time auto-fill for admin Edit Booking form
(function() {
    var paymentStatusMap = <?php echo json_encode($payment_status_ui_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var shiftTimes = {
        'morning':   { start: '06:00', end: '12:00' },
        'afternoon': { start: '12:00', end: '18:00' },
        'evening':   { start: '18:00', end: '23:00' },
        'fullday':   { start: '06:00', end: '23:00' }
    };
    var shiftSel   = document.getElementById('shift');
    var startInput = document.getElementById('start_time');
    var endInput   = document.getElementById('end_time');
    var paymentStatusInput = document.getElementById('payment_status');
    var bookingStatusInput = document.getElementById('booking_status');
    var bookingStatusBadge = document.getElementById('booking-status-badge');
    var advanceAmountInput = document.getElementById('advance_amount_received');
    var advanceAmountHelp  = document.getElementById('advance-amount-help');
    var previousPaymentStatus = paymentStatusInput ? paymentStatusInput.value : null;
    if (shiftSel && startInput && endInput) {
        shiftSel.addEventListener('change', function() {
            var times = shiftTimes[this.value];
            if (times) {
                startInput.value = times.start;
                endInput.value   = times.end;
            }
        });
    }

    function syncPaymentDetails(shouldAutofillAdvance) {
        if (!paymentStatusInput || !bookingStatusInput || !bookingStatusBadge || !advanceAmountInput) {
            return;
        }

        var config = paymentStatusMap[paymentStatusInput.value];
        if (!config) {
            return;
        }

        bookingStatusInput.value = config.bookingStatus;
        bookingStatusBadge.className = 'badge bg-' + config.badge + ' px-2 py-1 fs-6';
        bookingStatusBadge.textContent = config.label;

        var shouldEnableAdvance = config.advanceRequired;
        advanceAmountInput.readOnly = !shouldEnableAdvance;

        if (!shouldEnableAdvance) {
            advanceAmountInput.value = '';
            if (advanceAmountHelp) {
                advanceAmountHelp.textContent = 'Advance amount is cleared when payment status is pending or cancelled.';
            }
            return;
        }

        if (advanceAmountInput.value === '' && shouldAutofillAdvance) {
            advanceAmountInput.value = paymentStatusInput.value === 'paid'
                ? advanceAmountInput.dataset.defaultPaid
                : advanceAmountInput.dataset.defaultPartial;
        }

        if (advanceAmountHelp) {
            advanceAmountHelp.textContent = paymentStatusInput.value === 'paid'
                ? 'Paid status defaults the advance amount to the full booking total unless you override it.'
                : 'Partial status can store the actual advance amount received from the customer.';
        }
    }

    if (paymentStatusInput) {
        paymentStatusInput.addEventListener('change', function() {
            var oldStatus = previousPaymentStatus;
            previousPaymentStatus = paymentStatusInput.value;
            syncPaymentDetails(oldStatus !== paymentStatusInput.value);
        });
        syncPaymentDetails(advanceAmountInput.value === '');
    }
}());
</script>

<?php 
$extra_js = '<script src="' . BASE_URL . '/admin/js/admin-booking-calendar.js"></script>';
require_once __DIR__ . '/../includes/footer.php'; 
?>
