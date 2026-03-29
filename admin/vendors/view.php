<?php
$page_title = 'Vendor Profile';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$vendor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($vendor_id <= 0) {
    header('Location: index.php');
    exit;
}

$vendor = getVendor($vendor_id);
if (!$vendor) {
    $_SESSION['error_message'] = 'Vendor not found.';
    header('Location: index.php');
    exit;
}

$vendor_photos      = getVendorPhotos($vendor_id);
$vendor_assignments = getVendorAssignments($vendor_id);
$total_receivable   = getVendorTotalReceivable($vendor_id);

// Group totals by status for summary
$status_totals = [];
foreach ($vendor_assignments as $a) {
    $s = $a['status'];
    if (!isset($status_totals[$s])) {
        $status_totals[$s] = ['count' => 0, 'amount' => 0.0];
    }
    $status_totals[$s]['count']++;
    $status_totals[$s]['amount'] += (float)$a['assigned_amount'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php">Vendors</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($vendor['name']); ?></li>
        </ol>
    </nav>
    <div>
        <a href="edit.php?id=<?php echo $vendor_id; ?>" class="btn btn-sm btn-warning">
            <i class="fas fa-edit"></i> Edit Vendor
        </a>
        <a href="index.php" class="btn btn-sm btn-secondary ms-1">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Vendor Profile Card -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php if (!empty($vendor_photos)): ?>
                    <?php if (count($vendor_photos) === 1): ?>
                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . $vendor_photos[0]['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($vendor['name']); ?>"
                             class="rounded-circle mb-3"
                             style="width:120px;height:120px;object-fit:cover;border:3px solid #dee2e6;">
                    <?php else: ?>
                        <div id="profileCarousel" class="carousel slide mb-3" data-bs-ride="carousel" style="width:120px;margin:0 auto;">
                            <div class="carousel-inner">
                                <?php foreach ($vendor_photos as $pi => $vp): ?>
                                    <div class="carousel-item<?php echo $pi === 0 ? ' active' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . $vp['image_path']); ?>"
                                             alt="<?php echo htmlspecialchars($vendor['name']); ?>"
                                             style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:3px solid #dee2e6;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#profileCarousel" data-bs-slide="prev" style="width:20px;">
                                <span class="carousel-control-prev-icon" style="width:12px;height:12px;filter:invert(1);"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#profileCarousel" data-bs-slide="next" style="width:20px;">
                                <span class="carousel-control-next-icon" style="width:12px;height:12px;filter:invert(1);"></span>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-3 mx-auto"
                         style="width:120px;height:120px;border:3px solid #dee2e6;">
                        <i class="fas fa-user-tie fa-3x text-muted"></i>
                    </div>
                <?php endif; ?>

                <h5 class="mb-1"><?php echo htmlspecialchars($vendor['name']); ?></h5>
                <p class="text-muted mb-2">
                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars(getVendorTypeLabel($vendor['type'])); ?></span>
                    <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : 'secondary'; ?> ms-1">
                        <?php echo ucfirst($vendor['status']); ?>
                    </span>
                </p>
                <?php if (!empty($vendor['short_description'])): ?>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($vendor['short_description']); ?></p>
                <?php endif; ?>

                <hr>

                <dl class="row text-start small mb-0">
                    <?php if (!empty($vendor['phone'])): ?>
                        <dt class="col-5 text-muted">Phone</dt>
                        <dd class="col-7">
                            <a href="tel:<?php echo htmlspecialchars($vendor['phone']); ?>">
                                <?php echo htmlspecialchars($vendor['phone']); ?>
                            </a>
                        </dd>
                    <?php endif; ?>
                    <?php if (!empty($vendor['email'])): ?>
                        <dt class="col-5 text-muted">Email</dt>
                        <dd class="col-7">
                            <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>">
                                <?php echo htmlspecialchars($vendor['email']); ?>
                            </a>
                        </dd>
                    <?php endif; ?>
                    <?php if (!empty($vendor['city_name'])): ?>
                        <dt class="col-5 text-muted">City</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($vendor['city_name']); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($vendor['address'])): ?>
                        <dt class="col-5 text-muted">Address</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($vendor['address']); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($vendor['notes'])): ?>
                        <dt class="col-5 text-muted">Notes</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($vendor['notes']); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Receivable Summary -->
    <div class="col-md-8">
        <!-- Total Receivable Highlight -->
        <div class="card border-success mb-4">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:64px;height:64px;flex-shrink:0;">
                    <i class="fas fa-hand-holding-usd fa-2x text-success"></i>
                </div>
                <div>
                    <div class="text-muted small mb-1">कम्पनीमार्फत पाउनुपर्ने कुल रकम</div>
                    <div class="fw-bold fs-3 text-success"><?php echo formatCurrency($total_receivable); ?></div>
                    <div class="text-muted small">Total Receivable Amount (excluding cancelled assignments)</div>
                </div>
            </div>
        </div>

        <!-- Status-wise breakdown -->
        <?php if (!empty($status_totals)): ?>
        <div class="row g-3 mb-4">
            <?php
            $status_labels = [
                'assigned'  => ['label' => 'Assigned',  'color' => 'secondary', 'icon' => 'fa-clock'],
                'confirmed' => ['label' => 'Confirmed', 'color' => 'primary',   'icon' => 'fa-check-circle'],
                'completed' => ['label' => 'Completed', 'color' => 'success',   'icon' => 'fa-check-double'],
                'cancelled' => ['label' => 'Cancelled', 'color' => 'danger',    'icon' => 'fa-times-circle'],
            ];
            foreach ($status_labels as $st => $meta):
                if (!isset($status_totals[$st])) continue;
                $info = $status_totals[$st];
            ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 border-<?php echo $meta['color']; ?> border-opacity-50">
                    <div class="card-body text-center p-3">
                        <i class="fas <?php echo $meta['icon']; ?> text-<?php echo $meta['color']; ?> mb-2"></i>
                        <div class="small text-muted mb-1"><?php echo $meta['label']; ?></div>
                        <div class="fw-bold"><?php echo formatCurrency($info['amount']); ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?php echo $info['count']; ?> assignment<?php echo $info['count'] !== 1 ? 's' : ''; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Assignments Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-list-alt"></i> Booking Assignments</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($vendor_assignments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        No assignments found for this vendor.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking #</th>
                                    <th>Customer</th>
                                    <th>Event Date</th>
                                    <th>Venue / Hall</th>
                                    <th>Task</th>
                                    <th>Status</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendor_assignments as $a): ?>
                                    <tr>
                                        <td>
                                            <a href="../bookings/view.php?id=<?php echo $a['booking_id']; ?>" class="text-decoration-none fw-semibold">
                                                <?php echo htmlspecialchars($a['booking_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($a['customer_name'] ?? '—'); ?></td>
                                        <td><?php echo !empty($a['event_date']) ? date('d M Y', strtotime($a['event_date'])) : '—'; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($a['venue_name'] ?? ''); ?>
                                            <?php if (!empty($a['hall_name'])): ?>
                                                <span class="text-muted"> / <?php echo htmlspecialchars($a['hall_name']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($a['task_description']); ?>
                                            <?php if (!empty($a['notes'])): ?>
                                                <i class="fas fa-info-circle text-muted ms-1"
                                                   data-bs-toggle="tooltip"
                                                   title="<?php echo htmlspecialchars($a['notes']); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getVendorAssignmentStatusColor($a['status']); ?>">
                                                <?php echo ucfirst($a['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold <?php echo $a['status'] === 'cancelled' ? 'text-muted text-decoration-line-through' : 'text-success'; ?>">
                                            <?php echo formatCurrency($a['assigned_amount']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="6" class="text-end">Total Receivable (excl. cancelled):</th>
                                    <th class="text-end text-success"><?php echo formatCurrency($total_receivable); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Enable Bootstrap tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
