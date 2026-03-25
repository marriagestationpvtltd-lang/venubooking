<?php
$page_title = 'Manage Menu Groups';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$menu_id    = isset($_GET['menu_id'])    ? intval($_GET['menu_id'])    : 0;

if ($section_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch section and menu
$stmt = $db->prepare("SELECT ms.*, m.name as menu_name, m.id as menu_id FROM menu_sections ms JOIN menus m ON m.id = ms.menu_id WHERE ms.id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();
if (!$section) {
    header('Location: index.php');
    exit;
}
$menu_id = intval($section['menu_id']);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $group_name              = trim($_POST['group_name'] ?? '');
        $choose_limit            = ($_POST['choose_limit'] ?? '') !== '' ? intval($_POST['choose_limit']) : null;
        $extra_charge_per_item   = floatval($_POST['extra_charge_per_item'] ?? 0);
        $display_order           = intval($_POST['display_order'] ?? 0);

        if (empty($group_name)) {
            $error = 'Group name is required.';
        } else {
            $photo_filename = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['photo'], 'menu_group');
                if ($upload_result['success']) {
                    $photo_filename = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            if (empty($error)) {
                try {
                    $db->prepare("INSERT INTO menu_groups (menu_section_id, group_name, photo, choose_limit, extra_charge_per_item, display_order) VALUES (?, ?, ?, ?, ?, ?)")
                       ->execute([$section_id, $group_name, $photo_filename, $choose_limit, $extra_charge_per_item, $display_order]);
                    $success = 'Group added successfully.';
                } catch (\Throwable $e) {
                    error_log("Add group error: " . $e->getMessage());
                    if ($photo_filename) deleteUploadedFile($photo_filename);
                    $error = 'Failed to add group.';
                }
            }
        }
    } elseif ($action === 'edit') {
        $group_id              = intval($_POST['group_id'] ?? 0);
        $group_name            = trim($_POST['group_name'] ?? '');
        $choose_limit          = ($_POST['choose_limit'] ?? '') !== '' ? intval($_POST['choose_limit']) : null;
        $extra_charge_per_item = floatval($_POST['extra_charge_per_item'] ?? 0);
        $display_order         = intval($_POST['display_order'] ?? 0);
        $status                = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if ($group_id <= 0 || empty($group_name)) {
            $error = 'Invalid data.';
        } else {
            // Fetch existing photo so we can delete it if replaced
            $existing_stmt = $db->prepare("SELECT photo FROM menu_groups WHERE id=? AND menu_section_id=?");
            $existing_stmt->execute([$group_id, $section_id]);
            $existing_photo = $existing_stmt->fetchColumn();

            $new_photo = $existing_photo; // keep existing by default
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['photo'], 'menu_group');
                if ($upload_result['success']) {
                    $new_photo = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            // Allow explicit removal of photo
            if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
                $new_photo = null;
            }

            if (empty($error)) {
                try {
                    $db->prepare("UPDATE menu_groups SET group_name=?, photo=?, choose_limit=?, extra_charge_per_item=?, display_order=?, status=? WHERE id=? AND menu_section_id=?")
                       ->execute([$group_name, $new_photo, $choose_limit, $extra_charge_per_item, $display_order, $status, $group_id, $section_id]);
                    // Delete old file only after successful update
                    if ($existing_photo && $existing_photo !== $new_photo) {
                        deleteUploadedFile($existing_photo);
                    }
                    $success = 'Group updated successfully.';
                } catch (\Throwable $e) {
                    error_log("Edit group error: " . $e->getMessage());
                    // Roll back newly uploaded file on DB failure
                    if ($new_photo && $new_photo !== $existing_photo) {
                        deleteUploadedFile($new_photo);
                    }
                    $error = 'Failed to update group.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $group_id = intval($_POST['group_id'] ?? 0);
        if ($group_id > 0) {
            try {
                $photo_stmt = $db->prepare("SELECT photo FROM menu_groups WHERE id=? AND menu_section_id=?");
                $photo_stmt->execute([$group_id, $section_id]);
                $photo_to_delete = $photo_stmt->fetchColumn();
                $db->prepare("DELETE FROM menu_groups WHERE id=? AND menu_section_id=?")
                   ->execute([$group_id, $section_id]);
                if ($photo_to_delete) deleteUploadedFile($photo_to_delete);
                $success = 'Group deleted.';
            } catch (\Throwable $e) {
                error_log("Delete group error: " . $e->getMessage());
                $error = 'Failed to delete group.';
            }
        }
    }
}

// Fetch groups
$groups_stmt = $db->prepare("SELECT * FROM menu_groups WHERE menu_section_id = ? ORDER BY display_order, id");
$groups_stmt->execute([$section_id]);
$groups = $groups_stmt->fetchAll();
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-object-group"></i> Groups in: <strong><?php echo sanitize($section['section_name']); ?></strong></h4>
    <div>
        <a href="sections.php?menu_id=<?php echo $menu_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Sections</a>
    </div>
</div>
<p class="text-muted">Menu: <a href="view.php?id=<?php echo $menu_id; ?>"><?php echo sanitize($section['menu_name']); ?></a></p>

<!-- Add Group Form -->
<div class="card mb-4">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-plus"></i> Add New Group</h6></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Group Name <span class="text-danger">*</span></label>
                    <input type="text" name="group_name" class="form-control" placeholder="e.g. VEG STARTERS, NON-VEG STARTERS" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Photo <small class="text-muted">(optional, shown as icon)</small></label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Choose Limit <small class="text-muted">(blank = section limit)</small></label>
                    <input type="number" name="choose_limit" class="form-control" min="1" placeholder="e.g. 5">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Over-Limit Charge <small class="text-muted">(per extra item)</small></label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" name="extra_charge_per_item" class="form-control" value="0" min="0" step="0.01" placeholder="0">
                    </div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <input type="number" name="display_order" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> Add Group</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Groups List -->
<div class="card">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-list"></i> Groups (<?php echo count($groups); ?>)</h6></div>
    <div class="card-body p-0">
        <?php if (empty($groups)): ?>
            <div class="p-3"><div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> No groups yet. Add the first group above.</div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Group Name</th>
                        <th>Photo</th>
                        <th>Choose Limit</th>
                        <th>Over-Limit Charge</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                    <?php
                    $item_count_stmt = $db->prepare("SELECT COUNT(*) FROM menu_group_items WHERE menu_group_id = ? AND status='active'");
                    $item_count_stmt->execute([$group['id']]);
                    $item_count = $item_count_stmt->fetchColumn();
                    ?>
                    <tr>
                        <td><?php echo intval($group['display_order']); ?></td>
                        <td><strong><?php echo sanitize($group['group_name']); ?></strong></td>
                        <td>
                            <?php if (!empty($group['photo'])): ?>
                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($group['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%;border:2px solid #e5e7eb;">
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $group['choose_limit'] !== null ? intval($group['choose_limit']) : '<em class="text-muted">Section-level</em>'; ?></td>
                        <td><?php echo floatval($group['extra_charge_per_item'] ?? 0) > 0 ? '<span class="badge bg-warning text-dark">' . formatCurrency(floatval($group['extra_charge_per_item'])) . '/item</span>' : '<em class="text-muted">None</em>'; ?></td>
                        <td><span class="badge bg-<?php echo $group['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst(sanitize($group['status'])); ?></span></td>
                        <td><a href="group-items.php?group_id=<?php echo $group['id']; ?>&section_id=<?php echo $section_id; ?>&menu_id=<?php echo $menu_id; ?>" class="badge bg-primary text-decoration-none"><?php echo $item_count; ?> items</a></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editGroupModal<?php echo $group['id']; ?>"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this group and all its items?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <a href="group-items.php?group_id=<?php echo $group['id']; ?>&section_id=<?php echo $section_id; ?>&menu_id=<?php echo $menu_id; ?>" class="btn btn-sm btn-info"><i class="fas fa-list"></i> Items</a>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editGroupModal<?php echo $group['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Group</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Group Name <span class="text-danger">*</span></label>
                                            <input type="text" name="group_name" class="form-control" value="<?php echo sanitize($group['group_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Photo <small class="text-muted">(optional, shown as icon next to group name)</small></label>
                                            <?php if (!empty($group['photo'])): ?>
                                                <div class="mb-2 d-flex align-items-center gap-2">
                                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($group['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         alt="" style="width:50px;height:50px;object-fit:cover;border-radius:50%;border:2px solid #e5e7eb;">
                                                    <div>
                                                        <small class="text-muted d-block">Current photo</small>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto<?php echo $group['id']; ?>">
                                                            <label class="form-check-label text-danger small" for="removePhoto<?php echo $group['id']; ?>">Remove photo</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="photo" class="form-control" accept="image/*">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Choose Limit <small class="text-muted">(blank = use section limit)</small></label>
                                            <input type="number" name="choose_limit" class="form-control" min="1" value="<?php echo $group['choose_limit'] !== null ? intval($group['choose_limit']) : ''; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Over-Limit Charge <small class="text-muted">(per extra item beyond Choose Limit)</small></label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rs.</span>
                                                <input type="number" name="extra_charge_per_item" class="form-control" min="0" step="0.01" value="<?php echo floatval($group['extra_charge_per_item'] ?? 0); ?>">
                                            </div>
                                            <div class="form-text">If a customer selects more than the Choose Limit, each extra item will cost this amount.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" value="<?php echo intval($group['display_order']); ?>" min="0">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="active" <?php echo $group['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $group['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
