<?php
/**
 * Admin - Edit Venue
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$db = getDB();
$pageTitle = 'Edit Venue';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect('/admin/venues/list.php');
}

// Get venue
$stmt = $db->prepare("SELECT * FROM venues WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$venue = $stmt->fetch();

if (!$venue) {
    setFlashMessage('error', 'Venue not found');
    redirect('/admin/venues/list.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
    } else {
        $venue_name = sanitizeInput($_POST['venue_name']);
        $location = sanitizeInput($_POST['location']);
        $address = sanitizeInput($_POST['address']);
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);
        $image_name = $venue['image'];
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadImage($_FILES['image'], UPLOAD_PATH_VENUES);
            if ($upload_result['success']) {
                // Delete old image
                if ($venue['image'] && file_exists(BASE_PATH . '/' . UPLOAD_PATH_VENUES . $venue['image'])) {
                    unlink(BASE_PATH . '/' . UPLOAD_PATH_VENUES . $venue['image']);
                }
                $image_name = $upload_result['filename'];
            }
        }
        
        // Update venue
        $sql = "UPDATE venues SET venue_name = :venue_name, location = :location, address = :address, 
                description = :description, image = :image, status = :status, updated_at = NOW() 
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':venue_name', $venue_name);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':image', $image_name);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['admin_id'], 'update', 'venues', $id, $venue, [
                'venue_name' => $venue_name,
                'location' => $location,
                'status' => $status
            ]);
            setFlashMessage('success', 'Venue updated successfully');
            redirect('/admin/venues/list.php');
        } else {
            setFlashMessage('error', 'Failed to update venue');
        }
    }
}

$csrfToken = generateCSRFToken();
include __DIR__ . '/../../includes/admin-header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Edit Venue</h1>
        <a href="/admin/venues/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="venue_name">Venue Name *</label>
                            <input type="text" class="form-control" id="venue_name" name="venue_name" 
                                   value="<?php echo clean($venue['venue_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo clean($venue['location']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Full Address *</label>
                    <input type="text" class="form-control" id="address" name="address" 
                           value="<?php echo clean($venue['address']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo clean($venue['description']); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="image">Venue Image</label>
                            <?php if ($venue['image']): ?>
                                <div class="mb-2">
                                    <img src="/<?php echo UPLOAD_PATH_VENUES . $venue['image']; ?>" 
                                         alt="Current image" style="max-width: 200px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="form-text text-muted">Leave empty to keep current image</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active" <?php echo $venue['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $venue['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Venue
                    </button>
                    <a href="/admin/venues/list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
