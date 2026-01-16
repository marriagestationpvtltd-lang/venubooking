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

### Method 1: MySQL Command Line (Recommended)

**Step 1:** Open terminal/command prompt

**Step 2:** Navigate to the project directory
```bash
cd /path/to/venubooking
```

**Step 3:** Run the SQL file
```bash
mysql -u root -p < database/complete-database-setup.sql
```

**Step 4:** Enter your MySQL password when prompted

**Step 5:** Wait for completion (you'll see verification output)

✅ **Done!** Skip to "Verification" section below.

---

### Method 2: phpMyAdmin

**Step 1:** Open phpMyAdmin in your browser
```
http://localhost/phpmyadmin
```

**Step 2:** Click on "SQL" tab at the top

**Step 3:** Click "Import" tab

**Step 4:** Click "Choose File" and select:
```
database/complete-database-setup.sql
```

**Step 5:** Scroll down and click "Go" button

**Step 6:** Wait for success message

✅ **Done!** Continue to "Verification" section below.

---

### Method 3: MySQL Workbench

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

Run this query in MySQL:
```sql
USE venubooking;
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

### Issue: "Database connection failed"

**Solution 1:** Check `.env` file
```bash
# Make sure these match your MySQL credentials
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=root
DB_PASS=your_password
```

**Solution 2:** Create `.env` file if it doesn't exist
```bash
cp .env.example .env
# Then edit .env with your credentials
```

### Issue: "Table doesn't exist"

**Solution:** The SQL file creates all tables. If some are missing:

1. Drop the database:
```sql
DROP DATABASE IF EXISTS venubooking;
```

2. Re-run the complete-database-setup.sql file

### Issue: "Access denied for user"

**Solution:** Grant proper permissions

```sql
GRANT ALL PRIVILEGES ON venubooking.* TO 'root'@'localhost';
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
