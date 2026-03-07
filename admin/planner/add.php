<?php
$page_title = 'Add New Event Plan';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$success_message = '';
$error_message   = '';

$customers = $db->query("SELECT id, full_name, phone FROM customers ORDER BY full_name")->fetchAll();

$event_types = [
    'Wedding', 'Birthday', 'Anniversary', 'Corporate Event', 'Reception',
    'Engagement', 'Baby Shower', 'Bridal Shower', 'Conference', 'Seminar',
    'Product Launch', 'Farewell Party', 'New Year Party', 'Cultural Event', 'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $title        = trim($_POST['title'] ?? '');
        $event_type   = trim($_POST['event_type'] ?? '');
        $event_date   = trim($_POST['event_date'] ?? '') ?: null;
        $customer_id  = intval($_POST['customer_id'] ?? 0) ?: null;
        $total_budget = floatval($_POST['total_budget'] ?? 0);
        $description  = trim($_POST['description'] ?? '');
        $status       = in_array($_POST['status'] ?? '', ['planning', 'in_progress', 'completed', 'cancelled'])
                        ? $_POST['status'] : 'planning';

        if (empty($title)) {
            $error_message = 'Plan title is required.';
        } elseif (empty($event_type)) {
            $error_message = 'Event type is required.';
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO event_plans (title, event_type, event_date, customer_id, total_budget, description, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $title, $event_type, $event_date, $customer_id,
                    $total_budget, $description, $status, $current_user['id']
                ]);
                $plan_id = $db->lastInsertId();

                logActivity($current_user['id'], 'Created event plan', 'event_plans', $plan_id, "Created plan: $title");

                $_SESSION['success_message'] = 'Event plan created successfully!';
                header('Location: view.php?id=' . $plan_id);
                exit;
            } catch (Exception $e) {
                $error_message = 'Failed to create plan. Please try again.';
            }
        }
    }
}
?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-plus-circle text-success"></i> New Event Plan</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Plan Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           placeholder="e.g. John & Jane Wedding Plan" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['planning' => 'Planning', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo ($_POST['status'] ?? 'planning') === $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Event Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="event_type" required>
                        <option value="">— Select Event Type —</option>
                        <?php foreach ($event_types as $et): ?>
                            <option value="<?php echo htmlspecialchars($et); ?>"
                                <?php echo ($_POST['event_type'] ?? '') === $et ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($et); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Event Date</label>
                    <input type="date" class="form-control" name="event_date"
                           value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Total Budget (<?php echo htmlspecialchars(getSetting('currency') ?: 'NPR'); ?>)</label>
                    <input type="number" class="form-control" name="total_budget" min="0" step="0.01"
                           value="<?php echo htmlspecialchars($_POST['total_budget'] ?? '0'); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer (optional)</label>
                    <select class="form-select" name="customer_id">
                        <option value="">— No customer linked —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"
                                <?php echo intval($_POST['customer_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['full_name']); ?>
                                (<?php echo htmlspecialchars($c['phone']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description / Notes</label>
                <textarea class="form-control" name="description" rows="4"
                          placeholder="Any special requirements, vision, or notes for this event..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Create Plan
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
