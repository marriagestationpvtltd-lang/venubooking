<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// ── Actions ────────────────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$id     = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($action && $id > 0) {
    switch ($action) {
        case 'approve':
            if (updateReviewStatus($id, 'approved')) {
                logActivity($current_user['id'], 'Approved user review', 'user_reviews', $id);
                $_SESSION['success_message'] = 'Review approved and is now publicly visible.';
            } else {
                $_SESSION['error_message'] = 'Failed to approve review.';
            }
            break;

        case 'reject':
            $note = trim($_POST['admin_note'] ?? '');
            if (updateReviewStatus($id, 'rejected', $note)) {
                logActivity($current_user['id'], 'Rejected user review', 'user_reviews', $id);
                $_SESSION['success_message'] = 'Review has been rejected.';
            } else {
                $_SESSION['error_message'] = 'Failed to reject review.';
            }
            break;

        case 'delete':
            try {
                $stmt = $db->prepare("DELETE FROM user_reviews WHERE id = ?");
                if ($stmt->execute([$id])) {
                    logActivity($current_user['id'], 'Deleted user review', 'user_reviews', $id);
                    $_SESSION['success_message'] = 'Review deleted.';
                } else {
                    $_SESSION['error_message'] = 'Failed to delete review.';
                }
            } catch (\Throwable $e) {
                error_log('Delete review error: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to delete review.';
            }
            break;
    }

    header('Location: index.php');
    exit;
}

// ── Data ───────────────────────────────────────────────────────────────────
$filter = trim($_GET['filter'] ?? 'all');

try {
    if ($filter === 'pending') {
        $stmt = $db->query(
            "SELECT ur.*, b.booking_number, b.event_type AS booking_event_type, b.event_date AS booking_event_date, c.full_name AS booking_name
             FROM user_reviews ur
             LEFT JOIN bookings b  ON ur.booking_id = b.id
             LEFT JOIN customers c ON b.customer_id = c.id
             WHERE ur.submitted = 1 AND ur.status = 'pending'
             ORDER BY ur.created_at DESC"
        );
    } elseif ($filter === 'approved') {
        $stmt = $db->query(
            "SELECT ur.*, b.booking_number, b.event_type AS booking_event_type, b.event_date AS booking_event_date, c.full_name AS booking_name
             FROM user_reviews ur
             LEFT JOIN bookings b  ON ur.booking_id = b.id
             LEFT JOIN customers c ON b.customer_id = c.id
             WHERE ur.submitted = 1 AND ur.status = 'approved'
             ORDER BY ur.updated_at DESC"
        );
    } elseif ($filter === 'rejected') {
        $stmt = $db->query(
            "SELECT ur.*, b.booking_number, b.event_type AS booking_event_type, b.event_date AS booking_event_date, c.full_name AS booking_name
             FROM user_reviews ur
             LEFT JOIN bookings b  ON ur.booking_id = b.id
             LEFT JOIN customers c ON b.customer_id = c.id
             WHERE ur.submitted = 1 AND ur.status = 'rejected'
             ORDER BY ur.updated_at DESC"
        );
    } else {
        // All submitted reviews
        $stmt = $db->query(
            "SELECT ur.*, b.booking_number, b.event_type AS booking_event_type, b.event_date AS booking_event_date, c.full_name AS booking_name
             FROM user_reviews ur
             LEFT JOIN bookings b  ON ur.booking_id = b.id
             LEFT JOIN customers c ON b.customer_id = c.id
             WHERE ur.submitted = 1
             ORDER BY ur.created_at DESC"
        );
    }
    $reviews = $stmt->fetchAll();
} catch (\Throwable $e) {
    error_log('Admin reviews list error: ' . $e->getMessage());
    $reviews = [];
}

// Count badges
try {
    $counts = $db->query(
        "SELECT
            SUM(submitted = 1) AS total_submitted,
            SUM(submitted = 1 AND status = 'pending')  AS pending,
            SUM(submitted = 1 AND status = 'approved') AS approved,
            SUM(submitted = 1 AND status = 'rejected') AS rejected
         FROM user_reviews"
    )->fetch();
} catch (\Throwable $e) {
    $counts = ['total_submitted' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

$page_title = 'User Reviews';
require_once __DIR__ . '/../includes/header.php';

$success_message = $_SESSION['success_message'] ?? '';
$error_message   = $_SESSION['error_message']   ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="container-fluid py-4">

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fas fa-star me-2 text-warning"></i>User Reviews</h4>
            <small class="text-muted">Review and moderate customer-submitted reviews</small>
        </div>
        <a href="<?php echo BASE_URL; ?>/testimonials.php" target="_blank" class="btn btn-outline-success btn-sm">
            <i class="fas fa-external-link-alt me-1"></i> View Public Testimonials
        </a>
    </div>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="index.php?filter=all">
                All <span class="badge bg-secondary ms-1"><?php echo (int)$counts['total_submitted']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="index.php?filter=pending">
                Pending <span class="badge bg-warning text-dark ms-1"><?php echo (int)$counts['pending']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'approved' ? 'active' : ''; ?>" href="index.php?filter=approved">
                Approved <span class="badge bg-success ms-1"><?php echo (int)$counts['approved']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>" href="index.php?filter=rejected">
                Rejected <span class="badge bg-danger ms-1"><?php echo (int)$counts['rejected']; ?></span>
            </a>
        </li>
    </ul>

    <?php if (empty($reviews)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-star fa-3x mb-3 opacity-25"></i>
        <p class="mb-0">No reviews found<?php echo $filter !== 'all' ? ' for this filter' : ''; ?>.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($reviews as $r): ?>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <!-- Header row -->
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <span class="fw-semibold"><?php echo htmlspecialchars($r['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($r['reviewer_email'])): ?>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($r['reviewer_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php
                            $badge_class = match($r['status']) {
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                default    => 'bg-warning text-dark',
                            };
                        ?>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($r['status']); ?></span>
                    </div>

                    <!-- Stars -->
                    <div class="mb-2" aria-label="<?php echo (int)$r['rating']; ?> stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?php echo $i <= (int)$r['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                        <?php endfor; ?>
                    </div>

                    <!-- Review text -->
                    <p class="mb-2 text-muted" style="font-size:.93rem;"><?php echo nl2br(htmlspecialchars($r['review_text'], ENT_QUOTES, 'UTF-8')); ?></p>

                    <!-- Booking reference -->
                    <?php if (!empty($r['booking_number'])): ?>
                    <small class="text-muted">
                        <i class="fas fa-bookmark me-1"></i>
                        Booking:
                        <a href="<?php echo BASE_URL; ?>/admin/bookings/view.php?id=<?php echo (int)$r['booking_id']; ?>" class="text-success">
                            <?php echo htmlspecialchars($r['booking_number'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php if (!empty($r['booking_event_type'])): ?>
                        – <?php echo htmlspecialchars($r['booking_event_type'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </small><br>
                    <?php endif; ?>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i> Submitted: <?php echo date('d M Y, H:i', strtotime($r['created_at'])); ?>
                    </small>

                    <?php if (!empty($r['admin_note'])): ?>
                    <div class="mt-2 p-2 bg-light rounded">
                        <small><i class="fas fa-sticky-note me-1 text-muted"></i><em><?php echo htmlspecialchars($r['admin_note'], ENT_QUOTES, 'UTF-8'); ?></em></small>
                    </div>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <?php if ($r['status'] !== 'approved'): ?>
                        <form method="POST" action="index.php" onsubmit="return confirm('Approve this review?')">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if ($r['status'] !== 'rejected'): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-review-id="<?php echo (int)$r['id']; ?>"
                                data-reviewer="<?php echo htmlspecialchars($r['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-times me-1"></i> Reject
                        </button>
                        <?php endif; ?>

                        <form method="POST" action="index.php" onsubmit="return confirm('Permanently delete this review?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-trash me-1"></i>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectReviewId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel"><i class="fas fa-times-circle me-1 text-danger"></i> Reject Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject review from <strong id="rejectReviewerName"></strong>?</p>
                    <div class="mb-3">
                        <label for="admin_note" class="form-label">Internal Note <small class="text-muted">(optional, not shown publicly)</small></label>
                        <textarea class="form-control" name="admin_note" id="admin_note" rows="3"
                                  placeholder="Reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var rejectModal = document.getElementById('rejectModal');
if (rejectModal) {
    rejectModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('rejectReviewId').value    = btn.dataset.reviewId;
        document.getElementById('rejectReviewerName').textContent = btn.dataset.reviewer;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
