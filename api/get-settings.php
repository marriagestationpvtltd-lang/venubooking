<?php
/**
 * API: Get Settings
 * Returns frontend-relevant settings for dynamic configuration
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

try {
    // Get settings that frontend needs
    $settings = [
        'currency' => getSetting('currency', 'NPR'),
        'tax_rate' => floatval(getSetting('tax_rate', '13')),
        'site_name' => getSetting('site_name', 'Venue Booking System'),
    ];
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching settings: ' . $e->getMessage()
    ]);
}
