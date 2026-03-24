<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();
$db = getDB();

$group_id = intval($_GET['id'] ?? 0);
if ($group_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch group title for the log message
$stmt = $db->prepare("SELECT title FROM gallery_card_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    $_SESSION['error_message'] = 'Gallery card group not found.';
    header('Location: index.php');
    exit;
}

// Unlink photos from this group (set card_group_id = NULL so they become ungrouped)
$db->prepare("UPDATE site_images SET card_group_id = NULL WHERE card_group_id = ?")
   ->execute([$group_id]);

// Delete the group
$db->prepare("DELETE FROM gallery_card_groups WHERE id = ?")
   ->execute([$group_id]);

logActivity($current_user['id'], 'Deleted gallery card group', 'gallery_card_groups', $group_id, "Deleted: " . $group['title']);

$_SESSION['success_message'] = "Gallery card group \"" . $group['title'] . "\" deleted. Photos have been ungrouped.";
header('Location: index.php');
exit;
