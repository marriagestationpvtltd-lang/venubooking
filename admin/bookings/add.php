<?php
$page_title = 'Add New Booking';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch venues
$venues = $db->query("SELECT id, name FROM venues WHERE status = 'active' ORDER BY name")->fetchAll();

// Fetch halls
$halls = $db->query("SELECT h.id, h.name, v.name as venue_name, h.capacity FROM halls h INNER JOIN venues v ON h.venue_id = v.id WHERE h.status = 'active' ORDER BY v.name, h.name")->fetchAll();

// Fetch menus
$menus = $db->query("SELECT id, name, price_per_person FROM menus WHERE status = 'active' ORDER BY name")->fetchAll();

// Fetch services
$services = $db->query("SELECT id, name, price, category FROM additional_services WHERE status = 'active' ORDER BY category, name")->fetchAll();

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
    $selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
    $selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $booking_status = $_POST['booking_status'];
    $payment_status = $_POST['payment_status'];

    // Validation
    if (empty($full_name) || empty($phone) || $hall_id <= 0 || empty($event_date) || $number_of_guests <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Check availability
        if (!checkHallAvailability($hall_id, $event_date, $shift)) {
            $error_message = 'This hall is not available for the selected date and shift.';
        } else {
            try {
                $db->beginTransaction();
                
                // Generate booking number
                $booking_number = generateBookingNumber();
                
                // Get or create customer
                $customer_id = getOrCreateCustomer($full_name, $phone, $email, $address);
                
                // Calculate totals
                $totals = calculateBookingTotal($hall_id, $selected_menus, $number_of_guests, $selected_services);
                
                // Insert booking
                $sql = "INSERT INTO bookings (
                            booking_number, customer_id, hall_id, event_date, shift, 
                            event_type, number_of_guests, hall_price, menu_total, 
                            services_total, subtotal, tax_amount, grand_total, 
                            special_requests, booking_status, payment_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $booking_number,
                    $customer_id,
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
                    $payment_status
                ]);
                
                $booking_id = $db->lastInsertId();
                
                // Insert booking menus
                if (!empty($selected_menus)) {
                    foreach ($selected_menus as $menu_id) {
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
                
                // Insert booking services
                if (!empty($selected_services)) {
                    foreach ($selected_services as $service_id) {
                        $stmt = $db->prepare("SELECT name, price FROM additional_services WHERE id = ?");
                        $stmt->execute([$service_id]);
                        $service = $stmt->fetch();
                        
                        if ($service) {
                            $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$booking_id, $service_id, $service['name'], $service['price']]);
                        }
                    }
                }
                
                // Log activity
                logActivity($current_user['id'], 'Added new booking', 'bookings', $booking_id, "Added booking: $booking_number");
                
                $db->commit();
                
                header('Location: view.php?id=' . $booking_id);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Booking</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
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
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Event Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hall_id" class="form-label">Hall <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_id" name="hall_id" required>
                                    <option value="">Select Hall</option>
                                    <?php foreach ($halls as $hall): ?>
                                        <option value="<?php echo $hall['id']; ?>" <?php echo (isset($_POST['hall_id']) && $_POST['hall_id'] == $hall['id']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo isset($_POST['event_type']) ? htmlspecialchars($_POST['event_type']) : ''; ?>" 
                                       placeholder="e.g., Wedding, Birthday" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : ''; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="shift" class="form-label">Shift <span class="text-danger">*</span></label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="morning" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'morning') ? 'selected' : ''; ?>>Morning</option>
                                    <option value="afternoon" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'afternoon') ? 'selected' : ''; ?>>Afternoon</option>
                                    <option value="evening" <?php echo (!isset($_POST['shift']) || $_POST['shift'] == 'evening') ? 'selected' : ''; ?>>Evening</option>
                                    <option value="fullday" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'fullday') ? 'selected' : ''; ?>>Full Day</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="number_of_guests" class="form-label">Number of Guests <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" 
                                       value="<?php echo isset($_POST['number_of_guests']) ? $_POST['number_of_guests'] : ''; ?>" 
                                       min="1" required>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Menus & Services</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Menus (Optional)</label>
                                <?php foreach ($menus as $menu): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menus[]" value="<?php echo $menu['id']; ?>" 
                                           id="menu_<?php echo $menu['id']; ?>" 
                                           <?php echo (isset($_POST['menus']) && in_array($menu['id'], $_POST['menus'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="menu_<?php echo $menu['id']; ?>">
                                        <?php echo htmlspecialchars($menu['name']) . ' - ' . formatCurrency($menu['price_per_person']) . '/person'; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Additional Services (Optional)</label>
                                <?php foreach ($services as $service): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                           id="service_<?php echo $service['id']; ?>" 
                                           <?php echo (isset($_POST['services']) && in_array($service['id'], $_POST['services'])) ? 'checked' : ''; ?>>
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
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"><?php echo isset($_POST['special_requests']) ? htmlspecialchars($_POST['special_requests']) : ''; ?></textarea>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Booking Status</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="booking_status" class="form-label">Booking Status</label>
                                <select class="form-select" id="booking_status" name="booking_status">
                                    <option value="pending" <?php echo (!isset($_POST['booking_status']) || $_POST['booking_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo (isset($_POST['booking_status']) && $_POST['booking_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo (isset($_POST['booking_status']) && $_POST['booking_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo (isset($_POST['booking_status']) && $_POST['booking_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="unpaid" <?php echo (!isset($_POST['payment_status']) || $_POST['payment_status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                    <option value="partial" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Create Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
