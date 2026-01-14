<?php
/**
 * Get Menus API
 * Returns list of menus available for a hall
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

$hall_id = $_GET['hall_id'] ?? null;

if (!$hall_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Hall ID is required'
    ]);
    exit;
}

// Get menus for the hall
$sql = "SELECT m.* FROM menus m
        JOIN hall_menus hm ON m.id = hm.menu_id
        WHERE hm.hall_id = :hall_id 
        AND m.status = 'active'
        ORDER BY m.price_per_person DESC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':hall_id', $hall_id, PDO::PARAM_INT);
$stmt->execute();
$menus = $stmt->fetchAll();

// Get menu items for each menu
foreach ($menus as &$menu) {
    $itemStmt = $db->prepare("SELECT * FROM menu_items WHERE menu_id = :menu_id ORDER BY display_order ASC");
    $itemStmt->bindParam(':menu_id', $menu['id'], PDO::PARAM_INT);
    $itemStmt->execute();
    $menu['items'] = $itemStmt->fetchAll();
    $menu['items_count'] = count($menu['items']);
}

echo json_encode([
    'success' => true,
    'menus' => $menus
]);
