<?php
$page_title = 'Manage Cities';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM cities ORDER BY name");
$cities = $stmt->fetchAll();

// Fetch venue counts for all cities in one query
$vc_stmt = $db->query("SELECT city_id, COUNT(*) as cnt FROM venues WHERE city_id IS NOT NULL GROUP BY city_id");
$venue_counts_raw = $vc_stmt->fetchAll();
$venue_counts = [];
foreach ($venue_counts_raw as $row) {
    $venue_counts[$row['city_id']] = $row['cnt'];
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

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
        <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> All Cities</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add City</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>City Name</th>
                        <th>Venues</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cities as $city): ?>
                        <?php
                        $venue_count = isset($venue_counts[$city['id']]) ? $venue_counts[$city['id']] : 0;
                        ?>
                        <tr>
                            <td><?php echo $city['id']; ?></td>
                            <td><?php echo htmlspecialchars($city['name']); ?></td>
                            <td><?php echo $venue_count; ?></td>
                            <td><span class="badge bg-<?php echo $city['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($city['status']); ?></span></td>
                            <td>
                                <a href="edit.php?id=<?php echo $city['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this city?');">
                                    <input type="hidden" name="id" value="<?php echo $city['id']; ?>">
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
