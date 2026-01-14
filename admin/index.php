<?php
/**
 * Admin Index - Redirect to Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect('/admin/dashboard.php');
} else {
    redirect('/admin/login.php');
}
