<?php
$page_title = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get filter parameter - default to 'active' (new bookings only)
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'active';

// Filter mapping - maps filter values to their SQL WHERE conditions
// Using hardcoded values ensures SQL safety (no user input in queries)
//
// Business logic for 'active' bookings:
// - 'pending': New bookings awaiting payment or confirmation
// - 'payment_submitted': Bookings where customer has submitted payment (awaiting verification)
// - 'confirmed': Bookings that are confirmed and upcoming
// These are considered "active" because they require ongoing attention from staff.
// 'completed' and 'cancelled' bookings are historical/archived and hidden by default.
$filter_conditions = [
    'active' => " WHERE b.booking_status IN ('pending', 'payment_submitted', 'confirmed')",
    'pending' => " WHERE b.booking_status = 'pending'",
    'payment_submitted' => " WHERE b.booking_status = 'payment_submitted'",
    'confirmed' => " WHERE b.booking_status = 'confirmed'",
    'completed' => " WHERE b.booking_status = 'completed'",
    'cancelled' => " WHERE b.booking_status = 'cancelled'",
    'all' => ''  // No filter - show everything
];

// Whitelist validation - only allow known filter values
if (!array_key_exists($status_filter, $filter_conditions)) {
    $status_filter = 'active'; // Fall back to default if invalid value provided
}

// Build query with filter condition (hardcoded values only, no user input)
$base_query = "SELECT b.*, 
                    c.full_name, c.phone, c.email,
                    COALESCE(h.name, b.custom_hall_name) as hall_name, 
                    COALESCE(v.name, b.custom_venue_name) as venue_name,
                    COALESCE((SELECT SUM(paid_amount) FROM payments WHERE booking_id = b.id AND payment_status = 'verified'), 0) as total_paid
                    FROM bookings b
                    INNER JOIN customers c ON b.customer_id = c.id
                    LEFT JOIN halls h ON b.hall_id = h.id
                    LEFT JOIN venues v ON h.venue_id = v.id"
                    . $filter_conditions[$status_filter]
                    . " ORDER BY b.created_at DESC";

// $db_error_html holds a trusted, hardcoded HTML error message (not from user/session input)
$db_error_html = '';
try {
    $stmt = $db->query($base_query);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Bookings index query failed: ' . $e->getMessage());
    $db_error_html = 'Unable to load bookings due to a database error. Please run the upgrade script (<code>database/upgrade.sql</code>) via MySQL client or phpMyAdmin, then reload this page. If the problem persists, contact your administrator.';
    $bookings = [];
}

// Compute summary stats for the stat cards (always from full data set for accuracy)
try {
    $stats_stmt = $db->query("SELECT
        COUNT(*) AS total_all,
        SUM(CASE WHEN b.booking_status IN ('pending','payment_submitted','confirmed') THEN 1 ELSE 0 END) AS total_active,
        SUM(CASE WHEN b.booking_status = 'pending' THEN 1 ELSE 0 END) AS total_pending,
        SUM(CASE WHEN b.booking_status = 'payment_submitted' THEN 1 ELSE 0 END) AS total_payment_submitted,
        SUM(CASE WHEN b.booking_status = 'confirmed' THEN 1 ELSE 0 END) AS total_confirmed,
        SUM(CASE WHEN b.booking_status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
        SUM(CASE WHEN b.booking_status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled,
        SUM(b.grand_total) AS total_revenue,
        COALESCE((SELECT SUM(p.paid_amount) FROM payments p WHERE p.payment_status = 'verified'), 0) AS total_collected
        FROM bookings b");
    $stats = $stats_stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_all' => 0, 'total_active' => 0, 'total_pending' => 0,
              'total_payment_submitted' => 0, 'total_confirmed' => 0,
              'total_completed' => 0, 'total_cancelled' => 0,
              'total_revenue' => 0, 'total_collected' => 0];
}

$filter_labels = [
    'active' => 'Active Bookings',
    'pending' => 'Pending Bookings',
    'payment_submitted' => 'Payment Submitted',
    'confirmed' => 'Confirmed Bookings',
    'completed' => 'Order Complete',
    'cancelled' => 'Cancelled Bookings',
    'all' => 'All Bookings',
];
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show bm-alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show bm-alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($db_error_html): ?>
    <div class="alert alert-danger alert-dismissible fade show bm-alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $db_error_html; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PAGE HEADER                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-page-header">
    <div class="bm-header-content">
        <div class="bm-header-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <h1 class="bm-header-title">Manage Bookings</h1>
            <p class="bm-header-subtitle">Track, manage and update all venue bookings in one place</p>
        </div>
    </div>
    <div class="bm-header-actions">
        <a href="calendar.php" class="bm-btn bm-btn-outline">
            <i class="fas fa-calendar-alt"></i> Calendar View
        </a>
        <a href="add.php" class="bm-btn bm-btn-primary">
            <i class="fas fa-plus"></i> New Booking
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SUMMARY STAT CARDS                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-stats-grid">
    <a href="?status_filter=active" class="bm-stat-card bm-stat-active <?php echo $status_filter === 'active' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-bolt"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$stats['total_active']; ?></span>
            <span class="bm-stat-label">Active Bookings</span>
        </div>
    </a>
    <a href="?status_filter=pending" class="bm-stat-card bm-stat-pending <?php echo $status_filter === 'pending' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$stats['total_pending']; ?></span>
            <span class="bm-stat-label">Pending Review</span>
        </div>
    </a>
    <a href="?status_filter=payment_submitted" class="bm-stat-card bm-stat-payment <?php echo $status_filter === 'payment_submitted' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-money-check-alt"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$stats['total_payment_submitted']; ?></span>
            <span class="bm-stat-label">Awaiting Verification</span>
        </div>
    </a>
    <a href="?status_filter=confirmed" class="bm-stat-card bm-stat-confirmed <?php echo $status_filter === 'confirmed' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$stats['total_confirmed']; ?></span>
            <span class="bm-stat-label">Confirmed</span>
        </div>
    </a>
    <a href="?status_filter=completed" class="bm-stat-card bm-stat-completed <?php echo $status_filter === 'completed' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-trophy"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value"><?php echo (int)$stats['total_completed']; ?></span>
            <span class="bm-stat-label">Completed</span>
        </div>
    </a>
    <a href="?status_filter=all" class="bm-stat-card bm-stat-revenue <?php echo $status_filter === 'all' ? 'bm-stat-selected' : ''; ?>">
        <div class="bm-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="bm-stat-body">
            <span class="bm-stat-value bm-stat-value-sm"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></span>
            <span class="bm-stat-label">Total Revenue</span>
        </div>
    </a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  BOOKINGS TABLE CARD                                       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bm-card">
    <!-- Card toolbar -->
    <div class="bm-card-toolbar">
        <div class="bm-toolbar-left">
            <h2 class="bm-card-title">
                <?php echo htmlspecialchars($filter_labels[$status_filter] ?? 'Bookings'); ?>
                <span class="bm-count-badge"><?php echo count($bookings); ?></span>
            </h2>
        </div>
        <div class="bm-toolbar-right">
            <!-- Filter pill tabs -->
            <div class="bm-filter-pills">
                <a href="?status_filter=active"            class="bm-pill <?php echo $status_filter === 'active'            ? 'bm-pill-active'   : ''; ?>">Active</a>
                <a href="?status_filter=pending"           class="bm-pill <?php echo $status_filter === 'pending'           ? 'bm-pill-pending'  : ''; ?>">Pending</a>
                <a href="?status_filter=payment_submitted" class="bm-pill <?php echo $status_filter === 'payment_submitted' ? 'bm-pill-payment'  : ''; ?>">Payment</a>
                <a href="?status_filter=confirmed"         class="bm-pill <?php echo $status_filter === 'confirmed'         ? 'bm-pill-confirmed': ''; ?>">Confirmed</a>
                <a href="?status_filter=completed"         class="bm-pill <?php echo $status_filter === 'completed'         ? 'bm-pill-completed': ''; ?>">Completed</a>
                <a href="?status_filter=cancelled"         class="bm-pill <?php echo $status_filter === 'cancelled'         ? 'bm-pill-cancelled': ''; ?>">Cancelled</a>
                <a href="?status_filter=all"               class="bm-pill <?php echo $status_filter === 'all'               ? 'bm-pill-all'      : ''; ?>">All</a>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bm-table-wrap">
        <table class="table datatable bm-table mb-0" id="bookingsTable">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th>Venue &amp; Hall</th>
                    <th>Event Date</th>
                    <th>Type / Guests</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="9" class="bm-empty-state">
                            <div class="bm-empty-icon"><i class="fas fa-calendar-times"></i></div>
                            <div class="bm-empty-title">No bookings found</div>
                            <div class="bm-empty-sub">
                                <?php if ($status_filter !== 'all'): ?>
                                    There are no <strong><?php echo htmlspecialchars(strtolower($filter_labels[$status_filter])); ?></strong> right now.
                                    <a href="?status_filter=all">View all bookings</a>
                                <?php else: ?>
                                    No bookings have been created yet.
                                    <a href="add.php">Create the first booking</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): 
                        // Calculate due amount using correct formula (never negative)
                        $balance_due = max(0, $booking['grand_total'] - $booking['total_paid']);
                        $payment_percentage = $booking['grand_total'] > 0 ? ($booking['total_paid'] / $booking['grand_total']) * 100 : 0;

                        // Avatar initials (first letter of first & last name, fallback to '?')
                        $name_parts = explode(' ', trim($booking['full_name']));
                        $first_part = isset($name_parts[0]) ? $name_parts[0] : '';
                        $avatar_initials = $first_part !== '' ? strtoupper(substr($first_part, 0, 1)) : '?';
                        if (count($name_parts) > 1 && $first_part !== '') {
                            $last_part = end($name_parts);
                            if ($last_part !== '') {
                                $avatar_initials .= strtoupper(substr($last_part, 0, 1));
                            }
                        }

                        // Booking status config
                        $bs = $booking['booking_status'];
                        $bs_cfg = [
                            'confirmed'         => ['cls' => 'bm-badge-confirmed',  'icon' => 'fa-check-circle'],
                            'payment_submitted' => ['cls' => 'bm-badge-payment',    'icon' => 'fa-money-check-alt'],
                            'pending'           => ['cls' => 'bm-badge-pending',    'icon' => 'fa-hourglass-half'],
                            'cancelled'         => ['cls' => 'bm-badge-cancelled',  'icon' => 'fa-times-circle'],
                            'completed'         => ['cls' => 'bm-badge-completed',  'icon' => 'fa-trophy'],
                        ];
                        $bs_class = $bs_cfg[$bs]['cls']  ?? 'bm-badge-secondary';
                        $bs_icon  = $bs_cfg[$bs]['icon'] ?? 'fa-circle';

                        // Payment status config
                        $ps = $booking['payment_status'];
                        $ps_cfg = [
                            'paid'      => ['cls' => 'bm-badge-confirmed',  'icon' => 'fa-check-double'],
                            'partial'   => ['cls' => 'bm-badge-payment',    'icon' => 'fa-adjust'],
                            'pending'   => ['cls' => 'bm-badge-pending',    'icon' => 'fa-clock'],
                            'cancelled' => ['cls' => 'bm-badge-cancelled',  'icon' => 'fa-ban'],
                        ];
                        $ps_class = $ps_cfg[$ps]['cls']  ?? 'bm-badge-secondary';
                        $ps_icon  = $ps_cfg[$ps]['icon'] ?? 'fa-circle';
                    ?>
                    <tr class="bm-row">
                        <!-- Booking Number -->
                        <td>
                            <div class="bm-booking-num"><?php echo htmlspecialchars($booking['booking_number']); ?></div>
                            <div class="bm-row-sub"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></div>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="bm-customer-cell">
                                <div class="bm-avatar"><?php echo htmlspecialchars($avatar_initials); ?></div>
                                <div class="bm-customer-info">
                                    <div class="bm-customer-name"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                                    <div class="bm-row-sub"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($booking['phone']); ?></div>
                                    <?php if (!empty($booking['email'])): ?>
                                        <div class="bm-row-sub"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($booking['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Venue / Hall -->
                        <td>
                            <div class="bm-venue-name">
                                <?php echo htmlspecialchars($booking['venue_name']); ?>
                                <?php if (empty($booking['hall_id'])): ?>
                                    <span class="bm-custom-tag" title="Customer's own venue"><i class="fas fa-map-marker-alt"></i> Custom</span>
                                <?php endif; ?>
                            </div>
                            <div class="bm-row-sub"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($booking['hall_name']); ?></div>
                        </td>

                        <!-- Event Date -->
                        <td>
                            <div class="bm-event-date"><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></div>
                            <div class="bm-row-sub">
                                <i class="fas fa-sun"></i> <?php echo ucfirst($booking['shift']); ?>
                                <?php if (!empty($booking['start_time']) && !empty($booking['end_time'])): ?>
                                    &nbsp;·&nbsp;<?php echo formatBookingTime($booking['start_time']); ?> – <?php echo formatBookingTime($booking['end_time']); ?>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Event Type / Guests -->
                        <td>
                            <div class="bm-event-type-badge"><?php echo htmlspecialchars(ucfirst($booking['event_type'])); ?></div>
                            <div class="bm-row-sub mt-1"><i class="fas fa-users"></i> <?php echo (int)$booking['number_of_guests']; ?> guests</div>
                        </td>

                        <!-- Amount -->
                        <td>
                            <div class="bm-amount-total"><?php echo formatCurrency($booking['grand_total']); ?></div>
                            <?php if ($booking['total_paid'] > 0): ?>
                                <div class="bm-row-sub bm-amount-paid"><i class="fas fa-check"></i> Paid <?php echo formatCurrency($booking['total_paid']); ?></div>
                            <?php endif; ?>
                            <?php if ($balance_due > 0 && $booking['payment_status'] !== 'paid'): ?>
                                <div class="bm-row-sub bm-amount-due"><i class="fas fa-exclamation"></i> Due <?php echo formatCurrency($balance_due); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Booking Status -->
                        <td>
                            <span class="bm-badge <?php echo $bs_class; ?> booking-status-badge"
                                  title="Booking status is read-only here. Open the booking to update it."
                                  role="status"
                                  aria-label="Booking status: <?php echo getBookingStatusLabel($booking['booking_status']); ?> (read-only)">
                                <i class="fas <?php echo $bs_icon; ?>"></i>
                                <?php echo getBookingStatusLabel($booking['booking_status']); ?>
                            </span>
                        </td>

                        <!-- Payment Status -->
                        <td>
                            <span class="bm-badge <?php echo $ps_class; ?>"
                                  title="Payment status is read-only here. Open the booking to update it."
                                  role="status"
                                  aria-label="Payment status: <?php echo htmlspecialchars(ucfirst($booking['payment_status']), ENT_QUOTES, 'UTF-8'); ?> (read-only)">
                                <i class="fas <?php echo $ps_icon; ?>"></i>
                                <?php echo ucfirst($booking['payment_status']); ?>
                            </span>
                            <?php if ($payment_percentage > 0 && $payment_percentage < 100): ?>
                                <div class="bm-progress mt-1">
                                    <div class="bm-progress-bar" style="width:<?php echo round($payment_percentage); ?>%"
                                         role="progressbar"
                                         aria-valuenow="<?php echo round($payment_percentage); ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="bm-progress-pct"><?php echo round($payment_percentage); ?>% paid</div>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-center">
                            <div class="bm-actions">
                                <a href="view.php?id=<?php echo $booking['id']; ?>"
                                   class="bm-action-btn bm-action-view"
                                   title="View Details"
                                   data-bs-toggle="tooltip">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $booking['id']; ?>"
                                   class="bm-action-btn bm-action-edit"
                                   title="Edit Booking"
                                   data-bs-toggle="tooltip">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form method="POST" action="delete.php" style="display:inline;"
                                      onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit"
                                            class="bm-action-btn bm-action-delete"
                                            title="Delete Booking"
                                            data-bs-toggle="tooltip">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  PRO-LEVEL STYLES                                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
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
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(76,175,80,0.12);
    pointer-events: none;
}
.bm-page-header::after {
    content: '';
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
    content: '';
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
.bm-stat-active  .bm-stat-icon { background: #e8f5e9; color: #2E7D32; }
.bm-stat-active  { color: #2E7D32; }
.bm-stat-pending .bm-stat-icon { background: #fff3e0; color: #e65100; }
.bm-stat-pending { color: #e65100; }
.bm-stat-payment .bm-stat-icon { background: #e3f2fd; color: #1565c0; }
.bm-stat-payment { color: #1565c0; }
.bm-stat-confirmed .bm-stat-icon { background: #e8f5e9; color: #1b5e20; }
.bm-stat-confirmed { color: #1b5e20; }
.bm-stat-completed .bm-stat-icon { background: #ede7f6; color: #4527a0; }
.bm-stat-completed { color: #4527a0; }
.bm-stat-revenue .bm-stat-icon { background: #fce4ec; color: #880e4f; }
.bm-stat-revenue { color: #880e4f; }
.bm-stat-selected .bm-stat-value { color: inherit; }

/* ── Main Card ───────────────────────────────────────────────── */
.bm-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    overflow: hidden;
    border: 1px solid #f0f2f5;
}

/* ── Card Toolbar ────────────────────────────────────────────── */
.bm-card-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #f0f2f5;
    background: #fafbfc;
}
.bm-toolbar-left { display: flex; align-items: center; gap: 0.75rem; }
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
.bm-filter-pills {
    display: flex; flex-wrap: wrap; gap: 0.35rem;
}
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
    padding: 1rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
    font-size: 0.875rem;
}
.bm-row:last-child td { border-bottom: none; }
.bm-row:hover td { background: #f8fffe; }

/* row sub-text */
.bm-row-sub {
    font-size: 0.76rem; color: #9ca3af; margin-top: 3px;
}

/* Booking number */
.bm-booking-num {
    font-weight: 700; font-size: 0.875rem; color: #2c6bed;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.3px;
}

/* Customer cell */
.bm-customer-cell { display: flex; align-items: flex-start; gap: 0.65rem; }
.bm-avatar {
    width: 36px; height: 36px; border-radius: 10px;
    background: linear-gradient(135deg, #4CAF50, #1b5e20);
    color: #fff; font-size: 0.75rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; letter-spacing: 0.5px;
}
.bm-customer-name { font-weight: 600; color: #1f2937; font-size: 0.875rem; }

/* Venue */
.bm-venue-name { font-weight: 600; color: #374151; }
.bm-custom-tag {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.68rem; font-weight: 700;
    background: #e3f2fd; color: #1565c0;
    border-radius: 6px; padding: 2px 6px;
    margin-left: 4px; vertical-align: middle;
}

/* Event date */
.bm-event-date { font-weight: 700; color: #1f2937; }

/* Event type */
.bm-event-type-badge {
    display: inline-flex; align-items: center;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.72rem; font-weight: 700;
    background: #f0fdf4; color: #166534;
    border: 1px solid #bbf7d0;
    text-transform: capitalize;
}

/* Amount */
.bm-amount-total { font-weight: 800; font-size: 0.95rem; color: #1f2937; }
.bm-amount-paid  { color: #16a34a !important; }
.bm-amount-due   { color: #dc2626 !important; }

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

.booking-status-badge { cursor: default; user-select: none; }

/* Progress bar */
.bm-progress {
    height: 4px; background: #f3f4f6; border-radius: 99px; overflow: hidden;
    min-width: 70px;
}
.bm-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #f59e0b, #d97706);
    border-radius: 99px;
    transition: width 0.4s ease;
}
.bm-progress-pct {
    font-size: 0.68rem; color: #d97706; font-weight: 600; margin-top: 2px;
}

/* Action buttons */
.bm-actions { display: flex; align-items: center; justify-content: center; gap: 0.4rem; }
.bm-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    font-size: 0.8rem; border: none; cursor: pointer;
    transition: all 0.2s ease; text-decoration: none;
    background: transparent;
}
.bm-action-view   { color: #2563eb; background: #dbeafe; }
.bm-action-view:hover { background: #2563eb; color: #fff; }
.bm-action-edit   { color: #d97706; background: #fef3c7; }
.bm-action-edit:hover { background: #d97706; color: #fff; }
.bm-action-delete { color: #dc2626; background: #fee2e2; }
.bm-action-delete:hover { background: #dc2626; color: #fff; }

/* Empty state */
.bm-empty-state { text-align: center; padding: 4rem 2rem !important; }
.bm-empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1rem; }
.bm-empty-title { font-size: 1.1rem; font-weight: 700; color: #374151; margin-bottom: 0.5rem; }
.bm-empty-sub { font-size: 0.875rem; color: #9ca3af; }
.bm-empty-sub a { color: #4CAF50; font-weight: 600; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .bm-page-header { flex-direction: column; align-items: flex-start; padding: 1.25rem; }
    .bm-header-actions { width: 100%; justify-content: flex-start; order: -1; }
    .bm-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .bm-card-toolbar { flex-direction: column; align-items: flex-start; }
    .bm-filter-pills { gap: 0.25rem; }
    .bm-pill { font-size: 0.72rem; padding: 0.25rem 0.7rem; }
    .bm-header-title { font-size: 1.2rem; }
}
@media (max-width: 480px) {
    .bm-stats-grid { grid-template-columns: 1fr 1fr; }
    .bm-stat-card { padding: 0.9rem 1rem; }
    .bm-stat-value { font-size: 1.2rem; }
}

/* ── Reduced motion ──────────────────────────────────────────── */
@media (prefers-reduced-motion: no-preference) {
    .bm-btn-primary:hover { transform: translateY(-2px); }
    .bm-stat-card:hover   { transform: translateY(-3px); }
    .bm-stat-selected     { transform: translateY(-3px); }
    .bm-action-btn:hover  { transform: scale(1.1); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Read-only status badge feedback
    document.querySelectorAll('.booking-status-badge').forEach(function (badge) {
        badge.addEventListener('click', function () {
            alert('Booking status cannot be edited from this page. Open the booking to update it.');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
