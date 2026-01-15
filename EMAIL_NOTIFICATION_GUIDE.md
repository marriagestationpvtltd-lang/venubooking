# Email Notification System Guide

## Overview

The Venue Booking System now includes automatic email notifications for booking creation and updates. Emails are sent to both the admin and the customer whenever a booking is created or its status is updated.

## Features

### Automatic Email Notifications

1. **New Booking Created**
   - Email sent to: Admin + Customer
   - Triggered when: A new booking is created (frontend or admin panel)
   - Content: Complete booking details including venue, hall, menus, services, and cost breakdown

2. **Booking Status Updated**
   - Email sent to: Admin + Customer
   - Triggered when: Booking status or payment status changes
   - Content: Updated booking details with status change information

## Email Configuration

### Admin Panel Settings

Navigate to: **Admin Panel → Settings → Email Settings**

#### Basic Email Settings

- **Enable Email Notifications**: Toggle to enable/disable all email notifications
- **Admin Email Address**: Email address that receives all booking notifications
- **From Name**: Name displayed as sender (e.g., "Venue Booking System")
- **From Email Address**: Email address displayed as sender (e.g., "noreply@venubooking.com")

#### SMTP Configuration (Optional - Recommended)

For better email deliverability, configure SMTP settings:

- **Enable SMTP**: Toggle to use SMTP instead of PHP mail()
- **SMTP Host**: Your SMTP server address (e.g., smtp.gmail.com)
- **SMTP Port**: Port number (587 for TLS, 465 for SSL, 25 for plain)
- **SMTP Encryption**: TLS (recommended), SSL, or None
- **SMTP Username**: Your SMTP account username/email
- **SMTP Password**: Your SMTP account password

### Common SMTP Configurations

#### Gmail
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: Your App Password (not regular password)
```

**Note**: For Gmail, you need to use an "App Password" instead of your regular password. Generate one at: https://myaccount.google.com/apppasswords

#### SendGrid
```
SMTP Host: smtp.sendgrid.net
SMTP Port: 587
Encryption: TLS
Username: apikey
Password: Your SendGrid API Key
```

#### Amazon SES
```
SMTP Host: email-smtp.us-east-1.amazonaws.com
SMTP Port: 587
Encryption: TLS
Username: Your SMTP Username
Password: Your SMTP Password
```

## Database Migration

To enable email settings in your database, run the migration:

```bash
mysql -u username -p venubooking < database/migrations/add_email_settings.sql
```

Or execute the SQL directly in your database:

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

## Email Content

### Booking Confirmation Email (New Booking)

**Subject**: Booking Confirmation - [Booking Number]

**Contains**:
- Greeting with customer name
- Booking number
- Booking and payment status
- Customer information
- Event details (type, date, shift, guests)
- Venue and hall information
- Selected menus with items
- Additional services
- Special requests
- Complete cost breakdown
- Contact information

### Booking Update Email

**Subject**: Booking Status Updated - [Booking Number]

**Contains**:
- Status change notification (old status → new status)
- All booking details (same as confirmation email)

### Email Recipients

- **Admin Email**: Receives all booking notifications (new bookings and updates)
- **Customer Email**: Receives notifications for their own bookings (if email provided)

## Technical Implementation

### Function Calls

The system automatically calls email functions in the following scenarios:

1. **Frontend Booking Creation** (`booking-step5.php`)
   ```php
   $booking_result = createBooking([...]);
   // Email sent automatically within createBooking()
   ```

2. **Admin Booking Creation** (`admin/bookings/add.php`)
   ```php
   // After successful booking creation
   sendBookingNotification($booking_id, 'new');
   ```

3. **Admin Booking Update** (`admin/bookings/edit.php`)
   ```php
   // After successful booking update, if status changed
   if ($status_changed) {
       sendBookingNotification($booking_id, 'update', $old_booking_status);
   }
   ```

### Key Functions

- `sendEmail($to, $subject, $message, $recipient_name)` - Sends individual email
- `sendEmailSMTP($to, $subject, $message, $recipient_name)` - SMTP implementation
- `sendBookingNotification($booking_id, $type, $old_status)` - Sends booking notification emails
- `generateBookingEmailHTML($booking, $recipient, $type, $old_status)` - Generates email HTML

## Troubleshooting

### Emails Not Being Sent

1. **Check Email Settings**
   - Verify "Enable Email Notifications" is set to "Enabled"
   - Confirm admin email address is valid
   - Ensure customer has provided an email address

2. **SMTP Issues**
   - Verify SMTP credentials are correct
   - Check SMTP host and port
   - Ensure firewall allows outbound connections on SMTP port
   - Try disabling SMTP to use PHP mail() instead

3. **PHP mail() Issues**
   - Ensure your server has mail functionality configured
   - Check server logs for mail errors
   - Consider switching to SMTP for better reliability

4. **Check Error Logs**
   ```bash
   tail -f /path/to/php/error.log
   ```

### Test Email Functionality

To test if emails are working:

1. Go to Admin Panel → Bookings → Add New Booking
2. Fill in all required fields
3. **Important**: Include a valid email address for the customer
4. Submit the booking
5. Check both admin and customer email inboxes

### Email Deliverability

For best deliverability:

- Use SMTP instead of PHP mail()
- Use a professional email service (Gmail, SendGrid, Amazon SES, etc.)
- Ensure your domain has proper SPF and DKIM records
- Use a verified "From" email address

## Security Considerations

1. **SMTP Password Storage**: Passwords are stored in the database. Consider encryption for production use.
2. **Email Validation**: All email addresses are validated before sending.
3. **HTML Sanitization**: All booking data is sanitized before including in emails.
4. **Error Logging**: Failed email attempts are logged for monitoring.

## Future Enhancements

Potential improvements for future versions:

- Email templates customization in admin panel
- Email queue for bulk sending
- Email activity logs and tracking
- Attachment support for booking confirmation PDF
- SMS notifications integration
- Webhook support for third-party integrations
