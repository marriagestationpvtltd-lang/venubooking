<?php
$page_title       = 'Write a Review';
$page_description = 'Share your experience and help others make informed decisions about their special events.';
require_once __DIR__ . '/includes/header.php';

$token   = trim($_GET['token'] ?? '');
$success = false;
$error   = '';
$review  = null;

if (empty($token)) {
    $error = 'Invalid or missing review link. Please use the link sent to you via email or WhatsApp.';
} else {
    $review = getReviewByToken($token);
    if (!$review) {
        $error = 'This review link is invalid. Please use the link sent to you.';
    } elseif ((int)$review['submitted'] === 1) {
        $success = true; // Already submitted — show thank-you
    }
}

// Handle form submission
if (!$success && empty($error) && $review && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['reviewer_name'] ?? '');
    $email  = trim($_POST['reviewer_email'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $text   = trim($_POST['review_text'] ?? '');

    if (empty($name)) {
        $error = 'Please enter your name.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } elseif (empty($text)) {
        $error = 'Please write your review before submitting.';
    } elseif (strlen($text) < 10) {
        $error = 'Your review is too short. Please write at least 10 characters.';
    } else {
        if (submitUserReview($token, $name, $email, $rating, $text)) {
            $success = true;
        } else {
            $error = 'Sorry, we could not save your review. The link may have already been used. Please contact us if you need assistance.';
        }
    }
}

$site_name      = getSetting('site_name', 'Venue Booking System');
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

// Pre-fill name/email from booking when available
$prefill_name  = $review['booking_name']  ?? '';
$prefill_email = $review['booking_email'] ?? '';
$prefill_event = $review['booking_event_type'] ?? '';
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Write a Review</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><i class="fas fa-star me-2"></i>Share Your Experience</h1>
        <p class="mb-0 mt-1 text-white-75 small">Your feedback helps us serve future clients better</p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">

                <?php if ($success): ?>
                <!-- Thank-you state -->
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <div class="mb-4">
                            <span style="font-size:4rem;">🎉</span>
                        </div>
                        <h2 class="h4 fw-bold text-success mb-3">Thank You for Your Review!</h2>
                        <p class="text-muted mb-4">
                            Your feedback has been submitted and is currently under review.
                            Once approved it will appear on our
                            <a href="<?php echo BASE_URL; ?>/testimonials.php" class="text-success fw-semibold">Testimonials</a> page.
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success">
                            <i class="fas fa-home me-1"></i> Back to Home
                        </a>
                    </div>
                </div>

                <?php elseif (!empty($error)): ?>
                <!-- Error state -->
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <div class="mb-4 text-danger" style="font-size:3rem;"><i class="fas fa-exclamation-circle"></i></div>
                        <h2 class="h5 fw-bold text-danger mb-3">Unable to Load Review Form</h2>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-1"></i> Back to Home
                        </a>
                        <?php if (!empty($clean_office_whatsapp)): ?>
                        <a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-success ms-2" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp me-1"></i> Contact Us
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- Review form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h5 fw-bold mb-1">
                            <?php if (!empty($prefill_event)): ?>
                                How was your <span class="text-success"><?php echo htmlspecialchars($prefill_event, ENT_QUOTES, 'UTF-8'); ?></span>?
                            <?php else: ?>
                                How was your experience?
                            <?php endif; ?>
                        </h2>
                        <p class="text-muted small mb-4">Please share your honest feedback below.</p>

                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="write-review.php?token=<?php echo urlencode($token); ?>" novalidate id="reviewForm">

                            <!-- Star Rating -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Your Rating <span class="text-danger">*</span></label>
                                <div class="star-rating-wrap d-flex gap-1" id="starRatingWrap" role="group" aria-label="Star rating">
                                    <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <input type="radio" name="rating" id="star<?php echo $s; ?>" value="<?php echo $s; ?>"
                                           class="visually-hidden star-radio"
                                           <?php echo ($s === 5) ? 'checked' : ''; ?>>
                                    <label for="star<?php echo $s; ?>" class="star-label" title="<?php echo $s; ?> star<?php echo $s > 1 ? 's' : ''; ?>">
                                        <i class="fas fa-star"></i>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted" id="ratingText">5 stars – Excellent</small>
                            </div>

                            <!-- Name -->
                            <div class="mb-3">
                                <label for="reviewer_name" class="form-label fw-semibold">Your Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="reviewer_name" name="reviewer_name"
                                       value="<?php echo htmlspecialchars($_POST['reviewer_name'] ?? $prefill_name, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="e.g. Ram Sharma" required maxlength="255" autocomplete="name">
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="reviewer_email" class="form-label fw-semibold">Email <small class="text-muted fw-normal">(optional, not shown publicly)</small></label>
                                <input type="email" class="form-control" id="reviewer_email" name="reviewer_email"
                                       value="<?php echo htmlspecialchars($_POST['reviewer_email'] ?? $prefill_email, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="your@email.com" maxlength="255" autocomplete="email">
                            </div>

                            <!-- Review Text -->
                            <div class="mb-4">
                                <label for="review_text" class="form-label fw-semibold">Your Review <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="review_text" name="review_text"
                                          rows="5" required minlength="10" maxlength="2000"
                                          placeholder="Tell us about your experience..."><?php echo htmlspecialchars($_POST['review_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <small class="text-muted"><span id="charCount">0</span>/2000 characters</small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Review
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>

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

<?php
$extra_css = '
<style>
/* ── Star rating (RTL trick: highest star first in DOM, rendered LTR via flex-row-reverse) ── */
.star-rating-wrap {
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.star-label {
    font-size: 2rem;
    color: #dee2e6;
    cursor: pointer;
    transition: color .15s, transform .1s;
    padding: 0 2px;
    line-height: 1;
}
.star-label:hover,
.star-label:hover ~ .star-label,
.star-radio:checked ~ .star-label {
    color: #ffc107;
}
.star-label:hover { transform: scale(1.15); }
</style>
';
$extra_js = '
<script>
(function () {
    // Star rating labels
    var ratingLabels = {
        1: "1 star \u2013 Poor",
        2: "2 stars \u2013 Fair",
        3: "3 stars \u2013 Good",
        4: "4 stars \u2013 Very Good",
        5: "5 stars \u2013 Excellent"
    };
    var radios = document.querySelectorAll(".star-radio");
    var ratingText = document.getElementById("ratingText");
    radios.forEach(function(r) {
        r.addEventListener("change", function() {
            if (ratingText) ratingText.textContent = ratingLabels[this.value] || "";
        });
    });

    // Character counter
    var textarea = document.getElementById("review_text");
    var counter  = document.getElementById("charCount");
    if (textarea && counter) {
        function updateCount() { counter.textContent = textarea.value.length; }
        textarea.addEventListener("input", updateCount);
        updateCount();
    }

    // Scroll-top button
    var btn = document.getElementById("scrollTopFab");
    if (btn) {
        window.addEventListener("scroll", function() {
            btn.classList.toggle("visible", window.scrollY > 400);
        }, { passive: true });
        btn.addEventListener("click", function() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }
}());
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
