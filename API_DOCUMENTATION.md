# API Documentation

This document describes the API endpoints available in the Venue Booking System.

## Base URL

All API endpoints are relative to: `/api/`

## Authentication

Most API endpoints require session-based authentication for the admin panel. Frontend APIs use session data to maintain booking state.

## Endpoints

### 1. Get Halls for Venue

Retrieves all halls for a specific venue, filtered by capacity and availability.

**Endpoint:** `GET /api/get-halls.php`

**Parameters:**
- `venue_id` (required) - Integer - ID of the venue
- `guests` (required) - Integer - Minimum capacity required
- `date` (optional) - Date - Event date (YYYY-MM-DD)
- `shift` (optional) - String - Event shift (morning/afternoon/evening/fullday)

**Response:**
```json
{
    "success": true,
    "halls": [
        {
            "id": 1,
            "venue_id": 1,
            "name": "Sagarmatha Hall",
            "capacity": 700,
            "hall_type": "single",
            "indoor_outdoor": "indoor",
            "base_price": 150000.00,
            "description": "Our flagship hall...",
            "features": "Air conditioning, Stage, Sound system",
            "available": true,
            "image": "sagarmatha-hall-1.jpg"
        }
    ]
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Missing required parameters"
}
```

---

### 2. Select Hall

Saves the selected hall to the session for the current booking.

**Endpoint:** `POST /api/select-hall.php`

**Request Body:**
```json
{
    "id": 1,
    "name": "Sagarmatha Hall",
    "venue_name": "Royal Palace",
    "base_price": 150000.00,
    "capacity": 700
}
```

**Response:**
```json
{
    "success": true,
    "message": "Hall selected successfully"
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Hall ID is required"
}
```

---

### 3. Check Availability

Checks if a specific hall is available for a given date and shift.

**Endpoint:** `GET /api/check-availability.php`

**Parameters:**
- `hall_id` (required) - Integer - ID of the hall
- `date` (required) - Date - Event date (YYYY-MM-DD)
- `shift` (required) - String - Event shift

**Response:**
```json
{
    "success": true,
    "available": true
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Missing required parameters"
}
```

---

### 4. Calculate Price

Calculates the total booking price including hall, menus, and services.

**Endpoint:** `POST /api/calculate-price.php`

**Request Body:**
```json
{
    "hall_id": 1,
    "guests": 500,
    "menus": [1, 2],
    "services": [1, 3, 5]
}
```

**Response:**
```json
{
    "success": true,
    "totals": {
        "hall_price": 150000.00,
        "menu_total": 2149000.00,
        "services_total": 70000.00,
        "subtotal": 2369000.00,
        "tax_amount": 307970.00,
        "grand_total": 2676970.00
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Missing required parameters"
}
```

---

## Response Codes

- `200 OK` - Request successful
- `400 Bad Request` - Invalid parameters
- `401 Unauthorized` - Authentication required
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Data Types

### Shift Types
- `morning` - 6:00 AM - 12:00 PM
- `afternoon` - 12:00 PM - 6:00 PM
- `evening` - 6:00 PM - 12:00 AM
- `fullday` - Full day booking

### Booking Status
- `pending` - Awaiting confirmation
- `confirmed` - Confirmed booking
- `cancelled` - Cancelled booking
- `completed` - Event completed

### Payment Status
- `unpaid` - No payment received
- `partial` - Partial payment received
- `paid` - Fully paid

### Hall Types
- `single` - Single hall
- `multiple` - Multiple connected halls

### Indoor/Outdoor
- `indoor` - Indoor hall
- `outdoor` - Outdoor venue
- `both` - Both indoor and outdoor

## Core Functions

These PHP functions are available in `includes/functions.php`:

### checkHallAvailability()
```php
checkHallAvailability($hall_id, $date, $shift)
```
Returns true if hall is available, false if booked.

### generateBookingNumber()
```php
generateBookingNumber()
```
Generates unique booking number in format: BK-YYYYMMDD-XXXX

### calculateBookingTotal()
```php
calculateBookingTotal($hall_id, $menus, $guests, $services)
```
Returns array with price breakdown.

### getAvailableVenues()
```php
getAvailableVenues($date, $shift)
```
Returns array of available venues.

### getHallsForVenue()
```php
getHallsForVenue($venue_id, $min_capacity)
```
Returns array of halls for a venue.

### getMenusForHall()
```php
getMenusForHall($hall_id)
```
Returns array of menus available for a hall.

### getMenuItems()
```php
getMenuItems($menu_id)
```
Returns array of items in a menu.

### getActiveServices()
```php
getActiveServices()
```
Returns array of all active additional services.

### createBooking()
```php
createBooking($data)
```
Creates a new booking. Returns success/failure with booking details.

### getBookingDetails()
```php
getBookingDetails($booking_id)
```
Returns complete booking information including customer, hall, venue, menus, and services.

### formatCurrency()
```php
formatCurrency($amount)
```
Formats amount as currency string (e.g., "NPR 150,000.00").

## Session Variables

### Booking Flow
- `$_SESSION['booking_data']` - Initial booking details (shift, date, guests, event_type)
- `$_SESSION['selected_hall']` - Selected hall information
- `$_SESSION['selected_menus']` - Array of selected menu IDs
- `$_SESSION['selected_services']` - Array of selected service IDs
- `$_SESSION['booking_completed']` - Booking completion information

### Admin Authentication
- `$_SESSION['admin_user_id']` - Logged in admin user ID
- `$_SESSION['admin_username']` - Username
- `$_SESSION['admin_full_name']` - Full name
- `$_SESSION['admin_role']` - User role (admin/manager/staff)
- `$_SESSION['csrf_token']` - CSRF protection token

## Error Handling

All API endpoints return JSON responses with a `success` boolean and appropriate `message` on errors.

Common error scenarios:
1. Missing required parameters
2. Invalid data types
3. Database connection errors
4. Resource not found
5. Duplicate bookings

## Security Considerations

1. **SQL Injection Prevention**: All database queries use PDO prepared statements
2. **XSS Protection**: All output is sanitized with `htmlspecialchars()`
3. **CSRF Protection**: Token-based protection for forms
4. **Session Security**: HTTPOnly cookies, secure flags in production
5. **Input Validation**: Server-side validation on all inputs
6. **Password Security**: bcrypt hashing for passwords

## Rate Limiting

Currently, there is no rate limiting implemented. Consider adding rate limiting in production:
- Limit API calls per IP address
- Implement CAPTCHA for booking forms
- Add brute force protection for admin login

## Future API Enhancements

Potential additions for future versions:
1. RESTful API for mobile app integration
2. Webhook support for external integrations
3. OAuth2 authentication
4. Real-time availability via WebSockets
5. Payment gateway integration APIs
6. Email notification APIs
7. SMS notification APIs

## Testing

Test the APIs using:
- Browser developer tools
- Postman or similar API testing tool
- cURL commands

Example cURL test:
```bash
curl -X GET "http://localhost/venubooking/api/check-availability.php?hall_id=1&date=2026-02-15&shift=evening"
```

---

**Last Updated**: January 2026
