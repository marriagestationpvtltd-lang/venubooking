<?php
$page_title = 'Complete Your Booking';
require_once __DIR__ . '/includes/header.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    header('Location: index.php');
    exit;
}

// Save selected services
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['selected_services'] = $_POST['services'] ?? [];
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];
$selected_services = $_SESSION['selected_services'] ?? [];

// Calculate final totals
$totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], $selected_services);

// Get menu details
$menu_details = [];
if (!empty($selected_menus)) {
    $db = getDB();
    $placeholders = str_repeat('?,', count($selected_menus) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM menus WHERE id IN ($placeholders)");
    $stmt->execute($selected_menus);
    $menu_details = $stmt->fetchAll();
    
    // Get menu items for each menu (prepare statement once for efficiency)
    if (!empty($menu_details)) {
        $itemsStmt = $db->prepare("SELECT item_name, category, display_order FROM menu_items WHERE menu_id = ? ORDER BY display_order, category");
        foreach ($menu_details as &$menu) {
            $itemsStmt->execute([$menu['id']]);
            $menu['items'] = $itemsStmt->fetchAll();
        }
    }
}

// Get service details
$service_details = [];
if (!empty($selected_services)) {
    $db = getDB();
    $placeholders = str_repeat('?,', count($selected_services) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM additional_services WHERE id IN ($placeholders)");
    $stmt->execute($selected_services);
    $service_details = $stmt->fetchAll();
}

// Get active payment methods
$active_payment_methods = getActivePaymentMethods();

// Calculate advance payment
$advance = calculateAdvancePayment($totals['grand_total']);

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    // Validate inputs
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $special_requests = trim($_POST['special_requests']);
    $payment_option = $_POST['payment_option'] ?? 'without';

    if (empty($full_name) || empty($phone)) {
        $error = 'Full name and phone number are required.';
    } elseif ($payment_option === 'with') {
        // Validate payment fields
        $payment_method_id = $_POST['payment_method_id'] ?? '';
        $transaction_id = trim($_POST['transaction_id'] ?? '');
        $paid_amount = $_POST['paid_amount'] ?? '';
        
        if (empty($payment_method_id)) {
            $error = 'Please select a payment method.';
        } elseif (empty($transaction_id)) {
            $error = 'Transaction ID / Reference Number is required.';
        } elseif (empty($paid_amount) || !is_numeric($paid_amount) || $paid_amount <= 0) {
            $error = 'Please enter a valid paid amount.';
        } elseif (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Payment slip / screenshot upload is required.';
        } else {
            // Validate payment slip upload
            $upload_result = handleImageUpload($_FILES['payment_slip'], 'payment-slips');
            if (!$upload_result['success']) {
                $error = 'Payment slip upload failed: ' . $upload_result['message'];
            }
        }
    }
    
    if (empty($error)) {
        // Create booking
        $booking_result = createBooking([
            'hall_id' => $selected_hall['id'],
            'event_date' => $booking_data['event_date'],
            'shift' => $booking_data['shift'],
            'event_type' => $booking_data['event_type'],
            'guests' => $booking_data['guests'],
            'menus' => $selected_menus,
            'services' => $selected_services,
            'full_name' => $full_name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'special_requests' => $special_requests
        ]);

        if ($booking_result['success']) {
            $booking_id = $booking_result['booking_id'];
            
            // If payment option is with payment, record the payment
            if ($payment_option === 'with' && isset($upload_result)) {
                $payment_result = recordPayment([
                    'booking_id' => $booking_id,
                    'payment_method_id' => $payment_method_id,
                    'transaction_id' => $transaction_id,
                    'paid_amount' => $paid_amount,
                    'payment_slip' => $upload_result['filename'],
                    'notes' => 'Payment submitted during booking',
                    'update_booking_status' => true
                ]);
                
                if (!$payment_result['success']) {
                    $error = 'Booking created but payment recording failed: ' . $payment_result['error'];
                }
            }
            
            // Clear booking session
            $_SESSION['booking_completed'] = [
                'booking_id' => $booking_id,
                'booking_number' => $booking_result['booking_number'],
                'payment_submitted' => ($payment_option === 'with')
            ];
            unset($_SESSION['booking_data']);
            unset($_SESSION['selected_hall']);
            unset($_SESSION['selected_menus']);
            unset($_SESSION['selected_services']);
            
            header('Location: confirmation.php');
            exit;
        } else {
            $error = $booking_result['error'];
        }
    }
}
?>

<!-- Booking Progress -->
<div class="booking-progress py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="progress-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-label">Details</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">2</span>
                        <span class="step-label">Venue & Hall</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">4</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">5</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Customer Information Form -->
            <div class="col-lg-8">
                <h2 class="mb-4">Your Information</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?>
                    </div>
                <?php endif; ?>

                <form id="customerForm" method="POST">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="special_requests" class="form-label">Special Requests</label>
                                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" 
                                          placeholder="Any special requirements or requests for your event..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Confirmation Options -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Options</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Choose how you would like to proceed with your booking:</p>
                            
                            <!-- Payment Option Selection -->
                            <div class="mb-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_option" id="payment_with" value="with">
                                    <label class="form-check-label fw-bold" for="payment_with">
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>Confirm Booking With Payment
                                    </label>
                                    <p class="text-muted small ms-4 mt-1">Submit payment details now to confirm your booking immediately</p>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_option" id="payment_without" value="without" checked>
                                    <label class="form-check-label fw-bold" for="payment_without">
                                        <i class="fas fa-calendar-check text-success me-2"></i>Confirm Booking Without Payment
                                    </label>
                                    <p class="text-muted small ms-4 mt-1">Confirm your booking now and add payment details later</p>
                                </div>
                            </div>

                            <!-- Payment Details Section (shown when "With Payment" is selected) -->
                            <div id="payment_details_section" style="display: none;">
                                <hr class="my-4">
                                <h6 class="mb-3 text-success"><i class="fas fa-info-circle me-2"></i>Payment Information</h6>
                                
                                <!-- Advance Payment Display -->
                                <div class="alert alert-info mb-3">
                                    <strong><i class="fas fa-calculator me-2"></i>Required Advance Payment:</strong><br>
                                    <span class="fs-5"><?php echo formatCurrency($advance['amount']); ?></span>
                                    <small class="d-block mt-1">(<?php echo $advance['percentage']; ?>% of Total: <?php echo formatCurrency($totals['grand_total']); ?>)</small>
                                </div>

                                <?php if (!empty($active_payment_methods)): ?>
                                    <!-- Payment Method Selection -->
                                    <div class="mb-3">
                                        <label for="payment_method_id" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_method_id" name="payment_method_id">
                                            <option value="">Select Payment Method</option>
                                            <?php foreach ($active_payment_methods as $method): ?>
                                                <option value="<?php echo $method['id']; ?>"><?php echo sanitize($method['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Payment Method Details Display -->
                                    <div id="payment_method_details" class="mb-3" style="display: none;">
                                        <?php foreach ($active_payment_methods as $method): ?>
                                            <div id="method_details_<?php echo $method['id']; ?>" class="payment-method-detail" style="display: none;">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                                                            <div class="text-center mb-3">
                                                                <img src="<?php echo UPLOAD_URL . sanitize($method['qr_code']); ?>" 
                                                                     alt="QR Code" 
                                                                     class="img-fluid"
                                                                     style="max-width: 250px; border: 2px solid #ddd; border-radius: 8px; padding: 10px; background: white;">
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($method['bank_details'])): ?>
                                                            <div class="bg-white p-3 rounded">
                                                                <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem;"><?php echo sanitize($method['bank_details']); ?></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No payment methods are currently configured. Please contact us to complete your booking.
                                    </div>
                                <?php endif; ?>

                                <!-- Transaction ID -->
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label">Transaction ID / Reference Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           placeholder="Enter your transaction ID or reference number">
                                    <small class="form-text text-muted">The reference number from your payment transaction</small>
                                </div>

                                <!-- Paid Amount -->
                                <div class="mb-3">
                                    <label for="paid_amount" class="form-label">Paid Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                                           step="0.01" min="0" placeholder="0.00" value="<?php echo $advance['amount']; ?>">
                                    <small class="form-text text-muted">Amount you have paid</small>
                                </div>

                                <!-- Payment Slip Upload -->
                                <div class="mb-3">
                                    <label for="payment_slip" class="form-label">Payment Slip / Screenshot <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="payment_slip" name="payment_slip" 
                                           accept="image/*,.pdf">
                                    <small class="form-text text-muted">Upload a screenshot or photo of your payment receipt (JPG, PNG, or PDF)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <a href="booking-step4.php" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="submit_booking" class="btn btn-success btn-lg w-100" id="submit_btn">
                                <i class="fas fa-check"></i> Confirm Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Booking Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Booking Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Event Details -->
                        <h6 class="mb-2 text-success"><i class="fas fa-calendar-check me-2"></i>Event Details</h6>
                        <div class="mb-2">
                            <small class="text-muted">Event Type:</small><br>
                            <strong><?php echo sanitize($booking_data['event_type']); ?></strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Date & Shift:</small><br>
                            <strong><?php echo date('F d, Y', strtotime($booking_data['event_date'])); ?></strong><br>
                            <small class="text-success"><?php echo ucfirst($booking_data['shift']); ?></small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Number of Guests:</small><br>
                            <strong><?php echo $booking_data['guests']; ?> persons</strong>
                        </div>

                        <hr class="my-2">

                        <!-- Venue & Hall -->
                        <h6 class="mb-2 text-success"><i class="fas fa-building me-2"></i>Venue & Hall</h6>
                        <div class="mb-2">
                            <strong><?php echo sanitize($selected_hall['venue_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($selected_hall['name']); ?> (<?php echo $selected_hall['capacity']; ?> pax)</small>
                        </div>

                        <hr class="my-2">

                        <!-- Menus -->
                        <?php if (!empty($menu_details)): ?>
                            <h6 class="mb-2 text-success"><i class="fas fa-utensils me-2"></i>Selected Menus</h6>
                            <?php foreach ($menu_details as $menu): ?>
                                <div class="mb-2">
                                    <small><strong><?php echo sanitize($menu['name']); ?></strong></small><br>
                                    <small class="text-success"><?php echo formatCurrency($menu['price_per_person']); ?>/pax</small>
                                    
                                    <?php if (!empty($menu['items'])): ?>
                                        <div class="mt-1 ms-2">
                                            <small class="text-muted d-block mb-1">Menu Items:</small>
                                            <ul class="booking-list small">
                                                <?php 
                                                $items_by_category = [];
                                                foreach ($menu['items'] as $item) {
                                                    $category = !empty($item['category']) ? $item['category'] : 'Other';
                                                    $items_by_category[$category][] = $item;
                                                }
                                                
                                                foreach ($items_by_category as $category => $items): 
                                                ?>
                                                    <?php if (count($items_by_category) > 1): ?>
                                                        <li><strong><?php echo sanitize($category); ?>:</strong>
                                                            <ul>
                                                                <?php foreach ($items as $item): ?>
                                                                    <li><?php echo sanitize($item['item_name']); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php else: ?>
                                                        <?php foreach ($items as $item): ?>
                                                            <li><?php echo sanitize($item['item_name']); ?></li>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <hr class="my-2">
                        <?php endif; ?>

                        <!-- Services -->
                        <?php if (!empty($service_details)): ?>
                            <h6 class="mb-2 text-success"><i class="fas fa-star me-2"></i>Additional Services</h6>
                            <?php foreach ($service_details as $service): ?>
                                <div class="mb-1">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    <small><strong><?php echo sanitize($service['name']); ?></strong></small>
                                    <small class="text-success ms-1"><?php echo formatCurrency($service['price']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <hr class="my-2">
                        <?php endif; ?>

                        <!-- Cost Breakdown -->
                        <h6 class="mb-2 text-success"><i class="fas fa-calculator me-2"></i>Cost Breakdown</h6>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Hall Cost:</span>
                            <strong class="text-success"><?php echo formatCurrency($totals['hall_price']); ?></strong>
                        </div>
                        <?php if ($totals['menu_total'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Menu Cost:</span>
                                <strong class="text-success"><?php echo formatCurrency($totals['menu_total']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($totals['services_total'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Services Cost:</span>
                                <strong class="text-success"><?php echo formatCurrency($totals['services_total']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal:</span>
                            <strong class="text-success"><?php echo formatCurrency($totals['subtotal']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                            <strong class="text-success"><?php echo formatCurrency($totals['tax_amount']); ?></strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <h5>Grand Total:</h5>
                            <h5 class="text-success"><?php echo formatCurrency($totals['grand_total']); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentWithRadio = document.getElementById('payment_with');
    const paymentWithoutRadio = document.getElementById('payment_without');
    const paymentDetailsSection = document.getElementById('payment_details_section');
    const paymentMethodSelect = document.getElementById('payment_method_id');
    const paymentMethodDetails = document.getElementById('payment_method_details');
    const customerForm = document.getElementById('customerForm');
    const submitBtn = document.getElementById('submit_btn');
    
    // Toggle payment details section
    function togglePaymentSection() {
        if (paymentWithRadio && paymentWithRadio.checked) {
            paymentDetailsSection.style.display = 'block';
            // Make payment fields required
            document.getElementById('payment_method_id').required = true;
            document.getElementById('transaction_id').required = true;
            document.getElementById('paid_amount').required = true;
            document.getElementById('payment_slip').required = true;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking & Submit Payment';
        } else {
            paymentDetailsSection.style.display = 'none';
            // Make payment fields optional
            if (document.getElementById('payment_method_id')) {
                document.getElementById('payment_method_id').required = false;
                document.getElementById('transaction_id').required = false;
                document.getElementById('paid_amount').required = false;
                document.getElementById('payment_slip').required = false;
            }
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
        }
    }
    
    // Show payment method details
    function showPaymentMethodDetails() {
        const selectedMethodId = paymentMethodSelect.value;
        
        // Hide all payment method details
        const allDetails = document.querySelectorAll('.payment-method-detail');
        allDetails.forEach(detail => {
            detail.style.display = 'none';
        });
        
        // Show selected payment method details
        if (selectedMethodId) {
            const selectedDetail = document.getElementById('method_details_' + selectedMethodId);
            if (selectedDetail) {
                paymentMethodDetails.style.display = 'block';
                selectedDetail.style.display = 'block';
            } else {
                paymentMethodDetails.style.display = 'none';
            }
        } else {
            paymentMethodDetails.style.display = 'none';
        }
    }
    
    // Event listeners
    if (paymentWithRadio) {
        paymentWithRadio.addEventListener('change', togglePaymentSection);
    }
    if (paymentWithoutRadio) {
        paymentWithoutRadio.addEventListener('change', togglePaymentSection);
    }
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', showPaymentMethodDetails);
    }
    
    // Form validation
    if (customerForm) {
        customerForm.addEventListener('submit', function(e) {
            if (paymentWithRadio && paymentWithRadio.checked) {
                const paymentMethodId = document.getElementById('payment_method_id').value;
                const transactionId = document.getElementById('transaction_id').value.trim();
                const paidAmount = document.getElementById('paid_amount').value;
                const paymentSlip = document.getElementById('payment_slip').files.length;
                
                if (!paymentMethodId) {
                    e.preventDefault();
                    alert('Please select a payment method.');
                    return false;
                }
                if (!transactionId) {
                    e.preventDefault();
                    alert('Please enter the transaction ID / reference number.');
                    return false;
                }
                if (!paidAmount || parseFloat(paidAmount) <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid paid amount.');
                    return false;
                }
                if (!paymentSlip) {
                    e.preventDefault();
                    alert('Please upload the payment slip / screenshot.');
                    return false;
                }
            }
        });
    }
    
    // Initialize
    togglePaymentSection();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
