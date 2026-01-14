<?php
$page_title = 'View Customer Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch customer details
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: index.php');
    exit;
}

// Fetch booking history
$bookings_stmt = $db->prepare("SELECT b.*, h.name as hall_name, v.name as venue_name
                                FROM bookings b
                                INNER JOIN halls h ON b.hall_id = h.id
                                INNER JOIN venues v ON h.venue_id = v.id
                                WHERE b.customer_id = ?
                                ORDER BY b.event_date DESC");
$bookings_stmt->execute([$customer_id]);
$bookings = $bookings_stmt->fetchAll();

// Calculate statistics
$total_bookings = count($bookings);
$total_spent = 0;
$confirmed_bookings = 0;
foreach ($bookings as $booking) {
    $total_spent += $booking['grand_total'];
    if ($booking['booking_status'] == 'confirmed') {
        $confirmed_bookings++;
    }
}
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-user"></i> <?php echo htmlspecialchars($customer['full_name']); ?></h4>
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Customer
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Customer Details -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Full Name:</strong><br>
                        <?php echo htmlspecialchars($customer['full_name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Phone:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>">
                            <?php echo htmlspecialchars($customer['phone']); ?>
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong><br>
                        <?php if ($customer['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        <?php else: ?>
                            <em class="text-muted">Not provided</em>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Customer Since:</strong><br>
                        <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Address:</strong><br>
                    <?php echo $customer['address'] ? nl2br(htmlspecialchars($customer['address'])) : '<em class="text-muted">Not provided</em>'; ?>
                </div>
            </div>
        </div>

        <!-- Booking History -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Booking History (<?php echo $total_bookings; ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($total_bookings > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Venue/Hall</th>
                                    <th>Event Date</th>
                                    <th>Guests</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['venue_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['hall_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($booking['event_date'])); ?><br>
                                            <small class="text-muted"><?php echo ucfirst($booking['shift']); ?></small>
                                        </td>
                                        <td><?php echo $booking['number_of_guests']; ?></td>
                                        <td><?php echo formatCurrency($booking['grand_total']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                                    ($booking['booking_status'] == 'pending' ? 'warning' : 
                                                    ($booking['booking_status'] == 'cancelled' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../bookings/view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> This customer has no bookings yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Sidebar -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Total Bookings</h6>
                    <h3 class="mb-0"><?php echo $total_bookings; ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Confirmed Bookings</h6>
                    <h3 class="mb-0"><?php echo $confirmed_bookings; ?></h3>
                </div>
                <hr>
                <div>
                    <h6 class="text-muted mb-1">Total Spent</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($total_spent); ?></h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-warning btn-block w-100 mb-2">
                    <i class="fas fa-edit"></i> Edit Customer
                </a>
                <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="btn btn-success btn-block w-100 mb-2">
                    <i class="fas fa-phone"></i> Call Customer
                </a>
                <?php if ($customer['email']): ?>
                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-info btn-block w-100 mb-2">
                    <i class="fas fa-envelope"></i> Send Email
                </a>
                <?php endif; ?>
                <a href="../bookings/add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary btn-block w-100">
                    <i class="fas fa-plus"></i> New Booking
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
