<?php
$page_title = 'Manage Vendors';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$stmt = $db->query("SELECT * FROM vendors ORDER BY type, name");
$vendors = $stmt->fetchAll();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';

unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

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

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-tie"></i> All Vendors</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Vendor</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?php echo $vendor['id']; ?></td>
                            <td>
                                <?php if (!empty($vendor['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $vendor['photo']); ?>"
                                         alt="<?php echo htmlspecialchars($vendor['name']); ?>"
                                         class="img-thumbnail" style="max-height:40px;max-width:40px;">
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                            <td><?php echo htmlspecialchars(getVendorTypeLabel($vendor['type'])); ?></td>
                            <td><?php echo htmlspecialchars($vendor['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($vendor['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($vendor['location'] ?? '—'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $vendor['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($vendor['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="delete.php" style="display: inline;"
                                      onsubmit="return confirm('Delete this vendor? This cannot be undone if the vendor has no assignments.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo $vendor['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No vendors found. <a href="add.php">Add your first vendor</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
