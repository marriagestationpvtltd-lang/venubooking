<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

$page_title = 'Add Gallery Card Group';
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
                "INSERT INTO gallery_card_groups (title, description, display_order, status, created_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$title, $description ?: null, $display_order, $status, $current_user['id']]);
            $new_id = $db->lastInsertId();
            logActivity($current_user['id'], 'Created gallery card group', 'gallery_card_groups', $new_id, "Created: $title");
            $_SESSION['success_message'] = "Gallery card group \"$title\" created successfully!";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error_message = 'Failed to create gallery card group: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-plus-circle"></i> Add Gallery Card Group</h4>
    <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-layer-group"></i> New Gallery Card Group</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i>
            Create a named gallery card group (e.g., <strong>Asmita &amp; Suman's Wedding</strong>).
            After creating the group, go to
            <a href="<?php echo BASE_URL; ?>/admin/images/add.php" class="alert-link">Upload Images</a>
            and select <em>General Gallery</em> as the section — you will then be able to assign
            images to this named group.
        </div>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="title" class="form-label">Group Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="e.g., Asmita &amp; Suman's Wedding"
                       required maxlength="255">
                <small class="text-muted">This title will be shown as the card label on the public gallery page.</small>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"
                          placeholder="Optional: short description about this event"><?php echo htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order"
                               value="<?php echo intval($_POST['display_order'] ?? 0); ?>" min="0">
                        <small class="text-muted">Lower number = shown first</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Create Group
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
