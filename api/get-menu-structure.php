<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;

if ($menu_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid menu_id']);
    exit;
}

// Verify menu exists and is active
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, price_per_person FROM menus WHERE id = ? AND status = 'active'");
    $stmt->execute([$menu_id]);
    $menu = $stmt->fetch();

    if (!$menu) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Menu not found']);
        exit;
    }

    $structure = getMenuStructure($menu_id);

    echo json_encode([
        'success'   => true,
        'menu_id'   => intval($menu['id']),
        'menu_name' => $menu['name'],
        'price_per_person' => floatval($menu['price_per_person']),
        'sections'  => $structure,
    ]);
} catch (\Throwable $e) {
    error_log("get-menu-structure API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
