<?php
$page_title = 'Edit Booking';
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
$services = $db->query("SELECT id, name, price, category FROM additional_services WHERE status = 'active' ORDER BY category, name")->fetchAll();

// Fetch active payment methods
$payment_methods = getActivePaymentMethods();

// Get currently selected menus
$selected_menus = array_column($booking['menus'], 'menu_id');

// Get currently selected services
$selected_services = array_column($booking['services'], 'service_id');

// Get currently selected payment methods
$selected_payment_methods = array_column(getBookingPaymentMethods($booking_id), 'id');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $hall_id = intval($_POST['hall_id']);
    $event_date = $_POST['event_date'];
    $shift = $_POST['shift'];
    $event_type = trim($_POST['event_type']);
    $number_of_guests = intval($_POST['number_of_guests']);
    $special_requests = trim($_POST['special_requests']);
    $post_selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
    $post_selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $post_selected_payment_methods = isset($_POST['payment_methods']) ? $_POST['payment_methods'] : [];
    $booking_status = $_POST['booking_status'];
    $payment_status = $_POST['payment_status'];
    
    // Store old status for email notification
    $old_booking_status = $booking['booking_status'];
    $old_payment_status = $booking['payment_status'];
    $status_changed = ($old_booking_status !== $booking_status) || ($old_payment_status !== $payment_status);

    // Validation
    if (empty($full_name) || empty($phone) || $hall_id <= 0 || empty($event_date) || $number_of_guests <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
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
                
                // Calculate totals
                $totals = calculateBookingTotal($hall_id, $post_selected_menus, $number_of_guests, $post_selected_services);
                
                // Update booking
                $sql = "UPDATE bookings SET 
                        hall_id = ?, event_date = ?, shift = ?, 
                        event_type = ?, number_of_guests = ?, hall_price = ?, menu_total = ?, 
                        services_total = ?, subtotal = ?, tax_amount = ?, grand_total = ?, 
                        special_requests = ?, booking_status = ?, payment_status = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $hall_id,
                    $event_date,
                    $shift,
                    $event_type,
                    $number_of_guests,
                    $totals['hall_price'],
                    $totals['menu_total'],
                    $totals['services_total'],
                    $totals['subtotal'],
                    $totals['tax_amount'],
                    $totals['grand_total'],
                    $special_requests,
                    $booking_status,
                    $payment_status,
                    $booking_id
                ]);
                
                // Delete old menus and services
                $db->prepare("DELETE FROM booking_menus WHERE booking_id = ?")->execute([$booking_id]);
                $db->prepare("DELETE FROM booking_services WHERE booking_id = ?")->execute([$booking_id]);
                
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
                
                // Insert new booking services
                if (!empty($post_selected_services)) {
                    foreach ($post_selected_services as $service_id) {
                        $stmt = $db->prepare("SELECT name, price, description, category FROM additional_services WHERE id = ?");
                        $stmt->execute([$service_id]);
                        $service = $stmt->fetch();
                        
                        if ($service) {
                            $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$booking_id, $service_id, $service['name'], $service['price'], $service['description'], $service['category']]);
                        }
                    }
                }
                
                // Link payment methods to booking
                linkPaymentMethodsToBooking($booking_id, $post_selected_payment_methods);
                
                // Log activity
                logActivity($current_user['id'], 'Updated booking', 'bookings', $booking_id, "Updated booking: {$booking['booking_number']}");
                
                $db->commit();
                
                // Send email notifications if status changed
                if ($status_changed) {
                    sendBookingNotification($booking_id, 'update', $old_booking_status);
                }
                
                $success_message = 'Booking updated successfully!';
                
                // Refresh booking data
                $booking = getBookingDetails($booking_id);
                $selected_menus = array_column($booking['menus'], 'menu_id');
                $selected_services = array_column($booking['services'], 'service_id');
                $selected_payment_methods = array_column(getBookingPaymentMethods($booking_id), 'id');
                
            } catch (Exception $e) {
                $db->rollBack();
                // Log the error for debugging
                error_log('Booking update error: ' . $e->getMessage());
                $error_message = 'Error updating booking. Please try again or contact support.';
            }
        }
    }
}
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
                                    <option value="morning" <?php echo ($booking['shift'] == 'morning') ? 'selected' : ''; ?>>Morning</option>
                                    <option value="afternoon" <?php echo ($booking['shift'] == 'afternoon') ? 'selected' : ''; ?>>Afternoon</option>
                                    <option value="evening" <?php echo ($booking['shift'] == 'evening') ? 'selected' : ''; ?>>Evening</option>
                                    <option value="fullday" <?php echo ($booking['shift'] == 'fullday') ? 'selected' : ''; ?>>Full Day</option>
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
                                <?php foreach ($services as $service): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                           id="service_<?php echo $service['id']; ?>" 
                                           <?php echo in_array($service['id'], $selected_services) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="service_<?php echo $service['id']; ?>">
                                        <?php echo htmlspecialchars($service['name']) . ' - ' . formatCurrency($service['price']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

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
                                <label for="booking_status" class="form-label">Booking Status</label>
                                <select class="form-select" id="booking_status" name="booking_status">
                                    <option value="pending" <?php echo ($booking['booking_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo ($booking['booking_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo ($booking['booking_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo ($booking['booking_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="pending" <?php echo ($booking['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo ($booking['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo ($booking['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo ($booking['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <small class="text-muted">Flow: Pending → Partial → Paid</small>
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
});
</script>

<?php 
$extra_js = '<script src="' . BASE_URL . '/admin/js/admin-booking-calendar.js"></script>';
require_once __DIR__ . '/../includes/footer.php'; 
?>
