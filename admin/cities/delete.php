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

    // Check if any vendors have this city as a service city
    try {
        $vendor_check = $db->prepare("SELECT COUNT(*) as count FROM vendor_service_cities WHERE city_id = ?");
        $vendor_check->execute([$city_id]);
        $vendor_result = $vendor_check->fetch();
        if ($vendor_result['count'] > 0) {
            $_SESSION['error_message'] = 'Cannot delete city. It is assigned as a service city for ' . $vendor_result['count'] . ' vendor(s). Please remove it from those vendors first.';
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        // vendor_service_cities table may not exist on older installs; skip this check
    }

    // Check if any vendors have this as their primary city
    $vendor_primary_check = $db->prepare("SELECT COUNT(*) as count FROM vendors WHERE city_id = ?");
    $vendor_primary_check->execute([$city_id]);
    $vendor_primary_result = $vendor_primary_check->fetch();
    if ($vendor_primary_result['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete city. It is the primary city for ' . $vendor_primary_result['count'] . ' vendor(s). Please reassign those vendors first.';
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
