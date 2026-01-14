<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Get statistics
$stats = [];

// Total bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = $stmt->fetch()['count'];

// Pending bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'");
$stats['pending_bookings'] = $stmt->fetch()['count'];

// Confirmed bookings
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'confirmed'");
$stats['confirmed_bookings'] = $stmt->fetch()['count'];

// Total revenue
$stmt = $db->query("SELECT SUM(grand_total) as total FROM bookings WHERE booking_status != 'cancelled'");
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// This month's revenue
$stmt = $db->query("SELECT SUM(grand_total) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND booking_status != 'cancelled'");
$stats['month_revenue'] = $stmt->fetch()['total'] ?? 0;

// Total venues
$stmt = $db->query("SELECT COUNT(*) as count FROM venues WHERE status = 'active'");
$stats['total_venues'] = $stmt->fetch()['count'];

// Total halls
$stmt = $db->query("SELECT COUNT(*) as count FROM halls WHERE status = 'active'");
$stats['total_halls'] = $stmt->fetch()['count'];

// Total customers
$stmt = $db->query("SELECT COUNT(*) as count FROM customers");
$stats['total_customers'] = $stmt->fetch()['count'];

// Get recent bookings
$stmt = $db->query("SELECT b.*, c.full_name, h.name as hall_name, v.name as venue_name 
                    FROM bookings b
                    INNER JOIN customers c ON b.customer_id = c.id
                    INNER JOIN halls h ON b.hall_id = h.id
                    INNER JOIN venues v ON h.venue_id = v.id
                    ORDER BY b.created_at DESC
                    LIMIT 10");
$recent_bookings = $stmt->fetchAll();

// Get upcoming events
$stmt = $db->query("SELECT b.*, c.full_name, h.name as hall_name, v.name as venue_name 
                    FROM bookings b
                    INNER JOIN customers c ON b.customer_id = c.id
                    INNER JOIN halls h ON b.hall_id = h.id
                    INNER JOIN venues v ON h.venue_id = v.id
                    WHERE b.event_date >= CURDATE()
                    AND b.booking_status != 'cancelled'
                    ORDER BY b.event_date ASC
                    LIMIT 10");
$upcoming_events = $stmt->fetchAll();
?>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_bookings']; ?></h3>
                    <p class="text-muted mb-0">Total Bookings</p>
                </div>
                <div class="stat-icon bg-primary text-white">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['pending_bookings']; ?></h3>
                    <p class="text-muted mb-0">Pending</p>
                </div>
                <div class="stat-icon bg-warning text-white">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['confirmed_bookings']; ?></h3>
                    <p class="text-muted mb-0">Confirmed</p>
                </div>
                <div class="stat-icon bg-success text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                    <p class="text-muted mb-0">Total Revenue</p>
                </div>
                <div class="stat-icon bg-info text-white">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['month_revenue']); ?></h3>
                    <p class="text-muted mb-0">This Month</p>
                </div>
                <div class="stat-icon bg-success text-white">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_venues']; ?></h3>
                    <p class="text-muted mb-0">Active Venues</p>
                </div>
                <div class="stat-icon bg-secondary text-white">
                    <i class="fas fa-building"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_halls']; ?></h3>
                    <p class="text-muted mb-0">Active Halls</p>
                </div>
                <div class="stat-icon bg-dark text-white">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_customers']; ?></h3>
                    <p class="text-muted mb-0">Customers</p>
                </div>
                <div class="stat-icon bg-primary text-white">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Bookings -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Recent Bookings</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_bookings)): ?>
                    <p class="text-muted">No bookings yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Customer</th>
                                    <th>Event Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><a href="bookings/view.php?id=<?php echo $booking['id']; ?>"><?php echo $booking['booking_number']; ?></a></td>
                                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                                    ($booking['booking_status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Events -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Upcoming Events</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_events)): ?>
                    <p class="text-muted">No upcoming events.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
