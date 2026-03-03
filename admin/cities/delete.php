<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = getDB();
$city_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($city_id <= 0) {
    $_SESSION['error_message'] = 'Invalid city ID.';
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM cities WHERE id = ?");
$stmt->execute([$city_id]);
$city = $stmt->fetch();

if (!$city) {
    $_SESSION['error_message'] = 'City not found.';
    header('Location: index.php');
    exit;
}

try {
    // Check if any venues are linked to this city
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM venues WHERE city_id = ?");
    $check_stmt->execute([$city_id]);
    $result = $check_stmt->fetch();

    if ($result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete city. It has ' . $result['count'] . ' associated venue(s). Please reassign those venues first.';
        header('Location: index.php');
        exit;
    }

    $stmt = $db->prepare("DELETE FROM cities WHERE id = ?");
    if ($stmt->execute([$city_id])) {
        logActivity($current_user['id'], 'Deleted city', 'cities', $city_id, "Deleted city: {$city['name']}");
        $_SESSION['success_message'] = 'City deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete city. Please try again.';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit;
?>
