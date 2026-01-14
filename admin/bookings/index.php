<?php
$page_title = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$success_message = '';
$error_message = '';

if (isset($_GET['deleted'])) {
    $success_message = 'Booking deleted successfully!';
}

if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

// Get all bookings
$stmt = $db->query("SELECT b.*, c.full_name, c.phone, h.name as hall_name, v.name as venue_name 
                    FROM bookings b
                    INNER JOIN customers c ON b.customer_id = c.id
                    INNER JOIN halls h ON b.hall_id = h.id
                    INNER JOIN venues v ON h.venue_id = v.id
                    ORDER BY b.created_at DESC");
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

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-calendar-check"></i> All Bookings</h5>
        <a href="add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Booking
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Customer</th>
                        <th>Venue/Hall</th>
                        <th>Event Date</th>
                        <th>Event Type</th>
                        <th>Guests</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($booking['full_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($booking['phone']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($booking['venue_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($booking['hall_name']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($booking['event_date'])); ?><br>
                                <small class="text-muted"><?php echo ucfirst($booking['shift']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($booking['event_type']); ?></td>
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
                                <span class="badge bg-<?php 
                                    echo $booking['payment_status'] == 'paid' ? 'success' : 
                                        ($booking['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
