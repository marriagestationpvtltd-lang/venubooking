    <!-- Footer -->
    <footer style="background-color: #2E7D32; color: white; padding: 40px 0; margin-top: 60px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 20px;"><?php echo APP_NAME; ?></h5>
                    <p><?php echo getSetting('site_address', 'Kathmandu, Nepal'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo getSetting('site_phone', '+977-1-4123456'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo getSetting('site_email', 'info@venubooking.com'); ?></p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 20px;">Quick Links</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px;"><a href="/" style="color: white; text-decoration: none;">Home</a></li>
                        <li style="margin-bottom: 10px;"><a href="/admin/login.php" style="color: white; text-decoration: none;">Admin Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="font-weight: 700; margin-bottom: 20px;">Follow Us</h5>
                    <div>
                        <a href="#" style="color: white; font-size: 24px; margin-right: 15px;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="color: white; font-size: 24px; margin-right: 15px;"><i class="fab fa-instagram"></i></a>
                        <a href="#" style="color: white; font-size: 24px; margin-right: 15px;"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.3); margin: 30px 0;">
            <div class="text-center">
                <p style="margin: 0;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- jQuery Validation -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    
    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
