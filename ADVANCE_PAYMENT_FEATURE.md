# Advance Payment Received Feature

## Overview
This feature allows admins to mark whether the advance payment for a booking has been received. The invoice/bill will display the correct advance payment amount based on this setting.

## What Changed

### Database Changes
- Added `advance_payment_received` field to the `bookings` table (TINYINT(1), default: 0)
- Added index for faster queries on this field

### Admin Interface Changes
1. **Add Booking Page** (`admin/bookings/add.php`)
   - Added checkbox "Advance Payment Received" in the booking form
   - Checkbox is saved with the booking data

2. **View Booking Page** (`admin/bookings/view.php`)
   - Added interactive toggle in the "Quick Actions" section to mark advance payment as received/not received
   - Toggle form allows admins to quickly change the advance payment status
   - Shows visual indicator in the Payment Summary section:
     - Green alert if advance payment is received
     - Red alert if advance payment is not received
   - Invoice print section shows:
     - Advance amount when marked as received
     - formatCurrency(0) when not received
   - Balance Due is calculated as: Grand Total - Total Paid (actual payments made)

3. **Edit Booking Page** (`admin/bookings/edit.php`)
   - Allows editing of all booking details (hall, date, menus, services, etc.)
   - Does NOT include advance payment status (moved to view page for easier access)

## Installation

### For Fresh Installations
The field is already included in all database schema files:
- `database/schema.sql`
- `database/complete-database-setup.sql`
- `database/complete-setup.sql`
- `database/production-ready.sql`
- `database/production-shared-hosting.sql`

### For Existing Installations
Run the migration script:

```bash
./apply-advance-payment-migration.sh
```

Or manually run the SQL migration:

```bash
mysql -u username -p database_name < database/migrations/add_advance_payment_received.sql
```

## Usage

### Marking Advance Payment as Received
1. Go to Admin Panel → Bookings
2. Click "View" on any booking
3. In the "Quick Actions" section, you'll see "Advance Payment Status"
4. Check or uncheck the toggle switch as needed
5. Click "Save Status" button to update

### Viewing Advance Payment Status
1. Go to Admin Panel → Bookings
2. Click "View" on any booking
3. In the "Payment Summary" section on the right, you'll see:
   - Advance Required amount
   - Advance Payment Received status (with amount or 0.00)
4. In the "Quick Actions" section, you'll see the current status badge

### Printing Bills/Invoices
When you print a booking bill:
- If "Advance Payment Received" is checked: Shows the calculated advance amount
- If "Advance Payment Received" is not checked: Shows the currency equivalent of 0 (e.g., NPR 0.00)
- Balance Due is calculated based on actual payment transactions, not the checkbox status

## Technical Details

### Database Schema
```sql
ALTER TABLE bookings 
ADD COLUMN advance_payment_received TINYINT(1) DEFAULT 0 
COMMENT 'Whether advance payment has been received (0=No, 1=Yes)' 
AFTER payment_status;

ALTER TABLE bookings 
ADD INDEX idx_advance_payment_received (advance_payment_received);
```

### Logic
The advance payment amount is calculated using the `calculateAdvancePayment()` function based on the `advance_payment_percentage` setting (default: 25%).

The display logic:
```php
if (!empty($booking['advance_payment_received'])) {
    // Show calculated advance amount
    echo formatCurrency($advance['amount']);
} else {
    // Show 0.00
    echo formatCurrency(0);
}
```

The balance due is calculated from actual payments and advance payment status:
```php
$balance_due = $booking['grand_total'] - $total_paid;

// If advance payment is marked as received, subtract it from balance due
if ($booking['advance_payment_received'] === 1) {
    $balance_due -= $advance['amount'];
}
```

**Note**: As of the latest update, the balance due calculation now properly accounts for the advance payment when it's marked as received. This ensures that when the admin marks "Advance Payment Received", the balance due will correctly show:
- Balance Due = Grand Total - Total Paid - Advance Amount (when marked as received)

For example, if Grand Total is NPR 100,000 and Advance (25%) is NPR 25,000:
- When advance NOT marked as received: Balance Due = NPR 100,000 - Total Paid
- When advance IS marked as received: Balance Due = NPR 75,000 - Total Paid

## Benefits
- ✅ Accurate invoice display showing actual advance payment received
- ✅ Admin control over advance payment status
- ✅ Clear visual indicators in the admin panel
- ✅ Proper balance due calculation
- ✅ Professional invoice printouts

## Security
- The checkbox is only accessible in the admin panel
- Proper authentication and authorization checks are in place
- SQL injection prevention through prepared statements
