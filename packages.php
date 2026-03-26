<?php
$page_title       = 'Service Packages';
$page_description = 'Browse our premium service packages for weddings, birthdays, corporate events and more. Transparent pricing, wide variety of options.';
$page_keywords    = 'service packages, venue packages, wedding packages, event packages, Nepal';
require_once __DIR__ . '/includes/header.php';
$page_canonical   = BASE_URL . '/packages.php';

// Data
$service_categories  = getServicePackagesByCategory();
$office_whatsapp     = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

// Flatten packages
$all_service_packages     = [];
$pkg_categories_present   = [];
if (!empty($service_categories)) {
    foreach ($service_categories as $cat) {
        if (!empty($cat['packages'])) {
            $pkg_categories_present[] = ['id' => $cat['id'], 'name' => $cat['name']];
            foreach ($cat['packages'] as $pkg) {
                $all_service_packages[] = array_merge($pkg, [
                    'category_name' => $cat['name'],
                    'category_id'   => $cat['id'],
                ]);
            }
        }
    }
}
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Packages</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <h1 class="h3 mb-0 fw-bold"><i class="fas fa-box-open me-2"></i>हाम्रा सेवा प्याकेजहरू</h1>
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
        <p class="mb-0 mt-1 text-white-75 small">Our Service Packages — तपाईंको अनुष्ठानको लागि उत्तम प्याकेज छान्नुहोस्</p>
    </div>
</div>

<?php if (!empty($all_service_packages)): ?>
<!-- Service Packages Section -->
<section class="service-packages-section pt-4 pb-5" id="section-packages">
    <div class="container">
        <?php if (count($pkg_categories_present) > 1): ?>
        <!-- Package Category Filter Buttons -->
        <div class="service-category-filter-bar text-center mb-4" id="pkgFilterBar">
            <button class="service-category-filter-btn active" data-filter="all">सबै</button>
            <?php foreach ($pkg_categories_present as $pcat): ?>
                <button class="service-category-filter-btn"
                        data-filter="<?php echo (int)$pcat['id']; ?>">
                    <?php echo htmlspecialchars($pcat['name'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="service-category-block">
            <div class="row g-3" id="pkgGrid">
                <?php foreach ($all_service_packages as $pkg):
                    $pkg_carousel_id = 'pkgCarousel' . (int)$pkg['id'];
                ?>
                    <div class="col-12 col-sm-6 col-lg-4" data-pkg-category="<?php echo (int)$pkg['category_id']; ?>">
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
                                <?php if (!empty($pkg['category_name'])): ?>
                                <div class="text-center mb-1">
                                    <span class="pkg-category-badge"><?php echo htmlspecialchars($pkg['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <?php endif; ?>
                                <h5 class="package-name text-center mb-2">
                                    <?php echo htmlspecialchars($pkg['name']); ?>
                                </h5>
                                <div class="text-center mb-2">
                                    <div class="package-price d-inline-block">
                                        <span class="price-label"><?php echo formatCurrency($pkg['price']); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($pkg['description'])): ?>
                                <p class="text-muted small mb-2"><?php echo sanitize($pkg['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($pkg['features'])):
                                    $max_visible = 5;
                                    $total_features = count($pkg['features']);
                                    $remaining = $total_features - $max_visible;
                                    $visible_features = array_slice($pkg['features'], 0, $max_visible);
                                    $hidden_features  = array_slice($pkg['features'], $max_visible);
                                    $feat_collapse_id = 'pkgFeatures' . (int)$pkg['id'];
                                ?>
                                    <div class="pkg-feat-icons mb-2">
                                        <?php foreach ($visible_features as $feat): ?>
                                        <div class="pkg-feat-icon-item" title="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                            <p class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ($remaining > 0): ?>
                                        <div class="pkg-feat-icon-item pkg-feat-more-item"
                                             role="button"
                                             data-bs-toggle="collapse"
                                             data-bs-target="#<?php echo $feat_collapse_id; ?>"
                                             aria-expanded="false"
                                             aria-controls="<?php echo $feat_collapse_id; ?>"
                                             title="+<?php echo $remaining; ?> more features">
                                            <div class="pkg-feat-more-chip">+<?php echo $remaining; ?></div>
                                            <p class="pkg-feat-icon-label">थप</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($remaining > 0): ?>
                                    <div class="collapse" id="<?php echo $feat_collapse_id; ?>">
                                        <div class="pkg-feat-icons mb-2">
                                            <?php foreach ($hidden_features as $feat): ?>
                                            <div class="pkg-feat-icon-item" title="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                                <p class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php
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
                                ?>
                                <div class="mt-auto pt-2">
                                    <a href="<?php echo BASE_URL; ?>/package-detail.php?id=<?php echo (int)$pkg['id']; ?>"
                                       class="btn btn-outline-success w-100 mb-2">
                                        <i class="fas fa-eye me-1"></i> भ्युअल
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
        </div>
    </div>
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No packages available at the moment.</h3>
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
$extra_js = '
<script>
(function() {
    var pkgFilterBar = document.getElementById(\'pkgFilterBar\');
    if (!pkgFilterBar) return;
    var pkgGrid = document.getElementById(\'pkgGrid\');
    pkgFilterBar.addEventListener(\'click\', function(e) {
        var btn = e.target.closest(\'.service-category-filter-btn\');
        if (!btn) return;
        pkgFilterBar.querySelectorAll(\'.service-category-filter-btn\').forEach(function(b) { b.classList.toggle(\'active\', b === btn); });
        var filter = btn.getAttribute(\'data-filter\');
        pkgGrid.querySelectorAll(\'[data-pkg-category]\').forEach(function(card) {
            card.style.display = (filter === \'all\' || card.getAttribute(\'data-pkg-category\') === filter) ? \'\' : \'none\';
        });
    });
})();
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
