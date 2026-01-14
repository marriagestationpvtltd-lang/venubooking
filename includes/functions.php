<?php
/**
 * Core Functions for Venue Booking System
 */

require_once __DIR__ . '/db.php';

/**
 * Sanitize input to prevent XSS
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if hall is available for booking
 */
function checkHallAvailability($hall_id, $date, $shift) {
    $db = getDB();
    
    $sql = "SELECT COUNT(*) as count FROM bookings 
            WHERE hall_id = ? 
            AND event_date = ? 
            AND shift = ? 
            AND booking_status != 'cancelled'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$hall_id, $date, $shift]);
    $result = $stmt->fetch();
    
    return $result['count'] == 0;
}

/**
 * Generate unique booking number
 */
function generateBookingNumber() {
    $db = getDB();
    $date = date('Ymd');
    $prefix = 'BK-' . $date . '-';
    
    $sql = "SELECT booking_number FROM bookings 
            WHERE booking_number LIKE ? 
            ORDER BY booking_number DESC LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$prefix . '%']);
    $result = $stmt->fetch();
    
    if ($result) {
        $lastNumber = intval(substr($result['booking_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate booking total
 */
function calculateBookingTotal($hall_id, $menus, $guests, $services = []) {
    $db = getDB();
    
    // Get hall price
    $stmt = $db->prepare("SELECT base_price FROM halls WHERE id = ?");
    $stmt->execute([$hall_id]);
    $hall = $stmt->fetch();
    $hall_price = $hall ? $hall['base_price'] : 0;
    
    // Calculate menu total
    $menu_total = 0;
    if (!empty($menus)) {
        $placeholders = str_repeat('?,', count($menus) - 1) . '?';
        $stmt = $db->prepare("SELECT SUM(price_per_person) as total FROM menus WHERE id IN ($placeholders)");
        $stmt->execute($menus);
        $result = $stmt->fetch();
        $menu_price_per_person = $result['total'] ?? 0;
        $menu_total = $menu_price_per_person * $guests;
    }
    
    // Calculate services total
    $services_total = 0;
    if (!empty($services)) {
        $placeholders = str_repeat('?,', count($services) - 1) . '?';
        $stmt = $db->prepare("SELECT SUM(price) as total FROM additional_services WHERE id IN ($placeholders)");
        $stmt->execute($services);
        $result = $stmt->fetch();
        $services_total = $result['total'] ?? 0;
    }
    
    // Calculate totals
    $subtotal = $hall_price + $menu_total + $services_total;
    $tax_amount = $subtotal * (TAX_RATE / 100);
    $grand_total = $subtotal + $tax_amount;
    
    return [
        'hall_price' => $hall_price,
        'menu_total' => $menu_total,
        'services_total' => $services_total,
        'subtotal' => $subtotal,
        'tax_amount' => $tax_amount,
        'grand_total' => $grand_total
    ];
}

/**
 * Get available venues for a date
 */
function getAvailableVenues($date, $shift) {
    $db = getDB();
    
    // Get all active venues
    $sql = "SELECT v.* FROM venues v 
            WHERE v.status = 'active' 
            ORDER BY v.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $venues = $stmt->fetchAll();
    
    // Get venue images from site_images as fallback
    $venue_images = getImagesBySection('venue');
    $venue_image_index = 0;
    
    // Process each venue to ensure it has an image
    foreach ($venues as &$venue) {
        // If venue doesn't have an image or image file doesn't exist
        if (empty($venue['image']) || !file_exists(UPLOAD_PATH . $venue['image'])) {
            // Use fallback from site_images
            if (!empty($venue_images) && isset($venue_images[$venue_image_index])) {
                $venue['image'] = $venue_images[$venue_image_index]['image_path'];
                $venue_image_index = ($venue_image_index + 1) % count($venue_images);
            } else {
                // Use placeholder if no images available
                $venue['image'] = 'placeholder-venue.jpg';
            }
        }
    }
    
    return $venues;
}

/**
 * Get halls for a venue
 */
function getHallsForVenue($venue_id, $min_capacity = 0) {
    $db = getDB();
    
    $sql = "SELECT h.* FROM halls h 
            WHERE h.venue_id = ? 
            AND h.status = 'active'
            AND h.capacity >= ?
            ORDER BY h.capacity DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$venue_id, $min_capacity]);
    return $stmt->fetchAll();
}

/**
 * Get menus for a hall
 */
function getMenusForHall($hall_id) {
    $db = getDB();
    
    $sql = "SELECT m.* FROM menus m
            INNER JOIN hall_menus hm ON m.id = hm.menu_id
            WHERE hm.hall_id = ? 
            AND m.status = 'active'
            ORDER BY m.price_per_person DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$hall_id]);
    return $stmt->fetchAll();
}

/**
 * Get menu items
 */
function getMenuItems($menu_id) {
    $db = getDB();
    
    $sql = "SELECT * FROM menu_items 
            WHERE menu_id = ? 
            ORDER BY display_order, category";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$menu_id]);
    return $stmt->fetchAll();
}

/**
 * Get all active services
 */
function getActiveServices() {
    $db = getDB();
    
    $sql = "SELECT * FROM additional_services 
            WHERE status = 'active' 
            ORDER BY category, name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get or create customer
 */
function getOrCreateCustomer($full_name, $phone, $email = '', $address = '') {
    $db = getDB();
    
    // Check if customer exists
    $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
    $stmt->execute([$phone]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Update customer info
        $stmt = $db->prepare("UPDATE customers SET full_name = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $address, $customer['id']]);
        return $customer['id'];
    } else {
        // Create new customer
        $stmt = $db->prepare("INSERT INTO customers (full_name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $phone, $email, $address]);
        return $db->lastInsertId();
    }
}

/**
 * Create booking
 */
function createBooking($data) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Generate booking number
        $booking_number = generateBookingNumber();
        
        // Get or create customer
        $customer_id = getOrCreateCustomer(
            $data['full_name'],
            $data['phone'],
            $data['email'] ?? '',
            $data['address'] ?? ''
        );
        
        // Calculate totals
        $totals = calculateBookingTotal(
            $data['hall_id'],
            $data['menus'] ?? [],
            $data['guests'],
            $data['services'] ?? []
        );
        
        // Insert booking
        $sql = "INSERT INTO bookings (
                    booking_number, customer_id, hall_id, event_date, shift, 
                    event_type, number_of_guests, hall_price, menu_total, 
                    services_total, subtotal, tax_amount, grand_total, 
                    special_requests, booking_status, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $booking_number,
            $customer_id,
            $data['hall_id'],
            $data['event_date'],
            $data['shift'],
            $data['event_type'],
            $data['guests'],
            $totals['hall_price'],
            $totals['menu_total'],
            $totals['services_total'],
            $totals['subtotal'],
            $totals['tax_amount'],
            $totals['grand_total'],
            $data['special_requests'] ?? ''
        ]);
        
        $booking_id = $db->lastInsertId();
        
        // Insert booking menus
        if (!empty($data['menus'])) {
            foreach ($data['menus'] as $menu_id) {
                $stmt = $db->prepare("SELECT price_per_person FROM menus WHERE id = ?");
                $stmt->execute([$menu_id]);
                $menu = $stmt->fetch();
                
                if ($menu) {
                    $menu_price = $menu['price_per_person'];
                    $menu_total = $menu_price * $data['guests'];
                    
                    $stmt = $db->prepare("INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$booking_id, $menu_id, $menu_price, $data['guests'], $menu_total]);
                }
            }
        }
        
        // Insert booking services
        if (!empty($data['services'])) {
            foreach ($data['services'] as $service_id) {
                $stmt = $db->prepare("SELECT name, price FROM additional_services WHERE id = ?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();
                
                if ($service) {
                    $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$booking_id, $service_id, $service['name'], $service['price']]);
                }
            }
        }
        
        $db->commit();
        return ['success' => true, 'booking_id' => $booking_id, 'booking_number' => $booking_number];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get booking details
 */
function getBookingDetails($booking_id) {
    $db = getDB();
    
    $sql = "SELECT b.*, c.full_name, c.phone, c.email, c.address,
                   h.name as hall_name, h.capacity,
                   v.name as venue_name, v.location
            FROM bookings b
            INNER JOIN customers c ON b.customer_id = c.id
            INNER JOIN halls h ON b.hall_id = h.id
            INNER JOIN venues v ON h.venue_id = v.id
            WHERE b.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        // Get menus
        $stmt = $db->prepare("SELECT bm.*, m.name as menu_name FROM booking_menus bm INNER JOIN menus m ON bm.menu_id = m.id WHERE bm.booking_id = ?");
        $stmt->execute([$booking_id]);
        $booking['menus'] = $stmt->fetchAll();
        
        // Get services
        $stmt = $db->prepare("SELECT * FROM booking_services WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $booking['services'] = $stmt->fetchAll();
    }
    
    return $booking;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

/**
 * Get setting value
 */
function getSetting($key, $default = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $table_name = '', $record_id = null, $details = '') {
    $db = getDB();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $details, $ip_address]);
}

/**
 * Get images by section
 * Returns active images for a specific section, ordered by display_order
 */
function getImagesBySection($section, $limit = null) {
    $db = getDB();
    
    $sql = "SELECT id, title, description, image_path, section, display_order 
            FROM site_images 
            WHERE section = ? AND status = 'active' 
            ORDER BY display_order, created_at DESC";
    
    if ($limit !== null) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$section]);
    $images = $stmt->fetchAll();
    
    // Add full URL to each image
    foreach ($images as &$image) {
        $image['image_url'] = UPLOAD_URL . $image['image_path'];
    }
    
    return $images;
}

/**
 * Get first image from a section
 * Convenience function to get just the first image
 */
function getFirstImage($section) {
    $images = getImagesBySection($section, 1);
    return !empty($images) ? $images[0] : null;
}

/**
 * Handle file upload for images
 * 
 * @param array $file The $_FILES array element
 * @param string $prefix Prefix for the filename (e.g., 'hall', 'venue', 'menu')
 * @return array Array with 'success' boolean and 'message' or 'filename'
 */
function handleImageUpload($file, $prefix = 'image') {
    $result = ['success' => false, 'message' => ''];
    
    // Check if file was uploaded
    if ($file['error'] == UPLOAD_ERR_NO_FILE) {
        $result['message'] = 'No file uploaded.';
        return $result;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Error uploading file. Please try again.';
        return $result;
    }
    
    // Validate file type using MIME type (basic check)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        $result['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
        return $result;
    }
    
    // Validate actual image content using getimagesize (security check)
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $result['message'] = 'Invalid image file. The file does not appear to be a valid image.';
        return $result;
    }
    
    // Double-check MIME type from getimagesize and map to extension
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!isset($mime_to_ext[$image_info['mime']])) {
        $result['message'] = 'Invalid image type detected. Only JPG, PNG, GIF, and WebP images are allowed.';
        return $result;
    }
    
    // Use extension based on actual MIME type, not client-provided filename
    $extension = $mime_to_ext[$image_info['mime']];
    
    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $result['message'] = 'File is too large. Maximum size is 5MB.';
        return $result;
    }
    
    // Generate unique filename with validation
    $filename = basename($prefix . '_' . time() . '_' . uniqid() . '.' . $extension);
    
    // Additional safety check: ensure filename contains no directory separators
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        $result['message'] = 'Invalid filename generated.';
        return $result;
    }
    
    $upload_path = UPLOAD_PATH . $filename;
    
    // Create uploads directory if it doesn't exist with error handling
    if (!is_dir(UPLOAD_PATH)) {
        if (!mkdir(UPLOAD_PATH, 0755, true)) {
            $result['message'] = 'Failed to create upload directory. Please check server permissions.';
            return $result;
        }
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['message'] = 'Failed to upload file. Please check directory permissions.';
    }
    
    return $result;
}

/**
 * Delete an uploaded file
 * 
 * @param string $filename The filename to delete
 * @return boolean True if file was deleted or doesn't exist, false on error
 */
function deleteUploadedFile($filename) {
    if (empty($filename)) {
        return true;
    }
    
    // Validate filename to prevent directory traversal
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false; // Invalid filename
    }
    
    // Use basename as additional safety measure
    $filename = basename($filename);
    
    $filepath = UPLOAD_PATH . $filename;
    
    // Ensure the file path is within the upload directory before attempting deletion
    // Use realpath on the directory and manually construct the expected path
    $real_upload_path = realpath(UPLOAD_PATH);
    if ($real_upload_path === false) {
        return false; // Upload directory doesn't exist or is inaccessible
    }
    
    // Construct expected path
    $expected_path = $real_upload_path . DIRECTORY_SEPARATOR . $filename;
    
    // If file exists, verify its real path matches expected path
    if (file_exists($filepath)) {
        $real_file_path = realpath($filepath);
        if ($real_file_path === false || $real_file_path !== $expected_path) {
            return false; // File path doesn't match expected location
        }
        return unlink($filepath);
    }
    
    return true; // File doesn't exist, consider it deleted
}

/**
 * Display current image preview HTML
 * 
 * @param string $image_filename The image filename
 * @param string $alt_text Alternative text for the image
 * @return string HTML for image preview or empty string if no image
 */
function displayImagePreview($image_filename, $alt_text = 'Current image') {
    if (empty($image_filename)) {
        return '';
    }
    
    $image_path = UPLOAD_PATH . $image_filename;
    if (!file_exists($image_path)) {
        return '';
    }
    
    // URL encode the filename and escape for HTML
    $image_url = UPLOAD_URL . rawurlencode($image_filename);
    $escaped_url = htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8');
    $escaped_alt = htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8');
    
    return '<div class="mb-2">
        <img src="' . $escaped_url . '" alt="' . $escaped_alt . '" class="img-thumbnail" style="max-width: 200px;">
        <p class="text-muted small mt-1">Current image</p>
    </div>';
}
