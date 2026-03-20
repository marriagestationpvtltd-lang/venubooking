<?php
/**
 * Shared Photo Upload Page - DEPRECATED
 * 
 * This standalone photo upload feature has been removed.
 * Users are redirected to folder-based photo sharing which supports
 * better organization and large-scale photo uploads.
 * 
 * To upload photos:
 * 1. Go to Photo Share (admin/shared-folders/)
 * 2. Create a new folder
 * 3. Upload photos to that folder
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Redirect to the folder-based photo sharing page
header('Location: ' . BASE_URL . '/admin/shared-folders/index.php');
exit;
