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
    
    <!-- Google Fonts: Noto Sans Devanagari for Nepali text, Roboto & Roboto Mono for invoice print -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Roboto:wght@400;500;700;900&family=Roboto+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo BASE_URL; ?>/admin/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/vendor/fontawesome/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/vendor/datatables/css/dataTables.bootstrap5.min.css">
    
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
        
        .nav-link-sub {
            padding-left: 2.5rem;
        }

        .nav-link .nav-chevron {
            margin-left: auto;
            transition: transform 0.25s;
        }

        .nav-link[aria-expanded="true"] .nav-chevron {
            transform: rotate(180deg);
        }
        
        .nav-section-label {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255,255,255,.35);
            padding: .75rem 1.5rem .2rem;
            margin-top: .25rem;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.show {
            display: block;
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
    
    <!-- Nepali Date Picker CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/nepali-date-picker.css">
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
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

            <!-- ── Bookings & Events ─────────────────────── -->
            <div class="nav-section-label">Bookings &amp; Events</div>
            <a href="<?php echo BASE_URL; ?>/admin/bookings/index.php" class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'bookings/') !== false && strpos($_SERVER['PHP_SELF'], 'calendar') === false) ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Bookings
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/bookings/calendar.php" class="nav-link nav-link-sub <?php echo strpos($_SERVER['PHP_SELF'], 'bookings/calendar') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Booking Calendar
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/customers/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Customers
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/planner/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/planner/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Planner
            </a>

            <!-- ── Venue & Hall ──────────────────────────── -->
            <div class="nav-section-label">Venue &amp; Hall</div>
            <a href="<?php echo BASE_URL; ?>/admin/venues/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'venues') !== false ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Venues
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/cities/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'cities') !== false ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i> Cities
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/halls/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'halls') !== false ? 'active' : ''; ?>">
                <i class="fas fa-door-open"></i> Halls
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/menus/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'menus') !== false ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i> Menus
            </a>

            <!-- ── Services & Packages ───────────────────── -->
            <div class="nav-section-label">Services &amp; Packages</div>
            <a href="<?php echo BASE_URL; ?>/admin/services/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'services') !== false ? 'active' : ''; ?>">
                <i class="fas fa-concierge-bell"></i> Services
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/packages/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/packages/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i> Packages
            </a>

            <!-- ── Vendors ───────────────────────────────── -->
            <?php
            $self = $_SERVER['PHP_SELF'];
            $vendor_list_active    = strpos($self, '/admin/vendors/') !== false && strpos($self, 'dues') === false;
            $vendor_types_active   = strpos($self, 'vendor-types') !== false;
            $vendor_dues_active    = basename($self) === 'dues.php' && strpos($self, '/vendors/') !== false;
            $vendor_payable_active = strpos($self, 'vendor-payable') !== false;
            $vendor_active = $vendor_list_active || $vendor_types_active || $vendor_dues_active || $vendor_payable_active;
            ?>
            <div class="nav-section-label">Vendors</div>
            <a href="#vendorSubmenu" class="nav-link <?php echo $vendor_active ? 'active' : ''; ?>"
               data-bs-toggle="collapse"
               aria-expanded="<?php echo $vendor_active ? 'true' : 'false'; ?>">
                <i class="fas fa-user-tie"></i> Manage Vendors
                <i class="fas fa-chevron-down nav-chevron"></i>
            </a>
            <div class="collapse <?php echo $vendor_active ? 'show' : ''; ?>" id="vendorSubmenu">
                <a href="<?php echo BASE_URL; ?>/admin/vendors/index.php" class="nav-link nav-link-sub <?php echo $vendor_list_active ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Vendors
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/vendor-types/index.php" class="nav-link nav-link-sub <?php echo $vendor_types_active ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Vendor Types
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/vendors/dues.php" class="nav-link nav-link-sub <?php echo $vendor_dues_active ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> Vendor Dues
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/vendor-payable/index.php" class="nav-link nav-link-sub <?php echo $vendor_payable_active ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-usd"></i> Vendor Payable
                </a>
            </div>

            <!-- ── Finance & Reports ─────────────────────── -->
            <div class="nav-section-label">Finance &amp; Reports</div>
            <a href="<?php echo BASE_URL; ?>/admin/payment-methods/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payment-methods') !== false ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Payment Methods
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/venue-payable/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'venue-payable') !== false ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-usd"></i> Venue Payable
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

            <!-- ── Media & Content ───────────────────────── -->
            <div class="nav-section-label">Media &amp; Content</div>
            <a href="<?php echo BASE_URL; ?>/admin/images/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/images/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> Images
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/gallery-cards/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/gallery-cards/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Gallery Cards
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reviews/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/reviews/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/shared-folders/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'shared-folders') !== false ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> Photo Share
            </a>

            <!-- ── System ────────────────────────────────── -->
            <div class="nav-section-label">System</div>
            <a href="<?php echo BASE_URL; ?>/admin/policy-pages/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'policy-pages') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Policy Pages
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/settings/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>">
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
            <div class="d-flex align-items-center gap-2">

                <!-- ── Call notification bell ── -->
                <div class="position-relative me-1">
                    <button class="btn btn-outline-secondary position-relative" id="callNotifBell"
                            title="Incoming Calls" aria-label="Incoming call notifications">
                        <i class="fas fa-phone-volume"></i>
                        <span id="callNotifBadge"
                              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                              style="font-size:.65rem;">0</span>
                    </button>
                    <!-- Call queue panel (toggle on bell click) -->
                    <div id="callQueuePanel"
                         class="d-none position-absolute end-0 bg-white border rounded shadow-lg"
                         style="min-width:320px;max-width:380px;z-index:2000;top:calc(100% + 8px);">
                        <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center justify-content-between">
                            <span class="fw-semibold small"><i class="fas fa-phone-volume me-1 text-success"></i>Incoming Calls</span>
                            <button type="button" class="btn-close btn-sm" id="closeCallQueuePanel" aria-label="Close"></button>
                        </div>
                        <div id="callQueueList" class="list-group list-group-flush"
                             style="max-height:300px;overflow-y:auto;">
                            <div class="list-group-item text-muted small py-3 text-center">No active calls</div>
                        </div>
                    </div>
                </div>

                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['full_name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/change-password.php">
                            <i class="fas fa-key"></i> Change Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
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
        </div>

        <!-- ── Incoming Call Modal ─────────────────────────────────────────── -->
        <div class="modal fade" id="incomingCallModal" tabindex="-1" aria-labelledby="incomingCallModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="incomingCallModalLabel">
                            <i class="fas fa-phone-volume fa-shake me-2"></i>Incoming Call
                        </h5>
                    </div>
                    <div class="modal-body">

                        <!-- Caller details (filled by JS) -->
                        <div id="callerDetailsHTML" class="mb-3"></div>

                        <!-- Ringing state: accept / decline -->
                        <div id="callModalRinging">
                            <div class="d-flex gap-2">
                                <button type="button" id="acceptCallBtn"
                                        class="btn btn-success flex-fill btn-lg"
                                        data-call-id="">
                                    <i class="fas fa-phone me-2"></i>Answer
                                </button>
                                <button type="button" id="declineCallBtn"
                                        class="btn btn-danger flex-fill btn-lg"
                                        data-call-id="">
                                    <i class="fas fa-phone-slash me-2"></i>Decline
                                </button>
                            </div>
                        </div>

                        <!-- Active-call state: mute / end -->
                        <div id="callModalActive" class="d-none">
                            <div class="alert alert-success py-2 mb-3 text-center">
                                <i class="fas fa-circle text-success me-1" style="font-size:.6rem;"></i>
                                Call connected &nbsp;
                                <strong id="callAdminTimer">0:00</strong>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" id="muteCallAdminBtn" class="btn btn-outline-secondary flex-fill">
                                    <i class="fas fa-microphone" id="muteCallAdminIcon"></i>
                                    <span id="muteCallAdminText"> Mute</span>
                                </button>
                                <button type="button" id="endCallAdminBtn" class="btn btn-danger flex-fill">
                                    <i class="fas fa-phone-slash me-1"></i>End Call
                                </button>
                            </div>
                        </div>

                        <!-- Ended state -->
                        <div id="callModalEnded" class="d-none text-center text-muted py-2">
                            <i class="fas fa-phone-slash fa-2x mb-2 text-secondary"></i>
                            <p class="mb-0">Call ended.</p>
                            <button type="button" class="btn btn-secondary btn-sm mt-2"
                                    data-bs-dismiss="modal">Close</button>
                        </div>

                        <!-- Hidden audio for remote stream -->
                        <audio id="callAdminRemoteAudio" autoplay playsinline style="display:none;"></audio>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
