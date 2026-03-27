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
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || !checkdate(
        (int)substr($event_date, 5, 2),
        (int)substr($event_date, 8, 2),
        (int)substr($event_date, 0, 4)
    )) {
        $error = 'Please select a valid event date.';
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
        $_SESSION['vendor_for_service']      = [];

        header('Location: booking-step6.php');
        exit;
        } // end if (empty($error)) - session population
    } // end if (empty($error)) - slot processing
} // end POST handler

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
                    Select one or more available time slots for your event on <strong id="pkgTsModalDate"></strong>.
                    You can pick multiple consecutive slots — the booking will span from the earliest start to the latest end time.
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
                    _pkgSlots.splice(existingIdx, 1);
                    this.classList.remove(\'selected-slot\', \'border-warning\', \'shadow\');
                    this.classList.add(\'border-success\');
                    if (badge) {
                        badge.className = \'badge bg-success slot-status-badge\';
                        badge.innerHTML = \'<i class="fas fa-check-circle me-1"></i>Available\';
                    }
                } else {
                    _pkgSlots.push({
                        id:    slotId,
                        name:  this.getAttribute(\'data-slot-name\'),
                        start: this.getAttribute(\'data-start\'),
                        end:   this.getAttribute(\'data-end\'),
                    });
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
