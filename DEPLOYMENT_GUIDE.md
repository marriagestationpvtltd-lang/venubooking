# Complete Deployment Guide - Venue Booking System

## 🚀 Quick Start Guide for Production Deployment

This guide will help you deploy and test the venue booking system on any server (localhost, VPS, or cPanel shared hosting).

---

## Part 1: Database Setup

### Step 1: Create Database

```sql
CREATE DATABASE venubooking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Import Schema

Navigate to your project directory and import the database schema:

**Using Command Line:**
```bash
mysql -u your_username -p venubooking < database/schema.sql
```

**Using phpMyAdmin:**
1. Login to phpMyAdmin
2. Select `venubooking` database
3. Click "Import" tab
4. Choose `database/schema.sql` file
5. Click "Go"

### Step 3: Import Sample Data

**Using Command Line:**
```bash
mysql -u your_username -p venubooking < database/sample-data.sql
```

**Using phpMyAdmin:**
1. In phpMyAdmin, with `venubooking` database selected
2. Click "Import" tab
3. Choose `database/sample-data.sql` file
4. Click "Go"

---

## Part 2: Configuration

### Step 1: Create .env File

Copy the example environment file:

```bash
cp .env.example .env
```

### Step 2: Edit .env File

Open `.env` file and update these values:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=your_database_username
DB_PASS=your_database_password

# Application Configuration
APP_NAME="Venue Booking System"
APP_URL=http://yourdomain.com
APP_TIMEZONE=Asia/Kathmandu

# Email Configuration (Optional - for email notifications)
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USERNAME=your-email@example.com
SMTP_PASSWORD=your-email-password
SMTP_ENCRYPTION=tls
EMAIL_FROM_ADDRESS=noreply@yourdomain.com
EMAIL_FROM_NAME="Venue Booking System"
```

### Step 3: Set File Permissions

```bash
chmod -R 775 uploads/
chmod -R 775 uploads/venues/
chmod -R 775 uploads/halls/
chmod -R 775 uploads/menus/
```

If on shared hosting:
```bash
chmod 755 uploads/
chmod 755 uploads/venues/
chmod 755 uploads/halls/
chmod 755 uploads/menus/
```

---

## Part 3: Testing the System

### A. Test Frontend (Customer Booking Flow)

#### 1. Access Landing Page
- Open: `http://yourdomain.com/` or `http://localhost/venubooking/`
- You should see the green-themed landing page with booking form

#### 2. Test Step 1 - Booking Details
- Select a shift (e.g., "Evening")
- Choose a date (at least 1 day in the future)
- Enter number of guests (minimum 10, try 150)
- Select event type (e.g., "Wedding")
- Click "CHECK AVAILABILITY & PROCEED"

#### 3. Test Step 2 - Venue & Hall Selection
- You should see 4 venues (from sample data):
  - Royal Palace (Kathmandu)
  - Garden View Hall (Lalitpur)
  - City Convention Center (Kathmandu)
  - Lakeside Resort (Pokhara)
- Click "View Halls" on any venue
- You should see halls with capacity and pricing
- Select a hall that can accommodate your guest count
- Click "Select This Hall"

#### 4. Test Step 3 - Menu Selection
- You should see 5 menus available
- You can select one or multiple menus
- Price should calculate dynamically (Menu Price × Guests)
- Click "Continue to Services"

#### 5. Test Step 4 - Additional Services
- You should see 8 services available:
  - Flower Decoration
  - Stage Decoration
  - Photography Package
  - Videography Package
  - DJ Service
  - Live Band
  - Transportation
  - Valet Parking
- Select any services you want
- Total price updates automatically
- Click "Continue to Booking Details"

#### 6. Test Step 5 - Customer Information & Summary
- Fill in customer details:
  - Full Name
  - Phone Number
  - Email Address
  - Address (optional)
  - Special Requests (optional)
- Review booking summary showing:
  - Hall cost
  - Menu cost
  - Services cost
  - Tax (13%)
  - Grand total
- Select payment option:
  - Advance Payment (30%)
  - Full Payment
  - Pay Later
- Accept terms and conditions
- Click "CONFIRM BOOKING"

#### 7. Test Step 6 - Confirmation
- You should see confirmation page with:
  - Booking ID (format: BK-YYYYMMDD-XXXX)
  - Complete booking details
  - Payment status
  - Print button
- Email notification should be sent (if SMTP is configured)

### B. Test Admin Panel

#### 1. Access Admin Login
- Open: `http://yourdomain.com/admin/` or `http://localhost/venubooking/admin/`
- You should see the admin login page

#### 2. Login with Default Credentials
```
Username: admin
Password: Admin@123
```
**⚠️ IMPORTANT: Change this password immediately after first login!**

#### 3. Test Dashboard
After login, you should see:
- Total bookings count (should show bookings from sample data)
- Total revenue (sum of booking amounts)
- Pending bookings count
- Recent bookings table (should show 10 sample bookings)
- Revenue chart showing monthly data
- Upcoming events list

#### 4. Test Venue Management
- Click "Venues" in the sidebar
- Click "Manage Venues" or navigate to `/admin/venues/list.php`
- You should see 4 venues from sample data
- **Test Add Venue:**
  - Click "Add New Venue"
  - Fill in form:
    - Venue Name: "Test Venue"
    - Location: "Kathmandu"
    - Address: "Test Address"
    - Description: "This is a test venue"
    - Upload an image (optional)
    - Status: Active
  - Click "Save Venue"
  - Venue should appear in list
- **Test Edit Venue:**
  - Click edit button on any venue
  - Modify details
  - Click "Update Venue"
  - Changes should be saved
- **Test Delete Venue:**
  - Click delete button on test venue
  - Confirm deletion
  - Venue should be removed

### C. Verify Data Flow (Admin → Frontend)

#### Test 1: Add New Venue and Verify on Frontend

**Admin Side:**
1. Login to admin panel
2. Go to Venues → Add New Venue
3. Add a new venue:
   - Name: "Premium Gardens"
   - Location: "Kathmandu"
   - Address: "Durbarmarg, Kathmandu"
   - Description: "Luxury outdoor venue"
   - Status: Active
4. Save

**Frontend Side:**
1. Go to booking page (index.php)
2. Fill in booking details (Step 1)
3. Click "CHECK AVAILABILITY"
4. In Step 2, you should now see "Premium Gardens" in the venue list

#### Test 2: Verify Booking Appears in Admin

**Frontend Side:**
1. Complete a full booking (all 6 steps)
2. Note the booking ID

**Admin Side:**
1. Login to admin panel
2. Dashboard should show updated counts
3. Recent bookings should include your new booking
4. Verify all booking details are correct

---

## Part 4: What Works & What Doesn't

### ✅ Fully Functional Features

1. **Database & Backend**
   - 14 tables with relationships
   - Sample data loaded
   - PDO database connection
   - All helper functions working
   - Security functions (CSRF, XSS prevention)

2. **Frontend Booking Flow**
   - Step 1: Event details form ✅
   - Step 2: Venue & hall selection ✅
   - Step 3: Menu selection ✅
   - Step 4: Services selection ✅
   - Step 5: Customer info & payment ✅
   - Step 6: Confirmation page ✅
   - Email notifications ✅

3. **API Endpoints**
   - `/api/get-venues.php` - Returns all active venues ✅
   - `/api/get-halls.php` - Returns halls by venue ✅
   - `/api/get-menus.php` - Returns available menus ✅
   - `/api/get-services.php` - Returns additional services ✅
   - `/api/check-availability.php` - Checks hall availability ✅
   - `/api/calculate-price.php` - Calculates booking total ✅
   - `/api/create-booking.php` - Creates new booking ✅

4. **Admin Features**
   - Admin login/logout ✅
   - Dashboard with statistics ✅
   - Revenue charts ✅
   - Recent bookings display ✅
   - Venue management (list, add, edit, delete) ✅

5. **Security**
   - CSRF protection on all forms ✅
   - XSS prevention (output sanitization) ✅
   - SQL injection prevention (PDO) ✅
   - Password hashing ✅
   - Session security ✅
   - File upload validation ✅

### ⏳ Features Requiring Completion

1. **Admin CRUD Pages (Remaining)**
   - Hall Management (list, add, edit, delete)
   - Menu Management (list, add, edit, delete)
   - Menu Items Management
   - Hall-Menu Assignment
   - Services Management
   - Booking Management (view, edit, cancel)
   - Customer Management
   - Reports (revenue, bookings, customers)
   - Settings pages

**Note:** The venue management is complete and serves as a template. Other CRUD pages follow the same pattern and can be created using the venue management files as reference.

---

## Part 5: Verifying Admin-to-Frontend Data Flow

### Quick Verification Steps:

1. **Login to Admin Panel** (admin / Admin@123)

2. **Check Sample Data:**
   - Navigate to Dashboard
   - Verify you see 10 bookings in "Recent Bookings"
   - Verify statistics show correct counts

3. **Go to Frontend:**
   - Open homepage in new tab
   - Start booking process
   - In Step 2, verify you see all 4 sample venues
   - Click "View Halls" on any venue
   - Verify you see halls with correct capacity and pricing

4. **Test New Venue:**
   - In admin, add a new venue
   - Go back to frontend booking
   - Refresh Step 2 page
   - New venue should appear immediately

5. **Test Booking Creation:**
   - Complete a booking on frontend
   - Go to admin dashboard
   - New booking should appear in "Recent Bookings"
   - Statistics should update

### If Data Doesn't Show:

**Check 1: Database Connection**
```php
// Add this to test database connection
// Create file: test-db.php in root directory
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = getDB();
    echo "✅ Database connected successfully!<br>";
    
    // Test venues
    $stmt = $db->query("SELECT COUNT(*) as count FROM venues");
    $result = $stmt->fetch();
    echo "✅ Venues in database: " . $result['count'] . "<br>";
    
    // Test halls
    $stmt = $db->query("SELECT COUNT(*) as count FROM halls");
    $result = $stmt->fetch();
    echo "✅ Halls in database: " . $result['count'] . "<br>";
    
    // Test menus
    $stmt = $db->query("SELECT COUNT(*) as count FROM menus");
    $result = $stmt->fetch();
    echo "✅ Menus in database: " . $result['count'] . "<br>";
    
    // Test bookings
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch();
    echo "✅ Bookings in database: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

Run this file: `http://yourdomain.com/test-db.php`

You should see:
```
✅ Database connected successfully!
✅ Venues in database: 4
✅ Halls in database: 8
✅ Menus in database: 5
✅ Bookings in database: 10
```

**Check 2: Sample Data Imported**
- Login to phpMyAdmin
- Select `venubooking` database
- Click on each table (venues, halls, menus, bookings)
- Verify there are records in each table

**Check 3: File Paths**
- Make sure `.env` file exists and has correct database credentials
- Check that uploads directory exists and is writable
- Verify PHP version is 8.0 or higher

---

## Part 6: Final Setup Steps

### Step 1: Change Default Admin Password

1. Login to admin panel
2. Navigate to Settings → Users (when implemented) OR
3. Run this SQL query in phpMyAdmin:
```sql
-- Replace 'YourNewPassword' with your actual password
UPDATE users 
SET password = '$2y$10$YourHashedPassword' 
WHERE username = 'admin';
```

To generate hashed password, create a temporary PHP file:
```php
<?php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT);
?>
```

### Step 2: Configure Email (Optional)

Edit `.env` file with your SMTP settings:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
```

For Gmail: Use App Password (not regular password)

### Step 3: Test Email Notifications

1. Complete a booking on frontend
2. Check if confirmation email is received
3. If not, check SMTP settings in `.env`

### Step 4: Clean Up

After everything works:
```bash
# Remove test file (if created)
rm test-db.php

# Ensure error display is off in production
# Edit includes/config.php:
error_reporting(0);
ini_set('display_errors', 0);
```

---

## Part 7: Troubleshooting Common Issues

### Issue 1: "Database connection failed"
**Solution:**
- Verify `.env` file exists and has correct credentials
- Check database exists: `SHOW DATABASES;`
- Test connection with MySQL client

### Issue 2: "No venues showing on frontend"
**Solution:**
- Check sample data was imported: `SELECT * FROM venues;`
- Verify venues have status 'active'
- Check API endpoint: Visit `/api/get-venues.php` directly

### Issue 3: "Admin login doesn't work"
**Solution:**
- Verify users table has admin user
- Check password hash is correct
- Run: `SELECT * FROM users WHERE username = 'admin';`

### Issue 4: "Images not uploading"
**Solution:**
- Check uploads directory permissions (755 or 775)
- Verify max file size in PHP.ini
- Check error logs

### Issue 5: "Session errors"
**Solution:**
- Check session directory is writable
- Verify PHP session.save_path
- Clear browser cookies

---

## Part 8: System Requirements Verification

Run this PHP info script to verify requirements:

```php
<?php
// Create file: check-requirements.php
echo "<h2>System Requirements Check</h2>";

// PHP Version
echo "<p>PHP Version: " . phpversion();
echo (version_compare(phpversion(), '8.0.0', '>=')) ? " ✅" : " ❌ (Need 8.0+)</p>";

// PDO
echo "<p>PDO Extension: ";
echo extension_loaded('pdo') ? "✅ Installed" : "❌ Missing</p>";

// PDO MySQL
echo "<p>PDO MySQL: ";
echo extension_loaded('pdo_mysql') ? "✅ Installed" : "❌ Missing</p>";

// mbstring
echo "<p>mbstring: ";
echo extension_loaded('mbstring') ? "✅ Installed" : "❌ Missing</p>";

// JSON
echo "<p>JSON: ";
echo extension_loaded('json') ? "✅ Installed" : "❌ Missing</p>";

// OpenSSL
echo "<p>OpenSSL: ";
echo extension_loaded('openssl') ? "✅ Installed" : "❌ Missing</p>";

// GD
echo "<p>GD (for image processing): ";
echo extension_loaded('gd') ? "✅ Installed" : "❌ Missing</p>";

// File Permissions
echo "<h3>File Permissions</h3>";
echo "<p>uploads/ directory: ";
echo is_writable('uploads') ? "✅ Writable" : "❌ Not writable</p>";

// .env file
echo "<p>.env file: ";
echo file_exists('.env') ? "✅ Exists" : "❌ Missing (copy from .env.example)</p>";
?>
```

---

## Part 9: What to Do Next

### Immediate Actions:
1. ✅ Import database schema
2. ✅ Import sample data
3. ✅ Configure .env file
4. ✅ Set file permissions
5. ✅ Test frontend booking flow
6. ✅ Login to admin panel
7. ✅ Change default password
8. ✅ Add a test venue from admin
9. ✅ Verify venue appears on frontend
10. ✅ Complete a test booking

### For Production Use:
- Complete remaining admin CRUD pages (use venue management as template)
- Configure email notifications
- Set up SSL certificate
- Configure automated backups
- Set up error logging
- Disable PHP error display
- Set up monitoring

---

## Part 10: Support & Resources

### Sample Data Included:
- 4 Venues (Royal Palace, Garden View Hall, City Convention Center, Lakeside Resort)
- 8 Halls (2 per venue, various capacities)
- 5 Menus (Rs. 1,299 to Rs. 2,999 per person)
- 8 Additional Services (Rs. 10,000 to Rs. 50,000)
- 10 Sample Bookings (various dates and statuses)
- 1 Admin User (username: admin, password: Admin@123)

### File Structure:
```
venubooking/
├── index.php                  # Landing page ✅
├── booking-step2.php          # Venue/Hall selection ✅
├── booking-step3.php          # Menu selection ✅
├── booking-step4.php          # Services selection ✅
├── booking-step5.php          # Customer info ✅
├── confirmation.php           # Booking confirmation ✅
├── api/                       # 7 API endpoints ✅
├── admin/
│   ├── login.php             # Admin login ✅
│   ├── dashboard.php         # Dashboard ✅
│   └── venues/
│       ├── list.php          # List venues ✅
│       ├── add.php           # Add venue ✅
│       └── edit.php          # Edit venue ✅
├── includes/
│   ├── config.php            # Configuration ✅
│   ├── db.php                # Database connection ✅
│   ├── functions.php         # Helper functions ✅
│   ├── auth.php              # Authentication ✅
│   ├── header.php            # Frontend header ✅
│   ├── footer.php            # Frontend footer ✅
│   ├── admin-header.php      # Admin header ✅
│   └── admin-sidebar.php     # Admin sidebar ✅
└── database/
    ├── schema.sql            # Database structure ✅
    └── sample-data.sql       # Sample data ✅
```

---

## Summary

**✅ Core System is Fully Functional:**
- Database structure complete
- Sample data loaded
- Frontend booking flow (6 steps) working
- Admin panel login & dashboard working
- Venue management (CRUD) working
- All API endpoints functional
- Security measures implemented

**⏳ Additional Admin Pages Needed:**
- Follow venue management pattern for halls, menus, services, bookings
- All database structure is ready
- All helper functions exist
- Just need to create additional list/add/edit pages

**🎉 Ready for Use:**
The system is production-ready for the core booking functionality. Customers can browse venues, select halls and menus, add services, and complete bookings. Admins can manage venues and view bookings.

---

**Last Updated:** January 14, 2026
**Version:** 1.0.0
**Status:** ✅ Core System Complete & Tested
