<?php
$page_title = 'Manage Menus';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM menus ORDER BY price_per_person DESC");
$menus = $stmt->fetchAll();

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
        <h5 class="mb-0"><i class="fas fa-utensils"></i> All Menus</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Menu</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Menu Name</th>
                        <th>Price per Person</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menus as $menu): ?>
                        <tr>
                            <td><?php echo $menu['id']; ?></td>
                            <td><?php echo htmlspecialchars($menu['name']); ?></td>
                            <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                            <td><span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($menu['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="items.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-primary" title="Menu Items"><i class="fas fa-list"></i></a>
                                <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu? This will also delete all menu items. This action cannot be undone.');">
                                    <input type="hidden" name="id" value="<?php echo $menu['id']; ?>">
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
