<?php
$page_title = 'Edit Service';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get service ID from URL
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($service_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch service details
$stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: index.php');
    exit;
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        // Check if service is used in any bookings
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_services WHERE service_id = ?");
        $check_stmt->execute([$service_id]);
        $result = $check_stmt->fetch();
        
        if ($result['count'] > 0) {
            $error_message = 'Cannot delete service. It is associated with existing bookings. You can set it to inactive instead.';
        } else {
            $stmt = $db->prepare("DELETE FROM additional_services WHERE id = ?");
            if ($stmt->execute([$service_id])) {
                // Log activity
                logActivity($current_user['id'], 'Deleted service', 'additional_services', $service_id, "Deleted service: {$service['name']}");
                
                header('Location: index.php?deleted=1');
                exit;
            } else {
                $error_message = 'Failed to delete service. Please try again.';
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
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = $_POST['status'];

    // Validation
    if (empty($name) || $price <= 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        try {
            $sql = "UPDATE additional_services SET 
                    name = ?,
                    description = ?,
                    price = ?,
                    category = ?,
                    status = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $name,
                $description,
                $price,
                $category,
                $status,
                $service_id
            ]);

            if ($result) {
                // Log activity
                logActivity($current_user['id'], 'Updated service', 'additional_services', $service_id, "Updated service: $name");
                
                $success_message = 'Service updated successfully!';
                
                // Refresh service data
                $stmt = $db->prepare("SELECT * FROM additional_services WHERE id = ?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();
            } else {
                $error_message = 'Failed to update service. Please try again.';
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
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Service</h5>
                <div>
                    <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-info btn-sm">
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

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($service['name']); ?>" 
                                       placeholder="e.g., Professional Photography" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?php echo htmlspecialchars($service['category']); ?>" 
                                       placeholder="e.g., Photography, Decoration, Entertainment">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo CURRENCY; ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo $service['price']; ?>" 
                                       min="0" step="0.01" placeholder="e.g., 25000.00" required>
                                <small class="text-muted">Price for this service</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $service['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $service['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the service and what it includes..."><?php echo htmlspecialchars($service['description']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Service
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Service
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
    if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo $service_id; ?>&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
