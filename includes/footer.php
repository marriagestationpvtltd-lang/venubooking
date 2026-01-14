    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Venue Booking System</h5>
                    <p>Your perfect venue for every occasion</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/admin/login.php" class="text-white-50">Admin</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <p class="mb-1"><i class="fas fa-phone"></i> <?php echo getSetting('contact_phone', '+977 1234567890'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo getSetting('contact_email', 'info@venubooking.com'); ?></p>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Venue Booking System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/js/main.js"></script>
    
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
