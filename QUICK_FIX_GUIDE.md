# Quick Installation Guide - Fix HTTP 500 Error

## Issue: HTTP 500 Error on generate_pdf.php?id=23

This error occurs because:
1. Database tables are missing
2. Booking record with ID=23 doesn't exist
3. Database connection is not configured

## Quick Fix (3 Steps)

### Step 1: Configure Database Connection

Create `.env` file in the root directory:

```bash
cd /path/to/venubooking
cp .env.example .env
nano .env
```

Update with your credentials:
```
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=root
DB_PASS=your_mysql_password
```

### Step 2: Setup Database

**Choose ONE option:**

#### Option A: Fresh Installation (Recommended)
```bash
mysql -u root -p < database/complete-setup.sql
```
This creates everything from scratch including booking #23.

#### Option B: Fix Existing Database
```bash
mysql -u root -p < database/fix-booking-23.sql
```
This only adds booking #23 to existing database.

### Step 3: Test the Fix

Visit in browser:
```
http://your-domain.com/venubooking/generate_pdf.php?id=23
```

Expected result: PDF downloads successfully ✅

## What Gets Fixed

The database setup includes:

### Tables Created (14 total)
- ✅ venues (4 sample venues)
- ✅ halls (8 sample halls)
- ✅ menus (5 menus with items)
- ✅ customers (7 customers including Uttam Acharya)
- ✅ bookings (3 bookings **including ID=23**)
- ✅ booking_menus (relationships)
- ✅ booking_services (services for bookings)
- ✅ additional_services (8 services)
- ✅ users (admin account)
- ✅ settings (system configuration)
- ✅ And more...

### Booking #23 Details
- **Booking Number:** BK-20260125-0023
- **Customer:** Uttam Acharya (+977 9801234567)
- **Event:** Wedding Reception
- **Date:** 2026-04-10
- **Shift:** Evening
- **Guests:** 250
- **Hall:** Rose Hall
- **Status:** Confirmed/Paid
- **Total:** NPR 598,617.50

## Verification Commands

### Check if database exists:
```bash
mysql -u root -p -e "SHOW DATABASES LIKE 'venubooking';"
```

### Check if tables exist:
```bash
mysql -u root -p -e "USE venubooking; SHOW TABLES;"
```

### Check if booking #23 exists:
```bash
mysql -u root -p -e "USE venubooking; SELECT id, booking_number, event_type FROM bookings WHERE id=23;"
```

### Test PHP connection:
```bash
php -r "require 'config/database.php'; require 'includes/db.php'; getDB(); echo 'OK';"
```

## Troubleshooting

### Error: "Access denied for user"
- Check username/password in `.env`
- Grant permissions: `GRANT ALL ON venubooking.* TO 'user'@'localhost';`

### Error: "Database doesn't exist"
- Create manually: `CREATE DATABASE venubooking;`
- Then run: `mysql -u root -p venubooking < database/complete-setup.sql`

### Error: "Table doesn't exist"
- Run complete setup: `mysql -u root -p < database/complete-setup.sql`

### Error: "Booking not found"
- Run fix: `mysql -u root -p < database/fix-booking-23.sql`

### Error: "FPDF library not found"
- Check: `ls lib/fpdf.php`
- Download from: http://www.fpdf.org/

### Still getting HTTP 500?
- Check error log: `cat error_log.txt`
- Check PHP logs: `tail -f /var/log/apache2/error.log`
- Enable debug: Add at top of generate_pdf.php:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```

## Admin Panel Access

After setup, you can login to admin panel:
- URL: `/admin/`
- Username: `admin`
- Password: `Admin@123`
- **⚠️ Change password immediately!**

## Production Checklist

Before going live:
- [ ] Change admin password
- [ ] Update site settings (Admin → Settings)
- [ ] Remove test bookings (optional)
- [ ] Configure backups
- [ ] Enable HTTPS
- [ ] Test all features

## Files Reference

| File | Purpose |
|------|---------|
| `complete-setup.sql` | Complete fresh database setup ✅ |
| `fix-booking-23.sql` | Quick fix to add booking #23 |
| `schema.sql` | Database structure only |
| `sample-data.sql` | Sample data only |
| `DATABASE_FIX_README.md` | Detailed documentation |
| `validate-setup.sh` | Validation script |

## Support

For detailed documentation, see:
- `database/DATABASE_FIX_README.md` - Full documentation
- `INSTALLATION.md` - Complete installation guide
- `README.md` - Project overview

---

**Need Help?** Check the error logs and documentation above.
