<?php
/**
 * Booking Step 2: Venue & Hall Selection
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check if Step 1 is completed
if (!isset($_SESSION['booking']['shift']) || !isset($_SESSION['booking']['booking_date'])) {
    setFlashMessage('error', 'Please complete Step 1 first');
    redirect('/index.php');
}

// Save Step 1 data if coming from form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        redirect('/index.php');
    }
    
    $_SESSION['booking']['shift'] = sanitizeInput($_POST['shift']);
    $_SESSION['booking']['booking_date'] = sanitizeInput($_POST['booking_date']);
    $_SESSION['booking']['number_of_guests'] = (int)$_POST['number_of_guests'];
    $_SESSION['booking']['event_type'] = sanitizeInput($_POST['event_type']);
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Select Venue & Hall - ' . APP_NAME;
include __DIR__ . '/includes/header.php';

$db = getDB();

// Get all active venues
$venueStmt = $db->query("SELECT * FROM venues WHERE status = 'active' ORDER BY venue_name ASC");
$venues = $venueStmt->fetchAll();
?>

<!-- Progress Indicator -->
<div class="container mt-4">
    <div class="step-indicator">
        <div class="step completed">
            <div class="step-number">1</div>
            <div class="step-title">Booking Details</div>
        </div>
        <div class="step active">
            <div class="step-number">2</div>
            <div class="step-title">Select Venue & Hall</div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-title">Select Menu</div>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <div class="step-title">Additional Services</div>
        </div>
        <div class="step">
            <div class="step-number">5</div>
            <div class="step-title">Confirm & Pay</div>
        </div>
    </div>
</div>

<!-- Booking Summary Sidebar -->
<section class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h2 style="color: var(--primary-dark); margin-bottom: 30px;">
                    <i class="fas fa-building"></i> Select Your Venue & Hall
                </h2>
                
                <!-- Venues Section -->
                <div id="venuesSection">
                    <div class="venue-grid">
                        <?php foreach ($venues as $venue): ?>
                        <div class="venue-card" data-venue-id="<?php echo $venue['id']; ?>">
                            <?php if ($venue['image']): ?>
                                <img src="/<?php echo UPLOAD_PATH_VENUES . clean($venue['image']); ?>" 
                                     alt="<?php echo clean($venue['venue_name']); ?>" 
                                     class="venue-img"
                                     onerror="this.src='/images/placeholder-venue.jpg'">
                            <?php else: ?>
                                <div class="venue-img" style="background: linear-gradient(135deg, var(--primary-light), var(--primary-green)); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-building" style="font-size: 3rem; color: white;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="venue-info">
                                <h3 class="venue-name"><?php echo clean($venue['venue_name']); ?></h3>
                                <p class="venue-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo clean($venue['location']); ?>
                                </p>
                                <p style="color: var(--medium-gray); font-size: 0.9rem;">
                                    <?php echo clean(substr($venue['description'], 0, 100)); ?>...
                                </p>
                                <button class="btn btn-primary btn-block mt-3 view-halls-btn" 
                                        data-venue-id="<?php echo $venue['id']; ?>"
                                        data-venue-name="<?php echo clean($venue['venue_name']); ?>">
                                    <i class="fas fa-eye"></i> View Halls
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Halls Section (Initially Hidden) -->
                <div id="hallsSection" class="d-none">
                    <div class="mb-4">
                        <button class="btn btn-outline" id="backToVenuesBtn">
                            <i class="fas fa-arrow-left"></i> Back to Venues
                        </button>
                    </div>
                    
                    <h3 style="color: var(--primary-dark); margin-bottom: 20px;">
                        Available Halls at <span id="selectedVenueName"></span>
                    </h3>
                    
                    <div id="hallsGrid" class="hall-grid">
                        <!-- Halls will be loaded here dynamically -->
                    </div>
                </div>
                
                <!-- Form to proceed to next step -->
                <form id="step2Form" method="POST" action="/booking-step3.php" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="venue_id" id="selectedVenueId">
                    <input type="hidden" name="hall_id" id="selectedHallId">
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="booking-summary">
                    <h3 class="summary-title">Booking Summary</h3>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-star"></i> Event Type:</span>
                        <span class="summary-value"><?php echo clean($_SESSION['booking']['event_type']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="summary-value"><?php echo formatDate($_SESSION['booking']['booking_date']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-clock"></i> Shift:</span>
                        <span class="summary-value"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['booking']['shift'])); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-users"></i> Guests:</span>
                        <span class="summary-value"><?php echo $_SESSION['booking']['number_of_guests']; ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div id="selectedVenueInfo" class="d-none">
                        <div class="summary-item">
                            <span class="summary-label"><i class="fas fa-building"></i> Venue:</span>
                            <span class="summary-value" id="summaryVenueName"></span>
                        </div>
                    </div>
                    
                    <div id="selectedHallInfo" class="d-none">
                        <div class="summary-item">
                            <span class="summary-label"><i class="fas fa-door-open"></i> Hall:</span>
                            <span class="summary-value" id="summaryHallName"></span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Hall Price:</span>
                            <span class="summary-value" id="summaryHallPrice"></span>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <button type="button" class="btn btn-primary btn-block mt-3" id="proceedToMenuBtn">
                            <i class="fas fa-arrow-right"></i> Proceed to Menu Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    let selectedVenueId = null;
    let selectedHallId = null;
    let selectedVenueName = '';
    let selectedHallName = '';
    let selectedHallPrice = 0;
    
    // View halls button click
    $('.view-halls-btn').click(function() {
        selectedVenueId = $(this).data('venue-id');
        selectedVenueName = $(this).data('venue-name');
        
        // Show loading
        Swal.fire({
            title: 'Loading Halls...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Load halls via AJAX
        $.ajax({
            url: '/api/get-halls.php',
            method: 'GET',
            data: {
                venue_id: selectedVenueId,
                min_capacity: <?php echo $_SESSION['booking']['number_of_guests']; ?>,
                date: '<?php echo $_SESSION['booking']['booking_date']; ?>',
                shift: '<?php echo $_SESSION['booking']['shift']; ?>'
            },
            success: function(response) {
                Swal.close();
                
                if (response.success && response.halls.length > 0) {
                    displayHalls(response.halls);
                    $('#venuesSection').addClass('d-none');
                    $('#hallsSection').removeClass('d-none');
                    $('#selectedVenueName').text(selectedVenueName);
                    
                    // Update summary
                    $('#summaryVenueName').text(selectedVenueName);
                    $('#selectedVenueInfo').removeClass('d-none');
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'No Halls Available',
                        text: 'Sorry, no halls are available at this venue for your selected date and guest count.'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load halls. Please try again.'
                });
            }
        });
    });
    
    // Display halls
    function displayHalls(halls) {
        let hallsHTML = '';
        
        halls.forEach(function(hall) {
            let amenities = [];
            try {
                amenities = JSON.parse(hall.amenities || '[]');
            } catch (e) {
                amenities = [];
            }
            
            let availabilityClass = hall.available ? 'badge-success' : 'badge-danger';
            let availabilityText = hall.available ? 'Available' : 'Booked';
            let disabledClass = !hall.available ? 'disabled' : '';
            
            let imageSrc = hall.primary_image ? 
                '/<?php echo UPLOAD_PATH_HALLS; ?>' + hall.primary_image : 
                '/images/placeholder-hall.jpg';
            
            hallsHTML += `
                <div class="hall-card ${disabledClass}" data-hall-id="${hall.id}">
                    <img src="${imageSrc}" 
                         alt="${hall.hall_name}" 
                         class="hall-img"
                         onerror="this.src='/images/placeholder-hall.jpg'">
                    
                    <div class="hall-info">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="hall-name mb-0">${hall.hall_name}</h3>
                            <span class="badge ${availabilityClass}">${availabilityText}</span>
                        </div>
                        
                        <p class="hall-capacity">
                            <i class="fas fa-users"></i> Capacity: ${hall.capacity} pax | 
                            <i class="fas fa-tag"></i> ${hall.indoor_outdoor.charAt(0).toUpperCase() + hall.indoor_outdoor.slice(1)}
                        </p>
                        
                        <p class="hall-price"><?php echo CURRENCY_SYMBOL; ?>${parseFloat(hall.base_price).toLocaleString()}</p>
                        
                        ${hall.description ? `<p style="font-size: 0.9rem; color: var(--medium-gray);">${hall.description.substring(0, 100)}...</p>` : ''}
                        
                        ${amenities.length > 0 ? `
                            <div style="margin-top: 10px;">
                                <strong style="font-size: 0.9rem;">Amenities:</strong>
                                <div style="font-size: 0.85rem; color: var(--medium-gray); margin-top: 5px;">
                                    ${amenities.slice(0, 3).map(a => `<span class="badge" style="background: var(--light-gray); color: var(--dark-gray); margin: 2px;">${a}</span>`).join('')}
                                    ${amenities.length > 3 ? `<span>+${amenities.length - 3} more</span>` : ''}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${hall.available ? `
                            <button class="btn btn-primary btn-block mt-3 select-hall-btn" 
                                    data-hall-id="${hall.id}"
                                    data-hall-name="${hall.hall_name}"
                                    data-hall-price="${hall.base_price}">
                                <i class="fas fa-check"></i> Select This Hall
                            </button>
                        ` : `
                            <button class="btn btn-block mt-3" style="background: #ccc; cursor: not-allowed;" disabled>
                                <i class="fas fa-times"></i> Not Available
                            </button>
                        `}
                    </div>
                </div>
            `;
        });
        
        $('#hallsGrid').html(hallsHTML);
        
        // Attach click event to select hall buttons
        $('.select-hall-btn').click(function() {
            selectedHallId = $(this).data('hall-id');
            selectedHallName = $(this).data('hall-name');
            selectedHallPrice = $(this).data('hall-price');
            
            // Update UI
            $('.hall-card').removeClass('selected');
            $(this).closest('.hall-card').addClass('selected');
            
            // Update summary
            $('#summaryHallName').text(selectedHallName);
            $('#summaryHallPrice').text('<?php echo CURRENCY_SYMBOL; ?>' + parseFloat(selectedHallPrice).toLocaleString());
            $('#selectedHallInfo').removeClass('d-none');
            
            // Update hidden form fields
            $('#selectedVenueId').val(selectedVenueId);
            $('#selectedHallId').val(selectedHallId);
            
            // Scroll to summary
            $('html, body').animate({
                scrollTop: $('#selectedHallInfo').offset().top - 100
            }, 500);
        });
    }
    
    // Back to venues button
    $('#backToVenuesBtn').click(function() {
        $('#hallsSection').addClass('d-none');
        $('#venuesSection').removeClass('d-none');
        $('#selectedHallInfo').addClass('d-none');
        selectedHallId = null;
    });
    
    // Proceed to menu button
    $('#proceedToMenuBtn').click(function() {
        if (!selectedHallId) {
            Swal.fire({
                icon: 'warning',
                title: 'No Hall Selected',
                text: 'Please select a hall to proceed'
            });
            return;
        }
        
        // Submit form
        $('#step2Form').submit();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
