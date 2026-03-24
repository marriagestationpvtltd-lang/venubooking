<?php
/**
 * API: Select Hall
 * Validates the requested hall against the database before saving to session,
 * ensuring data integrity and preventing foreign-key constraint violations.
 * Also validates and stores the chosen time slot(s) – supports multiple slots.
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

// Accept slot_ids[] array (new multi-slot flow) OR legacy single slot_id
$slot_ids = [];
if (isset($input['slot_ids']) && is_array($input['slot_ids'])) {
    foreach ($input['slot_ids'] as $sid) {
        if (is_numeric($sid) && intval($sid) > 0) {
            $slot_ids[] = intval($sid);
        }
    }
} elseif (isset($input['slot_id']) && is_numeric($input['slot_id'])) {
    $slot_ids = [intval($input['slot_id'])];
}

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

    // Validate each requested time slot and build the slot data list
    $slot_data_list = [];
    $event_date = $_SESSION['booking_data']['event_date'] ?? '';

    foreach ($slot_ids as $sid) {
        $slot_stmt = $db->prepare(
            "SELECT * FROM hall_time_slots WHERE id = ? AND hall_id = ? AND status = 'active'"
        );
        $slot_stmt->execute([$sid, $hall_id]);
        $slot_data = $slot_stmt->fetch();

        if (!$slot_data) {
            echo json_encode(['success' => false, 'message' => 'One or more selected time slots are not valid. Please choose again.']);
            exit;
        }

        // Re-check availability for each slot (race-condition guard)
        if (!empty($event_date)) {
            if (!checkIndividualSlotAvailability($sid, $hall_id, $event_date)) {
                echo json_encode(['success' => false, 'message' => 'One of the selected time slots was just booked by someone else. Please select a different slot.']);
                exit;
            }
        }

        $slot_data_list[] = $slot_data;
    }

    // Sort slots by start time so aggregate calculation is correct
    usort($slot_data_list, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

    // Determine effective price:
    //   multi-slot  → sum of each slot's price_override (or hall base_price per slot when no override)
    //   no slots    → hall base_price
    if (!empty($slot_data_list)) {
        $total_price = 0;
        foreach ($slot_data_list as $sd) {
            $total_price += ($sd['price_override'] !== null)
                ? (float)$sd['price_override']
                : (float)$hall['base_price'];
        }
        $effective_price = $total_price;
    } else {
        $effective_price = (float)$hall['base_price'];
    }

    $_SESSION['selected_hall'] = [
        'id'         => (int)$hall['id'],
        'name'       => $hall['name'],
        'venue_name' => $hall['venue_name'],
        'base_price' => $effective_price,
        'capacity'   => (int)$hall['capacity'],
    ];

    // Store time slot data in booking_data session
    if (!empty($slot_data_list)) {
        // Aggregate: earliest start → latest end (use min/max to handle any slot ordering)
        $all_starts = array_column($slot_data_list, 'start_time');
        $all_ends   = array_column($slot_data_list, 'end_time');
        $agg_start  = min($all_starts);
        $agg_end    = max($all_ends);

        $shift = deriveShiftFromTimes($agg_start, $agg_end);
        $_SESSION['booking_data']['shift']      = $shift;
        $_SESSION['booking_data']['start_time'] = substr($agg_start, 0, 5);
        $_SESSION['booking_data']['end_time']   = substr($agg_end,   0, 5);

        // Build per-slot detail array (used by createBooking to populate junction table)
        $selected_slots = [];
        foreach ($slot_data_list as $sd) {
            $selected_slots[] = [
                'id'             => (int)$sd['id'],
                'slot_name'      => $sd['slot_name'],
                'start_time'     => substr($sd['start_time'], 0, 5),
                'end_time'       => substr($sd['end_time'],   0, 5),
                'price_override' => $sd['price_override'] !== null ? (float)$sd['price_override'] : null,
            ];
        }
        $_SESSION['booking_data']['selected_slots'] = $selected_slots;

        // Backward-compatible single-slot keys (used by display code in later steps)
        $_SESSION['booking_data']['slot_id']   = (int)$slot_data_list[0]['id'];
        $slot_names = array_map(fn($s) => $s['slot_name'], $slot_data_list);
        $_SESSION['booking_data']['slot_name'] = implode(', ', $slot_names);
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
