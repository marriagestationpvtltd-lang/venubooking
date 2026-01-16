<?php
$page_title = 'Change Password';
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long';
    } else {
        // Verify current password
        $db = getDB();
        $user_id = $_SESSION['admin_user_id'];
        
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            // Update password
            $new_password_hash = hashPassword($new_password);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$new_password_hash, $user_id])) {
                // Log activity
                logActivity($user_id, 'Password changed', 'users', $user_id);
                $success = 'Password changed successfully!';
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<style>
    .password-card {
        max-width: 600px;
        margin: 0 auto;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    .password-requirements {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }
    .password-requirements ul {
        margin: 0.5rem 0 0 0;
        padding-left: 1.5rem;
    }
</style>

<div class="card password-card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-key"></i> Change Password</h5>
        <small class="text-muted">Update your admin account password</small>
    </div>
    <div class="card-body">
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
        
        <form method="POST" action="" id="changePasswordForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="mb-3">
                <label for="current_password" class="form-label">
                    <i class="fas fa-lock"></i> Current Password *
                </label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            
            <div class="mb-3">
                <label for="new_password" class="form-label">
                    <i class="fas fa-key"></i> New Password *
                </label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li>Minimum 6 characters</li>
                        <li>Use a combination of letters, numbers, and special characters for better security</li>
                    </ul>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-check-double"></i> Confirm New Password *
                </label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Client-side validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirm password do not match');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters long');
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
