# Quick Start: Invoice Content Settings

## What This Feature Does
Removes all hardcoded data from bill printing. Now administrators can customize invoice content through the admin panel without touching code.

## Quick Setup (2 Minutes)

### Step 1: Apply Database Migration
```bash
cd /home/runner/work/venubooking/venubooking
chmod +x apply-invoice-content-migration.sh
./apply-invoice-content-migration.sh
```

Or manually:
```bash
mysql -u your_user -p your_database < database/migrations/add_invoice_content_settings.sql
```

### Step 2: Configure Settings (Optional)
1. Login to admin panel
2. Go to **Settings** → **Company/Invoice** tab
3. Scroll to "Invoice Content" section
4. Customize:
   - Invoice title
   - Cancellation policy (one term per line)
   - Invoice disclaimer
   - Package label
   - Additional items label
5. Click **Save Settings**

### Step 3: Test
1. Go to **Bookings** → View any booking
2. Click **Print** button
3. Verify customized content appears

## Default Behavior
If you don't configure settings, the system uses sensible defaults:
- Invoice Title: "Wedding Booking Confirmation & Partial Payment Receipt"
- Standard cancellation policy (5 terms)
- Standard disclaimer
- Package Label: "Marriage Package"
- Additional Items Label: "Additional Items"

## Key Benefits
✅ No hardcoded data - everything from database  
✅ Easy customization through admin panel  
✅ Multi-language ready  
✅ No code changes needed  
✅ Backward compatible  

## Files Changed
- `database/migrations/add_invoice_content_settings.sql` - New settings
- `admin/settings/index.php` - Admin interface
- `admin/bookings/view.php` - Bill print logic
- Documentation files (this and others)

## Need Help?
See `INVOICE_CONTENT_IMPLEMENTATION.md` for detailed documentation.
