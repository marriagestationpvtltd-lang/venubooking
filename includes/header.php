<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Get site settings
$site_name = getSetting('site_name', 'Venue Booking System');
$site_logo = getSetting('site_logo', '');
$site_favicon = getSetting('site_favicon', '');
$meta_title = getSetting('meta_title', '');
$meta_description = getSetting('meta_description', '');
$meta_keywords = getSetting('meta_keywords', '');

// Build page title
$full_title = isset($page_title) ? $page_title . ' - ' . $site_name : $site_name;
if (!empty($meta_title)) {
    $full_title = $meta_title;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_title); ?></title>
    
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <?php endif; ?>
    
    <?php if (!empty($site_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(UPLOAD_URL . $site_favicon); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars(UPLOAD_URL . $site_favicon); ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/booking.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/responsive.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/nepali-date-picker.css">
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>/index.php">
                <?php if (!empty($site_logo)): ?>
                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $site_logo); ?>" 
                         alt="<?php echo htmlspecialchars($site_name); ?>" 
                         style="max-height: 40px; max-width: 200px;">
                <?php else: ?>
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($site_name); ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">Home</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
