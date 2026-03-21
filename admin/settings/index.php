<?php
$page_title = 'Settings';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

$success = '';
$error = '';
$test_email_result = null;

// Handle test email action (separate from settings form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $test_to = trim($_POST['test_email_address'] ?? '');
        if (empty($test_to) || !filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address to send the test email.';
        } else {
            $site_name = getSetting('site_name', 'Venue Booking System');
            $subject   = 'Test Email from ' . $site_name;
            $message   = '<p>This is a test email sent from <strong>' . htmlspecialchars($site_name) . '</strong> to verify your email configuration is working correctly.</p>'
                       . '<p>If you received this email, your email settings are configured properly.</p>';
            $sent = sendEmail($test_to, $subject, $message);
            if ($sent) {
                $success = 'Test email sent successfully to ' . htmlspecialchars($test_to) . '. Please check your inbox.';
            } else {
                $error = 'Failed to send test email. Please review your email/SMTP settings and check the server error log for details.';
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'send_test_email') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $db->beginTransaction();
        
        // Handle file uploads (logo and favicon)
        if (isset($_FILES['setting_site_logo']) && $_FILES['setting_site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['setting_site_logo'], 'logo');
            if ($upload_result['success']) {
                // Delete old logo if exists
                $old_logo = getSetting('site_logo', '');
                if (!empty($old_logo)) {
                    deleteUploadedFile($old_logo);
                }
                $_POST['setting_site_logo'] = $upload_result['filename'];
            } else {
                throw new Exception('Logo upload failed: ' . $upload_result['message']);
            }
        }
        
        if (isset($_FILES['setting_site_favicon']) && $_FILES['setting_site_favicon']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['setting_site_favicon'], 'favicon');
            if ($upload_result['success']) {
                // Delete old favicon if exists
                $old_favicon = getSetting('site_favicon', '');
                if (!empty($old_favicon)) {
                    deleteUploadedFile($old_favicon);
                }
                $_POST['setting_site_favicon'] = $upload_result['filename'];
            } else {
                throw new Exception('Favicon upload failed: ' . $upload_result['message']);
            }
        }
        
        if (isset($_FILES['setting_company_logo']) && $_FILES['setting_company_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['setting_company_logo'], 'logo');
            if ($upload_result['success']) {
                $_POST['setting_company_logo'] = $upload_result['filename'];
                // Only delete old logo after successful upload
                $old_company_logo = getSetting('company_logo', '');
                if (!empty($old_company_logo) && $old_company_logo !== $upload_result['filename']) {
                    deleteUploadedFile($old_company_logo);
                }
            } else {
                throw new Exception('Company logo upload failed: ' . $upload_result['message']);
            }
        }
        
        // Handle Banner A upload
        if (isset($_FILES['setting_folder_banner_a']) && $_FILES['setting_folder_banner_a']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['setting_folder_banner_a'], 'banner_a');
            if ($upload_result['success']) {
                $old_banner = getSetting('folder_banner_a', '');
                if (!empty($old_banner)) {
                    deleteUploadedFile($old_banner);
                }
                $_POST['setting_folder_banner_a'] = $upload_result['filename'];
            } else {
                throw new Exception('Banner A upload failed: ' . $upload_result['message']);
            }
        }
        
        // Handle Banner B upload
        if (isset($_FILES['setting_folder_banner_b']) && $_FILES['setting_folder_banner_b']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleImageUpload($_FILES['setting_folder_banner_b'], 'banner_b');
            if ($upload_result['success']) {
                $old_banner = getSetting('folder_banner_b', '');
                if (!empty($old_banner)) {
                    deleteUploadedFile($old_banner);
                }
                $_POST['setting_folder_banner_b'] = $upload_result['filename'];
            } else {
                throw new Exception('Banner B upload failed: ' . $upload_result['message']);
            }
        }
        
        // Update all settings
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = str_replace('setting_', '', $key);
                
                // Skip empty password field (keep existing password)
                if ($setting_key === 'smtp_password' && empty($value)) {
                    continue;
                }
                
                // Check if setting exists
                $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
                $stmt->execute([$setting_key]);
                
                if ($stmt->fetch()) {
                    // Update existing setting
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $setting_key]);
                } else {
                    // Insert new setting with default type
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
                    $stmt->execute([$setting_key, $value]);
                }
            }
        }
        
        $db->commit();
        $success = 'Settings updated successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
    } // end CSRF else
}

// Get all settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
    }
    .nav-tabs .nav-link.active {
        color: #4CAF50;
        font-weight: 600;
    }
    .settings-section {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    .form-text {
        font-size: 0.875rem;
        color: #6c757d;
    }
    .image-preview {
        margin-top: 0.5rem;
    }
    .image-preview img {
        max-width: 150px;
        max-height: 150px;
        object-fit: contain;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 0.25rem;
    }
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-cog"></i> Website Settings</h5>
        <small class="text-muted">Manage all website settings from this central control panel</small>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#basic">
                        <i class="fas fa-home"></i> Basic Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#content">
                        <i class="fas fa-file-alt"></i> Content
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#email">
                        <i class="fas fa-envelope"></i> Email Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#company">
                        <i class="fas fa-building"></i> Company/Invoice
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#booking">
                        <i class="fas fa-calendar-check"></i> Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#seo">
                        <i class="fas fa-search"></i> SEO & Meta
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#social">
                        <i class="fas fa-share-alt"></i> Social Media
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#quicklinks">
                        <i class="fas fa-link"></i> Quick Links
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#bannerads">
                        <i class="fas fa-ad"></i> Banner Ads
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Basic Settings Tab -->
                <div class="tab-pane fade show active" id="basic">
                    <h6 class="mb-3 text-success"><i class="fas fa-info-circle"></i> Basic Website Information</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website Name *</label>
                            <input type="text" class="form-control" name="setting_site_name" 
                                   value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
                            <div class="form-text">The name displayed across the website</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Email *</label>
                            <input type="email" class="form-control" name="setting_contact_email" 
                                   value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" required>
                            <div class="form-text">Main contact email address</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Phone *</label>
                            <input type="text" class="form-control" name="setting_contact_phone" 
                                   value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" required>
                            <div class="form-text">Main contact phone number</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">WhatsApp Number</label>
                            <input type="text" class="form-control" name="setting_whatsapp_number" 
                                   value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>">
                            <div class="form-text">WhatsApp contact number (with country code)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Business Address</label>
                            <textarea class="form-control" name="setting_contact_address" rows="3"><?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?></textarea>
                            <div class="form-text">Full business address</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Business Hours</label>
                            <textarea class="form-control" name="setting_business_hours" rows="3"><?php echo htmlspecialchars($settings['business_hours'] ?? ''); ?></textarea>
                            <div class="form-text">Operating hours (e.g., Mon-Fri: 9 AM - 6 PM)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Google Maps URL</label>
                            <input type="url" class="form-control" name="setting_contact_map_url" 
                                   value="<?php echo htmlspecialchars($settings['contact_map_url'] ?? ''); ?>">
                            <div class="form-text">Google Maps embed or link URL</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website Logo</label>
                            <input type="file" class="form-control" name="setting_site_logo" accept="image/*">
                            <div class="form-text">Recommended: 250x60px, PNG with transparent background</div>
                            <?php if (!empty($settings['site_logo'])): ?>
                                <div class="image-preview">
                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($settings['site_logo']); ?>" alt="Current Logo">
                                    <p class="text-muted small mt-1">Current logo</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website Favicon</label>
                            <input type="file" class="form-control" name="setting_site_favicon" accept="image/*">
                            <div class="form-text">Recommended: 32x32px or 64x64px, ICO or PNG format</div>
                            <?php if (!empty($settings['site_favicon'])): ?>
                                <div class="image-preview">
                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($settings['site_favicon']); ?>" alt="Current Favicon">
                                    <p class="text-muted small mt-1">Current favicon</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency</label>
                            <input type="text" class="form-control" name="setting_currency" 
                                   value="<?php echo htmlspecialchars($settings['currency'] ?? 'NPR'); ?>">
                            <div class="form-text">Currency symbol or code (e.g., NPR, $, €)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" class="form-control" name="setting_tax_rate" 
                                   value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '13'); ?>" step="0.01" min="0">
                            <div class="form-text">Default tax rate percentage</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Advance Payment Percentage (%)</label>
                            <input type="number" class="form-control" name="setting_advance_payment_percentage" 
                                   value="<?php echo htmlspecialchars($settings['advance_payment_percentage'] ?? '25'); ?>" step="0.01" min="0" max="100">
                            <div class="form-text">Percentage of total amount required as advance payment (default: 25%)</div>
                        </div>
                    </div>
                </div>

                <!-- Content Settings Tab -->
                <div class="tab-pane fade" id="content">
                    <h6 class="mb-3 text-success"><i class="fas fa-file-alt"></i> Frontend Content</h6>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Footer About Text</label>
                            <textarea class="form-control" name="setting_footer_about" rows="3"><?php echo htmlspecialchars($settings['footer_about'] ?? ''); ?></textarea>
                            <div class="form-text">Brief description shown in footer</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Footer Copyright Text</label>
                            <input type="text" class="form-control" name="setting_footer_copyright" 
                                   value="<?php echo htmlspecialchars($settings['footer_copyright'] ?? ''); ?>">
                            <div class="form-text">Custom copyright text (leave empty for auto-generated)</div>
                        </div>
                    </div>
                </div>

                <!-- Company/Invoice Settings Tab -->
                <div class="tab-pane fade" id="company">
                    <h6 class="mb-3 text-success"><i class="fas fa-building"></i> Company & Invoice Details</h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> These details will appear on printed invoices and booking bills. If not specified, the system will use basic settings as fallback.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="setting_company_name" 
                                   value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" 
                                   placeholder="<?php echo htmlspecialchars($settings['site_name'] ?? 'Wedding Venue Booking'); ?>">
                            <div class="form-text">Company name for invoices (defaults to website name)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Phone</label>
                            <input type="text" class="form-control" name="setting_company_phone" 
                                   value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                            <div class="form-text">Company phone for invoices (defaults to contact phone)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Email</label>
                            <input type="email" class="form-control" name="setting_company_email" 
                                   value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                            <div class="form-text">Company email for invoices (defaults to contact email)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Company Address</label>
                            <textarea class="form-control" name="setting_company_address" rows="3" 
                                      placeholder="<?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?>"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                            <div class="form-text">Full company address for invoices (defaults to business address)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Company Logo (for Invoices/Bills)</label>
                            <input type="file" class="form-control" name="setting_company_logo" accept="image/*">
                            <div class="form-text">Logo specifically for printed invoices and bills. Recommended: 200x80px PNG. If not set, website logo will be used.</div>
                            <?php 
                            $logo_info = getCompanyLogo();
                            $has_company_logo = !empty($settings['company_logo']);
                            if ($logo_info !== null): 
                            ?>
                                <div class="image-preview">
                                    <img src="<?php echo $logo_info['url']; ?>" alt="Current Company Logo">
                                    <p class="text-muted small mt-1">
                                        <?php echo $has_company_logo ? 'Current company logo' : 'Using website logo (no company logo set)'; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3 text-success"><i class="fas fa-file-invoice"></i> Invoice Content</h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Customize the content that appears on printed invoices and booking bills.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Invoice Title</label>
                            <input type="text" class="form-control" name="setting_invoice_title" 
                                   value="<?php echo htmlspecialchars($settings['invoice_title'] ?? 'Wedding Booking Confirmation & Partial Payment Receipt'); ?>">
                            <div class="form-text">The main title displayed on printed invoices/bills</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Cancellation Policy</label>
                            <textarea class="form-control" name="setting_cancellation_policy" rows="8"><?php echo htmlspecialchars($settings['cancellation_policy'] ?? 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.'); ?></textarea>
                            <div class="form-text">Cancellation policy terms displayed on invoices. Each line will be shown as a separate bullet point.</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Invoice Disclaimer</label>
                            <textarea class="form-control" name="setting_invoice_disclaimer" rows="3"><?php echo htmlspecialchars($settings['invoice_disclaimer'] ?? 'Note: This is a computer-generated estimate bill. Please create a complete invoice yourself.'); ?></textarea>
                            <div class="form-text">Disclaimer note shown at the bottom of invoices</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package Label</label>
                            <input type="text" class="form-control" name="setting_invoice_package_label" 
                                   value="<?php echo htmlspecialchars($settings['invoice_package_label'] ?? 'Marriage Package'); ?>">
                            <div class="form-text">Label for hall/venue package on invoice (e.g., "Marriage Package", "Event Package")</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Additional Items Label</label>
                            <input type="text" class="form-control" name="setting_invoice_additional_items_label" 
                                   value="<?php echo htmlspecialchars($settings['invoice_additional_items_label'] ?? 'Additional Items'); ?>">
                            <div class="form-text">Label for additional services on invoice</div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email">
                    <h6 class="mb-3 text-success"><i class="fas fa-envelope"></i> Email & Notification Settings</h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Configure email settings for booking notifications. Emails will be sent to customers and admin when bookings are created or updated.
                        You can also set these values in your <code>.env</code> file using <code>MAIL_*</code> variables (e.g. <code>MAIL_SMTP_ENABLED</code>, <code>MAIL_HOST</code>, <code>MAIL_USERNAME</code>). Settings saved here take priority over the <code>.env</code> file.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Enable Email Notifications</label>
                            <select class="form-select" name="setting_email_enabled">
                                <option value="1" <?php echo ($settings['email_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="0" <?php echo ($settings['email_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                            <div class="form-text">Enable or disable all email notifications</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admin Email Address *</label>
                            <input type="email" class="form-control" name="setting_admin_email" 
                                   value="<?php echo htmlspecialchars($settings['admin_email'] ?? $settings['contact_email'] ?? ''); ?>" required>
                            <div class="form-text">Email address to receive booking notifications</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">From Name</label>
                            <input type="text" class="form-control" name="setting_email_from_name" 
                                   value="<?php echo htmlspecialchars($settings['email_from_name'] ?? 'Venue Booking System'); ?>">
                            <div class="form-text">Name shown as sender in emails</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">From Email Address</label>
                            <input type="email" class="form-control" name="setting_email_from_address" 
                                   value="<?php echo htmlspecialchars($settings['email_from_address'] ?? 'noreply@venubooking.com'); ?>">
                            <div class="form-text">Email address shown as sender</div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3 text-success"><i class="fas fa-server"></i> SMTP Configuration</h6>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> Using PHP's built-in <code>mail()</code> function (SMTP disabled) sends unauthenticated mail directly from your server. Gmail and other providers will <strong>block these emails</strong> with SPF/DKIM authentication errors. <strong>You must enable SMTP</strong> and configure an authenticated mail service (e.g. Gmail, SendGrid, Mailgun) to ensure reliable delivery.
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>For Gmail SMTP:</strong> Use <code>smtp.gmail.com</code>, port <code>587</code> (TLS) or <code>465</code> (SSL). You must create an <strong>App Password</strong> at <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> — your regular Gmail password will not work. The SMTP Username must be your full Gmail address and will be used as the sender address for SPF/DKIM alignment.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Enable SMTP</label>
                            <select class="form-select" name="setting_smtp_enabled" id="smtp_enabled">
                                <option value="0" <?php echo ($settings['smtp_enabled'] ?? '0') == '0' ? 'selected' : ''; ?>>Disabled (Use PHP mail() &mdash; not recommended, emails may be blocked)</option>
                                <option value="1" <?php echo ($settings['smtp_enabled'] ?? '0') == '1' ? 'selected' : ''; ?>>Enabled (Recommended)</option>
                            </select>
                            <div class="form-text">Enable SMTP for authenticated, reliable email delivery</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="setting_smtp_host" 
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                   placeholder="smtp.gmail.com">
                            <div class="form-text">SMTP server address</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="setting_smtp_port" 
                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                            <div class="form-text">Common ports: 587 (TLS), 465 (SSL), 25 (Plain)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">SMTP Encryption</label>
                            <select class="form-select" name="setting_smtp_encryption">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo empty($settings['smtp_encryption']) ? 'selected' : ''; ?>>None</option>
                            </select>
                            <div class="form-text">Encryption method for SMTP connection</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="setting_smtp_username" 
                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                   autocomplete="off">
                            <div class="form-text">Your full email address (e.g. yourname@gmail.com). This is used as the sender address so that SPF and DKIM checks pass.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="setting_smtp_password" 
                                   <?php echo !empty($settings['smtp_password']) ? 'placeholder="••••••••"' : 'placeholder="Enter SMTP password or App Password"'; ?>
                                   autocomplete="new-password">
                            <div class="form-text">SMTP account password or App Password (leave empty to keep current password)</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3 text-success"><i class="fas fa-vial"></i> Test Email Configuration</h6>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> After saving your email settings, use this tool to verify that emails are being delivered correctly.
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Send Test Email To</label>
                            <input type="email" class="form-control" id="testEmailAddress"
                                   placeholder="you@example.com"
                                   value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>">
                            <div class="form-text">A test message will be sent to this address</div>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-primary" id="sendTestEmailBtn">
                                <i class="fas fa-paper-plane me-1"></i> Send Test Email
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Booking Settings Tab -->
                <div class="tab-pane fade" id="booking">
                    <h6 class="mb-3 text-success"><i class="fas fa-calendar-check"></i> Booking & System Settings</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Advance Booking (Days)</label>
                            <input type="number" class="form-control" name="setting_booking_min_advance_days" 
                                   value="<?php echo htmlspecialchars($settings['booking_min_advance_days'] ?? '1'); ?>" min="0">
                            <div class="form-text">Minimum days in advance for booking</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cancellation Notice (Hours)</label>
                            <input type="number" class="form-control" name="setting_booking_cancellation_hours" 
                                   value="<?php echo htmlspecialchars($settings['booking_cancellation_hours'] ?? '24'); ?>" min="0">
                            <div class="form-text">Hours before event for cancellation</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Default Booking Status</label>
                            <select class="form-select" name="setting_default_booking_status">
                                <option value="pending" <?php echo ($settings['default_booking_status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo ($settings['default_booking_status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            </select>
                            <div class="form-text">Initial status for new bookings</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Enable Online Payment</label>
                            <select class="form-select" name="setting_enable_online_payment">
                                <option value="0" <?php echo ($settings['enable_online_payment'] ?? '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                <option value="1" <?php echo ($settings['enable_online_payment'] ?? '0') == '1' ? 'selected' : ''; ?>>Enabled</option>
                            </select>
                            <div class="form-text">Allow customers to pay online</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Allow Custom Venue Entry</label>
                            <select class="form-select" name="setting_allow_custom_venue">
                                <option value="1" <?php echo ($settings['allow_custom_venue'] ?? '1') == '1' ? 'selected' : ''; ?>>Enabled (Default)</option>
                                <option value="0" <?php echo ($settings['allow_custom_venue'] ?? '1') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                            <div class="form-text">Allow customers to enter their own venue details when it is not listed</div>
                        </div>
                    </div>
                </div>

                <!-- SEO Settings Tab -->
                <div class="tab-pane fade" id="seo">
                    <h6 class="mb-3 text-success"><i class="fas fa-search"></i> SEO & Meta Tags</h6>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Meta Title</label>
                            <input type="text" class="form-control" name="setting_meta_title" 
                                   value="<?php echo htmlspecialchars($settings['meta_title'] ?? ''); ?>" maxlength="60">
                            <div class="form-text">Page title shown in search results (50-60 characters)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Meta Description</label>
                            <textarea class="form-control" name="setting_meta_description" rows="3" maxlength="160"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
                            <div class="form-text">Description shown in search results (150-160 characters)</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Meta Keywords</label>
                            <textarea class="form-control" name="setting_meta_keywords" rows="2"><?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?></textarea>
                            <div class="form-text">Comma-separated keywords (e.g., venue booking, event venue, wedding hall)</div>
                        </div>
                    </div>
                </div>

                <!-- Social Media Tab -->
                <div class="tab-pane fade" id="social">
                    <h6 class="mb-3 text-success"><i class="fas fa-share-alt"></i> Social Media & External Links</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-facebook"></i> Facebook URL</label>
                            <input type="url" class="form-control" name="setting_social_facebook" 
                                   value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>"
                                   placeholder="https://facebook.com/yourpage">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-instagram"></i> Instagram URL</label>
                            <input type="url" class="form-control" name="setting_social_instagram" 
                                   value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>"
                                   placeholder="https://instagram.com/yourprofile">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-tiktok"></i> TikTok URL</label>
                            <input type="url" class="form-control" name="setting_social_tiktok" 
                                   value="<?php echo htmlspecialchars($settings['social_tiktok'] ?? ''); ?>"
                                   placeholder="https://tiktok.com/@yourprofile">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-twitter"></i> Twitter URL</label>
                            <input type="url" class="form-control" name="setting_social_twitter" 
                                   value="<?php echo htmlspecialchars($settings['social_twitter'] ?? ''); ?>"
                                   placeholder="https://twitter.com/yourprofile">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-youtube"></i> YouTube URL</label>
                            <input type="url" class="form-control" name="setting_social_youtube" 
                                   value="<?php echo htmlspecialchars($settings['social_youtube'] ?? ''); ?>"
                                   placeholder="https://youtube.com/channel/yourchannel">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-linkedin"></i> LinkedIn URL</label>
                            <input type="url" class="form-control" name="setting_social_linkedin" 
                                   value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? ''); ?>"
                                   placeholder="https://linkedin.com/company/yourcompany">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fab fa-google"></i> Google Review Link</label>
                            <input type="url" class="form-control" name="setting_google_review_link" 
                                   value="<?php echo htmlspecialchars($settings['google_review_link'] ?? ''); ?>"
                                   placeholder="https://g.page/r/your-business-review-link">
                            <small class="text-muted">Used in WhatsApp thank you message after full payment</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-chart-line"></i> Google Analytics Measurement ID</label>
                            <input type="text" class="form-control" name="setting_google_analytics_id" 
                                   value="<?php echo htmlspecialchars($settings['google_analytics_id'] ?? ''); ?>"
                                   placeholder="G-XXXXXXXXXX">
                            <small class="text-muted">Your GA4 Measurement ID (e.g. G-03XW3FWG7L). Leave blank to disable tracking.</small>
                        </div>
                    </div>
                </div>

                <!-- Quick Links Tab -->
                <div class="tab-pane fade" id="quicklinks">
                    <h6 class="mb-3 text-success"><i class="fas fa-link"></i> Footer Quick Links Management</h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Manage the quick links displayed in the footer section. Links will appear in the order specified.
                    </div>
                    
                    <div id="quickLinksContainer">
                        <?php
                        $quick_links_json = $settings['quick_links'] ?? '[]';
                        $quick_links = json_decode($quick_links_json, true);
                        if (!is_array($quick_links)) {
                            $quick_links = [];
                        }
                        
                        if (empty($quick_links)) {
                            $quick_links = [['label' => 'Home', 'url' => '/index.php', 'order' => 1]];
                        }
                        
                        foreach ($quick_links as $index => $link):
                        ?>
                        <div class="card mb-3 quick-link-item">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <label class="form-label">Link Label</label>
                                        <input type="text" class="form-control quick-link-label" 
                                               value="<?php echo htmlspecialchars($link['label'] ?? ''); ?>"
                                               placeholder="e.g., Home, About Us">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Link URL</label>
                                        <input type="text" class="form-control quick-link-url" 
                                               value="<?php echo htmlspecialchars($link['url'] ?? ''); ?>"
                                               placeholder="e.g., /index.php or https://example.com">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Order</label>
                                        <input type="number" class="form-control quick-link-order" 
                                               value="<?php echo htmlspecialchars($link['order'] ?? ($index + 1)); ?>"
                                               min="1">
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm remove-link" title="Remove Link">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-secondary mb-3" id="addQuickLink">
                        <i class="fas fa-plus"></i> Add Quick Link
                    </button>
                    
                    <input type="hidden" name="setting_quick_links" id="quickLinksData" value="">
                </div>

                <!-- Banner Ads Tab -->
                <div class="tab-pane fade" id="bannerads">
                    <h6 class="mb-3 text-success"><i class="fas fa-ad"></i> Folder Page Banner Ads</h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Upload banner advertisements that will be displayed on the public folder/photo sharing page (folder.php). 
                        <strong>Banner A</strong> appears on the left side and <strong>Banner B</strong> appears on the right side of the page on desktop.
                        <br><br>
                        <i class="fas fa-mobile-alt"></i> <strong>Mobile Friendly:</strong> On mobile and tablet devices, banners are displayed at the bottom of the page for better user experience.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-image"></i> Banner A (Left Side / First on Mobile)
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Banner Image</label>
                                        <input type="file" class="form-control" name="setting_folder_banner_a" accept="image/*">
                                        <div class="form-text">Upload image for Banner A (displayed on left side on desktop)</div>
                                    </div>
                                    <?php if (!empty($settings['folder_banner_a'])): ?>
                                        <div class="image-preview mb-3">
                                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($settings['folder_banner_a']); ?>" 
                                                 alt="Banner A" style="max-width: 200px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">
                                            <p class="text-muted small mt-1">Current Banner A</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Banner A Link (Optional)</label>
                                        <input type="url" class="form-control" name="setting_folder_banner_a_link" 
                                               value="<?php echo htmlspecialchars($settings['folder_banner_a_link'] ?? ''); ?>"
                                               placeholder="https://example.com">
                                        <div class="form-text">URL to open when banner is clicked</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Enable Banner A</label>
                                        <select class="form-select" name="setting_folder_banner_a_enabled">
                                            <option value="1" <?php echo ($settings['folder_banner_a_enabled'] ?? '0') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo ($settings['folder_banner_a_enabled'] ?? '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-image"></i> Banner B (Right Side / Second on Mobile)
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Banner Image</label>
                                        <input type="file" class="form-control" name="setting_folder_banner_b" accept="image/*">
                                        <div class="form-text">Upload image for Banner B (displayed on right side on desktop)</div>
                                    </div>
                                    <?php if (!empty($settings['folder_banner_b'])): ?>
                                        <div class="image-preview mb-3">
                                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($settings['folder_banner_b']); ?>" 
                                                 alt="Banner B" style="max-width: 200px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">
                                            <p class="text-muted small mt-1">Current Banner B</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Banner B Link (Optional)</label>
                                        <input type="url" class="form-control" name="setting_folder_banner_b_link" 
                                               value="<?php echo htmlspecialchars($settings['folder_banner_b_link'] ?? ''); ?>"
                                               placeholder="https://example.com">
                                        <div class="form-text">URL to open when banner is clicked</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Enable Banner B</label>
                                        <select class="form-select" name="setting_folder_banner_b_enabled">
                                            <option value="1" <?php echo ($settings['folder_banner_b_enabled'] ?? '0') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo ($settings['folder_banner_b_enabled'] ?? '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-lightbulb"></i> <strong>Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Use high-quality images optimized for web (JPG, PNG, or WebP)</li>
                            <li><strong>Desktop:</strong> Banners appear as sidebars (left & right) - Recommended size: 300×600px (vertical)</li>
                            <li><strong>Mobile/Tablet:</strong> Banners appear at the bottom of the page - horizontal images also work well</li>
                            <li>On tablets, both banners appear side by side at the bottom. On mobile phones, they stack vertically.</li>
                            <li>Leave the link empty if you don't want the banner to be clickable.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            
            <div class="d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> Changes will reflect immediately on the website
                </small>
            </div>
        </form>
    </div>
</div>

<!-- Standalone test-email form (outside the main settings form to avoid nesting) -->
<form method="POST" id="testEmailForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="send_test_email">
    <input type="hidden" name="test_email_address" id="testEmailAddressHidden">
</form>

<script>
// Quick Links Management
function updateQuickLinksData() {
    const links = [];
    document.querySelectorAll('.quick-link-item').forEach((item, index) => {
        const label = item.querySelector('.quick-link-label').value.trim();
        const url = item.querySelector('.quick-link-url').value.trim();
        const orderValue = item.querySelector('.quick-link-order').value;
        const order = orderValue && orderValue.trim() !== '' ? parseInt(orderValue) : (index + 1);
        
        if (label && url) {
            links.push({
                label: label,
                url: url,
                order: order
            });
        }
    });
    
    document.getElementById('quickLinksData').value = JSON.stringify(links);
}

// Add new quick link
document.getElementById('addQuickLink').addEventListener('click', function() {
    const container = document.getElementById('quickLinksContainer');
    const count = container.querySelectorAll('.quick-link-item').length + 1;
    
    const newLink = document.createElement('div');
    newLink.className = 'card mb-3 quick-link-item';
    newLink.innerHTML = `
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label class="form-label">Link Label</label>
                    <input type="text" class="form-control quick-link-label" 
                           placeholder="e.g., Home, About Us">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Link URL</label>
                    <input type="text" class="form-control quick-link-url" 
                           placeholder="e.g., /index.php or https://example.com">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order</label>
                    <input type="number" class="form-control quick-link-order" 
                           value="${count}" min="1">
                </div>
                <div class="col-md-1 text-end">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm remove-link" title="Remove Link">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(newLink);
});

// Remove quick link
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-link') || e.target.closest('.remove-link')) {
        const button = e.target.classList.contains('remove-link') ? e.target : e.target.closest('.remove-link');
        const item = button.closest('.quick-link-item');
        if (confirm('Are you sure you want to remove this quick link?')) {
            item.remove();
            updateQuickLinksData();
        }
    }
});

// Update data before form submission
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    updateQuickLinksData();
});

// Wire "Send Test Email" button to the standalone test-email form
document.getElementById('sendTestEmailBtn').addEventListener('click', function() {
    var emailInput = document.getElementById('testEmailAddress');
    if (!emailInput.value.trim() || !emailInput.validity.valid) {
        emailInput.focus();
        emailInput.reportValidity();
        return;
    }
    document.getElementById('testEmailAddressHidden').value = emailInput.value.trim();
    document.getElementById('testEmailForm').submit();
});

// Auto-activate email tab when navigated to via hash (e.g., from an error link)
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash && /^#[a-zA-Z0-9_-]+$/.test(hash)) {
        const tab = document.querySelector('a[href="' + hash + '"]');
        if (tab) {
            tab.click();
        }
    }
});

// Auto-save warning before leaving page with unsaved changes
let formChanged = false;
let formSubmitting = false;

document.getElementById('settingsForm').addEventListener('change', function(e) {
    // Only track user-initiated changes, not programmatic ones
    if (e.isTrusted) {
        formChanged = true;
    }
});

document.getElementById('settingsForm').addEventListener('submit', function() {
    formChanged = false;
    formSubmitting = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged && !formSubmitting) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
