        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-dashboard"></i> Admin Panel</h3>
            </div>
            
            <ul class="list-unstyled components">
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'active' : ''; ?>">
                    <a href="/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/venues/') !== false) ? 'active' : ''; ?>">
                    <a href="#venuesSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-building"></i> Venues
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/venues/') !== false) ? 'show' : ''; ?>" id="venuesSubmenu">
                        <li><a href="/admin/venues/list.php"><i class="fas fa-list"></i> All Venues</a></li>
                        <li><a href="/admin/venues/add.php"><i class="fas fa-plus"></i> Add Venue</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/halls/') !== false) ? 'active' : ''; ?>">
                    <a href="#hallsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-door-open"></i> Halls
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/halls/') !== false) ? 'show' : ''; ?>" id="hallsSubmenu">
                        <li><a href="/admin/halls/list.php"><i class="fas fa-list"></i> All Halls</a></li>
                        <li><a href="/admin/halls/add.php"><i class="fas fa-plus"></i> Add Hall</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/menus/') !== false) ? 'active' : ''; ?>">
                    <a href="#menusSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-utensils"></i> Menus
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/menus/') !== false) ? 'show' : ''; ?>" id="menusSubmenu">
                        <li><a href="/admin/menus/list.php"><i class="fas fa-list"></i> All Menus</a></li>
                        <li><a href="/admin/menus/add.php"><i class="fas fa-plus"></i> Add Menu</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/bookings/') !== false) ? 'active' : ''; ?>">
                    <a href="#bookingsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-calendar-check"></i> Bookings
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/bookings/') !== false) ? 'show' : ''; ?>" id="bookingsSubmenu">
                        <li><a href="/admin/bookings/list.php"><i class="fas fa-list"></i> All Bookings</a></li>
                        <li><a href="/admin/bookings/calendar.php"><i class="fas fa-calendar"></i> Calendar View</a></li>
                        <li><a href="/admin/bookings/add.php"><i class="fas fa-plus"></i> Add Booking</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/customers/') !== false) ? 'active' : ''; ?>">
                    <a href="/admin/customers/list.php">
                        <i class="fas fa-users"></i> Customers
                    </a>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/services/') !== false) ? 'active' : ''; ?>">
                    <a href="#servicesSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-concierge-bell"></i> Services
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/services/') !== false) ? 'show' : ''; ?>" id="servicesSubmenu">
                        <li><a href="/admin/services/list.php"><i class="fas fa-list"></i> All Services</a></li>
                        <li><a href="/admin/services/add.php"><i class="fas fa-plus"></i> Add Service</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/reports/') !== false) ? 'active' : ''; ?>">
                    <a href="#reportsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/reports/') !== false) ? 'show' : ''; ?>" id="reportsSubmenu">
                        <li><a href="/admin/reports/revenue.php"><i class="fas fa-money-bill"></i> Revenue</a></li>
                        <li><a href="/admin/reports/bookings.php"><i class="fas fa-calendar"></i> Bookings</a></li>
                        <li><a href="/admin/reports/customers.php"><i class="fas fa-users"></i> Customers</a></li>
                    </ul>
                </li>
                
                <li class="<?php echo (strpos($_SERVER['PHP_SELF'], '/settings/') !== false) ? 'active' : ''; ?>">
                    <a href="#settingsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($_SERVER['PHP_SELF'], '/settings/') !== false) ? 'show' : ''; ?>" id="settingsSubmenu">
                        <li><a href="/admin/settings/general.php"><i class="fas fa-globe"></i> General</a></li>
                        <li><a href="/admin/settings/booking.php"><i class="fas fa-calendar-alt"></i> Booking</a></li>
                        <li><a href="/admin/settings/users.php"><i class="fas fa-users-cog"></i> Users</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <!-- Page Content -->
        <div id="content" class="content">
