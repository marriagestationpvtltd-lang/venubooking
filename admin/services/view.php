<?php
$page_title = 'View Service Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get service ID from URL
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($service_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch service details
$stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: index.php');
    exit;
}

// Fetch usage statistics
$stats_stmt = $db->prepare("SELECT 
                            COUNT(DISTINCT bs.booking_id) as times_booked,
                            SUM(bs.price) as total_revenue
                            FROM booking_services bs
                            WHERE bs.service_id = ?");
$stats_stmt->execute([$service_id]);
$stats = $stats_stmt->fetch();

// Fetch recent bookings with this service
$bookings_stmt = $db->prepare("SELECT b.*, c.full_name, bs.price as service_price
                                FROM booking_services bs
                                INNER JOIN bookings b ON bs.booking_id = b.id
                                INNER JOIN customers c ON b.customer_id = c.id
                                WHERE bs.service_id = ?
                                ORDER BY b.event_date DESC
                                LIMIT 10");
$bookings_stmt->execute([$service_id]);
$recent_bookings = $bookings_stmt->fetchAll();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-concierge-bell"></i> <?php echo htmlspecialchars($service['name']); ?></h4>
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="edit.php?id=<?php echo $service_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Service
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Service Details -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Service Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Service Name:</strong><br>
                        <?php echo htmlspecialchars($service['name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Category:</strong><br>
                        <?php echo $service['category'] ? htmlspecialchars($service['category']) : '<em class="text-muted">Not categorized</em>'; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Price:</strong><br>
                        <?php
                        // Check if this service has direct designs (price managed per design)
                        $has_direct_designs = !empty(getServiceDesigns($service_id));
                        if ($has_direct_designs): ?>
                            <span class="text-muted small">
                                <i class="fas fa-info-circle"></i>
                                Price is managed per design in Visual Design Flow mode.
                            </span>
                        <?php else: ?>
                            <h4 class="text-success"><?php echo formatCurrency($service['price']); ?></h4>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?> fs-6">
                            <?php echo ucfirst($service['status']); ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($service['photo'])): ?>
                <div class="mb-3">
                    <strong>Service Photo:</strong><br>
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                         alt="<?php echo htmlspecialchars($service['name']); ?>"
                         class="img-thumbnail mt-1" style="max-height:200px;">
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?php echo $service['description'] ? nl2br(htmlspecialchars($service['description'])) : '<em class="text-muted">No description available</em>'; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Created:</strong><br>
                        <?php echo date('M d, Y', strtotime($service['created_at'])); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Last Updated:</strong><br>
                        <?php echo date('M d, Y', strtotime($service['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Bookings (<?php echo count($recent_bookings); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($recent_bookings) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Customer</th>
                                    <th>Event Date</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></td>
                                        <td><?php echo formatCurrency($booking['service_price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                                    ($booking['booking_status'] == 'pending' ? 'warning' : 
                                                    ($booking['booking_status'] == 'cancelled' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../bookings/view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> This service has not been used in any bookings yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Designs -->
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 d-inline">
                        <i class="fas fa-images"></i> Designs
                    </h5>
                    <?php
                    $direct_designs = getServiceDesigns($service_id);
                    if (!empty($direct_designs)): ?>
                        <span class="badge bg-success ms-2">
                            <i class="fas fa-check-circle"></i> Visual Design Flow Active
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">
                            <i class="fas fa-check-square"></i> Checkbox Mode
                        </span>
                    <?php endif; ?>
                </div>
                <a href="service-design-add.php?service_id=<?php echo $service_id; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Add Design
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-3">
                    Add design photos with prices. Customers will see a photo gallery and select one design when booking this service.
                </p>

                <?php if (empty($direct_designs)): ?>
                    <div class="alert alert-warning mb-3">
                        <strong><i class="fas fa-exclamation-triangle"></i> No Designs Yet</strong><br>
                        This service currently shows as a <strong>plain checkbox</strong> to customers during booking.<br>
                        Click <strong>"Add Design"</strong> above to add design photos (e.g. Royal Mandap, Classic Stage).<br>
                        Once at least one design exists, customers will see a photo gallery to choose from.
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($direct_designs as $design): ?>
                            <div class="col-6 col-md-3">
                                <div class="card border h-100">
                                    <?php if (!empty($design['photo'])): ?>
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($design['photo']); ?>"
                                             alt="<?php echo htmlspecialchars($design['name']); ?>"
                                             class="card-img-top" style="height:100px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:100px;">
                                            <i class="fas fa-image fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body p-2">
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($design['name']); ?></div>
                                        <div class="text-success small"><?php echo formatCurrency($design['price']); ?></div>
                                        <span class="badge bg-<?php echo $design['status'] === 'active' ? 'success' : 'secondary'; ?> small">
                                            <?php echo ucfirst($design['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-footer p-1 d-flex justify-content-end gap-1">
                                        <a href="service-design-edit.php?id=<?php echo $design['id']; ?>" class="btn btn-xs btn-warning py-0 px-1">
                                            <i class="fas fa-edit fa-xs"></i>
                                        </a>
                                        <a href="service-design-delete.php?id=<?php echo $design['id']; ?>"
                                           class="btn btn-xs btn-danger py-0 px-1"
                                           onclick="return confirm('Delete this design?');">
                                            <i class="fas fa-trash fa-xs"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <h6 class="text-muted mb-1">Times Booked</h6>
                    <h3 class="mb-0"><?php echo $stats['times_booked'] ?? 0; ?></h3>
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
                <a href="edit.php?id=<?php echo $service_id; ?>" class="btn btn-warning btn-block w-100 mb-2">
                    <i class="fas fa-edit"></i> Edit Service
                </a>
                <a href="add.php" class="btn btn-success btn-block w-100 mb-2">
                    <i class="fas fa-plus"></i> Add New Service
                </a>
                <a href="../bookings/index.php" class="btn btn-info btn-block w-100">
                    <i class="fas fa-calendar"></i> View Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
