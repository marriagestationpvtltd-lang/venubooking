<?php
$page_title = 'View Image';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$error_message = '';

// Get image ID
$image_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($image_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch image details
$stmt = $db->prepare("SELECT * FROM site_images WHERE id = ?");
$stmt->execute([$image_id]);
$image = $stmt->fetch();

if (!$image) {
    $_SESSION['error_message'] = 'Image not found.';
    header('Location: index.php');
    exit;
}

$image_url = UPLOAD_URL . $image['image_path'];
$image_exists = file_exists(UPLOAD_PATH . $image['image_path']);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-image"></i> Image Details</h5>
                <div>
                    <a href="edit.php?id=<?php echo $image['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Image Preview</h6>
                        <div class="border rounded p-3 text-center bg-light">
                            <?php if ($image_exists): ?>
                                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" 
                                     class="img-fluid" style="max-height: 500px; object-fit: contain;">
                            <?php else: ?>
                                <div class="text-danger py-5">
                                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                    <p>Image file not found on server</p>
                                    <small class="text-muted"><?php echo htmlspecialchars($image['image_path']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($image_exists): ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($image_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> Open in New Tab
                                </a>
                                <button onclick="copyToClipboard(<?php echo htmlspecialchars(json_encode($image_url), ENT_QUOTES); ?>)" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-copy"></i> Copy URL
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Image Information</h6>
                        
                        <table class="table table-bordered">
                            <tr>
                                <th width="35%">Title</th>
                                <td><?php echo htmlspecialchars($image['title']); ?></td>
                            </tr>
                            <tr>
                                <th>Section</th>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($image['section']); ?></span></td>
                            </tr>
                            <tr>
                                <th>Display Order</th>
                                <td><?php echo $image['display_order']; ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge bg-<?php echo $image['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($image['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td><?php echo $image['description'] ? htmlspecialchars($image['description']) : '<em class="text-muted">No description</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>File Path</th>
                                <td><code><?php echo htmlspecialchars($image['image_path']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Full URL</th>
                                <td><code><?php echo htmlspecialchars($image_url); ?></code></td>
                            </tr>
                            <?php if ($image_exists): 
                                $file_path = UPLOAD_PATH . $image['image_path'];
                                $file_size = filesize($file_path);
                                $image_info = getimagesize($file_path);
                            ?>
                            <tr>
                                <th>Dimensions</th>
                                <td><?php echo $image_info[0]; ?> × <?php echo $image_info[1]; ?> px</td>
                            </tr>
                            <tr>
                                <th>File Size</th>
                                <td><?php echo formatBytes($file_size); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Created</th>
                                <td><?php echo date('F d, Y h:i A', strtotime($image['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td><?php echo date('F d, Y h:i A', strtotime($image['updated_at'])); ?></td>
                            </tr>
                        </table>
                        
                        <div class="mt-3">
                            <a href="edit.php?id=<?php echo $image['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Image
                            </a>
                            <a href="index.php?delete=<?php echo $image['id']; ?>" class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this image? This action cannot be undone.');">
                                <i class="fas fa-trash"></i> Delete Image
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    // Modern approach with fallback
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('URL copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    // Fallback for older browsers
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.width = "2em";
    textArea.style.height = "2em";
    textArea.style.padding = "0";
    textArea.style.border = "none";
    textArea.style.outline = "none";
    textArea.style.boxShadow = "none";
    textArea.style.background = "transparent";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            alert('URL copied to clipboard!');
        } else {
            alert('Failed to copy URL. Please copy manually: ' + text);
        }
    } catch (err) {
        alert('Failed to copy URL. Please copy manually: ' + text);
    }
    
    document.body.removeChild(textArea);
}
</script>

<?php
// Helper function to format bytes
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

require_once __DIR__ . '/../includes/footer.php'; 
?>
