<?php
$page_title = 'View Venue Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get venue ID from URL
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch venue details
$stmt = $db->prepare("SELECT v.*, c.name AS city_name FROM venues v LEFT JOIN cities c ON v.city_id = c.id WHERE v.id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    header('Location: index.php');
    exit;
}

// Fetch halls for this venue
$halls_stmt = $db->prepare("SELECT * FROM halls WHERE venue_id = ? ORDER BY name");
$halls_stmt->execute([$venue_id]);
$halls = $halls_stmt->fetchAll();

// Fetch booking statistics
$stats_stmt = $db->prepare("SELECT 
                            COUNT(DISTINCT b.id) as total_bookings,
                            SUM(b.grand_total) as total_revenue,
                            COUNT(DISTINCT CASE WHEN b.booking_status = 'confirmed' THEN b.id END) as confirmed_bookings
                            FROM bookings b
                            INNER JOIN halls h ON b.hall_id = h.id
                            WHERE h.venue_id = ?");
$stats_stmt->execute([$venue_id]);
$stats = $stats_stmt->fetch();

// Fetch venue images
try {
    $vim_stmt = $db->prepare("SELECT * FROM venue_images WHERE venue_id = ? ORDER BY is_primary DESC, display_order ASC");
    $vim_stmt->execute([$venue_id]);
    $venue_imgs = $vim_stmt->fetchAll();
} catch (Exception $e) {
    $venue_imgs = [];
}

// Fetch bookings for this venue (via halls), sorted by event_date DESC
$venue_bookings = [];
$venue_total_payable = 0.0;
$venue_total_paid    = 0.0;
$venue_total_due     = 0.0;
try {
    $vb_stmt = $db->prepare("
        SELECT b.id, b.booking_number, b.customer_name, b.event_date, b.event_type,
               b.booking_status, b.grand_total,
               COALESCE(h.name, b.custom_hall_name) AS hall_name,
               COALESCE(b.hall_price, 0) + COALESCE(b.menu_total, 0) AS venue_payable,
               COALESCE(b.venue_amount_paid, 0) AS venue_amount_paid
        FROM bookings b
        JOIN halls h ON b.hall_id = h.id
        WHERE h.venue_id = ? AND b.booking_status != 'cancelled'
        ORDER BY b.event_date DESC, b.id DESC
    ");
    $vb_stmt->execute([$venue_id]);
    $venue_bookings = $vb_stmt->fetchAll();
    foreach ($venue_bookings as $vb) {
        $payable = (float)$vb['venue_payable'];
        $paid    = (float)$vb['venue_amount_paid'];
        $due     = max(0.0, $payable - $paid);
        $venue_total_payable += $payable;
        $venue_total_paid    += $paid;
        $venue_total_due     += $due;
    }
} catch (Exception $e) {
    error_log("Error fetching venue bookings: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-building"></i> <?php echo htmlspecialchars($venue['name']); ?></h4>
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $venue_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Venue
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Venue Details -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Venue Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($venue['name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>City:</strong><br>
                        <?php echo $venue['city_name'] ? htmlspecialchars($venue['city_name']) : '<em class="text-muted">Not assigned</em>'; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Full Address:</strong><br>
                    <?php echo htmlspecialchars($venue['address']) ?: '<em class="text-muted">Not provided</em>'; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Contact Phone:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($venue['contact_phone']); ?>">
                            <?php echo htmlspecialchars($venue['contact_phone']); ?>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Contact Email:</strong><br>
                        <?php if ($venue['contact_email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($venue['contact_email']); ?>">
                                <?php echo htmlspecialchars($venue['contact_email']); ?>
                            </a>
                        <?php else: ?>
                            <em class="text-muted">Not provided</em>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($venue['map_link'])): ?>
                <div class="mb-3">
                    <strong><i class="fas fa-map-marker-alt"></i> Google Map:</strong><br>
                    <a href="<?php echo htmlspecialchars($venue['map_link']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success mt-1">
                        <i class="fas fa-map"></i> View on Google Maps
                    </a>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?php echo $venue['description'] ? nl2br(htmlspecialchars($venue['description'])) : '<em class="text-muted">No description available</em>'; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $venue['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($venue['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Created:</strong><br>
                        <?php echo date('M d, Y', strtotime($venue['created_at'])); ?>
                        <br><small class="text-muted"><?php echo convertToNepaliDate($venue['created_at']); ?></small>
                    </div>
                </div>

                <?php if (!empty($venue_imgs)): ?>
                <div class="mb-3">
                    <strong><i class="fas fa-images"></i> Photos:</strong>
                    <?php
                    $vc_id = 'venueViewCarousel' . $venue_id;
                    ?>
                    <div id="<?php echo $vc_id; ?>" class="carousel slide mt-2" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($venue_imgs as $vi_idx => $vi): ?>
                                <?php
                                $vi_url  = UPLOAD_URL . rawurlencode($vi['image_path']);
                                $vi_file = UPLOAD_PATH . $vi['image_path'];
                                if (!file_exists($vi_file)) continue;
                                ?>
                                <div class="carousel-item <?php echo $vi_idx === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars($vi_url, ENT_QUOTES, 'UTF-8'); ?>" class="d-block w-100 rounded" alt="Venue photo" style="height:260px;object-fit:cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($venue_imgs) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $vc_id; ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $vc_id; ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Halls -->
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-door-open"></i> Halls (<?php echo count($halls); ?>)</h5>
                <a href="../halls/add.php?venue_id=<?php echo $venue_id; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add Hall
                </a>
            </div>
            <div class="card-body">
                <?php if (count($halls) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Hall Name</th>
                                    <th>Capacity</th>
                                    <th>Type</th>
                                    <th>Base Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($halls as $hall): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($hall['name']); ?></td>
                                        <td><?php echo $hall['capacity']; ?> pax</td>
                                        <td><?php echo ucfirst($hall['indoor_outdoor']); ?></td>
                                        <td><?php echo formatCurrency($hall['base_price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $hall['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($hall['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../halls/view.php?id=<?php echo $hall['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../halls/edit.php?id=<?php echo $hall['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No halls found for this venue.
                        <a href="../halls/add.php?venue_id=<?php echo $venue_id; ?>" class="alert-link">Add one now</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bookings by Date with Due Amounts -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Bookings &amp; Due Amounts (गते अनुसार)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($venue_bookings)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                        No bookings found for this venue.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking #</th>
                                    <th>Customer</th>
                                    <th>Event Date</th>
                                    <th>Hall</th>
                                    <th>Event Type</th>
                                    <th>Status</th>
                                    <th class="text-end">Venue Payable</th>
                                    <th class="text-end">Venue Paid</th>
                                    <th class="text-end">Venue Due (बाँकी)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($venue_bookings as $vb):
                                    $vb_payable = (float)$vb['venue_payable'];
                                    $vb_paid    = (float)$vb['venue_amount_paid'];
                                    $vb_due     = max(0.0, $vb_payable - $vb_paid);
                                ?>
                                <tr>
                                    <td>
                                        <a href="../bookings/view.php?id=<?php echo $vb['id']; ?>" class="text-decoration-none fw-semibold">
                                            <?php echo htmlspecialchars($vb['booking_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($vb['customer_name'] ?? '—'); ?></td>
                                    <td data-sort="<?php echo htmlspecialchars($vb['event_date'] ?? ''); ?>">
                                        <?php echo !empty($vb['event_date']) ? date('d M Y', strtotime($vb['event_date'])) : '—'; ?>
                                        <?php if (!empty($vb['event_date'])): ?>
                                            <br><small class="text-muted"><?php echo convertToNepaliDate($vb['event_date']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($vb['hall_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($vb['event_type'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                        $bs_color = [
                                            'pending'           => 'secondary',
                                            'payment_submitted' => 'info',
                                            'confirmed'         => 'success',
                                            'completed'         => 'primary',
                                        ][$vb['booking_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $bs_color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $vb['booking_status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo formatCurrency($vb_payable); ?></td>
                                    <td class="text-end text-primary"><?php echo formatCurrency($vb_paid); ?></td>
                                    <td class="text-end fw-semibold <?php echo $vb_due > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php if ($vb_due > 0): ?>
                                            <?php echo formatCurrency($vb_due); ?>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i> Cleared
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="6" class="text-end">Total:</th>
                                    <th class="text-end"><?php echo formatCurrency($venue_total_payable); ?></th>
                                    <th class="text-end text-primary"><?php echo formatCurrency($venue_total_paid); ?></th>
                                    <th class="text-end text-danger"><?php echo formatCurrency($venue_total_due); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Sidebar -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Total Halls</h6>
                    <h3 class="mb-0"><?php echo count($halls); ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Total Bookings</h6>
                    <h3 class="mb-0"><?php echo $stats['total_bookings'] ?? 0; ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Confirmed Bookings</h6>
                    <h3 class="mb-0"><?php echo $stats['confirmed_bookings'] ?? 0; ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Total Revenue</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Venue Payable <small class="text-muted">(Hall + Menu)</small></h6>
                    <h4 class="mb-0"><?php echo formatCurrency($venue_total_payable); ?></h4>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Venue Paid Out</h6>
                    <h4 class="mb-0 text-primary"><?php echo formatCurrency($venue_total_paid); ?></h4>
                </div>
                <hr>
                <div>
                    <h6 class="text-muted mb-1">Venue Due (बाँकी)</h6>
                    <h4 class="mb-0 <?php echo $venue_total_due > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo formatCurrency($venue_total_due); ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="edit.php?id=<?php echo $venue_id; ?>" class="btn btn-warning btn-block w-100 mb-2">
                    <i class="fas fa-edit"></i> Edit Venue
                </a>
                <a href="../halls/add.php?venue_id=<?php echo $venue_id; ?>" class="btn btn-success btn-block w-100 mb-2">
                    <i class="fas fa-plus"></i> Add Hall
                </a>
                <a href="../bookings/index.php" class="btn btn-info btn-block w-100">
                    <i class="fas fa-calendar"></i> View Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
