# Additional Services Display - Troubleshooting Guide

## Overview

This guide helps diagnose and fix issues with additional services not displaying in booking views. Based on a comprehensive code review, the application code is correctly implemented. If services are not displaying, the issue is likely related to data or configuration.

## Quick Diagnosis

Run the diagnostic test script to identify the issue:

```bash
# Navigate to your application directory
cd /path/to/venubooking

# Access the diagnostic script in your browser
http://yourdomain.com/test-services-display.php
```

The diagnostic script will automatically:
- ✅ Check database connection
- ✅ Verify table structure
- ✅ Count existing service data
- ✅ Test the `getBookingDetails()` function
- ✅ Provide specific recommendations

## Code Verification ✅

The following components have been verified to be working correctly:

### 1. Booking Flow (User-facing)

| File | Status | Description |
|------|--------|-------------|
| `booking-step4.php` | ✅ Correct | Users can select services via checkboxes `name="services[]"` |
| `booking-step5.php` | ✅ Correct | Services saved to session and displayed in summary |
| `booking-step5.php` (POST) | ✅ Correct | Services passed to `createBooking()` function |
| `confirmation.php` | ✅ Correct | Services displayed after booking completion |

### 2. Data Layer

| Function | Location | Status | Description |
|----------|----------|--------|-------------|
| `calculateBookingTotal()` | `includes/functions.php:70-114` | ✅ Correct | Properly includes services in total calculation |
| `createBooking()` | `includes/functions.php:116-448` | ✅ Correct | Inserts services into `booking_services` with denormalized data |
| `getBookingDetails()` | `includes/functions.php:450-522` | ✅ Correct | Retrieves services with LEFT JOIN for description/category |

### 3. Admin Panel

| File | Status | Description |
|------|--------|-------------|
| `admin/bookings/view.php` (lines 754-806) | ✅ Correct | Screen view displays services in table |
| `admin/bookings/view.php` (lines 275-289) | ✅ Correct | Print invoice includes services as line items |
| `admin/bookings/view.php` (lines 1043-1047) | ✅ Correct | Sidebar shows services total |
| `admin/bookings/edit.php` | ✅ Correct | Allows editing services for existing bookings |

### 4. Database Schema

| Table | Status | Description |
|-------|--------|-------------|
| `additional_services` | ✅ Correct | Master table for available services |
| `booking_services` | ✅ Correct | Junction table with denormalized data (service_name, price) |

## Common Issues and Solutions

### Issue 1: No Services to Select

**Symptoms:**
- Users see "No additional services available at this time" on booking-step4.php
- Admin can create bookings but no services appear

**Cause:**
- No active services exist in the `additional_services` table

**Solution:**
```sql
-- Check if services exist
SELECT * FROM additional_services WHERE status = 'active';

-- If empty, add sample services
INSERT INTO additional_services (name, description, price, category, status) VALUES
('Flower Decoration', 'Beautiful flower arrangements for your event', 15000.00, 'Decoration', 'active'),
('Stage Decoration', 'Professional stage setup and decoration', 25000.00, 'Decoration', 'active'),
('Photography Package', 'Full-day professional photography service', 30000.00, 'Photography', 'active'),
('Valet Parking', 'Professional valet parking service for guests', 10000.00, 'Services', 'active');
```

Or add services through the admin panel:
1. Go to **Admin Panel** → **Services**
2. Click **"Add New Service"**
3. Fill in service details and click **Save**

### Issue 2: Bookings Don't Have Services

**Symptoms:**
- Services exist in `additional_services` table
- View booking page shows no services section
- Diagnostic script shows "0 booking services"

**Cause:**
- Bookings were created without selecting services
- OR bookings were created before services feature was added

**Solution:**

**Option A:** Create a new test booking with services
1. Go to the booking page as a customer
2. Complete steps 1-3 (details, venue, menu)
3. On step 4 - Select at least one service
4. Complete the booking
5. View the booking in admin panel

**Option B:** Edit an existing booking to add services
1. Go to **Admin Panel** → **Bookings**
2. Click **Edit** on any booking
3. Select services in the "Additional Services" section
4. Click **Save Changes**

**Option C:** Manually add services to a booking (database)
```sql
-- Replace 1 with your actual booking ID
-- Replace service IDs with actual service IDs from your database
INSERT INTO booking_services (booking_id, service_id, service_name, price)
SELECT 1, id, name, price 
FROM additional_services 
WHERE id IN (1, 2, 3)
LIMIT 3;

-- Recalculate and update booking totals
UPDATE bookings b
SET services_total = (
    SELECT COALESCE(SUM(price), 0) 
    FROM booking_services 
    WHERE booking_id = b.id
),
subtotal = hall_price + menu_total + (
    SELECT COALESCE(SUM(price), 0) 
    FROM booking_services 
    WHERE booking_id = b.id
),
tax_amount = (hall_price + menu_total + (
    SELECT COALESCE(SUM(price), 0) 
    FROM booking_services 
    WHERE booking_id = b.id
)) * 0.13,
grand_total = (hall_price + menu_total + (
    SELECT COALESCE(SUM(price), 0) 
    FROM booking_services 
    WHERE booking_id = b.id
)) * 1.13
WHERE id = 1;
```

### Issue 3: Services Showing NULL Description/Category

**Symptoms:**
- Services display but show "NULL" or empty description/category
- Everything else works fine

**Cause:**
- The service was deleted from `additional_services` master table after booking
- LEFT JOIN returns NULL for deleted services' description/category

**Solution:**

This is actually **expected behavior** and demonstrates the system working correctly! The service name and price are preserved from the booking time (denormalized data), but description/category come from the current master table.

If you want to also preserve description/category:

```sql
-- Add description and category columns to booking_services table
ALTER TABLE booking_services 
ADD COLUMN description TEXT AFTER price,
ADD COLUMN category VARCHAR(100) AFTER description;

-- Update future bookings to save description/category
-- Modify includes/functions.php createBooking() around line 427:
```

```php
// Change from:
$stmt = $db->prepare("SELECT name, price FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if ($service) {
    $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$booking_id, $service_id, $service['name'], $service['price']]);
}

// Change to:
$stmt = $db->prepare("SELECT name, price, description, category FROM additional_services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if ($service) {
    $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$booking_id, $service_id, $service['name'], $service['price'], $service['description'], $service['category']]);
}
```

**Note:** This is optional and only needed if you want to preserve description/category even after service deletion.

### Issue 4: Services Not Showing in Print Invoice

**Symptoms:**
- Services show on screen but not in print view
- Print button works but invoice is incomplete

**Cause:**
- Browser print CSS not loading
- JavaScript blocking print

**Solution:**

1. **Clear browser cache** and try again
2. Check browser console (F12) for errors
3. Try different browser
4. Use "Print Preview" to see if services appear before actual print
5. Check the print CSS is loaded:

```html
<!-- Should be present in view.php around line 1087 -->
<style>
@media print {
    /* Print styles for services */
}
</style>
```

### Issue 5: Booking Created But Services Missing

**Symptoms:**
- User completes booking with services
- Confirmation page shows services
- But viewing booking in admin shows no services

**Cause:**
- Transaction rollback or error during booking creation
- Database connection lost mid-transaction

**Solution:**

1. Check PHP error logs:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

2. Check for transaction errors:
```sql
-- Check if booking exists but services don't
SELECT b.id, b.booking_number, 
       (SELECT COUNT(*) FROM booking_services WHERE booking_id = b.id) as service_count
FROM bookings b
WHERE b.services_total > 0
HAVING service_count = 0;
```

3. If found, the transaction failed partially. Either:
   - Re-create the booking
   - Or manually add services as shown in Issue 2, Option C

## Validation Checklist

Use this checklist to verify everything is working:

- [ ] **Database Connection**: Diagnostic script connects successfully
- [ ] **Table Structure**: `booking_services` table exists with correct columns
- [ ] **Active Services**: At least one service exists with status='active'
- [ ] **Test Booking**: Create new booking and select at least one service
- [ ] **Service Saved**: Check `booking_services` table has entry for test booking
- [ ] **Screen Display**: Service appears in booking view on admin/bookings/view.php
- [ ] **Sidebar Total**: Services total shows in payment summary sidebar
- [ ] **Print Invoice**: Service appears in print preview/printed invoice
- [ ] **Edit Booking**: Can edit booking and change selected services
- [ ] **Total Calculation**: Grand total correctly includes service prices

## SQL Debugging Queries

Use these queries to investigate service issues:

```sql
-- 1. Check all services
SELECT id, name, price, category, status 
FROM additional_services 
ORDER BY status, category, name;

-- 2. Check booking services for a specific booking (replace 1 with booking_id)
SELECT bs.*, s.status as master_status, s.name as current_name
FROM booking_services bs
LEFT JOIN additional_services s ON bs.service_id = s.id
WHERE bs.booking_id = 1;

-- 3. Find bookings with services_total but no service records
SELECT b.id, b.booking_number, b.services_total,
       (SELECT COUNT(*) FROM booking_services WHERE booking_id = b.id) as actual_services
FROM bookings b
WHERE b.services_total > 0
HAVING actual_services = 0;

-- 4. Verify totals match between booking and services
SELECT b.id, b.booking_number,
       b.services_total as recorded_total,
       COALESCE(SUM(bs.price), 0) as actual_total,
       b.services_total - COALESCE(SUM(bs.price), 0) as difference
FROM bookings b
LEFT JOIN booking_services bs ON b.id = bs.booking_id
GROUP BY b.id
HAVING difference != 0;

-- 5. Get full booking data for debugging
SELECT 
    b.id,
    b.booking_number,
    b.hall_price,
    b.menu_total,
    b.services_total,
    b.subtotal,
    b.tax_amount,
    b.grand_total,
    (SELECT GROUP_CONCAT(service_name SEPARATOR ', ') FROM booking_services WHERE booking_id = b.id) as services
FROM bookings b
WHERE b.id = 1; -- Replace with your booking ID
```

## Still Having Issues?

If services still don't display after following this guide:

1. **Run the diagnostic script** (`test-services-display.php`) and share the output
2. **Check PHP version**: Ensure PHP 7.4+ is installed
3. **Check PDO extension**: Ensure PHP PDO MySQL extension is enabled
4. **Database permissions**: Ensure database user has SELECT permission on `booking_services` and `additional_services`
5. **Clear all caches**: PHP opcache, browser cache, application cache
6. **Check for custom code**: Any custom modifications to core files?

## Security Note

**Important:** After troubleshooting, delete the test script for security:

```bash
rm test-services-display.php
```

## Summary

The venue booking system's additional services feature is **fully functional** in the codebase. If services are not displaying, it's due to:

1. ❌ No active services in database → Add services through admin panel
2. ❌ Bookings created without services → Create new test booking with services
3. ❌ Database connection/permission issues → Run diagnostic script
4. ❌ Browser/cache issues → Clear cache and try different browser

The code correctly:
- ✅ Allows users to select services during booking
- ✅ Saves services to database with denormalized data
- ✅ Calculates totals including services
- ✅ Displays services in booking view (screen and print)
- ✅ Includes services in invoice
- ✅ Preserves historical data even if services are deleted

---

**Last Updated:** 2026-01-16  
**Status:** Code Verified ✅ | Issue is Data/Configuration Related
