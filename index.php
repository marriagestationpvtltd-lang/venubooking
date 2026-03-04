<?php
$page_title = 'Book Your Event';
require_once __DIR__ . '/includes/header.php';

// Get banner images
$banner_images = getImagesBySection('banner', 1);
$banner_image = !empty($banner_images) ? $banner_images[0] : null;

// Get all active cities for the city filter dropdown
$cities = getAllCities();

// Get service packages grouped by category
$service_categories = getServicePackagesByCategory();
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
                        <form id="bookingForm" method="POST" action="booking-step2.php" novalidate>
                            <input type="hidden" id="preferred_venue_id" name="preferred_venue_id" value="">
                            <div class="mb-3">
                                <label for="city_id" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Select City <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="city_id" name="city_id" required>
                                    <option value="">Choose a city...</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo $city['id']; ?>">
                                            <?php echo htmlspecialchars($city['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a city.</div>
                            </div>

                            <div class="mb-3">
                                <label for="shift" class="form-label">
                                    <i class="fas fa-clock"></i> Select Shift <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="">Choose a shift...</option>
                                    <option value="morning">Morning (6:00 AM - 12:00 PM)</option>
                                    <option value="afternoon">Afternoon (12:00 PM - 6:00 PM)</option>
                                    <option value="evening">Evening (6:00 PM - 12:00 AM)</option>
                                    <option value="fullday">Full Day</option>
                                </select>
                                <div class="invalid-feedback">Please select a shift.</div>
                            </div>

                            <div class="mb-3">
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Event Date <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="event_date" name="event_date" 
                                           readonly placeholder="Select event date (Click to open calendar)" required>
                                    <button class="btn btn-outline-success" type="button" id="toggleCalendar" title="Toggle between BS/AD calendar">
                                        <i class="fas fa-exchange-alt"></i> <span id="calendarType">BS</span>
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    <span id="nepaliDateDisplay"></span>
                                </small>
                                <div class="invalid-feedback">Please select an event date.</div>
                            </div>

                            <div class="mb-3">
                                <label for="guests" class="form-label">
                                    <i class="fas fa-users"></i> Number of Guests <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="guests" name="guests" 
                                       min="10" max="10000" placeholder="Enter number of guests (minimum 10)" required>
                                <div class="invalid-feedback">Please enter number of guests (minimum 10).</div>
                            </div>

                            <div class="mb-4">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-tag"></i> Event Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Choose event type...</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Birthday Party">Birthday Party</option>
                                    <option value="Corporate Event">Corporate Event</option>
                                    <option value="Anniversary">Anniversary</option>
                                    <option value="Other Events">Other Events</option>
                                </select>
                                <div class="invalid-feedback">Please select an event type.</div>
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
                                // Prepare images array - use gallery images if available, otherwise use venue's main image
                                $images_to_display = [];
                                
                                if (!empty($venue['gallery_images']) && count($venue['gallery_images']) > 0) {
                                    // Use hall images
                                    $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                                    foreach ($venue['gallery_images'] as $gallery_image) {
                                        $safe_url = $upload_url_base . rawurlencode($gallery_image['image_path']);
                                        $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                                    }
                                } else if (!empty($venue['image'])) {
                                    // Use venue's main image
                                    $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                                    $safe_url = $upload_url_base . rawurlencode($venue['image']);
                                    $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                                } else {
                                    // Use placeholder
                                    $images_to_display[] = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                                }
                                
                                // Generate unique carousel ID for this venue
                                $carousel_id = 'venueImageCarousel' . $venue['id'];
                                
                                // Truncate description and add ellipsis only if needed
                                $description = sanitize($venue['description']);
                                $truncated_description = substr($description, 0, 100);
                                if (strlen($description) > 100) {
                                    $truncated_description .= '...';
                                }
                            ?>
                                <div class="col-md-4">
                                    <div class="venue-card-home card h-100 shadow-sm">
                                        <?php if (count($images_to_display) > 1): ?>
                                            <!-- Carousel for multiple images -->
                                            <div id="<?php echo $carousel_id; ?>" class="carousel slide venue-image-carousel" data-bs-ride="carousel">
                                                <div class="carousel-inner">
                                                    <?php foreach ($images_to_display as $img_index => $image_url): ?>
                                                        <div class="carousel-item <?php echo $img_index === 0 ? 'active' : ''; ?>">
                                                            <div class="venue-image-home" style="background-image: url('<?php echo $image_url; ?>');"></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="prev">
                                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                    <span class="visually-hidden">Previous</span>
                                                </button>
                                                <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="next">
                                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                    <span class="visually-hidden">Next</span>
                                                </button>
                                                <!-- Image counter indicator -->
                                                <div class="carousel-indicators-counter">
                                                    <span class="badge bg-dark bg-opacity-75">
                                                        <i class="fas fa-images"></i> <?php echo count($images_to_display); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Single image display -->
                                            <div class="card-img-top venue-image-home" style="background-image: url('<?php echo $images_to_display[0]; ?>');"></div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo sanitize($venue['name']); ?></h5>
                                            <p class="card-text">
                                                <i class="fas fa-map-marker-alt text-success"></i> 
                                                <?php echo sanitize($venue['city_name'] ?? $venue['location']); ?>
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
// Get all active vendors and office WhatsApp number for the vendor listing section
$vendors = getVendors();
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);
if (!empty($vendors)):
?>
<!-- Vendors Section -->
<section class="vendors-section py-5">
    <div class="container">
        <h2 class="text-center mb-2">Our Vendors</h2>
        <p class="text-center text-muted mb-5">Meet the professionals who make your event special</p>

        <div id="vendorsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000" data-bs-pause="hover" data-bs-touch="false">
            <div class="carousel-indicators">
                <?php
                $vendor_chunks = array_chunk($vendors, 3);
                foreach ($vendor_chunks as $vi => $vchunk):
                ?>
                    <button type="button" data-bs-target="#vendorsCarousel" data-bs-slide-to="<?php echo $vi; ?>"
                            <?php echo $vi === 0 ? 'class="active" aria-current="true"' : ''; ?>
                            aria-label="Slide <?php echo $vi + 1; ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="carousel-inner pb-4">
                <?php foreach ($vendor_chunks as $vi => $vchunk): ?>
                    <div class="carousel-item <?php echo $vi === 0 ? 'active' : ''; ?>">
                        <div class="row g-4 justify-content-center">
                            <?php foreach ($vchunk as $vendor):
                                $vendor_type_label = htmlspecialchars(getVendorTypeLabel($vendor['type']), ENT_QUOTES, 'UTF-8');
                                $vendor_name       = htmlspecialchars($vendor['name'], ENT_QUOTES, 'UTF-8');
                                $vendor_location   = htmlspecialchars($vendor['city_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                $vendor_address    = htmlspecialchars($vendor['address'] ?? '', ENT_QUOTES, 'UTF-8');
                                $vendor_notes      = htmlspecialchars($vendor['notes'] ?? '', ENT_QUOTES, 'UTF-8');

                                // Build WhatsApp URL for Contact Us (use plain text values, not HTML-escaped)
                                $wa_vendor_name = strip_tags($vendor['name']);
                                $wa_vendor_type = strip_tags(getVendorTypeLabel($vendor['type']));
                                $wa_message = "Hello, I am interested in your vendor: {$wa_vendor_name} ({$wa_vendor_type}). Please contact me with more details.";
                                $wa_url = '';
                                if (!empty($clean_office_whatsapp)) {
                                    $wa_url = 'https://wa.me/' . $clean_office_whatsapp . '?text=' . rawurlencode($wa_message);
                                }

                                // Build additional info slides (address and/or notes)
                                $extra_slides = [];
                                if (!empty($vendor['address'])) {
                                    $extra_slides[] = ['icon' => 'fas fa-map-marker-alt', 'label' => 'Address', 'value' => $vendor_address];
                                }
                                if (!empty($vendor['notes'])) {
                                    $extra_slides[] = ['icon' => 'fas fa-info-circle', 'label' => 'About', 'value' => $vendor_notes];
                                }

                                $detail_carousel_id = 'vendorDetail' . (int)$vendor['id'];
                            ?>
                                <div class="col-md-4">
                                    <div class="vendor-card card h-100 shadow-sm">
                                        <!-- Vendor Photo -->
                                        <?php if (!empty($vendor['photo'])): ?>
                                            <img src="<?php echo htmlspecialchars(rtrim(UPLOAD_URL, '/') . '/' . rawurlencode($vendor['photo']), ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo $vendor_name; ?>"
                                                 class="vendor-photo">
                                        <?php else: ?>
                                            <div class="vendor-photo vendor-photo-placeholder">
                                                <i class="fas fa-user-tie fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>

                                        <div class="card-body d-flex flex-column">
                                            <!-- Vendor Type Badge -->
                                            <span class="badge bg-success mb-2 align-self-start">
                                                <i class="fas fa-tag me-1"></i><?php echo $vendor_type_label; ?>
                                            </span>
                                            <!-- Vendor Name -->
                                            <h5 class="card-title mb-1"><?php echo $vendor_name; ?></h5>
                                            <!-- Vendor Location -->
                                            <?php if (!empty($vendor_location)): ?>
                                                <p class="card-text text-muted mb-2">
                                                    <i class="fas fa-map-marker-alt text-success"></i>
                                                    <?php echo $vendor_location; ?>
                                                </p>
                                            <?php endif; ?>

                                            <!-- Additional info slider -->
                                            <?php if (!empty($extra_slides)): ?>
                                                <div id="<?php echo $detail_carousel_id; ?>" class="carousel slide vendor-detail-carousel mb-3" data-bs-ride="false">
                                                    <div class="carousel-inner">
                                                        <?php foreach ($extra_slides as $si => $slide): ?>
                                                            <div class="carousel-item <?php echo $si === 0 ? 'active' : ''; ?>">
                                                                <div class="vendor-detail-slide p-2 rounded bg-light">
                                                                    <small class="text-muted d-block fw-semibold mb-1">
                                                                        <i class="<?php echo htmlspecialchars($slide['icon'], ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php echo htmlspecialchars($slide['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                                    </small>
                                                                    <small><?php echo $slide['value']; ?></small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if (count($extra_slides) > 1): ?>
                                                        <button class="carousel-control-prev vendor-detail-prev" type="button"
                                                                data-bs-target="#<?php echo $detail_carousel_id; ?>" data-bs-slide="prev">
                                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Previous</span>
                                                        </button>
                                                        <button class="carousel-control-next vendor-detail-next" type="button"
                                                                data-bs-target="#<?php echo $detail_carousel_id; ?>" data-bs-slide="next">
                                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Next</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Contact Us button -->
                                            <div class="mt-auto">
                                                <?php if (!empty($wa_url)): ?>
                                                    <a href="<?php echo htmlspecialchars($wa_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                       target="_blank" rel="noopener noreferrer"
                                                       class="btn btn-success w-100">
                                                        <i class="fab fa-whatsapp me-1"></i> Contact Us
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-success w-100" disabled>
                                                        <i class="fab fa-whatsapp me-1"></i> Contact Us
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($vendor_chunks) > 1): ?>
                <button class="carousel-control-prev vendors-carousel-prev" type="button"
                        data-bs-target="#vendorsCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next vendors-carousel-next" type="button"
                        data-bs-target="#vendorsCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($service_categories)): ?>
<!-- Service Packages Section -->
<section class="service-packages-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-2">हाम्रा सेवा प्याकेजहरू</h2>
        <p class="text-center text-muted mb-5">तपाईंको अनुष्ठानको लागि उत्तम प्याकेज छान्नुहोस्</p>

        <?php foreach ($service_categories as $cat): ?>
            <?php if (empty($cat['packages'])) continue; ?>
            <div class="service-category-block mb-5">
                <h3 class="service-category-title mb-4">
                    <span class="category-label"><?php echo htmlspecialchars($cat['name']); ?></span>
                </h3>
                <div class="row g-4">
                    <?php foreach ($cat['packages'] as $pkg):
                        $pkg_carousel_id = 'pkgCarousel' . (int)$pkg['id'];
                    ?>
                        <div class="col-md-4">
                            <div class="package-card card h-100 shadow-sm">
                                <?php if (!empty($pkg['photos'])): ?>
                                    <?php if (count($pkg['photos']) > 1): ?>
                                        <div id="<?php echo $pkg_carousel_id; ?>" class="carousel slide package-photo-carousel" data-bs-ride="carousel">
                                            <div class="carousel-inner">
                                                <?php foreach ($pkg['photos'] as $pi => $photo_path): ?>
                                                    <div class="carousel-item <?php echo $pi === 0 ? 'active' : ''; ?>">
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo_path, ENT_QUOTES, 'UTF-8'); ?>"
                                                             class="d-block w-100 package-carousel-img"
                                                             alt="<?php echo htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?> photo">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $pkg_carousel_id; ?>" data-bs-slide="prev" aria-label="Previous photo">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Previous</span>
                                            </button>
                                            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $pkg_carousel_id; ?>" data-bs-slide="next" aria-label="Next photo">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Next</span>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($pkg['photos'][0], ENT_QUOTES, 'UTF-8'); ?>"
                                             class="card-img-top package-carousel-img"
                                             alt="<?php echo htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?> photo">
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="package-name text-center mb-3">
                                        <?php echo htmlspecialchars($pkg['name']); ?>
                                    </h5>
                                    <div class="package-price text-center mb-3">
                                        <span class="price-label"><?php echo formatCurrency($pkg['price']); ?></span>
                                    </div>
                                    <?php if (!empty($pkg['features'])): ?>
                                        <ul class="package-features list-unstyled mb-3">
                                            <?php foreach ($pkg['features'] as $feat): ?>
                                                <li class="feature-item">
                                                    <span class="text-success me-2">&#10004;</span>
                                                    <?php echo htmlspecialchars($feat); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($pkg['description'])): ?>
                                        <div class="mt-auto">
                                            <a class="btn btn-outline-success btn-sm w-100 read-more-btn"
                                               data-bs-toggle="collapse"
                                               href="#pkgDesc<?php echo (int)$pkg['id']; ?>"
                                               role="button"
                                               aria-expanded="false">
                                                <i class="fas fa-chevron-down me-1"></i> Read More
                                            </a>
                                            <div class="collapse mt-2" id="pkgDesc<?php echo (int)$pkg['id']; ?>">
                                                <div class="card card-body bg-light border-0 small">
                                                    <?php echo nl2br(htmlspecialchars($pkg['description'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
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
