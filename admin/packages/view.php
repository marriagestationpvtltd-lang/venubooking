<?php
$page_title = 'View Package Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($package_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT sp.*, sc.name as category_name
     FROM service_packages sp
     LEFT JOIN service_categories sc ON sc.id = sp.category_id
     WHERE sp.id = ?"
);
$stmt->execute([$package_id]);
$package = $stmt->fetch();

if (!$package) {
    header('Location: index.php');
    exit;
}

$feat_stmt = $db->prepare(
    "SELECT feature_text FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
);
$feat_stmt->execute([$package_id]);
$features = $feat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-3">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h4><i class="fas fa-box"></i> <?php echo htmlspecialchars($package['name']); ?></h4>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="edit.php?id=<?php echo $package_id; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Package Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Package Name:</strong><br>
                        <?php echo htmlspecialchars($package['name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Service Category:</strong><br>
                        <?php echo htmlspecialchars($package['category_name'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Price:</strong><br>
                        <h4 class="text-success"><?php echo formatCurrency($package['price']); ?></h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $package['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                            <?php echo ucfirst($package['status']); ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($package['description'])): ?>
                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?php echo nl2br(htmlspecialchars($package['description'])); ?>
                </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Display Order:</strong><br>
                        <?php echo (int)$package['display_order']; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Created:</strong><br>
                        <?php echo date('M d, Y', strtotime($package['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-list-ul"></i> Package Features (<?php echo count($features); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($features)): ?>
                    <p class="text-muted mb-0"><em>No features added yet.</em></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($features as $feat): ?>
                            <li class="mb-2">
                                <span class="text-success me-2"><i class="fas fa-check-circle"></i></span>
                                <?php echo htmlspecialchars($feat); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="edit.php?id=<?php echo $package_id; ?>" class="btn btn-warning w-100 mb-2">
                    <i class="fas fa-edit"></i> Edit Package
                </a>
                <a href="add.php?category_id=<?php echo (int)$package['category_id']; ?>" class="btn btn-success w-100 mb-2">
                    <i class="fas fa-plus"></i> Add New Package
                </a>
                <a href="index.php" class="btn btn-secondary w-100">
                    <i class="fas fa-list"></i> All Packages
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
