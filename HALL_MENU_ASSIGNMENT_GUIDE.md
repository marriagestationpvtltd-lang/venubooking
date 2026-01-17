# Hall-Wise Menu Assignment System

## Overview
This feature allows administrators to assign specific menus to each hall. When customers book a hall, they will only see menus that have been assigned to that particular hall.

## Features

### 1. Database Structure
- **hall_menus table**: Mapping table with columns:
  - `id`: Primary key
  - `hall_id`: Foreign key to halls table
  - `menu_id`: Foreign key to menus table
  - `status`: ENUM('active', 'inactive') - Controls whether the assignment is active
  - `created_at`: Timestamp

### 2. Admin Panel - Hall Management

#### Adding a Hall
1. Navigate to Admin → Halls → Add New Hall
2. Fill in hall details (name, capacity, price, etc.)
3. In the "Assign Menus to Hall" section, select one or multiple menus
4. Save the hall

#### Editing a Hall
1. Navigate to Admin → Halls → Edit Hall
2. Modify hall details as needed
3. Update menu assignments using the checkboxes
4. Save changes

**Note**: Changing menus for one hall does not affect other halls.

### 3. Admin Panel - Booking Management

#### Creating a Booking
1. Navigate to Admin → Bookings → Add New Booking
2. Select a hall from the dropdown
3. The menu section will automatically update to show only menus assigned to the selected hall
4. Select desired menus and continue with booking

#### Editing a Booking
1. Navigate to Admin → Bookings → Edit Booking
2. If you change the hall, available menus will update automatically
3. Only menus assigned to the selected hall will be available

### 4. User Booking Flow
- When a customer selects a hall in the booking process, only assigned menus are displayed
- This happens automatically in booking-step3.php

### 5. Backward Compatibility
- Existing bookings retain their menu selections even if menus are later unassigned from halls
- All existing hall-menu relationships are automatically set to 'active' status
- The system gracefully handles halls with no assigned menus

## Database Migration

To apply this feature to an existing database, run the migration:

```bash
mysql -u [username] -p [database_name] < database/migrations/add_hall_menus_status.sql
```

Or execute the SQL directly in phpMyAdmin/MySQL Workbench:

```sql
ALTER TABLE hall_menus 
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' 
AFTER menu_id;

UPDATE hall_menus SET status = 'active' WHERE status IS NULL;
```

## API Endpoints

### GET /api/get-hall-menus.php
Returns menus assigned to a specific hall.

**Parameters:**
- `hall_id` (required): The ID of the hall

**Response:**
```json
{
  "success": true,
  "menus": [
    {
      "id": 1,
      "name": "Deluxe Menu",
      "description": "Premium dining experience",
      "price_per_person": 1500.00,
      "price_formatted": "NPR 1,500.00"
    }
  ]
}
```

## Functions

### Backend Functions (includes/functions.php)

#### `getMenusForHall($hall_id)`
Returns all active menus assigned to a specific hall.

#### `getAllActiveMenus()`
Returns all active menus (used in admin interface for selection).

#### `getAssignedMenuIds($hall_id)`
Returns array of menu IDs assigned to a hall.

#### `updateHallMenus($hall_id, $menu_ids)`
Updates hall-menu assignments, marking removed menus as inactive and adding new ones.

## Testing Checklist

- [ ] Create a new hall with menu assignments
- [ ] Edit an existing hall to add/remove menus
- [ ] Create a booking and verify only assigned menus appear
- [ ] Change hall in booking edit and verify menus update
- [ ] Verify user booking flow shows correct menus
- [ ] Confirm existing bookings still display their menus
- [ ] Test with halls that have no assigned menus
- [ ] Verify admin booking view displays booked menus correctly

## Security Notes

- All inputs are sanitized and validated
- SQL injection prevention through prepared statements
- CSRF token validation where applicable
- XSS prevention through proper HTML escaping

## Troubleshooting

### Menus not showing in admin booking form
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify the hall has assigned menus in Hall Management

### No menus available for a hall
- Edit the hall in Admin → Halls → Edit
- Assign at least one menu to the hall
- Ensure the menu status is 'active'

### Existing bookings showing wrong menus
- This should not happen as bookings store their own menu data
- Check booking_menus table for the specific booking

## Files Modified

1. `database/migrations/add_hall_menus_status.sql` - Database migration
2. `includes/functions.php` - Backend functions
3. `admin/halls/add.php` - Hall creation with menu assignment
4. `admin/halls/edit.php` - Hall editing with menu management
5. `admin/bookings/add.php` - Dynamic menu loading in booking creation
6. `admin/bookings/edit.php` - Dynamic menu loading in booking editing
7. `api/get-hall-menus.php` - API endpoint for fetching hall menus
