<?php
$page_title = 'Manage Vendors';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$stmt = $db->query("
    SELECT v.*, c.name AS city_name,
           COALESCE(SUM(CASE WHEN bva.status != 'cancelled' THEN bva.assigned_amount ELSE 0 END), 0) AS total_receivable
    FROM vendors v
    LEFT JOIN cities c ON v.city_id = c.id
    LEFT JOIN booking_vendor_assignments bva ON bva.vendor_id = v.id
    GROUP BY v.id
    ORDER BY v.type, v.name
");
$vendors = $stmt->fetchAll();

// Batch-load service cities for all vendors (avoids N+1 queries)
$vendor_ids = array_column($vendors, 'id');
$service_cities_map = [];
if (!empty($vendor_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($vendor_ids), '?'));
        $sc_stmt = $db->prepare(
            "SELECT vsc.vendor_id, c.name AS city_name
               FROM vendor_service_cities vsc
               JOIN cities c ON c.id = vsc.city_id
              WHERE vsc.vendor_id IN ($placeholders)
             ORDER BY c.name"
        );
        $sc_stmt->execute($vendor_ids);
        foreach ($sc_stmt->fetchAll() as $row) {
            $service_cities_map[$row['vendor_id']][] = $row['city_name'];
        }
    } catch (Exception $e) {
        // Table may not exist on older installs; fall back to city_name from vendors query
        $service_cities_map = [];
    }
}

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
                        <th>Photos</th>
                        <th>Name</th>
                        <th>Short Description</th>
                        <th>Type</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Service Cities</th>
                        <th>Receivable</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <?php $vendor_photos = getVendorPhotos($vendor['id']); ?>
                        <tr>
                            <td><?php echo $vendor['id']; ?></td>
                            <td style="min-width:80px;">
                                <?php if (!empty($vendor_photos)): ?>
                                    <?php $carousel_id = 'vc_' . $vendor['id']; ?>
                                    <div id="<?php echo $carousel_id; ?>" class="carousel slide vendor-carousel" data-bs-ride="carousel" style="width:72px;">
                                        <div class="carousel-inner">
                                            <?php foreach ($vendor_photos as $pi => $vp): ?>
                                                <div class="carousel-item<?php echo $pi === 0 ? ' active' : ''; ?>">
                                                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $vp['image_path']); ?>"
                                                         alt="<?php echo htmlspecialchars($vendor['name']); ?>"
                                                         style="width:72px;height:72px;object-fit:cover;border-radius:6px;">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($vendor_photos) > 1): ?>
                                            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="prev" style="width:20px;">
                                                <span class="carousel-control-prev-icon" style="width:12px;height:12px;"></span>
                                            </button>
                                            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="next" style="width:20px;">
                                                <span class="carousel-control-next-icon" style="width:12px;height:12px;"></span>
                                            </button>
                                            <div class="text-center mt-1">
                                                <small class="text-muted"><?php echo count($vendor_photos); ?> photos</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                            <td><?php echo htmlspecialchars($vendor['short_description'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars(getVendorTypeLabel($vendor['type'])); ?></td>
                            <td><?php echo htmlspecialchars($vendor['phone'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($vendor['email'] ?? '—'); ?></td>
                            <td>
                                <?php
                                $svc_cities = $service_cities_map[$vendor['id']] ?? [];
                                if (!empty($svc_cities)):
                                    echo htmlspecialchars(implode(', ', $svc_cities));
                                elseif (!empty($vendor['city_name'])):
                                    echo htmlspecialchars($vendor['city_name']);
                                else:
                                    echo '—';
                                endif;
                                ?>
                            </td>
                            <td>
                                <?php if ((float)$vendor['total_receivable'] > 0): ?>
                                    <a href="view.php?id=<?php echo $vendor['id']; ?>" class="text-success fw-semibold text-decoration-none">
                                        <?php echo formatCurrency($vendor['total_receivable']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : ($vendor['status'] === 'unapproved' ? 'warning text-dark' : 'secondary'); ?>">
                                    <?php echo $vendor['status'] === 'unapproved' ? 'Unapproved' : ucfirst($vendor['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $vendor['id']; ?>" class="btn btn-sm btn-info" title="View Profile" aria-label="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
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
                            <td colspan="11" class="text-center text-muted py-4">
                                No vendors found. <a href="add.php">Add your first vendor</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.vendor-carousel .carousel-control-prev,
.vendor-carousel .carousel-control-next {
    background: rgba(0,0,0,0.4);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
    height: 20px;
    width: 20px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

