<?php
$page_title = 'View Menu Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

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

// Fetch menu items
$items_stmt = $db->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY display_order, category");
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

// Fetch usage statistics
$stats_stmt = $db->prepare("SELECT 
                            COUNT(DISTINCT bm.booking_id) as times_ordered,
                            SUM(bm.total_price) as total_revenue,
                            SUM(bm.number_of_guests) as total_guests_served
                            FROM booking_menus bm
                            WHERE bm.menu_id = ?");
$stats_stmt->execute([$menu_id]);
$stats = $stats_stmt->fetch();

// Fetch halls associated with this menu
$halls_stmt = $db->prepare("SELECT h.*, v.name as venue_name 
                            FROM halls h
                            INNER JOIN hall_menus hm ON h.id = hm.hall_id
                            INNER JOIN venues v ON h.venue_id = v.id
                            WHERE hm.menu_id = ?
                            ORDER BY v.name, h.name");
$halls_stmt->execute([$menu_id]);
$linked_halls = $halls_stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-utensils"></i> <?php echo htmlspecialchars($menu['name']); ?></h4>
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="items.php?id=<?php echo $menu_id; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-list"></i> Manage Items
                </a>
                <a href="edit.php?id=<?php echo $menu_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit Menu
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Menu Details -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Menu Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Menu Name:</strong><br>
                        <?php echo htmlspecialchars($menu['name']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Price per Person:</strong><br>
                        <h4 class="text-success"><?php echo formatCurrency($menu['price_per_person']); ?></h4>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?php echo $menu['description'] ? nl2br(htmlspecialchars($menu['description'])) : '<em class="text-muted">No description available</em>'; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $menu['status'] == 'active' ? 'success' : 'secondary'; ?> fs-6">
                            <?php echo ucfirst($menu['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Total Items:</strong><br>
                        <?php echo count($menu_items); ?> items
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Created:</strong><br>
                        <?php echo date('M d, Y', strtotime($menu['created_at'])); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Last Updated:</strong><br>
                        <?php echo date('M d, Y', strtotime($menu['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Items -->
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Menu Items (<?php echo count($menu_items); ?>)</h5>
                <a href="items.php?id=<?php echo $menu_id; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Manage Items
                </a>
            </div>
            <div class="card-body">
                <?php if (count($menu_items) > 0): ?>
                    <?php foreach ($items_by_category as $category => $items): ?>
                        <h6 class="text-muted mb-3"><?php echo htmlspecialchars($category); ?></h6>
                        <ul class="list-group mb-4">
                            <?php foreach ($items as $item): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-utensils text-muted me-2"></i>
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> No items added to this menu yet.
                        <a href="items.php?id=<?php echo $menu_id; ?>" class="alert-link">Add items now</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Linked Halls -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-door-open"></i> Associated Halls (<?php echo count($linked_halls); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($linked_halls) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Hall Name</th>
                                    <th>Venue</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linked_halls as $hall): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($hall['name']); ?></td>
                                        <td><?php echo htmlspecialchars($hall['venue_name']); ?></td>
                                        <td><?php echo $hall['capacity']; ?> pax</td>
                                        <td>
                                            <span class="badge bg-<?php echo $hall['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($hall['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> This menu is not associated with any halls yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Sidebar -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Times Ordered</h6>
                    <h3 class="mb-0"><?php echo $stats['times_ordered'] ?? 0; ?></h3>
                </div>
                <hr>
                <div class="mb-3">
                    <h6 class="text-muted mb-1">Total Guests Served</h6>
                    <h3 class="mb-0"><?php echo $stats['total_guests_served'] ?? 0; ?></h3>
                </div>
                <hr>
                <div>
                    <h6 class="text-muted mb-1">Total Revenue</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="edit.php?id=<?php echo $menu_id; ?>" class="btn btn-warning btn-block w-100 mb-2">
                    <i class="fas fa-edit"></i> Edit Menu
                </a>
                <a href="items.php?id=<?php echo $menu_id; ?>" class="btn btn-primary btn-block w-100 mb-2">
                    <i class="fas fa-list"></i> Manage Items
                </a>
                <a href="add.php" class="btn btn-success btn-block w-100 mb-2">
                    <i class="fas fa-plus"></i> Add New Menu
                </a>
                <a href="../bookings/index.php" class="btn btn-info btn-block w-100">
                    <i class="fas fa-calendar"></i> View Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
