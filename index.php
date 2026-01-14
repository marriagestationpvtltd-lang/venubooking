<?php
$page_title = 'Book Your Event';
require_once __DIR__ . '/includes/header.php';

// Get banner images
$banner_images = getImagesBySection('banner', 1);
$banner_image = !empty($banner_images) ? $banner_images[0] : null;
?>

<!-- Hero Section -->
<section class="hero-section" <?php if ($banner_image): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo htmlspecialchars($banner_image['image_url']); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;"<?php endif; ?>>
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
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Event Date
                                </label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
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
$extra_js = '<script src="' . BASE_URL . '/js/booking-flow.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
