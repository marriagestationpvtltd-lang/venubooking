<?php
$page_title = 'Manage Menu Sections';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;

if ($menu_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch menu
$stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$menu_id]);
$menu = $stmt->fetch();
if (!$menu) {
    header('Location: index.php');
    exit;
}

$success = '';
$error   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $section_name = trim($_POST['section_name'] ?? '');
        $choose_limit = ($_POST['choose_limit'] ?? '') !== '' ? intval($_POST['choose_limit']) : null;
        $display_order = intval($_POST['display_order'] ?? 0);

        if (empty($section_name)) {
            $error = 'Section name is required.';
        } else {
            try {
                $db->prepare("INSERT INTO menu_sections (menu_id, section_name, choose_limit, display_order) VALUES (?, ?, ?, ?)")
                   ->execute([$menu_id, $section_name, $choose_limit, $display_order]);
                $success = 'Section added successfully.';
            } catch (\Throwable $e) {
                error_log("Add section error: " . $e->getMessage());
                $error = 'Failed to add section.';
            }
        }
    } elseif ($action === 'edit') {
        $section_id   = intval($_POST['section_id'] ?? 0);
        $section_name = trim($_POST['section_name'] ?? '');
        $choose_limit = ($_POST['choose_limit'] ?? '') !== '' ? intval($_POST['choose_limit']) : null;
        $display_order = intval($_POST['display_order'] ?? 0);
        $status        = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if ($section_id <= 0 || empty($section_name)) {
            $error = 'Invalid data.';
        } else {
            try {
                $db->prepare("UPDATE menu_sections SET section_name=?, choose_limit=?, display_order=?, status=? WHERE id=? AND menu_id=?")
                   ->execute([$section_name, $choose_limit, $display_order, $status, $section_id, $menu_id]);
                $success = 'Section updated successfully.';
            } catch (\Throwable $e) {
                error_log("Edit section error: " . $e->getMessage());
                $error = 'Failed to update section.';
            }
        }
    } elseif ($action === 'delete') {
        $section_id = intval($_POST['section_id'] ?? 0);
        if ($section_id > 0) {
            try {
                $db->prepare("DELETE FROM menu_sections WHERE id=? AND menu_id=?")
                   ->execute([$section_id, $menu_id]);
                $success = 'Section deleted.';
            } catch (\Throwable $e) {
                error_log("Delete section error: " . $e->getMessage());
                $error = 'Failed to delete section.';
            }
        }
    }
}

// Fetch sections
$sections_stmt = $db->prepare("SELECT * FROM menu_sections WHERE menu_id = ? ORDER BY display_order, id");
$sections_stmt->execute([$menu_id]);
$sections = $sections_stmt->fetchAll();
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-layer-group"></i> Sections for: <strong><?php echo sanitize($menu['name']); ?></strong></h4>
    <div>
        <a href="view.php?id=<?php echo $menu_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Menu</a>
    </div>
</div>

<!-- Add Section Form -->
<div class="card mb-4">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-plus"></i> Add New Section</h6></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Section Name <span class="text-danger">*</span></label>
                    <input type="text" name="section_name" class="form-control" placeholder="e.g. SOUP, STARTERS" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Choose Limit <small class="text-muted">(blank = use group limits)</small></label>
                    <input type="number" name="choose_limit" class="form-control" min="1" placeholder="e.g. 2">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Display Order</label>
                    <input type="number" name="display_order" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> Add Section</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sections List -->
<div class="card">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-list"></i> Sections (<?php echo count($sections); ?>)</h6></div>
    <div class="card-body p-0">
        <?php if (empty($sections)): ?>
            <div class="p-3"><div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> No sections yet. Add the first section above.</div></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Section Name</th>
                        <th>Choose Limit</th>
                        <th>Status</th>
                        <th>Groups</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sections as $section): ?>
                    <?php
                    $grp_count_stmt = $db->prepare("SELECT COUNT(*) FROM menu_groups WHERE menu_section_id = ? AND status='active'");
                    $grp_count_stmt->execute([$section['id']]);
                    $grp_count = $grp_count_stmt->fetchColumn();
                    ?>
                    <tr>
                        <td><?php echo intval($section['display_order']); ?></td>
                        <td><strong><?php echo sanitize($section['section_name']); ?></strong></td>
                        <td><?php echo $section['choose_limit'] !== null ? intval($section['choose_limit']) : '<em class="text-muted">Group-level</em>'; ?></td>
                        <td><span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst(sanitize($section['status'])); ?></span></td>
                        <td><a href="groups.php?section_id=<?php echo $section['id']; ?>&menu_id=<?php echo $menu_id; ?>" class="badge bg-primary text-decoration-none"><?php echo $grp_count; ?> groups</a></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $section['id']; ?>"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this section and all its groups/items?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <a href="groups.php?section_id=<?php echo $section['id']; ?>&menu_id=<?php echo $menu_id; ?>" class="btn btn-sm btn-info"><i class="fas fa-layer-group"></i> Groups</a>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?php echo $section['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Section</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Section Name <span class="text-danger">*</span></label>
                                            <input type="text" name="section_name" class="form-control" value="<?php echo sanitize($section['section_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Choose Limit <small class="text-muted">(blank = use group limits)</small></label>
                                            <input type="number" name="choose_limit" class="form-control" min="1" value="<?php echo $section['choose_limit'] !== null ? intval($section['choose_limit']) : ''; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" value="<?php echo intval($section['display_order']); ?>" min="0">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="active" <?php echo $section['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $section['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
