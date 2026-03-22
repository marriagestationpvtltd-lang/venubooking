<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$current_user = getCurrentUser();

$db = getDB();

// ─── Statistics ───────────────────────────────────────────────────────────────

$stats = [];

// Total bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = (int)$stmt->fetch()['count'];

// Last month bookings (for trend)
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings
                    WHERE YEAR(created_at)  = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                      AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)");
$stats['last_month_bookings'] = (int)$stmt->fetch()['count'];

// This month bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings
                    WHERE YEAR(created_at)  = YEAR(CURRENT_DATE())
                      AND MONTH(created_at) = MONTH(CURRENT_DATE())");
$stats['this_month_bookings'] = (int)$stmt->fetch()['count'];

// Pending bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'");
$stats['pending_bookings'] = (int)$stmt->fetch()['count'];

// Confirmed bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'confirmed'");
$stats['confirmed_bookings'] = (int)$stmt->fetch()['count'];

// Completed bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'completed'");
$stats['completed_bookings'] = (int)$stmt->fetch()['count'];

// Cancelled bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'cancelled'");
$stats['cancelled_bookings'] = (int)$stmt->fetch()['count'];

// Total revenue (all non-cancelled)
$stmt = $db->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM bookings WHERE booking_status != 'cancelled'");
$stats['total_revenue'] = (float)$stmt->fetch()['total'];

// This month revenue
$stmt = $db->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM bookings
                    WHERE YEAR(created_at)  = YEAR(CURRENT_DATE())
                      AND MONTH(created_at) = MONTH(CURRENT_DATE())
                      AND booking_status != 'cancelled'");
$stats['month_revenue'] = (float)$stmt->fetch()['total'];

// Last month revenue (for trend)
$stmt = $db->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM bookings
                    WHERE YEAR(created_at)  = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                      AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                      AND booking_status != 'cancelled'");
$stats['last_month_revenue'] = (float)$stmt->fetch()['total'];

// Total venues & halls
$stmt = $db->query("SELECT COUNT(*) as count FROM venues WHERE status = 'active'");
$stats['total_venues'] = (int)$stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM halls WHERE status = 'active'");
$stats['total_halls'] = (int)$stmt->fetch()['count'];

// Total customers
$stmt = $db->query("SELECT COUNT(*) as count FROM customers");
$stats['total_customers'] = (int)$stmt->fetch()['count'];

// New customers this month
$stmt = $db->query("SELECT COUNT(*) as count FROM customers
                    WHERE YEAR(created_at)  = YEAR(CURRENT_DATE())
                      AND MONTH(created_at) = MONTH(CURRENT_DATE())");
$stats['new_customers_month'] = (int)$stmt->fetch()['count'];

// Today's events
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings
                    WHERE event_date = CURDATE() AND booking_status != 'cancelled'");
$stats['today_events'] = (int)$stmt->fetch()['count'];

// ─── Monthly Revenue (last 6 months) for chart ────────────────────────────────
$monthly_data_raw = $db->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m')   AS ym,
        DATE_FORMAT(created_at, '%b %Y')   AS label,
        COALESCE(SUM(grand_total), 0)      AS revenue,
        COUNT(*)                           AS bookings
    FROM bookings
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      AND booking_status != 'cancelled'
    GROUP BY ym, label
    ORDER BY ym ASC
")->fetchAll();

$chart_labels   = [];
$chart_revenue  = [];
$chart_bookings = [];
foreach ($monthly_data_raw as $row) {
    $chart_labels[]   = $row['label'];
    $chart_revenue[]  = round((float)$row['revenue'], 2);
    $chart_bookings[] = (int)$row['bookings'];
}

// ─── Booking status breakdown for doughnut chart ─────────────────────────────
$status_raw = $db->query("
    SELECT booking_status, COUNT(*) as count FROM bookings GROUP BY booking_status
")->fetchAll();

$status_labels = [];
$status_counts = [];
$status_colors = [
    'pending'   => '#f59e0b',
    'confirmed' => '#3b82f6',
    'completed' => '#10b981',
    'cancelled' => '#ef4444',
];
foreach ($status_raw as $row) {
    $status_labels[] = ucfirst($row['booking_status']);
    $status_counts[] = (int)$row['count'];
}

// ─── Recent bookings ─────────────────────────────────────────────────────────
$recent_bookings = $db->query("
    SELECT b.id, b.booking_number, b.booking_status, b.payment_status,
           b.event_date, b.grand_total,
           c.full_name,
           COALESCE(h.name, b.custom_hall_name) AS hall_name,
           COALESCE(v.name, b.custom_venue_name) AS venue_name,
           b.event_type
    FROM bookings b
    INNER JOIN customers c ON b.customer_id = c.id
    LEFT  JOIN halls   h ON b.hall_id = h.id
    LEFT  JOIN venues  v ON h.venue_id = v.id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetchAll();

// ─── Upcoming events ─────────────────────────────────────────────────────────
$upcoming_events = $db->query("
    SELECT b.id, b.booking_number, b.booking_status, b.event_date, b.event_type,
           c.full_name,
           COALESCE(h.name, b.custom_hall_name) AS hall_name,
           COALESCE(v.name, b.custom_venue_name) AS venue_name
    FROM bookings b
    INNER JOIN customers c ON b.customer_id = c.id
    LEFT  JOIN halls   h ON b.hall_id = h.id
    LEFT  JOIN venues  v ON h.venue_id = v.id
    WHERE b.event_date >= CURDATE()
      AND b.booking_status != 'cancelled'
    ORDER BY b.event_date ASC
    LIMIT 8
")->fetchAll();

// ─── Trend helpers ────────────────────────────────────────────────────────────
function calcTrendPercent($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

$booking_trend = calcTrendPercent($stats['this_month_bookings'], $stats['last_month_bookings']);
$revenue_trend = calcTrendPercent($stats['month_revenue'], $stats['last_month_revenue']);

// ─── Page-specific CSS ───────────────────────────────────────────────────────
$extra_css = <<<CSS
<style>
/* ── Dashboard reset & variables ───────────────────────────── */
:root {
    --db-blue:    #4f46e5;
    --db-indigo:  #6366f1;
    --db-green:   #10b981;
    --db-amber:   #f59e0b;
    --db-red:     #ef4444;
    --db-cyan:    #06b6d4;
    --db-purple:  #8b5cf6;
    --db-slate:   #64748b;
    --db-card-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    --db-card-hover:  0 8px 30px rgba(0,0,0,.14);
    --db-radius: 16px;
    --db-radius-sm: 10px;
}

/* ── Main metric cards ──────────────────────────────────────── */
.db-metric-card {
    background: #fff;
    border-radius: var(--db-radius);
    box-shadow: var(--db-card-shadow);
    padding: 1.5rem;
    transition: transform .2s ease, box-shadow .2s ease;
    border: 1px solid rgba(0,0,0,.04);
    position: relative;
    overflow: hidden;
}
.db-metric-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--db-card-hover);
}
.db-metric-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: var(--db-radius) var(--db-radius) 0 0;
}
.db-metric-card.card-blue::before   { background: linear-gradient(90deg, var(--db-blue), var(--db-indigo)); }
.db-metric-card.card-green::before  { background: linear-gradient(90deg, var(--db-green), #34d399); }
.db-metric-card.card-amber::before  { background: linear-gradient(90deg, var(--db-amber), #fbbf24); }
.db-metric-card.card-purple::before { background: linear-gradient(90deg, var(--db-purple), #a78bfa); }
.db-metric-card.card-cyan::before   { background: linear-gradient(90deg, var(--db-cyan), #22d3ee); }
.db-metric-card.card-slate::before  { background: linear-gradient(90deg, var(--db-slate), #94a3b8); }
.db-metric-card.card-red::before    { background: linear-gradient(90deg, var(--db-red), #f87171); }
.db-metric-card.card-teal::before   { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }

.db-metric-icon {
    width: 52px; height: 52px;
    border-radius: var(--db-radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.icon-blue   { background: rgba(79,70,229,.12);  color: var(--db-blue); }
.icon-green  { background: rgba(16,185,129,.12); color: var(--db-green); }
.icon-amber  { background: rgba(245,158,11,.12); color: var(--db-amber); }
.icon-purple { background: rgba(139,92,246,.12); color: var(--db-purple); }
.icon-cyan   { background: rgba(6,182,212,.12);  color: var(--db-cyan); }
.icon-slate  { background: rgba(100,116,139,.12);color: var(--db-slate); }
.icon-red    { background: rgba(239,68,68,.12);  color: var(--db-red); }
.icon-teal   { background: rgba(20,184,166,.12); color: #14b8a6; }

.db-metric-value {
    font-size: 1.85rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.1;
    margin: 0;
}
.db-metric-label {
    font-size: .8rem;
    font-weight: 500;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin: .3rem 0 0;
}
.db-metric-sub {
    font-size: .78rem;
    margin-top: .5rem;
    display: flex;
    align-items: center;
    gap: 4px;
}
.trend-up   { color: var(--db-green); }
.trend-down { color: var(--db-red); }
.trend-flat { color: var(--db-slate); }

/* ── Status badge cards (second row) ───────────────────────── */
.db-status-card {
    background: #fff;
    border-radius: var(--db-radius);
    box-shadow: var(--db-card-shadow);
    padding: 1.1rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid rgba(0,0,0,.04);
    transition: transform .2s ease;
}
.db-status-card:hover { transform: translateY(-2px); }
.db-status-num {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}
.db-status-lbl {
    font-size: .78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
}
.db-status-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}
.dot-amber  { background: var(--db-amber); }
.dot-blue   { background: var(--db-blue); }
.dot-green  { background: var(--db-green); }
.dot-red    { background: var(--db-red); }

/* ── Chart cards ────────────────────────────────────────────── */
.db-chart-card {
    background: #fff;
    border-radius: var(--db-radius);
    box-shadow: var(--db-card-shadow);
    border: 1px solid rgba(0,0,0,.04);
    overflow: hidden;
}
.db-chart-header {
    padding: 1.2rem 1.5rem .8rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
}
.db-chart-title {
    font-size: .95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.db-chart-title i { color: var(--db-blue); }
.db-chart-body { padding: 1.2rem 1.5rem; }

/* ── Table card ─────────────────────────────────────────────── */
.db-table-card {
    background: #fff;
    border-radius: var(--db-radius);
    box-shadow: var(--db-card-shadow);
    border: 1px solid rgba(0,0,0,.04);
    overflow: hidden;
}
.db-table-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.db-table-header h6 {
    font-size: .95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.db-table-header h6 i { color: var(--db-blue); }
.db-table-wrap table {
    margin: 0;
    font-size: .85rem;
}
.db-table-wrap thead th {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1px solid #e2e8f0;
    padding: .75rem 1.2rem;
    white-space: nowrap;
}
.db-table-wrap tbody tr {
    transition: background .15s;
    border-bottom: 1px solid #f1f5f9;
}
.db-table-wrap tbody tr:last-child { border-bottom: none; }
.db-table-wrap tbody tr:hover { background: #f8fafc; }
.db-table-wrap tbody td {
    padding: .85rem 1.2rem;
    vertical-align: middle;
    color: #334155;
}
.db-booking-num {
    font-weight: 600;
    color: var(--db-blue) !important;
    text-decoration: none;
    font-family: monospace;
    font-size: .82rem;
}
.db-booking-num:hover { text-decoration: underline; }
.db-customer-name {
    font-weight: 500;
    color: #1e293b;
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.db-venue-text {
    color: #64748b;
    font-size: .8rem;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.db-amount { font-weight: 600; color: #1e293b; }

/* ── Status badges ──────────────────────────────────────────── */
.db-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: .28rem .7rem;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .03em;
}
.db-badge-pending   { background: #fef3c7; color: #92400e; }
.db-badge-confirmed { background: #dbeafe; color: #1e40af; }
.db-badge-completed { background: #d1fae5; color: #065f46; }
.db-badge-cancelled { background: #fee2e2; color: #991b1b; }

/* ── Upcoming events timeline ───────────────────────────────── */
.db-event-list { list-style: none; padding: 0; margin: 0; }
.db-event-item {
    display: flex;
    gap: 1rem;
    padding: .9rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}
.db-event-item:last-child { border-bottom: none; }
.db-event-item:hover { background: #f8fafc; }
.db-event-date-box {
    flex-shrink: 0;
    width: 46px; height: 50px;
    background: linear-gradient(135deg, var(--db-blue), var(--db-indigo));
    border-radius: 10px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: #fff; text-align: center;
    line-height: 1.1;
}
.db-event-date-box .eday  { font-size: 1.1rem; font-weight: 700; }
.db-event-date-box .emon  { font-size: .62rem; font-weight: 500; text-transform: uppercase; letter-spacing: .05em; }
.db-event-info { flex: 1; min-width: 0; }
.db-event-customer {
    font-weight: 600; color: #1e293b;
    font-size: .88rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.db-event-venue {
    font-size: .78rem; color: #64748b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 2px;
}
.db-event-type {
    font-size: .72rem;
    font-weight: 600;
    background: #eff6ff;
    color: #3b82f6;
    padding: 2px 8px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 4px;
}

/* ── Quick actions ──────────────────────────────────────────── */
.db-quick-actions-card {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: var(--db-radius);
    padding: 1.5rem;
    box-shadow: var(--db-card-shadow);
}
.db-quick-actions-card h6 {
    color: rgba(255,255,255,.6);
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 600;
    margin-bottom: 1.2rem;
}
.db-qa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: .75rem;
}
.db-qa-btn {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 1rem .5rem;
    border-radius: 12px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: #fff;
    text-decoration: none;
    font-size: .78rem;
    font-weight: 500;
    transition: background .2s, transform .2s;
    text-align: center;
    gap: .5rem;
}
.db-qa-btn i { font-size: 1.3rem; }
.db-qa-btn:hover {
    background: rgba(255,255,255,.18);
    color: #fff;
    transform: translateY(-2px);
}

/* ── Today's highlight banner ───────────────────────────────── */
.db-today-banner {
    background: linear-gradient(135deg, var(--db-blue) 0%, var(--db-indigo) 100%);
    border-radius: var(--db-radius);
    padding: 1.4rem 1.8rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: 0 4px 20px rgba(79,70,229,.35);
    margin-bottom: 1.5rem;
}
.db-today-banner .today-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    opacity: .8;
}
.db-today-banner .today-val {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1;
    margin: .15rem 0;
}
.db-today-banner .today-sub { font-size: .82rem; opacity: .85; }
.db-today-banner .today-icon {
    font-size: 3rem;
    opacity: .25;
}

/* ── Section title ──────────────────────────────────────────── */
.db-section-title {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #94a3b8;
    margin: 1.5rem 0 .75rem;
}

/* ── Scrollable table wrapper ───────────────────────────────── */
.db-scroll-table { max-height: 360px; overflow-y: auto; }
.db-scroll-table::-webkit-scrollbar { width: 4px; }
.db-scroll-table::-webkit-scrollbar-track { background: transparent; }
.db-scroll-table::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* ── Empty state ────────────────────────────────────────────── */
.db-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 2.5rem 1rem;
    color: #94a3b8;
    gap: .5rem;
}
.db-empty i { font-size: 2rem; }
.db-empty span { font-size: .85rem; }

/* ── Utility overrides ───────────────────────────────────── */
.db-metric-value-sm   { font-size: 1.4rem !important; }
.db-btn-action        { font-size: .78rem; border-radius: 8px !important; }
.db-nowrap            { white-space: nowrap; }
.db-scroll-events     { overflow-y: auto; max-height: 380px; }
.db-icon-xs           { font-size: .7rem; }
.db-status-canvas     { max-width: 200px; }

/* ── Chart legend items ─────────────────────────────────── */
.db-chart-legend-item { font-size: .75rem; display: flex; align-items: center; gap: 4px; }
.db-chart-legend-dot  { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

/* ── Today banner stat items ─────────────────────────────── */
.db-today-stat-num  { font-size: 1.4rem; font-weight: 700; }
.db-today-stat-lbl  { font-size: .75rem; opacity: .8; }
/* ── Venue/Hall mini tiles ───────────────────────────────── */
.db-mini-tile-num { font-size: 1.4rem; font-weight: 700; color: #1e293b; }
.db-mini-tile-lbl { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }

/* ── View-all link ──────────────────────────────────────────── */
.db-view-all {
    display: block;
    text-align: center;
    padding: .75rem;
    font-size: .8rem;
    font-weight: 600;
    color: var(--db-blue);
    border-top: 1px solid #f1f5f9;
    text-decoration: none;
    transition: background .15s;
}
.db-view-all:hover { background: #f8fafc; color: var(--db-blue); }
</style>
CSS;

require_once __DIR__ . '/includes/header.php';

// ─── Helper for booking status badge ─────────────────────────────────────────
function dashBadge($status) {
    $map = [
        'pending'   => 'db-badge-pending',
        'confirmed' => 'db-badge-confirmed',
        'completed' => 'db-badge-completed',
        'cancelled' => 'db-badge-cancelled',
    ];
    $cls   = $map[$status] ?? 'db-badge-pending';
    $label = ucfirst($status);
    return "<span class=\"db-badge {$cls}\">{$label}</span>";
}
?>

<!-- ════════════════════════════════════════════════════════════
     TODAY BANNER
════════════════════════════════════════════════════════════ -->
<div class="db-today-banner">
    <div>
        <div class="today-label">Today — <?php echo date('l, F j, Y'); ?> <small class="opacity-75">(<?php echo convertToNepaliDate(date('Y-m-d')); ?>)</small></div>
        <div class="today-val"><?php echo $stats['today_events']; ?></div>
        <div class="today-sub">Event<?php echo $stats['today_events'] != 1 ? 's' : ''; ?> scheduled today</div>
    </div>
    <div class="today-icon"><i class="fas fa-calendar-star"></i></div>
    <div class="d-flex gap-3 flex-wrap">
        <div class="text-center">
            <div class="db-today-stat-num"><?php echo $stats['pending_bookings']; ?></div>
            <div class="db-today-stat-lbl">Pending</div>
        </div>
        <div class="text-center">
            <div class="db-today-stat-num"><?php echo $stats['confirmed_bookings']; ?></div>
            <div class="db-today-stat-lbl">Confirmed</div>
        </div>
        <div class="text-center">
            <div class="db-today-stat-num"><?php echo formatCurrency($stats['month_revenue']); ?></div>
            <div class="db-today-stat-lbl">This Month</div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ROW 1 — MAIN METRICS
════════════════════════════════════════════════════════════ -->
<div class="db-section-title">Overview</div>
<div class="row g-3 mb-2">
    <!-- Total Bookings -->
    <div class="col-xl-3 col-md-6">
        <div class="db-metric-card card-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div class="db-metric-icon icon-blue"><i class="fas fa-calendar-check"></i></div>
                <div class="text-end">
                    <p class="db-metric-value counter" data-target="<?php echo $stats['total_bookings']; ?>"><?php echo $stats['total_bookings']; ?></p>
                    <p class="db-metric-label">Total Bookings</p>
                </div>
            </div>
            <div class="db-metric-sub <?php echo $booking_trend >= 0 ? 'trend-up' : 'trend-down'; ?>">
                <i class="fas fa-arrow-<?php echo $booking_trend >= 0 ? 'up' : 'down'; ?>-right"></i>
                <?php echo abs($booking_trend); ?>% vs last month
            </div>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="col-xl-3 col-md-6">
        <div class="db-metric-card card-green">
            <div class="d-flex align-items-start justify-content-between">
                <div class="db-metric-icon icon-green"><i class="fas fa-coins"></i></div>
                <div class="text-end">
                    <p class="db-metric-value db-metric-value-sm"><?php echo formatCurrency($stats['total_revenue']); ?></p>
                    <p class="db-metric-label">Total Revenue</p>
                </div>
            </div>
            <div class="db-metric-sub trend-flat">
                <i class="fas fa-calendar-month"></i>
                <?php echo formatCurrency($stats['month_revenue']); ?> this month
            </div>
        </div>
    </div>

    <!-- Monthly Revenue Trend -->
    <div class="col-xl-3 col-md-6">
        <div class="db-metric-card card-purple">
            <div class="d-flex align-items-start justify-content-between">
                <div class="db-metric-icon icon-purple"><i class="fas fa-chart-line"></i></div>
                <div class="text-end">
                    <p class="db-metric-value db-metric-value-sm"><?php echo formatCurrency($stats['month_revenue']); ?></p>
                    <p class="db-metric-label">This Month Revenue</p>
                </div>
            </div>
            <div class="db-metric-sub <?php echo $revenue_trend >= 0 ? 'trend-up' : 'trend-down'; ?>">
                <i class="fas fa-arrow-<?php echo $revenue_trend >= 0 ? 'up' : 'down'; ?>-right"></i>
                <?php echo abs($revenue_trend); ?>% vs last month
            </div>
        </div>
    </div>

    <!-- Customers -->
    <div class="col-xl-3 col-md-6">
        <div class="db-metric-card card-cyan">
            <div class="d-flex align-items-start justify-content-between">
                <div class="db-metric-icon icon-cyan"><i class="fas fa-users"></i></div>
                <div class="text-end">
                    <p class="db-metric-value counter" data-target="<?php echo $stats['total_customers']; ?>"><?php echo $stats['total_customers']; ?></p>
                    <p class="db-metric-label">Total Customers</p>
                </div>
            </div>
            <div class="db-metric-sub trend-flat">
                <i class="fas fa-user-plus"></i>
                <?php echo $stats['new_customers_month']; ?> new this month
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ROW 2 — STATUS STRIP
════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="db-status-card">
            <div class="db-metric-icon icon-amber"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <div class="db-status-num counter" data-target="<?php echo $stats['pending_bookings']; ?>"><?php echo $stats['pending_bookings']; ?></div>
                <div class="db-status-lbl"><span class="db-status-dot dot-amber"></span>Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-status-card">
            <div class="db-metric-icon icon-blue"><i class="fas fa-thumbs-up"></i></div>
            <div>
                <div class="db-status-num counter" data-target="<?php echo $stats['confirmed_bookings']; ?>"><?php echo $stats['confirmed_bookings']; ?></div>
                <div class="db-status-lbl"><span class="db-status-dot dot-blue"></span>Confirmed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-status-card">
            <div class="db-metric-icon icon-green"><i class="fas fa-check-double"></i></div>
            <div>
                <div class="db-status-num counter" data-target="<?php echo $stats['completed_bookings']; ?>"><?php echo $stats['completed_bookings']; ?></div>
                <div class="db-status-lbl"><span class="db-status-dot dot-green"></span>Completed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-status-card">
            <div class="db-metric-icon icon-red"><i class="fas fa-ban"></i></div>
            <div>
                <div class="db-status-num counter" data-target="<?php echo $stats['cancelled_bookings']; ?>"><?php echo $stats['cancelled_bookings']; ?></div>
                <div class="db-status-lbl"><span class="db-status-dot dot-red"></span>Cancelled</div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ROW 3 — CHARTS
════════════════════════════════════════════════════════════ -->
<div class="db-section-title">Analytics</div>
<div class="row g-3 mb-4">
    <!-- Revenue Bar Chart -->
    <div class="col-xl-8">
        <div class="db-chart-card h-100">
            <div class="db-chart-header">
                <h6 class="db-chart-title"><i class="fas fa-chart-bar"></i> Revenue — Last 6 Months</h6>
                <a href="<?php echo BASE_URL; ?>/admin/reports/index.php" class="btn btn-sm btn-outline-primary db-btn-action">
                    <i class="fas fa-external-link-alt me-1"></i>Full Report
                </a>
            </div>
            <div class="db-chart-body">
                <canvas id="revenueChart" height="90"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut Status Chart + Venue/Hall counts -->
    <div class="col-xl-4">
        <div class="db-chart-card h-100 d-flex flex-column">
            <div class="db-chart-header">
                <h6 class="db-chart-title"><i class="fas fa-chart-pie"></i> Booking Status</h6>
            </div>
            <div class="db-chart-body flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                <?php if (array_sum($status_counts) > 0): ?>
                    <canvas id="statusChart" height="200" class="db-status-canvas"></canvas>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <?php foreach ($status_labels as $i => $lbl): ?>
                            <?php $dotColor = $status_colors[strtolower($lbl)] ?? '#94a3b8'; ?>
                            <span class="db-chart-legend-item">
                                <span class="db-chart-legend-dot" style="background:<?php echo $dotColor; ?>"></span>
                                <?php echo $lbl; ?> (<?php echo $status_counts[$i]; ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="db-empty"><i class="fas fa-chart-pie"></i><span>No data yet</span></div>
                <?php endif; ?>
            </div>
            <!-- Mini venue/hall tiles -->
            <div class="row g-0 border-top">
                <div class="col-6 p-3 text-center border-end">
                    <div class="db-mini-tile-num"><?php echo $stats['total_venues']; ?></div>
                    <div class="db-mini-tile-lbl">Active Venues</div>
                </div>
                <div class="col-6 p-3 text-center">
                    <div class="db-mini-tile-num"><?php echo $stats['total_halls']; ?></div>
                    <div class="db-mini-tile-lbl">Active Halls</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ROW 4 — RECENT BOOKINGS + UPCOMING EVENTS
════════════════════════════════════════════════════════════ -->
<div class="db-section-title">Activity</div>
<div class="row g-3 mb-4">
    <!-- Recent Bookings Table -->
    <div class="col-xl-7">
        <div class="db-table-card">
            <div class="db-table-header">
                <h6><i class="fas fa-clock-rotate-left"></i> Recent Bookings</h6>
                <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="btn btn-sm btn-outline-primary db-btn-action">View All</a>
            </div>
            <?php if (empty($recent_bookings)): ?>
                <div class="db-empty"><i class="fas fa-calendar-xmark"></i><span>No bookings yet</span></div>
            <?php else: ?>
                <div class="db-table-wrap db-scroll-table">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>Booking #</th>
                                <th>Customer</th>
                                <th>Venue / Hall</th>
                                <th>Event Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $bk): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/bookings/view.php?id=<?php echo $bk['id']; ?>" class="db-booking-num">
                                            <?php echo htmlspecialchars($bk['booking_number']); ?>
                                        </a>
                                    </td>
                                    <td><div class="db-customer-name" title="<?php echo htmlspecialchars($bk['full_name']); ?>"><?php echo htmlspecialchars($bk['full_name']); ?></div></td>
                                    <td><div class="db-venue-text" title="<?php echo htmlspecialchars($bk['venue_name'] . ' / ' . $bk['hall_name']); ?>"><?php echo htmlspecialchars($bk['venue_name'] ?? '—'); ?></div></td>
                                    <td class="db-nowrap"><?php echo date('d M Y', strtotime($bk['event_date'])); ?><br><small class="text-muted"><?php echo convertToNepaliDate($bk['event_date']); ?></small></td>
                                    <td class="db-amount"><?php echo formatCurrency($bk['grand_total']); ?></td>
                                    <td><?php echo dashBadge($bk['booking_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="db-view-all"><i class="fas fa-arrow-right me-1"></i>View all bookings</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Events Timeline -->
    <div class="col-xl-5">
        <div class="db-table-card h-100 d-flex flex-column">
            <div class="db-table-header">
                <h6><i class="fas fa-calendar-days"></i> Upcoming Events</h6>
                <a href="<?php echo BASE_URL; ?>/admin/bookings/calendar.php" class="btn btn-sm btn-outline-primary db-btn-action">
                    <i class="fas fa-calendar-alt me-1"></i>Calendar
                </a>
            </div>
            <?php if (empty($upcoming_events)): ?>
                <div class="db-empty flex-grow-1"><i class="fas fa-calendar-check"></i><span>No upcoming events</span></div>
            <?php else: ?>
                <div class="flex-grow-1 db-scroll-events">
                    <ul class="db-event-list">
                        <?php foreach ($upcoming_events as $ev): ?>
                            <li class="db-event-item">
                                <div class="db-event-date-box">
                                    <span class="eday"><?php echo date('d', strtotime($ev['event_date'])); ?></span>
                                    <span class="emon"><?php echo date('M', strtotime($ev['event_date'])); ?></span>
                                </div>
                                <div class="db-event-info">
                                    <div class="db-event-customer"><?php echo htmlspecialchars($ev['full_name']); ?></div>
                                    <div class="db-event-venue"><i class="fas fa-location-dot me-1 db-icon-xs"></i><?php echo htmlspecialchars($ev['venue_name'] ?? '—'); ?><?php if (!empty($ev['hall_name'])): ?> · <?php echo htmlspecialchars($ev['hall_name']); ?><?php endif; ?></div>
                                    <div style="font-size:0.68rem;color:#6c757d;"><?php echo convertToNepaliDate($ev['event_date']); ?></div>
                                    <?php if (!empty($ev['event_type'])): ?>
                                        <span class="db-event-type"><?php echo htmlspecialchars($ev['event_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-shrink-0 ms-1">
                                    <?php echo dashBadge($ev['booking_status']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a href="<?php echo BASE_URL; ?>/admin/bookings/calendar.php" class="db-view-all"><i class="fas fa-arrow-right me-1"></i>View calendar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ROW 5 — QUICK ACTIONS
════════════════════════════════════════════════════════════ -->
<div class="db-section-title">Quick Actions</div>
<div class="db-quick-actions-card mb-4">
    <h6><i class="fas fa-bolt me-1"></i> Quick Actions</h6>
    <div class="db-qa-grid">
        <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="db-qa-btn">
            <i class="fas fa-calendar-check"></i> Bookings
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/bookings/calendar.php" class="db-qa-btn">
            <i class="fas fa-calendar-alt"></i> Calendar
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/customers/index.php" class="db-qa-btn">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/venues/index.php" class="db-qa-btn">
            <i class="fas fa-building"></i> Venues
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/halls/index.php" class="db-qa-btn">
            <i class="fas fa-door-open"></i> Halls
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/services/index.php" class="db-qa-btn">
            <i class="fas fa-concierge-bell"></i> Services
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/vendors/index.php" class="db-qa-btn">
            <i class="fas fa-user-tie"></i> Vendors
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/reports/index.php" class="db-qa-btn">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/shared-folders/index.php" class="db-qa-btn">
            <i class="fas fa-share-alt"></i> Photo Share
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/settings/index.php" class="db-qa-btn">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>
</div>

<?php
// ─── Page-specific JS (charts + counters) ────────────────────────────────────
$extra_js = '<script>
(function() {

    /* ── Animated number counters ─────────────────────────── */
    function animateCounter(el) {
        var target = parseInt(el.dataset.target, 10) || 0;
        var start  = 0;
        var dur    = 900;
        var step   = 16;
        var inc    = target / (dur / step);
        var timer  = setInterval(function() {
            start += inc;
            if (start >= target) { start = target; clearInterval(timer); }
            el.textContent = Math.floor(start).toLocaleString();
        }, step);
    }
    document.querySelectorAll(".counter").forEach(function(el) {
        animateCounter(el);
    });

    /* ── Revenue Bar Chart ────────────────────────────────── */
    var revenueCanvas = document.getElementById("revenueChart");
    if (revenueCanvas) {
        var chartLabels   = ' . json_encode($chart_labels) . ';
        var chartRevenue  = ' . json_encode($chart_revenue) . ';
        var chartBookings = ' . json_encode($chart_bookings) . ';

        new Chart(revenueCanvas, {
            type: "bar",
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: "Revenue",
                        data: chartRevenue,
                        backgroundColor: "rgba(79,70,229,.75)",
                        hoverBackgroundColor: "rgba(79,70,229,1)",
                        borderRadius: 6,
                        borderSkipped: false,
                        yAxisID: "y1"
                    },
                    {
                        label: "Bookings",
                        data: chartBookings,
                        type: "line",
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16,185,129,.12)",
                        borderWidth: 2.5,
                        pointBackgroundColor: "#10b981",
                        pointRadius: 4,
                        fill: true,
                        tension: .4,
                        yAxisID: "y2"
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { position: "top", labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.dataset.label === "Revenue") {
                                    return " Revenue: " + ctx.parsed.y.toLocaleString();
                                }
                                return " Bookings: " + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y1: {
                        position: "left",
                        ticks: { font: { size: 10 }, callback: function(v) { return v.toLocaleString(); } },
                        grid: { color: "rgba(0,0,0,.04)" }
                    },
                    y2: {
                        position: "right",
                        grid: { display: false },
                        ticks: { font: { size: 10 }, stepSize: 1, callback: function(v) { return Number.isInteger(v) ? v : ""; } }
                    }
                }
            }
        });
    }

    /* ── Status Doughnut Chart ────────────────────────────── */
    var statusCanvas = document.getElementById("statusChart");
    if (statusCanvas) {
        var statusLabels = ' . json_encode($status_labels) . ';
        var statusCounts = ' . json_encode($status_counts) . ';
        var colorMap     = ' . json_encode(array_combine(
                array_map('ucfirst', array_keys($status_colors)),
                array_values($status_colors)
            )) . ';
        var bgColors     = statusLabels.map(function(l){ return colorMap[l] || "#94a3b8"; });

        new Chart(statusCanvas, {
            type: "doughnut",
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: bgColors,
                    borderWidth: 3,
                    borderColor: "#fff",
                    hoverBorderColor: "#fff",
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                cutout: "70%",
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return " " + ctx.label + ": " + ctx.parsed; } } }
                }
            }
        });
    }

})();
</script>';

require_once __DIR__ . '/includes/footer.php';
