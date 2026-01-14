<?php
$page_title = 'Edit Venue';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get venue ID from URL
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch venue details
$stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();

if (!$venue) {
    header('Location: index.php');
    exit;
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        // Check if venue has halls
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM halls WHERE venue_id = ?");
        $check_stmt->execute([$venue_id]);
        $result = $check_stmt->fetch();
        
        if ($result['count'] > 0) {
            $error_message = 'Cannot delete venue. It has ' . $result['count'] . ' associated hall(s). Please delete the halls first.';
        } else {
            $stmt = $db->prepare("DELETE FROM venues WHERE id = ?");
            if ($stmt->execute([$venue_id])) {
                // Log activity
                logActivity($current_user['id'], 'Deleted venue', 'venues', $venue_id, "Deleted venue: {$venue['name']}");
                
                header('Location: index.php?deleted=1');
                exit;
            } else {
                $error_message = 'Failed to delete venue. Please try again.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || empty($location) || empty($contact_phone)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            // Handle image upload
            $image_filename = $venue['image'];
            if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['venue_image'], 'venue');
                
                if ($upload_result['success']) {
                    // Delete old image if exists
                    if (!empty($venue['image'])) {
                        deleteUploadedFile($venue['image']);
                    }
                    $image_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $sql = "UPDATE venues SET 
                        name = ?,
                        location = ?,
                        address = ?,
                        description = ?,
                        image = ?,
                        contact_phone = ?,
                        contact_email = ?,
                        status = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $location,
                    $address,
                    $description,
                    $image_filename,
                    $contact_phone,
                    $contact_email,
                    $status,
                    $venue_id
                ]);

                if ($result) {
                    // Log activity
                    logActivity($current_user['id'], 'Updated venue', 'venues', $venue_id, "Updated venue: $name");
                    
                    $success_message = 'Venue updated successfully!';
                    
                    // Refresh venue data
                    $stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
                    $stmt->execute([$venue_id]);
                    $venue = $stmt->fetch();
                } else {
                    $error_message = 'Failed to update venue. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Venue</h5>
                <div>
                    <a href="view.php?id=<?php echo $venue_id; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
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

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Venue Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($venue['name']); ?>" 
                                       placeholder="e.g., Grand Palace Hotel" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($venue['location']); ?>" 
                                       placeholder="e.g., Kathmandu, Nepal" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Full Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                                  placeholder="Enter complete address..."><?php echo htmlspecialchars($venue['address']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($venue['contact_phone']); ?>" 
                                       placeholder="e.g., +977 9876543210" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($venue['contact_email']); ?>" 
                                       placeholder="e.g., info@venue.com">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the venue, its facilities, and unique features..."><?php echo htmlspecialchars($venue['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="venue_image" class="form-label">Venue Image</label>
                        <?php echo displayImagePreview($venue['image'], 'Current venue image'); ?>
                        <input type="file" class="form-control" id="venue_image" name="venue_image" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the current one. JPG, PNG, GIF, or WebP. Max 5MB</small>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $venue['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $venue['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Venue
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Venue
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this venue? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo $venue_id; ?>&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
