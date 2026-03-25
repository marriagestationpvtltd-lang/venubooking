<?php
$page_title = 'Policy Pages';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success = '';
$error   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $db->prepare('DELETE FROM policy_pages WHERE id = ?');
                    $stmt->execute([$id]);
                    logActivity($current_user['id'], 'Deleted policy page', 'policy_pages', $id, 'Deleted policy page ID: ' . $id);
                    $success = 'Policy page deleted successfully.';
                } catch (\Exception $e) {
                    $error = 'Failed to delete: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $db->prepare("UPDATE policy_pages SET status = IF(status='active','inactive','active') WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Status updated.';
                } catch (\Exception $e) {
                    $error = 'Failed to update status: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all pages
$pages = $db->query('SELECT * FROM policy_pages ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Policy Pages</h5>
            <small class="text-muted">Manage Terms &amp; Conditions, Privacy Policy, Refund Policy, and more.</small>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/policy-pages/add.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus me-1"></i> Add New Page
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pages)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-file-alt fa-3x mb-3 d-block"></i>
                No policy pages yet.
                <a href="<?php echo BASE_URL; ?>/admin/policy-pages/add.php">Add your first one.</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Slug (URL)</th>
                        <th class="text-center">Requires Acceptance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?php echo $page['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($page['title']); ?></strong></td>
                        <td>
                            <code><?php echo htmlspecialchars($page['slug']); ?></code>
                            <a href="<?php echo BASE_URL; ?>/policy.php?slug=<?php echo urlencode($page['slug']); ?>"
                               target="_blank" class="ms-1 text-muted small" title="View public page">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td class="text-center">
                            <?php if ($page['require_acceptance']): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-check-circle me-1"></i>Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $page['status'] === 'active' ? 'btn-success' : 'btn-secondary'; ?> border-0">
                                    <?php echo $page['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-center"><?php echo $page['sort_order']; ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/admin/policy-pages/edit.php?id=<?php echo $page['id']; ?>"
                               class="btn btn-sm btn-outline-primary me-1">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Are you sure you want to delete this policy page? This action cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3 small text-muted">
    <i class="fas fa-info-circle me-1"></i>
    Pages with <strong>Requires Acceptance = Yes</strong> will show a mandatory checkbox on the booking confirmation step (booking-step6).
</div>
