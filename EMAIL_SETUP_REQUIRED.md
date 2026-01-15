# 📧 Email Setup Required - Why Emails Are Not Working

## 🔴 Problem Identified

Your venue booking system has email functionality **already implemented in the code**, but emails are not being sent because **the email settings have not been configured in your database**.

## 🎯 What's Happening

When a booking is created, the system tries to:
1. ✅ Call the `sendBookingNotification()` function (this works)
2. ❌ Retrieve email settings from the database (these don't exist)
3. ❌ Send emails to admin and customer (fails silently)

The booking is created successfully, but the email sending fails silently because:
- Email settings don't exist in the database
- Admin email address is not configured
- SMTP settings (if needed) are not configured

## 🔍 Diagnostic Tool

**First, run the diagnostic tool to check your configuration:**

### Via Web Browser:
```
http://your-domain.com/check-email-setup.php
```

### Via Command Line:
```bash
cd /path/to/venubooking
php check-email-setup.php
```

This tool will tell you exactly what's missing and how to fix it.

## 🛠️ Complete Setup Instructions

### Step 1: Setup Database Settings

You need to add email configuration settings to your database. Choose one method:

#### Method A: Automated Script (Easiest)
```bash
cd /path/to/venubooking
mysql -u your_username -p venubooking < database/migrations/add_email_settings.sql
```

Replace:
- `your_username` with your MySQL username
- `venubooking` with your database name

#### Method B: Manual SQL (If you use phpMyAdmin)

1. Open phpMyAdmin
2. Select your `venubooking` database
3. Click on "SQL" tab
4. Copy and paste this SQL:

```sql
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('email_enabled', '1', 'boolean'),
('email_from_name', 'Venue Booking System', 'text'),
('email_from_address', 'noreply@venubooking.com', 'text'),
('admin_email', 'admin@venubooking.com', 'text'),
('smtp_enabled', '0', 'boolean'),
('smtp_host', '', 'text'),
('smtp_port', '587', 'number'),
('smtp_username', '', 'text'),
('smtp_password', '', 'password'),
('smtp_encryption', 'tls', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

5. Click "Go" to execute

### Step 2: Configure Email Settings via Admin Panel

1. **Login to Admin Panel**
   - Go to: `http://your-domain.com/admin/`
   - Login with your admin credentials

2. **Navigate to Settings**
   - Click on "Settings" in the navigation
   - Click on "Email Settings" tab

3. **Configure Basic Email Settings**
   - ✅ **Enable Email Notifications**: Set to "Enabled"
   - ✅ **Admin Email Address**: Enter YOUR email address (e.g., `admin@yourdomain.com`)
   - ✅ **From Name**: Enter your business name (e.g., "ABC Venue Booking")
   - ✅ **From Email**: Enter a sender email (e.g., `noreply@yourdomain.com`)

4. **Choose Email Sending Method**

   You have two options:

   #### Option A: Use PHP mail() - Simple but Less Reliable
   - Leave "Enable SMTP" as "No"
   - This uses your server's built-in mail function
   - ⚠️ May not work if your server doesn't have sendmail configured
   - ⚠️ Emails may go to spam

   #### Option B: Use SMTP - Recommended for Production
   - Set "Enable SMTP" to "Yes"
   - Configure SMTP settings (see below)
   - ✅ More reliable
   - ✅ Better deliverability
   - ✅ Less likely to be marked as spam

5. **Click "Save Settings"**

### Step 3: Configure SMTP (Recommended)

If you chose SMTP, you need to configure these settings:

#### Using Gmail:
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
SMTP Encryption: TLS
SMTP Username: your-email@gmail.com
SMTP Password: [App Password - see note below]
```

**Important for Gmail:**
- You CANNOT use your regular Gmail password
- You MUST create an "App Password":
  1. Go to: https://myaccount.google.com/apppasswords
  2. Generate a new app password for "Mail"
  3. Use that 16-character password in SMTP settings

#### Using SendGrid:
```
SMTP Host: smtp.sendgrid.net
SMTP Port: 587
SMTP Encryption: TLS
SMTP Username: apikey
SMTP Password: [Your SendGrid API Key]
```

#### Using Other SMTP Providers:
Contact your email hosting provider for SMTP settings. You'll need:
- SMTP hostname
- SMTP port (usually 587 for TLS or 465 for SSL)
- SMTP username (usually your email address)
- SMTP password
- Encryption type (TLS or SSL)

### Step 4: Test Email Functionality

1. **Create a Test Booking**
   - Go to your booking page
   - Fill in all booking details
   - **IMPORTANT**: Use a **valid email address** that you can check
   - Complete the booking

2. **Check Email Delivery**
   - Check the customer email inbox (the email you entered)
   - Check the admin email inbox (the email you configured in settings)
   - ⚠️ **Check spam/junk folders** - emails might be there initially

3. **Verify Email Content**
   - Email should contain booking number
   - Email should have customer and event details
   - Email should have venue and hall information
   - Email should have cost breakdown

### Step 5: Troubleshooting

If emails are still not working:

#### 1. Run Diagnostic Tool Again
```
http://your-domain.com/check-email-setup.php
```

#### 2. Check PHP Error Logs
```bash
# Linux/Mac
tail -f /var/log/php/error.log
# or
tail -f /var/log/apache2/error.log

# Check your hosting control panel for error logs
```

#### 3. Common Issues & Solutions

**Issue**: "Email settings not found in database"
- **Solution**: Run Step 1 again to add email settings

**Issue**: "Admin email is empty"
- **Solution**: Configure admin email in Admin Panel → Settings → Email Settings

**Issue**: "SMTP connection failed"
- **Solution**: 
  - Verify SMTP credentials are correct
  - Check SMTP host and port
  - Ensure your hosting allows outbound SMTP connections
  - Try disabling SMTP and using PHP mail() instead

**Issue**: "mail() function not available"
- **Solution**: 
  - Enable SMTP instead
  - Contact your hosting provider to enable sendmail

**Issue**: "Emails going to spam"
- **Solution**:
  - Use SMTP instead of PHP mail()
  - Use a professional email service (Gmail, SendGrid)
  - Configure SPF and DKIM records for your domain

**Issue**: "Customer not receiving emails"
- **Solution**:
  - Verify customer provided a valid email address
  - Check if customer email is filled in the booking form
  - Email field is optional - ask customers to provide it

## 📋 Quick Checklist

- [ ] Run database migration to add email settings
- [ ] Login to Admin Panel
- [ ] Go to Settings → Email Settings
- [ ] Set Admin Email to your email address
- [ ] Enable Email Notifications
- [ ] Configure From Name and From Email
- [ ] Choose email method (PHP mail or SMTP)
- [ ] If using SMTP, configure all SMTP settings
- [ ] Save Settings
- [ ] Create test booking with valid email
- [ ] Check both admin and customer inboxes (including spam)
- [ ] Verify email content is correct

## 🎓 Why This Setup Is Needed

The email system was implemented but requires configuration because:

1. **Database-Driven Configuration**: Email settings are stored in the database for flexibility
2. **Admin Control**: Admins can change email settings without code changes
3. **Security**: SMTP credentials should not be hardcoded in code
4. **Flexibility**: Different environments (dev/staging/production) need different email configs

## 📞 Need Help?

1. **Run Diagnostic**: `check-email-setup.php` will tell you exactly what's wrong
2. **Check Documentation**: 
   - `EMAIL_NOTIFICATION_GUIDE.md` - Detailed setup guide
   - `EMAIL_NOTIFICATION_COMPLETE.md` - Feature overview
3. **Check Logs**: Look at PHP error logs for runtime errors
4. **Test Step-by-Step**: Follow this guide exactly in order

## ✅ Success Indicators

You'll know emails are working when:
- ✅ Diagnostic tool shows all checks passed
- ✅ Admin receives email for every new booking
- ✅ Customer receives email for their booking (if they provided email)
- ✅ Emails contain complete booking details
- ✅ No email-related errors in PHP error log

---

**Note**: The email functionality is fully implemented in your code. You just need to configure the settings following this guide. Once configured, emails will be sent automatically for all new bookings and booking updates.
