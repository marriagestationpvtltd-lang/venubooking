# 🚀 Email Not Working? Quick Fix Guide

## ⚡ TL;DR - Quick Fix (5 Minutes)

Your email system is **already coded** but **not configured**. Follow these 3 steps:

### Step 1: Add Email Settings to Database (30 seconds)
```bash
cd /path/to/venubooking
mysql -u your_username -p venubooking < database/migrations/add_email_settings.sql
```

**OR** if you prefer the setup script:
```bash
bash setup-email-notifications.sh
```

### Step 2: Configure in Admin Panel (2 minutes)
1. Login to admin panel: `http://your-domain.com/admin/`
2. Click **Settings** → **Email Settings** tab
3. Set these fields:
   - **Admin Email**: Your email (e.g., `admin@example.com`)
   - **Enable Email Notifications**: Set to "Enabled"
   - **From Name**: Your business name
   - **From Email**: Sender email (e.g., `noreply@yourdomain.com`)
4. Click **Save Settings**

### Step 3: Test (2 minutes)
1. Create a test booking on your website
2. Use a **valid email address** in the customer email field
3. Check both admin and customer inboxes
4. ⚠️ **Check spam/junk folder** if not in inbox

✅ **That's it!** Emails should now work.

---

## 🔍 Diagnostic Tool

**Before setup or if emails still don't work:**

Visit this URL in your browser:
```
http://your-domain.com/check-email-setup.php
```

This tool will:
- ✅ Check if email settings exist
- ✅ Verify configuration
- ✅ Identify exactly what's wrong
- ✅ Show you how to fix it

---

## 📋 What Was Wrong?

The issue is **NOT in the code** - it's just **not configured yet**.

### Why emails weren't working:
1. ❌ Email settings don't exist in database (migration not run)
2. ❌ Admin email not configured (no one to send to)
3. ❌ Email notifications may be disabled

### What's already working:
1. ✅ Email sending code is complete
2. ✅ Booking notification system is implemented
3. ✅ Email templates are ready
4. ✅ SMTP support is built-in

You just need to **configure the settings** (Steps 1-3 above).

---

## 🎯 Common Questions

### Q: Do I need SMTP?
**A:** No, but it's recommended.
- **Without SMTP**: Uses PHP `mail()` - simple but may not work on all servers
- **With SMTP**: More reliable, better deliverability, less spam issues

### Q: How to setup SMTP?
**A:** In Admin Panel → Settings → Email Settings:

**For Gmail:**
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: [App Password - not your regular password]
```

Get Gmail App Password: https://myaccount.google.com/apppasswords

**For SendGrid:**
```
SMTP Host: smtp.sendgrid.net
SMTP Port: 587
Encryption: TLS
Username: apikey
Password: [Your SendGrid API Key]
```

### Q: Customer not getting emails?
**A:** Check if customer provided an email address. The email field is **optional** in the booking form.

### Q: Emails going to spam?
**A:** Use SMTP instead of PHP mail(). SMTP emails are less likely to be marked as spam.

### Q: Can I test without SMTP?
**A:** Yes! Just skip SMTP configuration and use PHP mail(). It works on most servers.

---

## 📚 Detailed Documentation

For more information:
- **`EMAIL_SETUP_REQUIRED.md`** - Complete setup guide with troubleshooting
- **`EMAIL_NOTIFICATION_GUIDE.md`** - Feature documentation and examples
- **`EMAIL_NOTIFICATION_COMPLETE.md`** - Technical implementation details

---

## ✅ Verification Checklist

After setup, verify these:
- [ ] Diagnostic tool shows "All checks passed"
- [ ] Admin email is configured
- [ ] Email notifications are enabled
- [ ] Test booking sends emails
- [ ] Admin receives email
- [ ] Customer receives email (if provided)
- [ ] No errors in PHP error log

---

## 🆘 Still Not Working?

1. **Run diagnostic**: `http://your-domain.com/check-email-setup.php`
2. **Check PHP error logs**: Look for email-related errors
3. **Test with different email**: Some providers block automated emails
4. **Check spam folder**: Especially for first emails
5. **Try SMTP**: More reliable than PHP mail()

---

## 🎓 Technical Details

If you're curious about the implementation:

**Email functions are in:** `includes/functions.php`
- `sendEmail()` - Main email function
- `sendEmailSMTP()` - SMTP implementation  
- `sendBookingNotification()` - Sends booking emails
- `generateBookingEmailHTML()` - Creates email HTML

**Email is called from:**
- `createBooking()` - When booking is created (frontend)
- `admin/bookings/add.php` - When admin creates booking
- `admin/bookings/edit.php` - When booking status changes

**Settings are stored in:** Database `settings` table

---

**Need help?** Run the diagnostic tool: `http://your-domain.com/check-email-setup.php`
