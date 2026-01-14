# Image Upload Feature - User Guide

## Overview

The Image Upload Feature allows administrators to dynamically manage images across different sections of the website through an intuitive admin panel. Images are organized by sections and can be displayed automatically on the frontend.

## Available Sections

The system supports the following image sections:

1. **Banner** - Main hero/banner images on the homepage
2. **Venue Gallery** - Images displayed in venue galleries
3. **Hall Gallery** - Images shown in hall detail pages
4. **Package/Menu** - Images for menu packages
5. **Gallery** - General photo gallery section on the homepage
6. **Testimonials** - Images for testimonial sections
7. **Features** - Images for feature sections
8. **About Us** - Images for about us section
9. **Other** - Miscellaneous images

## Admin Panel Usage

### Accessing Image Management

1. Log in to the admin panel
2. Click on **Images** in the sidebar navigation
3. You will see a list of all uploaded images

### Uploading a New Image

1. Click the **Upload New Image** button
2. Fill in the required fields:
   - **Image Title**: A descriptive title for the image
   - **Section**: Select where the image should appear
   - **Description**: Optional description or alt text
   - **Image File**: Select the image file (JPG, PNG, GIF, WebP, max 5MB)
   - **Display Order**: Lower numbers appear first (default: 0)
   - **Status**: Active or Inactive
3. Click **Upload Image**

### Editing an Image

1. From the image list, click the **Edit** button (pencil icon)
2. Update any fields as needed
3. Optionally upload a new image file to replace the current one
4. Click **Update Image**

### Viewing Image Details

1. From the image list, click the **View** button (eye icon)
2. View full image details including:
   - Preview of the image
   - Image dimensions and file size
   - Full URL for the image
   - Creation and update dates

### Deleting an Image

1. From the image list, click the **Delete** button (trash icon)
2. Confirm the deletion
3. The image will be removed from both the database and server

## Frontend Display

### Banner Section

Banner images appear as the background of the hero section on the homepage. The system automatically uses the first active banner image (ordered by display_order).

**Code Example:**
```php
<?php
$banner_images = getImagesBySection('banner', 1);
$banner_image = !empty($banner_images) ? $banner_images[0] : null;
?>
<section style="background: url('<?php echo $banner_image['image_url']; ?>') center/cover;">
    <!-- Content -->
</section>
```

### Gallery Section

Gallery images are displayed in a grid on the homepage. Up to 6 images are shown by default.

**Code Example:**
```php
<?php
$gallery_images = getImagesBySection('gallery', 6);
foreach ($gallery_images as $image):
?>
    <img src="<?php echo $image['image_url']; ?>" alt="<?php echo $image['title']; ?>">
<?php endforeach; ?>
```

### API Access

Images can also be retrieved via the API endpoint:

**Endpoint:** `/api/get-images.php?section=SECTION_NAME`

**Example:** `/api/get-images.php?section=gallery`

**Response:**
```json
{
    "success": true,
    "section": "gallery",
    "count": 6,
    "images": [
        {
            "id": 1,
            "title": "Grand Ballroom",
            "description": "Our luxurious ballroom",
            "image_path": "gallery_1234567890_abc123.jpg",
            "image_url": "http://example.com/uploads/gallery_1234567890_abc123.jpg",
            "section": "gallery",
            "display_order": 0,
            "file_exists": true
        }
    ]
}
```

## Helper Functions

### PHP Functions

**Get images by section:**
```php
$images = getImagesBySection('gallery', 10); // Get up to 10 gallery images
```

**Get first image from section:**
```php
$banner = getFirstImage('banner'); // Get the first banner image
```

## Technical Details

### Database Schema

Images are stored in the `site_images` table with the following structure:

- `id` - Primary key
- `title` - Image title
- `description` - Optional description
- `image_path` - Filename on server
- `section` - Section identifier
- `display_order` - Order for display (lower = first)
- `status` - Active or Inactive
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

### File Storage

- Images are stored in `/uploads/` directory
- Filenames are automatically generated: `{section}_{timestamp}_{uniqueid}.{extension}`
- Supported formats: JPG, JPEG, PNG, GIF, WebP
- Maximum file size: 5MB

### Security

- File types are validated on upload
- File size limits are enforced
- Filenames are sanitized and made unique
- Only authenticated admin users can upload images
- Activity logging tracks all image operations

## Best Practices

1. **Image Optimization**: Optimize images before uploading to reduce file size
2. **Naming**: Use descriptive titles for better organization
3. **Display Order**: Use increments of 10 (0, 10, 20) to allow easy reordering
4. **Alt Text**: Always provide descriptions for accessibility
5. **Active Status**: Use inactive status instead of deleting if you want to hide temporarily

## Troubleshooting

### Image not displaying

1. Check that the image status is "Active"
2. Verify the section name matches in both admin and frontend
3. Check file permissions on `/uploads/` directory (should be 755)
4. Confirm the image file exists on the server

### Upload fails

1. Check file size (must be under 5MB)
2. Verify file format is supported (JPG, PNG, GIF, WebP)
3. Ensure `/uploads/` directory exists and is writable
4. Check PHP upload_max_filesize and post_max_size settings

### Images not ordered correctly

1. Set explicit display_order values (0, 10, 20, etc.)
2. Lower numbers appear first
3. When display_order is the same, newer images appear first
