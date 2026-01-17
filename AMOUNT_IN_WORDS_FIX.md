# Amount in Words Fix - Before and After Comparison

## Problem Statement
The "Amount in Words" field on the printed invoice was displaying the grand total amount instead of the remaining payable amount (balance due after deducting advance payment).

## Technical Details

### File Modified
- `/admin/bookings/view.php` (Line 367)

### Change Made
```php
// BEFORE:
<td class="payment-value-words"><?php echo numberToWords($booking['grand_total']); ?> Only</td>

// AFTER:
<td class="payment-value-words"><?php echo numberToWords($balance_due); ?> Only</td>
```

## Behavior Comparison

### Example Scenario 1: Advance Payment Received
**Booking Details:**
- Grand Total: NPR 100,000
- Advance Payment (25%): NPR 25,000
- Advance Status: Received ✓
- Balance Due: NPR 75,000

| Field | Before Fix | After Fix |
|-------|-----------|-----------|
| Balance Due Amount | NPR 75,000.00 | NPR 75,000.00 |
| **Amount in Words** | **One Lakh Only** ❌ | **Seventy Five Thousand Only** ✓ |

**Issue:** The amount in words showed "One Lakh" (grand total) instead of "Seventy Five Thousand" (balance due).

### Example Scenario 2: No Advance Payment
**Booking Details:**
- Grand Total: NPR 100,000
- Advance Payment (25%): NPR 25,000
- Advance Status: Not Received ✗
- Balance Due: NPR 100,000

| Field | Before Fix | After Fix |
|-------|-----------|-----------|
| Balance Due Amount | NPR 100,000.00 | NPR 100,000.00 |
| **Amount in Words** | **One Lakh Only** ✓ | **One Lakh Only** ✓ |

**Correct:** Both before and after show the same since balance due equals grand total.

### Example Scenario 3: Partial Payment Made
**Booking Details:**
- Grand Total: NPR 100,000
- Advance Payment (25%): NPR 25,000
- Advance Status: Received ✓
- Additional Payment: NPR 35,000
- Balance Due: NPR 40,000

| Field | Before Fix | After Fix |
|-------|-----------|-----------|
| Balance Due Amount | NPR 40,000.00 | NPR 40,000.00 |
| **Amount in Words** | **One Lakh Only** ❌ | **Forty Thousand Only** ✓ |

**Issue:** The amount in words showed "One Lakh" (grand total) instead of "Forty Thousand" (remaining balance).

## How Balance Due is Calculated

The `$balance_due` variable is calculated by the `calculatePaymentSummary()` function which:

1. Gets the grand total from the booking
2. Sums all verified payments
3. If advance payment is marked as received, deducts the advance amount
4. Returns: `balance_due = grand_total - total_paid - (advance if received)`
5. Ensures the result is never negative using `max(0, $due_amount)`

## Testing

Created `test-amount-in-words-fix.php` which verifies:
- ✓ No advance payment (full amount due)
- ✓ Advance payment received (balance after deduction)
- ✓ Partial payments made (remaining balance)
- ✓ Nearly paid (small balance)
- ✓ Fully paid (zero balance)

All tests pass successfully.

## Impact

This fix ensures that customers see the correct payable amount in words on their printed invoice, which is important for:
- Payment clarity
- Legal/accounting accuracy
- Customer trust
- Reducing confusion about payment obligations

## Security Review

- Code review completed: No critical issues
- Upstream validation confirmed in `calculatePaymentSummary()`
- CodeQL security check: No vulnerabilities detected
- PHP syntax check: No errors
