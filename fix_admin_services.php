<?php
/**
 * Fix Admin Services - Database Column Fixer
 * 
 * This script checks if the required columns for admin services exist
 * and adds them if they're missing. Run this script if you're experiencing
 * "Failed to add admin service" error.
 * 
 * HOW TO USE:
 * 1. Access this file via your browser: http://yoursite.com/fix_admin_services.php
 * 2. Follow the on-screen instructions
 * 3. Delete this file after successful execution
 * 
 * SECURITY WARNING: This file makes database changes. Delete it after use!
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
        <p>This database migration requires admin authentication.</p>
        <p><a href="/admin/login.php">Login as Admin</a></p>
    </body></html>');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle self-delete request
if (isset($_GET['delete_me']) && $_GET['delete_me'] === 'yes') {
    $deleted = @unlink(__FILE__);
    if ($deleted) {
        die('<!DOCTYPE html>
        <html><head><meta charset="UTF-8"><title>File Deleted</title></head>
        <body style="font-family: Arial; padding: 50px; text-align: center;">
            <h1 style="color: green;">✅ File Deleted Successfully</h1>
            <p>The fix_admin_services.php file has been removed from your server.</p>
            <p><a href="/admin/bookings/">Go to Bookings</a></p>
        </body></html>');
    } else {
        die('<!DOCTYPE html>
        <html><head><meta charset="UTF-8"><title>Delete Failed</title></head>
        <body style="font-family: Arial; padding: 50px; text-align: center;">
            <h1 style="color: red;">❌ Could Not Delete File</h1>
            <p>Please manually delete fix_admin_services.php from your server.</p>
            <p>This may be a permissions issue. Contact your hosting provider if needed.</p>
        </body></html>');
    }
}

require_once __DIR__ . '/includes/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Admin Services - Database Migration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        .status { 
            padding: 15px; 
            border-radius: 8px; 
            margin: 15px 0; 
            font-weight: 500;
        }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        code { 
            background: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-family: 'Courier New', monospace; 
            font-size: 14px;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .step { 
            margin: 20px 0; 
            padding: 20px; 
            background: #f8f9fa; 
            border-radius: 8px; 
        }
        .step h3 { color: #667eea; margin-bottom: 10px; }
        ul { margin-left: 20px; line-height: 1.8; }
        .footer { 
            margin-top: 30px; 
            padding-top: 20px; 
            border-top: 2px solid #eee; 
            color: #666; 
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Admin Services Database</h1>
        
        <?php
        try {
            $db = getDB();
            
            // Check if columns exist
            $stmt = $db->query("SHOW COLUMNS FROM booking_services LIKE 'added_by'");
            $has_added_by = $stmt->rowCount() > 0;
            
            $stmt = $db->query("SHOW COLUMNS FROM booking_services LIKE 'quantity'");
            $has_quantity = $stmt->rowCount() > 0;
            
            if ($has_added_by && $has_quantity) {
                echo '<div class="status success">
                    ✅ <strong>Database is already up to date!</strong><br>
                    The required columns (<code>added_by</code> and <code>quantity</code>) already exist in the booking_services table.
                </div>';
                
                echo '<div class="status info">
                    ℹ️ If you\'re still experiencing issues, please check:
                    <ul>
                        <li>PHP error logs for detailed error messages</li>
                        <li>Database connection settings in <code>config/db.php</code></li>
                        <li>File permissions for the uploads directory</li>
                    </ul>
                </div>';
                
            } else if (isset($_POST['apply_fix'])) {
                // Apply the fix
                $db->beginTransaction();
                
                try {
                    $errors = [];
                    
                    // Add added_by column if missing
                    if (!$has_added_by) {
                        $db->exec("ALTER TABLE booking_services 
                                   ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user' 
                                   COMMENT 'Who added the service: user during booking or admin later'
                                   AFTER category");
                        $db->exec("UPDATE booking_services SET added_by = 'user' WHERE added_by IS NULL");
                        echo '<div class="status success">✅ Added <code>added_by</code> column successfully</div>';
                    }
                    
                    // Add quantity column if missing
                    if (!$has_quantity) {
                        $db->exec("ALTER TABLE booking_services 
                                   ADD COLUMN quantity INT DEFAULT 1 
                                   COMMENT 'Quantity of service'
                                   AFTER added_by");
                        $db->exec("UPDATE booking_services SET quantity = 1 WHERE quantity IS NULL");
                        echo '<div class="status success">✅ Added <code>quantity</code> column successfully</div>';
                    }
                    
                    // Create index
                    try {
                        $db->exec("CREATE INDEX idx_booking_services_added_by ON booking_services(added_by)");
                        echo '<div class="status success">✅ Created performance index</div>';
                    } catch (PDOException $e) {
                        // Index might already exist, that's okay
                        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                            throw $e;
                        }
                    }
                    
                    $db->commit();
                    
                    echo '<div class="status success">
                        🎉 <strong>Migration completed successfully!</strong><br>
                        Your database has been updated. You can now add admin services to bookings.
                    </div>';
                    
                    // Self-delete option
                    echo '<div class="status warning">
                        ⚠️ <strong>Security:</strong> This file makes database changes and should be deleted immediately!
                        <div style="margin-top: 15px;">
                            <a href="?delete_me=yes" 
                               onclick="return confirm(\'Are you sure you want to delete this fix file? Make sure the migration was successful first!\')"
                               class="btn btn-success">
                                🗑️ Delete This File Now
                            </a>
                        </div>
                    </div>';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    echo '<div class="status error">
                        ❌ <strong>Error applying fix:</strong><br>' . htmlspecialchars($e->getMessage()) . '
                    </div>';
                }
                
            } else {
                // Show form to apply fix
                echo '<div class="status warning">
                    ⚠️ <strong>Missing database columns detected!</strong><br>
                    The booking_services table is missing required columns for admin services feature.
                </div>';
                
                echo '<div class="step">';
                echo '<h3>Missing Columns:</h3>';
                echo '<ul>';
                if (!$has_added_by) echo '<li><code>added_by</code> - Tracks whether service was added by user or admin</li>';
                if (!$has_quantity) echo '<li><code>quantity</code> - Stores the quantity of each service</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '<div class="status info">
                    ℹ️ <strong>What this will do:</strong>
                    <ul>
                        <li>Add missing columns to the <code>booking_services</code> table</li>
                        <li>Set default values for existing records</li>
                        <li>Create performance indexes</li>
                        <li>Does NOT delete or modify existing data</li>
                    </ul>
                </div>';
                
                echo '<form method="post" onsubmit="return confirm(\'Are you sure you want to apply these database changes?\');">
                    <button type="submit" name="apply_fix" class="btn btn-success">✓ Apply Fix Now</button>
                </form>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="status error">
                ❌ <strong>Database Connection Error:</strong><br>' . 
                htmlspecialchars($e->getMessage()) . 
            '</div>';
            
            echo '<div class="status info">
                Please check your database configuration in <code>config/db.php</code>
            </div>';
        }
        ?>
        
        <div class="footer">
            <p><strong>Need help?</strong> Check the documentation or contact support.</p>
        </div>
    </div>
</body>
</html>
