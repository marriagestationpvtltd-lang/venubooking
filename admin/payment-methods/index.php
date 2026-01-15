<?php
$page_title = 'Payment Methods';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $bank_details = trim($_POST['bank_details']);
        $status = $_POST['status'];
        $display_order = intval($_POST['display_order']);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($name)) {
            $error = 'Payment method name is required.';
        } else {
            try {
                $db->beginTransaction();
                
                // Handle QR code upload
                $qr_code = '';
                if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = handleImageUpload($_FILES['qr_code'], 'payment-qr');
                    if ($upload_result['success']) {
                        $qr_code = $upload_result['filename'];
                        
                        // Delete old QR code if editing
                        if ($action === 'edit' && $id > 0) {
                            $stmt = $db->prepare("SELECT qr_code FROM payment_methods WHERE id = ?");
                            $stmt->execute([$id]);
                            $old_data = $stmt->fetch();
                            if ($old_data && !empty($old_data['qr_code'])) {
                                deleteUploadedFile($old_data['qr_code']);
                            }
                        }
                    } else {
                        throw new Exception('QR code upload failed: ' . $upload_result['message']);
                    }
                }
                
                if ($action === 'add') {
                    $sql = "INSERT INTO payment_methods (name, qr_code, bank_details, status, display_order) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $qr_code, $bank_details, $status, $display_order]);
                    logActivity($current_user['id'], 'Added payment method', 'payment_methods', $db->lastInsertId(), "Added payment method: {$name}");
                    $success = 'Payment method added successfully!';
                } else {
                    // For edit, only update QR code if a new one was uploaded
                    if (!empty($qr_code)) {
                        $sql = "UPDATE payment_methods SET name = ?, qr_code = ?, bank_details = ?, status = ?, display_order = ? WHERE id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$name, $qr_code, $bank_details, $status, $display_order, $id]);
                    } else {
                        $sql = "UPDATE payment_methods SET name = ?, bank_details = ?, status = ?, display_order = ? WHERE id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$name, $bank_details, $status, $display_order, $id]);
                    }
                    logActivity($current_user['id'], 'Updated payment method', 'payment_methods', $id, "Updated payment method: {$name}");
                    $success = 'Payment method updated successfully!';
                }
                
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        try {
            $db->beginTransaction();
            
            // Get QR code to delete
            $stmt = $db->prepare("SELECT name, qr_code FROM payment_methods WHERE id = ?");
            $stmt->execute([$id]);
            $method = $stmt->fetch();
            
            if ($method) {
                // Delete QR code file if exists
                if (!empty($method['qr_code'])) {
                    deleteUploadedFile($method['qr_code']);
                }
                
                // Delete payment method
                $stmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($current_user['id'], 'Deleted payment method', 'payment_methods', $id, "Deleted payment method: {$method['name']}");
                $success = 'Payment method deleted successfully!';
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error deleting payment method: ' . $e->getMessage();
        }
    }
}

// Fetch all payment methods
$stmt = $db->query("SELECT * FROM payment_methods ORDER BY display_order ASC, name ASC");
$payment_methods = $stmt->fetchAll();
?>

<style>
    .payment-method-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: white;
        transition: box-shadow 0.3s;
    }
    .payment-method-card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .qr-preview {
        max-width: 150px;
        max-height: 150px;
        object-fit: contain;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 0.25rem;
    }
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .status-active {
        background-color: #d4edda;
        color: #155724;
    }
    .status-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }
    .bank-details-text {
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 0.875rem;
        background-color: #f8f9fa;
        padding: 0.5rem;
        border-radius: 4px;
    }
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Methods</h5>
            <small class="text-muted">Manage payment methods for booking confirmations</small>
        </div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMethodModal">
            <i class="fas fa-plus"></i> Add Payment Method
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($payment_methods)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payment methods found. Add your first payment method to get started.
            </div>
        <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
                <div class="payment-method-card">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <?php if (!empty($method['qr_code'])): ?>
                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>" 
                                     alt="QR Code" class="qr-preview">
                            <?php else: ?>
                                <div class="text-muted text-center" style="min-height: 80px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-qrcode fa-3x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <h6 class="mb-2">
                                <?php echo htmlspecialchars($method['name']); ?>
                                <span class="status-badge status-<?php echo $method['status']; ?>">
                                    <?php echo ucfirst($method['status']); ?>
                                </span>
                            </h6>
                            <?php if (!empty($method['bank_details'])): ?>
                                <div class="bank-details-text"><?php echo htmlspecialchars($method['bank_details']); ?></div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No bank details provided</p>
                            <?php endif; ?>
                            <small class="text-muted">Display Order: <?php echo $method['display_order']; ?></small>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="editMethod(<?php echo htmlspecialchars(json_encode($method)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $method['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Payment Method Modal -->
<div class="modal fade" id="addMethodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="addMethodForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Method Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name" name="name" required
                               placeholder="e.g., Bank Transfer, eSewa, Khalti">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_qr_code" class="form-label">QR Code (Optional)</label>
                        <input type="file" class="form-control" id="add_qr_code" name="qr_code" accept="image/*">
                        <div class="form-text">Upload a QR code image for this payment method</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_bank_details" class="form-label">Bank Details / Account Info (Optional)</label>
                        <textarea class="form-control" id="add_bank_details" name="bank_details" rows="5"
                                  placeholder="Enter bank account details, payment instructions, etc."></textarea>
                        <div class="form-text">Add relevant payment information like account number, bank name, branch, etc.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="add_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="add_display_order" name="display_order" 
                                   value="<?php echo count($payment_methods) + 1; ?>" min="0">
                            <div class="form-text">Lower numbers appear first</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Payment Method
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Payment Method Modal -->
<div class="modal fade" id="editMethodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="editMethodForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Method Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_qr_code" class="form-label">QR Code</label>
                        <input type="file" class="form-control" id="edit_qr_code" name="qr_code" accept="image/*">
                        <div class="form-text">Upload a new QR code to replace the existing one (leave empty to keep current)</div>
                        <div id="current_qr_preview" class="mt-2"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_bank_details" class="form-label">Bank Details / Account Info</label>
                        <textarea class="form-control" id="edit_bank_details" name="bank_details" rows="5"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Payment Method
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMethod(method) {
    document.getElementById('edit_id').value = method.id;
    document.getElementById('edit_name').value = method.name;
    document.getElementById('edit_bank_details').value = method.bank_details || '';
    document.getElementById('edit_status').value = method.status;
    document.getElementById('edit_display_order').value = method.display_order;
    
    // Show current QR code if exists
    const qrPreview = document.getElementById('current_qr_preview');
    if (method.qr_code) {
        qrPreview.innerHTML = '<p class="text-muted small">Current QR Code:</p><img src="<?php echo UPLOAD_URL; ?>' + method.qr_code + '" alt="Current QR" class="qr-preview">';
    } else {
        qrPreview.innerHTML = '<p class="text-muted small">No QR code currently uploaded</p>';
    }
    
    const editModal = new bootstrap.Modal(document.getElementById('editMethodModal'));
    editModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
