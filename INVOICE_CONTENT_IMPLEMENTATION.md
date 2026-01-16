# Invoice Content Settings - Implementation Guide

## Overview
This implementation removes all hardcoded data from the bill print feature, ensuring that all information is pulled from the billing system database exactly as it is stored. This addresses the requirement that no data should be hardcoded, and everything should be pulled from the database.

## What Was Changed

### Previously Hardcoded Elements (Now Database-Driven)
1. **Invoice Title**: "Wedding Booking Confirmation & Partial Payment Receipt"
2. **Cancellation Policy**: All 5 policy terms
3. **Invoice Disclaimer**: Footer disclaimer note
4. **Package Label**: "Marriage Package"
5. **Additional Items Label**: "Additional Items"

### Database Migration
A new migration file has been created: `database/migrations/add_invoice_content_settings.sql`

This adds five new settings to the `settings` table:
- `invoice_title` - The main title displayed on printed invoices
- `cancellation_policy` - Multi-line cancellation policy (each line becomes a bullet point)
- `invoice_disclaimer` - Disclaimer note shown at the bottom
- `invoice_package_label` - Label for hall/venue package line item
- `invoice_additional_items_label` - Label for additional services line items

### Admin Interface Updates
The Admin Settings page (`admin/settings/index.php`) has been updated with a new "Invoice Content" section under the Company/Invoice tab. Administrators can now customize:
- Invoice title
- Cancellation policy (textarea with multiple lines)
- Invoice disclaimer
- Package label
- Additional items label

### Bill Print Updates
The booking view page (`admin/bookings/view.php`) has been updated to:
- Pull invoice title from `getSetting('invoice_title')` instead of hardcoded value
- Pull cancellation policy from `getSetting('cancellation_policy')` and dynamically generate bullet points
- Pull disclaimer from `getSetting('invoice_disclaimer')` instead of hardcoded text
- Pull package label from `getSetting('invoice_package_label')` instead of hardcoded "Marriage Package"
- Pull additional items label from `getSetting('invoice_additional_items_label')` instead of hardcoded "Additional Items"

## Installation Instructions

### Step 1: Apply Database Migration

Run the migration script:
```bash
chmod +x apply-invoice-content-migration.sh
./apply-invoice-content-migration.sh
```

Or manually apply the migration:
```bash
mysql -u your_user -p your_database < database/migrations/add_invoice_content_settings.sql
```

### Step 2: Configure Invoice Content

1. Login to Admin Panel
2. Go to **Settings** → **Company/Invoice** tab
3. Scroll down to the "Invoice Content" section
4. Customize the following fields:
   - **Invoice Title**: Update the main invoice header
   - **Cancellation Policy**: Enter policy terms (one per line)
   - **Invoice Disclaimer**: Update the footer disclaimer
   - **Package Label**: Update the label for hall/venue packages
   - **Additional Items Label**: Update the label for additional services
5. Click **Save Settings**

### Step 3: Verify Changes

1. Go to **Bookings** → **View Bookings**
2. Click on any booking to view details
3. Click the **Print** button
4. Verify that the invoice displays your customized content

## Default Values

If settings are not configured, the system uses these defaults:
- **Invoice Title**: "Wedding Booking Confirmation & Partial Payment Receipt"
- **Cancellation Policy**: 
  - Advance payment is non-refundable in case of cancellation.
  - Full payment must be completed 7 days before the event date.
  - Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
  - Cancellations made less than 30 days before the event are non-refundable.
  - Date changes are subject to availability and must be requested at least 15 days in advance.
- **Invoice Disclaimer**: "Note: This is a computer-generated estimate bill. Please create a complete invoice yourself."
- **Package Label**: "Marriage Package"
- **Additional Items Label**: "Additional Items"

## Technical Details

### Cancellation Policy Format
The cancellation policy is stored as a multi-line text. When displayed on the invoice:
1. Each line is trimmed of whitespace
2. Empty lines are removed
3. Each remaining line is displayed as a bullet point (`<li>` element)

### Invoice Title Format
The invoice title supports line breaks. Use `\n` in the database or press Enter in the admin interface to create multi-line titles.

### Security
All displayed content is properly escaped using `htmlspecialchars()` to prevent XSS attacks.

## Benefits

1. **No Hardcoded Data**: All invoice content is now database-driven
2. **Easy Customization**: Administrators can update content without code changes
3. **Multi-Language Support**: Content can be easily changed to support different languages
4. **Business Rule Flexibility**: Cancellation policies can be updated as business needs change
5. **Consistency**: All data comes from the same source (database)

## Files Modified

1. `database/migrations/add_invoice_content_settings.sql` - New migration file
2. `apply-invoice-content-migration.sh` - New migration script
3. `admin/settings/index.php` - Added invoice content settings fields
4. `admin/bookings/view.php` - Updated to use database settings instead of hardcoded values

## Testing Checklist

- [ ] Migration applies successfully without errors
- [ ] New settings appear in Admin Panel → Settings → Company/Invoice tab
- [ ] Default values are shown when settings are not configured
- [ ] Settings can be saved successfully
- [ ] Invoice title appears correctly on printed bills
- [ ] Cancellation policy lines appear as bullet points
- [ ] Invoice disclaimer appears at the bottom
- [ ] Package label appears on invoice for hall/venue
- [ ] Additional items label appears on invoice for services
- [ ] All content is properly escaped (no XSS vulnerabilities)
- [ ] Multi-line content displays correctly
- [ ] Changes persist after page reload

## Troubleshooting

### Settings Not Showing in Admin Panel
- Verify the migration was applied successfully
- Check database for `invoice_title`, `cancellation_policy`, `invoice_disclaimer`, `invoice_package_label`, and `invoice_additional_items_label` in the `settings` table
- Clear any application caches

### Content Not Appearing on Invoice
- Verify settings are saved in the database
- Check that `getSetting()` function is working correctly
- Verify the setting keys match exactly: `invoice_title`, `cancellation_policy`, `invoice_disclaimer`, `invoice_package_label`, `invoice_additional_items_label`

### Formatting Issues
- For cancellation policy, ensure each policy term is on a separate line
- For invoice title, use line breaks (Enter key) for multi-line titles
- Check that HTML special characters are displaying correctly

## Future Enhancements

Potential improvements for future versions:
1. Add rich text editor for cancellation policy
2. Support for multiple languages
3. Template system for different invoice types
4. Version history for policy changes
5. Preview functionality in settings page

## Compliance

This implementation ensures:
- All invoice data comes from the billing system database
- No hardcoded values in the bill print feature
- Data is displayed exactly as stored in the database
- Full administrator control over invoice content
