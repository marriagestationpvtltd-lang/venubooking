<?php
$page_title = 'Book Your Event';
require_once __DIR__ . '/includes/header.php';

// Get ALL banner images (not limited to 1)
$banner_images = getImagesBySection('banner');
$banner_image = !empty($banner_images) ? $banner_images[0] : null;

// Get all active cities for the city filter dropdown
$cities = getAllCities();

// Get service packages grouped by category
$service_categories = getServicePackagesByCategory();
?>

<!-- Hero Section -->
<section class="hero-section<?php if (!empty($banner_images)): ?> with-banner-image<?php endif; ?>" id="bookingForm">
    <?php if (count($banner_images) > 1): ?>
    <!-- Multi-image banner carousel (fills entire hero as background, auto-plays) -->
    <div id="heroBannerCarousel" class="carousel slide hero-banner-carousel" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-inner">
            <?php foreach ($banner_images as $bi => $bimg): ?>
                <div class="carousel-item <?php echo $bi === 0 ? 'active' : ''; ?>">
                    <div class="hero-banner-slide" style="background-image: url('<?php echo htmlspecialchars($bimg['image_url'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($banner_image): ?>
    <!-- Single banner image -->
    <div class="hero-banner-single" style="background-image: url('<?php echo htmlspecialchars($banner_image['image_url'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
    <?php endif; ?>

    <div class="hero-overlay">
        <div class="container">
            <div class="row align-items-center py-5 py-lg-0 min-vh-lg-100">
                <div class="col-lg-6 order-lg-1 order-2 mt-4 mt-lg-0">
                    <h1 class="display-4 text-white fw-bold mb-4">
                        Book Your Perfect Venue
                    </h1>
                    <p class="lead text-white mb-5">
                        Find and book the ideal venue for your wedding, birthday party, corporate event, or any special occasion.
                    </p>
                </div>
                <div class="col-lg-6 order-lg-2 order-1">
                    <div class="booking-card">
                        <h4 class="text-center mb-3 text-success">
                            <i class="fas fa-calendar-check"></i> Start Your Booking
                        </h4>
                        <form id="bookingForm" method="POST" action="booking-step2.php" novalidate>
                            <input type="hidden" id="preferred_venue_id" name="preferred_venue_id" value="">
                            <div class="mb-2">
                                <label for="city_id" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Select City <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-sm" id="city_id" name="city_id" required>
                                    <option value="">Choose a city...</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo $city['id']; ?>">
                                            <?php echo htmlspecialchars($city['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a city.</div>
                            </div>

                            <div class="mb-2">
                                <label for="shift" class="form-label">
                                    <i class="fas fa-clock"></i> Select Shift <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-sm" id="shift" name="shift" required>
                                    <option value="">Choose a shift...</option>
                                    <option value="morning">Morning (6:00 AM - 12:00 PM)</option>
                                    <option value="afternoon">Afternoon (12:00 PM - 6:00 PM)</option>
                                    <option value="evening">Evening (6:00 PM - 12:00 AM)</option>
                                    <option value="fullday">Full Day</option>
                                </select>
                                <div class="invalid-feedback">Please select a shift.</div>
                            </div>

                            <div class="mb-2">
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Event Date <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
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

                            <div class="mb-2">
                                <label for="guests" class="form-label">
                                    <i class="fas fa-users"></i> Number of Guests <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-sm" id="guests" name="guests" 
                                       min="10" max="10000" placeholder="Enter number of guests (minimum 10)" required>
                                <div class="invalid-feedback">Please enter number of guests (minimum 10).</div>
                            </div>

                            <div class="mb-3">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-tag"></i> Event Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-sm" id="event_type" name="event_type" required>
                                    <option value="">Choose event type...</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Birthday Party">Birthday Party</option>
                                    <option value="Corporate Event">Corporate Event</option>
                                    <option value="Anniversary">Anniversary</option>
                                    <option value="Other Events">Other Events</option>
                                </select>
                                <div class="invalid-feedback">Please select an event type.</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-arrow-right"></i> ONLINE BOOKING
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($service_categories)): ?>
<!-- Service Packages Section -->
<section class="service-packages-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center section-title mb-2">हाम्रा सेवा प्याकेजहरू</h2>
        <p class="text-center text-muted mb-5">तपाईंको अनुष्ठानको लागि उत्तम प्याकेज छान्नुहोस्</p>

        <?php foreach ($service_categories as $cat): ?>
            <?php if (empty($cat['packages'])) continue; ?>
            <div class="service-category-block mb-5">
                <h3 class="service-category-title mb-4">
                    <span class="category-label"><?php echo htmlspecialchars($cat['name']); ?></span>
                </h3>
                <div class="pkg-slider-wrapper">
                <div class="pkg-slider-track" data-pkg-slider>
                    <?php foreach ($cat['packages'] as $pkg):
                        $pkg_carousel_id = 'pkgCarousel' . (int)$pkg['id'];
                    ?>
                        <div class="pkg-slider-card">
                            <div class="package-card card h-100 shadow-sm">
                                <?php if (!empty($pkg['photos'])): ?>
                                    <?php if (count($pkg['photos']) > 1): ?>
                                        <div id="<?php echo $pkg_carousel_id; ?>" class="carousel slide package-photo-carousel" data-bs-ride="carousel">
                                            <div class="carousel-inner">
                                                <?php foreach ($pkg['photos'] as $pi => $photo_path): ?>
                                                    <div class="carousel-item <?php echo $pi === 0 ? 'active' : ''; ?>">
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo_path, ENT_QUOTES, 'UTF-8'); ?>"
                                                             class="d-block w-100 package-carousel-img"
                                                             loading="lazy"
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
                                             loading="lazy"
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
                                        <?php
                                        // Build WhatsApp message with package details
                                        $wa_pkg_name  = strip_tags($pkg['name']);
                                        $wa_pkg_price = strip_tags(formatCurrency($pkg['price']));
                                        $wa_pkg_msg   = "Hello, I would like to know more about this package:\n\nPackage: {$wa_pkg_name}\nPrice: {$wa_pkg_price}";
                                        if (!empty($pkg['features'])) {
                                            $wa_pkg_msg .= "\n\nFeatures:";
                                            foreach ($pkg['features'] as $feat) {
                                                $wa_pkg_msg .= "\n- " . strip_tags($feat);
                                            }
                                        }
                                        $wa_pkg_msg .= "\n\nDescription:\n" . strip_tags($pkg['description']);
                                        $wa_pkg_msg .= "\n\nPlease provide me with more details.";
                                        $pkg_wa_url = '';
                                        if (!empty($clean_office_whatsapp)) {
                                            $pkg_wa_url = 'https://wa.me/' . $clean_office_whatsapp . '?text=' . rawurlencode($wa_pkg_msg);
                                        }
                                        ?>
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
                                                <?php if (!empty($pkg_wa_url)): ?>
                                                    <a href="<?php echo htmlspecialchars($pkg_wa_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                       target="_blank" rel="noopener noreferrer"
                                                       class="btn btn-success btn-sm w-100 mt-2">
                                                        <i class="fab fa-whatsapp me-1"></i> Contact Us
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-success btn-sm w-100 mt-2" disabled>
                                                        <i class="fab fa-whatsapp me-1"></i> Contact Us
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <h2 class="text-center section-title mb-5">Why Choose Us</h2>
        <div class="row g-4">
            <div class="col-6 col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-building fa-3x text-success"></i>
                    </div>
                    <h5>Multiple Venues</h5>
                    <p>Choose from our premium venues across the city</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-utensils fa-3x text-success"></i>
                    </div>
                    <h5>Delicious Menus</h5>
                    <p>Wide variety of menu options to suit every taste</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-dollar-sign fa-3x text-success"></i>
                    </div>
                    <h5>Transparent Pricing</h5>
                    <p>No hidden charges, clear pricing structure</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
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
        <h2 class="text-center section-title mb-4">Our Venues</h2>
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
                                <div class="col-12 col-sm-6 col-md-4">
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
// Get gallery images grouped into photo cards (max 10 per card)
$gallery_cards = getImagesByCards('gallery');
if (!empty($gallery_cards)):
    // Build a flat JS-safe data array for the card modal
    $gallery_cards_json = json_encode(array_map(function($card) {
        return array_map(function($img) {
            return [
                'src'   => $img['image_url'],
                'title' => $img['title'],
                'desc'  => $img['description'] ?? '',
            ];
        }, $card);
    }, $gallery_cards), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!-- Gallery Section – Photo Cards -->
<section class="gallery-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center section-title mb-2">Our Gallery</h2>
        <p class="text-center text-muted mb-5">Moments we are proud to capture</p>

        <div class="photo-cards-grid">
            <?php foreach ($gallery_cards as $ci => $card):
                $preview     = $card[0];
                $total       = count($card);
                $extra       = $total - 1;
            ?>
            <div class="photo-card" role="button" tabindex="0"
                 data-card-index="<?php echo $ci; ?>"
                 aria-label="View card <?php echo $ci + 1; ?> (<?php echo $total; ?> photo<?php echo $total !== 1 ? 's' : ''; ?>)">

                <div class="photo-card-img-wrap">
                    <!-- Preview image only; remaining images load in modal -->
                    <img src="<?php echo htmlspecialchars($preview['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="photo-card-img"
                         loading="lazy">

                    <?php if ($extra > 0): ?>
                    <span class="photo-card-badge">
                        <i class="fas fa-images me-1"></i>+<?php echo $extra; ?> Photo<?php echo $extra !== 1 ? 's' : ''; ?>
                    </span>
                    <?php endif; ?>

                    <div class="photo-card-overlay">
                        <i class="fas fa-search-plus photo-card-zoom-icon"></i>
                    </div>
                </div>

                <?php if (!empty($preview['title'])): ?>
                <div class="photo-card-caption">
                    <?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Photo Card Slider Modal -->
<div id="photoCardModal" class="photo-card-modal" role="dialog" aria-modal="true" aria-label="Photo gallery">
    <div class="photo-card-modal-backdrop"></div>
    <div class="photo-card-modal-content">

        <button class="photo-card-modal-close" id="photoCardModalClose" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <div class="photo-card-modal-img-wrap">
            <button class="photo-card-modal-nav photo-card-modal-prev" id="photoCardModalPrev" aria-label="Previous photo">
                <i class="fas fa-chevron-left"></i>
            </button>
            <img id="photoCardModalImg" src="" alt="" class="photo-card-modal-img" draggable="false">
            <button class="photo-card-modal-nav photo-card-modal-next" id="photoCardModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <div class="photo-card-modal-footer">
            <div class="photo-card-modal-caption">
                <span id="photoCardModalTitle"></span>
                <span id="photoCardModalDesc" class="photo-card-modal-desc-text"></span>
            </div>
            <div class="photo-card-modal-counter" id="photoCardModalCounter"></div>
        </div>

        <!-- Thumbnail strip -->
        <div class="photo-card-modal-thumbs" id="photoCardModalThumbs"></div>
    </div>
</div>

<script>
// Photo-card slider modal
(function() {
    var allCards = <?php echo $gallery_cards_json; ?>;

    var modal       = document.getElementById("photoCardModal");
    var modalImg    = document.getElementById("photoCardModalImg");
    var modalTitle  = document.getElementById("photoCardModalTitle");
    var modalDesc   = document.getElementById("photoCardModalDesc");
    var modalCnt    = document.getElementById("photoCardModalCounter");
    var thumbsEl    = document.getElementById("photoCardModalThumbs");

    var cardPhotos  = [];
    var current     = 0;

    function buildThumbs(photos) {
        thumbsEl.innerHTML = "";
        photos.forEach(function(photo, idx) {
            var img = document.createElement("img");
            img.src           = photo.src;
            img.alt           = photo.title || "";
            img.className     = "photo-card-modal-thumb";
            img.dataset.index = idx;
            img.loading       = "lazy";
            img.draggable     = false;
            img.addEventListener("click", function() { showSlide(idx); });
            thumbsEl.appendChild(img);
        });
    }

    function showSlide(idx) {
        if (!cardPhotos.length) return;
        current = ((idx % cardPhotos.length) + cardPhotos.length) % cardPhotos.length;
        var photo = cardPhotos[current];
        modalImg.src              = photo.src;
        modalImg.alt              = photo.title || "";
        modalTitle.textContent    = photo.title || "";
        modalDesc.textContent     = photo.desc  || "";
        modalCnt.textContent      = (current + 1) + " / " + cardPhotos.length;

        var thumbs = Array.from(thumbsEl.querySelectorAll(".photo-card-modal-thumb"));
        thumbs.forEach(function(t) { t.classList.remove("active"); });
        if (thumbs[current]) {
            thumbs[current].classList.add("active");
            thumbs[current].scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
        }
    }

    function openModal(cardIndex, startIndex) {
        cardPhotos = allCards[cardIndex] || [];
        buildThumbs(cardPhotos);
        modal.style.display = "flex";
        document.body.classList.add("modal-open");
        showSlide(startIndex || 0);
    }

    function closeModal() {
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
        modalImg.src = "";
        cardPhotos   = [];
        thumbsEl.innerHTML = "";
    }

    function prevSlide() { showSlide(current - 1); }
    function nextSlide() { showSlide(current + 1); }

    // Open cards on click / keyboard
    document.querySelectorAll(".photo-card").forEach(function(card) {
        function open() {
            var ci = parseInt(card.dataset.cardIndex, 10);
            openModal(ci, 0);
        }
        card.addEventListener("click", open);
        card.addEventListener("keydown", function(e) {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
        });
    });

    document.getElementById("photoCardModalClose").addEventListener("click", closeModal);
    document.getElementById("photoCardModalPrev").addEventListener("click", function(e) { e.stopPropagation(); prevSlide(); });
    document.getElementById("photoCardModalNext").addEventListener("click", function(e) { e.stopPropagation(); nextSlide(); });

    modal.querySelector(".photo-card-modal-backdrop").addEventListener("click", closeModal);

    document.addEventListener("keydown", function(e) {
        if (modal.style.display !== "flex") return;
        if (e.key === "Escape")     closeModal();
        if (e.key === "ArrowLeft")  prevSlide();
        if (e.key === "ArrowRight") nextSlide();
    });

    // Touch swipe
    var swipeX = 0;
    modal.addEventListener("touchstart", function(e) { swipeX = e.touches[0].pageX; }, { passive: true });
    modal.addEventListener("touchend",   function(e) {
        var dx = e.changedTouches[0].pageX - swipeX;
        if (Math.abs(dx) > 50) { dx < 0 ? nextSlide() : prevSlide(); }
    }, { passive: true });
})();
</script>
<?php endif; ?>

<?php
// Get work portfolio photos for the portfolio card + slideshow
$work_photos = getImagesBySection('work_photos');
if (!empty($work_photos)):
    $cover_photo = $work_photos[0];
    $photo_count = count($work_photos);
?>
<!-- Our Work Portfolio Section -->
<section class="work-photos-section py-5">
    <div class="container">
        <h2 class="text-center section-title mb-2">Our Work</h2>
        <p class="text-center text-muted mb-4">A glimpse of the events and moments we have been part of</p>

        <div class="d-flex justify-content-center">
            <!-- Single portfolio card – click to open slideshow -->
            <div class="portfolio-card" id="portfolioCard" role="button" tabindex="0"
                 aria-label="View our work slideshow (<?php echo $photo_count; ?> photos)">
                <div class="portfolio-card-img-wrap">
                    <img src="<?php echo htmlspecialchars($cover_photo['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($cover_photo['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="portfolio-card-img"
                         loading="lazy"
                         draggable="false">
                    <div class="portfolio-card-overlay">
                        <span class="portfolio-card-count">
                            <i class="fas fa-images me-1"></i><?php echo $photo_count; ?> Photos
                        </span>
                        <span class="portfolio-card-cta">
                            <i class="fas fa-play-circle me-1"></i>View Slideshow
                        </span>
                    </div>
                </div>
                <div class="portfolio-card-info">
                    <h6 class="portfolio-card-title">Our Work</h6>
                    <p class="portfolio-card-desc">Click to browse our full photo gallery</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Portfolio Slideshow Modal -->
<div id="portfolioModal" class="portfolio-modal" role="dialog" aria-modal="true" aria-label="Portfolio slideshow">
    <div class="portfolio-modal-backdrop"></div>
    <div class="portfolio-modal-content">
        <button class="portfolio-modal-close" id="portfolioModalClose" aria-label="Close slideshow">
            <i class="fas fa-times"></i>
        </button>

        <div class="portfolio-modal-img-wrap">
            <button class="portfolio-modal-nav portfolio-modal-prev" id="portfolioModalPrev" aria-label="Previous photo">
                <i class="fas fa-chevron-left"></i>
            </button>
            <img id="portfolioModalImg" src="" alt="" class="portfolio-modal-img" draggable="false">
            <button class="portfolio-modal-nav portfolio-modal-next" id="portfolioModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <div class="portfolio-modal-footer">
            <div class="portfolio-modal-caption">
                <span id="portfolioModalTitle"></span>
                <span id="portfolioModalDesc" class="portfolio-modal-desc-text"></span>
            </div>
            <div class="portfolio-modal-counter" id="portfolioModalCounter"></div>
        </div>

        <!-- Thumbnail strip -->
        <div class="portfolio-modal-thumbs" id="portfolioModalThumbs">
            <?php foreach ($work_photos as $idx => $wp): ?>
                <img src="<?php echo htmlspecialchars($wp['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($wp['title'], ENT_QUOTES, 'UTF-8'); ?>"
                     class="portfolio-modal-thumb"
                     data-index="<?php echo $idx; ?>"
                     data-src="<?php echo htmlspecialchars($wp['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-title="<?php echo htmlspecialchars($wp['title'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-desc="<?php echo htmlspecialchars($wp['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     loading="lazy"
                     draggable="false">
            <?php endforeach; ?>
        </div>
    </div>
</div>
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
        <h2 class="text-center section-title mb-2">Our Vendors</h2>
        <p class="text-center text-muted mb-5">Meet the professionals who make your event special</p>

        <div id="vendorsCarousel" class="carousel slide vendors-carousel-animated" data-bs-ride="false" data-bs-touch="false">
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
                                $vendor_type_label  = htmlspecialchars(getVendorTypeLabel($vendor['type']), ENT_QUOTES, 'UTF-8');
                                $vendor_name        = htmlspecialchars($vendor['name'], ENT_QUOTES, 'UTF-8');
                                $vendor_location    = htmlspecialchars($vendor['city_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                $vendor_address     = htmlspecialchars($vendor['address'] ?? '', ENT_QUOTES, 'UTF-8');
                                $vendor_notes       = htmlspecialchars($vendor['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                                $vendor_description = htmlspecialchars($vendor['short_description'] ?? '', ENT_QUOTES, 'UTF-8');

                                // Resolve primary photo from vendor_photos table (falls back to legacy photo column)
                                $vendor_photos_list  = getVendorPhotos($vendor['id']);
                                $primary_photo_path  = !empty($vendor_photos_list) ? $vendor_photos_list[0]['image_path'] : ($vendor['photo'] ?? '');

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
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="vendor-card card h-100 shadow-sm">
                                        <!-- Vendor Photo -->
                                        <?php if (!empty($primary_photo_path)): ?>
                                            <img src="<?php echo htmlspecialchars(rtrim(UPLOAD_URL, '/') . '/' . rawurlencode($primary_photo_path), ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo $vendor_name; ?>"
                                                 class="vendor-photo"
                                                 loading="lazy">
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
                                            <!-- Vendor Short Description -->
                                            <?php if (!empty($vendor_description)): ?>
                                                <p class="card-text text-muted small mb-2"><?php echo $vendor_description; ?></p>
                                            <?php endif; ?>
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

<?php
// Get testimonials
$testimonial_images = getImagesBySection('testimonial');
if (!empty($testimonial_images)):
?>
<!-- Testimonials Section -->
<section class="testimonials-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center section-title mb-2">What Our Clients Say</h2>
        <p class="text-center text-muted mb-5">Memories made, moments cherished</p>

        <div id="testimonialsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
            <div class="carousel-inner">
                <?php
                $testimonial_chunks = array_chunk($testimonial_images, 3);
                foreach ($testimonial_chunks as $tci => $tchunk):
                ?>
                    <div class="carousel-item <?php echo $tci === 0 ? 'active' : ''; ?>">
                        <div class="row g-4 justify-content-center">
                            <?php foreach ($tchunk as $testimonial): ?>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="testimonial-card">
                                        <div class="testimonial-img-wrap">
                                            <img src="<?php echo htmlspecialchars($testimonial['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo htmlspecialchars($testimonial['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 loading="lazy"
                                                 class="testimonial-img">
                                        </div>
                                        <?php if (!empty($testimonial['title']) || !empty($testimonial['description'])): ?>
                                            <div class="testimonial-body">
                                                <?php if (!empty($testimonial['title'])): ?>
                                                    <h6 class="testimonial-name"><?php echo htmlspecialchars($testimonial['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                <?php endif; ?>
                                                <?php if (!empty($testimonial['description'])): ?>
                                                    <p class="testimonial-quote"><i class="fas fa-quote-left text-success me-1"></i><?php echo htmlspecialchars($testimonial['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($testimonial_chunks) > 1): ?>
                <button class="carousel-control-prev testimonials-prev" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next testimonials-next" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
                <div class="carousel-indicators testimonials-indicators">
                    <?php foreach ($testimonial_chunks as $tci => $tchunk): ?>
                        <button type="button" data-bs-target="#testimonialsCarousel" data-bs-slide-to="<?php echo $tci; ?>"
                                <?php echo $tci === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                aria-label="Slide <?php echo $tci + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// Get about section images
$about_images = getImagesBySection('about');
if (!empty($about_images)):
?>
<!-- About Section -->
<section class="about-section py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <?php if (count($about_images) > 1): ?>
                    <div id="aboutCarousel" class="carousel slide about-carousel" data-bs-ride="carousel" data-bs-interval="4000">
                        <div class="carousel-inner">
                            <?php foreach ($about_images as $ai => $aimg): ?>
                                <div class="carousel-item <?php echo $ai === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars($aimg['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="<?php echo htmlspecialchars($aimg['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         class="about-carousel-img"
                                         loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#aboutCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#aboutCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                        <div class="carousel-indicators">
                            <?php foreach ($about_images as $ai => $aimg): ?>
                                <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="<?php echo $ai; ?>"
                                        <?php echo $ai === 0 ? 'class="active" aria-current="true"' : ''; ?>></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($about_images[0]['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($about_images[0]['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="about-single-img"
                         loading="lazy">
                <?php endif; ?>
            </div>
            <div class="col-lg-7">
                <h2 class="section-title mb-3">About Us</h2>
                <?php
                $about_desc = '';
                foreach ($about_images as $aimg) {
                    if (!empty($aimg['description'])) { $about_desc = $aimg['description']; break; }
                }
                ?>
                <?php if (!empty($about_desc)): ?>
                    <p class="about-description"><?php echo nl2br(htmlspecialchars($about_desc, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php else: ?>
                    <p class="about-description">We are dedicated to making your events unforgettable. From intimate gatherings to grand celebrations, our venues and professional team ensure every detail is perfect.</p>
                <?php endif; ?>
                <div class="about-stats row g-3 mt-3">
                    <div class="col-4 text-center">
                        <div class="about-stat-card">
                            <i class="fas fa-building about-stat-icon"></i>
                            <div class="about-stat-number">10+</div>
                            <div class="about-stat-label">Venues</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="about-stat-card">
                            <i class="fas fa-calendar-check about-stat-icon"></i>
                            <div class="about-stat-number">500+</div>
                            <div class="about-stat-label">Events</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="about-stat-card">
                            <i class="fas fa-smile about-stat-icon"></i>
                            <div class="about-stat-number">1000+</div>
                            <div class="about-stat-label">Happy Clients</div>
                        </div>
                    </div>
                </div>
            </div>
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
<script>
// Portfolio single-card + slideshow modal
(function() {
    var card    = document.getElementById("portfolioCard");
    var modal   = document.getElementById("portfolioModal");
    if (!card || !modal) return;

    var modalImg     = document.getElementById("portfolioModalImg");
    var modalTitle   = document.getElementById("portfolioModalTitle");
    var modalDesc    = document.getElementById("portfolioModalDesc");
    var modalCounter = document.getElementById("portfolioModalCounter");
    var thumbsEl     = document.getElementById("portfolioModalThumbs");
    var thumbs       = thumbsEl ? Array.from(thumbsEl.querySelectorAll(".portfolio-modal-thumb")) : [];
    var total        = thumbs.length;
    var current      = 0;
    var autoTimer    = null;
    var AUTO_INTERVAL = 4000; // ms between auto-advance slides

    // ── Open / close ──────────────────────────────────────────
    function openModal(startIndex) {
        current = startIndex || 0;
        modal.classList.add("active");
        document.body.classList.add("modal-open");
        showSlide(current);
        startAuto();
    }

    function closeModal() {
        modal.classList.remove("active");
        document.body.classList.remove("modal-open");
        stopAuto();
        modalImg.src = "";
    }

    // ── Slide navigation ──────────────────────────────────────
    function showSlide(idx) {
        if (!thumbs.length) return;
        current = ((idx % total) + total) % total; // handles both positive and negative idx (wrap-around)
        var thumb = thumbs[current];
        modalImg.src   = thumb.dataset.src;
        modalImg.alt   = thumb.dataset.title || "";
        modalTitle.textContent = thumb.dataset.title || "";
        modalDesc.textContent  = thumb.dataset.desc  || "";
        modalCounter.textContent = (current + 1) + " / " + total;

        // Highlight active thumb and scroll it into view
        thumbs.forEach(function(t) { t.classList.remove("active"); });
        thumb.classList.add("active");
        thumb.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
    }

    function prevSlide() { stopAuto(); showSlide(current - 1); startAuto(); }
    function nextSlide() { stopAuto(); showSlide(current + 1); startAuto(); }

    // ── Auto-advance ──────────────────────────────────────────
    function startAuto() {
        stopAuto();
        if (total > 1) autoTimer = setInterval(function() { showSlide(current + 1); }, AUTO_INTERVAL);
    }
    function stopAuto() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }

    // ── Event listeners ───────────────────────────────────────
    card.addEventListener("click", function() { openModal(0); });
    card.addEventListener("keydown", function(e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openModal(0); }
    });

    document.getElementById("portfolioModalClose").addEventListener("click", closeModal);
    document.getElementById("portfolioModalPrev").addEventListener("click", prevSlide);
    document.getElementById("portfolioModalNext").addEventListener("click", nextSlide);

    // Click on backdrop closes modal
    modal.querySelector(".portfolio-modal-backdrop").addEventListener("click", closeModal);

    // Thumbnail clicks
    thumbs.forEach(function(thumb) {
        thumb.addEventListener("click", function() {
            stopAuto();
            showSlide(parseInt(thumb.dataset.index, 10));
            startAuto();
        });
    });

    // Keyboard navigation
    document.addEventListener("keydown", function(e) {
        if (!modal.classList.contains("active")) return;
        if (e.key === "Escape")     closeModal();
        if (e.key === "ArrowLeft")  prevSlide();
        if (e.key === "ArrowRight") nextSlide();
    });

    // Touch swipe inside modal
    var swipeStartX = 0;
    modal.addEventListener("touchstart", function(e) {
        swipeStartX = e.touches[0].pageX;
    }, { passive: true });
    modal.addEventListener("touchend", function(e) {
        var dx = e.changedTouches[0].pageX - swipeStartX;
        if (Math.abs(dx) > 50) { dx < 0 ? nextSlide() : prevSlide(); }
    }, { passive: true });

    // Pause auto-advance while hovering main image area
    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseenter", stopAuto);
    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseleave", startAuto);
})();

// ── Auto-scroll for package category sliders ──
(function() {
    var speed = 0.5; // pixels per frame
    var dragSensitivity = 1.5;

    document.querySelectorAll("[data-pkg-slider]").forEach(function(track) {
        // Only enable auto-scroll when content overflows the wrapper
        if (track.scrollWidth <= track.clientWidth) return;

        // Duplicate cards for seamless infinite loop
        var origCards = Array.from(track.children);
        origCards.forEach(function(card) {
            track.appendChild(card.cloneNode(true));
        });

        var hovered = false, dragging = false;

        function isPaused() { return hovered || dragging; }

        function step() {
            if (!isPaused()) {
                track.scrollLeft += speed;
                var half = track.scrollWidth / 2;
                // Subtract 1px tolerance to avoid floating-point overshoot
                if (track.scrollLeft >= half - 1) {
                    track.scrollLeft -= half;
                }
            }
            requestAnimationFrame(step);
        }
        requestAnimationFrame(step);

        // Pause on mouse hover
        track.addEventListener("mouseenter", function() { hovered = true; });
        track.addEventListener("mouseleave", function() { hovered = false; });

        // Mouse drag-to-scroll
        var isDown = false, startX = 0, scrollStart = 0;
        track.addEventListener("mousedown", function(e) {
            isDown = true;
            dragging = true;
            track.classList.add("pkg-slider-grabbing");
            startX = e.pageX - track.offsetLeft;
            scrollStart = track.scrollLeft;
            document.addEventListener("mousemove", onMove);
            e.preventDefault();
        });
        function onMove(e) {
            if (!isDown) return;
            track.scrollLeft = scrollStart - (e.pageX - track.offsetLeft - startX) * dragSensitivity;
        }
        function stopDrag() {
            if (!isDown) return;
            isDown = false;
            dragging = false;
            track.classList.remove("pkg-slider-grabbing");
            document.removeEventListener("mousemove", onMove);
        }
        document.addEventListener("mouseup", stopDrag);

        // Touch: pause auto-scroll and drag-to-scroll
        var tStartX = 0, tScrollStart = 0;
        track.addEventListener("touchstart", function(e) {
            hovered = true;
            dragging = true;
            tStartX = e.touches[0].pageX;
            tScrollStart = track.scrollLeft;
        }, { passive: true });
        track.addEventListener("touchmove", function(e) {
            track.scrollLeft = tScrollStart - (e.touches[0].pageX - tStartX);
        }, { passive: true });
        track.addEventListener("touchend", function() { hovered = false; dragging = false; }, { passive: true });
        track.addEventListener("touchcancel", function() { hovered = false; dragging = false; }, { passive: true });
    });
})();
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
