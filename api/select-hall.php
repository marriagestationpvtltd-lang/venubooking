<?php
/**
 * API: Select Hall
 * Validates the requested hall against the database before saving to session,
 * ensuring data integrity and preventing foreign-key constraint violations.
 * Also validates and stores the chosen time slot.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Hall ID is required']);
    exit;
}

$hall_id = intval($input['id']);

// Validate and extract time slot data
$slot_id = isset($input['slot_id']) && is_numeric($input['slot_id']) ? intval($input['slot_id']) : null;

try {
    $db = getDB();

    // Fetch the hall and its venue from the database to ensure the data is
    // authoritative and the hall_id foreign-key constraint will be satisfied.
    $stmt = $db->prepare(
        "SELECT h.id, h.name, h.base_price, h.capacity,
                v.name AS venue_name
         FROM halls h
         JOIN venues v ON v.id = h.venue_id
         WHERE h.id = ? AND h.status = 'active'"
    );
    $stmt->execute([$hall_id]);
    $hall = $stmt->fetch();

    if (!$hall) {
        echo json_encode(['success' => false, 'message' => 'Selected hall is not available. Please choose another hall.']);
        exit;
    }

    // Validate time slot when provided
    $slot_data = null;
    if ($slot_id !== null) {
        $slot_stmt = $db->prepare(
            "SELECT * FROM hall_time_slots WHERE id = ? AND hall_id = ? AND status = 'active'"
        );
        $slot_stmt->execute([$slot_id, $hall_id]);
        $slot_data = $slot_stmt->fetch();

        if (!$slot_data) {
            echo json_encode(['success' => false, 'message' => 'Selected time slot is not valid. Please choose again.']);
            exit;
        }

        // Re-check availability for the slot (race-condition guard)
        $event_date = $_SESSION['booking_data']['event_date'] ?? '';
        if (!empty($event_date)) {
            if (!checkTimeSlotAvailability($hall_id, $event_date, $slot_data['start_time'], $slot_data['end_time'])) {
                echo json_encode(['success' => false, 'message' => 'This time slot was just booked by someone else. Please select a different slot.']);
                exit;
            }
        }
    }

    // Determine effective price (slot override or hall base price)
    $effective_price = ($slot_data && $slot_data['price_override'] !== null)
        ? (float)$slot_data['price_override']
        : (float)$hall['base_price'];

    $_SESSION['selected_hall'] = [
        'id'         => (int)$hall['id'],
        'name'       => $hall['name'],
        'venue_name' => $hall['venue_name'],
        'base_price' => $effective_price,
        'capacity'   => (int)$hall['capacity'],
    ];

    // Store time slot in booking_data session
    if ($slot_data) {
        $shift = deriveShiftFromTimes($slot_data['start_time'], $slot_data['end_time']);
        $_SESSION['booking_data']['shift']      = $shift;
        $_SESSION['booking_data']['start_time'] = substr($slot_data['start_time'], 0, 5);
        $_SESSION['booking_data']['end_time']   = substr($slot_data['end_time'],   0, 5);
        $_SESSION['booking_data']['slot_id']    = (int)$slot_data['id'];
        $_SESSION['booking_data']['slot_name']  = $slot_data['slot_name'];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Hall selected successfully'
    ]);

} catch (Exception $e) {
    error_log('select-hall error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error selecting hall. Please try again.'
    ]);
}
