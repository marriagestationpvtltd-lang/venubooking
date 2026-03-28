<?php
$page_title = 'Book Your Event';
$body_class = 'page-landing';
$extra_css = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css">';
require_once __DIR__ . '/includes/header.php';

// Load privacy policy for gallery popup (first active policy requiring acceptance)
$gallery_privacy_policy = null;
try {
    $all_policies = getPolicyPagesRequiringAcceptance();
    if (!empty($all_policies)) {
        $gallery_privacy_policy = getPolicyPageBySlug($all_policies[0]['slug']);
    }
} catch (\Throwable $e) {
    error_log('Failed to load gallery privacy policy: ' . $e->getMessage());
}

// Load terms & conditions page for display below booking button
$terms_policy = null;
try {
    // Re-fetch policies here to guard against $all_policies being undefined
    $all_policies_for_terms = !empty($all_policies) ? $all_policies : getPolicyPagesRequiringAcceptance();
    $terms_policy = getPolicyPageBySlug('terms-conditions');
    if (!$terms_policy) {
        $terms_policy = getPolicyPageBySlug('terms-and-conditions');
    }
    if (!$terms_policy && !empty($all_policies_for_terms)) {
        // Fallback: use the first available policy
        $terms_policy = getPolicyPageBySlug($all_policies_for_terms[0]['slug'] ?? '');
    }
} catch (\Throwable $e) {
    error_log('Failed to load terms policy: ' . $e->getMessage());
}
$banner_images = getImagesBySection('banner');
$banner_image = !empty($banner_images) ? $banner_images[0] : null;

// Get all active cities for the city filter dropdown
$cities = getAllCities();

// Get service packages grouped by category
$service_categories = getServicePackagesByCategory();

// Get office WhatsApp number early so it is available in the packages section
$office_whatsapp       = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

// Public stats for homepage counters
$public_stats = getPublicStats();
$stat_venues  = (int)($public_stats['venues'] ?? 0);
$stat_events  = (int)($public_stats['events'] ?? 0);
$stat_clients = (int)($public_stats['clients'] ?? 0);
$stat_years   = (int)($public_stats['service_years'] ?? 0);

/**
 * Returns the HTML for a share button widget for a given section ID.
 * The button lets users copy a direct link or share via WhatsApp/Facebook.
 *
 * @param  string $sectionId  The HTML id of the target section (e.g. "section-packages").
 * @param  string $pageUrl    Optional dedicated page URL for this section.
 * @return string             HTML markup for the share button and its dropdown.
 */
function getSectionShareButton(string $sectionId, string $pageUrl = ''): string {
    $escaped    = htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8');
    $viewPageLink = '';
    if (!empty($pageUrl)) {
        $viewPageLink = '
        <a class="share-opt share-view-page" href="' . $escapedUrl . '" role="menuitem">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i> Open Full Page
        </a>';
    }
    return '
<div class="section-share-wrap" data-share-wrap="' . $escaped . '"' . (!empty($escapedUrl) ? ' data-page-url="' . $escapedUrl . '"' : '') . '>
    <button class="section-share-btn" type="button"
            data-section="' . $escaped . '"
            title="Share"
            aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-share-alt" aria-hidden="true"></i>
    </button>
    <div class="section-share-dropdown" role="menu" aria-label="Share options">' . $viewPageLink . '
        <button class="share-opt share-copy" type="button" data-section="' . $escaped . '" role="menuitem">
            <i class="fas fa-link" aria-hidden="true"></i> Copy Link
        </button>
        <a class="share-opt share-whatsapp" data-section="' . $escaped . '" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp" aria-hidden="true"></i> Share via WhatsApp
        </a>
        <a class="share-opt share-facebook" data-section="' . $escaped . '" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-facebook-f" aria-hidden="true"></i> Share on Facebook
        </a>
    </div>
</div>';
}
?>
<!-- Landing page: LocalBusiness structured data for Google rich results -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "@id": "<?php echo htmlspecialchars(rtrim(BASE_URL, '/'), ENT_QUOTES, 'UTF-8'); ?>/#localbusiness",
  "name": <?php echo json_encode($site_name, JSON_UNESCAPED_UNICODE); ?>,
  "description": <?php echo json_encode(!empty($meta_description) ? $meta_description : 'Premium venue booking service for weddings, events, and celebrations.', JSON_UNESCAPED_UNICODE); ?>,
  "url": "<?php echo htmlspecialchars(rtrim(BASE_URL, '/'), ENT_QUOTES, 'UTF-8'); ?>/",
  <?php if (!empty($og_image)): ?>"image": "<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>",<?php endif; ?>
  "priceRange": "$$",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "<?php echo max(1, $stat_clients); ?>"
  }
}
</script>

<!-- Hero Section -->
<section class="hero-section<?php if (!empty($banner_images)): ?> with-banner-image<?php endif; ?>" id="bookingSection">
    <!-- Decorative floating shapes -->
    <div class="hero-deco hero-deco-1" aria-hidden="true"></div>
    <div class="hero-deco hero-deco-2" aria-hidden="true"></div>
    <div class="hero-deco hero-deco-3" aria-hidden="true"></div>
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
                    <div class="hero-eyebrow">
                        <span class="eyebrow-dot" aria-hidden="true"></span>
                        Premium Venue Booking
                    </div>
                    <h1 class="display-4 <?php echo !empty($banner_images) ? 'text-white' : 'hero-heading-dark'; ?> fw-bold mb-3">
                        Book Your Perfect Venue
                    </h1>
                    <p class="lead <?php echo !empty($banner_images) ? 'text-white' : 'hero-lead-dark'; ?> mb-4">
                        Find and book the ideal venue for your wedding, birthday party, corporate event, or any special occasion. Trusted by 1,000+ happy clients.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <div class="hero-trust-badge">
                            <i class="fas fa-shield-alt"></i> Verified Venues
                        </div>
                        <div class="hero-trust-badge">
                            <i class="fas fa-bolt"></i> Instant Confirmation
                        </div>
                        <div class="hero-trust-badge">
                            <i class="fas fa-headset"></i> 24/7 Support
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-lg-2 order-1">
                    <div class="booking-card">
                        <div class="text-center mb-3">
                            <div class="booking-card-icon mx-auto mb-2">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h4 class="booking-card-title mb-0">Start Your Booking</h4>
                            <p class="text-muted small mt-1 mb-0">Fill in your event details to begin</p>
                        </div>
                        <?php if (!empty($_SESSION['booking_error_flash'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo sanitize($_SESSION['booking_error_flash']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['booking_error_flash']); ?>
                        <?php endif; ?>
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

                            <div class="mb-2">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-star"></i> Event Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-sm" id="event_type" name="event_type" required>
                                    <option value="">— Select Event Type —</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Birthday">Birthday</option>
                                    <option value="Anniversary">Anniversary</option>
                                    <option value="Corporate Event">Corporate Event</option>
                                    <option value="Engagement">Engagement</option>
                                    <option value="Reception">Reception</option>
                                    <option value="Conference">Conference</option>
                                    <option value="Cultural Program">Cultural Program</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback">Please select the event type.</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3" style="font-size:1rem;letter-spacing:0.05em;">
                                <i class="fas fa-calendar-check me-2"></i> ONLINE BOOKING
                            </button>
                        </form>
                        <?php if ($terms_policy): ?>
                        <div class="booking-terms-preview mt-3">
                            <div class="booking-terms-header d-flex align-items-center justify-content-between mb-1"
                                 role="button" tabindex="0" id="bookingTermsToggle"
                                 aria-expanded="false" aria-controls="bookingTermsBody">
                                <span class="small fw-semibold text-muted">
                                    <i class="fas fa-file-contract me-1 text-success"></i>
                                    <?php echo htmlspecialchars($terms_policy['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <i class="fas fa-chevron-down text-muted booking-terms-chevron" style="font-size:0.75rem;transition:transform 0.2s;"></i>
                            </div>
                            <div id="bookingTermsBody" class="booking-terms-body" style="display:none;">
                                <div class="booking-terms-scroll small text-muted"
                                     style="max-height:160px;overflow-y:auto;padding:8px 10px;background:#f8f9fa;border-radius:6px;border:1px solid #dee2e6;line-height:1.5;">
                                    <?php
                                    $allowed_tags = '<p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><a><span>';
                                    echo strip_tags($terms_policy['content'] ?? '', $allowed_tags);
                                    ?>
                                </div>
                                <div class="mt-1 text-end">
                                    <a href="<?php echo BASE_URL; ?>/policy.php?slug=<?php echo urlencode($terms_policy['slug']); ?>"
                                       target="_blank" rel="noopener noreferrer"
                                       class="small text-success">
                                        <i class="fas fa-external-link-alt me-1"></i>View Full Terms
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Scroll-down indicator -->
        <a href="#mainContentStart" class="hero-scroll-down" aria-label="Scroll down to explore">
            <div class="scroll-arrow-ring"><i class="fas fa-chevron-down" style="font-size:0.8rem;"></i></div>
            <span>Explore</span>
        </a>
    </div>
</section>

<!-- Anchor for scroll-down -->
<div id="mainContentStart"></div>

<!-- Stats Section -->
<section class="stats-section reveal" aria-label="Key statistics">
    <div class="container">
        <div class="row g-0 justify-content-center">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-icon-wrap"><i class="fas fa-building"></i></div>
                    <span class="stat-number"><span class="counter" data-target="<?php echo $stat_venues; ?>">0</span><span class="stat-suffix">+</span></span>
                    <span class="stat-label">Premium Venues</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
                    <span class="stat-number"><span class="counter" data-target="<?php echo $stat_events; ?>">0</span><span class="stat-suffix">+</span></span>
                    <span class="stat-label">Events Done</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-icon-wrap"><i class="fas fa-smile"></i></div>
                    <span class="stat-number"><span class="counter" data-target="<?php echo $stat_clients; ?>">0</span><span class="stat-suffix">+</span></span>
                    <span class="stat-label">Happy Clients</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-icon-wrap"><i class="fas fa-star"></i></div>
                    <span class="stat-number"><span class="counter" data-target="<?php echo $stat_years; ?>">0</span><span class="stat-suffix">+</span></span>
                    <span class="stat-label">Years of Service</span>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
// Animate stat counters when section enters viewport
(function() {
    var counters = document.querySelectorAll('.counter');
    if (!counters.length) return;

    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        var duration = 1800;
        var startTime = null;
        function step(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3); // ease-out-cubic
            el.textContent = Math.round(ease * target);
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    var section = document.querySelector('.stats-section');
    if (!section || !('IntersectionObserver' in window)) {
        counters.forEach(function(c) { c.textContent = c.getAttribute('data-target'); });
        return;
    }

    var triggered = false;
    var obs = new IntersectionObserver(function(entries) {
        if (!entries.length || triggered || !entries[0].isIntersecting) return;
        triggered = true;
        counters.forEach(animateCounter);
        obs.disconnect();
    }, { threshold: 0.3 });
    obs.observe(section);
}());
</script>

<?php
// Collect all packages from all categories into a single flat list
$all_service_packages = [];
$pkg_categories_present = []; // categories that actually have active packages
if (!empty($service_categories)) {
    foreach ($service_categories as $cat) {
        if (!empty($cat['packages'])) {
            $pkg_categories_present[] = ['id' => $cat['id'], 'name' => $cat['name']];
            foreach ($cat['packages'] as $pkg) {
                $all_service_packages[] = array_merge($pkg, ['category_name' => $cat['name'], 'category_id' => $cat['id']]);
            }
        }
    }
}
// Load linked menu data for each package (used in the "View" detail modal)
try {
    $db_pkg_menus = getDB();
    // Collect all menu IDs per package and batch-fetch menu names in one query
    $pkg_menu_map = [];
    $all_view_menu_ids = [];
    foreach ($all_service_packages as $pkg_ref) {
        $pid = (int)$pkg_ref['id'];
        $pkg_menu_map[$pid] = getPackageMenuIds($pid);
        foreach ($pkg_menu_map[$pid] as $mid) {
            $all_view_menu_ids[] = (int)$mid;
        }
    }
    $view_menu_names = [];
    $unique_view_ids = array_unique($all_view_menu_ids);
    if (!empty($unique_view_ids)) {
        $placeholders = implode(',', array_fill(0, count($unique_view_ids), '?'));
        $ms = $db_pkg_menus->prepare(
            "SELECT id, name FROM menus WHERE id IN ($placeholders) AND status = 'active'"
        );
        $ms->execute(array_values($unique_view_ids));
        foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $view_menu_names[(int)$row['id']] = $row['name'];
        }
    }
    // Assign structured menu data to each package
    foreach ($all_service_packages as &$pkg_ref) {
        $pkg_ref['view_menus'] = [];
        $pid = (int)$pkg_ref['id'];
        foreach ($pkg_menu_map[$pid] ?? [] as $pmid) {
            $pmid = (int)$pmid;
            if (isset($view_menu_names[$pmid])) {
                $pkg_ref['view_menus'][] = [
                    'id'        => $pmid,
                    'name'      => $view_menu_names[$pmid],
                    'structure' => getMenuStructure($pmid),
                ];
            }
        }
    }
    unset($pkg_ref);
} catch (\Throwable $e) {
    error_log('pkg view menus load failed: ' . $e->getMessage());
}
?>
<?php if (!empty($all_service_packages)): ?>
<!-- Service Packages Section -->
<section class="service-packages-section" id="section-packages">
    <div class="container">
        <div class="text-center mb-4 reveal section-heading-wrap">
            <span class="section-eyebrow">Service Packages</span>
            <h2 class="section-title">Our Service Packages</h2>
            <p class="section-subtitle mt-2">Find the perfect package for your celebration</p>
            <?php echo getSectionShareButton('section-packages', BASE_URL . '/packages.php'); ?>
        </div>

        <?php if (count($pkg_categories_present) > 1): ?>
        <!-- Package Category Filter Buttons -->
        <div class="service-category-filter-bar text-center mb-4" id="pkgFilterBar">
            <button class="service-category-filter-btn active" data-filter="all">All</button>
            <?php foreach ($pkg_categories_present as $pcat): ?>
                <button class="service-category-filter-btn"
                        data-filter="<?php echo (int)$pcat['id']; ?>">
                    <?php echo htmlspecialchars($pcat['name'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="service-category-block">
            <div class="pkg-slider-wrapper">
                <button class="pkg-slider-nav pkg-slider-prev" type="button" aria-label="Previous packages">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pkg-slider-track" data-pkg-slider>
                <?php foreach ($all_service_packages as $pkg):
                    $pkg_carousel_id = 'pkgCarousel' . (int)$pkg['id'];
                ?>
                    <div class="pkg-slider-card" data-pkg-category="<?php echo (int)$pkg['category_id']; ?>">
                        <div class="package-card card h-100">
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
                            <div class="card-body d-flex flex-column p-3">
                                <div class="pkg-card-meta-row mb-1">
                                    <?php if (!empty($pkg['category_name'])): ?>
                                        <span class="pkg-category-badge"><?php echo htmlspecialchars($pkg['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <div class="package-price">
                                        <span class="price-label"><?php echo formatCurrency($pkg['price']); ?></span>
                                    </div>
                                </div>
                                <h5 class="package-name mb-2">
                                    <?php echo htmlspecialchars($pkg['name']); ?>
                                </h5>
                                <?php if (!empty($pkg['description'])): ?>
                                <?php
                                    $_desc_plain = strip_tags($pkg['description']);
                                    $_desc_limit = 120;
                                    $_desc_short = mb_strlen($_desc_plain) > $_desc_limit
                                        ? mb_substr($_desc_plain, 0, $_desc_limit) . '…'
                                        : $_desc_plain;
                                ?>
                                <p class="text-muted small mb-2">
                                    <?php echo htmlspecialchars($_desc_short, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (mb_strlen($_desc_plain) > $_desc_limit): ?>
                                    <a href="package-detail.php?id=<?php echo (int)$pkg['id']; ?>" class="text-success small ms-1">Read More</a>
                                    <?php endif; ?>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($pkg['guest_limit']) && $pkg['guest_limit'] > 0 && !empty($pkg['view_menus'])): ?>
                                <div class="mb-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fas fa-utensils me-1"></i>खाना = <?php echo (int)$pkg['guest_limit']; ?> जना
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($pkg['features'])):
                                    $max_visible = 4;
                                    $total_features = count($pkg['features']);
                                    $remaining = $total_features - $max_visible;
                                    $visible_features = array_slice($pkg['features'], 0, $max_visible);
                                    $hidden_features = array_slice($pkg['features'], $max_visible);
                                    $feat_collapse_id = 'pkgFeatures' . (int)$pkg['id'];
                                ?>
                                    <div class="pkg-feat-icons mb-2">
                                        <?php foreach ($visible_features as $feat): ?>
                                            <div class="pkg-feat-icon-item">
                                                <?php if (!empty($feat['service_photo'])): ?>
                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($feat['service_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                     class="pkg-feat-icon-img"
                                                     loading="lazy"
                                                     alt="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php else: ?>
                                                <div class="pkg-feat-icon-fallback">
                                                    <i class="fas fa-check" aria-hidden="true"></i>
                                                </div>
                                                <?php endif; ?>
                                                <span class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($remaining > 0): ?>
                                            <div class="pkg-feat-icon-item pkg-feat-more-item" data-bs-toggle="collapse" data-bs-target="#<?php echo $feat_collapse_id; ?>" role="button" aria-expanded="false" aria-controls="<?php echo $feat_collapse_id; ?>">
                                                <div class="pkg-feat-more-chip">+<?php echo $remaining; ?></div>
                                                <span class="pkg-feat-icon-label">More</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($remaining > 0): ?>
                                    <div class="collapse" id="<?php echo $feat_collapse_id; ?>">
                                        <div class="pkg-feat-icons mb-2">
                                            <?php foreach ($hidden_features as $feat): ?>
                                                <div class="pkg-feat-icon-item">
                                                    <?php if (!empty($feat['service_photo'])): ?>
                                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($feat['service_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         class="pkg-feat-icon-img"
                                                         loading="lazy"
                                                         alt="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php else: ?>
                                                    <div class="pkg-feat-icon-fallback">
                                                        <i class="fas fa-check" aria-hidden="true"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <span class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php
                                // Build WhatsApp message with package details
                                $wa_pkg_name  = strip_tags($pkg['name']);
                                $wa_pkg_price = strip_tags(formatCurrency($pkg['price']));
                                $wa_pkg_msg   = "Hello, I would like to know more about this package:\n\nPackage: {$wa_pkg_name}\nPrice: {$wa_pkg_price}";
                                if (!empty($pkg['features'])) {
                                    $wa_pkg_msg .= "\n\nFeatures:";
                                    foreach ($pkg['features'] as $feat) {
                                        $wa_pkg_msg .= "\n- " . strip_tags($feat['feature_text']);
                                    }
                                }
                                if (!empty($pkg['description'])) {
                                    $wa_pkg_msg .= "\n\nDescription:\n" . strip_tags($pkg['description']);
                                }
                                $wa_pkg_msg .= "\n\nPlease provide me with more details.";
                                $pkg_wa_url = '';
                                if (!empty($clean_office_whatsapp)) {
                                    $pkg_wa_url = 'https://wa.me/' . $clean_office_whatsapp . '?text=' . rawurlencode($wa_pkg_msg);
                                }
                                $view_pkg_data = json_encode([
                                    'id'          => (int)$pkg['id'],
                                    'name'        => $pkg['name'],
                                    'price'       => formatCurrency($pkg['price']),
                                    'category'    => $pkg['category_name'] ?? '',
                                    'description' => $pkg['description'] ?? '',
                                    'guest_limit' => (int)($pkg['guest_limit'] ?? 0),
                                    'photos'      => array_map(fn($p) => UPLOAD_URL . $p, $pkg['photos'] ?? []),
                                    'features'    => array_column($pkg['features'] ?? [], 'feature_text'),
                                    'menus'       => $pkg['view_menus'] ?? [],
                                    'book_url'    => BASE_URL . '/package-booking.php?id=' . (int)$pkg['id'],
                                    'detail_url'  => BASE_URL . '/package-detail.php?id=' . (int)$pkg['id'],
                                    'wa_url'      => $pkg_wa_url,
                                ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG);
                                ?>
                                <div class="mt-auto pt-2">
                                    <button type="button" class="btn btn-outline-primary w-100 mb-2 pkg-view-btn"
                                            data-pkg="<?php echo htmlspecialchars($view_pkg_data, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/package-booking.php?id=<?php echo (int)$pkg['id']; ?>"
                                       class="btn btn-success w-100 mb-2">
                                        <i class="fas fa-calendar-check me-1"></i> Book Now
                                    </a>
                                    <?php if (!empty($pkg_wa_url)): ?>
                                        <a href="<?php echo htmlspecialchars($pkg_wa_url, ENT_QUOTES, 'UTF-8'); ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="btn pkg-wa-btn w-100">
                                            <i class="fab fa-whatsapp me-1"></i> Contact Us
                                        </a>
                                    <?php else: ?>
                                        <button class="btn pkg-wa-btn w-100" disabled>
                                            <i class="fab fa-whatsapp me-1"></i> Contact Us
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <button class="pkg-slider-nav pkg-slider-next" type="button" aria-label="Next packages">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <p class="text-center pkg-swipe-hint mt-2 mb-0">
                <i class="fas fa-hand-pointer me-1"></i> Swipe left or right to explore packages
            </p>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/packages.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
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
<section class="gallery-section py-5 bg-light" id="section-gallery">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Our Gallery</span>
            <h2 class="section-title">Cherished Memories</h2>
            <p class="text-muted mt-2">Moments we are proud to capture</p>
            <?php echo getSectionShareButton('section-gallery', BASE_URL . '/gallery.php'); ?>
        </div>

        <div class="gallery-scroll-outer" id="galleryScrollOuter">
            <div class="gallery-scroll-track" id="galleryScrollTrack">
            <?php foreach ($gallery_cards as $ci => $card):
                $preview      = $card[0];
                $total        = count($card);
                $extra        = $total - 1;
                $card_caption = !empty($preview['card_group_title'])
                    ? $preview['card_group_title']
                    : ($preview['title'] ?? '');
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

                <?php if (!empty($card_caption)): ?>
                <div class="photo-card-caption">
                    <?php echo htmlspecialchars($card_caption, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/gallery.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
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
            <div class="photo-card-modal-img-container" id="photoCardImgContainer">
                <img id="photoCardModalImg" src="" alt="" class="photo-card-modal-img" draggable="false">
            </div>
            <button class="photo-card-modal-nav photo-card-modal-next" id="photoCardModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
            <!-- Zoom controls -->
            <div class="photo-card-modal-zoom-controls">
                <button class="photo-card-zoom-btn" id="photoCardZoomIn" aria-label="Zoom in" title="Zoom In">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="photo-card-zoom-btn" id="photoCardZoomOut" aria-label="Zoom out" title="Zoom Out">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="photo-card-zoom-btn" id="photoCardZoomReset" aria-label="Reset zoom" title="Reset Zoom">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <div class="photo-card-modal-zoom-hint" id="photoCardZoomHint">Double-click or pinch to zoom</div>
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

<!-- Package View Modal -->
<div class="modal fade" id="pkgViewModal" tabindex="-1" aria-labelledby="pkgViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="pkgViewModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="pkgViewModalBody">
                <!-- Populated dynamically by JS -->
            </div>
            <div class="modal-footer flex-wrap gap-2 justify-content-start">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
                <a href="#" class="btn btn-success" id="pkgViewBookBtn">
                    <i class="fas fa-calendar-check me-1"></i> Book Now
                </a>
                <a href="#" class="btn btn-outline-primary" id="pkgViewDetailBtn">
                    <i class="fas fa-info-circle me-1"></i> More Details About This Package
                </a>
                <a href="#" class="btn pkg-wa-btn" id="pkgViewContactBtn" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp me-1"></i> Contact Us
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($gallery_privacy_policy): ?>
<!-- Gallery Privacy Policy Acceptance Modal -->
<div id="galleryPrivacyModal" class="gallery-privacy-modal" role="dialog" aria-modal="true"
     aria-labelledby="galleryPrivacyModalTitle" style="display:none;">
    <div class="gallery-privacy-backdrop"></div>
    <div class="gallery-privacy-content">
        <div class="gallery-privacy-header">
            <i class="fas fa-shield-alt me-2 text-success"></i>
            <span id="galleryPrivacyModalTitle"><?php echo htmlspecialchars($gallery_privacy_policy['title'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="gallery-privacy-body" id="galleryPrivacyBody">
            <?php
            $allowed_tags = '<p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><a><span>';
            echo strip_tags($gallery_privacy_policy['content'] ?? '', $allowed_tags);
            ?>
        </div>
        <div class="gallery-privacy-scroll-hint" id="galleryPrivacyScrollHint">
            <i class="fas fa-arrow-down me-1"></i> Scroll down to read and accept
        </div>
        <div class="gallery-privacy-footer">
            <a href="<?php echo BASE_URL; ?>/policy.php?slug=<?php echo urlencode($gallery_privacy_policy['slug']); ?>"
               target="_blank" rel="noopener noreferrer" class="gallery-privacy-link">
                <i class="fas fa-external-link-alt me-1"></i>View Full Policy
            </a>
            <button id="galleryPrivacyDecline" class="gallery-privacy-btn gallery-privacy-btn-decline" type="button">
                Decline
            </button>
            <button id="galleryPrivacyAccept" class="gallery-privacy-btn gallery-privacy-btn-accept" type="button" disabled>
                <i class="fas fa-check me-1"></i>Accept &amp; View Photos
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Photo-card slider modal with zoom functionality
(function() {
    var allCards = <?php echo $gallery_cards_json; ?>;

    var modal       = document.getElementById("photoCardModal");
    var modalImg    = document.getElementById("photoCardModalImg");
    var imgContainer = document.getElementById("photoCardImgContainer");
    var modalTitle  = document.getElementById("photoCardModalTitle");
    var modalDesc   = document.getElementById("photoCardModalDesc");
    var modalCnt    = document.getElementById("photoCardModalCounter");
    var thumbsEl    = document.getElementById("photoCardModalThumbs");
    var zoomHint    = document.getElementById("photoCardZoomHint");

    var cardPhotos  = [];
    var current     = 0;

    // Zoom state
    var zoomLevel = 1;
    var minZoom = 1;
    var maxZoom = 4;
    var zoomStep = 0.5;
    var panX = 0, panY = 0;
    var isDragging = false;
    var startX = 0, startY = 0;
    var pinchStartDist = 0;
    var pinchStartZoom = 1;

    function resetZoom() {
        zoomLevel = 1;
        panX = 0;
        panY = 0;
        applyTransform();
        imgContainer.classList.remove("zoomed");
    }

    function applyTransform() {
        modalImg.style.transform = "scale(" + zoomLevel + ") translate(" + panX + "px, " + panY + "px)";
    }

    function zoomIn() {
        zoomLevel = Math.min(maxZoom, zoomLevel + zoomStep);
        if (zoomLevel > 1) imgContainer.classList.add("zoomed");
        applyTransform();
        hideZoomHint();
    }

    function zoomOut() {
        zoomLevel = Math.max(minZoom, zoomLevel - zoomStep);
        if (zoomLevel <= 1) {
            resetZoom();
        } else {
            applyTransform();
        }
    }

    function zoomToPoint(clientX, clientY, newZoom) {
        var rect = imgContainer.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var offsetX = (clientX - centerX) / zoomLevel;
        var offsetY = (clientY - centerY) / zoomLevel;

        var oldZoom = zoomLevel;
        zoomLevel = Math.max(minZoom, Math.min(maxZoom, newZoom));

        if (zoomLevel > 1) {
            var scaleDiff = zoomLevel / oldZoom;
            panX = panX - offsetX * (scaleDiff - 1) / zoomLevel;
            panY = panY - offsetY * (scaleDiff - 1) / zoomLevel;
            imgContainer.classList.add("zoomed");
        } else {
            resetZoom();
            return;
        }
        applyTransform();
        hideZoomHint();
    }

    function hideZoomHint() {
        if (zoomHint) zoomHint.style.display = "none";
    }

    function showZoomHint() {
        if (zoomHint) zoomHint.style.display = "block";
    }

    // Double click/tap to zoom using native dblclick event
    imgContainer.addEventListener("dblclick", function(e) {
        e.preventDefault();
        if (zoomLevel > 1) {
            resetZoom();
        } else {
            zoomToPoint(e.clientX, e.clientY, 2.5);
        }
    });

    // Mouse wheel zoom
    imgContainer.addEventListener("wheel", function(e) {
        e.preventDefault();
        var delta = e.deltaY > 0 ? -zoomStep : zoomStep;
        zoomToPoint(e.clientX, e.clientY, zoomLevel + delta);
    }, { passive: false });

    // Mouse drag for panning
    imgContainer.addEventListener("mousedown", function(e) {
        if (zoomLevel <= 1) return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        modalImg.classList.add("zooming");
        e.preventDefault();
    });

    document.addEventListener("mousemove", function(e) {
        if (!isDragging || modal.style.display !== "flex") return;
        var dx = (e.clientX - startX) / zoomLevel;
        var dy = (e.clientY - startY) / zoomLevel;
        panX += dx;
        panY += dy;
        startX = e.clientX;
        startY = e.clientY;
        applyTransform();
    });

    document.addEventListener("mouseup", function() {
        if (modal.style.display !== "flex") return;
        isDragging = false;
        modalImg.classList.remove("zooming");
    });

    // Touch pinch zoom
    function getPinchDist(touches) {
        var dx = touches[0].clientX - touches[1].clientX;
        var dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    imgContainer.addEventListener("touchstart", function(e) {
        if (e.touches.length === 2) {
            pinchStartDist = getPinchDist(e.touches);
            pinchStartZoom = zoomLevel;
        } else if (e.touches.length === 1 && zoomLevel > 1) {
            isDragging = true;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            modalImg.classList.add("zooming");
        }
    }, { passive: true });

    imgContainer.addEventListener("touchmove", function(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            var dist = getPinchDist(e.touches);
            var scale = dist / pinchStartDist;
            var newZoom = pinchStartZoom * scale;
            var cx = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            var cy = (e.touches[0].clientY + e.touches[1].clientY) / 2;
            zoomToPoint(cx, cy, newZoom);
        } else if (e.touches.length === 1 && isDragging && zoomLevel > 1) {
            var dx = (e.touches[0].clientX - startX) / zoomLevel;
            var dy = (e.touches[0].clientY - startY) / zoomLevel;
            panX += dx;
            panY += dy;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            applyTransform();
        }
    }, { passive: false });

    imgContainer.addEventListener("touchend", function() {
        isDragging = false;
        modalImg.classList.remove("zooming");
    }, { passive: true });

    // Zoom control buttons
    document.getElementById("photoCardZoomIn").addEventListener("click", function(e) {
        e.stopPropagation();
        zoomIn();
    });

    document.getElementById("photoCardZoomOut").addEventListener("click", function(e) {
        e.stopPropagation();
        zoomOut();
    });

    document.getElementById("photoCardZoomReset").addEventListener("click", function(e) {
        e.stopPropagation();
        resetZoom();
    });

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
            img.addEventListener("click", function() { resetZoom(); showSlide(idx); });
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
        resetZoom();
        showZoomHint();

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
    // Expose globally so the privacy modal handler (separate IIFE) can call it
    window._galleryOpenModal = openModal;

    function closeModal() {
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
        modalImg.src = "";
        cardPhotos   = [];
        thumbsEl.innerHTML = "";
        resetZoom();
    }

    function prevSlide() { resetZoom(); showSlide(current - 1); }
    function nextSlide() { resetZoom(); showSlide(current + 1); }

    // Open cards on click / keyboard
    // Show privacy policy acceptance modal first (if not already accepted this session)
    var GALLERY_PRIVACY_KEY = 'galleryPrivacyAccepted';
    var privacyModal    = document.getElementById("galleryPrivacyModal");
    var privacyAccepted = (sessionStorage.getItem(GALLERY_PRIVACY_KEY) === '1');
    window._galleryPendingCardIndex = -1;
    window._GALLERY_PRIVACY_KEY     = GALLERY_PRIVACY_KEY;

    function openWithPrivacyCheck(ci) {
        if (!privacyModal || privacyAccepted) {
            openModal(ci, 0);
            return;
        }
        window._galleryPendingCardIndex = ci;
        if (typeof window.showPrivacyModal === 'function') window.showPrivacyModal();
    }

    document.querySelectorAll(".photo-card").forEach(function(card) {
        function open() {
            var ci = parseInt(card.dataset.cardIndex, 10);
            openWithPrivacyCheck(ci);
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
        if (e.key === "+" || e.key === "=") zoomIn();
        if (e.key === "-") zoomOut();
        if (e.key === "0") resetZoom();
    });

    // Touch swipe (only when not zoomed)
    var swipeX = 0;
    modal.addEventListener("touchstart", function(e) {
        if (zoomLevel <= 1) swipeX = e.touches[0].pageX;
    }, { passive: true });
    modal.addEventListener("touchend", function(e) {
        if (zoomLevel > 1) return;
        var dx = e.changedTouches[0].pageX - swipeX;
        if (Math.abs(dx) > 50) { dx < 0 ? nextSlide() : prevSlide(); }
    }, { passive: true });
})();

// Gallery Privacy Policy Modal
(function() {
    var privacyModal  = document.getElementById("galleryPrivacyModal");
    if (!privacyModal) return;

    var body          = document.getElementById("galleryPrivacyBody");
    var acceptBtn     = document.getElementById("galleryPrivacyAccept");
    var declineBtn    = document.getElementById("galleryPrivacyDecline");
    var scrollHint    = document.getElementById("galleryPrivacyScrollHint");

    window.showPrivacyModal = function() {
        privacyModal.style.display = "flex";
        document.body.classList.add("modal-open");
        // Reset scroll and button state each time
        body.scrollTop = 0;
        acceptBtn.disabled = true;
        if (scrollHint) scrollHint.style.display = "block";
    };

    function hidePrivacyModal() {
        privacyModal.style.display = "none";
        document.body.classList.remove("modal-open");
    }

    // Enable Accept button only after scrolling to bottom
    body.addEventListener("scroll", function() {
        var atBottom = body.scrollHeight - body.scrollTop <= body.clientHeight + 10;
        if (atBottom) {
            acceptBtn.disabled = false;
            if (scrollHint) scrollHint.style.display = "none";
        }
    });

    acceptBtn.addEventListener("click", function() {
        var key = window._GALLERY_PRIVACY_KEY || 'galleryPrivacyAccepted';
        sessionStorage.setItem(key, '1');
        hidePrivacyModal();
        var ci = window._galleryPendingCardIndex;
        if (ci !== undefined && ci >= 0 && typeof window._galleryOpenModal === 'function') {
            window._galleryOpenModal(ci, 0);
            window._galleryPendingCardIndex = -1;
        }
    });

    declineBtn.addEventListener("click", function() {
        hidePrivacyModal();
        window._galleryPendingCardIndex = -1;
    });

    privacyModal.querySelector(".gallery-privacy-backdrop").addEventListener("click", function() {
        hidePrivacyModal();
        window._galleryPendingCardIndex = -1;
    });

    document.addEventListener("keydown", function(e) {
        if (privacyModal.style.display === "flex" && e.key === "Escape") {
            hidePrivacyModal();
            window._galleryPendingCardIndex = -1;
        }
    });
})();

// Booking terms toggle
(function() {
    var toggle = document.getElementById("bookingTermsToggle");
    if (!toggle) return;
    var body    = document.getElementById("bookingTermsBody");
    var chevron = toggle.querySelector(".booking-terms-chevron");

    function toggleTerms() {
        var expanded = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
        body.style.display = expanded ? "none" : "block";
        if (chevron) chevron.style.transform = expanded ? "" : "rotate(180deg)";
    }
    toggle.addEventListener("click", toggleTerms);
    toggle.addEventListener("keydown", function(e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); toggleTerms(); }
    });
})();

// Gallery auto-scroll carousel
(function() {
    var outer = document.getElementById('galleryScrollOuter');
    var track = document.getElementById('galleryScrollTrack');
    if (!outer || !track) return;

    // Clone cards for seamless infinite loop; mark clones so we know which is which
    var origCards = Array.from(track.children);
    origCards.forEach(function(card) {
        var clone = card.cloneNode(true);
        clone.setAttribute('data-clone', 'true');
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
        track.appendChild(clone);
    });

    var speed   = 0.6;   // pixels per animation frame
    var offset  = 0;
    var paused  = false;
    var raf;

    function getHalfWidth() {
        // Width of the original set of cards (before cloning)
        var gapStyle = getComputedStyle(track).gap || getComputedStyle(track).columnGap || '0px';
        var totalGap = parseFloat(gapStyle) || 0;
        var w = 0;
        origCards.forEach(function(c) { w += c.offsetWidth + totalGap; });
        return w;
    }

    function step() {
        if (!paused) {
            offset += speed;
            var half = getHalfWidth();
            if (half > 0 && offset >= half) {
                offset -= half;
            }
            track.style.transform = 'translateX(-' + offset + 'px)';
        }
        raf = requestAnimationFrame(step);
    }

    outer.addEventListener('mouseenter', function() { paused = true; });
    outer.addEventListener('mouseleave', function() { paused = false; });
    outer.addEventListener('touchstart', function() { paused = true; }, { passive: true });
    outer.addEventListener('touchend',   function() { paused = false; }, { passive: true });

    // Forward clicks on cloned cards to the original card (which has the modal handler)
    track.addEventListener('click', function(e) {
        var card = e.target.closest('.photo-card[data-clone="true"]');
        if (!card) return;
        var idx = card.getAttribute('data-card-index');
        var original = track.querySelector('.photo-card[data-card-index="' + idx + '"]:not([data-clone])');
        if (original) original.click();
    });

    raf = requestAnimationFrame(step);
})();
</script>
<?php endif; ?>


<!-- Features Section -->
<section class="features-section py-5" id="section-features">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Why Choose Us</span>
            <h2 class="section-title">Why Choose Us?</h2>
            <p class="text-muted mt-2" style="max-width:520px;margin:0 auto;">Our premium services make every celebration truly unforgettable.</p>
            <?php echo getSectionShareButton('section-features', BASE_URL . '/about.php'); ?>
        </div>
        <div class="row g-4 reveal-stagger">
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-green">
                    <div class="pro-feature-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h5>Multiple Venues</h5>
                    <p>Choose from our premium venues across the city for every occasion</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-orange">
                    <div class="pro-feature-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h5>Delicious Menus</h5>
                    <p>Wide variety of menu options to suit every taste and budget</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-purple">
                    <div class="pro-feature-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h5>Transparent Pricing</h5>
                    <p>No hidden charges — clear and upfront pricing for all services</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-teal">
                    <div class="pro-feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h5>24/7 Support</h5>
                    <p>Our dedicated team is always here to help you at every step</p>
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
<section class="venues-section py-5" id="section-venues">
    <div class="container">
        <div class="text-center mb-4 reveal section-heading-wrap">
            <span class="section-eyebrow">Our Venues</span>
            <h2 class="section-title">Our Premium Venues</h2>
            <p class="text-muted mt-2">Explore our premium venues and start your booking today</p>
            <?php echo getSectionShareButton('section-venues', BASE_URL . '/venues.php'); ?>
        </div>

        <!-- City filter bar — auto-updates from booking form selection -->
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

        <!-- Venue slideshow — one row, scrolls horizontally when there are many venues -->
        <div class="venues-slider-wrapper">
            <button class="venues-slider-btn venues-slider-prev" id="venuesSliderPrev" aria-label="Previous venues" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="venues-slider-viewport" id="venuesSliderViewport">
                <div class="venues-slider-track" id="venuesGrid">
                    <?php foreach ($venues as $venue): 
                        $images_to_display = [];
                        if (!empty($venue['gallery_images']) && count($venue['gallery_images']) > 0) {
                            $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                            foreach ($venue['gallery_images'] as $gallery_image) {
                                $safe_url = $upload_url_base . rawurlencode($gallery_image['image_path']);
                                $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                            }
                        } elseif (!empty($venue['image'])) {
                            $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
                            $safe_url = $upload_url_base . rawurlencode($venue['image']);
                            $images_to_display[] = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                        } else {
                            $images_to_display[] = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                        }
                        $carousel_id = 'venueImageCarousel' . $venue['id'];
                        $description = sanitize($venue['description']);
                        $truncated_description = mb_strlen($description) > 100 ? mb_substr($description, 0, 100) . '...' : $description;
                        // Build 360° panoramic URL if the venue has one
                        $home_pano_url = '';
                        if (!empty($venue['pano_image'])) {
                            $home_pano_fn = basename($venue['pano_image']);
                            if (preg_match(SAFE_FILENAME_PATTERN, $home_pano_fn) && file_exists(UPLOAD_PATH . $home_pano_fn)) {
                                $home_pano_url = UPLOAD_URL . rawurlencode($home_pano_fn);
                            }
                        }
                    ?>
                        <div class="venue-slide">
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
                                    <p class="card-text text-muted flex-grow-1">
                                        <?php echo $truncated_description; ?>
                                    </p>
                                    <?php if (!empty($home_pano_url)): ?>
                                    <button type="button"
                                            class="btn btn-outline-primary w-100 home-pano-btn mb-2"
                                            data-pano-url="<?php echo htmlspecialchars($home_pano_url, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-venue-name="<?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-street-view"></i> View 360°
                                    </button>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="btn btn-success w-100 venue-book-btn mt-auto"
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
            <button class="venues-slider-btn venues-slider-next" id="venuesSliderNext" aria-label="Next venues">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Empty state (hidden by default) -->
        <div id="venuesEmptyState" class="text-center py-5 d-none">
            <i class="fas fa-building fa-3x text-muted mb-3"></i>
            <p class="text-muted">No venues found for the selected city.</p>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/venues.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
        </div>
    </div>
</section>
<?php endif; ?>


<?php
// Get work photos organised by event category (folder-style gallery)
$work_categories = getWorkPhotosByCategory();
if (!empty($work_categories)):
    // Build a JS-safe data structure: array of {name, photos:[{src,title,desc}]}
    $work_categories_js = [];
    foreach ($work_categories as $cat_name => $cat_photos) {
        $work_categories_js[] = [
            'name'   => $cat_name,
            'photos' => array_map(function($img) {
                return [
                    'src'   => $img['image_url'],
                    'title' => $img['title'],
                    'desc'  => $img['description'] ?? '',
                ];
            }, $cat_photos),
        ];
    }
    $work_categories_json = json_encode($work_categories_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!-- Our Work – Folder Gallery Section -->
<section class="work-photos-section py-5" id="section-work">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Portfolio</span>
            <h2 class="section-title">Our Portfolio</h2>
            <p class="text-muted mt-2">Browse our events by category</p>
            <?php echo getSectionShareButton('section-work', BASE_URL . '/portfolio.php'); ?>
        </div>

        <!-- Marquee wrapper: cards scroll continuously; hovering pauses the animation -->
        <div class="work-folder-marquee">
            <div class="work-folder-track" id="workFolderTrack">
                <?php
                // Render cards twice: first set is interactive, second set is an
                // aria-hidden visual duplicate needed for the seamless loop.
                $cat_keys = array_keys($work_categories);
                for ($wf_pass = 0; $wf_pass < 2; $wf_pass++):
                    foreach ($work_categories as $cat_name => $cat_photos):
                        $preview   = $cat_photos[0];
                        $cat_count = count($cat_photos);
                        $cat_index = array_search($cat_name, $cat_keys);
                        $is_dup    = ($wf_pass === 1);
                ?>
                <div class="work-folder-card"
                     data-cat-index="<?php echo $cat_index; ?>"
                     <?php if ($is_dup): ?>
                     aria-hidden="true" tabindex="-1"
                     <?php else: ?>
                     role="button" tabindex="0"
                     aria-label="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $cat_count; ?> photo<?php echo $cat_count !== 1 ? 's' : ''; ?>)"
                     <?php endif; ?>>

                    <div class="work-folder-img-wrap">
                        <img src="<?php echo htmlspecialchars($preview['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="work-folder-img"
                             loading="lazy"
                             draggable="false">
                        <div class="work-folder-overlay">
                            <i class="fas fa-folder-open work-folder-icon"></i>
                        </div>
                    </div>

                    <div class="work-folder-info">
                        <div class="work-folder-title">
                            <i class="fas fa-folder me-2 text-warning"></i><?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="work-folder-count">
                            <i class="fas fa-images me-1"></i><?php echo $cat_count; ?> Photo<?php echo $cat_count !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </div>
                <?php
                    endforeach;
                endfor;
                ?>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/portfolio.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
        </div>
    </div>
</section>

<!-- Portfolio Slideshow Modal (reused for each folder) -->
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
            <div class="portfolio-modal-img-container" id="portfolioImgContainer">
                <img id="portfolioModalImg" src="" alt="" class="portfolio-modal-img" draggable="false">
            </div>
            <button class="portfolio-modal-nav portfolio-modal-next" id="portfolioModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
            <!-- Zoom controls -->
            <div class="portfolio-modal-zoom-controls">
                <button class="portfolio-zoom-btn" id="portfolioZoomIn" aria-label="Zoom in" title="Zoom In">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="portfolio-zoom-btn" id="portfolioZoomOut" aria-label="Zoom out" title="Zoom Out">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="portfolio-zoom-btn" id="portfolioZoomReset" aria-label="Reset zoom" title="Reset Zoom">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <div class="portfolio-modal-zoom-hint" id="portfolioZoomHint">Double-click or pinch to zoom</div>
        </div>

        <div class="portfolio-modal-footer">
            <div class="portfolio-modal-caption">
                <span id="portfolioModalTitle"></span>
                <span id="portfolioModalDesc" class="portfolio-modal-desc-text"></span>
            </div>
            <div class="portfolio-modal-counter" id="portfolioModalCounter"></div>
        </div>

        <!-- Thumbnail strip -->
        <div class="portfolio-modal-thumbs" id="portfolioModalThumbs"></div>
    </div>
</div>

<script>
// Folder-style Our Work gallery with zoom functionality
(function() {
    var allCategories = <?php echo $work_categories_json; ?>;

    var modal        = document.getElementById("portfolioModal");
    var modalImg     = document.getElementById("portfolioModalImg");
    var imgContainer = document.getElementById("portfolioImgContainer");
    var modalTitle   = document.getElementById("portfolioModalTitle");
    var modalDesc    = document.getElementById("portfolioModalDesc");
    var modalCounter = document.getElementById("portfolioModalCounter");
    var thumbsEl     = document.getElementById("portfolioModalThumbs");
    var zoomHint     = document.getElementById("portfolioZoomHint");

    var currentPhotos = [];
    var current       = 0;
    var autoTimer     = null;
    var AUTO_INTERVAL = 4000;

    // Zoom state
    var zoomLevel = 1;
    var minZoom = 1;
    var maxZoom = 4;
    var zoomStep = 0.5;
    var panX = 0, panY = 0;
    var isDragging = false;
    var startX = 0, startY = 0;
    var pinchStartDist = 0;
    var pinchStartZoom = 1;

    function resetZoom() {
        zoomLevel = 1;
        panX = 0;
        panY = 0;
        applyTransform();
        imgContainer.classList.remove("zoomed");
    }

    function applyTransform() {
        modalImg.style.transform = "scale(" + zoomLevel + ") translate(" + panX + "px, " + panY + "px)";
    }

    function zoomIn() {
        stopAuto();
        zoomLevel = Math.min(maxZoom, zoomLevel + zoomStep);
        if (zoomLevel > 1) imgContainer.classList.add("zoomed");
        applyTransform();
        hideZoomHint();
    }

    function zoomOut() {
        zoomLevel = Math.max(minZoom, zoomLevel - zoomStep);
        if (zoomLevel <= 1) {
            resetZoom();
            startAuto();
        } else {
            applyTransform();
        }
    }

    function zoomToPoint(clientX, clientY, newZoom) {
        var rect = imgContainer.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var offsetX = (clientX - centerX) / zoomLevel;
        var offsetY = (clientY - centerY) / zoomLevel;

        var oldZoom = zoomLevel;
        zoomLevel = Math.max(minZoom, Math.min(maxZoom, newZoom));

        if (zoomLevel > 1) {
            stopAuto();
            var scaleDiff = zoomLevel / oldZoom;
            panX = panX - offsetX * (scaleDiff - 1) / zoomLevel;
            panY = panY - offsetY * (scaleDiff - 1) / zoomLevel;
            imgContainer.classList.add("zoomed");
        } else {
            resetZoom();
            startAuto();
            return;
        }
        applyTransform();
        hideZoomHint();
    }

    function hideZoomHint() {
        if (zoomHint) zoomHint.style.display = "none";
    }

    function showZoomHint() {
        if (zoomHint) zoomHint.style.display = "block";
    }

    // Double click/tap to zoom using native dblclick event
    imgContainer.addEventListener("dblclick", function(e) {
        e.preventDefault();
        if (zoomLevel > 1) {
            resetZoom();
            startAuto();
        } else {
            stopAuto();
            zoomToPoint(e.clientX, e.clientY, 2.5);
        }
    });

    // Mouse wheel zoom
    imgContainer.addEventListener("wheel", function(e) {
        e.preventDefault();
        var delta = e.deltaY > 0 ? -zoomStep : zoomStep;
        zoomToPoint(e.clientX, e.clientY, zoomLevel + delta);
    }, { passive: false });

    // Mouse drag for panning
    imgContainer.addEventListener("mousedown", function(e) {
        if (zoomLevel <= 1) return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        modalImg.classList.add("zooming");
        e.preventDefault();
    });

    document.addEventListener("mousemove", function(e) {
        if (!isDragging || !modal.classList.contains("active")) return;
        var dx = (e.clientX - startX) / zoomLevel;
        var dy = (e.clientY - startY) / zoomLevel;
        panX += dx;
        panY += dy;
        startX = e.clientX;
        startY = e.clientY;
        applyTransform();
    });

    document.addEventListener("mouseup", function() {
        if (modal.classList.contains("active")) {
            isDragging = false;
            modalImg.classList.remove("zooming");
        }
    });

    // Touch pinch zoom
    function getPinchDist(touches) {
        var dx = touches[0].clientX - touches[1].clientX;
        var dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    imgContainer.addEventListener("touchstart", function(e) {
        if (e.touches.length === 2) {
            pinchStartDist = getPinchDist(e.touches);
            pinchStartZoom = zoomLevel;
        } else if (e.touches.length === 1 && zoomLevel > 1) {
            isDragging = true;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            modalImg.classList.add("zooming");
        }
    }, { passive: true });

    imgContainer.addEventListener("touchmove", function(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            var dist = getPinchDist(e.touches);
            var scale = dist / pinchStartDist;
            var newZoom = pinchStartZoom * scale;
            var cx = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            var cy = (e.touches[0].clientY + e.touches[1].clientY) / 2;
            zoomToPoint(cx, cy, newZoom);
        } else if (e.touches.length === 1 && isDragging && zoomLevel > 1) {
            var dx = (e.touches[0].clientX - startX) / zoomLevel;
            var dy = (e.touches[0].clientY - startY) / zoomLevel;
            panX += dx;
            panY += dy;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            applyTransform();
        }
    }, { passive: false });

    imgContainer.addEventListener("touchend", function() {
        isDragging = false;
        modalImg.classList.remove("zooming");
    }, { passive: true });

    // Zoom control buttons
    document.getElementById("portfolioZoomIn").addEventListener("click", function(e) {
        e.stopPropagation();
        zoomIn();
    });

    document.getElementById("portfolioZoomOut").addEventListener("click", function(e) {
        e.stopPropagation();
        zoomOut();
    });

    document.getElementById("portfolioZoomReset").addEventListener("click", function(e) {
        e.stopPropagation();
        resetZoom();
        startAuto();
    });

    function buildThumbs(photos) {
        thumbsEl.innerHTML = "";
        photos.forEach(function(photo, idx) {
            var img = document.createElement("img");
            img.src            = photo.src;
            img.alt            = photo.title || "";
            img.className      = "portfolio-modal-thumb";
            img.dataset.index  = idx;
            img.loading        = "lazy";
            img.draggable      = false;
            img.addEventListener("click", function() {
                stopAuto();
                resetZoom();
                showSlide(idx);
                startAuto();
            });
            thumbsEl.appendChild(img);
        });
    }

    function showSlide(idx) {
        if (!currentPhotos.length) return;
        current = ((idx % currentPhotos.length) + currentPhotos.length) % currentPhotos.length;
        var photo = currentPhotos[current];
        modalImg.src                  = photo.src;
        modalImg.alt                  = photo.title || "";
        modalTitle.textContent        = photo.title || "";
        modalDesc.textContent         = photo.desc  || "";
        modalCounter.textContent      = (current + 1) + " / " + currentPhotos.length;
        resetZoom();
        showZoomHint();

        var thumbs = Array.from(thumbsEl.querySelectorAll(".portfolio-modal-thumb"));
        thumbs.forEach(function(t) { t.classList.remove("active"); });
        if (thumbs[current]) {
            thumbs[current].classList.add("active");
            thumbs[current].scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
        }
    }

    function openFolder(catIndex, startIndex) {
        var cat = allCategories[catIndex];
        if (!cat) return;
        // Cancel any in-progress close animation before reopening
        modal.classList.remove("closing");
        currentPhotos = cat.photos;
        buildThumbs(currentPhotos);
        current = startIndex || 0;
        modal.classList.add("active");
        document.body.classList.add("modal-open");
        showSlide(current);
        startAuto();
    }

    var MODAL_CLOSE_DURATION = 260; // must stay in sync with CSS .portfolio-modal.closing animation (0.25s + buffer)
    var closeTimer = null;
    function closeModal() {
        clearTimeout(closeTimer);
        stopAuto();
        resetZoom();
        document.body.classList.remove("modal-open");
        modal.classList.add("closing");
        closeTimer = setTimeout(function() {
            modal.classList.remove("active");
            modal.classList.remove("closing");
            modalImg.src       = "";
            currentPhotos      = [];
            thumbsEl.innerHTML = "";
        }, MODAL_CLOSE_DURATION);
    }

    function prevSlide() { stopAuto(); resetZoom(); showSlide(current - 1); startAuto(); }
    function nextSlide() { stopAuto(); resetZoom(); showSlide(current + 1); startAuto(); }

    function startAuto() {
        stopAuto();
        if (currentPhotos.length > 1) {
            autoTimer = setInterval(function() { showSlide(current + 1); }, AUTO_INTERVAL);
        }
    }
    function stopAuto() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }

    // ── Marquee speed: ~5 s per card (min 10 s total) ──────────
    var wfTrack = document.getElementById("workFolderTrack");
    if (wfTrack) {
        var numCats  = allCategories.length;
        var duration = Math.max(10, numCats * 5);
        wfTrack.style.setProperty("--wf-duration", duration + "s");
    }

    // ── Custom mouse-circle cursor + drag-to-scroll ──────────────
    (function() {
        var wfMarquee = document.querySelector(".work-folder-marquee");
        if (!wfMarquee || !wfTrack) return;

        // Create the cursor circle element
        var wfCursor = document.createElement("div");
        wfCursor.className = "wf-cursor";
        wfCursor.setAttribute("aria-hidden", "true");
        wfMarquee.appendChild(wfCursor);

        var isDragging  = false;
        var didDrag     = false;   // true when pointer actually moved > 5 px
        var dragStartX  = 0;
        var dragTrackX  = 0;      // translateX value captured at drag-start
        var idleTimer   = null;   // hides cursor circle after inactivity
        var CURSOR_IDLE_TIMEOUT = 800; // ms of no movement before circle fades out

        /** Read the current translateX of the track from the computed matrix. */
        function getTrackX() {
            var style = window.getComputedStyle(wfTrack).transform;
            if (!style || style === "none") return 0;
            // matrix(a,b,c,d,tx,ty) — tx is the 5th value
            var m = style.match(/matrix\([^,]+,[^,]+,[^,]+,[^,]+,([^,]+),/);
            if (m) return parseFloat(m[1]) || 0;
            // 3d matrix fallback: matrix3d(…,tx,…)
            var m3 = style.match(/matrix3d\([^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,([^,]+),/);
            return m3 ? (parseFloat(m3[1]) || 0) : 0;
        }

        /** Return the half-width of the track in pixels (= the -50% travel distance). */
        function halfWidth() {
            return wfTrack.offsetWidth / 2;
        }

        /**
         * After a drag, re-sync the CSS animation so it resumes smoothly from
         * the position where the user left the track.
         */
        function resumeMarquee() {
            var x   = getTrackX();                            // current drag position (negative)
            var hw  = halfWidth();
            if (hw <= 0) return;

            // Clamp to valid range [−hw, 0]
            x = Math.max(-hw, Math.min(0, x));

            var progress  = -x / hw;                         // 0 → 1
            var durStr    = window.getComputedStyle(wfTrack).animationDuration;
            var dur       = parseFloat(durStr) || 30;        // seconds

            // Negative delay fast-forwards the animation to the correct frame
            wfTrack.style.animationDelay     = -(progress * dur) + "s";
            wfTrack.style.transform          = "";            // let CSS animation take over
            wfTrack.style.animationPlayState = "";            // let CSS :hover rule decide
        }

        // ── Mouse-move: position the circle + apply drag delta ──────
        wfMarquee.addEventListener("mousemove", function(e) {
            var rect = wfMarquee.getBoundingClientRect();
            wfCursor.style.left = (e.clientX - rect.left) + "px";
            wfCursor.style.top  = (e.clientY - rect.top)  + "px";

            // Show the circle whenever the mouse moves; start idle timer
            if (!isDragging) {
                wfCursor.classList.add("wf-cursor--visible");
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    if (!isDragging) {
                        wfCursor.classList.remove("wf-cursor--visible");
                    }
                }, CURSOR_IDLE_TIMEOUT);
            }

            if (!isDragging) return;

            var dx   = e.clientX - dragStartX;
            if (Math.abs(dx) > 5) didDrag = true;

            var hw   = halfWidth();
            var newX = Math.max(-hw, Math.min(0, dragTrackX + dx));
            wfTrack.style.transform = "translateX(" + newX + "px)";
        });

        // ── Mouse-enter: show cursor circle + start idle timer ─────
        wfMarquee.addEventListener("mouseenter", function() {
            clearTimeout(idleTimer);
            wfCursor.classList.add("wf-cursor--visible");
            idleTimer = setTimeout(function() {
                if (!isDragging) {
                    wfCursor.classList.remove("wf-cursor--visible");
                }
            }, CURSOR_IDLE_TIMEOUT);
        });

        // ── Mouse-leave: hide circle + clear idle timer + end drag ──
        wfMarquee.addEventListener("mouseleave", function() {
            clearTimeout(idleTimer);
            wfCursor.classList.remove("wf-cursor--visible");
            if (isDragging) {
                isDragging = false;
                wfCursor.classList.remove("wf-cursor--grabbing");
                resumeMarquee();
            }
            // Reset so a re-entry doesn't suppress the next genuine click
            didDrag = false;
        });

        // ── Mouse-down: start drag ────────────────────────────────────
        wfMarquee.addEventListener("mousedown", function(e) {
            if (e.button !== 0) return;     // left button only
            isDragging = true;
            didDrag    = false;
            dragStartX = e.clientX;
            dragTrackX = getTrackX();
            wfTrack.style.animationPlayState = "paused";
            clearTimeout(idleTimer);        // keep circle visible while dragging
            wfCursor.classList.add("wf-cursor--visible");
            wfCursor.classList.add("wf-cursor--grabbing");
            e.preventDefault();             // prevent text selection while dragging
        });

        // ── Mouse-up: end drag (document-level to catch releases outside) ──
        document.addEventListener("mouseup", function() {
            if (!isDragging) return;
            isDragging = false;
            wfCursor.classList.remove("wf-cursor--grabbing");
            resumeMarquee();
        });

        // ── Suppress folder-open clicks that follow a real drag ──────
        wfMarquee.addEventListener("click", function(e) {
            if (didDrag) {
                e.stopPropagation();
                e.preventDefault();
                didDrag = false;
            }
        }, true /* capture phase — runs before card click handlers */);
    }());

    // Attach click / keyboard to interactive (non-duplicate) folder cards only
    document.querySelectorAll(".work-folder-card:not([aria-hidden])").forEach(function(card) {
        function open() {
            openFolder(parseInt(card.dataset.catIndex, 10), 0);
        }
        card.addEventListener("click", open);
        card.addEventListener("keydown", function(e) {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
        });
    });

    // Duplicate cards (aria-hidden) still need click support for mouse users
    document.querySelectorAll(".work-folder-card[aria-hidden]").forEach(function(card) {
        card.addEventListener("click", function() {
            openFolder(parseInt(card.dataset.catIndex, 10), 0);
        });
    });

    document.getElementById("portfolioModalClose").addEventListener("click", closeModal);
    document.getElementById("portfolioModalPrev").addEventListener("click", prevSlide);
    document.getElementById("portfolioModalNext").addEventListener("click", nextSlide);

    modal.querySelector(".portfolio-modal-backdrop").addEventListener("click", closeModal);

    document.addEventListener("keydown", function(e) {
        if (!modal.classList.contains("active")) return;
        if (e.key === "Escape")     closeModal();
        if (e.key === "ArrowLeft")  prevSlide();
        if (e.key === "ArrowRight") nextSlide();
        if (e.key === "+" || e.key === "=") zoomIn();
        if (e.key === "-") zoomOut();
        if (e.key === "0") { resetZoom(); startAuto(); }
    });

    var swipeStartX = 0;
    modal.addEventListener("touchstart", function(e) {
        if (zoomLevel <= 1) swipeStartX = e.touches[0].pageX;
    }, { passive: true });
    modal.addEventListener("touchend", function(e) {
        if (zoomLevel > 1) return;
        var dx = e.changedTouches[0].pageX - swipeStartX;
        if (Math.abs(dx) > 50) { dx < 0 ? nextSlide() : prevSlide(); }
    }, { passive: true });

    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseenter", function() {
        if (zoomLevel <= 1) stopAuto();
    });
    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseleave", function() {
        if (zoomLevel <= 1) startAuto();
    });
})();
</script>
<?php endif; ?>

<?php
// Get all active vendors for the vendor listing section
$vendors = getVendors();
if (!empty($vendors)):
    // Collect distinct vendor types present in the current vendor list
    $vendor_type_slugs_present = array_filter(array_unique(array_column($vendors, 'type')));
    $all_vendor_types = getVendorTypes();
    $present_vendor_types = array_filter($all_vendor_types, function($vt) use ($vendor_type_slugs_present) {
        return in_array($vt['slug'], $vendor_type_slugs_present, true);
    });
?>
<!-- Vendors Section -->
<section class="vendors-section py-5" id="section-vendors">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Our Team</span>
            <h2 class="section-title">Our Expert Team</h2>
            <p class="text-muted mt-2">Meet the professionals who make your event special</p>
            <?php echo getSectionShareButton('section-vendors', BASE_URL . '/vendors.php'); ?>
        </div>

        <?php if (count($present_vendor_types) > 1): ?>
        <!-- Vendor Category Filter Buttons -->
        <div class="vendor-filter-bar text-center mb-4" id="vendorFilterBar">
            <button class="vendor-filter-btn active" data-filter="all">All</button>
            <?php foreach ($present_vendor_types as $vt): ?>
                <button class="vendor-filter-btn"
                        data-filter="<?php echo htmlspecialchars($vt['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($vt['label'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="vendor-auto-wrapper">
            <div class="vendor-auto-track" data-vendor-slider>
                <?php
                $seen_vendor_ids = [];
                foreach ($vendors as $vendor):
                    // Deduplicate: skip vendors already rendered
                    if (in_array((int)$vendor['id'], $seen_vendor_ids, true)) continue;
                    $seen_vendor_ids[] = (int)$vendor['id'];

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
                    <div class="vendor-auto-card" data-vendor-type="<?php echo htmlspecialchars($vendor['type'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                    <p class="card-text text-muted mb-2 d-flex align-items-center gap-1">
                                        <i class="fas fa-map-marker-alt text-success flex-shrink-0"></i>
                                        <span><?php echo $vendor_location; ?></span>
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
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/vendors.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// Get testimonials
$testimonial_images = getImagesBySection('testimonial');
$user_reviews = getApprovedUserReviews(6);
if (!empty($testimonial_images)):
?>
<!-- Testimonials Section -->
<section class="testimonials-section py-5 bg-light" id="section-testimonials">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Testimonials</span>
            <h2 class="section-title">What Our Clients Say</h2>
            <p class="text-muted mt-2">Memories made, moments cherished</p>
            <?php echo getSectionShareButton('section-testimonials', BASE_URL . '/testimonials.php'); ?>
        </div>

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
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/testimonials.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($user_reviews)): ?>
<!-- User Reviews Section -->
<section class="py-5" id="section-user-reviews">
    <div class="container">
        <div class="text-center mb-5 reveal section-heading-wrap">
            <span class="section-eyebrow">Client Reviews</span>
            <h2 class="section-title">Real Experiences, Real Stories</h2>
            <p class="text-muted mt-2">Hear what our happy clients have to say</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($user_reviews as $review): ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="mb-2" aria-label="<?php echo (int)$review['rating']; ?> <?php echo (int)$review['rating'] === 1 ? 'star' : 'stars'; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= (int)$review['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" style="font-size:.85rem;" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-3" style="font-size:.95rem; color:#444;">
                            <i class="fas fa-quote-left text-success me-1" style="opacity:.5;"></i><?php echo htmlspecialchars($review['review_text'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mb-0 fw-semibold small text-muted">
                            — <?php echo htmlspecialchars($review['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>/testimonials.php" class="btn btn-outline-success px-5 py-2 section-view-btn">
                <i class="fas fa-eye me-2"></i>View All Reviews
            </a>
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
<section class="about-section py-5" id="section-about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 reveal">
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
            <div class="col-lg-7 reveal">
                <div class="section-heading-wrap d-inline-block w-100">
                    <span class="section-eyebrow">About Us</span>
                    <h2 class="section-title mb-3">Our Story</h2>
                    <?php echo getSectionShareButton('section-about', BASE_URL . '/about.php'); ?>
                </div>
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
                            <div class="about-stat-number"><?php echo $stat_venues; ?>+</div>
                            <div class="about-stat-label">Venues</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="about-stat-card">
                            <i class="fas fa-calendar-check about-stat-icon"></i>
                            <div class="about-stat-number"><?php echo $stat_events; ?>+</div>
                            <div class="about-stat-label">Events</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="about-stat-card">
                            <i class="fas fa-smile about-stat-icon"></i>
                            <div class="about-stat-number"><?php echo $stat_clients; ?>+</div>
                            <div class="about-stat-label">Happy Clients</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Venue city filter script (output directly so PHP values can be embedded cleanly) -->
<script>
(function () {
    var BASE_URL_JS = <?php echo json_encode(rtrim(BASE_URL, '/')); ?>;

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }

    function buildVenueCard(venue) {
        var carouselId = 'venueImageCarouselDyn' + venue.id;
        var imgHtml = '';
        if (venue.images.length > 1) {
            var items = venue.images.map(function (src, idx) {
                return '<div class="carousel-item' + (idx === 0 ? ' active' : '') + '">' +
                       '<div class="venue-image-home" style="background-image:url(\'' + encodeURI(src) + '\')"></div>' +
                       '</div>';
            }).join('');
            imgHtml = '<div id="' + carouselId + '" class="carousel slide venue-image-carousel" data-bs-ride="carousel">' +
                      '<div class="carousel-inner">' + items + '</div>' +
                      '<button class="carousel-control-prev" type="button" data-bs-target="#' + carouselId + '" data-bs-slide="prev">' +
                      '<span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>' +
                      '<button class="carousel-control-next" type="button" data-bs-target="#' + carouselId + '" data-bs-slide="next">' +
                      '<span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>' +
                      '<div class="carousel-indicators-counter"><span class="badge bg-dark bg-opacity-75"><i class="fas fa-images"></i> ' + venue.images.length + '</span></div>' +
                      '</div>';
        } else {
            imgHtml = '<div class="card-img-top venue-image-home" style="background-image:url(\'' + encodeURI(venue.images[0]) + '\')"></div>';
        }
        var panoBtn = venue.pano_image_url
            ? '<button type="button" class="btn btn-outline-primary w-100 home-pano-btn mb-2"' +
              ' data-pano-url="' + escapeHtml(venue.pano_image_url) + '"' +
              ' data-venue-name="' + escapeHtml(venue.name) + '">' +
              '<i class="fas fa-street-view"></i> View 360°</button>'
            : '';
        return '<div class="venue-slide">' +
               '<div class="venue-card-home card h-100 shadow-sm">' +
               imgHtml +
               '<div class="card-body d-flex flex-column">' +
               '<h5 class="card-title">' + escapeHtml(venue.name) + '</h5>' +
               '<p class="card-text"><i class="fas fa-map-marker-alt text-success"></i> ' + escapeHtml(venue.city_name) + '</p>' +
               '<p class="card-text text-muted flex-grow-1">' + escapeHtml(venue.description) + '</p>' +
               panoBtn +
               '<button type="button" class="btn btn-success w-100 venue-book-btn mt-auto"' +
               ' data-venue-id="' + venue.id + '" data-venue-name="' + escapeHtml(venue.name) + '">' +
               '<i class="fas fa-calendar-check"></i> Book Now</button>' +
               '</div></div></div>';
    }

    function handleVenueBookClick() {
        var venueId   = this.getAttribute('data-venue-id');
        var venueName = this.getAttribute('data-venue-name');
        sessionStorage.setItem('preferred_venue_id', venueId);
        sessionStorage.setItem('preferred_venue_name', venueName);
        var hiddenField = document.getElementById('preferred_venue_id');
        if (hiddenField) { hiddenField.value = venueId; }
        var bookingFormSection = document.getElementById('bookingSection');
        if (bookingFormSection) {
            bookingFormSection.scrollIntoView({ behavior: 'smooth' });
            var bookingCard = document.querySelector('.booking-card');
            if (bookingCard) {
                bookingCard.style.animation = 'pulse 0.5s ease-in-out';
                setTimeout(function () { bookingCard.style.animation = ''; }, 500);
            }
        }
    }

    function attachBookBtnListeners() {
        document.querySelectorAll('.venue-book-btn').forEach(function (btn) {
            btn.removeEventListener('click', handleVenueBookClick);
            btn.addEventListener('click', handleVenueBookClick);
        });
    }

    function handleHomePanoClick() {
        var panoUrl   = this.getAttribute('data-pano-url');
        var venueName = this.getAttribute('data-venue-name') || '';
        openHomePanoViewer(panoUrl, venueName);
    }

    function attachPanoBtnListeners() {
        document.querySelectorAll('.home-pano-btn').forEach(function (btn) {
            btn.removeEventListener('click', handleHomePanoClick);
            btn.addEventListener('click', handleHomePanoClick);
        });
    }

    function ensureHomePanoModal() {
        if (!document.getElementById('homePanoViewerModal')) {
            var modalHtml =
                '<div class="modal fade" id="homePanoViewerModal" tabindex="-1" aria-labelledby="homePanoViewerModalLabel" aria-hidden="true">' +
                  '<div class="modal-dialog modal-xl modal-dialog-centered">' +
                    '<div class="modal-content">' +
                      '<div class="modal-header">' +
                        '<h5 class="modal-title" id="homePanoViewerModalLabel">' +
                          '<i class="fas fa-street-view text-primary"></i> <span id="homePanoViewerVenueName"></span> — 360° View' +
                        '</h5>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                      '</div>' +
                      '<div class="modal-body p-0">' +
                        '<div id="homePanoViewerContainer" style="width:100%;height:480px;"></div>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>';
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            document.getElementById('homePanoViewerModal').addEventListener('hidden.bs.modal', function () {
                if (window._homePanoViewerInstance) {
                    window._homePanoViewerInstance.destroy();
                    window._homePanoViewerInstance = null;
                }
            });
        }
    }

    function showHomePanoFallback(panoUrl) {
        var container = document.getElementById('homePanoViewerContainer');
        if (!container) return;
        container.style.cssText = 'width:100%;height:480px;background:#000;overflow:hidden;display:flex;align-items:center;justify-content:center;';
        container.innerHTML = '';
        var img = document.createElement('img');
        img.src = panoUrl;
        img.alt = '360\u00b0 panoramic photo';
        img.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain;';
        img.onerror = function () {
            img.style.display = 'none';
            var msg = document.createElement('div');
            msg.style.cssText = 'color:#fff;text-align:center;';
            var icon = document.createElement('i');
            icon.className = 'fas fa-image fa-3x';
            icon.style.cssText = 'opacity:.5;display:block;margin-bottom:8px;';
            var text = document.createTextNode('Image could not be loaded.');
            msg.appendChild(icon);
            msg.appendChild(text);
            container.appendChild(msg);
        };
        container.appendChild(img);
    }

    function openHomePanoViewer(panoUrl, venueName) {
        ensureHomePanoModal();
        var modalEl = document.getElementById('homePanoViewerModal');
        if (!modalEl) return;
        var nameEl = document.getElementById('homePanoViewerVenueName');
        if (nameEl) nameEl.textContent = venueName;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        modalEl.addEventListener('shown.bs.modal', function initViewer() {
            if (window._homePanoViewerInstance) {
                window._homePanoViewerInstance.destroy();
                window._homePanoViewerInstance = null;
            }
            var container = document.getElementById('homePanoViewerContainer');
            if (container) container.innerHTML = '';

            if (typeof pannellum === 'undefined') {
                showHomePanoFallback(panoUrl);
                return;
            }

            try {
                window._homePanoViewerInstance = pannellum.viewer('homePanoViewerContainer', {
                    type: 'equirectangular',
                    panorama: panoUrl,
                    autoLoad: true,
                    autoRotate: -2,
                    autoRotateInactivityDelay: 3000,
                    showControls: true,
                    showZoomCtrl: true,
                    showFullscreenCtrl: true,
                    compass: false,
                    keyboardZoom: false
                });

                window._homePanoViewerInstance.on('error', function() {
                    if (window._homePanoViewerInstance) {
                        window._homePanoViewerInstance.destroy();
                        window._homePanoViewerInstance = null;
                    }
                    showHomePanoFallback(panoUrl);
                });
            } catch (e) {
                window._homePanoViewerInstance = null;
                console.error('Pannellum initialization failed:', e);
                showHomePanoFallback(panoUrl);
            }
        }, { once: true });
    }

    function loadVenues(cityId) {
        var url = BASE_URL_JS + '/api/get-venues.php';
        if (cityId) { url += '?city_id=' + encodeURIComponent(cityId); }
        var venuesGrid  = document.getElementById('venuesGrid');
        var venuesEmpty = document.getElementById('venuesEmptyState');
        if (!venuesGrid) return;
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    if (venuesEmpty) {
                        venuesEmpty.classList.remove('d-none');
                        venuesGrid.innerHTML = '';
                    }
                    refreshVenuesSlider();
                    return;
                }
                if (data.venues.length === 0) {
                    venuesGrid.innerHTML = '';
                    if (venuesEmpty) venuesEmpty.classList.remove('d-none');
                } else {
                    if (venuesEmpty) venuesEmpty.classList.add('d-none');
                    venuesGrid.innerHTML = data.venues.map(buildVenueCard).join('');
                    attachBookBtnListeners();
                    attachPanoBtnListeners();
                    venuesGrid.querySelectorAll('.venue-image-carousel').forEach(function (el) {
                        new bootstrap.Carousel(el, { interval: 4000 });
                    });
                }
                // Reset scroll position and refresh nav buttons after content update
                var viewport = document.getElementById('venuesSliderViewport');
                if (viewport) { viewport.scrollLeft = 0; }
                refreshVenuesSlider();
            })
            .catch(function () {
                if (venuesEmpty) {
                    venuesGrid.innerHTML = '';
                    venuesEmpty.classList.remove('d-none');
                }
                refreshVenuesSlider();
            });
    }

    // Venues horizontal slider navigation
    function refreshVenuesSlider() {
        var viewport = document.getElementById('venuesSliderViewport');
        var prevBtn  = document.getElementById('venuesSliderPrev');
        var nextBtn  = document.getElementById('venuesSliderNext');
        if (!viewport || !prevBtn || !nextBtn) return;
        prevBtn.disabled = viewport.scrollLeft <= 0;
        // Subtract 1 to absorb sub-pixel rounding that can prevent the button from disabling at the true end
        nextBtn.disabled = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 1;
    }

    function initVenuesSlider() {
        var viewport  = document.getElementById('venuesSliderViewport');
        var prevBtn   = document.getElementById('venuesSliderPrev');
        var nextBtn   = document.getElementById('venuesSliderNext');
        var sliderTrack = document.getElementById('venuesGrid');
        if (!viewport || !prevBtn || !nextBtn) return;

        function getSlideScrollAmount() {
            var slide = viewport.querySelector('.venue-slide');
            // Fall back to a reasonable pixel estimate if no slide is rendered yet
            if (!slide) return 300;
            var gap = parseFloat(getComputedStyle(sliderTrack).gap) || 24;
            return slide.offsetWidth + gap;
        }

        prevBtn.addEventListener('click', function () {
            viewport.scrollBy({ left: -getSlideScrollAmount(), behavior: 'smooth' });
        });
        nextBtn.addEventListener('click', function () {
            viewport.scrollBy({ left: getSlideScrollAmount(), behavior: 'smooth' });
        });
        viewport.addEventListener('scroll', refreshVenuesSlider);
        window.addEventListener('resize', refreshVenuesSlider);
        refreshVenuesSlider();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var cityFilterBtns    = document.querySelectorAll('.venue-city-btn');
        var bookingCitySelect = document.getElementById('city_id');

        // City filter pill buttons
        cityFilterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                cityFilterBtns.forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                loadVenues(this.getAttribute('data-city-id'));
            });
        });

        // Auto-sync with booking form city dropdown
        if (bookingCitySelect) {
            bookingCitySelect.addEventListener('change', function () {
                var cityId = this.value;
                cityFilterBtns.forEach(function (b) {
                    var match = b.getAttribute('data-city-id') === cityId ||
                                (!cityId && b.getAttribute('data-city-id') === '');
                    b.classList.toggle('active', match);
                });
                loadVenues(cityId);
            });
        }

        // Attach listeners to server-rendered cards on first load
        attachBookBtnListeners();
        attachPanoBtnListeners();
        // Initialise horizontal venues slider
        initVenuesSlider();
    });
}());
</script>

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

<!-- Scroll to Top Button -->
<button class="scroll-top-fab" id="scrollTopFab" aria-label="Scroll to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<?php
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
<script src="' . BASE_URL . '/js/booking-flow.js"></script>
<script>
// Handle venue book button clicks - preferred venue message
document.addEventListener("DOMContentLoaded", function() {

    // Show preferred venue message if set
    const preferredVenueName = sessionStorage.getItem("preferred_venue_name");
    if (preferredVenueName) {
        const bookingCard = document.querySelector(".booking-card h4");
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
    const form = document.querySelector("form#bookingForm");
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
// ── Auto-scroll for multiple package category sliders ──
(function() {
    var speed = 0.5; // pixels per frame
    var dragSensitivity = 1.5;

    // Get all pkg-slider tracks (one per category)
    var allTracks = document.querySelectorAll(\'[data-pkg-slider]\');
    if (allTracks.length === 0) return;

    allTracks.forEach(function(track, trackIdx) {
        var hovered = false, dragging = false;
        var rafId = null;

        function isPaused() { return hovered || dragging; }

        // Mark all original cards so we can tell them apart from clones
        Array.from(track.querySelectorAll(\'.pkg-slider-card\')).forEach(function(card) {
            card.setAttribute(\'data-original\', \'1\');
        });

        function initSlider() {
            // Cancel any running animation
            if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

            // Remove existing clones
            Array.from(track.querySelectorAll(\'[data-clone]\'))
                .forEach(function(c) { track.removeChild(c); });

            // Collect original visible cards
            var origCards = Array.from(track.querySelectorAll(\'.pkg-slider-card[data-original]\'))
                .filter(function(c) { return c.style.display !== \'none\'; });
            if (origCards.length === 0) return;

            // Only auto-scroll (and clone) when the visible content overflows the wrapper.
            if (track.scrollWidth <= track.clientWidth + 2) {
                track.scrollLeft = 0;
                return;
            }

            // Duplicate cards for seamless infinite loop
            origCards.forEach(function(card, idx) {
                var clone = card.cloneNode(true);
                clone.setAttribute(\'data-clone\', \'1\');
                clone.removeAttribute(\'data-original\');
                // Remap all IDs in the clone to avoid duplicate-ID conflicts
                var idMap = {};
                clone.querySelectorAll(\'[id]\').forEach(function(el) {
                    var oldId = el.id;
                    var newId = oldId + \'_t\' + trackIdx + \'_c\' + idx;
                    idMap[oldId] = newId;
                    el.id = newId;
                });
                // Update all href and data-bs-target references in a single pass
                clone.querySelectorAll(\'[href], [data-bs-target]\').forEach(function(el) {
                    [\'href\', \'data-bs-target\'].forEach(function(attr) {
                        var val = el.getAttribute(attr);
                        if (val && val.charAt(0) === \'#\') {
                            var refId = val.slice(1);
                            if (idMap.hasOwnProperty(refId)) {
                                el.setAttribute(attr, \'#\' + idMap[refId]);
                            }
                        }
                    });
                });
                track.appendChild(clone);
            });

            track.scrollLeft = 0;

            function step() {
                if (!isPaused()) {
                    track.scrollLeft += speed;
                    var half = track.scrollWidth / 2;
                    if (track.scrollLeft >= half - 1) {
                        track.scrollLeft -= half;
                    }
                }
                rafId = requestAnimationFrame(step);
            }
            rafId = requestAnimationFrame(step);
        }

        // Initialize slider on load
        initSlider();

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

        // Category filter buttons – show/hide cards by category and reinitialise slider
        var pkgFilterBar = document.getElementById(\'pkgFilterBar\');
        if (pkgFilterBar) {
            pkgFilterBar.addEventListener(\'click\', function(e) {
                var btn = e.target.closest(\'.service-category-filter-btn\');
                if (!btn) return;
                pkgFilterBar.querySelectorAll(\'.service-category-filter-btn\').forEach(function(b) {
                    b.classList.toggle(\'active\', b === btn);
                });
                var filter = btn.getAttribute(\'data-filter\');
                Array.from(track.querySelectorAll(\'.pkg-slider-card[data-original]\')).forEach(function(card) {
                    card.style.display = (filter === \'all\' || card.getAttribute(\'data-pkg-category\') === filter) ? \'\' : \'none\';
                });
                initSlider();
            });
        }
    });
})();
</script>
<script>
// ── Pkg slider prev/next navigation buttons for multiple sliders ──
(function() {
    document.querySelectorAll(".pkg-slider-wrapper").forEach(function(wrapper) {
        var track = wrapper.querySelector("[data-pkg-slider]");
        var prevBtn = wrapper.querySelector(".pkg-slider-prev");
        var nextBtn = wrapper.querySelector(".pkg-slider-next");
        if (!track) return;

        function getCardWidth() {
            var card = track.querySelector(".pkg-slider-card:not([style*=\'display: none\'])");
            if (!card) return 320; // fallback: matches pkg-slider-card width in CSS
            var gap = parseFloat(getComputedStyle(track).gap) || 20;
            return card.offsetWidth + gap;
        }

        // Hide nav buttons when the slider does not overflow.
        // The +2 tolerance handles sub-pixel rounding differences across browsers.
        function updateNavVisibility() {
            var overflows = track.scrollWidth > wrapper.clientWidth + 2;
            if (prevBtn) prevBtn.style.display = overflows ? "" : "none";
            if (nextBtn) nextBtn.style.display = overflows ? "" : "none";
        }
        updateNavVisibility();
        window.addEventListener("resize", updateNavVisibility);

        if (prevBtn) {
            prevBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                track.scrollBy({ left: -getCardWidth(), behavior: "smooth" });
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                track.scrollBy({ left: getCardWidth(), behavior: "smooth" });
            });
        }
    });
})();
</script>
<script>
// ── Vendor auto-scroll carousel ──
(function() {
    var speed = 0.5; // pixels per frame
    var dragSensitivity = 1.5;

    var filterBar = document.getElementById(\'vendorFilterBar\');
    var track = document.querySelector(\'[data-vendor-slider]\');
    if (!track) return;

    var hovered = false, dragging = false;
    var rafId = null;

    function isPaused() { return hovered || dragging; }

    // Mark all original cards so we can tell them apart from clones
    Array.from(track.querySelectorAll(\'.vendor-auto-card\')).forEach(function(card) {
        card.setAttribute(\'data-original\', \'1\');
    });

    function initSlider() {
        // Cancel any running animation
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

        // Remove existing clones
        Array.from(track.querySelectorAll(\'[data-clone]\'))
            .forEach(function(c) { track.removeChild(c); });

        // Collect original visible cards
        var origCards = Array.from(track.querySelectorAll(\'.vendor-auto-card[data-original]\'))
            .filter(function(c) { return c.style.display !== \'none\'; });
        if (origCards.length === 0) return;

        // Only auto-scroll (and clone) when the visible content overflows the wrapper.
        // Without this check, filtering to a small number of vendors would still
        // create clones, making each card appear twice on-screen ("double duplicate").
        if (track.scrollWidth <= track.clientWidth + 2) {
            track.scrollLeft = 0;
            return;
        }

        // Clone visible cards for seamless infinite loop
        origCards.forEach(function(card, idx) {
            var clone = card.cloneNode(true);
            clone.removeAttribute(\'data-original\');
            clone.setAttribute(\'data-clone\', \'1\');
            // Remap IDs to avoid duplicate-ID conflicts
            var idMap = {};
            clone.querySelectorAll(\'[id]\').forEach(function(el) {
                var oldId = el.id;
                var newId = oldId + \'_vc\' + idx;
                idMap[oldId] = newId;
                el.id = newId;
            });
            clone.querySelectorAll(\'[href], [data-bs-target]\').forEach(function(el) {
                [\'href\', \'data-bs-target\'].forEach(function(attr) {
                    var val = el.getAttribute(attr);
                    if (val && val.charAt(0) === \'#\') {
                        var refId = val.slice(1);
                        if (idMap.hasOwnProperty(refId)) el.setAttribute(attr, \'#\' + idMap[refId]);
                    }
                });
            });
            track.appendChild(clone);
        });

        track.scrollLeft = 0;

        function step() {
            if (!isPaused()) {
                track.scrollLeft += speed;
                var half = track.scrollWidth / 2;
                if (track.scrollLeft >= half - 1) {
                    track.scrollLeft -= half;
                }
            }
            rafId = requestAnimationFrame(step);
        }
        rafId = requestAnimationFrame(step);
    }

    initSlider();

    // Pause on mouse hover
    track.addEventListener(\'mouseenter\', function() { hovered = true; });
    track.addEventListener(\'mouseleave\', function() { hovered = false; });

    // Mouse drag-to-scroll
    var isDown = false, startX = 0, scrollStart = 0;
    track.addEventListener(\'mousedown\', function(e) {
        isDown = true; dragging = true;
        track.classList.add(\'vendor-grabbing\');
        startX = e.pageX - track.offsetLeft;
        scrollStart = track.scrollLeft;
        document.addEventListener(\'mousemove\', onMove);
        e.preventDefault();
    });
    function onMove(e) {
        if (!isDown) return;
        track.scrollLeft = scrollStart - (e.pageX - track.offsetLeft - startX) * dragSensitivity;
    }
    function stopDrag() {
        if (!isDown) return;
        isDown = false; dragging = false;
        track.classList.remove(\'vendor-grabbing\');
        document.removeEventListener(\'mousemove\', onMove);
    }
    document.addEventListener(\'mouseup\', stopDrag);

    // Touch support
    var tStartX = 0, tScrollStart = 0;
    track.addEventListener(\'touchstart\', function(e) {
        hovered = true; dragging = true;
        tStartX = e.touches[0].pageX; tScrollStart = track.scrollLeft;
    }, { passive: true });
    track.addEventListener(\'touchmove\', function(e) {
        track.scrollLeft = tScrollStart - (e.touches[0].pageX - tStartX);
    }, { passive: true });
    track.addEventListener(\'touchend\', function() { hovered = false; dragging = false; }, { passive: true });
    track.addEventListener(\'touchcancel\', function() { hovered = false; dragging = false; }, { passive: true });

    // Category filter buttons – reinitialise slider after filtering
    if (filterBar) {
        filterBar.addEventListener(\'click\', function(e) {
            var btn = e.target.closest(\'.vendor-filter-btn\');
            if (!btn) return;
            filterBar.querySelectorAll(\'.vendor-filter-btn\').forEach(function(b) {
                b.classList.toggle(\'active\', b === btn);
            });
            var filter = btn.getAttribute(\'data-filter\');
            Array.from(track.querySelectorAll(\'.vendor-auto-card[data-original]\')).forEach(function(card) {
                card.style.display = (filter === \'all\' || card.getAttribute(\'data-vendor-type\') === filter) ? \'\' : \'none\';
            });
            initSlider();
        });
    }
})();
</script>
<script>
// ── Reveal / entrance animations via IntersectionObserver ──
(function() {
    if (!("IntersectionObserver" in window)) {
        document.querySelectorAll(".reveal, .reveal-stagger").forEach(function(el) {
            el.classList.add("revealed");
        });
        return;
    }
    var revealEls = document.querySelectorAll(".reveal, .reveal-stagger");
    var revealed  = 0;
    var total     = revealEls.length;
    var obs = new IntersectionObserver(function(entries) {
        if (!entries.length) return;
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("revealed");
                obs.unobserve(entry.target);
                revealed++;
                if (revealed >= total) { obs.disconnect(); }
            }
        });
    }, { threshold: 0.12 });
    revealEls.forEach(function(el) { obs.observe(el); });
}());
</script>
<script>
// ── Scroll-to-top button (RAF-throttled) ──
(function() {
    var btn = document.getElementById("scrollTopFab");
    if (!btn) return;
    var ticking = false;
    window.addEventListener("scroll", function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                btn.classList.toggle("visible", window.scrollY > 400);
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
    btn.addEventListener("click", function() {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
}());
</script>
<script>
// Deep-link: scroll to hash section on page load
(function() {
    function scrollToHashSection() {
        var hash = window.location.hash;
        if (!hash) return;
        var target = document.querySelector(hash);
        if (!target) return;
        setTimeout(function() {
            var nav = document.querySelector(\'.navbar\') || document.querySelector(\'nav\');
            var navHeight = nav ? nav.offsetHeight : 70;
            var top = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
            window.scrollTo({ top: top, behavior: \'smooth\' });
        }, 350);
    }
    if (document.readyState === \'complete\') {
        scrollToHashSection();
    } else {
        window.addEventListener(\'load\', scrollToHashSection);
    }
}());
</script>
<script>
// ── Package "View" detail modal ──
(function() {
    function escHtml(str) {
        if (str == null) return \'\';
        var d = document.createElement(\'div\');
        d.textContent = String(str);
        return d.innerHTML;
    }
    document.addEventListener(\'click\', function(e) {
        var btn = e.target.closest(\'.pkg-view-btn\');
        if (!btn) return;
        var pkg;
        try {
            pkg = JSON.parse(btn.getAttribute(\'data-pkg\'));
        } catch (err) {
            console.error(\'pkg-view-btn: failed to parse package data\', err);
            return;
        }

        // Set modal title
        document.getElementById(\'pkgViewModalLabel\').textContent = pkg.name || \'\';

        // Build modal body
        var body = \'\';

        // Price & category
        body += \'<div class="mb-3">\';
        if (pkg.category) {
            body += \'<span class="badge bg-secondary me-2">\' + escHtml(pkg.category) + \'</span>\';
        }
        body += \'<span class="fw-bold text-success fs-5">\' + escHtml(pkg.price) + \'</span>\';
        body += \'</div>\';

        // Food guest limit
        if (pkg.guest_limit && pkg.guest_limit > 0 && pkg.menus && pkg.menus.length > 0) {
            body += \'<div class="mb-3"><span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-2">\';
            body += \'<i class="fas fa-utensils me-2"></i>खाना = \' + escHtml(String(pkg.guest_limit)) + \' जना\';
            body += \'</span></div>\';
        }

        // Description
        if (pkg.description) {
            body += \'<p class="text-muted mb-3">\' + escHtml(pkg.description) + \'</p>\';
        }

        // Gallery photos
        if (pkg.photos && pkg.photos.length > 0) {
            body += \'<h6 class="fw-bold mb-2"><i class="fas fa-images me-1 text-primary"></i>Gallery</h6>\';
            body += \'<div class="row g-2 mb-3">\';
            pkg.photos.forEach(function(photo) {
                body += \'<div class="col-6 col-md-4">\';
                body += \'<img src="\' + escHtml(photo) + \'" class="img-fluid rounded" loading="lazy" alt="\' + escHtml(pkg.name) + \'">\';
                body += \'</div>\';
            });
            body += \'</div>\';
        }

        // Features
        if (pkg.features && pkg.features.length > 0) {
            body += \'<h6 class="fw-bold mb-2"><i class="fas fa-check-circle me-1 text-success"></i>Features</h6>\';
            body += \'<ul class="list-unstyled mb-3">\';
            pkg.features.forEach(function(feat) {
                body += \'<li class="mb-1"><i class="fas fa-check text-success me-2"></i>\' + escHtml(feat) + \'</li>\';
            });
            body += \'</ul>\';
        }

        // Menus
        if (pkg.menus && pkg.menus.length > 0) {
            body += \'<h6 class="fw-bold mb-2"><i class="fas fa-utensils me-1 text-warning"></i>Menu Items</h6>\';
            pkg.menus.forEach(function(menu) {
                body += \'<div class="card mb-2"><div class="card-body py-2 px-3">\';
                body += \'<div class="fw-semibold mb-1">\' + escHtml(menu.name) + \'</div>\';
                if (menu.structure && menu.structure.length > 0) {
                    menu.structure.forEach(function(section) {
                        body += \'<div class="ms-2 mb-1">\';
                        body += \'<span class="text-muted small fw-semibold">\' + escHtml(section.section_name) + \'</span>\';
                        if (section.groups) {
                            section.groups.forEach(function(group) {
                                body += \'<div class="ms-3">\';
                                body += \'<span class="small fw-semibold">\' + escHtml(group.group_name) + \':</span> \';
                                if (group.items && group.items.length > 0) {
                                    var itemNames = group.items.map(function(item) { return escHtml(item.item_name); });
                                    body += \'<span class="small text-secondary">\' + itemNames.join(\', \') + \'</span>\';
                                }
                                body += \'</div>\';
                            });
                        }
                        body += \'</div>\';
                    });
                }
                body += \'</div></div>\';
            });
        }

        document.getElementById(\'pkgViewModalBody\').innerHTML = body;

        // Update footer buttons
        var bookBtn = document.getElementById(\'pkgViewBookBtn\');
        if (bookBtn) bookBtn.href = pkg.book_url || \'#\';

        var detailBtn = document.getElementById(\'pkgViewDetailBtn\');
        if (detailBtn) detailBtn.href = pkg.detail_url || \'#\';

        var contactBtn = document.getElementById(\'pkgViewContactBtn\');
        if (contactBtn) {
            if (pkg.wa_url) {
                contactBtn.href = pkg.wa_url;
                contactBtn.style.display = \'\';
            } else {
                contactBtn.style.display = \'none\';
            }
        }

        // Show modal
        var modal = new bootstrap.Modal(document.getElementById(\'pkgViewModal\'));
        modal.show();
    });
}());
</script>

';
require_once __DIR__ . '/includes/footer.php';
?>
