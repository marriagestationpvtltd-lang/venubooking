# 🚀 Production Database Setup Guide for Shared Hosting

This guide will help you deploy the Venue Booking System to your shared hosting with the pre-configured database.

## 📋 Pre-configured Database Credentials

Your database is already set up with the following credentials:

```
Database Name: digitallami_partybooking
Database User: digitallami_partybooking
Database Password: P@sswo0rdms
Database Host: localhost
```

## 🎯 Quick Start (3 Simple Steps)

### Step 1: Import Database

1. **Open phpMyAdmin** from your cPanel
2. **Select the database** `digitallami_partybooking` from the left sidebar
3. Click the **"Import"** tab at the top
4. Click **"Choose File"** and select: `database/production-shared-hosting.sql`
5. Scroll down and click **"Go"**
6. Wait for the import to complete (you should see a success message)

### Step 2: Upload Files & Configure

1. **Upload all website files** to your hosting (public_html or subdirectory)
2. **Copy `.env.production` to `.env`** in the root directory:
   ```bash
   cp .env.production .env
   ```
   Or manually create `.env` file with these contents:
   ```
   DB_HOST=localhost
   DB_NAME=digitallami_partybooking
   DB_USER=digitallami_partybooking
   DB_PASS=P@sswo0rdms
   ```

3. **Set proper permissions**:
   ```bash
   chmod 755 uploads/
   chmod 644 .env
   ```

### Step 3: Login & Secure Admin

1. **Access admin panel**: `https://yoursite.com/admin/`
2. **Login with default credentials**:
   - Username: `admin`
   - Password: `Admin@123`
3. **⚠️ IMMEDIATELY change the admin password!**
   - Go to: Settings → Change Password
   - Use a strong password

## ✅ What's Included in the Database

### Complete System Setup

- ✅ **18 Database Tables** - All required tables with proper relationships
- ✅ **Default Admin User** - Username: admin, Password: Admin@123
- ✅ **System Settings** - Currency (NPR), Tax Rate (13%), etc.
- ✅ **4 Sample Venues** - Royal Palace, Garden View Hall, etc.
- ✅ **8 Sample Halls** - Various capacities from 300 to 1000 guests
- ✅ **5 Food Menus** - From Bronze (NPR 1,499) to Platinum (NPR 2,999) per person
- ✅ **8 Additional Services** - Decoration, Photography, DJ, etc.
- ✅ **4 Payment Methods** - Bank Transfer, eSewa, Khalti, Cash
- ✅ **7 Sample Customers** - Test customer data
- ✅ **4 Sample Bookings** - Including booking #23 and #37 for testing

### Test Data for Demonstration

The database includes comprehensive test data so you can:
- See how the system works immediately
- Test all features (booking, payments, etc.)
- Show the system to clients/users
- Replace with real data when ready

## 📊 Database Tables Overview

| Table | Purpose |
|-------|---------|
| `venues` | Venue information |
| `halls` | Halls/rooms in venues |
| `hall_images` | Hall photo galleries |
| `menus` | Food menu packages |
| `menu_items` | Items in each menu |
| `hall_menus` | Which menus are available for which halls |
| `additional_services` | Extra services (decoration, DJ, etc.) |
| `customers` | Customer information |
| `bookings` | Booking records |
| `booking_menus` | Menus selected for bookings |
| `booking_services` | Services selected for bookings |
| `payment_methods` | Available payment methods |
| `booking_payment_methods` | Payment methods for each booking |
| `payments` | Payment transaction records |
| `users` | Admin users |
| `settings` | System settings (key-value pairs) |
| `activity_logs` | User activity tracking |
| `site_images` | Dynamic site images |

## 🔒 Important Security Steps (DO NOT SKIP!)

### 1. Change Admin Password
After first login, immediately change the default password:
- Go to: Admin Panel → Settings → Change Password
- Use a strong password with at least 12 characters

### 2. Update Company Information
Update your business details:
- Go to: Admin Panel → Settings
- Update company name, address, phone, email
- Configure tax rates if different from 13%

### 3. Configure Payment Methods
Before accepting payments:
- Go to: Admin Panel → Payment Methods
- Update bank details with your real account information
- Add QR codes for eSewa/Khalti if using
- Activate payment methods when ready

### 4. Secure .env File
```bash
chmod 600 .env  # Only owner can read/write
```

### 5. Remove Test Data (Optional)
When ready to go live with real data:
- Delete sample bookings: Admin Panel → Bookings → Delete test bookings
- Delete sample customers if not needed
- Keep venues/halls/menus or replace with your own

## 🧪 Verify Installation

### Check Database Import Success

1. **In phpMyAdmin**, run this query:
   ```sql
   SHOW TABLES;
   ```
   You should see 18 tables.

2. **Check sample data**:
   ```sql
   SELECT * FROM bookings WHERE id IN (23, 37);
   ```
   Should return 2 bookings.

3. **Check admin user**:
   ```sql
   SELECT username, full_name FROM users WHERE role = 'admin';
   ```
   Should show: admin, System Administrator

### Check Website

1. **Frontend**: Visit `https://yoursite.com/`
   - Should show the booking system homepage
   - Try browsing available venues and halls

2. **Admin Panel**: Visit `https://yoursite.com/admin/`
   - Login with: admin / Admin@123
   - You should see the dashboard with sample data

## 🔧 Troubleshooting

### "Access denied" or "Cannot connect to database"

**Check your .env file**:
```bash
cat .env
```
Make sure credentials match:
```
DB_HOST=localhost
DB_NAME=digitallami_partybooking
DB_USER=digitallami_partybooking
DB_PASS=P@sswo0rdms
```

### "Table doesn't exist"

The database import might have failed. Try again:
1. In phpMyAdmin, select database `digitallami_partybooking`
2. Drop all tables (if any exist)
3. Re-import `database/production-shared-hosting.sql`

### "Permission denied" for uploads

Set proper permissions:
```bash
chmod 755 uploads/
chmod 755 uploads/halls/
chmod 755 uploads/menus/
chmod 755 uploads/services/
chmod 755 uploads/venues/
chmod 755 uploads/payments/
```

### Missing PHP extensions

The system requires:
- PHP 7.4 or higher
- MySQLi extension
- GD extension (for image processing)
- Session support

Check with your hosting provider if any are missing.

## 📝 Sample Booking Details

### Booking #23
- Event: Wedding Reception
- Date: April 10, 2026
- Hall: Rose Hall (250 guests)
- Menu: Silver Deluxe Menu
- Services: Flower Decoration, Stage Decoration, Photography, Valet Parking
- Total: NPR 604,267.50

### Booking #37
- Event: Wedding Ceremony
- Date: May 20, 2026
- Hall: Sagarmatha Hall (600 guests)
- Menu: Royal Gold Menu
- Services: Flower Decoration, Stage Decoration, Photography, Videography
- Total: NPR 1,570,022.00

## 🎨 Customization After Setup

### Update Settings
- **Site Name**: Admin → Settings → Site Name
- **Contact Info**: Admin → Settings → Contact Email/Phone
- **Tax Rate**: Admin → Settings → Tax Rate
- **Currency**: Admin → Settings → Currency

### Add Your Venues
- Go to: Admin Panel → Venues → Add Venue
- Upload photos for each hall
- Set pricing and capacity

### Customize Menus
- Go to: Admin Panel → Menus → Edit
- Update prices, items, descriptions
- Add/remove menu items as needed

### Configure Services
- Go to: Admin Panel → Services → Edit
- Update service prices
- Add custom services

## 📞 Support

If you encounter any issues:

1. **Check the logs**: Look for error messages in browser console (F12)
2. **Verify database connection**: Test .env credentials
3. **Check file permissions**: uploads/ directory needs write access
4. **PHP errors**: Check hosting error logs

## 🎉 You're All Set!

Your Venue Booking System is now ready for production use with:
- ✅ Complete database with 18 tables
- ✅ Sample venues, halls, and menus
- ✅ Test bookings for demonstration
- ✅ Admin panel configured
- ✅ Payment tracking system
- ✅ Customer management

**Next Steps**:
1. Change admin password ⚠️
2. Update company information
3. Customize venues/halls/menus for your business
4. Configure payment methods
5. Test the booking flow
6. Go live!

---

**Database Version**: 2.0 (Production Edition)  
**Last Updated**: January 2026  
**Tested on**: Shared Hosting with cPanel & phpMyAdmin
