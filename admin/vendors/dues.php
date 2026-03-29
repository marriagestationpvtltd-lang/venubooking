<?php
$page_title = 'Vendor Dues / Payables';

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
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
    border-radius: 16px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 32px rgba(15,52,96,0.28);
    position: relative;
    overflow: hidden;
}
.bm-page-header::before {
    content: \'\';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,160,0,0.10);
    pointer-events: none;
}
.bm-page-header::after {
    content: \'\';
    position: absolute;
    bottom: -30px; left: 160px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,160,0,0.07);
    pointer-events: none;
}
.bm-header-content { display: flex; align-items: center; gap: 1.25rem; }
.bm-header-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #FFA000, #E65100);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #fff;
    box-shadow: 0 4px 14px rgba(255,160,0,0.45);
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
.bm-stat-total   .bm-stat-icon { background: #e3f2fd; color: #1565c0; }
.bm-stat-total   { color: #1565c0; }
.bm-stat-vendors .bm-stat-icon { background: #e8f5e9; color: #2E7D32; }
.bm-stat-vendors { color: #2E7D32; }
.bm-stat-paid    .bm-stat-icon { background: #ede7f6; color: #4527a0; }
.bm-stat-paid    { color: #4527a0; }
.bm-stat-due     .bm-stat-icon { background: #fff3e0; color: #e65100; }
.bm-stat-due     { color: #e65100; }
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
    background: #fff3e0; color: #e65100;
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
.bm-pill-all        { background: #eceff1 !important; color: #37474f !important; border-color: #b0bec5 !important; }
.bm-pill-outstanding{ background: #fff3e0 !important; color: #e65100 !important; border-color: #ffcc80 !important; }
.bm-pill-cleared    { background: #e8f5e9 !important; color: #2E7D32 !important; border-color: #a5d6a7 !important; }

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
    font-size: 0.82rem;
    font-weight: 700;
    color: #1a252f;
}
.bm-table tbody tr { transition: background 0.15s ease; }
.bm-row td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
    font-size: 0.875rem;
}
.bm-row:last-child td { border-bottom: none; }
.bm-row:hover td { background: #fffbf5; }

/* Vendor name */
.bm-vendor-name { font-weight: 600; color: #1f2937; font-size: 0.875rem; }
.bm-vendor-name a { text-decoration: none; color: inherit; }
.bm-vendor-name a:hover { color: #FFA000; }

/* row sub-text */
.bm-row-sub { font-size: 0.76rem; color: #9ca3af; margin-top: 3px; }

/* Status badges */
.bm-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 99px;
    font-size: 0.72rem; font-weight: 700;
    white-space: nowrap;
}
.bm-badge-due     { background: #fff3e0; color: #e65100; }
.bm-badge-cleared { background: #dcfce7; color: #15803d; }
.bm-badge-manual  { background: #f3f4f6; color: #6b7280; }
.bm-badge-type    { background: #e3f2fd; color: #1565c0; }

/* Reusable colour helpers */
.bm-text-paid { color: #4527a0; }
.bm-text-due  { color: #e65100; }

/* Tfoot label column */
.bm-tfoot-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
}

/* Empty state */
.bm-empty-state { text-align: center; padding: 4rem 2rem !important; }
.bm-empty-icon  { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1rem; }
.bm-empty-title { font-size: 1.1rem; font-weight: 700; color: #374151; margin-bottom: 0.5rem; }
.bm-empty-sub   { font-size: 0.875rem; color: #9ca3af; }
.bm-empty-sub a { color: #FFA000; font-weight: 600; }

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
    .bm-stat-card:hover { transform: translateY(-3px); }
    .bm-stat-selected   { transform: translateY(-3px); }
}
</style>
';

require_once __DIR__ . '/../includes/header.php';

$data    = getAllVendorDues();
$summary = $data['summary'];
$vendors = $data['vendors'];

// Filter: 'all' | 'outstanding' | 'cleared'
$filter          = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$allowed_filters = ['all', 'outstanding', 'cleared'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

if ($filter === 'outstanding') {
    $vendors = array_filter($vendors, fn($v) => (float)$v['total_due'] > 0.005);
} elseif ($filter === 'cleared') {
    $vendors = array_filter($vendors, fn($v) => (float)$v['total_due'] <= 0.005);
}

// Count vendors with outstanding balance for stat card
$vendors_with_due = count(array_filter($data['vendors'], fn($v) => (float)$v['total_due'] > 0.005));

$filter_labels = [
    'all'         => 'All Vendor Payables',
    'outstanding' => 'Vendors with Outstanding Balance',
    'cleared'     => 'Fully Cleared Vendors',
];
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PAGE HEADER                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-page-header">
    <div class="bm-header-content">
        <div class="bm-header-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div>
            <h1 class="bm-header-title">Vendor Dues / Payables</h1>
            <p class="bm-header-subtitle">सर्भिस प्रोभाइडरलाई दिन बाँकी रकम — Track outstanding payments owed to each vendor</p>
        </div>
    </div>
    <div class="bm-header-actions">
        <a href="index.php" class="bm-btn bm-btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Vendors
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SUMMARY STAT CARDS                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-stats-grid">
    <div class="bm-stat-card bm-stat-vendors">
        <div class="bm-stat-icon"><i class="fas fa-user-tie"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo count($data['vendors']); ?></span>
            <span class="bm-stat-label">Total Vendors</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-total">
        <div class="bm-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['total_assigned']); ?></span>
            <span class="bm-stat-label">कुल दिने रकम (Total Assigned)</span>
        </div>
    </div>
    <div class="bm-stat-card bm-stat-paid">
        <div class="bm-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['total_paid']); ?></span>
            <span class="bm-stat-label">भुक्तानी गरिएको (Total Paid)</span>
        </div>
    </div>
    <a href="?filter=outstanding" class="bm-stat-card bm-stat-due <?php echo $filter === 'outstanding' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($summary['total_due']); ?></span>
            <span class="bm-stat-label">दिन बाँकी (Due) &nbsp;<small>(<?php echo $vendors_with_due; ?> vendors)</small></span>
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
                <i class="fas fa-user-tie" style="color:#FFA000;"></i>
                <?php echo htmlspecialchars($filter_labels[$filter]); ?>
                <span class="bm-count-badge"><?php echo count($vendors); ?></span>
            </h2>
        </div>
        <div class="bm-toolbar-right">
            <div class="bm-filter-pills">
                <a href="?filter=all"         class="bm-pill <?php echo $filter === 'all'         ? 'bm-pill-all'         : ''; ?>">All</a>
                <a href="?filter=outstanding" class="bm-pill <?php echo $filter === 'outstanding' ? 'bm-pill-outstanding'  : ''; ?>">Outstanding</a>
                <a href="?filter=cleared"     class="bm-pill <?php echo $filter === 'cleared'     ? 'bm-pill-cleared'     : ''; ?>">Cleared</a>
            </div>
        </div>
    </div>

    <div class="bm-table-wrap">
        <table class="table datatable bm-table mb-0" id="vendorDuesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vendor</th>
                    <th>Type</th>
                    <th class="text-center">Assignments</th>
                    <th class="text-end">Total Assigned</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due (Remaining)</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendors)): ?>
                    <tr>
                        <td colspan="9" class="bm-empty-state">
                            <div class="bm-empty-icon">
                                <?php echo $filter === 'outstanding' ? '<i class="fas fa-check-circle" style="color:#a5d6a7;"></i>' : '<i class="fas fa-user-tie"></i>'; ?>
                            </div>
                            <div class="bm-empty-title">
                                <?php if ($filter === 'outstanding'): ?>
                                    सबै भुक्तानी भइसकेको छ!
                                <?php elseif ($filter === 'cleared'): ?>
                                    No fully cleared vendors yet
                                <?php else: ?>
                                    No vendor assignments found
                                <?php endif; ?>
                            </div>
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
                    <?php foreach (array_values($vendors) as $i => $v):
                        $total_assigned = (float)$v['total_assigned'];
                        $total_paid     = (float)$v['total_paid'];
                        $total_due      = (float)$v['total_due'];
                        $pay_pct        = $total_assigned > 0 ? min(100, ($total_paid / $total_assigned) * 100) : 100;
                        $is_cleared     = $total_due <= 0.005;
                        $is_manual      = empty($v['vendor_id']);
                    ?>
                    <tr class="bm-row">
                        <td class="text-muted"><?php echo ($i + 1); ?></td>
                        <td>
                            <div class="bm-vendor-name">
                                <?php if (!$is_manual): ?>
                                    <a href="view.php?id=<?php echo (int)$v['vendor_id']; ?>">
                                        <?php echo htmlspecialchars($v['vendor_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($v['vendor_name']); ?>
                                    <span class="bm-badge bm-badge-manual ms-1">Manual</span>
                                <?php endif; ?>
                            </div>
                            <?php
                                $phone = !$is_manual
                                    ? ($v['vendor_phone'] ?? '')
                                    : ($v['manual_vendor_phone'] ?? '');
                            ?>
                            <?php if (!empty($phone)): ?>
                                <div class="bm-row-sub"><i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($phone); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($v['vendor_type'])): ?>
                                <span class="bm-badge bm-badge-type"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $v['vendor_type']))); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-semibold"><?php echo (int)$v['assignment_count']; ?></span>
                        </td>
                        <td class="text-end fw-semibold"><?php echo formatCurrency($total_assigned); ?></td>
                        <td class="text-end bm-text-paid"><?php echo $total_paid > 0 ? formatCurrency($total_paid) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-end fw-semibold <?php echo $is_cleared ? 'text-success' : 'bm-text-due'; ?>">
                            <?php echo $is_cleared ? formatCurrency(0) : formatCurrency($total_due); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($is_cleared): ?>
                                <span class="bm-badge bm-badge-cleared"><i class="fas fa-check-circle"></i> Cleared</span>
                            <?php else: ?>
                                <div style="width:90px;margin:0 auto;">
                                    <div class="progress" style="height:8px;" title="<?php echo number_format($pay_pct, 1); ?>% paid">
                                        <div class="progress-bar" style="width:<?php echo $pay_pct; ?>%;background:linear-gradient(90deg,#FFA000,#E65100);"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($pay_pct, 0); ?>% paid</small>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$is_manual): ?>
                                <a href="view.php?id=<?php echo (int)$v['vendor_id']; ?>#assignments"
                                   class="btn btn-sm btn-warning" title="Manage Vendor &amp; Record Payment">
                                    <i class="fas fa-hand-holding-usd me-1"></i>Manage
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($vendors)): ?>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end bm-tfoot-label">Totals</td>
                    <td class="text-end"><?php echo formatCurrency($summary['total_assigned']); ?></td>
                    <td class="text-end bm-text-paid"><?php echo formatCurrency($summary['total_paid']); ?></td>
                    <td class="text-end bm-text-due"><?php echo formatCurrency($summary['total_due']); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
