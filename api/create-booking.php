<?php
/**
 * Create Booking API
 * Creates a new booking with all details
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

// Extract booking data
$customer = [
    'full_name' => $data['full_name'] ?? '',
    'phone' => $data['phone'] ?? '',
    'email' => $data['email'] ?? '',
    'address' => $data['address'] ?? ''
];

$booking = [
    'venue_id' => $data['venue_id'] ?? null,
    'hall_id' => $data['hall_id'] ?? null,
    'booking_date' => $data['booking_date'] ?? null,
    'shift' => $data['shift'] ?? null,
    'number_of_guests' => $data['number_of_guests'] ?? 0,
    'event_type' => $data['event_type'] ?? '',
    'special_requests' => $data['special_requests'] ?? '',
    'payment_option' => $data['payment_option'] ?? 'advance'
];

$menus = $data['menus'] ?? [];
$services = $data['services'] ?? [];

// Validate required fields
if (empty($customer['full_name']) || empty($customer['phone']) || empty($customer['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Customer information is incomplete'
    ]);
    exit;
}

if (empty($booking['hall_id']) || empty($booking['booking_date']) || empty($booking['shift']) || empty($booking['number_of_guests'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking information is incomplete'
    ]);
    exit;
}

// Check hall availability
if (!checkHallAvailability($booking['hall_id'], $booking['booking_date'], $booking['shift'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, the selected hall is not available for the chosen date and shift'
    ]);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Insert or get customer
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = :email");
    $stmt->bindParam(':email', $customer['email']);
    $stmt->execute();
    $existingCustomer = $stmt->fetch();
    
    if ($existingCustomer) {
        $customer_id = $existingCustomer['id'];
        
        // Update customer info
        $stmt = $db->prepare("UPDATE customers SET full_name = :full_name, phone = :phone, address = :address WHERE id = :id");
        $stmt->bindParam(':full_name', $customer['full_name']);
        $stmt->bindParam(':phone', $customer['phone']);
        $stmt->bindParam(':address', $customer['address']);
        $stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Insert new customer
        $stmt = $db->prepare("INSERT INTO customers (full_name, phone, email, address) VALUES (:full_name, :phone, :email, :address)");
        $stmt->bindParam(':full_name', $customer['full_name']);
        $stmt->bindParam(':phone', $customer['phone']);
        $stmt->bindParam(':email', $customer['email']);
        $stmt->bindParam(':address', $customer['address']);
        $stmt->execute();
        $customer_id = $db->lastInsertId();
    }
    
    // Calculate totals
    $calculation = calculateBookingTotal($booking['hall_id'], $menus, $booking['number_of_guests'], $services);
    
    // Calculate advance payment
    $advance_payment = 0;
    if ($booking['payment_option'] === 'advance') {
        $advance_payment = $calculation['total'] * (ADVANCE_PAYMENT_PERCENTAGE / 100);
    } elseif ($booking['payment_option'] === 'full') {
        $advance_payment = $calculation['total'];
    }
    
    // Determine payment status
    $payment_status = 'pending';
    if ($booking['payment_option'] === 'full') {
        $payment_status = 'paid';
    } elseif ($booking['payment_option'] === 'advance') {
        $payment_status = 'partial';
    }
    
    // Generate booking number
    $booking_number = generateBookingNumber();
    
    // Insert booking
    $sql = "INSERT INTO bookings (booking_number, customer_id, venue_id, hall_id, booking_date, shift, 
            number_of_guests, event_type, subtotal, tax_amount, total_cost, advance_payment, 
            payment_status, booking_status, special_requests) 
            VALUES (:booking_number, :customer_id, :venue_id, :hall_id, :booking_date, :shift, 
            :number_of_guests, :event_type, :subtotal, :tax_amount, :total_cost, :advance_payment, 
            :payment_status, 'confirmed', :special_requests)";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':booking_number', $booking_number);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':venue_id', $booking['venue_id'], PDO::PARAM_INT);
    $stmt->bindParam(':hall_id', $booking['hall_id'], PDO::PARAM_INT);
    $stmt->bindParam(':booking_date', $booking['booking_date']);
    $stmt->bindParam(':shift', $booking['shift']);
    $stmt->bindParam(':number_of_guests', $booking['number_of_guests'], PDO::PARAM_INT);
    $stmt->bindParam(':event_type', $booking['event_type']);
    $stmt->bindParam(':subtotal', $calculation['subtotal']);
    $stmt->bindParam(':tax_amount', $calculation['tax_amount']);
    $stmt->bindParam(':total_cost', $calculation['total']);
    $stmt->bindParam(':advance_payment', $advance_payment);
    $stmt->bindParam(':payment_status', $payment_status);
    $stmt->bindParam(':special_requests', $booking['special_requests']);
    $stmt->execute();
    
    $booking_id = $db->lastInsertId();
    
    // Insert booking menus
    if (!empty($menus)) {
        foreach ($menus as $menu_id) {
            $menuStmt = $db->prepare("SELECT price_per_person FROM menus WHERE id = :menu_id");
            $menuStmt->bindParam(':menu_id', $menu_id, PDO::PARAM_INT);
            $menuStmt->execute();
            $menu = $menuStmt->fetch();
            
            if ($menu) {
                $total_price = $menu['price_per_person'] * $booking['number_of_guests'];
                
                $stmt = $db->prepare("INSERT INTO booking_menus (booking_id, menu_id, quantity, price_per_person, total_price) 
                                     VALUES (:booking_id, :menu_id, :quantity, :price_per_person, :total_price)");
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(':menu_id', $menu_id, PDO::PARAM_INT);
                $stmt->bindParam(':quantity', $booking['number_of_guests'], PDO::PARAM_INT);
                $stmt->bindParam(':price_per_person', $menu['price_per_person']);
                $stmt->bindParam(':total_price', $total_price);
                $stmt->execute();
            }
        }
    }
    
    // Insert booking services
    if (!empty($services)) {
        foreach ($services as $service_id) {
            $serviceStmt = $db->prepare("SELECT price FROM additional_services WHERE id = :service_id");
            $serviceStmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
            $serviceStmt->execute();
            $service = $serviceStmt->fetch();
            
            if ($service) {
                $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, quantity, price, total_price) 
                                     VALUES (:booking_id, :service_id, 1, :price, :price)");
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
                $stmt->bindParam(':price', $service['price']);
                $stmt->execute();
            }
        }
    }
    
    $db->commit();
    
    // Send confirmation email
    sendBookingConfirmation($booking_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $booking_id,
        'booking_number' => $booking_number
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create booking: ' . $e->getMessage()
    ]);
}
