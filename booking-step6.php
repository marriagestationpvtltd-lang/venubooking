<?php
// Include dependencies before any HTML output to allow redirects
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    // Store a flash message so the user understands why they're being redirected
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Save selected services (only when coming from the services step, not the final booking form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['submit_booking'])) {
    // If a package is selected, additional services must be ignored
    if (!empty($_SESSION['selected_packages'])) {
        $_SESSION['selected_services']  = [];
        $_SESSION['selected_designs']   = [];
        $_SESSION['vendor_for_service'] = [];
    } else {
        $_SESSION['selected_services'] = $_POST['services'] ?? [];
        // packages are already saved in session from step 5 (booking-step5.php)
        // Save selected designs: service_id => design_id (or sub_service_id => design_id for legacy data)
        if (!empty($_POST['selected_designs']) && is_array($_POST['selected_designs'])) {
            $raw_designs = $_POST['selected_designs'];
            $clean_designs = [];
            foreach ($raw_designs as $key_id => $d_id) {
                $key_id_int = intval($key_id);
                $d_id_int   = intval($d_id);
                if ($key_id_int > 0 && $d_id_int > 0) {
                    $clean_designs[$key_id_int] = $d_id_int;
                }
            }
            $_SESSION['selected_designs'] = $clean_designs;
        } else {
            $_SESSION['selected_designs'] = [];
        }
        // Save vendor selections for each service: service_id => vendor_id
        if (!empty($_POST['vendor_for_service']) && is_array($_POST['vendor_for_service'])) {
            $raw_vendors = $_POST['vendor_for_service'];
            $clean_vendors = [];
            foreach ($raw_vendors as $svc_id => $vendor_id) {
                $svc_id_int    = intval($svc_id);
                $vendor_id_int = intval($vendor_id);
                if ($svc_id_int > 0 && $vendor_id_int > 0) {
                    $clean_vendors[$svc_id_int] = $vendor_id_int;
                }
            }
            $_SESSION['vendor_for_service'] = $clean_vendors;
        } else {
            $_SESSION['vendor_for_service'] = [];
        }
    }
}

$booking_data = $_SESSION['booking_data'];
$selected_hall = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];
$selected_services = $_SESSION['selected_services'] ?? [];
$selected_designs  = $_SESSION['selected_designs'] ?? [];
$selected_packages = $_SESSION['selected_packages'] ?? [];
$menu_selections         = $_SESSION['menu_selections'] ?? [];
$menu_special_instructions = $_SESSION['menu_special_instructions'] ?? '';
$vendor_for_service = $_SESSION['vendor_for_service'] ?? [];

// Determine whether multiple slots were selected so we can show the
// consolidated time range rather than a comma-separated list of slot labels.
$multi_slot = count($booking_data['selected_slots'] ?? []) > 1;

// Calculate final totals — if this fails on a GET request the page cannot render
// correctly, so redirect back to the beginning.  On a POST submission (final booking
// confirmation) we keep the user on the page and surface the error so they can retry.
$totals_error = '';
try {
    $totals = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], $selected_services, $selected_designs, $selected_packages, null, $menu_selections);
} catch (\Throwable $e) {
    error_log('Failed to calculate booking totals: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On form submission keep the user on the page so they see the error.
        $totals_error = 'We were unable to calculate your booking total. Please go back and try again, or contact support.';
        $totals = ['hall_price' => 0, 'menu_total' => 0, 'services_total' => 0, 'subtotal' => 0, 'tax_amount' => 0, 'grand_total' => 0];
    } else {
        header('Location: index.php');
        exit;
    }
}

// Get menu details
$menu_details = [];
if (!empty($selected_menus)) {
    try {
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

        // Load custom menu selections for display
        if (!empty($menu_selections)) {
            foreach ($menu_details as &$menu_det) {
                $mid = intval($menu_det['id']);
                if (isset($menu_selections[$mid])) {
                    $menu_det['custom_structure'] = getMenuStructure($mid);
                    $menu_det['custom_selections'] = $menu_selections[$mid];
                }
            }
        }
        unset($menu_det);
    } catch (\Throwable $e) {
        error_log('Failed to load menu details: ' . $e->getMessage());
        $menu_details = [];
    }
}

// Get service details
$service_details = [];
if (!empty($selected_services)) {
    try {
        $db = getDB();
        $placeholders = str_repeat('?,', count($selected_services) - 1) . '?';
        $stmt = $db->prepare("SELECT * FROM additional_services WHERE id IN ($placeholders)");
        $stmt->execute($selected_services);
        $service_details = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('Failed to load service details: ' . $e->getMessage());
        $service_details = [];
    }
}

// Get selected package details (for display in summary)
$package_details = [];
if (!empty($selected_packages)) {
    try {
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($selected_packages), '?'));
        $stmt = $db->prepare(
            "SELECT sp.id, sp.name, sp.price, sp.description,
                    sc.name AS category_name,
                    (SELECT image_path FROM service_package_photos
                     WHERE package_id = sp.id ORDER BY display_order, id LIMIT 1) AS first_photo
             FROM service_packages sp
             LEFT JOIN service_categories sc ON sc.id = sp.category_id
             WHERE sp.id IN ($placeholders) AND sp.status = 'active'"
        );
        $stmt->execute(array_map('intval', $selected_packages));
        $package_details = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('Failed to load package details: ' . $e->getMessage());
        $package_details = [];
    }
}

// Get selected design details (for display and booking insertion)
$design_details = [];
if (!empty($selected_designs)) {
    try {
        $db = getDB();
        $design_ids = array_map('intval', array_values($selected_designs));
        $placeholders = implode(',', array_fill(0, count($design_ids), '?'));
        // Support both new direct-service designs (service_id) and legacy sub-service designs
        $stmt = $db->prepare(
            "SELECT d.*,
                    COALESCE(s_direct.name, s_via_ss.name) AS service_name,
                    COALESCE(s_direct.category, s_via_ss.category) AS category,
                    NULL AS sub_service_name
             FROM service_designs d
             LEFT JOIN additional_services s_direct ON s_direct.id = d.service_id
             LEFT JOIN service_sub_services ss ON ss.id = d.sub_service_id
             LEFT JOIN additional_services s_via_ss ON s_via_ss.id = ss.service_id
             WHERE d.id IN ($placeholders)"
        );
        $stmt->execute($design_ids);
        $design_details = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('Failed to load design details: ' . $e->getMessage());
        $design_details = [];
    }
}

// Get active payment methods
try {
    $active_payment_methods = getActivePaymentMethods();
} catch (\Throwable $e) {
    error_log('Failed to load payment methods: ' . $e->getMessage());
    $active_payment_methods = [];
}

// Calculate advance payment
$advance = calculateAdvancePayment($totals['grand_total']);

// Load policy pages that require user acceptance before booking
try {
    $required_policies = getPolicyPagesRequiringAcceptance();
} catch (\Throwable $e) {
    error_log('Failed to load required policies: ' . $e->getMessage());
    $required_policies = [];
}

// Handle form submission
$error = $totals_error; // Propagate any totals calculation error into the main error display
// Initialize form values
$full_name = '';
$phone = '';
$email = '';
$address = '';
$special_requests = '';
$payment_option = 'without';
$payment_method_id = '';
$transaction_id = '';
$paid_amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    // If totals calculation already failed, skip submission processing
    if (!empty($totals_error)) {
        // $error is already set to $totals_error; just re-read POST values for display
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $special_requests = trim($_POST['special_requests'] ?? '');
        $payment_option = $_POST['payment_option'] ?? 'without';
    } else {
    // Validate inputs with enhanced validation
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $special_requests = trim($_POST['special_requests'] ?? '');
    $payment_option = $_POST['payment_option'] ?? 'without';

    // Validate required fields
    $validation_errors = [];
    
    $nameValidation = validateRequired($full_name, 'Full name');
    if (!$nameValidation['valid']) {
        $validation_errors[] = $nameValidation['error'];
    }
    
    $phoneValidation = validatePhoneNumber($phone);
    if (!$phoneValidation['valid']) {
        $validation_errors[] = $phoneValidation['error'];
    }
    
    // Validate email if provided
    if (!empty($email)) {
        $emailValidation = validateEmailFormat($email);
        if (!$emailValidation['valid']) {
            $validation_errors[] = $emailValidation['error'];
        }
    }

    // Validate policy acceptance
    $accepted_policy_ids = array_map('intval', $_POST['accepted_policies'] ?? []);
    foreach ($required_policies as $rp) {
        if (!in_array((int)$rp['id'], $accepted_policy_ids, true)) {
            $validation_errors[] = 'You must accept the "' . $rp['title'] . '" to proceed.';
        }
    }

    // If there are validation errors, show them
    if (!empty($validation_errors)) {
        $error = implode(' ', $validation_errors);
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
        } elseif ($_FILES['payment_slip']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['payment_slip']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Payment slip file is too large. Please upload an image smaller than 5MB.';
        } elseif ($_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Payment slip upload error (code ' . $_FILES['payment_slip']['error'] . '). Please try again with a different image.';
        } else {
            // Validate payment slip upload
            $upload_result = handleImageUpload($_FILES['payment_slip'], 'payment-slips');
            if (!$upload_result['success']) {
                $error = 'Payment slip upload failed: ' . $upload_result['message'];
            }
        }
    }
    
    if (empty($error)) {
        // Create booking — wrap in try-catch so any unexpected exception
        // (e.g. DB connection drop) sets $error instead of producing a blank page.
        try {
            $booking_result = createBooking([
                'hall_id'            => $selected_hall['id'],
                'is_custom'          => !empty($selected_hall['is_custom']),
                'custom_venue_name'  => $selected_hall['custom_venue_name'] ?? '',
                'custom_hall_name'   => $selected_hall['custom_hall_name']  ?? '',
                'event_date'         => $booking_data['event_date'],
                'start_time'         => $booking_data['start_time'] ?? '',
                'end_time'           => $booking_data['end_time']   ?? '',
                'shift'              => $booking_data['shift'],
                'event_type'         => $booking_data['event_type'],
                'guests'             => $booking_data['guests'],
                'menus'              => $selected_menus,
                'services'           => $selected_services,
                'selected_designs'   => $selected_designs,
                'packages'           => $selected_packages,
                'selected_slots'     => $booking_data['selected_slots'] ?? [],
                'slot_id'            => $booking_data['slot_id'] ?? null,
                'full_name'          => $full_name,
                'phone'              => $phone,
                'email'              => $email,
                'address'            => $address,
                'special_requests'   => $special_requests,
                'menu_selections'            => $menu_selections,
                'menu_special_instructions'  => $menu_special_instructions,
            ]);
        } catch (\Throwable $e) {
            error_log('Unexpected booking error: ' . $e->getMessage());
            $booking_result = ['success' => false, 'error' => 'Unable to complete your booking. Please try again or contact support.'];
        }

        if ($booking_result['success']) {
            $booking_id = $booking_result['booking_id'];
            $payment_submitted_successfully = false;
            
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
                    // Log the payment error but do not block the booking confirmation.
                    // The booking was saved; the user will see payment_submitted = false
                    // on the confirmation page and can re-submit payment details later.
                    error_log("Payment recording failed for booking ID {$booking_id}: " . ($payment_result['error'] ?? 'unknown error'));
                } else {
                    $payment_submitted_successfully = true;
                }
            }

            // Assign vendors selected by the user during the services step.
            // vendor_for_service is { service_id => vendor_id } stored in session.
            // Each assignment is non-fatal: a failure is logged but does not abort
            // the booking confirmation redirect.
            if (!empty($vendor_for_service)) {
                // Build a quick lookup of booking_services rows for this booking so
                // we can link each vendor assignment to the specific service row.
                $booking_service_rows = [];
                try {
                    $db = getDB();
                    $bs_stmt = $db->prepare(
                        "SELECT id, service_id FROM booking_services WHERE booking_id = ?"
                    );
                    $bs_stmt->execute([$booking_id]);
                    foreach ($bs_stmt->fetchAll() as $bsr) {
                        // Map by service_id; keep the first row per service_id
                        if (!isset($booking_service_rows[(int)$bsr['service_id']])) {
                            $booking_service_rows[(int)$bsr['service_id']] = (int)$bsr['id'];
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to load booking_services for vendor assignment (booking {$booking_id}): " . $e->getMessage());
                }

                foreach ($vendor_for_service as $svc_id => $vnd_id) {
                    $svc_id = intval($svc_id);
                    $vnd_id = intval($vnd_id);
                    if ($svc_id <= 0 || $vnd_id <= 0) continue;

                    // Get service name for the assignment task description
                    $svc_name = "Service #{$svc_id}";
                    try {
                        $db_svc = getDB();
                        $sn_stmt = $db_svc->prepare("SELECT name FROM additional_services WHERE id = ?");
                        $sn_stmt->execute([$svc_id]);
                        $sn_row = $sn_stmt->fetch();
                        if ($sn_row) {
                            $svc_name = $sn_row['name'];
                        }
                    } catch (\Throwable $e) {
                        // Keep default service name; proceed
                    }

                    $bs_id = $booking_service_rows[$svc_id] ?? null;
                    $va_result = addVendorAssignment($booking_id, $vnd_id, $svc_name, 0, 'Selected during booking', $bs_id);
                    if (!$va_result) {
                        error_log("Vendor assignment failed for booking {$booking_id}, service {$svc_id}, vendor {$vnd_id}");
                    }
                }
            }

            // Clear booking session data and redirect to confirmation
            $_SESSION['booking_completed'] = [
                'booking_id' => $booking_id,
                'booking_number' => $booking_result['booking_number'],
                'payment_submitted' => ($payment_option === 'with' && $payment_submitted_successfully)
            ];
            unset($_SESSION['booking_data']);
            unset($_SESSION['selected_hall']);
            unset($_SESSION['selected_menus']);
            unset($_SESSION['selected_services']);
            unset($_SESSION['selected_designs']);
            unset($_SESSION['selected_packages']);
            unset($_SESSION['menu_selections']);
            unset($_SESSION['menu_special_instructions']);
            unset($_SESSION['vendor_for_service']);
            
            header('Location: confirmation.php');
            exit;
        } else {
            $error = $booking_result['error'];
        }
    }
    } // end else (totals were calculated successfully)
}

$page_title = 'Complete Your Booking';
require_once __DIR__ . '/includes/header.php';
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
                        <span class="step-label">Venue &amp; Hall</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">4</span>
                        <span class="step-label">Packages</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">5</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">6</span>
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
                <h2 class="mb-4">Complete Your Booking</h2>
                <p class="text-muted mb-4">Follow the steps below to complete your booking</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" id="errorAlert" role="alert">
                        <strong><i class="fas fa-exclamation-circle me-1"></i>Booking Error:</strong> <?php echo sanitize($error); ?>
                        <div class="mt-2 small">
                            If this problem persists, please contact us for assistance.
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="customerForm" method="POST" enctype="multipart/form-data">
                    <!-- Step 1: Customer Information -->
                    <div class="card mb-4" id="customer_info_section">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Step 1: Your Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo sanitize($full_name); ?>" 
                                       placeholder="Enter your full name" 
                                       required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo sanitize($phone); ?>" 
                                       placeholder="Enter your phone number" 
                                       pattern="[+]?[\d\s().\-]{7,}"
                                       required>
                                <div class="invalid-feedback">Please enter a valid phone number (at least 7 digits).</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-muted">(Optional)</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo sanitize($email); ?>"
                                       placeholder="your.email@example.com">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address <span class="text-muted">(Optional)</span></label>
                                <textarea class="form-control" id="address" name="address" rows="2"
                                          placeholder="Enter your address"><?php echo sanitize($address); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="special_requests" class="form-label">Special Requests <span class="text-muted">(Optional)</span></label>
                                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" 
                                          placeholder="Any special requirements or requests for your event..."><?php echo sanitize($special_requests); ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="button" class="btn btn-success btn-lg" id="continue_to_bill_btn">
                                    Continue to View Bill <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Bill Summary (Initially Hidden) -->
                    <div class="card mb-4" id="bill_summary_section" style="display: none;">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Step 2: Your Total Bill</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td><i class="fas fa-building text-success me-2"></i>Hall Cost:</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['hall_price']); ?></strong></td>
                                        </tr>
                                        <?php if ($totals['menu_total'] > 0): ?>
                                        <?php if (!empty($totals['menu_extra_total']) && $totals['menu_extra_total'] > 0): ?>
                                        <tr>
                                            <td><i class="fas fa-utensils text-success me-2"></i>Menu Base Cost:</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['menu_total'] - $totals['menu_extra_total']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-plus-circle text-warning me-2"></i>Menu Extra Items:</td>
                                            <td class="text-end"><strong class="text-warning">+<?php echo formatCurrency($totals['menu_extra_total']); ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td><i class="fas fa-utensils text-success me-2"></i>Menu Cost:</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['menu_total']); ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($totals['services_total'] > 0): ?>
                                        <tr>
                                            <td><i class="fas fa-star text-success me-2"></i>Services Cost:</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['services_total']); ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td>Subtotal:</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['subtotal']); ?></strong></td>
                                        </tr>
                                        <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                                        <tr>
                                            <td>Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</td>
                                            <td class="text-end"><strong><?php echo formatCurrency($totals['tax_amount']); ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="border-top">
                                            <td class="fs-5"><strong>Grand Total:</strong></td>
                                            <td class="text-end fs-4"><strong class="text-success"><?php echo formatCurrency($totals['grand_total']); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Advance Payment Required:</strong> <?php echo formatCurrency($advance['amount']); ?> 
                                <small>(<?php echo $advance['percentage']; ?>% of total)</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-lg" id="continue_to_payment_btn">
                                    Continue to Payment Options <i class="fas fa-arrow-right"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="back_to_info_btn">
                                    <i class="fas fa-arrow-left"></i> Back to Information
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Payment Confirmation Options (Initially Hidden) -->
                    <div class="card mb-4" id="payment_options_section" style="display: none;">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Step 3: Payment Options</h5>
                        </div>
                        <div class="card-body">
                            <!-- Advance Payment Required — always visible in this step -->
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-calculator me-2"></i>
                                <strong>Advance Payment Required:</strong>
                                <?php echo formatCurrency($advance['amount']); ?>
                                <small class="ms-1">(<?php echo $advance['percentage']; ?>% of Grand Total: <?php echo formatCurrency($totals['grand_total']); ?>)</small>
                            </div>

                            <p class="mb-3">Choose how you would like to proceed with your booking:</p>
                            
                            <!-- Payment Option Selection -->
                            <div class="mb-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_option" id="payment_with" value="with" <?php echo ($payment_option === 'with') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="payment_with">
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>Confirm Booking With Payment
                                    </label>
                                    <p class="text-muted small ms-4 mt-1">Submit payment details now to confirm your booking immediately</p>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_option" id="payment_without" value="without" <?php echo ($payment_option === 'without') ? 'checked' : ''; ?>>
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
                                                <option value="<?php echo $method['id']; ?>" <?php echo ($payment_method_id == $method['id']) ? 'selected' : ''; ?>><?php echo sanitize($method['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a payment method.</div>
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
                                           placeholder="Enter your transaction ID or reference number" value="<?php echo sanitize($transaction_id); ?>">
                                    <div class="invalid-feedback">Please enter the transaction ID / reference number.</div>
                                    <small class="form-text text-muted">The reference number from your payment transaction</small>
                                </div>

                                <!-- Paid Amount -->
                                <div class="mb-3">
                                    <label for="paid_amount" class="form-label">Paid Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                                           step="0.01" min="0" placeholder="0.00" value="<?php echo !empty($paid_amount) ? sanitize($paid_amount) : $advance['amount']; ?>">
                                    <div class="invalid-feedback">Please enter a valid paid amount.</div>
                                    <small class="form-text text-muted">Amount you have paid</small>
                                </div>

                                <!-- Payment Slip Upload -->
                                <div class="mb-3">
                                    <label for="payment_slip" class="form-label">Payment Slip / Screenshot <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="payment_slip" name="payment_slip" 
                                           accept="image/*">
                                    <div class="invalid-feedback">Please upload the payment slip / screenshot.</div>
                                    <small class="form-text text-muted">Upload a screenshot or photo of your payment receipt (JPG, PNG, GIF, or WebP)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden field ensures submit_booking is always present in POST data
                         even when the submit button is programmatically disabled on click -->
                    <input type="hidden" name="submit_booking" value="1">

                    <?php if (!empty($required_policies)): ?>
                    <!-- Policy Acceptance -->
                    <div class="card mb-4" id="policy_acceptance_section" style="display: none;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-file-contract me-2 text-success"></i>Accept Policies</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Please read and accept the following policies to complete your booking.</p>
                            <?php foreach ($required_policies as $rp): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input policy-checkbox" type="checkbox"
                                       name="accepted_policies[]"
                                       value="<?php echo (int)$rp['id']; ?>"
                                       id="policy_<?php echo (int)$rp['id']; ?>"
                                       <?php
                                       // Re-check if previously submitted
                                       $accepted_ids_display = array_map('intval', $_POST['accepted_policies'] ?? []);
                                       echo in_array((int)$rp['id'], $accepted_ids_display, true) ? 'checked' : '';
                                       ?>>
                                <label class="form-check-label" for="policy_<?php echo (int)$rp['id']; ?>">
                                    I have read and agree to the
                                    <a href="<?php echo BASE_URL; ?>/policy.php?slug=<?php echo urlencode($rp['slug']); ?>"
                                       target="_blank" class="text-success fw-semibold">
                                        <?php echo htmlspecialchars($rp['title']); ?>
                                        <i class="fas fa-external-link-alt ms-1 small"></i>
                                    </a>
                                    <span class="text-danger">*</span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Navigation Buttons (Initially Hidden) -->
                    <div class="row" id="final_buttons_section" style="display: none;">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-secondary btn-lg w-100" id="back_to_payment_btn">
                                <i class="fas fa-arrow-left"></i> Back to Payment Options
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success btn-lg w-100" id="submit_btn">
                                <i class="fas fa-check"></i> Confirm Booking
                            </button>
                        </div>
                    </div>
                    
                    <!-- Back Button for Step 1 (Initially Visible) -->
                    <div class="row" id="initial_back_button">
                        <div class="col-12">
                            <a href="booking-step5.php" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="fas fa-arrow-left"></i> Back to Services
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Booking Summary -->
            <div class="col-lg-4">
                <!-- Desktop: Standard sticky sidebar -->
                <div class="card shadow-sm sticky-top d-none d-lg-block" style="top: 20px;">
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
                            <small class="text-muted">Date & Time:</small><br>
                            <strong><?php echo date('F d, Y', strtotime($booking_data['event_date'])); ?></strong><br>
                            <small class="text-muted"><?php echo convertToNepaliDate($booking_data['event_date']); ?></small><br>
                            <small class="text-success">
                                <?php if (!$multi_slot && !empty($booking_data['slot_name'])): ?>
                                    <?php echo htmlspecialchars($booking_data['slot_name']); ?>
                                <?php elseif (!$multi_slot && !empty($booking_data['shift'])): ?>
                                    <?php echo ucfirst($booking_data['shift']); ?>
                                <?php endif; ?>
                                <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                                    <?php echo ($multi_slot ? '' : '&nbsp;•&nbsp;') . formatBookingTime($booking_data['start_time']); ?> – <?php echo formatBookingTime($booking_data['end_time']); ?>
                                <?php endif; ?>
                            </small>
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
                            <small class="text-muted">
                                <?php echo sanitize($selected_hall['name']); ?>
                                <?php if (!empty($selected_hall['capacity'])): ?>
                                    (<?php echo $selected_hall['capacity']; ?> pax)
                                <?php endif; ?>
                            </small>
                        </div>

                        <hr class="my-2">

                        <!-- Menus -->
                        <?php if (!empty($menu_details)): ?>
                            <h6 class="mb-2 text-success"><i class="fas fa-utensils me-2"></i>Selected Menus</h6>
                            <?php foreach ($menu_details as $menu): ?>
                                <div class="mb-2">
                                    <small><strong><?php echo sanitize($menu['name']); ?></strong></small><br>
                                    <small class="text-success"><?php echo formatCurrency($menu['price_per_person']); ?>/pax</small>
                                    <?php if (!empty($menu['custom_structure']) && !empty($menu['custom_selections'])): ?>
                                        <div class="mt-1 ms-2">
                                            <?php
                                            $menu_running_extra = 0;
                                            foreach ($menu['custom_structure'] as $section):
                                                // Pre-calculate section data
                                                $section_rows = [];
                                                $section_extra = 0;
                                                foreach ($section['groups'] as $group):
                                                    $gid = intval($group['id']);
                                                    $chosen_ids = array_map('intval', isset($menu['custom_selections'][$gid]) ? $menu['custom_selections'][$gid] : []);
                                                    $sel_items = array_values(array_filter($group['items'], function($it) use ($chosen_ids) {
                                                        return in_array(intval($it['id']), $chosen_ids);
                                                    }));
                                                    if (empty($sel_items)) continue;
                                                    $parts = [];
                                                    foreach ($sel_items as $sit):
                                                        $charge = floatval($sit['extra_charge']);
                                                        $section_extra += $charge;
                                                        $parts[] = htmlspecialchars($sit['item_name'], ENT_QUOTES, 'UTF-8')
                                                            . ($charge > 0 ? ' <span class="text-warning fw-semibold">+' . htmlspecialchars(formatCurrency($charge), ENT_QUOTES, 'UTF-8') . '</span>' : '');
                                                    endforeach;
                                                    $section_rows[] = ['group_name' => $group['group_name'], 'items_html' => implode(', ', $parts)];
                                                endforeach;
                                                if (empty($section_rows)) continue;
                                                $menu_running_extra += $section_extra;
                                            ?>
                                                <div class="border rounded mb-1 overflow-hidden">
                                                    <div class="d-flex justify-content-between align-items-center px-2 py-1" style="background:#f1f5f9;">
                                                        <span class="fw-semibold" style="font-size:0.72rem;color:#334155;"><?php echo htmlspecialchars($section['section_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php if ($section_extra > 0): ?>
                                                            <span class="badge bg-warning text-dark" style="font-size:0.62rem;">+<?php echo htmlspecialchars(formatCurrency($section_extra), ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="px-2 py-1">
                                                        <?php foreach ($section_rows as $row): ?>
                                                            <div style="font-size:0.7rem;" class="text-muted">
                                                                <span class="fw-semibold text-success"><?php echo htmlspecialchars($row['group_name'], ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                                <?php echo $row['items_html']; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if ($section_extra > 0): ?>
                                                        <div class="d-flex justify-content-between px-2 py-1 border-top" style="background:#fafafa;font-size:0.65rem;">
                                                            <span class="text-muted">Cumulative extras:</span>
                                                            <span class="fw-bold text-warning"><?php echo htmlspecialchars(formatCurrency($menu_running_extra), ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($menu_running_extra > 0): ?>
                                                <div class="d-flex justify-content-between px-2 py-1 rounded" style="font-size:0.72rem;background:#f0fdf4;border:1px solid #bbf7d0;">
                                                    <span class="fw-semibold text-success">Total extras:</span>
                                                    <span class="fw-bold text-success">+<?php echo htmlspecialchars(formatCurrency($menu_running_extra), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!empty($menu_special_instructions)): ?>
                                <div class="small text-muted mt-1"><em><i class="fas fa-comment-alt me-1"></i><?php echo sanitize($menu_special_instructions); ?></em></div>
                            <?php endif; ?>
                            <hr class="my-2">
                        <?php endif; ?>

                        <!-- Packages -->
                        <?php if (!empty($package_details)): ?>
                            <h6 class="mb-2 text-success"><i class="fas fa-box-open me-2"></i>Selected Packages</h6>
                            <?php foreach ($package_details as $pkg): ?>
                                <div class="mb-2 d-flex align-items-start gap-2">
                                    <?php if (!empty($pkg['first_photo'])): ?>
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($pkg['first_photo']); ?>"
                                             alt="<?php echo htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                             style="width:40px;height:30px;object-fit:cover;border-radius:3px;flex-shrink:0;">
                                    <?php else: ?>
                                        <span style="width:40px;height:30px;flex-shrink:0;" class="d-flex align-items-center justify-content-center bg-light rounded">
                                            <i class="fas fa-box text-muted small"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <small><strong><?php echo sanitize($pkg['name']); ?></strong></small>
                                        <?php if (!empty($pkg['category_name'])): ?>
                                            <small class="text-muted d-block"><?php echo sanitize($pkg['category_name']); ?></small>
                                        <?php endif; ?>
                                        <small class="text-success"><?php echo formatCurrency($pkg['price']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <hr class="my-2">
                        <?php endif; ?>

                        <!-- Services -->
                        <?php if (!empty($service_details) || !empty($design_details)): ?>
                            <h6 class="mb-2 text-success"><i class="fas fa-star me-2"></i>Additional Services</h6>
                            <?php foreach ($service_details as $service): ?>
                                <div class="mb-1">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    <small><strong><?php echo sanitize($service['name']); ?></strong></small>
                                    <small class="text-success ms-1"><?php echo formatCurrency($service['price']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($design_details as $design): ?>
                                <div class="mb-1">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    <small><strong><?php echo sanitize($design['service_name']); ?>:</strong>
                                        <?php echo sanitize($design['name']); ?></small>
                                    <small class="text-success ms-1"><?php echo formatCurrency($design['price']); ?></small>
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
                            <?php if (!empty($totals['menu_extra_total']) && $totals['menu_extra_total'] > 0): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Menu Base Cost:</span>
                                    <strong class="text-success"><?php echo formatCurrency($totals['menu_total'] - $totals['menu_extra_total']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Menu Extra Items:</span>
                                    <strong class="text-warning">+<?php echo formatCurrency($totals['menu_extra_total']); ?></strong>
                                </div>
                            <?php endif; ?>
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
                        <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                            <strong class="text-success"><?php echo formatCurrency($totals['tax_amount']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <h5>Grand Total:</h5>
                            <h5 class="text-success"><?php echo formatCurrency($totals['grand_total']); ?></h5>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile: Collapsible summary at top -->
                <div class="card shadow-sm d-lg-none mb-4">
                    <div class="card-header bg-success text-white" style="cursor: pointer;" 
                         data-bs-toggle="collapse" 
                         data-bs-target="#mobileSummaryCollapse" 
                         aria-expanded="false">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Booking Summary</h6>
                            <div>
                                <strong><?php echo formatCurrency($totals['grand_total']); ?></strong>
                                <i class="fas fa-chevron-down ms-2"></i>
                            </div>
                        </div>
                    </div>
                    <div id="mobileSummaryCollapse" class="collapse">
                        <div class="card-body">
                            <!-- Compact Event Info -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1"><i class="fas fa-calendar-check me-1"></i>Event</small>
                                <div><strong><?php echo sanitize($booking_data['event_type']); ?></strong></div>
                                <div><small><?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?> (<?php echo convertToNepaliDate($booking_data['event_date']); ?>)
                                <?php if (!$multi_slot && !empty($booking_data['slot_name'])): ?>
                                    • <?php echo htmlspecialchars($booking_data['slot_name']); ?>
                                <?php elseif (!$multi_slot && !empty($booking_data['shift'])): ?>
                                    • <?php echo ucfirst($booking_data['shift']); ?>
                                <?php endif; ?>
                                <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                                    (<?php echo formatBookingTime($booking_data['start_time']); ?> – <?php echo formatBookingTime($booking_data['end_time']); ?>)
                                <?php endif; ?></small></div>
                                <div><small><?php echo $booking_data['guests']; ?> guests</small></div>
                            </div>

                            <!-- Compact Venue Info -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1"><i class="fas fa-building me-1"></i>Venue</small>
                                <div><strong><?php echo sanitize($selected_hall['venue_name']); ?></strong></div>
                                <div><small><?php echo sanitize($selected_hall['name']); ?></small></div>
                            </div>

                            <?php if (!empty($package_details)): ?>
                            <!-- Compact Packages -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1"><i class="fas fa-box-open me-1"></i>Packages</small>
                                <?php foreach ($package_details as $pkg): ?>
                                    <div><small><strong><?php echo sanitize($pkg['name']); ?></strong>
                                    <span class="text-success ms-1"><?php echo formatCurrency($pkg['price']); ?></span></small></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Compact Cost Summary -->
                            <div class="border-top pt-2">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Hall:</span>
                                    <strong><?php echo formatCurrency($totals['hall_price']); ?></strong>
                                </div>
                                <?php if ($totals['menu_total'] > 0): ?>
                                    <?php if (!empty($totals['menu_extra_total']) && $totals['menu_extra_total'] > 0): ?>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span>Menu (base):</span>
                                            <strong><?php echo formatCurrency($totals['menu_total'] - $totals['menu_extra_total']); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span>Menu extras:</span>
                                            <strong class="text-warning">+<?php echo formatCurrency($totals['menu_extra_total']); ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span>Menu:</span>
                                            <strong><?php echo formatCurrency($totals['menu_total']); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($totals['services_total'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span>Services:</span>
                                        <strong><?php echo formatCurrency($totals['services_total']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span>Tax:</span>
                                        <strong><?php echo formatCurrency($totals['tax_amount']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong class="text-success fs-5"><?php echo formatCurrency($totals['grand_total']); ?></strong>
                                </div>
                            </div>
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
    
    // Section elements
    const customerInfoSection = document.getElementById('customer_info_section');
    const billSummarySection = document.getElementById('bill_summary_section');
    const paymentOptionsSection = document.getElementById('payment_options_section');
    const policyAcceptanceSection = document.getElementById('policy_acceptance_section');
    const finalButtonsSection = document.getElementById('final_buttons_section');
    const initialBackButton = document.getElementById('initial_back_button');
    
    // Button elements
    const continueToBillBtn = document.getElementById('continue_to_bill_btn');
    const continueToPaymentBtn = document.getElementById('continue_to_payment_btn');
    const backToInfoBtn = document.getElementById('back_to_info_btn');
    const backToPaymentBtn = document.getElementById('back_to_payment_btn');
    
    // Clear validation errors in real-time as the user fills in the fields
    function attachErrorClearListener(input) {
        if (!input) return;
        const isBinary = input.type === 'file' || input.type === 'radio' ||
            input.type === 'checkbox' || input.tagName === 'SELECT';
        const eventType = isBinary ? 'change' : 'input';
        input.addEventListener(eventType, function() {
            const hasValue = isBinary
                ? (input.type === 'file' ? input.files && input.files.length > 0 : input.value)
                : input.value.trim();
            if (hasValue) {
                this.classList.remove('is-invalid');
            }
        });
    }

    [
        document.getElementById('full_name'),
        document.getElementById('phone'),
        document.getElementById('email'),
        document.getElementById('address'),
        document.getElementById('special_requests'),
        document.getElementById('transaction_id'),
        document.getElementById('paid_amount'),
        document.getElementById('payment_method_id'),
        document.getElementById('payment_slip')
    ].forEach(attachErrorClearListener);

    // Step 1 -> Step 2: Show Bill Summary
    continueToBillBtn.addEventListener('click', function() {
        // Validate customer info fields
        const fullName = document.getElementById('full_name').value.trim();
        const phone = document.getElementById('phone').value.trim();
        
        if (!fullName) {
            document.getElementById('full_name').classList.add('is-invalid');
            document.getElementById('full_name').focus();
            return;
        } else {
            document.getElementById('full_name').classList.remove('is-invalid');
        }
        
        if (!phone) {
            document.getElementById('phone').classList.add('is-invalid');
            document.getElementById('phone').focus();
            return;
        }
        // Validate phone has at least 7 digits (matches server-side validation)
        const phoneDigits = phone.replace(/[\s()\-\.]/g, '').replace(/^\+/, '');
        if (phoneDigits.length < 7 || !/^\d+$/.test(phoneDigits)) {
            document.getElementById('phone').classList.add('is-invalid');
            document.getElementById('phone').focus();
            return;
        }
        document.getElementById('phone').classList.remove('is-invalid');
        
        // Hide customer info section, show bill summary
        customerInfoSection.style.display = 'none';
        initialBackButton.style.display = 'none';
        billSummarySection.style.display = 'block';
        
        // Scroll to top of the section
        billSummarySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    // Step 2 -> Step 1: Back to Customer Info
    backToInfoBtn.addEventListener('click', function() {
        billSummarySection.style.display = 'none';
        customerInfoSection.style.display = 'block';
        initialBackButton.style.display = 'block';
        
        customerInfoSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    // Step 2 -> Step 3: Show Payment Options (and policy acceptance if present)
    continueToPaymentBtn.addEventListener('click', function() {
        billSummarySection.style.display = 'none';
        paymentOptionsSection.style.display = 'block';
        if (policyAcceptanceSection) {
            policyAcceptanceSection.style.display = 'block';
        }
        finalButtonsSection.style.display = 'flex';
        
        paymentOptionsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    // Step 3 -> Step 2: Back to Bill Summary
    backToPaymentBtn.addEventListener('click', function() {
        paymentOptionsSection.style.display = 'none';
        if (policyAcceptanceSection) {
            policyAcceptanceSection.style.display = 'none';
        }
        finalButtonsSection.style.display = 'none';
        billSummarySection.style.display = 'block';
        
        billSummarySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    // Toggle payment details section
    function togglePaymentSection() {
        if (paymentWithRadio && paymentWithRadio.checked) {
            paymentDetailsSection.style.display = 'block';
            // Make payment fields required (guard against missing elements when no payment methods configured)
            if (document.getElementById('payment_method_id')) {
                document.getElementById('payment_method_id').required = true;
            }
            if (document.getElementById('transaction_id')) {
                document.getElementById('transaction_id').required = true;
            }
            if (document.getElementById('paid_amount')) {
                document.getElementById('paid_amount').required = true;
            }
            if (document.getElementById('payment_slip')) {
                document.getElementById('payment_slip').required = true;
            }
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
            // Validate policy acceptance checkboxes
            const policyCheckboxes = document.querySelectorAll('.policy-checkbox');
            let allAccepted = true;
            policyCheckboxes.forEach(function(cb) {
                if (!cb.checked) {
                    allAccepted = false;
                    cb.classList.add('is-invalid');
                } else {
                    cb.classList.remove('is-invalid');
                }
            });
            if (!allAccepted) {
                e.preventDefault();
                const firstUnchecked = document.querySelector('.policy-checkbox:not(:checked)');
                if (firstUnchecked) {
                    firstUnchecked.closest('.form-check').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            if (paymentWithRadio && paymentWithRadio.checked) {
                const paymentMethodEl = document.getElementById('payment_method_id');
                const transactionIdEl = document.getElementById('transaction_id');
                const paidAmountEl = document.getElementById('paid_amount');
                const paymentSlipEl = document.getElementById('payment_slip');

                let firstInvalid = null;

                if (paymentMethodEl && !paymentMethodEl.value) {
                    e.preventDefault();
                    paymentMethodEl.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = paymentMethodEl;
                }
                if (transactionIdEl && !transactionIdEl.value.trim()) {
                    e.preventDefault();
                    transactionIdEl.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = transactionIdEl;
                }
                if (paidAmountEl && (!paidAmountEl.value || parseFloat(paidAmountEl.value) <= 0)) {
                    e.preventDefault();
                    paidAmountEl.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = paidAmountEl;
                }
                if (paymentSlipEl && (!paymentSlipEl.files || paymentSlipEl.files.length === 0)) {
                    e.preventDefault();
                    paymentSlipEl.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = paymentSlipEl;
                }

                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                    return false;
                }
            }

            // Show loading state on submit button to prevent double-submission
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            }
        });
    }
    
    // Initialize
    togglePaymentSection();
    
    // If payment method was previously selected, show its details
    if (paymentMethodSelect && paymentMethodSelect.value) {
        showPaymentMethodDetails();
    }
    
    // Remove error alert when user starts correcting their input
    <?php if ($error): ?>
    const errorAlert = document.getElementById('errorAlert');
    if (errorAlert && customerForm) {
        // Get all form inputs
        const formInputs = customerForm.querySelectorAll('input, select, textarea');
        
        // Helper function to determine the appropriate event type for each input
        function getEventType(input) {
            const tagName = input.tagName.toUpperCase();
            if (input.type === 'file' || tagName === 'SELECT' || 
                input.type === 'radio' || input.type === 'checkbox') {
                return 'change';
            }
            return 'input';
        }
        
        // Flag to track if alert has been dismissed
        let alertDismissed = false;
        
        // Add event listener to each input to hide error when user makes changes
        formInputs.forEach(function(input) {
            const eventType = getEventType(input);
            
            input.addEventListener(eventType, function() {
                // Only process if alert hasn't been dismissed yet
                if (!alertDismissed && errorAlert && errorAlert.classList.contains('show')) {
                    // Use Bootstrap's native alert dismissal
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(errorAlert);
                        bsAlert.close();
                        alertDismissed = true;
                    }
                }
            });
        });
    }
    <?php endif; ?>
    
    // If there was a form error, show all steps so the user can review and resubmit
    <?php if ($error): ?>
    // Always restore the full multi-step UI so the submit button is visible and
    // the error alert (which sits above the form) can be scrolled into view.
    customerInfoSection.style.display = 'block';
    billSummarySection.style.display = 'block';
    paymentOptionsSection.style.display = 'block';
    if (policyAcceptanceSection) {
        policyAcceptanceSection.style.display = 'block';
    }
    finalButtonsSection.style.display = 'flex';
    initialBackButton.style.display = 'none';
    // Scroll to the top of the booking section so the error alert is visible
    var errorAlertEl = document.getElementById('errorAlert');
    if (errorAlertEl) {
        errorAlertEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    <?php endif; ?>
});
</script>

<style>
/* Visual feedback for step-by-step flow - ensures consistent validation styling when dynamically applied */
.card-header {
    position: relative;
}

/* Bootstrap validation styling - ensuring consistency when applied via JavaScript */
.form-control.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

/* Smooth transitions for sections */
.card {
    transition: all 0.3s ease-in-out;
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
