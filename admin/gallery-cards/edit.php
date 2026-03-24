<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

$group_id = intval($_GET['id'] ?? 0);
if ($group_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch group
$stmt = $db->prepare("SELECT * FROM gallery_card_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();
if (!$group) {
    $_SESSION['error_message'] = 'Gallery card group not found.';
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Gallery Card Group';
require_once __DIR__ . '/../includes/header.php';

$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    $status        = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($title)) {
        $error_message = 'Please enter a title for this gallery card group.';
    } else {
        try {
            $stmt = $db->prepare(
                "UPDATE gallery_card_groups
                    SET title = ?, description = ?, display_order = ?, status = ?, updated_at = NOW()
                  WHERE id = ?"
            );
            $stmt->execute([$title, $description ?: null, $display_order, $status, $group_id]);
            logActivity($current_user['id'], 'Updated gallery card group', 'gallery_card_groups', $group_id, "Updated: $title");
            $group['title']         = $title;
            $group['description']   = $description;
            $group['display_order'] = $display_order;
            $group['status']        = $status;
            $success_message        = 'Gallery card group updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Failed to update gallery card group: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-edit"></i> Edit Gallery Card Group</h4>
    <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-layer-group"></i> Group #<?php echo $group_id; ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="mb-3">
                <label for="title" class="form-label">Group Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="e.g., Asmita &amp; Suman's Wedding"
                       required maxlength="255">
                <small class="text-muted">This title is shown as the card label on the public gallery page.</small>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"
                          placeholder="Optional: short description about this event"><?php echo htmlspecialchars($group['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order"
                               value="<?php echo (int)$group['display_order']; ?>" min="0">
                        <small class="text-muted">Lower number = shown first</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $group['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $group['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
