<?php
/**
 * Email Setup Diagnostic Tool
 * 
 * This script checks the email configuration and identifies issues
 * Run this from your web browser or command line to diagnose email problems
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
    <title>Email Setup Diagnostic Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #27ae60;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: 500;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .check-item {
            margin: 15px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #ddd;
        }
        .check-item.pass {
            border-left-color: #27ae60;
        }
        .check-item.fail {
            border-left-color: #e74c3c;
        }
        .check-item.warn {
            border-left-color: #f39c12;
        }
        .icon {
            display: inline-block;
            width: 20px;
            font-weight: bold;
        }
        .pass .icon { color: #27ae60; }
        .fail .icon { color: #e74c3c; }
        .warn .icon { color: #f39c12; }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .solution {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-left: 3px solid #007bff;
            font-size: 14px;
        }
        .sql-command {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>📧 Email Setup Diagnostic Tool</h1>
    <p>This tool checks your email configuration and identifies any issues preventing emails from being sent.</p>

    <?php
    $issues = [];
    $warnings = [];
    $passes = [];
    
    try {
        $db = getDB();
        
        // Check 1: Settings table exists
        echo "<h2>1️⃣ Database Configuration</h2>";
        
        $stmt = $db->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() == 0) {
            $issues[] = "Settings table does not exist in the database";
            echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>Settings table:</strong> NOT FOUND</div>";
        } else {
            $passes[] = "Settings table exists";
            echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>Settings table:</strong> EXISTS</div>";
        }
        
        // Check 2: Email settings exist
        echo "<h2>2️⃣ Email Settings in Database</h2>";
        
        $required_settings = [
            'email_enabled' => 'Email notifications enabled/disabled',
            'admin_email' => 'Admin email address (CRITICAL)',
            'email_from_name' => 'From name for emails',
            'email_from_address' => 'From email address',
            'smtp_enabled' => 'SMTP enabled/disabled',
        ];
        
        $optional_settings = [
            'smtp_host' => 'SMTP server hostname',
            'smtp_port' => 'SMTP port number',
            'smtp_username' => 'SMTP username',
            'smtp_password' => 'SMTP password',
            'smtp_encryption' => 'SMTP encryption type',
        ];
        
        $missing_settings = [];
        $empty_critical_settings = [];
        
        foreach ($required_settings as $key => $description) {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if (!$result) {
                $missing_settings[] = $key;
                echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>$key:</strong> NOT FOUND - $description</div>";
            } else {
                $value = $result['setting_value'];
                $display_value = $value;
                
                if (empty($value) && $key == 'admin_email') {
                    $empty_critical_settings[] = $key;
                    echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>$key:</strong> EMPTY (value: '$display_value') - $description</div>";
                } elseif (empty($value)) {
                    echo "<div class='check-item warn'><span class='icon'>⚠</span> <strong>$key:</strong> EMPTY (value: '$display_value') - $description</div>";
                } else {
                    if ($key == 'email_enabled' && $value != '1') {
                        $warnings[] = "Email notifications are disabled (email_enabled = '$value')";
                        echo "<div class='check-item warn'><span class='icon'>⚠</span> <strong>$key:</strong> DISABLED (value: '$display_value') - $description</div>";
                    } elseif ($key == 'admin_email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $issues[] = "Admin email address is invalid: $value";
                            echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>$key:</strong> INVALID EMAIL (value: '$display_value') - $description</div>";
                        } else {
                            $passes[] = "Admin email is set to: $value";
                            echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>$key:</strong> '$display_value' - $description</div>";
                        }
                    } else {
                        $passes[] = "$key is configured";
                        echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>$key:</strong> '$display_value' - $description</div>";
                    }
                }
            }
        }
        
        // Check optional SMTP settings
        echo "<h3>SMTP Configuration (Optional)</h3>";
        $smtp_enabled = getSetting('smtp_enabled', '0') == '1';
        
        if ($smtp_enabled) {
            echo "<div class='info status'>SMTP is enabled. Checking SMTP settings...</div>";
            
            foreach ($optional_settings as $key => $description) {
                $value = getSetting($key, '');
                $display_value = ($key == 'smtp_password' && !empty($value)) ? str_repeat('*', strlen($value)) : $value;
                
                if (empty($value)) {
                    $warnings[] = "SMTP is enabled but $key is empty";
                    echo "<div class='check-item warn'><span class='icon'>⚠</span> <strong>$key:</strong> EMPTY - $description</div>";
                } else {
                    echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>$key:</strong> '$display_value' - $description</div>";
                }
            }
        } else {
            echo "<div class='info status'>SMTP is disabled. Using PHP mail() function.</div>";
        }
        
        // Check 3: PHP mail configuration
        echo "<h2>3️⃣ PHP Mail Configuration</h2>";
        
        if (!$smtp_enabled) {
            if (function_exists('mail')) {
                $passes[] = "PHP mail() function is available";
                echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>mail() function:</strong> AVAILABLE</div>";
                
                $sendmail_path = ini_get('sendmail_path');
                if (empty($sendmail_path)) {
                    $warnings[] = "sendmail_path is not configured in php.ini";
                    echo "<div class='check-item warn'><span class='icon'>⚠</span> <strong>sendmail_path:</strong> NOT CONFIGURED</div>";
                    echo "<div class='solution'><strong>Note:</strong> PHP mail() may not work if sendmail is not configured on your server. Consider using SMTP instead.</div>";
                } else {
                    $passes[] = "sendmail_path is configured: $sendmail_path";
                    echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>sendmail_path:</strong> $sendmail_path</div>";
                }
            } else {
                $issues[] = "PHP mail() function is not available";
                echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>mail() function:</strong> NOT AVAILABLE</div>";
                echo "<div class='solution'><strong>Solution:</strong> Enable SMTP to send emails.</div>";
            }
        }
        
        // Check 4: Email functions exist
        echo "<h2>4️⃣ Email Functions</h2>";
        
        $functions_to_check = [
            'sendEmail' => 'Core email sending function',
            'sendEmailSMTP' => 'SMTP email sending function',
            'sendBookingNotification' => 'Booking notification function',
            'generateBookingEmailHTML' => 'Email HTML generator',
        ];
        
        foreach ($functions_to_check as $func => $description) {
            if (function_exists($func)) {
                $passes[] = "Function $func exists";
                echo "<div class='check-item pass'><span class='icon'>✓</span> <strong>$func():</strong> EXISTS - $description</div>";
            } else {
                $issues[] = "Function $func does not exist";
                echo "<div class='check-item fail'><span class='icon'>✗</span> <strong>$func():</strong> NOT FOUND - $description</div>";
            }
        }
        
        // Summary
        echo "<h2>📊 Summary</h2>";
        
        if (!empty($missing_settings) || !empty($empty_critical_settings)) {
            $issues[] = "Email settings are missing or incomplete in database";
        }
        
        $total_checks = count($passes) + count($warnings) + count($issues);
        echo "<div class='info status'>";
        echo "<strong>Total Checks:</strong> $total_checks<br>";
        echo "<strong>Passed:</strong> " . count($passes) . "<br>";
        echo "<strong>Warnings:</strong> " . count($warnings) . "<br>";
        echo "<strong>Errors:</strong> " . count($issues);
        echo "</div>";
        
        if (empty($issues) && empty($warnings)) {
            echo "<div class='success status'>";
            echo "<strong>✅ All checks passed!</strong><br>";
            echo "Your email system is properly configured. If you're still not receiving emails, check:";
            echo "<ul>";
            echo "<li>Spam/junk folders</li>";
            echo "<li>Server firewall settings (for SMTP)</li>";
            echo "<li>Email provider restrictions</li>";
            echo "<li>PHP error logs for runtime errors</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<h2>🔧 Issues Found & Solutions</h2>";
            
            if (!empty($missing_settings) || !empty($empty_critical_settings)) {
                echo "<div class='error status'>";
                echo "<strong>❌ Critical Issue: Email settings not configured</strong>";
                echo "</div>";
                
                echo "<h3>Solution 1: Run Database Migration</h3>";
                echo "<p>Run this SQL command in your database to add email settings:</p>";
                echo "<div class='sql-command'>";
                echo "mysql -u your_username -p venubooking < database/migrations/add_email_settings.sql";
                echo "</div>";
                
                echo "<p><strong>OR</strong> execute this SQL directly in phpMyAdmin or MySQL client:</p>";
                echo "<div class='sql-command'>";
                echo htmlspecialchars("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('email_enabled', '1', 'boolean'),
('email_from_name', 'Venue Booking System', 'text'),
('email_from_address', 'noreply@venubooking.com', 'text'),
('admin_email', 'your-email@example.com', 'text'),
('smtp_enabled', '0', 'boolean'),
('smtp_host', '', 'text'),
('smtp_port', '587', 'number'),
('smtp_username', '', 'text'),
('smtp_password', '', 'password'),
('smtp_encryption', 'tls', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;");
                echo "</div>";
                
                echo "<h3>Solution 2: Configure via Admin Panel</h3>";
                echo "<p>After running the migration:</p>";
                echo "<ol>";
                echo "<li>Login to Admin Panel</li>";
                echo "<li>Go to <strong>Settings → Email Settings</strong></li>";
                echo "<li>Set your admin email address</li>";
                echo "<li>Configure SMTP (recommended) or use PHP mail()</li>";
                echo "<li>Click 'Save Settings'</li>";
                echo "</ol>";
            }
            
            if (in_array('Email notifications are disabled (email_enabled = \'0\')', $warnings)) {
                echo "<div class='warning status'>";
                echo "<strong>⚠ Warning: Email notifications are disabled</strong>";
                echo "</div>";
                echo "<div class='solution'>";
                echo "<strong>Solution:</strong> Enable email notifications in Admin Panel → Settings → Email Settings";
                echo "</div>";
            }
            
            if (!empty($warnings) && $smtp_enabled) {
                echo "<div class='warning status'>";
                echo "<strong>⚠ Warning: SMTP is enabled but not fully configured</strong>";
                echo "</div>";
                echo "<div class='solution'>";
                echo "<strong>Solution:</strong> Either configure all SMTP settings or disable SMTP to use PHP mail() instead.";
                echo "</div>";
            }
        }
        
        // Next steps
        echo "<h2>📝 Next Steps</h2>";
        echo "<ol>";
        echo "<li><strong>Fix Issues:</strong> Address all errors listed above</li>";
        echo "<li><strong>Configure Settings:</strong> Go to Admin Panel → Settings → Email Settings</li>";
        echo "<li><strong>Test Emails:</strong> Create a test booking with a valid email address</li>";
        echo "<li><strong>Check Logs:</strong> Monitor PHP error logs for email-related errors</li>";
        echo "<li><strong>Verify Delivery:</strong> Check both admin and customer email inboxes (including spam folder)</li>";
        echo "</ol>";
        
        echo "<h2>📚 Documentation</h2>";
        echo "<p>For detailed setup instructions, see:</p>";
        echo "<ul>";
        echo "<li><code>EMAIL_NOTIFICATION_GUIDE.md</code> - Complete setup guide</li>";
        echo "<li><code>EMAIL_NOTIFICATION_COMPLETE.md</code> - Feature documentation</li>";
        echo "<li><code>EMAIL_VERIFICATION_CHECKLIST.md</code> - Testing checklist</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<div class='error status'>";
        echo "<strong>❌ Fatal Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
        echo "<div class='solution'>";
        echo "<strong>This usually means:</strong>";
        echo "<ul>";
        echo "<li>Database connection is not configured (check .env file)</li>";
        echo "<li>Database does not exist</li>";
        echo "<li>Database credentials are incorrect</li>";
        echo "</ul>";
        echo "</div>";
    }
    ?>

</body>
</html>
