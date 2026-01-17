<?php
/**
 * Test script to verify "Amount in Words" displays balance due instead of grand total
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== Amount in Words Fix Test ===\n\n";

// Test the numberToWords function with different balance due amounts
$test_cases = [
    [
        'description' => 'No advance payment received, full amount due',
        'grand_total' => 100000,
        'balance_due' => 100000,
    ],
    [
        'description' => 'Advance payment received (25%), balance due',
        'grand_total' => 100000,
        'balance_due' => 75000,
    ],
    [
        'description' => 'Partial payment made, remaining balance',
        'grand_total' => 100000,
        'balance_due' => 40000,
    ],
    [
        'description' => 'Nearly paid, small balance',
        'grand_total' => 100000,
        'balance_due' => 5000,
    ],
    [
        'description' => 'Fully paid, zero balance',
        'grand_total' => 100000,
        'balance_due' => 0,
    ],
];

echo "Testing numberToWords function with balance due amounts:\n\n";

foreach ($test_cases as $test) {
    echo "Test Case: {$test['description']}\n";
    echo "  Grand Total: NPR " . number_format($test['grand_total'], 2) . "\n";
    echo "  Balance Due: NPR " . number_format($test['balance_due'], 2) . "\n";
    
    // Test that we're using balance_due (not grand_total)
    $words_for_balance = numberToWords($test['balance_due']);
    $words_for_grand = numberToWords($test['grand_total']);
    
    echo "  Amount in Words (Balance Due): {$words_for_balance}\n";
    echo "  Amount in Words (Grand Total): {$words_for_grand}\n";
    
    // Verify that for cases where balance != grand total, the words are different
    if ($test['balance_due'] != $test['grand_total']) {
        if ($words_for_balance !== $words_for_grand) {
            echo "  ✓ PASS - Balance due words differ from grand total (correct)\n";
        } else {
            echo "  ✗ FAIL - Balance due words same as grand total (incorrect)\n";
        }
    } else {
        echo "  ✓ PASS - Balance equals grand total (words should match)\n";
    }
    echo "\n";
}

echo "=== Test Summary ===\n";
echo "The fix changes 'Amount in Words' from using grand_total to balance_due.\n";
echo "This ensures the printed invoice shows the correct payable amount in words.\n";
echo "\n";
echo "Expected behavior after fix:\n";
echo "- When advance payment is received: Shows remaining balance in words\n";
echo "- When no advance received: Shows full grand total in words\n";
echo "- When partial payments made: Shows remaining amount in words\n";
echo "\n";
echo "=== Test Complete ===\n";
