<?php
$page_title = 'Select Menu';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have booking data and hall selected
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Handle POST: save selections and redirect to step 4
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save selected menu IDs
    $raw_menus = $_POST['menus'] ?? [];
    $clean_menus = array_values(array_filter(array_map('intval', $raw_menus), function($v) { return $v > 0; }));
    $_SESSION['selected_menus'] = $clean_menus;

    // Save custom menu item selections: [menu_id => [group_id => [item_id, ...]]]
    $menu_selections_json = $_POST['menu_selections_json'] ?? '';
    $menu_selections = [];
    if (!empty($menu_selections_json)) {
        $decoded = json_decode($menu_selections_json, true);
        if (is_array($decoded)) {
            // Sanitize: only int keys and int values
            foreach ($decoded as $mid => $groups) {
                $mid_int = intval($mid);
                if ($mid_int <= 0 || !is_array($groups)) continue;
                $menu_selections[$mid_int] = [];
                foreach ($groups as $gid => $item_ids) {
                    $gid_int = intval($gid);
                    if ($gid_int <= 0 || !is_array($item_ids)) continue;
                    $menu_selections[$mid_int][$gid_int] = array_values(array_filter(array_map('intval', $item_ids)));
                }
            }
        }
    }
    $_SESSION['menu_selections'] = $menu_selections;

    // Save special instructions
    $_SESSION['menu_special_instructions'] = trim(strip_tags($_POST['menu_special_instructions'] ?? ''));

    header('Location: booking-step4.php');
    exit;
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

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

$tax_rate = floatval(getSetting('tax_rate', '13'));
$current_total = ($hall_price + $menu_total) * (1 + $tax_rate / 100);
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
                        <span class="step-label">Venue &amp; Hall</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step">
                        <span class="step-number">4</span>
                        <span class="step-label">Packages</span>
                    </div>
                    <div class="step">
                        <span class="step-number">5</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step">
                        <span class="step-number">6</span>
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
                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?> <small class="opacity-75">(<?php echo convertToNepaliDate($booking_data['event_date']); ?>)</small>
                    <span class="mx-1">•</span>
                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                    <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                        (<?php echo formatBookingTime($booking_data['start_time']); ?> – <?php echo formatBookingTime($booking_data['end_time']); ?>)
                    <?php endif; ?>
                    <span class="mx-2 d-none d-md-inline">|</span>
                </span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-users"></i> <?php echo $booking_data['guests']; ?> Guests
                </span>
            </div>
            <div class="col-md-4 col-12 text-md-end mt-2 mt-md-0">
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
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> No menus are assigned to this hall. You can continue to the next step.
            </div>
            <form method="POST" action="booking-step4.php">
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
        <?php else: ?>
            <form id="menuForm" method="POST" action="booking-step3.php">
                <div class="mb-4" id="menuSearchWrapper">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="menuSearchInput" class="form-control"
                               placeholder="Search menus by name..."
                               aria-label="Search menus by name">
                        <button class="btn btn-outline-secondary" type="button" id="menuSearchClear" style="display:none;" aria-label="Clear search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div id="menuSearchNoResults" class="alert alert-info" style="display:none;">
                    <i class="fas fa-info-circle"></i> No menus found matching your search.
                </div>
                <div class="row g-4" id="menusContainer">
                    <?php foreach ($menus as $menu): ?>
                        <?php
                        $menu_items = getMenuItems($menu['id']);
                        $is_selected = in_array($menu['id'], $selected_menus);
                        // Build grouped items for JS summary panel
                        $menu_items_for_js = [];
                        foreach ($menu_items as $mi) {
                            $cat = $mi['category'] ?? '';
                            $menu_items_for_js[] = ['category' => $cat, 'item_name' => $mi['item_name']];
                        }
                        ?>
                        <div class="col-md-6 col-lg-4" data-menu-name="<?php echo htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8'); ?>" data-menu-items="<?php echo htmlspecialchars(json_encode($menu_items_for_js), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="menu-card card h-100 <?php echo $is_selected ? 'selected' : ''; ?>">
                                <?php if ($menu['image']): ?>
                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($menu['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="card-img-top" loading="lazy" alt="<?php echo sanitize($menu['name']); ?>">
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
                                        <!-- Desktop: Show all items -->
                                        <div class="d-none d-md-block">
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
                                        </div>
                                        
                                        <!-- Mobile: Collapsible with "View Items" button -->
                                        <div class="d-md-none">
                                            <button class="btn btn-sm btn-outline-success w-100" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#menuItems<?php echo $menu['id']; ?>" 
                                                    aria-expanded="false">
                                                <i class="fas fa-list me-1"></i> View Menu Items 
                                                <i class="fas fa-chevron-down ms-1"></i>
                                            </button>
                                            <div class="collapse mt-2" id="menuItems<?php echo $menu['id']; ?>">
                                                <div class="menu-items small">
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
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Menu Selection Panel (shown when menu with sections is selected) -->
                <div id="customMenuPanel" class="mt-4" style="display:none;">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header border-0" style="background:linear-gradient(135deg,#14532d 0%,#166534 100%);">
                            <div class="d-flex align-items-center gap-2 text-white">
                                <i class="fas fa-clipboard-list fs-5"></i>
                                <div>
                                    <div class="fw-bold">Customize Your Menu Selections</div>
                                    <div class="opacity-75" style="font-size:0.8rem;">Tap any item to select or deselect it. Limits are shown per group/section.</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3" id="customMenuPanelBody">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Hidden field storing the JSON of custom menu item selections -->
                <input type="hidden" name="menu_selections_json" id="menuSelectionsJson" value="">

                <!-- Selected Menus Confirmation Summary (shown when any menus are selected) -->
                <div id="selectedMenusSummary" class="mt-4" style="display:none;">
                    <div class="card border-0" style="border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(21,128,61,0.13);">
                        <div class="card-header border-0 py-3 px-4" style="background:linear-gradient(135deg,#15803d 0%,#166534 100%);">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:42px;height:42px;background:rgba(255,255,255,0.18);">
                                    <i class="fas fa-clipboard-check text-white" style="font-size:1.1rem;"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-white" style="font-size:1rem;">Your Menu Selection Summary</div>
                                    <div class="text-white-50" style="font-size:0.78rem;">Review the items below before continuing</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-4 py-3" id="selectedMenusSummaryBody">
                            <!-- Populated by JS -->
                        </div>
                        <div class="card-footer border-0 px-4 py-2" style="background:#f0fdf4;">
                            <div class="d-flex align-items-center gap-2 text-success" style="font-size:0.82rem;">
                                <i class="fas fa-shield-alt"></i>
                                <span>Please verify that all selected items are correct before clicking <strong>Continue</strong>.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Special Instructions -->
                <div class="mt-4" id="menuSpecialInstructions" style="display:none;">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-comment-alt me-2 text-success"></i>Special Instructions</h5>
                            <p class="text-muted small">Any special dietary requirements or menu customization notes?</p>
                            <textarea class="form-control" name="menu_special_instructions" id="menuSpecialInstructionsText" rows="3"
                                      placeholder="e.g. No garlic in soups, extra spicy starters, nut-free desserts..."><?php echo sanitize($_SESSION['menu_special_instructions'] ?? ''); ?></textarea>
                        </div>
                    </div>
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
const taxRate = ' . $tax_rate . ';
const BASE_URL = ' . json_encode(BASE_URL) . ';
const CURRENCY = ' . json_encode(getSetting('currency', 'Rs.')) . ';
const menuSelectionsSession = ' . json_encode($_SESSION['menu_selections'] ?? []) . ';
const menuSpecialInstructionsSession = ' . json_encode($_SESSION['menu_special_instructions'] ?? '') . ';
</script>
<script src="' . BASE_URL . '/js/booking-step3.js"></script>
<script src="' . BASE_URL . '/js/booking-step3-menu.js"></script>
';
require_once __DIR__ . '/includes/footer.php';
?>
