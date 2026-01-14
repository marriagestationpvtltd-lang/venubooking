<?php
/**
 * Booking Step 4: Additional Services
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check previous steps
if (!isset($_SESSION['booking']['hall_id'])) {
    redirect('/index.php');
}

// Save Step 3 data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        redirect('/booking-step3.php');
    }
    
    $_SESSION['booking']['selected_menus'] = json_decode($_POST['selected_menus'], true);
}

if (!isset($_SESSION['booking']['selected_menus']) || empty($_SESSION['booking']['selected_menus'])) {
    setFlashMessage('error', 'Please select at least one menu');
    redirect('/booking-step3.php');
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Additional Services - ' . APP_NAME;
include __DIR__ . '/includes/header.php';

$db = getDB();

// Get services
$stmt = $db->query("SELECT * FROM additional_services WHERE status = 'active' ORDER BY service_type, service_name");
$services = $stmt->fetchAll();

// Group services by type
$servicesByType = [];
foreach ($services as $service) {
    $type = $service['service_type'] ?: 'Other';
    if (!isset($servicesByType[$type])) {
        $servicesByType[$type] = [];
    }
    $servicesByType[$type][] = $service;
}

// Get venue and hall details for summary
$stmt = $db->prepare("SELECT v.venue_name, h.hall_name, h.base_price 
                      FROM venues v 
                      JOIN halls h ON v.id = h.venue_id 
                      WHERE h.id = :hall_id");
$stmt->bindParam(':hall_id', $_SESSION['booking']['hall_id'], PDO::PARAM_INT);
$stmt->execute();
$venueHall = $stmt->fetch();
?>

<!-- Progress Indicator -->
<div class="container mt-4">
    <div class="step-indicator">
        <div class="step completed"><div class="step-number">1</div><div class="step-title">Booking Details</div></div>
        <div class="step completed"><div class="step-number">2</div><div class="step-title">Venue & Hall</div></div>
        <div class="step completed"><div class="step-number">3</div><div class="step-title">Menu</div></div>
        <div class="step active"><div class="step-number">4</div><div class="step-title">Additional Services</div></div>
        <div class="step"><div class="step-number">5</div><div class="step-title">Confirm & Pay</div></div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h2 style="color: var(--primary-dark); margin-bottom: 30px;">
                    <i class="fas fa-concierge-bell"></i> Additional Services
                </h2>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Select optional services to enhance your event experience
                </div>
                
                <?php foreach ($servicesByType as $type => $typeServices): ?>
                    <div class="mb-4">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px;">
                            <i class="fas fa-tag"></i> <?php echo clean($type); ?>
                        </h4>
                        
                        <div class="service-list">
                            <?php foreach ($typeServices as $service): ?>
                                <div class="service-item">
                                    <div class="service-check">
                                        <input type="checkbox" 
                                               class="service-checkbox" 
                                               id="service_<?php echo $service['id']; ?>"
                                               data-service-id="<?php echo $service['id']; ?>"
                                               data-service-name="<?php echo clean($service['service_name']); ?>"
                                               data-service-price="<?php echo $service['price']; ?>">
                                        <div class="service-details">
                                            <h4><?php echo clean($service['service_name']); ?></h4>
                                            <?php if ($service['description']): ?>
                                                <p style="margin: 0; color: var(--medium-gray); font-size: 0.9rem;">
                                                    <?php echo clean($service['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="service-price">
                                        <?php echo formatCurrency($service['price']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-4">
                    <button type="button" class="btn btn-outline" onclick="window.location.href='/booking-step3.php'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary float-end" id="proceedToConfirmBtn">
                        Continue to Confirmation <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                
                <form id="step4Form" method="POST" action="/booking-step5.php" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="selected_services" id="selectedServicesInput">
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="booking-summary">
                    <h3 class="summary-title">Booking Summary</h3>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-building"></i> Venue:</span>
                        <span class="summary-value"><?php echo clean($venueHall['venue_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-door-open"></i> Hall:</span>
                        <span class="summary-value"><?php echo clean($venueHall['hall_name']); ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Hall Price:</span>
                        <span class="summary-value" id="summaryHallPrice"><?php echo formatCurrency($venueHall['base_price']); ?></span>
                    </div>
                    
                    <div id="menusSummary"></div>
                    <div id="servicesSummary"></div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value" id="summarySubtotal"></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span class="summary-value" id="summaryTax"></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total:</span>
                        <span id="summaryTotal"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    let selectedServices = [];
    let hallId = <?php echo $_SESSION['booking']['hall_id']; ?>;
    let selectedMenus = <?php echo json_encode($_SESSION['booking']['selected_menus']); ?>;
    let numGuests = <?php echo $_SESSION['booking']['number_of_guests']; ?>;
    
    // Load initial summary
    calculateTotal();
    
    // Service checkbox change
    $('.service-checkbox').change(function() {
        let serviceId = $(this).data('service-id');
        let serviceName = $(this).data('service-name');
        let servicePrice = parseFloat($(this).data('service-price'));
        
        if ($(this).is(':checked')) {
            selectedServices.push({
                id: serviceId,
                name: serviceName,
                price: servicePrice
            });
        } else {
            selectedServices = selectedServices.filter(s => s.id !== serviceId);
        }
        
        calculateTotal();
    });
    
    function calculateTotal() {
        $.ajax({
            url: '/api/calculate-price.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                hall_id: hallId,
                menus: selectedMenus,
                guests: numGuests,
                services: selectedServices.map(s => s.id)
            }),
            success: function(response) {
                if (response.success) {
                    // Update menus summary
                    let menusHTML = '';
                    if (response.menu_total > 0) {
                        menusHTML = `
                            <div class="summary-item">
                                <span class="summary-label">Menus Total:</span>
                                <span class="summary-value">${response.menu_total_formatted}</span>
                            </div>
                        `;
                    }
                    $('#menusSummary').html(menusHTML);
                    
                    // Update services summary
                    let servicesHTML = '';
                    selectedServices.forEach(function(service) {
                        servicesHTML += `
                            <div class="summary-item">
                                <span class="summary-label">${service.name}:</span>
                                <span class="summary-value"><?php echo CURRENCY_SYMBOL; ?>${service.price.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                            </div>
                        `;
                    });
                    $('#servicesSummary').html(servicesHTML);
                    
                    $('#summarySubtotal').text(response.subtotal_formatted);
                    $('#summaryTax').text(response.tax_amount_formatted);
                    $('#summaryTotal').text(response.total_formatted);
                }
            }
        });
    }
    
    $('#proceedToConfirmBtn').click(function() {
        let serviceIds = selectedServices.map(s => s.id);
        $('#selectedServicesInput').val(JSON.stringify(serviceIds));
        $('#step4Form').submit();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
