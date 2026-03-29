<?php
$page_title = 'Vendor Dues / Payables';
require_once __DIR__ . '/../includes/header.php';

$data    = getAllVendorDues();
$summary = $data['summary'];
$vendors = $data['vendors'];

// Filter: only show vendors with outstanding due (default = all)
$filter_outstanding = isset($_GET['filter']) && $_GET['filter'] === 'outstanding';
if ($filter_outstanding) {
    $vendors = array_filter($vendors, fn($v) => (float)$v['total_due'] > 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2 text-warning"></i>
        Vendor Dues / Payables
        <small class="text-muted fs-6 ms-2">सर्भिस प्रोभाइडरलाई दिन बाँकी रकम</small>
    </h4>
    <div class="d-flex gap-2">
        <a href="?filter=outstanding" class="btn btn-sm <?php echo $filter_outstanding ? 'btn-warning' : 'btn-outline-warning'; ?>">
            <i class="fas fa-filter me-1"></i> Outstanding Only
        </a>
        <a href="?" class="btn btn-sm <?php echo !$filter_outstanding ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
            <i class="fas fa-list me-1"></i> All Vendors
        </a>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Vendors
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:56px;height:56px;flex-shrink:0;">
                    <i class="fas fa-file-invoice-dollar fa-lg text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small mb-1">कुल दिने रकम (Assigned)</div>
                    <div class="fw-bold fs-4"><?php echo formatCurrency($summary['total_assigned']); ?></div>
                    <div class="text-muted small">Total Assigned to Vendors</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:56px;height:56px;flex-shrink:0;">
                    <i class="fas fa-check-circle fa-lg text-success"></i>
                </div>
                <div>
                    <div class="text-muted small mb-1">भुक्तानी गरिएको रकम (Paid)</div>
                    <div class="fw-bold fs-4 text-success"><?php echo formatCurrency($summary['total_paid']); ?></div>
                    <div class="text-muted small">Total Paid to Vendors</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning border-2 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:56px;height:56px;flex-shrink:0;">
                    <i class="fas fa-hourglass-half fa-lg text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small mb-1">दिन बाँकी रकम (Outstanding)</div>
                    <div class="fw-bold fs-4 text-warning"><?php echo formatCurrency($summary['total_due']); ?></div>
                    <div class="text-muted small">Total Still Owed to Vendors</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Per-Vendor Breakdown -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>
            <?php echo $filter_outstanding ? 'Vendors with Outstanding Balance' : 'All Vendor Payables'; ?>
            <span class="badge bg-secondary ms-2"><?php echo count($vendors); ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($vendors)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                <?php if ($filter_outstanding): ?>
                    <strong>सबै भुक्तानी भइसकेको छ!</strong><br>
                    No outstanding dues for any vendor.
                <?php else: ?>
                    No vendor assignments found.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 datatable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Vendor Name</th>
                        <th>Type</th>
                        <th class="text-center">Assignments</th>
                        <th class="text-end">Assigned</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due (Remaining)</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($vendors as $v): ?>
                    <tr class="<?php echo (float)$v['total_due'] > 0 ? '' : 'text-muted'; ?>">
                        <td class="text-muted small"><?php echo $i++; ?></td>
                        <td>
                            <?php if (!empty($v['vendor_id'])): ?>
                                <a href="view.php?id=<?php echo (int)$v['vendor_id']; ?>" class="fw-semibold text-decoration-none">
                                    <?php echo htmlspecialchars($v['vendor_name']); ?>
                                </a>
                                <?php if (!empty($v['vendor_phone'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($v['vendor_phone']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="fw-semibold"><?php echo htmlspecialchars($v['vendor_name']); ?></span>
                                <?php if (!empty($v['manual_vendor_phone'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($v['manual_vendor_phone']); ?></div>
                                <?php endif; ?>
                                <span class="badge bg-secondary ms-1 small">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($v['vendor_type'])): ?>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $v['vendor_type']))); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark"><?php echo (int)$v['assignment_count']; ?></span>
                        </td>
                        <td class="text-end"><?php echo formatCurrency($v['total_assigned']); ?></td>
                        <td class="text-end text-success">
                            <?php if ((float)$v['total_paid'] > 0): ?>
                                <?php echo formatCurrency($v['total_paid']); ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php if ((float)$v['total_due'] > 0): ?>
                                <span class="text-warning"><?php echo formatCurrency($v['total_due']); ?></span>
                            <?php else: ?>
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>Cleared</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($v['vendor_id'])): ?>
                                <a href="view.php?id=<?php echo (int)$v['vendor_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Vendor">
                                    <i class="fas fa-eye"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-end"><?php echo formatCurrency($summary['total_assigned']); ?></td>
                        <td class="text-end text-success"><?php echo formatCurrency($summary['total_paid']); ?></td>
                        <td class="text-end text-warning"><?php echo formatCurrency($summary['total_due']); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
