# Testing Guide: Mobile Booking Steps Improvements

## Quick Test Summary

This guide helps you verify the mobile booking improvements are working correctly.

## What Changed?

**Three booking steps now have collapsible sections on mobile devices:**
1. **Step 3 (Menu):** Menu items can be collapsed/expanded
2. **Step 4 (Services):** Service categories in accordion format
3. **Step 5 (Confirm):** Booking summary is collapsible

## Testing Setup

### Requirements
- Mobile device OR desktop browser with mobile emulation
- Access to the booking flow
- Test data for a booking

### Browser DevTools Mobile Emulation
1. Open Chrome/Firefox DevTools (F12)
2. Click "Toggle Device Toolbar" (Ctrl+Shift+M)
3. Select device: iPhone 12 Pro or similar (390x844)
4. Refresh the page

## Test Cases

### Test 1: Step 3 - Menu Selection (Mobile)

**Objective:** Verify menu items are collapsible on mobile

**Steps:**
1. Start a new booking and reach Step 3 (Menu Selection)
2. Resize browser to mobile width (< 768px) OR use mobile device
3. Observe the menu cards

**Expected Results:**
- ✅ Menu items should be hidden initially
- ✅ Each menu card shows a "View Menu Items" button
- ✅ Button has a down chevron icon
- ✅ Clicking button expands to show all menu items
- ✅ Clicking again collapses the items
- ✅ Checkbox selection works even when collapsed
- ✅ Price calculation updates correctly

**Desktop Verification:**
- Resize browser to desktop width (> 768px)
- Menu items should be fully visible (no collapse button)
- No behavior change from before

### Test 2: Step 4 - Additional Services (Mobile)

**Objective:** Verify service categories are in accordion format on mobile

**Steps:**
1. Continue to Step 4 (Additional Services)
2. Ensure mobile viewport (< 768px)
3. Observe the service categories

**Expected Results:**
- ✅ Services grouped in collapsible cards
- ✅ Each category shows: Category name + service count badge
- ✅ First category is expanded by default
- ✅ Other categories are collapsed
- ✅ Clicking category header expands/collapses it
- ✅ Chevron icon rotates on expand/collapse
- ✅ Service checkboxes work correctly
- ✅ Price updates in real-time

**Desktop Verification:**
- Resize to desktop width (> 768px)
- All categories should be visible (no accordion)
- Standard grid layout as before

### Test 3: Step 5 - Booking Confirmation (Mobile)

**Objective:** Verify booking summary is collapsible on mobile

**Steps:**
1. Continue to Step 5 (Complete Booking)
2. Ensure mobile viewport (< 768px)
3. Observe the booking summary

**Expected Results:**
- ✅ Summary appears as collapsible card at top
- ✅ Header shows "Booking Summary" with grand total
- ✅ Summary is collapsed by default
- ✅ Tapping header expands/collapses summary
- ✅ Expanded view shows compact event/venue/cost details
- ✅ Form is immediately accessible below
- ✅ Progressive form steps still work correctly

**Desktop Verification:**
- Resize to desktop width (> 992px)
- Summary should be sticky sidebar on right
- Full detailed summary visible
- Same behavior as before

### Test 4: End-to-End Mobile Booking

**Objective:** Complete full booking flow on mobile

**Steps:**
1. Start fresh booking on mobile device
2. Complete Step 1 (Event Details)
3. Complete Step 2 (Venue & Hall)
4. Complete Step 3 (Menu) - test collapse/expand
5. Complete Step 4 (Services) - test accordion
6. Complete Step 5 (Confirmation) - test summary
7. Submit booking

**Expected Results:**
- ✅ All steps complete without errors
- ✅ Selections persist across steps
- ✅ Price calculations are correct
- ✅ Booking submits successfully
- ✅ Confirmation page displays

## Visual Indicators

### What to Look For

#### Menu Items Collapse Button (Step 3)
```
┌─────────────────────────────────┐
│ Menu Card                       │
│ ₹500/pax                        │
│ Description text...             │
│                                 │
│ [📋 View Menu Items ▼]          │ <- Button
└─────────────────────────────────┘
```

#### Service Category Accordion (Step 4)
```
┌─────────────────────────────────┐
│ Decoration Services    [3]      │ <- Expanded
├─────────────────────────────────┤
│ ☐ Flower Decoration   ₹5,000   │
│ ☐ Stage Decoration    ₹10,000  │
│ ☐ LED Lights          ₹3,000   │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Photography Services   [2]  ▼   │ <- Collapsed
└─────────────────────────────────┘
```

#### Mobile Summary (Step 5)
```
┌─────────────────────────────────┐
│ 📄 Booking Summary   ₹50,000 ▼  │ <- Collapsed
└─────────────────────────────────┘

When expanded:
┌─────────────────────────────────┐
│ 📄 Booking Summary   ₹50,000 ▲  │
├─────────────────────────────────┤
│ Event: Wedding                  │
│ Date: Jan 20, 2026              │
│ Venue: Grand Hall               │
│                                 │
│ Hall:     ₹20,000              │
│ Menu:     ₹25,000              │
│ Services: ₹5,000               │
│ Total:    ₹50,000              │
└─────────────────────────────────┘
```

## Performance Tests

### Page Load Speed
- [ ] Step 3 loads in < 3 seconds
- [ ] Step 4 loads in < 3 seconds  
- [ ] Step 5 loads in < 3 seconds
- [ ] No console errors

### Interaction Responsiveness
- [ ] Collapse/expand animations are smooth
- [ ] No lag when tapping buttons
- [ ] Scrolling is smooth
- [ ] Touch targets are easy to tap

### Battery Impact
- [ ] No excessive CPU usage
- [ ] No memory leaks
- [ ] Animations don't drain battery

## Cross-Browser Testing

Test on these browsers (mobile):

### iOS (iPhone)
- [ ] Safari Mobile (latest)
- [ ] Chrome Mobile (latest)
- [ ] Firefox Mobile (latest)

### Android
- [ ] Chrome Mobile (latest)
- [ ] Samsung Internet (latest)
- [ ] Firefox Mobile (latest)

### Tablet
- [ ] iPad Safari (768-991px)
- [ ] Android Tablet Chrome

## Accessibility Testing

- [ ] Can navigate with keyboard (Tab)
- [ ] Screen reader announces expand/collapse
- [ ] Focus indicators are visible
- [ ] Color contrast is adequate
- [ ] Touch targets are minimum 44x44px

## Edge Cases

### Long Content
- [ ] Test with 10+ menu items in a menu
- [ ] Test with 20+ services in a category
- [ ] Verify scrolling works in collapsed areas

### No Data Scenarios
- [ ] No menus available
- [ ] No services available
- [ ] Empty summary sections

### Network Issues
- [ ] Test on slow 3G connection
- [ ] Verify loading states
- [ ] Check timeout handling

## Common Issues & Solutions

### Issue: Collapse buttons don't work
**Solution:** Check Bootstrap JS is loaded properly

### Issue: Desktop view shows mobile layout
**Solution:** Clear browser cache and check CSS media queries

### Issue: Animations are jerky
**Solution:** Check CSS transitions are enabled, test on different device

### Issue: Checkboxes don't sync between mobile/desktop views
**Solution:** Ensure unique IDs for mobile version (e.g., `_mobile` suffix)

## Success Criteria

All tests pass when:
- ✅ Mobile pages are 40-70% shorter
- ✅ All collapse/expand interactions work smoothly
- ✅ Desktop experience is unchanged
- ✅ No JavaScript errors in console
- ✅ Booking submission works correctly
- ✅ All selections are preserved
- ✅ Responsive at all breakpoints (320px - 2560px)

## Rollback Trigger

Rollback changes if:
- ❌ Bookings fail to submit
- ❌ Critical JavaScript errors occur
- ❌ Desktop layout is broken
- ❌ More than 5% increase in booking abandonment
- ❌ Major accessibility issues found

## Testing Timeline

**Quick Smoke Test:** 10 minutes
- One complete mobile booking flow
- Verify all collapses work
- Check desktop unchanged

**Full Regression Test:** 30 minutes
- All test cases above
- Multiple devices
- Edge cases

**Production Monitoring:** First 24 hours
- Monitor booking completion rate
- Check error logs
- User feedback collection

## Reporting Issues

When reporting bugs, include:
1. Device/browser details
2. Screen size
3. Steps to reproduce
4. Screenshot or video
5. Console errors (if any)

## Testing Checklist Summary

Use this quick checklist:

```
Mobile Step 3 (Menu)
├── [ ] Menu items collapsed by default
├── [ ] "View Items" button visible
├── [ ] Button expands items
└── [ ] Selection works correctly

Mobile Step 4 (Services)
├── [ ] Categories in accordion format
├── [ ] First category expanded
├── [ ] Other categories collapsed
└── [ ] Category selection works

Mobile Step 5 (Confirm)
├── [ ] Summary collapsible at top
├── [ ] Grand total visible
├── [ ] Expands to show details
└── [ ] Form submission works

Desktop Verification
├── [ ] Step 3 unchanged
├── [ ] Step 4 unchanged
├── [ ] Step 5 unchanged
└── [ ] All functionality works

Cross-Cutting
├── [ ] No JavaScript errors
├── [ ] Smooth animations
├── [ ] Fast load times
└── [ ] Works on all browsers
```

---

**Happy Testing! 🧪📱**

For questions or issues, refer to MOBILE_BOOKING_STEPS_IMPROVEMENT.md
