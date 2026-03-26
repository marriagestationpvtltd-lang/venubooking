<?php
$page_title = 'Add New Booking';
// Require PHP utilities before any HTML output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$current_user = getCurrentUser();

$db = getDB();
$success_message = '';
$error_message = '';

// Fetch venues
$venues = $db->query("SELECT id, name FROM venues WHERE status = 'active' ORDER BY name")->fetchAll();

// Fetch halls
$halls = $db->query("SELECT h.id, h.name, v.name as venue_name, h.capacity FROM halls h INNER JOIN venues v ON h.venue_id = v.id WHERE h.status = 'active' ORDER BY v.name, h.name")->fetchAll();

// Fetch services with designs (same as frontend booking-step4.php)
$services = getActiveServices();
$services_map = [];
foreach ($services as &$svc) {
    $svc['designs'] = getServiceDesigns($svc['id']);
    $svc['has_designs'] = !empty($svc['designs']);
    $services_map[$svc['id']] = $svc;
}
unset($svc);

// Fetch service packages grouped by category (same as frontend booking-step4.php)
$packages_by_category = getServicePackagesByCategory();
$packages_by_category = array_filter($packages_by_category, function ($cat) {
    return !empty($cat['packages']);
});

// Fetch active payment methods
$payment_methods = getActivePaymentMethods();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $hall_id = intval($_POST['hall_id']);
    $event_date = $_POST['event_date'];
    $shift = $_POST['shift'];
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time']   ?? '';
    // Default times from shift if not provided
    if (empty($start_time) || empty($end_time)) {
        $shift_times = getShiftDefaultTimes($shift);
        if (empty($start_time)) $start_time = $shift_times['start'];
        if (empty($end_time))   $end_time   = $shift_times['end'];
    }
    $event_type = trim($_POST['event_type']);
    $number_of_guests = intval($_POST['number_of_guests']);
    $special_requests = trim($_POST['special_requests']);
    $selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
    $selected_services = isset($_POST['services']) ? $_POST['services'] : [];
    $selected_designs = [];
    if (isset($_POST['selected_designs']) && is_array($_POST['selected_designs'])) {
        foreach ($_POST['selected_designs'] as $k => $v) {
            $ki = intval($k);
            $vi = intval($v);
            if ($ki > 0 && $vi > 0) {
                $selected_designs[$ki] = $vi;
            }
        }
    }
    $selected_payment_methods = isset($_POST['payment_methods']) ? $_POST['payment_methods'] : [];
    $selected_packages = isset($_POST['packages']) ? array_map('intval', (array)$_POST['packages']) : [];
    $slot_price_override = (isset($_POST['slot_price_override']) && is_numeric($_POST['slot_price_override']) && $_POST['slot_price_override'] !== '') ? (float)$_POST['slot_price_override'] : null;
    $booking_status = $_POST['booking_status'];
    $payment_status = $_POST['payment_status'];
    $advance_payment_received = isset($_POST['advance_payment_received']) ? 1 : 0;

    // Validation
    if (empty($full_name) || empty($phone) || $hall_id <= 0 || empty($event_date) || $number_of_guests <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Check availability
        if (!checkHallAvailability($hall_id, $event_date, $shift, $start_time ?: null, $end_time ?: null)) {
            $error_message = 'This hall is not available for the selected date and shift.';
        } else {
            try {
                $db->beginTransaction();
                
                // Generate booking number
                $booking_number = generateBookingNumber();
                
                // Get or create customer
                $customer_id = getOrCreateCustomer($full_name, $phone, $email, $address);
                
                // Calculate totals
                $totals = calculateBookingTotal($hall_id, $selected_menus, $number_of_guests, $selected_services, $selected_designs, $selected_packages, $slot_price_override);
                
                // Insert booking
                $sql = "INSERT INTO bookings (
                            booking_number, customer_id, hall_id, event_date, start_time, end_time, shift,
                            event_type, number_of_guests, hall_price, menu_total, 
                            services_total, subtotal, tax_amount, grand_total, 
                            special_requests, booking_status, payment_status, advance_payment_received
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $booking_number,
                    $customer_id,
                    $hall_id,
                    $event_date,
                    $start_time ?: null,
                    $end_time   ?: null,
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
                    $payment_status,
                    $advance_payment_received
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
                        $stmt = $db->prepare("SELECT name, price, description, category FROM additional_services WHERE id = ?");
                        $stmt->execute([$service_id]);
                        $service = $stmt->fetch();
                        
                        if ($service) {
                            $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$booking_id, $service_id, $service['name'], $service['price'], $service['description'], $service['category']]);
                        }
                    }
                }

                // Insert selected service packages into booking_services
                if (!empty($selected_packages)) {
                    foreach ($selected_packages as $package_id) {
                        $package_id = intval($package_id);
                        if ($package_id <= 0) continue;
                        try {
                            $stmt = $db->prepare("SELECT name, price, description FROM service_packages WHERE id = ? AND status = 'active'");
                            $stmt->execute([$package_id]);
                            $package = $stmt->fetch();
                            if ($package) {
                                $insert = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $insert->execute([
                                    $booking_id,
                                    $package_id,
                                    $package['name'],
                                    $package['price'],
                                    $package['description'] ?? '',
                                    PACKAGE_SERVICE_CATEGORY,
                                    USER_SERVICE_TYPE,
                                    DEFAULT_SERVICE_QUANTITY,
                                ]);
                            }
                        } catch (\Throwable $pkgErr) {
                            error_log("Package insertion skipped for booking {$booking_id}, package_id={$package_id}: " . $pkgErr->getMessage());
                        }
                    }
                }

                // Insert booking service designs (services selected via design drilldown)
                if (!empty($selected_designs)) {
                    foreach ($selected_designs as $key_id => $design_id) {
                        $key_id    = intval($key_id);
                        $design_id = intval($design_id);
                        if ($design_id <= 0) continue;
                        try {
                            // Try direct-service design first
                            $stmt = $db->prepare(
                                "SELECT d.name, d.price, d.description, d.service_id, s.category
                                 FROM service_designs d
                                 JOIN additional_services s ON s.id = d.service_id
                                 WHERE d.id = ? AND d.service_id = ?"
                            );
                            $stmt->execute([$design_id, $key_id]);
                            $design = $stmt->fetch();
                            $sub_service_id_val = null;

                            if (!$design) {
                                // Fall back to legacy sub-service flow
                                $stmt = $db->prepare(
                                    "SELECT d.name, d.price, d.description, ss.service_id, s.category
                                     FROM service_designs d
                                     JOIN service_sub_services ss ON ss.id = d.sub_service_id
                                     JOIN additional_services s ON s.id = ss.service_id
                                     WHERE d.id = ? AND d.sub_service_id = ?"
                                );
                                $stmt->execute([$design_id, $key_id]);
                                $design = $stmt->fetch();
                                if ($design) {
                                    $sub_service_id_val = $key_id;
                                }
                            }

                            if ($design) {
                                $insert = $db->prepare(
                                    "INSERT INTO booking_services
                                         (booking_id, service_id, service_name, price, description, category,
                                          added_by, quantity, sub_service_id, design_id)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                                );
                                $insert->execute([
                                    $booking_id,
                                    $design['service_id'],
                                    $design['name'],
                                    $design['price'],
                                    $design['description'],
                                    $design['category'],
                                    USER_SERVICE_TYPE,
                                    DEFAULT_SERVICE_QUANTITY,
                                    $sub_service_id_val,
                                    $design_id
                                ]);
                            }
                        } catch (\Throwable $designErr) {
                            error_log("Admin booking design insertion skipped for booking {$booking_id}, design_id={$design_id}: " . $designErr->getMessage());
                        }
                    }
                }
                
                // Link payment methods to booking
                linkPaymentMethodsToBooking($booking_id, $selected_payment_methods);
                
                // Log activity
                logActivity($current_user['id'], 'Added new booking', 'bookings', $booking_id, "Added booking: $booking_number");
                
                $db->commit();
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Log the error for debugging
                error_log('Booking creation error: ' . $e->getMessage());
                $error_message = 'Error creating booking. Please try again or contact support.';
            }
            
            // Send notification and redirect only if booking was created successfully
            if (empty($error_message) && isset($booking_id)) {
                try {
                    sendBookingNotification($booking_id, 'new');
                } catch (Exception $e) {
                    error_log("Booking notification email failed for booking ID {$booking_id}: " . $e->getMessage());
                }
                // Store flash message so view.php can display confirmation
                $_SESSION['flash_success'] = "Booking {$booking_number} created successfully!";
                header('Location: view.php?id=' . $booking_id);
                exit;
            }
        }
    }
    } // end CSRF-valid else
}
require_once __DIR__ . '/../includes/header.php';
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
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
                                    <option value="morning" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'morning') ? 'selected' : ''; ?>>Morning (6:00 AM – 12:00 PM)</option>
                                    <option value="afternoon" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'afternoon') ? 'selected' : ''; ?>>Afternoon (12:00 PM – 6:00 PM)</option>
                                    <option value="evening" <?php echo (!isset($_POST['shift']) || $_POST['shift'] == 'evening') ? 'selected' : ''; ?>>Evening (6:00 PM – 11:00 PM)</option>
                                    <option value="fullday" <?php echo (isset($_POST['shift']) && $_POST['shift'] == 'fullday') ? 'selected' : ''; ?>>Full Day (6:00 AM – 11:00 PM)</option>
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

                    <!-- Time Slot Selection -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <div id="admin-selected-slot-info" class="alert alert-success py-2 d-none">
                                <i class="fas fa-check-circle me-1"></i>
                                <strong>Time Slot:</strong> <span id="admin-slot-name-display"></span>
                                &bull; <span id="admin-slot-time-display"></span>
                                <button type="button" class="btn-close float-end btn-sm" onclick="adminClearTimeSlot()" title="Clear selected slot"></button>
                            </div>
                            <input type="hidden" name="slot_id" id="admin-slot-id" value="<?php echo isset($_POST['slot_id']) ? intval($_POST['slot_id']) : ''; ?>">
                            <input type="hidden" name="slot_price_override" id="admin-slot-price-override" value="<?php echo isset($_POST['slot_price_override']) && is_numeric($_POST['slot_price_override']) ? htmlspecialchars($_POST['slot_price_override']) : ''; ?>">
                            <button type="button" id="admin-check-slots-btn" class="btn btn-outline-primary btn-sm" disabled onclick="adminOpenTimeSlotModal()">
                                <i class="fas fa-clock me-1"></i> View Available Time Slots
                            </button>
                            <small class="text-muted ms-2">Or set shift/start/end manually below.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label"><i class="fas fa-hourglass-start text-success me-1"></i>Start Time</label>
                                <?php
                                    // Use submitted value, or derive default from the selected shift.
                                    // Strip to "HH:MM" and sanitize before passing to generateTimeOptions.
                                    $default_shift  = isset($_POST['shift']) ? $_POST['shift'] : 'evening';
                                    $default_times  = getShiftDefaultTimes($default_shift);
                                    $raw_start      = isset($_POST['start_time']) ? $_POST['start_time'] : $default_times['start'];
                                    $raw_end        = isset($_POST['end_time'])   ? $_POST['end_time']   : $default_times['end'];
                                    $add_start_time = preg_match('/^\d{2}:\d{2}$/', substr($raw_start, 0, 5)) ? substr($raw_start, 0, 5) : $default_times['start'];
                                    $add_end_time   = preg_match('/^\d{2}:\d{2}$/', substr($raw_end, 0, 5))   ? substr($raw_end, 0, 5)   : $default_times['end'];
                                ?>
                                <select class="form-select" id="start_time" name="start_time">
                                    <?php echo generateTimeOptions($add_start_time); ?>
                                </select>
                                <small class="text-muted">Auto-filled from shift; adjust if needed.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label"><i class="fas fa-hourglass-end text-success me-1"></i>End Time</label>
                                <select class="form-select" id="end_time" name="end_time">
                                    <?php echo generateTimeOptions($add_end_time); ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($packages_by_category)): ?>
                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Service Packages (Optional)</h6>
                    <div class="row mb-3">
                        <div class="col-12">
                            <!-- Category filter buttons -->
                            <div class="d-flex flex-wrap gap-2 mb-3" id="admin-pkg-category-btns">
                                <?php $pkg_cat_idx = 0; foreach ($packages_by_category as $cat): ?>
                                    <?php if (empty($cat['packages'])) continue; ?>
                                    <button type="button"
                                            class="btn btn-sm admin-pkg-category-btn <?php echo $pkg_cat_idx === 0 ? 'btn-success' : 'btn-outline-secondary'; ?>"
                                            data-pkg-cat="admin-pkgcat<?php echo (int)$cat['id']; ?>">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($cat['name']); ?>
                                    </button>
                                <?php $pkg_cat_idx++; endforeach; ?>
                            </div>
                            <!-- Per-category package panels -->
                            <?php $pkg_cat_idx = 0; foreach ($packages_by_category as $cat): ?>
                                <?php if (empty($cat['packages'])) continue; ?>
                                <div class="admin-pkg-category-panel <?php echo $pkg_cat_idx > 0 ? 'd-none' : ''; ?>"
                                     id="admin-pkgcat<?php echo (int)$cat['id']; ?>">
                                    <div class="row g-3 mb-3">
                                        <?php foreach ($cat['packages'] as $pkg): ?>
                                            <div class="col-sm-6 col-lg-4">
                                                <div class="card h-100 border admin-pkg-card" style="cursor:pointer;"
                                                     onclick="document.getElementById('admin_pkg_<?php echo intval($pkg['id']); ?>').click()">
                                                    <?php if (!empty($pkg['photos'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($pkg['photos'][0]); ?>"
                                                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                                             class="card-img-top"
                                                             style="height:150px;object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center bg-light"
                                                             style="height:150px;">
                                                            <i class="fas fa-box fa-2x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body p-2">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <div class="form-check flex-grow-1 me-1">
                                                                <input class="form-check-input admin-package-checkbox"
                                                                       type="checkbox"
                                                                       name="packages[]"
                                                                       value="<?php echo $pkg['id']; ?>"
                                                                       id="admin_pkg_<?php echo $pkg['id']; ?>"
                                                                       data-price="<?php echo htmlspecialchars($pkg['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                       <?php echo (isset($_POST['packages']) && in_array($pkg['id'], $_POST['packages'])) ? 'checked' : ''; ?>
                                                                       onclick="event.stopPropagation()">
                                                                <label class="form-check-label fw-semibold small"
                                                                       for="admin_pkg_<?php echo $pkg['id']; ?>"
                                                                       onclick="event.stopPropagation()">
                                                                    <?php echo htmlspecialchars($pkg['name']); ?>
                                                                </label>
                                                            </div>
                                                            <span class="text-success fw-bold small text-nowrap">
                                                                <?php echo formatCurrency($pkg['price']); ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!empty($pkg['description'])): ?>
                                                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($pkg['description']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($pkg['features'])): ?>
                                                            <ul class="list-unstyled small mb-0">
                                                                <?php foreach (array_slice($pkg['features'], 0, 4) as $feat): ?>
                                                                    <li><i class="fas fa-check-circle text-success me-1"></i><?php echo htmlspecialchars($feat['feature_text']); ?></li>
                                                                <?php endforeach; ?>
                                                                <?php if (count($pkg['features']) > 4): ?>
                                                                    <li class="text-muted"><i class="fas fa-ellipsis-h me-1"></i>+<?php echo count($pkg['features']) - 4; ?> more</li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php $pkg_cat_idx++; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Menus & Services</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Menus (Optional)</label>
                                <div id="menus-container">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Please select a hall first to see available menus.
                                    </div>
                                </div>
                                <div id="menus-loading" class="d-none">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-success" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="ms-2">Loading menus...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Additional Services (Optional)</label>
                                <?php
                                // Group services by category
                                $services_by_category = [];
                                foreach ($services as $service) {
                                    $cat = !empty($service['category']) ? $service['category'] : 'Other';
                                    $services_by_category[$cat][] = $service;
                                }
                                $categories = array_keys($services_by_category);
                                ?>
                                <?php if (empty($services)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No additional services available.
                                    </div>
                                <?php else: ?>

                                <!-- ── View 1: Services list ── -->
                                <div id="admin-view-services">
                                    <!-- Category filter -->
                                    <select class="form-select form-select-sm mb-2" id="service-category-filter">
                                        <option value="">— All Categories —</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <!-- Services grouped by category as visual cards -->
                                    <div class="border rounded p-2" style="max-height:480px;overflow-y:auto;" id="admin-services-scroll">
                                        <?php foreach ($services_by_category as $cat => $cat_services): ?>
                                        <div class="svc-category-group mb-3" data-category="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>">
                                            <small class="text-muted fw-semibold d-block mb-2"><?php echo htmlspecialchars($cat); ?></small>
                                            <div class="row g-2">
                                            <?php foreach ($cat_services as $service): ?>
                                                <div class="col-6">
                                                <?php if ($service['has_designs']): ?>
                                                    <!-- Service with designs: drilldown card -->
                                                    <div class="card h-100 admin-svc-drilldown-card border"
                                                         data-service-id="<?php echo $service['id']; ?>"
                                                         onclick="adminOpenDesignsView(<?php echo intval($service['id']); ?>)"
                                                         style="cursor:pointer;">
                                                        <div id="admin-svc-photo-<?php echo $service['id']; ?>" style="display:none;">
                                                            <img src="" alt=""
                                                                 id="admin-svc-selected-img-<?php echo $service['id']; ?>"
                                                                 class="card-img-top" style="height:90px;object-fit:cover;">
                                                        </div>
                                                        <?php if (!empty($service['photo'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                             alt="<?php echo htmlspecialchars($service['name']); ?>"
                                                             class="card-img-top admin-svc-default-img-<?php echo $service['id']; ?>"
                                                             style="height:90px;object-fit:cover;">
                                                        <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center bg-light admin-svc-default-img-<?php echo $service['id']; ?>"
                                                             style="height:90px;">
                                                            <i class="fas fa-images fa-2x text-muted"></i>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="card-body p-2">
                                                            <div class="fw-medium small"><?php echo htmlspecialchars($service['name']); ?></div>
                                                            <div id="admin-svc-summary-<?php echo $service['id']; ?>" class="text-success small"></div>
                                                            <div class="text-end mt-1"><i class="fas fa-chevron-right text-muted small"></i></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Regular service: checkbox card -->
                                                    <div class="card h-100 border" style="cursor:pointer;"
                                                         onclick="document.getElementById('admin_service_<?php echo intval($service['id']); ?>').click()">
                                                        <?php if (!empty($service['photo'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                                             alt="<?php echo htmlspecialchars($service['name']); ?>"
                                                             class="card-img-top"
                                                             style="height:90px;object-fit:cover;">
                                                        <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center bg-light"
                                                             style="height:90px;">
                                                            <i class="fas fa-concierge-bell fa-2x text-muted"></i>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="card-body p-2">
                                                            <div class="form-check mb-0">
                                                                <input class="form-check-input admin-service-checkbox" type="checkbox"
                                                                       name="services[]" value="<?php echo $service['id']; ?>"
                                                                       id="admin_service_<?php echo $service['id']; ?>"
                                                                       data-price="<?php echo $service['price']; ?>"
                                                                       <?php echo (isset($_POST['services']) && in_array($service['id'], $_POST['services'])) ? 'checked' : ''; ?>
                                                                       onclick="event.stopPropagation()">
                                                                <label class="form-check-label small fw-medium" for="admin_service_<?php echo $service['id']; ?>" onclick="event.stopPropagation()">
                                                                    <?php echo htmlspecialchars($service['name']); ?>
                                                                </label>
                                                            </div>
                                                            <div class="text-success small fw-semibold"><?php echo formatCurrency($service['price']); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div><!-- /#admin-view-services -->

                                <!-- ── View 2: Design selection grid ── -->
                                <div id="admin-view-designs" style="display:none;">
                                    <div class="d-flex align-items-center mb-2 gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="adminBackToServices()">
                                            <i class="fas fa-arrow-left"></i>
                                        </button>
                                        <div>
                                            <div class="fw-semibold" id="admin-designs-title"></div>
                                            <div class="text-muted small" id="admin-designs-subtitle"></div>
                                        </div>
                                    </div>
                                    <div class="alert alert-info py-2 small mb-2">
                                        <i class="fas fa-hand-pointer me-1"></i> Click a photo to select your preferred design.
                                    </div>
                                    <div id="admin-designs-grid" class="row g-2" style="max-height:420px;overflow-y:auto;"></div>
                                </div><!-- /#admin-view-designs -->

                                <!-- Hidden inputs for selected designs (populated by JS) -->
                                <div id="admin-selected-designs-inputs"></div>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="special_requests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"><?php echo isset($_POST['special_requests']) ? htmlspecialchars($_POST['special_requests']) : ''; ?></textarea>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Payment Methods</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Select Payment Methods (Optional)</label>
                                <small class="text-muted d-block mb-2">Choose which payment methods to offer for this booking</small>
                                <?php if (empty($payment_methods)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No payment methods configured. 
                                        <a href="<?php echo BASE_URL; ?>/admin/payment-methods/index.php">Add payment methods</a> to use this feature.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($payment_methods as $method): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="payment_methods[]" 
                                               value="<?php echo $method['id']; ?>" 
                                               id="payment_method_<?php echo $method['id']; ?>">
                                        <label class="form-check-label" for="payment_method_<?php echo $method['id']; ?>">
                                            <?php echo htmlspecialchars($method['name']); ?>
                                            <?php if (!empty($method['bank_details'])): ?>
                                                <small class="text-muted">(<?php echo substr(htmlspecialchars($method['bank_details']), 0, 50); ?>...)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                    <option value="completed" <?php echo (isset($_POST['booking_status']) && $_POST['booking_status'] == 'completed') ? 'selected' : ''; ?>>Order Complete</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="pending" <?php echo (!isset($_POST['payment_status']) || $_POST['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <small class="text-muted">Flow: Pending → Partial → Paid</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="advance_payment_received" name="advance_payment_received" value="1" <?php echo isset($_POST['advance_payment_received']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="advance_payment_received">
                                        <strong>Advance Payment Received</strong>
                                        <small class="text-muted d-block">Check this box if the customer has paid the advance payment</small>
                                    </label>
                                </div>
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

<!-- JSON data for admin service/design selection (mirrors booking-step4.php) -->
<script>
const adminServicesData = <?php echo json_encode(array_values($services_map)); ?>;
const adminUploadUrl    = <?php echo json_encode(rtrim(UPLOAD_URL, '/')); ?>;
const adminCurrency     = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
</script>

<script>
// Dynamic menu loading based on hall selection
document.addEventListener('DOMContentLoaded', function() {
    const hallSelect = document.getElementById('hall_id');
    const menusContainer = document.getElementById('menus-container');
    const menusLoading = document.getElementById('menus-loading');
    
    if (hallSelect) {
        // Load menus when hall is selected
        hallSelect.addEventListener('change', function() {
            const hallId = this.value;
            
            if (!hallId) {
                menusContainer.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Please select a hall first to see available menus.</div>';
                return;
            }
            
            // Show loading
            menusContainer.classList.add('d-none');
            menusLoading.classList.remove('d-none');
            
            // Fetch menus for selected hall
            fetch('<?php echo BASE_URL; ?>/api/get-hall-menus.php?hall_id=' + hallId)
                .then(response => response.json())
                .then(data => {
                    menusLoading.classList.add('d-none');
                    menusContainer.classList.remove('d-none');
                    
                    if (data.success && data.menus && data.menus.length > 0) {
                        let html = '';
                        data.menus.forEach(menu => {
                            html += `
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="menus[]" 
                                           value="${menu.id}" id="menu_${menu.id}">
                                    <label class="form-check-label" for="menu_${menu.id}">
                                        ${escapeHtml(menu.name)} - ${escapeHtml(menu.price_formatted)}/person
                                    </label>
                                </div>
                            `;
                        });
                        menusContainer.innerHTML = html;
                    } else {
                        menusContainer.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No menus are assigned to this hall. Please assign menus to the hall first.</div>';
                    }
                })
                .catch(error => {
                    menusLoading.classList.add('d-none');
                    menusContainer.classList.remove('d-none');
                    menusContainer.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading menus. Please try again.</div>';
                    console.error('Error fetching menus:', error);
                });
        });
        
        // Trigger change event if hall is already selected (on page reload with error)
        if (hallSelect.value) {
            hallSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Service category filter ──────────────────────────────────────────────
    const serviceCategoryFilter = document.getElementById('service-category-filter');
    if (serviceCategoryFilter) {
        serviceCategoryFilter.addEventListener('change', function() {
            const selected = this.value;
            document.querySelectorAll('.svc-category-group').forEach(function(group) {
                if (!selected || group.dataset.category === selected) {
                    group.style.display = '';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    }

    // ── Admin service/design selection (mirrors booking-step4.js) ────────────
    const adminSelectedDesigns = {};  // { service_id: { design_id, price, name, photo, service_id } }
    let adminCurrentServiceId = null;

    // Build lookup maps from PHP-injected JSON
    const adminServicesById = {};
    const adminDesignsById  = {};
    if (typeof adminServicesData !== 'undefined') {
        adminServicesData.forEach(function(svc) {
            adminServicesById[svc.id] = svc;
            if (svc.designs) {
                svc.designs.forEach(function(d) {
                    adminDesignsById[d.id] = d;
                    d.service_id = svc.id;
                });
            }
        });
    }

    // Currency formatter
    function adminFormatPrice(amount) {
        const num = parseFloat(amount) || 0;
        const cur = (typeof adminCurrency !== 'undefined') ? adminCurrency : 'NPR';
        return cur + ' ' + num.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Update hidden inputs so form submits selected designs
    function adminSyncDesignInputs() {
        const container = document.getElementById('admin-selected-designs-inputs');
        if (!container) return;
        container.innerHTML = '';
        Object.values(adminSelectedDesigns).forEach(function(d) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'selected_designs[' + d.service_id + ']';
            input.value = d.design_id;
            container.appendChild(input);
        });
    }

    // Update drilldown service card summary + selected photo
    function adminUpdateServiceSummary(serviceId) {
        const sel = adminSelectedDesigns[serviceId];
        const text = sel ? (sel.name + ' (' + adminFormatPrice(sel.price) + ')') : '';

        const summaryEl = document.getElementById('admin-svc-summary-' + serviceId);
        if (summaryEl) summaryEl.textContent = text;

        const photoContainer = document.getElementById('admin-svc-photo-'        + serviceId);
        const photoImg       = document.getElementById('admin-svc-selected-img-' + serviceId);

        // Default placeholder images (service photo or icon div)
        const defaultImgs = document.querySelectorAll('.admin-svc-default-img-' + serviceId);

        if (sel && sel.photo) {
            if (photoImg) {
                photoImg.src = adminUploadUrl + '/' + sel.photo;
                photoImg.alt = sel.name;
            }
            if (photoContainer) photoContainer.style.display = '';
            defaultImgs.forEach(function(el) { el.style.display = 'none'; });
        } else {
            if (photoContainer) photoContainer.style.display = 'none';
            defaultImgs.forEach(function(el) { el.style.display = ''; });
        }

        // Highlight card if a design is selected
        const card = document.querySelector('.admin-svc-drilldown-card[data-service-id="' + serviceId + '"]');
        if (card) {
            card.classList.toggle('border-success', !!sel);
        }
    }

    // Build design photo grid HTML for a service
    function adminBuildDesignGrid(svc) {
        if (!svc.designs || svc.designs.length === 0) {
            return '<div class="col-12"><div class="alert alert-info small py-2 mb-0">'
                + '<i class="fas fa-info-circle me-1"></i>No designs available.</div></div>';
        }
        const sel = adminSelectedDesigns[svc.id];
        let html = '';
        svc.designs.forEach(function(d) {
            const isChosen = sel && sel.design_id === d.id;
            const photoHtml = d.photo
                ? '<img src="' + escapeHtml(adminUploadUrl + '/' + d.photo) + '" '
                    + 'alt="' + escapeHtml(d.name) + '" '
                    + 'class="card-img-top" style="height:120px;object-fit:cover;">'
                : '<div class="d-flex align-items-center justify-content-center bg-light" style="height:120px;">'
                    + '<i class="fas fa-image fa-2x text-muted"></i></div>';

            html += '<div class="col-6 col-md-4">';
            html += '<div class="card h-100 ' + (isChosen ? 'border-success border-3' : 'border') + '"'
                  + ' style="cursor:pointer;" onclick="adminSelectDesign(' + d.id + ')">';
            html += photoHtml;
            html += '<div class="card-body p-2 text-center">';
            if (isChosen) html += '<i class="fas fa-check-circle text-success me-1"></i>';
            html += '<div class="fw-semibold small">' + escapeHtml(d.name) + '</div>';
            html += '<div class="text-success small fw-bold">' + escapeHtml(adminFormatPrice(d.price)) + '</div>';
            if (d.description) {
                html += '<div class="text-muted small mt-1">' + escapeHtml(d.description) + '</div>';
            }
            html += '</div></div></div>';
        });
        return html;
    }

    // Open design view for a service
    window.adminOpenDesignsView = function(serviceId) {
        adminCurrentServiceId = serviceId;
        const svc = adminServicesById[serviceId];
        if (!svc) return;

        const titleEl    = document.getElementById('admin-designs-title');
        const subtitleEl = document.getElementById('admin-designs-subtitle');
        const gridEl     = document.getElementById('admin-designs-grid');
        if (titleEl)    titleEl.textContent    = svc.name;
        if (subtitleEl) subtitleEl.textContent = svc.description || '';
        if (gridEl)     gridEl.innerHTML = adminBuildDesignGrid(svc);

        document.getElementById('admin-view-services').style.display = 'none';
        document.getElementById('admin-view-designs').style.display  = '';
    };

    // Go back to services view
    window.adminBackToServices = function() {
        if (adminCurrentServiceId !== null) {
            adminUpdateServiceSummary(adminCurrentServiceId);
        }
        document.getElementById('admin-view-designs').style.display  = 'none';
        document.getElementById('admin-view-services').style.display = '';
    };

    // Select a design and auto-return to services view
    window.adminSelectDesign = function(designId) {
        const d = adminDesignsById[designId];
        if (!d) return;
        adminSelectedDesigns[d.service_id] = {
            design_id  : d.id,
            price      : parseFloat(d.price) || 0,
            name       : d.name,
            service_id : d.service_id,
            photo      : d.photo || ''
        };
        adminSyncDesignInputs();
        adminBackToServices();
    };
});
</script>

<script>
// Shift → Time auto-fill for admin Add Booking form
(function() {
    var shiftTimes = {
        'morning':   { start: '06:00', end: '12:00' },
        'afternoon': { start: '12:00', end: '18:00' },
        'evening':   { start: '18:00', end: '23:00' },
        'fullday':   { start: '06:00', end: '23:00' }
    };
    var shiftSel   = document.getElementById('shift');
    var startInput = document.getElementById('start_time');
    var endInput   = document.getElementById('end_time');
    if (shiftSel && startInput && endInput) {
        shiftSel.addEventListener('change', function() {
            var times = shiftTimes[this.value];
            if (times) {
                startInput.value = times.start;
                endInput.value   = times.end;
            }
        });
        // Trigger once on load to set correct defaults for the pre-selected shift
        shiftSel.dispatchEvent(new Event('change'));
    }
}());
</script>

<script>
// Time slot modal and package category filter for admin Add Booking
(function () {
    var adminBaseUrl = '<?php echo BASE_URL; ?>';
    var adminCur = (typeof adminCurrency !== 'undefined') ? adminCurrency : 'NPR';

    function fmtPrice(amount) {
        var num = parseFloat(amount) || 0;
        return adminCur + ' ' + num.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Enable "View Time Slots" button when hall + date are both set ─────────
    function updateSlotsBtn() {
        var hallId = document.getElementById('hall_id') ? document.getElementById('hall_id').value : '';
        var eventDate = document.getElementById('event_date') ? document.getElementById('event_date').value : '';
        var btn = document.getElementById('admin-check-slots-btn');
        if (btn) btn.disabled = !(hallId && eventDate);
    }

    var hallSel = document.getElementById('hall_id');
    var dateSel = document.getElementById('event_date');
    if (hallSel) hallSel.addEventListener('change', updateSlotsBtn);
    if (dateSel) dateSel.addEventListener('change', updateSlotsBtn);
    updateSlotsBtn();

    // ── Open time slot modal ─────────────────────────────────────────────────
    window.adminOpenTimeSlotModal = function () {
        var hallId = document.getElementById('hall_id') ? document.getElementById('hall_id').value : '';
        var eventDate = document.getElementById('event_date') ? document.getElementById('event_date').value : '';
        if (!hallId || !eventDate) return;

        var content = document.getElementById('admin-time-slots-content');
        if (content) {
            content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div><div class="mt-2">Loading time slots...</div></div>';
        }

        var modalEl = document.getElementById('adminTimeSlotModal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        fetch(adminBaseUrl + '/api/get-time-slots.php?hall_id=' + encodeURIComponent(hallId) + '&date=' + encodeURIComponent(eventDate))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!content) return;
                if (!data.success || !data.slots || data.slots.length === 0) {
                    content.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>No time slots are configured for this hall. Set the shift and times manually below.</div>';
                    return;
                }
                var html = '<div class="row g-3">';
                // Pending slot selections stored as data attributes to avoid onclick injection
                var slotDataMap = {};
                data.slots.forEach(function (slot) {
                    slotDataMap[slot.id] = slot;
                    var avail = slot.available;
                    var cardClass = avail ? 'border-success' : 'bg-light text-muted';
                    var cursor = avail ? 'cursor:pointer;' : 'cursor:not-allowed;opacity:.6;';
                    var dataAttrs = avail
                        ? ' data-slot-id="' + slot.id + '"'
                        : '';
                    html += '<div class="col-md-4 col-sm-6">';
                    html += '<div class="card h-100 admin-slot-card ' + cardClass + '" style="' + cursor + '"' + dataAttrs + '>';
                    html += '<div class="card-body text-center py-3">';
                    html += '<div class="fw-bold mb-1">' + escHtml(slot.slot_name) + '</div>';
                    html += '<div class="small">' + escHtml(slot.start_time_display) + ' &ndash; ' + escHtml(slot.end_time_display) + '</div>';
                    if (slot.price_override !== null) {
                        html += '<div class="text-success fw-bold mt-1">' + fmtPrice(slot.price_override) + '</div>';
                    }
                    html += '<div class="mt-2">';
                    html += avail
                        ? '<span class="badge bg-success">Available</span>'
                        : '<span class="badge bg-secondary">Booked</span>';
                    html += '</div>';
                    html += '</div></div></div>';
                });
                html += '</div>';
                content.innerHTML = html;

                // Attach click handlers using stored data (no inline onclick)
                content.querySelectorAll('.admin-slot-card[data-slot-id]').forEach(function (card) {
                    card.addEventListener('click', function () {
                        var sid = parseInt(this.dataset.slotId, 10);
                        var s = slotDataMap[sid];
                        if (s) adminSelectTimeSlot(s.id, s.slot_name, s.start_time, s.end_time, s.price_override);
                    });
                });
            })
            .catch(function () {
                if (content) content.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Error loading time slots. Please try again.</div>';
            });
    };

    // ── Select a time slot: auto-fill shift/start/end and store slot_id ──────
    window.adminSelectTimeSlot = function (slotId, slotName, startTime, endTime, priceOverride) {
        // Derive shift from times — mirrors PHP deriveShiftFromTimes()
        var sTotal = parseInt(startTime.substring(0, 2), 10) * 60 + parseInt(startTime.substring(3, 5), 10);
        var eTotal = parseInt(endTime.substring(0, 2), 10) * 60 + parseInt(endTime.substring(3, 5), 10);
        var shift;
        if (eTotal <= 12 * 60) {
            shift = 'morning';
        } else if (sTotal >= 12 * 60 && eTotal <= 18 * 60) {
            shift = 'afternoon';
        } else if (sTotal >= 18 * 60) {
            shift = 'evening';
        } else {
            shift = 'fullday';
        }

        var shiftSel = document.getElementById('shift');
        if (shiftSel) shiftSel.value = shift;

        var startHHMM = startTime.substring(0, 5);
        var endHHMM   = endTime.substring(0, 5);
        var startSel  = document.getElementById('start_time');
        var endSel    = document.getElementById('end_time');
        if (startSel) {
            for (var i = 0; i < startSel.options.length; i++) {
                if (startSel.options[i].value === startHHMM) { startSel.selectedIndex = i; break; }
            }
        }
        if (endSel) {
            for (var j = 0; j < endSel.options.length; j++) {
                if (endSel.options[j].value === endHHMM) { endSel.selectedIndex = j; break; }
            }
        }

        // Store slot ID and price override
        var slotInput = document.getElementById('admin-slot-id');
        if (slotInput) slotInput.value = slotId;
        var priceInput = document.getElementById('admin-slot-price-override');
        if (priceInput) priceInput.value = (priceOverride !== null && priceOverride !== undefined) ? priceOverride : '';

        // Show selected slot info banner
        var infoDiv = document.getElementById('admin-selected-slot-info');
        var nameSpan = document.getElementById('admin-slot-name-display');
        var timeSpan = document.getElementById('admin-slot-time-display');
        if (infoDiv) infoDiv.classList.remove('d-none');
        if (nameSpan) nameSpan.textContent = slotName;
        if (timeSpan) timeSpan.textContent = startTime + ' – ' + endTime;

        // Close the modal
        var modalEl = document.getElementById('adminTimeSlotModal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    };

    // ── Clear selected time slot ─────────────────────────────────────────────
    window.adminClearTimeSlot = function () {
        var slotInput = document.getElementById('admin-slot-id');
        if (slotInput) slotInput.value = '';
        var priceInput = document.getElementById('admin-slot-price-override');
        if (priceInput) priceInput.value = '';
        var infoDiv = document.getElementById('admin-selected-slot-info');
        if (infoDiv) infoDiv.classList.add('d-none');
    };

    // ── Package category filter ──────────────────────────────────────────────
    document.querySelectorAll('.admin-pkg-category-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.dataset.pkgCat;
            document.querySelectorAll('.admin-pkg-category-btn').forEach(function (b) {
                b.classList.remove('btn-success');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');
            document.querySelectorAll('.admin-pkg-category-panel').forEach(function (panel) {
                panel.classList.toggle('d-none', panel.id !== targetId);
            });
        });
    });

    // ── Package card visual toggle (highlight on checkbox check) ────────────
    document.querySelectorAll('.admin-package-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var card = this.closest('.admin-pkg-card');
            if (card) card.classList.toggle('border-success', this.checked);
        });
        // Reflect initial checked state (on page reload with form error)
        var card = cb.closest('.admin-pkg-card');
        if (card && cb.checked) card.classList.add('border-success');
    });
}());
</script>

<!-- Time Slot Selection Modal -->
<div class="modal fade" id="adminTimeSlotModal" tabindex="-1" aria-labelledby="adminTimeSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminTimeSlotModalLabel">
                    <i class="fas fa-clock me-2 text-success"></i>Available Time Slots
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="admin-time-slots-content">
                    <div class="text-center py-4 text-muted">Select a hall and event date to view available time slots.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . BASE_URL . '/admin/js/admin-booking-calendar.js"></script>';
require_once __DIR__ . '/../includes/footer.php';
?>
