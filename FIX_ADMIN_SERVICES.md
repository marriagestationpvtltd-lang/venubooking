# Fix: Admin Services "Failed to add admin service" Error

## Problem
When trying to add a service from the admin booking page (`admin/bookings/view.php`), you get this error:
```
Failed to add admin service. Please try again.
```

The service is not saved in the database.

## Root Cause
The `booking_services` table is missing two required columns:
- `added_by` (ENUM: 'user' or 'admin') - Tracks who added the service
- `quantity` (INT) - Stores the quantity of the service

These columns were added in a migration but may not have been applied to your database.

## Solution

You have **THREE options** to fix this issue:

### Option 1: Use the Auto-Fix Script (Recommended - Easiest)

1. Access the fix script in your browser:
   ```
   http://yoursite.com/fix_admin_services.php
   ```

2. Click "Apply Fix Now" button

3. Wait for confirmation message

4. **IMPORTANT:** Delete `fix_admin_services.php` from your server after successful execution

### Option 2: Run SQL Migration Manually

1. Access your database via phpMyAdmin or command line

2. Select your venue booking database

3. Run the migration script:
   ```bash
   mysql -u username -p database_name < database/migrations/fix_admin_services_columns.sql
   ```

   **OR** in phpMyAdmin:
   - Go to SQL tab
   - Open `database/migrations/fix_admin_services_columns.sql`
   - Copy and paste the contents
   - Click "Go"

### Option 3: Fresh Database Setup (If you don't have important data)

If you're okay with resetting your database:

1. Backup your database (IMPORTANT!)
2. Drop all tables
3. Import the latest database setup:
   ```bash
   mysql -u username -p database_name < database/complete-database-setup.sql
   ```

## Verification

After applying the fix, verify it worked:

1. Go to any booking details page: `admin/bookings/view.php?id=<booking_id>`
2. Scroll to "Admin Added Services" section
3. Fill in the form:
   - Service Name: Test Service
   - Description: Testing fix
   - Quantity: 1
   - Price: 100
4. Click "Add Service"
5. You should see: "Admin service added successfully!"
6. The service should appear in the table above

## What the Fix Does

### Database Changes
```sql
-- Adds 'added_by' column
ALTER TABLE booking_services 
ADD COLUMN added_by ENUM('user', 'admin') DEFAULT 'user' 
AFTER category;

-- Adds 'quantity' column  
ALTER TABLE booking_services 
ADD COLUMN quantity INT DEFAULT 1 
AFTER added_by;

-- Creates index for performance
CREATE INDEX idx_booking_services_added_by ON booking_services(added_by);

-- Updates existing records with default values
UPDATE booking_services SET added_by = 'user', quantity = 1 
WHERE added_by IS NULL OR quantity IS NULL;
```

### Safety
- ✅ Does NOT delete any existing data
- ✅ Only adds new columns
- ✅ Sets safe default values for existing records
- ✅ Can be rolled back if needed

## Rollback Instructions

If you need to undo the changes:

```sql
DROP INDEX idx_booking_services_added_by ON booking_services;
ALTER TABLE booking_services DROP COLUMN quantity;
ALTER TABLE booking_services DROP COLUMN added_by;
```

**WARNING:** This will permanently delete all admin-added services data. Make a backup first!

## After Fixing

Once the database is fixed, admin users can:

1. ✅ Add custom services to existing bookings
2. ✅ Specify service name, description, quantity, and price
3. ✅ Delete admin-added services (user services cannot be deleted)
4. ✅ See services immediately in booking details
5. ✅ See services in total calculations
6. ✅ See services in printed invoices

## Features Enabled

### Admin Added Services vs User Services

| Feature | User Services | Admin Services |
|---------|--------------|----------------|
| Added during | Booking creation | After booking created |
| Added by | Customer | Admin only |
| Reference | Links to `additional_services` table | Custom (no reference) |
| Can be deleted | ❌ No | ✅ Yes |
| Has quantity | ✅ Yes | ✅ Yes |
| Has description | ✅ Yes | ✅ Yes |
| In calculations | ✅ Yes | ✅ Yes |
| In invoice | ✅ Yes | ✅ Yes |

### Example Usage Scenarios

**Scenario 1:** Customer booked a venue but forgot to add decoration
- Admin can add "Extra Decoration" service with custom price
- Service is marked as admin-added
- Shows up separately in the booking details
- Included in total amount calculation

**Scenario 2:** Last-minute service addition
- Event is tomorrow, customer calls to add valet parking
- Admin adds service directly from booking page
- No need to recreate the booking
- Total is automatically recalculated

**Scenario 3:** Custom one-time service
- Customer needs a special service not in the master list
- Admin can create it on the fly
- Service is specific to this booking only
- Can be edited or removed later

## Technical Details

### Code Flow

1. **Form Submission** (`admin/bookings/view.php`, line 1076-1113)
   ```php
   <form method="POST" action="">
       <input type="hidden" name="action" value="add_admin_service">
       <!-- Form fields -->
   </form>
   ```

2. **Request Handling** (`admin/bookings/view.php`, line 34-59)
   ```php
   if ($action === 'add_admin_service') {
       $service_id = addAdminService($booking_id, $service_name, ...);
   }
   ```

3. **Database Insert** (`includes/functions.php`, line 2020-2060)
   ```php
   function addAdminService($booking_id, $service_name, $description, $quantity, $price) {
       // Validates inputs
       // Inserts into booking_services with added_by='admin'
       // Recalculates booking totals
       // Returns service ID or false
   }
   ```

4. **Total Recalculation** (`includes/functions.php`, line 2112-2164)
   ```php
   function recalculateBookingTotals($booking_id) {
       // Sums all services (user + admin)
       // Updates booking totals
       // Updates tax and grand total
   }
   ```

### Database Schema

```sql
CREATE TABLE booking_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,           -- 0 for admin services
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    added_by ENUM('user', 'admin') DEFAULT 'user',  -- NEW
    quantity INT DEFAULT 1,                         -- NEW
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES additional_services(id),
    INDEX idx_booking_services_added_by (added_by)
);
```

## Troubleshooting

### Issue: Still getting error after applying fix

**Solution:**
1. Clear browser cache and reload page
2. Check if columns were actually added:
   ```sql
   SHOW COLUMNS FROM booking_services;
   ```
3. Enable error reporting to see detailed errors:
   ```php
   // Add to top of admin/bookings/view.php temporarily
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

### Issue: Permission denied error

**Solution:**
Make sure your database user has ALTER TABLE permissions:
```sql
GRANT ALTER ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

### Issue: Services not showing in total

**Solution:**
The recalculation should happen automatically, but you can manually trigger it:
```sql
-- Get booking ID
SELECT id FROM bookings WHERE booking_number = 'BK-XXXXXXXX-XXXX';

-- Recalculate (replace ? with booking_id)
UPDATE bookings 
SET services_total = (
    SELECT COALESCE(SUM(price * quantity), 0) 
    FROM booking_services 
    WHERE booking_id = ?
)
WHERE id = ?;
```

### Issue: Can't delete user services

**Expected Behavior:** This is by design! User services (selected during booking) cannot be deleted from the admin panel. Only admin-added services can be deleted.

## Support

If you continue to experience issues after applying this fix:

1. Check PHP error logs: `/var/log/php/error.log` or via cPanel
2. Check MySQL error logs
3. Verify database user has proper permissions
4. Check that uploads directory is writable
5. Contact support with error logs

## Related Files

- `admin/bookings/view.php` - Booking details page with admin service form
- `includes/functions.php` - Contains `addAdminService()` and `deleteAdminService()` functions
- `database/migrations/add_admin_services_support.sql` - Original migration file
- `database/migrations/fix_admin_services_columns.sql` - Safe migration with checks
- `fix_admin_services.php` - Auto-fix web script (delete after use)

## Changelog

### 2026-01-17
- Created comprehensive fix for admin services issue
- Updated base database setup files with new columns
- Created auto-fix PHP script
- Created safe SQL migration with existence checks
- Updated all INSERT statements in sample data
