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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if (empty($name) || $venue_id <= 0 || $capacity <= 0 || $base_price <= 0) {
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
                // Log activity
                logActivity($current_user['id'], 'Updated hall', 'halls', $hall_id, "Updated hall: $name");
                
                $success_message = 'Hall updated successfully!';
                
                // Refresh hall data
                $stmt = $db->prepare("SELECT * FROM halls WHERE id = ?");
                $stmt->execute([$hall_id]);
                $hall = $stmt->fetch();
            } else {
                $error_message = 'Failed to update hall. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
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
                                <label for="base_price" class="form-label">Base Price (<?php echo CURRENCY; ?>) <span class="text-danger">*</span></label>
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

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Hall
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Display linked menus
$menus_stmt = $db->prepare("SELECT m.* FROM menus m 
                            INNER JOIN hall_menus hm ON m.id = hm.menu_id 
                            WHERE hm.hall_id = ? 
                            ORDER BY m.name");
$menus_stmt->execute([$hall_id]);
$linked_menus = $menus_stmt->fetchAll();

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
$images_stmt = $db->prepare("SELECT * FROM hall_images WHERE hall_id = ? ORDER BY display_order");
$images_stmt->execute([$hall_id]);
$images = $images_stmt->fetchAll();

if (count($images) > 0):
?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-images"></i> Hall Images</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($images as $image): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="mb-0 mt-2 small"><?php echo htmlspecialchars($image['image_path']); ?></p>
                                    <?php if ($image['is_primary']): ?>
                                        <span class="badge bg-primary mt-1">Primary</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
