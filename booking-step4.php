<?php
$page_title = 'Additional Services';
require_once __DIR__ . '/includes/header.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    header('Location: index.php');
    exit;
}

// Save selected menus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menus'])) {
    $_SESSION['selected_menus'] = $_POST['menus'];
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];

// Get all active services
$services = getActiveServices();

// Calculate current totals
$totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests']);
$current_total = $totals['subtotal'];
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
                    <div class="step active">
                        <span class="step-number">4</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step">
                        <span class="step-number">5</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Summary Bar -->
<div class="booking-summary-bar py-2 bg-success text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 col-12">
                <strong><?php echo sanitize($selected_hall['venue_name']); ?> - <?php echo sanitize($selected_hall['name']); ?></strong>
                <span class="mx-2 d-none d-md-inline">|</span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?>
                </span>
            </div>
            <div class="col-md-4 col-12 text-md-end mt-2 mt-md-0">
                <strong>Total: <span id="totalCost"><?php echo formatCurrency($current_total); ?></span></strong>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Additional Services</h2>
        <p class="lead text-muted mb-4">Enhance your event with our premium services (Optional)</p>

        <form id="servicesForm" method="POST" action="booking-step5.php">
            <?php if (empty($services)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No additional services available at this time.
                </div>
            <?php else: ?>
                <?php
                $grouped_services = [];
                foreach ($services as $service) {
                    $category = $service['category'] ?: 'Other';
                    $grouped_services[$category][] = $service;
                }
                ?>

                <?php foreach ($grouped_services as $category => $category_services): ?>
                    <div class="mb-4">
                        <h4 class="mb-3"><?php echo sanitize($category); ?></h4>
                        <div class="row g-3">
                            <?php foreach ($category_services as $service): ?>
                                <div class="col-md-6">
                                    <div class="service-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5 class="card-title">
                                                        <div class="form-check">
                                                            <input class="form-check-input service-checkbox" 
                                                                   type="checkbox" 
                                                                   name="services[]" 
                                                                   value="<?php echo $service['id']; ?>" 
                                                                   id="service<?php echo $service['id']; ?>"
                                                                   data-price="<?php echo $service['price']; ?>">
                                                            <label class="form-check-label" for="service<?php echo $service['id']; ?>">
                                                                <?php echo sanitize($service['name']); ?>
                                                            </label>
                                                        </div>
                                                    </h5>
                                                    <p class="card-text text-muted"><?php echo sanitize($service['description']); ?></p>
                                                </div>
                                                <div class="ms-3">
                                                    <span class="h5 text-success"><?php echo formatCurrency($service['price']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row mt-4">
                <div class="col-md-6">
                    <a href="booking-step3.php" class="btn btn-outline-secondary btn-lg w-100">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<?php
$extra_js = '
<script>
const baseTotal = ' . $current_total . ';
</script>
<script src="' . BASE_URL . '/js/booking-step4.js"></script>
';
require_once __DIR__ . '/includes/footer.php';
?>
