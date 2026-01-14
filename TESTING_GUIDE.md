# Testing Guide for Image Upload Feature

## Quick Testing Checklist

### Prerequisites
- Database is set up and running
- Web server (Apache/Nginx) is running
- PHP 8.x is installed
- MySQL is accessible
- Admin user credentials are available (default: admin/Admin@123)

### Installation Steps

1. **Run the installation script:**
   ```bash
   ./install-image-feature.sh
   ```

2. **Verify the database table was created:**
   ```bash
   mysql -u [username] -p [database_name] -e "DESCRIBE site_images;"
   ```
   
   Expected output should show columns: id, title, description, image_path, section, display_order, status, created_at, updated_at

3. **Check uploads directory permissions:**
   ```bash
   ls -ld uploads/
   ```
   
   Should show: `drwxr-xr-x` (755 permissions)

### Admin Panel Testing

#### Test 1: Access Image Management
1. Navigate to: `http://your-domain/admin/login.php`
2. Login with admin credentials
3. Click on "Images" in the sidebar
4. **Expected Result:** You should see the image management page with an empty table or upload button

#### Test 2: Upload New Image
1. Click "Upload New Image" button
2. Fill in the form:
   - Title: "Test Banner Image"
   - Section: "Banner / Hero Section"
   - Description: "This is a test banner"
   - Select an image file (JPG/PNG, under 5MB)
   - Display Order: 0
   - Status: Active
3. Click "Upload Image"
4. **Expected Result:** Success message appears, image appears in the list

#### Test 3: View Image Details
1. From the images list, click the "eye" icon (View) on your test image
2. **Expected Result:** 
   - Image preview is displayed
   - All details are shown correctly
   - Image URL is copyable
   - File size and dimensions are shown

#### Test 4: Edit Image
1. Click "Edit" button on an image
2. Change the title to "Updated Test Banner"
3. Change display order to 10
4. Click "Update Image"
5. **Expected Result:** Success message appears, changes are reflected

#### Test 5: Test Without Replacing Image
1. Edit an image but don't upload a new file
2. Just change the title or description
3. Click "Update Image"
4. **Expected Result:** Changes saved, original image preserved

#### Test 6: Replace Image File
1. Edit an image
2. Upload a NEW image file
3. Click "Update Image"
4. **Expected Result:** 
   - New image is displayed
   - Old image file is removed from server
   - Changes saved successfully

#### Test 7: Delete Image
1. From the images list, click the "trash" icon
2. Confirm deletion
3. **Expected Result:** 
   - Image removed from database
   - Image file deleted from server
   - Success message appears

### Frontend Testing

#### Test 8: Banner Display
1. Upload an image with section "Banner"
2. Navigate to: `http://your-domain/index.php`
3. **Expected Result:** Your banner image appears as the background of the hero section

#### Test 9: Gallery Display
1. Upload 3-6 images with section "Gallery"
2. Navigate to: `http://your-domain/index.php`
3. Scroll down to the gallery section
4. **Expected Result:** Gallery section appears with your images in a grid layout

### API Testing

#### Test 10: API Endpoint
1. Open in browser or use curl:
   ```bash
   curl http://your-domain/api/get-images.php?section=banner
   ```
2. **Expected Result:** JSON response with array of banner images

#### Test 11: Invalid Section
1. Request with invalid section:
   ```bash
   curl http://your-domain/api/get-images.php?section=nonexistent
   ```
2. **Expected Result:** JSON response with empty images array, no errors

### Security Testing

#### Test 12: File Type Validation
1. Try to upload a .txt or .php file
2. **Expected Result:** Error message "Invalid file type"

#### Test 13: File Size Validation
1. Try to upload a file larger than 5MB
2. **Expected Result:** Error message "File is too large"

#### Test 14: Authentication Check
1. Log out of admin panel
2. Try to access: `http://your-domain/admin/images/index.php`
3. **Expected Result:** Redirected to login page

#### Test 15: SQL Injection Protection
1. Try to edit an image with ID: `1' OR '1'='1`
2. **Expected Result:** No data returned, handled safely

### Display Order Testing

#### Test 16: Display Order
1. Upload 3 images in the same section with different display orders:
   - Image 1: order = 0
   - Image 2: order = 10
   - Image 3: order = 5
2. View frontend or call API
3. **Expected Result:** Images appear in order: Image 1, Image 3, Image 2

### Status Testing

#### Test 17: Inactive Status
1. Edit an image and set status to "Inactive"
2. View frontend
3. **Expected Result:** Inactive image does NOT appear on frontend

#### Test 18: Active Status
1. Change the same image back to "Active"
2. View frontend
3. **Expected Result:** Image now appears on frontend

### Error Handling Testing

#### Test 19: Missing Required Fields
1. Try to upload image without title
2. **Expected Result:** Error message about required fields

#### Test 20: Database Connection Error
1. Temporarily change database credentials in .env
2. Try to access admin images
3. **Expected Result:** Graceful error handling (check error logs)

### PHP Function Testing

You can create a test file `test-image-functions.php` in the root directory:

```php
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Test getImagesBySection
echo "Testing getImagesBySection('banner'):\n";
$banners = getImagesBySection('banner');
echo "Found " . count($banners) . " banner images\n\n";

// Test getFirstImage
echo "Testing getFirstImage('gallery'):\n";
$first = getFirstImage('gallery');
if ($first) {
    echo "First gallery image: " . $first['title'] . "\n";
} else {
    echo "No gallery images found\n";
}
?>
```

Run with: `php test-image-functions.php`

## Common Issues and Solutions

### Issue: "Directory not writable"
**Solution:** 
```bash
chmod 755 uploads/
chown www-data:www-data uploads/  # For Apache
```

### Issue: "Image not displaying on frontend"
**Solution:** 
1. Check image status is "Active"
2. Verify correct section name
3. Check file exists: `ls uploads/`
4. Clear browser cache

### Issue: "Cannot upload large images"
**Solution:** Check PHP settings in `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### Issue: "API returns empty array"
**Solution:** 
1. Check section name is correct
2. Verify images exist with that section in database
3. Check image status is "active"

## Success Criteria

✅ All 20 tests pass without errors
✅ Images upload and display correctly
✅ API returns expected JSON
✅ Security validations work
✅ Error messages are clear and helpful
✅ No PHP warnings or errors in logs

## Reporting Issues

If any tests fail, please provide:
1. Test number that failed
2. Error message or unexpected behavior
3. PHP error logs (check `/var/log/php/error.log` or similar)
4. Browser console errors (F12 Developer Tools)
5. Database queries (if applicable)
