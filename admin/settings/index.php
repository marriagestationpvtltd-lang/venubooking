<?php
$page_title = 'Settings';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = str_replace('setting_', '', $key);
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $setting_key]);
        }
    }
    $success = 'Settings updated successfully!';
}

// Get all settings
$stmt = $db->query("SELECT * FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-cog"></i> System Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Site Name</label>
                    <input type="text" class="form-control" name="setting_site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Email</label>
                    <input type="email" class="form-control" name="setting_contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" class="form-control" name="setting_contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" name="setting_currency" value="<?php echo htmlspecialchars($settings['currency'] ?? 'NPR'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" class="form-control" name="setting_tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '13'); ?>" step="0.01">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Advance Payment (%)</label>
                    <input type="number" class="form-control" name="setting_advance_payment_percentage" value="<?php echo htmlspecialchars($settings['advance_payment_percentage'] ?? '30'); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Settings</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
