# Print Bill Feature - Complete Guide

## Overview
The print bill feature allows administrators to print professional, print-friendly invoices/bills for bookings directly from the booking view page. The invoice is designed to fit on A4 size paper (2 pages maximum) and includes all relevant booking details.

## Features

### What's Included in the Bill
The printed bill includes all comprehensive details:

1. **Company Header**
   - Company logo (pulled from settings)
   - Company name
   - Company address
   - Phone and email

2. **Invoice Details**
   - Invoice/Booking date
   - Booking number
   - Event date and time

3. **Customer Information**
   - Full name
   - Phone number
   - Email address
   - Event date and shift
   - Venue and hall details
   - Number of guests

4. **Booking Details Table**
   - Hall/venue package with pricing
   - All selected menus with price per person and total
   - Additional services/snacks with pricing
   - Subtotal, tax, and grand total

5. **Payment Information**
   - Advance payment received
   - Balance due amount
   - Amount in words
   - Payment mode

6. **Important Information**
   - Cancellation policy
   - Terms and conditions

7. **Footer**
   - Signature section
   - Thank you message
   - Contact information

## Setup Instructions

### Step 1: Apply Database Migration

Run the migration script to add company settings to your database:

```bash
chmod +x apply-company-settings-migration.sh
./apply-company-settings-migration.sh
```

Or manually run the SQL:
```bash
mysql -u your_user -p your_database < database/migrations/add_company_settings.sql
```

### Step 2: Configure Company Settings

1. Login to Admin Panel
2. Go to **Settings** → **Company/Invoice** tab
3. Fill in the following details:
   - **Company Name**: Your business name (defaults to website name if not set)
   - **Company Phone**: Contact phone for invoices
   - **Company Email**: Contact email for invoices  
   - **Company Address**: Full business address
   - **Company Logo**: Upload a logo specifically for invoices
     - Recommended size: 200x80px
     - Format: PNG with transparent background
     - If not set, the website logo will be used as fallback

4. Click **Save Settings**

### Step 3: Print a Bill

1. Go to **Bookings** → **View Bookings**
2. Click on any booking to view details
3. Click the **Print** button at the top of the page
4. The print dialog will open with a formatted invoice
5. Select your printer or "Save as PDF" option
6. The invoice is optimized for A4 paper size

## Technical Details

### Database Settings
The following settings are added to the `settings` table:
- `company_name` - Company name for invoices
- `company_address` - Company address for invoices
- `company_phone` - Company phone for invoices
- `company_email` - Company email for invoices
- `company_logo` - Path to company logo file

### Fallback Behavior
If company-specific settings are not configured, the system automatically falls back to:
- `site_name` → `company_name`
- `contact_address` → `company_address`
- `contact_phone` → `company_phone`
- `contact_email` → `company_email`
- `site_logo` → `company_logo`

### Print Styles
- Uses print-specific CSS with `@media print`
- Hides navigation, buttons, and other non-essential elements when printing
- A4 page size with proper margins (15mm)
- Black and white optimized for better printing
- Professional invoice layout with borders and proper spacing
- Page break handling to avoid splitting important sections

## File Locations

- **View/Print Page**: `/admin/bookings/view.php`
- **Settings Page**: `/admin/settings/index.php`
- **Migration File**: `/database/migrations/add_company_settings.sql`
- **Migration Script**: `/apply-company-settings-migration.sh`

## Troubleshooting

### Logo Not Showing
- Verify the logo file exists in the `uploads/` directory
- Check that the file path in settings is correct
- Ensure the image format is supported (PNG, JPG, GIF)
- Check file permissions (should be readable)

### Settings Not Saving
- Verify database connection
- Check that the migration has been applied
- Look at error logs for any PHP errors
- Ensure proper file upload permissions for logo files

### Print Layout Issues
- Use modern browsers (Chrome, Firefox, Edge) for best results
- Check print preview before printing
- Adjust printer settings if needed (margins, orientation)
- For PDF export, use "Save as PDF" option in print dialog

## Browser Compatibility

The print feature works best with:
- Google Chrome (recommended)
- Mozilla Firefox
- Microsoft Edge
- Safari

## Tips for Best Results

1. **Logo**: Use a high-resolution PNG with transparent background for professional appearance
2. **Testing**: Always preview before printing to check layout
3. **PDF Export**: Use "Save as PDF" to create digital copies
4. **Settings**: Configure all company details for complete invoices
5. **Consistency**: Use the same logo and details across all invoices

## Support

For issues or questions:
1. Check this documentation first
2. Verify all settings are configured correctly
3. Check the browser console for any errors
4. Review the application error logs

## Updates

Version 1.0 - Initial Release
- Complete invoice layout with all booking details
- Company settings configuration
- Logo support with fallback
- A4-optimized print styles
- Professional invoice design
