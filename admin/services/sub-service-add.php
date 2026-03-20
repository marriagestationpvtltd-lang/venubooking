<?php
$page_title = 'Add Sub-Service';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
if ($service_id <= 0) {
    header('Location: index.php');
    exit;
}

// Verify service exists
$stmt = $db->prepare("SELECT id, name FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();
if (!$service) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($name)) {
        $error_message = 'Sub-service name is required.';
    } else {
        try {
            $stmt = $db->prepare(
                "INSERT INTO service_sub_services (service_id, name, description, display_order, status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$service_id, $name, $description, $display_order, $status]);
            $sub_service_id = $db->lastInsertId();
            logActivity($current_user['id'], 'Added sub-service', 'service_sub_services', $sub_service_id, "Added sub-service '$name' to service '{$service['name']}'");
            $success_message = 'Sub-service added successfully!';
            $_POST = [];
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
                <h5 class="mb-0">
                    <i class="fas fa-plus"></i> Add Sub-Service
                    <small class="text-muted ms-2">for: <?php echo htmlspecialchars($service['name']); ?></small>
                </h5>
                <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="view.php?id=<?php echo $service_id; ?>" class="alert-link">Back to service</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Sub-Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="e.g., Mandap, Stage" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Describe this sub-service..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo intval($_POST['display_order'] ?? 0); ?>" min="0">
                                <small class="text-muted">Lower numbers appear first.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $service_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Sub-Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
