# Payment Methods Integration - Complete Guide

## Overview

The Payment Methods feature allows admins to configure multiple payment options for bookings. Payment methods can include QR codes, bank details, and other payment instructions. These payment methods are automatically included in booking confirmations, payment requests, and other communications.

## Features

### 1. Payment Methods Management
- **Add/Edit/Delete Payment Methods**: Manage all payment options from a centralized interface
- **QR Code Upload**: Upload QR code images for digital payment methods (eSewa, Khalti, etc.)
- **Bank Details**: Add bank account information and payment instructions
- **Status Control**: Enable/disable payment methods without deleting them
- **Display Order**: Control the order in which payment methods appear

### 2. Booking Integration
- **Link to Bookings**: Select which payment methods to offer for each booking
- **Flexible Selection**: Choose one or multiple payment methods per booking
- **View in Admin**: See payment methods in booking details page

### 3. Email Notifications
- **Automatic Inclusion**: Payment methods are automatically included in:
  - New booking confirmations
  - Payment request emails
  - Booking update notifications
- **QR Code Display**: QR codes are embedded directly in emails
- **Bank Details**: Bank account information is formatted clearly

### 4. WhatsApp Integration
- Payment methods information can be included in WhatsApp messages
- QR codes and bank details are available for manual sharing

## Installation

### Step 1: Apply Database Migration

Run the migration script to create necessary database tables:

```bash
./apply-payment-methods-migration.sh
```

Or manually apply the SQL:

```bash
mysql -u [username] -p [database_name] < database/migrations/add_payment_methods.sql
```

This will create:
- `payment_methods` table - stores payment method configurations
- `booking_payment_methods` table - links bookings to payment methods
- Default payment methods (can be customized)

### Step 2: Configure Payment Methods

1. Log into the Admin Panel
2. Navigate to **Payment Methods** in the sidebar
3. Click **Add Payment Method**
4. Fill in the details:
   - **Method Name**: e.g., "Bank Transfer", "eSewa", "Khalti"
   - **QR Code**: Upload a QR code image (optional)
   - **Bank Details**: Add account information or payment instructions
   - **Status**: Active/Inactive
   - **Display Order**: Set the order (lower numbers appear first)
5. Click **Add Payment Method**

### Step 3: Link to Bookings

When creating or editing a booking:

1. Scroll to the **Payment Methods** section
2. Select the payment methods you want to offer
3. Save the booking
4. The selected payment methods will appear in:
   - Booking details page
   - Email notifications
   - Payment requests

## Usage Guide

### Adding a Bank Transfer Payment Method

1. Go to Admin Panel > Payment Methods
2. Click "Add Payment Method"
3. Fill in:
   ```
   Method Name: Bank Transfer
   Bank Details:
   Bank: ABC Bank Limited
   Account Name: Your Business Name
   Account Number: 1234567890123456
   Branch: Main Branch, Kathmandu
   Swift Code: ABCNPKA (for international transfers)
   ```
4. Set Status to "Active"
5. Click "Add Payment Method"

### Adding Digital Payment Methods (eSewa, Khalti, etc.)

1. Go to Admin Panel > Payment Methods
2. Click "Add Payment Method"
3. Fill in:
   ```
   Method Name: eSewa
   Bank Details:
   eSewa ID: 9841234567
   Name: Your Business Name
   ```
4. Upload a QR Code image (generate from eSewa merchant panel)
5. Set Status to "Active"
6. Click "Add Payment Method"

### Selecting Payment Methods for a Booking

**When Adding a Booking:**
1. Fill in customer and event details
2. In the "Payment Methods" section, check the methods you want to offer
3. Complete and save the booking

**When Editing a Booking:**
1. Open the booking
2. Click "Edit"
3. Scroll to "Payment Methods"
4. Select/deselect methods as needed
5. Save changes

### Sending Payment Requests

1. Open a booking in the admin panel
2. Click "Send Payment Request via Email" or "Send Payment Request via WhatsApp"
3. The customer receives:
   - Total amount due
   - Advance payment amount (based on configured percentage)
   - All selected payment methods with QR codes and bank details
   - Instructions for confirming payment

## Payment Methods in Emails

When a booking confirmation or payment request is sent, the email includes:

- **Payment Method Name**: Clearly labeled
- **QR Code**: Embedded image (if uploaded)
- **Bank Details**: Formatted text with account information
- **Separator**: "OR" between multiple payment methods
- **Payment Instructions**: Note to contact admin after payment

Example email section:
```
Payment Methods
─────────────────────────────

Bank Transfer
Bank: ABC Bank Limited
Account Name: Your Business Name
Account Number: 1234567890123456
Branch: Main Branch

- OR -

eSewa
[QR CODE IMAGE]
eSewa ID: 9841234567
Name: Your Business Name
```

## Best Practices

### 1. Keep Information Updated
- Regularly verify bank account details are correct
- Update QR codes if they change
- Remove outdated payment methods

### 2. Use Clear Names
- Use recognizable names: "Bank Transfer", "eSewa", "Khalti"
- Avoid abbreviations that customers might not understand

### 3. Provide Complete Information
- Include all necessary details for bank transfers
- Add notes like "For international transfers, use Swift Code"
- Specify if certain methods have fees

### 4. Organize with Display Order
- Put most commonly used methods first
- Consider customer preferences

### 5. Status Management
- Use "Inactive" instead of deleting if a method is temporarily unavailable
- This preserves historical data for existing bookings

### 6. QR Code Quality
- Upload clear, high-resolution QR codes
- Test QR codes before uploading
- Recommended size: 500x500px minimum

### 7. Security
- Never share sensitive credentials in bank details field
- Use official merchant QR codes only
- Regularly audit active payment methods

## Troubleshooting

### Payment Methods Not Showing in Email

**Check:**
1. Payment methods are linked to the booking (in booking edit page)
2. Payment methods status is "Active"
3. Email notifications are enabled in Settings
4. The email being sent is for a payment request or new booking

**Solution:**
- Edit the booking and select the payment methods
- Verify payment method status
- Re-send the email notification

### QR Code Not Displaying

**Check:**
1. QR code file was uploaded successfully
2. File format is supported (JPG, PNG, GIF)
3. Upload directory has correct permissions

**Solution:**
- Re-upload the QR code
- Check `/uploads/payment-qr/` directory permissions
- Verify file size is under upload limit

### Cannot Delete Payment Method

**Possible Cause:**
- Payment method is linked to existing bookings

**Solution:**
- Set status to "Inactive" instead of deleting
- Or remove the payment method from all bookings first

### Bank Details Not Formatted Properly

**Check:**
- Line breaks are preserved
- Special characters are not causing issues

**Solution:**
- Use simple text formatting
- Use line breaks (Enter key) to separate lines
- Avoid special symbols that might not display correctly

## Database Schema

### payment_methods Table
```sql
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    qr_code VARCHAR(255),
    bank_details TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### booking_payment_methods Table
```sql
CREATE TABLE booking_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_payment_method (booking_id, payment_method_id)
);
```

## API Functions

### getActivePaymentMethods()
Returns all active payment methods ordered by display order.

```php
$methods = getActivePaymentMethods();
foreach ($methods as $method) {
    echo $method['name'];
}
```

### getBookingPaymentMethods($booking_id)
Returns payment methods linked to a specific booking.

```php
$methods = getBookingPaymentMethods($booking_id);
```

### linkPaymentMethodsToBooking($booking_id, $payment_method_ids)
Links multiple payment methods to a booking.

```php
linkPaymentMethodsToBooking($booking_id, [1, 2, 3]);
```

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the activity logs in Admin Panel
3. Check database for any errors
4. Contact support with specific error messages

## Version History

### v1.0.0 - Initial Release
- Payment methods CRUD interface
- QR code upload support
- Bank details management
- Booking integration
- Email notification integration
- WhatsApp message support

## Future Enhancements

Potential future features:
- Online payment gateway integration
- Payment tracking and confirmation
- Automatic payment verification
- Payment history per customer
- Multiple QR codes per payment method
- Payment method analytics
