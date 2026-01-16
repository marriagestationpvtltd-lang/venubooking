<?php
/**
 * Core Functions for Venue Booking System
 */

require_once __DIR__ . '/db.php';

/**
 * Filename validation pattern for image uploads
 * Requires: alphanumeric name, single separators (._-), and file extension
 * Blocks: consecutive separators, leading/trailing separators, special chars
 */
define('SAFE_FILENAME_PATTERN', '/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z0-9]+$/');

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
    
    // Calculate totals - get tax rate from database settings
    $tax_rate = floatval(getSetting('tax_rate', '13'));
    $subtotal = $hall_price + $menu_total + $services_total;
    $tax_amount = $subtotal * ($tax_rate / 100);
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
    
    // Check file existence once and cache results
    $file_exists_cache = [];
    $needs_fallback = false;
    
    foreach ($venues as $venue) {
        $safe_filename = !empty($venue['image']) ? basename($venue['image']) : '';
        
        // Validate filename structure using pattern defined at top of file
        // Pattern ensures: name.ext or name-part.ext or name_part.ext
        // Blocks: consecutive dots, leading/trailing separators, special chars
        if (!empty($safe_filename) && !preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $safe_filename = ''; // Invalid filename structure
        }
        
        $exists = !empty($safe_filename) && file_exists(UPLOAD_PATH . $safe_filename);
        $file_exists_cache[$venue['id']] = ['filename' => $safe_filename, 'exists' => $exists];
        
        if (!$exists) {
            $needs_fallback = true;
        }
    }
    
    // Only fetch gallery images if needed
    $venue_images = [];
    $venue_image_index = 0;
    if ($needs_fallback) {
        $venue_images = getImagesBySection('venue');
    }
    
    // Process each venue to ensure it has an image
    $venue_images_count = count($venue_images);
    
    foreach ($venues as &$venue) {
        $cache = $file_exists_cache[$venue['id']];
        
        // If venue doesn't have a valid image
        if (!$cache['exists']) {
            // Use fallback from site_images
            if ($venue_images_count > 0 && isset($venue_images[$venue_image_index])) {
                $venue['image'] = $venue_images[$venue_image_index]['image_path'];
                $venue_image_index = ($venue_image_index + 1) % $venue_images_count;
            } else {
                // Use empty string to trigger SVG placeholder in frontend
                $venue['image'] = '';
            }
        } else {
            // Ensure we use the sanitized filename
            $venue['image'] = $cache['filename'];
        }
    }
    
    return $venues;
}

/**
 * Get all active venues for homepage display
 */
function getAllActiveVenues() {
    $db = getDB();
    
    // Get all active venues
    $sql = "SELECT v.* FROM venues v 
            WHERE v.status = 'active' 
            ORDER BY v.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $venues = $stmt->fetchAll();
    
    // Process venue images and get hall images
    foreach ($venues as &$venue) {
        $safe_filename = !empty($venue['image']) ? basename($venue['image']) : '';
        
        // Validate filename structure
        if (!empty($safe_filename) && !preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $safe_filename = '';
        }
        
        $exists = !empty($safe_filename) && file_exists(UPLOAD_PATH . $safe_filename);
        
        if (!$exists) {
            $venue['image'] = '';
        } else {
            $venue['image'] = $safe_filename;
        }
        
        // Get all hall images for this venue
        $venue['gallery_images'] = getVenueGalleryImages($venue['id']);
    }
    
    return $venues;
}

/**
 * Get all hall images for a venue (from all halls belonging to the venue)
 */
function getVenueGalleryImages($venue_id) {
    $db = getDB();
    
    // Get all hall images for halls belonging to this venue, ordered by display_order
    $sql = "SELECT hi.image_path, hi.is_primary, hi.display_order, h.name as hall_name
            FROM hall_images hi
            INNER JOIN halls h ON hi.hall_id = h.id
            WHERE h.venue_id = ? AND h.status = 'active'
            ORDER BY hi.is_primary DESC, hi.display_order ASC, hi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$venue_id]);
    $images = $stmt->fetchAll();
    
    // Process and validate each image
    $validated_images = [];
    foreach ($images as $image) {
        $safe_filename = !empty($image['image_path']) ? basename($image['image_path']) : '';
        
        // Validate filename structure
        if (!empty($safe_filename) && preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $exists = file_exists(UPLOAD_PATH . $safe_filename);
            
            if ($exists) {
                $validated_images[] = [
                    'image_path' => $safe_filename,
                    'is_primary' => $image['is_primary'],
                    'hall_name' => $image['hall_name']
                ];
            }
        }
    }
    
    return $validated_images;
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
        
        // Send email notifications after successful booking
        sendBookingNotification($booking_id, 'new');
        
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
    try {
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
        if (!$stmt) {
            throw new Exception("Failed to prepare booking query");
        }
        
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            // Get menus
            $stmt = $db->prepare("SELECT bm.*, m.name as menu_name FROM booking_menus bm INNER JOIN menus m ON bm.menu_id = m.id WHERE bm.booking_id = ?");
            if ($stmt) {
                $stmt->execute([$booking_id]);
                $booking['menus'] = $stmt->fetchAll();
            } else {
                $booking['menus'] = [];
            }
            
            // Get menu items for each menu (prepare statement once for efficiency)
            if (!empty($booking['menus'])) {
                $itemsStmt = $db->prepare("SELECT item_name, category, display_order FROM menu_items WHERE menu_id = ? ORDER BY display_order, category");
                if ($itemsStmt) {
                    foreach ($booking['menus'] as &$menu) {
                        $itemsStmt->execute([$menu['menu_id']]);
                        $menu['items'] = $itemsStmt->fetchAll();
                    }
                }
            }
            
            // Get services
            $stmt = $db->prepare("SELECT bs.*, s.name as service_name, s.price FROM booking_services bs INNER JOIN additional_services s ON bs.service_id = s.id WHERE bs.booking_id = ?");
            if ($stmt) {
                $stmt->execute([$booking_id]);
                $booking['services'] = $stmt->fetchAll();
            } else {
                $booking['services'] = [];
            }
        }
        
        return $booking;
    } catch (PDOException $e) {
        error_log("Database error in getBookingDetails: " . $e->getMessage());
        throw new Exception("Unable to retrieve booking information");
    } catch (Exception $e) {
        error_log("Error in getBookingDetails: " . $e->getMessage());
        throw new Exception("Unable to retrieve booking information");
    }
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    $currency = getSetting('currency', 'NPR');
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Convert number to words (for invoices)
 * Supports numbers up to 99,999,999.99
 */
function numberToWords($number) {
    $number = number_format($number, 2, '.', '');
    list($integer, $fraction) = explode('.', $number);
    
    $output = '';
    
    if ($integer == 0) {
        $output = 'Zero';
    } else {
        $ones = array(
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'
        );
        
        $tens = array(
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
        );
        
        $scales = array('', 'Thousand', 'Lakh', 'Crore');
        
        $integer = str_pad($integer, 9, '0', STR_PAD_LEFT);
        $crore = substr($integer, 0, 2);
        $lakh = substr($integer, 2, 2);
        $thousand = substr($integer, 4, 2);
        $hundred = substr($integer, 6, 1);
        $ten = substr($integer, 7, 2);
        
        $result = array();
        
        // Crores
        if ($crore > 0) {
            if ($crore < 20) {
                $result[] = $ones[$crore] . ' Crore';
            } else {
                $result[] = $tens[intval($crore / 10)] . ' ' . $ones[$crore % 10] . ' Crore';
            }
        }
        
        // Lakhs
        if ($lakh > 0) {
            if ($lakh < 20) {
                $result[] = $ones[$lakh] . ' Lakh';
            } else {
                $result[] = $tens[intval($lakh / 10)] . ' ' . $ones[$lakh % 10] . ' Lakh';
            }
        }
        
        // Thousands
        if ($thousand > 0) {
            if ($thousand < 20) {
                $result[] = $ones[$thousand] . ' Thousand';
            } else {
                $result[] = $tens[intval($thousand / 10)] . ' ' . $ones[$thousand % 10] . ' Thousand';
            }
        }
        
        // Hundreds
        if ($hundred > 0) {
            $result[] = $ones[$hundred] . ' Hundred';
        }
        
        // Tens and ones
        if ($ten > 0) {
            if ($ten < 20) {
                $result[] = $ones[$ten];
            } else {
                $result[] = trim($tens[intval($ten / 10)] . ' ' . $ones[$ten % 10]);
            }
        }
        
        $output = trim(implode(' ', $result));
    }
    
    // Add paisa if fraction exists
    if (intval($fraction) > 0) {
        $output .= ' and ' . intval($fraction) . '/100';
    }
    
    return $output;
}

/**
 * Calculate advance payment amount
 * 
 * @param float $total_amount The total booking amount
 * @return array Array with 'percentage' and 'amount' keys
 */
function calculateAdvancePayment($total_amount) {
    // Validate input
    if (!is_numeric($total_amount) || $total_amount < 0) {
        return [
            'percentage' => 0,
            'amount' => 0
        ];
    }
    
    $advance_percentage = floatval(getSetting('advance_payment_percentage', '25'));
    $advance_amount = $total_amount * ($advance_percentage / 100);
    
    return [
        'percentage' => $advance_percentage,
        'amount' => $advance_amount
    ];
}

/**
 * Get setting value with caching
 */
function getSetting($key, $default = '') {
    static $cache = [];
    
    // Return from cache if available
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    
    try {
        // Query database
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare settings query");
        }
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        // Store in cache and return
        $cache[$key] = $result ? $result['setting_value'] : $default;
        return $cache[$key];
    } catch (Exception $e) {
        error_log("Error in getSetting for key '$key': " . $e->getMessage());
        // Return default value on error
        $cache[$key] = $default;
        return $default;
    }
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
 * Validate uploaded file path for display
 * Ensures the file exists, is within upload directory, and has no path traversal
 * 
 * @param string $filename The filename to validate
 * @return bool True if valid and safe to display
 */
function validateUploadedFilePath($filename) {
    if (empty($filename)) {
        return false;
    }
    
    // Check for null bytes which can be used to bypass security
    if (strpos($filename, "\0") !== false) {
        return false;
    }
    
    // Check for directory traversal characters
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false;
    }
    
    // Use basename as additional safety - ensures only filename, no path
    $safe_filename = basename($filename);
    
    // Verify filename hasn't changed after basename (would indicate path manipulation)
    if ($safe_filename !== $filename) {
        return false;
    }
    
    // Check if file exists
    $filepath = UPLOAD_PATH . $safe_filename;
    if (!file_exists($filepath)) {
        return false;
    }
    
    // Verify the real path is within upload directory
    $real_upload_path = realpath(UPLOAD_PATH);
    $real_file_path = realpath($filepath);
    
    if ($real_upload_path === false || $real_file_path === false) {
        return false;
    }
    
    // Ensure both paths end with directory separator for accurate comparison
    $real_upload_path = rtrim($real_upload_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
    // Check if file is within upload directory (with proper path comparison)
    if (strpos($real_file_path, $real_upload_path) !== 0) {
        return false;
    }
    
    return true;
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

/**
 * Get placeholder image data URL for missing images
 * Returns an inline SVG as a data URL
 * 
 * @return string Data URL for placeholder SVG
 */
function getPlaceholderImageUrl() {
    // Build SVG placeholder for better readability
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">' .
           '<rect fill="#e9ecef" width="400" height="300"/>' .
           '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" ' .
           'fill="#6c757d" font-size="24" font-family="Arial">No Image</text>' .
           '</svg>';
    
    // URL encode for use in data URL
    return 'data:image/svg+xml,' . rawurlencode($svg);
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $recipient_name Optional recipient name
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $recipient_name = '') {
    // Check if email is enabled
    if (getSetting('email_enabled', '1') != '1') {
        error_log("Email notification skipped - email notifications are disabled in settings");
        return false;
    }
    
    // Validate email address
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Email notification failed - invalid email address: " . ($to ?: '(empty)'));
        return false;
    }
    
    $from_name = getSetting('email_from_name', 'Venue Booking System');
    $from_email = getSetting('email_from_address', 'noreply@venubooking.com');
    
    // Use SMTP if enabled
    if (getSetting('smtp_enabled', '0') == '1') {
        return sendEmailSMTP($to, $subject, $message, $recipient_name);
    }
    
    // Use PHP mail() function
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $full_to = !empty($recipient_name) ? $recipient_name . ' <' . $to . '>' : $to;
    
    $result = @mail($full_to, $subject, $message, implode("\r\n", $headers));
    
    if (!$result) {
        error_log("Failed to send email to: $to, subject: $subject");
    }
    
    return $result;
}

/**
 * Send email using SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $recipient_name Optional recipient name
 * @return bool True on success, false on failure
 */
function sendEmailSMTP($to, $subject, $message, $recipient_name = '') {
    $smtp_host = getSetting('smtp_host', '');
    $smtp_port = intval(getSetting('smtp_port', '587'));
    $smtp_username = getSetting('smtp_username', '');
    $smtp_password = getSetting('smtp_password', '');
    $smtp_encryption = getSetting('smtp_encryption', 'tls');
    $from_name = getSetting('email_from_name', 'Venue Booking System');
    $from_email = getSetting('email_from_address', 'noreply@venubooking.com');
    
    if (empty($smtp_host) || empty($smtp_username)) {
        error_log("SMTP email failed - SMTP settings incomplete (host: " . ($smtp_host ?: '(empty)') . ", username: " . ($smtp_username ?: '(empty)') . ")");
        return false;
    }
    
    try {
        // Set timeout for socket operations
        ini_set('default_socket_timeout', 30);
        
        // Create socket connection
        $context = stream_context_create();
        
        if ($smtp_encryption === 'ssl') {
            $socket = @stream_socket_client(
                "ssl://$smtp_host:$smtp_port",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            $socket = @stream_socket_client(
                "tcp://$smtp_host:$smtp_port",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }
        
        if (!$socket) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }
        
        // Set socket timeout
        stream_set_timeout($socket, 30);
        
        // Read server response
        $response = fgets($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            error_log("SMTP server not ready: $response");
            return false;
        }
        
        // Get server name for EHLO, use localhost as fallback
        $ehlo_domain = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        // Sanitize domain name to prevent SMTP injection
        $ehlo_domain = preg_replace('/[^a-zA-Z0-9.-]/', '', $ehlo_domain);
        if (empty($ehlo_domain)) {
            $ehlo_domain = 'localhost';
        }
        
        // Send EHLO
        fwrite($socket, "EHLO $ehlo_domain\r\n");
        $response = fgets($socket);
        
        // Start TLS if needed
        if ($smtp_encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                error_log("STARTTLS failed: $response");
                return false;
            }
            
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            fwrite($socket, "EHLO $ehlo_domain\r\n");
            $response = fgets($socket);
        }
        
        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            error_log("SMTP AUTH LOGIN failed: $response");
            return false;
        }
        
        fwrite($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            error_log("SMTP username failed: $response");
            return false;
        }
        
        fwrite($socket, base64_encode($smtp_password) . "\r\n");
        $response = fgets($socket);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            error_log("SMTP authentication failed: $response");
            return false;
        }
        
        // Send email
        fwrite($socket, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            error_log("SMTP MAIL FROM failed: $response");
            return false;
        }
        
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            error_log("SMTP RCPT TO failed: $response");
            return false;
        }
        
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            error_log("SMTP DATA failed: $response");
            return false;
        }
        
        // Build email content
        $full_to = !empty($recipient_name) ? $recipient_name : $to;
        $email_content = "From: $from_name <$from_email>\r\n";
        $email_content .= "To: $full_to <$to>\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $message;
        $email_content .= "\r\n.\r\n";
        
        fwrite($socket, $email_content);
        $response = fgets($socket);
        
        // Quit
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP send failed: $response");
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send booking notification emails
 * 
 * @param int $booking_id Booking ID
 * @param string $type Type of notification (new, update, payment_request)
 * @param string $old_status Old status (for updates)
 * @return array Array with admin and user email results
 */
function sendBookingNotification($booking_id, $type = 'new', $old_status = '') {
    $booking = getBookingDetails($booking_id);
    
    if (!$booking) {
        error_log("Email notification failed - could not retrieve booking details for booking ID: $booking_id");
        return ['admin' => false, 'user' => false];
    }
    
    $admin_email = getSetting('admin_email', getSetting('contact_email', ''));
    $results = ['admin' => false, 'user' => false];
    
    // Determine subject and message based on type
    if ($type === 'new') {
        $admin_subject = 'New Booking Received - ' . $booking['booking_number'];
        $user_subject = 'Booking Confirmation - ' . $booking['booking_number'];
    } elseif ($type === 'payment_request') {
        $admin_subject = 'Payment Request Sent - ' . $booking['booking_number'];
        $user_subject = 'Payment Request for Booking - ' . $booking['booking_number'];
    } else {
        $status_text = ucfirst($booking['booking_status']);
        $admin_subject = 'Booking Updated - ' . $booking['booking_number'];
        $user_subject = 'Booking Status Updated - ' . $booking['booking_number'];
    }
    
    // Generate email HTML
    $admin_message = generateBookingEmailHTML($booking, 'admin', $type, $old_status);
    $user_message = generateBookingEmailHTML($booking, 'user', $type, $old_status);
    
    // Send to admin
    if (!empty($admin_email)) {
        $results['admin'] = sendEmail($admin_email, $admin_subject, $admin_message);
        if ($results['admin']) {
            error_log("Booking notification email sent to admin: $admin_email for booking " . $booking['booking_number']);
        }
    } else {
        error_log("Admin email notification skipped for booking " . $booking['booking_number'] . " - admin email not configured in settings");
    }
    
    // Send to user
    if (!empty($booking['email'])) {
        $results['user'] = sendEmail($booking['email'], $user_subject, $user_message, $booking['full_name']);
        if ($results['user']) {
            error_log("Booking notification email sent to customer: " . $booking['email'] . " for booking " . $booking['booking_number']);
        }
    } else {
        error_log("Customer email notification skipped for booking " . $booking['booking_number'] . " - customer email not provided");
    }
    
    return $results;
}

/**
 * Generate booking email HTML
 * 
 * @param array $booking Booking details
 * @param string $recipient Type of recipient (admin/user)
 * @param string $type Type of notification (new/update/payment_request)
 * @param string $old_status Old status (for updates)
 * @return string HTML email content
 */
function generateBookingEmailHTML($booking, $recipient = 'user', $type = 'new', $old_status = '') {
    $site_name = getSetting('site_name', 'Venue Booking System');
    $contact_email = getSetting('contact_email', '');
    $contact_phone = getSetting('contact_phone', '');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
            .detail-label { font-weight: bold; color: #555; }
            .detail-value { color: #333; }
            .section-title { color: #4CAF50; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
            .cost-row { display: flex; justify-content: space-between; padding: 5px 0; }
            .total-row { font-weight: bold; font-size: 18px; color: #4CAF50; border-top: 2px solid #4CAF50; padding-top: 10px; margin-top: 10px; }
            .footer { text-align: center; padding: 20px; color: #777; font-size: 14px; }
            .status-badge { display: inline-block; padding: 5px 15px; border-radius: 3px; font-weight: bold; }
            .status-pending { background-color: #fff3cd; color: #856404; }
            .status-confirmed { background-color: #d4edda; color: #155724; }
            .status-cancelled { background-color: #f8d7da; color: #721c24; }
            .status-completed { background-color: #d1ecf1; color: #0c5460; }
            .payment-notice { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
                <h2><?php echo $type === 'new' ? 'Booking Confirmation' : ($type === 'payment_request' ? 'Payment Request' : 'Booking Update'); ?></h2>
            </div>
            
            <div class="content">
                <?php if ($recipient === 'user'): ?>
                    <?php if ($type === 'new'): ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>Thank you for your booking! Your reservation has been successfully created.</p>
                    <?php elseif ($type === 'payment_request'): ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <?php 
                        // Calculate advance payment for display in notice
                        $advance = calculateAdvancePayment($booking['grand_total']);
                        ?>
                        <div class="payment-notice">
                            <strong>Payment Request</strong><br>
                            Your booking for <?php echo htmlspecialchars($booking['venue_name']); ?> on <?php echo date('F d, Y', strtotime($booking['event_date'])); ?> is almost confirmed.<br><br>
                            <strong>Total Amount:</strong> <?php echo formatCurrency($booking['grand_total']); ?><br>
                            <strong>Advance Payment (<?php echo htmlspecialchars($advance['percentage']); ?>%):</strong> <?php echo formatCurrency($advance['amount']); ?><br><br>
                            Please complete the advance payment at your earliest convenience to confirm your booking.
                        </div>
                    <?php else: ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>Your booking status has been updated.</p>
                        <?php if (!empty($old_status)): ?>
                            <p><strong>Previous Status:</strong> <?php echo ucfirst($old_status); ?> → <strong>New Status:</strong> <?php echo ucfirst($booking['booking_status']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($type === 'new'): ?>
                        <p><strong>A new booking has been received:</strong></p>
                    <?php elseif ($type === 'payment_request'): ?>
                        <p><strong>Payment request sent for booking:</strong></p>
                    <?php else: ?>
                        <p><strong>Booking has been updated:</strong></p>
                        <?php if (!empty($old_status)): ?>
                            <p><strong>Status Change:</strong> <?php echo ucfirst($old_status); ?> → <?php echo ucfirst($booking['booking_status']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="booking-details">
                    <div class="section-title">Booking Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Booking Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                            <?php echo ucfirst($booking['booking_status']); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value"><?php echo ucfirst($booking['payment_status']); ?></span>
                    </div>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Customer Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                    </div>
                    <?php if (!empty($booking['email'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Event Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Event Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shift:</span>
                        <span class="detail-value"><?php echo ucfirst($booking['shift']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Guests:</span>
                        <span class="detail-value"><?php echo $booking['number_of_guests']; ?> persons</span>
                    </div>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Venue & Hall</div>
                    <div class="detail-row">
                        <span class="detail-label">Venue:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['venue_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Hall:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['hall_name']); ?> (<?php echo $booking['capacity']; ?> capacity)</span>
                    </div>
                </div>
                
                <?php if (!empty($booking['menus'])): ?>
                <div class="booking-details">
                    <div class="section-title">Selected Menus</div>
                    <?php foreach ($booking['menus'] as $menu): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo htmlspecialchars($menu['menu_name']); ?>:</span>
                            <span class="detail-value"><?php echo formatCurrency($menu['price_per_person']); ?>/person × <?php echo $menu['number_of_guests']; ?> = <?php echo formatCurrency($menu['total_price']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['services'])): ?>
                <div class="booking-details">
                    <div class="section-title">Additional Services</div>
                    <?php foreach ($booking['services'] as $service): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo htmlspecialchars($service['service_name']); ?>:</span>
                            <span class="detail-value"><?php echo formatCurrency($service['price']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['special_requests'])): ?>
                <div class="booking-details">
                    <div class="section-title">Special Requests</div>
                    <p><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="booking-details">
                    <div class="section-title">Cost Breakdown</div>
                    <div class="cost-row">
                        <span>Hall Cost:</span>
                        <span><?php echo formatCurrency($booking['hall_price']); ?></span>
                    </div>
                    <?php if ($booking['menu_total'] > 0): ?>
                    <div class="cost-row">
                        <span>Menu Cost:</span>
                        <span><?php echo formatCurrency($booking['menu_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['services_total'] > 0): ?>
                    <div class="cost-row">
                        <span>Services Cost:</span>
                        <span><?php echo formatCurrency($booking['services_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="cost-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                    </div>
                    <div class="cost-row">
                        <span>Tax (<?php echo htmlspecialchars(getSetting('tax_rate', '13'), ENT_QUOTES, 'UTF-8'); ?>%):</span>
                        <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                    </div>
                    <div class="cost-row total-row">
                        <span>Grand Total:</span>
                        <span><?php echo formatCurrency($booking['grand_total']); ?></span>
                    </div>
                    <?php if ($type === 'payment_request'): ?>
                        <?php 
                        // Calculate advance payment based on configured percentage
                        $advance = calculateAdvancePayment($booking['grand_total']);
                        ?>
                        <div class="cost-row" style="margin-top: 10px; border-top: 1px solid #ddd; background-color: #fff3cd; padding: 10px; border-radius: 3px;">
                            <span><strong>Advance Payment Required (<?php echo htmlspecialchars($advance['percentage']); ?>%):</strong></span>
                            <span style="color: #856404; font-weight: bold; font-size: 18px;"><?php echo formatCurrency($advance['amount']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Get payment methods for this booking (only show if payment request or if methods are linked)
                if ($type === 'payment_request' || $type === 'new'):
                    $payment_methods = getBookingPaymentMethods($booking['id']);
                    if (!empty($payment_methods)): 
                ?>
                <div class="booking-details">
                    <div class="section-title">Payment Methods</div>
                    <p style="margin-bottom: 15px;">You can make payment using any of the following methods:</p>
                    <?php foreach ($payment_methods as $idx => $method): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #4CAF50; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #4CAF50;"><?php echo htmlspecialchars($method['name']); ?></h4>
                            
                            <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                                <div style="margin: 10px 0;">
                                    <img src="<?php echo BASE_URL . '/' . UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>" 
                                         alt="<?php echo htmlspecialchars($method['name']); ?> QR Code" 
                                         style="max-width: 250px; max-height: 250px; border: 2px solid #ddd; border-radius: 8px; padding: 10px; background: white;">
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($method['bank_details'])): ?>
                                <div style="background-color: white; padding: 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; line-height: 1.6;">
                                    <?php echo htmlspecialchars($method['bank_details']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($idx < count($payment_methods) - 1): ?>
                            <div style="margin: 15px 0; text-align: center; color: #999;">- OR -</div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if ($type === 'payment_request'): ?>
                        <p style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 4px;">
                            <strong>Note:</strong> After making the payment, please contact us with your booking number 
                            <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong> 
                            to confirm the payment.
                        </p>
                    <?php endif; ?>
                </div>
                <?php 
                    endif;
                endif; 
                ?>
                
                <?php if ($recipient === 'user'): ?>
                    <p style="margin-top: 20px;">If you have any questions about your booking, please don't hesitate to contact us.</p>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p><strong><?php echo htmlspecialchars($site_name); ?></strong></p>
                <?php if ($contact_phone): ?>
                    <p>Phone: <?php echo htmlspecialchars($contact_phone); ?></p>
                <?php endif; ?>
                <?php if ($contact_email): ?>
                    <p>Email: <?php echo htmlspecialchars($contact_email); ?></p>
                <?php endif; ?>
                <p style="margin-top: 15px; font-size: 12px; color: #999;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Get active payment methods
 * @return array Array of active payment methods
 */
function getActivePaymentMethods() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY display_order ASC, name ASC");
    return $stmt->fetchAll();
}

/**
 * Get payment methods for a booking
 * @param int $booking_id Booking ID
 * @return array Array of payment methods assigned to the booking
 */
function getBookingPaymentMethods($booking_id) {
    $db = getDB();
    $sql = "SELECT pm.* FROM payment_methods pm
            INNER JOIN booking_payment_methods bpm ON pm.id = bpm.payment_method_id
            WHERE bpm.booking_id = ?
            ORDER BY pm.display_order ASC, pm.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll();
}

/**
 * Link payment methods to a booking
 * @param int $booking_id Booking ID
 * @param array $payment_method_ids Array of payment method IDs
 * @return bool Success status
 */
function linkPaymentMethodsToBooking($booking_id, $payment_method_ids) {
    if (empty($payment_method_ids)) {
        return true; // No payment methods to link
    }
    
    $db = getDB();
    
    try {
        // Delete existing payment method associations
        $stmt = $db->prepare("DELETE FROM booking_payment_methods WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        
        // Insert new associations
        $stmt = $db->prepare("INSERT INTO booking_payment_methods (booking_id, payment_method_id) VALUES (?, ?)");
        foreach ($payment_method_ids as $method_id) {
            $stmt->execute([$booking_id, intval($method_id)]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error linking payment methods: ' . $e->getMessage());
        return false;
    }
}

/**
 * Record a payment transaction
 * @param array $data Payment data (booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, notes)
 * @return array Result with success status and payment_id or error message
 */
function recordPayment($data) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Validate required fields
        if (empty($data['booking_id'])) {
            throw new Exception('Booking ID is required.');
        }
        if (empty($data['paid_amount'])) {
            throw new Exception('Paid amount is required.');
        }
        
        // Insert payment record
        $sql = "INSERT INTO payments (booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, notes) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['booking_id'],
            $data['payment_method_id'] ?? null,
            $data['transaction_id'] ?? null,
            $data['paid_amount'],
            $data['payment_slip'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $payment_id = $db->lastInsertId();
        
        // Update booking status
        if (isset($data['update_booking_status']) && $data['update_booking_status']) {
            // Update booking status to payment_submitted if with payment
            $stmt = $db->prepare("UPDATE bookings SET booking_status = 'payment_submitted' WHERE id = ?");
            $stmt->execute([$data['booking_id']]);
        }
        
        // Calculate total paid amount for this booking
        $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total_paid FROM payments WHERE booking_id = ?");
        $stmt->execute([$data['booking_id']]);
        $result = $stmt->fetch();
        $total_paid = floatval($result['total_paid']);
        
        // Get booking grand total
        $stmt = $db->prepare("SELECT grand_total FROM bookings WHERE id = ?");
        $stmt->execute([$data['booking_id']]);
        $booking = $stmt->fetch();
        $grand_total = $booking['grand_total'] ?? 0;
        
        // Update payment status
        $payment_status = 'unpaid';
        if ($total_paid >= $grand_total) {
            $payment_status = 'paid';
        } elseif ($total_paid > 0) {
            $payment_status = 'partial';
        }
        
        $stmt = $db->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $data['booking_id']]);
        
        $db->commit();
        
        return ['success' => true, 'payment_id' => $payment_id];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get payments for a booking
 * @param int $booking_id Booking ID
 * @return array Array of payments
 */
function getBookingPayments($booking_id) {
    $db = getDB();
    $sql = "SELECT p.*, pm.name as payment_method_name 
            FROM payments p
            LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
            WHERE p.booking_id = ?
            ORDER BY p.payment_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll();
}
