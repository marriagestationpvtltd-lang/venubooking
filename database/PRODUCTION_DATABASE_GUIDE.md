# Production Database Setup Guide

## 📋 Overview

This guide helps you set up the Venue Booking System database for **production/live deployment**. The `production-ready.sql` file is specifically designed for production environments with no sample data.

## 🎯 What's Included in production-ready.sql

### ✅ Included (Essential for Production)
- **All 18 database tables** with proper structure and relationships
- **Default admin user** (username: `admin`, password: `Admin@123`)
- **Essential system settings** (site name, currency, tax rate, etc.)
- **Placeholder payment methods** (inactive by default - must be configured)
- **Empty tables** ready for your real data

### ❌ NOT Included (Sample/Test Data)
- ❌ No sample venues or halls
- ❌ No sample menus or menu items
- ❌ No sample services
- ❌ No sample customers
- ❌ No test bookings
- ❌ No sample transactions

This ensures a clean production database without any test/demo data.

## 🚀 Quick Start for Production

### Step 1: Create Database

#### Option A: cPanel (Shared Hosting)
1. Login to cPanel
2. Go to **MySQL Databases**
3. Create new database (e.g., `username_venubooking`)
4. Create database user with strong password
5. Add user to database with **ALL PRIVILEGES**
6. Note down the full database name (including prefix)

#### Option B: Command Line (VPS/Dedicated)
```bash
mysql -u root -p
CREATE DATABASE venubooking_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON venubooking_prod.* TO 'venubooking_user'@'localhost' IDENTIFIED BY 'your_strong_password_here';
FLUSH PRIVILEGES;
EXIT;
```

### Step 2: Import production-ready.sql

#### Option A: phpMyAdmin (Recommended for Shared Hosting)
1. Open phpMyAdmin
2. **Select your database** from left sidebar (very important!)
3. Click **Import** tab
4. Click **Choose File** → Select `production-ready.sql`
5. Click **Go** button at bottom
6. Wait for import to complete (should take 5-10 seconds)
7. Verify success message appears

#### Option B: Command Line
```bash
# Navigate to your project directory
cd /path/to/venubooking

# Import the SQL file
mysql -u venubooking_user -p venubooking_prod < database/production-ready.sql
```

You will see output confirming the setup is complete with admin credentials.

### Step 3: Configure Environment File

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` with your database credentials:
   ```env
   DB_HOST=localhost
   DB_NAME=username_venubooking  # Your full database name with prefix
   DB_USER=username_dbuser        # Your database user
   DB_PASS=your_strong_password   # Your database password
   ```

3. Set proper permissions:
   ```bash
   chmod 600 .env  # Make it readable only by owner
   ```

### Step 4: Critical Security Steps

⚠️ **IMPORTANT: Do these immediately after installation!**

1. **Login to Admin Panel:**
   - URL: `https://yourdomain.com/admin/`
   - Username: `admin`
   - Password: `Admin@123`

2. **Change Admin Password** (CRITICAL!):
   - Go to Admin Panel → Profile/Settings
   - Change password to a strong password
   - Use at least 12 characters with mix of upper/lower/numbers/symbols

3. **Update Company Information:**
   - Go to Admin Panel → Settings
   - Update:
     - Company name
     - Company address
     - Contact email
     - Contact phone
     - Site name

4. **Configure Payment Methods:**
   - Go to Admin Panel → Payment Methods
   - Edit each payment method
   - Update bank details/account numbers
   - Upload QR codes for digital wallets
   - **Only activate after details are correct**

5. **Configure Email Settings** (if using):
   - Go to Admin Panel → Settings → Email
   - Configure SMTP settings
   - Test email functionality

### Step 5: Add Your Business Data

Now add your actual business data through the admin panel:

1. **Add Venues:**
   - Admin Panel → Venues → Add New
   - Add your venue locations

2. **Add Halls:**
   - Admin Panel → Halls → Add New
   - Add halls/rooms for each venue

3. **Add Menus:**
   - Admin Panel → Menus → Add New
   - Add food/beverage packages
   - Add menu items for each package

4. **Add Services:**
   - Admin Panel → Services → Add New
   - Add additional services (decoration, photography, etc.)

### Step 6: Test the System

1. **Test Booking Flow:**
   - Go to your website frontend
   - Complete a test booking
   - Verify all calculations are correct

2. **Test Admin Functions:**
   - View bookings in admin panel
   - Update booking status
   - Test payment confirmation

3. **Test Email Notifications:**
   - Complete a booking
   - Verify confirmation email is sent

## 📊 Database Structure

The production database includes these 18 tables:

| Table Name | Purpose |
|------------|---------|
| `venues` | Venue locations |
| `halls` | Halls/rooms in venues |
| `hall_images` | Hall photo gallery |
| `menus` | Food/beverage packages |
| `menu_items` | Items in each menu |
| `hall_menus` | Hall-menu relationships |
| `additional_services` | Extra services offered |
| `customers` | Customer information |
| `bookings` | Booking records |
| `booking_menus` | Menus selected for bookings |
| `booking_services` | Services selected for bookings |
| `payment_methods` | Available payment methods |
| `booking_payment_methods` | Payment methods per booking |
| `payments` | Payment transactions |
| `users` | Admin users |
| `settings` | System configuration |
| `activity_logs` | User activity tracking |
| `site_images` | Frontend image gallery |

## 🔒 Security Checklist

Before going live, ensure:

- ✅ Admin password changed from default
- ✅ `.env` file has chmod 600 permissions
- ✅ SSL/HTTPS is enabled on your domain
- ✅ Payment method details are correct before activation
- ✅ Email settings configured correctly
- ✅ Database user has only necessary privileges
- ✅ Regular backups configured
- ✅ File upload directory has proper permissions (755)
- ✅ PHP error display is disabled in production (`display_errors = 0`)
- ✅ PHP error logging is enabled to log file

## 🔧 Troubleshooting

### "Access denied for user" Error
- Verify database credentials in `.env` file
- Check database user has privileges on the database
- Ensure database user can connect from localhost

### "Unknown database" Error
- Verify database name in `.env` is correct
- Include full database name with prefix (shared hosting)
- Database must exist before importing SQL file

### "Cannot import large file" in phpMyAdmin
- The production SQL is small (~450 lines)
- If still having issues, increase `upload_max_filesize` in PHP
- Or use command line import instead

### "Table already exists" Error
- The SQL file drops existing tables automatically
- If you see this, the previous import was incomplete
- Manually drop all tables and re-import

### Admin login not working
- Default credentials: `admin` / `Admin@123`
- Check if users table has data: `SELECT * FROM users;`
- Verify password hash exists in database

## 📚 Additional Resources

- `README.md` - Main project documentation
- `database/README.md` - Database directory overview
- `PRODUCTION_DEPLOYMENT_GUIDE.md` - Full production deployment guide
- `INSTALLATION.md` - Complete installation instructions

## 🆘 Need Help?

If you encounter issues:

1. Check the troubleshooting section above
2. Review the verification queries in the SQL file
3. Check PHP error logs for specific errors
4. Verify all security steps were completed

## ✅ Success Indicators

You'll know the setup is successful when:

- ✅ All 18 tables are created in database
- ✅ You can login to `/admin/` with admin credentials
- ✅ Admin dashboard shows zero bookings/venues (clean start)
- ✅ Settings page shows default values ready to customize
- ✅ Payment methods page shows 4 inactive methods
- ✅ Frontend website loads without errors

---

**Last Updated:** January 2026  
**Version:** 1.0  
**For:** Production Deployment Only
