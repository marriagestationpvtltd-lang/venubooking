<?php
$page_title = 'Additional Services';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
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

// Get all active services, enriched with sub-services and designs
$services = getActiveServices();
$services_map = []; // keyed by service id
foreach ($services as &$svc) {
    $svc['sub_services'] = getServiceSubServicesWithDesigns($svc['id']);
    $svc['has_sub_services'] = !empty($svc['sub_services']);
    $services_map[$svc['id']] = $svc;
}
unset($svc);

// Calculate current totals (no services/designs selected yet in initial load)
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
                        $category = $service['category'] ?: 'Other';
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
                        <div class="mb-4 service-category-section">
                            <!-- Desktop: Always show category -->
                            <div class="d-none d-md-block">
                                <h4 class="mb-3"><?php echo sanitize($category); ?></h4>
                                <div class="row g-3">
                                    <?php foreach ($category_services as $service): ?>
                                        <div class="col-md-6" data-service-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php if ($service['has_sub_services']): ?>
                                                <!-- Service with sub-services: drill-down card -->
                                                <div class="service-card card service-drilldown-card" style="cursor:pointer;"
                                                     data-service-id="<?php echo $service['id']; ?>"
                                                     onclick="openSubServicesView(<?php echo $service['id']; ?>)">
                                                    <?php if (!empty($service['photo'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                             alt="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                             class="card-img-top" style="height:140px;object-fit:cover;">
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h5 class="card-title mb-1"><?php echo sanitize($service['name']); ?></h5>
                                                                <p class="card-text text-muted small mb-1"><?php echo sanitize($service['description']); ?></p>
                                                                <div id="service-summary-<?php echo $service['id']; ?>" class="service-design-summary text-success small"></div>
                                                            </div>
                                                            <div class="ms-3 text-end">
                                                                <i class="fas fa-chevron-right text-muted fa-lg mt-1"></i>
                                                            </div>
                                                        </div>
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
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Mobile: Collapsible category (first one expanded by default) -->
                            <div class="d-md-none">
                                <div class="card mb-3">
                                    <div class="card-header bg-light" style="cursor: pointer;" 
                                         data-bs-toggle="collapse" 
                                         data-bs-target="#<?php echo $category_id; ?>" 
                                         aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo sanitize($category); ?></h5>
                                            <span class="badge bg-success"><?php echo count($category_services); ?> services</span>
                                        </div>
                                    </div>
                                    <div id="<?php echo $category_id; ?>" class="collapse <?php echo $is_first ? 'show' : ''; ?>">
                                        <div class="card-body p-2">
                                            <?php foreach ($category_services as $service): ?>
                                                <?php if ($service['has_sub_services']): ?>
                                                    <div class="service-card card mb-2" data-service-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php if (!empty($service['photo'])): ?>
                                                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                                 alt="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                 class="card-img-top" style="height:100px;object-fit:cover;">
                                                        <?php endif; ?>
                                                        <div class="card-body p-3 service-drilldown-card d-flex justify-content-between align-items-center"
                                                             style="cursor:pointer;"
                                                             onclick="openSubServicesView(<?php echo $service['id']; ?>)">
                                                            <div>
                                                                <strong><?php echo sanitize($service['name']); ?></strong>
                                                                <div id="service-summary-mob-<?php echo $service['id']; ?>" class="service-design-summary-mob text-success small"></div>
                                                            </div>
                                                            <i class="fas fa-chevron-right text-muted"></i>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="service-card card mb-2" data-service-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php if (!empty($service['photo'])): ?>
                                                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                                 alt="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                 class="card-img-top" style="height:100px;object-fit:cover;">
                                                        <?php endif; ?>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <div class="form-check flex-grow-1">
                                                                    <input class="form-check-input service-checkbox" 
                                                                           type="checkbox" 
                                                                           name="services[]" 
                                                                           value="<?php echo $service['id']; ?>" 
                                                                           id="service<?php echo $service['id']; ?>"
                                                                           data-price="<?php echo $service['price']; ?>">
                                                                    <label class="form-check-label" for="service<?php echo $service['id']; ?>">
                                                                        <strong><?php echo sanitize($service['name']); ?></strong>
                                                                    </label>
                                                                </div>
                                                                <span class="text-success fw-bold"><?php echo formatCurrency($service['price']); ?></span>
                                                            </div>
                                                            <p class="card-text text-muted small mb-0"><?php echo sanitize($service['description']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
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
             VIEW 2: Design Selection – Sub-Services with their Design Photos
             ===================================================================== -->
        <div id="view-sub-services" style="display:none;">
            <div class="d-flex align-items-center mb-1">
                <div class="flex-grow-1">
                    <h2 class="mb-0" id="sub-services-title">Select Sub-Service</h2>
                    <p class="text-muted mb-0 small" id="sub-services-subtitle"></p>
                </div>
                <span id="sub-service-progress" class="badge bg-secondary ms-3 fs-6" style="display:none;"></span>
            </div>
            <div class="alert alert-info py-2 small mb-3" id="sub-services-hint">
                <i class="fas fa-hand-pointer me-1"></i>
                Tap a photo to select your preferred design for each option. Tap <strong>Done</strong> when finished.
            </div>
            <div id="sub-services-list" class="row g-3"></div>
            <div class="row mt-4">
                <div class="col-6">
                    <button class="btn btn-outline-secondary w-100" onclick="backToServices()">
                        <i class="fas fa-arrow-left me-1"></i> Back to Services
                    </button>
                </div>
                <div class="col-6">
                    <button class="btn btn-success w-100" id="sub-services-done-btn" onclick="backToServices()" style="display:none;">
                        <i class="fas fa-check me-1"></i> Done
                    </button>
                </div>
            </div>
        </div><!-- /#view-sub-services -->

    </div>
</section>

<!-- JSON data for JS -->
<script>
const baseTotal    = <?php echo json_encode($current_total); ?>;
const servicesData = <?php echo json_encode(array_values($services_map)); ?>;
const uploadUrl    = <?php echo json_encode(rtrim(UPLOAD_URL, '/')); ?>;
const currency     = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
</script>
<?php
$extra_js = '<script src="' . BASE_URL . '/js/booking-step4.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
