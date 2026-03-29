<?php
/**
 * dues-detail.php — redirects to the unified Vendor Management page (view.php).
 *
 * All vendor dues and payment recording functionality is now handled in
 * admin/vendors/view.php#assignments. This file exists for backwards
 * compatibility with any bookmarks or external links.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if ($vendor_id <= 0) {
    header('Location: dues.php');
    exit;
}

header('Location: view.php?id=' . $vendor_id . '#assignments');
exit;
