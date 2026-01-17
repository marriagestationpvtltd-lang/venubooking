# Hall-Wise Menu Assignment - Testing Guide

This guide will help you test the hall-wise menu assignment feature to ensure it works correctly.

## Prerequisites

1. Database migration has been applied (`apply-hall-menu-migration.sh`)
2. Admin access to the system
3. At least 2 halls created
4. At least 3 menus created

## Test Scenarios

### Scenario 1: Assign Menus to a New Hall

**Steps:**
1. Log in to admin panel
2. Navigate to: Admin → Halls → Add New Hall
3. Fill in hall details:
   - Venue: Select any venue
   - Hall Name: "Test Hall A"
   - Capacity: 500
   - Base Price: 150000
   - Status: Active
4. In "Assign Menus to Hall" section, select 2 menus (e.g., "Deluxe Menu" and "Standard Menu")
5. Click "Add Hall"

**Expected Result:**
- ✓ Hall is created successfully
- ✓ Success message appears
- ✓ When viewing the hall, both selected menus appear in the "Available Menus" section

**Verification:**
- Go to: Admin → Halls → View "Test Hall A"
- Check that only the 2 assigned menus are listed

---

### Scenario 2: Edit Menu Assignments for Existing Hall

**Steps:**
1. Navigate to: Admin → Halls → Edit (select "Test Hall A")
2. Uncheck "Deluxe Menu"
3. Check "Premium Menu" (if available)
4. Click "Update Hall"

**Expected Result:**
- ✓ Hall updated successfully
- ✓ "Deluxe Menu" is no longer listed in linked menus
- ✓ "Premium Menu" now appears in linked menus
- ✓ "Standard Menu" remains in linked menus

**Verification:**
- Refresh the edit page
- Verify checkboxes reflect current assignments
- Check hall view page shows correct menus

---

### Scenario 3: Create Admin Booking with Hall-Specific Menus

**Steps:**
1. Navigate to: Admin → Bookings → Add New Booking
2. Fill in customer information
3. Select "Test Hall A" from hall dropdown
4. Observe the menus section

**Expected Result:**
- ✓ Menus section shows "Loading menus..." briefly
- ✓ Only menus assigned to "Test Hall A" appear as checkboxes
- ✓ Unassigned menus do NOT appear

**Verification:**
- Change hall selection to another hall
- Verify menu list updates to show that hall's menus
- If hall has no menus, warning message appears

---

### Scenario 4: User Booking Flow

**Steps:**
1. Log out of admin (or use incognito mode)
2. Go to website homepage
3. Start a new booking:
   - Select event date
   - Choose "Test Hall A"
   - Proceed to menu selection (Step 3)

**Expected Result:**
- ✓ Only menus assigned to "Test Hall A" are displayed
- ✓ Unassigned menus do not appear
- ✓ Can select and proceed with booking

**Verification:**
- Try booking another hall with different menus
- Verify each hall shows only its assigned menus

---

### Scenario 5: Edit Existing Booking - Change Hall

**Steps:**
1. Log in to admin
2. Navigate to: Admin → Bookings → Edit (select any booking)
3. Note currently selected menus
4. Change hall to "Test Hall A"
5. Observe menu section

**Expected Result:**
- ✓ Menu list updates automatically
- ✓ Only menus for "Test Hall A" are available
- ✓ Previously selected menus are unchecked (if not available for new hall)

**Verification:**
- Change hall back and forth
- Verify menus update each time

---

### Scenario 6: View Booking with Old Menu Assignments (Backward Compatibility)

**Steps:**
1. Find a booking created before menu assignment implementation
2. Edit the hall for that booking's hall and remove all menus
3. Navigate to: Admin → Bookings → View (that booking)

**Expected Result:**
- ✓ Booking still displays its original menus
- ✓ No errors occur
- ✓ Menu information appears correctly in booking details

**Verification:**
- Check booking view page
- Print/invoice (if available) should still show menus

---

### Scenario 7: Hall with No Assigned Menus

**Steps:**
1. Create a new hall "Test Hall B"
2. Do NOT assign any menus
3. Try to create a booking for "Test Hall B"

**Expected Result:**
- ✓ In admin booking form, menu section shows warning: "No menus are assigned to this hall"
- ✓ Can still create booking without selecting menus
- ✓ No JavaScript errors in browser console

**Verification:**
- Check browser console (F12) for errors
- Verify booking can be completed

---

### Scenario 8: Multiple Halls with Different Menu Sets

**Setup:**
- Hall A: Assigned Menus 1, 2, 3
- Hall B: Assigned Menus 2, 3, 4
- Hall C: Assigned Menus 1, 4, 5

**Steps:**
1. Start booking for Hall A → See Menus 1, 2, 3 only
2. Start booking for Hall B → See Menus 2, 3, 4 only
3. Start booking for Hall C → See Menus 1, 4, 5 only

**Expected Result:**
- ✓ Each hall shows only its assigned menus
- ✓ Common menus appear for multiple halls
- ✓ Exclusive menus appear only for their assigned halls

---

## Database Verification

Run these SQL queries to verify the implementation:

### Check hall_menus table structure:
```sql
DESCRIBE hall_menus;
```
Should show `status` column with type `enum('active','inactive')`

### Check active menu assignments:
```sql
SELECT h.name as hall_name, m.name as menu_name, hm.status
FROM hall_menus hm
JOIN halls h ON hm.hall_id = h.id
JOIN menus m ON hm.menu_id = m.id
WHERE hm.status = 'active'
ORDER BY h.name, m.name;
```

### Check all assignments (active and inactive):
```sql
SELECT h.name as hall_name, m.name as menu_name, hm.status, hm.created_at
FROM hall_menus hm
JOIN halls h ON hm.hall_id = h.id
JOIN menus m ON hm.menu_id = m.id
ORDER BY h.name, hm.status, m.name;
```

---

## Troubleshooting

### Issue: Menus not loading in admin booking form
**Solution:**
1. Open browser console (F12)
2. Check for JavaScript errors
3. Verify API endpoint is accessible: `/api/get-hall-menus.php?hall_id=1`
4. Check server error logs

### Issue: Wrong menus appearing for a hall
**Solution:**
1. Edit the hall in Admin → Halls → Edit
2. Review selected menus
3. Save changes
4. Clear browser cache

### Issue: Migration fails
**Solution:**
1. Check if column already exists: `DESCRIBE hall_menus;`
2. If exists, manually verify status column
3. Check database user permissions

---

## Success Criteria

All tests pass if:

- ✓ Menus can be assigned to halls during creation
- ✓ Menu assignments can be edited for existing halls
- ✓ Admin booking form shows only assigned menus
- ✓ User booking flow shows only assigned menus
- ✓ Changing halls updates available menus dynamically
- ✓ Existing bookings retain their menu data
- ✓ Halls without menus handle gracefully
- ✓ No JavaScript errors in console
- ✓ No PHP errors in logs
- ✓ Database queries execute without errors

---

## Performance Testing

For large installations:

1. **Test with 50+ halls and 100+ menus**
   - Menu assignment interface should load in < 2 seconds
   - Dynamic menu loading should complete in < 1 second

2. **Test concurrent bookings**
   - Multiple users booking different halls simultaneously
   - Each should see correct menus

3. **Test database load**
   - Run `EXPLAIN` on menu queries
   - Ensure indexes are being used

---

## Regression Testing

Verify these features still work:

- [ ] Hall CRUD operations (Create, Read, Update, Delete)
- [ ] Menu CRUD operations
- [ ] Booking creation and editing
- [ ] User booking flow (Steps 1-5)
- [ ] Invoice generation
- [ ] Print functionality
- [ ] Email notifications
- [ ] Payment processing

---

## Test Data Cleanup

After testing, you may want to remove test data:

```sql
-- Remove test halls
DELETE FROM halls WHERE name LIKE 'Test Hall%';

-- This will cascade delete:
-- - hall_menus relationships
-- - hall_images
-- - bookings (be careful!)
```

---

## Report Issues

If you encounter any issues during testing:

1. Note the exact steps to reproduce
2. Check browser console for JavaScript errors
3. Check server logs for PHP errors
4. Note which scenario failed
5. Document expected vs actual behavior
6. Report to development team

---

## Next Steps After Successful Testing

1. Apply migration to production database
2. Train staff on new menu assignment feature
3. Assign menus to all existing halls
4. Monitor for any issues
5. Gather user feedback
