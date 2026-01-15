<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --dark-green: #2E7D32;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 250px;
            background: #2c3e50;
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            background: #1a252f;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: var(--primary-green);
            color: white;
        }
        
        .nav-link i {
            width: 25px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-success {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-success:hover {
            background: var(--dark-green);
            border-color: var(--dark-green);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h5 class="mb-0"><i class="fas fa-building"></i> Venue Booking</h5>
            <small>Admin Panel</small>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/venues/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'venues') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Venues
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/halls/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'halls') ? 'active' : ''; ?>">
                <i class="fas fa-door-open"></i> Halls
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/menus/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'menus') ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i> Menus
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'bookings') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Bookings
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/customers/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Customers
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/services/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'services') ? 'active' : ''; ?>">
                <i class="fas fa-concierge-bell"></i> Services
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/images/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'images') ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> Images
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/payment-methods/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payment-methods') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Payment Methods
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/settings/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-outline-secondary d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 d-inline ms-2"><?php echo $page_title ?? 'Dashboard'; ?></h5>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php" target="_blank">
                        <i class="fas fa-eye"></i> View Site
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
        
        <!-- Page Content -->
