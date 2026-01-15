    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><?php echo htmlspecialchars(getSetting('site_name', 'Venue Booking System')); ?></h5>
                    <p><?php echo htmlspecialchars(getSetting('footer_about', 'Your perfect venue for every occasion')); ?></p>
                    
                    <?php
                    // Get social media links
                    $social_links = [
                        'facebook' => ['icon' => 'fab fa-facebook', 'url' => getSetting('social_facebook', '')],
                        'instagram' => ['icon' => 'fab fa-instagram', 'url' => getSetting('social_instagram', '')],
                        'tiktok' => ['icon' => 'fab fa-tiktok', 'url' => getSetting('social_tiktok', '')],
                        'twitter' => ['icon' => 'fab fa-twitter', 'url' => getSetting('social_twitter', '')],
                        'youtube' => ['icon' => 'fab fa-youtube', 'url' => getSetting('social_youtube', '')],
                        'linkedin' => ['icon' => 'fab fa-linkedin', 'url' => getSetting('social_linkedin', '')],
                    ];
                    
                    $has_social = false;
                    foreach ($social_links as $link) {
                        if (!empty($link['url'])) {
                            $has_social = true;
                            break;
                        }
                    }
                    
                    if ($has_social):
                    ?>
                    <div class="social-links mt-3">
                        <?php foreach ($social_links as $platform => $link): ?>
                            <?php if (!empty($link['url'])): ?>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer" 
                                   class="text-white me-3" title="<?php echo ucfirst($platform); ?>">
                                    <i class="<?php echo $link['icon']; ?> fa-lg"></i>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <?php 
                        // Get quick links from settings
                        $quick_links_json = getSetting('quick_links', '[]');
                        $quick_links = json_decode($quick_links_json, true);
                        
                        // Ensure we have valid data
                        if (!is_array($quick_links)) {
                            $quick_links = [];
                        }
                        
                        // Sort by order if it exists
                        usort($quick_links, function($a, $b) {
                            $order_a = isset($a['order']) ? intval($a['order']) : PHP_INT_MAX;
                            $order_b = isset($b['order']) ? intval($b['order']) : PHP_INT_MAX;
                            return $order_a - $order_b;
                        });
                        
                        // Display quick links
                        if (!empty($quick_links)):
                            foreach ($quick_links as $link):
                                if (!empty($link['label']) && !empty($link['url'])):
                                    // Check if URL is absolute or relative
                                    $url = $link['url'];
                                    // Check if URL has a protocol (http, https, mailto, tel, etc.) or is protocol-relative (//)
                                    $parsed = parse_url($url);
                                    $has_scheme = isset($parsed['scheme']);
                                    $is_protocol_relative = (strpos($url, '//') === 0);
                                    
                                    if (!$has_scheme && !$is_protocol_relative) {
                                        // No protocol and not protocol-relative - treat as relative URL
                                        if (strpos($url, '/') === 0) {
                                            // URL starts with / - relative to domain root
                                            $url = BASE_URL . $url;
                                        } else {
                                            // URL doesn't start with / - relative to current directory
                                            $url = BASE_URL . '/' . $url;
                                        }
                                    }
                                    // If has_scheme or is_protocol_relative is true, use URL as-is
                        ?>
                        <li><a href="<?php echo htmlspecialchars($url); ?>" class="text-white-50 text-decoration-none"><?php echo htmlspecialchars($link['label']); ?></a></li>
                        <?php 
                                endif;
                            endforeach;
                        else:
                            // Default fallback if no links are configured
                        ?>
                        <li><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Contact</h5>
                    <?php 
                    $contact_phone = getSetting('contact_phone', '');
                    $contact_email = getSetting('contact_email', '');
                    $contact_address = getSetting('contact_address', '');
                    $whatsapp_number = getSetting('whatsapp_number', '');
                    ?>
                    
                    <?php if (!empty($contact_phone)): ?>
                        <p class="mb-1">
                            <i class="fas fa-phone"></i> 
                            <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="text-white-50 text-decoration-none">
                                <?php echo htmlspecialchars($contact_phone); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_email)): ?>
                        <p class="mb-1">
                            <i class="fas fa-envelope"></i> 
                            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="text-white-50 text-decoration-none">
                                <?php echo htmlspecialchars($contact_email); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($whatsapp_number)): ?>
                        <p class="mb-1">
                            <i class="fab fa-whatsapp"></i> 
                            <?php 
                            $clean_whatsapp = preg_replace('/[^0-9]/', '', $whatsapp_number);
                            if (!empty($clean_whatsapp)): 
                            ?>
                            <a href="https://wa.me/<?php echo htmlspecialchars($clean_whatsapp); ?>" 
                               target="_blank" rel="noopener noreferrer" class="text-white-50 text-decoration-none">
                                WhatsApp Us
                            </a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_address)): ?>
                        <p class="mb-1">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo nl2br(htmlspecialchars($contact_address)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p class="mb-0">
                    <?php 
                    $copyright_text = getSetting('footer_copyright', '');
                    if (!empty($copyright_text)) {
                        echo htmlspecialchars($copyright_text);
                    } else {
                        echo '&copy; ' . date('Y') . ' ' . htmlspecialchars(getSetting('site_name', 'Venue Booking System')) . '. All rights reserved.';
                    }
                    ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Base URL for API calls -->
    <script>
        const baseUrl = "<?php echo BASE_URL; ?>";
    </script>
    
    <!-- Nepali Date Picker -->
    <script src="<?php echo BASE_URL; ?>/js/nepali-date-picker.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/js/main.js"></script>
    
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
