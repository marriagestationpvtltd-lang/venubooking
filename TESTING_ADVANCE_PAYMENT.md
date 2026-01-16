# Advance Payment Feature - Testing Guide

## Overview
This feature allows admins to control whether advance payment appears as received on booking invoices.

## What to Test

### 1. Database Migration
**For Existing Installations:**
```bash
./apply-advance-payment-migration.sh
```
Or manually:
```bash
mysql -u username -p database_name < database/migrations/add_advance_payment_received.sql
```

**Verify:**
- Field `advance_payment_received` exists in `bookings` table
- Index `idx_advance_payment_received` exists

### 2. Create New Booking
1. Go to Admin Panel → Bookings → Add New Booking
2. Fill in all booking details
3. Look for "Advance Payment Received" checkbox (after Payment Status field)
4. Test Case A: Leave unchecked, create booking
5. Test Case B: Check the box, create booking

### 3. Edit Existing Booking
1. Go to Admin Panel → Bookings → View any booking → Edit
2. Look for "Advance Payment Received" checkbox
3. Test Case C: Uncheck if checked, save
4. Test Case D: Check if unchecked, save
5. Verify checkbox state persists after save

### 4. View Booking Details
**In the Payment Summary section (right sidebar):**

When **Advance Payment Received is CHECKED:**
- Should see GREEN alert box with:
  - Icon: check-circle
  - Text: "Advance Payment Received"
  - Amount: The calculated advance (e.g., NPR 5000)

When **Advance Payment Received is UNCHECKED:**
- Should see RED alert box with:
  - Icon: times-circle
  - Text: "Advance Payment Not Received"
  - Amount: NPR 0.00

### 5. Print Invoice
**Test the print functionality:**

1. View any booking
2. Click Print button
3. Check the "Payment Calculation Section" in the printout

**When Advance Payment Received is CHECKED:**
```
Advance Payment Required (25%):    NPR 5,000.00
Advance Payment Received:           NPR 5,000.00
Balance Due Amount:                 NPR 15,000.00
```

**When Advance Payment Received is UNCHECKED:**
```
Advance Payment Required (25%):    NPR 5,000.00
Advance Payment Received:           NPR 0.00
Balance Due Amount:                 NPR 15,000.00
```

**Important:** Balance Due should be calculated based on actual payment transactions, NOT the checkbox.

### 6. Currency Consistency
Verify that all currency displays use the currency set in Settings (default: NPR):
- Advance Payment Required
- Advance Payment Received
- Balance Due Amount
- All amounts should use the same currency format

### 7. Edge Cases to Test

**Test Case E: Advance Percentage Setting**
1. Go to Admin → Settings
2. Change `advance_payment_percentage` from 25% to 30%
3. Create a new booking for NPR 10,000
4. Check if advance shows NPR 3,000 (30%)

**Test Case F: Multiple Payments**
1. Create a booking
2. Add payment transaction for NPR 3,000
3. Check "Advance Payment Received"
4. View invoice - should show:
   - Advance Payment Received: NPR (calculated advance)
   - Total Paid: NPR 3,000 (in payment history)
   - Balance Due: Grand Total - 3,000

**Test Case G: Zero Grand Total**
1. Create a booking with zero amount (if possible)
2. Check advance calculation doesn't break

## Expected Behavior Summary

| Checkbox State | Invoice Display | Visual Indicator |
|---------------|-----------------|------------------|
| Unchecked     | NPR 0.00       | Red Alert        |
| Checked       | Advance Amount | Green Alert      |

## Important Notes

1. **The checkbox does NOT affect balance calculations**
   - Balance Due = Grand Total - Total Paid (from payment transactions)
   - The checkbox only controls what's shown as "Advance Payment Received" on invoice

2. **Default Behavior**
   - New bookings: Unchecked (advance_payment_received = 0)
   - Shows NPR 0.00 on invoice

3. **Backward Compatibility**
   - Existing bookings will have advance_payment_received = 0
   - Will show NPR 0.00 on invoices until admin checks the box

## Common Issues to Watch For

❌ **Issue:** Checkbox doesn't persist after save
✅ **Solution:** Verify form is submitting correctly, check browser console for errors

❌ **Issue:** Currency shows wrong format
✅ **Solution:** Check Settings → Currency is set correctly

❌ **Issue:** Balance Due calculation is wrong
✅ **Solution:** Balance should be Grand Total - Total Paid (from payment transactions table)

## Success Criteria

✅ Checkbox appears in Add/Edit booking forms
✅ Checkbox state persists after save
✅ Visual indicators show correct colors (green/red)
✅ Invoice print shows correct advance amounts
✅ Currency formatting is consistent
✅ Balance calculations are accurate
✅ Migration runs without errors

## Rollback (If Needed)

If issues occur, rollback with:
```sql
ALTER TABLE bookings DROP COLUMN advance_payment_received;
ALTER TABLE bookings DROP INDEX idx_advance_payment_received;
```

Then restore old code from git.
