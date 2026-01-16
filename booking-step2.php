<?php
$page_title = 'Select Venue & Hall';
require_once __DIR__ . '/includes/header.php';

// Get booking data from session or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['booking_data'] = [
        'shift' => $_POST['shift'],
        'event_date' => $_POST['event_date'],
        'guests' => $_POST['guests'],
        'event_type' => $_POST['event_type']
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

$booking_data = $_SESSION['booking_data'];

// Get available venues
$venues = getAvailableVenues($booking_data['event_date'], $booking_data['shift']);

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
            <div class="col-md-8">
                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?>
                <span class="mx-2">|</span>
                <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                <span class="mx-2">|</span>
                <i class="fas fa-users"></i> <?php echo $booking_data['guests']; ?> Guests
                <span class="mx-2">|</span>
                <i class="fas fa-tag"></i> <?php echo $booking_data['event_type']; ?>
            </div>
            <div class="col-md-4 text-end">
                <strong>Total: <span id="totalCost"><?php echo formatCurrency(0); ?></span></strong>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Select a Venue</h2>
        
        <?php if (empty($venues)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No venues available. Please try a different date.
            </div>
        <?php else: ?>
            <div class="row g-4" id="venuesContainer">
                <?php foreach ($venues as $venue): 
                    // Get image URL (already validated and sanitized in getAvailableVenues)
                    // The image filename is already safe, but we URL-encode it for proper URL construction
                    if (!empty($venue['image'])) {
                        $safe_url = UPLOAD_URL . rawurlencode($venue['image']);
                        $venue_image_url = htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8');
                    } else {
                        // Use placeholder for venues without images
                        $venue_image_url = htmlspecialchars(getPlaceholderImageUrl(), ENT_QUOTES, 'UTF-8');
                    }
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="venue-card card h-100 shadow-sm">
                            <div class="card-img-top venue-image" style="background-image: url('<?php echo $venue_image_url; ?>');">
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo sanitize($venue['name']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt text-success"></i> 
                                    <?php echo sanitize($venue['location']); ?>
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
                const matches = onclickAttr.match(/showHalls\\((\\d+),\\s*['\"](.*?)['\"]/);
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
