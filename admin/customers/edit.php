<?php
$page_title = 'Edit Customer';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch customer details
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: index.php');
    exit;
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        // Check if customer has bookings
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?");
        $check_stmt->execute([$customer_id]);
        $result = $check_stmt->fetch();
        
        if ($result['count'] > 0) {
            header('Location: index.php?error=' . urlencode('Cannot delete customer. They have existing bookings in the system.'));
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
        if ($stmt->execute([$customer_id])) {
            // Log activity
            logActivity($current_user['id'], 'Deleted customer', 'customers', $customer_id, "Deleted customer: {$customer['full_name']}");
            
            header('Location: index.php?deleted=1');
            exit;
        } else {
            header('Location: index.php?error=' . urlencode('Failed to delete customer. Please try again.'));
            exit;
        }
    } catch (Exception $e) {
        header('Location: index.php?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validation
    if (empty($full_name) || empty($phone)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Check if phone already exists for another customer
        $check_stmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $check_stmt->execute([$phone, $customer_id]);
        if ($check_stmt->fetch()) {
            $error_message = 'Phone number already exists for another customer.';
        } else {
            try {
                $sql = "UPDATE customers SET 
                        full_name = ?,
                        phone = ?,
                        email = ?,
                        address = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    $full_name,
                    $phone,
                    $email,
                    $address,
                    $customer_id
                ]);

                if ($result) {
                    // Log activity
                    logActivity($current_user['id'], 'Updated customer', 'customers', $customer_id, "Updated customer: $full_name");
                    
                    $success_message = 'Customer updated successfully!';
                    
                    // Refresh customer data
                    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    $customer = $stmt->fetch();
                } else {
                    $error_message = 'Failed to update customer. Please try again.';
                }
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Customer</h5>
                <div>
                    <a href="view.php?id=<?php echo $customer_id; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($customer['full_name']); ?>" 
                                       placeholder="e.g., John Doe" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($customer['phone']); ?>" 
                                       placeholder="e.g., +977 9876543210" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($customer['email']); ?>" 
                               placeholder="e.g., customer@example.com">
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                                  placeholder="Enter complete address..."><?php echo htmlspecialchars($customer['address']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Customer
                        </button>
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Customer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        window.location.href = 'edit.php?id=<?php echo $customer_id; ?>&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
