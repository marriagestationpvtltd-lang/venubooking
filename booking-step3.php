<?php
$page_title = 'Select Menu';
require_once __DIR__ . '/includes/header.php';

// Check if we have booking data and hall selected
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    header('Location: index.php');
    exit;
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];

// Get available menus for the selected hall
$menus = getMenusForHall($selected_hall['id']);

// Calculate current totals
$hall_price = $selected_hall['base_price'];
$menu_total = 0;
$selected_menus = $_SESSION['selected_menus'] ?? [];

if (!empty($selected_menus)) {
    $totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests']);
    $menu_total = $totals['menu_total'];
}

$current_total = $hall_price + $menu_total;
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
                    <div class="step active">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step">
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
            <div class="col-md-8">
                <strong><?php echo sanitize($selected_hall['venue_name']); ?> - <?php echo sanitize($selected_hall['name']); ?></strong>
                <span class="mx-2">|</span>
                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?>
                <span class="mx-2">|</span>
                <i class="fas fa-users"></i> <?php echo $booking_data['guests']; ?> Guests
            </div>
            <div class="col-md-4 text-end">
                <div class="small">Hall: <?php echo formatCurrency($hall_price); ?></div>
                <strong>Total: <span id="totalCost"><?php echo formatCurrency($current_total); ?></span></strong>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Select Your Menu</h2>
        <p class="lead text-muted mb-4">Choose one or more menus for your event</p>

        <?php if (empty($menus)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No menus available for this hall.
            </div>
        <?php else: ?>
            <form id="menuForm" method="POST" action="booking-step4.php">
                <div class="row g-4">
                    <?php foreach ($menus as $menu): ?>
                        <?php
                        $menu_items = getMenuItems($menu['id']);
                        $is_selected = in_array($menu['id'], $selected_menus);
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="menu-card card h-100 <?php echo $is_selected ? 'selected' : ''; ?>">
                                <?php if ($menu['image']): ?>
                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($menu['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="card-img-top" alt="<?php echo sanitize($menu['name']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0"><?php echo sanitize($menu['name']); ?></h5>
                                        <div class="form-check">
                                            <input class="form-check-input menu-checkbox" type="checkbox" 
                                                   name="menus[]" value="<?php echo $menu['id']; ?>" 
                                                   id="menu<?php echo $menu['id']; ?>"
                                                   data-price="<?php echo $menu['price_per_person']; ?>"
                                                   <?php echo $is_selected ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                    <p class="h4 text-success mb-3">
                                        <?php echo formatCurrency($menu['price_per_person']); ?>/pax
                                    </p>
                                    <p class="card-text text-muted"><?php echo sanitize($menu['description']); ?></p>
                                    
                                    <?php if (!empty($menu_items)): ?>
                                        <hr>
                                        <h6 class="mb-2">Menu Items:</h6>
                                        <div class="menu-items">
                                            <?php
                                            $grouped_items = [];
                                            foreach ($menu_items as $item) {
                                                $category = $item['category'] ?: 'Other';
                                                $grouped_items[$category][] = $item;
                                            }
                                            foreach ($grouped_items as $category => $items):
                                            ?>
                                                <div class="mb-2">
                                                    <strong class="text-success"><?php echo sanitize($category); ?>:</strong>
                                                    <ul class="mb-0 ps-3">
                                                        <?php foreach ($items as $item): ?>
                                                            <li><?php echo sanitize($item['item_name']); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <a href="booking-step2.php" class="btn btn-outline-secondary btn-lg w-100">
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
        <?php endif; ?>
    </div>
</section>

<!-- Menu Customization Modal -->
<div class="modal fade" id="menuCustomizationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Menu Customization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Would you like to design your menu now?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="continueBooking()">
                    Later
                </button>
                <button type="button" class="btn btn-success" onclick="designMenu()">
                    Yes, Design Now
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
const bookingData = ' . json_encode($booking_data) . ';
const hallPrice = ' . $hall_price . ';
const guestsCount = ' . $booking_data['guests'] . ';
const baseUrl = "' . BASE_URL . '";
</script>
<script src="' . BASE_URL . '/js/booking-step3.js"></script>
';
require_once __DIR__ . '/includes/footer.php';
?>
