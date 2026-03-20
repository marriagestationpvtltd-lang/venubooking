<?php
/**
 * Shared Photos Management - DEPRECATED
 * 
 * This standalone photo sharing feature has been removed.
 * All users are redirected to the folder-based photo sharing feature
 * which supports large-scale photo sharing more effectively.
 * 
 * The folder-based sharing (admin/shared-folders/) provides:
 * - Better organization with folders
 * - Bulk uploads of thousands of photos
 * - ZIP download for entire folders
 * - Album/sub-folder support within folders
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Redirect to the folder-based photo sharing page
header('Location: ../shared-folders/index.php');
exit;
