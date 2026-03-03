<?php
$page_title = 'Add New Vendor';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

$vendor_types = ['pandit', 'photographer', 'videographer', 'baje', 'decoration', 'catering', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
    $name     = trim($_POST['name']     ?? '');
    $type     = trim($_POST['type']     ?? 'other');
    $phone    = trim($_POST['phone']    ?? '');
    $email    = trim($_POST['email']    ?? '');
    $address  = trim($_POST['address']  ?? '');
    $location = trim($_POST['location'] ?? '');
    $notes    = trim($_POST['notes']    ?? '');
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($name)) {
        $error_message = 'Vendor name is required.';
    } elseif (!in_array($type, $vendor_types, true)) {
        $error_message = 'Invalid vendor type selected.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['photo'], 'vendor');
                if ($upload_result['success']) {
                    $photo = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['message'];
                }
            }

            if (empty($error_message)) {
                $stmt = $db->prepare("INSERT INTO vendors (name, type, phone, email, address, location, photo, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $type, $phone ?: null, $email ?: null, $address ?: null, $location ?: null, $photo, $notes ?: null, $status]);
                $vendor_id = $db->lastInsertId();

                logActivity($current_user['id'], 'Added vendor', 'vendors', $vendor_id, "Added vendor: $name ($type)");

                $success_message = 'Vendor added successfully!';
                $_POST = [];
            }
        } catch (Exception $e) {
            $error_message = 'Failed to add vendor. Please try again.';
        }
    }
    }
}
?>

<div class="row">
    <div class="col-md-10 col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Vendor</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <a href="index.php" class="alert-link">View all vendors</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   placeholder="e.g., Ram Sharma" required>
                        </div>

                        <div class="col-md-6">
                            <label for="type" class="form-label">Vendor Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <?php foreach ($vendor_types as $vtype): ?>
                                    <option value="<?php echo $vtype; ?>"
                                        <?php echo (($_POST['type'] ?? 'other') === $vtype) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(getVendorTypeLabel($vtype)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                   placeholder="e.g., +977 9800000000">
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="vendor@example.com">
                        </div>

                        <div class="col-md-8">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                   placeholder="e.g., Kathmandu, Nepal">
                        </div>

                        <div class="col-md-4">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location"
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                   placeholder="e.g., Thamel, Kathmandu">
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status">
                                <option value="active"   <?php echo (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any special notes about this vendor..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label for="photo" class="form-label">Vendor Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <small class="text-muted">Upload a photo for this vendor. JPG, PNG, GIF, or WebP. Max 5MB.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Vendor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
