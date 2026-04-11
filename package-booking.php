<?php
/**
 * package-booking.php
 *
 * Simplified booking flow for a specific service package.
 * Skips the normal venue / menu / additional-services selection steps because
 * those are already encoded in the package itself.
 *
 * Flow:
 *   1.  User clicks "Book Now" on a package card → lands here (?id=<package_id>)
 *   2.  If the package has multiple halls the user picks one; otherwise it is
 *       resolved automatically (or treated as "custom / no hall" when none are set).
 *   3.  User fills in basic event details + personal information.
 *   4.  On POST the session is populated and the user is sent to booking-step6.php
 *       for the final confirmation / payment step.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ------------------------------------------------------------------
// Resolve the package
// ------------------------------------------------------------------
$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($package_id <= 0) {
    header('Location: packages.php');
    exit;
}

try {
    $db  = getDB();
    $stmt = $db->prepare(
        "SELECT sp.*, sc.name AS category_name
         FROM service_packages sp
         LEFT JOIN service_categories sc ON sc.id = sp.category_id
         WHERE sp.id = ? AND sp.status = 'active'"
    );
    $stmt->execute([$package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('package-booking.php: failed to load package — ' . $e->getMessage());
    $package = null;
}

if (!$package) {
    header('Location: packages.php');
    exit;
}

// Package features
$features = getPackageFeatures($package_id);

// Package services: features that link to an additional_service (with vendor type info)
// service_id → { feature_text, service_name, service_photo, vendor_type_slug }
$pkg_services = [];
try {
    $psStmt = $db->prepare(
        "SELECT spf.service_id, spf.feature_text,
                s.name  AS service_name,
                s.photo AS service_photo,
                vt.slug AS vendor_type_slug
           FROM service_package_features spf
           LEFT JOIN additional_services s  ON s.id  = spf.service_id
           LEFT JOIN vendor_types        vt ON vt.id = s.vendor_type_id
          WHERE spf.package_id = ?
            AND spf.service_id IS NOT NULL
          ORDER BY spf.display_order, spf.id"
    );
    $psStmt->execute([$package_id]);
    foreach ($psStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pkg_services[(int)$row['service_id']] = $row;
    }
} catch (\Throwable $e) {
    // service_id column may not exist on older installs; fall back gracefully
    error_log('package-booking: failed to load package services: ' . $e->getMessage());
    $pkg_services = [];
}

// Vendors grouped by type slug (for vendor selection modal)
$vendors_by_type = [];
try {
    $all_vendors = getVendors();
    foreach ($all_vendors as $v) {
        $slug = $v['type'] ?? '';
        if ($slug !== '') {
            $vendors_by_type[$slug][] = $v;
        }
    }
} catch (\Throwable $e) {
    error_log('package-booking: failed to load vendors: ' . $e->getMessage());
    $vendors_by_type = [];
}

// Package photos
$photos = [];
try {
    $phStmt = $db->prepare(
        "SELECT image_path FROM service_package_photos
         WHERE package_id = ? ORDER BY display_order, id"
    );
    $phStmt->execute([$package_id]);
    $photos = $phStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) { /* ignore */ }

// Linked halls (with venue info) and menu IDs
$package_halls   = getPackageHalls($package_id);
$package_menu_ids = getPackageMenuIds($package_id);

// ------------------------------------------------------------------
// Handle form submission
// ------------------------------------------------------------------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_package_booking'])) {
    $event_date  = trim($_POST['event_date']  ?? '');
    $guests      = intval($_POST['guests']     ?? 0);
    $category_name = trim($package['category_name'] ?? '');
    $pkg_name      = trim($package['name']          ?? '');
    if (!empty($category_name) && !empty($pkg_name)) {
        $event_type = $category_name . ' — ' . $pkg_name;
    } else {
        $event_type = !empty($category_name) ? $category_name : $pkg_name;
    }
    $hall_id_sel = intval($_POST['hall_id']    ?? 0);

    // Basic validation
    if (empty($event_date)) {
        $error = 'Please select an event date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || !checkdate(
        (int)substr($event_date, 5, 2),
        (int)substr($event_date, 8, 2),
        (int)substr($event_date, 0, 4)
    )) {
        $error = 'Please select a valid event date.';
    } elseif ($guests < 1) {
        $error = 'Please enter the number of guests (at least 1).';
    } elseif (!empty($package_halls) && $hall_id_sel <= 0) {
        $error = 'Please select a venue/hall for this package.';
    }

    if (empty($error)) {
        // ------------------------------------------------------------------
        // Resolve hall information
        // ------------------------------------------------------------------
        if (!empty($package_halls)) {
            // Validate chosen hall belongs to this package
            $chosen_hall = null;
            foreach ($package_halls as $ph) {
                if ((int)$ph['hall_id'] === $hall_id_sel) {
                    $chosen_hall = $ph;
                    break;
                }
            }
            if (!$chosen_hall) {
                $error = 'Invalid hall selection. Please choose a valid venue/hall.';
            }
        } else {
            $chosen_hall = null; // package has no linked halls → custom / hall-less booking
        }
    }

    if (empty($error)) {
        // ------------------------------------------------------------------
        // Process time slot selection (if hall has configured time slots)
        // ------------------------------------------------------------------
        $selected_slot_ids_json = trim($_POST['selected_slot_ids'] ?? '');
        $session_slots   = [];
        $slot_start_time = '';
        $slot_end_time   = '';
        $selected_shift  = '';

        if ($chosen_hall && (int)$chosen_hall['hall_id'] > 0) {
            $hall_configured_slots = getHallTimeSlots((int)$chosen_hall['hall_id']);

            if (!empty($hall_configured_slots)) {
                // Hall has configured time slots → require the user to select one
                if (empty($selected_slot_ids_json)) {
                    $error = 'Please select a time slot for this hall.';
                } else {
                    $slot_ids = json_decode($selected_slot_ids_json, true);
                    if (!is_array($slot_ids) || empty($slot_ids)) {
                        $error = 'Please select a valid time slot.';
                    } else {
                        // Build a lookup map from hall slot id → slot row
                        $hall_slot_map = [];
                        foreach ($hall_configured_slots as $hs) {
                            $hall_slot_map[(int)$hs['id']] = $hs;
                        }

                        // Validate each submitted slot ID
                        foreach ($slot_ids as $sid) {
                            $sid = intval($sid);
                            if (!isset($hall_slot_map[$sid])) {
                                $error = 'Invalid time slot selected. Please try again.';
                                break;
                            }
                            if (!checkIndividualSlotAvailability($sid, (int)$chosen_hall['hall_id'], $event_date)) {
                                $error = 'One or more selected time slots are no longer available. Please select different slots.';
                                break;
                            }
                        }

                        if (empty($error)) {
                            foreach ($slot_ids as $sid) {
                                $sid = intval($sid);
                                $hs  = $hall_slot_map[$sid];
                                $session_slots[] = [
                                    'id'             => $sid,
                                    'slot_name'      => $hs['slot_name'],
                                    'start_time'     => substr($hs['start_time'], 0, 5),
                                    'end_time'       => substr($hs['end_time'], 0, 5),
                                    'price_override' => $hs['price_override'] !== null
                                                            ? (float)$hs['price_override'] : null,
                                ];
                            }
                            usort($session_slots, function ($a, $b) {
                                return strcmp($a['start_time'], $b['start_time']);
                            });
                            $starts          = array_column($session_slots, 'start_time');
                            $ends            = array_column($session_slots, 'end_time');
                            $slot_start_time = min($starts);
                            $slot_end_time   = max($ends);
                            $selected_shift  = deriveShiftFromTimes($slot_start_time, $slot_end_time);
                        }
                    }
                }
            }
        }

        if (empty($error)) {
        // ------------------------------------------------------------------
        // Populate session data — mirrors what the normal multi-step flow does
        // ------------------------------------------------------------------
        $_SESSION['booking_data'] = [
            'event_date'      => $event_date,
            'guests'          => $guests,
            'event_type'      => $event_type,
            'shift'           => $selected_shift,
            'start_time'      => $slot_start_time,
            'end_time'        => $slot_end_time,
            'city_id'         => 0,
            'is_package_booking' => true,
            'package_guest_limit' => (int)($package['guest_limit'] ?? 0),
            'selected_slots'  => $session_slots,
            'slot_id'         => !empty($session_slots) ? $session_slots[0]['id'] : null,
            'slot_name'       => !empty($session_slots) ? implode(', ', array_column($session_slots, 'slot_name')) : null,
        ];

        if ($chosen_hall) {
            $_SESSION['selected_hall'] = [
                'id'         => (int)$chosen_hall['hall_id'],
                'name'       => $chosen_hall['hall_name'],
                'venue_name' => $chosen_hall['venue_name'],
                'base_price' => (float)$chosen_hall['hall_base_price'],
                'capacity'   => (int)$chosen_hall['hall_capacity'],
                'is_custom'  => false,
                'custom_venue_name' => '',
                'custom_hall_name'  => '',
            ];
        } else {
            // No hall linked → treat as custom so availability check is skipped
            $_SESSION['selected_hall'] = [
                'id'         => null,
                'name'       => $package['name'],
                'venue_name' => $package['name'],
                'base_price' => 0.0,
                'capacity'   => 0,
                'is_custom'  => true,
                'custom_venue_name' => $package['name'],
                'custom_hall_name'  => $package['name'],
            ];
        }

        $_SESSION['selected_menus']          = $package_menu_ids;
        $_SESSION['menu_selections']         = [];
        $_SESSION['menu_special_instructions'] = '';
        $_SESSION['selected_packages']       = [$package_id];
        $_SESSION['selected_services']       = [];
        $_SESSION['selected_designs']        = [];

        // Save vendor selections for package-included services
        $raw_vendors   = $_POST['vendor_for_service'] ?? [];
        $clean_vendors = [];
        if (is_array($raw_vendors)) {
            foreach ($raw_vendors as $svc_id => $vendor_id) {
                $svc_id_int    = intval($svc_id);
                $vendor_id_int = intval($vendor_id);
                if ($svc_id_int > 0 && $vendor_id_int > 0) {
                    $clean_vendors[$svc_id_int] = $vendor_id_int;
                }
            }
        }
        $_SESSION['vendor_for_service'] = $clean_vendors;

        header('Location: booking-step6.php');
        exit;
        } // end if (empty($error)) - session population
    } // end if (empty($error)) - slot processing
} // end POST handler

// ------------------------------------------------------------------
// Page output
// ------------------------------------------------------------------
$page_title = 'Book Package: ' . htmlspecialchars($package['name']);
$page_robots = 'noindex, nofollow';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Booking Progress (simplified for package flow) -->
<div class="booking-progress py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="progress-steps">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-label">Package Details</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row g-4">

            <!-- Right: Booking form (first in DOM so it appears first on mobile) -->
            <div class="col-lg-7 col-md-6 order-md-1">
                <h2 class="mb-1">Book This Package</h2>
                <p class="text-muted mb-4">Fill in your event details below. Venue, menu, and services are already included in this package.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="package-booking.php?id=<?php echo $package_id; ?>" novalidate>
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt text-success me-2"></i>Event Details</h6>
                        </div>
                        <div class="card-body">

                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="event_date" name="event_date"
                                           value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                                           readonly placeholder="Select event date (Click to open calendar)" required>
                                    <button class="btn btn-outline-success" type="button" id="toggleCalendar" title="Toggle between BS/AD calendar">
                                        <i class="fas fa-exchange-alt"></i> <span id="calendarType">BS</span>
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    <span id="nepaliDateDisplay"></span>
                                </small>
                                <div class="invalid-feedback">Please select an event date.</div>
                            </div>

                            <div class="mb-3">
                                <label for="guests" class="form-label">Number of Guests <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="guests" name="guests"
                                       value="<?php echo htmlspecialchars($_POST['guests'] ?? ''); ?>"
                                       min="1" max="10000" placeholder="e.g., 200" required>
                                <div class="invalid-feedback">Please enter a valid guest count.</div>
                                <?php if (!empty($package['guest_limit'])): ?>
                                <div class="form-text">
                                    <i class="fas fa-info-circle text-primary me-1"></i>
                                    This package covers up to <strong><?php echo (int)$package['guest_limit']; ?> guests</strong>.
                                    If your guest count exceeds this limit, menu charges will apply for the additional guests only.
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($package_halls)): ?>
                            <div class="mb-3">
                                <label for="hall_id" class="form-label">
                                    Select Venue / Hall
                                    <?php if (count($package_halls) > 1): ?>
                                    <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                <?php if (count($package_halls) === 1): ?>
                                    <?php $ph = $package_halls[0]; ?>
                                    <input type="hidden" name="hall_id" value="<?php echo (int)$ph['hall_id']; ?>">
                                    <div class="alert alert-light border mb-0 py-2">
                                        <i class="fas fa-building text-success me-1"></i>
                                        <strong><?php echo htmlspecialchars($ph['venue_name']); ?></strong>
                                        — <?php echo htmlspecialchars($ph['hall_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <select class="form-select" id="hall_id" name="hall_id" required>
                                        <option value="">Select a hall...</option>
                                        <?php foreach ($package_halls as $ph): ?>
                                        <option value="<?php echo (int)$ph['hall_id']; ?>"
                                                <?php echo (intval($_POST['hall_id'] ?? 0) === (int)$ph['hall_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ph['venue_name'] . ' — ' . $ph['hall_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a hall.</div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <!-- No halls linked; hall_id defaults to 0 (no-hall booking) -->
                            <input type="hidden" name="hall_id" value="0">
                            <?php endif; ?>

                            <?php if (!empty($package_halls)): ?>
                            <!-- Time Slot Selection (shown when a hall and date are both selected) -->
                            <div id="pkgTimeSlotSection" class="mb-3" style="display:none;">
                                <label class="form-label">Time Slot <span class="text-danger">*</span></label>
                                <div id="pkgSlotNotSelected">
                                    <button type="button" class="btn btn-outline-success" id="pkgOpenSlotsBtn">
                                        <i class="fas fa-clock me-1"></i>Select Time Slot
                                    </button>
                                    <div class="form-text text-muted mt-1">Please select a time slot for your event.</div>
                                </div>
                                <div id="pkgSlotSelected" style="display:none;">
                                    <div class="alert alert-success d-flex justify-content-between align-items-center py-2 mb-0">
                                        <span><i class="fas fa-clock me-1"></i> <span id="pkgSlotSummary"></span></span>
                                        <button type="button" class="btn btn-sm btn-outline-success" id="pkgChangeSlotBtn">
                                            <i class="fas fa-edit me-1"></i>Change
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" id="pkg_selected_slot_ids" name="selected_slot_ids"
                                       value="<?php echo htmlspecialchars($_POST['selected_slot_ids'] ?? ''); ?>">
                                <input type="hidden" id="pkg_slot_start_time" name="slot_start_time"
                                       value="<?php echo htmlspecialchars($_POST['slot_start_time'] ?? ''); ?>">
                                <input type="hidden" id="pkg_slot_end_time" name="slot_end_time"
                                       value="<?php echo htmlspecialchars($_POST['slot_end_time'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <?php if (!empty($pkg_services)): ?>
                    <!-- Vendor selection for package-included services -->
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="fas fa-user-tie text-success me-2"></i>Select Service Providers
                                <span class="text-muted fw-normal small">(Optional)</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                These services are included in your package. Optionally select a preferred vendor for each.
                            </p>
                            <div class="row g-2">
                                <?php foreach ($pkg_services as $svc_id => $svc): ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 p-2 border rounded">
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold small text-truncate">
                                                <?php echo htmlspecialchars($svc['service_name'] ?: $svc['feature_text'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div id="pkgsvc-vendor-label-<?php echo $svc_id; ?>" class="small text-muted"></div>
                                        </div>
                                        <?php if (!empty($svc['vendor_type_slug'])): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success flex-shrink-0"
                                                id="pkgsvc-vendor-btn-<?php echo $svc_id; ?>"
                                                onclick="pkgOpenVendorModal(<?php echo $svc_id; ?>, <?php echo json_encode($svc['service_name'] ?: $svc['feature_text']); ?>, <?php echo json_encode($svc['vendor_type_slug']); ?>)"
                                                title="Select vendor">
                                            <i class="fas fa-plus me-1"></i>Add Vendor
                                        </button>
                                        <?php else: ?>
                                        <span class="badge bg-success-subtle text-success flex-shrink-0">
                                            <i class="fas fa-check me-1"></i>Included
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Hidden inputs for selected vendor IDs (submitted with form) -->
                            <div id="pkg-vendor-inputs"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_package_booking" class="btn btn-success btn-lg">
                            <i class="fas fa-arrow-right me-2"></i> Continue to Confirm Booking
                        </button>
                        <a href="packages.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Packages
                        </a>
                    </div>
                </form>
            </div>

            <!-- Left: Package summary (second in DOM so it appears below on mobile) -->
            <div class="col-lg-5 col-md-6 order-md-2">
                <div class="card shadow-sm sticky-top" style="top:80px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i><?php echo htmlspecialchars($package['name']); ?></h5>
                    </div>

                    <?php if (!empty($photos)): ?>
                    <div id="pkgBookCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($photos as $i => $photo): ?>
                            <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo UPLOAD_URL . htmlspecialchars(basename($photo), ENT_QUOTES, 'UTF-8'); ?>"
                                     class="d-block w-100"
                                     style="height:200px;object-fit:cover;"
                                     alt="<?php echo htmlspecialchars($package['name']); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($photos) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#pkgBookCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#pkgBookCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <?php if (!empty($package['category_name'])): ?>
                        <p class="text-muted small mb-1">
                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($package['category_name']); ?>
                        </p>
                        <?php endif; ?>

                        <h4 class="text-success fw-bold mb-2">
                            <?php echo formatCurrency($package['price']); ?>
                        </h4>

                        <?php if (!empty($package['description'])): ?>
                        <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($features)): ?>
                        <div class="mb-2">
                            <p class="fw-semibold small mb-1"><i class="fas fa-check-circle text-success me-1"></i> Included:</p>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($features as $feat): ?>
                                <li class="mb-1 d-flex align-items-center justify-content-between gap-1">
                                    <span>
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo htmlspecialchars($feat['feature_text']); ?>
                                    </span>
                                    <?php
                                    $feat_svc_id = !empty($feat['service_id']) ? (int)$feat['service_id'] : 0;
                                    $feat_svc    = $feat_svc_id > 0 && isset($pkg_services[$feat_svc_id])
                                                   ? $pkg_services[$feat_svc_id] : null;
                                    if ($feat_svc && !empty($feat_svc['vendor_type_slug'])):
                                    ?>
                                    <button type="button"
                                            class="btn btn-xs py-0 px-1 btn-outline-success flex-shrink-0"
                                            style="font-size:0.7rem;"
                                            onclick="pkgOpenVendorModal(<?php echo $feat_svc_id; ?>, <?php echo json_encode($feat_svc['service_name'] ?: $feat['feature_text']); ?>, <?php echo json_encode($feat_svc['vendor_type_slug']); ?>)"
                                            id="pkgfeat-vendor-btn-<?php echo $feat_svc_id; ?>"
                                            title="Select vendor">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($package_menu_ids)): ?>
                        <div class="mt-2 pt-2 border-top">
                            <p class="small text-muted mb-0">
                                <i class="fas fa-utensils text-success me-1"></i>
                                <?php echo count($package_menu_ids); ?> menu(s) included
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($package_halls)): ?>
                        <div class="mt-2 pt-2 border-top">
                            <p class="small text-muted mb-0">
                                <i class="fas fa-building text-success me-1"></i>
                                Available at <?php echo count($package_halls); ?> hall(s)
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Vendor Selection Modal (package services) -->
<style>
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
<div class="modal fade" id="pkgVendorSelectModal" tabindex="-1" aria-labelledby="pkgVendorSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="pkgVendorSelectModalLabel">
                    <i class="fas fa-user-tie me-2"></i>Select Vendor for <span id="pkgVendorModalServiceName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle me-1 text-info"></i>
                    Select a vendor for this service. You can also skip and let our team assign one for you.
                </p>
                <div id="pkgVendorModalList" class="row g-3">
                    <!-- Populated by JS -->
                </div>
                <div id="pkgVendorModalEmpty" class="alert alert-info" style="display:none;">
                    <i class="fas fa-info-circle me-1"></i>No vendors available for this service type. Our team will coordinate with you.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="pkgVendorSkipBtn" data-bs-dismiss="modal">
                    <i class="fas fa-forward me-1"></i>Skip – assign later
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Time Slot Selection Modal -->
<div class="modal fade" id="pkgTimeSlotModal" tabindex="-1" aria-labelledby="pkgTimeSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="pkgTimeSlotModalLabel">
                    <i class="fas fa-clock me-2"></i>Select Time Slots — <span id="pkgTsModalHallName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Select one available time slot for your event on <strong id="pkgTsModalDate"></strong>.
                    Only a single time slot can be selected.
                    Slots already booked are shown as unavailable.
                </p>
                <div id="pkgTimeSlotsContainer">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading available time slots…</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="pkgSlotSelectionPreview" class="me-auto text-success fw-semibold small" style="display:none;"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="pkgConfirmSlotBtn" disabled>
                    <i class="fas fa-check me-1"></i>Confirm Time Slot
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function (e) {
            form.classList.add('was-validated');
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }
})();
</script>

<!-- Vendor data + selection logic for package-booking page -->
<script>
var pkgVendorsByType = <?php echo json_encode($vendors_by_type); ?>;
var uploadUrl        = <?php echo json_encode(rtrim(UPLOAD_URL, '/')); ?>;
var pkgVendorForService = {}; // service_id → vendor_id
var pkgVendorModalServiceId = null;

function pkgEscapeHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function pkgSyncVendorInputs() {
    var container = document.getElementById('pkg-vendor-inputs');
    if (!container) return;
    container.innerHTML = '';
    Object.keys(pkgVendorForService).forEach(function (sid) {
        var vid = pkgVendorForService[sid];
        if (!vid) return;
        var input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'vendor_for_service[' + sid + ']';
        input.value = vid;
        container.appendChild(input);
    });
}

function pkgUpdateVendorLabels() {
    Object.keys(pkgVendorForService).forEach(function (sid) {
        var vid = pkgVendorForService[sid];
        var labelEl = document.getElementById('pkgsvc-vendor-label-' + sid)
                   || document.getElementById('pkgfeat-vendor-label-' + sid);
        var btn1    = document.getElementById('pkgsvc-vendor-btn-' + sid);
        var btn2    = document.getElementById('pkgfeat-vendor-btn-' + sid);

        var vendorName = '';
        if (vid) {
            Object.values(pkgVendorsByType).forEach(function (list) {
                list.forEach(function (v) {
                    if (v.id === vid) vendorName = v.name;
                });
            });
        }

        if (labelEl) {
            labelEl.innerHTML = vid
                ? '<i class="fas fa-check-circle text-success me-1"></i>' + pkgEscapeHtml(vendorName || 'Vendor selected')
                : '';
        }
        [btn1, btn2].forEach(function (btn) {
            if (!btn) return;
            if (vid) {
                btn.innerHTML = '<i class="fas fa-edit me-1"></i>Change';
                btn.classList.remove('btn-outline-success');
                btn.classList.add('btn-success');
            } else {
                btn.innerHTML = btn.id && btn.id.indexOf('pkgfeat') === 0
                    ? '<i class="fas fa-plus"></i>'
                    : '<i class="fas fa-plus me-1"></i>Add Vendor';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-success');
            }
        });
    });
}

function pkgOpenVendorModal(serviceId, serviceName, vendorTypeSlug) {
    pkgVendorModalServiceId = serviceId;
    var modalEl = document.getElementById('pkgVendorSelectModal');
    if (!modalEl) return;

    var nameEl = document.getElementById('pkgVendorModalServiceName');
    if (nameEl) nameEl.textContent = serviceName;

    var listEl  = document.getElementById('pkgVendorModalList');
    var emptyEl = document.getElementById('pkgVendorModalEmpty');

    var vendors = (pkgVendorsByType && pkgVendorsByType[vendorTypeSlug]) || [];

    if (listEl)  listEl.innerHTML = '';
    if (vendors.length === 0) {
        if (emptyEl) emptyEl.style.display = 'block';
        if (listEl)  listEl.style.display  = 'none';
    } else {
        if (emptyEl) emptyEl.style.display = 'none';
        if (listEl)  listEl.style.display  = '';

        var currentVendorId = pkgVendorForService[serviceId] || 0;
        vendors.forEach(function (v) {
            var isSelected = (currentVendorId === v.id);
            var photoHtml = v.photo
                ? '<img src="' + pkgEscapeHtml(uploadUrl + '/' + v.photo) + '" alt="' + pkgEscapeHtml(v.name) + '" class="rounded-circle me-2" style="width:40px;height:40px;object-fit:cover;">'
                : '<span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;font-size:1.1rem;"><i class="fas fa-user-tie"></i></span>';

            var cityText  = v.city_name ? '<small class="text-muted">' + pkgEscapeHtml(v.city_name) + '</small>' : '';
            var phoneText = v.phone     ? '<small class="text-muted"><i class="fas fa-phone me-1"></i>' + pkgEscapeHtml(v.phone) + '</small>' : '';
            var infoLine  = [cityText, phoneText].filter(Boolean).join(' &bull; ');

            var vendorId = parseInt(v.id) || 0;
            var card = document.createElement('div');
            card.className = 'col-12 col-md-6';
            card.innerHTML = '<div class="card vendor-select-card h-100 ' + (isSelected ? 'border-success selected-vendor' : '') + '" style="cursor:pointer;" data-vendor-id="' + vendorId + '">'
                + '<div class="card-body d-flex align-items-center py-2 px-3">'
                + photoHtml
                + '<div class="flex-grow-1 min-w-0">'
                + '<div class="fw-semibold">' + pkgEscapeHtml(v.name) + (isSelected ? ' <i class="fas fa-check-circle text-success ms-1"></i>' : '') + '</div>'
                + (infoLine ? '<div>' + infoLine + '</div>' : '')
                + '</div>'
                + '</div></div>';

            card.querySelector('.vendor-select-card').addEventListener('click', function () {
                pkgSelectVendor(serviceId, parseInt(this.dataset.vendorId));
            });
            listEl.appendChild(card);
        });
    }

    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function pkgSelectVendor(serviceId, vendorId) {
    pkgVendorForService[serviceId] = vendorId;
    pkgSyncVendorInputs();
    pkgUpdateVendorLabels();

    var modalEl = document.getElementById('pkgVendorSelectModal');
    if (modalEl) {
        var inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var skipBtn = document.getElementById('pkgVendorSkipBtn');
    if (skipBtn) {
        skipBtn.addEventListener('click', function () {
            if (pkgVendorModalServiceId !== null) {
                delete pkgVendorForService[pkgVendorModalServiceId];
                pkgSyncVendorInputs();
                pkgUpdateVendorLabels();
                pkgVendorModalServiceId = null;
            }
        });
    }
});
</script>

<?php
// -------------------------------------------------------------------------
// Pass hall data to JS so the time-slot section knows about configured halls
// -------------------------------------------------------------------------
$pkg_halls_js = [];
foreach ($package_halls as $ph) {
    $pkg_halls_js[] = [
        'hallId'   => (int)$ph['hall_id'],
        'hallName' => $ph['venue_name'] . ' — ' . $ph['hall_name'],
    ];
}

$extra_js = '<script src="' . BASE_URL . '/js/booking-flow.js"></script>
<script>
(function () {
    \'use strict\';

    // ── State ─────────────────────────────────────────────────────────────
    var _pkgSlots         = [];   // slot objects currently selected in the modal
    var _pkgHallId        = 0;
    var _pkgCurrentDate   = \'\';
    var _pkgHasSlotsConf  = false; // true once slots are loaded and there is ≥1 slot
    var _pkgInitialized   = false; // true after initial page setup; guards auto-open

    // ── Helpers ───────────────────────────────────────────────────────────
    function escapeHtml(text) {
        if (text === null || text === undefined) return \'\';
        return String(text)
            .replace(/&/g, \'&amp;\')
            .replace(/</g, \'&lt;\')
            .replace(/>/g, \'&gt;\')
            .replace(/"/g, \'&quot;\')
            .replace(/\'/g, \'&#039;\');
    }

    function getSelectedHallId() {
        var sel = document.getElementById(\'hall_id\');
        if (sel) return parseInt(sel.value, 10) || 0;
        var hidden = document.querySelector(\'input[type="hidden"][name="hall_id"]\');
        return hidden ? parseInt(hidden.value, 10) || 0 : 0;
    }

    function getSelectedHallName() {
        var sel = document.getElementById(\'hall_id\');
        if (sel && sel.options && sel.selectedIndex >= 0 && sel.value) {
            return sel.options[sel.selectedIndex].text;
        }
        // Single hall display text
        var disp = document.querySelector(\'.alert.alert-light.border strong\');
        if (disp) {
            var sibling = disp.nextSibling;
            return disp.textContent + (sibling ? sibling.textContent : \'\');
        }
        return \'Hall\';
    }

    function getPkgAggregatedTimes(slots) {
        if (!slots || slots.length === 0) {
            return { aggStart: \'00:00\', aggEnd: \'00:00\' };
        }
        var starts = slots.map(function(s) { return s.start.substring(0, 5); });
        var ends   = slots.map(function(s) { return s.end.substring(0, 5); });
        return {
            aggStart: starts.reduce(function(a, b) { return a < b ? a : b; }),
            aggEnd:   ends.reduce(function(a, b) { return a > b ? a : b; })
        };
    }

    function fmtTime(t) {
        var parts = t.split(\':\').map(Number);
        var h = parts[0], m = parts[1];
        var ampm = h >= 12 ? \'PM\' : \'AM\';
        var h12  = h % 12 || 12;
        return h12 + \':\' + String(m).padStart(2, \'0\') + \' \' + ampm;
    }

    // ── Slot section visibility ────────────────────────────────────────────
    function updateTimeSlotSectionVisibility() {
        var section = document.getElementById(\'pkgTimeSlotSection\');
        if (!section) return;

        var hallId = getSelectedHallId();
        var dateEl = document.getElementById(\'event_date\');
        var date   = dateEl ? dateEl.value : \'\';

        if (hallId > 0 && date) {
            section.style.display = \'\';
            // Clear slot selection when hall or date changes
            if (hallId !== _pkgHallId || date !== _pkgCurrentDate) {
                clearPkgSlotSelection();
                _pkgHallId      = hallId;
                _pkgCurrentDate = date;
                // Auto-open the time slot modal when the user selects or changes a hall/date
                if (_pkgInitialized) {
                    openPkgTimeSlotsModal();
                }
            }
        } else {
            section.style.display = \'none\';
            clearPkgSlotSelection();
        }
    }

    function clearPkgSlotSelection() {
        _pkgSlots        = [];
        _pkgHasSlotsConf = false;
        var ids = document.getElementById(\'pkg_selected_slot_ids\');
        if (ids) ids.value = \'\';
        var st = document.getElementById(\'pkg_slot_start_time\');
        if (st) st.value = \'\';
        var et = document.getElementById(\'pkg_slot_end_time\');
        if (et) et.value = \'\';
        var notSel = document.getElementById(\'pkgSlotNotSelected\');
        if (notSel) {
            notSel.style.display = \'\';
            notSel.innerHTML = \'<button type="button" class="btn btn-outline-success" id="pkgOpenSlotsBtn"><i class="fas fa-clock me-1"></i>Select Time Slot</button>\' +
                \'<div class="form-text text-muted mt-1">Please select a time slot for your event.</div>\';
            var btn = document.getElementById(\'pkgOpenSlotsBtn\');
            if (btn) btn.addEventListener(\'click\', openPkgTimeSlotsModal);
        }
        var selEl = document.getElementById(\'pkgSlotSelected\');
        if (selEl) selEl.style.display = \'none\';
    }

    // ── Open modal ────────────────────────────────────────────────────────
    function openPkgTimeSlotsModal() {
        var hallId = getSelectedHallId();
        var dateEl = document.getElementById(\'event_date\');
        var date   = dateEl ? dateEl.value : \'\';
        if (!hallId || !date) return;

        _pkgSlots = [];

        var hallNameEl = document.getElementById(\'pkgTsModalHallName\');
        if (hallNameEl) hallNameEl.textContent = getSelectedHallName();
        var dateDispEl = document.getElementById(\'pkgTsModalDate\');
        if (dateDispEl) dateDispEl.textContent = date;

        var confirmBtn = document.getElementById(\'pkgConfirmSlotBtn\');
        if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.innerHTML = \'<i class="fas fa-check me-1"></i>Confirm Time Slot\'; }
        var preview = document.getElementById(\'pkgSlotSelectionPreview\');
        if (preview) { preview.style.display = \'none\'; preview.textContent = \'\'; }

        var container = document.getElementById(\'pkgTimeSlotsContainer\');
        if (container) {
            container.innerHTML = \'<div class="text-center py-4"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading…</span></div><p class="mt-2 text-muted">Loading available time slots…</p></div>\';
        }

        var modalEl = document.getElementById(\'pkgTimeSlotModal\');
        if (!modalEl) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        var params = new URLSearchParams({ hall_id: hallId, date: date });
        fetch(baseUrl + \'/api/get-time-slots.php?\' + params)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    container.innerHTML = \'<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>\' + escapeHtml(data.message || \'Failed to load time slots.\') + \'</div>\';
                    return;
                }
                renderPkgTimeSlots(data.slots, container);
            })
            .catch(function() {
                container.innerHTML = \'<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>An error occurred while loading time slots.</div>\';
            });
    }

    // ── Render slot cards ─────────────────────────────────────────────────
    function renderPkgTimeSlots(slots, container) {
        if (!slots || slots.length === 0) {
            _pkgHasSlotsConf = false;
            container.innerHTML = \'<div class="alert alert-warning"><i class="fas fa-clock me-1"></i>No time slots have been configured for this hall. You can proceed without selecting a time slot.</div>\';
            var confirmBtn = document.getElementById(\'pkgConfirmSlotBtn\');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = \'<i class="fas fa-check me-1"></i>Continue Without Time Slot\';
            }
            return;
        }

        _pkgHasSlotsConf = true;
        var html = \'<div class="row g-3">\';
        slots.forEach(function(slot) {
            var available  = slot.available;
            // formatCurrency is provided globally by main.js (loaded via footer.php)
            var priceLabel = slot.price_override !== null
                ? formatCurrency(slot.price_override)
                : formatCurrency(0);

            html += \'<div class="col-12 col-md-6">\' +
                \'<div class="card h-100 time-slot-card \' + (available ? \'border-success\' : \'border-secondary opacity-50\') + \'"\' +
                \' data-slot-id="\' + parseInt(slot.id, 10) + \'"\' +
                \' data-slot-name="\' + escapeHtml(slot.slot_name) + \'"\' +
                \' data-start="\' + escapeHtml(slot.start_time) + \'"\' +
                \' data-end="\' + escapeHtml(slot.end_time) + \'"\' +
                \' data-price="\' + (slot.price_override !== null ? parseFloat(slot.price_override) : \'\') + \'"\' +
                \' data-available="\' + (available ? \'1\' : \'0\') + \'"\' +
                \' style="\' + (available ? \'cursor:pointer;\' : \'cursor:not-allowed;\') + \'">\' +
                \'<div class="card-body d-flex flex-column justify-content-between">\' +
                \'<div>\' +
                \'<h6 class="card-title mb-1 \' + (available ? \'text-success\' : \'text-muted\') + \'">\' +
                \'<i class="fas fa-clock me-1"></i>\' + escapeHtml(slot.slot_name) + \'</h6>\' +
                \'<p class="text-muted mb-2 small">\' + escapeHtml(slot.start_time_display) + \' – \' + escapeHtml(slot.end_time_display) + \'</p>\' +
                \'<p class="mb-0 fw-semibold small">\' + priceLabel + \'</p>\' +
                \'</div><div class="mt-2">\' +
                (available
                    ? \'<span class="badge bg-success slot-status-badge"><i class="fas fa-check-circle me-1"></i>Available</span>\'
                    : \'<span class="badge bg-secondary slot-status-badge"><i class="fas fa-ban me-1"></i>Already Booked</span>\') +
                \'</div></div></div></div>\';
        });
        html += \'</div>\';
        container.innerHTML = html;

        container.querySelectorAll(\'.time-slot-card\').forEach(function(card) {
            var slotId = parseInt(card.getAttribute(\'data-slot-id\'), 10);
            if (card.getAttribute(\'data-available\') !== \'1\') return;

            card.addEventListener(\'click\', function() {
                var existingIdx = _pkgSlots.findIndex(function(s) { return s.id === slotId; });
                var badge = this.querySelector(\'.slot-status-badge\');

                if (existingIdx >= 0) {
                    // Deselect this slot
                    _pkgSlots.splice(existingIdx, 1);
                    this.classList.remove(\'selected-slot\', \'border-warning\', \'shadow\');
                    this.classList.add(\'border-success\');
                    if (badge) {
                        badge.className = \'badge bg-success slot-status-badge\';
                        badge.innerHTML = \'<i class="fas fa-check-circle me-1"></i>Available\';
                    }
                } else {
                    // Single selection: deselect any previously selected slot first
                    container.querySelectorAll(\'.time-slot-card.selected-slot\').forEach(function(otherCard) {
                        otherCard.classList.remove(\'selected-slot\', \'border-warning\', \'shadow\');
                        otherCard.classList.add(\'border-success\');
                        var otherBadge = otherCard.querySelector(\'.slot-status-badge\');
                        if (otherBadge) {
                            otherBadge.className = \'badge bg-success slot-status-badge\';
                            otherBadge.innerHTML = \'<i class="fas fa-check-circle me-1"></i>Available\';
                        }
                    });
                    _pkgSlots = [{
                        id:    slotId,
                        name:  this.getAttribute(\'data-slot-name\'),
                        start: this.getAttribute(\'data-start\'),
                        end:   this.getAttribute(\'data-end\'),
                    }];
                    this.classList.add(\'selected-slot\', \'border-warning\', \'shadow\');
                    this.classList.remove(\'border-success\');
                    if (badge) {
                        badge.className = \'badge bg-primary slot-status-badge\';
                        badge.innerHTML = \'<i class="fas fa-check me-1"></i>Selected\';
                    }
                }

                var confirmBtn = document.getElementById(\'pkgConfirmSlotBtn\');
                if (confirmBtn) confirmBtn.disabled = _pkgSlots.length === 0;
                updatePkgSlotPreview();
            }.bind(card));
        });
    }

    function updatePkgSlotPreview() {
        var preview = document.getElementById(\'pkgSlotSelectionPreview\');
        if (!preview) return;
        if (_pkgSlots.length === 0) { preview.style.display = \'none\'; preview.textContent = \'\'; return; }
        var times = getPkgAggregatedTimes(_pkgSlots);
        var count = _pkgSlots.length;
        var label = count === 1 ? \'1 slot selected\' : count + \' slots selected\';
        preview.textContent = \'⏰ \' + label + \': \' + fmtTime(times.aggStart) + \' – \' + fmtTime(times.aggEnd);
        preview.style.display = \'\';
    }

    // ── DOMContentLoaded ──────────────────────────────────────────────────
    document.addEventListener(\'DOMContentLoaded\', function() {

        // Restore pre-filled slot selection (after a POST error re-render)
        (function restoreSlotSelection() {
            var idsInput = document.getElementById(\'pkg_selected_slot_ids\');
            var stInput  = document.getElementById(\'pkg_slot_start_time\');
            var etInput  = document.getElementById(\'pkg_slot_end_time\');
            if (!idsInput || !idsInput.value) return;
            try {
                var ids = JSON.parse(idsInput.value);
                if (!Array.isArray(ids) || ids.length === 0) return;
                var st = stInput ? stInput.value : \'\';
                var et = etInput ? etInput.value : \'\';
                if (st && et) {
                    var summaryEl = document.getElementById(\'pkgSlotSummary\');
                    if (summaryEl) summaryEl.textContent = st + \' – \' + et;
                    var notSel = document.getElementById(\'pkgSlotNotSelected\');
                    var selEl  = document.getElementById(\'pkgSlotSelected\');
                    if (notSel) notSel.style.display = \'none\';
                    if (selEl)  selEl.style.display  = \'\';
                }
            } catch (e) { /* ignore */ }
        }());

        // Watch date changes to toggle slot section visibility
        var eventDateEl = document.getElementById(\'event_date\');
        if (eventDateEl) {
            eventDateEl.addEventListener(\'change\', updateTimeSlotSectionVisibility);
        }

        // Watch hall dropdown changes
        var hallSel = document.getElementById(\'hall_id\');
        if (hallSel) {
            hallSel.addEventListener(\'change\', updateTimeSlotSectionVisibility);
        }

        // Initial visibility (single-hall case: hall_id already set)
        updateTimeSlotSectionVisibility();
        // Mark page as initialized so subsequent hall/date changes trigger auto-open
        _pkgInitialized = true;

        // Open modal button (initial render; also re-wired after clearPkgSlotSelection)
        var openBtn = document.getElementById(\'pkgOpenSlotsBtn\');
        if (openBtn) openBtn.addEventListener(\'click\', openPkgTimeSlotsModal);

        // Change slot button
        var changeBtn = document.getElementById(\'pkgChangeSlotBtn\');
        if (changeBtn) {
            changeBtn.addEventListener(\'click\', function() {
                clearPkgSlotSelection();
                openPkgTimeSlotsModal();
            });
        }

        // Confirm slot selection
        var confirmBtn = document.getElementById(\'pkgConfirmSlotBtn\');
        if (confirmBtn) {
            confirmBtn.addEventListener(\'click\', function() {
                var modal = bootstrap.Modal.getInstance(document.getElementById(\'pkgTimeSlotModal\'));
                if (modal) modal.hide();

                if (!_pkgHasSlotsConf) {
                    // No slots configured – allow submission without slot data
                    clearPkgSlotSelection();
                    return;
                }

                if (_pkgSlots.length === 0) return;

                var sorted   = _pkgSlots.slice().sort(function(a, b) { return a.start.localeCompare(b.start); });
                var slotIds  = sorted.map(function(s) { return s.id; });
                var slotNames = sorted.map(function(s) { return s.name; }).join(\', \');
                var times    = getPkgAggregatedTimes(sorted);

                document.getElementById(\'pkg_selected_slot_ids\').value = JSON.stringify(slotIds);
                document.getElementById(\'pkg_slot_start_time\').value   = times.aggStart;
                document.getElementById(\'pkg_slot_end_time\').value     = times.aggEnd;

                var summaryEl = document.getElementById(\'pkgSlotSummary\');
                if (summaryEl) summaryEl.textContent = slotNames + \'  \' + fmtTime(times.aggStart) + \' – \' + fmtTime(times.aggEnd);

                var notSel = document.getElementById(\'pkgSlotNotSelected\');
                var selEl  = document.getElementById(\'pkgSlotSelected\');
                if (notSel) notSel.style.display = \'none\';
                if (selEl)  selEl.style.display  = \'\';
            });
        }

        // Extra form-submit guard: prevent submission if slot section is visible
        // and no slot has been selected (and the hall has configured slots)
        var form = document.querySelector(\'form[method="POST"]\');
        if (form) {
            form.addEventListener(\'submit\', function(e) {
                var section = document.getElementById(\'pkgTimeSlotSection\');
                if (!section || section.style.display === \'none\') return; // not visible → skip check
                var idsVal = document.getElementById(\'pkg_selected_slot_ids\').value;
                if (_pkgHasSlotsConf && !idsVal) {
                    // We know slots are configured but none selected → block
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    var notSel = document.getElementById(\'pkgSlotNotSelected\');
                    if (notSel) {
                        notSel.innerHTML = \'<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Please select a time slot before proceeding.</span>\' +
                            \'<br><button type="button" class="btn btn-outline-success mt-2" id="pkgOpenSlotsBtn"><i class="fas fa-clock me-1"></i>Select Time Slot</button>\';
                        var btn = document.getElementById(\'pkgOpenSlotsBtn\');
                        if (btn) btn.addEventListener(\'click\', openPkgTimeSlotsModal);
                    }
                    section.scrollIntoView({ behavior: \'smooth\' });
                }
            });
        }
    });
}());
</script>';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
