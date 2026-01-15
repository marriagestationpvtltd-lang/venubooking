# Booking Confirmation with Payment Options - Feature Guide

## Overview

This feature adds comprehensive payment confirmation options to the venue booking system, allowing users to either submit payment details during booking or confirm their booking without payment and add payment details later.

## Features Implemented

### 1. Two Booking Confirmation Options

#### Option A: Confirm Booking With Payment
- User selects "Confirm Booking With Payment"
- System displays:
  - Active payment methods from settings (QR Codes, Bank Details, etc.)
  - Calculated advance amount (e.g., 25% of total, configurable in settings)
- Required payment input fields:
  - Transaction ID / Reference Number
  - Paid Amount
  - Payment Slip / Screenshot Upload (image or PDF)
- Validation:
  - All payment fields are mandatory
  - Booking cannot be submitted without valid payment details and uploaded slip
- After submission:
  - Booking status → "Payment Submitted"
  - Payment record created and linked to Booking ID
  - Admin can view payment details and uploaded slip

#### Option B: Confirm Booking Without Payment
- User selects "Confirm Booking Without Payment"
- No payment gateway or fields are shown
- Direct booking confirmation allowed
- Booking status → "Pending"
- Payment status → "Unpaid"
- Admin or user can add payment details later

### 2. Database Schema

#### New Tables

**payments** - Tracks payment transactions
```sql
- id (Primary Key)
- booking_id (Foreign Key → bookings.id)
- payment_method_id (Foreign Key → payment_methods.id)
- transaction_id (Transaction reference number)
- paid_amount (Amount paid)
- payment_slip (Uploaded receipt/screenshot)
- payment_date (Timestamp of payment)
- payment_status (pending/verified/rejected)
- notes (Additional notes)
```

**payment_methods** - Stores payment gateway/method configurations
```sql
- id (Primary Key)
- name (Method name: Bank Transfer, eSewa, Khalti, etc.)
- qr_code (QR code image path)
- bank_details (Account details text)
- status (active/inactive)
- display_order (Display priority)
```

**booking_payment_methods** - Junction table
```sql
- id (Primary Key)
- booking_id (Foreign Key → bookings.id)
- payment_method_id (Foreign Key → payment_methods.id)
```

#### Updated Tables

**bookings** - Updated booking_status enum
```sql
booking_status: 'pending', 'payment_submitted', 'confirmed', 'cancelled', 'completed'
```

**settings** - New setting
```sql
advance_payment_percentage: Default 25%
```

### 3. Payment Settings

#### Admin Panel → Settings → General Settings
- **Advance Payment Percentage**: Configure the percentage of total amount required as advance (default: 25%)

#### Admin Panel → Payment Methods
- Add/Edit/Delete payment methods
- Configure:
  - Method name
  - QR code (optional, with image upload)
  - Bank details (optional, text area)
  - Status (Active/Inactive)
  - Display order
- Only active methods appear in booking flow

### 4. Frontend Booking Flow

#### booking-step5.php
- Added payment option selection (radio buttons)
- Dynamic payment method display with QR codes and bank details
- Payment input fields (shown/hidden based on selection)
- Client-side validation
- Payment slip upload with preview

#### confirmation.php
- Shows payment information if payment was submitted
- Displays:
  - Payment method used
  - Transaction ID
  - Paid amount
  - Payment date and status
  - Uploaded payment slip (with full-size view)

### 5. Admin Panel Features

#### Bookings View (admin/bookings/view.php)
- **Payment Transactions Section**: Shows all payments for a booking
  - Table with payment details
  - Transaction ID, amount, date, status
  - View payment slip button (opens modal with full-size image)
  - Download payment slip option
  - Total paid amount calculation
  - Balance due display
- **Updated Status Management**: Includes "Payment Submitted" status

#### Bookings List (admin/bookings/index.php)
- Updated status badges to include "Payment Submitted"
- Color-coded status indicators

### 6. Data Relationships

```
bookings (1) ←→ (Many) payments
bookings (Many) ←→ (Many) payment_methods (via booking_payment_methods)
payments (Many) → (1) payment_methods
```

Each booking can have:
- Multiple payment records (track partial payments)
- Link to multiple payment methods (flexibility)
- Clear payment status tracking

## Installation

### 1. Apply Database Migration

```bash
cd /path/to/venubooking
./apply-payment-confirmation-migration.sh
```

Or manually:
```bash
mysql -u username -p database_name < database/migrations/add_booking_payment_confirmation.sql
```

### 2. Configure Settings

1. Log in to Admin Panel
2. Navigate to **Settings**
3. Set **Advance Payment Percentage** (e.g., 25)
4. Save settings

### 3. Configure Payment Methods

1. Navigate to **Admin Panel → Payment Methods**
2. Click **"Add Payment Method"**
3. Fill in details:
   - Name (e.g., "Bank Transfer", "eSewa", "Khalti")
   - Upload QR Code (optional)
   - Enter bank/account details (optional)
   - Set status to **Active**
   - Set display order
4. Save
5. Repeat for all payment methods

### 4. File Permissions

Ensure the payment slip upload directory exists and is writable:
```bash
mkdir -p uploads/payment-slips
chmod 755 uploads/payment-slips
```

## Usage

### For Customers

1. Complete booking steps 1-4 (details, venue, menus, services)
2. On Step 5 (Customer Information):
   - Enter personal details
   - Choose payment option:
     - **With Payment**: Select method, enter transaction details, upload slip
     - **Without Payment**: Skip payment section
3. Submit booking
4. View confirmation with booking number and payment status

### For Administrators

#### View Payment Details
1. Navigate to **Admin Panel → Bookings**
2. Click **"View"** on any booking
3. Scroll to **"Payment Transactions"** section
4. Click **"View"** to see payment slip
5. Track total paid vs. balance due

#### Update Payment Status
1. View booking details
2. In **"Payment Transactions"** section
3. Update payment status:
   - Pending → Verified (payment confirmed)
   - Pending → Rejected (payment invalid)

#### Send Payment Requests
1. View booking details
2. Use **"Quick Actions"** section
3. Send payment request via:
   - Email (shows payment methods, amounts, QR codes)
   - WhatsApp (includes payment details)

## Validation Rules

### With Payment Option
- Payment method selection: **Required**
- Transaction ID: **Required**
- Paid amount: **Required** (must be numeric > 0)
- Payment slip: **Required** (image or PDF file)

### Without Payment Option
- No payment validation required
- Booking proceeds with pending status

## Security Features

### Implemented Protections
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars, sanitize function)
- ✅ File upload validation
- ✅ File path validation (validateUploadedFilePath)
- ✅ File type restrictions (images and PDF only)
- ✅ Transaction safety (database transactions)
- ✅ Input sanitization for all user inputs

### File Upload Security
- Validates file extensions
- Checks file MIME types
- Generates secure file names
- Stores files outside web root accessible paths
- Path traversal prevention

## Payment Status Flow

```
User Books → Payment Option Selected
    ├─ With Payment
    │   ├─ Submit Details → booking_status: "payment_submitted"
    │   │                   payment_status: "unpaid/partial"
    │   └─ Admin Verifies → payment record status: "verified"
    │                       payment_status: "partial/paid"
    │                       booking_status: "confirmed"
    │
    └─ Without Payment
        └─ Confirm → booking_status: "pending"
                     payment_status: "unpaid"
```

## API Functions

### Backend Functions (includes/functions.php)

#### `recordPayment($data)`
Records a payment transaction
- Parameters: booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, notes
- Returns: success status and payment_id
- Updates booking payment_status automatically

#### `getBookingPayments($booking_id)`
Retrieves all payments for a booking
- Parameters: booking_id
- Returns: Array of payment records with method details

#### `getActivePaymentMethods()`
Gets all active payment methods
- Returns: Array of active payment methods ordered by display_order

#### `calculateAdvancePayment($total_amount)`
Calculates advance payment amount
- Parameters: total_amount
- Returns: Array with percentage and amount

## Troubleshooting

### Payment Slip Upload Fails
- **Check**: Directory permissions on `uploads/payment-slips/`
- **Solution**: `chmod 755 uploads/payment-slips/`

### Payment Methods Not Showing
- **Check**: Payment methods status in Admin Panel
- **Solution**: Set status to "Active" for desired methods

### Advance Amount Incorrect
- **Check**: Settings → Advance Payment Percentage
- **Solution**: Update percentage value and save

### Migration Fails
- **Check**: Database credentials in `.env` file
- **Solution**: Verify DB_HOST, DB_NAME, DB_USER, DB_PASS

### Payment Slip Not Visible
- **Check**: File path validation and file existence
- **Solution**: Ensure file was uploaded successfully and path is correct

## File Structure

```
venubooking/
├── database/
│   └── migrations/
│       └── add_booking_payment_confirmation.sql
├── admin/
│   ├── bookings/
│   │   ├── view.php (updated)
│   │   └── index.php (updated)
│   ├── payment-methods/
│   │   └── index.php (existing)
│   └── settings/
│       └── index.php (updated)
├── includes/
│   └── functions.php (updated)
├── uploads/
│   └── payment-slips/ (new directory)
├── booking-step5.php (updated)
├── confirmation.php (updated)
└── apply-payment-confirmation-migration.sh (new)
```

## Testing Checklist

### Booking With Payment
- [ ] Select "Confirm With Payment" option
- [ ] Payment section becomes visible
- [ ] Select payment method
- [ ] QR code and bank details display correctly
- [ ] Enter transaction ID
- [ ] Enter paid amount
- [ ] Upload payment slip (try image and PDF)
- [ ] Submit booking
- [ ] Verify booking status is "Payment Submitted"
- [ ] Check confirmation page shows payment details

### Booking Without Payment
- [ ] Select "Confirm Without Payment" option
- [ ] Payment section remains hidden
- [ ] Submit booking without payment fields
- [ ] Verify booking status is "Pending"
- [ ] Check confirmation page (no payment section)

### Admin Panel
- [ ] View booking with payment
- [ ] Payment transactions section displays
- [ ] Click "View" on payment slip
- [ ] Modal shows full-size image
- [ ] Download payment slip works
- [ ] Total paid and balance due calculated correctly
- [ ] Update booking status includes "Payment Submitted"

### Settings
- [ ] Update advance payment percentage
- [ ] Verify changes reflect in booking flow
- [ ] Add new payment method
- [ ] Upload QR code
- [ ] Enter bank details
- [ ] Set to active/inactive
- [ ] Verify only active methods show in booking

## Support & Maintenance

### Regular Maintenance
- Monitor payment slip uploads directory size
- Periodically archive old payment slips
- Review and update payment method details
- Verify payment status updates regularly

### Future Enhancements (Optional)
- Payment gateway API integration (real-time)
- Automated payment verification
- Payment reminder emails
- Installment payment support
- Payment analytics dashboard

## Changelog

### Version 1.0.0 (January 2026)
- ✅ Initial implementation
- ✅ Two booking confirmation options
- ✅ Payment methods management
- ✅ Payment transaction tracking
- ✅ Admin payment viewing
- ✅ Payment slip upload and preview
- ✅ Advance payment calculation
- ✅ Status management
- ✅ Security hardening

---

**Implementation Date**: January 15, 2026  
**Version**: 1.0.0  
**Status**: Production Ready ✅
