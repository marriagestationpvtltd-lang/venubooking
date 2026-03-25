<?php
$page_title = 'Add Policy Page';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success = '';
$error   = '';

// Form field defaults
$title              = '';
$slug               = '';
$content            = '';
$status             = 'active';
$require_acceptance = 0;
$sort_order         = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $title              = trim($_POST['title'] ?? '');
        $slug               = trim($_POST['slug'] ?? '');
        $content            = $_POST['content'] ?? '';
        $status             = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $require_acceptance = isset($_POST['require_acceptance']) ? 1 : 0;
        $sort_order         = intval($_POST['sort_order'] ?? 0);

        // Auto-generate slug from title if blank
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        }
        // Ensure slug is lowercase and safe
        $slug = strtolower(preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', $slug)));

        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (empty($slug)) {
            $error = 'Slug could not be generated. Please enter one manually.';
        } elseif (empty($content)) {
            $error = 'Content is required.';
        } else {
            // Check slug uniqueness
            $stmt = $db->prepare('SELECT id FROM policy_pages WHERE slug = ?');
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $error = 'A policy page with this slug already exists. Please use a different slug.';
            } else {
                try {
                    $stmt = $db->prepare(
                        'INSERT INTO policy_pages (title, slug, content, status, require_acceptance, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $slug, $content, $status, $require_acceptance, $sort_order]);
                    $new_id = $db->lastInsertId();
                    logActivity($current_user['id'], 'Added policy page', 'policy_pages', $new_id, "Added: {$title}");
                    header('Location: ' . BASE_URL . '/admin/policy-pages/index.php?success=added');
                    exit;
                } catch (\Exception $e) {
                    $error = 'Failed to save: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Policy Page</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="policyForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?php echo htmlspecialchars($title); ?>"
                           placeholder="e.g. Terms and Conditions" required>
                </div>
                <div class="col-md-4">
                    <label for="slug" class="form-label">Slug (URL) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?php echo htmlspecialchars($slug); ?>"
                           placeholder="e.g. terms-and-conditions">
                    <div class="form-text">Lowercase letters, numbers, and hyphens only. Auto-generated from title if blank.</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                <textarea class="form-control" id="content" name="content" rows="20"
                          placeholder="Write the policy content here (HTML is supported)..."><?php echo htmlspecialchars($content); ?></textarea>
                <div class="form-text">HTML formatting is supported. Use &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;strong&gt; etc.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order"
                           value="<?php echo $sort_order; ?>" min="0" step="1">
                    <div class="form-text">Lower numbers appear first (e.g. 10, 20, 30).</div>
                </div>
                <div class="col-md-4 d-flex align-items-end pb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="require_acceptance"
                               name="require_acceptance" value="1"
                               <?php echo $require_acceptance ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="require_acceptance">
                            <strong>Require acceptance before booking</strong>
                        </label>
                        <div class="form-text">If checked, users must tick a checkbox agreeing to this policy on the booking confirmation page.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Save Policy Page
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/policy-pages/index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function () {
    var slugField = document.getElementById('slug');
    if (slugField.dataset.manual) return; // user has edited slug manually
    slugField.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s]+/g, '-')
        .replace(/-+/g, '-');
});
document.getElementById('slug').addEventListener('input', function () {
    this.dataset.manual = '1';
    // Enforce slug format on manual edit
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
});
</script>
