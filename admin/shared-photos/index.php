<?php
/**
 * Shared Photos Management - List all shared photos
 * Admin can view, copy download links, and delete photos
 */

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
    $stmt = $db->prepare("SELECT image_path, title FROM shared_photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();
    
    if ($photo) {
        // Delete from database
        $delete_stmt = $db->prepare("DELETE FROM shared_photos WHERE id = ?");
        if ($delete_stmt->execute([$id])) {
            // Delete physical file completely
            $file_path = UPLOAD_PATH . $photo['image_path'];
            
            // Security: Ensure file is within uploads directory
            $real_upload_path = realpath(UPLOAD_PATH);
            $real_file_path = realpath($file_path);
            
            if ($real_file_path && $real_upload_path && strpos($real_file_path, $real_upload_path) === 0) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            logActivity($current_user['id'], 'Deleted shared photo', 'shared_photos', $id, "Permanently deleted: " . $photo['title']);
            $_SESSION['success_message'] = 'Photo deleted permanently!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete photo.';
        }
    }
    
    header('Location: index.php');
    exit;
}

$page_title = 'फोटो सेयर व्यवस्थापन (Photo Sharing)';
require_once __DIR__ . '/../includes/header.php';

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Check for URL-based success message (from upload redirect)
if (isset($_GET['success']) && is_numeric($_GET['success'])) {
    $count = intval($_GET['success']);
    $success_message = $count . ' photo' . ($count > 1 ? 's' : '') . ' uploaded successfully!';
}

// Clear session messages after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch all shared photos
$stmt = $db->query("SELECT sp.*, u.full_name as uploaded_by_name 
                    FROM shared_photos sp 
                    LEFT JOIN users u ON sp.created_by = u.id 
                    ORDER BY sp.created_at DESC");
$photos = $stmt->fetchAll();

// Generate base download URL
$download_base_url = BASE_URL . '/download.php?token=';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fas fa-share-alt"></i> फोटो सेयर व्यवस्थापन</h4>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
            <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>
        <a href="add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Upload New Photo
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

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> <strong>कसरी प्रयोग गर्ने:</strong>
    <ul class="mb-0 mt-2">
        <li><strong>फोटो अपलोड:</strong> "Upload New Photo" बटन क्लिक गरेर फोटो अपलोड गर्नुहोस्</li>
        <li><strong>लिङ्क सेयर:</strong> "Copy Link" बटन क्लिक गरेर डाउनलोड लिङ्क कपी गर्नुहोस् र युजरलाई पठाउनुहोस्</li>
        <li><strong>युजर डाउनलोड:</strong> युजरले उक्त लिङ्कबाट आफ्नो फोटो डाउनलोड गर्न सक्छन्</li>
        <li><strong>फोटो डिलिट:</strong> युजरले डाउनलोड गरिसकेपछि "Delete" बटन क्लिक गरेर फोटो पूर्ण रूपमा हटाउनुहोस्</li>
    </ul>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($photos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-share-alt fa-4x text-muted mb-3"></i>
                <p class="text-muted">No shared photos yet.</p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Upload Your First Photo
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="photosTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" title="Select All">
                            </th>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Download Link</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th>Uploaded By</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($photos as $photo): 
                            $download_url = $download_base_url . urlencode($photo['download_token']);
                            $is_expired = ($photo['expires_at'] && strtotime($photo['expires_at']) < time()) || $photo['status'] === 'expired';
                            $max_reached = ($photo['max_downloads'] && $photo['download_count'] >= $photo['max_downloads']);
                        ?>
                            <tr data-photo-id="<?php echo $photo['id']; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input photo-checkbox" value="<?php echo $photo['id']; ?>">
                                </td>
                                <td>
                                    <?php 
                                    $image_url = UPLOAD_URL . $photo['image_path'];
                                    if (file_exists(UPLOAD_PATH . $photo['image_path'])): 
                                    ?>
                                        <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($photo['title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; border-radius: 4px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($photo['title']); ?></strong>
                                    <?php if ($photo['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($photo['description'], 0, 50)); ?><?php echo strlen($photo['description']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 200px;">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm download-link" 
                                               value="<?php echo htmlspecialchars($download_url); ?>" readonly
                                               style="font-size: 11px;">
                                        <button class="btn btn-outline-primary copy-link-btn" type="button" 
                                                data-url="<?php echo htmlspecialchars($download_url); ?>"
                                                title="Copy Link">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $photo['download_count']; ?></span>
                                    <?php if ($photo['max_downloads']): ?>
                                        / <?php echo $photo['max_downloads']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge bg-secondary">Expired</span>
                                    <?php elseif ($max_reached): ?>
                                        <span class="badge bg-warning text-dark">Max Reached</span>
                                    <?php elseif ($photo['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($photo['status']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($photo['expires_at']): ?>
                                        <br><small class="text-muted">
                                            Expires: <?php echo date('M d, Y', strtotime($photo['expires_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($photo['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($photo['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo $download_url; ?>" class="btn btn-outline-info" title="Preview" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?delete=<?php echo $photo['id']; ?>" class="btn btn-outline-danger" 
                                           onclick="return confirm('के तपाईं यो फोटो स्थायी रूपमा हटाउन चाहनुहुन्छ?\n\nयो कार्य उल्टाउन सकिँदैन।');" 
                                           title="Delete Permanently">
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
    // Initialize DataTable
    var table = $('#photosTable').DataTable({
        "order": [[7, "desc"]], // Column 7 = Created date
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "searchable": false, "targets": [0, 3, 8] }
        ]
    });
    
    // Copy link functionality
    $(document).on('click', '.copy-link-btn', function() {
        var url = $(this).data('url');
        var $btn = $(this);
        
        navigator.clipboard.writeText(url).then(function() {
            // Show success feedback
            var originalHtml = $btn.html();
            $btn.html('<i class="fas fa-check"></i>');
            $btn.removeClass('btn-outline-primary').addClass('btn-success');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        }).catch(function() {
            // Fallback for older browsers
            var $input = $btn.closest('.input-group').find('input');
            $input.select();
            document.execCommand('copy');
            
            var originalHtml = $btn.html();
            $btn.html('<i class="fas fa-check"></i>');
            $btn.removeClass('btn-outline-primary').addClass('btn-success');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        });
    });
    
    // Update selected count and show/hide bulk delete button
    function updateSelectedCount() {
        var count = $('.photo-checkbox:checked').length;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }
    
    // Handle individual checkbox change
    $(document).on('change', '.photo-checkbox', function() {
        updateSelectedCount();
        
        var allChecked = $('.photo-checkbox').length === $('.photo-checkbox:checked').length;
        var someChecked = $('.photo-checkbox:checked').length > 0 && !allChecked;
        
        $('#selectAllCheckbox').prop('checked', allChecked);
        $('#selectAllCheckbox').prop('indeterminate', someChecked);
    });
    
    // Handle "Select All" checkbox
    $('#selectAllCheckbox').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.photo-checkbox').prop('checked', isChecked);
        $(this).prop('indeterminate', false);
        updateSelectedCount();
    });
    
    // Handle bulk delete button click
    $('#bulkDeleteBtn').on('click', function() {
        var selectedIds = [];
        $('.photo-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Please select at least one photo to delete.');
            return;
        }
        
        var confirmMsg = 'के तपाईं ' + selectedIds.length + ' फोटो(हरू) स्थायी रूपमा हटाउन चाहनुहुन्छ?\n\nयो कार्य उल्टाउन सकिँदैन।';
        if (!confirm(confirmMsg)) {
            return;
        }
        
        var csrfToken = $('#csrf_token').val();
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $btn.find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');
        
        $.ajax({
            url: 'ajax-bulk-delete.php',
            method: 'POST',
            data: {
                csrf_token: csrfToken,
                photo_ids: selectedIds.join(',')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.deleted_ids && response.deleted_ids.length > 0) {
                        response.deleted_ids.forEach(function(id) {
                            var row = $('tr[data-photo-id="' + id + '"]');
                            table.row(row).remove();
                        });
                        table.draw();
                    }
                    
                    $('#selectAllCheckbox').prop('checked', false).prop('indeterminate', false);
                    updateSelectedCount();
                    
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message || 'Failed to delete photos.');
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred while deleting photos. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
            }
        });
    });
    
    function showAlert(type, message) {
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        var $alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            '<i class="fas ' + iconClass + '"></i> ' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        
        $('.card').first().before($alert);
        
        setTimeout(function() {
            $alert.alert('close');
        }, 5000);
    }
    
    table.on('draw', function() {
        updateSelectedCount();
        var allChecked = $('.photo-checkbox').length > 0 && $('.photo-checkbox').length === $('.photo-checkbox:checked').length;
        var someChecked = $('.photo-checkbox:checked').length > 0 && !allChecked;
        
        $('#selectAllCheckbox').prop('checked', allChecked);
        $('#selectAllCheckbox').prop('indeterminate', someChecked);
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/footer.php'; 
?>
