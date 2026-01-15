# Database Setup and Fix for HTTP 500 Error (generate_pdf.php)

## Problem Description
HTTP 500 error occurs when accessing `generate_pdf.php?id=23` due to:
- Missing database tables
- Incorrect database configuration
- Missing booking record with ID=23

## Solutions

### Solution 1: Complete Fresh Database Setup
Use this if you're setting up the database for the first time or want to start fresh.

**⚠️ WARNING: This will DROP all existing tables and data!**

```bash
# Navigate to the database directory
cd /path/to/venubooking/database

# Run the complete setup script
mysql -u root -p < complete-setup.sql
```

Or if you have a specific database user:
```bash
mysql -u your_username -p venubooking < complete-setup.sql
```

This will:
- Create the `venubooking` database
- Create all required tables (14 tables)
- Insert default settings
- Insert sample data (venues, halls, menus, services)
- **Insert booking #23** with all required data
- Verify the setup

### Solution 2: Quick Fix for Missing Booking #23
Use this if you already have the database setup but are missing booking ID=23.

```bash
# Navigate to the database directory
cd /path/to/venubooking/database

# Run the fix script
mysql -u root -p < fix-booking-23.sql
```

This will:
- Add customer record (Uttam Acharya)
- Add booking #23 with all details
- Add related booking menus
- Add related booking services
- Verify the booking exists

### Solution 3: Manual Database Setup via phpMyAdmin
1. Open phpMyAdmin
2. Create database: `venubooking`
3. Select the database
4. Click on "Import" tab
5. Choose file: `complete-setup.sql`
6. Click "Go"

### Solution 4: Command-line One-liner
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS venubooking;" && mysql -u root -p venubooking < database/complete-setup.sql
```

## Verifying the Fix

### Test 1: Check Database Tables
```bash
mysql -u root -p -e "USE venubooking; SHOW TABLES;"
```

You should see 14 tables:
- activity_logs
- additional_services
- booking_menus
- booking_services
- bookings
- customers
- hall_images
- hall_menus
- halls
- menu_items
- menus
- settings
- site_images
- users
- venues

### Test 2: Check Booking #23
```bash
mysql -u root -p -e "USE venubooking; SELECT * FROM bookings WHERE id = 23;"
```

You should see booking #23 with:
- Booking Number: BK-20260125-0023
- Customer: Uttam Acharya
- Event Type: Wedding Reception
- Status: confirmed/paid

### Test 3: Test PDF Generation
Visit in your browser:
```
http://localhost/venubooking/generate_pdf.php?id=23
```

Or test from command line:
```bash
php generate_pdf.php "id=23"
```

The PDF should download successfully without HTTP 500 error.

## Configuration Check

### Verify .env File
Make sure you have a `.env` file in the root directory:

```bash
# Copy example if needed
cp .env.example .env

# Edit with your credentials
nano .env
```

Required settings in `.env`:
```
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=root
DB_PASS=your_password
```

### Verify Database Connection
Test the database connection:
```bash
php -r "require 'config/database.php'; require 'includes/db.php'; try { \$db = getDB(); echo 'Connection successful!\n'; } catch (Exception \$e) { echo 'Connection failed: ' . \$e->getMessage() . '\n'; }"
```

## Common Issues and Solutions

### Issue 1: "Database connection failed"
**Solution:** Check your `.env` file credentials and MySQL service status
```bash
sudo service mysql status
sudo service mysql start
```

### Issue 2: "Access denied for user"
**Solution:** Grant proper permissions
```bash
mysql -u root -p
GRANT ALL PRIVILEGES ON venubooking.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

### Issue 3: "Table doesn't exist"
**Solution:** Run the complete-setup.sql script
```bash
mysql -u root -p < database/complete-setup.sql
```

### Issue 4: "Booking not found"
**Solution:** Run the fix-booking-23.sql script
```bash
mysql -u root -p < database/fix-booking-23.sql
```

### Issue 5: "FPDF library not found"
**Solution:** Check if FPDF library exists
```bash
ls -l lib/fpdf.php
```

If missing, download from http://www.fpdf.org/ and place in `lib/` directory.

## Database Schema Summary

### Core Tables
- **venues** - Venue locations
- **halls** - Halls within venues
- **menus** - Food menu packages
- **menu_items** - Items within each menu
- **additional_services** - Extra services (decoration, photography, etc.)

### Booking Tables
- **customers** - Customer information
- **bookings** - Main booking records
- **booking_menus** - Selected menus for bookings
- **booking_services** - Selected services for bookings

### System Tables
- **users** - Admin panel users
- **settings** - System settings
- **site_images** - Dynamic image management
- **activity_logs** - Audit trail

## Production Deployment Notes

### Before Going Live
1. **Remove test data** (if desired):
   ```sql
   USE venubooking;
   TRUNCATE TABLE booking_services;
   TRUNCATE TABLE booking_menus;
   TRUNCATE TABLE bookings;
   TRUNCATE TABLE customers;
   ```

2. **Change default admin password**:
   - Login to admin panel: `/admin/`
   - Username: admin
   - Password: Admin@123
   - Change password in settings

3. **Update site settings**:
   - Go to Admin → Settings
   - Update site name, contact info, currency, tax rate

4. **Backup database regularly**:
   ```bash
   mysqldump -u root -p venubooking > backup_$(date +%Y%m%d).sql
   ```

## Support

If you continue to experience issues:
1. Check the error log: `error_log.txt` in root directory
2. Check PHP error logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
3. Enable PHP error display temporarily:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

## Files Included
- `complete-setup.sql` - Full database setup from scratch
- `fix-booking-23.sql` - Quick fix to add booking #23
- `schema.sql` - Database schema only (no data)
- `sample-data.sql` - Sample data only (requires schema first)

## Summary of Changes
This fix adds:
- ✅ Complete database schema (14 tables)
- ✅ Default admin user (admin/Admin@123)
- ✅ System settings (currency, tax, etc.)
- ✅ Sample venues, halls, menus, services
- ✅ **Booking #23** with complete data
- ✅ Related booking menus and services
- ✅ Customer record (Uttam Acharya)
