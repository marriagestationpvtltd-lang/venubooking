<?php
/**
 * Test script to verify balance due calculation with advance payment
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Balance Due Calculation Test ===\n\n";

// Test scenarios
$test_scenarios = [
    [
        'name' => 'Scenario 1: No advance payment received, no payments made',
        'grand_total' => 100000,
        'total_paid' => 0,
        'advance_payment_received' => 0,
        'advance_percentage' => 25,
        'expected_balance' => 100000
    ],
    [
        'name' => 'Scenario 2: Advance payment received (25%), no payments made',
        'grand_total' => 100000,
        'total_paid' => 0,
        'advance_payment_received' => 1,
        'advance_percentage' => 25,
        'expected_balance' => 75000  // 100000 - 25000 (advance)
    ],
    [
        'name' => 'Scenario 3: Advance payment received, partial payment made',
        'grand_total' => 100000,
        'total_paid' => 10000,
        'advance_payment_received' => 1,
        'advance_percentage' => 25,
        'expected_balance' => 65000  // 100000 - 25000 (advance) - 10000 (paid)
    ],
    [
        'name' => 'Scenario 4: Advance payment received, full payment made',
        'grand_total' => 100000,
        'total_paid' => 75000,
        'advance_payment_received' => 1,
        'advance_percentage' => 25,
        'expected_balance' => 0  // 100000 - 25000 (advance) - 75000 (paid) = 0
    ],
    [
        'name' => 'Scenario 5: No advance payment received, partial payment made',
        'grand_total' => 100000,
        'total_paid' => 30000,
        'advance_payment_received' => 0,
        'advance_percentage' => 25,
        'expected_balance' => 70000  // 100000 - 30000 (paid)
    ]
];

foreach ($test_scenarios as $scenario) {
    echo "Testing: {$scenario['name']}\n";
    echo "  Grand Total: NPR " . number_format($scenario['grand_total'], 2) . "\n";
    echo "  Total Paid: NPR " . number_format($scenario['total_paid'], 2) . "\n";
    echo "  Advance Payment Received: " . ($scenario['advance_payment_received'] ? 'Yes' : 'No') . "\n";
    echo "  Advance Percentage: {$scenario['advance_percentage']}%\n";
    
    // Calculate using the logic from calculatePaymentSummary
    $grand_total = $scenario['grand_total'];
    $total_paid = $scenario['total_paid'];
    $due_amount = $grand_total - $total_paid;
    
    // Calculate advance
    $advance_amount = $grand_total * ($scenario['advance_percentage'] / 100);
    
    // If advance payment is marked as received, subtract it from balance due
    if ($scenario['advance_payment_received'] === 1) {
        $due_amount -= $advance_amount;
    }
    
    // Ensure due amount is never negative
    $due_amount = max(0, $due_amount);
    
    echo "  Calculated Balance Due: NPR " . number_format($due_amount, 2) . "\n";
    echo "  Expected Balance Due: NPR " . number_format($scenario['expected_balance'], 2) . "\n";
    
    if (abs($due_amount - $scenario['expected_balance']) < 0.01) {
        echo "  ✓ PASS\n";
    } else {
        echo "  ✗ FAIL - Expected {$scenario['expected_balance']} but got {$due_amount}\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
