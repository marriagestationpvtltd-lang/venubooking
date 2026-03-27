<?php
$page_title       = 'Testimonials';
$page_description = 'Read what our clients say about us — real reviews from happy couples, families, and corporate clients who trusted us with their special events.';
$page_keywords    = 'testimonials, reviews, client feedback, venue reviews, Nepal event reviews';
require_once __DIR__ . '/includes/header.php';

// Data
$testimonial_images  = getImagesBySection('testimonial');
$user_reviews        = getApprovedUserReviews();
$office_whatsapp     = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

$has_content = !empty($testimonial_images) || !empty($user_reviews);
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
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <h1 class="h3 mb-0 fw-bold"><i class="fas fa-quote-left me-2"></i>हाम्रा ग्राहकहरूका विचार</h1>
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
        <p class="mb-0 mt-1 text-white-75 small">Testimonials — Memories made, moments cherished</p>
    </div>
</div>

<?php if ($has_content): ?>
<!-- Admin-uploaded testimonial images -->
<?php if (!empty($testimonial_images)): ?>
<section class="testimonials-section py-5 bg-light" id="section-testimonials">
    <div class="container">
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
<?php endif; ?>

<!-- User-submitted reviews -->
<?php if (!empty($user_reviews)): ?>
<section class="py-5" id="section-user-reviews">
    <div class="container">
        <h2 class="h5 fw-bold mb-4 text-center">
            <i class="fas fa-users text-success me-2"></i>What Our Clients Say
        </h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($user_reviews as $review): ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <!-- Stars -->
                        <div class="mb-2" aria-label="<?php echo (int)$review['rating']; ?> stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= (int)$review['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" style="font-size:.85rem;"></i>
                            <?php endfor; ?>
                        </div>
                        <!-- Quote -->
                        <p class="mb-3" style="font-size:.95rem; color:#444;">
                            <i class="fas fa-quote-left text-success me-1" style="opacity:.5;"></i><?php echo htmlspecialchars($review['review_text'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <!-- Name -->
                        <p class="mb-0 fw-semibold small text-muted">
                            — <?php echo htmlspecialchars($review['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

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
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to get in touch about your venue services. Please help me.'); ?>"
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
