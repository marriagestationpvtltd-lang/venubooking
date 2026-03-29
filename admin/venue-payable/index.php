<?php
$page_title = 'Venue Provider Payable';

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

/* ── Filter Pills ────────────────────────────────────────────── */
.bm-filter-pills { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.bm-pill {
    display: inline-flex; align-items: center;
    padding: 0.3rem 0.85rem;
    border-radius: 99px;
    font-size: 0.78rem; font-weight: 600;
    text-decoration: none;
    color: #6b7280;
    background: #f3f4f6;
    border: 1.5px solid transparent;
    transition: all 0.2s ease;
}
.bm-pill:hover { background: #e5e7eb; color: #374151; }
.bm-pill-active    { background: #e8f5e9 !important; color: #2E7D32 !important; border-color: #a5d6a7 !important; }
.bm-pill-pending   { background: #fff3e0 !important; color: #e65100 !important; border-color: #ffcc80 !important; }
.bm-pill-payment   { background: #e3f2fd !important; color: #1565c0 !important; border-color: #90caf9 !important; }
.bm-pill-confirmed { background: #e8f5e9 !important; color: #1b5e20 !important; border-color: #a5d6a7 !important; }
.bm-pill-completed { background: #ede7f6 !important; color: #4527a0 !important; border-color: #ce93d8 !important; }
.bm-pill-cancelled { background: #fce4ec !important; color: #880e4f !important; border-color: #f48fb1 !important; }
.bm-pill-all       { background: #eceff1 !important; color: #37474f !important; border-color: #b0bec5 !important; }

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

/* Empty state */
.bm-empty-state { text-align: center; padding: 4rem 2rem !important; }
.bm-empty-icon  { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1rem; }
.bm-empty-title { font-size: 1.1rem; font-weight: 700; color: #374151; margin-bottom: 0.5rem; }
.bm-empty-sub   { font-size: 0.875rem; color: #9ca3af; }
.bm-empty-sub a { color: #4CAF50; font-weight: 600; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .bm-page-header   { flex-direction: column; align-items: flex-start; padding: 1.25rem; }
    .bm-stats-grid    { grid-template-columns: repeat(2, 1fr); }
    .bm-card-toolbar  { flex-direction: column; align-items: flex-start; }
    .bm-filter-pills  { gap: 0.25rem; }
    .bm-pill          { font-size: 0.72rem; padding: 0.25rem 0.7rem; }
    .bm-header-title  { font-size: 1.2rem; }
}
@media (max-width: 480px) {
    .bm-stats-grid  { grid-template-columns: 1fr 1fr; }
    .bm-stat-card   { padding: 0.9rem 1rem; }
    .bm-stat-value  { font-size: 1.2rem; }
}
@media (prefers-reduced-motion: no-preference) {
    .bm-btn-primary:hover { transform: translateY(-2px); }
    .bm-stat-card:hover   { transform: translateY(-3px); }
    .bm-stat-selected     { transform: translateY(-3px); }
}
</style>
';

require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Handle flash messages (set by detail.php after recording payment)
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Filter: 'all' | 'has_due' | 'fully_paid'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$allowed_filters = ['all', 'has_due', 'fully_paid'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

// ── Summary stats ──────────────────────────────────────────────────────────
try {
    $summary_stmt = $db->query(
        "SELECT
            COUNT(DISTINCT v.id)                                              AS total_venues,
            COALESCE(SUM(b.hall_price + b.menu_total), 0)                     AS grand_total_payable,
            COALESCE(SUM(COALESCE(b.venue_amount_paid, 0)), 0)                AS grand_total_paid,
            COALESCE(SUM(b.hall_price + b.menu_total)
                   - SUM(COALESCE(b.venue_amount_paid, 0)), 0)                AS grand_total_due,
            COUNT(DISTINCT CASE
                WHEN (b.hall_price + b.menu_total)
                   - COALESCE(b.venue_amount_paid, 0) > 0
                THEN v.id END)                                                AS venues_with_due
         FROM venues v
         JOIN halls    h ON h.venue_id = v.id
         JOIN bookings b ON b.hall_id  = h.id
        WHERE b.booking_status NOT IN ('cancelled')"
    );
    $summary = $summary_stmt->fetch();
} catch (PDOException $e) {
    error_log('Venue payable summary query failed: ' . $e->getMessage());
    $summary = [
        'total_venues'       => 0,
        'grand_total_payable'=> 0,
        'grand_total_paid'   => 0,
        'grand_total_due'    => 0,
        'venues_with_due'    => 0,
    ];
}

// ── Per-venue list ──────────────────────────────────────────────────────────
$having_clause = '';
if ($filter === 'has_due') {
    $having_clause = ' HAVING total_due > 0';
} elseif ($filter === 'fully_paid') {
    $having_clause = ' HAVING total_due <= 0';
}

try {
    $venues_stmt = $db->query(
        "SELECT
            v.id                                                               AS venue_id,
            v.name                                                             AS venue_name,
            v.contact_phone,
            v.contact_email,
            COUNT(b.id)                                                        AS total_bookings,
            COALESCE(SUM(b.hall_price + b.menu_total), 0)                      AS total_payable,
            COALESCE(SUM(COALESCE(b.venue_amount_paid, 0)), 0)                 AS total_paid,
            COALESCE(SUM(b.hall_price + b.menu_total), 0)
                - COALESCE(SUM(COALESCE(b.venue_amount_paid, 0)), 0)           AS total_due
         FROM venues v
         JOIN halls    h ON h.venue_id = v.id
         JOIN bookings b ON b.hall_id  = h.id
        WHERE b.booking_status NOT IN ('cancelled')
        GROUP BY v.id, v.name, v.contact_phone, v.contact_email"
        . $having_clause
        . " ORDER BY total_due DESC, v.name ASC"
    );
    $venues = $venues_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Venue payable list query failed: ' . $e->getMessage());
    $venues = [];
}

$filter_labels = [
    'all'        => 'All Venues',
    'has_due'    => 'Has Outstanding Due',
    'fully_paid' => 'Fully Paid',
];
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
            <h1 class="bm-header-title">Venue Provider Payable</h1>
            <p class="bm-header-subtitle">Track and manage outstanding payments owed to each venue provider</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SUMMARY STAT CARDS                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-stats-grid">
    <div class="bm-stat-card bm-stat-active">
        <div class="bm-stat-icon"><i class="fas fa-building"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$summary['total_venues']; ?></span>
            <span class="bm-stat-label">Total Venues</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-confirmed">
        <div class="bm-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['grand_total_payable']); ?></span>
            <span class="bm-stat-label">Total Payable</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-completed">
        <div class="bm-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['grand_total_paid']); ?></span>
            <span class="bm-stat-label">Total Paid</span>
        </div>
    </div>
    <a href="?filter=has_due" class="bm-stat-card bm-stat-pending <?php echo $filter === 'has_due' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['grand_total_due']); ?></span>
            <span class="bm-stat-label">Total Due &nbsp;<small>(<?php echo (int)$summary['venues_with_due']; ?> venues)</small></span>
        </div>
    </a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  VENUES TABLE CARD                                         -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-card">
    <div class="bm-card-toolbar">
        <div class="bm-toolbar-left">
            <h2 class="bm-card-title">
                <?php echo htmlspecialchars($filter_labels[$filter]); ?>
                <span class="bm-count-badge"><?php echo count($venues); ?></span>
            </h2>
        </div>
        <div class="bm-toolbar-right">
            <div class="bm-filter-pills">
                <a href="?filter=all"        class="bm-pill <?php echo $filter === 'all'        ? 'bm-pill-active'    : ''; ?>">All</a>
                <a href="?filter=has_due"    class="bm-pill <?php echo $filter === 'has_due'    ? 'bm-pill-pending'   : ''; ?>">Has Due</a>
                <a href="?filter=fully_paid" class="bm-pill <?php echo $filter === 'fully_paid' ? 'bm-pill-completed' : ''; ?>">Fully Paid</a>
            </div>
        </div>
    </div>

    <div class="bm-table-wrap">
        <table class="table datatable bm-table mb-0" id="venuePayableTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Venue</th>
                    <th>Contact</th>
                    <th class="text-end">Bookings</th>
                    <th class="text-end">Total Payable</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($venues)): ?>
                    <tr>
                        <td colspan="9" class="bm-empty-state">
                            <div class="bm-empty-icon"><i class="fas fa-building"></i></div>
                            <div class="bm-empty-title">No venues found</div>
                            <div class="bm-empty-sub">
                                <?php if ($filter !== 'all'): ?>
                                    No venues match the <strong><?php echo htmlspecialchars(strtolower($filter_labels[$filter])); ?></strong> filter.
                                    <a href="?filter=all">View all venues</a>
                                <?php else: ?>
                                    No venue bookings have been recorded yet.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($venues as $i => $venue):
                        $total_due     = floatval($venue['total_due']);
                        $total_payable = floatval($venue['total_payable']);
                        $total_paid    = floatval($venue['total_paid']);
                        $pay_pct       = $total_payable > 0 ? min(100, ($total_paid / $total_payable) * 100) : 100;
                        $is_fully_paid = $total_due <= 0.005;
                    ?>
                    <tr class="bm-row">
                        <td class="text-muted"><?php echo ($i + 1); ?></td>
                        <td>
                            <div class="bm-customer-name fw-semibold">
                                <a href="detail.php?venue_id=<?php echo (int)$venue['venue_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <?php if ($venue['contact_phone']): ?>
                                <div><i class="fas fa-phone fa-xs text-muted me-1"></i><?php echo htmlspecialchars($venue['contact_phone']); ?></div>
                            <?php endif; ?>
                            <?php if ($venue['contact_email']): ?>
                                <div><i class="fas fa-envelope fa-xs text-muted me-1"></i><?php echo htmlspecialchars($venue['contact_email']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo (int)$venue['total_bookings']; ?></td>
                        <td class="text-end fw-semibold"><?php echo formatCurrency($total_payable); ?></td>
                        <td class="text-end text-success"><?php echo formatCurrency($total_paid); ?></td>
                        <td class="text-end <?php echo $is_fully_paid ? 'text-success' : 'text-danger fw-semibold'; ?>">
                            <?php echo $is_fully_paid ? formatCurrency(0) : formatCurrency($total_due); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($is_fully_paid): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Paid</span>
                            <?php else: ?>
                                <div style="width:90px;margin:0 auto;">
                                    <div class="progress" style="height:8px;" title="<?php echo number_format($pay_pct, 1); ?>% paid">
                                        <div class="progress-bar bg-warning" style="width:<?php echo $pay_pct; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($pay_pct, 0); ?>% paid</small>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="detail.php?venue_id=<?php echo (int)$venue['venue_id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
