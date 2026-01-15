# Admin Panel Booking View Enhancement - Deployment Guide

## Quick Start

This guide helps you deploy the enhanced booking view improvements to your production environment.

## Prerequisites

- Access to MySQL/MariaDB database
- PHP 7.4+ installed
- Backup of your database (IMPORTANT!)

## Deployment Steps

### Step 1: Backup Database
```bash
mysqldump -u username -p venubooking > venubooking_backup_$(date +%Y%m%d).sql
```

### Step 2: Run Database Migration
```bash
# Navigate to your project directory
cd /path/to/venubooking

# Run the migration script
mysql -u username -p venubooking < database/update-payment-status-enum.sql
```

This migration will:
- Convert all existing 'unpaid' payment statuses to 'pending'
- Update the payment_status enum to: pending, partial, paid, cancelled
- Set default payment status to 'pending'

### Step 3: Deploy Files

Upload/sync the following files to your server:

**Modified Files:**
```
admin/bookings/index.php
admin/bookings/add.php
admin/bookings/edit.php
database/schema.sql
database/complete-setup.sql
```

**New Files:**
```
admin/bookings/update-payment-status.php
database/update-payment-status-enum.sql
BOOKING_VIEW_IMPROVEMENTS.md
```

### Step 4: Set File Permissions
```bash
chmod 644 admin/bookings/*.php
chmod 644 database/*.sql
```

### Step 5: Test the Changes

1. **Login to Admin Panel:**
   - Navigate to: https://yoursite.com/admin/
   - Login with admin credentials

2. **Check Bookings List:**
   - Go to: Bookings → Manage Bookings
   - Verify the enhanced layout is displayed
   - Check that payment status dropdowns are visible

3. **Test Payment Status Update:**
   - Select a booking with 'Pending' status
   - Change to 'Partial' using the dropdown
   - Confirm the change
   - Verify toast notification appears
   - Check that the status updates immediately

4. **Verify Database:**
   ```sql
   -- Check the enum values
   SHOW COLUMNS FROM bookings LIKE 'payment_status';
   
   -- Check activity logs
   SELECT * FROM activity_logs 
   WHERE action = 'Updated payment status' 
   ORDER BY created_at DESC 
   LIMIT 5;
   ```

## Rollback Procedure

If you encounter issues, you can rollback:

### Option 1: Restore from Backup
```bash
mysql -u username -p venubooking < venubooking_backup_YYYYMMDD.sql
```

### Option 2: Manual Rollback
```sql
-- Revert payment_status enum
ALTER TABLE bookings 
MODIFY COLUMN payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid';

-- Convert 'pending' back to 'unpaid'
UPDATE bookings 
SET payment_status = 'unpaid' 
WHERE payment_status = 'pending';
```

Then restore the old PHP files from your backup.

## Verification Checklist

After deployment, verify:

- [ ] Database migration completed successfully
- [ ] No PHP errors in logs
- [ ] Enhanced booking list displays correctly
- [ ] Payment status dropdowns are functional
- [ ] AJAX updates work without page reload
- [ ] Toast notifications appear
- [ ] Activity logs are being created
- [ ] All existing bookings display correctly
- [ ] Can create new bookings
- [ ] Can edit existing bookings
- [ ] Payment status colors are correct

## Troubleshooting

### Issue: "Invalid payment status" error
**Solution:** Make sure the database migration ran successfully. Check the enum values:
```sql
SHOW COLUMNS FROM bookings LIKE 'payment_status';
```

### Issue: AJAX update not working
**Solution:** 
1. Check browser console for JavaScript errors
2. Verify update-payment-status.php is accessible
3. Check file permissions (should be 644)
4. Verify user is logged in as admin

### Issue: Toast notifications not appearing
**Solution:**
1. Check Bootstrap 5 is loaded correctly
2. Check browser console for JavaScript errors
3. Verify no Content Security Policy blocking

### Issue: Old statuses showing
**Solution:**
1. Clear browser cache
2. Check database to ensure migration ran
3. Verify correct PHP files are deployed

## Performance Notes

- The enhanced view includes a subquery to calculate total_paid, which may be slower on large datasets
- Consider adding an index on payment_status if you have >10,000 bookings:
  ```sql
  CREATE INDEX idx_payment_status ON bookings(payment_status);
  ```

## Security Notes

- All payment status updates are logged with user ID and timestamp
- AJAX endpoint requires admin authentication
- SQL injection protection via prepared statements
- XSS protection via htmlspecialchars()

## Support

For issues or questions:
1. Check activity_logs table for error details
2. Review browser console for JavaScript errors
3. Check PHP error logs
4. Refer to BOOKING_VIEW_IMPROVEMENTS.md for detailed documentation

## Next Steps

After successful deployment:
1. Train admin users on the new interface
2. Monitor activity logs for any issues
3. Gather user feedback
4. Consider implementing future enhancements (see BOOKING_VIEW_IMPROVEMENTS.md)

## Maintenance

- Regularly backup your database
- Monitor activity_logs table size (consider archiving old logs)
- Keep Bootstrap and Font Awesome updated
- Review payment status flow if business requirements change

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Database Backup Location:** _______________  
**Rollback Plan Verified:** [ ] Yes [ ] No
