<?php
$page_title = 'Manage Venues';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM venues ORDER BY name");
$venues = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-building"></i> All Venues</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Venue</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($venues as $venue): ?>
                        <tr>
                            <td><?php echo $venue['id']; ?></td>
                            <td><?php echo htmlspecialchars($venue['name']); ?></td>
                            <td><?php echo htmlspecialchars($venue['location']); ?></td>
                            <td><?php echo htmlspecialchars($venue['contact_phone']); ?></td>
                            <td><span class="badge bg-<?php echo $venue['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($venue['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $venue['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $venue['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
