<?php
$page_title = 'View Booking Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch booking details
$booking = getBookingDetails($booking_id);

if (!$booking) {
    header('Location: index.php');
    exit;
}
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-check"></i> Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></h4>
            <div>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $booking_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Booking
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Booking Details -->
    <div class="col-md-8">
        <!-- Customer Information -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($booking['full_name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Phone:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>">
                            <?php echo htmlspecialchars($booking['phone']); ?>
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong><br>
                        <?php if ($booking['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>">
                                <?php echo htmlspecialchars($booking['email']); ?>
                            </a>
                        <?php else: ?>
                            <em class="text-muted">Not provided</em>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Address:</strong><br>
                        <?php echo $booking['address'] ? htmlspecialchars($booking['address']) : '<em class="text-muted">Not provided</em>'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Details -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Event Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Venue:</strong><br>
                        <?php echo htmlspecialchars($booking['venue_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Hall:</strong><br>
                        <?php echo htmlspecialchars($booking['hall_name']); ?><br>
                        <small class="text-muted">Capacity: <?php echo $booking['capacity']; ?> guests</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <strong>Event Date:</strong><br>
                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <strong>Shift:</strong><br>
                        <?php echo ucfirst($booking['shift']); ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <strong>Event Type:</strong><br>
                        <?php echo htmlspecialchars($booking['event_type']); ?>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Number of Guests:</strong><br>
                    <?php echo $booking['number_of_guests']; ?> guests
                </div>
                <?php if ($booking['special_requests']): ?>
                <div class="mb-3">
                    <strong>Special Requests:</strong><br>
                    <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Menus -->
        <?php if (count($booking['menus']) > 0): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Selected Menus</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Price per Person</th>
                                <th>Guests</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['menus'] as $menu): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($menu['menu_name']); ?>
                                    <?php if (!empty($menu['items'])): ?>
                                        <?php $safeMenuId = intval($menu['menu_id']); ?>
                                        <button class="btn btn-sm btn-link p-0 ms-2" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#menu-items-<?php echo $safeMenuId; ?>" 
                                                aria-expanded="false">
                                            <i class="fas fa-list"></i> View Items
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                <td><?php echo $menu['number_of_guests']; ?></td>
                                <td><?php echo formatCurrency($menu['total_price']); ?></td>
                            </tr>
                            <?php if (!empty($menu['items'])): ?>
                            <tr class="collapse" id="menu-items-<?php echo $safeMenuId; ?>">
                                <td colspan="4" class="bg-light">
                                    <div class="p-2">
                                        <strong class="small">Menu Items:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php 
                                            $items_by_category = [];
                                            foreach ($menu['items'] as $item) {
                                                $category = !empty($item['category']) ? $item['category'] : 'Other';
                                                $items_by_category[$category][] = $item;
                                            }
                                            
                                            foreach ($items_by_category as $category => $items): 
                                            ?>
                                                <?php if (count($items_by_category) > 1): ?>
                                                    <li class="small"><strong><?php echo htmlspecialchars($category); ?>:</strong>
                                                        <ul>
                                                            <?php foreach ($items as $item): ?>
                                                                <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </li>
                                                <?php else: ?>
                                                    <?php foreach ($items as $item): ?>
                                                        <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Services -->
        <?php if (count($booking['services']) > 0): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> Additional Services</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['services'] as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td><?php echo formatCurrency($service['price']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Booking Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Booking Status:</strong><br>
                    <span class="badge bg-<?php 
                        echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                            ($booking['booking_status'] == 'pending' ? 'warning' : 
                            ($booking['booking_status'] == 'cancelled' ? 'danger' : 'info')); 
                    ?> fs-6">
                        <?php echo ucfirst($booking['booking_status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Payment Status:</strong><br>
                    <span class="badge bg-<?php 
                        echo $booking['payment_status'] == 'paid' ? 'success' : 
                            ($booking['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                    ?> fs-6">
                        <?php echo ucfirst($booking['payment_status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Booked On:</strong><br>
                    <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calculator"></i> Payment Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Hall Price:</span>
                    <strong><?php echo formatCurrency($booking['hall_price']); ?></strong>
                </div>
                <?php if ($booking['menu_total'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Menu Total:</span>
                    <strong><?php echo formatCurrency($booking['menu_total']); ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($booking['services_total'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Services Total:</span>
                    <strong><?php echo formatCurrency($booking['services_total']); ?></strong>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <strong><?php echo formatCurrency($booking['subtotal']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                    <strong><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <h5>Grand Total:</h5>
                    <h5 class="text-success"><?php echo formatCurrency($booking['grand_total']); ?></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .card-header, nav, footer {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
