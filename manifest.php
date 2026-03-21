<?php
/**
 * Web App Manifest
 * Serves a dynamic manifest.json based on site settings.
 * Replaces the deprecated apple-mobile-web-app-capable meta tags.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=86400');

$site_name    = getSetting('site_name', 'Venue Booking');
$site_favicon = getSetting('site_favicon', '');

$manifest = [
    'name'             => $site_name,
    'short_name'       => $site_name,
    'description'      => getSetting('meta_description', ''),
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'start_url'        => BASE_URL . '/',
    'scope'            => BASE_URL . '/',
    'background_color' => '#ffffff',
    'theme_color'      => '#4CAF50',
    'icons'            => [],
];

if (!empty($site_favicon)) {
    $icon_url = UPLOAD_URL . $site_favicon;
    $ext = strtolower(pathinfo($site_favicon, PATHINFO_EXTENSION));

    // Determine MIME type from extension
    $mime_map = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
    ];

    // Only include the icon in the manifest when the browser can use it as a
    // PWA icon.  ICO files are not a valid manifest icon format and would
    // cause browsers to ignore the entire icons entry.
    if (isset($mime_map[$ext])) {
        $icon_entry = [
            'src'  => $icon_url,
            'type' => $mime_map[$ext],
        ];
        // 'sizes: any' is the standard way to indicate a scalable icon (SVG) or
        // an icon that covers all sizes.  It is widely accepted for PNG/JPEG as
        // a fallback when the exact pixel dimensions are not known.
        $icon_entry['sizes'] = 'any';
        $manifest['icons'][] = $icon_entry;
    }
}

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
