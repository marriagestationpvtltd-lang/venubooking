<?php
$page_title       = 'About Us';
$page_description = 'Learn about our story, our team, and our commitment to making every event unforgettable. Premium venues, professional service, and 1000+ happy clients.';
$page_keywords    = 'about us, venue booking company, Nepal event company, wedding planners Nepal';
require_once __DIR__ . '/includes/header.php';

// Data
$about_images = getImagesBySection('about');
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);
$public_stats = getPublicStats();
$stat_venues  = (int)($public_stats['venues'] ?? 0);
$stat_events  = (int)($public_stats['events'] ?? 0);
$stat_clients = (int)($public_stats['clients'] ?? 0);

$about_desc = '';
if (!empty($about_images)) {
    foreach ($about_images as $aimg) {
        if (!empty($aimg['description'])) {
            $about_desc = $aimg['description'];
            break;
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
                <li class="breadcrumb-item active text-white" aria-current="page">About Us</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i>हाम्रो बारेमा</h1>
        <p class="mb-0 mt-1 text-white-75 small">About Us — Our story, our mission, our team</p>
    </div>
</div>

<!-- About Section -->
<section class="about-section py-5" id="section-about">
    <div class="container">
        <div class="row align-items-center g-5">
            <?php if (!empty($about_images)): ?>
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
            <?php endif; ?>
            <div class="<?php echo !empty($about_images) ? 'col-lg-7' : 'col-12'; ?>">
                <span class="section-eyebrow">About Us</span>
                <h2 class="section-title mb-3">हाम्रो बारेमा</h2>
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

<!-- Why Choose Us Section -->
<section class="features-section py-5 bg-light" id="section-features">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">Why Choose Us</span>
            <h2 class="section-title">हामीलाई किन छान्ने?</h2>
            <p class="text-muted mt-2" style="max-width:520px;margin:0 auto;">हाम्रो प्रिमियम सेवाले तपाईंको हरेक अनुष्ठानलाई अविस्मरणीय बनाउँछ।</p>
        </div>
        <div class="row g-4">
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-green">
                    <div class="pro-feature-icon"><i class="fas fa-building"></i></div>
                    <h5>Multiple Venues</h5>
                    <p>Choose from our premium venues across the city for every occasion</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-orange">
                    <div class="pro-feature-icon"><i class="fas fa-utensils"></i></div>
                    <h5>Delicious Menus</h5>
                    <p>Wide variety of menu options to suit every taste and budget</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-purple">
                    <div class="pro-feature-icon"><i class="fas fa-tags"></i></div>
                    <h5>Transparent Pricing</h5>
                    <p>No hidden charges — clear and upfront pricing for all services</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pro-feature-card pfc-teal">
                    <div class="pro-feature-icon"><i class="fas fa-headset"></i></div>
                    <h5>24/7 Support</h5>
                    <p>Our dedicated team is always here to help you at every step</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 text-center">
    <div class="container">
        <h2 class="section-title mb-3">Ready to Book?</h2>
        <p class="text-muted mb-4">Start planning your perfect event today. Our team is ready to help you every step of the way.</p>
        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success btn-lg me-2">
            <i class="fas fa-calendar-check me-1"></i> Book Now
        </a>
        <?php if (!empty($clean_office_whatsapp)): ?>
        <a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to know more about your venues and services.'); ?>"
           target="_blank" rel="noopener noreferrer"
           class="btn btn-outline-success btn-lg">
            <i class="fab fa-whatsapp me-1"></i> Chat with Us
        </a>
        <?php endif; ?>
    </div>
</section>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to learn more about your venue services. Please help me.'); ?>"
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
