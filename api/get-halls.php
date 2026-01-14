<?php
/**
 * Get Halls API
 * Returns list of halls for a venue, optionally filtered by capacity and availability
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

$venue_id = $_GET['venue_id'] ?? null;
$min_capacity = $_GET['min_capacity'] ?? 0;
$date = $_GET['date'] ?? null;
$shift = $_GET['shift'] ?? null;

if (!$venue_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Venue ID is required'
    ]);
    exit;
}

// Get halls for the venue
$sql = "SELECT h.* FROM halls h 
        WHERE h.venue_id = :venue_id 
        AND h.status = 'active'
        AND h.capacity >= :min_capacity
        ORDER BY h.capacity DESC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':venue_id', $venue_id, PDO::PARAM_INT);
$stmt->bindParam(':min_capacity', $min_capacity, PDO::PARAM_INT);
$stmt->execute();
$halls = $stmt->fetchAll();

// Check availability for each hall if date and shift are provided
if ($date && $shift) {
    foreach ($halls as &$hall) {
        $hall['available'] = checkHallAvailability($hall['id'], $date, $shift);
    }
}

// Get primary image for each hall
foreach ($halls as &$hall) {
    $imgStmt = $db->prepare("SELECT image_path FROM hall_images WHERE hall_id = :hall_id AND is_primary = 1 LIMIT 1");
    $imgStmt->bindParam(':hall_id', $hall['id'], PDO::PARAM_INT);
    $imgStmt->execute();
    $image = $imgStmt->fetch();
    $hall['primary_image'] = $image ? $image['image_path'] : null;
}

echo json_encode([
    'success' => true,
    'halls' => $halls
]);
