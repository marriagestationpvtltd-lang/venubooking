<?php
/**
 * API: Get Active Venues (optionally filtered by city)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$city_id = isset($_GET['city_id']) && is_numeric($_GET['city_id']) ? intval($_GET['city_id']) : null;

try {
    $venues = getAllActiveVenues($city_id);

    $result = [];
    foreach ($venues as $venue) {
        $images_to_display = [];

        if (!empty($venue['gallery_images']) && count($venue['gallery_images']) > 0) {
            $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
            foreach ($venue['gallery_images'] as $gallery_image) {
                $safe_url = $upload_url_base . rawurlencode($gallery_image['image_path']);
                $images_to_display[] = $safe_url;
            }
        } elseif (!empty($venue['image'])) {
            $upload_url_base = rtrim(UPLOAD_URL, '/') . '/';
            $images_to_display[] = $upload_url_base . rawurlencode($venue['image']);
        } else {
            $images_to_display[] = getPlaceholderImageUrl();
        }

        $description = $venue['description'] ?? '';
        $truncated   = mb_strlen($description) > 100
            ? mb_substr($description, 0, 100) . '...'
            : $description;

        $result[] = [
            'id'          => (int) $venue['id'],
            'name'        => $venue['name'],
            'city_name'   => $venue['city_name'] ?? ($venue['location'] ?? ''),
            'description' => $truncated,
            'images'      => $images_to_display,
        ];
    }

    echo json_encode(['success' => true, 'venues' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading venues.']);
}
