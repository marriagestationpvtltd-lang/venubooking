<?php
$page_title = 'Edit Vendor';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

$vendor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($vendor_id <= 0) {
    header('Location: index.php');
    exit;
}

$vendor = getVendor($vendor_id);
if (!$vendor) {
    $_SESSION['error_message'] = 'Vendor not found.';
    header('Location: index.php');
    exit;
}

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
    $bank_details      = trim($_POST['bank_details']      ?? '');
    $status            = in_array($_POST['status'] ?? '', ['active', 'inactive', 'unapproved']) ? $_POST['status'] : 'active';

    // Handle photo deletions
    $delete_photo_ids = isset($_POST['delete_photos']) ? array_map('intval', (array)$_POST['delete_photos']) : [];
    if (!empty($delete_photo_ids)) {
        foreach ($delete_photo_ids as $photo_id) {
            $del_stmt = $db->prepare("SELECT image_path FROM vendor_photos WHERE id = ? AND vendor_id = ?");
            $del_stmt->execute([$photo_id, $vendor_id]);
            $del_photo = $del_stmt->fetch();
            if ($del_photo) {
                deleteUploadedFile($del_photo['image_path']);
                $db->prepare("DELETE FROM vendor_photos WHERE id = ? AND vendor_id = ?")->execute([$photo_id, $vendor_id]);
            }
        }
    }

    if (empty($name)) {
        $error_message = 'Vendor name is required.';
    } elseif (!in_array($type, array_column($vendor_types, 'slug'), true)) {
        $error_message = 'Invalid vendor type selected.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Handle new photo uploads
            if (isset($_FILES['photos']) && is_array($_FILES['photos']['error'])) {
                $existing_count = count(getVendorPhotos($vendor_id));
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
                        $is_primary = ($existing_count === 0 && $i === 0) ? 1 : 0;
                        $photo_stmt = $db->prepare("INSERT INTO vendor_photos (vendor_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $photo_stmt->execute([$vendor_id, $upload_result['filename'], $is_primary, $existing_count + $i]);
                    } else {
                        $error_message = $upload_result['message'];
                        break;
                    }
                }
            }

            if (empty($error_message)) {
                // Handle QR code upload
                $qr_code = $vendor['qr_code'] ?? null;
                $has_new_qr_upload = isset($_FILES['qr_code']) && ($_FILES['qr_code']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($has_new_qr_upload) {
                    $qr_result = handleImageUpload($_FILES['qr_code'], 'vendor-qr');
                    if ($qr_result['success']) {
                        if (!empty($vendor['qr_code'])) {
                            deleteUploadedFile($vendor['qr_code']);
                        }
                        $qr_code = $qr_result['filename'];
                    } else {
                        $error_message = $qr_result['message'];
                    }
                }
                if (isset($_POST['delete_qr_code']) && !$has_new_qr_upload) {
                    if (!empty($vendor['qr_code'])) {
                        deleteUploadedFile($vendor['qr_code']);
                    }
                    $qr_code = null;
                }
            }

            if (empty($error_message)) {
                $stmt = $db->prepare("UPDATE vendors SET name = ?, type = ?, short_description = ?, phone = ?, email = ?, address = ?, city_id = ?, photo = NULL, notes = ?, bank_details = ?, qr_code = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $type, $short_description ?: null, $phone ?: null, $email ?: null, $address ?: null, $city_id ?: null, $notes ?: null, $bank_details ?: null, $qr_code, $status, $vendor_id]);

                // Update service cities (replaces all existing entries with the new selection)
                setVendorServiceCities($vendor_id, $service_city_ids);

                logActivity($current_user['id'], 'Updated vendor', 'vendors', $vendor_id, "Updated vendor: $name ($type)");

                $success_message = 'Vendor updated successfully!';
                $vendor = getVendor($vendor_id);
            }
        } catch (Exception $e) {
            $error_message = 'Failed to update vendor. Please try again.';
        }
    }
    } // end CSRF check
}
?>

<div class="row">
    <div class="col-md-10 col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Vendor</h5>
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
                                   value="<?php echo htmlspecialchars($vendor['name']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="type" class="form-label">Vendor Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <?php foreach ($vendor_types as $vtype): ?>
                                    <option value="<?php echo htmlspecialchars($vtype['slug']); ?>"
                                        <?php echo ($vendor['type'] === $vtype['slug']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vtype['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="short_description" class="form-label">Short Description</label>
                            <input type="text" class="form-control" id="short_description" name="short_description"
                                   value="<?php echo htmlspecialchars($vendor['short_description'] ?? ''); ?>"
                                   maxlength="500"
                                   placeholder="e.g., Professional wedding photographer with 10+ years experience">
                            <small class="text-muted">A brief description of what this vendor does (max 500 characters).</small>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>">
                        </div>

                        <div class="col-md-8">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   value="<?php echo htmlspecialchars($vendor['address'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="city_id" class="form-label">City</label>
                            <select class="form-select" id="city_id" name="city_id">
                                <option value="">— Select City —</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city['id']; ?>"
                                        <?php echo (intval($vendor['city_id'] ?? 0) === $city['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Service Cities <small class="text-muted">(cities where this vendor operates)</small></label>
                            <?php
                            $existing_service_cities = getVendorServiceCities($vendor_id);
                            $existing_service_city_ids = array_map('intval', array_column($existing_service_cities, 'city_id'));
                            // On POST (validation error), prefer submitted values over DB values
                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_city_ids'])) {
                                $existing_service_city_ids = array_map('intval', (array)$_POST['service_city_ids']);
                            }
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
                                                           <?php echo in_array((int)$city['id'], $existing_service_city_ids) ? 'checked' : ''; ?>>
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
                                <option value="unapproved" <?php echo $vendor['status'] === 'unapproved' ? 'selected' : ''; ?>>Unapproved</option>
                                <option value="active"     <?php echo $vendor['status'] === 'active'     ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive"   <?php echo $vendor['status'] === 'inactive'   ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($vendor['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label for="bank_details" class="form-label"><i class="fas fa-university me-1"></i>Bank Details</label>
                            <textarea class="form-control" id="bank_details" name="bank_details" rows="4"
                                      placeholder="Bank Name:&#10;Account Name:&#10;Account Number:&#10;Branch:"><?php echo htmlspecialchars($vendor['bank_details'] ?? ''); ?></textarea>
                            <small class="text-muted">Enter bank account details for payment (bank name, account name/number, etc.).</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label"><i class="fas fa-qrcode me-1"></i>Payment QR Code</label>
                            <?php if (!empty($vendor['qr_code'])): ?>
                                <div class="mb-2 d-flex align-items-center gap-3">
                                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $vendor['qr_code']); ?>"
                                         alt="Payment QR Code"
                                         style="width:120px;height:120px;object-fit:contain;border:2px solid #dee2e6;border-radius:8px;">
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="delete_qr_code" id="delete_qr_code" value="1">
                                            <label class="form-check-label text-danger" for="delete_qr_code">Remove current QR code</label>
                                        </div>
                                        <small class="text-muted d-block mt-1">Upload a new image below to replace it.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="qr_code" name="qr_code" accept="image/*">
                            <small class="text-muted">Upload a QR code image for payment scanning. JPG, PNG, or WebP. Max 5MB.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Vendor Photos</label>
                            <?php $existing_photos = getVendorPhotos($vendor_id); ?>
                            <?php if (!empty($existing_photos)): ?>
                                <div class="mb-2">
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($existing_photos as $photo): ?>
                                            <div class="position-relative">
                                                <img src="<?php echo htmlspecialchars(UPLOAD_URL . $photo['image_path']); ?>"
                                                     alt="Vendor photo"
                                                     style="width:100px;height:100px;object-fit:cover;border-radius:6px;border:2px solid <?php echo $photo['is_primary'] ? '#4CAF50' : '#dee2e6'; ?>">
                                                <?php if ($photo['is_primary']): ?>
                                                    <span class="badge bg-success position-absolute top-0 start-0" style="font-size:0.65rem;">Primary</span>
                                                <?php endif; ?>
                                                <div class="form-check position-absolute bottom-0 end-0 p-1" style="background:rgba(255,255,255,0.8);border-radius:4px;">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="delete_photos[]"
                                                           value="<?php echo $photo['id']; ?>"
                                                           id="del_photo_<?php echo $photo['id']; ?>">
                                                    <label class="form-check-label text-danger" for="del_photo_<?php echo $photo['id']; ?>" style="font-size:0.7rem;">Del</label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="d-block text-muted mt-1">Check "Del" on any photo to delete it. Upload new photos below.</small>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="photos" name="photos[]" accept="image/*" multiple>
                            <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB each. You can select multiple files.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
