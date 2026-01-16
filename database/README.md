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

### Method 1: Use the Automated Script

```bash
cd /path/to/venubooking
bash setup-database.sh
```

### Method 2: Import Directly

```bash
mysql -u root -p < database/complete-database-setup.sql
```

### Method 3: phpMyAdmin

1. Open phpMyAdmin
2. Import → Choose File → `complete-database-setup.sql`
3. Click "Go"

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
USE venubooking;

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

### "Database already exists"
The script drops and recreates the database. Your old data will be lost. Backup first if needed.

### "Cannot connect to database"
Check your .env file:
```
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=root
DB_PASS=your_password
```

### "Missing tables"
Some PHP hosting environments don't allow DROP DATABASE. In this case:
1. Manually drop all tables in phpMyAdmin
2. Then import the SQL file

### "Foreign key constraint fails"
The script handles this automatically. Make sure you're running the complete file, not portions of it.

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
