<?php
/**
 * Admin - Add Venue
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$db = getDB();
$pageTitle = 'Add New Venue';

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
        
        // Handle image upload
        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadImage($_FILES['image'], UPLOAD_PATH_VENUES);
            if ($upload_result['success']) {
                $image_name = $upload_result['filename'];
            } else {
                setFlashMessage('error', $upload_result['message']);
            }
        }
        
        // Insert venue
        $sql = "INSERT INTO venues (venue_name, location, address, description, image, status, created_at, updated_at) 
                VALUES (:venue_name, :location, :address, :description, :image, :status, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':venue_name', $venue_name);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':image', $image_name);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['admin_id'], 'create', 'venues', $db->lastInsertId(), null, [
                'venue_name' => $venue_name,
                'location' => $location
            ]);
            setFlashMessage('success', 'Venue added successfully');
            redirect('/admin/venues/list.php');
        } else {
            setFlashMessage('error', 'Failed to add venue');
        }
    }
}

$csrfToken = generateCSRFToken();
include __DIR__ . '/../../includes/admin-header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Add New Venue</h1>
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
                            <input type="text" class="form-control" id="venue_name" name="venue_name" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   placeholder="e.g., Kathmandu, Lalitpur" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Full Address *</label>
                    <input type="text" class="form-control" id="address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="image">Venue Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="form-text text-muted">Max file size: 5MB. Allowed: JPG, PNG, GIF</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Venue
                    </button>
                    <a href="/admin/venues/list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
