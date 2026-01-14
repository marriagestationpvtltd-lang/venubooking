<?php
$page_title = 'Manage Images';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get image path before deletion
    $stmt = $db->prepare("SELECT image_path FROM site_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM site_images WHERE id = ?");
        if ($delete_stmt->execute([$id])) {
            // Delete physical file
            $file_path = UPLOAD_PATH . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            logActivity($current_user['id'], 'Deleted image', 'site_images', $id, "Deleted image: " . $image['image_path']);
            $_SESSION['success_message'] = 'Image deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete image.';
        }
    }
    
    // Redirect to remove query parameter
    header('Location: index.php');
    exit;
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch all images
$stmt = $db->query("SELECT * FROM site_images ORDER BY section, display_order, created_at DESC");
$images = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-images"></i> Image Management</h4>
    <a href="add.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Upload New Image
    </a>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($images)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No images uploaded yet.</p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Upload Your First Image
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="imagesTable">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Section</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($images as $image): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $image_url = UPLOAD_URL . $image['image_path'];
                                    if (file_exists(UPLOAD_PATH . $image['image_path'])): 
                                    ?>
                                        <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; border-radius: 4px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($image['title']); ?></strong>
                                    <?php if ($image['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($image['description'], 0, 50)); ?><?php echo strlen($image['description']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($image['section']); ?></span>
                                </td>
                                <td><?php echo $image['display_order']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $image['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($image['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($image['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?php echo $image['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view.php?id=<?php echo $image['id']; ?>" class="btn btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?delete=<?php echo $image['id']; ?>" class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this image? This action cannot be undone.');" 
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$extra_js = <<<'JS'
<script>
$(document).ready(function() {
    $('#imagesTable').DataTable({
        "order": [[5, "desc"]],
        "pageLength": 25
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/footer.php'; 
?>
