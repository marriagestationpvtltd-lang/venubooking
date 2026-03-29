<?php
$page_title = 'Vendor Payable';
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
            COUNT(DISTINCT v.id)                                                     AS total_vendors,
            COALESCE(SUM(bva.assigned_amount), 0)                                    AS grand_total_assigned,
            COALESCE(SUM(bva.amount_paid), 0)                                        AS grand_total_paid,
            COALESCE(SUM(bva.assigned_amount - bva.amount_paid), 0)                  AS grand_total_due,
            COUNT(DISTINCT CASE
                WHEN (bva.assigned_amount - bva.amount_paid) > 0
                THEN v.id END)                                                        AS vendors_with_due
         FROM vendors v
         JOIN booking_vendor_assignments bva ON bva.vendor_id = v.id
        WHERE bva.status != 'cancelled'"
    );
    $summary = $summary_stmt->fetch();
} catch (PDOException $e) {
    error_log('Vendor payable summary query failed: ' . $e->getMessage());
    $summary = [
        'total_vendors'       => 0,
        'grand_total_assigned'=> 0,
        'grand_total_paid'    => 0,
        'grand_total_due'     => 0,
        'vendors_with_due'    => 0,
    ];
}

// ── Per-vendor list ──────────────────────────────────────────────────────────
$having_clause = '';
if ($filter === 'has_due') {
    $having_clause = ' HAVING total_due > 0';
} elseif ($filter === 'fully_paid') {
    $having_clause = ' HAVING total_due <= 0';
}

try {
    $vendors_stmt = $db->query(
        "SELECT
            v.id                                                               AS vendor_id,
            v.name                                                             AS vendor_name,
            v.type                                                             AS vendor_type,
            v.phone                                                            AS vendor_phone,
            v.email                                                            AS vendor_email,
            COUNT(bva.id)                                                      AS total_assignments,
            COALESCE(SUM(bva.assigned_amount), 0)                              AS total_assigned,
            COALESCE(SUM(bva.amount_paid), 0)                                  AS total_paid,
            COALESCE(SUM(bva.assigned_amount - bva.amount_paid), 0)            AS total_due
         FROM vendors v
         JOIN booking_vendor_assignments bva ON bva.vendor_id = v.id
        WHERE bva.status != 'cancelled'
        GROUP BY v.id, v.name, v.type, v.phone, v.email"
        . $having_clause
        . " ORDER BY total_due DESC, v.name ASC"
    );
    $vendors = $vendors_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Vendor payable list query failed: ' . $e->getMessage());
    $vendors = [];
}

$filter_labels = [
    'all'        => 'All Vendors',
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
            <h1 class="bm-header-title">Vendor Payable</h1>
            <p class="bm-header-subtitle">सर्भिस प्रोभाइडरलाई दिनुपर्ने रकम — Track and manage outstanding payments owed to each service provider</p>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SUMMARY STAT CARDS                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-stats-grid">
    <div class="bm-stat-card bm-stat-active">
        <div class="bm-stat-icon"><i class="fas fa-user-tie"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$summary['total_vendors']; ?></span>
            <span class="bm-stat-label">Total Vendors</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-confirmed">
        <div class="bm-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['grand_total_assigned']); ?></span>
            <span class="bm-stat-label">Total Assigned</span>
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
            <span class="bm-stat-label">Total Due &nbsp;<small>(<?php echo (int)$summary['vendors_with_due']; ?> vendors)</small></span>
        </div>
    </a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  VENDORS TABLE CARD                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-card">
    <div class="bm-card-toolbar">
        <div class="bm-toolbar-left">
            <h2 class="bm-card-title">
                <?php echo htmlspecialchars($filter_labels[$filter]); ?>
                <span class="bm-count-badge"><?php echo count($vendors); ?></span>
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
        <table class="table datatable bm-table mb-0" id="vendorPayableTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vendor</th>
                    <th>Type</th>
                    <th>Contact</th>
                    <th class="text-end">Assignments</th>
                    <th class="text-end">Total Assigned</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendors)): ?>
                    <tr>
                        <td colspan="10" class="bm-empty-state">
                            <div class="bm-empty-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="bm-empty-title">No vendors found</div>
                            <div class="bm-empty-sub">
                                <?php if ($filter !== 'all'): ?>
                                    No vendors match the <strong><?php echo htmlspecialchars(strtolower($filter_labels[$filter])); ?></strong> filter.
                                    <a href="?filter=all">View all vendors</a>
                                <?php else: ?>
                                    No vendor assignments have been recorded yet.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendors as $i => $vendor):
                        $total_due      = floatval($vendor['total_due']);
                        $total_assigned = floatval($vendor['total_assigned']);
                        $total_paid     = floatval($vendor['total_paid']);
                        $pay_pct        = $total_assigned > 0 ? min(100, ($total_paid / $total_assigned) * 100) : 100;
                        $is_fully_paid  = $total_due <= 0.005;
                    ?>
                    <tr class="bm-row">
                        <td class="text-muted"><?php echo ($i + 1); ?></td>
                        <td>
                            <div class="bm-customer-name fw-semibold">
                                <a href="detail.php?vendor_id=<?php echo (int)$vendor['vendor_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <?php if ($vendor['vendor_type']): ?>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(getVendorTypeLabel($vendor['vendor_type'])); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vendor['vendor_phone']): ?>
                                <div><i class="fas fa-phone fa-xs text-muted me-1"></i><?php echo htmlspecialchars($vendor['vendor_phone']); ?></div>
                            <?php endif; ?>
                            <?php if ($vendor['vendor_email']): ?>
                                <div><i class="fas fa-envelope fa-xs text-muted me-1"></i><?php echo htmlspecialchars($vendor['vendor_email']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo (int)$vendor['total_assignments']; ?></td>
                        <td class="text-end fw-semibold"><?php echo formatCurrency($total_assigned); ?></td>
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
                            <a href="detail.php?vendor_id=<?php echo (int)$vendor['vendor_id']; ?>"
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
