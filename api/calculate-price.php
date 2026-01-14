<?php
/**
 * Calculate Price API
 * Returns price breakdown for booking
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$hall_id = $data['hall_id'] ?? null;
$menus = $data['menus'] ?? [];
$guests = $data['guests'] ?? 0;
$services = $data['services'] ?? [];

if (!$hall_id || !$guests) {
    echo json_encode([
        'success' => false,
        'message' => 'Hall ID and number of guests are required'
    ]);
    exit;
}

// Calculate total
$calculation = calculateBookingTotal($hall_id, $menus, $guests, $services);

// Format currency values
$formatted = [
    'success' => true,
    'hall_price' => $calculation['hall_price'],
    'hall_price_formatted' => formatCurrency($calculation['hall_price']),
    'menu_total' => $calculation['menu_total'],
    'menu_total_formatted' => formatCurrency($calculation['menu_total']),
    'services_total' => $calculation['services_total'],
    'services_total_formatted' => formatCurrency($calculation['services_total']),
    'subtotal' => $calculation['subtotal'],
    'subtotal_formatted' => formatCurrency($calculation['subtotal']),
    'tax_rate' => $calculation['tax_rate'],
    'tax_amount' => $calculation['tax_amount'],
    'tax_amount_formatted' => formatCurrency($calculation['tax_amount']),
    'total' => $calculation['total'],
    'total_formatted' => formatCurrency($calculation['total']),
    'advance_payment' => $calculation['total'] * (ADVANCE_PAYMENT_PERCENTAGE / 100),
    'advance_payment_formatted' => formatCurrency($calculation['total'] * (ADVANCE_PAYMENT_PERCENTAGE / 100)),
    'advance_percentage' => ADVANCE_PAYMENT_PERCENTAGE
];

echo json_encode($formatted);
