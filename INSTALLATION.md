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
- [ ] Create database: `CREATE DATABASE venubooking;`
- [ ] Import schema: `mysql -u root -p venubooking < database/schema.sql`
- [ ] Import sample data: `mysql -u root -p venubooking < database/sample-data.sql`
- [ ] Verify tables created (should have 14 tables)

### 3. Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Edit `.env` with your database credentials:
  - [ ] DB_HOST (default: localhost)
  - [ ] DB_NAME (default: venubooking)
  - [ ] DB_USER (your MySQL username)
  - [ ] DB_PASS (your MySQL password)
  - [ ] CURRENCY (default: NPR)
  - [ ] TAX_RATE (default: 13)

### 4. File Permissions
- [ ] Make uploads directory writable: `chmod -R 755 uploads/`
- [ ] Verify web server can write to uploads directory

### 5. Web Server Configuration

#### Apache
- [ ] Ensure mod_rewrite is enabled
- [ ] `.htaccess` file is present in root directory

#### Nginx
- [ ] Add rewrite rules to server configuration
- [ ] Restart Nginx service

### 6. Test the Installation

#### Frontend Tests
- [ ] Visit: `http://localhost/venubooking/`
- [ ] Homepage loads correctly
- [ ] Navigation menu works
- [ ] Can access booking form
- [ ] Date picker shows future dates only
- [ ] Form validation works

#### Backend Tests
- [ ] Visit: `http://localhost/venubooking/admin/`
- [ ] Redirects to login page
- [ ] Login with default credentials:
  - Username: `admin`
  - Password: `Admin@123`
- [ ] Dashboard loads with statistics
- [ ] Can access all menu items:
  - [ ] Dashboard
  - [ ] Venues
  - [ ] Halls
  - [ ] Menus
  - [ ] Bookings
  - [ ] Customers
  - [ ] Services
  - [ ] Reports
  - [ ] Settings

### 7. Verify Sample Data
- [ ] 4 venues visible
- [ ] 8 halls visible
- [ ] 5 menus visible with items
- [ ] 8 additional services visible
- [ ] Sample bookings (if any) display correctly

### 8. Security Checklist
- [ ] Change default admin password
- [ ] Review database user permissions
- [ ] Ensure `.env` file is not web-accessible
- [ ] Set appropriate file permissions
- [ ] Enable HTTPS in production
- [ ] Configure secure session settings

### 9. Customization (Optional)
- [ ] Update site name in Settings
- [ ] Update contact information
- [ ] Upload company logo
- [ ] Customize tax rates
- [ ] Adjust currency if needed
- [ ] Add real venue images
- [ ] Add real hall images
- [ ] Customize menus

## Post-Installation

### Test Complete Booking Flow
1. [ ] Start new booking from homepage
2. [ ] Select shift, date, guests, event type
3. [ ] View available venues
4. [ ] Select venue and hall
5. [ ] Choose menu(s)
6. [ ] Add optional services
7. [ ] Enter customer information
8. [ ] Submit booking
9. [ ] View confirmation page
10. [ ] Verify booking appears in admin panel

### Admin Panel Tests
1. [ ] View booking in admin dashboard
2. [ ] Update booking status
3. [ ] Update payment status
4. [ ] Generate reports
5. [ ] Export data (if implemented)
6. [ ] Test search and filters
7. [ ] Test sorting in data tables

## Troubleshooting

If you encounter issues:

- [ ] Check PHP error logs
- [ ] Check MySQL error logs
- [ ] Check web server error logs
- [ ] Verify database connection in `.env`
- [ ] Ensure all tables were created
- [ ] Check file permissions
- [ ] Clear browser cache
- [ ] Try different browser

## Production Deployment

Before going live:

- [ ] Backup database
- [ ] Remove sample/test data
- [ ] Change all default passwords
- [ ] Enable HTTPS/SSL
- [ ] Configure email settings (if implemented)
- [ ] Set up automated backups
- [ ] Configure error reporting (production mode)
- [ ] Test on multiple devices
- [ ] Test on multiple browsers
- [ ] Load test with expected traffic
- [ ] Set up monitoring
- [ ] Document any customizations
- [ ] Train staff on admin panel

## Support

If you need help:
- Review README.md documentation
- Check troubleshooting section
- Contact support team

---

**Installation Complete!** ✅

Your Venue Booking System is now ready to use.

Remember to:
1. Change the default admin password
2. Customize settings for your business
3. Add your real venue/hall data
4. Test thoroughly before going live
