<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireLogin();

$db = getDB();

// Get statistics
// Total bookings
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
$totalBookings = $stmt->fetch()['total'];

// Today's bookings
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) = CURDATE()");
$todayBookings = $stmt->fetch()['total'];

// Pending bookings
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'pending'");
$pendingBookings = $stmt->fetch()['total'];

// Total revenue
$stmt = $db->query("SELECT SUM(total_cost) as total FROM bookings WHERE booking_status != 'cancelled'");
$totalRevenue = $stmt->fetch()['total'] ?? 0;

// This month revenue
$stmt = $db->query("SELECT SUM(total_cost) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND booking_status != 'cancelled'");
$monthRevenue = $stmt->fetch()['total'] ?? 0;

// Total customers
$stmt = $db->query("SELECT COUNT(*) as total FROM customers");
$totalCustomers = $stmt->fetch()['total'];

// Recent bookings
$recentStmt = $db->query("SELECT b.*, c.full_name, v.venue_name, h.hall_name 
                          FROM bookings b
                          JOIN customers c ON b.customer_id = c.id
                          JOIN venues v ON b.venue_id = v.id
                          JOIN halls h ON b.hall_id = h.id
                          ORDER BY b.created_at DESC
                          LIMIT 10");
$recentBookings = $recentStmt->fetchAll();

// Upcoming events (next 7 days)
$upcomingStmt = $db->query("SELECT b.*, c.full_name, v.venue_name, h.hall_name 
                            FROM bookings b
                            JOIN customers c ON b.customer_id = c.id
                            JOIN venues v ON b.venue_id = v.id
                            JOIN halls h ON b.hall_id = h.id
                            WHERE b.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                            AND b.booking_status != 'cancelled'
                            ORDER BY b.booking_date ASC");
$upcomingEvents = $upcomingStmt->fetchAll();

// Monthly revenue chart data (last 12 months)
$monthlyData = $db->query("SELECT 
                           DATE_FORMAT(created_at, '%Y-%m') as month,
                           SUM(total_cost) as revenue
                           FROM bookings
                           WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                           AND booking_status != 'cancelled'
                           GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                           ORDER BY month ASC")->fetchAll();

$months = array_map(function($row) { return date('M Y', strtotime($row['month'] . '-01')); }, $monthlyData);
$revenues = array_map(function($row) { return $row['revenue']; }, $monthlyData);

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/admin-header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="page-header">
    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
    <p class="text-muted">Welcome back, <?php echo clean($_SESSION['full_name']); ?>!</p>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h3><?php echo $totalBookings; ?></h3>
            <p>Total Bookings</p>
            <small><i class="fas fa-arrow-up"></i> <?php echo $todayBookings; ?> today</small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h3><?php echo formatCurrency($totalRevenue); ?></h3>
            <p>Total Revenue</p>
            <small><i class="fas fa-calendar-alt"></i> <?php echo formatCurrency($monthRevenue); ?> this month</small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h3><?php echo $pendingBookings; ?></h3>
            <p>Pending Bookings</p>
            <small>Awaiting confirmation</small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3><?php echo $totalCustomers; ?></h3>
            <p>Total Customers</p>
            <small>Registered users</small>
        </div>
    </div>
</div>

<!-- Revenue Chart -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="table-card">
            <h5><i class="fas fa-chart-line"></i> Revenue Trend (Last 12 Months)</h5>
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="table-card">
            <h5><i class="fas fa-calendar-day"></i> Upcoming Events</h5>
            <?php if (empty($upcomingEvents)): ?>
                <p class="text-muted">No upcoming events in the next 7 days</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <a href="/admin/bookings/view.php?id=<?php echo $event['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo clean($event['venue_name']); ?></h6>
                                <small><?php echo formatDate($event['booking_date'], 'M d'); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo clean($event['full_name']); ?> - <?php echo clean($event['event_type']); ?></p>
                            <small class="text-muted"><?php echo clean($event['hall_name']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="row mt-4">
    <div class="col-12">
        <div class="table-card">
            <h5><i class="fas fa-list"></i> Recent Bookings</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Customer</th>
                            <th>Venue/Hall</th>
                            <th>Event Date</th>
                            <th>Event Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo clean($booking['booking_number']); ?></strong></td>
                                <td><?php echo clean($booking['full_name']); ?></td>
                                <td>
                                    <?php echo clean($booking['venue_name']); ?><br>
                                    <small class="text-muted"><?php echo clean($booking['hall_name']); ?></small>
                                </td>
                                <td><?php echo formatDate($booking['booking_date']); ?></td>
                                <td><?php echo clean($booking['event_type']); ?></td>
                                <td><?php echo formatCurrency($booking['total_cost']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'cancelled' => 'danger',
                                        'completed' => 'info'
                                    ];
                                    $class = $statusClass[$booking['booking_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $class; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/admin/bookings/view.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="/admin/bookings/list.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Revenue Chart
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Revenue (<?php echo CURRENCY_SYMBOL; ?>)',
                    data: <?php echo json_encode($revenues); ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }).format(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
