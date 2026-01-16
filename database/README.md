# Database Directory

This directory contains all database-related files for the Venue Booking System.

## 📁 Files

### Main Setup File (Use This!)

**`complete-database-setup.sql`** - ⭐ **RECOMMENDED**
- Complete A-Z database implementation in ONE file
- Creates all 18 required tables
- Includes default admin user (admin/Admin@123)
- Loads all essential settings
- Includes sample data (venues, halls, menus, services)
- Contains test bookings #23 and #37
- **This is what you need to import!**

### Original Files (Reference Only)

**`schema.sql`**
- Base database schema only
- No sample data
- Use this if you want a clean start without sample data

**`sample-data.sql`**
- Sample data only (requires schema.sql first)
- Use after importing schema.sql

**`complete-setup.sql`** (DEPRECATED)
- Old complete setup file
- Missing payment-related tables
- Use `complete-database-setup.sql` instead

### Migration Files

**`migrations/`** directory contains:
- `add_payment_methods.sql` - Adds payment tracking tables
- `add_booking_payment_confirmation.sql` - Payment confirmation feature
- `add_company_settings.sql` - Company information settings
- `add_email_settings.sql` - Email configuration
- `add_invoice_content_settings.sql` - Invoice customization
- And other feature migrations

These are for incremental updates if you already have a database.

## 🚀 Quick Start

### Method 1: Use the Automated Script (Local Development)

```bash
cd /path/to/venubooking
bash setup-database.sh
```

**Note:** This script will:
- Read your database name from `.env` file
- Create the database if it doesn't exist
- Import the complete SQL file
- Verify the installation

This method requires CREATE DATABASE privileges.

### Method 2: Import Directly (Command Line)

**For local development with full permissions:**
```bash
# Create database first
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS your_database_name;"
# Import the SQL file
mysql -u root -p your_database_name < database/complete-database-setup.sql
```

**For shared hosting (cPanel, etc.):**
```bash
# Database must be created via cPanel first, then:
mysql -u your_username -p your_database_name < database/complete-database-setup.sql
```

### Method 3: phpMyAdmin (Recommended for Shared Hosting)

1. **Create Database** (if not already created):
   - Go to cPanel → MySQL Databases
   - Create a new database (e.g., `username_venubooking`)
   - Note the full database name including prefix

2. **Import the SQL File**:
   - Open phpMyAdmin
   - **Select your database** from the left sidebar
   - Click "Import" tab
   - Choose File → `complete-database-setup.sql`
   - Click "Go"

3. **Update .env file**:
   ```
   DB_NAME=your_database_name  # Use the full name including prefix
   DB_USER=your_database_user
   DB_PASS=your_database_password
   ```

## ✅ What Gets Installed

### Tables (18)
- `venues` - Venue information
- `halls` - Halls/rooms in venues
- `hall_images` - Hall photos
- `menus` - Food menu packages
- `menu_items` - Items in each menu
- `hall_menus` - Which menus are available for which halls
- `additional_services` - Extra services (decoration, DJ, etc.)
- `customers` - Customer information
- `bookings` - Booking records
- `booking_menus` - Menus selected for bookings
- `booking_services` - Services selected for bookings
- `payment_methods` - Available payment methods
- `booking_payment_methods` - Payment methods for each booking
- `payments` - Payment transaction records
- `users` - Admin users
- `settings` - System settings (key-value pairs)
- `activity_logs` - User activity tracking
- `site_images` - Dynamic site images

### Default Data
- **Admin User:** username: `admin`, password: `Admin@123`
- **4 Venues:** Royal Palace, Garden View Hall, City Convention Center, Lakeside Resort
- **8 Halls:** Various halls with different capacities
- **5 Menus:** From Bronze (NPR 1,499) to Platinum (NPR 2,999) per person
- **8 Services:** Decoration, Photography, DJ, etc.
- **4 Payment Methods:** Bank Transfer, eSewa, Khalti, Cash
- **7 Sample Customers**
- **4 Test Bookings:** Including booking #23 and #37

## 🔍 Verification

After installation, run:

```bash
bash verify-database.sh
```

This will check:
- ✅ All tables are created
- ✅ Sample data is loaded
- ✅ Test bookings exist
- ✅ Admin user is created
- ✅ Settings are configured

## 📋 Manual Verification

```sql
-- Make sure you're connected to your database first

-- Check tables
SHOW TABLES;
-- Should show 18 tables

-- Check bookings
SELECT * FROM bookings WHERE id IN (23, 37);
-- Should show 2 bookings

-- Check admin
SELECT username FROM users WHERE role = 'admin';
-- Should show: admin
```

## 🔧 Troubleshooting

### "Access denied to database" (Error #1044)

**This occurs on shared hosting when trying to create a database without proper permissions.**

**Solution:**
1. Create the database first via cPanel:
   - Go to cPanel → MySQL Databases
   - Create a new database (note the full name with prefix, e.g., `username_venubooking`)
   - Create a database user and grant all privileges to that database
2. In phpMyAdmin, select that database from the left sidebar
3. Then import the SQL file - it will only create tables, not the database
4. Update your `.env` file with the correct database name including prefix

### "Cannot connect to database"

Check your .env file:
```
DB_HOST=localhost
DB_NAME=your_database_name  # Use full name with prefix on shared hosting
DB_USER=your_database_user
DB_PASS=your_password
```

### "Missing tables"

If some tables are missing after import:
1. Make sure you selected the correct database before importing
2. Check for import errors in phpMyAdmin
3. Try importing again with the database selected

### "Foreign key constraint fails"

The script handles this automatically with `FOREIGN_KEY_CHECKS = 0`. If you see this error:
1. Make sure you're running the complete file in one go
2. Don't run partial scripts
3. Ensure all tables are being created in the correct order

## 📚 Database Schema Diagram

```
venues
  └── halls
      ├── hall_images
      └── hall_menus → menus
                        └── menu_items

customers → bookings
              ├── halls
              ├── booking_menus → menus
              ├── booking_services → additional_services
              ├── booking_payment_methods → payment_methods
              └── payments → payment_methods

users → activity_logs

settings (standalone)
site_images (standalone)
```

## 🔐 Security Notes

1. **Change Default Password**
   - Default: admin/Admin@123
   - Change immediately after first login
   - Go to: Admin Panel → Settings → Change Password

2. **Update Payment Methods**
   - Default methods have placeholder details
   - Update bank details and QR codes
   - Go to: Admin Panel → Payment Methods

3. **Configure Settings**
   - Update company information
   - Set correct tax rates
   - Configure email settings
   - Go to: Admin Panel → Settings

## 📝 Notes

- All prices are in NPR (Nepalese Rupees)
- Default tax rate: 13%
- Default advance payment: 30%
- All dates are in 2026 for sample data
- Booking numbers format: BK-YYYYMMDD-XXXX
- Sample data can be deleted after setup

## 🆘 Need Help?

See the comprehensive guides:
- `DATABASE_INSTALLATION_GUIDE.md` - Detailed installation instructions
- `QUICK_START_DATABASE.md` - Quick reference guide
- `README.md` - Main project documentation

---

**Last Updated:** January 2026
**Database Version:** 1.0 (Complete A-Z Implementation)
