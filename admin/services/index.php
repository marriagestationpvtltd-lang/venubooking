<?php
$page_title = 'Manage Services';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query(
    "SELECT s.*,
            COUNT(DISTINCT ss.id) AS sub_service_count,
            COUNT(DISTINCT sd.id) AS design_count
     FROM additional_services s
     LEFT JOIN service_sub_services ss ON ss.service_id = s.id AND ss.status = 'active'
     LEFT JOIN service_designs sd ON sd.sub_service_id = ss.id AND sd.status = 'active'
     GROUP BY s.id
     ORDER BY s.category, s.name"
);
$services = $stmt->fetchAll();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> All Services</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Service</a>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <strong><i class="fas fa-info-circle"></i> Visual Design Selection Flow</strong><br>
            Each service can offer a <strong>visual photo-based design selection</strong> to customers during booking.
            To enable it, open a service (click <i class="fas fa-eye"></i>) and add <strong>Sub-Services</strong>
            (e.g. <em>Mandap</em>, <em>Stage</em>) and design photos with prices under each sub-service.
            Services without sub-services show as a simple checkbox.
        </div>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Design Flow</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo $service['id']; ?></td>
                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                            <td><?php echo htmlspecialchars($service['category']); ?></td>
                            <td><?php echo formatCurrency($service['price']); ?></td>
                            <td>
                                <?php if ($service['sub_service_count'] > 0): ?>
                                    <span class="badge bg-success" title="<?php echo $service['sub_service_count']; ?> sub-service(s) with <?php echo $service['design_count']; ?> design(s)">
                                        <i class="fas fa-images"></i>
                                        Visual (<?php echo $service['sub_service_count']; ?> sub-services / <?php echo $service['design_count']; ?> designs)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-check-square"></i> Checkbox only
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-info" title="View / Manage Sub-Services"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                                    <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
