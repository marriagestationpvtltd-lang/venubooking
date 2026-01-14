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
$stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
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
                        <strong>Location:</strong><br>
                        <?php echo htmlspecialchars($venue['location']); ?>
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Halls -->
        <div class="card">
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
                <div>
                    <h6 class="text-muted mb-1">Total Revenue</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
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
