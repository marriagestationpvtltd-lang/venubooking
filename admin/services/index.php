<?php
$page_title = 'Manage Services';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM additional_services ORDER BY category, name");
$services = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> All Services</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Service</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Price</th>
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
                            <td><span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                            <td>
                                <a href="edit.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
