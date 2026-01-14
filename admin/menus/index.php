<?php
$page_title = 'Manage Menus';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM menus ORDER BY price_per_person DESC");
$menus = $stmt->fetchAll();
?>

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
                                <a href="view.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="items.php?id=<?php echo $menu['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-list"></i> Items</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
