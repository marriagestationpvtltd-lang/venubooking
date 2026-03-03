<?php
$page_title = 'Vendor Types';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $label         = trim($_POST['label'] ?? '');
            $slug          = trim($_POST['slug']  ?? '');
            $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
            $display_order = intval($_POST['display_order'] ?? 0);
            $id            = intval($_POST['id'] ?? 0);

            // Auto-generate slug from label if not provided
            if (empty($slug) && !empty($label)) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
                $slug = trim($slug, '_');
            }

            if (empty($label)) {
                $error = 'Vendor type label is required.';
            } elseif (empty($slug) || !preg_match('/^[a-z0-9_]+$/', $slug)) {
                $error = 'Slug must contain only lowercase letters, numbers, and underscores.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $db->prepare("INSERT INTO vendor_types (slug, label, status, display_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$slug, $label, $status, $display_order]);
                        logActivity($current_user['id'], 'Added vendor type', 'vendor_types', $db->lastInsertId(), "Added vendor type: $label ($slug)");
                        $success = 'Vendor type added successfully!';
                    } else {
                        // Check slug not used by another type
                        $check = $db->prepare("SELECT id FROM vendor_types WHERE slug = ? AND id != ?");
                        $check->execute([$slug, $id]);
                        if ($check->fetch()) {
                            $error = 'That slug is already used by another vendor type.';
                        } else {
                            $stmt = $db->prepare("UPDATE vendor_types SET slug = ?, label = ?, status = ?, display_order = ? WHERE id = ?");
                            $stmt->execute([$slug, $label, $status, $display_order, $id]);
                            logActivity($current_user['id'], 'Updated vendor type', 'vendor_types', $id, "Updated vendor type: $label ($slug)");
                            $success = 'Vendor type updated successfully!';
                        }
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $error = 'A vendor type with that slug already exists.';
                    } else {
                        $error = 'Error saving vendor type. Please try again.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            try {
                // Check if any vendor uses this type
                $check = $db->prepare("SELECT COUNT(*) FROM vendors WHERE type = (SELECT slug FROM vendor_types WHERE id = ?)");
                $check->execute([$id]);
                $count = $check->fetchColumn();
                if ($count > 0) {
                    $error = "Cannot delete: $count vendor(s) are assigned to this type. Reassign them first.";
                } else {
                    $stmt = $db->prepare("SELECT label, slug FROM vendor_types WHERE id = ?");
                    $stmt->execute([$id]);
                    $vt = $stmt->fetch();
                    if ($vt) {
                        $del = $db->prepare("DELETE FROM vendor_types WHERE id = ?");
                        $del->execute([$id]);
                        logActivity($current_user['id'], 'Deleted vendor type', 'vendor_types', $id, "Deleted vendor type: {$vt['label']} ({$vt['slug']})");
                        $success = 'Vendor type deleted successfully!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error deleting vendor type: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all vendor types
$stmt = $db->query("SELECT vt.*, (SELECT COUNT(*) FROM vendors v WHERE v.type = vt.slug) AS vendor_count FROM vendor_types vt ORDER BY display_order ASC, label ASC");
$vendor_types = $stmt->fetchAll();
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="fas fa-tags"></i> Vendor Types</h5>
            <small class="text-muted">Manage the types used when adding or editing vendors</small>
        </div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTypeModal">
            <i class="fas fa-plus"></i> Add Vendor Type
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Slug</th>
                        <th>Vendors</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendor_types as $vt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vt['label']); ?></td>
                            <td><code><?php echo htmlspecialchars($vt['slug']); ?></code></td>
                            <td><?php echo intval($vt['vendor_count']); ?></td>
                            <td><?php echo intval($vt['display_order']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $vt['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($vt['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editType(<?php echo htmlspecialchars(json_encode($vt), ENT_QUOTES); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Delete this vendor type? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $vt['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            <?php echo intval($vt['vendor_count']) > 0 ? 'disabled title="Vendors use this type"' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($vendor_types)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No vendor types found. <a href="#" data-bs-toggle="modal" data-bs-target="#addTypeModal">Add the first one</a>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addTypeForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Vendor Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_label" class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_label" name="label" required
                               placeholder="e.g., DJ Service" oninput="autoSlug(this)">
                        <div class="form-text">Human-readable name shown in forms and reports.</div>
                    </div>
                    <div class="mb-3">
                        <label for="add_slug" class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_slug" name="slug" required
                               placeholder="e.g., dj_service" pattern="[a-z0-9_]+"
                               title="Lowercase letters, numbers, and underscores only">
                        <div class="form-text">Unique identifier stored in the database. Auto-filled from the label.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="add_display_order" name="display_order"
                                   value="<?php echo count($vendor_types) + 1; ?>" min="0">
                            <div class="form-text">Lower numbers appear first.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Add Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editTypeForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Vendor Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_label" class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_label" name="label" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_slug" class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_slug" name="slug" required
                               pattern="[a-z0-9_]+"
                               title="Lowercase letters, numbers, and underscores only">
                        <div class="form-text">Changing the slug will break the association with existing vendors that already use the old slug — those vendors will show an unrecognised type until manually corrected.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoSlug(labelInput) {
    const slugInput = document.getElementById('add_slug');
    // Only auto-fill if the user hasn't manually edited the slug yet
    if (!slugInput.dataset.manualEdit) {
        const slug = labelInput.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '');
        slugInput.value = slug;
    }
}

document.getElementById('add_slug').addEventListener('input', function() {
    this.dataset.manualEdit = '1';
});

function editType(vt) {
    document.getElementById('edit_id').value = vt.id;
    document.getElementById('edit_label').value = vt.label;
    document.getElementById('edit_slug').value = vt.slug;
    document.getElementById('edit_status').value = vt.status;
    document.getElementById('edit_display_order').value = vt.display_order;
    new bootstrap.Modal(document.getElementById('editTypeModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
