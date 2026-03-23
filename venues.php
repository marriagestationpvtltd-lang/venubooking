<?php
$page_title       = 'Venues';
$page_description = 'Explore our premium venues across Nepal for weddings, receptions, corporate events, and every special occasion. Book online instantly.';
$page_keywords    = 'venues, wedding venues, event venues, hall booking, Nepal, Kathmandu';
require_once __DIR__ . '/includes/header.php';

// Data
$cities  = getAllCities();
$venues  = getAllActiveVenues();
$office_whatsapp       = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

// Encode venues for JS
$venues_js = array_map(function($v) {
    $images = [];
    if (!empty($v['gallery_images'])) {
        $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
        foreach ($v['gallery_images'] as $gi) {
            $images[] = $upload_url_base . rawurlencode($gi['image_path']);
        }
    } elseif (!empty($v['image'])) {
        $images[] = rtrim(UPLOAD_URL, '/') . '/' . rawurlencode($v['image']);
    } else {
        $images[] = getPlaceholderImageUrl();
    }

    $pano_url = '';
    if (!empty($v['pano_image'])) {
        $fn = basename($v['pano_image']);
        if (preg_match(SAFE_FILENAME_PATTERN, $fn) && file_exists(UPLOAD_PATH . $fn)) {
            $pano_url = UPLOAD_URL . rawurlencode($fn);
        }
    }

    return [
        'id'          => (int)$v['id'],
        'name'        => $v['name'],
        'city_name'   => $v['city_name'] ?? ($v['location'] ?? ''),
        'city_id'     => (int)($v['city_id'] ?? 0),
        'description' => sanitize($v['description'] ?? ''),
        'images'      => $images,
        'pano_image_url' => $pano_url,
    ];
}, $venues);
$venues_js_json = json_encode($venues_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Venues</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <h1 class="h3 mb-0 fw-bold"><i class="fas fa-building me-2"></i>हाम्रा प्रिमियम स्थानहरू</h1>
            <div class="section-share-wrap">
                <button class="section-share-btn" type="button" title="Share" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-share-alt" aria-hidden="true"></i>
                    <span>Share</span>
                </button>
                <div class="section-share-dropdown" role="menu" aria-label="Share options">
                    <button class="share-opt share-copy" type="button" role="menuitem">
                        <i class="fas fa-link" aria-hidden="true"></i> Copy link
                    </button>
                    <a class="share-opt share-whatsapp" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i> Share on WhatsApp
                    </a>
                    <a class="share-opt share-facebook" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i> Share on Facebook
                    </a>
                </div>
            </div>
        </div>
        <p class="mb-0 mt-1 text-white-75 small">Our Premium Venues — Explore and book the perfect venue for your event</p>
    </div>
</div>

<?php if (!empty($venues)): ?>
<!-- Venues Section -->
<section class="venues-section py-5" id="section-venues">
    <div class="container">
        <!-- City filter bar -->
        <div class="venues-filter-bar mb-4 d-flex flex-wrap justify-content-center gap-2" id="venueCityFilters">
            <button type="button" class="btn btn-outline-success venue-city-btn active" data-city-id="">
                <i class="fas fa-globe-asia me-1"></i> All Cities
            </button>
            <?php foreach ($cities as $city): ?>
                <button type="button" class="btn btn-outline-success venue-city-btn"
                        data-city-id="<?php echo (int)$city['id']; ?>">
                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($city['name'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Venue grid -->
        <div class="row g-3" id="venuesGrid">
            <?php foreach ($venues as $venue):
                        $images_to_display = [];
                        if (!empty($venue['gallery_images'])) {
                            $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                            foreach ($venue['gallery_images'] as $gallery_image) {
                                $safe_url = $upload_url_base . rawurlencode($gallery_image['image_path']);
                                $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                            }
                        } elseif (!empty($venue['image'])) {
                            $safe_url = rtrim(UPLOAD_URL, '/') . '/' . rawurlencode($venue['image']);
                            $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                        } else {
                            $images_to_display[] = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                        }
                        $carousel_id = 'venueImageCarousel' . $venue['id'];
                        $description = sanitize($venue['description']);
                        $truncated_description = mb_strlen($description) > 100 ? mb_substr($description, 0, 100) . '...' : $description;
                        $home_pano_url = '';
                        if (!empty($venue['pano_image'])) {
                            $home_pano_fn = basename($venue['pano_image']);
                            if (preg_match(SAFE_FILENAME_PATTERN, $home_pano_fn) && file_exists(UPLOAD_PATH . $home_pano_fn)) {
                                $home_pano_url = UPLOAD_URL . rawurlencode($home_pano_fn);
                            }
                        }
                    ?>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="venue-card-home card h-100 shadow-sm">
                                <?php if (count($images_to_display) > 1): ?>
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
                                        <div class="carousel-indicators-counter">
                                            <span class="badge bg-dark bg-opacity-75">
                                                <i class="fas fa-images"></i> <?php echo count($images_to_display); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-img-top venue-image-home" style="background-image: url('<?php echo $images_to_display[0]; ?>');"></div>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo sanitize($venue['name']); ?></h5>
                                    <p class="card-text">
                                        <i class="fas fa-map-marker-alt text-success"></i>
                                        <?php echo sanitize($venue['city_name'] ?? $venue['location']); ?>
                                    </p>
                                    <p class="card-text text-muted flex-grow-1"><?php echo $truncated_description; ?></p>
                                    <?php if (!empty($home_pano_url)): ?>
                                    <button type="button"
                                            class="btn btn-outline-primary w-100 home-pano-btn mb-2"
                                            data-pano-url="<?php echo htmlspecialchars($home_pano_url, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-venue-name="<?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-street-view"></i> View 360°
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php"
                                       class="btn btn-success w-100 venue-book-btn mt-auto"
                                       data-venue-id="<?php echo $venue['id']; ?>"
                                       data-venue-name="<?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-calendar-check"></i> Book Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
        </div>

        <!-- Empty state (hidden by default) -->
        <div id="venuesEmptyState" class="text-center py-5 d-none">
            <i class="fas fa-building fa-3x text-muted mb-3"></i>
            <p class="text-muted">No venues found for the selected city.</p>
        </div>
    </div>
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-building fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No venues available at the moment.</h3>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success mt-3">
        <i class="fas fa-home me-1"></i> Back to Home
    </a>
</div>
<?php endif; ?>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to book a venue. Please help me.'); ?>"
   class="floating-wa-btn"
   target="_blank" rel="noopener noreferrer"
   aria-label="Contact us on WhatsApp"
   title="Chat on WhatsApp">
    <span class="floating-wa-pulse" aria-hidden="true"></span>
    <i class="fab fa-whatsapp wa-fab-icon"></i>
    <span class="wa-fab-text">Chat with Us</span>
</a>
<?php endif; ?>

<button class="scroll-top-fab" id="scrollTopFab" aria-label="Scroll to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<?php
$base_url_js = json_encode(rtrim(BASE_URL, '/'));
$venues_js_output = $venues_js_json;
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
<script>
(function () {
    var BASE_URL_JS = ' . $base_url_js . ';
    var allVenues   = ' . $venues_js_output . ';

    function escapeHtml(str) {
        var d = document.createElement(\'div\');
        d.appendChild(document.createTextNode(str || \'\'));
        return d.innerHTML;
    }

    function buildVenueCard(venue) {
        var carouselId = \'venueImageCarouselDyn\' + venue.id;
        var imgHtml = \'\';
        if (venue.images.length > 1) {
            var items = venue.images.map(function (src, idx) {
                return \'<div class="carousel-item\' + (idx === 0 ? \' active\' : \'\') + \'">\' +
                       \'<div class="venue-image-home" style="background-image:url(\\\'\' + encodeURI(src) + \'\\\')">\</div>\' +
                       \'</div>\';
            }).join(\'\');
            imgHtml = \'<div id="\' + carouselId + \'" class="carousel slide venue-image-carousel" data-bs-ride="carousel">\' +
                      \'<div class="carousel-inner">\' + items + \'</div>\' +
                      \'<button class="carousel-control-prev" type="button" data-bs-target="#\' + carouselId + \'" data-bs-slide="prev">\' +
                      \'<span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>\' +
                      \'<button class="carousel-control-next" type="button" data-bs-target="#\' + carouselId + \'" data-bs-slide="next">\' +
                      \'<span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>\' +
                      \'<div class="carousel-indicators-counter"><span class="badge bg-dark bg-opacity-75"><i class="fas fa-images"></i> \' + venue.images.length + \'</span></div>\' +
                      \'</div>\';
        } else {
            imgHtml = \'<div class="card-img-top venue-image-home" style="background-image:url(\\\'\' + encodeURI(venue.images[0]) + \'\\\')"></div>\';
        }
        var panoBtn = venue.pano_image_url
            ? \'<button type="button" class="btn btn-outline-primary w-100 home-pano-btn mb-2" data-pano-url="\' + escapeHtml(venue.pano_image_url) + \'" data-venue-name="\' + escapeHtml(venue.name) + \'"><i class="fas fa-street-view"></i> View 360°</button>\'
            : \'\';
        return \'<div class="col-12 col-sm-6 col-lg-4">\' +
               \'<div class="venue-card-home card h-100 shadow-sm">\' +
               imgHtml +
               \'<div class="card-body d-flex flex-column">\' +
               \'<h5 class="card-title">\' + escapeHtml(venue.name) + \'</h5>\' +
               \'<p class="card-text"><i class="fas fa-map-marker-alt text-success"></i> \' + escapeHtml(venue.city_name) + \'</p>\' +
               \'<p class="card-text text-muted flex-grow-1">\' + escapeHtml(venue.description) + \'</p>\' +
               panoBtn +
               \'<a href="\' + BASE_URL_JS + \'/index.php" class="btn btn-success w-100 venue-book-btn mt-auto" data-venue-id="\' + venue.id + \'" data-venue-name="\' + escapeHtml(venue.name) + \'"><i class="fas fa-calendar-check"></i> Book Now</a>\' +
               \'</div></div></div>\';
    }

    function handleVenueBookClick() {
        var venueId   = this.getAttribute(\'data-venue-id\');
        var venueName = this.getAttribute(\'data-venue-name\');
        sessionStorage.setItem(\'preferred_venue_id\', venueId);
        sessionStorage.setItem(\'preferred_venue_name\', venueName);
        // href already set to index.php, browser will navigate there
    }

    function attachBookBtnListeners() {
        document.querySelectorAll(\'.venue-book-btn\').forEach(function (btn) {
            btn.removeEventListener(\'click\', handleVenueBookClick);
            btn.addEventListener(\'click\', handleVenueBookClick);
        });
    }

    function handleHomePanoClick() {
        var panoUrl   = this.getAttribute(\'data-pano-url\');
        var venueName = this.getAttribute(\'data-venue-name\') || \'\';
        openHomePanoViewer(panoUrl, venueName);
    }

    function attachPanoBtnListeners() {
        document.querySelectorAll(\'.home-pano-btn\').forEach(function (btn) {
            btn.removeEventListener(\'click\', handleHomePanoClick);
            btn.addEventListener(\'click\', handleHomePanoClick);
        });
    }

    function openHomePanoViewer(panoUrl, venueName) {
        var modal = document.getElementById(\'homePanoViewerModal\');
        if (!modal) {
            var modalHtml =
                \'<div class="modal fade" id="homePanoViewerModal" tabindex="-1" aria-labelledby="homePanoViewerModalLabel" aria-hidden="true">\' +
                  \'<div class="modal-dialog modal-xl modal-dialog-centered">\' +
                    \'<div class="modal-content">\' +
                      \'<div class="modal-header">\' +
                        \'<h5 class="modal-title" id="homePanoViewerModalLabel"><i class="fas fa-street-view text-primary"></i> <span id="homePanoViewerVenueName"></span> — 360° View</h5>\' +
                        \'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>\' +
                      \'</div>\' +
                      \'<div class="modal-body p-0" style="height:75vh">\' +
                        \'<div id="homePanoViewerContainer" style="width:100%;height:100%"></div>\' +
                      \'</div>\' +
                    \'</div>\' +
                  \'</div>\' +
                \'</div>\';
            document.body.insertAdjacentHTML(\'beforeend\', modalHtml);
            modal = document.getElementById(\'homePanoViewerModal\');
        }
        var nameEl = document.getElementById(\'homePanoViewerVenueName\');
        if (nameEl) nameEl.textContent = venueName;
        var bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener(\'shown.bs.modal\', function handler() {
            modal.removeEventListener(\'shown.bs.modal\', handler);
            if (window.pannellum) {
                pannellum.viewer(\'homePanoViewerContainer\', {
                    type: \'equirectangular\', panorama: panoUrl,
                    autoLoad: true, autoRotate: -2, showControls: true
                });
            }
        });
        modal.addEventListener(\'hidden.bs.modal\', function handler() {
            modal.removeEventListener(\'hidden.bs.modal\', handler);
            var container = document.getElementById(\'homePanoViewerContainer\');
            if (container) container.innerHTML = \'\';
        });
    }

    function loadVenues(cityId) {
        var venuesGrid  = document.getElementById(\'venuesGrid\');
        var venuesEmpty = document.getElementById(\'venuesEmptyState\');
        if (!venuesGrid) return;
        var filtered = cityId ? allVenues.filter(function(v) { return v.city_id === parseInt(cityId, 10); }) : allVenues;
        if (filtered.length === 0) {
            venuesGrid.innerHTML = \'\';
            if (venuesEmpty) venuesEmpty.classList.remove(\'d-none\');
        } else {
            if (venuesEmpty) venuesEmpty.classList.add(\'d-none\');
            venuesGrid.innerHTML = filtered.map(buildVenueCard).join(\'\');
            attachBookBtnListeners();
            attachPanoBtnListeners();
            venuesGrid.querySelectorAll(\'.venue-image-carousel\').forEach(function (el) {
                new bootstrap.Carousel(el, { interval: 4000 });
            });
        }
    }

    document.addEventListener(\'DOMContentLoaded\', function () {
        var cityFilterBtns = document.querySelectorAll(\'.venue-city-btn\');
        cityFilterBtns.forEach(function (btn) {
            btn.addEventListener(\'click\', function () {
                cityFilterBtns.forEach(function (b) { b.classList.remove(\'active\'); });
                this.classList.add(\'active\');
                loadVenues(this.getAttribute(\'data-city-id\'));
            });
        });
        attachBookBtnListeners();
        attachPanoBtnListeners();
    });
}());
</script>
<script>
(function() {
    var btn = document.getElementById("scrollTopFab");
    if (!btn) return;
    var ticking = false;
    window.addEventListener("scroll", function() {
        if (!ticking) { requestAnimationFrame(function() { btn.classList.toggle("visible", window.scrollY > 400); ticking = false; }); ticking = true; }
    }, { passive: true });
    btn.addEventListener("click", function() { window.scrollTo({ top: 0, behavior: "smooth" }); });
}());
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
