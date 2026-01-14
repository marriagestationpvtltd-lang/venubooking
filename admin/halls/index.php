<?php
$page_title = 'Manage Halls';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT h.*, v.name as venue_name FROM halls h INNER JOIN venues v ON h.venue_id = v.id ORDER BY v.name, h.name");
$halls = $stmt->fetchAll();

$success_message = '';
if (isset($_GET['deleted'])) {
    $success_message = 'Hall deleted successfully!';
}
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-door-open"></i> All Halls</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Hall</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hall Name</th>
                        <th>Venue</th>
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
                            <td><?php echo $hall['id']; ?></td>
                            <td><?php echo htmlspecialchars($hall['name']); ?></td>
                            <td><?php echo htmlspecialchars($hall['venue_name']); ?></td>
                            <td><?php echo $hall['capacity']; ?> pax</td>
                            <td><?php echo ucfirst($hall['indoor_outdoor']); ?></td>
                            <td><?php echo formatCurrency($hall['base_price']); ?></td>
                            <td><span class="badge bg-<?php echo $hall['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($hall['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $hall['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $hall['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="edit.php?id=<?php echo $hall['id']; ?>&action=delete" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this hall? This action cannot be undone.');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
