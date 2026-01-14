<?php
/**
 * Booking Confirmation Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check if booking was just completed
if (!isset($_SESSION['last_booking'])) {
    redirect('/index.php');
}

$booking_id = $_SESSION['last_booking']['booking_id'];
$booking_number = $_SESSION['last_booking']['booking_number'];

$db = getDB();

// Get complete booking details
$sql = "SELECT b.*, c.full_name, c.email, c.phone, c.address,
        v.venue_name, v.location, v.address as venue_address,
        h.hall_name, h.capacity
        FROM bookings b
        JOIN customers c ON b.customer_id = c.id
        JOIN venues v ON b.venue_id = v.id
        JOIN halls h ON b.hall_id = h.id
        WHERE b.id = :booking_id";

$stmt = $db->prepare($sql);
$stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
$stmt->execute();
$booking = $stmt->fetch();

if (!$booking) {
    redirect('/index.php');
}

// Get booking menus
$menuStmt = $db->prepare("SELECT bm.*, m.menu_name 
                          FROM booking_menus bm
                          JOIN menus m ON bm.menu_id = m.id
                          WHERE bm.booking_id = :booking_id");
$menuStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
$menuStmt->execute();
$bookingMenus = $menuStmt->fetchAll();

// Get booking services
$serviceStmt = $db->prepare("SELECT bs.*, s.service_name 
                             FROM booking_services bs
                             JOIN additional_services s ON bs.service_id = s.id
                             WHERE bs.booking_id = :booking_id");
$serviceStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
$serviceStmt->execute();
$bookingServices = $serviceStmt->fetchAll();

$pageTitle = 'Booking Confirmed - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<section class="section" style="background: linear-gradient(135deg, #e8f5e9, #f1f8e9); padding: 60px 0;">
    <div class="container">
        <div class="text-center mb-5">
            <div style="width: 100px; height: 100px; background: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);">
                <i class="fas fa-check" style="font-size: 3rem; color: white;"></i>
            </div>
            <h1 style="color: var(--primary-dark); font-size: 2.5rem; font-weight: 700; margin-bottom: 20px;">
                Booking Confirmed!
            </h1>
            <p style="font-size: 1.25rem; color: var(--medium-gray);">
                Thank you for choosing <?php echo APP_NAME; ?>
            </p>
            <p style="font-size: 1.125rem; color: var(--dark-gray); margin-top: 15px;">
                Your booking number is: <strong style="color: var(--primary-green); font-size: 1.5rem;"><?php echo clean($booking_number); ?></strong>
            </p>
        </div>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card" style="border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <div class="card-body" style="padding: 40px;">
                        <h3 style="color: var(--primary-dark); margin-bottom: 30px; border-bottom: 2px solid var(--primary-green); padding-bottom: 15px;">
                            <i class="fas fa-file-alt"></i> Booking Details
                        </h3>
                        
                        <!-- Customer Information -->
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-user"></i> Customer Information
                            </h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Name:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['full_name']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Phone:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['phone']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Email:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['email']); ?></p>
                                </div>
                                <?php if ($booking['address']): ?>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Address:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['address']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <hr style="margin: 30px 0;">
                        
                        <!-- Event Details -->
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-calendar-check"></i> Event Details
                            </h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Event Type:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['event_type']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Date:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo formatDate($booking['booking_date']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Shift:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo ucfirst(str_replace('_', ' ', $booking['shift'])); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Number of Guests:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo $booking['number_of_guests']; ?> persons</p>
                                </div>
                            </div>
                        </div>
                        
                        <hr style="margin: 30px 0;">
                        
                        <!-- Venue & Hall -->
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-building"></i> Venue & Hall
                            </h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Venue:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['venue_name']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Location:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['location']); ?></p>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label style="font-weight: 600; color: var(--medium-gray); font-size: 0.9rem;">Hall:</label>
                                    <p style="margin: 5px 0; font-size: 1.1rem;"><?php echo clean($booking['hall_name']); ?> (Capacity: <?php echo $booking['capacity']; ?> pax)</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($bookingMenus)): ?>
                        <hr style="margin: 30px 0;">
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-utensils"></i> Selected Menus
                            </h5>
                            <?php foreach ($bookingMenus as $menu): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo clean($menu['menu_name']); ?> (<?php echo $menu['quantity']; ?> pax @ <?php echo formatCurrency($menu['price_per_person']); ?>)</span>
                                <strong><?php echo formatCurrency($menu['total_price']); ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bookingServices)): ?>
                        <hr style="margin: 30px 0;">
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-concierge-bell"></i> Additional Services
                            </h5>
                            <?php foreach ($bookingServices as $service): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo clean($service['service_name']); ?></span>
                                <strong><?php echo formatCurrency($service['total_price']); ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <hr style="margin: 30px 0;">
                        
                        <!-- Payment Summary -->
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-receipt"></i> Payment Summary
                            </h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                                <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3" style="font-size: 1.25rem; font-weight: 700; color: var(--primary-dark);">
                                <span>Total Amount:</span>
                                <span><?php echo formatCurrency($booking['total_cost']); ?></span>
                            </div>
                            <?php if ($booking['advance_payment'] > 0): ?>
                            <div class="d-flex justify-content-between" style="padding: 15px; background: #e8f5e9; border-radius: 8px;">
                                <span style="font-weight: 600;">Advance Paid:</span>
                                <span style="font-weight: 700; color: var(--primary-green);"><?php echo formatCurrency($booking['advance_payment']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mt-2" style="padding: 15px; background: #fff3cd; border-radius: 8px;">
                                <span style="font-weight: 600;">Balance Due:</span>
                                <span style="font-weight: 700; color: #f57c00;"><?php echo formatCurrency($booking['total_cost'] - $booking['advance_payment']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($booking['special_requests']): ?>
                        <hr style="margin: 30px 0;">
                        <div class="mb-4">
                            <h5 style="color: var(--primary-green); margin-bottom: 15px;">
                                <i class="fas fa-comment-dots"></i> Special Requests
                            </h5>
                            <p style="background: #f5f5f5; padding: 15px; border-radius: 8px;">
                                <?php echo nl2br(clean($booking['special_requests'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> A confirmation email has been sent to <strong><?php echo clean($booking['email']); ?></strong>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Booking Details
                            </button>
                            <a href="/" class="btn btn-outline">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Clear the last booking from session after displaying
<?php unset($_SESSION['last_booking']); ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
