<?php
$page_title = 'Edit Service Package';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($package_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch package
$stmt = $db->prepare("SELECT * FROM service_packages WHERE id = ?");
$stmt->execute([$package_id]);
$package = $stmt->fetch();
if (!$package) {
    header('Location: index.php');
    exit;
}

// Load categories
$cat_stmt = $db->query("SELECT id, name FROM service_categories ORDER BY display_order, name");
$categories = $cat_stmt->fetchAll();

// Load all active services for the features checkboxes
$all_services = getActiveServices();

// Batch-load all active designs for those services (keyed by service_id)
$designs_by_service = [];
if (!empty($all_services)) {
    $svc_ids = array_column($all_services, 'id');
    if (!empty($svc_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($svc_ids), '?'));
            $des_stmt = $db->prepare(
                "SELECT id, service_id, name, price, photo
                   FROM service_designs
                  WHERE service_id IN ($placeholders) AND status = 'active'
                  ORDER BY service_id, display_order, name"
            );
            $des_stmt->execute($svc_ids);
            foreach ($des_stmt->fetchAll() as $d) {
                $designs_by_service[(int)$d['service_id']][] = $d;
            }
        } catch (\Throwable $e) {
            error_log('packages/edit.php: failed to load service designs — ' . $e->getMessage());
        }
    }
}

// Load existing features
// Guard against installations where service_id column hasn't been added yet
// (add_service_id_to_package_features.sql migration not yet run).
$existing_features = [];
try {
    $feat_stmt = $db->prepare(
        "SELECT id, feature_text, service_id FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
    );
    $feat_stmt->execute([$package_id]);
    $existing_features = $feat_stmt->fetchAll();
} catch (Exception $e) {
    // service_id column missing on this install; fall back to feature_text only
    try {
        $feat_stmt = $db->prepare(
            "SELECT id, feature_text, NULL AS service_id FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
        );
        $feat_stmt->execute([$package_id]);
        $existing_features = $feat_stmt->fetchAll();
    } catch (Exception $e2) {
        // ignore; $existing_features stays []
    }
}

// Load existing photos
$existing_photos = [];
try {
    $photo_load_stmt = $db->prepare(
        "SELECT id, image_path FROM service_package_photos WHERE package_id = ? ORDER BY display_order, id"
    );
    $photo_load_stmt->execute([$package_id]);
    $existing_photos = $photo_load_stmt->fetchAll();
} catch (Exception $e) {
    // table may not exist yet; silently continue
}

// Load all active halls grouped by venue (for venue assignment)
$halls_by_venue = [];
try {
    $hstmt = $db->query(
        "SELECT h.id AS hall_id, h.name AS hall_name, h.base_price,
                v.id AS venue_id, v.name AS venue_name
         FROM halls h
         INNER JOIN venues v ON v.id = h.venue_id
         WHERE h.status = 'active' AND v.status = 'active'
         ORDER BY v.name, h.name"
    );
    foreach ($hstmt->fetchAll() as $row) {
        $halls_by_venue[$row['venue_id']]['venue_name'] = $row['venue_name'];
        $halls_by_venue[$row['venue_id']]['halls'][]    = $row;
    }
} catch (\Throwable $e) {
    error_log('packages/edit.php: failed to load halls — ' . $e->getMessage());
}

// Load all active menus
$all_menus = [];
try {
    $mstmt = $db->query("SELECT id, name, price_per_person FROM menus WHERE status = 'active' ORDER BY name");
    $all_menus = $mstmt->fetchAll();
} catch (\Throwable $e) {
    error_log('packages/edit.php: failed to load menus — ' . $e->getMessage());
}

// Load existing venue/hall associations for this package
$existing_hall_ids = [];
try {
    $pvload = $db->prepare("SELECT hall_id FROM package_venues WHERE package_id = ?");
    $pvload->execute([$package_id]);
    $existing_hall_ids = $pvload->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    // Table may not exist yet on older installs
    error_log('packages/edit.php: failed to load package_venues — ' . $e->getMessage());
}

// Load existing menu associations for this package
$existing_menu_ids = [];
try {
    $pmload = $db->prepare("SELECT menu_id FROM package_menus WHERE package_id = ?");
    $pmload->execute([$package_id]);
    $existing_menu_ids = $pmload->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    error_log('packages/edit.php: failed to load package_menus — ' . $e->getMessage());
}

// Load all gallery images for the gallery-photo selector
$all_gallery_images = [];
try {
    $gi_stmt = $db->query(
        "SELECT id, title, image_path FROM site_images
         WHERE section = 'gallery' AND status = 'active'
         ORDER BY display_order, id"
    );
    $all_gallery_images = $gi_stmt->fetchAll();
} catch (\Throwable $e) {
    error_log('packages/edit.php: failed to load gallery images — ' . $e->getMessage());
}

// Load existing gallery photo associations for this package
$existing_gallery_ids = [];
try {
    $pgpload = $db->prepare("SELECT site_image_id FROM package_gallery_photos WHERE package_id = ? ORDER BY display_order, id");
    $pgpload->execute([$package_id]);
    $existing_gallery_ids = $pgpload->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    // table may not exist yet on older installs
    error_log('packages/edit.php: failed to load package_gallery_photos — ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $features_raw  = $_POST['features'] ?? [];
    $features      = array_values(array_filter(array_map('intval', $features_raw)));
    // Venue/hall and menu associations
    $hall_ids = array_values(array_filter(array_map('intval', $_POST['package_hall_ids'] ?? [])));
    $menu_ids = array_values(array_filter(array_map('intval', $_POST['package_menu_ids'] ?? [])));
    // Gallery photo associations
    $gallery_ids = array_values(array_filter(array_map('intval', $_POST['gallery_image_ids'] ?? [])));
    // Photos to delete
    $delete_photo_ids = array_map('intval', $_POST['delete_photos'] ?? []);

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Handle new photo uploads before transaction
        $uploaded_photos = [];
        $photo_upload_error = '';
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $i => $fname) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
                $upload = handleImageUpload($file, 'pkg');
                if ($upload['success']) {
                    $uploaded_photos[] = $upload['filename'];
                } else {
                    $photo_upload_error = $upload['message'];
                    break;
                }
            }
        }

        if ($photo_upload_error) {
            foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
            $error_message = $photo_upload_error;
        } else {
            try {
                $db->beginTransaction();

                $upd = $db->prepare(
                    "UPDATE service_packages SET category_id=?, name=?, description=?, price=?, display_order=?, status=? WHERE id=?"
                );
                $upd->execute([$category_id, $name, $description, $price, $display_order, $status, $package_id]);

                // Replace features: delete old, insert new
                $db->prepare("DELETE FROM service_package_features WHERE package_id = ?")->execute([$package_id]);
                $svc_map = [];
                foreach ($all_services as $svc) { $svc_map[(int)$svc['id']] = $svc['name']; }
                $feat_ins = $db->prepare(
                    "INSERT INTO service_package_features (package_id, service_id, feature_text, display_order) VALUES (?, ?, ?, ?)"
                );
                foreach ($features as $i => $svc_id) {
                    $feat_name = $svc_map[$svc_id] ?? '';
                    if ($feat_name !== '') {
                        $feat_ins->execute([$package_id, $svc_id, $feat_name, $i + 1]);
                    } else {
                        error_log("edit package: unknown service_id $svc_id submitted for package $package_id; skipping feature.");
                    }
                }

                // Delete selected photos
                if (!empty($delete_photo_ids)) {
                    $del_stmt = $db->prepare("SELECT image_path FROM service_package_photos WHERE id = ? AND package_id = ?");
                    $rm_stmt  = $db->prepare("DELETE FROM service_package_photos WHERE id = ? AND package_id = ?");
                    foreach ($delete_photo_ids as $del_id) {
                        $del_stmt->execute([$del_id, $package_id]);
                        $del_row = $del_stmt->fetch();
                        if ($del_row) {
                            $rm_stmt->execute([$del_id, $package_id]);
                            deleteUploadedFile($del_row['image_path']);
                        }
                    }
                }

                // Insert new photos
                $photo_ins = $db->prepare(
                    "INSERT INTO service_package_photos (package_id, image_path, display_order) VALUES (?, ?, ?)"
                );
                foreach ($uploaded_photos as $i => $photo_path) {
                    $photo_ins->execute([$package_id, $photo_path, $i + 1]);
                }

                // Update venue/hall associations: replace all
                try {
                    $db->prepare("DELETE FROM package_venues WHERE package_id = ?")->execute([$package_id]);
                    if (!empty($hall_ids)) {
                        $pv_ins = $db->prepare(
                            "INSERT IGNORE INTO package_venues (package_id, hall_id) VALUES (?, ?)"
                        );
                        foreach ($hall_ids as $hid) {
                            $pv_ins->execute([$package_id, $hid]);
                        }
                    }
                } catch (\Throwable $pvErr) {
                    error_log("edit package: package_venues update failed: " . $pvErr->getMessage());
                }

                // Update menu associations: replace all
                try {
                    $db->prepare("DELETE FROM package_menus WHERE package_id = ?")->execute([$package_id]);
                    if (!empty($menu_ids)) {
                        $pm_ins = $db->prepare(
                            "INSERT IGNORE INTO package_menus (package_id, menu_id) VALUES (?, ?)"
                        );
                        foreach ($menu_ids as $mid) {
                            $pm_ins->execute([$package_id, $mid]);
                        }
                    }
                } catch (\Throwable $pmErr) {
                    error_log("edit package: package_menus update failed: " . $pmErr->getMessage());
                }

                // Update gallery photo associations: replace all
                try {
                    $db->prepare("DELETE FROM package_gallery_photos WHERE package_id = ?")->execute([$package_id]);
                    if (!empty($gallery_ids)) {
                        $pgp_ins = $db->prepare(
                            "INSERT IGNORE INTO package_gallery_photos (package_id, site_image_id, display_order) VALUES (?, ?, ?)"
                        );
                        foreach ($gallery_ids as $gi => $gid) {
                            $pgp_ins->execute([$package_id, $gid, $gi + 1]);
                        }
                    }
                } catch (\Throwable $pgpErr) {
                    error_log("edit package: package_gallery_photos update failed: " . $pgpErr->getMessage());
                }

                $db->commit();

                logActivity($current_user['id'], 'Updated service package', 'service_packages', $package_id, "Updated package: $name");

                $success_message = 'Package updated successfully!';

                // Refresh data
                $stmt->execute([$package_id]);
                $package = $stmt->fetch();
                // Re-load features using the same resilient query used at page load
                try {
                    $feat_stmt_r = $db->prepare(
                        "SELECT id, feature_text, service_id FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
                    );
                    $feat_stmt_r->execute([$package_id]);
                    $existing_features = $feat_stmt_r->fetchAll();
                } catch (Exception $eR) {
                    try {
                        $feat_stmt_r = $db->prepare(
                            "SELECT id, feature_text, NULL AS service_id FROM service_package_features WHERE package_id = ? ORDER BY display_order, id"
                        );
                        $feat_stmt_r->execute([$package_id]);
                        $existing_features = $feat_stmt_r->fetchAll();
                    } catch (Exception $eR2) {
                        $existing_features = [];
                    }
                }
                if (isset($photo_load_stmt)) {
                    try {
                        $photo_load_stmt->execute([$package_id]);
                        $existing_photos = $photo_load_stmt->fetchAll();
                    } catch (Exception $ePh) { /* ignore */ }
                }
                // Refresh hall/menu associations
                try {
                    $pvr = $db->prepare("SELECT hall_id FROM package_venues WHERE package_id = ?");
                    $pvr->execute([$package_id]);
                    $existing_hall_ids = $pvr->fetchAll(PDO::FETCH_COLUMN);
                } catch (\Throwable $e) { /* ignore */ }
                try {
                    $pmr = $db->prepare("SELECT menu_id FROM package_menus WHERE package_id = ?");
                    $pmr->execute([$package_id]);
                    $existing_menu_ids = $pmr->fetchAll(PDO::FETCH_COLUMN);
                } catch (\Throwable $e) { /* ignore */ }
                try {
                    $pgpr = $db->prepare("SELECT site_image_id FROM package_gallery_photos WHERE package_id = ? ORDER BY display_order, id");
                    $pgpr->execute([$package_id]);
                    $existing_gallery_ids = $pgpr->fetchAll(PDO::FETCH_COLUMN);
                } catch (\Throwable $e) { /* ignore */ }
            } catch (Exception $e) {
                $db->rollBack();
                foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Use POST values if a validation error occurred
$form = $_SERVER['REQUEST_METHOD'] === 'POST' && $error_message ? $_POST : $package;
// Collect selected service IDs for pre-checking the checkboxes.
// For legacy features stored without a service_id, try to match by name.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message) {
    $display_feature_ids = array_values(array_filter(array_map('intval', $_POST['features'] ?? [])));
} else {
    $display_feature_ids = [];
    $svc_name_to_id = [];
    foreach ($all_services as $svc) { $svc_name_to_id[strtolower(trim($svc['name']))] = (int)$svc['id']; }
    foreach ($existing_features as $feat) {
        if (!empty($feat['service_id'])) {
            $display_feature_ids[] = (int)$feat['service_id'];
        } else {
            // Backward compat: match by name for old text-only rows
            $key = strtolower(trim($feat['feature_text']));
            if (isset($svc_name_to_id[$key])) {
                $display_feature_ids[] = $svc_name_to_id[$key];
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Package</h5>
                <div>
                    <a href="view.php?id=<?php echo $package_id; ?>" class="btn btn-info btn-sm">
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
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Package Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($form['name'] ?? ''); ?>"
                                       placeholder="e.g., Silver Package" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Service Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int)$cat['id']; ?>"
                                            <?php if ((int)($form['category_id'] ?? 0) === (int)$cat['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (<?php echo htmlspecialchars(getSetting('currency', 'NPR')); ?>) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?php echo htmlspecialchars($form['price'] ?? ''); ?>"
                                       min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo (int)($form['display_order'] ?? 0); ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active"   <?php echo (($form['status'] ?? '') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($form['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($form['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Features (checkmark list)</label>
                        <p class="text-muted small mb-2">Select services from the list below to include as features in this package.</p>
                        <?php
                        $selected_features = array_flip($display_feature_ids);
                        if (!empty($all_services)):
                            $services_by_cat = [];
                            foreach ($all_services as $svc) {
                                $cat_label = $svc['vendor_type_label'] ?: 'Other';
                                $services_by_cat[$cat_label][] = $svc;
                            }
                        ?>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="featureSearch"
                                   placeholder="Search services..." autocomplete="off">
                        </div>
                        <div id="featuresContainer" style="max-height:400px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($services_by_cat as $cat_label => $svcs): ?>
                            <div class="feature-category-group mb-2">
                                <div class="fw-semibold text-secondary small text-uppercase px-1 mb-1"
                                     style="letter-spacing:.04em;"><?php echo htmlspecialchars($cat_label); ?></div>
                                <?php foreach ($svcs as $svc): ?>
                                <?php $svc_designs = $designs_by_service[(int)$svc['id']] ?? []; ?>
                                <div class="feature-item ms-2 mb-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="checkbox" name="features[]"
                                               id="feat_<?php echo (int)$svc['id']; ?>"
                                               value="<?php echo (int)$svc['id']; ?>"
                                               <?php echo isset($selected_features[(int)$svc['id']]) ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="feat_<?php echo (int)$svc['id']; ?>" style="cursor:pointer;">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($svc['photo'])): ?>
                                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($svc['photo']); ?>"
                                                         alt="<?php echo htmlspecialchars($svc['name']); ?>"
                                                         style="width:40px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light flex-shrink-0"
                                                         style="width:40px;height:40px;border-radius:4px;border:1px solid #dee2e6;">
                                                        <i class="fas fa-concierge-bell text-muted" style="font-size:0.85rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-medium" style="font-size:0.875rem;"><?php echo htmlspecialchars($svc['name']); ?></div>
                                                    <div class="text-success" style="font-size:0.8rem;"><?php echo formatCurrency($svc['price']); ?></div>
                                                </div>
                                            </div>
                                            <?php if (!empty($svc_designs)): ?>
                                            <div class="d-flex flex-wrap gap-2 mt-1 ms-1">
                                                <?php foreach ($svc_designs as $design): ?>
                                                <div class="d-flex align-items-center gap-1 text-muted"
                                                     style="font-size:0.75rem;background:#f8f9fa;border-radius:4px;padding:2px 5px;">
                                                    <?php if (!empty($design['photo'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($design['photo']); ?>"
                                                             alt="<?php echo htmlspecialchars($design['name']); ?>"
                                                             style="width:22px;height:22px;object-fit:cover;border-radius:3px;flex-shrink:0;">
                                                    <?php else: ?>
                                                        <i class="fas fa-image" style="font-size:0.7rem;"></i>
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($design['name']); ?></span>
                                                    <span class="text-success fw-medium"><?php echo formatCurrency($design['price']); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active services found. <a href="../services/index.php" class="alert-link">Add services first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Photos</label>
                        <?php if (!empty($existing_photos)): ?>
                            <div class="row g-2 mb-2" id="existingPhotos">
                                <?php foreach ($existing_photos as $photo): ?>
                                    <?php $photo_url = UPLOAD_URL . htmlspecialchars($photo['image_path']); ?>
                                    <div class="col-auto position-relative" id="photo-<?php echo (int)$photo['id']; ?>">
                                        <img src="<?php echo $photo_url; ?>" alt="Package photo"
                                             style="width:100px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                                        <div class="form-check position-absolute top-0 end-0 m-1">
                                            <input class="form-check-input" type="checkbox"
                                                   name="delete_photos[]"
                                                   value="<?php echo (int)$photo['id']; ?>"
                                                   id="delPhoto<?php echo (int)$photo['id']; ?>"
                                                   title="Mark for deletion"
                                                   onchange="this.closest('.col-auto').style.opacity = this.checked ? '0.4' : '1'">
                                        </div>
                                        <label class="d-block text-center mt-1" for="delPhoto<?php echo (int)$photo['id']; ?>"
                                               style="font-size:0.7rem;color:#dc3545;cursor:pointer;">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted d-block mb-2">Check the checkbox on a photo to remove it on save.</small>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="photos[]" accept="image/*" multiple>
                        <small class="text-muted">Upload additional photos (JPG, PNG, GIF, WebP; max 5MB each).</small>
                    </div>

                    <!-- Venue / Hall Assignment -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-building text-success me-1"></i> Available Venues &amp; Halls</label>
                        <p class="text-muted small mb-2">Select the halls where this package is available. When a user books this package they will choose from these halls (or the package will apply to any hall if none are selected).</p>
                        <?php
                        $sel_hall_ids = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message)
                            ? array_flip(array_map('intval', $_POST['package_hall_ids'] ?? []))
                            : array_flip(array_map('intval', $existing_hall_ids));
                        if (!empty($halls_by_venue)):
                        ?>
                        <div style="max-height:280px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($halls_by_venue as $vid => $venueData): ?>
                            <div class="mb-2">
                                <div class="fw-semibold text-secondary small text-uppercase px-1 mb-1"
                                     style="letter-spacing:.04em;">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($venueData['venue_name']); ?>
                                </div>
                                <?php foreach ($venueData['halls'] as $hall): ?>
                                <div class="form-check ms-3 mb-1">
                                    <input class="form-check-input" type="checkbox" name="package_hall_ids[]"
                                           id="hall_<?php echo (int)$hall['hall_id']; ?>"
                                           value="<?php echo (int)$hall['hall_id']; ?>"
                                           <?php echo isset($sel_hall_ids[(int)$hall['hall_id']]) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hall_<?php echo (int)$hall['hall_id']; ?>">
                                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                                        <span class="text-muted small ms-1">(<?php echo formatCurrency($hall['base_price']); ?>)</span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active halls found. <a href="../venues/index.php" class="alert-link">Add venues &amp; halls first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Menu Assignment -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-utensils text-success me-1"></i> Included Menus</label>
                        <p class="text-muted small mb-2">Select menus that are included in this package. Customers booking via this package will have these menus pre-selected.</p>
                        <?php
                        $sel_menu_ids = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message)
                            ? array_flip(array_map('intval', $_POST['package_menu_ids'] ?? []))
                            : array_flip(array_map('intval', $existing_menu_ids));
                        if (!empty($all_menus)):
                        ?>
                        <div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($all_menus as $menu): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="package_menu_ids[]"
                                       id="menu_<?php echo (int)$menu['id']; ?>"
                                       value="<?php echo (int)$menu['id']; ?>"
                                       <?php echo isset($sel_menu_ids[(int)$menu['id']]) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="menu_<?php echo (int)$menu['id']; ?>">
                                    <?php echo htmlspecialchars($menu['name']); ?>
                                    <span class="text-muted small ms-1">(<?php echo formatCurrency($menu['price_per_person']); ?>/person)</span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active menus found. <a href="../menus/index.php" class="alert-link">Add menus first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gallery Photos Association -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-images text-success me-1"></i> Gallery Photos</label>
                        <p class="text-muted small mb-2">Select photos from the general gallery to display alongside this package so users can see the kind of work done for this event type.</p>
                        <?php
                        $sel_gallery_ids = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message)
                            ? array_flip(array_map('intval', $_POST['gallery_image_ids'] ?? []))
                            : array_flip(array_map('intval', $existing_gallery_ids));
                        if (!empty($all_gallery_images)):
                        ?>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="gallerySearch"
                                   placeholder="Search gallery photos..." autocomplete="off">
                        </div>
                        <div id="galleryContainer" style="max-height:320px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <div class="row g-2">
                                <?php foreach ($all_gallery_images as $gimg): ?>
                                <div class="col-auto gallery-pick-item">
                                    <label class="d-block position-relative<?php echo isset($sel_gallery_ids[(int)$gimg['id']]) ? ' gallery-pick-selected' : ''; ?>"
                                           style="cursor:pointer;"
                                           title="<?php echo htmlspecialchars($gimg['title']); ?>">
                                        <input type="checkbox" class="form-check-input position-absolute top-0 start-0 m-1"
                                               name="gallery_image_ids[]"
                                               value="<?php echo (int)$gimg['id']; ?>"
                                               <?php echo isset($sel_gallery_ids[(int)$gimg['id']]) ? 'checked' : ''; ?>>
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($gimg['image_path']); ?>"
                                             class="gallery-pick-thumb"
                                             data-title="<?php echo htmlspecialchars(strtolower($gimg['title'])); ?>"
                                             alt="<?php echo htmlspecialchars($gimg['title']); ?>"
                                             loading="lazy">
                                        <div class="gallery-pick-label"><?php echo htmlspecialchars($gimg['title']); ?></div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle"></i>
                            No gallery photos found. <a href="../images/index.php" class="alert-link">Upload gallery photos first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <form method="POST" action="delete.php" style="display:inline;"
                              onsubmit="return confirm('Delete this package and all its features?');">
                            <input type="hidden" name="id" value="<?php echo $package_id; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Package
                            </button>
                        </form>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Package
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Live search filter for services checkboxes (matches on service name only)
document.getElementById('featureSearch')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#featuresContainer .feature-category-group').forEach(function (group) {
        let groupVisible = false;
        group.querySelectorAll('.feature-item').forEach(function (item) {
            const nameEl = item.querySelector('.fw-medium');
            const name = nameEl ? nameEl.textContent.toLowerCase() : '';
            const show = !q || name.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) groupVisible = true;
        });
        group.style.display = groupVisible ? '' : 'none';
    });
});

// Live search filter for gallery photo picker
document.getElementById('gallerySearch')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#galleryContainer .gallery-pick-item').forEach(function (item) {
        const img = item.querySelector('img');
        const title = img ? (img.getAttribute('data-title') || '') : '';
        item.style.display = (!q || title.includes(q)) ? '' : 'none';
    });
});

// Highlight selected gallery thumbnails
document.querySelectorAll('#galleryContainer input[type="checkbox"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
        this.closest('label').classList.toggle('gallery-pick-selected', this.checked);
    });
});
</script>

<style>
.gallery-pick-thumb {
    width: 90px;
    height: 70px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid transparent;
    display: block;
    transition: border-color .15s;
}
label.gallery-pick-selected .gallery-pick-thumb {
    border-color: #198754;
}
.gallery-pick-label {
    font-size: 0.68rem;
    text-align: center;
    max-width: 90px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    color: #555;
    margin-top: 2px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
