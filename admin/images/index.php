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
    $stmt = $db->prepare("SELECT image_path FROM site_images WHERE id = ?");
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
    
    // Redirect to remove query parameter
    header('Location: index.php');
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

// Fetch all images
$stmt = $db->query("SELECT * FROM site_images ORDER BY section, display_order, created_at DESC");
$images = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-images"></i> Image Management</h4>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
            <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>
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

<div class="card">
    <div class="card-body">
        <?php if (empty($images)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No images uploaded yet.</p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Upload Your First Image
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="imagesTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" title="Select All">
                            </th>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Section</th>
                            <th>Event Category</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($images as $image): ?>
                            <tr data-image-id="<?php echo $image['id']; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input image-checkbox" value="<?php echo $image['id']; ?>">
                                </td>
                                <td>
                                    <?php 
                                    $image_url = UPLOAD_URL . $image['image_path'];
                                    if (file_exists(UPLOAD_PATH . $image['image_path'])): 
                                    ?>
                                        <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; border-radius: 4px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($image['title']); ?></strong>
                                    <?php if ($image['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($image['description'], 0, 50)); ?><?php echo strlen($image['description']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($image['section']); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($image['event_category'])): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($image['event_category']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $image['display_order']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $image['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($image['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($image['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?php echo $image['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view.php?id=<?php echo $image['id']; ?>" class="btn btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?delete=<?php echo $image['id']; ?>" class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this image? This action cannot be undone.');" 
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$extra_js = <<<'JS'
<script>
$(document).ready(function() {
    // Initialize DataTable with checkbox column excluded from sorting
    var table = $('#imagesTable').DataTable({
        "order": [[7, "desc"]], // Updated column index for Created
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "searchable": false, "targets": 0 } // Disable sorting on checkbox column
        ]
    });
    
    // Update selected count and show/hide bulk delete button
    function updateSelectedCount() {
        var count = $('.image-checkbox:checked').length;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }
    
    // Handle individual checkbox change
    $(document).on('change', '.image-checkbox', function() {
        updateSelectedCount();
        
        // Update "Select All" checkbox state
        var allChecked = $('.image-checkbox').length === $('.image-checkbox:checked').length;
        var someChecked = $('.image-checkbox:checked').length > 0 && !allChecked;
        
        $('#selectAllCheckbox').prop('checked', allChecked);
        $('#selectAllCheckbox').prop('indeterminate', someChecked);
    });
    
    // Handle "Select All" checkbox
    $('#selectAllCheckbox').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.image-checkbox').prop('checked', isChecked);
        $(this).prop('indeterminate', false);
        updateSelectedCount();
    });
    
    // Handle bulk delete button click
    $('#bulkDeleteBtn').on('click', function() {
        var selectedIds = [];
        $('.image-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Please select at least one image to delete.');
            return;
        }
        
        var confirmMsg = 'Are you sure you want to delete ' + selectedIds.length + ' image(s)?\n\nThis action cannot be undone.';
        if (!confirm(confirmMsg)) {
            return;
        }
        
        var csrfToken = $('#csrf_token').val();
        var $btn = $(this);
        
        // Disable button and show loading state
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        
        $.ajax({
            url: 'ajax-bulk-delete.php',
            method: 'POST',
            data: {
                csrf_token: csrfToken,
                image_ids: selectedIds.join(',')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remove deleted rows from table
                    if (response.deleted_ids && response.deleted_ids.length > 0) {
                        response.deleted_ids.forEach(function(id) {
                            var row = $('tr[data-image-id="' + id + '"]');
                            table.row(row).remove();
                        });
                        table.draw();
                    }
                    
                    // Reset select all checkbox
                    $('#selectAllCheckbox').prop('checked', false).prop('indeterminate', false);
                    updateSelectedCount();
                    
                    // Show success message
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message || 'Failed to delete images.');
                }
            },
            error: function(xhr, status, error) {
                showAlert('danger', 'An error occurred while deleting images. Please try again.');
            },
            complete: function() {
                // Re-enable button
                $btn.prop('disabled', false).html('<i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">' + $('.image-checkbox:checked').length + '</span>)');
            }
        });
    });
    
    // Helper function to show alert messages
    function showAlert(type, message) {
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            '<i class="fas ' + iconClass + '"></i> ' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>';
        
        // Remove existing alerts and add new one
        $('.card').first().before(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
    
    // Handle DataTable page change - reset "Select All" checkbox state
    table.on('draw', function() {
        updateSelectedCount();
        var allChecked = $('.image-checkbox').length > 0 && $('.image-checkbox').length === $('.image-checkbox:checked').length;
        var someChecked = $('.image-checkbox:checked').length > 0 && !allChecked;
        
        $('#selectAllCheckbox').prop('checked', allChecked);
        $('#selectAllCheckbox').prop('indeterminate', someChecked);
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/footer.php'; 
?>
