<?php
$page_title = 'Add Service Package';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message   = '';

// Load categories for dropdown
$cat_stmt = $db->query("SELECT id, name FROM service_categories WHERE status = 'active' ORDER BY display_order, name");
$categories = $cat_stmt->fetchAll();

// Pre-select category if passed in URL
$preselect_cat = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Load all active services for the features checkboxes
$all_services = getActiveServices();

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
    error_log('packages/add.php: failed to load halls — ' . $e->getMessage());
}

// Load all active menus
$all_menus = [];
try {
    $mstmt = $db->query("SELECT id, name, price_per_person FROM menus WHERE status = 'active' ORDER BY name");
    $all_menus = $mstmt->fetchAll();
} catch (\Throwable $e) {
    error_log('packages/add.php: failed to load menus — ' . $e->getMessage());
}

// Load gallery images grouped by Gallery Card Groups for the photo selector
$gallery_groups = [];
try {
    $gi_stmt = $db->query(
        "SELECT si.id, si.title, si.image_path, si.card_group_id,
                COALESCE(gcg.title, 'Other Photos') AS card_group_title,
                COALESCE(gcg.display_order, 99999) AS group_display_order
         FROM site_images si
         LEFT JOIN gallery_card_groups gcg
                ON si.card_group_id = gcg.id AND gcg.status = 'active'
         WHERE si.section = 'gallery' AND si.status = 'active'
         ORDER BY
             CASE WHEN si.card_group_id IS NOT NULL THEN COALESCE(gcg.display_order, 0) ELSE 99999 END ASC,
             CASE WHEN si.card_group_id IS NOT NULL THEN si.card_group_id ELSE si.card_id END ASC,
             si.display_order ASC,
             si.id ASC"
    );
    foreach ($gi_stmt->fetchAll() as $row) {
        $gkey = $row['card_group_id'] ? 'g_' . (int)$row['card_group_id'] : 'ungrouped';
        if (!isset($gallery_groups[$gkey])) {
            $gallery_groups[$gkey] = [
                'title'  => $row['card_group_title'],
                'images' => [],
            ];
        }
        $gallery_groups[$gkey]['images'][] = $row;
    }
} catch (\Throwable $e) {
    error_log('packages/add.php: failed to load gallery images — ' . $e->getMessage());
}

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
            error_log('packages/add.php: failed to load service designs — ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    // Features: array of service IDs chosen from the checkbox list
    $features_raw = $_POST['features'] ?? [];
    $features     = array_values(array_filter(array_map('intval', $features_raw)));
    // Venue/hall associations
    $hall_ids_raw = $_POST['package_hall_ids'] ?? [];
    $hall_ids     = array_values(array_filter(array_map('intval', $hall_ids_raw)));
    // Menu associations
    $menu_ids_raw = $_POST['package_menu_ids'] ?? [];
    $menu_ids     = array_values(array_filter(array_map('intval', $menu_ids_raw)));
    // Gallery photo associations
    $gallery_ids_raw = $_POST['gallery_image_ids'] ?? [];
    $gallery_ids     = array_values(array_filter(array_map('intval', $gallery_ids_raw)));

    if (empty($name) || $category_id <= 0 || $price < 0) {
        $error_message = 'Please fill in all required fields correctly.';
    } else {
        // Handle photo uploads before transaction
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
            // Clean up any already-uploaded photos
            foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
            $error_message = $photo_upload_error;
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare(
                    "INSERT INTO service_packages (category_id, name, description, price, display_order, status)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$category_id, $name, $description, $price, $display_order, $status]);
                $package_id = $db->lastInsertId();

                // Insert features – store both service_id and feature_text (name)
                $svc_map = [];
                foreach ($all_services as $svc) { $svc_map[(int)$svc['id']] = $svc['name']; }
                $feat_stmt = $db->prepare(
                    "INSERT INTO service_package_features (package_id, service_id, feature_text, display_order) VALUES (?, ?, ?, ?)"
                );
                foreach ($features as $i => $svc_id) {
                    $feat_name = $svc_map[$svc_id] ?? '';
                    if ($feat_name !== '') {
                        $feat_stmt->execute([$package_id, $svc_id, $feat_name, $i + 1]);
                    } else {
                        error_log("add package: unknown service_id $svc_id submitted for package $package_id; skipping feature.");
                    }
                }

                // Insert photos
                $photo_stmt = $db->prepare(
                    "INSERT INTO service_package_photos (package_id, image_path, display_order) VALUES (?, ?, ?)"
                );
                foreach ($uploaded_photos as $i => $photo_path) {
                    $photo_stmt->execute([$package_id, $photo_path, $i + 1]);
                }

                // Insert venue/hall associations
                if (!empty($hall_ids)) {
                    try {
                        $pv_stmt = $db->prepare(
                            "INSERT IGNORE INTO package_venues (package_id, hall_id) VALUES (?, ?)"
                        );
                        foreach ($hall_ids as $hid) {
                            $pv_stmt->execute([$package_id, $hid]);
                        }
                    } catch (\Throwable $pvErr) {
                        error_log("add package: package_venues INSERT failed: " . $pvErr->getMessage());
                    }
                }

                // Insert menu associations
                if (!empty($menu_ids)) {
                    try {
                        $pm_stmt = $db->prepare(
                            "INSERT IGNORE INTO package_menus (package_id, menu_id) VALUES (?, ?)"
                        );
                        foreach ($menu_ids as $mid) {
                            $pm_stmt->execute([$package_id, $mid]);
                        }
                    } catch (\Throwable $pmErr) {
                        error_log("add package: package_menus INSERT failed: " . $pmErr->getMessage());
                    }
                }

                // Insert gallery photo associations
                if (!empty($gallery_ids)) {
                    try {
                        $pgp_stmt = $db->prepare(
                            "INSERT IGNORE INTO package_gallery_photos (package_id, site_image_id, display_order) VALUES (?, ?, ?)"
                        );
                        foreach ($gallery_ids as $gi => $gid) {
                            $pgp_stmt->execute([$package_id, $gid, $gi + 1]);
                        }
                    } catch (\Throwable $pgpErr) {
                        error_log("add package: package_gallery_photos INSERT failed: " . $pgpErr->getMessage());
                    }
                }

                $db->commit();

                logActivity($current_user['id'], 'Added service package', 'service_packages', $package_id, "Added package: $name");

                $success_message = 'Package added successfully!';
                $_POST = [];
                $preselect_cat = $category_id;
            } catch (Exception $e) {
                $db->rollBack();
                foreach ($uploaded_photos as $f) { deleteUploadedFile($f); }
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
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Package</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <a href="index.php" class="alert-link">View all packages</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($categories)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        No active categories found.
                        <a href="categories.php" class="alert-link">Add a category first.</a>
                    </div>
                <?php else: ?>
                <form method="POST" action="" id="packageForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Package Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
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
                                            <?php if ((int)($preselect_cat ?: ($_POST['category_id'] ?? 0)) === (int)$cat['id']) echo 'selected'; ?>>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       min="0" step="0.01" placeholder="e.g., 50000.00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order"
                                       value="<?php echo (int)($_POST['display_order'] ?? 0); ?>"
                                       min="0" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active"   <?php echo (($_POST['status'] ?? 'active') === 'active')   ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of this package..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Package Features (checkmark list)</label>
                        <p class="text-muted small mb-2">Select services from the list below to include as features in this package.</p>
                        <?php
                        $selected_features = array_flip(array_map('intval', $_POST['features'] ?? []));
                        if (!empty($all_services)):
                            // Group services by category
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
                        <input type="file" class="form-control" name="photos[]" accept="image/*" multiple>
                        <small class="text-muted">You can select multiple photos (JPG, PNG, GIF, WebP; max 5MB each).</small>
                    </div>

                    <!-- Venue / Hall Assignment -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-building text-success me-1"></i> Available Venues &amp; Halls</label>
                        <p class="text-muted small mb-2">Select the halls where this package is available. When a user books this package they will choose from these halls (or the package will apply to any hall if none are selected).</p>
                        <?php
                        $selected_hall_ids_post = array_flip(array_map('intval', $_POST['package_hall_ids'] ?? []));
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
                                           <?php echo isset($selected_hall_ids_post[(int)$hall['hall_id']]) ? 'checked' : ''; ?>>
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
                        $selected_menu_ids_post = array_flip(array_map('intval', $_POST['package_menu_ids'] ?? []));
                        if (!empty($all_menus)):
                        ?>
                        <div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($all_menus as $menu): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="package_menu_ids[]"
                                       id="menu_<?php echo (int)$menu['id']; ?>"
                                       value="<?php echo (int)$menu['id']; ?>"
                                       <?php echo isset($selected_menu_ids_post[(int)$menu['id']]) ? 'checked' : ''; ?>>
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
                        <p class="text-muted small mb-2">Select photos from the gallery grouped by Gallery Card Groups to display alongside this package.</p>
                        <?php
                        $selected_gallery_ids_post = array_flip(array_map('intval', $_POST['gallery_image_ids'] ?? []));
                        if (!empty($gallery_groups)):
                        ?>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="gallerySearch"
                                   placeholder="Search gallery photos..." autocomplete="off">
                        </div>
                        <div id="galleryContainer" style="max-height:400px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($gallery_groups as $gkey => $groupData): ?>
                            <div class="gallery-card-group mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-1 pb-1"
                                     style="border-bottom:1px solid #dee2e6;">
                                    <span class="fw-semibold text-secondary small text-uppercase" style="letter-spacing:.04em;">
                                        <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($groupData['title']); ?>
                                        <span class="text-muted fw-normal">(<?php echo count($groupData['images']); ?>)</span>
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-success gallery-group-toggle"
                                            data-group="<?php echo htmlspecialchars($gkey); ?>">Select All</button>
                                </div>
                                <div class="row g-2">
                                    <?php foreach ($groupData['images'] as $gimg): ?>
                                    <div class="col-auto gallery-pick-item" data-group="<?php echo htmlspecialchars($gkey); ?>">
                                        <label class="d-block position-relative<?php echo isset($selected_gallery_ids_post[(int)$gimg['id']]) ? ' gallery-pick-selected' : ''; ?>"
                                               style="cursor:pointer;"
                                               title="<?php echo htmlspecialchars($gimg['title']); ?>">
                                            <input type="checkbox" class="form-check-input position-absolute top-0 start-0 m-1"
                                                   name="gallery_image_ids[]"
                                                   value="<?php echo (int)$gimg['id']; ?>"
                                                   <?php echo isset($selected_gallery_ids_post[(int)$gimg['id']]) ? 'checked' : ''; ?>>
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
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle"></i>
                            No gallery photos found. <a href="../images/index.php" class="alert-link">Upload gallery photos first.</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Package
                        </button>
                    </div>
                </form>
                <?php endif; ?>
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

// Live search filter for gallery photo picker (works across card groups)
document.getElementById('gallerySearch')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#galleryContainer .gallery-card-group').forEach(function (group) {
        let groupVisible = false;
        group.querySelectorAll('.gallery-pick-item').forEach(function (item) {
            const img = item.querySelector('img');
            const title = img ? (img.getAttribute('data-title') || '') : '';
            const show = !q || title.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) groupVisible = true;
        });
        group.style.display = groupVisible ? '' : 'none';
    });
});

// Highlight selected gallery thumbnails
document.querySelectorAll('#galleryContainer input[type="checkbox"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
        this.closest('label').classList.toggle('gallery-pick-selected', this.checked);
    });
    if (cb.checked) cb.closest('label').classList.add('gallery-pick-selected');
});

// Select All / Deselect All per gallery card group
document.querySelectorAll('.gallery-group-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const group = this.dataset.group;
        const items = document.querySelectorAll(
            '#galleryContainer .gallery-pick-item[data-group="' + group + '"] input[type="checkbox"]'
        );
        const allChecked = Array.from(items).every(function (cb) { return cb.checked; });
        items.forEach(function (cb) {
            cb.checked = !allChecked;
            cb.closest('label').classList.toggle('gallery-pick-selected', cb.checked);
        });
        this.textContent = allChecked ? 'Select All' : 'Deselect All';
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
