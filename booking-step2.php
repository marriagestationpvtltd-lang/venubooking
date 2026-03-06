<?php
$page_title = 'Select Venue & Hall';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Get booking data from session or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['booking_data'] = [
        'shift' => $_POST['shift'],
        'event_date' => $_POST['event_date'],
        'guests' => $_POST['guests'],
        'event_type' => $_POST['event_type'],
        'city_id' => isset($_POST['city_id']) && is_numeric($_POST['city_id']) ? intval($_POST['city_id']) : null
    ];
    
    // Check if there's a preferred venue
    if (isset($_POST['preferred_venue_id']) && is_numeric($_POST['preferred_venue_id']) && $_POST['preferred_venue_id'] > 0) {
        $preferred_venue_id = intval($_POST['preferred_venue_id']);
        // Redirect to same page with venue_id in query string
        header('Location: booking-step2.php?venue_id=' . $preferred_venue_id);
        exit;
    }
} elseif (!isset($_SESSION['booking_data'])) {
    header('Location: index.php');
    exit;
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

$booking_data = $_SESSION['booking_data'];

// Get available venues, filtered by city if provided
$city_id = isset($booking_data['city_id']) ? $booking_data['city_id'] : null;
$venues = getAvailableVenues($booking_data['event_date'], $booking_data['shift'], $city_id);

// Check if there's a preferred venue from query string
$preferred_venue_id = null;
if (isset($_GET['venue_id']) && is_numeric($_GET['venue_id'])) {
    $preferred_venue_id = intval($_GET['venue_id']);
}
?>

<!-- Booking Progress -->
<div class="booking-progress py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="progress-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-label">Details</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">2</span>
                        <span class="step-label">Venue & Hall</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step">
                        <span class="step-number">4</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step">
                        <span class="step-number">5</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Summary Bar -->
<div class="booking-summary-bar py-2 bg-success text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 col-12">
                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?>
                <span class="mx-2 d-none d-md-inline">|</span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                    <span class="mx-2 d-none d-md-inline">|</span>
                </span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-users"></i> <?php echo $booking_data['guests']; ?> Guests
                    <span class="mx-2 d-none d-md-inline">|</span>
                </span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-tag"></i> <?php echo $booking_data['event_type']; ?>
                </span>
            </div>
            <div class="col-md-4 col-12 text-md-end mt-2 mt-md-0">
                <strong>Total: <span id="totalCost"><?php echo formatCurrency(0); ?></span></strong>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Select a Venue
            <?php if (!empty($booking_data['city_id'])): ?>
                <?php
                $db_step2 = getDB();
                $city_stmt = $db_step2->prepare("SELECT name FROM cities WHERE id = ?");
                $city_stmt->execute([$booking_data['city_id']]);
                $selected_city = $city_stmt->fetchColumn();
                ?>
                <?php if ($selected_city): ?>
                    <small class="text-muted fs-6"> — <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($selected_city); ?></small>
                <?php endif; ?>
            <?php endif; ?>
        </h2>
        
        <?php if (empty($venues)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No venues available for the selected city and date. 
                <a href="index.php" class="alert-link">Try a different city or date.</a>
            </div>
        <?php else: ?>
            <div class="mb-4" id="venueSearchWrapper">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="venueSearchInput" class="form-control"
                           placeholder="Search venues by name..."
                           aria-label="Search venues by name">
                    <button class="btn btn-outline-secondary" type="button" id="venueSearchClear" style="display:none;" aria-label="Clear search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="venueSearchNoResults" class="alert alert-info" style="display:none;">
                <i class="fas fa-info-circle"></i> No venues found matching your search.
            </div>
            <div class="row g-4" id="venuesContainer">
                <?php foreach ($venues as $venue):
                    // Build images array for carousel (prefer gallery_images, fall back to single image)
                    $images_to_display = [];
                    if (!empty($venue['gallery_images'])) {
                        foreach ($venue['gallery_images'] as $gi) {
                            $images_to_display[] = htmlspecialchars(UPLOAD_URL . rawurlencode($gi['image_path']), ENT_QUOTES, 'UTF-8');
                        }
                    }
                    if (empty($images_to_display) && !empty($venue['image'])) {
                        $images_to_display[] = htmlspecialchars(UPLOAD_URL . rawurlencode($venue['image']), ENT_QUOTES, 'UTF-8');
                    }
                    if (empty($images_to_display)) {
                        $images_to_display[] = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                    }
                    $step2_carousel_id = 'venueStep2Carousel' . $venue['id'];
                ?>
                    <div class="col-md-6 col-lg-4" data-venue-name="<?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="venue-card card h-100 shadow-sm">
                            <?php if (count($images_to_display) > 1): ?>
                                <div id="<?php echo $step2_carousel_id; ?>" class="carousel slide venue-image-carousel" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                        <?php foreach ($images_to_display as $si_idx => $si_url): ?>
                                            <div class="carousel-item <?php echo $si_idx === 0 ? 'active' : ''; ?>">
                                                <div class="venue-image" style="background-image: url('<?php echo $si_url; ?>');"></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $step2_carousel_id; ?>" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $step2_carousel_id; ?>" data-bs-slide="next">
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
                                <div class="card-img-top venue-image" style="background-image: url('<?php echo $images_to_display[0]; ?>');"></div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo sanitize($venue['name']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt text-success"></i> 
                                    <?php echo sanitize($venue['city_name'] ?? $venue['location']); ?>
                                    <?php if (!empty($venue['map_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($venue['map_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ms-2 text-success" title="View on Google Maps">
                                            <i class="fas fa-map"></i>
                                        </a>
                                    <?php endif; ?>
                                </p>
                                <p class="card-text text-muted"><?php echo sanitize(substr($venue['description'], 0, 100)); ?>...</p>
                                <button type="button" class="btn btn-success w-100" 
                                        onclick="showHalls(<?php echo $venue['id']; ?>, '<?php echo sanitize($venue['name']); ?>')">
                                    <i class="fas fa-eye"></i> View Halls
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Halls Section (Initially Hidden) -->
        <div id="hallsSection" class="mt-5" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Available Halls in <span id="venueName"></span></h2>
                <button class="btn btn-outline-secondary" onclick="showVenues()">
                    <i class="fas fa-arrow-left"></i> Back to Venues
                </button>
            </div>
            <div class="row g-4" id="hallsContainer">
                <!-- Halls will be loaded here dynamically -->
            </div>
        </div>
    </div>
</section>

<?php
$extra_js = '
<script>
const bookingData = ' . json_encode($booking_data) . ';
const preferredVenueId = ' . ($preferred_venue_id ? $preferred_venue_id : 'null') . ';
</script>
<script src="' . BASE_URL . '/js/booking-flow.js"></script>
<script src="' . BASE_URL . '/js/booking-step2.js"></script>
<script>
// Auto-show halls for preferred venue
if (preferredVenueId) {
    document.addEventListener("DOMContentLoaded", function() {
        // Find the venue in the list
        const venueCards = document.querySelectorAll(".venue-card");
        venueCards.forEach(card => {
            const viewHallsBtn = card.querySelector("button[onclick*=\"showHalls\"]");
            if (viewHallsBtn) {
                const onclickAttr = viewHallsBtn.getAttribute("onclick");
                // More robust extraction - match showHalls with venue ID and name
                const matches = onclickAttr.match(/showHalls\\((\\d+),\\s*[\'\\"](.*?)[\'\\"]/);
                if (matches && parseInt(matches[1]) === preferredVenueId) {
                    const venueId = parseInt(matches[1]);
                    const venueName = matches[2];
                    setTimeout(() => {
                        showHalls(venueId, venueName);
                    }, 500);
                }
            }
        });
    });
}
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
