<?php
/**
 * API: Get Active Venues (optionally filtered by city)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$city_id = (isset($_GET['city_id']) && $_GET['city_id'] !== '' && is_numeric($_GET['city_id']) && (int)$_GET['city_id'] > 0)
    ? (int)$_GET['city_id']
    : null;

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

        // Validate 360° panoramic image
        $pano_image_url = null;
        if (!empty($venue['pano_image'])) {
            $pano_filename = basename($venue['pano_image']);
            if (preg_match(SAFE_FILENAME_PATTERN, $pano_filename) && file_exists(UPLOAD_PATH . $pano_filename)) {
                $pano_image_url = UPLOAD_URL . rawurlencode($pano_filename);
            }
        }

        $result[] = [
            'id'            => (int) $venue['id'],
            'name'          => $venue['name'],
            'city_name'     => $venue['city_name'] ?? ($venue['location'] ?? ''),
            'description'   => $truncated,
            'images'        => $images_to_display,
            'pano_image_url' => $pano_image_url,
        ];
    }

    echo json_encode(['success' => true, 'venues' => $result]);
} catch (Exception $e) {
    error_log('get-venues.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading venues.']);
}
