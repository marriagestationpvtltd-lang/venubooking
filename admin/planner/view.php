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
                        <span class="text-muted"><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($plan['event_date'])); ?> <small>(<?php echo convertToNepaliDate($plan['event_date']); ?>)</small></span>
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

<!-- Tasks Section (Dark Planner Design) -->
<div class="planner-dark-panel">
    <div class="planner-dark-header d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 planner-dark-title"><i class="fas fa-calendar-check me-2"></i>Planning Checklist</h5>
        <button type="button" class="btn btn-sm planner-add-btn" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="fas fa-plus me-1"></i> Add Task
        </button>
    </div>

    <?php if (empty($all_tasks)): ?>
        <div class="text-center py-5">
            <div class="planner-empty-icon mx-auto mb-3">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <h6 class="planner-empty-title">No tasks yet</h6>
            <p class="planner-empty-sub">Add tasks to track your event planning progress.</p>
            <button type="button" class="btn planner-add-btn" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="fas fa-plus me-1"></i> Add First Task
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($tasks_by_cat as $category => $tasks): ?>
            <div class="planner-category-block mb-4">
                <!-- Category Header -->
                <div class="planner-cat-header mb-3">
                    <div class="planner-cat-icon-wrap">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div>
                        <div class="planner-cat-name"><?php echo htmlspecialchars($category); ?></div>
                        <div class="planner-cat-sub">Choose from <?php echo count($tasks); ?> <?php echo count($tasks) === 1 ? 'item' : 'items'; ?></div>
                    </div>
                </div>

                <!-- Task Rows -->
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $due_str   = '';
                    $overdue   = false;
                    if ($task['due_date']) {
                        $due     = strtotime($task['due_date']);
                        $today   = strtotime('today');
                        $overdue = $due < $today && $task['status'] !== 'completed';
                        $due_str = date('M d, Y', $due) . ' (' . convertToNepaliDate(date('Y-m-d', $due)) . ')';
                    }
                    $pri_colors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'];
                    $pc = $pri_colors[$task['priority']] ?? 'secondary';
                    ?>
                    <div class="planner-item-row <?php echo $task['status'] === 'completed' ? 'task-completed' : ''; ?>"
                         id="task-<?php echo (int)$task['id']; ?>">
                        <!-- Left: box icon -->
                        <div class="planner-item-icon">
                            <i class="fas fa-cube"></i>
                        </div>

                        <!-- Middle: task info -->
                        <div class="planner-item-body flex-grow-1">
                            <div class="planner-item-name task-name"><?php echo htmlspecialchars($task['task_name']); ?></div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="planner-item-desc"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                            <div class="planner-item-meta d-flex flex-wrap gap-2 mt-1">
                                <span class="planner-badge planner-badge-<?php echo $pc; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                <?php if ($due_str): ?>
                                    <span class="planner-item-due <?php echo $overdue ? 'planner-overdue' : ''; ?>">
                                        <i class="fas fa-calendar-day me-1"></i><?php echo $due_str; ?>
                                        <?php if ($overdue): ?><span class="planner-badge planner-badge-danger ms-1">Overdue</span><?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($task['estimated_cost'] > 0): ?>
                                    <span class="planner-item-cost"><i class="fas fa-tag me-1"></i><?php echo formatCurrency($task['estimated_cost']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right: actions + toggle -->
                        <div class="planner-item-actions d-flex align-items-center gap-2">
                            <button type="button" class="planner-action-btn edit-task-btn"
                                    data-task='<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>'
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="task-delete.php" style="display:inline;"
                                  onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
                                <button type="submit" class="planner-action-btn planner-action-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <input type="checkbox" class="planner-toggle task-toggle"
                                   data-task-id="<?php echo (int)$task['id']; ?>"
                                   data-plan-id="<?php echo $plan_id; ?>"
                                   <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
/* ── Dark Planner Panel ─────────────────────────────────────────────── */
.planner-dark-panel {
    background: #0d1323;
    border-radius: 14px;
    padding: 1.75rem 1.5rem;
    border: 1px solid rgba(255,255,255,0.07);
}

.planner-dark-title {
    color: #f1f5f9;
    font-size: 1.05rem;
    font-weight: 600;
}

/* Add Task button */
.planner-add-btn {
    background: rgba(76,175,80,0.15);
    color: #4CAF50;
    border: 1px solid rgba(76,175,80,0.35);
    font-size: 0.82rem;
    padding: 0.35rem 0.85rem;
    border-radius: 8px;
    transition: background 0.2s;
}
.planner-add-btn:hover {
    background: rgba(76,175,80,0.28);
    color: #81C784;
}

/* ── Category block ─────────────────────────────────────────────────── */
.planner-cat-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0 0.25rem;
}
.planner-cat-icon-wrap {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.07);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 1rem;
    flex-shrink: 0;
}
.planner-cat-name {
    color: #e2e8f0;
    font-size: 1.05rem;
    font-weight: 600;
    line-height: 1.2;
}
.planner-cat-sub {
    color: #64748b;
    font-size: 0.78rem;
    margin-top: 2px;
}

/* ── Task item row ──────────────────────────────────────────────────── */
.planner-item-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1.1rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    transition: background 0.18s;
}
.planner-item-row:hover {
    background: rgba(255,255,255,0.08);
}

.planner-item-icon {
    width: 38px;
    height: 38px;
    background: rgba(255,255,255,0.07);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.planner-item-name {
    color: #e2e8f0;
    font-size: 0.97rem;
    font-weight: 500;
}

.planner-item-desc {
    color: #64748b;
    font-size: 0.78rem;
    margin-top: 2px;
}

.planner-item-meta {
    font-size: 0.75rem;
}

.planner-item-due {
    color: #64748b;
}
.planner-overdue {
    color: #f87171 !important;
    font-weight: 600;
}

.planner-item-cost {
    color: #64748b;
}

/* Badges */
.planner-badge {
    display: inline-block;
    padding: 0.18rem 0.5rem;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: capitalize;
}
.planner-badge-success  { background: rgba(74,222,128,0.15); color: #4ade80; }
.planner-badge-warning  { background: rgba(251,191,36,0.15);  color: #fbbf24; }
.planner-badge-danger   { background: rgba(248,113,113,0.15); color: #f87171; }

/* ── Circular toggle ────────────────────────────────────────────────── */
.planner-toggle {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.25);
    appearance: none;
    -webkit-appearance: none;
    background: rgba(255,255,255,0.08);
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s, border-color 0.2s;
}
.planner-toggle:checked {
    background: #4CAF50;
    border-color: #4CAF50;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
    background-size: 100%;
    background-position: center;
    background-repeat: no-repeat;
}
.planner-toggle:hover:not(:checked) {
    border-color: rgba(255,255,255,0.5);
}

/* ── Completed state ────────────────────────────────────────────────── */
.task-completed .planner-item-icon {
    opacity: 0.45;
}
.task-completed .planner-item-name {
    text-decoration: line-through;
    color: #475569;
}

/* ── Action buttons ─────────────────────────────────────────────────── */
.planner-action-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #94a3b8;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.planner-action-btn:hover {
    background: rgba(255,193,7,0.2);
    color: #fbbf24;
    border-color: rgba(255,193,7,0.3);
}
.planner-action-danger:hover {
    background: rgba(239,68,68,0.2);
    color: #f87171;
    border-color: rgba(239,68,68,0.3);
}

/* ── Empty state ────────────────────────────────────────────────────── */
.planner-empty-icon {
    width: 64px;
    height: 64px;
    background: rgba(255,255,255,0.07);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #475569;
}
.planner-empty-title { color: #94a3b8; font-size: 1rem; margin-bottom: 0.35rem; }
.planner-empty-sub   { color: #475569; font-size: 0.85rem; margin-bottom: 1rem; }

/* ── Legacy helpers kept for modals ─────────────────────────────────── */
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
.badge-sm { font-size: 0.7rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
