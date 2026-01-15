# Production Deployment Guide

## Pre-Deployment Checklist

### 1. Environment Configuration

#### Database Setup
```bash
# Create production database
mysql -u root -p
CREATE DATABASE venubooking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON venubooking.* TO 'venubooking_user'@'localhost' IDENTIFIED BY 'strong_password_here';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u venubooking_user -p venubooking < database/complete-setup.sql
```

#### Environment File
```bash
# Copy and configure .env
cp .env.example .env
nano .env
```

Update with production values:
```
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=venubooking_user
DB_PASS=your_strong_password
```

#### Production Configuration
To enable production mode, include the production config at the top of your entry files:
```php
// Add to index.php, booking-step*.php, admin pages
require_once __DIR__ . '/config/production.php';
```

### 2. File Permissions

```bash
# Set correct ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/venubooking

# Set directory permissions
find /var/www/venubooking -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/venubooking -type f -exec chmod 644 {} \;

# Make uploads directory writable
chmod 775 /var/www/venubooking/uploads
chmod 775 /var/www/venubooking/uploads/*

# Create logs directory
mkdir -p /var/www/venubooking/logs
chmod 775 /var/www/venubooking/logs

# Protect sensitive files
chmod 600 /var/www/venubooking/.env
chmod 644 /var/www/venubooking/config/database.php
```

### 3. Security Hardening

#### Update Default Admin Password
1. Login at: https://yourdomain.com/admin/
2. Default credentials: admin / Admin@123
3. Change immediately in Admin → Profile/Settings

#### Configure SMTP Email
1. Go to Admin → Settings → Email Configuration
2. Enable SMTP
3. Configure:
   - SMTP Host (e.g., smtp.gmail.com)
   - SMTP Port (587 for TLS, 465 for SSL)
   - SMTP Username
   - SMTP Password
   - From Email Address
   - From Name

#### SSL/HTTPS Configuration
```apache
# Apache: Enable SSL
sudo a2enmod ssl
sudo a2ensite default-ssl
sudo systemctl restart apache2

# Update session cookie security in config/database.php
ini_set('session.cookie_secure', '1');
```

#### Web Server Configuration

**Apache (.htaccess)**
Already included, ensure mod_rewrite is enabled:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Nginx**
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/venubooking;
    index index.php;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
    
    # Protect sensitive directories
    location ~ ^/(config|includes|database)/ {
        deny all;
    }
}
```

### 4. System Settings Configuration

After deployment, configure via Admin Panel:

#### Basic Settings
- Site Name
- Site Logo (upload)
- Site Favicon (upload)
- Contact Email
- Contact Phone
- Address

#### Financial Settings
- Currency (NPR)
- Tax Rate (%)
- Advance Payment Percentage

#### Email Settings
- Enable Email Notifications
- Enable SMTP
- Configure SMTP details
- Test email delivery

#### Content Settings
- Meta Title
- Meta Description
- Meta Keywords
- Terms & Conditions
- Privacy Policy

### 5. Testing Checklist

#### Functional Testing
- [ ] Complete booking flow (all 5 steps)
- [ ] Hall availability checking
- [ ] Price calculation accuracy
- [ ] Email notifications (user & admin)
- [ ] Payment recording
- [ ] Admin login
- [ ] CRUD operations for venues, halls, menus
- [ ] Image uploads
- [ ] Settings changes reflect on frontend

#### Browser Testing
- [ ] Chrome (desktop & mobile)
- [ ] Firefox
- [ ] Safari (iOS)
- [ ] Edge

#### Mobile Testing
- [ ] Forms are usable
- [ ] Buttons are tappable (min 44px)
- [ ] Text is readable
- [ ] Images load correctly
- [ ] Navigation works
- [ ] Checkout flow is clear

#### Security Testing
- [ ] SQL injection protection (using prepared statements)
- [ ] XSS protection (htmlspecialchars)
- [ ] CSRF protection
- [ ] File upload validation
- [ ] Session security
- [ ] Admin access control

### 6. Performance Optimization

#### Database
```sql
-- Add indexes for better performance
CREATE INDEX idx_bookings_date ON bookings(event_date);
CREATE INDEX idx_bookings_status ON bookings(booking_status);
CREATE INDEX idx_halls_venue ON halls(venue_id);
```

#### PHP Configuration
```ini
# php.ini optimizations
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M

# Enable OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

#### Caching
- Enable browser caching for static assets
- Use CDN for Bootstrap, Font Awesome (already implemented)
- Consider Redis/Memcached for session storage

### 7. Backup Strategy

#### Database Backups
```bash
# Daily backup script
#!/bin/bash
BACKUP_DIR="/var/backups/venubooking"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u venubooking_user -p'password' venubooking | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete
```

#### File Backups
```bash
# Backup uploads directory
tar -czf /var/backups/venubooking/uploads_$DATE.tar.gz /var/www/venubooking/uploads/

# Keep only last 30 days
find $BACKUP_DIR -name "uploads_*.tar.gz" -mtime +30 -delete
```

Add to crontab:
```bash
crontab -e
# Add this line for daily backup at 2 AM
0 2 * * * /path/to/backup-script.sh
```

### 8. Monitoring & Logging

#### Error Logs
Monitor these files:
- `/var/www/venubooking/logs/error.log` - Application errors
- `/var/log/apache2/error.log` or `/var/log/nginx/error.log` - Web server errors
- `/var/log/mysql/error.log` - Database errors

#### Log Rotation
```bash
# Create logrotate config
sudo nano /etc/logrotate.d/venubooking
```

Add:
```
/var/www/venubooking/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 9. Maintenance Mode

Create a maintenance page:
```php
// maintenance.php
<?php
http_response_code(503);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Under Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center">
            <h1>🔧 Site Under Maintenance</h1>
            <p>We're performing scheduled maintenance. We'll be back soon!</p>
        </div>
    </div>
</body>
</html>
```

To enable:
```bash
# Rename index.php
mv index.php index.php.bak
mv maintenance.php index.php
```

### 10. Post-Deployment

#### Verify Everything Works
1. Test complete booking flow
2. Verify email notifications
3. Check admin panel access
4. Test on mobile devices
5. Monitor error logs

#### Documentation
- Document admin credentials (secure location)
- Document SMTP settings
- Document database credentials
- Document backup locations
- Document any custom configurations

## Common Issues & Solutions

### Issue: Emails Not Sending
**Solution:**
1. Check SMTP settings in Admin → Settings
2. Verify SMTP credentials are correct
3. Check firewall isn't blocking SMTP port
4. Enable less secure apps (if using Gmail)
5. Check error logs: `tail -f logs/error.log`

### Issue: Images Not Displaying
**Solution:**
1. Check uploads directory permissions: `chmod 775 uploads/`
2. Verify web server can write to uploads/
3. Check file paths in database
4. Clear browser cache

### Issue: Database Connection Error
**Solution:**
1. Verify .env file has correct credentials
2. Check MySQL service is running: `sudo systemctl status mysql`
3. Verify database exists: `mysql -u root -p -e "SHOW DATABASES;"`
4. Check user permissions: `SHOW GRANTS FOR 'venubooking_user'@'localhost';`

### Issue: 404 Errors on Subpages
**Solution:**
1. Enable mod_rewrite: `sudo a2enmod rewrite`
2. Check .htaccess file exists
3. Verify AllowOverride is set to All in Apache config
4. Restart web server

## Support

For issues:
1. Check error logs first
2. Review this deployment guide
3. Check PRODUCTION_READY_CHECKLIST.md
4. Contact: info@venubooking.com

## Version History

- v1.0.0 - Initial production release
- Production-ready with all features
- Complete booking system
- Email notifications
- Admin panel
- Security hardened
