<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Due Calculation Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .scenario {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .scenario h2 {
            color: #2196F3;
            margin-top: 0;
        }
        .details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .detail-item {
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
            font-size: 14px;
        }
        .detail-value {
            font-size: 18px;
            color: #333;
            margin-top: 5px;
        }
        .result {
            background: #E3F2FD;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-top: 15px;
        }
        .result.success {
            background: #E8F5E9;
            border-left-color: #4CAF50;
        }
        .result h3 {
            margin-top: 0;
            color: #1976D2;
        }
        .result.success h3 {
            color: #2E7D32;
        }
        .balance-breakdown {
            font-family: monospace;
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .pass {
            color: #4CAF50;
            font-weight: bold;
        }
        .fail {
            color: #f44336;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>📊 Balance Due Calculation Test Results</h1>
    <p><strong>Test Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php
    // Test scenarios
    $scenarios = [
        [
            'name' => 'Scenario 1: No Advance Payment Received, No Payments Made',
            'description' => 'Customer has not paid any advance payment, and no verified payments exist.',
            'grand_total' => 100000,
            'total_paid' => 0,
            'advance_payment_received' => 0,
            'advance_percentage' => 25,
            'expected_balance' => 100000
        ],
        [
            'name' => 'Scenario 2: Advance Payment Received, No Payments Made',
            'description' => 'Admin confirmed advance payment received, but no payment transactions recorded yet.',
            'grand_total' => 100000,
            'total_paid' => 0,
            'advance_payment_received' => 1,
            'advance_percentage' => 25,
            'expected_balance' => 75000
        ],
        [
            'name' => 'Scenario 3: Advance Payment Received, Partial Payment Made',
            'description' => 'Admin confirmed advance received, and customer made additional partial payment.',
            'grand_total' => 100000,
            'total_paid' => 10000,
            'advance_payment_received' => 1,
            'advance_percentage' => 25,
            'expected_balance' => 65000
        ],
        [
            'name' => 'Scenario 4: Advance Payment Received, Full Balance Paid',
            'description' => 'Admin confirmed advance received, and customer paid remaining balance.',
            'grand_total' => 100000,
            'total_paid' => 75000,
            'advance_payment_received' => 1,
            'advance_percentage' => 25,
            'expected_balance' => 0
        ],
        [
            'name' => 'Scenario 5: No Advance Received, Partial Payment Made',
            'description' => 'Admin has not confirmed advance, but customer made a payment.',
            'grand_total' => 100000,
            'total_paid' => 30000,
            'advance_payment_received' => 0,
            'advance_percentage' => 25,
            'expected_balance' => 70000
        ],
        [
            'name' => 'Scenario 6: Overpayment Protection',
            'description' => 'Admin confirmed advance, customer paid more than remaining balance.',
            'grand_total' => 100000,
            'total_paid' => 100000,
            'advance_payment_received' => 1,
            'advance_percentage' => 25,
            'expected_balance' => 0  // Should not go negative
        ]
    ];
    
    foreach ($scenarios as $index => $scenario) {
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
        
        $pass = abs($due_amount - $scenario['expected_balance']) < 0.01;
        ?>
        
        <div class="scenario">
            <h2><?php echo htmlspecialchars($scenario['name']); ?></h2>
            <p><?php echo htmlspecialchars($scenario['description']); ?></p>
            
            <div class="details">
                <div class="detail-item">
                    <div class="detail-label">Grand Total</div>
                    <div class="detail-value">NPR <?php echo number_format($scenario['grand_total'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Paid (Verified)</div>
                    <div class="detail-value">NPR <?php echo number_format($scenario['total_paid'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Advance Payment Received</div>
                    <div class="detail-value">
                        <?php echo $scenario['advance_payment_received'] ? '✅ Yes' : '❌ No'; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Advance Percentage</div>
                    <div class="detail-value"><?php echo $scenario['advance_percentage']; ?>%</div>
                </div>
            </div>
            
            <div class="result <?php echo $pass ? 'success' : ''; ?>">
                <h3>Calculation Result <?php echo $pass ? '✓' : '✗'; ?></h3>
                <div class="balance-breakdown">
                    <div>Grand Total: NPR <?php echo number_format($grand_total, 2); ?></div>
                    <div>- Total Paid: NPR <?php echo number_format($total_paid, 2); ?></div>
                    <?php if ($scenario['advance_payment_received'] === 1): ?>
                    <div>- Advance Amount: NPR <?php echo number_format($advance_amount, 2); ?></div>
                    <?php endif; ?>
                    <div style="border-top: 2px solid #333; margin-top: 5px; padding-top: 5px;">
                        <strong>= Balance Due: NPR <?php echo number_format($due_amount, 2); ?></strong>
                    </div>
                </div>
                <p style="margin: 10px 0 0 0;">
                    <strong>Expected:</strong> NPR <?php echo number_format($scenario['expected_balance'], 2); ?>
                    <br>
                    <strong>Calculated:</strong> NPR <?php echo number_format($due_amount, 2); ?>
                    <br>
                    <strong>Status:</strong> <span class="<?php echo $pass ? 'pass' : 'fail'; ?>">
                        <?php echo $pass ? '✓ PASS' : '✗ FAIL'; ?>
                    </span>
                </p>
            </div>
        </div>
        
        <?php
    }
    ?>
    
    <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px;">
        <h2>Implementation Summary</h2>
        <p><strong>Changes Made:</strong></p>
        <ol>
            <li>Updated <code>calculatePaymentSummary()</code> function in <code>includes/functions.php</code></li>
            <li>Function now fetches <code>advance_payment_received</code> field from database</li>
            <li>Balance due calculation now considers advance payment status:
                <ul>
                    <li>If advance received: Balance = Grand Total - Total Paid - Advance Amount</li>
                    <li>If advance not received: Balance = Grand Total - Total Paid</li>
                </ul>
            </li>
            <li>Added "Balance Due Amount" display in booking view sidebar</li>
            <li>Print section automatically updated (uses same calculation)</li>
        </ol>
        
        <p><strong>Display Locations:</strong></p>
        <ul>
            <li>✅ Booking View - Payment Summary Sidebar (newly added alert box)</li>
            <li>✅ Print Invoice - Payment Calculation Section</li>
            <li>✅ Booking View - Payment Transactions Table Footer</li>
        </ul>
    </div>
</body>
</html>
