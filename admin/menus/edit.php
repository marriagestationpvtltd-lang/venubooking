<?php
$page_title = 'Edit Menu';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get menu ID from URL
$menu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($menu_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch menu details
$stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$menu_id]);
$menu = $stmt->fetch();

if (!$menu) {
    header('Location: index.php');
    exit;
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        // Check if menu is used in any bookings
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_menus WHERE menu_id = ?");
        $check_stmt->execute([$menu_id]);
        $result = $check_stmt->fetch();
        
        if ($result['count'] > 0) {
            $error_message = 'Cannot delete menu. It is associated with existing bookings. You can set it to inactive instead.';
        } else {
            // Delete menu items first
            $stmt = $db->prepare("DELETE FROM menu_items WHERE menu_id = ?");
            $stmt->execute([$menu_id]);
            
            // Delete hall_menus associations
            $stmt = $db->prepare("DELETE FROM hall_menus WHERE menu_id = ?");
            $stmt->execute([$menu_id]);
            
            // Delete the menu
            $stmt = $db->prepare("DELETE FROM menus WHERE id = ?");
            if ($stmt->execute([$menu_id])) {
                // Log activity
                logActivity($current_user['id'], 'Deleted menu', 'menus', $menu_id, "Deleted menu: {$menu['name']}");
                
                header('Location: index.php?deleted=1');
                exit;
            } else {
                $error_message = 'Failed to delete menu. Please try again.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price_per_person = floatval($_POST['price_per_person']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $price_per_person <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        try {
            // Handle image upload
            $image_filename = $menu['image'];
            if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['menu_image'], 'menu');
                
                if ($upload_result['success']) {
                    // Delete old image if exists
                    if (!empty($menu['image'])) {
                        deleteUploadedFile($menu['image']);
                    }
                    $image_filename = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $sql = "UPDATE menus SET 
                        name = ?,
                        description = ?,
                        price_per_person = ?,
                        image = ?,
                        status = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $description,
                    $price_per_person,
                    $image_filename,
                    $status,
                    $menu_id
                ]);

                if ($result) {
                    // Log activity
                    logActivity($current_user['id'], 'Updated menu', 'menus', $menu_id, "Updated menu: $name");
                    
                    $success_message = 'Menu updated successfully!';
                    
                    // Refresh menu data
                    $stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
                    $stmt->execute([$menu_id]);
                    $menu = $stmt->fetch();
                } else {
                    $error_message = 'Failed to update menu. Please try again.';
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
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Menu</h5>
                <div>
                    <a href="view.php?id=<?php echo $menu_id; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="items.php?id=<?php echo $menu_id; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-list"></i> Items
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
                                <label for="name" class="form-label">Menu Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($menu['name']); ?>" 
                                       placeholder="e.g., Premium Wedding Package" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price_per_person" class="form-label">Price per Person (<?php echo CURRENCY; ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price_per_person" name="price_per_person" 
                                       value="<?php echo $menu['price_per_person']; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 1500.00" required>
                                <small class="text-muted">Price charged per guest</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the menu, its items, and what makes it special..."><?php echo htmlspecialchars($menu['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="menu_image" class="form-label">Menu Image</label>
                        <?php echo displayImagePreview($menu['image'], 'Current menu image'); ?>
                        <input type="file" class="form-control" id="menu_image" name="menu_image" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the current one. JPG, PNG, GIF, or WebP. Max 5MB</small>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $menu['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $menu['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Menu
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Menu
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
    if (confirm('Are you sure you want to delete this menu? This will also delete all menu items. This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo $menu_id; ?>&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
