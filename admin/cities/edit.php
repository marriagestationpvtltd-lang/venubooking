<?php
$page_title = 'Edit City';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

$city_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($city_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM cities WHERE id = ?");
$stmt->execute([$city_id]);
$city = $stmt->fetch();

if (!$city) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $status = $_POST['status'];

    if (empty($name)) {
        $error_message = 'City name is required.';
    } else {
        try {
            $stmt = $db->prepare("UPDATE cities SET name = ?, status = ? WHERE id = ?");
            $result = $stmt->execute([$name, $status, $city_id]);

            if ($result) {
                logActivity($current_user['id'], 'Updated city', 'cities', $city_id, "Updated city: $name");
                $success_message = 'City updated successfully!';

                $stmt = $db->prepare("SELECT * FROM cities WHERE id = ?");
                $stmt->execute([$city_id]);
                $city = $stmt->fetch();
            } else {
                $error_message = 'Failed to update city. Please try again.';
            }
        } catch (Exception $e) {
            if ($e->getCode() == 23000) {
                $error_message = 'A city with this name already exists.';
            } else {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit City</h5>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
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
                    <div class="mb-3">
                        <label for="name" class="form-label">City Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo htmlspecialchars($city['name']); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $city['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $city['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update City
                            </button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="delete.php" class="mt-3"
                      onsubmit="return confirm('Are you sure you want to delete this city?');">
                    <input type="hidden" name="id" value="<?php echo $city_id; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete City
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
