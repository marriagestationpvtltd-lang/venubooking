# Email Notification System - Verification Checklist

## Pre-Installation Verification

### Code Files Added/Modified
- [x] `includes/functions.php` - Email functions added
- [x] `admin/bookings/add.php` - Email notification on create
- [x] `admin/bookings/edit.php` - Email notification on update
- [x] `admin/settings/index.php` - Email settings UI tab
- [x] `database/migrations/add_email_settings.sql` - Migration file

### Documentation Files
- [x] `EMAIL_NOTIFICATION_GUIDE.md` - User guide
- [x] `EMAIL_IMPLEMENTATION_SUMMARY.md` - Technical summary
- [x] `setup-email-notifications.sh` - Setup script

## Installation Steps

### 1. Run Database Migration

```bash
# Option A: Using the setup script (recommended)
bash setup-email-notifications.sh

# Option B: Manual MySQL
mysql -u username -p venubooking < database/migrations/add_email_settings.sql
```

**Verify**: Check that 10 email-related settings exist in the `settings` table:
```sql
SELECT setting_key, setting_value FROM settings 
WHERE setting_key LIKE 'email_%' 
   OR setting_key LIKE 'smtp_%' 
   OR setting_key = 'admin_email';
```

Expected settings:
1. email_enabled
2. email_from_name
3. email_from_address
4. admin_email
5. smtp_enabled
6. smtp_host
7. smtp_port
8. smtp_username
9. smtp_password
10. smtp_encryption

## Post-Installation Verification

### 2. Admin Panel Configuration

**Test**: Can access email settings
- [ ] Navigate to Admin Panel → Settings
- [ ] Email Settings tab is visible
- [ ] All email configuration fields are present
- [ ] Can save email settings without errors

### 3. Email Settings Configuration

**Test**: Configure basic email settings
- [ ] Set "Enable Email Notifications" to "Enabled"
- [ ] Set "Admin Email Address" to a valid email you can access
- [ ] Set "From Name" (e.g., "Venue Booking System")
- [ ] Set "From Email Address" (e.g., "noreply@yourdomain.com")
- [ ] Click "Save Settings"
- [ ] Success message appears

### 4. SMTP Configuration (Optional but Recommended)

**Test**: Configure SMTP for better deliverability

For Gmail:
- [ ] Set "Enable SMTP" to "Enabled"
- [ ] Set "SMTP Host" to "smtp.gmail.com"
- [ ] Set "SMTP Port" to "587"
- [ ] Set "Encryption" to "TLS"
- [ ] Set "Username" to your Gmail address
- [ ] Set "Password" to your Gmail App Password
- [ ] Click "Save Settings"

**Note**: For Gmail, you need an App Password:
1. Go to https://myaccount.google.com/apppasswords
2. Generate a new app password for "Mail"
3. Use that password in SMTP settings

### 5. Test New Booking Email (Frontend)

**Test**: Email sent when customer creates booking

Steps:
1. [ ] Go to the public booking page (frontend)
2. [ ] Fill in booking details:
   - Event type, date, shift, number of guests
   - Select venue and hall
   - Select menus (optional)
   - Select services (optional)
3. [ ] **Important**: Enter a valid email address you can access
4. [ ] Complete the booking
5. [ ] Check your email inbox (and spam folder)

**Verify**:
- [ ] Customer receives "Booking Confirmation" email
- [ ] Admin receives "New Booking Received" email
- [ ] Emails contain:
  - [ ] Booking number
  - [ ] Customer information
  - [ ] Event details (date, shift, type, guests)
  - [ ] Venue and hall information
  - [ ] Selected menus with items
  - [ ] Selected services
  - [ ] Complete cost breakdown
  - [ ] Status badges (Pending, Unpaid)

### 6. Test New Booking Email (Admin Panel)

**Test**: Email sent when admin creates booking

Steps:
1. [ ] Login to Admin Panel
2. [ ] Go to Bookings → Add New Booking
3. [ ] Fill in all booking details
4. [ ] **Important**: Include customer email address
5. [ ] Submit the booking

**Verify**:
- [ ] Redirected to booking view page
- [ ] Check email inboxes
- [ ] Both customer and admin received emails
- [ ] Emails show correct booking information

### 7. Test Booking Update Email

**Test**: Email sent when booking status changes

Steps:
1. [ ] Go to Admin Panel → Bookings
2. [ ] Click "Edit" on a booking
3. [ ] Change "Booking Status" (e.g., Pending → Confirmed)
4. [ ] Click "Update Booking"
5. [ ] Success message appears

**Verify**:
- [ ] Customer receives "Booking Status Updated" email
- [ ] Admin receives "Booking Updated" email
- [ ] Email shows: "Previous Status: Pending → New Status: Confirmed"
- [ ] All booking details are included

**Test**: Email sent when payment status changes

Steps:
1. [ ] Edit a booking
2. [ ] Change "Payment Status" (e.g., Unpaid → Paid)
3. [ ] Click "Update Booking"

**Verify**:
- [ ] Email notifications sent for payment status change
- [ ] Email reflects the payment status update

### 8. Test Email Not Sent When Status Unchanged

**Test**: No email when updating booking without status change

Steps:
1. [ ] Edit a booking
2. [ ] Change only the number of guests or special requests
3. [ ] Keep booking_status and payment_status the same
4. [ ] Click "Update Booking"

**Verify**:
- [ ] No new emails sent (status didn't change)
- [ ] Booking updates successfully

### 9. HTML Email Rendering

**Test**: Email displays correctly in different email clients

Check email in:
- [ ] Gmail (web)
- [ ] Gmail (mobile app)
- [ ] Outlook (web)
- [ ] Outlook (desktop)
- [ ] Apple Mail
- [ ] Any other email client you use

**Verify**:
- [ ] HTML renders correctly
- [ ] Colors display properly
- [ ] Status badges visible
- [ ] Layout is readable
- [ ] No broken elements

### 10. Error Handling

**Test**: System handles email failures gracefully

Scenario A: Invalid customer email
1. [ ] Create booking with invalid email (e.g., "test@")
2. [ ] Booking should still be created successfully
3. [ ] Admin email still sent
4. [ ] Check error logs for validation message

Scenario B: SMTP failure (if using SMTP)
1. [ ] Enter wrong SMTP password
2. [ ] Create a booking
3. [ ] Booking should still be created
4. [ ] Check error logs for SMTP error

**Verify**:
- [ ] Booking creation never fails due to email issues
- [ ] Errors are logged but don't break functionality
- [ ] User sees success message even if email fails

### 11. Email Settings Disable

**Test**: Can disable email notifications

Steps:
1. [ ] Go to Settings → Email Settings
2. [ ] Set "Enable Email Notifications" to "Disabled"
3. [ ] Save settings
4. [ ] Create a new booking

**Verify**:
- [ ] No emails sent (admin or customer)
- [ ] Booking still creates successfully
- [ ] Re-enable emails and verify they work again

## Common Issues & Solutions

### Issue: No emails received

**Check**:
1. Spam/junk folder
2. Email address is correct
3. Email notifications are enabled
4. Admin email is configured
5. PHP error logs: `tail -f /var/log/php/error.log`

**Solution**:
- Verify settings in admin panel
- Try different email address
- Check server mail configuration
- Try enabling SMTP

### Issue: SMTP authentication failed

**Check**:
1. SMTP credentials are correct
2. Port matches encryption (587=TLS, 465=SSL)
3. Username format (some require full email, others just username)
4. For Gmail: using App Password, not regular password

**Solution**:
- Double-check all SMTP settings
- Try different SMTP port
- Verify credentials by logging into email account
- Temporarily disable SMTP to use PHP mail()

### Issue: Emails missing booking details

**Check**:
1. Booking was created successfully in database
2. `getBookingDetails()` returns data
3. PHP error logs for warnings

**Solution**:
- Verify booking_id is correct
- Check database tables have all data
- Review error logs

### Issue: HTML not rendering

**Check**:
1. Email client supports HTML
2. Email has correct MIME headers

**Solution**:
- Test in different email clients
- Check email source/raw view
- Verify content-type header is set

## Performance Testing

### Load Testing (Optional)

Test email system under load:
1. Create 10 bookings in quick succession
2. All should complete successfully
3. Check that all emails are sent
4. Monitor server logs for errors

## Security Verification

- [ ] Email addresses are validated before sending
- [ ] All user input in emails is sanitized (htmlspecialchars)
- [ ] No SQL injection possible (prepared statements used)
- [ ] Error messages don't expose sensitive info
- [ ] SMTP password stored (consider encryption for production)

## Sign-Off

### Functional Requirements
- [ ] ✓ Email sent to admin when booking is created
- [ ] ✓ Email sent to user when booking is created
- [ ] ✓ Email includes all booking details
- [ ] ✓ Email sent to admin when status is updated
- [ ] ✓ Email sent to user when status is updated
- [ ] ✓ Emails are automatic (no manual sending)
- [ ] ✓ Email settings configurable from admin panel
- [ ] ✓ Frontend and backend use same booking data

### Technical Requirements
- [ ] ✓ No breaking changes to existing functionality
- [ ] ✓ PHP syntax is valid
- [ ] ✓ Database migration available
- [ ] ✓ Documentation provided
- [ ] ✓ Error handling implemented
- [ ] ✓ Security measures in place

## Completion

Date: _________________

Tester: _________________

Notes:
_____________________________________________________
_____________________________________________________
_____________________________________________________
_____________________________________________________

All tests passed: [ ] YES  [ ] NO

If NO, list issues:
_____________________________________________________
_____________________________________________________
_____________________________________________________
