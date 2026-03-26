<?php
$page_title = 'Service Packages';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Save selected menus when arriving from step 3 (booking-step3.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menus'])) {
    $_SESSION['selected_menus'] = $_POST['menus'];
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

$booking_data   = $_SESSION['booking_data'];
$selected_hall  = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];

// Get service packages grouped by category
$packages_by_category = getServicePackagesByCategory();
// Keep only categories that have active packages
$packages_by_category = array_filter($packages_by_category, function ($cat) {
    return !empty($cat['packages']);
});

// Calculate current totals (no packages selected yet on initial load)
$totals        = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], [], [], [], $selected_hall['base_price'], $_SESSION['menu_selections'] ?? []);
$tax_rate      = floatval(getSetting('tax_rate', '13'));
$current_total = $totals['grand_total'];
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
                    <div class="step completed">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step active">
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
                    <span class="mx-1">&bull;</span>
                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                    <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                        (<?php echo formatBookingTime($booking_data['start_time']); ?> &ndash; <?php echo formatBookingTime($booking_data['end_time']); ?>)
                    <?php endif; ?>
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
        <h2 class="mb-2">Service Packages</h2>
        <p class="lead text-muted mb-4">Choose a pre-configured service package (Optional)</p>

        <form id="packagesForm" method="POST" action="booking-step5.php">

            <?php if (empty($packages_by_category)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No service packages available at this time.
                </div>
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
            <?php else: ?>
                <!-- Group Accordion -->
                <div class="pkg-groups-accordion mb-4" id="pkgGroupsAccordion">
                    <?php $pkg_cat_index = 0; foreach ($packages_by_category as $cat): ?>
                        <?php if (empty($cat['packages'])) continue; ?>
                        <div class="pkg-group-item <?php echo $pkg_cat_index === 0 ? 'pkg-group-active' : ''; ?>"
                             data-cat-id="<?php echo (int)$cat['id']; ?>">

                            <!-- Group Header (always visible horizontal row) -->
                            <div class="pkg-group-header" role="button" aria-expanded="<?php echo $pkg_cat_index === 0 ? 'true' : 'false'; ?>">
                                <div class="pkg-group-header-left">
                                    <span class="pkg-group-icon">
                                        <i class="fas fa-tag" aria-hidden="true"></i>
                                    </span>
                                    <span class="pkg-group-name-text"><?php echo sanitize($cat['name']); ?></span>

                                    <!-- Inline summary: shown when group is collapsed and has selections -->
                                    <span class="pkg-group-divider d-none">|</span>
                                    <span class="pkg-group-summary-inline" aria-live="polite">
                                        <i class="fas fa-check-circle pkg-group-summary-check" aria-hidden="true"></i>
                                        <span class="pkg-group-summary-text"></span>
                                        <span class="pkg-group-summary-cost d-none"></span>
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down pkg-group-chevron" aria-hidden="true"></i>
                            </div>

                            <!-- Group Body (package grid, visible only when active) -->
                            <div class="pkg-group-body">
                                <div class="row g-3">
                                    <?php foreach ($cat['packages'] as $pkg): ?>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="card package-select-card h-100">
                                                <?php if (!empty($pkg['photos'])): ?>
                                                    <?php if (count($pkg['photos']) > 1): ?>
                                                        <?php $pid = 'pkgCarousel' . $pkg['id']; ?>
                                                        <div id="<?php echo $pid; ?>" class="carousel slide" data-bs-ride="false">
                                                            <div class="carousel-indicators">
                                                                <?php foreach ($pkg['photos'] as $pi => $ph): ?>
                                                                    <button type="button"
                                                                            data-bs-target="#<?php echo $pid; ?>"
                                                                            data-bs-slide-to="<?php echo $pi; ?>"
                                                                            <?php if ($pi === 0) echo 'class="active" aria-current="true"'; ?>
                                                                            aria-label="Photo <?php echo $pi + 1; ?>">
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="carousel-inner">
                                                                <?php foreach ($pkg['photos'] as $pi => $photo): ?>
                                                                    <div class="carousel-item <?php echo ($pi === 0) ? 'active' : ''; ?>">
                                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo); ?>"
                                                                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                                                             class="d-block w-100"
                                                                             style="height:200px;object-fit:cover;">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $pid; ?>" data-bs-slide="prev">
                                                                <span class="carousel-control-prev-icon"></span>
                                                            </button>
                                                            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $pid; ?>" data-bs-slide="next">
                                                                <span class="carousel-control-next-icon"></span>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($pkg['photos'][0]); ?>"
                                                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                                             class="card-img-top"
                                                             style="height:200px;object-fit:cover;">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light"
                                                         style="height:200px;">
                                                        <i class="fas fa-box fa-3x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="form-check flex-grow-1 me-2">
                                                            <input class="form-check-input package-checkbox"
                                                                   type="checkbox"
                                                                   name="packages[]"
                                                                   value="<?php echo $pkg['id']; ?>"
                                                                   id="pkg<?php echo $pkg['id']; ?>"
                                                                   data-price="<?php echo htmlspecialchars($pkg['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                   data-pkg-name="<?php echo htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <label class="form-check-label fw-semibold" for="pkg<?php echo $pkg['id']; ?>">
                                                                <?php echo sanitize($pkg['name']); ?>
                                                            </label>
                                                        </div>
                                                        <span class="text-success fw-bold text-nowrap">
                                                            <?php echo formatCurrency($pkg['price']); ?>
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($pkg['description'])): ?>
                                                        <p class="text-muted small mb-2"><?php echo sanitize($pkg['description']); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($pkg['features'])): ?>
                                                        <div class="pkg-feat-icons mt-auto mb-0">
                                                            <?php foreach (array_slice($pkg['features'], 0, 6) as $feat): ?>
                                                            <div class="pkg-feat-icon-item" title="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php if (!empty($feat['service_photo'])): ?>
                                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($feat['service_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                     class="pkg-feat-icon-img"
                                                                     loading="lazy"
                                                                     alt="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php else: ?>
                                                                <div class="pkg-feat-icon-fallback">
                                                                    <i class="fas fa-check" aria-hidden="true"></i>
                                                                </div>
                                                                <?php endif; ?>
                                                                <p class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                            </div>
                                                            <?php endforeach; ?>
                                                            <?php if (count($pkg['features']) > 6): ?>
                                                            <div class="pkg-feat-icon-item" title="+<?php echo count($pkg['features']) - 6; ?> more features">
                                                                <div class="pkg-feat-more-chip">+<?php echo count($pkg['features']) - 6; ?></div>
                                                                <p class="pkg-feat-icon-label">थप</p>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php $pkg_cat_index++; endforeach; ?>
                </div>

                <div class="row mt-4">
                    <div class="col-12 mb-2 text-center">
                        <button type="submit" name="skip_packages" value="1"
                                id="skipPackagesBtn"
                                class="btn btn-link text-muted">
                            <i class="fas fa-forward me-1"></i> Skip Packages &rarr;
                        </button>
                    </div>
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
            <?php endif; ?>

        </form>
    </div>
</section>

<!-- JSON data for JS -->
<script>
const baseTotal = <?php echo json_encode($totals['subtotal']); ?>;
const taxRate   = <?php echo json_encode($tax_rate); ?>;
const currency  = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
// Uncheck all packages when the Skip button is clicked
document.addEventListener('DOMContentLoaded', function() {
    var skipBtn = document.getElementById('skipPackagesBtn');
    if (skipBtn) {
        skipBtn.addEventListener('click', function() {
            document.querySelectorAll('.package-checkbox').forEach(function(c) {
                c.checked = false;
            });
        });
    }
});
</script>
<?php
$extra_js = '<script src="' . BASE_URL . '/js/booking-step4.js"></script>'
          . '<script src="' . BASE_URL . '/js/design-zoom.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
