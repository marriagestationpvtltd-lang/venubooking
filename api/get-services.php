<?php
/**
 * Get Services API
 * Returns list of additional services
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// Get all active services
$sql = "SELECT * FROM additional_services WHERE status = 'active' ORDER BY service_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$services = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'services' => $services
]);
