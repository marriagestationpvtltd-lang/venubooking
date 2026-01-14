<?php
$page_title = 'Complete Your Booking';
require_once __DIR__ . '/includes/header.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    header('Location: index.php');
    exit;
}

// Save selected services
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['selected_services'] = $_POST['services'] ?? [];
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];
$selected_services = $_SESSION['selected_services'] ?? [];

// Calculate final totals
$totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], $selected_services);

// Get menu details
$menu_details = [];
if (!empty($selected_menus)) {
    $db = getDB();
    $placeholders = str_repeat('?,', count($selected_menus) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM menus WHERE id IN ($placeholders)");
    $stmt->execute($selected_menus);
    $menu_details = $stmt->fetchAll();
    
    // Get menu items for each menu
    foreach ($menu_details as &$menu) {
        $stmt = $db->prepare("SELECT item_name, category, display_order FROM menu_items WHERE menu_id = ? ORDER BY display_order, category");
        $stmt->execute([$menu['id']]);
        $menu['items'] = $stmt->fetchAll();
    }
}

// Get service details
$service_details = [];
if (!empty($selected_services)) {
    $db = getDB();
    $placeholders = str_repeat('?,', count($selected_services) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM additional_services WHERE id IN ($placeholders)");
    $stmt->execute($selected_services);
    $service_details = $stmt->fetchAll();
}

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    // Validate inputs
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $special_requests = trim($_POST['special_requests']);

    if (empty($full_name) || empty($phone)) {
        $error = 'Full name and phone number are required.';
    } else {
        // Create booking
        $booking_result = createBooking([
            'hall_id' => $selected_hall['id'],
            'event_date' => $booking_data['event_date'],
            'shift' => $booking_data['shift'],
            'event_type' => $booking_data['event_type'],
            'guests' => $booking_data['guests'],
            'menus' => $selected_menus,
            'services' => $selected_services,
            'full_name' => $full_name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'special_requests' => $special_requests
        ]);

        if ($booking_result['success']) {
            // Clear booking session
            $_SESSION['booking_completed'] = [
                'booking_id' => $booking_result['booking_id'],
                'booking_number' => $booking_result['booking_number']
            ];
            unset($_SESSION['booking_data']);
            unset($_SESSION['selected_hall']);
            unset($_SESSION['selected_menus']);
            unset($_SESSION['selected_services']);
            
            header('Location: confirmation.php');
            exit;
        } else {
            $error = $booking_result['error'];
        }
    }
}
?>

<!-- Booking Progress -->
<div class="booking-progress py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="progress-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-label">Details</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">2</span>
                        <span class="step-label">Venue & Hall</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">4</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">5</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Customer Information Form -->
            <div class="col-lg-8">
                <h2 class="mb-4">Your Information</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?>
                    </div>
                <?php endif; ?>

                <form id="customerForm" method="POST">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="special_requests" class="form-label">Special Requests</label>
                                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" 
                                          placeholder="Any special requirements or requests for your event..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <a href="booking-step4.php" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="submit_booking" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-check"></i> Confirm Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Booking Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Booking Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Event Details -->
                        <h6 class="mb-3">Event Details</h6>
                        <div class="mb-3">
                            <small class="text-muted">Event Type:</small><br>
                            <strong><?php echo sanitize($booking_data['event_type']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Date & Shift:</small><br>
                            <strong><?php echo date('F d, Y', strtotime($booking_data['event_date'])); ?></strong><br>
                            <small><?php echo ucfirst($booking_data['shift']); ?></small>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Number of Guests:</small><br>
                            <strong><?php echo $booking_data['guests']; ?> persons</strong>
                        </div>

                        <hr>

                        <!-- Venue & Hall -->
                        <h6 class="mb-3">Venue & Hall</h6>
                        <div class="mb-3">
                            <strong><?php echo sanitize($selected_hall['venue_name']); ?></strong><br>
                            <small><?php echo sanitize($selected_hall['name']); ?> (<?php echo $selected_hall['capacity']; ?> pax)</small>
                        </div>

                        <hr>

                        <!-- Menus -->
                        <?php if (!empty($menu_details)): ?>
                            <h6 class="mb-3">Selected Menus</h6>
                            <?php foreach ($menu_details as $menu): ?>
                                <div class="mb-3">
                                    <small><strong><?php echo sanitize($menu['name']); ?></strong></small><br>
                                    <small class="text-muted"><?php echo formatCurrency($menu['price_per_person']); ?>/pax</small>
                                    
                                    <?php if (!empty($menu['items'])): ?>
                                        <div class="mt-1 ms-2">
                                            <small class="text-muted d-block">Menu Items:</small>
                                            <ul class="small mb-0 mt-1" style="list-style-type: disc; padding-left: 20px;">
                                                <?php 
                                                $items_by_category = [];
                                                foreach ($menu['items'] as $item) {
                                                    $category = !empty($item['category']) ? $item['category'] : 'Other';
                                                    $items_by_category[$category][] = $item;
                                                }
                                                
                                                foreach ($items_by_category as $category => $items): 
                                                ?>
                                                    <?php if (count($items_by_category) > 1): ?>
                                                        <li><strong><?php echo sanitize($category); ?>:</strong>
                                                            <ul style="list-style-type: circle;">
                                                                <?php foreach ($items as $item): ?>
                                                                    <li><?php echo sanitize($item['item_name']); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php else: ?>
                                                        <?php foreach ($items as $item): ?>
                                                            <li><?php echo sanitize($item['item_name']); ?></li>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <hr>
                        <?php endif; ?>

                        <!-- Services -->
                        <?php if (!empty($service_details)): ?>
                            <h6 class="mb-3">Additional Services</h6>
                            <?php foreach ($service_details as $service): ?>
                                <div class="mb-2">
                                    <small><?php echo sanitize($service['name']); ?></small><br>
                                    <small class="text-muted"><?php echo formatCurrency($service['price']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <hr>
                        <?php endif; ?>

                        <!-- Cost Breakdown -->
                        <h6 class="mb-3">Cost Breakdown</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Hall Cost:</span>
                            <strong><?php echo formatCurrency($totals['hall_price']); ?></strong>
                        </div>
                        <?php if ($totals['menu_total'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Menu Cost:</span>
                                <strong><?php echo formatCurrency($totals['menu_total']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($totals['services_total'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Services Cost:</span>
                                <strong><?php echo formatCurrency($totals['services_total']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong><?php echo formatCurrency($totals['subtotal']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                            <strong><?php echo formatCurrency($totals['tax_amount']); ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <h5>Grand Total:</h5>
                            <h5 class="text-success"><?php echo formatCurrency($totals['grand_total']); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
