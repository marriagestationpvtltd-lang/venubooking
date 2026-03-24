<?php
$page_title = 'Edit Hall';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get hall ID from URL
$hall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hall_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch hall details
$stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
$stmt->execute([$hall_id]);
$hall = $stmt->fetch();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Fetch all venues for dropdown
$venues_stmt = $db->query("SELECT id, name FROM venues WHERE status = 'active' ORDER BY name");
$venues = $venues_stmt->fetchAll();

// Fetch all active menus for assignment
$available_menus = getAllActiveMenus();

// Fetch currently assigned menus for this hall
$assigned_menu_ids = getAssignedMenuIds($hall_id);

// Handle image upload
if (isset($_POST['upload_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (isset($_FILES['hall_image'])) {
        $upload_result = handleImageUpload($_FILES['hall_image'], 'hall');
        
        if ($upload_result['success']) {
            try {
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $display_order = intval($_POST['display_order']);
                
                // If this is primary, unset other primary images
                if ($is_primary) {
                    $db->prepare("UPDATE hall_images SET is_primary = 0 WHERE hall_id = ?")->execute([$hall_id]);
                }
                
                $sql = "INSERT INTO hall_images (hall_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$hall_id, $upload_result['filename'], $is_primary, $display_order]);
                
                logActivity($current_user['id'], 'Uploaded hall image', 'hall_images', $db->lastInsertId(), "Uploaded image for hall: {$hall['name']}");
                
                $success_message = 'Image uploaded successfully!';
            } catch (Exception $e) {
                deleteUploadedFile($upload_result['filename']);
                $error_message = 'Error saving image: ' . $e->getMessage();
            }
        } else {
            $error_message = $upload_result['message'];
        }
    } else {
        $error_message = 'Please select an image to upload.';
    }
}

// Handle 360° panoramic image upload
if (isset($_POST['upload_pano']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (isset($_FILES['hall_pano_image']) && $_FILES['hall_pano_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $pano_result = handleImageUpload($_FILES['hall_pano_image'], 'hall_pano');
        if ($pano_result['success']) {
            try {
                // Delete old pano image if it exists
                if (!empty($hall['pano_image'])) {
                    deleteUploadedFile($hall['pano_image']);
                }
                $db->prepare("UPDATE halls SET pano_image = ? WHERE id = ?")->execute([$pano_result['filename'], $hall_id]);
                logActivity($current_user['id'], 'Uploaded hall pano image', 'halls', $hall_id, "Uploaded 360° pano for hall: {$hall['name']}");
                // Refresh hall data
                $stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
                $stmt->execute([$hall_id]);
                $hall = $stmt->fetch();
                $success_message = '360° panoramic image uploaded successfully!';
            } catch (Exception $e) {
                deleteUploadedFile($pano_result['filename']);
                $error_message = 'Error saving panoramic image: ' . $e->getMessage();
            }
        } else {
            $error_message = $pano_result['message'];
        }
    } else {
        $error_message = 'Please select a panoramic image to upload.';
    }
}

// Handle 360° panoramic image deletion
if (isset($_POST['delete_pano']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            if (!empty($hall['pano_image'])) {
                deleteUploadedFile($hall['pano_image']);
            }
            $db->prepare("UPDATE halls SET pano_image = NULL WHERE id = ?")->execute([$hall_id]);
            logActivity($current_user['id'], 'Deleted hall pano image', 'halls', $hall_id, "Deleted 360° pano for hall: {$hall['name']}");
            // Refresh hall data
            $stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
            $stmt->execute([$hall_id]);
            $hall = $stmt->fetch();
            $success_message = '360° panoramic image deleted successfully!';
        } catch (Exception $e) {
            $error_message = 'Error deleting panoramic image: ' . $e->getMessage();
        }
    }
}

// Handle image deletion via POST
if (isset($_POST['delete_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $image_id = intval($_POST['delete_image']);
        try {
            $stmt = $db->prepare("SELECT * FROM hall_images WHERE id = ? AND hall_id = ?");
            $stmt->execute([$image_id, $hall_id]);
            $image = $stmt->fetch();
            
            if ($image) {
                deleteUploadedFile($image['image_path']);
                $db->prepare("DELETE FROM hall_images WHERE id = ?")->execute([$image_id]);
                
                logActivity($current_user['id'], 'Deleted hall image', 'hall_images', $image_id, "Deleted image for hall: {$hall['name']}");
                
                $success_message = 'Image deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting image: ' . $e->getMessage();
        }
    }
}

// Handle time slot addition
if (isset($_POST['add_time_slot']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $ts_name   = trim($_POST['ts_name'] ?? '');
        $ts_start  = trim($_POST['ts_start'] ?? '');
        $ts_end    = trim($_POST['ts_end'] ?? '');
        $ts_price  = isset($_POST['ts_price']) && is_numeric($_POST['ts_price']) && $_POST['ts_price'] !== '' ? floatval($_POST['ts_price']) : null;
        $ts_status = in_array($_POST['ts_status'] ?? '', ['active','inactive'], true) ? $_POST['ts_status'] : 'active';

        if ($ts_name === '' || !preg_match('/^\d{2}:\d{2}$/', $ts_start) || !preg_match('/^\d{2}:\d{2}$/', $ts_end)) {
            $error_message = 'Please provide a valid slot name, start time, and end time.';
        } else {
            try {
                $db->prepare("INSERT INTO hall_time_slots (hall_id, slot_name, start_time, end_time, price_override, status) VALUES (?,?,?,?,?,?)")
                   ->execute([$hall_id, $ts_name, $ts_start, $ts_end, $ts_price, $ts_status]);
                logActivity($current_user['id'], 'Added hall time slot', 'hall_time_slots', $db->lastInsertId(), "Added slot '$ts_name' for hall: {$hall['name']}");
                $success_message = 'Time slot added successfully!';
            } catch (Exception $e) {
                error_log('Error adding time slot: ' . $e->getMessage());
                $error_message = 'Error adding time slot.';
            }
        }
    }
}

// Handle time slot deletion
if (isset($_POST['delete_time_slot']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $ts_id = intval($_POST['delete_time_slot']);
        try {
            $db->prepare("DELETE FROM hall_time_slots WHERE id = ? AND hall_id = ?")->execute([$ts_id, $hall_id]);
            logActivity($current_user['id'], 'Deleted hall time slot', 'hall_time_slots', $ts_id, "Deleted time slot for hall: {$hall['name']}");
            $success_message = 'Time slot deleted successfully!';
        } catch (Exception $e) {
            $error_message = 'Error deleting time slot.';
        }
    }
}

// Handle time slot status toggle
if (isset($_POST['toggle_time_slot_status']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $ts_id = intval($_POST['toggle_time_slot_status']);
        try {
            $db->prepare("UPDATE hall_time_slots SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ? AND hall_id = ?")
               ->execute([$ts_id, $hall_id]);
            $success_message = 'Time slot status updated!';
        } catch (Exception $e) {
            $error_message = 'Error updating time slot status.';
        }
    }
}

// Handle form submission
if (isset($_POST['update_hall']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
    $venue_id = intval($_POST['venue_id']);
    $name = trim($_POST['name']);
    $capacity = intval($_POST['capacity']);
    $hall_type = $_POST['hall_type'];
    $indoor_outdoor = $_POST['indoor_outdoor'];
    $base_price = floatval($_POST['base_price']);
    $description = trim($_POST['description']);
    $features = trim($_POST['features']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $venue_id <= 0 || $capacity <= 0 || $base_price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Verify venue exists
        $venue_check = $db->prepare("SELECT id FROM venues WHERE id = ?");
        $venue_check->execute([$venue_id]);
        if (!$venue_check->fetch()) {
            $error_message = 'Selected venue does not exist.';
        } else {
        try {
            $sql = "UPDATE halls SET 
                    venue_id = ?,
                    name = ?,
                    capacity = ?,
                    hall_type = ?,
                    indoor_outdoor = ?,
                    base_price = ?,
                    description = ?,
                    features = ?,
                    status = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $venue_id,
                $name,
                $capacity,
                $hall_type,
                $indoor_outdoor,
                $base_price,
                $description,
                $features,
                $status,
                $hall_id
            ]);

            if ($result) {
                // Handle menu assignments
                $selected_menus = isset($_POST['menus']) ? $_POST['menus'] : [];
                updateHallMenus($hall_id, $selected_menus);
                
                // Log activity
                logActivity($current_user['id'], 'Updated hall', 'halls', $hall_id, "Updated hall: $name");
                
                $success_message = 'Hall updated successfully!';
                
                // Refresh hall data
                $stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
                $stmt->execute([$hall_id]);
                $hall = $stmt->fetch();
                
                // Refresh assigned menus
                $assigned_menu_ids = getAssignedMenuIds($hall_id);
            } else {
                $error_message = 'Failed to update hall. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
        }
    }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Hall</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
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

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="venue_id" class="form-label">Venue <span class="text-danger">*</span></label>
                                <select class="form-select" id="venue_id" name="venue_id" required>
                                    <option value="">Select Venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?php echo $venue['id']; ?>" <?php echo $venue['id'] == $hall['venue_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($venue['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Hall Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($hall['name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity (Guests) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo $hall['capacity']; ?>" min="1" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="hall_type" class="form-label">Hall Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_type" name="hall_type" required>
                                    <option value="single" <?php echo $hall['hall_type'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="multiple" <?php echo $hall['hall_type'] == 'multiple' ? 'selected' : ''; ?>>Multiple</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="indoor_outdoor" class="form-label">Indoor/Outdoor <span class="text-danger">*</span></label>
                                <select class="form-select" id="indoor_outdoor" name="indoor_outdoor" required>
                                    <option value="indoor" <?php echo $hall['indoor_outdoor'] == 'indoor' ? 'selected' : ''; ?>>Indoor</option>
                                    <option value="outdoor" <?php echo $hall['indoor_outdoor'] == 'outdoor' ? 'selected' : ''; ?>>Outdoor</option>
                                    <option value="both" <?php echo $hall['indoor_outdoor'] == 'both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="base_price" class="form-label">Base Price (<?php echo getSetting('currency', 'NPR'); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="base_price" name="base_price" 
                                       value="<?php echo $hall['base_price']; ?>" min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $hall['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $hall['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($hall['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="features" class="form-label">Features</label>
                        <textarea class="form-control" id="features" name="features" rows="2" 
                                  placeholder="e.g., Air conditioning, Stage, Sound system, LED screens"><?php echo htmlspecialchars($hall['features']); ?></textarea>
                        <small class="text-muted">Separate features with commas</small>
                    </div>

                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">Assign Menus to Hall</h6>
                    <div class="mb-3">
                        <label class="form-label">Select Menus Available for This Hall</label>
                        <small class="text-muted d-block mb-2">Choose which menus customers can select when booking this hall</small>
                        <?php if (empty($available_menus)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No active menus found. 
                                <a href="<?php echo BASE_URL; ?>/admin/menus/add.php" class="alert-link">Add menus</a> first to assign them to halls.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($available_menus as $menu): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="menus[]" 
                                                   value="<?php echo $menu['id']; ?>" 
                                                   id="menu_<?php echo $menu['id']; ?>"
                                                   <?php echo in_array($menu['id'], $assigned_menu_ids) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="menu_<?php echo $menu['id']; ?>">
                                                <?php echo htmlspecialchars($menu['name']); ?> 
                                                <span class="text-muted">(<?php echo formatCurrency($menu['price_per_person']); ?>/person)</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Hall
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="update_hall" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Hall
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Time Slots Management Card -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clock text-success me-2"></i>Booking Time Slots</h5>
                <small class="text-muted">Define when customers can book this hall</small>
            </div>
            <div class="card-body">
                <?php
                $time_slots = getHallTimeSlots($hall_id, false);
                if (!empty($time_slots)):
                ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Slot Name</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Price Override</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $slot): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($slot['slot_name']); ?></td>
                                <td><?php echo date('h:i A', strtotime($slot['start_time'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($slot['end_time'])); ?></td>
                                <td><?php echo $slot['price_override'] !== null ? formatCurrency($slot['price_override']) : '<span class="text-muted small">Hall base price</span>'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $slot['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($slot['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="toggle_time_slot_status" value="<?php echo $slot['id']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Toggle status">
                                            <i class="fas fa-toggle-<?php echo $slot['status'] === 'active' ? 'on text-success' : 'off'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this time slot?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="delete_time_slot" value="<?php echo $slot['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No time slots defined. Customers cannot book this hall until at least one time slot is added.
                </div>
                <?php endif; ?>

                <!-- Add New Slot Form -->
                <h6 class="border-bottom pb-2 mb-3">Add New Time Slot</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Slot Name <span class="text-danger">*</span></label>
                            <input type="text" name="ts_name" class="form-control form-control-sm" placeholder="e.g. Morning Slot" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="ts_start" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="ts_end" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Price Override</label>
                            <input type="number" name="ts_price" class="form-control form-control-sm" placeholder="(optional)" min="0" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select name="ts_status" class="form-select form-select-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" name="add_time_slot" class="btn btn-success btn-sm w-100">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Display linked menus
try {
    $menus_stmt = $db->prepare("SELECT m.* FROM menus m 
                                INNER JOIN hall_menus hm ON m.id = hm.menu_id 
                                WHERE hm.hall_id = ? 
                                AND hm.status = 'active'
                                ORDER BY m.name");
    $menus_stmt->execute([$hall_id]);
    $linked_menus = $menus_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch linked menus for hall $hall_id: " . $e->getMessage());
    $linked_menus = [];
}

if (count($linked_menus) > 0):
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Linked Menus</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Menu Name</th>
                                <th>Price per Person</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_menus as $menu): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($menu['name']); ?></td>
                                    <td><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                    <td><span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($menu['status']); ?>
                                    </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Display hall images
$images_stmt = $db->prepare("SELECT * FROM hall_images WHERE hall_id = ? ORDER BY is_primary DESC, display_order");
$images_stmt->execute([$hall_id]);
$images = $images_stmt->fetchAll();
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images"></i> Hall Images</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadImageForm">
                    <i class="fas fa-upload"></i> Upload New Image
                </button>
            </div>
            <div class="card-body">
                <!-- Image Upload Form -->
                <div class="collapse mb-3" id="uploadImageForm">
                    <div class="card">
                        <div class="card-body bg-light">
                            <h6 class="card-title">Upload New Image</h6>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hall_image" class="form-label">Select Image <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control" id="hall_image" name="hall_image" accept="image/*" required>
                                            <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="display_order" class="form-label">Display Order</label>
                                            <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                                            <small class="text-muted">Lower numbers first</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                                                <label class="form-check-label" for="is_primary">
                                                    Set as Primary Image
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="upload_image" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Upload Image
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Display Images -->
                <?php if (count($images) > 0): ?>
                    <div class="row">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <?php 
                                    $image_url = UPLOAD_URL . rawurlencode($image['image_path']);
                                    $image_file = UPLOAD_PATH . $image['image_path'];
                                    ?>
                                    <?php if (file_exists($image_file)): ?>
                                        <img src="<?php echo htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="Hall image" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="fas fa-image fa-3x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body text-center">
                                        <p class="mb-1 small text-muted">Order: <?php echo htmlspecialchars($image['display_order'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if ($image['is_primary']): ?>
                                            <span class="badge bg-primary mb-2">Primary Image</span>
                                        <?php endif; ?>
                                        <div>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delete_image" value="<?php echo htmlspecialchars($image['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to delete this image?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No images uploaded yet. Click "Upload New Image" to add images for this hall.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this hall? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo intval($hall_id); ?>&action=delete';
    }
}
</script>

<?php
// Display 360° panoramic image section
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-street-view text-primary"></i> 360° Panoramic Photo</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadPanoForm">
                    <i class="fas fa-upload"></i> <?php echo !empty($hall['pano_image']) ? 'Replace Pano Photo' : 'Upload Pano Photo'; ?>
                </button>
            </div>
            <div class="card-body">
                <!-- Pano Upload Form -->
                <div class="collapse mb-3" id="uploadPanoForm">
                    <div class="card">
                        <div class="card-body bg-light">
                            <h6 class="card-title">Upload 360° Panoramic Photo</h6>
                            <p class="text-muted small">Upload an equirectangular panoramic image. This will be displayed as an interactive 360° viewer on the booking page.</p>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="hall_pano_image" class="form-label">Panoramic Image <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="hall_pano_image" name="hall_pano_image" accept="image/*" required>
                                    <small class="text-muted">Equirectangular format (2:1 aspect ratio). JPG or PNG. Max 5MB.</small>
                                </div>
                                <button type="submit" name="upload_pano" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Upload Panoramic Image
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Display Current Pano Image -->
                <?php if (!empty($hall['pano_image'])): ?>
                    <?php $pano_url = UPLOAD_URL . rawurlencode($hall['pano_image']); ?>
                    <div class="row">
                        <div class="col-md-8">
                            <div id="hallPanoPreview" style="width:100%;height:300px;border-radius:8px;overflow:hidden;"></div>
                        </div>
                        <div class="col-md-4 d-flex flex-column justify-content-center">
                            <p class="text-muted small mb-2"><strong>File:</strong> <?php echo htmlspecialchars($hall['pano_image'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-muted small mb-3"><i class="fas fa-info-circle"></i> This equirectangular image is shown as an interactive 360° viewer to customers on the booking page.</p>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" name="delete_pano" value="1" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete the 360° panoramic photo for this hall?')">
                                    <i class="fas fa-trash"></i> Delete Pano Photo
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Pannellum 360° preview in admin -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css">
                    <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        pannellum.viewer('hallPanoPreview', {
                            type: 'equirectangular',
                            panorama: <?php echo json_encode($pano_url); ?>,
                            autoLoad: true,
                            autoRotate: -2, // negative = counter-clockwise, degrees/second
                            showControls: true,
                            showZoomCtrl: false,
                            showFullscreenCtrl: true,
                            compass: false
                        });
                    });
                    </script>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No 360° panoramic photo uploaded yet. Click "Upload Pano Photo" to add one.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
