<?php
/**
 * Test Services Display - Diagnostic Script
 * 
 * This script helps diagnose issues with additional services not displaying in booking views.
 * Run this script to check:
 * 1. Database connection
 * 2. Table structure
 * 3. Existing booking services data
 * 4. Query execution
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Display Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .test-pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .test-fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Additional Services Display Test</h1>
        <p><em>Run this script to diagnose issues with services not displaying in booking views.</em></p>

        <?php
        try {
            $db = getDB();
            echo '<div class="test-result test-pass">✅ <strong>Database Connection:</strong> Successful</div>';

            // Test 1: Check if booking_services table exists
            echo '<h2>Test 1: Check Database Table Structure</h2>';
            $tables = $db->query("SHOW TABLES LIKE 'booking_services'")->fetchAll();
            if (count($tables) > 0) {
                echo '<div class="test-result test-pass">✅ Table <code>booking_services</code> exists</div>';
                
                // Show table structure
                $columns = $db->query("DESCRIBE booking_services")->fetchAll();
                echo '<table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead><tbody>';
                foreach ($columns as $col) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Default']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="test-result test-fail">❌ Table <code>booking_services</code> does NOT exist. Please run database setup.</div>';
            }

            // Test 2: Count total services in booking_services
            echo '<h2>Test 2: Check Booking Services Data</h2>';
            $count = $db->query("SELECT COUNT(*) as count FROM booking_services")->fetch();
            echo '<div class="test-result ' . ($count['count'] > 0 ? 'test-pass' : 'test-fail') . '">';
            echo ($count['count'] > 0 ? '✅' : '⚠️') . ' Total booking services in database: <strong>' . $count['count'] . '</strong>';
            echo '</div>';

            if ($count['count'] > 0) {
                // Show sample data
                echo '<h3>Sample Booking Services:</h3>';
                $samples = $db->query("
                    SELECT bs.id, bs.booking_id, bs.service_id, bs.service_name, bs.price, 
                           b.booking_number, b.booking_status
                    FROM booking_services bs
                    LEFT JOIN bookings b ON bs.booking_id = b.id
                    ORDER BY bs.booking_id DESC
                    LIMIT 10
                ")->fetchAll();
                
                echo '<table><thead><tr><th>ID</th><th>Booking#</th><th>Service ID</th><th>Service Name</th><th>Price</th><th>Status</th></tr></thead><tbody>';
                foreach ($samples as $sample) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($sample['id']) . '</td>';
                    echo '<td><a href="admin/bookings/view.php?id=' . $sample['booking_id'] . '" target="_blank">' . htmlspecialchars($sample['booking_number']) . '</a></td>';
                    echo '<td>' . htmlspecialchars($sample['service_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($sample['service_name']) . '</td>';
                    echo '<td>NPR ' . number_format($sample['price'], 2) . '</td>';
                    echo '<td>' . htmlspecialchars($sample['booking_status']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="warning">⚠️ No booking services found. This could mean:</div>';
                echo '<ul>';
                echo '<li>No bookings have been created with additional services yet</li>';
                echo '<li>Database was recently reset</li>';
                echo '<li>Sample data was not imported</li>';
                echo '</ul>';
            }

            // Test 3: Check additional_services master table
            echo '<h2>Test 3: Check Available Services</h2>';
            $servicesCount = $db->query("SELECT COUNT(*) as count FROM additional_services WHERE status = 'active'")->fetch();
            echo '<div class="test-result ' . ($servicesCount['count'] > 0 ? 'test-pass' : 'test-fail') . '">';
            echo ($servicesCount['count'] > 0 ? '✅' : '❌') . ' Active services available: <strong>' . $servicesCount['count'] . '</strong>';
            echo '</div>';

            if ($servicesCount['count'] > 0) {
                $services = $db->query("SELECT id, name, description, price, category FROM additional_services WHERE status = 'active' ORDER BY category, name")->fetchAll();
                echo '<table><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Category</th><th>Price</th></tr></thead><tbody>';
                foreach ($services as $service) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($service['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($service['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($service['description']) . '</td>';
                    echo '<td>' . htmlspecialchars($service['category']) . '</td>';
                    echo '<td>NPR ' . number_format($service['price'], 2) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="test-result test-fail">❌ No active services found. Users cannot select services during booking.</div>';
            }

            // Test 4: Test getBookingDetails function
            echo '<h2>Test 4: Test getBookingDetails() Function</h2>';
            $testBookings = $db->query("
                SELECT DISTINCT b.id, b.booking_number
                FROM bookings b
                INNER JOIN booking_services bs ON b.id = bs.booking_id
                LIMIT 5
            ")->fetchAll();

            if (count($testBookings) > 0) {
                echo '<p>Testing bookings that have services...</p>';
                foreach ($testBookings as $testBooking) {
                    echo '<h4>Booking: ' . htmlspecialchars($testBooking['booking_number']) . '</h4>';
                    
                    try {
                        $details = getBookingDetails($testBooking['id']);
                        
                        if (isset($details['services']) && is_array($details['services'])) {
                            $serviceCount = count($details['services']);
                            echo '<div class="test-result ' . ($serviceCount > 0 ? 'test-pass' : 'test-fail') . '">';
                            echo ($serviceCount > 0 ? '✅' : '❌') . ' Services retrieved: <strong>' . $serviceCount . '</strong>';
                            echo '</div>';
                            
                            if ($serviceCount > 0) {
                                echo '<table><thead><tr><th>Service Name</th><th>Price</th><th>Description</th><th>Category</th></tr></thead><tbody>';
                                foreach ($details['services'] as $service) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($service['service_name']) . '</td>';
                                    echo '<td>NPR ' . number_format($service['price'], 2) . '</td>';
                                    echo '<td>' . htmlspecialchars($service['description'] ?? 'N/A') . '</td>';
                                    echo '<td>' . htmlspecialchars($service['category'] ?? 'N/A') . '</td>';
                                    echo '</tr>';
                                }
                                echo '</tbody></table>';
                            }
                        } else {
                            echo '<div class="test-result test-fail">❌ Services array not found in booking details</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="test-result test-fail">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            } else {
                echo '<div class="warning">⚠️ No bookings with services found to test</div>';
            }

            // Test 5: Check the actual SQL query
            echo '<h2>Test 5: Test SQL Query Directly</h2>';
            echo '<p>Testing the exact query used in getBookingDetails()...</p>';
            
            $testBookingId = $db->query("SELECT id FROM bookings WHERE id IN (SELECT booking_id FROM booking_services) LIMIT 1")->fetch();
            
            if ($testBookingId) {
                $stmt = $db->prepare("
                    SELECT bs.id, bs.booking_id, bs.service_id, bs.service_name, bs.price, 
                           bs.description, bs.category 
                    FROM booking_services bs 
                    WHERE bs.booking_id = ?
                ");
                $stmt->execute([$testBookingId['id']]);
                $results = $stmt->fetchAll();
                
                echo '<div class="test-result test-pass">✅ Query executed successfully. Results: <strong>' . count($results) . '</strong></div>';
                
                if (count($results) > 0) {
                    echo '<table><thead><tr><th>ID</th><th>Service Name</th><th>Price</th><th>Description</th><th>Category</th><th>Source</th></tr></thead><tbody>';
                    foreach ($results as $row) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['service_name']) . '</td>';
                        echo '<td>NPR ' . number_format($row['price'], 2) . '</td>';
                        echo '<td>' . htmlspecialchars($row['description'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($row['category'] ?? 'NULL') . '</td>';
                        echo '<td><span style="color: green;">Denormalized</span></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '<p class="info">ℹ️ Using enhanced query with denormalized description/category columns from booking_services table.</p>';
                }
            } else {
                echo '<div class="warning">⚠️ No bookings with services to test query</div>';
            }

            // Summary and Recommendations
            echo '<h2>📋 Summary and Recommendations</h2>';
            echo '<div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">';
            echo '<h3 style="margin-top: 0;">Diagnosis Complete</h3>';
            
            if ($count['count'] == 0) {
                echo '<p><strong>Issue Found:</strong> No booking services exist in the database.</p>';
                echo '<p><strong>Solution:</strong></p>';
                echo '<ol>';
                echo '<li>Import sample data: <code>mysql -u root -p venubooking < database/sample-data.sql</code></li>';
                echo '<li>Create a new booking with services through the front-end</li>';
                echo '<li>Make sure users can access booking-step4.php to select services</li>';
                echo '</ol>';
            } elseif ($servicesCount['count'] == 0) {
                echo '<p><strong>Issue Found:</strong> No active services available for selection.</p>';
                echo '<p><strong>Solution:</strong> Go to Admin Panel → Services → Add Service to create services users can select.</p>';
            } else {
                echo '<p><strong>Status:</strong> System appears to be functioning correctly.</p>';
                echo '<p><strong>If services still don\'t display:</strong></p>';
                echo '<ol>';
                echo '<li>Clear PHP cache and browser cache</li>';
                echo '<li>Check the specific booking ID that has issues</li>';
                echo '<li>Verify the booking was created after the services feature was implemented</li>';
                echo '<li>Check browser console for JavaScript errors</li>';
                echo '</ol>';
            }
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="test-result test-fail">❌ <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<p>Database connection or query failed. Please check your database configuration.</p>';
        }
        ?>

        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #666;">
            <small>After reviewing the results, you can delete this test file for security.</small>
        </p>
    </div>
</body>
</html>
