# Installation Checklist

Follow this checklist to ensure proper installation of the Venue Booking System.

## Pre-Installation

- [ ] PHP 8.0+ installed
- [ ] MySQL 8.0+ installed  
- [ ] Apache/Nginx web server configured
- [ ] Git installed (optional)

## Installation Steps

### 1. Get the Code
- [ ] Clone repository OR download ZIP file
- [ ] Extract to web server directory (e.g., `/var/www/html/venubooking/`)

### 2. Database Setup

**Option A – Fresh production install (recommended):**
- [ ] Create an empty database in MySQL / cPanel
- [ ] Import `database/production-ready.sql` — this creates all tables, the default admin user, and essential settings with no sample data

**Option B – Shared hosting (cPanel):**
- [ ] Create the database in cPanel MySQL Databases
- [ ] Import `database/production-shared-hosting.sql` via phpMyAdmin — this is a clean production database with no sample data, ready for live deployment

**Option C – Development/Testing:**
- [ ] Import `database/complete-database-setup.sql` — includes sample venues, halls, menus, bookings for testing

> All scripts create all required tables in a single import. Do **not** run individual migration files unless upgrading an existing database.

### 3. Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Edit `.env` with your actual database credentials:
  - [ ] `DB_HOST` (default: localhost)
  - [ ] `DB_NAME` (your database name)
  - [ ] `DB_USER` (your MySQL username)
  - [ ] `DB_PASS` (your MySQL password, use a strong password)
- [ ] Configure SMTP email settings in `.env` (see SMTP section in `.env.example`)
- [ ] Set file permissions on `.env`: `chmod 600 .env`

### 4. File Permissions
- [ ] Make uploads directory writable: `chmod -R 755 uploads/`
- [ ] Ensure the web server user can write to `uploads/`
- [ ] Ensure the `logs/` directory exists and is writable (created automatically on first request)

### 5. Web Server Configuration

#### Apache
- [ ] Ensure `mod_rewrite` and `mod_headers` are enabled
- [ ] The root `.htaccess` (included in the repo) enables security headers, blocks access to `.env`/`config`/`includes`/`database` directories, and disables directory listing
- [ ] The `uploads/.htaccess` (included in the repo) blocks PHP execution inside the upload directory
- [ ] Ensure `AllowOverride All` (or at minimum `AllowOverride Options FileInfo`) is set for the document root in your Apache VirtualHost

#### Nginx
- [ ] Add the following location blocks to your server configuration (adapt paths as needed):

```nginx
# Block access to sensitive files and directories
location ~ /\.(env|git|htaccess) { deny all; }
location ~* ^/(config|includes|database|logs)/ { deny all; }

# Block PHP execution in uploads
location ~* ^/uploads/.*\.(php|phtml|phar|pl|cgi)$ { deny all; }

# Security headers
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

### 6. Test the Installation

#### Frontend Tests
- [ ] Visit: `http://yourdomain.com/`
- [ ] Homepage loads correctly
- [ ] Booking form works end-to-end

#### Backend Tests
- [ ] Visit: `http://yourdomain.com/admin/`
- [ ] Redirects to login page
- [ ] Login with default credentials:
  - Username: `admin`
  - Password: `Admin@123`
- [ ] **Immediately change the password** (see Security Checklist below)
- [ ] Dashboard loads with statistics
- [ ] All menu items are accessible

### 7. Security Checklist ⚠️

Complete these steps **before going live**:

- [ ] **Change default admin password** — log in at `/admin/`, go to profile/settings and set a strong unique password
- [ ] Verify `.env` is not web-accessible (the `.htaccess` blocks it; test by requesting `https://yourdomain.com/.env` — should return 403)
- [ ] Set `.env` file permissions to `600`: `chmod 600 .env`
- [ ] Enable HTTPS / SSL and uncomment the HSTS header in `.htaccess`
- [ ] Verify that HTTPS is enabled so session cookies are automatically secured (the `session.cookie_secure` flag is set automatically when HTTPS is detected)
- [ ] Review MySQL database user permissions — the app only needs `SELECT, INSERT, UPDATE, DELETE` on its own database; avoid using `root` credentials in `.env`
- [ ] Verify uploads directory blocks PHP execution (test: try to access any `.php` file placed in `uploads/` — should return 403)
- [ ] Configure email/SMTP settings in Admin Panel → Settings → Email Settings
- [ ] Update company information in Admin Panel → Settings
- [ ] Add real payment method details in Admin Panel → Payment Methods, then activate them

### 8. Customization
- [ ] Update site name in Settings
- [ ] Upload company logo and favicon
- [ ] Configure social media links
- [ ] Add real venue and hall data (remove sample data from shared-hosting import if applicable)
- [ ] Customise menus and services
- [ ] Adjust tax rate and advance payment percentage in Settings

## Post-Installation

### Test Complete Booking Flow
1. [ ] Start new booking from homepage
2. [ ] Select shift, date, guests, event type
3. [ ] View available venues and select hall
4. [ ] Choose menu(s) and services
5. [ ] Enter customer information
6. [ ] Submit booking and view confirmation
7. [ ] Verify booking appears in admin panel
8. [ ] Confirm booking and update payment status

### Admin Panel Tests
1. [ ] View booking in admin dashboard
2. [ ] Update booking and payment status
3. [ ] Generate reports
4. [ ] Test search and filters

## Troubleshooting

If you encounter issues:

- [ ] Check PHP error logs (`logs/error.log` inside the app, or the server error log)
- [ ] Check MySQL error logs
- [ ] Check web server error logs
- [ ] Verify database credentials in `.env`
- [ ] Ensure all tables were created (run `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();` — should return 34)
- [ ] Check file permissions on `uploads/` and `logs/`
- [ ] Clear browser cache and try again

## Production Deployment

Before going live:

- [ ] Backup database
- [ ] Remove or replace sample/test data
- [ ] Change all default passwords
- [ ] Enable HTTPS/SSL
- [ ] Enable HSTS header in `.htaccess`
- [ ] Enable `session.cookie_secure` in `config/production.php`
- [ ] Configure email notifications
- [ ] Set up automated database backups
- [ ] Test on multiple devices and browsers
- [ ] Document any customisations

## Support

If you need help:
- Review README.md documentation
- Check the troubleshooting section above

---

**Installation Complete!** ✅

Your Venue Booking System is now ready to use.

Remember to:
1. Change the default admin password immediately
2. Customize settings for your business
3. Add your real venue/hall data
4. Test thoroughly before going live

