<?php
$page_title       = 'Testimonials';
$page_description = 'Read what our clients say about us — real reviews from happy couples, families, and corporate clients who trusted us with their special events.';
$page_keywords    = 'testimonials, reviews, client feedback, venue reviews, Nepal event reviews';
require_once __DIR__ . '/includes/header.php';

// Data
$testimonial_images = getImagesBySection('testimonial');
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Testimonials</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><i class="fas fa-quote-left me-2"></i>हाम्रा ग्राहकहरूका विचार</h1>
        <p class="mb-0 mt-1 text-white-75 small">Testimonials — Memories made, moments cherished</p>
    </div>
</div>

<?php if (!empty($testimonial_images)): ?>
<!-- Testimonials Section -->
<section class="testimonials-section py-5 bg-light" id="section-testimonials">
    <div class="container">
        <?php
        // Show all testimonials in a responsive grid instead of a carousel
        // This is better for SEO as content is always visible
        ?>
        <div class="row g-4 justify-content-center">
            <?php foreach ($testimonial_images as $testimonial): ?>
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
                                    <p class="testimonial-quote">
                                        <i class="fas fa-quote-left text-success me-1"></i><?php echo htmlspecialchars($testimonial['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-quote-left fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No testimonials available at the moment.</h3>
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
