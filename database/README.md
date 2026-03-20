# Database Directory

This directory contains all database-related files for the Venue Booking System.

## 📁 Files

### Upgrading an Existing Database (Keep Your Data)

**`upgrade.sql`** - ⭐ **USE THIS TO ADD NEW FEATURES WITHOUT LOSING DATA**
- Runs safely on any existing database — **never drops or overwrites data**
- Creates any tables that are missing
- Adds any columns that are missing (detected via information_schema)
- Inserts essential reference rows (admin user, cities, settings, etc.) only when absent
- **Run this whenever you update the codebase to pick up new database features**

```bash
# Command line
mysql -u username -p database_name < database/upgrade.sql

# phpMyAdmin: Select your database → Import → choose upgrade.sql → Go
```

### For Production Deployment

**`production-shared-hosting.sql`** - ⭐ **RECOMMENDED FOR SHARED HOSTING (LIVE)**
- **CLEAN production database with NO sample data**
- Creates all 34 required tables (including service packages and planner)
- Includes default admin user (admin/Admin@123)
- Includes essential system settings only
- Includes placeholder payment methods (INACTIVE by default)
- Includes database triggers for data integrity
- **Safe to re-run** — uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`
- **Perfect for immediate live deployment!**

**`production-ready.sql`** - ⭐ **RECOMMENDED FOR VPS/DEDICATED**
- Production-ready database in ONE file
- Creates all 34 required tables (including service packages and planner)
- Includes default admin user (admin/Admin@123)
- Includes essential system settings only
- Includes placeholder payment methods (inactive by default)
- **NO sample data** - clean database ready for your real data
- **Safe to re-run** — uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`
- **Use this for production/live websites when you don't need test data!**

### For Development/Testing

**`complete-database-setup.sql`** - ⭐ **RECOMMENDED FOR DEVELOPMENT**
- Complete A-Z database implementation in ONE file
- Creates all 34 required tables (including service packages)
- Includes default admin user (admin/Admin@123)
- Loads all essential settings
- Includes sample data (venues, halls, menus, services, service packages)
- Contains test bookings #23 and #37
- **Safe to re-run** — uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`
- **Use this for local development and testing!**

### Reference Files

**`sample-data.sql`**
- Sample data only (for reference)
- Contains sample venues, halls, menus, bookings for testing

### Migration Files

**`migrations/`** directory contains:
- `add_payment_methods.sql` - Adds payment tracking tables
- `add_booking_payment_confirmation.sql` - Payment confirmation feature
- `add_company_settings.sql` - Company information settings
- `add_email_settings.sql` - Email configuration
- `add_invoice_content_settings.sql` - Invoice customization
- And other feature migrations

These are incremental updates if you already have a database.
For a one-stop upgrade, use **`upgrade.sql`** instead.

## 🚀 Quick Start

### Upgrading an Existing Database (No Data Loss)

```bash
# Safe upgrade — only adds what is missing, never removes anything
mysql -u username -p database_name < database/upgrade.sql
```

Or via phpMyAdmin: Select your database → Import → `upgrade.sql` → Go.

### For Production Deployment

**Command Line (VPS/Dedicated Server):**
```bash
# Create production database first
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS your_production_db;"
# Import production-ready SQL
mysql -u root -p your_production_db < database/production-ready.sql
```

**phpMyAdmin (Shared Hosting - RECOMMENDED):**
1. **Create Database** in cPanel:
   - Go to cPanel → MySQL Databases
   - Create new database (e.g., `username_venubooking`)
   - Create database user with strong password
   - Grant all privileges to the user on that database

2. **Import the SQL File**:
   - Open phpMyAdmin
   - **Select your database** from the left sidebar
   - Click "Import" tab
   - Choose File → `production-ready.sql`
   - Click "Go"

3. **Update .env file**:
   ```
   DB_NAME=your_database_name  # Use full name with prefix
   DB_USER=your_database_user
   DB_PASS=your_strong_password
   ```

4. **CRITICAL SECURITY STEPS**:
   - Login to admin panel at: `/admin/`
   - Default credentials: `admin` / `Admin@123`
   - **IMMEDIATELY change the admin password!**
   - Update company information in Settings
   - Configure payment methods before activating them

### For Development/Testing

**Use Automated Script:**
```bash
cd /path/to/venubooking
bash setup-database.sh  # Uses complete-database-setup.sql with sample data
```

**Or Import Manually:**
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS venubooking_dev;"
# Import with sample data for testing
mysql -u root -p venubooking_dev < database/complete-database-setup.sql
```

## ✅ What Gets Installed

### Tables (34)
- `cities` - Predefined city list for filtering
- `venues` - Venue information
- `venue_images` - Venue photos
- `halls` - Halls/rooms in venues
- `hall_images` - Hall photos
- `menus` - Food menu packages
- `menu_items` - Items in each menu
- `hall_menus` - Which menus are available for which halls
- `additional_services` - Extra add-on services (decoration, DJ, etc.)
- `service_categories` - Event type categories for service packages
- `service_packages` - Service packages per category (Wedding, Birthday, etc.)
- `service_package_features` - Feature bullet points per package
- `service_package_photos` - Photos per service package
- `customers` - Customer information
- `bookings` - Booking records
- `booking_menus` - Menus selected for bookings
- `booking_services` - Services selected for bookings
- `payment_methods` - Available payment methods
- `booking_payment_methods` - Payment methods for each booking
- `payments` - Payment transaction records
- `vendor_types` - Vendor type categories
- `vendors` - Vendors (photographers, decorators, etc.)
- `vendor_photos` - Vendor photos
- `booking_vendor_assignments` - Vendors assigned to bookings
- `shared_folders` - Folder-based photo/file sharing
- `shared_photos` - Photos/files in shared folders
- `event_plans` - Event planning records
- `plan_tasks` - Tasks for event plans
- `users` - Admin users
- `settings` - System settings (key-value pairs)
- `activity_logs` - User activity tracking
- `login_attempts` - Login attempt tracking for security
- `site_images` - Dynamic site images

### Essential Data (Production)
- **Admin User:** username: `admin`, password: `Admin@123`
- **Cities:** Nepal cities (Kathmandu, Pokhara, etc.)
- **Vendor Types:** Photographer, Videographer, Decorator, etc.
- **System Settings:** Company info, tax rate, currency, etc.
- **Payment Methods:** Bank Transfer, eSewa, Khalti, Cash (INACTIVE by default)

### Sample Data (Development Only)
In `complete-database-setup.sql`:
- **4 Venues:** Royal Palace, Garden View Hall, City Convention Center, Lakeside Resort
- **8 Halls:** Various halls with different capacities
- **5 Menus:** From Bronze (NPR 1,499) to Platinum (NPR 2,999) per person
- **8 Services:** Decoration, Photography, DJ, etc.
- **3 Service Categories:** विवाह (Wedding), पास्नी, व्रतबन्द
- **6 Service Packages:** Silver/Gold/Platinum Wedding, Pasni, Bratabandha packages
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
3. Try importing `upgrade.sql` — it adds only what is missing without touching your data

## 📚 Database Schema Diagram

```
cities
  └── venues
        └── venue_images (multi-photo)

halls (linked to venues)
  ├── hall_images (multi-photo)
  └── hall_menus → menus
                    └── menu_items

service_categories
  └── service_packages
        ├── service_package_features
        └── service_package_photos

vendor_types
  └── vendors
        └── vendor_photos

customers → bookings
              ├── halls
              ├── booking_menus → menus
              ├── booking_services → additional_services
              ├── booking_vendor_assignments → vendors
              ├── booking_payment_methods → payment_methods
              └── payments → payment_methods

users → activity_logs

settings (standalone)
site_images (standalone)
```

## 📚 Detailed Documentation

For comprehensive guides, see:

- **[PRODUCTION_DATABASE_GUIDE.md](PRODUCTION_DATABASE_GUIDE.md)** - Complete production deployment guide
- **[SQL_FILES_COMPARISON.md](SQL_FILES_COMPARISON.md)** - Detailed comparison of all SQL files to help you choose

### Quick Reference:

- **Production/Live Website?** → Use `production-ready.sql`
- **Development/Testing?** → Use `complete-database-setup.sql`
- **Need help choosing?** → Read [SQL_FILES_COMPARISON.md](SQL_FILES_COMPARISON.md)

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
- Booking numbers format: BK-YYYYMMDD-XXXX
- Sample data in complete-database-setup.sql uses 2026 dates

## 🆘 Need Help?

See the comprehensive guides:
- **[PRODUCTION_DATABASE_GUIDE.md](PRODUCTION_DATABASE_GUIDE.md)** - Step-by-step production setup
- **[SQL_FILES_COMPARISON.md](SQL_FILES_COMPARISON.md)** - Which SQL file should you use?
- `DATABASE_INSTALLATION_GUIDE.md` - Detailed installation instructions (project root)
- `QUICK_START_DATABASE.md` - Quick reference guide (project root)
- `README.md` - Main project documentation (project root)

## 🎯 Quick Decision Guide

**I already have a database and want to add new features without losing data:**
→ Use `upgrade.sql` — safe, idempotent, adds only what is missing

**I'm deploying to shared hosting (cPanel) for LIVE/PRODUCTION:**
→ Use `production-shared-hosting.sql` — clean database, no sample data

**I'm deploying to VPS/dedicated server:**
→ Use `production-ready.sql` + Read [PRODUCTION_DATABASE_GUIDE.md](PRODUCTION_DATABASE_GUIDE.md)
   (Clean database, no sample data)

**I'm setting up for local development/testing:**
→ Use `complete-database-setup.sql` (includes sample data for testing)

---

**Last Updated:** March 2026  
**Database Version:** 2.2 (Production-Ready Edition)

