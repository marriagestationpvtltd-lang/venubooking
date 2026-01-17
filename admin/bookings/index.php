<?php
$page_title = 'Manage Bookings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get all bookings with payment summary
$stmt = $db->query("SELECT b.*, 
                    c.full_name, c.phone, c.email,
                    h.name as hall_name, 
                    v.name as venue_name,
                    COALESCE((SELECT SUM(paid_amount) FROM payments WHERE booking_id = b.id AND payment_status = 'verified'), 0) as total_paid
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

<!-- Enhanced Booking Management Card -->
<div class="card booking-management-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0"><i class="fas fa-calendar-check text-primary"></i> All Bookings</h5>
            <small class="text-muted">Manage and track all venue bookings</small>
        </div>
        <div>
            <a href="calendar.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-calendar-alt"></i> Calendar View
            </a>
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Booking
            </a>
        </div>
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
                                    <i class="fas fa-sun"></i> <?php echo ucfirst($booking['shift']); ?>
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
                                ?> px-2 py-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Quick Payment Status Update -->
                                <div class="payment-status-container" data-booking-id="<?php echo $booking['id']; ?>">
                                    <select class="form-select form-select-sm payment-status-select 
                                        <?php 
                                            echo $booking['payment_status'] == 'paid' ? 'status-paid' : 
                                                ($booking['payment_status'] == 'partial' ? 'status-partial' : 
                                                ($booking['payment_status'] == 'cancelled' ? 'status-cancelled' : 'status-pending')); 
                                        ?>" 
                                        data-booking-id="<?php echo $booking['id']; ?>"
                                        data-current-status="<?php echo $booking['payment_status']; ?>">
                                        <option value="pending" <?php echo ($booking['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="partial" <?php echo ($booking['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                        <option value="paid" <?php echo ($booking['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                        <option value="cancelled" <?php echo ($booking['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <?php if ($payment_percentage > 0 && $payment_percentage < 100): ?>
                                        <div class="progress mt-1" style="height: 3px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                style="width: <?php echo $payment_percentage; ?>%" 
                                                aria-valuenow="<?php echo $payment_percentage; ?>" 
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
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

.payment-status-container {
    min-width: 130px;
}

.payment-status-select {
    font-size: 0.875rem;
    font-weight: 500;
    border: 2px solid #dee2e6;
    padding: 4px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-status-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
    border-color: #4CAF50;
}

.payment-status-select.status-paid {
    background-color: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.payment-status-select.status-partial {
    background-color: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.payment-status-select.status-pending {
    background-color: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

.payment-status-select.status-cancelled {
    background-color: #e2e3e5;
    border-color: #6c757d;
    color: #383d41;
}

.payment-status-select.updating {
    opacity: 0.6;
    pointer-events: none;
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

/* Toast notification styles */
.toast-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
}

.custom-toast {
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<!-- Toast Container for Notifications -->
<div class="toast-container"></div>

<!-- JavaScript for Quick Payment Status Update -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle payment status change
    const paymentStatusSelects = document.querySelectorAll('.payment-status-select');
    
    paymentStatusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const bookingId = this.dataset.bookingId;
            const newStatus = this.value;
            const oldStatus = this.dataset.currentStatus;
            const selectElement = this;
            
            // Confirm the change
            if (!confirm(`Are you sure you want to change payment status from "${oldStatus}" to "${newStatus}"?`)) {
                // Revert to old value
                this.value = oldStatus;
                return;
            }
            
            // Add updating class
            selectElement.classList.add('updating');
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('payment_status', newStatus);
            
            fetch('update-payment-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                selectElement.classList.remove('updating');
                
                if (data.success) {
                    // Update the current status
                    selectElement.dataset.currentStatus = newStatus;
                    
                    // Update visual styling
                    selectElement.classList.remove('status-pending', 'status-partial', 'status-paid', 'status-cancelled');
                    selectElement.classList.add('status-' + newStatus);
                    
                    // Show success toast
                    showToast('Success', 'Payment status updated successfully', 'success');
                    
                    // Show warning if backward flow
                    if (data.is_backward) {
                        showToast('Warning', 'You moved the payment status backward in the flow', 'warning');
                    }
                } else {
                    // Revert to old status
                    selectElement.value = oldStatus;
                    
                    // Show error toast
                    showToast('Error', data.message || 'Failed to update payment status', 'danger');
                }
            })
            .catch(error => {
                selectElement.classList.remove('updating');
                selectElement.value = oldStatus;
                
                // Show error toast
                showToast('Error', 'An error occurred. Please try again.', 'danger');
                console.error('Error:', error);
            });
        });
    });
    
    // Toast notification function
    function showToast(title, message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        
        const toastId = 'toast-' + Date.now();
        const bgClass = type === 'success' ? 'bg-success' : 
                       type === 'danger' ? 'bg-danger' : 
                       type === 'warning' ? 'bg-warning' : 'bg-info';
        
        const toastHTML = `
            <div id="${toastId}" class="toast custom-toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${bgClass} text-white">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        
        toast.show();
        
        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
