# cPanel Shared Hosting Installation Guide

## Complete Step-by-Step Guide for Installing Venue Booking System on cPanel

This guide will help you install the venue booking system on your cPanel shared hosting account.

---

## 📋 Prerequisites

Before starting, make sure you have:
- cPanel hosting account with PHP 8.0+ support
- MySQL database access
- FTP/File Manager access
- Your cPanel login credentials

---

## 🚀 Installation Steps

### Step 1: Upload Files to cPanel

#### Method A: Using File Manager (Recommended for Beginners)

1. **Login to cPanel**
   - Go to your hosting provider's cPanel URL (usually: `https://yourdomain.com:2083`)
   - Enter your cPanel username and password

2. **Navigate to File Manager**
   - In cPanel, find and click on "File Manager"
   - Navigate to `public_html` folder (or your website's root directory)

3. **Upload the ZIP File**
   - Download the repository as a ZIP file from GitHub
   - Click "Upload" button in File Manager
   - Select the ZIP file and wait for upload to complete
   - Right-click the ZIP file and select "Extract"
   - After extraction, move all files from the extracted folder to `public_html`

#### Method B: Using FTP Client (FileZilla)

1. **Connect via FTP**
   - Open FileZilla (or any FTP client)
   - Host: `ftp.yourdomain.com` or your server IP
   - Username: Your cPanel username
   - Password: Your cPanel password
   - Port: 21

2. **Upload Files**
   - Navigate to `/public_html` on the remote side
   - Upload all project files to this directory

---

### Step 2: Create MySQL Database

1. **Login to cPanel**
   - Go to your cPanel dashboard

2. **Create Database**
   - Find and click "MySQL Databases" or "MySQL Database Wizard"
   - Click "Create New Database"
   - Database Name: `venubooking` (or any name you prefer)
   - Click "Create Database"
   - **Note**: Your full database name will be `cpanelusername_venubooking`

3. **Create Database User**
   - Scroll down to "MySQL Users" section
   - Username: `venuebookinguser` (or any name)
   - Password: Create a strong password (save this!)
   - Click "Create User"
   - **Note**: Your full username will be `cpanelusername_venuebookinguser`

4. **Add User to Database**
   - Scroll to "Add User To Database" section
   - Select the user you just created
   - Select the database you created
   - Click "Add"
   - Check "ALL PRIVILEGES"
   - Click "Make Changes"

---

### Step 3: Import Database Schema

#### Method A: Using phpMyAdmin (Recommended)

1. **Open phpMyAdmin**
   - In cPanel, find and click "phpMyAdmin"

2. **Select Database**
   - Click on your database name (`cpanelusername_venubooking`) in the left sidebar

3. **Import Schema**
   - Click on "Import" tab at the top
   - Click "Choose File"
   - Select `database/schema.sql` from your computer
   - Scroll down and click "Go"
   - Wait for success message

4. **Import Sample Data**
   - Click "Import" tab again
   - Choose `database/sample-data.sql`
   - Click "Go"
   - Wait for success message

#### Method B: Using MySQL Command Line (Advanced)

If your hosting provides SSH access:
```bash
mysql -u cpanelusername_venuebookinguser -p cpanelusername_venubooking < database/schema.sql
mysql -u cpanelusername_venuebookinguser -p cpanelusername_venubooking < database/sample-data.sql
```

---

### Step 4: Configure Environment Variables

1. **Create .env File**
   - In File Manager, navigate to your website root (where all files are)
   - Right-click `.env.example` file
   - Select "Copy"
   - Rename the copy to `.env`

2. **Edit .env File**
   - Right-click `.env` file
   - Select "Edit"
   - Update the following settings:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=cpanelusername_venubooking
DB_USER=cpanelusername_venuebookinguser
DB_PASS=your_database_password_here

# Application Configuration
APP_NAME="Venue Booking System"
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Kathmandu

# Email Configuration (Optional - configure later)
SMTP_HOST=mail.yourdomain.com
SMTP_PORT=587
SMTP_USERNAME=your-email@yourdomain.com
SMTP_PASSWORD=your-email-password
SMTP_ENCRYPTION=tls
EMAIL_FROM_ADDRESS=noreply@yourdomain.com
EMAIL_FROM_NAME="Venue Booking System"
```

3. **Save the file**

---

### Step 5: Set File Permissions

1. **In File Manager**
   - Navigate to the `uploads` folder
   - Right-click on it and select "Permissions"
   - Set to `755` (or check: Owner: Read, Write, Execute; Group: Read, Execute; World: Read, Execute)
   - Click "Change Permissions"

2. **Set permissions for subdirectories**
   - Do the same for:
     - `uploads/venues/`
     - `uploads/halls/`
     - `uploads/menus/`

---

### Step 6: Configure PHP Settings (if needed)

If your hosting uses PHP 7.x by default, you need to change it to PHP 8.0+:

1. **In cPanel**
   - Find "Select PHP Version" or "MultiPHP Manager"
   - Select your domain
   - Choose PHP version 8.0 or higher
   - Click "Apply"

2. **Enable Required Extensions**
   - In the same area, check that these extensions are enabled:
     - pdo
     - pdo_mysql
     - mbstring
     - json
     - openssl

---

### Step 7: Update .htaccess (if in subdirectory)

If you installed in a subdirectory (e.g., `public_html/venubooking/`):

1. **Edit .htaccess file**
   - Open `.htaccess` file
   - Find the line: `RewriteBase /`
   - Change it to: `RewriteBase /venubooking/`
   - Save the file

If installed directly in `public_html`, no changes needed.

---

### Step 8: Test the Installation

1. **Access Frontend**
   - Open browser and go to: `https://yourdomain.com`
   - You should see the venue booking homepage

2. **Access Admin Panel**
   - Go to: `https://yourdomain.com/admin/`
   - Login with:
     - Username: `admin`
     - Password: `Admin@123`

3. **Change Admin Password**
   - **IMPORTANT**: Change the default password immediately!

---

## ⚙️ Common Issues and Solutions

### Issue 1: "Database connection failed"
**Solution**: 
- Check that your database credentials in `.env` are correct
- Make sure you used the full database name with prefix (e.g., `cpanelusername_venubooking`)
- Verify the database user has been added to the database with all privileges

### Issue 2: "500 Internal Server Error"
**Solution**:
- Check if PHP version is 8.0 or higher
- Check file permissions (755 for directories, 644 for files)
- Check if `.htaccess` file is present
- Enable error reporting temporarily in `includes/config.php` to see detailed error

### Issue 3: "Images not uploading"
**Solution**:
- Check permissions on `uploads/` folder (should be 755 or 775)
- Check PHP upload_max_filesize setting (should be at least 10MB)
- Check if the upload directory exists

### Issue 4: "Page not found / 404 errors"
**Solution**:
- Check if mod_rewrite is enabled (most shared hosting has it enabled)
- Check `.htaccess` file is present in the root directory
- If in subdirectory, update `RewriteBase` in `.htaccess`

### Issue 5: "Email notifications not working"
**Solution**:
- Configure SMTP settings in `.env` file
- Use your hosting provider's mail server settings
- Most shared hosting provides SMTP details in their documentation

---

## 📧 Email Configuration for cPanel

To enable email notifications:

1. **Create Email Account**
   - In cPanel, go to "Email Accounts"
   - Create a new email: `noreply@yourdomain.com`
   - Set a strong password

2. **Get SMTP Settings**
   - Usually provided by your hosting provider
   - Common settings:
     - SMTP Host: `mail.yourdomain.com`
     - SMTP Port: `587` (TLS) or `465` (SSL)
     - SMTP Username: `noreply@yourdomain.com`
     - SMTP Password: Your email password

3. **Update .env file**
   - Add these SMTP settings to your `.env` file

---

## 🔒 Security Recommendations

1. **Change Default Password**
   - Login to admin panel
   - Change password from `Admin@123` to a strong password

2. **Delete Installation Files**
   - After setup is complete, you can remove:
     - `database/sample-data.sql` (if you don't need sample data)
     - `INSTALLATION.md`

3. **Secure .env File**
   - Make sure `.htaccess` is protecting `.env` file
   - The provided `.htaccess` already includes protection

4. **Regular Backups**
   - Use cPanel backup feature to backup your database and files regularly

---

## 🎯 Post-Installation Tasks

1. **Test Booking Flow**
   - Go through all 6 steps of booking
   - Test with sample data
   - Verify email notifications

2. **Customize Settings**
   - Update venue information
   - Add your own venues, halls, and menus
   - Configure pricing and tax rates

3. **Set Up SSL Certificate**
   - Most cPanel hosting offers free SSL (Let's Encrypt)
   - In cPanel, go to "SSL/TLS Status"
   - Enable SSL for your domain
   - Update `APP_URL` in `.env` to use `https://`

4. **Configure Cron Jobs** (Optional)
   - For automated email reminders or cleanup tasks
   - In cPanel, go to "Cron Jobs"
   - Set up as needed

---

## 📞 Support

If you encounter issues during installation:

1. Check the error logs in cPanel (Error Log section)
2. Enable PHP error display temporarily
3. Verify all requirements are met
4. Check file and folder permissions
5. Contact your hosting provider if PHP/MySQL configuration is needed

---

## 📌 Quick Reference

**Default Admin Login:**
- URL: `https://yourdomain.com/admin/`
- Username: `admin`
- Password: `Admin@123` (CHANGE THIS!)

**Database Prefix:**
- cPanel adds your username as prefix
- Example: `cpaneluser_venubooking`

**File Locations:**
- Website files: `/public_html/`
- Uploads: `/public_html/uploads/`
- Config: `/public_html/.env`
- Database: In phpMyAdmin

**Important URLs:**
- Frontend: `https://yourdomain.com/`
- Admin Panel: `https://yourdomain.com/admin/`
- phpMyAdmin: Usually `https://yourdomain.com:2083/phpMyAdmin`

---

**Installation Complete!** 🎉

Your venue booking system is now ready to use. Don't forget to change the default admin password and configure email settings.
