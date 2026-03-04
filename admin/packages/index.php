<?php
$page_title = 'Manage Service Packages';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all categories with their packages
$stmt = $db->query(
    "SELECT sc.id as cat_id, sc.name as cat_name, sc.status as cat_status,
            sp.id, sp.name, sp.price, sp.status, sp.display_order
     FROM service_categories sc
     LEFT JOIN service_packages sp ON sp.category_id = sc.id
     ORDER BY sc.display_order, sc.name, sp.display_order, sp.name"
);
$rows = $stmt->fetchAll();

// Group by category
$categories = [];
foreach ($rows as $row) {
    $cid = $row['cat_id'];
    if (!isset($categories[$cid])) {
        $categories[$cid] = [
            'id'     => $cid,
            'name'   => $row['cat_name'],
            'status' => $row['cat_status'],
            'packages' => [],
        ];
    }
    if ($row['id']) {
        $categories[$cid]['packages'][] = $row;
    }
}
?>

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

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <div>
        <a href="categories.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-list"></i> Manage Categories
        </a>
        <a href="add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Package
        </a>
    </div>
</div>

<?php if (empty($categories)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No categories found</h5>
            <p class="text-muted">Start by adding service categories, then add packages under them.</p>
            <a href="categories.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Category
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($categories as $cat): ?>
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-layer-group"></i>
                    <?php echo htmlspecialchars($cat['name']); ?>
                    <span class="badge bg-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?> ms-2">
                        <?php echo ucfirst($cat['status']); ?>
                    </span>
                </h5>
                <a href="add.php?category_id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add Package
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($cat['packages'])): ?>
                    <p class="text-muted mb-0"><em>No packages yet for this category.</em></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Package Name</th>
                                    <th>Price</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat['packages'] as $pkg): ?>
                                    <tr>
                                        <td><?php echo (int)$pkg['id']; ?></td>
                                        <td><?php echo htmlspecialchars($pkg['name']); ?></td>
                                        <td><?php echo formatCurrency($pkg['price']); ?></td>
                                        <td><?php echo (int)$pkg['display_order']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $pkg['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($pkg['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo (int)$pkg['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo (int)$pkg['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="delete.php" style="display:inline;"
                                                  onsubmit="return confirm('Delete this package and all its features?');">
                                                <input type="hidden" name="id" value="<?php echo (int)$pkg['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
