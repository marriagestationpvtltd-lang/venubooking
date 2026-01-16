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

2. **Edit Booking Page** (`admin/bookings/edit.php`)
   - Added checkbox "Advance Payment Received" in the booking form
   - Checkbox state reflects the current booking's advance payment status
   - Checkbox can be updated when editing a booking

3. **View Booking Page** (`admin/bookings/view.php`)
   - Shows visual indicator in the Payment Summary section:
     - Green alert if advance payment is received
     - Red alert if advance payment is not received
   - Invoice print section shows:
     - Advance amount when marked as received
     - NPR 0.00 when not received
   - Balance Due is calculated as: Grand Total - Advance Received (if checked)

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
2. Click "Edit" on any booking
3. Scroll down to the "Advance Payment Received" checkbox
4. Check the box if the customer has paid the advance
5. Click "Update Booking"

### Viewing Advance Payment Status
1. Go to Admin Panel → Bookings
2. Click "View" on any booking
3. In the "Payment Summary" section on the right, you'll see:
   - Advance Required amount
   - Advance Payment Received status (with amount or 0.00)

### Printing Bills/Invoices
When you print a booking bill:
- If "Advance Payment Received" is checked: Shows the calculated advance amount
- If "Advance Payment Received" is not checked: Shows NPR 0.00

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
    echo 'NPR 0.00';
}
```

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
