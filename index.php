<?php
/**
 * Landing Page - Step 1: Initial Booking Details
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Initialize session booking data if not exists
if (!isset($_SESSION['booking'])) {
    $_SESSION['booking'] = [];
}

$pageTitle = 'Book Your Perfect Venue - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Book Your Perfect Venue</h1>
        <p>Make your special day unforgettable with our premium venues and exceptional service</p>
    </div>
</section>

<!-- Booking Form Section -->
<section class="section">
    <div class="container">
        <div class="booking-form">
            <h2 class="text-center mb-4" style="color: var(--primary-dark);">Start Your Booking</h2>
            <p class="text-center mb-4" style="color: var(--medium-gray);">Fill in the details below to check availability and proceed with your booking</p>
            
            <form id="bookingStep1Form" method="POST" action="/booking-step2.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Shift Selection -->
                <div class="form-group">
                    <label class="form-label" for="shift">
                        <i class="fas fa-clock"></i> Select Shift *
                    </label>
                    <select class="form-control form-select" id="shift" name="shift" required>
                        <option value="">-- Choose Shift --</option>
                        <option value="morning" <?php echo ($_SESSION['booking']['shift'] ?? '') == 'morning' ? 'selected' : ''; ?>>
                            Morning (8:00 AM - 12:00 PM)
                        </option>
                        <option value="afternoon" <?php echo ($_SESSION['booking']['shift'] ?? '') == 'afternoon' ? 'selected' : ''; ?>>
                            Afternoon (12:00 PM - 5:00 PM)
                        </option>
                        <option value="evening" <?php echo ($_SESSION['booking']['shift'] ?? '') == 'evening' ? 'selected' : ''; ?>>
                            Evening (5:00 PM - 11:00 PM)
                        </option>
                        <option value="full_day" <?php echo ($_SESSION['booking']['shift'] ?? '') == 'full_day' ? 'selected' : ''; ?>>
                            Full Day (8:00 AM - 11:00 PM)
                        </option>
                    </select>
                </div>
                
                <!-- Date Selection -->
                <div class="form-group">
                    <label class="form-label" for="booking_date">
                        <i class="fas fa-calendar-alt"></i> Select Date *
                    </label>
                    <input type="date" 
                           class="form-control" 
                           id="booking_date" 
                           name="booking_date" 
                           value="<?php echo $_SESSION['booking']['booking_date'] ?? ''; ?>"
                           min="<?php echo date('Y-m-d', strtotime('+' . BOOKING_BUFFER_DAYS . ' days')); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+' . MAX_ADVANCE_BOOKING_DAYS . ' days')); ?>"
                           required>
                    <small class="form-text text-muted">Select a date between <?php echo BOOKING_BUFFER_DAYS; ?> days to <?php echo MAX_ADVANCE_BOOKING_DAYS; ?> days from today</small>
                </div>
                
                <!-- Number of Guests -->
                <div class="form-group">
                    <label class="form-label" for="number_of_guests">
                        <i class="fas fa-users"></i> Number of Guests *
                    </label>
                    <input type="number" 
                           class="form-control" 
                           id="number_of_guests" 
                           name="number_of_guests" 
                           value="<?php echo $_SESSION['booking']['number_of_guests'] ?? ''; ?>"
                           min="10" 
                           placeholder="Minimum 10 guests"
                           required>
                    <small class="form-text text-muted">Minimum 10 guests required</small>
                </div>
                
                <!-- Event Type -->
                <div class="form-group">
                    <label class="form-label" for="event_type">
                        <i class="fas fa-star"></i> Event Type *
                    </label>
                    <select class="form-control form-select" id="event_type" name="event_type" required>
                        <option value="">-- Choose Event Type --</option>
                        <option value="Wedding" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Wedding' ? 'selected' : ''; ?>>
                            Wedding
                        </option>
                        <option value="Birthday Party" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Birthday Party' ? 'selected' : ''; ?>>
                            Birthday Party
                        </option>
                        <option value="Corporate Event" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Corporate Event' ? 'selected' : ''; ?>>
                            Corporate Event
                        </option>
                        <option value="Anniversary" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Anniversary' ? 'selected' : ''; ?>>
                            Anniversary
                        </option>
                        <option value="Conference" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Conference' ? 'selected' : ''; ?>>
                            Conference
                        </option>
                        <option value="Other Events" <?php echo ($_SESSION['booking']['event_type'] ?? '') == 'Other Events' ? 'selected' : ''; ?>>
                            Other Events
                        </option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-search"></i> CHECK AVAILABILITY & PROCEED
                    </button>
                </div>
                
                <p class="text-center mt-3" style="font-size: 0.875rem; color: var(--medium-gray);">
                    <i class="fas fa-info-circle"></i> All fields marked with * are required
                </p>
            </form>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section" style="background-color: white;">
    <div class="container">
        <h2 class="section-title">Why Choose Us</h2>
        
        <div class="row mt-5">
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-building" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <h4 style="color: var(--primary-dark); margin-bottom: 15px;">Premium Venues</h4>
                    <p style="color: var(--medium-gray);">Choose from our selection of top-rated venues across Nepal</p>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-utensils" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <h4 style="color: var(--primary-dark); margin-bottom: 15px;">Delicious Menus</h4>
                    <p style="color: var(--medium-gray);">Wide variety of menu options to suit every taste and budget</p>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-hands-helping" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <h4 style="color: var(--primary-dark); margin-bottom: 15px;">Full Service</h4>
                    <p style="color: var(--medium-gray);">Complete event management from decoration to entertainment</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('#shift, #event_type').select2({
        placeholder: '-- Select --',
        allowClear: true
    });
    
    // Form validation
    $('#bookingStep1Form').validate({
        rules: {
            shift: {
                required: true
            },
            booking_date: {
                required: true,
                date: true
            },
            number_of_guests: {
                required: true,
                number: true,
                min: 10
            },
            event_type: {
                required: true
            }
        },
        messages: {
            shift: "Please select a shift",
            booking_date: "Please select a valid date",
            number_of_guests: {
                required: "Please enter number of guests",
                min: "Minimum 10 guests required"
            },
            event_type: "Please select an event type"
        },
        errorClass: 'error-message',
        submitHandler: function(form) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Checking availability',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            form.submit();
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
