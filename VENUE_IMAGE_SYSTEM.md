# Venue Image Display - Admin Guide

## Overview
The venue booking system now supports dynamic image display with an intelligent fallback system. Admins can manage venue images in two ways, and the system will automatically ensure all venues display properly on the frontend.

## How Venue Images Work

### Option 1: Direct Venue Images (Recommended)
Upload images directly when adding or editing a venue:

1. Go to **Admin Panel → Venues**
2. Click **Edit** on any venue
3. Scroll to the **Venue Image** section
4. Upload an image (JPG, PNG, GIF, or WebP, max 5MB)
5. Click **Update Venue**

**Result:** The image will be directly associated with that specific venue and display on booking-step2.php

### Option 2: Image Gallery Fallback (New Feature)
Upload venue images to the general image gallery:

1. Go to **Admin Panel → Images**
2. Click **Upload New Image**
3. Fill in the form:
   - **Title:** Give it a descriptive name (e.g., "Royal Palace Exterior")
   - **Section:** Select **"Venue Gallery"**
   - **Image File:** Upload your image
   - **Status:** Set to **Active**
4. Click **Upload Image**

**Result:** If a venue doesn't have a direct image, the system will automatically use images from the Venue Gallery section.

## How the Fallback System Works

The system follows this priority order:

1. **First Priority:** Use the direct venue image (if exists and file is valid)
2. **Second Priority:** Use images from "Venue Gallery" section in site_images table
3. **Third Priority:** Show a placeholder "No Image" graphic

### Intelligent Image Distribution
When using the Venue Gallery fallback:
- Multiple venues without images will automatically rotate through available gallery images
- This ensures visual variety even when venues don't have individual images
- Images are distributed in order of display_order setting

## Frontend Behavior

### booking-step2.php (Venue Selection Page)
- Displays all active venues with their images
- Automatically applies fallback logic for missing images
- Shows "No Image" placeholder only when absolutely no images are available

### What Changes Are Reflected Immediately?
- ✅ Images uploaded via Venues → Edit → Upload Image
- ✅ Images uploaded via Images → Upload with Section="Venue Gallery"
- ✅ Changes to image status (Active/Inactive) in both systems
- ✅ Changes to display_order in Venue Gallery images

## Best Practices

### For Best Results:
1. **Upload venue-specific images directly through Venues → Edit**
   - This gives you precise control over which image shows for each venue
   
2. **Use Venue Gallery for backup/general images**
   - Upload multiple venue-related images to the gallery
   - These serve as automatic fallbacks for any venue without a direct image

3. **Image Optimization**
   - Use images around 800x600px or similar aspect ratio
   - Keep file sizes under 2MB for faster loading
   - Use JPG for photos, PNG for graphics with transparency

4. **Maintain Active Status**
   - Inactive images won't show on the frontend
   - Use inactive status to temporarily hide images without deleting them

## Troubleshooting

### Image Not Showing on Frontend?

**Check 1: Venue Has Direct Image**
- Go to Admin → Venues → Edit
- Is there an image shown in "Current venue image"?
- If not, upload one or rely on Venue Gallery

**Check 2: Venue Gallery Has Active Images**
- Go to Admin → Images
- Filter by Section: "Venue Gallery"
- Are there any active images?
- If not, upload at least one

**Check 3: File Permissions**
- Ensure the `uploads/` directory is writable
- Check that uploaded files actually exist on the server

**Check 4: Image File Exists**
- The system verifies file existence before displaying
- If a database entry exists but file is missing, fallback applies

## Technical Details

### Database Tables
- **venues.image** - Stores direct venue images (filename only)
- **site_images** - Stores gallery images with section='venue'

### File Storage
- All images stored in `/uploads/` directory
- Direct venue images: `venue_[timestamp]_[unique].ext`
- Gallery images: `venue_[timestamp]_[unique].ext`

### Image Paths
- Frontend URL: `BASE_URL/uploads/filename.jpg`
- Server path: `UPLOAD_PATH/filename.jpg`

## Examples

### Example 1: Venue with Direct Image
```
Venue: Royal Palace
Direct Image: royal-palace-2024.jpg
Display: Shows royal-palace-2024.jpg
```

### Example 2: Venue without Image, Gallery Has Images
```
Venue: Garden View Hall
Direct Image: (none)
Gallery Images: venue_001.jpg, venue_002.jpg
Display: Shows venue_001.jpg (first available gallery image)
```

### Example 3: Multiple Venues, Some Without Images
```
Venue 1: City Center (has image: city.jpg) → Shows city.jpg
Venue 2: Beach Resort (no image) → Shows venue_001.jpg
Venue 3: Mountain Lodge (no image) → Shows venue_002.jpg
Venue 4: Lake House (no image) → Shows venue_001.jpg (rotates back)
```

### Example 4: No Images Available
```
Venue: New Venue
Direct Image: (none)
Gallery Images: (none)
Display: Shows "No Image" placeholder (gray box)
```

## Summary

✅ **Dynamic:** Images update automatically when changed in admin
✅ **Smart Fallback:** Never shows broken images, always has a fallback
✅ **Flexible:** Works with direct images OR gallery images
✅ **User-Friendly:** Admin doesn't need to worry about broken displays
✅ **Maintainable:** Easy to add/update images at any time

For any issues, contact the technical support team with screenshots of the admin panel and specific venue details.
