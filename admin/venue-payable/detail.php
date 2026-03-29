<?php
// Load core dependencies before any output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// ── Input validation ────────────────────────────────────────────────────────
$venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;
if ($venue_id <= 0) {
    header('Location: index.php');
    exit;
}

// ── Load venue info ─────────────────────────────────────────────────────────
try {
    $venue_stmt = $db->prepare("SELECT id, name, contact_phone, contact_email, location, address FROM venues WHERE id = ?");
    $venue_stmt->execute([$venue_id]);
    $venue = $venue_stmt->fetch();
} catch (PDOException $e) {
    error_log('Venue payable detail: venue lookup failed: ' . $e->getMessage());
    $venue = null;
}

if (!$venue) {
    header('Location: index.php');
    exit;
}

// ── Handle POST: record venue payment for a specific booking ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_venue_payment') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    if ($booking_id > 0) {
        try {
            $payment_sum       = calculatePaymentSummary($booking_id);
            $venue_payable_max = $payment_sum['venue_provider_payable'];
            $venue_amount_paid = min(max(0.0, floatval($_POST['venue_amount_paid'] ?? 0)), $venue_payable_max);

            if (recordVenuePayment($booking_id, $venue_amount_paid)) {
                logActivity($current_user['id'], 'Recorded venue payment', 'bookings', $booking_id,
                    "Venue paid: " . number_format($venue_amount_paid, 2));
                $_SESSION['flash_success'] = 'Venue payment updated successfully.';
            } else {
                $_SESSION['flash_error'] = 'Failed to update venue payment.';
            }
        } catch (Exception $e) {
            error_log('Venue payable detail POST error: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'An error occurred while saving the payment.';
        }
    }
    header('Location: detail.php?venue_id=' . $venue_id);
    exit;
}

// ── Set page title and include header ───────────────────────────────────────
$page_title = 'Payable – ' . htmlspecialchars($venue['name']);
require_once __DIR__ . '/../includes/header.php';

// ── Flash messages ──────────────────────────────────────────────────────────
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Load bookings for this venue ────────────────────────────────────────────
try {
    $bookings_stmt = $db->prepare(
        "SELECT
            b.id,
            b.booking_number,
            b.event_date,
            b.booking_status,
            b.payment_status,
            b.hall_price,
            b.menu_total,
            (b.hall_price + b.menu_total)              AS venue_payable,
            COALESCE(b.venue_amount_paid, 0)           AS venue_amount_paid,
            (b.hall_price + b.menu_total)
                - COALESCE(b.venue_amount_paid, 0)     AS venue_due,
            c.full_name                                AS customer_name,
            c.phone                                    AS customer_phone,
            h.name                                     AS hall_name
         FROM bookings b
         JOIN halls     h ON b.hall_id      = h.id
         JOIN customers c ON b.customer_id  = c.id
        WHERE h.venue_id = ?
          AND b.booking_status NOT IN ('cancelled')
        ORDER BY b.event_date DESC"
    );
    $bookings_stmt->execute([$venue_id]);
    $bookings = $bookings_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Venue payable detail: bookings query failed: ' . $e->getMessage());
    $bookings = [];
}

// ── Aggregate totals ─────────────────────────────────────────────────────────
$agg_payable = 0;
$agg_paid    = 0;
foreach ($bookings as $b) {
    $agg_payable += floatval($b['venue_payable']);
    $agg_paid    += floatval($b['venue_amount_paid']);
}
$agg_due = max(0.0, $agg_payable - $agg_paid);
?>

<?php if ($flash_success): ?>
<div class="alert alert-success alert-dismissible fade show bm-alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($flash_success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="alert alert-danger alert-dismissible fade show bm-alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($flash_error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PAGE HEADER                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-page-header">
    <div class="bm-header-content">
        <div class="bm-header-icon">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
        <div>
            <h1 class="bm-header-title"><?php echo htmlspecialchars($venue['name']); ?></h1>
            <p class="bm-header-subtitle">
                <i class="fas fa-hand-holding-usd me-1"></i>Venue Provider Payable
                <?php if ($venue['location']): ?>
                    &nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($venue['location']); ?>
                <?php endif; ?>
                <?php if ($venue['contact_phone']): ?>
                    &nbsp;·&nbsp;<i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($venue['contact_phone']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="bm-header-actions">
        <a href="index.php" class="bm-btn bm-btn-outline">
            <i class="fas fa-arrow-left me-1"></i>Back to All Venues
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  AGGREGATE STAT CARDS                                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-stats-grid">
    <div class="bm-stat-card bm-stat-active">
        <div class="bm-stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo count($bookings); ?></span>
            <span class="bm-stat-label">Bookings</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-confirmed">
        <div class="bm-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($agg_payable); ?></span>
            <span class="bm-stat-label">Total Payable</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-completed">
        <div class="bm-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($agg_paid); ?></span>
            <span class="bm-stat-label">Total Paid</span>
        </div>
    </div>
    <div class="bm-stat-card <?php echo $agg_due > 0.005 ? 'bm-stat-pending' : 'bm-stat-completed'; ?>">
        <div class="bm-stat-icon"><i class="fas fa-<?php echo $agg_due > 0.005 ? 'exclamation-circle' : 'check-double'; ?>"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($agg_due); ?></span>
            <span class="bm-stat-label">Outstanding Due</span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  BOOKINGS TABLE                                            -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-card">
    <div class="bm-card-toolbar">
        <div class="bm-toolbar-left">
            <h2 class="bm-card-title">
                Bookings for <?php echo htmlspecialchars($venue['name']); ?>
                <span class="bm-count-badge"><?php echo count($bookings); ?></span>
            </h2>
        </div>
    </div>

    <div class="bm-table-wrap">
        <table class="table datatable bm-table mb-0" id="venueDetailTable">
            <thead>
                <tr>
                    <th>Booking #</th>
                    <th>Customer</th>
                    <th>Hall</th>
                    <th>Event Date</th>
                    <th>Status</th>
                    <th class="text-end">Hall Price</th>
                    <th class="text-end">Menu Total</th>
                    <th class="text-end">Payable</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th class="text-center">Record Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="11" class="bm-empty-state">
                            <div class="bm-empty-icon"><i class="fas fa-calendar-times"></i></div>
                            <div class="bm-empty-title">No bookings found</div>
                            <div class="bm-empty-sub">There are no non-cancelled bookings for this venue.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking):
                        $venue_due  = floatval($booking['venue_due']);
                        $is_paid    = $venue_due <= 0.005;

                        // Booking status badge config
                        $bs      = $booking['booking_status'];
                        $bs_cfg  = [
                            'confirmed'         => ['cls' => 'bm-badge-confirmed',  'icon' => 'fa-check-circle',    'label' => 'Confirmed'],
                            'payment_submitted' => ['cls' => 'bm-badge-payment',    'icon' => 'fa-money-check-alt', 'label' => 'Pmt Submitted'],
                            'pending'           => ['cls' => 'bm-badge-pending',    'icon' => 'fa-hourglass-half',  'label' => 'Pending'],
                            'completed'         => ['cls' => 'bm-badge-completed',  'icon' => 'fa-trophy',          'label' => 'Completed'],
                        ];
                        $bs_class = $bs_cfg[$bs]['cls']   ?? 'bm-badge-secondary';
                        $bs_icon  = $bs_cfg[$bs]['icon']  ?? 'fa-circle';
                        $bs_label = $bs_cfg[$bs]['label'] ?? ucfirst($bs);
                    ?>
                    <tr class="bm-row">
                        <!-- Booking # -->
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/bookings/view.php?id=<?php echo (int)$booking['id']; ?>"
                               class="bm-booking-num text-decoration-none" target="_blank">
                                <?php echo htmlspecialchars($booking['booking_number']); ?>
                                <i class="fas fa-external-link-alt fa-xs ms-1 text-muted"></i>
                            </a>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="bm-customer-name"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                            <?php if ($booking['customer_phone']): ?>
                                <div class="bm-row-sub"><i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($booking['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Hall -->
                        <td><?php echo htmlspecialchars($booking['hall_name']); ?></td>

                        <!-- Event Date -->
                        <td>
                            <div><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></div>
                            <div class="bm-row-sub"><?php echo convertToNepaliDate($booking['event_date']); ?></div>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="bm-badge <?php echo $bs_class; ?>">
                                <i class="fas <?php echo $bs_icon; ?> me-1"></i><?php echo $bs_label; ?>
                            </span>
                        </td>

                        <!-- Hall Price -->
                        <td class="text-end"><?php echo formatCurrency($booking['hall_price']); ?></td>

                        <!-- Menu Total -->
                        <td class="text-end"><?php echo formatCurrency($booking['menu_total']); ?></td>

                        <!-- Payable -->
                        <td class="text-end fw-semibold"><?php echo formatCurrency($booking['venue_payable']); ?></td>

                        <!-- Paid -->
                        <td class="text-end text-success"><?php echo formatCurrency($booking['venue_amount_paid']); ?></td>

                        <!-- Due -->
                        <td class="text-end <?php echo $is_paid ? 'text-success' : 'text-danger fw-semibold'; ?>">
                            <?php echo $is_paid ? formatCurrency(0) : formatCurrency($venue_due); ?>
                        </td>

                        <!-- Record Payment inline form -->
                        <td class="text-center" style="min-width:200px;">
                            <?php if ($is_paid): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Paid</span>
                            <?php else: ?>
                                <form method="post" action="detail.php?venue_id=<?php echo $venue_id; ?>"
                                      class="d-flex align-items-center gap-1 justify-content-center"
                                      onsubmit="return confirmPayment(this)">
                                    <input type="hidden" name="action"     value="record_venue_payment">
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                    <input type="number"
                                           name="venue_amount_paid"
                                           class="form-control form-control-sm"
                                           style="width:110px;"
                                           min="0"
                                           max="<?php echo number_format(floatval($booking['venue_payable']), 2, '.', ''); ?>"
                                           step="0.01"
                                           value="<?php echo number_format(floatval($booking['venue_amount_paid']), 2, '.', ''); ?>"
                                           required>
                                    <button type="submit" class="btn btn-sm btn-success" title="Save payment">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($bookings)): ?>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="7" class="text-end">Totals</td>
                    <td class="text-end"><?php echo formatCurrency($agg_payable); ?></td>
                    <td class="text-end text-success"><?php echo formatCurrency($agg_paid); ?></td>
                    <td class="text-end <?php echo $agg_due > 0.005 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($agg_due); ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
$extra_js = '
<script>
function confirmPayment(form) {
    var amount = parseFloat(form.venue_amount_paid.value);
    if (isNaN(amount) || amount < 0) {
        Swal.fire({ icon: "error", title: "Invalid Amount", text: "Please enter a valid amount.", confirmButtonColor: "#4CAF50" });
        return false;
    }
    return true;
}
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
