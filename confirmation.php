<?php
$page_title = 'Booking Confirmed';
require_once __DIR__ . '/includes/header.php';

// Check if booking was completed
if (!isset($_SESSION['booking_completed'])) {
    header('Location: index.php');
    exit;
}

$booking_info = $_SESSION['booking_completed'];
$booking = getBookingDetails($booking_info['booking_id']);

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Clear the booking completed session
unset($_SESSION['booking_completed']);
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Success Message -->
                <div class="text-center mb-4">
                    <div class="success-icon mb-3">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h1 class="display-4 text-success mb-3">Booking Confirmed!</h1>
                    <p class="lead">Thank you for your booking. Your reservation has been successfully created.</p>
                    <p class="mb-4">
                        <strong>Booking Number: </strong>
                        <span class="text-success"><?php echo sanitize($booking['booking_number']); ?></span>
                    </p>
                </div>

                <!-- Booking Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Booking Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Customer Information -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-success mb-3">Customer Information</h6>
                                <div class="mb-2">
                                    <strong>Name:</strong> <?php echo sanitize($booking['full_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Phone:</strong> <?php echo sanitize($booking['phone']); ?>
                                </div>
                                <?php if ($booking['email']): ?>
                                    <div class="mb-2">
                                        <strong>Email:</strong> <?php echo sanitize($booking['email']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($booking['address']): ?>
                                    <div class="mb-2">
                                        <strong>Address:</strong> <?php echo sanitize($booking['address']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Event Information -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-success mb-3">Event Information</h6>
                                <div class="mb-2">
                                    <strong>Event Type:</strong> <?php echo sanitize($booking['event_type']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Date:</strong> <?php echo date('F d, Y', strtotime($booking['event_date'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Shift:</strong> <?php echo ucfirst($booking['shift']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Number of Guests:</strong> <?php echo $booking['number_of_guests']; ?> persons
                                </div>
                            </div>

                            <!-- Venue & Hall Information -->
                            <div class="col-md-6 mb-4">
                                <h6 class="text-success mb-3">Venue & Hall</h6>
                                <div class="mb-2">
                                    <strong>Venue:</strong> <?php echo sanitize($booking['venue_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Location:</strong> <?php echo sanitize($booking['location']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Hall:</strong> <?php echo sanitize($booking['hall_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Capacity:</strong> <?php echo $booking['capacity']; ?> persons
                                </div>
                            </div>

                            <!-- Menus -->
                            <?php if (!empty($booking['menus'])): ?>
                                <div class="col-md-12 mb-4">
                                    <h6 class="text-success mb-3">Selected Menus</h6>
                                    <?php foreach ($booking['menus'] as $menu): ?>
                                        <div class="mb-3">
                                            <strong><?php echo sanitize($menu['menu_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo formatCurrency($menu['price_per_person']); ?>/pax × 
                                                <?php echo $menu['number_of_guests']; ?> = 
                                                <?php echo formatCurrency($menu['total_price']); ?>
                                            </small>
                                            
                                            <?php if (!empty($menu['items'])): ?>
                                                <div class="mt-2 ms-3">
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
                                                                <li class="small"><strong><?php echo sanitize($category); ?>:</strong>
                                                                    <ul>
                                                                        <?php foreach ($items as $item): ?>
                                                                            <li class="small"><?php echo sanitize($item['item_name']); ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </li>
                                                            <?php else: ?>
                                                                <?php foreach ($items as $item): ?>
                                                                    <li class="small"><?php echo sanitize($item['item_name']); ?></li>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Services -->
                            <?php if (!empty($booking['services'])): ?>
                                <div class="col-md-12 mb-4">
                                    <h6 class="text-success mb-3">Additional Services</h6>
                                    <div class="row">
                                        <?php foreach ($booking['services'] as $service): ?>
                                            <div class="col-md-6 mb-2">
                                                <i class="fas fa-check text-success"></i>
                                                <?php echo sanitize($service['service_name']); ?>
                                                <span class="text-muted">(<?php echo formatCurrency($service['price']); ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Special Requests -->
                            <?php if ($booking['special_requests']): ?>
                                <div class="col-md-12 mb-4">
                                    <h6 class="text-success mb-3">Special Requests</h6>
                                    <p><?php echo nl2br(sanitize($booking['special_requests'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <!-- Cost Breakdown -->
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <h6 class="text-success mb-3">Cost Breakdown</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Hall Cost:</span>
                                    <strong><?php echo formatCurrency($booking['hall_price']); ?></strong>
                                </div>
                                <?php if ($booking['menu_total'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Menu Cost:</span>
                                        <strong><?php echo formatCurrency($booking['menu_total']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($booking['services_total'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Services Cost:</span>
                                        <strong><?php echo formatCurrency($booking['services_total']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <strong><?php echo formatCurrency($booking['subtotal']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                                    <strong><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <h5>Grand Total:</h5>
                                    <h5 class="text-success"><?php echo formatCurrency($booking['grand_total']); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Status -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">Booking Status</h6>
                                <span class="badge bg-warning text-dark">
                                    <?php echo ucfirst($booking['booking_status']); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Payment Status</h6>
                                <span class="badge bg-danger">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <button onclick="window.print()" class="btn btn-outline-success btn-lg me-2">
                        <i class="fas fa-print"></i> Print Booking
                    </button>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success btn-lg">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>

                <!-- Important Note -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle"></i> Important Information</h6>
                    <ul class="mb-0">
                        <li>Please save your booking number for future reference: <strong><?php echo sanitize($booking['booking_number']); ?></strong></li>
                        <li>Our team will contact you within 24 hours to confirm your booking and payment details.</li>
                        <li>For any queries, please contact us at <?php echo getSetting('contact_phone'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
@media print {
    nav, footer, .btn, .alert-info {
        display: none !important;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
