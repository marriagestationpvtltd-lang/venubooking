<?php
/**
 * Booking Step 5: Customer Information & Booking Confirmation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check previous steps
if (!isset($_SESSION['booking']['hall_id']) || !isset($_SESSION['booking']['selected_menus'])) {
    redirect('/index.php');
}

// Save Step 4 data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        redirect('/booking-step4.php');
    }
    
    $_SESSION['booking']['selected_services'] = json_decode($_POST['selected_services'] ?? '[]', true);
}

// Initialize services if not set
if (!isset($_SESSION['booking']['selected_services'])) {
    $_SESSION['booking']['selected_services'] = [];
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
    } else {
        // Prepare booking data
        $bookingData = [
            'csrf_token' => $csrfToken ?? generateCSRFToken(),
            'full_name' => sanitizeInput($_POST['full_name']),
            'phone' => sanitizeInput($_POST['phone']),
            'email' => sanitizeInput($_POST['email']),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'special_requests' => sanitizeInput($_POST['special_requests'] ?? ''),
            'payment_option' => sanitizeInput($_POST['payment_option']),
            'venue_id' => $_SESSION['booking']['venue_id'],
            'hall_id' => $_SESSION['booking']['hall_id'],
            'booking_date' => $_SESSION['booking']['booking_date'],
            'shift' => $_SESSION['booking']['shift'],
            'number_of_guests' => $_SESSION['booking']['number_of_guests'],
            'event_type' => $_SESSION['booking']['event_type'],
            'menus' => $_SESSION['booking']['selected_menus'],
            'services' => $_SESSION['booking']['selected_services']
        ];
        
        // Call API to create booking
        $ch = curl_init(APP_URL . '/api/create-booking.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bookingData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && $result['success']) {
            // Store booking info in session for confirmation page
            $_SESSION['last_booking'] = [
                'booking_id' => $result['booking_id'],
                'booking_number' => $result['booking_number']
            ];
            
            // Clear booking session
            unset($_SESSION['booking']);
            
            // Redirect to confirmation page
            redirect('/confirmation.php');
        } else {
            $error = $result['message'] ?? 'Failed to create booking. Please try again.';
            setFlashMessage('error', $error);
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Customer Information - ' . APP_NAME;
include __DIR__ . '/includes/header.php';

$db = getDB();

// Get booking details for summary
$stmt = $db->prepare("SELECT v.venue_name, v.location, h.hall_name, h.base_price, h.capacity 
                      FROM venues v 
                      JOIN halls h ON v.id = h.venue_id 
                      WHERE h.id = :hall_id");
$stmt->bindParam(':hall_id', $_SESSION['booking']['hall_id'], PDO::PARAM_INT);
$stmt->execute();
$venueHall = $stmt->fetch();

// Get selected menus
$menuStmt = $db->prepare("SELECT * FROM menus WHERE id IN (" . implode(',', array_map('intval', $_SESSION['booking']['selected_menus'])) . ")");
$menuStmt->execute();
$selectedMenus = $menuStmt->fetchAll();

// Get selected services
$selectedServices = [];
if (!empty($_SESSION['booking']['selected_services'])) {
    $serviceStmt = $db->prepare("SELECT * FROM additional_services WHERE id IN (" . implode(',', array_map('intval', $_SESSION['booking']['selected_services'])) . ")");
    $serviceStmt->execute();
    $selectedServices = $serviceStmt->fetchAll();
}

// Calculate totals
$calculation = calculateBookingTotal(
    $_SESSION['booking']['hall_id'],
    $_SESSION['booking']['selected_menus'],
    $_SESSION['booking']['number_of_guests'],
    $_SESSION['booking']['selected_services']
);
?>

<!-- Progress Indicator -->
<div class="container mt-4">
    <div class="step-indicator">
        <div class="step completed"><div class="step-number">1</div><div class="step-title">Details</div></div>
        <div class="step completed"><div class="step-number">2</div><div class="step-title">Venue</div></div>
        <div class="step completed"><div class="step-number">3</div><div class="step-title">Menu</div></div>
        <div class="step completed"><div class="step-number">4</div><div class="step-title">Services</div></div>
        <div class="step active"><div class="step-number">5</div><div class="step-title">Confirm</div></div>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if ($flashError = getFlashMessage('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo clean($flashError); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-7">
                <h2 style="color: var(--primary-dark); margin-bottom: 30px;">
                    <i class="fas fa-user"></i> Customer Information
                </h2>
                
                <form id="customerInfoForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="submit_booking" value="1">
                    
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address">Address</label>
                        <input type="text" class="form-control" id="address" name="address">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="special_requests">Special Requests</label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
                    </div>
                    
                    <h3 style="color: var(--primary-dark); margin-top: 30px; margin-bottom: 20px;">
                        <i class="fas fa-credit-card"></i> Payment Option
                    </h3>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_option" id="payment_advance" value="advance" checked>
                        <label class="form-check-label" for="payment_advance">
                            <strong>Pay Advance (<?php echo ADVANCE_PAYMENT_PERCENTAGE; ?>%)</strong> - 
                            <?php echo formatCurrency($calculation['total'] * (ADVANCE_PAYMENT_PERCENTAGE / 100)); ?>
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_option" id="payment_full" value="full">
                        <label class="form-check-label" for="payment_full">
                            <strong>Pay Full Amount</strong> - <?php echo formatCurrency($calculation['total']); ?>
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_option" id="payment_later" value="later">
                        <label class="form-check-label" for="payment_later">
                            <strong>Pay Later</strong> - Reserve now, pay at venue
                        </label>
                    </div>
                    
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="terms_conditions" required>
                        <label class="form-check-label" for="terms_conditions">
                            I agree to the <a href="#" style="color: var(--primary-green);">Terms & Conditions</a> *
                        </label>
                    </div>
                    
                    <div class="mt-4">
                        <button type="button" class="btn btn-outline" onclick="window.location.href='/booking-step4.php'">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-primary float-end">
                            <i class="fas fa-check"></i> Confirm Booking
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-md-5">
                <div class="booking-summary">
                    <h3 class="summary-title">Complete Booking Summary</h3>
                    
                    <h5 style="color: var(--primary-green); margin-top: 20px; margin-bottom: 10px;">Event Details</h5>
                    <div class="summary-item">
                        <span class="summary-label">Event Type:</span>
                        <span class="summary-value"><?php echo clean($_SESSION['booking']['event_type']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Date:</span>
                        <span class="summary-value"><?php echo formatDate($_SESSION['booking']['booking_date']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Shift:</span>
                        <span class="summary-value"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['booking']['shift'])); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Guests:</span>
                        <span class="summary-value"><?php echo $_SESSION['booking']['number_of_guests']; ?> persons</span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <h5 style="color: var(--primary-green); margin-bottom: 10px;">Venue & Hall</h5>
                    <div class="summary-item">
                        <span class="summary-label">Venue:</span>
                        <span class="summary-value"><?php echo clean($venueHall['venue_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Location:</span>
                        <span class="summary-value"><?php echo clean($venueHall['location']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Hall:</span>
                        <span class="summary-value"><?php echo clean($venueHall['hall_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Hall Price:</span>
                        <span class="summary-value"><?php echo formatCurrency($venueHall['base_price']); ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <h5 style="color: var(--primary-green); margin-bottom: 10px;">Selected Menus</h5>
                    <?php foreach ($selectedMenus as $menu): 
                        $menuCost = $menu['price_per_person'] * $_SESSION['booking']['number_of_guests'];
                    ?>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo clean($menu['menu_name']); ?>:</span>
                            <span class="summary-value"><?php echo formatCurrency($menuCost); ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($selectedServices)): ?>
                        <div class="summary-divider"></div>
                        <h5 style="color: var(--primary-green); margin-bottom: 10px;">Additional Services</h5>
                        <?php foreach ($selectedServices as $service): ?>
                            <div class="summary-item">
                                <span class="summary-label"><?php echo clean($service['service_name']); ?>:</span>
                                <span class="summary-value"><?php echo formatCurrency($service['price']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value"><?php echo formatCurrency($calculation['subtotal']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span class="summary-value"><?php echo formatCurrency($calculation['tax_amount']); ?></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Grand Total:</span>
                        <span><?php echo formatCurrency($calculation['total']); ?></span>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid var(--primary-green);">
                        <p style="margin: 0; font-size: 0.9rem; color: var(--dark-gray);">
                            <i class="fas fa-info-circle" style="color: var(--primary-green);"></i> 
                            <strong>Advance Payment:</strong> <?php echo formatCurrency($calculation['total'] * (ADVANCE_PAYMENT_PERCENTAGE / 100)); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    $('#customerInfoForm').validate({
        rules: {
            full_name: { required: true, minlength: 3 },
            phone: { required: true, minlength: 10 },
            email: { required: true, email: true },
            terms_conditions: { required: true }
        },
        messages: {
            full_name: "Please enter your full name",
            phone: "Please enter a valid phone number",
            email: "Please enter a valid email address",
            terms_conditions: "You must agree to the terms and conditions"
        },
        submitHandler: function(form) {
            Swal.fire({
                title: 'Confirm Booking?',
                text: 'Are you sure you want to proceed with this booking?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--primary-green)',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Confirm!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Creating your booking',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
