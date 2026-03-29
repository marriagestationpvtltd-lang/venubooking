<?php
$page_title = 'Financial Reports';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

// ── 1. Overall financial summary ──────────────────────────────────────────────
$overall = $db->query("
    SELECT
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN grand_total   ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN hall_price + menu_total ELSE 0 END), 0) AS total_venue_cost,
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN services_total ELSE 0 END), 0) AS total_services_revenue,
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN tax_amount    ELSE 0 END), 0) AS total_tax,
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN advance_amount_received ELSE 0 END), 0) AS total_advance_received,
        COALESCE(SUM(CASE WHEN booking_status != 'cancelled' THEN COALESCE(venue_amount_paid, 0) ELSE 0 END), 0) AS total_venue_paid,
        COUNT(CASE WHEN booking_status != 'cancelled' THEN 1 END) AS active_bookings,
        COUNT(CASE WHEN booking_status = 'cancelled'  THEN 1 END) AS cancelled_bookings,
        COUNT(*) AS total_bookings
    FROM bookings
")->fetch();

// ── 2. Payments collected (verified) ─────────────────────────────────────────
try {
    $payments_summary = $db->query("
        SELECT
            COALESCE(SUM(CASE WHEN p.payment_status = 'verified' THEN p.paid_amount ELSE 0 END), 0) AS total_collected,
            COALESCE(SUM(CASE WHEN p.payment_status = 'pending'  THEN p.paid_amount ELSE 0 END), 0) AS total_pending_verification,
            COUNT(CASE WHEN p.payment_status = 'verified' THEN 1 END) AS verified_transactions,
            COUNT(CASE WHEN p.payment_status = 'pending'  THEN 1 END) AS pending_transactions
        FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE b.booking_status != 'cancelled'
    ")->fetch();
} catch (PDOException $e) {
    $payments_summary = ['total_collected' => 0, 'total_pending_verification' => 0, 'verified_transactions' => 0, 'pending_transactions' => 0];
}

// ── 3. Vendor payable summary ─────────────────────────────────────────────────
try {
    $vendor_summary = $db->query("
        SELECT
            COALESCE(SUM(bva.assigned_amount), 0)                           AS total_assigned,
            COALESCE(SUM(bva.amount_paid), 0)                               AS total_paid,
            COALESCE(SUM(bva.assigned_amount - bva.amount_paid), 0)         AS total_due,
            COUNT(DISTINCT bva.vendor_id)                                   AS total_vendors
        FROM booking_vendor_assignments bva
        INNER JOIN bookings b ON bva.booking_id = b.id
        WHERE bva.status != 'cancelled' AND b.booking_status != 'cancelled'
    ")->fetch();
} catch (PDOException $e) {
    $vendor_summary = ['total_assigned' => 0, 'total_paid' => 0, 'total_due' => 0, 'total_vendors' => 0];
}

// ── 4. Derived totals ─────────────────────────────────────────────────────────
$total_venue_due    = max(0, (float)$overall['total_venue_cost'] - (float)$overall['total_venue_paid']);
$total_payout       = (float)$overall['total_venue_cost'] + (float)$vendor_summary['total_assigned'];
$net_profit         = (float)$overall['total_revenue'] - $total_payout;
$total_customer_due = max(0, (float)$overall['total_revenue'] - (float)$payments_summary['total_collected']);

// ── 5. Revenue by booking status ──────────────────────────────────────────────
$status_revenue = $db->query("
    SELECT
        booking_status,
        COUNT(*)                              AS bookings,
        COALESCE(SUM(grand_total), 0)         AS revenue,
        COALESCE(SUM(hall_price), 0)          AS hall_revenue,
        COALESCE(SUM(menu_total), 0)          AS menu_revenue,
        COALESCE(SUM(services_total), 0)      AS services_revenue,
        COALESCE(SUM(tax_amount), 0)          AS tax_revenue
    FROM bookings
    GROUP BY booking_status
    ORDER BY FIELD(booking_status,'confirmed','completed','pending','payment_submitted','cancelled')
")->fetchAll();

// Active hall/menu split (for venue payout badges)
$active_hall_total = 0.0;
$active_menu_total = 0.0;
foreach ($status_revenue as $sr) {
    if ($sr['booking_status'] !== 'cancelled') {
        $active_hall_total += (float)$sr['hall_revenue'];
        $active_menu_total += (float)$sr['menu_revenue'];
    }
}

// ── 6. Monthly revenue – last 12 months ───────────────────────────────────────
$monthly_data = $db->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m')     AS month,
        DATE_FORMAT(created_at, '%b %Y')     AS label,
        COALESCE(SUM(grand_total), 0)        AS revenue,
        COALESCE(SUM(hall_price), 0)         AS hall_revenue,
        COALESCE(SUM(menu_total), 0)         AS menu_revenue,
        COALESCE(SUM(services_total), 0)     AS services_revenue,
        COUNT(*)                             AS bookings
    FROM bookings
    WHERE booking_status != 'cancelled'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month, label
    ORDER BY month ASC
")->fetchAll();

// ── 7. Revenue by payment status ─────────────────────────────────────────────
$payment_status_data = $db->query("
    SELECT
        payment_status,
        COUNT(*)                              AS bookings,
        COALESCE(SUM(grand_total), 0)         AS total_value,
        COALESCE(SUM(advance_amount_received),0) AS advance_received
    FROM bookings
    WHERE booking_status != 'cancelled'
    GROUP BY payment_status
    ORDER BY FIELD(payment_status,'paid','partial','pending','cancelled')
")->fetchAll();

// ── 8. Top services by revenue ────────────────────────────────────────────────
try {
    $services_revenue = $db->query("
        SELECT
            COALESCE(NULLIF(bs.category,''), 'Uncategorized') AS category,
            COUNT(*)                                           AS count,
            COALESCE(SUM(bs.price * bs.quantity), 0)          AS total
        FROM booking_services bs
        INNER JOIN bookings b ON bs.booking_id = b.id
        WHERE b.booking_status != 'cancelled'
        GROUP BY category
        ORDER BY total DESC
        LIMIT 10
    ")->fetchAll();
} catch (PDOException $e) {
    $services_revenue = [];
}

// ── 9. This month vs last month ───────────────────────────────────────────────
$this_month = $db->query("
    SELECT COALESCE(SUM(grand_total),0) AS revenue, COUNT(*) AS bookings
    FROM bookings
    WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())
      AND booking_status != 'cancelled'
")->fetch();
$last_month = $db->query("
    SELECT COALESCE(SUM(grand_total),0) AS revenue, COUNT(*) AS bookings
    FROM bookings
    WHERE YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH) AND MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH)
      AND booking_status != 'cancelled'
")->fetch();

function reportTrend($current, $previous) {
    if ($previous === 0.0 || $previous === 0) return $current > 0 ? ['pct' => 100, 'dir' => 'up'] : ['pct' => 0, 'dir' => 'flat'];
    $pct = round((($current - $previous) / $previous) * 100, 1);
    return ['pct' => abs($pct), 'dir' => $pct >= 0 ? 'up' : 'down'];
}
$rev_trend = reportTrend($this_month['revenue'], $last_month['revenue']);

$status_colors = [
    'pending'           => ['bg' => '#f59e0b', 'light' => 'rgba(245,158,11,.12)', 'icon' => 'fa-clock',        'label' => 'Pending'],
    'payment_submitted' => ['bg' => '#3b82f6', 'light' => 'rgba(59,130,246,.12)',  'icon' => 'fa-paper-plane',  'label' => 'Payment Submitted'],
    'confirmed'         => ['bg' => '#6366f1', 'light' => 'rgba(99,102,241,.12)',  'icon' => 'fa-check-circle', 'label' => 'Confirmed'],
    'completed'         => ['bg' => '#10b981', 'light' => 'rgba(16,185,129,.12)',  'icon' => 'fa-flag-checkered','label' => 'Completed'],
    'cancelled'         => ['bg' => '#ef4444', 'light' => 'rgba(239,68,68,.12)',   'icon' => 'fa-times-circle', 'label' => 'Cancelled'],
];
$pay_status_colors = [
    'paid'    => ['bg' => '#10b981', 'label' => 'Fully Paid'],
    'partial' => ['bg' => '#f59e0b', 'label' => 'Partial'],
    'pending' => ['bg' => '#ef4444', 'label' => 'Pending'],
];

$extra_css = '
<style>
:root {
    --rp-green:  #10b981;
    --rp-blue:   #4f46e5;
    --rp-amber:  #f59e0b;
    --rp-red:    #ef4444;
    --rp-purple: #8b5cf6;
    --rp-cyan:   #06b6d4;
    --rp-teal:   #14b8a6;
    --rp-slate:  #64748b;
    --rp-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    --rp-radius: 16px;
}
.rp-page-header {
    background: linear-gradient(135deg, #1a252f 0%, #2c3e50 60%, #34495e 100%);
    border-radius: var(--rp-radius);
    padding: 1.75rem 2rem;
    margin-bottom: 1.75rem;
    position: relative; overflow: hidden;
    box-shadow: 0 8px 32px rgba(44,62,80,.25);
}
.rp-page-header::before {
    content:""; position:absolute; top:-40px; right:-40px;
    width:180px; height:180px; border-radius:50%;
    background:rgba(76,175,80,.12); pointer-events:none;
}
.rp-page-header h3 { color:#fff; font-weight:700; margin:0; }
.rp-page-header p  { color:rgba(255,255,255,.65); margin:.3rem 0 0; font-size:.9rem; }
.rp-section-title {
    font-size:.7rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.1em; color:var(--rp-slate);
    margin:.5rem 0 .75rem; padding-left:.1rem;
}
/* KPI cards */
.rp-kpi {
    background:#fff; border-radius:var(--rp-radius);
    box-shadow:var(--rp-shadow); padding:1.4rem 1.5rem;
    border:1px solid rgba(0,0,0,.04); position:relative; overflow:hidden;
    transition:transform .2s, box-shadow .2s;
}
.rp-kpi:hover { transform:translateY(-3px); box-shadow:0 8px 30px rgba(0,0,0,.13); }
.rp-kpi::before {
    content:""; position:absolute; top:0; left:0; right:0;
    height:4px; border-radius:var(--rp-radius) var(--rp-radius) 0 0;
}
.rp-kpi.k-green::before  { background:linear-gradient(90deg,var(--rp-green),#34d399); }
.rp-kpi.k-blue::before   { background:linear-gradient(90deg,var(--rp-blue),#6366f1); }
.rp-kpi.k-amber::before  { background:linear-gradient(90deg,var(--rp-amber),#fbbf24); }
.rp-kpi.k-red::before    { background:linear-gradient(90deg,var(--rp-red),#f87171); }
.rp-kpi.k-purple::before { background:linear-gradient(90deg,var(--rp-purple),#a78bfa); }
.rp-kpi.k-cyan::before   { background:linear-gradient(90deg,var(--rp-cyan),#22d3ee); }
.rp-kpi.k-teal::before   { background:linear-gradient(90deg,var(--rp-teal),#2dd4bf); }
.rp-kpi.k-slate::before  { background:linear-gradient(90deg,var(--rp-slate),#94a3b8); }
.rp-kpi-icon {
    width:48px; height:48px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; flex-shrink:0;
}
.rp-kpi-val  { font-size:1.7rem; font-weight:700; color:#1e293b; line-height:1.1; margin:0; }
.rp-kpi-lbl  { font-size:.78rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin:.25rem 0 0; }
.rp-kpi-sub  { font-size:.75rem; margin-top:.4rem; color:#64748b; }
.rp-kpi-sub .up   { color:var(--rp-green); }
.rp-kpi-sub .down { color:var(--rp-red); }
/* Chart card */
.rp-card {
    background:#fff; border-radius:var(--rp-radius);
    box-shadow:var(--rp-shadow); border:1px solid rgba(0,0,0,.04); overflow:hidden;
}
.rp-card-header {
    padding:1.1rem 1.5rem; border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:.5rem;
}
.rp-card-title {
    font-size:.95rem; font-weight:700; color:#1e293b; margin:0;
    display:flex; align-items:center; gap:.5rem;
}
.rp-card-body { padding:1.25rem 1.5rem; }
/* Progress bar row */
.rp-prog-row { margin-bottom:.85rem; }
.rp-prog-label { display:flex; justify-content:space-between; font-size:.82rem; margin-bottom:.3rem; }
.rp-prog-label span:first-child { font-weight:600; color:#334155; }
.rp-prog-label span:last-child  { color:#64748b; }
/* Status pill */
.rp-pill {
    display:inline-block; padding:.18rem .6rem;
    border-radius:20px; font-size:.72rem; font-weight:700;
}
/* Divider badge for summary row */
.rp-divider-badge {
    display:inline-flex; align-items:center; gap:.4rem;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
    padding:.4rem .9rem; font-size:.82rem; color:#475569;
}
/* Mobile improvements */
@media (max-width:575.98px) {
    .rp-page-header { padding:1.1rem 1.2rem; }
    .rp-kpi { padding:1rem 1.1rem; }
    .rp-kpi-val { font-size:1.35rem; }
    .rp-card-body { padding:.9rem 1rem; }
    .rp-card-header { padding:.9rem 1rem; }
}
@media (max-width:767.98px) {
    .rp-card .table-responsive { overflow-x: auto; }
    .rp-card .table-responsive table { min-width: 480px; }
}
</style>
';
?>

<!-- Page Header -->
<div class="rp-page-header mb-4">
    <div class="d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;background:linear-gradient(135deg,#4CAF50,#2E7D32);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 4px 14px rgba(76,175,80,.4);flex-shrink:0">
            <i class="fas fa-chart-pie"></i>
        </div>
        <div>
            <h3 class="mb-0">Financial Reports</h3>
            <p class="mb-0">Consolidated overview of all financial transactions across the platform</p>
        </div>
    </div>
</div>

<!-- ── Row 1: Top KPIs ─────────────────────────────────────────────────────── -->
<p class="rp-section-title"><i class="fas fa-layer-group me-1"></i> Overall Financial Summary</p>
<div class="row g-3 mb-4">
    <!-- Total Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-green">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(16,185,129,.12);color:var(--rp-green)"><i class="fas fa-coins"></i></div>
                <div>
                    <p class="rp-kpi-val"><?php echo formatCurrency($overall['total_revenue']); ?></p>
                    <p class="rp-kpi-lbl">Total Revenue</p>
                    <p class="rp-kpi-sub">
                        <?php if ($rev_trend['dir'] === 'up'): ?><span class="up"><i class="fas fa-arrow-up"></i> <?php echo $rev_trend['pct']; ?>%</span><?php elseif ($rev_trend['dir'] === 'down'): ?><span class="down"><i class="fas fa-arrow-down"></i> <?php echo $rev_trend['pct']; ?>%</span><?php else: ?><span>—</span><?php endif; ?>
                        vs last month
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Payments Collected -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-blue">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(79,70,229,.12);color:var(--rp-blue)"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <p class="rp-kpi-val"><?php echo formatCurrency($payments_summary['total_collected']); ?></p>
                    <p class="rp-kpi-lbl">Payments Collected</p>
                    <p class="rp-kpi-sub"><?php echo $payments_summary['verified_transactions']; ?> verified transactions</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Customer Due -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-red">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(239,68,68,.12);color:var(--rp-red)"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <p class="rp-kpi-val" style="color:var(--rp-red)"><?php echo formatCurrency($total_customer_due); ?></p>
                    <p class="rp-kpi-lbl">Customer Due</p>
                    <p class="rp-kpi-sub">Advance: <?php echo formatCurrency($overall['total_advance_received']); ?></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Net Profit -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi <?php echo $net_profit >= 0 ? 'k-green' : 'k-red'; ?>">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:<?php echo $net_profit >= 0 ? 'rgba(16,185,129,.12)' : 'rgba(239,68,68,.12)'; ?>;color:<?php echo $net_profit >= 0 ? 'var(--rp-green)' : 'var(--rp-red)'; ?>"><i class="fas fa-chart-line"></i></div>
                <div>
                    <p class="rp-kpi-val" style="color:<?php echo $net_profit >= 0 ? 'var(--rp-green)' : 'var(--rp-red)'; ?>"><?php echo formatCurrency(abs($net_profit)); ?></p>
                    <p class="rp-kpi-lbl">Net <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?></p>
                    <p class="rp-kpi-sub">Revenue − Total Payout</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 2: Payout KPIs ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Venue Cost -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-slate">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(100,116,139,.12);color:var(--rp-slate)"><i class="fas fa-building"></i></div>
                <div>
                    <p class="rp-kpi-val"><?php echo formatCurrency($overall['total_venue_cost']); ?></p>
                    <p class="rp-kpi-lbl">Total Venue Cost</p>
                    <p class="rp-kpi-sub">Hall + Catering</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Venue Due -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-red">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(239,68,68,.12);color:var(--rp-red)"><i class="fas fa-university"></i></div>
                <div>
                    <p class="rp-kpi-val" style="color:var(--rp-red)"><?php echo formatCurrency($total_venue_due); ?></p>
                    <p class="rp-kpi-lbl">Venue Payable</p>
                    <p class="rp-kpi-sub">Paid: <?php echo formatCurrency($overall['total_venue_paid']); ?></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Vendor Assigned -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-cyan">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(6,182,212,.12);color:var(--rp-cyan)"><i class="fas fa-users-cog"></i></div>
                <div>
                    <p class="rp-kpi-val"><?php echo formatCurrency($vendor_summary['total_assigned']); ?></p>
                    <p class="rp-kpi-lbl">Vendor Assigned</p>
                    <p class="rp-kpi-sub"><?php echo (int)$vendor_summary['total_vendors']; ?> vendors</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Vendor Due -->
    <div class="col-sm-6 col-xl-3">
        <div class="rp-kpi k-red">
            <div class="d-flex align-items-start gap-3">
                <div class="rp-kpi-icon" style="background:rgba(239,68,68,.12);color:var(--rp-red)"><i class="fas fa-exclamation-circle"></i></div>
                <div>
                    <p class="rp-kpi-val" style="color:var(--rp-red)"><?php echo formatCurrency($vendor_summary['total_due']); ?></p>
                    <p class="rp-kpi-lbl">Vendor Payable</p>
                    <p class="rp-kpi-sub">Paid: <?php echo formatCurrency($vendor_summary['total_paid']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 3: Monthly Revenue Chart + Breakdown ──────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="rp-card h-100">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-chart-area" style="color:var(--rp-blue)"></i> Monthly Revenue (Last 12 Months)</h6>
            </div>
            <div class="rp-card-body">
                <canvas id="revenueChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rp-card h-100">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-chart-pie" style="color:var(--rp-purple)"></i> Booking Status Breakdown</h6>
            </div>
            <div class="rp-card-body">
                <canvas id="statusChart" height="170"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 4: Revenue Breakdown + Payment Flow ───────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Revenue by booking status table -->
    <div class="col-lg-7">
        <div class="rp-card">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-table" style="color:var(--rp-green)"></i> Revenue by Booking Status</h6>
            </div>
            <div class="rp-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Status</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-end">Hall</th>
                                <th class="text-end">Catering</th>
                                <th class="text-end">Services</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status_revenue as $row):
                                $sc = $status_colors[$row['booking_status']] ?? ['bg' => '#94a3b8', 'icon' => 'fa-circle', 'label' => ucfirst($row['booking_status'])];
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="rp-pill" style="background:<?php echo $sc['bg']; ?>22;color:<?php echo $sc['bg']; ?>">
                                        <i class="fas <?php echo $sc['icon']; ?> me-1"></i><?php echo $sc['label']; ?>
                                    </span>
                                </td>
                                <td class="text-center"><?php echo (int)$row['bookings']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['hall_revenue']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['menu_revenue']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['services_revenue']); ?></td>
                                <td class="text-end pe-3 fw-bold"><?php echo formatCurrency($row['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th class="ps-3">All Active</th>
                                <th class="text-center"><?php echo (int)$overall['active_bookings']; ?></th>
                                <th class="text-end"><?php echo formatCurrency($active_hall_total); ?></th>
                                <th class="text-end"><?php echo formatCurrency($active_menu_total); ?></th>
                                <th class="text-end"><?php echo formatCurrency($overall['total_services_revenue']); ?></th>
                                <th class="text-end pe-3"><?php echo formatCurrency($overall['total_revenue']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment flow summary -->
    <div class="col-lg-5">
        <div class="rp-card h-100">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-money-bill-wave" style="color:var(--rp-amber)"></i> Payment Collection Flow</h6>
            </div>
            <div class="rp-card-body">
                <?php
                $grand = max(1, (float)$overall['total_revenue']);
                $adv_pct = min(100, round((float)$overall['total_advance_received'] / $grand * 100, 1));
                $col_pct = min(100, round((float)$payments_summary['total_collected'] / $grand * 100, 1));
                $due_pct = max(0, 100 - $col_pct);
                ?>
                <div class="rp-prog-row">
                    <div class="rp-prog-label"><span>Grand Total Billed</span><span><?php echo formatCurrency($overall['total_revenue']); ?></span></div>
                    <div class="progress" style="height:8px;border-radius:6px"><div class="progress-bar bg-secondary" style="width:100%"></div></div>
                </div>
                <div class="rp-prog-row">
                    <div class="rp-prog-label"><span><i class="fas fa-circle me-1" style="color:var(--rp-green);font-size:.55rem"></i>Payments Verified</span><span><?php echo formatCurrency($payments_summary['total_collected']); ?> (<?php echo $col_pct; ?>%)</span></div>
                    <div class="progress" style="height:8px;border-radius:6px"><div class="progress-bar" style="width:<?php echo $col_pct; ?>%;background:var(--rp-green)"></div></div>
                </div>
                <div class="rp-prog-row">
                    <div class="rp-prog-label"><span><i class="fas fa-circle me-1" style="color:var(--rp-blue);font-size:.55rem"></i>Advance Received</span><span><?php echo formatCurrency($overall['total_advance_received']); ?> (<?php echo $adv_pct; ?>%)</span></div>
                    <div class="progress" style="height:8px;border-radius:6px"><div class="progress-bar" style="width:<?php echo $adv_pct; ?>%;background:var(--rp-blue)"></div></div>
                </div>
                <div class="rp-prog-row">
                    <div class="rp-prog-label"><span><i class="fas fa-circle me-1" style="color:var(--rp-amber);font-size:.55rem"></i>Pending Verification</span><span><?php echo formatCurrency($payments_summary['total_pending_verification']); ?></span></div>
                    <div class="progress" style="height:8px;border-radius:6px">
                        <?php $pv_pct = min(100, round((float)$payments_summary['total_pending_verification'] / $grand * 100, 1)); ?>
                        <div class="progress-bar" style="width:<?php echo $pv_pct; ?>%;background:var(--rp-amber)"></div>
                    </div>
                </div>
                <div class="rp-prog-row">
                    <div class="rp-prog-label"><span><i class="fas fa-circle me-1" style="color:var(--rp-red);font-size:.55rem"></i>Still Due</span><span><?php echo formatCurrency($total_customer_due); ?> (<?php echo $due_pct; ?>%)</span></div>
                    <div class="progress" style="height:8px;border-radius:6px"><div class="progress-bar" style="width:<?php echo $due_pct; ?>%;background:var(--rp-red)"></div></div>
                </div>

                <hr class="my-3">
                <p class="rp-section-title mb-2">By Payment Status</p>
                <?php foreach ($payment_status_data as $row):
                    $psc = $pay_status_colors[$row['payment_status']] ?? ['bg' => '#94a3b8', 'label' => ucfirst($row['payment_status'])];
                    $ppct = min(100, round((float)$row['total_value'] / $grand * 100, 1));
                ?>
                <div class="d-flex align-items-center justify-content-between mb-2" style="font-size:.82rem">
                    <div>
                        <span class="rp-pill" style="background:<?php echo $psc['bg']; ?>22;color:<?php echo $psc['bg']; ?>"><?php echo $psc['label']; ?></span>
                        <span class="text-muted ms-1"><?php echo (int)$row['bookings']; ?> bookings</span>
                    </div>
                    <strong><?php echo formatCurrency($row['total_value']); ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 5: Payout breakdown ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Venue payout -->
    <div class="col-md-6">
        <div class="rp-card">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-hotel" style="color:var(--rp-purple)"></i> Venue Provider Payable</h6>
                <a href="<?php echo BASE_URL; ?>/admin/venue-payable/index.php" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">Details <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="rp-card-body">
                <?php $v_pct = $overall['total_venue_cost'] > 0 ? min(100, round($overall['total_venue_paid'] / $overall['total_venue_cost'] * 100, 1)) : 0; ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:#1e293b"><?php echo formatCurrency($overall['total_venue_cost']); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Total Venue Cost</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--rp-green)"><?php echo formatCurrency($overall['total_venue_paid']); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Paid Out</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--rp-red)"><?php echo formatCurrency($total_venue_due); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Still Due</div>
                    </div>
                </div>
                <div class="rp-prog-label"><span>Payment Progress</span><span><?php echo $v_pct; ?>% paid</span></div>
                <div class="progress" style="height:10px;border-radius:6px">
                    <div class="progress-bar" style="width:<?php echo $v_pct; ?>%;background:linear-gradient(90deg,var(--rp-green),#34d399)"></div>
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <span class="rp-divider-badge"><i class="fas fa-utensils me-1"></i>Catering: <?php echo formatCurrency($active_menu_total); ?></span>
                    <span class="rp-divider-badge"><i class="fas fa-door-open me-1"></i>Hall: <?php echo formatCurrency($active_hall_total); ?></span>
                </div>
            </div>
        </div>
    </div>
    <!-- Vendor payout -->
    <div class="col-md-6">
        <div class="rp-card">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-user-tie" style="color:var(--rp-cyan)"></i> Vendor Payable</h6>
                <a href="<?php echo BASE_URL; ?>/admin/vendor-payable/index.php" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">Details <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="rp-card-body">
                <?php $vnd_pct = $vendor_summary['total_assigned'] > 0 ? min(100, round($vendor_summary['total_paid'] / $vendor_summary['total_assigned'] * 100, 1)) : 0; ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:#1e293b"><?php echo formatCurrency($vendor_summary['total_assigned']); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Total Assigned</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--rp-green)"><?php echo formatCurrency($vendor_summary['total_paid']); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Paid Out</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--rp-red)"><?php echo formatCurrency($vendor_summary['total_due']); ?></div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em">Still Due</div>
                    </div>
                </div>
                <div class="rp-prog-label"><span>Payment Progress</span><span><?php echo $vnd_pct; ?>% paid</span></div>
                <div class="progress" style="height:10px;border-radius:6px">
                    <div class="progress-bar" style="width:<?php echo $vnd_pct; ?>%;background:linear-gradient(90deg,var(--rp-cyan),#22d3ee)"></div>
                </div>
                <div class="mt-3">
                    <span class="rp-divider-badge"><i class="fas fa-users me-1"></i><?php echo (int)$vendor_summary['total_vendors']; ?> active vendors assigned</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 6: Services Revenue + Monthly Table ───────────────────────────── -->
<div class="row g-3 mb-4">
    <?php if (!empty($services_revenue)): ?>
    <div class="col-lg-5">
        <div class="rp-card">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-concierge-bell" style="color:var(--rp-teal)"></i> Services Revenue by Category</h6>
            </div>
            <div class="rp-card-body">
                <?php
                $services_max = !empty($services_revenue) ? (float)max(array_column($services_revenue, 'total')) : 0;
                if ($services_max <= 0) $services_max = 1;
                foreach ($services_revenue as $svc):
                    $spct = round($svc['total'] / $services_max * 100, 1);
                    $cat_label = ucwords(str_replace(['_', '-'], ' ', $svc['category']));
                ?>
                <div class="rp-prog-row">
                    <div class="rp-prog-label">
                        <span><?php echo htmlspecialchars($cat_label); ?> <small class="text-muted">(<?php echo (int)$svc['count']; ?>)</small></span>
                        <span><?php echo formatCurrency($svc['total']); ?></span>
                    </div>
                    <div class="progress" style="height:7px;border-radius:6px">
                        <div class="progress-bar" style="width:<?php echo $spct; ?>%;background:linear-gradient(90deg,var(--rp-teal),#2dd4bf)"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo !empty($services_revenue) ? 'col-lg-7' : 'col-12'; ?>">
        <div class="rp-card">
            <div class="rp-card-header">
                <h6 class="rp-card-title"><i class="fas fa-calendar-alt" style="color:var(--rp-blue)"></i> Monthly Revenue Detail</h6>
            </div>
            <div class="rp-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Month</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-end">Hall</th>
                                <th class="text-end">Catering</th>
                                <th class="text-end">Services</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($monthly_data) as $row): ?>
                            <tr>
                                <td class="ps-3">
                                    <?php echo htmlspecialchars($row['label']); ?>
                                    <small class="text-muted d-block"><?php echo convertToNepaliDate($row['month'] . '-01'); ?></small>
                                </td>
                                <td class="text-center"><?php echo (int)$row['bookings']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['hall_revenue']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['menu_revenue']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['services_revenue']); ?></td>
                                <td class="text-end pe-3 fw-bold"><?php echo formatCurrency($row['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($monthly_data)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chart_labels    = json_encode(array_column($monthly_data, 'label'));
$chart_revenue   = json_encode(array_map('floatval', array_column($monthly_data, 'revenue')));
$chart_hall      = json_encode(array_map('floatval', array_column($monthly_data, 'hall_revenue')));
$chart_menu      = json_encode(array_map('floatval', array_column($monthly_data, 'menu_revenue')));
$chart_services  = json_encode(array_map('floatval', array_column($monthly_data, 'services_revenue')));
$chart_bookings  = json_encode(array_map('intval',   array_column($monthly_data, 'bookings')));

$status_chart_labels = [];
$status_chart_counts = [];
$status_chart_colors = [];
foreach ($status_revenue as $r) {
    $sc = $status_colors[$r['booking_status']] ?? ['label' => ucfirst($r['booking_status']), 'bg' => '#94a3b8'];
    $status_chart_labels[] = $sc['label'];
    $status_chart_counts[] = (int)$r['bookings'];
    $status_chart_colors[] = $sc['bg'];
}
$status_chart_labels = json_encode($status_chart_labels);
$status_chart_counts = json_encode($status_chart_counts);
$status_chart_colors = json_encode($status_chart_colors);

$extra_js = '
<script>
// ── Monthly Revenue Chart ──────────────────────────────────────────────────
(function(){
    const ctx = document.getElementById("revenueChart").getContext("2d");
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ' . $chart_labels . ',
            datasets: [
                {
                    label: "Hall",
                    data: ' . $chart_hall . ',
                    backgroundColor: "rgba(79,70,229,0.75)",
                    borderRadius: 4,
                    stack: "revenue"
                },
                {
                    label: "Catering",
                    data: ' . $chart_menu . ',
                    backgroundColor: "rgba(16,185,129,0.75)",
                    borderRadius: 4,
                    stack: "revenue"
                },
                {
                    label: "Services",
                    data: ' . $chart_services . ',
                    backgroundColor: "rgba(245,158,11,0.75)",
                    borderRadius: 4,
                    stack: "revenue"
                },
                {
                    label: "Bookings",
                    data: ' . $chart_bookings . ',
                    type: "line",
                    borderColor: "#ef4444",
                    backgroundColor: "rgba(239,68,68,0.1)",
                    tension: 0.4,
                    yAxisID: "y2",
                    pointBackgroundColor: "#ef4444",
                    pointRadius: 4,
                    fill: false,
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: "index", intersect: false },
            plugins: {
                legend: { position: "top", labels: { boxWidth: 12, font: { size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.label === "Bookings") return " Bookings: " + ctx.parsed.y;
                            return " " + ctx.dataset.label + ": Rs. " + ctx.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    stacked: true,
                    grid: { color: "rgba(0,0,0,.05)" },
                    ticks: { callback: v => "Rs. " + (v >= 1000 ? (v/1000).toFixed(0) + "k" : v) }
                },
                y2: {
                    position: "right",
                    grid: { display: false },
                    ticks: { stepSize: 1, callback: v => v + " bk" }
                }
            }
        }
    });
})();

// ── Booking Status Doughnut Chart ─────────────────────────────────────────
(function(){
    const ctx2 = document.getElementById("statusChart").getContext("2d");
    new Chart(ctx2, {
        type: "doughnut",
        data: {
            labels: ' . $status_chart_labels . ',
            datasets: [{
                data: ' . $status_chart_counts . ',
                backgroundColor: ' . $status_chart_colors . ',
                borderWidth: 2,
                borderColor: "#fff"
            }]
        },
        options: {
            responsive: true,
            cutout: "65%",
            plugins: {
                legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });
})();
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
