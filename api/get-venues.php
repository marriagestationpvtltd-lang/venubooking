<?php
/**
 * Get Venues API
 * Returns list of available venues
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$date = $_GET['date'] ?? null;

// Get all active venues
$sql = "SELECT v.* FROM venues v WHERE v.status = 'active' ORDER BY v.venue_name";
$stmt = $db->prepare($sql);
$stmt->execute();
$venues = $stmt->fetchAll();

// If date is provided, check which venues have available halls
if ($date) {
    foreach ($venues as &$venue) {
        // Get halls for this venue that are available on the date
        $hallSql = "SELECT h.* FROM halls h 
                    WHERE h.venue_id = :venue_id 
                    AND h.status = 'active'";
        $hallStmt = $db->prepare($hallSql);
        $hallStmt->bindParam(':venue_id', $venue['id'], PDO::PARAM_INT);
        $hallStmt->execute();
        $venue['halls_count'] = $hallStmt->rowCount();
    }
}

echo json_encode([
    'success' => true,
    'venues' => $venues
]);
