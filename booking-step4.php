<?php
$page_title = 'Additional Services';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

// Save selected menus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menus'])) {
    $_SESSION['selected_menus'] = $_POST['menus'];
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];

// Get all active services, enriched with direct designs
$services = getActiveServices();
$services_map = []; // keyed by service id
foreach ($services as &$svc) {
    $svc['designs'] = getServiceDesigns($svc['id']);
    $svc['has_designs'] = !empty($svc['designs']);
    $services_map[$svc['id']] = $svc;
}
unset($svc);

// Get service packages grouped by category (for package selection section)
$packages_by_category = getServicePackagesByCategory();
// Keep only categories that have active packages
$packages_by_category = array_filter($packages_by_category, function ($cat) {
    return !empty($cat['packages']);
});

// Calculate current totals (no services/designs/packages selected yet in initial load)
$totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests']);
$tax_rate = floatval(getSetting('tax_rate', '13'));
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
                    <span class="mx-1">•</span>
                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                    <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                        (<?php echo formatBookingTime($booking_data['start_time']); ?> – <?php echo formatBookingTime($booking_data['end_time']); ?>)
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

        <!-- Breadcrumb navigation – shown when drilling into designs -->
        <nav id="booking-breadcrumb" aria-label="breadcrumb" style="display:none;" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="#" id="bc-services" onclick="backToServices(); return false;">
                        <i class="fas fa-th-large me-1"></i>All Services
                    </a>
                </li>
                <li class="breadcrumb-item active" id="bc-service-name" style="display:none;"></li>
            </ol>
        </nav>

        <!-- =====================================================================
             VIEW 1: Main Services List
             ===================================================================== -->
        <div id="view-services">
            <h2 class="mb-2">Additional Services</h2>
            <p class="lead text-muted mb-4">Enhance your event with our premium services (Optional)</p>

            <form id="servicesForm" method="POST" action="booking-step5.php">
                <?php if (!empty($packages_by_category)): ?>
                <!-- ============================================================
                     SERVICE PACKAGES – photo cards with features, grouped by category
                     ============================================================ -->
                <div id="packages-section" class="mb-5">
                    <h3 class="mb-1"><i class="fas fa-box-open me-2 text-success"></i>Service Packages</h3>
                    <p class="text-muted mb-4">Choose a pre-configured service package (Optional)</p>

                    <?php foreach ($packages_by_category as $cat): ?>
                        <?php if (empty($cat['packages'])) continue; ?>
                        <h5 class="mb-3 text-secondary">
                            <i class="fas fa-tag me-1"></i><?php echo sanitize($cat['name']); ?>
                        </h5>
                        <div class="row g-3 mb-4">
                            <?php foreach ($cat['packages'] as $pkg): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card package-select-card h-100 shadow-sm" style="transition:box-shadow .2s;">
                                        <?php if (!empty($pkg['photos'])): ?>
                                            <?php if (count($pkg['photos']) > 1): ?>
                                                <!-- Multiple photos: simple carousel -->
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
                                            <!-- Package name + checkbox + price -->
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="form-check flex-grow-1 me-2">
                                                    <input class="form-check-input package-checkbox"
                                                           type="checkbox"
                                                           name="packages[]"
                                                           value="<?php echo $pkg['id']; ?>"
                                                           id="pkg<?php echo $pkg['id']; ?>"
                                                           data-price="<?php echo htmlspecialchars($pkg['price'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                                <ul class="list-unstyled small mb-0 mt-auto">
                                                    <?php foreach (array_slice($pkg['features'], 0, 6) as $feat): ?>
                                                        <li class="mb-1">
                                                            <i class="fas fa-check-circle text-success me-1"></i>
                                                            <?php echo sanitize($feat); ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    <?php if (count($pkg['features']) > 6): ?>
                                                        <li class="text-muted">
                                                            <i class="fas fa-ellipsis-h me-1"></i>
                                                            +<?php echo count($pkg['features']) - 6; ?> more features
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <hr class="mb-5">
                <?php endif; ?>

                <?php if (empty($services)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No additional services available at this time.
                    </div>
                <?php else: ?>
                    <div class="mb-4" id="serviceSearchWrapper">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="serviceSearchInput" class="form-control"
                                   placeholder="Search services by name..."
                                   aria-label="Search services by name">
                            <button class="btn btn-outline-secondary" type="button" id="serviceSearchClear" style="display:none;" aria-label="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="serviceSearchNoResults" class="alert alert-info" style="display:none;">
                        <i class="fas fa-info-circle"></i> No services found matching your search.
                    </div>
                    <?php
                    $grouped_services = [];
                    foreach ($services as $service) {
                        // Use vendor_type_label from JOIN when available, fall back to legacy category string
                        $category = !empty($service['vendor_type_label']) ? $service['vendor_type_label'] : (!empty($service['category']) ? $service['category'] : 'Other');
                        $grouped_services[$category][] = $service;
                    }
                    ?>

                    <?php 
                    $category_index = 0;
                    foreach ($grouped_services as $category => $category_services): 
                        $category_id = 'category' . $category_index;
                        $is_first = ($category_index === 0);
                        $category_index++;
                    ?>
                        <div class="mb-3 service-category-section">
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center"
                                     style="cursor: pointer;"
                                     data-bs-toggle="collapse"
                                     data-bs-target="#<?php echo $category_id; ?>"
                                     aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>">
                                    <h5 class="mb-0">
                                        <i class="fas fa-tag me-2 text-success"></i>
                                        <?php echo sanitize($category); ?>
                                    </h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success rounded-pill"><?php echo count($category_services); ?> services</span>
                                        <i class="fas fa-chevron-down category-toggle-icon"></i>
                                    </div>
                                </div>
                                <div id="<?php echo $category_id; ?>" class="collapse <?php echo $is_first ? 'show' : ''; ?>">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <?php foreach ($category_services as $service): ?>
                                                <div class="<?php echo $service['has_designs'] ? 'col-12' : 'col-md-6'; ?>" data-service-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php if ($service['has_designs']): ?>
                                                        <!-- Service with designs: inline checkbox design grid -->
                                                        <div class="card service-designs-inline-card p-3">
                                                            <h5 class="mb-1"><?php echo sanitize($service['name']); ?></h5>
                                                            <?php if (!empty($service['description'])): ?>
                                                                <p class="text-muted small mb-3"><?php echo sanitize($service['description']); ?></p>
                                                            <?php else: ?>
                                                                <div class="mb-3"></div>
                                                            <?php endif; ?>
                                                            <div class="row g-2">
                                                                <?php foreach ($service['designs'] as $design): ?>
                                                                    <div class="col-6 col-md-3 col-xl-2 design-col-item">
                                                                        <label class="design-select-label d-block h-100" for="design_<?php echo $design['id']; ?>">
                                                                            <div class="card design-checkbox-card h-100 position-relative" id="design-card-<?php echo $design['id']; ?>">
                                                                                <div class="design-check-overlay position-absolute top-0 end-0 m-1">
                                                                                    <span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-check me-1"></i>Selected</span>
                                                                                </div>
                                                                                <?php if (!empty($design['photo'])): ?>
                                                                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($design['photo']); ?>"
                                                                                         alt="<?php echo htmlspecialchars($design['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                                         class="card-img-top design-card-img">
                                                                                <?php else: ?>
                                                                                    <div class="d-flex align-items-center justify-content-center bg-light design-card-img-placeholder">
                                                                                        <i class="fas fa-image fa-2x text-muted"></i>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <div class="card-body p-2 text-center">
                                                                                    <input type="radio" class="design-radio visually-hidden"
                                                                                           name="design_group_<?php echo $service['id']; ?>"
                                                                                           id="design_<?php echo $design['id']; ?>"
                                                                                           data-design-id="<?php echo $design['id']; ?>"
                                                                                           data-service-id="<?php echo $service['id']; ?>"
                                                                                           data-price="<?php echo htmlspecialchars($design['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                                           data-name="<?php echo htmlspecialchars($design['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                                           data-photo="<?php echo htmlspecialchars($design['photo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                                    <div class="fw-semibold small"><?php echo sanitize($design['name']); ?></div>
                                                                                    <div class="text-success small fw-bold"><?php echo formatCurrency($design['price']); ?></div>
                                                                                    <?php if (!empty($design['description'])): ?>
                                                                                        <div class="text-muted mt-1" style="font-size:0.7rem;"><?php echo sanitize($design['description']); ?></div>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Regular service: checkbox -->
                                                        <div class="service-card card">
                                                            <?php if (!empty($service['photo'])): ?>
                                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                                     alt="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                     class="card-img-top" style="height:140px;object-fit:cover;">
                                                            <?php endif; ?>
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
                                                                                       data-price="<?php echo $service['price']; ?>"
                                                                                       data-vendor-type-slug="<?php echo htmlspecialchars($service['vendor_type_slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                                                       data-service-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Hidden inputs for selected designs (populated by JS) -->
                <div id="selected-designs-inputs"></div>

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
        </div><!-- /#view-services -->

        <!-- =====================================================================
             VIEW 2: Design Selection – Direct designs for the selected service
             ===================================================================== -->
        <div id="view-sub-services" style="display:none;">
            <div class="d-flex align-items-center mb-1">
                <div class="flex-grow-1">
                    <h2 class="mb-0" id="sub-services-title">Select Design</h2>
                    <p class="text-muted mb-0 small" id="sub-services-subtitle"></p>
                </div>
            </div>
            <div class="alert alert-info py-2 small mb-3" id="sub-services-hint">
                <i class="fas fa-hand-pointer me-1"></i>
                Tap a photo to select your preferred design. Selection saves automatically.
            </div>
            <div id="sub-services-list" class="row g-3"></div>
            <div class="row mt-4">
                <div class="col-12">
                    <button class="btn btn-outline-secondary w-100" onclick="backToServices()">
                        <i class="fas fa-arrow-left me-1"></i> Back to Services
                    </button>
                </div>
            </div>
        </div><!-- /#view-sub-services -->

    </div>
</section>

<!-- Design checkbox card styles -->
<style>
.design-checkbox-card {
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    border: 2px solid #dee2e6;
}
.design-select-label:hover .design-checkbox-card,
.design-checkbox-card:hover {
    border-color: #198754;
    box-shadow: 0 0 0 3px rgba(25,135,84,.15);
}
.design-checkbox-card.selected-design {
    border-color: #198754 !important;
    border-width: 3px !important;
    box-shadow: 0 0 0 3px rgba(25,135,84,.2);
    background-color: rgba(25,135,84,.04);
}
.service-designs-inline-card {
    background-color: #f8f9fa;
}
.design-select-label {
    cursor: pointer;
    margin: 0;
}
.design-check-overlay {
    display: none;
    z-index: 2;
}
.design-col-item.design-col-hidden {
    display: none;
}
.design-card-img {
    height: 120px;
    object-fit: cover;
}
.design-card-img-placeholder {
    height: 120px;
}
.design-card-img-mob {
    height: 100px;
    object-fit: cover;
}
.design-card-img-placeholder-mob {
    height: 100px;
}
.category-toggle-icon {
    transition: transform 0.2s ease;
}
[aria-expanded="true"] .category-toggle-icon {
    transform: rotate(180deg);
}
</style>

<!-- JSON data for JS -->
<script>
const baseTotal          = <?php echo json_encode($totals['subtotal']); ?>;
const taxRate            = <?php echo json_encode($tax_rate); ?>;
const servicesData       = <?php echo json_encode(array_values($services_map)); ?>;
const uploadUrl          = <?php echo json_encode(rtrim(UPLOAD_URL, '/')); ?>;
const currency           = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
</script>
<?php
$extra_js = '<script src="' . BASE_URL . '/js/booking-step4.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
