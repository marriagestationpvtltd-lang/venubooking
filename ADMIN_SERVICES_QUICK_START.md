# Admin Services Quick Start Guide

## 🚀 Quick Fix (3 Steps)

### Step 1: Check Current Status
Open in browser:
```
http://yoursite.com/test_admin_services.php
```

✅ **All tests pass?** → Skip to Step 3  
❌ **Tests fail?** → Continue to Step 2

---

### Step 2: Apply Database Fix
Open in browser:
```
http://yoursite.com/fix_admin_services.php
```

Click **"Apply Fix Now"** button and wait for confirmation.

---

### Step 3: Test It Works

1. Go to: `Admin Panel → Bookings → View any booking`
2. Scroll to **"Admin Added Services"** section
3. Fill the form:
   - Service Name: `Test Service`
   - Description: `Testing`
   - Quantity: `1`
   - Price: `100`
4. Click **"Add Service"**

✅ **Success!** You should see: "Admin service added successfully!"

---

## 🗑️ Clean Up

After successful testing, delete these files from your server:
- ✅ `test_admin_services.php`
- ✅ `fix_admin_services.php`

---

## 📋 What This Feature Does

### Before Fix
```
❌ Admin tries to add service
❌ Error: "Failed to add admin service"
❌ Service not saved
```

### After Fix
```
✅ Admin can add custom services
✅ Services save to database
✅ Totals automatically recalculate
✅ Services appear in invoice
```

---

## 💡 Use Cases

### Scenario 1: Forgot a Service
Customer booked venue but forgot to add decoration.
→ Admin adds "Decoration Service" for Rs. 5,000

### Scenario 2: Last-Minute Addition
Customer calls day before event to add valet parking.
→ Admin adds service without recreating booking

### Scenario 3: Custom Service
Customer needs special lighting setup not in service list.
→ Admin creates custom one-time service

---

## 🔍 How to Use Admin Services

### Adding a Service

1. Open booking details page
2. Find "Admin Added Services" section (below user services)
3. Fill in:
   - **Service Name**: What is the service? (Required)
   - **Description**: Brief details (Optional)
   - **Quantity**: How many units? (Default: 1)
   - **Price**: Cost per unit (Required)
4. Click "Add Service"

### Viewing Services

Admin services show separately from user services:

**User Services** (Selected during booking):
- ✅ Added by customer
- ❌ Cannot be deleted
- ✅ Links to master service list

**Admin Services** (Added by admin):
- ✅ Added by admin
- ✅ Can be deleted
- ✅ Custom for this booking

### Deleting a Service

Only admin-added services can be deleted:
1. Find service in admin services table
2. Click 🗑️ delete button
3. Confirm deletion
4. Total automatically updates

---

## 📊 Where Services Appear

Admin services appear in:
1. ✅ Booking details page
2. ✅ Payment summary calculations
3. ✅ Printed invoices
4. ✅ Email notifications
5. ✅ PDF exports (if enabled)

---

## ⚠️ Troubleshooting

### Still Getting Error After Fix?

**Check 1: Clear browser cache**
```
Ctrl + F5 (Windows)
Cmd + Shift + R (Mac)
```

**Check 2: Verify columns exist**
Run in database:
```sql
SHOW COLUMNS FROM booking_services;
```
Should see: `added_by` and `quantity` columns

**Check 3: Check PHP error logs**
Look for specific error messages in:
- `/var/log/php/error.log`
- cPanel → Error Logs

**Check 4: Database permissions**
Ensure user has ALTER TABLE permission:
```sql
SHOW GRANTS FOR 'your_user'@'localhost';
```

---

## 🆘 Need Help?

**Error: "Unknown column 'added_by'"**
→ Run `fix_admin_services.php` again

**Error: "Permission denied"**
→ Contact hosting provider to grant ALTER TABLE permission

**Services not in totals**
→ Total should auto-calculate. If not, edit booking to recalculate.

**Can't delete user services**
→ This is by design! Only admin services can be deleted.

---

## 🎓 For Developers

### Database Schema
```sql
CREATE TABLE booking_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    added_by ENUM('user', 'admin') DEFAULT 'user',  -- NEW
    quantity INT DEFAULT 1,                         -- NEW
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

### Key Functions
- `addAdminService()` - Add service to booking
- `deleteAdminService()` - Delete admin service
- `recalculateBookingTotals()` - Update booking totals
- `getAdminServices()` - Get admin services for booking
- `getUserServices()` - Get user services for booking

### Code Location
- Form: `admin/bookings/view.php` (line 1076-1113)
- Handler: `admin/bookings/view.php` (line 34-59)
- Functions: `includes/functions.php` (line 2020-2216)

---

## ✅ Success Checklist

- [ ] Ran test script - all tests pass
- [ ] Applied database fix
- [ ] Successfully added test service
- [ ] Service appears in booking details
- [ ] Service included in total calculation
- [ ] Service appears in printed invoice
- [ ] Deleted test service successfully
- [ ] Removed test and fix scripts from server

---

## 📚 More Information

Full documentation: See `FIX_ADMIN_SERVICES.md`

Issues? Check error logs and documentation first.
