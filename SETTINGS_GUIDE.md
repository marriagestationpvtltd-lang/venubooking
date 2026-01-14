# Admin Settings Panel - Installation Guide

This guide explains how to set up and use the comprehensive admin settings panel.

## Installation Steps

### 1. Run the Database Migration

Execute the migration SQL to add all new settings to your database:

```bash
mysql -u your_username -p venubooking < database/migrations/add_comprehensive_settings.sql
```

Or through phpMyAdmin:
1. Open phpMyAdmin
2. Select the `venubooking` database
3. Go to the SQL tab
4. Copy and paste the contents of `database/migrations/add_comprehensive_settings.sql`
5. Click "Go" to execute

### 2. Set Appropriate Permissions

Ensure the uploads directory is writable:

```bash
chmod 755 uploads/
chown www-data:www-data uploads/
```

### 3. Access the Settings Panel

1. Log into the admin panel at `https://yoursite.com/admin/login.php`
2. Navigate to "Settings" in the sidebar menu
3. Configure all settings across the five tabs

## Settings Overview

### Basic Settings Tab
- **Website Name**: The name displayed across your website
- **Logo**: Upload your company logo (250x60px recommended)
- **Favicon**: Upload a favicon (32x32px or 64x64px recommended)
- **Contact Information**: Email, phone, WhatsApp number
- **Business Address**: Full address and operating hours
- **Google Maps URL**: Link to your location on Google Maps
- **Currency & Tax**: Set currency symbol and tax rate

### Content Tab
- **Footer About**: Brief description shown in footer
- **Footer Copyright**: Custom copyright text (auto-generated if empty)

### Booking Tab
- **Advance Payment**: Percentage required upfront
- **Minimum Advance**: Days required before event date
- **Cancellation Notice**: Hours before event for cancellations
- **Default Status**: Initial status for new bookings
- **Online Payment**: Enable/disable online payment option

### SEO & Meta Tab
- **Meta Title**: Page title shown in search results (50-60 characters)
- **Meta Description**: Description in search results (150-160 characters)
- **Meta Keywords**: Comma-separated keywords for SEO

### Social Media Tab
- **Facebook**: Your Facebook page URL
- **Instagram**: Your Instagram profile URL
- **TikTok**: Your TikTok profile URL
- **Twitter**: Your Twitter profile URL
- **YouTube**: Your YouTube channel URL
- **LinkedIn**: Your LinkedIn company page URL

## Features

### ✅ Centralized Control
All website settings in one place - no need to edit code files

### ✅ Immediate Updates
Changes reflect immediately on the frontend without cache clearing

### ✅ File Uploads
Easy logo and favicon upload with validation and preview

### ✅ Smart Defaults
Settings use sensible defaults when not configured

### ✅ SEO Friendly
Full control over meta tags for better search engine visibility

### ✅ Social Integration
Display all your social media links in the footer

### ✅ No Hardcoded Values
All content is dynamic and editable from admin panel

## Frontend Integration

The settings are automatically used in:

1. **Header (`includes/header.php`)**
   - Website name and logo in navigation
   - Meta tags (title, description, keywords)
   - Favicon

2. **Footer (`includes/footer.php`)**
   - Company information and about text
   - Contact details (phone, email, WhatsApp)
   - Business address
   - Social media links
   - Copyright notice

## API Access

Settings can be accessed in any PHP file using:

```php
$site_name = getSetting('site_name', 'Default Value');
$contact_email = getSetting('contact_email', 'default@example.com');
```

Available setting keys:
- `site_name`
- `site_logo`
- `site_favicon`
- `contact_email`
- `contact_phone`
- `contact_address`
- `whatsapp_number`
- `business_hours`
- `contact_map_url`
- `currency`
- `tax_rate`
- `advance_payment_percentage`
- `footer_about`
- `footer_copyright`
- `booking_min_advance_days`
- `booking_cancellation_hours`
- `enable_online_payment`
- `default_booking_status`
- `meta_title`
- `meta_description`
- `meta_keywords`
- `social_facebook`
- `social_instagram`
- `social_tiktok`
- `social_twitter`
- `social_youtube`
- `social_linkedin`

## Security Features

- File upload validation (type, size, content)
- Path traversal prevention
- XSS protection via htmlspecialchars
- Transaction-based updates
- User authentication required

## Troubleshooting

### Logo/Favicon not displaying
- Check file permissions on uploads directory
- Verify file was uploaded successfully
- Clear browser cache
- Check file path in database

### Settings not saving
- Check database connection
- Verify user has admin access
- Check PHP error logs
- Ensure settings table exists

### Social links not showing
- Verify URLs are complete (including https://)
- Check for typos in URLs
- Ensure footer.php is updated

## Future Enhancements

This central settings panel allows for easy addition of new settings without code changes. To add a new setting:

1. Add the setting to the database migration
2. Add the form field in admin/settings/index.php
3. Use `getSetting('your_key')` wherever needed

## Support

For issues or questions, refer to the main project documentation or contact the development team.
