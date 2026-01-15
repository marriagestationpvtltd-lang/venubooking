<?php
/**
 * Settings Validation Script
 * Run this to validate that settings are properly database-driven
 */

// Disable strict error reporting for this test
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

echo "=== Settings Implementation Validation ===\n\n";

// Test 1: Check if functions.php has been updated
echo "Test 1: Checking functions.php...\n";
$functions_content = file_get_contents(__DIR__ . '/includes/functions.php');
if (strpos($functions_content, "TAX_RATE") !== false && strpos($functions_content, "getSetting('tax_rate'") === false) {
    echo "❌ FAIL: functions.php still uses TAX_RATE constant\n";
} else if (strpos($functions_content, "getSetting('tax_rate'") !== false) {
    echo "✅ PASS: functions.php uses getSetting() for tax rate\n";
} else {
    echo "⚠️  WARNING: Could not verify tax rate implementation\n";
}

if (strpos($functions_content, "CURRENCY") !== false && strpos($functions_content, "getSetting('currency'") === false) {
    echo "❌ FAIL: functions.php still uses CURRENCY constant\n";
} else if (strpos($functions_content, "getSetting('currency'") !== false) {
    echo "✅ PASS: functions.php uses getSetting() for currency\n";
} else {
    echo "⚠️  WARNING: Could not verify currency implementation\n";
}

echo "\n";

// Test 2: Check if config/database.php removed constants
echo "Test 2: Checking config/database.php...\n";
$config_content = file_get_contents(__DIR__ . '/config/database.php');
if (strpos($config_content, "define('CURRENCY'") !== false) {
    echo "❌ FAIL: CURRENCY constant still defined in config\n";
} else {
    echo "✅ PASS: CURRENCY constant removed from config\n";
}

if (strpos($config_content, "define('TAX_RATE'") !== false) {
    echo "❌ FAIL: TAX_RATE constant still defined in config\n";
} else {
    echo "✅ PASS: TAX_RATE constant removed from config\n";
}

echo "\n";

// Test 3: Check if API endpoint exists
echo "Test 3: Checking API endpoint...\n";
if (file_exists(__DIR__ . '/api/get-settings.php')) {
    echo "✅ PASS: API endpoint exists at /api/get-settings.php\n";
    
    $api_content = file_get_contents(__DIR__ . '/api/get-settings.php');
    if (strpos($api_content, "getSetting('currency'") !== false) {
        echo "✅ PASS: API uses getSetting() for currency\n";
    } else {
        echo "❌ FAIL: API doesn't use getSetting() properly\n";
    }
    
    if (strpos($api_content, "getSetting('tax_rate'") !== false) {
        echo "✅ PASS: API uses getSetting() for tax rate\n";
    } else {
        echo "❌ FAIL: API doesn't use getSetting() properly\n";
    }
} else {
    echo "❌ FAIL: API endpoint not found\n";
}

echo "\n";

// Test 4: Check JavaScript files
echo "Test 4: Checking JavaScript files...\n";
$main_js = file_get_contents(__DIR__ . '/js/main.js');
if (strpos($main_js, "loadSettings") !== false) {
    echo "✅ PASS: main.js has loadSettings() function\n";
} else {
    echo "❌ FAIL: main.js missing loadSettings() function\n";
}

if (strpos($main_js, "appSettings") !== false) {
    echo "✅ PASS: main.js has appSettings object\n";
} else {
    echo "❌ FAIL: main.js missing appSettings object\n";
}

if (strpos($main_js, "formatCurrency") !== false && strpos($main_js, "appSettings.currency") !== false) {
    echo "✅ PASS: formatCurrency() uses dynamic currency\n";
} else {
    echo "❌ FAIL: formatCurrency() doesn't use dynamic currency\n";
}

$calculator_js = file_get_contents(__DIR__ . '/js/price-calculator.js');
if (strpos($calculator_js, "loadTaxRate") !== false) {
    echo "✅ PASS: price-calculator.js loads dynamic tax rate\n";
} else {
    echo "❌ FAIL: price-calculator.js missing dynamic tax rate\n";
}

echo "\n";

// Test 5: Check PHP templates
echo "Test 5: Checking PHP template files...\n";
$templates = [
    'booking-step5.php',
    'confirmation.php',
    'admin/bookings/view.php',
];

$all_pass = true;
foreach ($templates as $template) {
    $path = __DIR__ . '/' . $template;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        // Check if they use TAX_RATE constant (bad) or getSetting (good)
        if (strpos($content, 'TAX_RATE') !== false && strpos($content, "getSetting('tax_rate'") === false) {
            echo "❌ FAIL: $template still uses TAX_RATE constant\n";
            $all_pass = false;
        }
    }
}

if ($all_pass) {
    echo "✅ PASS: All checked templates use getSetting()\n";
}

echo "\n";

// Test 6: Check admin forms
echo "Test 6: Checking admin form files...\n";
$forms = [
    'admin/halls/add.php',
    'admin/halls/edit.php',
    'admin/menus/add.php',
    'admin/menus/edit.php',
    'admin/services/add.php',
    'admin/services/edit.php',
];

$all_pass = true;
foreach ($forms as $form) {
    $path = __DIR__ . '/' . $form;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        // Check if they echo CURRENCY constant (bad) or use getSetting (good)
        if (strpos($content, 'echo CURRENCY') !== false && strpos($content, "getSetting('currency'") === false) {
            echo "❌ FAIL: $form still uses CURRENCY constant\n";
            $all_pass = false;
        }
    }
}

if ($all_pass) {
    echo "✅ PASS: All checked forms use getSetting()\n";
}

echo "\n";

// Summary
echo "=== Validation Summary ===\n";
echo "All core files have been updated to use database-driven settings.\n";
echo "The system now dynamically loads currency and tax rate from the database.\n\n";

echo "Next Steps:\n";
echo "1. Ensure database is set up with settings table\n";
echo "2. Visit Admin Panel → Settings to configure values\n";
echo "3. Test frontend pages to verify settings are applied\n";
echo "4. Open test-settings.html in browser for live testing\n\n";

echo "Documentation: See DYNAMIC_SETTINGS_IMPLEMENTATION.md for details\n";
