# Balance Due Amount Display Feature - Implementation Summary

## Overview
This feature fixes the balance due calculation to properly account for advance payments when the admin marks them as received. The balance due amount is now correctly displayed in both the booking view section and the print invoice section.

## Problem Statement
When the admin confirms that the advance payment has been received and then clicks on print, the remaining amount after deducting the advance payment should be displayed. The Balance Due Amount should be shown in:
1. Booking view section (Payment Summary sidebar)
2. Print invoice section (Payment Calculation section)

## Solution

### Changes Made

#### 1. Updated `calculatePaymentSummary()` Function
**File:** `includes/functions.php`

**Changes:**
- Added `advance_payment_received` field to the SQL query
- Modified balance calculation logic to deduct advance amount when marked as received
- Ensures balance is never negative

**Before:**
```php
$due_amount = max(0, $grand_total - $total_paid);
```

**After:**
```php
$due_amount = $grand_total - $total_paid;

// Calculate advance payment info for reference
$advance = calculateAdvancePayment($grand_total);

// If advance payment is marked as received, subtract it from balance due
if (isset($booking['advance_payment_received']) && $booking['advance_payment_received'] === 1) {
    $due_amount -= $advance['amount'];
}

// Ensure due amount is never negative
$due_amount = max(0, $due_amount);
```

#### 2. Added Balance Due Display in Booking View
**File:** `admin/bookings/view.php`

**Changes:**
- Added new alert box in the Payment Summary sidebar displaying the Balance Due Amount
- Shows contextual message: "(After advance deduction)" when advance is received, or "(Full amount)" when not received
- Displayed with red color for visibility and prominence

### Balance Calculation Logic

The balance due is calculated as follows:

**When advance payment is NOT received:**
```
Balance Due = Grand Total - Total Paid (from verified payment transactions)
```

**When advance payment IS received:**
```
Balance Due = Grand Total - Total Paid - Advance Amount
```

**Examples (with Grand Total = NPR 100,000, Advance = 25%):**

| Scenario | Advance Received | Total Paid | Balance Due |
|----------|------------------|------------|-------------|
| No advance, no payments | No | NPR 0 | NPR 100,000 |
| Advance received, no payments | Yes | NPR 0 | NPR 75,000 |
| Advance received, partial payment | Yes | NPR 10,000 | NPR 65,000 |
| Advance received, full balance paid | Yes | NPR 75,000 | NPR 0 |
| No advance, partial payment | No | NPR 30,000 | NPR 70,000 |

### Display Locations

The Balance Due Amount is now displayed in three locations:

1. **✅ Booking View - Payment Summary Sidebar** (newly added)
   - Located in the right sidebar under "Booking Overview"
   - Shows as an info alert box with clear label
   - Displays contextual message about advance status
   - Amount shown in red for emphasis

2. **✅ Print Invoice - Payment Calculation Section** (automatically updated)
   - Located in the print invoice template
   - Displayed in the payment calculation table
   - Label: "Balance Due Amount:"
   - Uses same calculation from `calculatePaymentSummary()`

3. **✅ Booking View - Payment Transactions Table Footer** (automatically updated)
   - Located at the bottom of the Payment Transactions table
   - Shows "Balance Due:" row
   - Uses same calculation from `calculatePaymentSummary()`

## Testing

All test scenarios pass successfully:

### Test Scenarios
1. ✓ No Advance Payment Received, No Payments Made
2. ✓ Advance Payment Received, No Payments Made
3. ✓ Advance Payment Received, Partial Payment Made
4. ✓ Advance Payment Received, Full Balance Paid
5. ✓ No Advance Received, Partial Payment Made
6. ✓ Overpayment Protection (balance never goes negative)

See screenshot: [Balance Due Calculation Test Results](https://github.com/user-attachments/assets/7b4a3e8f-1f66-424e-90a7-af995aea0ee1)

## Security Considerations
- No new security vulnerabilities introduced
- Uses existing security measures (prepared statements, input validation)
- Only accessible in admin panel with proper authentication
- No user input required for the calculation

## Benefits
- ✅ Accurate balance due calculation considering advance payment status
- ✅ Clear visibility of remaining amount to be paid
- ✅ Consistent display across all sections (view, print, transactions)
- ✅ Better financial tracking for admins
- ✅ Professional invoices with correct balance information

## Backward Compatibility
- Fully backward compatible
- Works with existing bookings (treats `advance_payment_received` as 0 if NULL)
- No database migration required (field already exists from previous implementation)

## Files Modified
1. `includes/functions.php` - Updated `calculatePaymentSummary()` function
2. `admin/bookings/view.php` - Added Balance Due Amount display in sidebar

## Related Documentation
- See `ADVANCE_PAYMENT_FEATURE.md` for advance payment feature documentation
- See `PAYMENT_CALCULATION_FIX_COMPLETE.md` for payment calculation details
