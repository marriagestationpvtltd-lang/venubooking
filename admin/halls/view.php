<?php
$page_title = 'View Hall Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get hall ID from URL
$hall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hall_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch hall details with venue information
$stmt = $db->prepare("SELECT h.*, v.name as venue_name, v.location, v.address 
                      FROM halls h 
                      INNER JOIN venues v ON h.venue_id = v.id 
                      WHERE h.id = ?");
$stmt->execute([$hall_id]);
$hall = $stmt->fetch();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Fetch linked menus
$menus_stmt = $db->prepare("SELECT m.* FROM menus m 
                            INNER JOIN hall_menus hm ON m.id = hm.menu_id 
                            WHERE hm.hall_id = ? 
                            ORDER BY m.name");
$menus_stmt->execute([$hall_id]);
$linked_menus = $menus_stmt->fetchAll();

// Fetch hall images
$images_stmt = $db->prepare("SELECT * FROM hall_images WHERE hall_id = ? ORDER BY display_order");
$images_stmt->execute([$hall_id]);
$images = $images_stmt->fetchAll();

// Fetch recent bookings for this hall
$bookings_stmt = $db->prepare("SELECT b.*, c.full_name, c.phone 
                               FROM bookings b 
                               INNER JOIN customers c ON b.customer_id = c.id 
                               WHERE b.hall_id = ? 
                               ORDER BY b.event_date DESC 
                               LIMIT 10");
$bookings_stmt->execute([$hall_id]);
$recent_bookings = $bookings_stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($hall['name']); ?></h4>
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $hall_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Hall
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Hall Details -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Hall Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Venue:</strong><br>
                        <?php echo htmlspecialchars($hall['venue_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($hall['location']); ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Capacity:</strong><br>
                        <?php echo $hall['capacity']; ?> guests
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Type:</strong><br>
                        <span class="badge bg-info"><?php echo ucfirst($hall['hall_type']); ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Indoor/Outdoor:</strong><br>
                        <span class="badge bg-primary"><?php echo ucfirst($hall['indoor_outdoor']); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Base Price:</strong><br>
                        <h4 class="text-success mb-0"><?php echo formatCurrency($hall['base_price']); ?></h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $hall['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($hall['status']); ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($hall['description'])): ?>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <strong>Description:</strong><br>
                        <p><?php echo nl2br(htmlspecialchars($hall['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($hall['features'])): ?>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <strong>Features:</strong><br>
                        <?php 
                        $features = explode(',', $hall['features']);
                        foreach ($features as $feature): 
                        ?>
                            <span class="badge bg-secondary me-1 mb-1"><?php echo trim(htmlspecialchars($feature)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($hall['created_at'])); ?></small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">Updated: <?php echo date('M d, Y', strtotime($hall['updated_at'])); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hall Images -->
        <?php if (count($images) > 0): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-images"></i> Hall Images</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($images as $image): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center p-3">
                                    <i class="fas fa-image fa-4x text-muted mb-2"></i>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($image['image_path']); ?></p>
                                    <?php if ($image['is_primary']): ?>
                                        <span class="badge bg-primary">Primary Image</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Linked Menus -->
        <?php if (count($linked_menus) > 0): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Available Menus</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Menu Name</th>
                                <th>Price per Person</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_menus as $menu): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($menu['name']); ?></td>
                                    <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($menu['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/menus/view.php?id=<?php echo $menu['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Available Menus</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">No menus linked to this hall yet.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Quick Stats -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Quick Stats</h5>
            </div>
            <div class="card-body">
                <?php
                $stats_stmt = $db->prepare("SELECT 
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed,
                    COUNT(CASE WHEN event_date >= CURDATE() THEN 1 END) as upcoming
                    FROM bookings WHERE hall_id = ?");
                $stats_stmt->execute([$hall_id]);
                $stats = $stats_stmt->fetch();
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Bookings:</span>
                        <strong><?php echo $stats['total_bookings']; ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Confirmed:</span>
                        <strong class="text-success"><?php echo $stats['confirmed']; ?></strong>
                    </div>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between">
                        <span>Upcoming Events:</span>
                        <strong class="text-info"><?php echo $stats['upcoming']; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Recent Bookings</h5>
            </div>
            <div class="card-body">
                <?php if (count($recent_bookings) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($booking['event_date'])); ?><br>
                                            <?php echo ucfirst($booking['shift']); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                            ($booking['booking_status'] == 'pending' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="btn btn-sm btn-outline-primary">
                            View All Bookings
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No bookings yet for this hall.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
