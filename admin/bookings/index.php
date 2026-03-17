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

// Whitelist validation - only allow known filter values
$valid_filters = ['active', 'pending', 'payment_submitted', 'confirmed', 'completed', 'cancelled', 'all'];
if (!in_array($status_filter, $valid_filters)) {
    $status_filter = 'active'; // Fall back to default if invalid value provided
}

// Build query based on filter
$base_query = "SELECT b.*, 
                    c.full_name, c.phone, c.email,
                    h.name as hall_name, 
                    v.name as venue_name,
                    COALESCE((SELECT SUM(paid_amount) FROM payments WHERE booking_id = b.id AND payment_status = 'verified'), 0) as total_paid
                    FROM bookings b
                    INNER JOIN customers c ON b.customer_id = c.id
                    INNER JOIN halls h ON b.hall_id = h.id
                    INNER JOIN venues v ON h.venue_id = v.id";

// Apply filter based on selection
if ($status_filter === 'active') {
    // Active bookings - pending, payment_submitted, confirmed (excluding completed and cancelled)
    $base_query .= " WHERE b.booking_status IN ('pending', 'payment_submitted', 'confirmed')";
} elseif ($status_filter === 'completed') {
    // Completed bookings only
    $base_query .= " WHERE b.booking_status = 'completed'";
} elseif ($status_filter === 'cancelled') {
    // Cancelled bookings only
    $base_query .= " WHERE b.booking_status = 'cancelled'";
} elseif ($status_filter === 'pending') {
    // Pending bookings only
    $base_query .= " WHERE b.booking_status = 'pending'";
} elseif ($status_filter === 'confirmed') {
    // Confirmed bookings only
    $base_query .= " WHERE b.booking_status = 'confirmed'";
} elseif ($status_filter === 'payment_submitted') {
    // Payment submitted bookings only
    $base_query .= " WHERE b.booking_status = 'payment_submitted'";
}
// 'all' - no filter, show everything

$base_query .= " ORDER BY b.created_at DESC";
$stmt = $db->query($base_query);
$bookings = $stmt->fetchAll();
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Enhanced Booking Management Card -->
<div class="card booking-management-card">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0"><i class="fas fa-calendar-check text-primary"></i> All Bookings</h5>
                <small class="text-muted">Manage and track all venue bookings</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Status Filter -->
                <div class="d-flex align-items-center">
                    <label for="statusFilter" class="me-2 mb-0 text-muted small fw-bold">
                        <i class="fas fa-filter"></i> Filter:
                    </label>
                    <select id="statusFilter" class="form-select form-select-sm" style="min-width: 160px;" onchange="applyStatusFilter(this.value)">
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Bookings</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                        <option value="payment_submitted" <?php echo $status_filter === 'payment_submitted' ? 'selected' : ''; ?>>Payment Submitted</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed Only</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed Only</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled Only</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Bookings</option>
                    </select>
                </div>
                <a href="calendar.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-calendar-alt"></i> Calendar View
                </a>
                <a href="add.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Add Booking
                </a>
            </div>
        </div>
        <!-- Active filter indicator -->
        <?php if ($status_filter !== 'all'): ?>
        <div class="mt-2">
            <span class="badge bg-info">
                <i class="fas fa-filter"></i> 
                <?php 
                $filter_labels = [
                    'active' => 'Showing: Active Bookings (Pending, Payment Submitted, Confirmed)',
                    'pending' => 'Showing: Pending Bookings',
                    'payment_submitted' => 'Showing: Payment Submitted',
                    'confirmed' => 'Showing: Confirmed Bookings',
                    'completed' => 'Showing: Completed Bookings',
                    'cancelled' => 'Showing: Cancelled Bookings'
                ];
                echo $filter_labels[$status_filter] ?? 'Filtered';
                ?>
            </span>
            <a href="?status_filter=all" class="btn btn-sm btn-link text-decoration-none">
                <i class="fas fa-times"></i> Clear Filter
            </a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable booking-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="fw-bold">Booking #</th>
                        <th class="fw-bold">Customer</th>
                        <th class="fw-bold">Venue/Hall</th>
                        <th class="fw-bold">Event Date</th>
                        <th class="fw-bold">Event Type</th>
                        <th class="fw-bold">Guests</th>
                        <th class="fw-bold">Amount</th>
                        <th class="fw-bold">Booking Status</th>
                        <th class="fw-bold">Payment Status</th>
                        <th class="fw-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        // Calculate due amount using correct formula (never negative)
                        $balance_due = max(0, $booking['grand_total'] - $booking['total_paid']);
                        $payment_percentage = $booking['grand_total'] > 0 ? ($booking['total_paid'] / $booking['grand_total']) * 100 : 0;
                    ?>
                        <tr class="booking-row">
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <div class="customer-info">
                                    <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong><br>
                                    <small class="text-muted">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['phone']); ?>
                                    </small>
                                    <?php if (!empty($booking['email'])): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($booking['email']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($booking['venue_name']); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($booking['hall_name']); ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking['shift']); ?>
                                    <?php if (!empty($booking['start_time']) && !empty($booking['end_time'])): ?>
                                        <br><i class="fas fa-hourglass-start"></i> <?php echo formatBookingTime($booking['start_time']); ?> – <?php echo formatBookingTime($booking['end_time']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?php echo htmlspecialchars($booking['event_type']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $booking['number_of_guests']; ?></strong>
                                <small class="text-muted">guests</small>
                            </td>
                            <td>
                                <div class="amount-details">
                                    <strong class="text-success fs-6"><?php echo formatCurrency($booking['grand_total']); ?></strong>
                                    <?php if ($booking['total_paid'] > 0): ?>
                                        <br>
                                        <small class="text-muted">Paid: <?php echo formatCurrency($booking['total_paid']); ?></small>
                                        <?php if ($balance_due > 0): ?>
                                            <br>
                                            <small class="text-danger">Due: <?php echo formatCurrency($balance_due); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                        ($booking['booking_status'] == 'payment_submitted' ? 'info' :
                                        ($booking['booking_status'] == 'pending' ? 'warning' : 
                                        ($booking['booking_status'] == 'cancelled' ? 'danger' : 
                                        ($booking['booking_status'] == 'completed' ? 'primary' : 'secondary')))); 
                                ?> px-2 py-1 booking-status-badge"
                                    title="Booking status is read-only on this page. Use the View page to update it."
                                    role="status"
                                    aria-label="Booking status: <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?> (read-only)">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Payment Status (read-only; change via View Details) -->
                                <span class="badge bg-<?php 
                                    echo $booking['payment_status'] == 'paid' ? 'success' : 
                                        ($booking['payment_status'] == 'partial' ? 'warning' : 
                                        ($booking['payment_status'] == 'cancelled' ? 'secondary' : 'danger')); 
                                ?> px-2 py-1"
                                    title="Payment status is read-only on this page. Use View Details to update it."
                                    role="status"
                                    aria-label="Payment status: <?php echo htmlspecialchars(ucfirst($booking['payment_status']), ENT_QUOTES, 'UTF-8'); ?> (read-only)">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                                <?php if ($payment_percentage > 0 && $payment_percentage < 100): ?>
                                    <div class="progress mt-1" style="height: 3px; min-width: 60px;">
                                        <div class="progress-bar bg-warning" role="progressbar" 
                                            style="width: <?php echo $payment_percentage; ?>%" 
                                            aria-valuenow="<?php echo $payment_percentage; ?>" 
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="View Details"
                                       data-bs-toggle="tooltip">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-warning" 
                                       title="Edit Booking"
                                       data-bs-toggle="tooltip">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="delete.php" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                        <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" 
                                                class="btn btn-sm btn-danger" 
                                                title="Delete"
                                                data-bs-toggle="tooltip">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enhanced Styling -->
<style>
.booking-management-card {
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.booking-table {
    border-collapse: separate;
    border-spacing: 0;
}

.booking-table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 10px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.booking-row {
    transition: all 0.2s ease;
}

.booking-row:hover {
    background-color: #f8f9fa;
}

.booking-table td {
    padding: 12px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.customer-info strong {
    color: #2c3e50;
}

.amount-details strong {
    display: block;
    font-size: 1.1rem;
}

.booking-status-badge {
    cursor: not-allowed;
    user-select: none;
}

.payment-status-container {
    min-width: 80px;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

</style>

<script>
// Apply status filter - redirect with filter parameter while preserving other query params
function applyStatusFilter(value) {
    var url = new URL(window.location.href);
    url.searchParams.set('status_filter', value);
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle booking status badge click (read-only enforcement)
    document.querySelectorAll('.booking-status-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            alert('Booking status cannot be edited from this page. This field is read-only.');
        });
    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
