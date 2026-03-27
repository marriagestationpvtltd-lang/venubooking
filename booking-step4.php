<?php
// Packages step removed – redirect transparently to the services step.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Save selected menus when arriving from step 3 (booking-step3.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menus'])) {
    $_SESSION['selected_menus'] = $_POST['menus'];
}

// Package step removed – clear any stale package selection
unset($_SESSION['selected_packages']);

header('Location: booking-step5.php');
exit;
