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
    $venue_stmt = $db->prepare("SELECT id, name, contact_phone, contact_email, location, address, bank_details, qr_code FROM venues WHERE id = ?");
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

$extra_css = '
<style>
/* ── Alerts ──────────────────────────────────────────────────── */
.bm-alert { border-radius: 12px; border: none; font-size: 0.9rem; }

/* ── Page Header ─────────────────────────────────────────────── */
.bm-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    background: linear-gradient(135deg, #1a252f 0%, #2c3e50 60%, #34495e 100%);
    border-radius: 16px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 32px rgba(44,62,80,0.25);
    position: relative;
    overflow: hidden;
}
.bm-page-header::before {
    content: \'\';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(76,175,80,0.12);
    pointer-events: none;
}
.bm-page-header::after {
    content: \'\';
    position: absolute;
    bottom: -30px; left: 160px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(76,175,80,0.08);
    pointer-events: none;
}
.bm-header-content { display: flex; align-items: center; gap: 1.25rem; }
.bm-header-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #fff;
    box-shadow: 0 4px 14px rgba(76,175,80,0.4);
    flex-shrink: 0;
}
.bm-header-title {
    margin: 0; font-size: 1.5rem; font-weight: 700; color: #fff;
    letter-spacing: -0.3px;
}
.bm-header-subtitle {
    margin: 0; font-size: 0.85rem; color: rgba(255,255,255,0.65);
}
.bm-header-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.bm-btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.55rem 1.2rem; border-radius: 10px;
    font-size: 0.875rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s ease;
    cursor: pointer; border: none; white-space: nowrap;
}
.bm-btn-primary {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    color: #fff;
    box-shadow: 0 4px 14px rgba(76,175,80,0.4);
}
.bm-btn-primary:hover { box-shadow: 0 6px 20px rgba(76,175,80,0.55); color: #fff; }
.bm-btn-outline {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.9);
    border: 1px solid rgba(255,255,255,0.25);
    backdrop-filter: blur(4px);
}
.bm-btn-outline:hover { background: rgba(255,255,255,0.18); color: #fff; }

/* ── Stat Cards ──────────────────────────────────────────────── */
.bm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.bm-stat-card {
    display: flex; align-items: center; gap: 1rem;
    background: #fff;
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    text-decoration: none;
    border: 2px solid transparent;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.25s ease;
    position: relative; overflow: hidden;
}
.bm-stat-card::before {
    content: \'\';
    position: absolute; inset: 0;
    opacity: 0; transition: opacity 0.25s;
}
.bm-stat-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
.bm-stat-card:hover::before { opacity: 1; }
.bm-stat-selected {
    border-color: currentColor !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.14) !important;
}
.bm-stat-icon {
    width: 46px; height: 46px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.bm-stat-body { display: flex; flex-direction: column; }
.bm-stat-value {
    font-size: 1.5rem; font-weight: 800; line-height: 1.1; color: #1a252f;
}
.bm-stat-value-sm { font-size: 1rem; }
.bm-stat-label { font-size: 0.75rem; color: #6b7280; font-weight: 500; margin-top: 2px; }

/* stat card colour variants */
.bm-stat-active   .bm-stat-icon { background: #e8f5e9; color: #2E7D32; }
.bm-stat-active   { color: #2E7D32; }
.bm-stat-pending  .bm-stat-icon { background: #fff3e0; color: #e65100; }
.bm-stat-pending  { color: #e65100; }
.bm-stat-payment  .bm-stat-icon { background: #e3f2fd; color: #1565c0; }
.bm-stat-payment  { color: #1565c0; }
.bm-stat-confirmed .bm-stat-icon { background: #e8f5e9; color: #1b5e20; }
.bm-stat-confirmed { color: #1b5e20; }
.bm-stat-completed .bm-stat-icon { background: #ede7f6; color: #4527a0; }
.bm-stat-completed { color: #4527a0; }
.bm-stat-revenue  .bm-stat-icon { background: #fce4ec; color: #880e4f; }
.bm-stat-revenue  { color: #880e4f; }
.bm-stat-selected .bm-stat-value { color: inherit; }

/* ── Main Card ───────────────────────────────────────────────── */
.bm-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    overflow: hidden;
    border: 1px solid #f0f2f5;
    margin-bottom: 1.5rem;
}

/* ── Card Toolbar ────────────────────────────────────────────── */
.bm-card-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #f0f2f5;
    background: #fafbfc;
}
.bm-toolbar-left  { display: flex; align-items: center; gap: 0.75rem; }
.bm-toolbar-right { display: flex; align-items: center; gap: 0.75rem; }
.bm-card-title {
    font-size: 1rem; font-weight: 700; color: #1a252f; margin: 0;
    display: flex; align-items: center; gap: 0.6rem;
}
.bm-count-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 26px; height: 22px; padding: 0 8px;
    background: #e8f5e9; color: #2E7D32;
    border-radius: 99px; font-size: 0.75rem; font-weight: 700;
}

/* ── Table ───────────────────────────────────────────────────── */
.bm-table-wrap { overflow-x: auto; }
.bm-table { border-collapse: separate; border-spacing: 0; width: 100%; }
.bm-table thead tr th {
    padding: 0.85rem 1rem;
    background: #f8f9fb;
    border-bottom: 2px solid #e9ecef;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6b7280;
    white-space: nowrap;
}
.bm-table tfoot tr td {
    padding: 0.85rem 1rem;
    background: #f8f9fb;
    border-top: 2px solid #e9ecef;
    font-size: 0.875rem;
}
.bm-table tbody tr { transition: background 0.15s ease; }
.bm-row td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
    font-size: 0.875rem;
}
.bm-row:last-child td { border-bottom: none; }
.bm-row:hover td { background: #f8fffe; }

/* row sub-text */
.bm-row-sub { font-size: 0.76rem; color: #9ca3af; margin-top: 3px; }

/* Booking number */
.bm-booking-num {
    font-weight: 700; font-size: 0.875rem; color: #2c6bed;
    font-family: \'Courier New\', monospace;
    letter-spacing: 0.3px;
}

/* Customer name */
.bm-customer-name { font-weight: 600; color: #1f2937; font-size: 0.875rem; }

/* Status badges */
.bm-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 99px;
    font-size: 0.72rem; font-weight: 700;
    white-space: nowrap;
}
.bm-badge-confirmed  { background: #dcfce7; color: #15803d; }
.bm-badge-payment    { background: #dbeafe; color: #1d4ed8; }
.bm-badge-pending    { background: #fef9c3; color: #92400e; }
.bm-badge-cancelled  { background: #fee2e2; color: #b91c1c; }
.bm-badge-completed  { background: #ede9fe; color: #6d28d9; }
.bm-badge-secondary  { background: #f3f4f6; color: #6b7280; }

/* ── Inline payment form ─────────────────────────────────────── */
.bm-col-payment { min-width: 210px; }
.bm-pay-form { display: flex; align-items: center; gap: 0.4rem; justify-content: center; }
.bm-pay-form .form-control-sm {
    border-radius: 8px; border: 1.5px solid #e5e7eb;
    font-size: 0.83rem; padding: 0.3rem 0.5rem;
    transition: border-color 0.2s;
}
.bm-pay-form .form-control-sm:focus { border-color: #4CAF50; box-shadow: 0 0 0 3px rgba(76,175,80,0.15); }
.bm-pay-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    color: #fff; border: none; cursor: pointer;
    font-size: 0.82rem;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(76,175,80,0.35);
}
.bm-pay-btn:hover { box-shadow: 0 4px 14px rgba(76,175,80,0.5); }

/* Empty state */
.bm-empty-state { text-align: center; padding: 4rem 2rem !important; }
.bm-empty-icon  { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1rem; }
.bm-empty-title { font-size: 1.1rem; font-weight: 700; color: #374151; margin-bottom: 0.5rem; }
.bm-empty-sub   { font-size: 0.875rem; color: #9ca3af; }
.bm-empty-sub a { color: #4CAF50; font-weight: 600; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .bm-page-header  { flex-direction: column; align-items: flex-start; padding: 1.25rem; }
    .bm-header-actions { width: 100%; justify-content: flex-start; }
    .bm-stats-grid   { grid-template-columns: repeat(2, 1fr); }
    .bm-card-toolbar { flex-direction: column; align-items: flex-start; }
    .bm-header-title { font-size: 1.2rem; }
}
@media (max-width: 480px) {
    .bm-stats-grid { grid-template-columns: 1fr 1fr; }
    .bm-stat-card  { padding: 0.9rem 1rem; }
    .bm-stat-value { font-size: 1.2rem; }
}
@media (prefers-reduced-motion: no-preference) {
    .bm-btn-outline:hover { transform: translateY(-1px); }
    .bm-stat-card:hover   { transform: translateY(-3px); }
    .bm-pay-btn:hover     { transform: scale(1.08); }
}

/* ── Payment Details (QR + Bank) ──────────────────────────────── */
.bm-payment-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    border: 1px solid #f0f2f5;
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.bm-payment-card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #f0f7ff 0%, #e8f5e9 100%);
    border-bottom: 1px solid #e9ecef;
    display: flex; align-items: center; gap: 0.6rem;
    font-size: 1rem; font-weight: 700; color: #1a252f;
}
.bm-payment-body {
    display: flex; align-items: flex-start; gap: 2rem;
    padding: 1.5rem;
    flex-wrap: wrap;
}
.bm-qr-wrap {
    position: relative;
    flex-shrink: 0;
    cursor: zoom-in;
}
.bm-qr-img {
    width: 180px; height: 180px;
    object-fit: contain;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 8px;
    background: #fff;
    display: block;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    transform-origin: top left;
}
.bm-qr-wrap:hover .bm-qr-img {
    transform: scale(2.0);
    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    z-index: 10;
    position: relative;
}
.bm-qr-hint {
    font-size: 0.72rem; color: #9ca3af; text-align: center; margin-top: 4px;
}
.bm-bank-details {
    flex: 1; min-width: 200px;
}
.bm-bank-details pre {
    white-space: pre-wrap; word-break: break-word;
    font-size: 0.88rem; color: #374151;
    background: #f8f9fb; border-radius: 8px;
    padding: 0.85rem 1rem; margin: 0;
    border: 1px solid #e9ecef;
    font-family: \'Courier New\', monospace;
}
.bm-due-highlight {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #fff3e0; color: #e65100;
    border-radius: 10px; padding: 0.6rem 1.1rem;
    font-size: 1.05rem; font-weight: 700;
    border: 1.5px solid #ffcc80;
    margin-bottom: 0.75rem;
}
.bm-payment-no-details {
    flex-direction: column;
}
.bm-payment-no-details .alert {
    border-radius: 10px;
    width: 100%;
}
</style>
';

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

<?php if (!empty($venue['bank_details']) || (!empty($venue['qr_code']) && validateUploadedFilePath($venue['qr_code']))): ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PAYMENT DETAILS (QR CODE + BANK DETAILS)                  -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-payment-card">
    <div class="bm-payment-card-header">
        <i class="fas fa-university"></i> Payment Details
    </div>
    <div class="bm-payment-body">
        <?php if (!empty($venue['qr_code']) && validateUploadedFilePath($venue['qr_code'])): ?>
        <div>
            <div class="bm-qr-wrap" title="Hover to zoom in for scanning">
                <img src="<?php echo htmlspecialchars(UPLOAD_URL . $venue['qr_code']); ?>"
                     alt="Payment QR Code"
                     class="bm-qr-img">
            </div>
            <p class="bm-qr-hint"><i class="fas fa-search-plus me-1"></i>Hover to zoom</p>
        </div>
        <?php endif; ?>
        <div class="bm-bank-details">
            <?php if ($agg_due > 0.005): ?>
                <div class="bm-due-highlight">
                    <i class="fas fa-exclamation-circle"></i>
                    Due Amount: <?php echo formatCurrency($agg_due); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($venue['bank_details'])): ?>
                <pre><?php echo htmlspecialchars($venue['bank_details']); ?></pre>
            <?php endif; ?>
            <?php if (empty($venue['bank_details']) && !empty($venue['qr_code']) && validateUploadedFilePath($venue['qr_code'])): ?>
                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>Scan the QR code to make payment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!--  NO PAYMENT DETAILS NOTICE                                  -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-payment-card">
    <div class="bm-payment-card-header">
        <i class="fas fa-university"></i> Payment Details
    </div>
    <div class="bm-payment-body bm-payment-no-details">
        <div class="alert alert-warning mb-0 w-100" style="border-radius:10px;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No bank details or QR code have been set for this venue provider.
            <a href="<?php echo BASE_URL; ?>/admin/venues/edit.php?id=<?php echo $venue_id; ?>"
               class="alert-link ms-1">
                <i class="fas fa-edit me-1"></i>Add payment details
            </a>
            so they can be displayed here for reference when making payments.
        </div>
    </div>
</div>
<?php endif; ?>

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
                        <td class="text-center bm-col-payment">
                            <?php if ($is_paid): ?>
                                <span class="bm-badge bm-badge-confirmed"><i class="fas fa-check-circle"></i>Paid</span>
                            <?php else: ?>
                                <form method="post" action="detail.php?venue_id=<?php echo $venue_id; ?>"
                                      class="bm-pay-form"
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
                                    <button type="submit" class="bm-pay-btn" title="Save payment">
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
