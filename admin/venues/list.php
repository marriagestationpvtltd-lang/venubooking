<?php
/**
 * Admin - List Venues
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$db = getDB();
$pageTitle = 'Manage Venues';

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (verifyCSRFToken($_GET['csrf_token'])) {
        $id = (int)$_GET['delete'];
        $stmt = $db->prepare("DELETE FROM venues WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            setFlashMessage('success', 'Venue deleted successfully');
        } else {
            setFlashMessage('error', 'Failed to delete venue');
        }
    }
    redirect('/admin/venues/list.php');
}

// Get all venues
$stmt = $db->query("SELECT v.*, 
                    (SELECT COUNT(*) FROM halls WHERE venue_id = v.id) as halls_count 
                    FROM venues v 
                    ORDER BY v.created_at DESC");
$venues = $stmt->fetchAll();

include __DIR__ . '/../../includes/admin-header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Manage Venues</h1>
        <a href="/admin/venues/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Venue
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card">
        <div class="card-body">
            <table id="venuesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Venue Name</th>
                        <th>Location</th>
                        <th>Halls</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($venues as $venue): ?>
                    <tr>
                        <td><?php echo $venue['id']; ?></td>
                        <td>
                            <?php if ($venue['image']): ?>
                                <img src="/<?php echo UPLOAD_PATH_VENUES . $venue['image']; ?>" 
                                     alt="<?php echo clean($venue['venue_name']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <i class="fas fa-building" style="font-size: 2rem; color: #ccc;"></i>
                            <?php endif; ?>
                        </td>
                        <td><?php echo clean($venue['venue_name']); ?></td>
                        <td><?php echo clean($venue['location']); ?></td>
                        <td><?php echo $venue['halls_count']; ?> halls</td>
                        <td>
                            <span class="badge badge-<?php echo $venue['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($venue['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($venue['created_at'])); ?></td>
                        <td>
                            <a href="/admin/venues/edit.php?id=<?php echo $venue['id']; ?>" 
                               class="btn btn-sm btn-info" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="/admin/venues/list.php?delete=<?php echo $venue['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this venue?');"
                               title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#venuesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
