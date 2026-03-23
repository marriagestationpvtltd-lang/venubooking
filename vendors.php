<?php
$page_title       = 'Our Team';
$page_description = 'Meet our expert vendors — photographers, caterers, decorators, and more. The dedicated professionals who make your events special.';
$page_keywords    = 'vendors, event vendors, wedding vendors, caterer, photographer, decorator, Nepal';
require_once __DIR__ . '/includes/header.php';

// Data
$vendors = getVendors();
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

$vendor_type_slugs_present = [];
$all_vendor_types = [];
$present_vendor_types = [];
if (!empty($vendors)) {
    $vendor_type_slugs_present = array_filter(array_unique(array_column($vendors, 'type')));
    $all_vendor_types = getVendorTypes();
    $present_vendor_types = array_filter($all_vendor_types, function($vt) use ($vendor_type_slugs_present) {
        return in_array($vt['slug'], $vendor_type_slugs_present, true);
    });
}
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Our Team</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><i class="fas fa-user-tie me-2"></i>हाम्रा विशेषज्ञहरू</h1>
        <p class="mb-0 mt-1 text-white-75 small">Our Team — Meet the professionals who make your event special</p>
    </div>
</div>

<?php if (!empty($vendors)): ?>
<!-- Vendors Section -->
<section class="vendors-section py-5" id="section-vendors">
    <div class="container">
        <?php if (count($present_vendor_types) > 1): ?>
        <!-- Vendor Category Filter -->
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

        <div class="row g-3">
            <?php
            $seen_vendor_ids = [];
            foreach ($vendors as $vendor):
                if (in_array((int)$vendor['id'], $seen_vendor_ids, true)) continue;
                $seen_vendor_ids[] = (int)$vendor['id'];

                $vendor_type_label  = htmlspecialchars(getVendorTypeLabel($vendor['type']), ENT_QUOTES, 'UTF-8');
                $vendor_name        = htmlspecialchars($vendor['name'], ENT_QUOTES, 'UTF-8');
                $vendor_location    = htmlspecialchars($vendor['city_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $vendor_address     = htmlspecialchars($vendor['address'] ?? '', ENT_QUOTES, 'UTF-8');
                $vendor_notes       = htmlspecialchars($vendor['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                $vendor_description = htmlspecialchars($vendor['short_description'] ?? '', ENT_QUOTES, 'UTF-8');

                $vendor_photos_list = getVendorPhotos($vendor['id']);
                $primary_photo_path = !empty($vendor_photos_list) ? $vendor_photos_list[0]['image_path'] : ($vendor['photo'] ?? '');

                $wa_vendor_name = strip_tags($vendor['name']);
                $wa_vendor_type = strip_tags(getVendorTypeLabel($vendor['type']));
                $wa_message = "Hello, I am interested in your vendor: {$wa_vendor_name} ({$wa_vendor_type}). Please contact me with more details.";
                $wa_url = '';
                if (!empty($clean_office_whatsapp)) {
                    $wa_url = 'https://wa.me/' . $clean_office_whatsapp . '?text=' . rawurlencode($wa_message);
                }

                $extra_slides = [];
                if (!empty($vendor['address'])) {
                    $extra_slides[] = ['icon' => 'fas fa-map-marker-alt', 'label' => 'Address', 'value' => $vendor_address];
                }
                if (!empty($vendor['notes'])) {
                    $extra_slides[] = ['icon' => 'fas fa-info-circle', 'label' => 'About', 'value' => $vendor_notes];
                }

                $detail_carousel_id = 'vendorDetail' . (int)$vendor['id'];
            ?>
                <div class="col-12 col-sm-6 col-lg-4" data-vendor-type="<?php echo htmlspecialchars($vendor['type'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="vendor-card card h-100 shadow-sm">
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
                                <span class="badge bg-success mb-2 align-self-start">
                                    <i class="fas fa-tag me-1"></i><?php echo $vendor_type_label; ?>
                                </span>
                                <h5 class="card-title mb-1"><?php echo $vendor_name; ?></h5>
                                <?php if (!empty($vendor_description)): ?>
                                    <p class="card-text text-muted small mb-2"><?php echo $vendor_description; ?></p>
                                <?php endif; ?>
                                <?php if (!empty($vendor_location)): ?>
                                    <p class="card-text text-muted mb-2 d-flex align-items-center gap-1">
                                        <i class="fas fa-map-marker-alt text-success flex-shrink-0"></i>
                                        <span><?php echo $vendor_location; ?></span>
                                    </p>
                                <?php endif; ?>
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
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No vendors available at the moment.</h3>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success mt-3">
        <i class="fas fa-home me-1"></i> Back to Home
    </a>
</div>
<?php endif; ?>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to inquire about your vendors and services. Please help me.'); ?>"
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
    var filterBar = document.getElementById(\'vendorFilterBar\');
    if (!filterBar) return;
    filterBar.addEventListener(\'click\', function(e) {
        var btn = e.target.closest(\'.vendor-filter-btn\');
        if (!btn) return;
        filterBar.querySelectorAll(\'.vendor-filter-btn\').forEach(function(b) { b.classList.toggle(\'active\', b === btn); });
        var filter = btn.getAttribute(\'data-filter\');
        document.querySelectorAll(\'[data-vendor-type]\').forEach(function(card) {
            card.style.display = (filter === \'all\' || card.getAttribute(\'data-vendor-type\') === filter) ? \'\' : \'none\';
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
