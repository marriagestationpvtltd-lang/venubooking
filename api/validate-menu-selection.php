<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$menu_id = isset($input['menu_id']) ? intval($input['menu_id']) : 0;
$selections = isset($input['selections']) && is_array($input['selections']) ? $input['selections'] : [];

if ($menu_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid menu_id']);
    exit;
}

// Sanitize selections: only int keys and int values
$clean_selections = [];
foreach ($selections as $gid => $item_ids) {
    $gid_int = intval($gid);
    if ($gid_int <= 0) continue;
    if (!is_array($item_ids)) {
        $item_ids = [$item_ids];
    }
    $clean_selections[$gid_int] = array_values(array_filter(array_map('intval', $item_ids)));
}

$result = validateMenuSelections($menu_id, $clean_selections);

echo json_encode([
    'success'     => true,
    'valid'       => $result['valid'],
    'errors'      => $result['errors'],
    'extra_total' => $result['extra_total'],
]);
