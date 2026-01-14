# 🚀 HOW TO RUN - Quick Start Guide

## What You Need to Do (5 Simple Steps)

### ✅ Step 1: Import Database (2 minutes)

**Using phpMyAdmin:**
1. Open phpMyAdmin
2. Create database: `venubooking`
3. Select the database
4. Click "Import" tab
5. Import `database/schema.sql` first
6. Then import `database/sample-data.sql`

**Using Command Line:**
```bash
mysql -u your_username -p -e "CREATE DATABASE venubooking"
mysql -u your_username -p venubooking < database/schema.sql
mysql -u your_username -p venubooking < database/sample-data.sql
```

---

### ✅ Step 2: Configure Database Connection (1 minute)

1. Copy `.env.example` to `.env`
2. Open `.env` file
3. Update these lines:
```env
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=your_database_username_here
DB_PASS=your_database_password_here
```

**For cPanel:** Use full database name with prefix:
```env
DB_NAME=cpanelusername_venubooking
DB_USER=cpanelusername_venuebookinguser
```

---

### ✅ Step 3: Set File Permissions (30 seconds)

```bash
chmod 755 uploads/
chmod 755 uploads/venues/
chmod 755 uploads/halls/
chmod 755 uploads/menus/
```

Or in cPanel File Manager:
- Right-click `uploads` folder → Permissions → Set to 755

---

### ✅ Step 4: Test the System (2 minutes)

**Test Frontend:**
1. Go to: `http://yourdomain.com/` (or `http://localhost/venubooking/`)
2. You should see the green booking page
3. Fill in the booking form (Step 1)
4. Click "CHECK AVAILABILITY"
5. You should see **4 venues** in Step 2:
   - Royal Palace
   - Garden View Hall
   - City Convention Center
   - Lakeside Resort

**If you see these 4 venues, everything is working! ✅**

**Test Admin:**
1. Go to: `http://yourdomain.com/admin/`
2. Login with:
   - Username: `admin`
   - Password: `Admin@123`
3. You should see the dashboard with:
   - Total bookings: 10
   - Recent bookings table
   - Revenue chart

---

### ✅ Step 5: Add Your Own Venue (Test Admin-to-Frontend Flow)

**In Admin Panel:**
1. Click "Venues" in sidebar
2. Click "Manage Venues"
3. Click "Add New Venue" button
4. Fill in:
   - Venue Name: `My Test Venue`
   - Location: `Kathmandu`
   - Address: `Test Address, Kathmandu`
   - Description: `This is my test venue`
   - Status: `Active`
5. Click "Save Venue"

**Check Frontend:**
1. Go back to booking page
2. Fill in Step 1 again
3. Go to Step 2 (Venue Selection)
4. **You should now see 5 venues** including "My Test Venue"

**✅ If you see your new venue, the admin-to-frontend data flow is working!**

---

## 🎯 What You Should See

### Frontend Booking Page:
- Green color theme ✅
- Hero section with booking form ✅
- 6-step booking process ✅
- Sample venues displayed ✅

### Admin Dashboard:
- Login page ✅
- Dashboard with statistics ✅
- Sidebar navigation ✅
- Venue management page ✅

---

## 🔍 Quick Troubleshooting

### Problem: "Database connection failed"
**Fix:** Check `.env` file has correct database credentials

### Problem: "No venues showing"
**Fix:** 
1. Check database was imported: `SELECT * FROM venues;`
2. Should return 4 venues
3. If empty, re-import `database/sample-data.sql`

### Problem: "Admin login doesn't work"
**Fix:**
1. Check database has admin user: `SELECT * FROM users;`
2. Should have username 'admin'
3. If missing, re-import `database/sample-data.sql`

### Problem: "Permission denied" on file upload
**Fix:** Set uploads folder to 755 or 775 permissions

### Problem: "Page not found" / 404 errors
**Fix:** 
- Check .htaccess file exists
- If in subdirectory, update RewriteBase in .htaccess

---

## 📊 What's Working

### ✅ Frontend (All 6 Steps Complete)
1. **Step 1:** Booking details form
2. **Step 2:** Venue & hall selection (4 sample venues)
3. **Step 3:** Menu selection (5 sample menus)
4. **Step 4:** Additional services (8 services)
5. **Step 5:** Customer information & payment
6. **Step 6:** Confirmation page with booking ID

### ✅ Admin Panel
- Login system
- Dashboard with statistics
- Venue management (add, edit, delete)
- Recent bookings display

### ✅ Sample Data Included
- 4 Venues
- 8 Halls
- 5 Menus (Rs. 1,299 to Rs. 2,999 per person)
- 8 Services (Rs. 10,000 to Rs. 50,000)
- 10 Sample bookings

---

## 🎉 Success Criteria

**You've successfully set it up if:**

1. ✅ Frontend shows 4 venues in booking process
2. ✅ Admin login works (admin / Admin@123)
3. ✅ Dashboard shows 10 bookings
4. ✅ You can add a new venue in admin
5. ✅ New venue appears on frontend immediately

---

## 📞 Need More Help?

**Read these files:**
- `DEPLOYMENT_GUIDE.md` - Complete setup guide (17,000+ words)
- `README.md` - General information
- `CPANEL_INSTALLATION.md` - cPanel-specific instructions

**Test database connection:**
Create file `test-db.php` in root:
```php
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = getDB();
    echo "✅ Database connected!<br>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM venues");
    $result = $stmt->fetch();
    echo "✅ Venues: " . $result['count'] . "<br>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch();
    echo "✅ Bookings: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

Visit: `http://yourdomain.com/test-db.php`

Should show:
```
✅ Database connected!
✅ Venues: 4
✅ Bookings: 10
```

---

## ⚠️ Important: Change Default Password!

After first login, change admin password immediately:

**SQL Method:**
```sql
-- Replace 'YourNewPassword' with actual password
UPDATE users 
SET password = '$2y$10$...' -- Use password_hash() to generate
WHERE username = 'admin';
```

**Or create temp file to hash password:**
```php
<?php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT);
?>
```

---

## 📁 File Structure Check

Make sure you have these files:
```
venubooking/
├── index.php                    ✅ Landing page
├── booking-step2.php            ✅ Venue selection
├── booking-step3.php            ✅ Menu selection
├── booking-step4.php            ✅ Services
├── booking-step5.php            ✅ Customer info
├── confirmation.php             ✅ Confirmation
├── .env                         ⚠️ You must create this
├── .htaccess                    ✅ Already exists
├── database/
│   ├── schema.sql              ✅ Database structure
│   └── sample-data.sql         ✅ Sample data
├── admin/
│   ├── login.php               ✅ Admin login
│   ├── dashboard.php           ✅ Dashboard
│   └── venues/
│       ├── list.php            ✅ List venues
│       ├── add.php             ✅ Add venue
│       └── edit.php            ✅ Edit venue
└── includes/
    ├── config.php              ✅ Configuration
    ├── db.php                  ✅ Database
    ├── functions.php           ✅ Helper functions
    └── auth.php                ✅ Authentication
```

---

## 🎊 You're All Set!

**After completing these 5 steps, you'll have:**
- ✅ Working frontend booking system
- ✅ Working admin panel
- ✅ 4 sample venues to test with
- ✅ Ability to add your own venues
- ✅ Complete booking workflow

**Time Required:** ~5-10 minutes total

**Difficulty:** Easy (just follow the checklist)

---

**Last Updated:** January 14, 2026  
**Status:** Ready to Deploy  
**Support:** See DEPLOYMENT_GUIDE.md for detailed help
