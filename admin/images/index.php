<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get image path before deletion
    $stmt = $db->prepare("SELECT image_path, section, event_category FROM site_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM site_images WHERE id = ?");
        if ($delete_stmt->execute([$id])) {
            // Delete physical file
            $file_path = UPLOAD_PATH . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            logActivity($current_user['id'], 'Deleted image', 'site_images', $id, "Deleted image: " . $image['image_path']);
            $_SESSION['success_message'] = 'Image deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete image.';
        }
    }
    
    // Redirect back to the same folder view
    $redirect_section = (!empty($image) && !empty($image['section']))
        ? $image['section']
        : (isset($_GET['section']) ? trim($_GET['section']) : '');
    $redirect_cat = (!empty($image) && !empty($image['event_category']))
        ? $image['event_category']
        : (isset($_GET['cat']) ? trim($_GET['cat']) : '');
    $redirect = 'index.php';
    if ($redirect_section !== '') {
        $redirect .= '?section=' . urlencode($redirect_section);
        if ($redirect_cat !== '') {
            $redirect .= '&cat=' . urlencode($redirect_cat);
        }
    }
    header('Location: ' . $redirect);
    exit;
}

$page_title = 'Manage Images';
require_once __DIR__ . '/../includes/header.php';

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Check for URL-based success message (from AJAX upload redirect)
if (isset($_GET['success']) && is_numeric($_GET['success'])) {
    $count = intval($_GET['success']);
    $success_message = $count . ' image' . ($count > 1 ? 's' : '') . ' uploaded successfully!';
}

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Determine current view: folder list or images inside a folder
$view_section  = isset($_GET['section'])  ? trim($_GET['section'])  : '';
$view_category = isset($_GET['cat'])      ? trim($_GET['cat'])      : '';

// Section labels map
$section_labels = [
    'banner'      => 'Banner / Hero Section',
    'venue'       => 'Venue Gallery',
    'hall'        => 'Hall Gallery',
    'package'     => 'Package/Menu Images',
    'gallery'     => 'General Gallery',
    'work_photos' => 'Our Work (Folder Gallery)',
    'testimonial' => 'Testimonials',
    'feature'     => 'Features Section',
    'about'       => 'About Us Section',
    'other'       => 'Other',
];

if ($view_section !== '') {
    // ── FOLDER CONTENTS VIEW ──────────────────────────────────────────────
    // Fetch images for the selected section (and optionally category)
    if ($view_section === 'work_photos' && $view_category !== '') {
        $stmt = $db->prepare(
            "SELECT * FROM site_images
              WHERE section = ? AND event_category = ?
              ORDER BY display_order ASC, created_at DESC"
        );
        $stmt->execute([$view_section, $view_category]);
    } else {
        $stmt = $db->prepare(
            "SELECT * FROM site_images
              WHERE section = ?
              ORDER BY event_category ASC, display_order ASC, created_at DESC"
        );
        $stmt->execute([$view_section]);
    }
    $images = $stmt->fetchAll();

    // Build back-link
    if ($view_section === 'work_photos' && $view_category !== '') {
        $back_url   = 'index.php?section=work_photos';
        $back_label = htmlspecialchars($section_labels['work_photos'], ENT_QUOTES, 'UTF-8');
    } else {
        $back_url   = 'index.php';
        $back_label = 'All Folders';
    }

    // Human-readable current folder title
    if ($view_section === 'work_photos' && $view_category !== '') {
        $folder_title = htmlspecialchars($view_category, ENT_QUOTES, 'UTF-8');
    } elseif ($view_section === 'work_photos') {
        $folder_title = htmlspecialchars($section_labels['work_photos'], ENT_QUOTES, 'UTF-8');
    } else {
        $folder_title = htmlspecialchars($section_labels[$view_section] ?? ucfirst($view_section), ENT_QUOTES, 'UTF-8');
    }
} else {
    // ── TOP-LEVEL FOLDER LIST VIEW ────────────────────────────────────────
    // For work_photos: one folder per event_category (or "Uncategorized")
    // For all other sections: one folder per section
    $folder_rows = $db->query(
        "SELECT
            section,
            COALESCE(event_category, '') AS event_category,
            COUNT(*)                     AS image_count,
            MIN(image_path)              AS preview_path
         FROM site_images
         GROUP BY section,
                  CASE WHEN section = 'work_photos' THEN COALESCE(event_category,'') ELSE '' END
         ORDER BY section ASC, event_category ASC"
    )->fetchAll();

    // For work_photos we may get multiple rows (one per category).
    // Collapse non-work_photos rows to single folder-per-section.
    $folders = [];
    foreach ($folder_rows as $row) {
        if ($row['section'] === 'work_photos') {
            $cat   = $row['event_category'] !== '' ? $row['event_category'] : 'Uncategorized';
            $url   = 'index.php?section=work_photos&cat=' . urlencode($cat);
            $label = $cat;
            $icon  = 'fa-folder text-warning';
            $folders[] = [
                'url'         => $url,
                'label'       => $label,
                'icon'        => $icon,
                'image_count' => $row['image_count'],
                'preview'     => $row['preview_path'],
                'section'     => $row['section'],
            ];
        } else {
            $sec   = $row['section'];
            // Merge all rows for the same section (shouldn't have multiple, but just in case)
            if (!isset($folders['sec_' . $sec])) {
                $folders['sec_' . $sec] = [
                    'url'         => 'index.php?section=' . urlencode($sec),
                    'label'       => $section_labels[$sec] ?? ucfirst($sec),
                    'icon'        => 'fa-folder text-primary',
                    'image_count' => 0,
                    'preview'     => $row['preview_path'],
                    'section'     => $sec,
                ];
            }
            $folders['sec_' . $sec]['image_count'] += $row['image_count'];
        }
    }
    $folders = array_values($folders);
}
?>

<!-- ── PAGE HEADER ───────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        <i class="fas fa-images"></i> Image Management
        <?php if ($view_section !== ''): ?>
            <small class="text-muted fs-6 ms-2">
                / <a href="index.php" class="text-decoration-none text-muted">All Folders</a>
                <?php if ($view_section === 'work_photos'): ?>
                    / <a href="index.php?section=work_photos" class="text-decoration-none text-muted"><?php echo htmlspecialchars($section_labels['work_photos']); ?></a>
                <?php endif; ?>
                <?php if ($view_category !== ''): ?>
                    / <?php echo htmlspecialchars($view_category, ENT_QUOTES, 'UTF-8'); ?>
                <?php elseif ($view_section !== 'work_photos'): ?>
                    / <?php echo htmlspecialchars($section_labels[$view_section] ?? ucfirst($view_section), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </small>
        <?php endif; ?>
    </h4>
    <div class="d-flex gap-2">
        <?php if ($view_section !== ''): ?>
            <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
                <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
            </button>
        <?php endif; ?>
        <a href="add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Upload New Image
        </a>
    </div>
</div>

<!-- Hidden CSRF token for AJAX requests -->
<input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

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

<?php if ($view_section === ''): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     TOP-LEVEL FOLDER LIST
     ══════════════════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-body">
        <?php if (empty($folders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <p class="text-muted">No images uploaded yet.</p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Upload Your First Image
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($folders as $folder): ?>
                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                        <a href="<?php echo htmlspecialchars($folder['url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="text-decoration-none">
                            <div class="card h-100 shadow-sm folder-card border-0">
                                <div class="folder-card-thumb bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                     style="height:130px; border-radius:.375rem .375rem 0 0;">
                                    <?php if (!empty($folder['preview']) && file_exists(UPLOAD_PATH . $folder['preview'])): ?>
                                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . $folder['preview'], ENT_QUOTES, 'UTF-8'); ?>"
                                             alt="<?php echo htmlspecialchars($folder['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                             style="width:100%; height:130px; object-fit:cover;">
                                    <?php else: ?>
                                        <i class="fas fa-folder fa-4x text-secondary opacity-50"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <div class="fw-semibold text-dark small text-truncate" title="<?php echo htmlspecialchars($folder['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas <?php echo htmlspecialchars($folder['icon'], ENT_QUOTES, 'UTF-8'); ?> me-1"></i>
                                        <?php echo htmlspecialchars($folder['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?php echo $folder['image_count']; ?> image<?php echo (int)$folder['image_count'] !== 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════
     FOLDER CONTENTS (images inside a folder)
     ══════════════════════════════════════════════════════════════════════ -->
<div class="mb-3">
    <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to <?php echo $back_label; ?>
    </a>
</div>

<?php
// For work_photos with no specific category selected: show sub-folders by event_category
if ($view_section === 'work_photos' && $view_category === ''):
    // Group images by event_category
    $cat_groups = [];
    foreach ($images as $img) {
        $cat = !empty($img['event_category']) ? $img['event_category'] : 'Uncategorized';
        if (!isset($cat_groups[$cat])) {
            $cat_groups[$cat] = ['count' => 0, 'preview' => null];
        }
        $cat_groups[$cat]['count']++;
        if ($cat_groups[$cat]['preview'] === null) {
            $cat_groups[$cat]['preview'] = $img['image_path'];
        }
    }
?>
    <div class="card">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-folder-open me-2 text-warning"></i><?php echo $folder_title; ?> — Event Category Folders</h6>
        </div>
        <div class="card-body">
            <?php if (empty($cat_groups)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No images in this section yet.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($cat_groups as $cat_name => $cat_info): ?>
                        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                            <a href="index.php?section=work_photos&cat=<?php echo urlencode($cat_name); ?>"
                               class="text-decoration-none">
                                <div class="card h-100 shadow-sm folder-card border-0">
                                    <div class="folder-card-thumb bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                         style="height:130px; border-radius:.375rem .375rem 0 0;">
                                        <?php if (!empty($cat_info['preview']) && file_exists(UPLOAD_PATH . $cat_info['preview'])): ?>
                                            <img src="<?php echo htmlspecialchars(UPLOAD_URL . $cat_info['preview'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                 style="width:100%; height:130px; object-fit:cover;">
                                        <?php else: ?>
                                            <i class="fas fa-folder fa-4x text-warning opacity-75"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-2 text-center">
                                        <div class="fw-semibold text-dark small text-truncate" title="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-folder text-warning me-1"></i>
                                            <?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <?php echo $cat_info['count']; ?> image<?php echo $cat_info['count'] !== 1 ? 's' : ''; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
<!-- Images grid inside a specific folder -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-folder-open me-2 text-warning"></i><?php echo $folder_title; ?>
            <span class="badge bg-secondary ms-2"><?php echo count($images); ?> image<?php echo count($images) !== 1 ? 's' : ''; ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($images)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No images in this folder yet.</p>
                <a href="add.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Upload Images
                </a>
            </div>
        <?php else: ?>
            <!-- Bulk action bar -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" title="Select All">
                <label for="selectAllCheckbox" class="form-check-label small text-muted">Select All</label>
                <button type="button" class="btn btn-danger btn-sm ms-2" id="bulkDeleteBtn" style="display:none;">
                    <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>

            <div class="row g-3" id="imagesGrid">
                <?php foreach ($images as $image): ?>
                    <div class="col-6 col-sm-4 col-md-3 col-xl-2 image-grid-item" data-image-id="<?php echo $image['id']; ?>">
                        <div class="card h-100 shadow-sm border position-relative image-card
                            <?php echo $image['status'] === 'inactive' ? 'opacity-50' : ''; ?>">
                            <!-- Checkbox overlay -->
                            <div class="position-absolute top-0 start-0 m-1" style="z-index:2;">
                                <input type="checkbox" class="form-check-input image-checkbox"
                                       value="<?php echo $image['id']; ?>"
                                       style="width:1.2rem; height:1.2rem; cursor:pointer;">
                            </div>
                            <!-- Status badge -->
                            <?php if ($image['status'] === 'inactive'): ?>
                                <div class="position-absolute top-0 end-0 m-1" style="z-index:2;">
                                    <span class="badge bg-secondary" style="font-size:.65rem;">Inactive</span>
                                </div>
                            <?php endif; ?>
                            <!-- Thumbnail -->
                            <div class="bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                 style="height:120px; border-radius:.375rem .375rem 0 0;">
                                <?php if (file_exists(UPLOAD_PATH . $image['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $image['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="<?php echo htmlspecialchars($image['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         style="width:100%; height:120px; object-fit:cover;"
                                         loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x text-secondary opacity-50"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-2">
                                <div class="small fw-semibold text-truncate text-dark"
                                     title="<?php echo htmlspecialchars($image['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($image['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="text-muted" style="font-size:.7rem;">
                                    Order: <?php echo $image['display_order']; ?>
                                    &nbsp;·&nbsp; <?php echo date('M d, Y', strtotime($image['created_at'])); ?>
                                </div>
                                <!-- Action buttons -->
                                <div class="d-flex gap-1 mt-2">
                                    <a href="edit.php?id=<?php echo $image['id']; ?>"
                                       class="btn btn-outline-primary btn-sm flex-fill" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view.php?id=<?php echo $image['id']; ?>"
                                       class="btn btn-outline-info btn-sm flex-fill" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?delete=<?php echo $image['id']; ?>&section=<?php echo urlencode($view_section); ?>&cat=<?php echo urlencode($view_category); ?>"
                                       class="btn btn-outline-danger btn-sm flex-fill"
                                       onclick="return confirm('Delete this image? This cannot be undone.');"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.folder-card { transition: transform .15s, box-shadow .15s; cursor: pointer; }
.folder-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; }
.image-card { transition: box-shadow .15s; }
.image-card:hover { box-shadow: 0 .4rem .8rem rgba(0,0,0,.15) !important; }
</style>

<?php 
$extra_js = <<<'JS'
<script>
$(document).ready(function() {
    // Only initialise bulk-delete logic when inside a folder (images view)
    if (!$('#imagesGrid').length) return;

    function updateSelectedCount() {
        var count = $('.image-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#bulkDeleteBtn').toggle(count > 0);
    }

    // Individual checkbox
    $(document).on('change', '.image-checkbox', function() {
        updateSelectedCount();
        var total    = $('.image-checkbox').length;
        var checked  = $('.image-checkbox:checked').length;
        $('#selectAllCheckbox')
            .prop('checked', checked === total && total > 0)
            .prop('indeterminate', checked > 0 && checked < total);
    });

    // Select-all checkbox
    $('#selectAllCheckbox').on('change', function() {
        $('.image-checkbox').prop('checked', $(this).prop('checked'));
        $(this).prop('indeterminate', false);
        updateSelectedCount();
    });

    // Bulk-delete button
    $('#bulkDeleteBtn').on('click', function() {
        var selectedIds = [];
        $('.image-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        if (!selectedIds.length) return;
        if (!confirm('Delete ' + selectedIds.length + ' image(s)? This cannot be undone.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');

        $.ajax({
            url: 'ajax-bulk-delete.php',
            method: 'POST',
            data: { csrf_token: $('#csrf_token').val(), image_ids: selectedIds.join(',') },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    (response.deleted_ids || []).forEach(function(id) {
                        $('.image-grid-item[data-image-id="' + id + '"]').remove();
                    });
                    $('#selectAllCheckbox').prop('checked', false).prop('indeterminate', false);
                    updateSelectedCount();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message || 'Failed to delete images.');
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
            }
        });
    });

    function showAlert(type, message) {
        var icon  = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        var $alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            '<i class="fas ' + icon + '"></i> ' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        $('.card').first().before($alert);
        setTimeout(function() { $alert.alert('close'); }, 5000);
    }
});
</script>
JS;

require_once __DIR__ . '/../includes/footer.php'; 
?>
