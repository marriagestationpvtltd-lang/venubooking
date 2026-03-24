<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

$page_title = 'Gallery Card Groups';
require_once __DIR__ . '/../includes/header.php';

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all gallery card groups with photo counts
$groups = $db->query(
    "SELECT gcg.id, gcg.title, gcg.description, gcg.display_order, gcg.status,
            gcg.created_at,
            COUNT(si.id) AS photo_count
     FROM gallery_card_groups gcg
     LEFT JOIN site_images si ON si.card_group_id = gcg.id AND si.status = 'active'
     GROUP BY gcg.id
     ORDER BY gcg.display_order ASC, gcg.created_at DESC"
)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-layer-group"></i> Gallery Card Groups</h4>
    <a href="add.php" class="btn btn-success">
        <i class="fas fa-plus"></i> New Card Group
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
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-info-circle text-primary"></i>
            <span>
                Gallery card groups let you create <strong>separate named cards</strong> for different events
                (e.g., <em>Asmita &amp; Suman's Wedding</em>, <em>Bina &amp; Rajan's Wedding</em>).
                Upload photos to each group via
                <a href="<?php echo BASE_URL; ?>/admin/images/add.php">Images &rarr; Upload</a>.
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($groups)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-layer-group fa-3x mb-3"></i>
            <p class="mb-0">No gallery card groups yet.</p>
            <a href="add.php" class="btn btn-success mt-3">
                <i class="fas fa-plus"></i> Create First Group
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th class="text-center">Photos</th>
                        <th class="text-center">Order</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $group): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <br><small class="text-muted">ID: <?php echo $group['id']; ?></small>
                    </td>
                    <td class="text-muted small">
                        <?php echo htmlspecialchars(mb_strimwidth($group['description'] ?? '', 0, 80, '…'), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?php echo (int)$group['photo_count']; ?></span>
                    </td>
                    <td class="text-center"><?php echo (int)$group['display_order']; ?></td>
                    <td class="text-center">
                        <?php if ($group['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a href="edit.php?id=<?php echo $group['id']; ?>"
                               class="btn btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete.php?id=<?php echo $group['id']; ?>"
                               class="btn btn-outline-danger"
                               title="Delete"
                               onclick="return confirm('Delete this card group? Photos in this group will become ungrouped.');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
