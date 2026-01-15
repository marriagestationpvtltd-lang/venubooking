# 📧 Email Notifications - Quick Start

## ⚡ Your Email System is Ready - Just Needs Configuration!

Good news! Your venue booking system already has a **complete email notification system** built-in. It's just waiting for you to configure it.

---

## 🚀 Get Emails Working in 5 Minutes

### Step 1: Add Settings to Database (30 seconds)

Run this command in your terminal:

```bash
cd /path/to/venubooking
mysql -u your_username -p venubooking < database/migrations/add_email_settings.sql
```

Replace `your_username` with your MySQL username and `venubooking` with your database name.

**Alternative:** Use the setup script:
```bash
bash setup-email-notifications.sh
```

### Step 2: Configure Email (2 minutes)

1. Login to your admin panel: `http://your-domain.com/admin/`
2. Click **Settings** → **Email Settings**
3. Fill in these fields:
   - **Enable Email Notifications**: ✅ Enabled
   - **Admin Email**: Your email address
   - **From Name**: Your business name
   - **From Email**: `noreply@yourdomain.com`
4. Click **Save Settings**

### Step 3: Test (2 minutes)

1. Create a test booking
2. Use a valid email address
3. Check your inbox (and spam folder)
4. ✅ Done!

---

## 🔍 Troubleshooting

### Not Working?

**Run the diagnostic tool:**
```
http://your-domain.com/check-email-setup.php
```

This tool will tell you exactly what's wrong and how to fix it.

### Common Issues:

**"Email settings not found"**
→ Run Step 1 again

**"Admin email is empty"**
→ Set admin email in Settings

**"Emails in spam folder"**
→ Consider setting up SMTP (see below)

---

## 🎯 Optional: Setup SMTP (Recommended)

For better email deliverability, use SMTP instead of PHP mail():

### Using Gmail:
1. Go to Settings → Email Settings
2. Enable SMTP: **Yes**
3. Configure:
   ```
   SMTP Host: smtp.gmail.com
   SMTP Port: 587
   Encryption: TLS
   Username: your-email@gmail.com
   Password: [App Password]
   ```
4. Get Gmail App Password: https://myaccount.google.com/apppasswords

### Using SendGrid:
```
SMTP Host: smtp.sendgrid.net
SMTP Port: 587
Encryption: TLS
Username: apikey
Password: [Your SendGrid API Key]
```

---

## 📚 Documentation

- **Quick Fix**: `EMAIL_QUICK_FIX.md`
- **Complete Setup**: `EMAIL_SETUP_REQUIRED.md`
- **Full Analysis**: `EMAIL_ISSUE_RESOLUTION.md`
- **User Guide**: `EMAIL_NOTIFICATION_GUIDE.md`
- **Testing**: `EMAIL_VERIFICATION_CHECKLIST.md`

---

## ✅ What Gets Emailed

When emails are working, you'll get:

**For New Bookings:**
- ✉️ Admin receives notification
- ✉️ Customer receives confirmation

**For Booking Updates:**
- ✉️ Admin receives update
- ✉️ Customer receives update

**Email Contains:**
- Booking number and status
- Customer details
- Event details (date, time, guests)
- Venue and hall info
- Menu selection
- Services
- Cost breakdown
- Special requests

---

## 🎉 That's It!

Your email system is production-ready. Just configure it following the 3 steps above and it will work automatically for all bookings.

**Need Help?** Visit: `http://your-domain.com/check-email-setup.php`
