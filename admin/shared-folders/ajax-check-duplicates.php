<?php
/**
 * Check for duplicate file names in a shared folder.
 * Returns the list of file names whose titles already exist in the folder
 * so the client can ask the user to replace or skip them.
 *
 * POST params:
 *   folder_id   int
 *   file_names  array of original file names (basename with extension)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$folder_id  = intval($_POST['folder_id'] ?? 0);
$file_names = $_POST['file_names'] ?? [];

if (!$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Folder ID is required.']);
    exit;
}

if (!is_array($file_names) || empty($file_names)) {
    echo json_encode(['success' => true, 'duplicates' => []]);
    exit;
}

$db = getDB();

// Verify folder exists
$stmt = $db->prepare("SELECT id FROM shared_folders WHERE id = ?");
$stmt->execute([$folder_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Folder not found.']);
    exit;
}

// Fetch the titles (original base names) of every active file in the folder
$stmt = $db->prepare(
    "SELECT title FROM shared_photos WHERE folder_id = ? AND status = 'active'"
);
$stmt->execute([$folder_id]);
$existing_titles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build a case-insensitive lookup set
$existing_lower = array_map('strtolower', $existing_titles);

// Determine which of the requested names are duplicates
$duplicates = [];
foreach ($file_names as $name) {
    $basename = pathinfo(trim($name), PATHINFO_FILENAME);
    if (in_array(strtolower($basename), $existing_lower, true)) {
        $duplicates[] = $name;
    }
}

echo json_encode([
    'success'    => true,
    'duplicates' => $duplicates,
]);
