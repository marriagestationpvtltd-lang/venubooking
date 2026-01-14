<?php
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Sanitize output to prevent XSS attacks
 */
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Check hall availability
 */
function checkHallAvailability($hall_id, $date, $shift, $exclude_booking_id = null) {
    $db = getDB();
    
    $sql = "SELECT COUNT(*) as count FROM bookings 
            WHERE hall_id = :hall_id 
            AND booking_date = :date 
            AND shift = :shift 
            AND booking_status != 'cancelled'";
    
    if ($exclude_booking_id) {
        $sql .= " AND id != :exclude_booking_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':hall_id', $hall_id, PDO::PARAM_INT);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':shift', $shift);
    
    if ($exclude_booking_id) {
        $stmt->bindParam(':exclude_booking_id', $exclude_booking_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['count'] == 0;
}

/**
 * Calculate booking total
 */
function calculateBookingTotal($hall_id, $menus, $guests, $services = []) {
    $db = getDB();
    
    // Get hall base price
    $stmt = $db->prepare("SELECT base_price FROM halls WHERE id = :hall_id");
    $stmt->bindParam(':hall_id', $hall_id, PDO::PARAM_INT);
    $stmt->execute();
    $hall = $stmt->fetch();
    $hallPrice = $hall ? $hall['base_price'] : 0;
    
    // Calculate menu total
    $menuTotal = 0;
    if (!empty($menus)) {
        foreach ($menus as $menu_id) {
            $stmt = $db->prepare("SELECT price_per_person FROM menus WHERE id = :menu_id");
            $stmt->bindParam(':menu_id', $menu_id, PDO::PARAM_INT);
            $stmt->execute();
            $menu = $stmt->fetch();
            if ($menu) {
                $menuTotal += $menu['price_per_person'] * $guests;
            }
        }
    }
    
    // Calculate services total
    $servicesTotal = 0;
    if (!empty($services)) {
        foreach ($services as $service_id) {
            $stmt = $db->prepare("SELECT price FROM additional_services WHERE id = :service_id");
            $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
            $stmt->execute();
            $service = $stmt->fetch();
            if ($service) {
                $servicesTotal += $service['price'];
            }
        }
    }
    
    // Calculate subtotal, tax, and total
    $subtotal = $hallPrice + $menuTotal + $servicesTotal;
    $taxAmount = $subtotal * (TAX_RATE / 100);
    $total = $subtotal + $taxAmount;
    
    return [
        'hall_price' => $hallPrice,
        'menu_total' => $menuTotal,
        'services_total' => $servicesTotal,
        'subtotal' => $subtotal,
        'tax_amount' => $taxAmount,
        'tax_rate' => TAX_RATE,
        'total' => $total
    ];
}

/**
 * Generate unique booking number
 */
function generateBookingNumber() {
    $db = getDB();
    $date = date('Ymd');
    
    // Get last booking number for today
    $stmt = $db->prepare("SELECT booking_number FROM bookings 
                          WHERE booking_number LIKE :pattern 
                          ORDER BY id DESC LIMIT 1");
    $pattern = "BK-{$date}-%";
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    $lastBooking = $stmt->fetch();
    
    if ($lastBooking) {
        // Extract sequence number and increment
        $lastNumber = substr($lastBooking['booking_number'], -4);
        $newNumber = str_pad((int)$lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        // First booking of the day
        $newNumber = '0001';
    }
    
    return "BK-{$date}-{$newNumber}";
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Upload image file
 */
function uploadImage($file, $uploadPath, $allowedTypes = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }
    
    // Check file type
    $allowedTypes = $allowedTypes ?? explode(',', ALLOWED_IMAGE_TYPES);
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Verify it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'File is not a valid image'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $fileExt;
    $targetPath = $uploadPath . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Delete file
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Get flash message
 */
function getFlashMessage($key) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 */
function setFlashMessage($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'staff'
        ];
    }
    return null;
}

/**
 * Log activity
 */
function logActivity($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $db = getDB();
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':table_name', $table_name);
    $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
    $stmt->bindParam(':old_values', $old_values);
    $stmt->bindParam(':new_values', $new_values);
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->bindParam(':user_agent', $user_agent);
    
    return $stmt->execute();
}

/**
 * Get setting value
 */
function getSetting($key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
    $stmt->bindParam(':key', $key);
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting($key, $value) {
    $db = getDB();
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = :value";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':key', $key);
    $stmt->bindParam(':value', $value);
    
    return $stmt->execute();
}

/**
 * Pagination helper
 */
function paginate($total, $perPage, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}
