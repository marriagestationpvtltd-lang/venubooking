<?php
$page_title = 'View Event Plan';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$plan_id = intval($_GET['id'] ?? 0);
if ($plan_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT ep.*, c.full_name AS customer_name, c.phone AS customer_phone
     FROM event_plans ep
     LEFT JOIN customers c ON ep.customer_id = c.id
     WHERE ep.id = ?"
);
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) {
    $_SESSION['error_message'] = 'Plan not found.';
    header('Location: index.php');
    exit;
}

// Fetch tasks grouped by category
$stmt = $db->prepare(
    "SELECT * FROM plan_tasks WHERE plan_id = ? ORDER BY display_order, category, due_date, id"
);
$stmt->execute([$plan_id]);
$all_tasks = $stmt->fetchAll();

// Group tasks by category
$tasks_by_cat = [];
foreach ($all_tasks as $task) {
    $tasks_by_cat[$task['category']][] = $task;
}

// Budget summary
$total_estimated = array_sum(array_column($all_tasks, 'estimated_cost'));
$total_actual    = array_sum(array_column($all_tasks, 'actual_cost'));
$total_tasks     = count($all_tasks);
$completed_tasks = count(array_filter($all_tasks, fn($t) => $t['status'] === 'completed'));
$progress        = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$status_colors = [
    'planning'    => 'warning',
    'in_progress' => 'info',
    'completed'   => 'success',
    'cancelled'   => 'secondary',
];
$sc = $status_colors[$plan['status']] ?? 'secondary';

$task_categories = [
    'Venue', 'Decoration', 'Catering', 'Photography', 'Videography',
    'Music & DJ', 'Invitation & Stationery', 'Transportation', 'Accommodation',
    'Attire & Makeup', 'Florist', 'Cake', 'Entertainment', 'Gifts & Favors',
    'Lighting', 'Security', 'Other'
];
?>

<!-- Hidden CSRF token for AJAX calls -->
<input type="hidden" id="csrf_token_toggle" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">

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

<!-- Plan Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><?php echo htmlspecialchars($plan['title']); ?></h4>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-<?php echo $sc; ?> fs-6">
                        <?php echo ucfirst(str_replace('_', ' ', $plan['status'])); ?>
                    </span>
                    <span class="text-muted"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($plan['event_type']); ?></span>
                    <?php if ($plan['event_date']): ?>
                        <span class="text-muted"><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($plan['event_date'])); ?></span>
                    <?php endif; ?>
                    <?php if ($plan['customer_name']): ?>
                        <span class="text-muted"><i class="fas fa-user"></i> <?php echo htmlspecialchars($plan['customer_name']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($plan['description'])): ?>
                    <p class="mt-2 mb-0 text-muted"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="edit.php?id=<?php echo $plan_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Plan
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <!-- Progress -->
    <div class="col-md-5 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-tasks"></i> Task Progress</h6>
                <div class="d-flex justify-content-between mb-1">
                    <span><?php echo $completed_tasks; ?> of <?php echo $total_tasks; ?> tasks completed</span>
                    <strong><?php echo $progress; ?>%</strong>
                </div>
                <div class="progress" style="height:12px;">
                    <div class="progress-bar bg-success" style="width:<?php echo $progress; ?>%"></div>
                </div>
                <div class="mt-2 small text-muted">
                    <?php
                    $pending     = count(array_filter($all_tasks, fn($t) => $t['status'] === 'pending'));
                    $in_progress = count(array_filter($all_tasks, fn($t) => $t['status'] === 'in_progress'));
                    ?>
                    <span class="me-3"><i class="fas fa-circle text-secondary"></i> Pending: <?php echo $pending; ?></span>
                    <span class="me-3"><i class="fas fa-circle text-info"></i> In Progress: <?php echo $in_progress; ?></span>
                    <span><i class="fas fa-circle text-success"></i> Completed: <?php echo $completed_tasks; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget -->
    <div class="col-md-7 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-wallet"></i> Budget Overview</h6>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h5 mb-0 text-primary"><?php echo $plan['total_budget'] > 0 ? formatCurrency($plan['total_budget']) : '—'; ?></div>
                        <small class="text-muted">Total Budget</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-warning"><?php echo formatCurrency($total_estimated); ?></div>
                        <small class="text-muted">Estimated</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-danger"><?php echo formatCurrency($total_actual); ?></div>
                        <small class="text-muted">Actual Spent</small>
                    </div>
                </div>
                <?php if ($plan['total_budget'] > 0): ?>
                    <?php
                    $pct_used = min(100, round(($total_actual / $plan['total_budget']) * 100));
                    $bar_color = $pct_used >= 90 ? 'danger' : ($pct_used >= 70 ? 'warning' : 'success');
                    ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Spent vs Budget</small>
                            <small><?php echo $pct_used; ?>%</small>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-<?php echo $bar_color; ?>" style="width:<?php echo $pct_used; ?>%"></div>
                        </div>
                        <?php $remaining = $plan['total_budget'] - $total_actual; ?>
                        <small class="text-muted mt-1 d-block">
                            Remaining: <strong class="text-<?php echo $remaining >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo formatCurrency(abs($remaining)); ?><?php echo $remaining < 0 ? ' over budget' : ''; ?>
                            </strong>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tasks Section -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list-check"></i> Planning Checklist</h5>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="fas fa-plus"></i> Add Task
        </button>
    </div>
    <div class="card-body p-0">

        <?php if (empty($all_tasks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No tasks yet</h6>
                <p class="text-muted">Add tasks to track your event planning progress.</p>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="fas fa-plus"></i> Add First Task
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($tasks_by_cat as $category => $tasks): ?>
                <div class="px-3 pt-3">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">
                        <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($category); ?>
                    </h6>
                </div>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $ts_colors = ['pending' => 'secondary', 'in_progress' => 'info', 'completed' => 'success'];
                        $tc = $ts_colors[$task['status']] ?? 'secondary';
                        $pri_colors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'];
                        $pc = $pri_colors[$task['priority']] ?? 'secondary';
                        ?>
                        <div class="list-group-item list-group-item-action px-4 task-row <?php echo $task['status'] === 'completed' ? 'task-completed' : ''; ?>"
                             id="task-<?php echo (int)$task['id']; ?>">
                            <div class="d-flex align-items-start gap-3">
                                <!-- Toggle checkbox -->
                                <div class="pt-1">
                                    <input type="checkbox" class="form-check-input task-toggle"
                                           style="width:1.2rem;height:1.2rem;cursor:pointer;"
                                           data-task-id="<?php echo (int)$task['id']; ?>"
                                           data-plan-id="<?php echo $plan_id; ?>"
                                           <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                                </div>

                                <!-- Task content -->
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                        <div>
                                            <span class="fw-semibold task-name"><?php echo htmlspecialchars($task['task_name']); ?></span>
                                            <span class="badge bg-<?php echo $pc; ?> ms-1 badge-sm"><?php echo ucfirst($task['priority']); ?></span>
                                            <span class="badge bg-<?php echo $tc; ?> ms-1 badge-sm"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span>
                                        </div>
                                        <div class="d-flex gap-1 task-actions">
                                            <button type="button" class="btn btn-xs btn-outline-warning edit-task-btn"
                                                    data-task='<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>'
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="task-delete.php" style="display:inline;"
                                                  onsubmit="return confirm('Delete this task?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                        <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($task['description']); ?></small>
                                    <?php endif; ?>
                                    <div class="d-flex flex-wrap gap-3 mt-1 small text-muted">
                                        <?php if ($task['due_date']): ?>
                                            <?php
                                            $due = strtotime($task['due_date']);
                                            $today = strtotime('today');
                                            $overdue = $due < $today && $task['status'] !== 'completed';
                                            ?>
                                            <span class="<?php echo $overdue ? 'text-danger fw-semibold' : ''; ?>">
                                                <i class="fas fa-calendar-day"></i>
                                                Due: <?php echo date('M d, Y', $due); ?>
                                                <?php if ($overdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($task['estimated_cost'] > 0): ?>
                                            <span><i class="fas fa-tag"></i> Est: <?php echo formatCurrency($task['estimated_cost']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($task['actual_cost'] > 0): ?>
                                            <span><i class="fas fa-receipt"></i> Actual: <?php echo formatCurrency($task['actual_cost']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="task-save.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                <input type="hidden" name="task_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle text-success"></i> Add Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Task Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="task_name" required
                                   placeholder="e.g. Book the photography team">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <?php foreach ($task_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Cost</label>
                            <input type="number" class="form-control" name="estimated_cost" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Actual Cost</label>
                            <input type="number" class="form-control" name="actual_cost" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Additional notes or details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="task-save.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                <input type="hidden" name="task_id" id="edit_task_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit text-warning"></i> Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Task Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="task_name" id="edit_task_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority" id="edit_priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="edit_category">
                                <?php foreach ($task_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" id="edit_due_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Cost</label>
                            <input type="number" class="form-control" name="estimated_cost" id="edit_estimated_cost" min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Actual Cost</label>
                            <input type="number" class="form-control" name="actual_cost" id="edit_actual_cost" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'JS'
<script>
// Edit task modal population
document.querySelectorAll('.edit-task-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var task = JSON.parse(this.getAttribute('data-task'));
        document.getElementById('edit_task_id').value       = task.id;
        document.getElementById('edit_task_name').value     = task.task_name;
        document.getElementById('edit_priority').value      = task.priority;
        document.getElementById('edit_category').value      = task.category;
        document.getElementById('edit_due_date').value      = task.due_date || '';
        document.getElementById('edit_status').value        = task.status;
        document.getElementById('edit_estimated_cost').value = task.estimated_cost;
        document.getElementById('edit_actual_cost').value   = task.actual_cost;
        document.getElementById('edit_description').value   = task.description || '';
        var modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
        modal.show();
    });
});

// Task toggle (checkbox) — AJAX
document.querySelectorAll('.task-toggle').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var taskId   = this.getAttribute('data-task-id');
        var planId   = this.getAttribute('data-plan-id');
        var csrfToken = document.getElementById('csrf_token_toggle').value;
        var row      = document.getElementById('task-' + taskId);
        fetch('task-toggle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'task_id=' + encodeURIComponent(taskId) + '&plan_id=' + encodeURIComponent(planId) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.new_status === 'completed') {
                    row.classList.add('task-completed');
                } else {
                    row.classList.remove('task-completed');
                }
            } else {
                // Revert checkbox on failure
                cb.checked = !cb.checked;
            }
        })
        .catch(function() { cb.checked = !cb.checked; });
    });
});
</script>
JS;
?>

<style>
.task-completed .task-name {
    text-decoration: line-through;
    color: #6c757d;
}
.task-row {
    transition: background 0.2s;
}
.btn-xs {
    padding: 0.15rem 0.4rem;
    font-size: 0.75rem;
}
.badge-sm {
    font-size: 0.7rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
