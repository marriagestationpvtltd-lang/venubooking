<?php
$page_title = 'Manage Menu Items';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$success_message = '';
$error_message = '';

// Get menu ID from URL
$menu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($menu_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch menu details
$stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$menu_id]);
$menu = $stmt->fetch();

if (!$menu) {
    header('Location: index.php');
    exit;
}

// Handle edit item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = intval($_POST['item_id']);
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $display_order = intval($_POST['display_order']);

    if (empty($item_name)) {
        $_SESSION['error_message'] = 'Item name is required.';
    } else {
        try {
            $sql = "UPDATE menu_items SET item_name = ?, category = ?, display_order = ? WHERE id = ? AND menu_id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$item_name, $category, $display_order, $item_id, $menu_id])) {
                logActivity($current_user['id'], 'Updated menu item', 'menu_items', $item_id, "Updated item in menu: {$menu['name']}");
                $_SESSION['success_message'] = 'Menu item updated successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to update menu item.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error updating item: ' . $e->getMessage();
        }
    }
    // Redirect to prevent refresh resubmission
    header("Location: items.php?id=$menu_id");
    exit;
}

// Handle add item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $display_order = intval($_POST['display_order']);

    if (empty($item_name)) {
        $_SESSION['error_message'] = 'Item name is required.';
    } else {
        try {
            $sql = "INSERT INTO menu_items (menu_id, item_name, category, display_order) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$menu_id, $item_name, $category, $display_order])) {
                logActivity($current_user['id'], 'Added menu item', 'menu_items', $db->lastInsertId(), "Added item to menu: {$menu['name']}");
                $_SESSION['success_message'] = 'Menu item added successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to add menu item.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error adding item: ' . $e->getMessage();
        }
    }
    // Redirect to prevent refresh resubmission
    header("Location: items.php?id=$menu_id");
    exit;
}

// Handle delete item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = intval($_POST['delete_item']);
    try {
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND menu_id = ?");
        if ($stmt->execute([$item_id, $menu_id])) {
            logActivity($current_user['id'], 'Deleted menu item', 'menu_items', $item_id, "Deleted item from menu: {$menu['name']}");
            $_SESSION['success_message'] = 'Menu item deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete menu item.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting item: ' . $e->getMessage();
    }
    // Redirect to prevent refresh resubmission
    header("Location: items.php?id=$menu_id");
    exit;
}

// Retrieve and clear session messages (only runs on GET requests after redirect)
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch menu items
$items_stmt = $db->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY display_order, category, item_name");
$items_stmt->execute([$menu_id]);
$menu_items = $items_stmt->fetchAll();

// Group items by category
$items_by_category = [];
foreach ($menu_items as $item) {
    $category = $item['category'] ?: 'Uncategorized';
    if (!isset($items_by_category[$category])) {
        $items_by_category[$category] = [];
    }
    $items_by_category[$category][] = $item;
}
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-list"></i> Menu Items: <?php echo htmlspecialchars($menu['name']); ?></h4>
            <div>
                <a href="view.php?id=<?php echo $menu_id; ?>" class="btn btn-info btn-sm">
                    <i class="fas fa-eye"></i> View Menu
                </a>
                <a href="edit.php?id=<?php echo $menu_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Menu
                </a>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

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

<div class="row">
    <!-- Add New Item Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Item</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="item_name" class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="item_name" name="item_name" 
                               value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>" 
                               placeholder="e.g., Chicken Biryani" required>
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" 
                               value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>" 
                               placeholder="e.g., Appetizers, Main Course">
                        <small class="text-muted">Leave empty for uncategorized</small>
                    </div>

                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" 
                               value="<?php echo isset($_POST['display_order']) ? htmlspecialchars($_POST['display_order']) : '0'; ?>" 
                               min="0" placeholder="0">
                        <small class="text-muted">Lower numbers appear first</small>
                    </div>

                    <button type="submit" name="add_item" class="btn btn-success w-100">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Menu Info</h5>
            </div>
            <div class="card-body">
                <p><strong>Price per Person:</strong><br>
                <?php echo formatCurrency($menu['price_per_person']); ?></p>
                <p><strong>Total Items:</strong><br>
                <?php echo count($menu_items); ?> items</p>
                <p><strong>Status:</strong><br>
                <span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($menu['status']); ?>
                </span></p>
            </div>
        </div>
    </div>

    <!-- Menu Items List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensils"></i> Current Menu Items (<?php echo count($menu_items); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($menu_items) > 0): ?>
                    <?php foreach ($items_by_category as $category => $items): ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-3 border-bottom pb-2">
                                <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($category); ?>
                            </h6>
                            <div class="list-group">
                                <?php foreach ($items as $item): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-utensils text-muted me-2"></i>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">Order: <?php echo htmlspecialchars($item['display_order']); ?></small>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-warning me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $item['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                                <input type="hidden" name="delete_item" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel<?php echo $item['id']; ?>">
                                                        <i class="fas fa-edit"></i> Edit Menu Item
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="edit_item_name_<?php echo $item['id']; ?>" class="form-label">
                                                                Item Name <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   id="edit_item_name_<?php echo $item['id']; ?>" 
                                                                   name="item_name" 
                                                                   value="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                                   required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="edit_category_<?php echo $item['id']; ?>" class="form-label">
                                                                Category
                                                            </label>
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   id="edit_category_<?php echo $item['id']; ?>" 
                                                                   name="category" 
                                                                   value="<?php echo htmlspecialchars($item['category']); ?>" 
                                                                   placeholder="e.g., Appetizers, Main Course">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="edit_display_order_<?php echo $item['id']; ?>" class="form-label">
                                                                Display Order
                                                            </label>
                                                            <input type="number" 
                                                                   class="form-control" 
                                                                   id="edit_display_order_<?php echo $item['id']; ?>" 
                                                                   name="display_order" 
                                                                   value="<?php echo htmlspecialchars($item['display_order']); ?>" 
                                                                   min="0">
                                                            <small class="text-muted">Lower numbers appear first</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="edit_item" class="btn btn-primary">
                                                            <i class="fas fa-save"></i> Save Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No items added to this menu yet. Use the form on the left to add items.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
