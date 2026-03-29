<?php
$page_title = 'Add New Vendor';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

$vendor_types = getVendorTypes();
$cities = getAllCities();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
    $name              = trim($_POST['name']              ?? '');
    $type              = trim($_POST['type']              ?? 'other');
    $short_description = trim($_POST['short_description'] ?? '');
    $phone             = trim($_POST['phone']             ?? '');
    $email             = trim($_POST['email']             ?? '');
    $address           = trim($_POST['address']           ?? '');
    $city_id           = intval($_POST['city_id']         ?? 0);
    $service_city_ids  = isset($_POST['service_city_ids']) ? array_map('intval', (array)$_POST['service_city_ids']) : [];
    $notes             = trim($_POST['notes']             ?? '');
    $status            = in_array($_POST['status'] ?? '', ['active', 'inactive', 'unapproved']) ? $_POST['status'] : 'unapproved';

    if (empty($name)) {
        $error_message = 'Vendor name is required.';
    } elseif (!in_array($type, array_column($vendor_types, 'slug'), true)) {
        $error_message = 'Invalid vendor type selected.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $photos_saved = [];
            if (isset($_FILES['photos']) && is_array($_FILES['photos']['error'])) {
                $file_count = count($_FILES['photos']['error']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    $single_file = [
                        'name'     => $_FILES['photos']['name'][$i],
                        'type'     => $_FILES['photos']['type'][$i],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                        'error'    => $_FILES['photos']['error'][$i],
                        'size'     => $_FILES['photos']['size'][$i],
                    ];
                    $upload_result = handleImageUpload($single_file, 'vendor');
                    if ($upload_result['success']) {
                        $photos_saved[] = $upload_result['filename'];
                    } else {
                        $error_message = $upload_result['message'];
                        break;
                    }
                }
            }

            if (empty($error_message)) {
                $stmt = $db->prepare("INSERT INTO vendors (name, type, short_description, phone, email, address, city_id, photo, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)");
                $stmt->execute([$name, $type, $short_description ?: null, $phone ?: null, $email ?: null, $address ?: null, $city_id ?: null, $notes ?: null, $status]);
                $vendor_id = $db->lastInsertId();

                // Save uploaded photos to vendor_photos table
                foreach ($photos_saved as $idx => $photo_filename) {
                    $is_primary = ($idx === 0) ? 1 : 0;
                    $photo_stmt = $db->prepare("INSERT INTO vendor_photos (vendor_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
                    $photo_stmt->execute([$vendor_id, $photo_filename, $is_primary, $idx]);
                }

                // Save service cities (junction table – supports multiple cities)
                if (!empty($service_city_ids)) {
                    setVendorServiceCities($vendor_id, $service_city_ids);
                } elseif ($city_id > 0) {
                    // Fall back: seed service cities from the primary city
                    setVendorServiceCities($vendor_id, [$city_id]);
                }

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
                                    <option value="<?php echo htmlspecialchars($vtype['slug']); ?>"
                                        <?php echo (($_POST['type'] ?? 'other') === $vtype['slug']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vtype['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="short_description" class="form-label">Short Description</label>
                            <input type="text" class="form-control" id="short_description" name="short_description"
                                   value="<?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?>"
                                   maxlength="500"
                                   placeholder="e.g., Professional wedding photographer with 10+ years experience">
                            <small class="text-muted">A brief description of what this vendor does (max 500 characters).</small>
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
                            <label for="city_id" class="form-label">City</label>
                            <select class="form-select" id="city_id" name="city_id">
                                <option value="">— Select City —</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city['id']; ?>"
                                        <?php echo (intval($_POST['city_id'] ?? 0) === $city['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Service Cities <small class="text-muted">(cities where this vendor operates)</small></label>
                            <?php
                            $selected_service_city_ids = isset($_POST['service_city_ids'])
                                ? array_map('intval', (array)$_POST['service_city_ids'])
                                : [];
                            ?>
                            <div class="border rounded p-3 bg-light" style="max-height:180px;overflow-y:auto;">
                                <?php if (empty($cities)): ?>
                                    <span class="text-muted small">No cities available. Add cities first.</span>
                                <?php else: ?>
                                    <div class="row g-2">
                                        <?php foreach ($cities as $city): ?>
                                            <div class="col-md-4 col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="service_city_ids[]"
                                                           value="<?php echo $city['id']; ?>"
                                                           id="svc_city_<?php echo $city['id']; ?>"
                                                           <?php echo in_array((int)$city['id'], $selected_service_city_ids) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="svc_city_<?php echo $city['id']; ?>">
                                                        <?php echo htmlspecialchars($city['name']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Select all cities this vendor provides services in. Multiple selections are allowed.</small>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status">
                                <option value="unapproved" <?php echo (($_POST['status'] ?? 'unapproved') === 'unapproved') ? 'selected' : ''; ?>>Unapproved</option>
                                <option value="active"     <?php echo (($_POST['status'] ?? '') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive"   <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any special notes about this vendor..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label for="photos" class="form-label">Vendor Photos</label>
                            <input type="file" class="form-control" id="photos" name="photos[]" accept="image/*" multiple>
                            <small class="text-muted">Upload one or more photos. JPG, PNG, GIF, or WebP. Max 5MB each.</small>
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
