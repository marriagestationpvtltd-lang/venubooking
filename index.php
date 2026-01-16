<?php
$page_title = 'Book Your Event';
require_once __DIR__ . '/includes/header.php';

// Get banner images
$banner_images = getImagesBySection('banner', 1);
$banner_image = !empty($banner_images) ? $banner_images[0] : null;
?>

<!-- Hero Section -->
<section class="hero-section<?php if ($banner_image): ?> with-banner-image<?php endif; ?>" id="bookingForm" <?php if ($banner_image): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo htmlspecialchars($banner_image['image_url']); ?>');"<?php endif; ?>>
    <div class="hero-overlay">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <h1 class="display-4 text-white fw-bold mb-4">
                        Book Your Perfect Venue
                    </h1>
                    <p class="lead text-white mb-5">
                        Find and book the ideal venue for your wedding, birthday party, corporate event, or any special occasion.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="booking-card">
                        <h3 class="text-center mb-4 text-success">
                            <i class="fas fa-calendar-check"></i> Start Your Booking
                        </h3>
                        <form id="bookingForm" method="POST" action="booking-step2.php">
                            <input type="hidden" id="preferred_venue_id" name="preferred_venue_id" value="">
                            <div class="mb-3">
                                <label for="shift" class="form-label">
                                    <i class="fas fa-clock"></i> Select Shift
                                </label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="">Choose a shift...</option>
                                    <option value="morning">Morning (6:00 AM - 12:00 PM)</option>
                                    <option value="afternoon">Afternoon (12:00 PM - 6:00 PM)</option>
                                    <option value="evening">Evening (6:00 PM - 12:00 AM)</option>
                                    <option value="fullday">Full Day</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="booking_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Event Date
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="booking_date" name="event_date" 
                                           readonly placeholder="Select Nepali Date (BS)" required>
                                    <button class="btn btn-outline-success" type="button" id="toggleCalendar" title="Current Calendar Mode (Click to toggle)">
                                        <i class="fas fa-exchange-alt"></i> <span id="calendarType">BS</span>
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    <span id="nepaliDateDisplay"></span>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="guests" class="form-label">
                                    <i class="fas fa-users"></i> Number of Guests
                                </label>
                                <input type="number" class="form-control" id="guests" name="guests" 
                                       min="10" placeholder="Minimum 10 guests" required>
                            </div>

                            <div class="mb-4">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-tag"></i> Event Type
                                </label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Choose event type...</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Birthday Party">Birthday Party</option>
                                    <option value="Corporate Event">Corporate Event</option>
                                    <option value="Anniversary">Anniversary</option>
                                    <option value="Other Events">Other Events</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-arrow-right"></i> ONLINE BOOKING
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <h2 class="text-center mb-5">Why Choose Us</h2>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-building fa-3x text-success"></i>
                    </div>
                    <h5>Multiple Venues</h5>
                    <p>Choose from our premium venues across the city</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-utensils fa-3x text-success"></i>
                    </div>
                    <h5>Delicious Menus</h5>
                    <p>Wide variety of menu options to suit every taste</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-dollar-sign fa-3x text-success"></i>
                    </div>
                    <h5>Transparent Pricing</h5>
                    <p>No hidden charges, clear pricing structure</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-headset fa-3x text-success"></i>
                    </div>
                    <h5>24/7 Support</h5>
                    <p>Our team is always here to help you</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Get all active venues for carousel
$venues = getAllActiveVenues();
if (!empty($venues)):
?>
<!-- Venues Section -->
<section class="venues-section py-5">
    <div class="container">
        <h2 class="text-center mb-4">Our Venues</h2>
        <p class="text-center text-muted mb-5">Explore our premium venues and start booking</p>
        
        <div id="venuesCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php 
                $venue_chunks = array_chunk($venues, 3); // 3 venues per slide
                foreach ($venue_chunks as $index => $chunk): 
                ?>
                    <button type="button" data-bs-target="#venuesCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                            <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                            aria-label="Slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
            
            <div class="carousel-inner">
                <?php foreach ($venue_chunks as $index => $chunk): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <div class="row g-4">
                            <?php foreach ($chunk as $venue): 
                                // Get image URL
                                if (!empty($venue['image'])) {
                                    // Ensure UPLOAD_URL ends with a slash
                                    $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                                    $safe_url = $upload_url_base . rawurlencode($venue['image']);
                                    $venue_image_url = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                                } else {
                                    $venue_image_url = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                                }
                                
                                // Truncate description and add ellipsis only if needed
                                $description = sanitize($venue['description']);
                                $truncated_description = substr($description, 0, 100);
                                if (strlen($description) > 100) {
                                    $truncated_description .= '...';
                                }
                            ?>
                                <div class="col-md-4">
                                    <div class="venue-card-home card h-100 shadow-sm">
                                        <div class="card-img-top venue-image-home" style="background-image: url('<?php echo $venue_image_url; ?>');">
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo sanitize($venue['name']); ?></h5>
                                            <p class="card-text">
                                                <i class="fas fa-map-marker-alt text-success"></i> 
                                                <?php echo sanitize($venue['location']); ?>
                                            </p>
                                            <p class="card-text text-muted">
                                                <?php echo $truncated_description; ?>
                                            </p>
                                            <button type="button" 
                                                    class="btn btn-success w-100 venue-book-btn"
                                                    data-venue-id="<?php echo $venue['id']; ?>"
                                                    data-venue-name="<?php echo sanitize($venue['name']); ?>">
                                                <i class="fas fa-calendar-check"></i> Book Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($venue_chunks) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#venuesCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#venuesCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// Get gallery images
$gallery_images = getImagesBySection('gallery', 6);
if (!empty($gallery_images)):
?>
<!-- Gallery Section -->
<section class="gallery-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Our Gallery</h2>
        <div class="row g-4">
            <?php foreach ($gallery_images as $image): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="gallery-item">
                        <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($image['title']); ?>" 
                             class="img-fluid rounded shadow-sm"
                             style="width: 100%; height: 250px; object-fit: cover;">
                        <?php if ($image['title']): ?>
                            <div class="gallery-caption mt-2">
                                <h6 class="mb-0"><?php echo htmlspecialchars($image['title']); ?></h6>
                                <?php if ($image['description']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($image['description']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$extra_js = '
<script src="' . BASE_URL . '/js/booking-flow.js"></script>
<script>
// Handle venue book button clicks
document.addEventListener("DOMContentLoaded", function() {
    const venueBookButtons = document.querySelectorAll(".venue-book-btn");
    
    venueBookButtons.forEach(button => {
        button.addEventListener("click", function() {
            const venueId = this.getAttribute("data-venue-id");
            const venueName = this.getAttribute("data-venue-name");
            
            // Store venue preference in sessionStorage
            sessionStorage.setItem("preferred_venue_id", venueId);
            sessionStorage.setItem("preferred_venue_name", venueName);
            
            // Set hidden field in form
            const hiddenField = document.getElementById("preferred_venue_id");
            if (hiddenField) {
                hiddenField.value = venueId;
            }
            
            // Scroll to booking form
            const bookingFormSection = document.getElementById("bookingForm");
            if (bookingFormSection) {
                bookingFormSection.scrollIntoView({ behavior: "smooth" });
                
                // Optional: Add a visual highlight to the form
                const bookingCard = document.querySelector(".booking-card");
                if (bookingCard) {
                    bookingCard.style.animation = "pulse 0.5s ease-in-out";
                    setTimeout(() => {
                        bookingCard.style.animation = "";
                    }, 500);
                }
            }
        });
    });
    
    // Show preferred venue message if set
    const preferredVenueName = sessionStorage.getItem("preferred_venue_name");
    if (preferredVenueName) {
        const bookingCard = document.querySelector(".booking-card h3");
        if (bookingCard) {
            const infoDiv = document.createElement("div");
            infoDiv.className = "alert alert-info alert-dismissible fade show mb-3";
            infoDiv.innerHTML = `
                <i class="fas fa-info-circle"></i> You selected <strong>${preferredVenueName}</strong>. 
                Complete the booking details below to continue.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            bookingCard.parentNode.insertBefore(infoDiv, bookingCard.nextSibling);
        }
        
        // Set hidden field value
        const hiddenField = document.getElementById("preferred_venue_id");
        const venueId = sessionStorage.getItem("preferred_venue_id");
        if (hiddenField && venueId) {
            hiddenField.value = venueId;
        }
    }
    
    // Clear session storage after form submission
    const form = document.getElementById("bookingForm");
    if (form) {
        form.addEventListener("submit", function() {
            // Keep the value in sessionStorage for the next page
            // It will be cleared after step2 loads
        });
    }
});
</script>
<style>
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}
</style>
';
require_once __DIR__ . '/includes/footer.php';
?>
