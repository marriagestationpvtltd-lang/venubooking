# Complete A-Z Database Installation Guide

## 📋 Overview

This guide provides **COMPLETE** step-by-step instructions to set up the database for the Venue Booking System from A to Z. After following this guide, your booking details page will display all data correctly.

## 🎯 What This Will Set Up

✅ **All Required Tables** (18 tables total):
- venues, halls, hall_images
- menus, menu_items, hall_menus
- additional_services
- customers, bookings, booking_menus, booking_services
- payment_methods, booking_payment_methods, payments
- users, settings, activity_logs, site_images

✅ **Default Admin User**:
- Username: `admin`
- Password: `Admin@123`

✅ **Sample Data**:
- 4 Venues (Royal Palace, Garden View Hall, etc.)
- 8 Halls with different capacities
- 5 Menus with items
- 8 Additional Services
- 4 Payment Methods
- 7 Sample Customers
- 4 Sample Bookings (including booking #23 and #37 for testing)

## 📁 Installation Files

The complete database setup is in one file:
```
database/complete-database-setup.sql
```

## 🚀 Installation Methods

Choose **ONE** of the following methods:

---

### Method 1: MySQL Command Line (Local Development)

**For local development with full permissions:**

**Step 1:** Open terminal/command prompt

**Step 2:** Navigate to the project directory
```bash
cd /path/to/venubooking
```

**Step 3:** Create database (if needed)
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS your_database_name;"
```

**Step 4:** Run the SQL file
```bash
mysql -u root -p your_database_name < database/complete-database-setup.sql
```

**Step 5:** Enter your MySQL password when prompted

**Step 6:** Wait for completion

✅ **Done!** Skip to "Verification" section below.

---

### Method 2: phpMyAdmin (Recommended for Shared Hosting)

**For shared hosting (cPanel, etc.):**

**Step 1:** Create Database in cPanel
- Go to cPanel → MySQL Databases
- Click "Create New Database"
- Database name will have a prefix (e.g., `username_venubooking`)
- Note the full database name

**Step 2:** Create Database User (if needed)
- In the same MySQL Databases section
- Create a new user or use existing
- Add user to the database with ALL PRIVILEGES

**Step 3:** Open phpMyAdmin
```
Usually: http://your-domain.com/phpmyadmin
Or via cPanel → phpMyAdmin
```

**Step 4:** Select Your Database
- **IMPORTANT:** Click on your database name in the left sidebar
- Make sure it's selected (highlighted)

**Step 5:** Import the SQL File
- Click "Import" tab at the top
- Click "Choose File" and select:
  ```
  database/complete-database-setup.sql
  ```
- Scroll down and click "Go" button

**Step 6:** Wait for success message
- You should see "Import has been successfully finished"
- Check that 18 tables were created

**Step 7:** Update .env File
```
DB_HOST=localhost
DB_NAME=username_venubooking  # Your full database name with prefix
DB_USER=username_dbuser       # Your database user
DB_PASS=your_password          # Your database password
```

✅ **Done!** Continue to "Verification" section below.

---

### Method 3: MySQL Workbench (Local Development)

**Step 1:** Open MySQL Workbench

**Step 2:** Connect to your MySQL server

**Step 3:** Go to: File → Open SQL Script

**Step 4:** Select the file:
```
database/complete-database-setup.sql
```

**Step 5:** Click the lightning bolt icon (⚡) to execute

**Step 6:** Wait for completion

✅ **Done!** Continue to "Verification" section below.

---

## ✔️ Verification

After installation, verify the setup:

### 1. Check Database Tables

Run this query in MySQL (make sure you're connected to your database):
```sql
SHOW TABLES;
```

**Expected output:** 18 tables

### 2. Check Sample Data

```sql
SELECT COUNT(*) FROM bookings;
```
**Expected:** At least 4 bookings

```sql
SELECT id, booking_number, event_type FROM bookings WHERE id IN (23, 37);
```
**Expected:** Should show booking #23 and #37

### 3. Test Admin Login

1. Open: `http://your-domain.com/admin/`
2. Username: `admin`
3. Password: `Admin@123`
4. Should redirect to dashboard

### 4. Test Booking View

Open in browser:
```
http://your-domain.com/admin/bookings/view.php?id=37
```

**You should see:**
- ✅ Customer information
- ✅ Event details
- ✅ Selected menus
- ✅ Additional services
- ✅ Payment methods
- ✅ Payment summary
- ✅ All sections populated with data

---

## 🔧 Troubleshooting

### Issue: "Access denied for user" OR Error #1044

**This is the most common issue on shared hosting!**

**Problem:** The SQL file tries to create a database, but you don't have permission.

**Solution:**
1. **Create database via cPanel first:**
   - cPanel → MySQL Databases → Create New Database
   - Note the full database name (includes prefix like `username_dbname`)

2. **Create/assign database user:**
   - Create user or use existing one
   - Grant ALL PRIVILEGES to that database

3. **Select database before importing:**
   - In phpMyAdmin, click on your database name in left sidebar
   - THEN click Import and choose the SQL file

4. **Update .env file:**
   ```
   DB_NAME=username_venubooking  # Full name with prefix!
   DB_USER=username_dbuser
   DB_PASS=your_password
   ```

### Issue: "Database connection failed"

**Solution 1:** Check `.env` file
```bash
# Make sure these match your actual credentials
DB_HOST=localhost
DB_NAME=your_full_database_name  # Include prefix on shared hosting
DB_USER=your_database_user
DB_PASS=your_password
```

**Solution 2:** Create `.env` file if it doesn't exist
```bash
cp .env.example .env
# Then edit .env with your credentials
```

### Issue: "Table doesn't exist"

**Solution:** If tables are missing after import:

1. Make sure you selected the database before importing
2. Check phpMyAdmin for import errors
3. Re-import the SQL file with database selected

### Issue: "Grant proper permissions" (Local Development)

**Solution for local MySQL:**

```sql
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

### Issue: "Foreign key constraint fails"

**Solution:** The script handles this automatically. If you see this error:

1. Make sure you're running the complete script in one go
2. Don't run partial scripts
3. The script sets `FOREIGN_KEY_CHECKS = 0` to handle this

### Issue: "Booking #37 shows no data"

**Checklist:**
1. ✅ Database imported successfully?
2. ✅ `.env` file configured correctly?
3. ✅ Web server can connect to database?
4. ✅ PHP errors in logs?

Check PHP error log:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php-fpm/error.log
```

---

## 🔐 Security - IMPORTANT!

After installation, **immediately change the default admin password**:

1. Log in to admin panel
2. Go to: Settings → Change Password
3. Set a strong password
4. Log out and log back in

**Default credentials are:**
- Username: `admin`
- Password: `Admin@123`

⚠️ **These are publicly documented and must be changed!**

---

## 📊 What Data Is Included

### Venues (4)
1. Royal Palace - Kathmandu
2. Garden View Hall - Lalitpur
3. City Convention Center - Kathmandu
4. Lakeside Resort - Pokhara

### Halls (8)
- Sagarmatha Hall (700 capacity, NPR 150,000)
- Everest Hall (500 capacity, NPR 120,000)
- Garden Lawn (1000 capacity, NPR 180,000)
- Rose Hall (300 capacity, NPR 80,000)
- Convention Hall A (800 capacity, NPR 200,000)
- Convention Hall B (400 capacity, NPR 100,000)
- Lakeview Terrace (600 capacity, NPR 220,000)
- Sunset Hall (350 capacity, NPR 90,000)

### Menus (5)
1. Royal Gold Menu - NPR 2,399/person
2. Silver Deluxe Menu - NPR 1,899/person
3. Bronze Classic Menu - NPR 1,499/person
4. Vegetarian Special - NPR 1,299/person
5. Premium Platinum - NPR 2,999/person

### Services (8)
- Flower Decoration - NPR 15,000
- Stage Decoration - NPR 25,000
- Photography Package - NPR 30,000
- Videography Package - NPR 40,000
- DJ Service - NPR 20,000
- Live Band - NPR 50,000
- Transportation - NPR 35,000
- Valet Parking - NPR 10,000

### Test Bookings (4)
- Booking #1: Wedding (500 guests)
- Booking #2: Birthday Party (200 guests)
- Booking #23: Wedding Reception (250 guests) ✅
- Booking #37: Wedding Ceremony (600 guests) ✅

---

## 🎯 Next Steps After Installation

1. ✅ Change admin password
2. ✅ Test the booking view page
3. ✅ Update payment methods in Admin > Payment Methods
4. ✅ Update company settings in Admin > Settings
5. ✅ Add your own venues and halls
6. ✅ Customize menus
7. ✅ Delete sample bookings if not needed

---

## 📞 Need Help?

If you still face issues:

1. Check all tables are created:
   ```sql
   SELECT COUNT(*) FROM information_schema.tables 
   WHERE table_schema = 'venubooking';
   ```
   **Expected:** 18 tables

2. Check if booking #37 exists:
   ```sql
   SELECT * FROM bookings WHERE id = 37;
   ```

3. Check PHP errors:
   - Enable error display in development
   - Check web server error logs
   - Check PHP error logs

4. Verify database connection:
   - Test connection with a simple PHP script
   - Make sure credentials in `.env` are correct

---

## ✅ Success Checklist

After installation, you should be able to:

- [ ] Access admin panel at `/admin/`
- [ ] Log in with admin/Admin@123
- [ ] See dashboard with statistics
- [ ] View list of bookings at `/admin/bookings/`
- [ ] Open booking #37 and see complete details
- [ ] Open booking #23 and see complete details
- [ ] See customer information
- [ ] See event details
- [ ] See selected menus with items
- [ ] See additional services
- [ ] See payment methods
- [ ] See payment transactions
- [ ] Print booking invoice

If all items are checked, **installation is successful!** 🎉

---

## 📝 Database Schema Reference

The complete schema includes these relationships:

```
venues
  └── halls
      ├── hall_images
      └── hall_menus → menus
                        └── menu_items

bookings
  ├── customers
  ├── halls
  ├── booking_menus → menus
  ├── booking_services → additional_services
  └── booking_payment_methods → payment_methods

payments
  ├── bookings
  └── payment_methods

users
  └── activity_logs

settings (key-value pairs)
site_images (dynamic content)
```

---

**Last Updated:** January 2026  
**Database Version:** 1.0 (Complete A-Z Setup)  
**Tested On:** MySQL 8.0, PHP 8.0+
