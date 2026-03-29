<?php
$page_title = 'Venue Provider Payable';
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
