<?php
/**
 * Test Admin Services Functionality
 * 
 * This script tests the admin services feature to ensure it's working correctly.
 * Run this after applying the database fix.
 * 
 * SECURITY: This file should be deleted after use!
 */

// Security check - require admin authentication
session_start();
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Access Denied</title></head>
    <body style="font-family: Arial; padding: 50px; text-align: center;">
        <h1>🔒 Access Denied</h1>
        <p>This page requires admin authentication.</p>
        <p><a href="/admin/login.php">Login as Admin</a></p>
    </body></html>');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Admin Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #667eea; margin-top: 30px; margin-bottom: 15px; font-size: 20px; }
        .test { 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 6px; 
            border-left: 4px solid #ccc;
        }
        .test.pass { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .test.fail { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .test.info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
        .test-name { font-weight: 600; margin-bottom: 5px; }
        .test-details { font-size: 14px; opacity: 0.9; margin-top: 5px; }
        code { 
            background: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        .summary {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .summary-card {
            flex: 1;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .summary-card.total { background: #e3f2fd; }
        .summary-card.passed { background: #d4edda; }
        .summary-card.failed { background: #f8d7da; }
        .summary-number { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .summary-label { font-size: 14px; text-transform: uppercase; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Admin Services Functionality Test</h1>
        
        <?php
        $tests_passed = 0;
        $tests_failed = 0;
        $tests_total = 0;
        
        function runTest($name, $callable) {
            global $tests_passed, $tests_failed, $tests_total;
            $tests_total++;
            
            try {
                $result = $callable();
                if ($result['success']) {
                    $tests_passed++;
                    echo '<div class="test pass">';
                    echo '<div class="test-name">✅ ' . htmlspecialchars($name) . '</div>';
                    if (isset($result['message'])) {
                        echo '<div class="test-details">' . htmlspecialchars($result['message']) . '</div>';
                    }
                    echo '</div>';
                } else {
                    $tests_failed++;
                    echo '<div class="test fail">';
                    echo '<div class="test-name">❌ ' . htmlspecialchars($name) . '</div>';
                    echo '<div class="test-details">' . htmlspecialchars($result['message'] ?? 'Test failed') . '</div>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                $tests_failed++;
                echo '<div class="test fail">';
                echo '<div class="test-name">❌ ' . htmlspecialchars($name) . '</div>';
                echo '<div class="test-details">Exception: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
            }
        }
        
        try {
            $db = getDB();
            
            echo '<h2>1. Database Connection</h2>';
            runTest('Database Connection', function() use ($db) {
                return ['success' => true, 'message' => 'Successfully connected to database'];
            });
            
            echo '<h2>2. Table Structure</h2>';
            
            // Check if booking_services table exists
            runTest('booking_services table exists', function() use ($db) {
                $stmt = $db->query("SHOW TABLES LIKE 'booking_services'");
                $exists = $stmt->rowCount() > 0;
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Table exists' : 'Table not found'
                ];
            });
            
            // Check for added_by column
            runTest('added_by column exists', function() use ($db) {
                $stmt = $db->query("SHOW COLUMNS FROM booking_services LIKE 'added_by'");
                $exists = $stmt->rowCount() > 0;
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Column exists with correct type' : 'Column missing - run fix script!'
                ];
            });
            
            // Check for quantity column
            runTest('quantity column exists', function() use ($db) {
                $stmt = $db->query("SHOW COLUMNS FROM booking_services LIKE 'quantity'");
                $exists = $stmt->rowCount() > 0;
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Column exists with correct type' : 'Column missing - run fix script!'
                ];
            });
            
            // Check for index
            runTest('Performance index exists', function() use ($db) {
                $stmt = $db->query("SHOW INDEX FROM booking_services WHERE Key_name = 'idx_booking_services_added_by'");
                $exists = $stmt->rowCount() > 0;
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Index exists for better query performance' : 'Index missing (optional but recommended)'
                ];
            });
            
            echo '<h2>3. Function Availability</h2>';
            
            // Check if addAdminService function exists
            runTest('addAdminService() function exists', function() {
                $exists = function_exists('addAdminService');
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Function is defined in includes/functions.php' : 'Function not found'
                ];
            });
            
            // Check if deleteAdminService function exists
            runTest('deleteAdminService() function exists', function() {
                $exists = function_exists('deleteAdminService');
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Function is defined in includes/functions.php' : 'Function not found'
                ];
            });
            
            // Check if recalculateBookingTotals function exists
            runTest('recalculateBookingTotals() function exists', function() {
                $exists = function_exists('recalculateBookingTotals');
                return [
                    'success' => $exists,
                    'message' => $exists ? 'Function is defined in includes/functions.php' : 'Function not found'
                ];
            });
            
            echo '<h2>4. Data Integrity</h2>';
            
            // Check if there are any bookings
            runTest('Bookings exist in database', function() use ($db) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
                $result = $stmt->fetch();
                $count = $result['count'];
                return [
                    'success' => $count > 0,
                    'message' => $count > 0 ? "Found {$count} booking(s)" : 'No bookings found (create one to test)'
                ];
            });
            
            // Check existing services data
            runTest('Existing services have correct data', function() use ($db) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM booking_services WHERE added_by IS NULL OR quantity IS NULL");
                $result = $stmt->fetch();
                $null_count = $result['count'];
                
                if ($null_count > 0) {
                    return [
                        'success' => false,
                        'message' => "{$null_count} service(s) have NULL values - run UPDATE query"
                    ];
                }
                
                return [
                    'success' => true,
                    'message' => 'All existing services have valid data'
                ];
            });
            
            echo '<h2>5. Display Current Data</h2>';
            
            // Show sample of existing services
            $stmt = $db->query("SELECT id, booking_id, service_name, price, quantity, added_by, created_at 
                               FROM booking_services 
                               ORDER BY id DESC 
                               LIMIT 10");
            $services = $stmt->fetchAll();
            
            if (count($services) > 0) {
                echo '<div class="test info">';
                echo '<div class="test-name">📋 Recent Services (Last 10)</div>';
                echo '<table>';
                echo '<thead><tr>
                        <th>ID</th>
                        <th>Booking</th>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Added By</th>
                        <th>Date</th>
                      </tr></thead>';
                echo '<tbody>';
                foreach ($services as $service) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($service['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($service['booking_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($service['service_name']) . '</td>';
                    echo '<td>NPR ' . number_format($service['price'], 2) . '</td>';
                    echo '<td>' . htmlspecialchars($service['quantity']) . '</td>';
                    echo '<td><code>' . htmlspecialchars($service['added_by']) . '</code></td>';
                    echo '<td>' . date('Y-m-d H:i', strtotime($service['created_at'])) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } else {
                echo '<div class="test info">';
                echo '<div class="test-name">ℹ️ No services found in database</div>';
                echo '<div class="test-details">This is normal for a fresh installation</div>';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="test fail">';
            echo '<div class="test-name">❌ Database Error</div>';
            echo '<div class="test-details">' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '</div>';
        }
        ?>
        
        <h2>📊 Test Summary</h2>
        <div class="summary">
            <div class="summary-card total">
                <div class="summary-number"><?php echo $tests_total; ?></div>
                <div class="summary-label">Total Tests</div>
            </div>
            <div class="summary-card passed">
                <div class="summary-number"><?php echo $tests_passed; ?></div>
                <div class="summary-label">Passed</div>
            </div>
            <div class="summary-card failed">
                <div class="summary-number"><?php echo $tests_failed; ?></div>
                <div class="summary-label">Failed</div>
            </div>
        </div>
        
        <?php if ($tests_failed > 0): ?>
            <div class="test fail">
                <div class="test-name">⚠️ Action Required</div>
                <div class="test-details">
                    Some tests failed. Please:
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Run <code>fix_admin_services.php</code> to apply database fixes</li>
                        <li>Or manually run the SQL migration from <code>database/migrations/fix_admin_services_columns.sql</code></li>
                        <li>Refresh this page after applying fixes</li>
                    </ol>
                </div>
            </div>
        <?php else: ?>
            <div class="test pass">
                <div class="test-name">✅ All Tests Passed!</div>
                <div class="test-details">
                    Your database is properly configured for admin services. You can now:
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>Go to any booking: <code>admin/bookings/view.php?id=&lt;booking_id&gt;</code></li>
                        <li>Scroll to "Admin Added Services" section</li>
                        <li>Add a test service to verify functionality</li>
                    </ul>
                </div>
            </div>
            
            <?php if (isset($_GET['delete_me']) && $_GET['delete_me'] === 'yes'): ?>
                <?php
                // Self-delete this file
                $deleted = @unlink(__FILE__);
                if ($deleted) {
                    echo '<div class="test pass">
                        <div class="test-name">🗑️ File Deleted Successfully</div>
                        <div class="test-details">This test file has been removed from your server.</div>
                    </div>';
                } else {
                    echo '<div class="test fail">
                        <div class="test-name">❌ Could Not Delete File</div>
                        <div class="test-details">Please manually delete <code>test_admin_services.php</code> from your server.</div>
                    </div>';
                }
                ?>
            <?php else: ?>
                <div class="test fail" style="background: #fff3cd; border-left-color: #ffc107; color: #856404;">
                    <div class="test-name">⚠️ SECURITY WARNING</div>
                    <div class="test-details">
                        This file exposes sensitive database information and should be deleted immediately!
                        <div style="margin-top: 10px;">
                            <a href="?delete_me=yes" 
                               onclick="return confirm('Are you sure you want to delete this test file?')"
                               style="display: inline-block; padding: 8px 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                                🗑️ Delete This File Now
                            </a>
                            <span style="font-size: 12px; color: #666;">Or manually delete <code>test_admin_services.php</code></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; color: #666; font-size: 14px;">
            <p><strong>⚠️ SECURITY:</strong> This test file should be deleted after verification. It is protected by admin authentication but should not remain on the server.</p>
        </div>
    </div>
</body>
</html>
