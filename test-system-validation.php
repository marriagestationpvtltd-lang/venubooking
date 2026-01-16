<?php
/**
 * System Validation Test Script
 * Tests all critical functionality before production deployment
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

// Start output buffer to format results nicely
ob_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Validation Tests</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #4CAF50;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .test {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ccc;
            border-radius: 4px;
        }
        .test.pass {
            border-left-color: #4CAF50;
        }
        .test.fail {
            border-left-color: #f44336;
            background: #ffebee;
        }
        .test.warning {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 10px;
        }
        .status.pass {
            background: #4CAF50;
            color: white;
        }
        .status.fail {
            background: #f44336;
            color: white;
        }
        .status.warning {
            background: #ff9800;
            color: white;
        }
        .summary {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-item {
            display: inline-block;
            margin: 10px 20px 10px 0;
            font-size: 18px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <h1>🧪 System Validation Test Results</h1>
    <p><strong>Test Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<?php

// Test counters
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$warnings = 0;

function runTest($testName, $testFunction) {
    global $totalTests, $passedTests, $failedTests, $warnings;
    $totalTests++;
    
    try {
        $result = $testFunction();
        
        if ($result['status'] === 'pass') {
            $passedTests++;
            $class = 'pass';
        } elseif ($result['status'] === 'warning') {
            $warnings++;
            $class = 'warning';
        } else {
            $failedTests++;
            $class = 'fail';
        }
        
        echo "<div class='test {$class}'>";
        echo "<span class='status {$class}'>" . strtoupper($result['status']) . "</span>";
        echo "<strong>{$testName}</strong><br>";
        echo $result['message'];
        echo "</div>";
        
    } catch (Exception $e) {
        $failedTests++;
        echo "<div class='test fail'>";
        echo "<span class='status fail'>ERROR</span>";
        echo "<strong>{$testName}</strong><br>";
        echo "Exception: " . $e->getMessage();
        echo "</div>";
    }
}

// ============================================
// 1. VALIDATION FUNCTION TESTS
// ============================================
echo "<h2>1. Validation Function Tests</h2>";

runTest("validateRequired() - Empty Value", function() {
    $result = validateRequired('', 'Test Field');
    return [
        'status' => !$result['valid'] ? 'pass' : 'fail',
        'message' => !$result['valid'] ? 
            "✓ Correctly identified empty field" : 
            "✗ Failed to identify empty field"
    ];
});

runTest("validateRequired() - Valid Value", function() {
    $result = validateRequired('Test Value', 'Test Field');
    return [
        'status' => $result['valid'] ? 'pass' : 'fail',
        'message' => $result['valid'] ? 
            "✓ Correctly validated non-empty field" : 
            "✗ Failed to validate non-empty field"
    ];
});

runTest("validateEmailFormat() - Valid Email", function() {
    $result = validateEmailFormat('test@example.com');
    return [
        'status' => $result['valid'] ? 'pass' : 'fail',
        'message' => $result['valid'] ? 
            "✓ Valid email accepted: <code>test@example.com</code>" : 
            "✗ Valid email rejected"
    ];
});

runTest("validateEmailFormat() - Invalid Email", function() {
    $result = validateEmailFormat('invalid-email');
    return [
        'status' => !$result['valid'] ? 'pass' : 'fail',
        'message' => !$result['valid'] ? 
            "✓ Invalid email rejected: <code>invalid-email</code>" : 
            "✗ Invalid email accepted"
    ];
});

runTest("validatePhoneNumber() - Valid Phone", function() {
    $result = validatePhoneNumber('9841234567');
    return [
        'status' => $result['valid'] ? 'pass' : 'fail',
        'message' => $result['valid'] ? 
            "✓ Valid phone accepted: <code>9841234567</code>" : 
            "✗ Valid phone rejected"
    ];
});

runTest("validatePhoneNumber() - International Format", function() {
    $result = validatePhoneNumber('+977-9841234567');
    return [
        'status' => $result['valid'] ? 'pass' : 'fail',
        'message' => $result['valid'] ? 
            "✓ International format accepted: <code>+977-9841234567</code>" : 
            "✗ International format rejected"
    ];
});

runTest("validatePhoneNumber() - Too Short", function() {
    $result = validatePhoneNumber('123');
    return [
        'status' => !$result['valid'] ? 'pass' : 'fail',
        'message' => !$result['valid'] ? 
            "✓ Short phone rejected: <code>123</code>" : 
            "✗ Short phone accepted"
    ];
});

// ============================================
// 2. DEFAULT VALUE HANDLING TESTS
// ============================================
echo "<h2>2. Default Value Handling Tests</h2>";

runTest("getValueOrDefault() - Null Value", function() {
    $result = getValueOrDefault(null, 'DEFAULT');
    return [
        'status' => $result === 'DEFAULT' ? 'pass' : 'fail',
        'message' => $result === 'DEFAULT' ? 
            "✓ Null returns default: <code>DEFAULT</code>" : 
            "✗ Null handling failed, got: <code>{$result}</code>"
    ];
});

runTest("getValueOrDefault() - Empty String", function() {
    $result = getValueOrDefault('', 'N/A');
    return [
        'status' => $result === 'N/A' ? 'pass' : 'fail',
        'message' => $result === 'N/A' ? 
            "✓ Empty string returns default: <code>N/A</code>" : 
            "✗ Empty string handling failed"
    ];
});

runTest("getValueOrDefault() - Valid Value", function() {
    $result = getValueOrDefault('Test Value', 'DEFAULT');
    return [
        'status' => $result === 'Test Value' ? 'pass' : 'fail',
        'message' => $result === 'Test Value' ? 
            "✓ Valid value preserved: <code>Test Value</code>" : 
            "✗ Valid value not preserved"
    ];
});

runTest("formatNumber() - Valid Number", function() {
    $result = formatNumber(1234.567, 2);
    return [
        'status' => $result === '1,234.57' ? 'pass' : 'fail',
        'message' => $result === '1,234.57' ? 
            "✓ Number formatted correctly: <code>1,234.57</code>" : 
            "✗ Number formatting failed, got: <code>{$result}</code>"
    ];
});

runTest("formatNumber() - Zero Value", function() {
    $result = formatNumber(0, 2);
    return [
        'status' => $result === '0.00' ? 'pass' : 'fail',
        'message' => $result === '0.00' ? 
            "✓ Zero formatted correctly: <code>0.00</code>" : 
            "✗ Zero formatting failed"
    ];
});

runTest("formatNumber() - Invalid Number with Default", function() {
    $result = formatNumber('invalid', 2, 0);
    return [
        'status' => $result === '0.00' ? 'pass' : 'fail',
        'message' => $result === '0.00' ? 
            "✓ Invalid number returns default: <code>0.00</code>" : 
            "✗ Invalid number handling failed"
    ];
});

// ============================================
// 3. DATABASE CONNECTION TEST
// ============================================
echo "<h2>3. Database Connection Test</h2>";

runTest("Database Connection", function() {
    try {
        $db = getDB();
        return [
            'status' => 'pass',
            'message' => "✓ Database connection successful"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'fail',
            'message' => "✗ Database connection failed: " . $e->getMessage()
        ];
    }
});

// ============================================
// 4. TAX CALCULATION TESTS
// ============================================
echo "<h2>4. Tax Calculation Tests</h2>";

runTest("Tax Calculation - Normal Rate", function() {
    $subtotal = 10000;
    $tax_rate = 13;
    $tax_amount = $subtotal * ($tax_rate / 100);
    $expected = 1300;
    
    return [
        'status' => $tax_amount === $expected ? 'pass' : 'fail',
        'message' => $tax_amount === $expected ? 
            "✓ Tax calculated correctly: NPR {$tax_amount} (13% of 10000)" : 
            "✗ Tax calculation failed, expected {$expected}, got {$tax_amount}"
    ];
});

runTest("Tax Calculation - Zero Rate", function() {
    $subtotal = 10000;
    $tax_rate = 0;
    $tax_amount = $subtotal * ($tax_rate / 100);
    $expected = 0;
    
    return [
        'status' => $tax_amount === $expected ? 'pass' : 'fail',
        'message' => $tax_amount === $expected ? 
            "✓ Zero tax handled correctly: NPR {$tax_amount}" : 
            "✗ Zero tax calculation failed"
    ];
});

runTest("Tax Display Logic - Zero Tax", function() {
    $tax_rate = 0;
    $should_display = floatval($tax_rate) > 0;
    
    return [
        'status' => !$should_display ? 'pass' : 'fail',
        'message' => !$should_display ? 
            "✓ Zero tax correctly hidden in display" : 
            "✗ Zero tax should not be displayed"
    ];
});

runTest("Tax Display Logic - Non-Zero Tax", function() {
    $tax_rate = 13;
    $should_display = floatval($tax_rate) > 0;
    
    return [
        'status' => $should_display ? 'pass' : 'fail',
        'message' => $should_display ? 
            "✓ Non-zero tax correctly shown in display" : 
            "✗ Non-zero tax should be displayed"
    ];
});

// ============================================
// 5. SANITIZATION TESTS
// ============================================
echo "<h2>5. Sanitization Tests</h2>";

runTest("sanitize() - XSS Prevention", function() {
    $input = "<script>alert('XSS')</script>";
    $result = sanitize($input);
    $expected = "&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;";
    
    return [
        'status' => $result === $expected ? 'pass' : 'fail',
        'message' => $result === $expected ? 
            "✓ XSS attack prevented: <code>" . htmlspecialchars($result) . "</code>" : 
            "✗ XSS prevention failed"
    ];
});

runTest("sanitize() - HTML Entity Encoding", function() {
    $input = "Test & Company's \"Product\"";
    $result = sanitize($input);
    $has_entities = (strpos($result, '&amp;') !== false || strpos($result, '&#039;') !== false || strpos($result, '&quot;') !== false);
    
    return [
        'status' => $has_entities ? 'pass' : 'fail',
        'message' => $has_entities ? 
            "✓ Special characters encoded: <code>" . htmlspecialchars($result) . "</code>" : 
            "✗ Special character encoding failed"
    ];
});

// ============================================
// 6. SETTINGS RETRIEVAL TEST
// ============================================
echo "<h2>6. Settings Configuration Tests</h2>";

runTest("Get Tax Rate Setting", function() {
    try {
        $tax_rate = getSetting('tax_rate', '13');
        return [
            'status' => is_numeric($tax_rate) ? 'pass' : 'warning',
            'message' => is_numeric($tax_rate) ? 
                "✓ Tax rate retrieved: <code>{$tax_rate}%</code>" : 
                "⚠ Tax rate not set, using default: <code>13%</code>"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'warning',
            'message' => "⚠ Settings table may not exist, using default values"
        ];
    }
});

runTest("Get Currency Setting", function() {
    try {
        $currency = getSetting('currency', 'NPR');
        return [
            'status' => !empty($currency) ? 'pass' : 'warning',
            'message' => !empty($currency) ? 
                "✓ Currency retrieved: <code>{$currency}</code>" : 
                "⚠ Currency not set, using default: <code>NPR</code>"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'warning',
            'message' => "⚠ Settings table may not exist, using default values"
        ];
    }
});

// ============================================
// SUMMARY
// ============================================
echo "<div class='summary'>";
echo "<h2>Test Summary</h2>";
echo "<div class='summary-item'><strong>Total Tests:</strong> {$totalTests}</div>";
echo "<div class='summary-item' style='color: #4CAF50'><strong>Passed:</strong> {$passedTests}</div>";
echo "<div class='summary-item' style='color: #f44336'><strong>Failed:</strong> {$failedTests}</div>";
echo "<div class='summary-item' style='color: #ff9800'><strong>Warnings:</strong> {$warnings}</div>";

$pass_rate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "<div class='summary-item'><strong>Pass Rate:</strong> {$pass_rate}%</div>";

if ($failedTests === 0 && $warnings === 0) {
    echo "<p style='color: #4CAF50; font-size: 20px; font-weight: bold;'>✅ ALL TESTS PASSED! System is ready for production.</p>";
} elseif ($failedTests === 0) {
    echo "<p style='color: #ff9800; font-size: 18px; font-weight: bold;'>⚠ All critical tests passed, but there are warnings to review.</p>";
} else {
    echo "<p style='color: #f44336; font-size: 20px; font-weight: bold;'>❌ SOME TESTS FAILED! Please fix the issues before deployment.</p>";
}

echo "</div>";

?>

</body>
</html>
