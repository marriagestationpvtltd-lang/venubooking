# System Audit & Testing Guide

## Overview
This document provides a comprehensive testing guide for the Venue Booking System before production deployment. Follow this step-by-step to ensure all functionality works correctly.

## Table of Contents
1. [Pre-Testing Setup](#pre-testing-setup)
2. [A. User Booking Flow Tests](#a-user-booking-flow-tests)
3. [B. Admin Panel Tests](#b-admin-panel-tests)
4. [C. Mobile Responsiveness Tests](#c-mobile-responsiveness-tests)
5. [D. Database Integrity Tests](#d-database-integrity-tests)
6. [E. Payment Flow Tests](#e-payment-flow-tests)
7. [F. Nepali Date System Tests](#f-nepali-date-system-tests)
8. [G. Edge Cases & Error Handling](#g-edge-cases--error-handling)
9. [Automated Validation](#automated-validation)
10. [Pre-Live Checklist](#pre-live-checklist)

---

## Pre-Testing Setup

### Requirements
- ✅ Database fully populated with test data
- ✅ At least one venue, hall, menu, and service created
- ✅ Admin account set up and working
- ✅ Email settings configured (optional but recommended)
- ✅ Test on multiple devices: Desktop, Tablet, Mobile

### Test Accounts
```
Admin Login: /admin/
Username: admin
Password: [Set during installation]
```

### Run Automated Tests First
```
URL: /test-system-validation.php
This will run 20+ automated validation tests
```

---

## A. User Booking Flow Tests

### Test 1: Initial Booking Form (index.php)

#### Desktop Testing
- [ ] **Page Load**
  - Page loads without errors
  - Hero section displays properly
  - Booking form visible and centered
  
- [ ] **Form Fields Validation**
  - **Shift Selection**
    - Select shift dropdown shows all options
    - Required field indicator (*) visible
    - Error shows if submitted without selection
    
  - **Event Date**
    - Date picker opens when clicking field
    - Shows Nepali calendar (BS) by default
    - Toggle button switches between BS/AD calendars
    - Cannot select past dates
    - Selected date displays in readable format
    
  - **Number of Guests**
    - Minimum value: 10
    - Shows error if less than 10
    - Placeholder text visible: "Enter number of guests (minimum 10)"
    - Accepts numeric input only
    
  - **Event Type**
    - Dropdown shows: Wedding, Birthday Party, Corporate Event, Anniversary, Other Events
    - Required field validation works
    - Error message displays if not selected

- [ ] **Form Submission**
  - Click "ONLINE BOOKING" button
  - All required fields validated before submission
  - Invalid fields highlighted in red with error messages
  - Valid submission redirects to Step 2

#### Mobile Testing (Phone)
- [ ] All form fields visible without horizontal scroll
- [ ] Input fields are at least 44px height (touch-friendly)
- [ ] Font size 16px (prevents iOS zoom on focus)
- [ ] Dropdown menus easily selectable
- [ ] Date picker works on mobile devices
- [ ] Submit button visible and easily tappable

---

### Test 2: Venue & Hall Selection (booking-step2.php)

- [ ] **Progress Bar**
  - Shows Step 1 completed (green checkmark)
  - Step 2 highlighted as active
  - Steps 3-5 shown as pending

- [ ] **Booking Summary Bar**
  - Displays selected date, shift, guests, event type
  - Shows total cost (updates dynamically)
  - All information readable on mobile

- [ ] **Venue Display**
  - All active venues displayed
  - Venue images load properly (or fallback image shown)
  - Venue names and descriptions visible
  - "Select Hall" button prominent

- [ ] **Hall Selection**
  - Halls load when venue selected
  - Shows hall name, capacity, price
  - Base price displayed clearly
  - Unavailable halls marked or hidden
  - Selection highlights chosen hall

- [ ] **Navigation**
  - "Back to Event Details" button works
  - "Continue to Menu Selection" enabled after hall selection
  - Session data preserved on back navigation

#### Mobile Specific
- [ ] Venue cards stack vertically
- [ ] Images resize appropriately
- [ ] No horizontal scrolling required
- [ ] Buttons remain visible and tappable

---

### Test 3: Menu Selection (booking-step3.php)

- [ ] **Menu Display**
  - All active menus displayed as cards
  - Menu name, price per person visible
  - Menu items list displayed
  - Checkbox for selection works
  
- [ ] **Multiple Selection**
  - Can select multiple menus
  - Selected menus highlighted
  - Price updates in real-time
  - Total calculation accurate: (price per person × guests)

- [ ] **Optional Selection**
  - Can proceed without selecting menus
  - "Skip" or "No menu needed" option available
  - System handles no menu selection gracefully

- [ ] **Price Calculation**
  - Menu total = (Sum of selected menu prices) × number of guests
  - Total displays in booking summary
  - Currency format correct (NPR X,XXX.XX)

#### Mobile Testing
- [ ] Menu cards stack properly
- [ ] Checkboxes at least 24px size
- [ ] Selected state clearly visible
- [ ] Price updates visible without scrolling

---

### Test 4: Additional Services (booking-step4.php)

- [ ] **Service Display**
  - All active services displayed
  - Service name, description, price visible
  - Grouped by category (if applicable)
  - Checkbox selection works

- [ ] **Optional Services**
  - Can proceed without selecting services
  - System shows "No services selected" message
  - Default handling: service total = 0

- [ ] **Multiple Selection**
  - Can select multiple services
  - Selected services highlighted
  - Prices add up correctly
  - Total updates in summary bar

- [ ] **Missing Data Handling**
  - Services without description show "N/A"
  - Services without category grouped as "Other"
  - Missing prices default to 0.00

#### Mobile Testing
- [ ] Services collapsible on mobile
- [ ] "View Services" toggle works
- [ ] No horizontal scroll for service cards
- [ ] Checkboxes easily selectable

---

### Test 5: Confirmation & Payment (booking-step5.php)

#### Step 1: Customer Information
- [ ] **Required Fields Validation**
  - Full Name: Required, shows error if empty
  - Phone Number: Required, validates format (10+ digits)
  - Placeholder text visible: "Enter your phone number (10+ digits)"
  
- [ ] **Optional Fields**
  - Email: Optional, validates format if provided
  - Address: Optional, no validation
  - Special Requests: Optional, text area expandable
  - Fields clearly marked as "(Optional)"

- [ ] **Field Validation**
  - Invalid phone shows error: "Please enter a valid phone number (10+ digits)"
  - Invalid email shows error: "Please enter a valid email address"
  - Validation happens on blur and on continue click
  - First invalid field gets focus on error

- [ ] **Continue Button**
  - "Continue to View Bill" button visible
  - Click validates required fields
  - Smooth scroll to Step 2 on success

#### Step 2: Bill Summary
- [ ] **Cost Breakdown Display**
  - Hall Cost shows with icon
  - Menu Cost shows (if menus selected)
  - Services Cost shows (if services selected)
  - Subtotal calculated correctly
  - Tax row shows IF tax rate > 0
  - Tax row HIDDEN if tax rate = 0
  - Grand Total prominently displayed

- [ ] **Tax Calculation Accuracy**
  - Test with tax rate = 13%: Verify calculation
  - Test with tax rate = 0%: Verify no tax row shown
  - Formula: Tax Amount = Subtotal × (Tax Rate / 100)
  - Grand Total = Subtotal + Tax Amount

- [ ] **Advance Payment Info**
  - Shows advance percentage (e.g., 25%)
  - Calculates advance amount correctly
  - Displays prominently in info box

- [ ] **Navigation**
  - "Back to Information" returns to Step 1 with data preserved
  - "Continue to Payment Options" proceeds to Step 3

#### Step 3: Payment Options
- [ ] **Payment Option Selection**
  - Two radio buttons visible
  - "Confirm Booking Without Payment" (default selected)
  - "Confirm Booking With Payment"
  - Only one selectable at a time

- [ ] **Without Payment Option**
  - Selected by default
  - Payment details section hidden
  - Submit button text: "Confirm Booking"
  - Submission creates booking with status: 'pending'

- [ ] **With Payment Option**
  - Selecting shows payment details section
  - Submit button text: "Confirm Booking & Submit Payment"
  - Required fields appear:
    - Payment Method dropdown (required)
    - Transaction ID / Reference Number (required)
    - Paid Amount (required, numeric, > 0)
    - Payment Slip Upload (required, image file)

- [ ] **Payment Method Selection**
  - Dropdown lists all active payment methods
  - Selecting method shows bank details or QR code
  - Details formatted properly and readable
  - Different methods show different information

- [ ] **Payment Validation**
  - All payment fields validated on submit
  - Error messages clear and specific
  - File upload validates:
    - File is image (jpg, jpeg, png)
    - File size reasonable (< 5MB)
    - Shows preview if possible

- [ ] **Form Submission**
  - "Confirm Booking" button submits form
  - Loading indicator shows during processing
  - Success redirects to confirmation page
  - Error displays at top of form with details

#### Mobile Testing
- [ ] All three steps clearly separated
- [ ] Form fields at least 48px height
- [ ] Radio buttons at least 24px size
- [ ] File upload works on mobile (camera option available)
- [ ] Payment details readable without zoom
- [ ] Submit button always visible (fixed or scrollable)

---

### Test 6: Confirmation Page (confirmation.php)

- [ ] **Success Message**
  - Displays confirmation message
  - Shows booking number (format: BK-YYYYMMDD-XXXX)
  - Thank you message visible

- [ ] **Booking Summary Display**
  - Customer details shown
  - Event details shown (date, shift, venue, hall)
  - Selected menus listed
  - Selected services listed
  - Total cost breakdown displayed
  - Payment status shown

- [ ] **Print Functionality**
  - "Print Booking" button visible
  - Click opens print dialog
  - Print view formatted properly
  - No navigation bars in print
  - Single page layout

- [ ] **Email Notification**
  - Email sent to customer (if email provided)
  - Email sent to admin
  - Email contains all booking details
  - Email format is professional

---

## B. Admin Panel Tests

### Test 1: Admin Login & Dashboard

- [ ] **Login**
  - Access /admin/
  - Login form displays
  - Invalid credentials show error
  - Valid credentials redirect to dashboard
  - Session maintained on refresh

- [ ] **Dashboard**
  - Statistics cards display
  - Recent bookings shown
  - Charts/graphs load (if present)
  - All navigation links work

---

### Test 2: Bookings Management

#### View All Bookings (admin/bookings/index.php)
- [ ] **Bookings List**
  - All bookings displayed in table
  - Columns: Booking#, Customer, Date, Venue/Hall, Status, Payment, Actions
  - Data formatted properly
  - Status badges color-coded
  - Payment status clear

- [ ] **Filtering & Search**
  - Filter by booking status works
  - Filter by payment status works
  - Date range filter works
  - Search by booking number works
  - Search by customer name works

- [ ] **Quick Actions**
  - View button opens booking details
  - Edit button loads edit form
  - Delete button shows confirmation (if present)
  - Payment status update dropdown works

- [ ] **Mobile Table**
  - Table responsive (no horizontal scroll)
  - Important columns visible
  - Actions accessible on mobile
  - Dropdown menus work on touch devices

#### View Booking Details (admin/bookings/view.php)
- [ ] **Booking Information Display**
  - All customer details visible
  - Event information complete
  - Hall/venue details shown
  - Selected menus listed with quantities
  - Selected services listed with descriptions
  - Service descriptions show (or "N/A" if missing)
  - Special requests displayed
  - Booking status badge shown
  - Payment status badge shown

- [ ] **Cost Breakdown**
  - Hall price shown
  - Menu costs itemized (price per person × guests)
  - Services costs itemized
  - Subtotal correct
  - Tax row shows IF tax rate > 0
  - Tax row HIDDEN if tax rate = 0
  - Grand total correct

- [ ] **Payment Information**
  - Advance payment amount shown
  - Advance payment received shown
  - Balance due calculated correctly
  - Amount in words displayed
  - Payment method shown

- [ ] **Payment Records**
  - If payment submitted, details shown
  - Transaction ID visible
  - Payment slip image displayed
  - Payment date shown
  - Verification status shown

- [ ] **Quick Status Update**
  - Booking status dropdown accessible
  - Can update without opening edit form
  - Status changes reflect immediately
  - Email notification sent on status change

- [ ] **Payment Request Features**
  - "Send Payment Request" button visible
  - Email option works (if email configured)
  - WhatsApp option opens WhatsApp with message

- [ ] **Invoice/Print View**
  - "Print Invoice" button prominent
  - Click opens print-friendly view
  - All information fits on single page
  - No unnecessary fields shown
  - Company logo/details at top (from settings)
  - Customer details section
  - Booking details table
  - Cost breakdown
  - Tax handling correct (hidden if 0)
  - Payment calculation section
  - Cancellation policy at bottom
  - Professional layout

- [ ] **Mobile View**
  - All sections visible without horizontal scroll
  - Cards stack vertically
  - Buttons accessible
  - Print function works on mobile
  - Status update dropdown usable

#### Edit Booking (admin/bookings/edit.php)
- [ ] **Form Pre-filled**
  - All current data loaded
  - Customer details editable
  - Event details modifiable
  - Can change hall
  - Can modify menus
  - Can modify services
  - Special requests editable

- [ ] **Validation on Edit**
  - Required fields validated
  - Date cannot be in past
  - Hall availability checked
  - Price recalculated on changes

- [ ] **Save Changes**
  - "Update Booking" button works
  - Changes saved to database
  - Confirmation message shown
  - Redirects to booking details
  - Email notification sent (optional)

- [ ] **Mobile Editing**
  - Form fields accessible
  - Dropdowns work
  - Date picker functional
  - Save button visible

---

### Test 3: Payment Management

#### Payment Status Updates
- [ ] **Status Options**
  - Pending
  - Partial
  - Paid
  - Cancelled

- [ ] **Status Change Validation**
  - Cannot downgrade status (paid → pending)
  - Confirmation required for cancellation
  - Status reflects in booking list

- [ ] **Payment Records**
  - View all payments for booking
  - Add payment manually (admin panel)
  - Record transaction details
  - Upload payment proof

---

### Test 4: Settings Management

- [ ] **Tax Rate Setting**
  - Current tax rate displayed
  - Can update tax rate
  - Validation: must be numeric
  - Changes apply to new bookings
  - Test with 0% tax rate

- [ ] **Currency Setting**
  - Currency symbol editable
  - Currency code editable
  - Changes reflect across system

- [ ] **Advance Payment Percentage**
  - Current percentage shown
  - Can update (e.g., 25%, 50%)
  - Applies to future bookings

- [ ] **Company Information**
  - Company name editable
  - Address, phone, email editable
  - Appears on invoices
  - Missing fields have fallback values

---

## C. Mobile Responsiveness Tests

### Device Testing Matrix

Test on the following devices/screen sizes:

#### Desktop
- [ ] 1920x1080 (Large Desktop)
- [ ] 1366x768 (Standard Laptop)
- [ ] 1280x720 (Small Laptop)

#### Tablet
- [ ] iPad (768x1024)
- [ ] Android Tablet (800x1280)
- [ ] Landscape and Portrait modes

#### Mobile
- [ ] iPhone 14 Pro (390x844)
- [ ] iPhone SE (375x667)
- [ ] Samsung Galaxy S21 (360x800)
- [ ] Small Android (320x568)

### Responsive Elements Checklist

#### Booking Form Pages
- [ ] **Touch Targets**
  - All buttons minimum 44x44 pixels
  - Form fields minimum 48px height
  - Checkboxes minimum 24x24 pixels
  - Radio buttons minimum 24x24 pixels
  - Adequate spacing between tappable elements

- [ ] **Form Fields**
  - Font size 16px (prevents iOS zoom)
  - Placeholders visible
  - Labels readable
  - Error messages visible
  - Input fields full width on mobile

- [ ] **Navigation**
  - Progress bar visible
  - Continue buttons visible at bottom
  - Back buttons accessible
  - Fixed bottom navigation (if used)

- [ ] **Content**
  - No horizontal scrolling required
  - Text readable without zooming
  - Images scale properly
  - Cards stack vertically

#### Admin Panel
- [ ] **Dashboard**
  - Stat cards stack on mobile
  - Charts resize properly
  - Sidebar menu accessible

- [ ] **Tables**
  - No horizontal scroll required
  - Important columns visible
  - Actions accessible
  - Pagination works

- [ ] **Forms**
  - Full-width inputs
  - Dropdowns usable
  - File uploads work
  - Submit buttons visible

- [ ] **Invoice Print**
  - Fits on single page
  - Readable on mobile screen
  - Print preview correct

### Orientation Testing
- [ ] Portrait mode: All content visible
- [ ] Landscape mode: Layout adjusts properly
- [ ] Rotation smooth, no content loss

### Browser Testing

#### Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

#### Mobile Browsers
- [ ] Safari iOS
- [ ] Chrome Android
- [ ] Samsung Internet
- [ ] Firefox Mobile

---

## D. Database Integrity Tests

### Test 1: Required Fields

- [ ] **Bookings Table**
  - booking_number: Auto-generated, unique
  - customer_id: Foreign key, required
  - hall_id: Foreign key, required
  - event_date: Required, date format
  - shift: Required, enum value
  - number_of_guests: Required, > 0
  - grand_total: Required, decimal

- [ ] **Customers Table**
  - full_name: Required, not null
  - phone: Required, not null
  - email: Optional, nullable
  - address: Optional, nullable

- [ ] **Bookings_Menus Table**
  - booking_id: Foreign key
  - menu_id: Foreign key
  - price_per_person: Required
  - number_of_guests: Required
  - total_price: Required

- [ ] **Bookings_Services Table**
  - booking_id: Foreign key
  - service_id: Foreign key
  - price: Required (default 0.00 if missing)

### Test 2: Default Values

- [ ] **Tax Amount**
  - Defaults to 0.00 if tax rate is 0
  - Calculates correctly if tax rate > 0
  - Stored in bookings table

- [ ] **Services Total**
  - Defaults to 0.00 if no services selected
  - Calculates sum of selected services
  - Stored correctly

- [ ] **Optional Fields**
  - Email: NULL if not provided
  - Address: NULL or empty string
  - Special requests: NULL or empty string
  - Service descriptions: NULL shows as "N/A"

### Test 3: Data Integrity

- [ ] **Foreign Key Constraints**
  - Cannot delete hall with existing bookings
  - Cannot delete menu with existing booking_menus
  - Cannot delete service with existing booking_services
  - Orphaned records prevented

- [ ] **Cascade Deletes**
  - Deleting booking removes booking_menus records
  - Deleting booking removes booking_services records
  - Deleting booking removes payment records

- [ ] **Data Consistency**
  - Total amounts match breakdown
  - Grand total = hall + menus + services + tax
  - Advance amount ≤ grand total
  - Balance due = grand total - total paid

### Test 4: Query for Missing Data

Run this SQL to find any missing or problematic data:

```sql
-- Find bookings with missing customer data
SELECT b.id, b.booking_number, c.full_name, c.phone 
FROM bookings b
LEFT JOIN customers c ON b.customer_id = c.id
WHERE c.full_name IS NULL OR c.full_name = '' 
   OR c.phone IS NULL OR c.phone = '';

-- Find services without descriptions
SELECT id, name, description 
FROM additional_services 
WHERE description IS NULL OR description = '';

-- Find bookings with zero grand total
SELECT id, booking_number, grand_total 
FROM bookings 
WHERE grand_total = 0 OR grand_total IS NULL;

-- Verify tax calculations
SELECT id, booking_number, subtotal, tax_amount, grand_total,
       (subtotal + tax_amount) as calculated_total,
       (grand_total - (subtotal + tax_amount)) as difference
FROM bookings
WHERE ABS(grand_total - (subtotal + tax_amount)) > 0.01;
```

---

## E. Payment Flow Tests

### Test 1: Payment Without Advance

- [ ] Select "Confirm Booking Without Payment"
- [ ] Submit form
- [ ] Verify booking created with status: 'pending'
- [ ] Verify payment_status: 'pending'
- [ ] Verify no payment records created
- [ ] Admin can send payment request

### Test 2: Payment With Advance

- [ ] Select "Confirm Booking With Payment"
- [ ] Payment details section appears
- [ ] Select payment method
- [ ] Bank details or QR code displays
- [ ] Enter transaction ID
- [ ] Enter paid amount (≥ advance amount)
- [ ] Upload payment slip (valid image file)
- [ ] Submit form
- [ ] Verify booking created with status: 'payment_submitted'
- [ ] Verify payment_status: 'partial' or 'paid'
- [ ] Verify payment record created
- [ ] Verify payment slip uploaded to /uploads/payment-slips/
- [ ] Admin can view payment details

### Test 3: Payment Slip Upload Validation

- [ ] **Valid Files**
  - JPG file: ✓ Accepted
  - JPEG file: ✓ Accepted
  - PNG file: ✓ Accepted
  - GIF file: ✓ Accepted

- [ ] **Invalid Files**
  - PDF file: ✗ Rejected with error
  - Word doc: ✗ Rejected with error
  - Executable: ✗ Rejected with error
  - File > 5MB: ✗ Rejected with size error

- [ ] **Security**
  - Filename sanitized (no ../../../etc/passwd)
  - File extension validated
  - MIME type checked
  - Stored outside web root or protected directory

### Test 4: Duplicate/Partial Submissions

- [ ] Submit payment form
- [ ] Before completion, refresh page
- [ ] Verify no duplicate booking created
- [ ] Session data preserved or cleared appropriately

- [ ] Submit form with network interruption
- [ ] Verify partial data not saved
- [ ] User can retry submission

### Test 5: Payment Status Update (Admin)

- [ ] Admin views booking with partial payment
- [ ] Can update payment_status from 'partial' to 'paid'
- [ ] Can add additional payment records
- [ ] Balance due updates correctly
- [ ] Cannot downgrade status (paid → pending)
- [ ] Email notification sent on status change

---

## F. Nepali Date System Tests

### Test 1: Date Picker Functionality

- [ ] **Calendar Display**
  - Opens on click
  - Shows current month in BS
  - Month name in Nepali script
  - Year selector works
  - Month navigation (< >) works

- [ ] **BS/AD Toggle**
  - Toggle button switches calendar
  - BS calendar shows Nepali months
  - AD calendar shows English months
  - Selected date preserved on toggle

- [ ] **Date Selection**
  - Click date to select
  - Selected date highlights
  - Date displays in input field
  - Format: YYYY-MM-DD (for submission)
  - Display format: readable (e.g., "2 Magh 2082 BS")

- [ ] **Past Date Prevention**
  - Past dates disabled or greyed out
  - Cannot select yesterday or older
  - Today and future dates selectable

### Test 2: Date Conversion Accuracy

Test these specific dates for accuracy:

- [ ] **Nepali New Year**
  - 2024-04-14 AD = 1 Baisakh 2081 BS ✓
  - 2025-04-14 AD = 1 Baisakh 2082 BS ✓
  - 2026-04-14 AD = 1 Baisakh 2083 BS ✓

- [ ] **Today's Date**
  - Convert current date AD → BS
  - Verify against: https://www.ashesh.com.np/nepali-date-converter.php
  - Check for off-by-one errors

- [ ] **Month End Dates**
  - Last day of Chaitra (variable: 29-31 days)
  - First day of Baisakh
  - No off-by-one error at month transitions

- [ ] **Year End Dates**
  - Last day of year 2081 BS
  - First day of year 2082 BS
  - Verify year transition

### Test 3: Timezone Handling

- [ ] **Nepal Timezone (UTC+5:45)**
  - System uses Nepal timezone for date calculations
  - Not affected by client's timezone
  - Test from different timezones:
    - US/Pacific (UTC-8)
    - Europe/London (UTC+0)
    - Asia/Tokyo (UTC+9)
  - Date displayed should match Nepal's current date

- [ ] **Midnight Transition**
  - Test around midnight Nepal time
  - Verify date changes at correct time
  - No date jumps or glitches

### Test 4: Edge Cases

- [ ] **Minimum Date**
  - Cannot select dates before reference date (2056 BS)
  - System handles gracefully

- [ ] **Maximum Date**
  - Dates up to 2100 BS supported
  - Beyond 2100: Show warning or limit

- [ ] **Invalid Date Input**
  - Manual entry of invalid date
  - System validates and shows error
  - Prevents form submission

- [ ] **Leap Year Handling**
  - BS calendar doesn't have leap years like AD
  - Month days vary by year (29-32 days)
  - Conversion handles variable month lengths

---

## G. Edge Cases & Error Handling

### Test 1: Missing Data Scenarios

- [ ] **Booking with No Menus**
  - Proceed without selecting menus
  - menu_total = 0
  - Grand total calculates without menus
  - Invoice shows no menu section

- [ ] **Booking with No Services**
  - Proceed without selecting services
  - services_total = 0
  - Grand total calculates without services
  - Invoice shows "No additional services selected"

- [ ] **Service Without Description**
  - Admin creates service with empty description
  - User views service: Shows "N/A" or blank gracefully
  - Invoice displays service name only

- [ ] **Tax Rate = 0**
  - Admin sets tax rate to 0%
  - New booking: tax_amount = 0.00
  - Invoice: Tax row hidden (not displayed)
  - Grand total = subtotal (no tax added)

- [ ] **Customer Without Email**
  - Submit booking without email
  - Booking created successfully
  - Email field NULL in database
  - Admin view shows email as "-" or "Not provided"
  - Email notification skipped gracefully

- [ ] **Customer Without Address**
  - Submit booking without address
  - Booking created successfully
  - Address NULL or empty
  - Invoice shows address as "-" or omitted

### Test 2: Concurrent Bookings

- [ ] Two users select same hall, same date, same shift
- [ ] User A completes booking first
- [ ] User B attempts to complete booking
- [ ] System checks availability again
- [ ] User B sees error: "Hall no longer available"
- [ ] User B redirected to select different hall

### Test 3: Session Expiration

- [ ] Start booking process
- [ ] Leave browser idle for 30+ minutes
- [ ] Attempt to continue booking
- [ ] Session expired: Redirect to home with message
- [ ] User must restart booking process

### Test 4: Database Connection Loss

- [ ] Simulate database disconnect
- [ ] Attempt to load page
- [ ] Graceful error message displayed
- [ ] No sensitive information exposed
- [ ] Error logged for admin review

### Test 5: File Upload Errors

- [ ] Upload file > max size
- [ ] Error: "File too large (max 5MB)"
  
- [ ] Upload corrupted image
- [ ] Error: "Invalid file format"
  
- [ ] Insufficient disk space (rare)
- [ ] Error: "Upload failed, please try again"
  
- [ ] Upload without file selected
- [ ] Error: "Please select a file"

### Test 6: Invalid Data Input

- [ ] **SQL Injection Attempts**
  - Input: `'; DROP TABLE bookings; --`
  - System sanitizes input
  - No SQL executed
  - Data stored safely with escaped characters

- [ ] **XSS Attempts**
  - Input: `<script>alert('XSS')</script>`
  - Output sanitized: `&lt;script&gt;...`
  - No script execution
  - Displayed safely as text

- [ ] **Path Traversal**
  - Filename: `../../../../etc/passwd`
  - System rejects or sanitizes
  - File stored with safe name

### Test 7: Browser Compatibility Issues

- [ ] Test on Internet Explorer (if still supported)
  - If not supported, show warning message
  
- [ ] Test on very old browsers
  - Basic functionality works or shows upgrade message

### Test 8: Large Data Volumes

- [ ] Booking with 10,000 guests
  - System handles large numbers
  - Calculation correct
  - No integer overflow

- [ ] 1000+ bookings in database
  - Admin list loads (pagination)
  - Search/filter works
  - Performance acceptable (< 3 seconds)

---

## Automated Validation

### Run System Validation Tests

1. Access: `http://your-domain.com/test-system-validation.php`

2. Tests automatically run:
   - ✅ Validation functions
   - ✅ Default value handling
   - ✅ Database connection
   - ✅ Tax calculations
   - ✅ Sanitization
   - ✅ Settings retrieval

3. Review results:
   - All tests should PASS (green)
   - Warnings (orange) are acceptable but review
   - Failures (red) must be fixed before production

4. Save test results:
   - Take screenshot
   - Note any failures
   - Re-run after fixes

### Create Test Bookings

Create at least 5 test bookings covering:
1. Full booking (menus + services + payment)
2. Booking without menus
3. Booking without services
4. Booking without payment
5. Booking with zero tax

Verify each in admin panel.

---

## Pre-Live Checklist

### Technical Readiness
- [ ] All automated tests passing (test-system-validation.php)
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors
- [ ] Database backups configured
- [ ] SSL certificate installed (HTTPS)
- [ ] File permissions correct (uploads writable)
- [ ] .env file secured (not web-accessible)

### Content Readiness
- [ ] At least 3 active venues
- [ ] At least 5 active halls
- [ ] At least 10 menu options
- [ ] At least 10 services
- [ ] All images uploaded and optimized
- [ ] Company information complete
- [ ] Cancellation policy written
- [ ] Terms & conditions ready

### Configuration Verification
- [ ] Tax rate set correctly
- [ ] Currency symbol correct
- [ ] Advance payment % set
- [ ] Email SMTP configured (optional)
- [ ] Admin password changed from default
- [ ] Test email notifications sent

### Performance & Security
- [ ] Page load time < 3 seconds
- [ ] Mobile performance acceptable
- [ ] No security vulnerabilities (run security scan)
- [ ] Backup script tested
- [ ] Error logging enabled
- [ ] SQL injection prevention verified
- [ ] XSS prevention verified
- [ ] File upload restrictions working

### User Acceptance Testing
- [ ] Client reviewed booking flow
- [ ] Client reviewed admin panel
- [ ] Client tested invoice printing
- [ ] Client approved design/layout
- [ ] Client tested on their devices
- [ ] Training completed for staff

### Documentation
- [ ] Admin user manual provided
- [ ] Setup/installation guide complete
- [ ] Troubleshooting guide available
- [ ] Support contact information shared

### Go-Live Preparation
- [ ] Live domain DNS configured
- [ ] Production database created
- [ ] Test data cleared
- [ ] Monitoring tools set up
- [ ] Launch date scheduled
- [ ] Rollback plan prepared
- [ ] Post-launch support scheduled

---

## Post-Launch Monitoring

### First 24 Hours
- [ ] Monitor error logs
- [ ] Check first real bookings
- [ ] Verify email notifications
- [ ] Check payment processing
- [ ] Monitor site performance
- [ ] Collect user feedback

### First Week
- [ ] Review all bookings for accuracy
- [ ] Check database integrity
- [ ] Verify invoice generation
- [ ] Review user feedback
- [ ] Address any issues immediately

### Ongoing
- [ ] Weekly database backups
- [ ] Monthly security updates
- [ ] Quarterly full system test
- [ ] Regular performance monitoring

---

## Troubleshooting Common Issues

### Issue: Tax Not Calculating
**Solution:** Check `settings` table, ensure `tax_rate` is set

### Issue: Images Not Loading
**Solution:** Check `uploads/` permissions (755), verify file paths

### Issue: Email Not Sending
**Solution:** Run `check-email-setup.php`, verify SMTP settings

### Issue: Date Picker Not Working
**Solution:** Check browser console, ensure JavaScript loaded

### Issue: Mobile Layout Broken
**Solution:** Clear cache, verify CSS files loaded, check viewport meta tag

### Issue: Payment Upload Fails
**Solution:** Check `uploads/payment-slips/` exists and writable

---

## Contact & Support

If you encounter issues during testing:

1. Check this guide's troubleshooting section
2. Review error logs: `error_log` or PHP error log
3. Check browser console for JavaScript errors
4. Verify database connection and credentials
5. Consult developer documentation

---

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Ready for System Audit
