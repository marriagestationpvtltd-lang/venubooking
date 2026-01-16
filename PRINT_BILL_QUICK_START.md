# Print Bill Feature - Quick Start Guide

## What This Feature Does

This feature enables printing professional, A4-sized invoices/bills for venue bookings directly from the admin panel. The bill pulls all necessary details from the database including:

- **Company Information**: Logo, name, address, phone, email
- **Customer Details**: Name, contact info, event details
- **Venue & Hall**: Selected venue and hall with capacity
- **Menus**: All selected menus with per-person pricing
- **Services**: Additional services/snacks selected
- **Payment Details**: Advance paid, balance due, payment mode
- **Terms**: Cancellation policy and important notes

## Quick Setup (3 Steps)

### Step 1: Run the Migration

```bash
cd /path/to/venubooking
./apply-company-settings-migration.sh
```

This adds the following fields to your `settings` table:
- company_name
- company_address  
- company_phone
- company_email
- company_logo

### Step 2: Configure Company Settings

1. Login to Admin Panel at `/admin/`
2. Click **Settings** in the sidebar
3. Go to the **Company/Invoice** tab
4. Fill in your company details:
   ```
   Company Name: Your Business Name
   Company Phone: +977 1-1234567
   Company Email: info@yourbusiness.com
   Company Address: Your full business address
   Company Logo: Upload a logo (200x80px PNG recommended)
   ```
5. Click **Save Settings**

**Note**: If you don't fill in company-specific fields, the system automatically uses your general website settings as fallback.

### Step 3: Print a Bill

1. Go to **Bookings** → **View Bookings**
2. Click on any booking to view details
3. Click the **Print** button at the top
4. Choose your printer or "Save as PDF"

## Example Output

The printed bill will look like this:

```
┌──────────────────────────────────────────────┐
│           [YOUR COMPANY LOGO]                │
│         YOUR COMPANY NAME                    │
│    Your Address, City, Country               │
│    Phone: +977 1-1234567                     │
│    Email: info@yourcompany.com               │
├──────────────────────────────────────────────┤
│   WEDDING BOOKING CONFIRMATION &             │
│      PARTIAL PAYMENT RECEIPT                 │
└──────────────────────────────────────────────┘

Invoice Date: January 16, 2024
Booking Date: January 15, 2024  
Booking No: BK-20240115-0001

┌─── CUSTOMER DETAILS ─────────────────────────┐
│ Booked By: John Doe                          │
│ Mobile: +977 98xxxxxxxx                      │
│ Email: john@example.com                      │
│ Event Date: February 14, 2024 (Evening)      │
│ Venue: Royal Palace - Sagarmatha Hall        │
│ Guests: 500                                  │
└──────────────────────────────────────────────┘

┌─── BOOKING DETAILS ──────────────────────────┐
│ Description          Qty    Rate     Amount  │
├──────────────────────────────────────────────┤
│ Marriage Package      1   150,000  150,000   │
│ Royal Gold Menu     500    2,399  1,199,500  │
│ Snacks - Samosa       1   25,000    25,000   │
├──────────────────────────────────────────────┤
│ Subtotal:                         1,374,500  │
│ Tax (13%):                          178,705  │
│ GRAND TOTAL:                      1,553,205  │
└──────────────────────────────────────────────┘

┌─── PAYMENT DETAILS ──────────────────────────┐
│ Advance Payment (30%):      NPR 465,961.50   │
│ Total Due Amount:           NPR 1,087,243.50 │
│ Amount in Words: Fifteen Lakh Fifty Three    │
│                  Thousand Two Hundred Five    │
│ Payment Mode: Bank Transfer                  │
└──────────────────────────────────────────────┘

IMPORTANT - CANCELLATION POLICY
• Advance payment is non-refundable
• Full payment due 7 days before event
• 50% refund if cancelled 30+ days prior
• No refund if cancelled <30 days before event

                    _____________________
                    YOUR COMPANY NAME
                    Authorized Signature

Thank you for choosing YOUR COMPANY NAME!
For queries: +977 1-1234567
```

## Features & Benefits

### For Administrators
- ✅ Quick print with one click
- ✅ Professional invoice layout
- ✅ All details pulled from database automatically
- ✅ No manual data entry required
- ✅ Print or save as PDF

### For Customers
- ✅ Professional appearance
- ✅ All booking details clearly listed
- ✅ Payment information transparent
- ✅ Terms and conditions included
- ✅ Easy to read and understand

### Technical Features
- ✅ Optimized for A4 paper (210mm × 297mm)
- ✅ Maximum 2 pages
- ✅ Black & white print-friendly
- ✅ Proper page break handling
- ✅ Logo displays correctly
- ✅ Security validated file paths

## Customization Options

### Logo Specifications
- **Size**: 200×80 pixels (recommended)
- **Format**: PNG with transparent background (recommended)
- **Alternative formats**: JPG, GIF also supported
- **Fallback**: Company name shown if no logo

### Settings Fallback Chain
If company-specific settings are empty, the system uses:
- `company_name` → `site_name` → "Wedding Venue Booking"
- `company_address` → `contact_address` → "Nepal"
- `company_phone` → `contact_phone` → "N/A"
- `company_email` → `contact_email` → ""
- `company_logo` → `site_logo` → [Company Name Text]

## Troubleshooting

### Logo Not Showing
**Problem**: Logo doesn't appear in print
**Solution**: 
1. Check the file is uploaded to `uploads/` directory
2. Verify file permissions (should be readable)
3. Confirm file format is PNG, JPG, or GIF
4. Try re-uploading the logo

### Print Layout Cut Off
**Problem**: Some content is cut off when printing
**Solution**:
1. Check your printer margins (should be 15mm minimum)
2. Use "Fit to page" option in print dialog
3. Try print preview before printing
4. Use Chrome or Firefox for best results

### Settings Not Saving
**Problem**: Company settings don't save
**Solution**:
1. Verify database migration was applied
2. Check database connection
3. Look at PHP error logs for details
4. Ensure file upload directory is writable

## Browser Compatibility

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | Latest | ✅ Full |
| Firefox | Latest | ✅ Full |
| Edge | Latest | ✅ Full |
| Safari | Latest | ✅ Full |
| IE | Any | ❌ Not supported |

## Need Help?

1. Check [PRINT_BILL_GUIDE.md](PRINT_BILL_GUIDE.md) for detailed documentation
2. Verify migration was applied correctly
3. Check all settings are configured
4. Review browser console for errors
5. Check application error logs

## Future Enhancements

Potential improvements for future versions:
- Multiple logo support (header/footer)
- Custom invoice templates
- Multi-language support
- PDF generation without print dialog
- Email invoice directly to customer
- Custom terms and conditions per venue
