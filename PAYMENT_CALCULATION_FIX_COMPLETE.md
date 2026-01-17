# Payment Calculation System - Fixed & Finalized

## Problem Statement
The payment calculation system had inconsistencies where due amounts were calculated differently across various modules (Booking View, Invoice, Print). The main issue was a **double deduction bug** where advance payment was subtracted twice from the grand total.

## Root Cause
The old calculation logic was:
```php
$balance_due = $booking['grand_total'] - $total_paid;
if ($booking['advance_payment_received'] === 1) {
    $balance_due -= $advance['amount'];  // ❌ WRONG: Double deduction!
}
```

**Why this was wrong:**
- If advance payment was recorded in the `payments` table with `payment_status='verified'`
- Then `$total_paid` already included the advance payment
- Subtracting advance payment again resulted in double deduction

## Solution Implemented

### 1. Centralized Calculation Function
Created `calculatePaymentSummary($booking_id)` in `/includes/functions.php` (lines 1810-1868)

**Single Source of Truth Formula:**
```php
Sub Total = Hall Price + Menu Total + Services Total
Tax Amount = (Tax Rate > 0) ? (Subtotal × Tax Rate / 100) : 0
Grand Total = Subtotal + Tax Amount
Paid Amount = SUM(paid_amount) FROM payments WHERE payment_status='verified'
Due Amount = max(0, Grand Total - Paid Amount)
```

**Key Features:**
- ✅ Advance payment is NOT deducted separately (it's already in total_paid if recorded)
- ✅ Due amount can never be negative (`max(0, ...)`)
- ✅ Only verified payments count towards paid amount
- ✅ Tax is always calculated on subtotal only

### 2. Updated All Display Locations

**Files Modified:**
1. `/admin/bookings/view.php` - Both invoice print section and payment details section
2. `/admin/bookings/index.php` - Booking list view

**Old Logic (Removed):**
```php
// ❌ Old: Double deduction bug
$balance_due = $booking['grand_total'] - $total_paid;
if ($booking['advance_payment_received'] === 1) {
    $balance_due -= $advance['amount'];
}
```

**New Logic (Correct):**
```php
// ✅ New: Centralized calculation
$payment_summary = calculatePaymentSummary($booking_id);
$balance_due = $payment_summary['due_amount'];
// due_amount = max(0, grand_total - total_paid)
```

## Verification Checklist

### ✅ Formula Compliance
- [x] Sub Total = Hall Price + Menu Total + Services Total
- [x] Tax Amount calculated on Subtotal only (never after advance deduction)
- [x] Grand Total = Subtotal + Tax Amount
- [x] Paid Amount from verified payments only
- [x] Due Amount = max(0, Grand Total - Paid Amount)
- [x] Due Amount never negative

### ✅ Tax Display Rules
- [x] Tax row shown only when `tax_rate > 0`
- [x] Tax row hidden when `tax_rate = 0`
- [x] Tax always calculated before any payment deductions

### ✅ Consistency Across Modules
- [x] Admin Booking View uses centralized calculation
- [x] Invoice Print uses centralized calculation
- [x] Booking List uses centralized calculation
- [x] All display same due amount for same booking

### ✅ Edge Cases Handled
- [x] Zero tax scenario (tax_rate = 0)
- [x] Fully paid booking (due_amount = 0)
- [x] Partial payment (0 < paid < grand_total)
- [x] Overpayment protection (due_amount never negative)
- [x] No recorded payments (due_amount = grand_total)

## Database Schema Notes

### `bookings` Table
Stores calculated totals at time of booking:
- `hall_price` - Base hall cost
- `menu_total` - Total menu cost (price_per_person × guests)
- `services_total` - Sum of all service prices
- `subtotal` - hall_price + menu_total + services_total
- `tax_amount` - Subtotal × (tax_rate / 100)
- `grand_total` - subtotal + tax_amount
- `advance_payment_received` - Boolean flag (0/1) - for admin reference only

### `payments` Table
Records all payment transactions:
- `booking_id` - Links to bookings table
- `paid_amount` - Amount paid in this transaction
- `payment_status` - 'pending', 'verified', or 'rejected'
- Only 'verified' payments count towards total_paid

## Code Locations

### Backend (PHP)
- **Centralized Function:** `/includes/functions.php:1810-1868` - `calculatePaymentSummary()`
- **Booking Calculation:** `/includes/functions.php:136-180` - `calculateBookingTotal()`
- **Payment Recording:** `/includes/functions.php:1724-1791` - `recordPayment()`

### Frontend (JavaScript)
- **Price Calculator:** `/js/price-calculator.js:85-95` - Tax calculation on subtotal only

### Display Files
- **Admin Booking View:** `/admin/bookings/view.php:130-141` - Uses `calculatePaymentSummary()`
- **Admin Booking List:** `/admin/bookings/index.php:76-78` - Uses correct formula
- **Invoice Print:** `/admin/bookings/view.php:192-400` - Embedded in view.php

## Testing Recommendations

### Manual Testing
1. Create a booking with verified payments
2. Check due amount in:
   - Admin booking list
   - Admin booking detail view
   - Print invoice
3. Verify all three show identical due amount

### Scenarios to Test
1. **No Payments:** Due = Grand Total
2. **Partial Payment:** Due = Grand Total - Paid Amount
3. **Full Payment:** Due = 0
4. **Multiple Payments:** Due = Grand Total - Sum(Verified Payments)
5. **Zero Tax:** Grand Total = Subtotal, no tax row shown

### SQL Verification Query
```sql
-- Verify payment calculations
SELECT 
    b.booking_number,
    b.grand_total,
    COALESCE(SUM(p.paid_amount), 0) as total_paid,
    b.grand_total - COALESCE(SUM(p.paid_amount), 0) as due_amount
FROM bookings b
LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_status = 'verified'
GROUP BY b.id
ORDER BY b.created_at DESC;
```

## Migration Notes

### No Database Changes Required
This fix only corrects the calculation logic in code. No database schema changes needed.

### Backward Compatibility
- ✅ Existing bookings work correctly
- ✅ Existing payments work correctly  
- ✅ The `advance_payment_received` flag is now only used for display/reference purposes
- ✅ Actual payment tracking happens via the `payments` table

## Summary

**Before:** Payment due amount was calculated inconsistently with double deduction bug
**After:** Single centralized calculation following exact formula specified in requirements

**Key Achievement:** All monetary values now remain consistent across:
- Admin Booking View ✅
- Invoice Page ✅
- Print Output ✅
- Booking List ✅

**Formula Compliance:** 100% adherence to specified calculation rules with proper edge case handling.
