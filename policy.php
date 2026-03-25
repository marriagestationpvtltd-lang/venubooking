<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Read and sanitise the slug from the query string
$slug = trim($_GET['slug'] ?? '');

// Validate slug format (alphanumeric + hyphens only)
if (empty($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    header('Location: index.php');
    exit;
}

$policy = getPolicyPageBySlug($slug);

if (!$policy) {
    // Page not found or inactive — redirect to home
    header('Location: index.php');
    exit;
}

$page_title = $policy['title'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a>
                </li>
                <li class="breadcrumb-item active text-white" aria-current="page">
                    <?php echo htmlspecialchars($policy['title']); ?>
                </li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold">
            <i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($policy['title']); ?>
        </h1>
    </div>
</div>

<!-- Policy Content -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <!-- Other policy pages navigation -->
                <?php
                $all_policies = getPolicyPages();
                if (count($all_policies) > 1):
                ?>
                <div class="mb-4 d-flex flex-wrap gap-2">
                    <?php foreach ($all_policies as $p): ?>
                        <a href="<?php echo BASE_URL; ?>/policy.php?slug=<?php echo urlencode($p['slug']); ?>"
                           class="btn btn-sm <?php echo $p['slug'] === $slug ? 'btn-success' : 'btn-outline-success'; ?>">
                            <?php echo htmlspecialchars($p['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5 policy-content">
                        <?php
                        // Content is stored as admin-authored HTML. We allow a safe
                        // subset of tags to prevent script injection while preserving
                        // all normal formatting elements used in policy documents.
                        $allowed_tags = '<h1><h2><h3><h4><h5><h6>'
                            . '<p><br><hr><blockquote>'
                            . '<ul><ol><li><dl><dt><dd>'
                            . '<strong><b><em><i><u><s><small><sup><sub>'
                            . '<a><abbr><acronym><code><pre><mark>'
                            . '<table><thead><tbody><tfoot><tr><th><td>'
                            . '<div><span><section><article>';
                        echo strip_tags($policy['content'], $allowed_tags);
                        ?>
                    </div>
                    <div class="card-footer text-muted small">
                        <i class="fas fa-clock me-1"></i>
                        Last updated: <?php echo date('F j, Y', strtotime($policy['updated_at'])); ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<style>
.policy-content h2 { font-size: 1.6rem; margin-top: 0; margin-bottom: 1rem; color: #2E7D32; }
.policy-content h3 { font-size: 1.2rem; margin-top: 1.75rem; margin-bottom: 0.5rem; color: #333; }
.policy-content p, .policy-content li { line-height: 1.8; color: #444; }
.policy-content ul { padding-left: 1.5rem; }
</style>
