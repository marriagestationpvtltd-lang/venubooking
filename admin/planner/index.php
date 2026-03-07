<?php
$page_title = 'Event Planner';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all plans with customer info and task stats
$stmt = $db->query(
    "SELECT ep.*,
            c.full_name AS customer_name,
            COUNT(pt.id) AS total_tasks,
            SUM(pt.status = 'completed') AS completed_tasks,
            COALESCE(SUM(pt.estimated_cost), 0) AS total_estimated,
            COALESCE(SUM(pt.actual_cost), 0) AS total_actual
     FROM event_plans ep
     LEFT JOIN customers c ON ep.customer_id = c.id
     LEFT JOIN plan_tasks pt ON pt.plan_id = ep.id
     GROUP BY ep.id
     ORDER BY ep.created_at DESC"
);
$plans = $stmt->fetchAll();

// Summary stats
$stats = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'planning') AS planning,
        SUM(status = 'in_progress') AS in_progress,
        SUM(status = 'completed') AS completed,
        SUM(status = 'cancelled') AS cancelled
     FROM event_plans"
)->fetch();
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Stats -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-primary text-white mx-auto mb-2" style="width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h4 class="mb-0"><?php echo (int)($stats['total'] ?? 0); ?></h4>
            <small class="text-muted">Total Plans</small>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-warning text-white mx-auto mb-2" style="width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-pencil-alt"></i>
            </div>
            <h4 class="mb-0"><?php echo (int)($stats['planning'] ?? 0); ?></h4>
            <small class="text-muted">Planning</small>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-info text-white mx-auto mb-2" style="width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-spinner"></i>
            </div>
            <h4 class="mb-0"><?php echo (int)($stats['in_progress'] ?? 0); ?></h4>
            <small class="text-muted">In Progress</small>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card text-center">
            <div class="stat-icon bg-success text-white mx-auto mb-2" style="width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 class="mb-0"><?php echo (int)($stats['completed'] ?? 0); ?></h4>
            <small class="text-muted">Completed</small>
        </div>
    </div>
</div>

<!-- Plans List -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> All Event Plans</h5>
        <a href="add.php" class="btn btn-success"><i class="fas fa-plus"></i> New Plan</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Event Type</th>
                        <th>Event Date</th>
                        <th>Customer</th>
                        <th>Budget</th>
                        <th>Tasks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $progress = $plan['total_tasks'] > 0
                            ? round(($plan['completed_tasks'] / $plan['total_tasks']) * 100)
                            : 0;
                        $status_colors = [
                            'planning'    => 'warning',
                            'in_progress' => 'info',
                            'completed'   => 'success',
                            'cancelled'   => 'secondary',
                        ];
                        $sc = $status_colors[$plan['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?php echo (int)$plan['id']; ?></td>
                            <td>
                                <a href="view.php?id=<?php echo (int)$plan['id']; ?>" class="fw-semibold text-decoration-none">
                                    <?php echo htmlspecialchars($plan['title']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($plan['event_type']); ?></td>
                            <td><?php echo $plan['event_date'] ? date('M d, Y', strtotime($plan['event_date'])) : '—'; ?></td>
                            <td>
                                <?php if ($plan['customer_name']): ?>
                                    <?php echo htmlspecialchars($plan['customer_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $plan['total_budget'] > 0 ? formatCurrency($plan['total_budget']) : '—'; ?></td>
                            <td>
                                <?php if ($plan['total_tasks'] > 0): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px;min-width:60px;">
                                            <div class="progress-bar bg-success" style="width:<?php echo $progress; ?>%"></div>
                                        </div>
                                        <small><?php echo $plan['completed_tasks']; ?>/<?php echo $plan['total_tasks']; ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No tasks</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $sc; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $plan['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="delete.php" style="display:inline;"
                                      onsubmit="return confirm('Delete this plan and all its tasks?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$plan['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-tasks fa-3x mb-3 d-block"></i>
                                No event plans yet. <a href="add.php">Create your first plan</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
