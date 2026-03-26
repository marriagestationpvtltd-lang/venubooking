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

// Save selected packages when arriving from step 4 (booking-step4.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_packages = $_POST['packages'] ?? [];
    $clean_packages = [];
    foreach ($raw_packages as $pkg_id) {
        $pkg_id_int = intval($pkg_id);
        if ($pkg_id_int > 0) {
            $clean_packages[] = $pkg_id_int;
        }
    }
    $_SESSION['selected_packages'] = $clean_packages;
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

$booking_data      = $_SESSION['booking_data'];
$selected_hall     = $_SESSION['selected_hall'];
$selected_menus    = $_SESSION['selected_menus'] ?? [];
$selected_packages = $_SESSION['selected_packages'] ?? [];

// Get all active services, enriched with direct designs
$services = getActiveServices();
$services_map = []; // keyed by service id
foreach ($services as &$svc) {
    $svc['designs']     = getServiceDesigns($svc['id']);
    $svc['has_designs'] = !empty($svc['designs']);
    $services_map[$svc['id']] = $svc;
}
unset($svc);

// Pre-load vendors grouped by vendor_type_slug so the vendor selection modal can
// display the right vendors for each service without additional AJAX calls.
$vendors_by_type = []; // slug → [vendor, ...]
try {
    $all_vendors = getVendors();
    foreach ($all_vendors as $v) {
        $slug = $v['type'] ?? '';
        if ($slug !== '') {
            $vendors_by_type[$slug][] = $v;
        }
    }
} catch (\Throwable $e) {
    error_log('booking-step5: failed to load vendors: ' . $e->getMessage());
    $vendors_by_type = [];
}

// Calculate current totals including already-selected packages
$totals        = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], [], [], $selected_packages);
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
                    <div class="step completed">
                        <span class="step-number">4</span>
                        <span class="step-label">Packages</span>
                    </div>
                    <div class="step active">
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

        <!-- Breadcrumb navigation - shown when drilling into designs -->
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

            <form id="servicesForm" method="POST" action="booking-step6.php">

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

                <!-- Hidden inputs for vendor selections per service (populated by JS) -->
                <div id="selected-vendors-inputs"></div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <a href="booking-step4.php" class="btn btn-outline-secondary btn-lg w-100">
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
             VIEW 2: Design Selection - Direct designs for the selected service
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
.vendor-select-card {
    transition: border-color .2s, box-shadow .2s;
    border: 2px solid #dee2e6;
}
.vendor-select-card:hover {
    border-color: #198754;
    box-shadow: 0 0 0 3px rgba(25,135,84,.15);
}
.vendor-select-card.selected-vendor {
    border-color: #198754 !important;
    box-shadow: 0 0 0 3px rgba(25,135,84,.2);
    background-color: rgba(25,135,84,.04);
}
</style>

<!-- JSON data for JS -->
<script>
const baseTotal          = <?php echo json_encode($totals['subtotal']); ?>;
const taxRate            = <?php echo json_encode($tax_rate); ?>;
const servicesData       = <?php echo json_encode(array_values($services_map)); ?>;
const uploadUrl          = <?php echo json_encode(rtrim(UPLOAD_URL, '/')); ?>;
const currency           = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
const vendorsByType      = <?php echo json_encode($vendors_by_type); ?>;
</script>

<!-- Vendor Selection Modal -->
<div class="modal fade" id="vendorSelectModal" tabindex="-1" aria-labelledby="vendorSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="vendorSelectModalLabel">
                    <i class="fas fa-user-tie me-2"></i>Select Vendor for <span id="vendorModalServiceName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle me-1 text-info"></i>
                    Select a vendor for this service. You can also skip and let our team assign one for you.
                </p>
                <div id="vendorModalList" class="row g-3">
                    <!-- Populated by JS -->
                </div>
                <div id="vendorModalEmpty" class="alert alert-info" style="display:none;">
                    <i class="fas fa-info-circle me-1"></i>No vendors available for this service type. Our team will coordinate with you.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="vendorSkipBtn" data-bs-dismiss="modal">
                    <i class="fas fa-forward me-1"></i>Skip – assign later
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . BASE_URL . '/js/booking-step5.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
