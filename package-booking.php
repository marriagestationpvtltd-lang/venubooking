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
    $event_type  = trim($_POST['event_type']   ?? '');
    $hall_id_sel = intval($_POST['hall_id']    ?? 0);

    // Basic validation
    if (empty($event_date)) {
        $error = 'Please select an event date.';
    } elseif ($guests < 1) {
        $error = 'Please enter the number of guests (at least 1).';
    } elseif (empty($event_type)) {
        $error = 'Please select an event type.';
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
        // Populate session data — mirrors what the normal multi-step flow does
        // ------------------------------------------------------------------
        $_SESSION['booking_data'] = [
            'event_date'      => $event_date,
            'guests'          => $guests,
            'event_type'      => $event_type,
            'shift'           => '',
            'start_time'      => '',
            'end_time'        => '',
            'city_id'         => 0,
            'is_package_booking' => true,
            'selected_slots'  => [],
            'slot_id'         => null,
            'slot_name'       => null,
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
        $_SESSION['vendor_for_service']      = [];

        header('Location: booking-step6.php');
        exit;
    }
}

// ------------------------------------------------------------------
// Page output
// ------------------------------------------------------------------
$page_title = 'Book Package: ' . htmlspecialchars($package['name']);
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

            <!-- Left: Package summary -->
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
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <?php echo htmlspecialchars($feat['feature_text']); ?>
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

            <!-- Right: Booking form -->
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
                                <input type="date" class="form-control" id="event_date" name="event_date"
                                       value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please select an event date.</div>
                            </div>

                            <div class="mb-3">
                                <label for="guests" class="form-label">Number of Guests <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="guests" name="guests"
                                       value="<?php echo htmlspecialchars($_POST['guests'] ?? ''); ?>"
                                       min="1" max="10000" placeholder="e.g., 200" required>
                                <div class="invalid-feedback">Please enter a valid guest count.</div>
                            </div>

                            <div class="mb-3">
                                <label for="event_type" class="form-label">Event Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Choose event type...</option>
                                    <?php
                                    $selected_event_type = $_POST['event_type'] ?? '';
                                    $event_types = ['Wedding', 'Birthday Party', 'Corporate Event', 'Anniversary', 'Other Events'];
                                    foreach ($event_types as $etype):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($etype); ?>"
                                            <?php echo ($selected_event_type === $etype) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($etype); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an event type.</div>
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

                        </div>
                    </div>

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

        </div>
    </div>
</section>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
