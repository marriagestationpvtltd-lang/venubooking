<?php
/**
 * Database Setup Verification Script
 * This script checks if the database is properly configured and booking #23 exists
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Database Setup Verification ===\n\n";

// Check if config file exists
if (!file_exists(__DIR__ . '/config/database.php')) {
    echo "❌ Error: config/database.php not found\n";
    exit(1);
}

echo "✅ Config file found\n";

// Load configuration
require_once __DIR__ . '/config/database.php';

echo "✅ Config loaded\n";
echo "   - DB_HOST: " . DB_HOST . "\n";
echo "   - DB_NAME: " . DB_NAME . "\n";
echo "   - DB_USER: " . DB_USER . "\n\n";

// Check if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ .env file found\n\n";
} else {
    echo "⚠️  Warning: .env file not found (using defaults)\n\n";
}

// Check if db.php exists
if (!file_exists(__DIR__ . '/includes/db.php')) {
    echo "❌ Error: includes/db.php not found\n";
    exit(1);
}

require_once __DIR__ . '/includes/db.php';
echo "✅ Database handler loaded\n\n";

// Test database connection
echo "Testing database connection...\n";
try {
    $db = getDB();
    echo "✅ Database connection successful\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    echo "Please check:\n";
    echo "1. MySQL service is running: sudo service mysql start\n";
    echo "2. Database credentials in .env file\n";
    echo "3. Database 'venubooking' exists\n\n";
    exit(1);
}

// Check if tables exist
echo "Checking database tables...\n";
try {
    $tables = [
        'venues', 'halls', 'menus', 'menu_items', 'customers', 
        'bookings', 'booking_menus', 'booking_services', 
        'additional_services', 'users', 'settings'
    ];
    
    $existingTables = 0;
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existingTables++;
        } else {
            $missingTables[] = $table;
        }
    }
    
    echo "✅ Found $existingTables/" . count($tables) . " required tables\n";
    
    if (!empty($missingTables)) {
        echo "⚠️  Missing tables: " . implode(', ', $missingTables) . "\n";
        echo "   Run: mysql -u root -p < database/complete-setup.sql\n\n";
    } else {
        echo "✅ All required tables exist\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "\n\n";
}

// Check for booking #23
echo "Checking for booking #23...\n";
try {
    $stmt = $db->prepare("SELECT id, booking_number, event_type, booking_status, payment_status FROM bookings WHERE id = 23");
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if ($booking) {
        echo "✅ Booking #23 found!\n";
        echo "   - Booking Number: " . $booking['booking_number'] . "\n";
        echo "   - Event Type: " . $booking['event_type'] . "\n";
        echo "   - Status: " . $booking['booking_status'] . " / " . $booking['payment_status'] . "\n\n";
        
        // Check booking details
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_menus WHERE booking_id = 23");
        $stmt->execute();
        $menuCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM booking_services WHERE booking_id = 23");
        $stmt->execute();
        $serviceCount = $stmt->fetch()['count'];
        
        echo "✅ Booking #23 has $menuCount menu(s) and $serviceCount service(s)\n\n";
    } else {
        echo "❌ Booking #23 NOT found\n";
        echo "   Run: mysql -u root -p < database/fix-booking-23.sql\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking booking: " . $e->getMessage() . "\n\n";
}

// Check FPDF library
echo "Checking FPDF library...\n";
if (file_exists(__DIR__ . '/lib/fpdf.php')) {
    echo "✅ FPDF library found\n\n";
} else {
    echo "❌ FPDF library NOT found at lib/fpdf.php\n";
    echo "   Download from: http://www.fpdf.org/\n\n";
}

// Check functions.php
echo "Checking functions.php...\n";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
    echo "✅ functions.php loaded\n";
    
    if (function_exists('getBookingDetails')) {
        echo "✅ getBookingDetails function exists\n\n";
    } else {
        echo "❌ getBookingDetails function NOT found\n\n";
    }
} else {
    echo "❌ functions.php NOT found\n\n";
}

// Test generate_pdf.php requirements
echo "Testing generate_pdf.php requirements...\n";
$allOk = true;

if (!file_exists(__DIR__ . '/config/database.php')) {
    echo "❌ config/database.php missing\n";
    $allOk = false;
}

if (!file_exists(__DIR__ . '/includes/functions.php')) {
    echo "❌ includes/functions.php missing\n";
    $allOk = false;
}

if (!file_exists(__DIR__ . '/lib/fpdf.php')) {
    echo "❌ lib/fpdf.php missing\n";
    $allOk = false;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE id = 23");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        echo "❌ Booking #23 not found in database\n";
        $allOk = false;
    }
} catch (Exception $e) {
    echo "❌ Cannot query database: " . $e->getMessage() . "\n";
    $allOk = false;
}

if ($allOk) {
    echo "✅ All requirements met!\n\n";
    echo "=== SUCCESS ===\n";
    echo "generate_pdf.php should work for booking ID=23\n";
    echo "Test it at: http://your-domain.com/venubooking/generate_pdf.php?id=23\n\n";
} else {
    echo "\n=== ISSUES FOUND ===\n";
    echo "Please fix the issues above before testing generate_pdf.php\n\n";
}

echo "=== Verification Complete ===\n";
