# Security Features - Image Upload Implementation

## Overview
This document details the comprehensive security measures implemented in the image upload functionality.

## Multi-Layer Security Approach

### 1. File Upload Security

#### Layer 1: MIME Type Validation (Browser-Provided)
```php
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    return error;
}
```
- Validates the MIME type provided by the browser
- First line of defense against invalid file types

#### Layer 2: Content Validation (getimagesize)
```php
$image_info = getimagesize($file['tmp_name']);
if ($image_info === false) {
    return error;
}
```
- Validates actual file content using PHP's image analysis
- Prevents file type spoofing attacks
- Cannot be bypassed by renaming files

#### Layer 3: MIME-to-Extension Mapping
```php
$mime_to_ext = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];
$extension = $mime_to_ext[$image_info['mime']];
```
- Ignores client-provided file extension completely
- Uses actual content type to determine extension
- Prevents extension manipulation attacks

#### Layer 4: File Size Validation
```php
$max_size = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $max_size) {
    return error;
}
```
- Prevents resource exhaustion attacks
- Limits disk usage
- Enforces reasonable file sizes

#### Layer 5: Unique Filename Generation
```php
$filename = basename($prefix . '_' . time() . '_' . uniqid() . '.' . $extension);
```
- Prevents filename collisions
- Makes filenames unpredictable
- Uses basename() for additional sanitization

### 2. Path Traversal Prevention

#### Layer 1: Directory Separator Detection
```php
if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    return false;
}
```
- Detects both Unix (/) and Windows (\) path separators
- Prevents basic path traversal attempts

#### Layer 2: Relative Path Detection
```php
if (strpos($filename, '..') !== false) {
    return false;
}
```
- Detects relative path components
- Prevents "../" directory traversal

#### Layer 3: basename() Sanitization
```php
$filename = basename($filename);
```
- Removes any path components
- Returns only the filename portion
- Built-in PHP security function

#### Layer 4: Additional Path Validation
```php
if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || 
    strpos($filename, '..') !== false) {
    return false;
}
```
- Secondary check after basename()
- Defense in depth approach

#### Layer 5: Expected Path Comparison
```php
$real_upload_path = realpath(UPLOAD_PATH);
$expected_path = $real_upload_path . DIRECTORY_SEPARATOR . $filename;
$real_file_path = realpath($filepath);

if ($real_file_path !== $expected_path) {
    return false;
}
```
- Validates file is in expected location
- Uses realpath() for canonical path resolution
- Compares actual vs expected paths

### 3. CSRF Protection

#### Token Generation
```php
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```
- Uses cryptographically secure random_bytes()
- 32 bytes (64 hex characters) for high entropy
- One token per session

#### Token Verification
```php
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}
```
- Uses hash_equals() for constant-time comparison
- Prevents timing attacks
- Validates token presence and value

#### Implementation
```php
// In forms
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

// In handlers
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $error_message = 'Invalid security token. Please try again.';
}
```
- Token included in all destructive forms
- Verified before processing any changes
- User-friendly error messages

### 4. XSS Prevention

#### URL Encoding
```php
$image_url = UPLOAD_URL . rawurlencode($image_filename);
```
- Uses rawurlencode() for URL components
- Prevents URL-based XSS attacks
- Properly encodes special characters

#### HTML Escaping
```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```
- Escapes all HTML special characters
- Uses ENT_QUOTES to escape both single and double quotes
- Specifies UTF-8 encoding for proper character handling

#### Output Escaping
All dynamic content is escaped before output:
- Image URLs: rawurlencode() + htmlspecialchars()
- Database values: htmlspecialchars()
- User input: htmlspecialchars()
- CSRF tokens: htmlspecialchars()

### 5. Directory Security

#### Directory Creation with Error Handling
```php
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        return error;
    }
}
```
- Checks directory existence
- Creates with secure permissions (0755)
- Handles creation errors gracefully

#### Upload Directory Validation
- Directory path is constant (UPLOAD_PATH)
- Cannot be modified by user input
- Validated on every operation

### 6. Request Method Validation

#### POST for Destructive Operations
```php
if (isset($_POST['delete_image']) && $_SERVER['REQUEST_METHOD'] === 'POST')
```
- DELETE operations use POST, not GET
- Prevents CSRF via URL manipulation
- Follows REST principles

### 7. Database Security

#### Prepared Statements
```php
$stmt = $db->prepare("SELECT * FROM hall_images WHERE id = ? AND hall_id = ?");
$stmt->execute([$image_id, $hall_id]);
```
- All queries use prepared statements
- Prevents SQL injection
- Parameters bound safely

#### Input Validation
```php
$image_id = intval($_POST['delete_image']);
$hall_id = intval($_GET['id']);
```
- All numeric inputs cast to integers
- String inputs sanitized
- Validation before database operations

## Security Checklist

- [x] File type validation (3 layers)
- [x] File size limits
- [x] Secure filename generation
- [x] Path traversal prevention (5 layers)
- [x] CSRF protection
- [x] XSS prevention
- [x] SQL injection prevention
- [x] Secure directory permissions
- [x] POST for destructive operations
- [x] Proper error handling
- [x] Activity logging
- [x] Session security

## Testing Recommendations

### File Upload Tests
1. Try uploading non-image files
2. Try uploading oversized files (>5MB)
3. Try uploading files with malicious extensions
4. Try uploading files with path traversal in names
5. Verify extension matches actual content

### CSRF Tests
1. Try deleting without token
2. Try deleting with invalid token
3. Try replaying old tokens
4. Verify token regeneration

### XSS Tests
1. Upload files with special characters in names
2. Verify proper encoding in HTML
3. Verify proper encoding in URLs
4. Check all output contexts

### Path Traversal Tests
1. Try "../" in filenames
2. Try absolute paths
3. Try mixing separators
4. Verify files stay in upload directory

## Compliance

This implementation follows security best practices from:
- OWASP Top 10
- OWASP File Upload Cheat Sheet
- PHP Security Best Practices
- CWE/SANS Top 25

## Maintenance

### Regular Security Updates
- Keep PHP updated
- Monitor security advisories
- Review and update allowed MIME types
- Audit file permissions regularly

### Logging and Monitoring
- All upload operations logged
- All deletion operations logged
- Review logs for suspicious patterns
- Monitor disk usage

### Security Reviews
- Regular code reviews
- Penetration testing
- Security audits
- Update documentation

## Conclusion

This implementation provides enterprise-grade security for image uploads through multiple layers of protection. Each layer addresses specific attack vectors, and together they create a robust defense against common vulnerabilities.
