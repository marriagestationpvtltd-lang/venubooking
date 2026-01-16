# Production Database Upload Guide

## 🚀 Quick Start for Production

This guide helps you upload the production-ready database SQL to your live server.

## 📋 What You Need

1. Database credentials (host, username, password)
2. The file: `database/production-ready.sql`
3. Access to phpMyAdmin or MySQL command line

## ⚡ Quick Steps

### Step 1: Create Database

**Via cPanel:**
1. Login to cPanel
2. Go to **MySQL Databases**
3. Create new database (e.g., `username_venubooking`)
4. Create user with strong password
5. Add user to database with ALL PRIVILEGES

**Via Command Line:**
```bash
mysql -u root -p
CREATE DATABASE venubooking_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON venubooking_prod.* TO 'vb_user'@'localhost' IDENTIFIED BY 'strong_password';
FLUSH PRIVILEGES;
EXIT;
```

### Step 2: Upload SQL File

**Via phpMyAdmin (Most Common):**
1. Open phpMyAdmin
2. **Select your database** from left sidebar (important!)
3. Click **Import** tab
4. Click **Choose File** → select `production-ready.sql`
5. Click **Go**
6. Wait for success message (~5-10 seconds)

**Via Command Line:**
```bash
cd /path/to/venubooking
mysql -u username -p database_name < database/production-ready.sql
```

### Step 3: Configure .env File

Create `.env` file with your database credentials:

```env
DB_HOST=localhost
DB_NAME=username_venubooking
DB_USER=username_dbuser
DB_PASS=your_strong_password
```

### Step 4: Security Steps (CRITICAL!)

⚠️ **Do these immediately after upload:**

1. **Login to Admin Panel:**
   - URL: `https://yourdomain.com/admin/`
   - Username: `admin`
   - Password: `Admin@123`

2. **Change Admin Password:**
   - Go to Admin Panel → Profile/Settings
   - Change to a strong password (12+ characters)

3. **Update Company Settings:**
   - Admin Panel → Settings
   - Update company name, address, contact info

4. **Configure Payment Methods:**
   - Admin Panel → Payment Methods
   - Edit each method with real bank details
   - Upload QR codes
   - **Only activate after configuration**

## ✅ Verify Installation

After upload, verify everything is working:

1. **Check Tables Created:**
   ```sql
   SHOW TABLES;
   -- Should show 18 tables
   ```

2. **Check Admin User:**
   ```sql
   SELECT username FROM users WHERE role='admin';
   -- Should show: admin
   ```

3. **Check Database is Empty:**
   ```sql
   SELECT COUNT(*) FROM venues;
   SELECT COUNT(*) FROM bookings;
   -- Both should return 0
   ```

4. **Test Admin Login:**
   - Visit `/admin/`
   - Login with admin/Admin@123
   - Should see empty dashboard

## 🎯 What's Included

The `production-ready.sql` file includes:

✅ All 18 database tables
✅ Default admin user (admin/Admin@123)
✅ Essential system settings
✅ Placeholder payment methods (inactive)
✅ Clean, empty database ready for real data

❌ NO sample/test data
❌ NO sample venues or halls
❌ NO test bookings
❌ NO demo customers

## 📊 Database Tables Created

1. `venues` - Venue locations
2. `halls` - Halls/rooms
3. `hall_images` - Hall photos
4. `menus` - Food packages
5. `menu_items` - Menu items
6. `hall_menus` - Hall-menu links
7. `additional_services` - Extra services
8. `customers` - Customer records
9. `bookings` - Booking records
10. `booking_menus` - Booking-menu links
11. `booking_services` - Booking-service links
12. `payment_methods` - Payment options
13. `booking_payment_methods` - Booking payment links
14. `payments` - Payment transactions
15. `users` - Admin users
16. `settings` - System settings
17. `activity_logs` - Activity tracking
18. `site_images` - Site images

## 🔧 Troubleshooting

### "Cannot connect to database"
- Check DB credentials in `.env`
- Verify database user has privileges
- Ensure database exists

### "Table already exists"
- The SQL file drops existing tables automatically
- If error persists, manually drop all tables first

### "Access denied"
- Verify database user password
- Check user has ALL PRIVILEGES on database
- On shared hosting, use full database name with prefix

### Admin login not working
- Default: admin / Admin@123
- Password is case-sensitive
- Check users table: `SELECT * FROM users;`

## 📚 Additional Documentation

- **[database/PRODUCTION_DATABASE_GUIDE.md](database/PRODUCTION_DATABASE_GUIDE.md)** - Comprehensive production guide
- **[database/SQL_FILES_COMPARISON.md](database/SQL_FILES_COMPARISON.md)** - Compare different SQL files
- **[database/README.md](database/README.md)** - Database directory overview
- **[README.md](README.md)** - Main project documentation

## 🔒 Security Checklist

Before going live:

- ✅ Database uploaded successfully
- ✅ Admin password changed from default
- ✅ Company information updated
- ✅ Payment methods configured (not just placeholders)
- ✅ SSL/HTTPS enabled
- ✅ .env file has chmod 600 permissions
- ✅ Uploads directory writable (chmod 755)
- ✅ Test booking flow works
- ✅ Email notifications configured (if using)

## 🎬 Next Steps

After successful database upload:

1. ✅ Change admin password
2. ✅ Add your real venues and halls
3. ✅ Add your menus and services
4. ✅ Configure payment methods
5. ✅ Test a booking from frontend
6. ✅ Configure email notifications
7. ✅ Go live!

---

**Need Help?**

- Production issues? See [PRODUCTION_DATABASE_GUIDE.md](database/PRODUCTION_DATABASE_GUIDE.md)
- Choosing SQL file? See [SQL_FILES_COMPARISON.md](database/SQL_FILES_COMPARISON.md)
- General setup? See [README.md](README.md)

---

**Last Updated:** January 2026  
**For:** Production Database Upload
