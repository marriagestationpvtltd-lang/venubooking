<?php
// Load core dependencies before any output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// ── Input validation ────────────────────────────────────────────────────────
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if ($vendor_id <= 0) {
    header('Location: index.php');
    exit;
}

// ── Load vendor info ─────────────────────────────────────────────────────────
$vendor = getVendor($vendor_id);
if (!$vendor) {
    header('Location: index.php');
    exit;
}

// ── Handle POST: record vendor payout for a specific assignment ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_vendor_payout') {
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    if ($assignment_id > 0) {
        try {
            // Fetch assignment to get assigned_amount cap
            $a_stmt = $db->prepare("SELECT id, assigned_amount FROM booking_vendor_assignments WHERE id = ? AND vendor_id = ?");
            $a_stmt->execute([$assignment_id, $vendor_id]);
            $assignment = $a_stmt->fetch();

            if ($assignment) {
                $max_payout  = floatval($assignment['assigned_amount']);
                $amount_paid = min(max(0.0, floatval($_POST['amount_paid'] ?? 0)), $max_payout);

                if (recordVendorPayout($assignment_id, $amount_paid)) {
                    logActivity($current_user['id'], 'Recorded vendor payout', 'booking_vendor_assignments', $assignment_id,
                        "Vendor paid: " . number_format($amount_paid, 2));
                    $_SESSION['flash_success'] = 'Vendor payment updated successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Failed to update vendor payment.';
                }
            } else {
                $_SESSION['flash_error'] = 'Assignment not found.';
            }
        } catch (Exception $e) {
            error_log('Vendor payable detail POST error: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'An error occurred while saving the payment.';
        }
    }
    header('Location: detail.php?vendor_id=' . $vendor_id);
    exit;
}

// ── Set page title and include header ────────────────────────────────────────
$page_title = 'Payable – ' . htmlspecialchars($vendor['name']);
require_once __DIR__ . '/../includes/header.php';

// ── Flash messages ──────────────────────────────────────────────────────────
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Load assignments for this vendor (ordered by event_date) ─────────────────
try {
    $assignments_stmt = $db->prepare(
        "SELECT
            bva.id                                                    AS assignment_id,
            bva.task_description,
            bva.assigned_amount,
            bva.amount_paid,
            bva.amount_paid                                           AS vendor_amount_paid,
            (bva.assigned_amount - bva.amount_paid)                  AS vendor_due,
            bva.status,
            bva.notes,
            b.id                                                      AS booking_id,
            b.booking_number,
            b.event_date,
            b.booking_status,
            b.event_type,
            c.full_name                                               AS customer_name,
            c.phone                                                    AS customer_phone,
            COALESCE(h.name, b.custom_hall_name)                      AS hall_name,
            COALESCE(ve.name, b.custom_venue_name)                    AS venue_name
         FROM booking_vendor_assignments bva
         JOIN bookings b  ON bva.booking_id = b.id
         LEFT JOIN customers c  ON b.customer_id = c.id
         LEFT JOIN halls h      ON b.hall_id = h.id
         LEFT JOIN venues ve    ON h.venue_id = ve.id
        WHERE bva.vendor_id = ?
          AND bva.status != 'cancelled'
        ORDER BY b.event_date DESC, bva.id DESC"
    );
    $assignments_stmt->execute([$vendor_id]);
    $assignments = $assignments_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Vendor payable detail: assignments query failed: ' . $e->getMessage());
    $assignments = [];
}

// ── Aggregate totals ─────────────────────────────────────────────────────────
$agg_assigned = 0;
$agg_paid     = 0;
foreach ($assignments as $a) {
    $agg_assigned += floatval($a['assigned_amount']);
    $agg_paid     += floatval($a['vendor_amount_paid']);
}
$agg_due = max(0.0, $agg_assigned - $agg_paid);
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
            <h1 class="bm-header-title"><?php echo htmlspecialchars($vendor['name']); ?></h1>
            <p class="bm-header-subtitle">
                <i class="fas fa-hand-holding-usd me-1"></i>Vendor Payable
                <?php if (!empty($vendor['type'])): ?>
                    &nbsp;·&nbsp;<i class="fas fa-tag me-1"></i><?php echo htmlspecialchars(getVendorTypeLabel($vendor['type'])); ?>
                <?php endif; ?>
                <?php if (!empty($vendor['phone'])): ?>
                    &nbsp;·&nbsp;<i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($vendor['phone']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="bm-header-actions">
        <a href="index.php" class="bm-btn bm-btn-outline">
            <i class="fas fa-arrow-left me-1"></i>Back to All Vendors
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
            <span class="bm-stat-value"><?php echo count($assignments); ?></span>
            <span class="bm-stat-label">Assignments</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-confirmed">
        <div class="bm-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($agg_assigned); ?></span>
            <span class="bm-stat-label">Total Assigned</span>
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
<!--  ASSIGNMENTS TABLE                                         -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-card">
    <div class="bm-card-toolbar">
        <div class="bm-toolbar-left">
            <h2 class="bm-card-title">
                Assignments for <?php echo htmlspecialchars($vendor['name']); ?>
                <span class="bm-count-badge"><?php echo count($assignments); ?></span>
            </h2>
        </div>
    </div>

    <div class="bm-table-wrap">
        <table class="table datatable bm-table mb-0" id="vendorDetailTable">
            <thead>
                <tr>
                    <th>Booking #</th>
                    <th>Customer</th>
                    <th>Venue / Hall</th>
                    <th>Event Date</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th class="text-end">Assigned</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th class="text-center">Record Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="10" class="bm-empty-state">
                            <div class="bm-empty-icon"><i class="fas fa-calendar-times"></i></div>
                            <div class="bm-empty-title">No assignments found</div>
                            <div class="bm-empty-sub">There are no active assignments for this vendor.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment):
                        $vendor_due = floatval($assignment['vendor_due']);
                        $is_paid    = $vendor_due <= 0.005;

                        // Booking status badge config
                        $bs     = $assignment['booking_status'];
                        $bs_cfg = [
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
                            <a href="<?php echo BASE_URL; ?>/admin/bookings/view.php?id=<?php echo (int)$assignment['booking_id']; ?>"
                               class="bm-booking-num text-decoration-none" target="_blank">
                                <?php echo htmlspecialchars($assignment['booking_number']); ?>
                                <i class="fas fa-external-link-alt fa-xs ms-1 text-muted"></i>
                            </a>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="bm-customer-name"><?php echo htmlspecialchars($assignment['customer_name'] ?? '—'); ?></div>
                            <?php if (!empty($assignment['customer_phone'])): ?>
                                <div class="bm-row-sub"><i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($assignment['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Venue / Hall -->
                        <td>
                            <?php echo htmlspecialchars($assignment['venue_name'] ?? '—'); ?>
                            <?php if (!empty($assignment['hall_name'])): ?>
                                <span class="text-muted"> / <?php echo htmlspecialchars($assignment['hall_name']); ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Event Date -->
                        <td>
                            <div><?php echo !empty($assignment['event_date']) ? date('M d, Y', strtotime($assignment['event_date'])) : '—'; ?></div>
                            <?php if (!empty($assignment['event_date'])): ?>
                                <div class="bm-row-sub"><?php echo convertToNepaliDate($assignment['event_date']); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Task -->
                        <td>
                            <?php echo htmlspecialchars($assignment['task_description']); ?>
                            <?php if (!empty($assignment['notes'])): ?>
                                <i class="fas fa-info-circle text-muted ms-1"
                                   data-bs-toggle="tooltip"
                                   title="<?php echo htmlspecialchars($assignment['notes']); ?>"></i>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="bm-badge <?php echo $bs_class; ?>">
                                <i class="fas <?php echo $bs_icon; ?> me-1"></i><?php echo $bs_label; ?>
                            </span>
                        </td>

                        <!-- Assigned -->
                        <td class="text-end fw-semibold"><?php echo formatCurrency($assignment['assigned_amount']); ?></td>

                        <!-- Paid -->
                        <td class="text-end text-success"><?php echo formatCurrency($assignment['vendor_amount_paid']); ?></td>

                        <!-- Due -->
                        <td class="text-end <?php echo $is_paid ? 'text-success' : 'text-danger fw-semibold'; ?>">
                            <?php echo $is_paid ? formatCurrency(0) : formatCurrency($vendor_due); ?>
                        </td>

                        <!-- Record Payment inline form -->
                        <td class="text-center" style="min-width:200px;">
                            <?php if ($is_paid): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Paid</span>
                            <?php else: ?>
                                <form method="post" action="detail.php?vendor_id=<?php echo $vendor_id; ?>"
                                      class="d-flex align-items-center gap-1 justify-content-center"
                                      onsubmit="return confirmPayment(this)">
                                    <input type="hidden" name="action"        value="record_vendor_payout">
                                    <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment['assignment_id']; ?>">
                                    <input type="number"
                                           name="amount_paid"
                                           class="form-control form-control-sm"
                                           style="width:110px;"
                                           min="0"
                                           max="<?php echo number_format(floatval($assignment['assigned_amount']), 2, '.', ''); ?>"
                                           step="0.01"
                                           value="<?php echo number_format(floatval($assignment['vendor_amount_paid']), 2, '.', ''); ?>"
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
            <?php if (!empty($assignments)): ?>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="6" class="text-end">Totals</td>
                    <td class="text-end"><?php echo formatCurrency($agg_assigned); ?></td>
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
    var amount = parseFloat(form.amount_paid.value);
    if (isNaN(amount) || amount < 0) {
        Swal.fire({ icon: "error", title: "Invalid Amount", text: "Please enter a valid amount.", confirmButtonColor: "#4CAF50" });
        return false;
    }
    return true;
}
// Enable Bootstrap tooltips
document.querySelectorAll(\'[data-bs-toggle="tooltip"]\').forEach(function(el) {
    new bootstrap.Tooltip(el);
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
