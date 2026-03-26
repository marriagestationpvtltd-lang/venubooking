<?php
$page_title = 'Manage Services';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query(
    "SELECT s.*,
            COALESCE(vt.label, s.category) AS vendor_type_label,
            COUNT(DISTINCT sd_direct.id) AS direct_design_count
     FROM additional_services s
     LEFT JOIN vendor_types vt ON vt.id = s.vendor_type_id
     LEFT JOIN service_designs sd_direct ON sd_direct.service_id = s.id AND sd_direct.status = 'active'
     GROUP BY s.id
     ORDER BY COALESCE(vt.display_order, 9999), vendor_type_label, s.name"
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
            To enable it, open a service (click <i class="fas fa-eye"></i>) and add <strong>Design photos</strong>
            with names and prices. Services without designs show as a simple checkbox.
        </div>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
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
                            <td>
                                <?php if (!empty($service['photo'])): ?>
                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($service['photo']); ?>"
                                         alt="<?php echo htmlspecialchars($service['name']); ?>"
                                         class="svc-icon-thumb">
                                <?php else: ?>
                                    <div class="svc-icon-thumb svc-icon-thumb--fallback">
                                        <i class="fas fa-concierge-bell"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                            <td><?php echo htmlspecialchars($service['vendor_type_label']); ?></td>
                            <td><?php echo formatCurrency($service['price']); ?></td>
                            <td>
                                <?php if ($service['direct_design_count'] > 0): ?>
                                    <span class="badge bg-success" title="<?php echo $service['direct_design_count']; ?> design(s)">
                                        <i class="fas fa-images"></i>
                                        Visual (<?php echo $service['direct_design_count']; ?> designs)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-check-square"></i> Checkbox only
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-info" title="View / Manage Designs"><i class="fas fa-eye"></i></a>
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

<style>
.svc-icon-thumb {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #4caf50;
    display: block;
    transition: transform 0.2s ease;
}
.svc-icon-thumb--fallback {
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2e7d32;
    font-size: 1.1rem;
}
.svc-icon-thumb:hover {
    transform: scale(1.25);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
