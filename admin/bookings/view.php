<?php
$page_title = 'View Booking Details';
// Require PHP utilities before any HTML output so redirects work correctly
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$current_user = getCurrentUser();

$db = getDB();
$success_message = '';
$error_message = '';
$new_vendor_wa_url = '';
$new_vendor_email_sent = false;
$is_vendor_flash = false;
$is_packages_flash = false;
$is_admin_services_flash = false;
$is_payout_flash = false;

// Display flash message from previous redirect (e.g., after creating a booking)
$_flash_section = $_SESSION['flash_section'] ?? '';
unset($_SESSION['flash_section']);
if (!empty($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    if ($_flash_section === 'packages') {
        $is_packages_flash = true;
    } elseif ($_flash_section === 'admin_services') {
        $is_admin_services_flash = true;
    } elseif ($_flash_section === 'payout') {
        $is_payout_flash = true;
    } else {
        $is_vendor_flash = true;
    }
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    if ($_flash_section === 'packages') {
        $is_packages_flash = true;
    } elseif ($_flash_section === 'admin_services') {
        $is_admin_services_flash = true;
    } elseif ($_flash_section === 'payout') {
        $is_payout_flash = true;
    } else {
        $is_vendor_flash = true;
    }
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_vendor_wa_url'])) {
    $new_vendor_wa_url = $_SESSION['flash_vendor_wa_url'];
    unset($_SESSION['flash_vendor_wa_url']);
}
if (!empty($_SESSION['flash_vendor_email_sent'])) {
    $new_vendor_email_sent = $_SESSION['flash_vendor_email_sent'];
    unset($_SESSION['flash_vendor_email_sent']);
}

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch booking details (only once)
$booking = getBookingDetails($booking_id);

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Helper variables for consistent status display formatting
$status_vars = calculateBookingStatusVariables($booking);
$booking_status_display = $status_vars['booking_status_display'];
$booking_status_color = $status_vars['booking_status_color'];
$payment_status_display = $status_vars['payment_status_display'];
$payment_status_color = $status_vars['payment_status_color'];
$payment_status_icon = $status_vars['payment_status_icon'];


// Handle payment request actions
$initial_tab = 'tab-overview'; // default active tab
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_admin_service') {
        // Handle adding admin service (manual entry)
        $service_name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $price = max(0, floatval($_POST['price'] ?? 0));

        if (empty($service_name)) {
            $_SESSION['flash_error']   = 'Service name is required.';
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        } elseif ($price <= 0) {
            $_SESSION['flash_error']   = 'Price must be greater than 0.';
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        } else {
            $new_service_id = addAdminService($booking_id, $service_name, $description, $quantity, $price);
            if ($new_service_id) {
                logActivity($current_user['id'], 'Added admin service', 'bookings', $booking_id, "Added service: {$service_name} (Qty: {$quantity}, Price: {$price})");
                $_SESSION['flash_success'] = 'Admin service added successfully!';
            } else {
                $_SESSION['flash_error'] = 'Failed to add admin service. Please check error logs or run fix_admin_services.php to update database schema.';
            }
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        }
    } elseif ($action === 'add_catalog_service') {
        // Handle adding a service selected from the admin-configured services catalog
        $catalog_service_id = intval($_POST['catalog_service_id'] ?? 0);
        $quantity           = max(1, intval($_POST['quantity'] ?? 1));
        $catalog_design_id  = intval($_POST['catalog_design_id'] ?? 0);

        if ($catalog_service_id <= 0) {
            $_SESSION['flash_error']   = 'Please select a service from the catalog.';
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        }

        // Fetch service details from the catalog
        $db_svc = getDB();
        $svc_stmt = $db_svc->prepare("SELECT * FROM additional_services WHERE id = ? AND status = 'active'");
        $svc_stmt->execute([$catalog_service_id]);
        $catalog_svc = $svc_stmt->fetch();

        if (!$catalog_svc) {
            $_SESSION['flash_error']   = 'Selected service not found or is inactive.';
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        }

        // Determine the final price: use the selected design's price if a design was chosen
        $final_price     = floatval($catalog_svc['price']);
        $catalog_design  = null;
        if ($catalog_design_id > 0) {
            $design_stmt = $db_svc->prepare("SELECT id, name, price, photo, description FROM service_designs WHERE id = ? AND service_id = ? AND status = 'active'");
            $design_stmt->execute([$catalog_design_id, $catalog_service_id]);
            $catalog_design = $design_stmt->fetch();
            if ($catalog_design) {
                $final_price = floatval($catalog_design['price']);
            } else {
                $catalog_design_id = 0; // Design not found; ignore
            }
        }

        $new_service_id = addAdminService(
            $booking_id,
            $catalog_svc['name'],
            $catalog_svc['description'] ?? '',
            $quantity,
            $final_price,
            $catalog_design_id,
            $catalog_service_id
        );

        if ($new_service_id) {
            $log_detail = "Added catalog service: {$catalog_svc['name']} (Qty: {$quantity}, Price: {$final_price})" . ($catalog_design ? ", Design: {$catalog_design['name']}" : '');
            logActivity($current_user['id'], 'Added catalog service', 'bookings', $booking_id, $log_detail);
            // If admin also selected a vendor for this service, create the assignment linked to the service row
            $catalog_vendor_id = intval($_POST['catalog_vendor_id'] ?? 0);
            if ($catalog_vendor_id > 0) {
                $va_id = addVendorAssignment($booking_id, $catalog_vendor_id, $catalog_svc['name'], 0, '', $new_service_id);
                if ($va_id) {
                    logActivity($current_user['id'], 'Auto-assigned vendor for catalog service', 'booking_vendor_assignments', $booking_id, "Vendor ID {$catalog_vendor_id} assigned for: {$catalog_svc['name']}");
                    $_SESSION['flash_success'] = 'Service added and vendor assigned successfully!';
                } else {
                    $_SESSION['flash_success'] = 'Service added from catalog successfully! (Vendor assignment failed.)';
                }
            } else {
                $_SESSION['flash_success'] = 'Service added from catalog successfully!';
            }
        } else {
            $_SESSION['flash_error'] = 'Failed to add service from catalog. Please try again.';
        }
        $_SESSION['flash_section'] = 'admin_services';
        header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
        exit;
    } elseif ($action === 'delete_admin_service') {
        // Handle deleting admin service (also handles package services since they share added_by='admin')
        $service_id    = intval($_POST['service_id'] ?? 0);
        $from_packages = ($_POST['from_packages'] ?? '') === '1';

        if ($service_id > 0) {
            if (deleteAdminService($service_id)) {
                logActivity($current_user['id'], 'Deleted admin service', 'bookings', $booking_id, "Deleted admin service ID: {$service_id}");
                if ($from_packages) {
                    $_SESSION['flash_success'] = 'Package removed successfully!';
                    $_SESSION['flash_section'] = 'packages';
                    header('Location: view.php?id=' . urlencode($booking_id) . '#packages');
                    exit;
                } else {
                    $_SESSION['flash_success'] = 'Service removed successfully!';
                    $_SESSION['flash_section'] = 'admin_services';
                    header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
                    exit;
                }
            } else {
                $_SESSION['flash_error']   = 'Failed to delete service. Please try again.';
                $_SESSION['flash_section'] = 'admin_services';
                header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
                exit;
            }
        } else {
            $_SESSION['flash_error']   = 'Invalid service ID.';
            $_SESSION['flash_section'] = 'admin_services';
            header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
            exit;
        }
    } elseif ($action === 'add_package') {
        // Handle adding a predefined service package to the booking
        $package_id = intval($_POST['package_id'] ?? 0);
        $quantity   = max(1, intval($_POST['quantity'] ?? 1));

        if ($package_id <= 0) {
            $_SESSION['flash_error']   = 'Please select a package.';
            $_SESSION['flash_section'] = 'packages';
            header('Location: view.php?id=' . urlencode($booking_id) . '#packages');
            exit;
        } else {
            $service_id = addPackageToBooking($booking_id, $package_id, $quantity);
            if ($service_id) {
                $db_log  = getDB();
                $pkg_log = $db_log->prepare("SELECT name FROM service_packages WHERE id = ?");
                $pkg_log->execute([$package_id]);
                $pkg_row  = $pkg_log->fetch();
                $pkg_name = $pkg_row ? $pkg_row['name'] : "Package #{$package_id}";
                logActivity($current_user['id'], 'Added package to booking', 'bookings', $booking_id, "Added package: {$pkg_name} (Qty: {$quantity})");
                $_SESSION['flash_success'] = 'Package added to booking successfully!';
                $_SESSION['flash_section'] = 'packages';
                header('Location: view.php?id=' . urlencode($booking_id) . '#packages');
                exit;
            } else {
                $_SESSION['flash_error']   = 'Failed to add package. Please try again.';
                $_SESSION['flash_section'] = 'packages';
                header('Location: view.php?id=' . urlencode($booking_id) . '#packages');
                exit;
            }
        }
    } elseif ($action === 'send_payment_request_email') {
        // Send payment request via email
        if (!empty($booking['email'])) {
            $result = sendBookingNotification($booking_id, 'payment_request');
            if ($result['user']) {
                $success_message = 'Payment request sent successfully via email to ' . htmlspecialchars($booking['email']);
                logActivity($current_user['id'], 'Sent payment request via email', 'bookings', $booking_id, "Payment request email sent for booking: {$booking['booking_number']}");
            } else {
                $error_message = 'Failed to send payment request email. Please <a href="' . BASE_URL . '/admin/settings/index.php#email" class="alert-link">check email settings</a>.';
            }
        } else {
            $error_message = 'Customer email not found. Cannot send email.';
        }
    } elseif ($action === 'send_payment_request_whatsapp') {
        // Send payment request via WhatsApp
        if (!empty($booking['phone'])) {
            $success_message = 'Opening WhatsApp to send payment request...';
            logActivity($current_user['id'], 'Initiated WhatsApp payment request', 'bookings', $booking_id, "WhatsApp payment request initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Customer phone number not found. Cannot send WhatsApp message.';
        }
    } elseif ($action === 'send_booking_confirmation_whatsapp') {
        // Send booking confirmation via WhatsApp (after advance payment received)
        if (!empty($booking['phone'])) {
            $success_message = 'Opening WhatsApp to send booking confirmation...';
            logActivity($current_user['id'], 'Initiated WhatsApp booking confirmation', 'bookings', $booking_id, "WhatsApp booking confirmation initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Customer phone number not found. Cannot send WhatsApp message.';
        }
    } elseif ($action === 'send_venue_provider_whatsapp') {
        // Notify venue provider about confirmed booking via WhatsApp
        if (!empty($booking['venue_contact_phone'])) {
            $success_message = 'Opening WhatsApp to notify venue provider...';
            logActivity($current_user['id'], 'Initiated WhatsApp venue provider notification', 'bookings', $booking_id, "WhatsApp venue provider notification initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Venue contact phone number not found. Please add a contact phone to the venue.';
        }
    } elseif ($action === 'send_thankyou_whatsapp') {
        // Send thank you message with Google review link via WhatsApp (after payment is fully paid)
        if (!empty($booking['phone'])) {
            $success_message = 'Opening WhatsApp to send thank you message...';
            logActivity($current_user['id'], 'Initiated WhatsApp thank you message', 'bookings', $booking_id, "WhatsApp thank you message initiated for booking: {$booking['booking_number']}");
        } else {
            $error_message = 'Customer phone number not found. Cannot send WhatsApp message.';
        }
    } elseif ($action === 'send_booking_confirmation_email') {
        // Send booking confirmation via email (after advance payment received)
        if (!empty($booking['email'])) {
            $result = sendBookingNotification($booking_id, 'confirmed');
            if ($result['user']) {
                $success_message = 'Booking confirmation sent successfully via email to ' . htmlspecialchars($booking['email']);
                logActivity($current_user['id'], 'Sent booking confirmation via email', 'bookings', $booking_id, "Booking confirmation email sent for booking: {$booking['booking_number']}");
            } else {
                $error_message = 'Failed to send booking confirmation email. Please <a href="' . BASE_URL . '/admin/settings/index.php#email" class="alert-link">check email settings</a>.';
            }
        } else {
            $error_message = 'Customer email not found. Cannot send email.';
        }
    } elseif ($action === 'add_vendor_assignment') {
        $vendor_id_input      = intval($_POST['vendor_id'] ?? 0);
        $task_description     = trim($_POST['task_description'] ?? '');
        $assignment_notes     = trim($_POST['assignment_notes'] ?? '');
        $booking_service_id   = intval($_POST['booking_service_id'] ?? 0);
        $is_manual            = !empty($_POST['is_manual_vendor']);
        $manual_vendor_name   = trim($_POST['manual_vendor_name'] ?? '');
        $manual_vendor_phone  = trim($_POST['manual_vendor_phone'] ?? '');
        $manual_vendor_type   = trim($_POST['manual_vendor_type'] ?? '');

        if ($is_manual) {
            // Manual vendor: name is required; vendor_id is not used
            if ($manual_vendor_name === '') {
                $_SESSION['flash_error'] = 'Please enter the vendor name.';
            } else {
                $assignment_id = addVendorAssignment($booking_id, null, $task_description, 0 /* amount handled separately */, $assignment_notes, $booking_service_id > 0 ? $booking_service_id : null, $manual_vendor_name, $manual_vendor_phone, $manual_vendor_type);
                if ($assignment_id) {
                    logActivity($current_user['id'], 'Added manual vendor assignment', 'booking_vendor_assignments', $booking_id, "Manual vendor \"{$manual_vendor_name}\": {$task_description}");
                    $_SESSION['flash_success'] = 'Manual vendor assigned successfully!';
                    if (!empty($manual_vendor_phone)) {
                        $_flash_design_info = null;
                        if ($booking_service_id > 0) {
                            try {
                                $_fds = getDB()->prepare("SELECT sd.name, sd.photo FROM booking_services bs JOIN service_designs sd ON bs.design_id = sd.id WHERE bs.id = ? AND bs.design_id > 0");
                                $_fds->execute([$booking_service_id]);
                                $_fdr = $_fds->fetch();
                                if ($_fdr && !empty($_fdr['photo'])) {
                                    $_flash_design_info = ['name' => $_fdr['name'] ?? '', 'photo' => rtrim(UPLOAD_URL, '/') . '/' . $_fdr['photo']];
                                }
                                unset($_fds, $_fdr);
                            } catch (Exception $e) { /* non-fatal */ }
                        }
                        $_SESSION['flash_vendor_wa_url'] = buildVendorAssignmentWhatsAppUrl($manual_vendor_name, $manual_vendor_phone, $booking, $manual_vendor_type, $_flash_design_info);
                        unset($_flash_design_info);
                    }
                } else {
                    $_SESSION['flash_error'] = 'Failed to add vendor assignment. Please try again.';
                }
            }
        } elseif ($vendor_id_input <= 0) {
            $_SESSION['flash_error'] = 'Please select a vendor.';
        } else {
            // Duplicate check: same vendor already assigned to this service?
            $is_duplicate = false;
            if ($booking_service_id > 0) {
                $chk = getDB()->prepare("SELECT COUNT(*) FROM booking_vendor_assignments WHERE booking_id = ? AND booking_service_id = ? AND vendor_id = ?");
                $chk->execute([$booking_id, $booking_service_id, $vendor_id_input]);
                $is_duplicate = (int)$chk->fetchColumn() > 0;
            }
            if ($is_duplicate) {
                $_SESSION['flash_error'] = 'This vendor is already assigned to this service.';
            } else {
                $assignment_id = addVendorAssignment($booking_id, $vendor_id_input, $task_description, 0 /* amount handled separately */, $assignment_notes, $booking_service_id > 0 ? $booking_service_id : null);
                if ($assignment_id) {
                    logActivity($current_user['id'], 'Added vendor assignment', 'booking_vendor_assignments', $booking_id, "Assigned vendor ID {$vendor_id_input}: {$task_description}");
                    $_SESSION['flash_success'] = 'Vendor assigned successfully!';
                    $new_vendor = getVendor($vendor_id_input);
                    if ($new_vendor && !empty($new_vendor['phone'])) {
                        $_flash_design_info = null;
                        if ($booking_service_id > 0) {
                            try {
                                $_fds = getDB()->prepare("SELECT sd.name, sd.photo FROM booking_services bs JOIN service_designs sd ON bs.design_id = sd.id WHERE bs.id = ? AND bs.design_id > 0");
                                $_fds->execute([$booking_service_id]);
                                $_fdr = $_fds->fetch();
                                if ($_fdr && !empty($_fdr['photo'])) {
                                    $_flash_design_info = ['name' => $_fdr['name'] ?? '', 'photo' => rtrim(UPLOAD_URL, '/') . '/' . $_fdr['photo']];
                                }
                                unset($_fds, $_fdr);
                            } catch (Exception $e) { /* non-fatal */ }
                        }
                        $_SESSION['flash_vendor_wa_url'] = buildVendorAssignmentWhatsAppUrl($new_vendor['name'], $new_vendor['phone'], $booking, $new_vendor['type'] ?? '', $_flash_design_info);
                        unset($_flash_design_info);
                    }
                    if ($new_vendor && !empty($new_vendor['email'])) {
                        $_SESSION['flash_vendor_email_sent'] = sendVendorAssignmentEmail($new_vendor['name'], $new_vendor['email'], $booking);
                    }
                } else {
                    $_SESSION['flash_error'] = 'Failed to add vendor assignment. Please try again.';
                }
            }
        }
        $_SESSION['flash_section'] = 'admin_services';
        header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
        exit;
    } elseif ($action === 'update_vendor_assignment_status') {
        $assignment_id     = intval($_POST['assignment_id'] ?? 0);
        $assignment_status = trim($_POST['assignment_status'] ?? '');

        if ($assignment_id > 0 && updateVendorAssignmentStatus($assignment_id, $assignment_status)) {
            logActivity($current_user['id'], 'Updated vendor assignment status', 'booking_vendor_assignments', $booking_id, "Assignment {$assignment_id} status set to {$assignment_status}");
            $_SESSION['flash_success'] = 'Vendor assignment status updated.';
        } else {
            $_SESSION['flash_error'] = 'Failed to update vendor assignment status.';
        }
        $_SESSION['flash_section'] = 'admin_services';
        header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
        exit;
    } elseif ($action === 'delete_vendor_assignment') {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);

        if ($assignment_id > 0 && deleteVendorAssignment($assignment_id)) {
            logActivity($current_user['id'], 'Deleted vendor assignment', 'booking_vendor_assignments', $booking_id, "Deleted assignment ID {$assignment_id}");
            $_SESSION['flash_success'] = 'Vendor assignment removed.';
        } else {
            $_SESSION['flash_error'] = 'Failed to remove vendor assignment.';
        }
        $_SESSION['flash_section'] = 'admin_services';
        header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
        exit;
    } elseif ($action === 'send_all_vendor_emails') {
        $va_all      = getBookingVendorAssignments($booking_id);
        $sent_count  = 0;
        $fail_count  = 0;
        foreach ($va_all as $_va) {
            if (!empty($_va['vendor_email'])) {
                if (sendVendorAssignmentEmail($_va['vendor_name'], $_va['vendor_email'], $booking)) {
                    $sent_count++;
                } else {
                    $fail_count++;
                }
            }
        }
        if ($sent_count > 0) {
            $_SESSION['flash_success'] = "Emails sent to {$sent_count} vendor(s) successfully!" . ($fail_count > 0 ? " ({$fail_count} failed)" : '');
        } elseif ($fail_count > 0) {
            $_SESSION['flash_error'] = "Failed to send emails to {$fail_count} vendor(s).";
        } else {
            $_SESSION['flash_error'] = 'No vendor emails found to send.';
        }
        logActivity($current_user['id'], 'Sent all vendor emails', 'bookings', $booking_id, "Sent to {$sent_count} vendor(s)");
        $_SESSION['flash_section'] = 'admin_services';
        header('Location: view.php?id=' . urlencode($booking_id) . '#section-services');
        exit;
    }
}

// Include the HTML header only after all PHP processing (and potential redirects)
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success_message && !$is_vendor_flash && !$is_packages_flash && !$is_admin_services_flash && !$is_payout_flash): ?>
    <div class="alert alert-success alert-dismissible fade show" id="flash-success-alert" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message && !$is_vendor_flash && !$is_packages_flash && !$is_admin_services_flash && !$is_payout_flash): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Print-Only Invoice Layout -->
<?php
// Calculate payment details using centralized function (single source of truth)
$payment_summary = calculatePaymentSummary($booking_id);
$total_paid = $payment_summary['total_paid'];
$balance_due = $payment_summary['due_amount'];
$advance = [
    'amount' => $payment_summary['advance_amount'],
    'percentage' => $payment_summary['advance_percentage']
];
// Actual advance amount received (manually entered by admin; 0 means not yet set)
$advance_amount_received = $payment_summary['advance_amount_received'];

// Calculate vendors total for display in the payment breakdown
$vendors_total      = $payment_summary['vendors_total'];
$vendors_paid_total = $payment_summary['vendors_paid_total'];
$vendors_due        = $payment_summary['vendors_due'];

// Venue provider payable = hall price + menu total
$venue_provider_payable = $payment_summary['venue_provider_payable'];
$venue_amount_paid_out  = $payment_summary['venue_amount_paid'];
$venue_due              = $payment_summary['venue_due'];

// Get vendor assignments for print invoice and display
$vendor_assignments = getBookingVendorAssignments($booking_id);

// Get available vendors for the assignment form (used in Quick Check panel)
$all_vendors = getAvailableVendors($booking['event_date'], $booking_id);

// Batch-fetch primary photo URLs for all relevant vendors (avoids N+1 queries)
$_vendor_photo_ids = array_unique(array_merge(
    array_column($vendor_assignments, 'vendor_id'),
    array_column($all_vendors, 'id')
));
$vendor_primary_photos = getVendorPrimaryPhotoUrls($_vendor_photo_ids);
unset($_vendor_photo_ids);

// Group vendor assignments by booking_service_id for inline display within service rows.
// Assignments with no booking_service_id (legacy or unlinked) go into the NULL bucket.
$vendor_assignments_by_service = [];
$vendor_assignments_unlinked   = [];
foreach ($vendor_assignments as $va) {
    $bsid = isset($va['booking_service_id']) ? (int)$va['booking_service_id'] : 0;
    if ($bsid > 0) {
        $vendor_assignments_by_service[$bsid][] = $va;
    } else {
        $vendor_assignments_unlinked[] = $va;
    }
}

// Get payment transactions for display
$payment_transactions = getBookingPayments($booking_id);

// Company details from settings - use company-specific or fallback to general
// Note: getSetting() caches results, but we check primary first to avoid unnecessary fallback queries
$company_name = getSetting('company_name');
if (empty($company_name)) {
    $company_name = getSetting('site_name', 'Wedding Venue Booking');
}

$company_address = getSetting('company_address');
if (empty($company_address)) {
    $company_address = getSetting('contact_address', 'Nepal');
}

$company_phone = getSetting('company_phone');
if (empty($company_phone)) {
    $company_phone = getSetting('contact_phone', 'N/A');
}

$company_email = getSetting('company_email');
if (empty($company_email)) {
    $company_email = getSetting('contact_email', '');
}

$company_logo = getCompanyLogo(); // Returns validated logo info or null

// Get payment mode from latest transaction
$payment_mode = 'Not specified';
if (!empty($payment_transactions)) {
    $latest_payment = $payment_transactions[0];
    $payment_mode = !empty($latest_payment['payment_method_name']) ? $latest_payment['payment_method_name'] : 'Not specified';
}

// Get invoice content from settings
$invoice_title = getSetting('invoice_title', 'Wedding Booking Confirmation & Partial Payment Receipt');
$cancellation_policy = getSetting('cancellation_policy', 'Advance payment is non-refundable in case of cancellation.
Full payment must be completed 7 days before the event date.
Cancellations made 30 days before the event will receive 50% refund of total amount (excluding advance).
Cancellations made less than 30 days before the event are non-refundable.
Date changes are subject to availability and must be requested at least 15 days in advance.');
$invoice_disclaimer = getSetting('invoice_disclaimer', 'Note: This is a computer-generated estimate bill. Please create a complete invoice yourself.');
$package_label = getSetting('invoice_package_label', 'Marriage Package');
$additional_items_label = getSetting('invoice_additional_items_label', 'Additional Items');
$currency = getSetting('currency', 'NPR');

// Separate user, admin, and package services for display in print invoice and screen view
// Note: Package services (added by admin OR user) have category=PACKAGE_SERVICE_CATEGORY
$user_services    = [];
$admin_services   = [];
$package_services = [];
if (!empty($booking['services']) && is_array($booking['services'])) {
    foreach ($booking['services'] as $service) {
        if (($service['category'] ?? '') === PACKAGE_SERVICE_CATEGORY) {
            // All package-category services (user- or admin-added) go to packages section
            $package_services[] = $service;
        } elseif (($service['category'] ?? '') === PACKAGE_INCLUDED_CATEGORY) {
            // Services auto-included from package features go to packages section
            // so they are displayed alongside the package they belong to.
            $package_services[] = $service;
        } elseif (isset($service['added_by']) && $service['added_by'] === 'admin') {
            $admin_services[] = $service;
        } else {
            $user_services[] = $service;
        }
    }
}
$admin_services_total = 0;
foreach ($admin_services as $_svc) {
    $admin_services_total += floatval($_svc['price'] ?? 0) * intval($_svc['quantity'] ?? 1);
}
$package_services_total = 0;
foreach ($package_services as $_pkg) {
    $package_services_total += floatval($_pkg['price'] ?? 0) * intval($_pkg['quantity'] ?? 1);
}

// Batch-fetch primary photo URLs for user services, admin services, and package-included services
// from additional_services.photo
$_pkg_included_services = array_filter($package_services, fn($s) => ($s['category'] ?? '') === PACKAGE_INCLUDED_CATEGORY);
$_svc_ids_for_photos = array_filter(array_unique(array_merge(
    array_column($user_services, 'service_id'),
    array_column($admin_services, 'service_id'),
    array_column($_pkg_included_services, 'service_id')
)), fn($id) => intval($id) > 0);
$service_primary_photos = getServicePrimaryPhotoUrls(array_values($_svc_ids_for_photos));
unset($_svc_ids_for_photos, $_pkg_included_services);

// Batch-fetch primary photo URLs for (non-included) package services from service_package_photos
$_pkg_ids_for_photos = array_filter(array_unique(array_map(
    fn($s) => ($s['category'] ?? '') !== PACKAGE_INCLUDED_CATEGORY ? $s['service_id'] : 0,
    $package_services
)), fn($id) => intval($id) > 0);
$package_primary_photos = getPackagePrimaryPhotoUrls(array_values($_pkg_ids_for_photos));
unset($_pkg_ids_for_photos);

// Batch-fetch design info (name + photo) for services that have a selected design
$service_design_info = []; // keyed by design_id → ['name' => ..., 'photo' => ...]
$_all_services_for_design = array_merge($user_services, $admin_services);
$_design_ids = array_filter(array_unique(array_column($_all_services_for_design, 'design_id')), fn($id) => intval($id) > 0);
if (!empty($_design_ids)) {
    try {
        $_db_conn = getDB();
        $_ph_d = implode(',', array_fill(0, count($_design_ids), '?'));
        $_design_stmt = $_db_conn->prepare("SELECT id, name, photo FROM service_designs WHERE id IN ($_ph_d)");
        $_design_stmt->execute(array_values(array_map('intval', $_design_ids)));
        foreach ($_design_stmt->fetchAll() as $_design_row) {
            $service_design_info[(int)$_design_row['id']] = [
                'name'  => $_design_row['name'] ?? '',
                'photo' => !empty($_design_row['photo']) ? (rtrim(UPLOAD_URL, '/') . '/' . $_design_row['photo']) : '',
            ];
        }
    } catch (Exception $e) {
        // Non-fatal; design photo will simply not display
    }
}
unset($_all_services_for_design, $_design_ids, $_db_conn, $_ph_d, $_design_stmt, $_design_row);

// Build map: booking_service_id → design_info (for passing to vendor WhatsApp messages)
$booking_svc_design_map = [];
foreach (array_merge($user_services, $admin_services, $package_services) as $_svc) {
    $_bsvc_id = (int)($_svc['id'] ?? 0);
    $_dsvc_id = (int)($_svc['design_id'] ?? 0);
    if ($_bsvc_id > 0 && $_dsvc_id > 0 && isset($service_design_info[$_dsvc_id])) {
        $booking_svc_design_map[$_bsvc_id] = $service_design_info[$_dsvc_id];
    }
}
unset($_svc, $_bsvc_id, $_dsvc_id);

// Batch-fetch vendor_type_slug for user services (join additional_services → vendor_types at once)
$service_vendor_type_slugs = []; // keyed by booking_services.id → vendor_type_slug
if (!empty($user_services) || !empty($admin_services)) {
    $_all_svc_rows = array_merge($user_services, $admin_services);
    $_svc_ids_for_vt = array_filter(array_unique(array_column($_all_svc_rows, 'service_id')), fn($id) => intval($id) > 0);
    if (!empty($_svc_ids_for_vt)) {
        try {
            $_db_tmp = getDB();
            $_ph = implode(',', array_fill(0, count($_svc_ids_for_vt), '?'));
            $_vt_stmt = $_db_tmp->prepare("SELECT s.id, vt.slug FROM additional_services s LEFT JOIN vendor_types vt ON s.vendor_type_id = vt.id WHERE s.id IN ($_ph)");
            $_vt_stmt->execute(array_values(array_map('intval', $_svc_ids_for_vt)));
            $_vt_rows = $_vt_stmt->fetchAll();
            // Build service_id → vendor_type_slug map, then map booking_service row id → slug
            $_vt_map = [];
            foreach ($_vt_rows as $_vtr) {
                $_vt_map[(int)$_vtr['id']] = $_vtr['slug'] ?? '';
            }
            foreach ($_all_svc_rows as $_srow) {
                $service_vendor_type_slugs[(int)$_srow['id']] = $_vt_map[(int)($_srow['service_id'] ?? 0)] ?? '';
            }
        } catch (Exception $e) {
            // Non-fatal; modal will fall back to category-based matching
        }
    }
    unset($_all_svc_rows, $_svc_ids_for_vt, $_vt_stmt, $_vt_rows, $_vt_map, $_srow, $_vtr, $_ph, $_db_tmp);
}

// Compute "Send All" combo messaging data.
// A service "requires a vendor" if its catalog entry has a vendor_type_id (non-empty slug).
$_combo_svc_ids_needing_vendor = [];
foreach (array_merge($user_services, $admin_services) as $_svc) {
    $_sid = (int)$_svc['id'];
    if (!empty($service_vendor_type_slugs[$_sid])) {
        $_combo_svc_ids_needing_vendor[] = $_sid;
    }
}
// All-assigned: there is at least one vendor assignment AND every vendor-requiring service
// has at least one assignment linked to it.
$combo_all_assigned = !empty($vendor_assignments);
if ($combo_all_assigned && !empty($_combo_svc_ids_needing_vendor)) {
    foreach ($_combo_svc_ids_needing_vendor as $_sid) {
        if (empty($vendor_assignments_by_service[$_sid])) {
            $combo_all_assigned = false;
            break;
        }
    }
}
// Build combo WhatsApp URL list and email list (all assigned vendors).
$combo_wa_urls    = [];
$combo_email_list = [];
foreach ($vendor_assignments as $_va) {
    if (!empty($_va['vendor_phone'])) {
        $_va_bsid   = (int)($_va['booking_service_id'] ?? 0);
        $_va_design = $booking_svc_design_map[$_va_bsid] ?? null;
        $_wa = buildVendorAssignmentWhatsAppUrl($_va['vendor_name'], $_va['vendor_phone'], $booking, $_va['vendor_type'] ?? '', $_va_design);
        if (!empty($_wa)) {
            $combo_wa_urls[] = $_wa;
        }
    }
    if (!empty($_va['vendor_email'])) {
        $combo_email_list[] = ['name' => $_va['vendor_name'], 'email' => $_va['vendor_email']];
    }
}
unset($_combo_svc_ids_needing_vendor, $_svc, $_sid, $_va, $_wa, $_va_bsid, $_va_design);

// Resolve display time – prefer saved start/end times; fall back to shift defaults so that
// the booking time is always visible in both the screen view and the print invoice.
$shift_default_times  = getShiftDefaultTimes($booking['shift']);
$display_start_time   = !empty($booking['start_time']) ? $booking['start_time'] : $shift_default_times['start'];
$display_end_time     = !empty($booking['end_time'])   ? $booking['end_time']   : $shift_default_times['end'];
$has_display_time     = !empty($display_start_time) && !empty($display_end_time);
?>

<div class="print-invoice-only" style="display: none;">
    <div class="invoice-container">
        <!-- Decorative top stripe -->
        <div class="invoice-top-stripe"></div>
        <!-- Header Section -->
        <div class="invoice-header">
            <div class="header-content">
                <div class="company-info">
                    <h1 class="company-name"><?php echo htmlspecialchars($company_name); ?></h1>
                    <p class="company-details">
                        <?php echo htmlspecialchars($company_address); ?><br>
                        Phone: <?php echo htmlspecialchars($company_phone); ?>
                        <?php if ($company_email): ?>
                            <br>Email: <?php echo htmlspecialchars($company_email); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="company-logo-space">
                    <?php if ($company_logo !== null): ?>
                        <img src="<?php echo $company_logo['url']; ?>" 
                             alt="<?php echo htmlspecialchars($company_name); ?>" 
                             class="company-logo-img">
                    <?php else: ?>
                        <div class="logo-placeholder"><?php echo htmlspecialchars($company_name); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="invoice-title">
                <h2><?php echo nl2br(htmlspecialchars($invoice_title)); ?></h2>
            </div>
        </div>

        <!-- Invoice Details Bar -->
        <div class="invoice-details-bar">
            <div class="invoice-detail-item">
                <strong>Invoice Date:</strong> <?php echo date('F d, Y', strtotime($booking['created_at'])); ?>
                <small class="text-muted">(<?php echo convertToNepaliDate($booking['created_at']); ?>)</small>
            </div>
            <div class="invoice-detail-item">
                <strong>Booking Date:</strong> <?php echo date('F d, Y', strtotime($booking['event_date'])); ?>
                <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small>
            </div>
            <div class="invoice-detail-item">
                <strong>Booking No:</strong> <?php echo htmlspecialchars($booking['booking_number']); ?>
            </div>
        </div>

        <!-- Customer Details Section -->
        <div class="customer-section">
            <h3>Customer Details</h3>
            <div class="customer-info-grid">
                <div class="info-row">
                    <span class="info-label">Booked By:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mobile Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                </div>
                <?php if ($booking['email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Event Type:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Event Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?> <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small> — <?php echo ucfirst($booking['shift']); ?></span>
                </div>
                <?php if ($has_display_time): ?>
                <div class="info-row">
                    <span class="info-label">Event Time:</span>
                    <span class="info-value">
                        <?php echo formatBookingTime($display_start_time); ?> – <?php echo formatBookingTime($display_end_time); ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Venue:</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars($booking['venue_name']); ?> - <?php echo htmlspecialchars($booking['hall_name']); ?>
                        <?php if (empty($booking['hall_id'])): ?>
                            <span class="badge bg-info ms-1"><i class="fas fa-map-marker-alt"></i> Customer's Own Venue</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Number of Guests:</span>
                    <span class="info-value"><?php echo $booking['number_of_guests']; ?></span>
                </div>
            </div>
        </div>

        <!-- Booking Details Table -->
        <div class="booking-table-section">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Amount (<?php echo htmlspecialchars($currency); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Hall/Venue -->
                    <tr>
                        <td><strong><?php echo htmlspecialchars($package_label); ?></strong> - <?php echo htmlspecialchars($booking['hall_name']); ?></td>
                        <td class="text-center">1</td>
                        <td class="text-right"><?php echo number_format($booking['hall_price'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($booking['hall_price'], 2); ?></td>
                    </tr>
                    
                    <!-- Menus -->
                    <?php if (!empty($booking['menus'])): ?>
                        <?php foreach ($booking['menus'] as $menu): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($menu['menu_name']); ?>
                                <?php if (!empty($menu['items'])): ?>
                                    <?php
                                    $items_by_cat = [];
                                    foreach ($menu['items'] as $item) {
                                        $cat = !empty($item['category']) ? htmlspecialchars($item['category']) : '';
                                        $items_by_cat[$cat][] = htmlspecialchars($item['item_name']);
                                    }
                                    $cat_parts = [];
                                    foreach ($items_by_cat as $cat => $names) {
                                        $part = ($cat !== '' ? '<strong>' . $cat . ':</strong> ' : '') . implode(', ', $names);
                                        $cat_parts[] = $part;
                                    }
                                    ?>
                                    <br><span class="menu-items-print"><?php echo implode(' | ', $cat_parts); ?></span>
                                <?php endif; ?>
                                <?php
                                $_print_sel = $booking['menu_item_selections'][$menu['menu_id']] ?? null;
                                if (!empty($_print_sel)):
                                    $_sel_parts = [];
                                    foreach ($_print_sel['sections'] as $_sec => $_grps) {
                                        foreach ($_grps as $_grp => $_its) {
                                            $_inames = array_map(function($i){ return htmlspecialchars($i['item_name']); }, $_its);
                                            $_sel_parts[] = '<strong>' . htmlspecialchars($_grp) . ':</strong> ' . implode(', ', $_inames);
                                        }
                                    }
                                    if (!empty($_sel_parts)):
                                ?>
                                    <br><span class="menu-items-print"><em>Selected:</em> <?php echo implode(' | ', $_sel_parts); ?></span>
                                <?php endif; endif; ?>
                            </td>
                            <td class="text-center"><?php echo $menu['number_of_guests']; ?></td>
                            <td class="text-right"><?php echo number_format($menu['price_per_person'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($menu['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- User Services / Additional Items -->
                    <?php if (!empty($user_services)): ?>
                        <?php foreach ($user_services as $service): ?>
                        <?php 
                            $service_price = floatval($service['price'] ?? 0);
                            $service_qty = intval($service['quantity'] ?? 1);
                            $service_total = $service_price * $service_qty;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($additional_items_label); ?></strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                <?php if (!empty($service['category'])): ?>
                                    <span class="service-category-print">[<?php echo htmlspecialchars($service['category']); ?>]</span>
                                <?php endif; ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $service_qty; ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo number_format($service_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Admin Added Services -->
                    <?php if (!empty($admin_services)): ?>
                        <?php foreach ($admin_services as $service): ?>
                        <?php 
                            $service_price = floatval($service['price'] ?? 0);
                            $service_qty = intval($service['quantity'] ?? 1);
                            $service_total = $service_price * $service_qty;
                        ?>
                        <tr>
                            <td>
                                <strong>Admin Service</strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $service_qty; ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo number_format($service_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Added Packages -->
                    <?php if (!empty($package_services)): ?>
                        <?php foreach ($package_services as $service): ?>
                        <?php
                            $service_price      = floatval($service['price'] ?? 0);
                            $service_qty        = intval($service['quantity'] ?? 1);
                            $service_total      = $service_price * $service_qty;
                            $svc_is_pkg_incl    = ($service['category'] ?? '') === PACKAGE_INCLUDED_CATEGORY;
                        ?>
                        <tr>
                            <td>
                                <?php if ($svc_is_pkg_incl): ?>
                                    <i class="fas fa-long-arrow-alt-right" style="color:#888;font-size:.85em;" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Service')); ?>
                                    <span style="font-size:.85em;color:#888;">(Included in package)</span>
                                <?php else: ?>
                                    <strong>Package</strong> - <?php echo htmlspecialchars(getValueOrDefault($service['service_name'], 'Package')); ?>
                                <?php endif; ?>
                                <?php if (!empty($service['description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($service['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $svc_is_pkg_incl ? '—' : $service_qty; ?></td>
                            <td class="text-right"><?php echo $svc_is_pkg_incl ? 'Incl.' : number_format($service_price, 2); ?></td>
                            <td class="text-right"><?php echo $svc_is_pkg_incl ? '—' : number_format($service_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($user_services) && empty($admin_services) && empty($package_services)): ?>
                        <tr class="no-services-row">
                            <td colspan="4" class="text-center text-muted"><em>No additional services selected</em></td>
                        </tr>
                    <?php endif; ?>
                    
                    <!-- Vendor Assignments (included in subtotal) -->
                    <?php
                    $invoice_vendors_total = 0;
                    $active_vendor_assignments = [];
                    if (!empty($vendor_assignments)) {
                        foreach ($vendor_assignments as $va) {
                            if (floatval($va['assigned_amount']) > 0 && $va['status'] !== 'cancelled') {
                                $active_vendor_assignments[] = $va;
                                $invoice_vendors_total += floatval($va['assigned_amount']);
                            }
                        }
                    }
                    $has_invoice_vendors = !empty($active_vendor_assignments);
                    ?>
                    <?php if ($has_invoice_vendors): ?>
                        <?php foreach ($active_vendor_assignments as $va): ?>
                        <tr>
                            <td>
                                <strong>Vendors</strong> - <?php echo htmlspecialchars(getVendorTypeLabel($va['vendor_type'] ?? '')); ?>
                                <?php if (!empty($va['task_description'])): ?>
                                    <br><span class="service-description-print"><?php echo htmlspecialchars($va['task_description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-right"><?php echo number_format(floatval($va['assigned_amount']), 2); ?></td>
                            <td class="text-right"><?php echo number_format(floatval($va['assigned_amount']), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Subtotal (hall + menus + services + vendors) -->
                    <tr class="subtotal-row">
                        <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($booking['subtotal'], 2); ?></strong></td>
                    </tr>
                    
                    <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                    <!-- Tax -->
                    <tr>
                        <td colspan="3" class="text-right">Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</td>
                        <td class="text-right"><?php echo number_format($booking['tax_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Grand Total -->
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>GRAND TOTAL:</strong></td>
                        <td class="text-right"><strong><?php echo number_format($booking['grand_total'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment Calculation Section -->
        <div class="payment-calculation-section">
            <table class="payment-table">
                <tr>
                    <td class="payment-label">Venue Provider Payable (Hall + Menu):</td>
                    <td class="payment-value"><?php echo formatCurrency($venue_provider_payable); ?></td>
                </tr>
                <?php if ($has_invoice_vendors): ?>
                <tr>
                    <td class="payment-label">Vendors Total:</td>
                    <td class="payment-value"><?php echo formatCurrency($invoice_vendors_total); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="payment-label">Advance Payment Required (<?php echo $advance['percentage']; ?>%):</td>
                    <td class="payment-value"><?php echo formatCurrency($advance['amount']); ?></td>
                </tr>
                <tr>
                    <td class="payment-label">Advance Payment Received:</td>
                    <td class="payment-value"><?php echo formatCurrency($advance_amount_received); ?></td>
                </tr>
                <?php if ($booking['payment_status'] !== 'paid'): ?>
                <tr class="due-amount-row">
                    <td class="payment-label"><strong>Balance Due Amount:</strong></td>
                    <td class="payment-value"><strong><?php echo formatCurrency($balance_due); ?></strong></td>
                </tr>
                <tr>
                    <td class="payment-label">Amount in Words:</td>
                    <td class="payment-value-words"><?php echo numberToWords($balance_due); ?> Only</td>
                </tr>
                <?php else: ?>
                <tr class="due-amount-row">
                    <td class="payment-label"><strong>Payment Status:</strong></td>
                    <td class="payment-value"><strong style="color: green;">✓ Fully Paid - All Accounts Cleared</strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="payment-label">Payment Mode:</td>
                    <td class="payment-value"><?php echo htmlspecialchars($payment_mode); ?></td>
                </tr>
            </table>
        </div>

        <!-- Important Note Section -->
        <div class="note-section">
            <h3>Important - Cancellation Policy</h3>
            <ul>
                <?php 
                // Split cancellation policy by lines and display as list items
                $policy_lines = array_filter(array_map('trim', explode("\n", $cancellation_policy)));
                foreach ($policy_lines as $line): 
                ?>
                    <li><?php echo htmlspecialchars($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Footer Section -->
        <div class="invoice-footer">
            <div class="signature-section">
                <div class="signature-line">
                    <p>_____________________</p>
                    <p><strong><?php echo htmlspecialchars($company_name); ?></strong></p>
                    <p>Authorized Signature</p>
                </div>
            </div>
            <div class="thank-you-section">
                <p><strong>Thank you for choosing <?php echo htmlspecialchars($company_name); ?>!</strong></p>
                <p>For any queries, please contact us at: <?php echo htmlspecialchars($company_phone); ?></p>
                <?php if ($company_email): ?>
                    <p>Email: <?php echo htmlspecialchars($company_email); ?></p>
                <?php endif; ?>
            </div>
            <div class="disclaimer-note">
                <p><?php echo nl2br(htmlspecialchars($invoice_disclaimer)); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0" style="background:#1e3a5f;">
            <div class="card-body py-3 px-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h3 class="mb-0 text-white fw-bold">
                                <i class="fas fa-calendar-check me-2 opacity-75"></i>
                                Booking #<?php echo htmlspecialchars($booking['booking_number']); ?>
                            </h3>
                            <span class="badge bg-<?php echo $booking_status_color; ?> fs-6 px-3 py-2">
                                <?php echo $booking_status_display; ?>
                            </span>
                            <span class="badge bg-<?php echo $payment_status_color; ?> fs-6 px-3 py-2">
                                <i class="fas <?php echo $payment_status_icon; ?> me-1"></i><?php echo $payment_status_display; ?>
                            </span>
                        </div>
                        <p class="text-white mb-0 mt-1 small opacity-75">
                            <i class="far fa-clock me-1"></i>
                            Created on <?php echo date('F d, Y \a\t h:i A', strtotime($booking['created_at'])); ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($booking['full_name']); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                        <a href="index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <a href="edit.php?id=<?php echo $booking_id; ?>" class="btn btn-warning btn-sm fw-semibold">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Check Panel -->
<?php 
// Prepare WhatsApp data
$clean_phone = !empty($booking['phone']) ? preg_replace('/[^0-9]/', '', $booking['phone']) : '';

// $advance (percentage-based required advance) and $advance_amount_received (actual received)
// were already computed above from calculatePaymentSummary().

// Get payment methods for this booking
$whatsapp_payment_methods = getBookingPaymentMethods($booking_id);

$whatsapp_shift_time = getBookingShiftTimeDisplay($booking);
$whatsapp_text  = "💳 *Payment Request – Booking #" . $booking['booking_number'] . "*\n\n";
$whatsapp_text .= "Dear " . strip_tags($booking['full_name']) . ",\n\n";
$whatsapp_text .= "To confirm your booking, please complete your advance payment at the earliest.\n\n";
$whatsapp_text .= "📅 " . convertToNepaliDate($booking['event_date']) . " (" . date('d M Y', strtotime($booking['event_date'])) . ")\n";
$whatsapp_text .= "🕐 " . $whatsapp_shift_time . "\n";
$whatsapp_text .= "🎉 " . strip_tags($booking['event_type']) . "\n";
$whatsapp_text .= "🏛️ " . strip_tags($booking['venue_name']) . "\n";
if (!empty($booking['hall_name'])) {
    $whatsapp_text .= "🚪 " . strip_tags($booking['hall_name']) . "\n";
}
$_wa_guests = intval($booking['number_of_guests'] ?? 0);
if ($_wa_guests > 0) {
    $whatsapp_text .= "👥 Guests: " . $_wa_guests . "\n";
}
if (!empty($booking['venue_address'])) {
    $whatsapp_text .= "🏠 " . strip_tags($booking['venue_address']) . "\n";
}
if (!empty($booking['map_link'])) {
    $whatsapp_text .= "🗺️ " . strip_tags($booking['map_link']) . "\n";
}
if (!empty($booking['menus'])) {
    $whatsapp_text .= "\n🍽️ *Menus:*\n";
    foreach ($booking['menus'] as $_wa_menu) {
        $_wa_menu_name = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_wa_menu['menu_name']));
        $whatsapp_text .= "• *" . $_wa_menu_name . "*\n";
        if (!empty($_wa_menu['items'])) {
            $_wa_by_cat = [];
            foreach ($_wa_menu['items'] as $_wa_item) {
                $_wa_cat = !empty($_wa_item['category']) ? strip_tags($_wa_item['category']) : '';
                $_wa_by_cat[$_wa_cat][] = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_wa_item['item_name']));
            }
            foreach ($_wa_by_cat as $_wa_cat => $_wa_cat_items) {
                if (!empty($_wa_cat)) {
                    $_wa_cat_esc = str_replace(['*', '_'], ['\*', '\_'], $_wa_cat);
                    $whatsapp_text .= "   _" . $_wa_cat_esc . ":_ " . implode(', ', $_wa_cat_items) . "\n";
                } else {
                    foreach ($_wa_cat_items as $_wa_item_name) {
                        $whatsapp_text .= "   - " . $_wa_item_name . "\n";
                    }
                }
            }
        }
        $_wa_sel = $booking['menu_item_selections'][$_wa_menu['menu_id']] ?? null;
        if (!empty($_wa_sel)) {
            $whatsapp_text .= "   📋 _Selected Items:_\n";
            foreach ($_wa_sel['sections'] as $_wa_sec => $_wa_grps) {
                $_wa_sec_esc = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_wa_sec));
                $whatsapp_text .= "   _" . $_wa_sec_esc . "_\n";
                foreach ($_wa_grps as $_wa_grp => $_wa_its) {
                    $_wa_grp_esc = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_wa_grp));
                    $_wa_names = array_map(function($i){ return str_replace(['*', '_'], ['\*', '\_'], strip_tags($i['item_name'])); }, $_wa_its);
                    $whatsapp_text .= "   • " . $_wa_grp_esc . ": " . implode(', ', $_wa_names) . "\n";
                }
            }
        }
    }
}
if (!empty($booking['menu_special_instructions'])) {
    $whatsapp_text .= "\n📝 *Menu Instructions:*\n" . strip_tags($booking['menu_special_instructions']) . "\n";
}
$whatsapp_text .= "\n💰 Total Amount: *" . formatCurrency($booking['grand_total']) . "*\n";
$whatsapp_text .= "💵 Advance (" . $advance['percentage'] . "%): *" . formatCurrency($advance['amount']) . "*\n";

if (!empty($whatsapp_payment_methods)) {
    $whatsapp_text .= "\n*Payment Options:*\n";
    foreach ($whatsapp_payment_methods as $idx => $method) {
        $whatsapp_text .= ($idx + 1) . ". *" . $method['name'] . "*\n";
        if (!empty($method['bank_details'])) {
            $whatsapp_text .= $method['bank_details'] . "\n";
        }
    }
    $whatsapp_text .= "\nAfter payment, please reply with your booking number *" . $booking['booking_number'] . "* for confirmation.\n";
} else {
    $whatsapp_text .= "\nPlease contact us to complete your payment.\n";
}

$whatsapp_text .= "\n*" . strip_tags($company_name) . "*";
$_wa_contact_phone = getSetting('contact_phone', '');
if (!empty($_wa_contact_phone)) {
    $whatsapp_text .= "\n📞 " . $_wa_contact_phone;
}

// Build booking confirmation WhatsApp message (shown after advance payment is received)
$booking_confirmation_vendors = $vendor_assignments;
$site_name_wa = !empty($company_name) ? $company_name : getSetting('site_name', 'Venue Booking System');

$confirmation_text  = "✅ *Booking Confirmed – #" . strip_tags($booking['booking_number']) . "*\n\n";
$confirmation_text .= "Dear " . strip_tags($booking['full_name']) . ",\n\n";
$confirmation_text .= "Your booking has been confirmed with *" . strip_tags($site_name_wa) . "*.\n\n";
$confirmation_text .= "📅 " . convertToNepaliDate($booking['event_date']) . " (" . date('d M Y', strtotime($booking['event_date'])) . ")\n";
$confirmation_text .= "🕐 " . getBookingShiftTimeDisplay($booking) . "\n";
$confirmation_text .= "🎉 " . strip_tags($booking['event_type']) . "\n\n";
$confirmation_text .= "🏛️ *" . strip_tags($booking['venue_name']) . "*\n";
if (!empty($booking['hall_name'])) {
    $confirmation_text .= "🚪 " . strip_tags($booking['hall_name']) . "\n";
}
$_conf_guests = intval($booking['number_of_guests'] ?? 0);
if ($_conf_guests > 0) {
    $confirmation_text .= "👥 Guests: " . $_conf_guests . "\n";
}
if (!empty($booking['venue_address'])) {
    $confirmation_text .= "🏠 " . strip_tags($booking['venue_address']) . "\n";
}
if (!empty($booking['map_link'])) {
    $confirmation_text .= "🗺️ " . strip_tags($booking['map_link']) . "\n";
}
if (!empty($booking['menus'])) {
    $confirmation_text .= "\n🍽️ *Menus:*\n";
    foreach ($booking['menus'] as $_conf_menu) {
        $_conf_menu_name = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_conf_menu['menu_name']));
        $confirmation_text .= "• *" . $_conf_menu_name . "*\n";
        // Standard menu items grouped by category
        if (!empty($_conf_menu['items'])) {
            $_conf_by_cat = [];
            foreach ($_conf_menu['items'] as $_conf_item) {
                $_conf_cat = !empty($_conf_item['category']) ? strip_tags($_conf_item['category']) : '';
                $_conf_by_cat[$_conf_cat][] = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_conf_item['item_name']));
            }
            foreach ($_conf_by_cat as $_conf_cat => $_conf_cat_items) {
                if (!empty($_conf_cat)) {
                    $_conf_cat_esc = str_replace(['*', '_'], ['\*', '\_'], $_conf_cat);
                    $confirmation_text .= "   _" . $_conf_cat_esc . ":_ " . implode(', ', $_conf_cat_items) . "\n";
                } else {
                    foreach ($_conf_cat_items as $_conf_item_name) {
                        $confirmation_text .= "   - " . $_conf_item_name . "\n";
                    }
                }
            }
        }
        // Custom selections for this menu
        $_conf_sel = $booking['menu_item_selections'][$_conf_menu['menu_id']] ?? null;
        if (!empty($_conf_sel)) {
            $confirmation_text .= "   📋 _Selected Items:_\n";
            foreach ($_conf_sel['sections'] as $_csec => $_cgrps) {
                $_csec_esc = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_csec));
                $confirmation_text .= "   _" . $_csec_esc . "_\n";
                foreach ($_cgrps as $_cgrp => $_cits) {
                    $_cgrp_esc = str_replace(['*', '_'], ['\*', '\_'], strip_tags($_cgrp));
                    $_cnames = array_map(function($i){ return str_replace(['*', '_'], ['\*', '\_'], strip_tags($i['item_name'])); }, $_cits);
                    $confirmation_text .= "   • " . $_cgrp_esc . ": " . implode(', ', $_cnames) . "\n";
                }
            }
        }
    }
}
if (!empty($booking['menu_special_instructions'])) {
    $confirmation_text .= "\n📝 *Menu Instructions:*\n" . strip_tags($booking['menu_special_instructions']) . "\n";
}
$confirmation_adv_info = getAdvanceDisplayInfo(
    floatval($booking['grand_total']),
    floatval($booking['advance_amount_received'] ?? 0)
);
if ($confirmation_adv_info['amount'] > 0) {
    $adv_label = $confirmation_adv_info['label'] ? ' ' . $confirmation_adv_info['label'] : '';
    $confirmation_text .= "\n💰 *Advance Received" . $adv_label . ":* " . formatCurrency($confirmation_adv_info['amount']) . "\n";
}
if ($balance_due > 0) {
    $confirmation_text .= "💳 *Remaining Balance:* " . formatCurrency($balance_due) . "\n";
}
if (!empty($booking_confirmation_vendors)) {
    $confirmation_text .= "\n👥 *Your Assigned Team:*\n";
    foreach ($booking_confirmation_vendors as $va) {
        $label = getVendorTypeLabel($va['vendor_type']);
        $confirmation_text .= "• " . $label . ": *" . strip_tags($va['vendor_name']) . "*";
        if (!empty($va['vendor_phone'])) {
            $confirmation_text .= " – " . strip_tags($va['vendor_phone']);
        }
        $confirmation_text .= "\n";
    }
}
$confirmation_text .= "\nFor any queries, feel free to contact us.\n";
$confirmation_text .= "\n*" . strip_tags($site_name_wa) . "*";
$_conf_contact_phone = getSetting('contact_phone', '');
if (!empty($_conf_contact_phone)) {
    $confirmation_text .= "\n📞 " . $_conf_contact_phone;
}

// Build thank you WhatsApp message (shown after payment is fully paid)
$google_review_link = getSetting('google_review_link') ?: 'https://g.page/r/CXn4LyBY3iY7EBM/review';
$review_token_wa    = generateReviewToken($booking['id']);
$review_url_wa      = $review_token_wa
    ? BASE_URL . '/write-review.php?token=' . urlencode($review_token_wa)
    : '';
$thankyou_text  = "🎉 *Thank You – " . strip_tags($booking['full_name']) . "!*\n\n";
$thankyou_text .= "We hope your *" . strip_tags($booking['event_type']) . "* on ";
if (!empty($booking['event_date'])) {
    $event_date_en = date('F d, Y', strtotime($booking['event_date']));
    $event_date_np = convertToNepaliDate($booking['event_date']);
    $thankyou_text .= $event_date_en;
    if (!empty($event_date_np)) {
        $thankyou_text .= " (" . $event_date_np . ")";
    }
}
$thankyou_text .= " was wonderful and memorable.\n\n";
$thankyou_text .= "It was truly a pleasure serving you at *" . strip_tags($booking['venue_name']) . "*.\n\n";
if (!empty($review_url_wa)) {
    $thankyou_text .= "✍️ We would love to hear about your experience. Please share your review:\n";
    $thankyou_text .= $review_url_wa . "\n\n";
}
if (!empty($google_review_link)) {
    $thankyou_text .= "⭐ You can also leave us a Google review:\n";
    $thankyou_text .= $google_review_link . "\n\n";
    $thankyou_text .= "Your kind words help us serve future clients better.\n\n";
}
$thankyou_text .= "*" . strip_tags($site_name_wa) . "*";
$contact_phone_wa = getSetting('contact_phone', '');
if (!empty($contact_phone_wa)) {
    $thankyou_text .= "\n📞 " . strip_tags($contact_phone_wa);
}

// Build venue provider WhatsApp URL
$venue_provider_wa_url = buildVenueProviderWhatsAppUrl($booking);
$clean_venue_phone = preg_replace('/[^0-9]/', '', $booking['venue_contact_phone'] ?? '');
// Pre-compute variables for tabbed layout
// (admin_services, package_services, and user_services already computed above for print invoice)
$user_services_count = count($user_services);
$user_services_total = 0;
foreach ($user_services as $_svc) {
    $user_services_total += floatval($_svc['price'] ?? 0) * intval($_svc['quantity'] ?? 1);
}
$booking_payment_methods = getBookingPaymentMethods($booking_id);
$active_payment_methods  = getActivePaymentMethods();
$csrf_token_value        = generateCSRFToken();
// Services tab count: menus, user services, admin services, and packages
$tab_services_count = count($user_services) + count($admin_services) + count($booking['menus'] ?? []) + count($package_services);
$tab_payments_count = count($payment_transactions);

// Load available service packages for the Add Package form
$available_packages_by_category = getServicePackagesByCategory();
// Load active services for the catalog selection dropdown, enriched with their designs
$available_services = getActiveServices();
foreach ($available_services as &$_avail_svc) {
    $_avail_svc['designs']     = getServiceDesigns($_avail_svc['id']);
    $_avail_svc['has_designs'] = !empty($_avail_svc['designs']);
}
unset($_avail_svc);
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-sliders me-2 opacity-75"></i> Quick Actions</h6>
                <small class="opacity-75 d-none d-sm-inline">Status &amp; messaging</small>
            </div>
            <div class="card-body p-2">
                <div class="row g-2">

                    <!-- Booking Status -->
                    <div class="col-md-4">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-circle-dot text-primary me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Booking Status</span>
                                <span class="badge bg-<?php echo $booking_status_color; ?> ms-auto" id="booking-status-badge">
                                    <?php echo $booking_status_display; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Advance Payment Status (auto-managed by Payment Status) -->
                    <div class="col-md-4">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-money-check-alt text-success me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Advance Payment</span>
                                <?php if ($booking['advance_payment_received'] === 1): ?>
                                    <span class="badge bg-success ms-auto" id="advance-payment-badge"><i class="fas fa-check-circle me-1"></i>Received</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-auto" id="advance-payment-badge"><i class="fas fa-times-circle me-1"></i>Not Received</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($booking['advance_payment_received'] === 1 && $advance_amount_received > 0): ?>
                                <span class="fw-semibold text-success small" id="advance-amount-display">
                                    <i class="fas fa-check me-1"></i><?php echo formatCurrency($advance_amount_received); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small" id="advance-amount-display">—</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Status + Advance Amount Entry (unified) -->
                    <div class="col-md-4">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-credit-card text-info me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Payment Status</span>
                                <span class="badge bg-<?php echo $payment_status_color; ?> ms-auto" id="payment-status-badge">
                                    <?php echo $payment_status_display; ?>
                                </span>
                            </div>
                            <?php if (in_array($booking['payment_status'], ['partial', 'paid']) && $advance_amount_received > 0): ?>
                            <div class="input-group input-group-sm mt-1">
                                <span class="input-group-text px-1 quick-check-currency"><?php echo htmlspecialchars(getSetting('currency', 'NPR'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <input type="text" class="form-control form-control-sm" readonly
                                    value="<?php echo htmlspecialchars(number_format($advance_amount_received, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Send Payment Request / Booking Confirmation / Thank You -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <?php
                            // Show "Thank You" when payment status is paid (full payment received)
                            $show_thankyou = (strtolower($booking['payment_status']) === 'paid');
                            // Show "Booking Confirmation" only when advance payment is received AND payment status is partial (not pending, not paid)
                            $show_confirmation = ($booking['advance_payment_received'] === 1 && strtolower($booking['payment_status']) === 'partial');
                            // Show "Payment Request" when payment status is pending or advance payment not yet received
                            $show_payment_request = (!$show_thankyou && !$show_confirmation);
                            // Build "Send to All" combo WhatsApp URL list (customer + venue provider + all vendors)
                            $_cust_wa_url_all = '';
                            if (!empty($clean_phone)) {
                                if ($show_thankyou) {
                                    $_cust_text_all = $thankyou_text;
                                } elseif ($show_confirmation) {
                                    $_cust_text_all = $confirmation_text;
                                } else {
                                    $_cust_text_all = $whatsapp_text;
                                }
                                $_cust_wa_url_all = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode($_cust_text_all);
                                unset($_cust_text_all);
                            }
                            $all_combo_wa_urls = [];
                            $all_combo_wa_data = [];
                            $_combo_has_customer = false;
                            $_combo_has_venue = false;
                            $_combo_vendor_count = 0;
                            if (!empty($_cust_wa_url_all)) {
                                $all_combo_wa_urls[] = $_cust_wa_url_all;
                                $all_combo_wa_data[] = [
                                    'url'   => $_cust_wa_url_all,
                                    'label' => 'Customer',
                                    'name'  => $booking['full_name'],
                                    'phone' => $booking['phone'],
                                ];
                                $_combo_has_customer = true;
                            }
                            if (!empty($venue_provider_wa_url)) {
                                $all_combo_wa_urls[] = $venue_provider_wa_url;
                                $all_combo_wa_data[] = [
                                    'url'   => $venue_provider_wa_url,
                                    'label' => 'Venue Provider',
                                    'name'  => $booking['venue_name'],
                                    'phone' => $booking['venue_contact_phone'],
                                ];
                                $_combo_has_venue = true;
                            }
                            foreach ($vendor_assignments as $_vwa_va) {
                                if (!empty($_vwa_va['vendor_phone'])) {
                                    $_vwa_bsid   = (int)($_vwa_va['booking_service_id'] ?? 0);
                                    $_vwa_design = $booking_svc_design_map[$_vwa_bsid] ?? null;
                                    $_vwa_url = buildVendorAssignmentWhatsAppUrl($_vwa_va['vendor_name'], $_vwa_va['vendor_phone'], $booking, $_vwa_va['vendor_type'] ?? '', $_vwa_design);
                                    if (!empty($_vwa_url)) {
                                        $all_combo_wa_urls[] = $_vwa_url;
                                        $_vwa_type_label = getVendorTypeLabel($_vwa_va['vendor_type'] ?? '');
                                        $all_combo_wa_data[] = [
                                            'url'   => $_vwa_url,
                                            'label' => $_vwa_type_label ?: 'Vendor',
                                            'name'  => $_vwa_va['vendor_name'],
                                            'phone' => $_vwa_va['vendor_phone'],
                                        ];
                                        $_combo_vendor_count++;
                                    }
                                }
                            }
                            unset($_cust_wa_url_all, $_vwa_va, $_vwa_url, $_vwa_type_label, $_vwa_bsid, $_vwa_design);
                            // "Send to All" button enabled only when booking is confirmed (advance payment received)
                            $send_all_whatsapp_enabled = ($booking['advance_payment_received'] === 1);
                            ?>
                            <!-- Thank You Message (shown after full payment - payment status is paid) -->
                            <div id="thankyou-section" <?php echo $show_thankyou ? '' : 'style="display:none"'; ?>>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-heart text-danger me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Thank You &amp; Review Request</span>
                                </div>
                                <div class="d-grid">
                                    <form method="POST" action="" id="thankyouWhatsappForm">
                                        <input type="hidden" name="action" value="send_thankyou_whatsapp">
                                        <button type="submit" class="btn btn-success btn-sm w-100" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                            <i class="fab fa-whatsapp me-1"></i> 🙏 Thank You + Review Request
                                        </button>
                                    </form>
                                </div>
                                <?php if (!empty($review_url_wa)): ?>
                                <div class="mt-1">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="review-link-input"
                                               value="<?php echo htmlspecialchars($review_url_wa, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" id="copyReviewLinkBtn" title="Copy review link">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (empty($booking['phone'])): ?>
                                    <small class="text-danger d-block mt-1">
                                        <i class="fas fa-exclamation-circle me-1"></i> Phone not available
                                    </small>
                                <?php endif; ?>
                            </div>
                            <!-- Booking Confirmation (shown after advance payment received and payment status is partial) -->
                            <div id="booking-confirmation-section" <?php echo $show_confirmation ? '' : 'style="display:none"'; ?>>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Booking Confirmation</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="" class="flex-fill">
                                        <input type="hidden" name="action" value="send_booking_confirmation_email">
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-envelope me-1"></i> Email
                                        </button>
                                    </form>
                                    <form method="POST" action="" id="confirmationWhatsappForm" class="flex-fill">
                                        <input type="hidden" name="action" value="send_booking_confirmation_whatsapp">
                                        <button type="submit" class="btn btn-success btn-sm w-100" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                            <i class="fab fa-whatsapp me-1"></i> ✅ Booking Confirmation
                                        </button>
                                    </form>
                                </div>
                                <?php if (empty($booking['phone']) && empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> No contact info available
                                    </small>
                                <?php elseif (empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> Phone not available
                                    </small>
                                <?php elseif (empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> Email not available
                                    </small>
                                <?php endif; ?>
                            </div>
                            <!-- Send Payment Request (shown when payment status is pending or advance payment not yet received) -->
                            <div id="payment-request-section" <?php echo $show_payment_request ? '' : 'style="display:none"'; ?>>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-paper-plane text-info me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Send Payment Request</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="" class="flex-fill">
                                        <input type="hidden" name="action" value="send_payment_request_email">
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" <?php echo empty($booking['email']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-envelope me-1"></i> Email
                                        </button>
                                    </form>
                                    <form method="POST" action="" id="whatsappForm" class="flex-fill">
                                        <input type="hidden" name="action" value="send_payment_request_whatsapp">
                                        <button type="submit" class="btn btn-outline-success btn-sm w-100" <?php echo empty($booking['phone']) ? 'disabled' : ''; ?>>
                                            <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                        </button>
                                    </form>
                                </div>
                                <?php if (empty($booking['email']) && empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> No contact info available
                                    </small>
                                <?php elseif (empty($booking['email'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> Email not available
                                    </small>
                                <?php elseif (empty($booking['phone'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i> Phone not available
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Venue Provider Notification -->
                    <div class="col-md-6">
                        <div class="quick-check-item h-100">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-building text-warning me-2"></i>
                                <span class="fw-bold small text-uppercase text-muted">Notify Venue Provider</span>
                            </div>
                            <form method="POST" action="" id="venueProviderWhatsappForm" class="d-grid">
                                <input type="hidden" name="action" value="send_venue_provider_whatsapp">
                                <button type="submit" class="btn btn-warning btn-sm w-100"
                                    <?php echo empty($booking['venue_contact_phone']) ? 'disabled' : ''; ?>>
                                    <i class="fab fa-whatsapp me-1"></i> Notify Venue via WhatsApp
                                </button>
                            </form>
                            <?php if (empty($booking['venue_contact_phone'])): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No venue contact phone. <a href="<?php echo BASE_URL; ?>/admin/venues/" class="alert-link">Update venue</a>.
                                </small>
                            <?php else: ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-phone me-1"></i>
                                    <?php echo htmlspecialchars($booking['venue_contact_phone']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Send WhatsApp to All (combo: customer + venue provider + all vendors) -->
                    <div class="col-12">
                        <div class="quick-check-item">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center">
                                    <i class="fab fa-whatsapp text-success me-2"></i>
                                    <span class="fw-bold small text-uppercase text-muted">Send WhatsApp to All</span>
                                </div>
                                <button type="button"
                                    class="btn btn-success btn-sm"
                                    id="send-all-whatsapp-btn"
                                    onclick="sendAllWhatsApp()"
                                    <?php echo (!$send_all_whatsapp_enabled || empty($all_combo_wa_urls)) ? 'disabled' : ''; ?>>
                                    <i class="fab fa-whatsapp me-1"></i> Send to All
                                </button>
                            </div>
                            <?php if (!$send_all_whatsapp_enabled): ?>
                                <small class="text-danger d-block mt-1">
                                    <i class="fas fa-lock me-1"></i> Available only after advance payment is received.
                                </small>
                            <?php elseif (empty($all_combo_wa_urls)): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i> No contact information available.
                                </small>
                            <?php else: ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-users me-1"></i> Sends to:
                                    <?php
                                    $_all_recipients = [];
                                    if ($_combo_has_customer) $_all_recipients[] = 'Customer';
                                    if ($_combo_has_venue) $_all_recipients[] = 'Venue Provider';
                                    if ($_combo_vendor_count > 0) $_all_recipients[] = $_combo_vendor_count . ' Vendor' . ($_combo_vendor_count > 1 ? 's' : '');
                                    echo htmlspecialchars(implode(', ', $_all_recipients));
                                    unset($_all_recipients);
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <?php
                // Build grouped vendor data for JS two-step selection (only types with available vendors)
                $vendors_by_type = [];
                foreach ($all_vendors as $v) {
                    $vendors_by_type[$v['type']][] = [
                        'id'            => $v['id'],
                        'name'          => $v['name'],
                        'description'   => $v['short_description'] ?? '',
                        'city'          => $v['city_name'] ?? '',
                        'photo'         => $vendor_primary_photos[$v['id']] ?? '',
                        'is_unapproved' => ($v['status'] === 'unapproved'),
                    ];
                }
                // Filter vendor types list to only those that have available vendors
                $vendor_types_available = array_filter(getVendorTypes(), function($vt) use ($vendors_by_type) {
                    return isset($vendors_by_type[$vt['slug']]);
                });
                ?>

                <!-- Packages -->
                <div class="border-top mt-3 pt-2" id="packages">
                    <!-- Section Header -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-box-open text-secondary" style="font-size:.85rem;"></i>
                            <span class="fw-bold text-uppercase text-muted">Packages</span>
                            <?php if (!empty($package_services)): ?>
                                <span class="badge bg-primary" style="font-size:.65rem;"><?php echo count($package_services); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($is_packages_flash && $success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2 px-3 small" role="alert">
                        <i class="fas fa-check-circle me-1"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_packages_flash && $error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($package_services)): ?>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered mb-0 align-middle" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:46px;"></th>
                                    <th>Package</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($package_services as $service):
                                    $pkg_price = floatval($service['price'] ?? 0);
                                    $pkg_qty   = intval($service['quantity'] ?? 1);
                                    $pkg_total = $pkg_price * $pkg_qty;
                                    $pkg_is_admin_added = isset($service['added_by']) && $service['added_by'] === 'admin';
                                    $pkg_is_included    = ($service['category'] ?? '') === PACKAGE_INCLUDED_CATEGORY;
                                    // Package rows use service_package_photos; included-service rows use additional_services photos
                                    if ($pkg_is_included) {
                                        $pkg_photo_url = ($service['service_id'] > 0) ? ($service_primary_photos[$service['service_id']] ?? '') : '';
                                    } else {
                                        $pkg_photo_url = ($service['service_id'] > 0) ? ($package_primary_photos[$service['service_id']] ?? '') : '';
                                    }
                                ?>
                                <tr<?php echo $pkg_is_included ? ' class="table-success bg-opacity-10"' : ''; ?>>
                                    <td class="text-center align-middle">
                                        <?php if (!empty($pkg_photo_url)): ?>
                                        <div class="photo-zoom-wrap mx-auto" style="width:36px;height:36px;">
                                            <img src="<?php echo htmlspecialchars($pkg_photo_url); ?>"
                                                 alt="<?php echo htmlspecialchars($service['service_name']); ?>"
                                                 class="rounded"
                                                 style="width:36px;height:36px;object-fit:cover;">
                                            <div class="photo-zoom-popup">
                                                <img src="<?php echo htmlspecialchars($pkg_photo_url); ?>"
                                                     alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                                            </div>
                                        </div>
                                        <?php elseif ($pkg_is_included): ?>
                                        <span class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded"
                                              style="width:36px;height:36px;font-size:.85rem;">
                                            <i class="fas fa-check"></i>
                                        </span>
                                        <?php else: ?>
                                        <span class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded"
                                              style="width:36px;height:36px;font-size:.85rem;">
                                            <i class="fas fa-box-open"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pkg_is_included): ?>
                                            <i class="fas fa-long-arrow-alt-right text-muted me-1" style="font-size:.8rem;" aria-hidden="true"></i>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                            <span class="badge bg-success ms-1" style="font-size:.65rem;" title="Automatically included because it is part of a selected package"><i class="fas fa-box-open me-1"></i>Included</span>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                            <?php if (!$pkg_is_admin_added): ?>
                                                <span class="badge bg-success ms-1" style="font-size:.65rem;" title="Selected by customer during booking">Customer</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($service['description'])): ?>
                                            <small class="d-block text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($service['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-info"><?php echo $pkg_qty; ?></span></td>
                                    <td class="text-end fw-bold text-primary">
                                        <?php if ($pkg_is_included): ?>
                                            <span class="text-success small">Included</span>
                                        <?php else: ?>
                                            <?php echo formatCurrency($pkg_price); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        <?php if ($pkg_is_included): ?>
                                            <span class="text-success small">—</span>
                                        <?php else: ?>
                                            <?php echo formatCurrency($pkg_total); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pkg_is_included): ?>
                                        <span class="text-muted" title="Auto-included from package" style="font-size:.75rem;">—</span>
                                        <?php elseif ($pkg_is_admin_added): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this package from the booking?');">
                                            <input type="hidden" name="action" value="delete_admin_service">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <input type="hidden" name="from_packages" value="1">
                                            <button type="submit" class="btn btn-danger btn-sm py-0 px-1" title="Remove package" style="font-size:.75rem;">
                                                <i class="fas fa-trash small"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted" title="Customer-selected package" style="font-size:.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (count($package_services) > 1): ?>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold small">Total:</td>
                                    <td class="text-end"><strong class="text-success"><?php echo formatCurrency($package_services_total); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i>No packages added yet.</p>
                    <?php endif; ?>

                    <!-- Add Package Form -->
                    <div class="border-top pt-2">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-plus-circle text-primary me-1" style="font-size:.8rem;"></i>
                            <span class="fw-semibold text-muted" style="font-size:.78rem;">Add Package</span>
                        </div>
                        <?php if (!empty($available_packages_by_category)): ?>
                        <form method="POST" action="" id="add-package-form">
                            <input type="hidden" name="action" value="add_package">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Package <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" name="package_id" id="package-select" required onchange="updatePackagePricePreview(this)" style="min-width:160px;">
                                        <option value="">— Select Package —</option>
                                        <?php foreach ($available_packages_by_category as $cat): ?>
                                            <?php if (!empty($cat['packages'])): ?>
                                            <optgroup label="<?php echo htmlspecialchars($cat['name']); ?>">
                                                <?php foreach ($cat['packages'] as $pkg): ?>
                                                <option value="<?php echo intval($pkg['id']); ?>"
                                                        data-formatted-price="<?php echo htmlspecialchars(formatCurrency($pkg['price']), ENT_QUOTES); ?>"
                                                        data-description="<?php echo htmlspecialchars($pkg['description'] ?? '', ENT_QUOTES); ?>">
                                                    <?php echo htmlspecialchars($pkg['name']); ?> — <?php echo formatCurrency($pkg['price']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Qty</label>
                                    <input type="number" class="form-control form-control-sm" name="quantity"
                                           min="1" value="1" style="width:65px;">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 small fw-semibold">Price</label>
                                    <input type="text" class="form-control form-control-sm bg-light" id="package-price-preview"
                                           readonly style="width:110px;" placeholder="—">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>
                            </div>
                        </form>
                        <script>
                        function updatePackagePricePreview(select) {
                            const opt = select.options[select.selectedIndex];
                            document.getElementById('package-price-preview').value = opt.dataset.formattedPrice || '';
                        }
                        </script>
                        <?php else: ?>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-info-circle me-1 text-info"></i>
                            No active packages available. <a href="<?php echo BASE_URL; ?>/admin/packages/add.php">Add packages</a> to use this feature.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- [Admin Added Services and Add Service sections moved to #section-services below] -->

            </div>
        </div>
        <div class="card shadow-sm border-0 mb-3 booking-section-card" id="section-overview">
            <div class="card-header booking-section-header d-flex align-items-center">
                <i class="fas fa-address-card me-2 text-primary"></i>
                <span class="fw-bold">Booking Overview</span>
            </div>
                    <div class="row g-0">
                        <!-- Customer Information -->
                        <div class="col-md-6 border-end-md">
                            <div class="p-4">
                                <div class="section-label-premium mb-3">
                                    <span class="section-dot bg-info"></span>
                                    <span class="fw-bold text-uppercase text-muted">Customer Information</span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-user-circle text-primary me-1"></i> Name</span>
                                    <span class="compact-field-value fw-semibold"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-phone text-success me-1"></i> Phone</span>
                                    <span class="compact-field-value">
                                        <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>" class="text-decoration-none fw-semibold text-dark">
                                            <?php echo htmlspecialchars($booking['phone']); ?>
                                        </a>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-envelope text-danger me-1"></i> Email</span>
                                    <span class="compact-field-value">
                                        <?php if ($booking['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="text-decoration-none fw-semibold text-dark">
                                                <?php echo htmlspecialchars($booking['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <em class="text-muted small">Not provided</em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-map-marker-alt text-warning me-1"></i> Address</span>
                                    <span class="compact-field-value">
                                        <?php if ($booking['address']): ?>
                                            <?php echo htmlspecialchars($booking['address']); ?>
                                        <?php else: ?>
                                            <em class="text-muted small">Not provided</em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Event Details -->
                        <div class="col-md-6">
                            <div class="p-4">
                                <div class="section-label-premium mb-3">
                                    <span class="section-dot bg-success"></span>
                                    <span class="fw-bold text-uppercase text-muted">Event Details</span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-building text-primary me-1"></i> Venue</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo htmlspecialchars($booking['venue_name']); ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($booking['location']); ?></small>
                                        <?php if (!empty($booking['map_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($booking['map_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none small">
                                                <i class="fas fa-map-pin text-danger me-1"></i>View Map
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-door-open text-info me-1"></i> Hall</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo htmlspecialchars($booking['hall_name']); ?>
                                        <small class="text-muted">(<?php echo $booking['capacity']; ?> capacity)</small>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="far fa-calendar text-danger me-1"></i> Date</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                                        <small class="text-muted">(<?php echo convertToNepaliDate($booking['event_date']); ?>)</small>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="far fa-clock text-warning me-1"></i> Shift</span>
                                    <span class="compact-field-value fw-semibold">
                                        <?php echo ucfirst($booking['shift']); ?>
                                        <?php if ($has_display_time): ?>
                                            <small class="text-muted"><?php echo formatBookingTime($display_start_time); ?> – <?php echo formatBookingTime($display_end_time); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-tag text-success me-1"></i> Event</span>
                                    <span class="compact-field-value fw-semibold"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                                </div>
                                <div class="compact-field">
                                    <span class="compact-field-label"><i class="fas fa-users text-primary me-1"></i> Guests</span>
                                    <span class="compact-field-value">
                                        <span class="badge bg-primary px-2 py-1"><?php echo $booking['number_of_guests']; ?> Guests</span>
                                    </span>
                                </div>
                                <?php if ($booking['special_requests']): ?>
                                <div class="compact-field align-items-start mt-1">
                                    <span class="compact-field-label pt-1"><i class="fas fa-comment-dots text-info me-1"></i> Notes</span>
                                    <span class="compact-field-value">
                                        <span class="d-block text-muted small border rounded px-2 py-1 bg-light"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
        </div><!-- /section-overview -->

        <!-- ===== SERVICES SECTION ===== -->
        <div class="card shadow-sm border-0 mb-3 booking-section-card" id="section-services">
            <div class="card-header booking-section-header d-flex align-items-center">
                <i class="fas fa-concierge-bell me-2 text-warning"></i>
                <span class="fw-bold">Services</span>
                <?php if ($tab_services_count > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $tab_services_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="p-3">

                        <!-- Menus -->
                        <?php if (count($booking['menus']) > 0): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-2">
                                <span class="section-dot bg-warning"></span>
                                <span class="fw-bold text-uppercase text-muted">Selected Menus</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fw-semibold">Menu</th>
                                            <th class="fw-semibold text-end">Price/Person</th>
                                            <th class="fw-semibold text-center">Guests</th>
                                            <th class="fw-semibold text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($booking['menus'] as $menu): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <i class="fas fa-plate-wheat text-warning me-2"></i>
                                                <?php echo htmlspecialchars($menu['menu_name']); ?>
                                                <?php if (!empty($menu['items'])): ?>
                                                    <?php $safeMenuId = intval($menu['menu_id']); ?>
                                                    <button class="btn btn-sm btn-outline-secondary ms-2 py-0 px-1" type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#menu-items-<?php echo $safeMenuId; ?>"
                                                            aria-expanded="false">
                                                        <i class="fas fa-list small"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold text-success"><?php echo formatCurrency($menu['price_per_person']); ?></td>
                                            <td class="text-center"><span class="badge bg-info"><?php echo $menu['number_of_guests']; ?></span></td>
                                            <td class="text-end fw-bold text-primary"><?php echo formatCurrency($menu['total_price']); ?></td>
                                        </tr>
                                        <?php if (!empty($menu['items'])): ?>
                                        <tr class="collapse" id="menu-items-<?php echo $safeMenuId; ?>">
                                            <td colspan="4" class="bg-light">
                                                <div class="p-2">
                                                    <strong class="small">Menu Items:</strong>
                                                    <ul class="mb-0 mt-1">
                                                        <?php
                                                        $items_by_category = [];
                                                        foreach ($menu['items'] as $item) {
                                                            $category = !empty($item['category']) ? $item['category'] : 'Other';
                                                            $items_by_category[$category][] = $item;
                                                        }
                                                        foreach ($items_by_category as $category => $items):
                                                        ?>
                                                            <?php if (count($items_by_category) > 1): ?>
                                                                <li class="small"><strong><?php echo htmlspecialchars($category); ?>:</strong>
                                                                    <ul>
                                                                        <?php foreach ($items as $item): ?>
                                                                            <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </li>
                                                            <?php else: ?>
                                                                <?php foreach ($items as $item): ?>
                                                                    <li class="small"><?php echo htmlspecialchars($item['item_name']); ?></li>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($booking['menu_item_selections'])): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-2">
                                <span class="section-dot bg-success"></span>
                                <span class="fw-bold text-uppercase text-muted">Custom Menu Selections</span>
                            </div>
                            <?php foreach ($booking['menu_item_selections'] as $sel_menu): ?>
                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2">
                                    <strong><i class="fas fa-clipboard-list text-success me-1"></i> <?php echo htmlspecialchars($sel_menu['menu_name']); ?></strong>
                                </div>
                                <div class="card-body p-2">
                                    <?php foreach ($sel_menu['sections'] as $sec_name => $groups): ?>
                                    <div class="mb-3">
                                        <div class="fw-semibold text-uppercase text-muted small border-bottom pb-1 mb-2"><?php echo htmlspecialchars($sec_name); ?></div>
                                        <?php foreach ($groups as $grp_name => $items): ?>
                                        <div class="mb-2 ms-2">
                                            <span class="fw-semibold text-success small"><?php echo htmlspecialchars($grp_name); ?>:</span>
                                            <div class="ms-3">
                                                <?php foreach ($items as $item): ?>
                                                <div class="small py-1 border-bottom border-light">
                                                    <i class="fas fa-utensils text-muted me-1" style="font-size:0.75em;"></i>
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                    <?php if (!empty($item['sub_category'])): ?>
                                                        <span class="text-muted ms-1">(<?php echo htmlspecialchars($item['sub_category']); ?>)</span>
                                                    <?php endif; ?>
                                                    <?php if ($item['extra_charge'] > 0): ?>
                                                        <span class="badge bg-warning text-dark ms-1">+<?php echo formatCurrency($item['extra_charge']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($booking['menu_special_instructions'])): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-2">
                                <span class="section-dot bg-info"></span>
                                <span class="fw-bold text-uppercase text-muted">Menu Special Instructions</span>
                            </div>
                            <div class="alert alert-info mb-0 py-2">
                                <i class="fas fa-comment-alt me-2"></i><?php echo nl2br(htmlspecialchars($booking['menu_special_instructions'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Unified Services Section (Customer + Admin) -->
                        <?php
                        $all_display_services = [];
                        foreach ($user_services as $_s) {
                            $all_display_services[] = array_merge($_s, ['_is_admin' => false]);
                        }
                        foreach ($admin_services as $_s) {
                            $all_display_services[] = array_merge($_s, ['_is_admin' => true]);
                        }
                        $all_display_services_count = count($all_display_services);
                        $all_display_services_total = $user_services_total + $admin_services_total;
                        ?>
                        <?php if ($all_display_services_count > 0 || !empty($available_services)): ?>
                        <div class="mb-3" id="unified-services">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="section-label-premium mb-0">
                                    <span class="section-dot bg-secondary"></span>
                                    <span class="fw-bold text-uppercase text-muted">Services</span>
                                    <?php if ($all_display_services_count > 0): ?>
                                        <span class="badge bg-secondary ms-1" style="font-size:.65rem;"><?php echo $all_display_services_count; ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($combo_wa_urls)): ?>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-success py-0 px-2" id="combo-wa-btn"
                                            onclick="sendAllVendorWhatsApp()"
                                            title="Send WhatsApp message to all assigned vendors one by one">
                                        <i class="fab fa-whatsapp me-1"></i>WhatsApp All (<?php echo count($combo_wa_urls); ?>)
                                    </button>
                                    <?php if (!empty($combo_email_list)): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="send_all_vendor_emails">
                                        <button type="submit" class="btn btn-sm btn-info py-0 px-2"
                                                onclick="return confirm('Send email notification to all <?php echo (int)count($combo_email_list); ?> assigned vendor(s)?')"
                                                title="Send email to all assigned vendors">
                                            <i class="fas fa-envelope me-1"></i>Email All
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (($is_admin_services_flash || $is_vendor_flash) && $success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show py-2 px-3 small" role="alert">
                                <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($success_message); ?>
                                <?php if (!empty($new_vendor_wa_url)): ?>
                                    <a href="<?php echo htmlspecialchars($new_vendor_wa_url); ?>" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-sm btn-success ms-2 py-0 px-2">
                                        <i class="fab fa-whatsapp me-1"></i>Notify
                                    </a>
                                <?php endif; ?>
                                <?php if ($new_vendor_email_sent): ?>
                                    <span class="badge bg-info ms-1"><i class="fas fa-envelope me-1"></i>Email sent</span>
                                <?php endif; ?>
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            <?php if (($is_admin_services_flash || $is_vendor_flash) && $error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" role="alert">
                                <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <!-- Service cards loop (user + admin combined) -->
                            <?php foreach ($all_display_services as $service):
                                $service_price   = floatval($service['price'] ?? 0);
                                $service_qty     = intval($service['quantity'] ?? 1);
                                $service_total   = $service_price * $service_qty;
                                $svc_id          = (int)$service['id'];
                                $svc_master_id   = (int)($service['service_id'] ?? 0);
                                $svc_design_id   = (int)($service['design_id'] ?? 0);
                                $svc_design      = ($svc_design_id > 0) ? ($service_design_info[$svc_design_id] ?? null) : null;
                                // Use design photo when available, otherwise fall back to service primary photo
                                $svc_photo_url   = ($svc_design && !empty($svc_design['photo'])) ? $svc_design['photo'] : (($svc_master_id > 0) ? ($service_primary_photos[$svc_master_id] ?? '') : '');
                                $svc_vt_slug     = $service_vendor_type_slugs[$svc_id] ?? '';
                                $svc_vendors     = $vendor_assignments_by_service[$svc_id] ?? [];
                                $svc_is_admin    = $service['_is_admin'];
                            ?>
                            <div class="border rounded mb-2 overflow-hidden">
                                <!-- Service header row -->
                                <div class="d-flex align-items-start gap-2 p-2 bg-light">
                                    <?php if (!empty($svc_photo_url)): ?>
                                    <div class="photo-zoom-wrap flex-shrink-0" style="width:48px;height:48px;">
                                        <img src="<?php echo htmlspecialchars($svc_photo_url); ?>"
                                             alt="<?php echo htmlspecialchars($service['service_name']); ?>"
                                             class="rounded"
                                             style="width:48px;height:48px;object-fit:cover;">
                                        <div class="photo-zoom-popup">
                                            <img src="<?php echo htmlspecialchars($svc_photo_url); ?>"
                                                 alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded flex-shrink-0"
                                          style="width:48px;height:48px;font-size:1.1rem;">
                                        <i class="fas fa-concierge-bell"></i>
                                    </span>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex align-items-center flex-wrap gap-1">
                                            <span class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                            <?php if ($svc_design): ?>
                                                <span class="badge bg-success" style="font-size:.65rem;" title="Selected design"><i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($svc_design['name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($service['category'])): ?>
                                                <span class="badge bg-secondary" style="font-size:.65rem;"><?php echo htmlspecialchars($service['category']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($svc_is_admin): ?>
                                                <span class="badge bg-warning text-dark" style="font-size:.6rem;" title="Added by admin" aria-label="Service added by administrator"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($service['description'])): ?>
                                            <small class="text-muted d-block" style="font-size:.72rem;"><?php echo htmlspecialchars($service['description']); ?></small>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap" style="font-size:.8rem;">
                                            <span class="text-muted">Qty: <strong><?php echo $service_qty; ?></strong></span>
                                            <span class="text-primary fw-bold"><?php echo formatCurrency($service_price); ?></span>
                                            <span class="text-success fw-bold">= <?php echo formatCurrency($service_total); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 d-flex align-items-center gap-1">
                                        <!-- Plus button: toggles inline vendor assignment form (hidden once a vendor is assigned) -->
                                        <?php if (empty($svc_vendors)): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary py-0 px-2 inline-va-toggle"
                                                title="Assign vendor for this service"
                                                aria-expanded="false"
                                                aria-controls="inline-va-<?php echo $svc_id; ?>"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#inline-va-<?php echo $svc_id; ?>"
                                                data-vendor-type-slug="<?php echo htmlspecialchars($svc_vt_slug); ?>"
                                                data-service-category="<?php echo htmlspecialchars($service['category'] ?? ''); ?>"
                                                data-service-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                                data-service-price="<?php echo $service_price; ?>">
                                            <i class="fas fa-plus" style="font-size:.75rem;"></i>
                                        </button>
                                        <?php endif; ?>
                                        <!-- Remove button (admin-added services only) -->
                                        <?php if ($svc_is_admin): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this service from the booking?');">
                                            <input type="hidden" name="action" value="delete_admin_service">
                                            <input type="hidden" name="service_id" value="<?php echo $svc_id; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Remove service" style="font-size:.75rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Inline vendor assignment form (collapsed, toggled by + button) -->
                                <div class="collapse" id="inline-va-<?php echo $svc_id; ?>">
                                    <div class="px-2 py-2 border-top" style="background:#eef4ff;">
                                        <form method="POST" action="" class="inline-va-form">
                                            <input type="hidden" name="action" value="add_vendor_assignment">
                                            <input type="hidden" name="booking_service_id" value="<?php echo $svc_id; ?>">
                                            <input type="hidden" name="task_description" value="<?php echo htmlspecialchars($service['service_name']); ?>">
                                            <input type="hidden" name="is_manual_vendor" value="<?php echo empty($vendor_types_available) ? '1' : '0'; ?>" class="inline-va-is-manual-flag">
                                            <!-- Mode toggle -->
                                            <div class="mb-2 d-flex align-items-center gap-2" style="font-size:.75rem;">
                                                <?php if (!empty($vendor_types_available)): ?>
                                                <button type="button" class="btn btn-sm py-0 px-2 btn-primary inline-va-mode-btn active"
                                                        data-mode="system" style="font-size:.72rem;">
                                                    <i class="fas fa-user-tie me-1"></i>System Vendor
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm py-0 px-2 <?php echo empty($vendor_types_available) ? 'btn-primary active' : 'btn-outline-secondary'; ?> inline-va-mode-btn"
                                                        data-mode="manual" style="font-size:.72rem;">
                                                    <i class="fas fa-pencil-alt me-1"></i>Manual Vendor
                                                </button>
                                            </div>
                                            <div class="row g-2 align-items-end">
                                                <!-- System vendor dropdown (hidden when manual mode) -->
                                                <?php if (!empty($vendor_types_available)): ?>
                                                <div class="col inline-va-vendor-wrap inline-va-system-fields">
                                                    <label class="form-label mb-1 small fw-semibold" style="font-size:.72rem;">Vendor <span class="text-danger">*</span></label>
                                                    <select name="vendor_id" class="form-select form-select-sm inline-va-vendor-select"
                                                            data-vendor-type-slug="<?php echo htmlspecialchars($svc_vt_slug); ?>"
                                                            data-service-category="<?php echo htmlspecialchars($service['category'] ?? ''); ?>"
                                                            required style="font-size:.78rem;">
                                                        <option value="">&#x2014; Select Vendor &#x2014;</option>
                                                    </select>
                                                    <div class="inline-va-vendor-photo-list mt-2"></div>
                                                </div>
                                                <?php endif; ?>
                                                <!-- Manual vendor fields (hidden by default when system vendors exist) -->
                                                <div class="col inline-va-manual-fields<?php echo !empty($vendor_types_available) ? ' d-none' : ''; ?>">
                                                    <div class="row g-2">
                                                        <div class="col">
                                                            <label class="form-label mb-1 small fw-semibold" style="font-size:.72rem;">Vendor Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="manual_vendor_name" class="form-control form-control-sm"
                                                                   placeholder="Enter vendor name"
                                                                   <?php echo empty($vendor_types_available) ? 'required' : ''; ?>
                                                                   style="font-size:.78rem;">
                                                        </div>
                                                        <div class="col-auto">
                                                            <label class="form-label mb-1 small fw-semibold" style="font-size:.72rem;">Phone</label>
                                                            <input type="text" name="manual_vendor_phone" class="form-control form-control-sm"
                                                                   placeholder="Phone number"
                                                                   style="width:130px;font-size:.78rem;">
                                                        </div>
                                                        <div class="col-auto">
                                                            <label class="form-label mb-1 small fw-semibold" style="font-size:.72rem;">Type</label>
                                                            <select name="manual_vendor_type" class="form-select form-select-sm" style="font-size:.78rem;min-width:110px;">
                                                                <option value="">&#x2014; Type &#x2014;</option>
                                                                <?php foreach (getVendorTypes() as $vt): ?>
                                                                <option value="<?php echo htmlspecialchars($vt['slug']); ?>"><?php echo htmlspecialchars($vt['label']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-user-plus me-1"></i>Assign
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <!-- Existing vendor assignments shown below the service -->
                                <?php if (!empty($svc_vendors)): ?>
                                <div class="px-2 pb-2 pt-1" style="background:#f8fff9;">
                                    <?php foreach ($svc_vendors as $idx => $va):
                                        $va_is_manual = empty($va['vendor_id']);
                                        $va_photo_url = $va_is_manual ? '' : ($vendor_primary_photos[$va['vendor_id']] ?? '');
                                        $is_last_va   = ($idx === count($svc_vendors) - 1);
                                    ?>
                                    <div class="d-flex align-items-center gap-2 py-1 flex-wrap<?php echo $is_last_va ? '' : ' border-bottom'; ?>" style="font-size:.8rem;">
                                        <?php if (!empty($va_photo_url)): ?>
                                            <div class="photo-zoom-wrap vendor-zoom flex-shrink-0" style="width:30px;height:30px;">
                                                <img src="<?php echo htmlspecialchars($va_photo_url); ?>"
                                                     alt="<?php echo htmlspecialchars($va['vendor_name']); ?>"
                                                     class="rounded-circle"
                                                     style="width:30px;height:30px;object-fit:cover;">
                                                <div class="photo-zoom-popup rounded-circle">
                                                    <img src="<?php echo htmlspecialchars($va_photo_url); ?>"
                                                         alt="<?php echo htmlspecialchars($va['vendor_name']); ?>">
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="d-inline-flex align-items-center justify-content-center <?php echo $va_is_manual ? 'bg-warning text-dark' : 'bg-secondary text-white'; ?> rounded-circle flex-shrink-0"
                                                  style="width:30px;height:30px;font-size:.7rem;">
                                                <i class="fas <?php echo $va_is_manual ? 'fa-pencil-alt' : 'fa-user'; ?>"></i>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($va['vendor_name']); ?></span>
                                        <?php if ($va_is_manual): ?>
                                            <span class="badge bg-warning text-dark border" style="font-size:.62rem;" title="Manually entered vendor"><i class="fas fa-pencil-alt me-1"></i>Manual</span>
                                            <?php if (!empty($va['manual_vendor_type'])): ?>
                                            <span class="badge bg-light text-secondary border" style="font-size:.62rem;"><?php echo htmlspecialchars(getVendorTypeLabel($va['manual_vendor_type'])); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border" style="font-size:.62rem;"><?php echo htmlspecialchars(getVendorTypeLabel($va['vendor_type'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($va['task_description'])): ?>
                                            <span class="text-muted" title="<?php echo htmlspecialchars($va['notes'] ?? ''); ?>"><?php echo htmlspecialchars($va['task_description']); ?></span>
                                        <?php endif; ?>
                                        <div class="ms-auto d-flex align-items-center gap-1 flex-shrink-0">
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="update_vendor_assignment_status">
                                                <input type="hidden" name="assignment_id" value="<?php echo $va['id']; ?>">
                                                <select name="assignment_status" class="form-select form-select-sm py-0 d-inline-block w-auto"
                                                        style="font-size:.7rem;" onchange="this.form.submit()">
                                                    <?php foreach (['assigned', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                                                        <option value="<?php echo $s; ?>" <?php echo ($va['status'] === $s) ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($s); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <?php if (!empty($va['vendor_phone'])): ?>
                                                <?php $va_wa_url = buildVendorAssignmentWhatsAppUrl($va['vendor_name'], $va['vendor_phone'], $booking, $va_is_manual ? ($va['manual_vendor_type'] ?? '') : ($va['vendor_type'] ?? ''), $svc_design); ?>
                                                <?php if (!empty($va_wa_url)): ?>
                                                <a href="<?php echo htmlspecialchars($va_wa_url); ?>" target="_blank" rel="noopener noreferrer"
                                                   class="btn btn-sm btn-outline-success py-0 px-1" title="Notify via WhatsApp" style="font-size:.7rem;">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Remove this vendor assignment?');">
                                                <input type="hidden" name="action" value="delete_vendor_assignment">
                                                <input type="hidden" name="assignment_id" value="<?php echo $va['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove" style="font-size:.7rem;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <?php if ($all_display_services_count > 1): ?>
                            <div class="text-end small fw-bold text-muted border-top pt-1 mt-1">
                                Total: <strong class="text-success"><?php echo formatCurrency($all_display_services_total); ?></strong>
                            </div>
                            <?php endif; ?>

                            <!-- Add Service form (catalog + manual) at bottom of services list -->
                            <div class="border-top pt-2 mt-2">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="fw-semibold text-muted" style="font-size:.78rem;">Add Service:</span>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-info active" id="tab-catalog-btn"
                                                onclick="switchAddServiceTab('catalog')">
                                            <i class="fas fa-list me-1"></i>From Catalog
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" id="tab-manual-btn"
                                                onclick="switchAddServiceTab('manual')">
                                            <i class="fas fa-pen me-1"></i>Manual Entry
                                        </button>
                                    </div>
                                </div>

                                <!-- Catalog Pane -->
                                <div id="add-service-catalog-pane">
                                <?php if (!empty($available_services)): ?>
                                <?php
                                // Build a map of service designs keyed by service ID for JS injection
                                $catalog_services_designs_map = [];
                                foreach ($available_services as $_cs) {
                                    if (!empty($_cs['designs'])) {
                                        $catalog_services_designs_map[intval($_cs['id'])] = array_map(function($d) {
                                            return [
                                                'id'    => intval($d['id']),
                                                'name'  => $d['name'],
                                                'price' => floatval($d['price']),
                                                'photo' => $d['photo'] ?? '',
                                                'description' => $d['description'] ?? '',
                                            ];
                                        }, $_cs['designs']);
                                    }
                                }
                                ?>
                                <!-- Design card styles (matching booking-step4.php) -->
                                <style>
                                .catalog-design-checkbox-card {
                                    cursor: pointer;
                                    transition: border-color .2s, box-shadow .2s;
                                    border: 2px solid #dee2e6;
                                }
                                .catalog-design-select-label:hover .catalog-design-checkbox-card,
                                .catalog-design-checkbox-card:hover {
                                    border-color: #198754;
                                    box-shadow: 0 0 0 3px rgba(25,135,84,.15);
                                }
                                .catalog-design-checkbox-card.selected-design {
                                    border-color: #198754 !important;
                                    border-width: 3px !important;
                                    box-shadow: 0 0 0 3px rgba(25,135,84,.2);
                                    background-color: rgba(25,135,84,.04);
                                }
                                .catalog-design-check-overlay {
                                    display: none;
                                    z-index: 2;
                                }
                                .catalog-design-checkbox-card.selected-design .catalog-design-check-overlay {
                                    display: block;
                                }
                                .catalog-design-card-img {
                                    height: 90px;
                                    object-fit: cover;
                                }
                                .catalog-design-select-label {
                                    cursor: pointer;
                                    margin: 0;
                                }
                                </style>
                                <form method="POST" action="" id="add-catalog-service-form">
                                    <input type="hidden" name="action" value="add_catalog_service">
                                    <input type="hidden" name="catalog_vendor_id" id="catalog-vendor-id-input" value="">
                                    <input type="hidden" name="catalog_design_id" id="catalog-design-id-input" value="">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-auto">
                                            <label class="form-label mb-1 small fw-semibold">Service <span class="text-danger">*</span></label>
                                            <?php
                                            $services_by_cat = [];
                                            foreach ($available_services as $svc) {
                                                $cat = !empty($svc['vendor_type_label']) ? $svc['vendor_type_label'] : (!empty($svc['category']) ? $svc['category'] : 'General');
                                                $services_by_cat[$cat][] = $svc;
                                            }
                                            ?>
                                            <select class="form-select form-select-sm" name="catalog_service_id" id="catalog-service-select"
                                                    required style="min-width:180px;">
                                                <option value="">&#x2014; Select Service &#x2014;</option>
                                                <?php foreach ($services_by_cat as $cat => $svcs): ?>
                                                    <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                                                        <?php foreach ($svcs as $svc): ?>
                                                        <option value="<?php echo intval($svc['id']); ?>"
                                                                data-formatted-price="<?php echo htmlspecialchars(formatCurrency($svc['price']), ENT_QUOTES); ?>"
                                                                data-description="<?php echo htmlspecialchars($svc['description'] ?? '', ENT_QUOTES); ?>"
                                                                data-vendor-type-slug="<?php echo htmlspecialchars($svc['vendor_type_slug'] ?? '', ENT_QUOTES); ?>"
                                                                data-has-designs="<?php echo $svc['has_designs'] ? '1' : '0'; ?>">
                                                            <?php echo htmlspecialchars($svc['name']); ?><?php echo $svc['has_designs'] ? ' &#x2605;' : (' &#x2014; ' . formatCurrency($svc['price'])); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <label class="form-label mb-1 small fw-semibold">Qty</label>
                                            <input type="number" class="form-control form-control-sm" name="quantity"
                                                   min="1" value="1" style="width:65px;">
                                        </div>
                                        <div class="col-auto">
                                            <label class="form-label mb-1 small fw-semibold">Price</label>
                                            <input type="text" class="form-control form-control-sm bg-light" id="catalog-service-price-preview"
                                                   readonly style="width:110px;" placeholder="&#x2014;">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-primary" id="catalog-add-btn">
                                                <i class="fas fa-plus me-1"></i>Add
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Design selection grid: shown when selected service has designs -->
                                    <div id="catalog-design-grid-wrap" class="d-none mt-2 p-2 rounded" style="background:#f8f9fa;border:1px solid #dee2e6;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-success" style="font-size:.72rem;"><i class="fas fa-palette me-1"></i>Select Design</span>
                                            <small class="text-muted" style="font-size:.72rem;">Choose a design — its price will be applied</small>
                                        </div>
                                        <div class="row g-2" id="catalog-design-grid"></div>
                                    </div>
                                    <!-- Vendor selection row: shown when selected service has linked vendors -->
                                    <div class="row g-2 align-items-center mt-1 d-none" id="catalog-vendor-row">
                                        <div class="col-auto">
                                            <span class="badge bg-info text-dark" style="font-size:.72rem;">
                                                <i class="fas fa-user-tie me-1"></i>Assign Vendor
                                            </span>
                                        </div>
                                        <div class="col-auto">
                                            <select class="form-select form-select-sm" id="catalog-vendor-select" style="min-width:180px;">
                                                <option value="">&#x2014; No vendor (skip) &#x2014;</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <small class="text-muted" style="font-size:.72rem;"><i class="fas fa-info-circle me-1"></i>Vendors available for this service type</small>
                                        </div>
                                    </div>
                                </form>
                                <script>
                                (function() {
                                    var catalogVendorsByType  = <?php echo json_encode($vendors_by_type, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                                    var catalogServiceDesigns = <?php echo json_encode($catalog_services_designs_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                                    var uploadUrlBase         = <?php echo json_encode(rtrim(UPLOAD_URL, '/'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

                                    var selectedDesignId    = 0;
                                    var selectedDesignPrice = '';

                                    function buildCatalogDesignGrid(serviceId) {
                                        var designs = catalogServiceDesigns[serviceId] || [];
                                        var grid    = document.getElementById('catalog-design-grid');
                                        var wrap    = document.getElementById('catalog-design-grid-wrap');
                                        var addBtn  = document.getElementById('catalog-add-btn');
                                        if (!grid) return;

                                        // Reset selection
                                        selectedDesignId    = 0;
                                        selectedDesignPrice = '';
                                        document.getElementById('catalog-design-id-input').value = '';
                                        document.getElementById('catalog-service-price-preview').value = '';
                                        if (addBtn) addBtn.disabled = true;

                                        if (designs.length === 0) {
                                            wrap.classList.add('d-none');
                                            return;
                                        }

                                        var html = '';
                                        designs.forEach(function(d) {
                                            var imgHtml = d.photo
                                                ? '<img src="' + uploadUrlBase + '/' + d.photo + '" class="card-img-top catalog-design-card-img" alt="' + escHtml(d.name) + '">'
                                                : '<div class="d-flex align-items-center justify-content-center bg-light catalog-design-card-img"><i class="fas fa-image fa-2x text-muted"></i></div>';

                                            html += '<div class="col-6 col-md-3 col-xl-2">';
                                            html += '<label class="catalog-design-select-label d-block h-100">';
                                            html += '<div class="card catalog-design-checkbox-card h-100 position-relative" id="c-design-card-' + d.id + '" onclick="selectCatalogDesign(' + d.id + ')" tabindex="0" role="radio" aria-checked="false" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();selectCatalogDesign(' + d.id + ')}">';
                                            html += '<div class="catalog-design-check-overlay position-absolute top-0 end-0 m-1">';
                                            html += '<span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-check me-1"></i>Selected</span>';
                                            html += '</div>';
                                            html += imgHtml;
                                            html += '<div class="card-body p-2 text-center">';
                                            html += '<div class="fw-semibold small">' + escHtml(d.name) + '</div>';
                                            html += '<div class="text-success small fw-bold">' + escHtml(formatDesignPrice(d.price)) + '</div>';
                                            if (d.description) {
                                                html += '<div class="text-muted mt-1" style="font-size:.68rem;">' + escHtml(d.description) + '</div>';
                                            }
                                            html += '</div></div></label></div>';
                                        });
                                        grid.innerHTML = html;
                                        wrap.classList.remove('d-none');
                                    }

                                    window.selectCatalogDesign = function(designId) {
                                        var designs = catalogServiceDesigns[currentCatalogServiceId()] || [];
                                        var design  = null;
                                        designs.forEach(function(d) { if (d.id === designId) design = d; });
                                        if (!design) return;

                                        // Update card states
                                        document.querySelectorAll('[id^="c-design-card-"]').forEach(function(card) {
                                            card.classList.remove('selected-design');
                                            card.setAttribute('aria-checked', 'false');
                                        });
                                        var selectedCard = document.getElementById('c-design-card-' + designId);
                                        if (selectedCard) {
                                            selectedCard.classList.add('selected-design');
                                            selectedCard.setAttribute('aria-checked', 'true');
                                        }

                                        // Update state & form fields
                                        selectedDesignId    = designId;
                                        selectedDesignPrice = formatDesignPrice(design.price);
                                        document.getElementById('catalog-design-id-input').value        = designId;
                                        document.getElementById('catalog-service-price-preview').value  = selectedDesignPrice;
                                        var addBtn = document.getElementById('catalog-add-btn');
                                        if (addBtn) addBtn.disabled = false;
                                        // Remove validation error message once a design is chosen
                                        var errMsg = document.getElementById('catalog-design-required-msg');
                                        if (errMsg) errMsg.remove();
                                    };

                                    function currentCatalogServiceId() {
                                        var sel = document.getElementById('catalog-service-select');
                                        return sel ? parseInt(sel.value) || 0 : 0;
                                    }

                                    function escHtml(str) {
                                        var d = document.createElement('div');
                                        d.appendChild(document.createTextNode(String(str)));
                                        return d.innerHTML;
                                    }

                                    function formatDesignPrice(price) {
                                        // Use same currency formatting as PHP formatCurrency
                                        var num = parseFloat(price) || 0;
                                        return '<?php echo addslashes(getSetting('currency', 'NPR')); ?> ' + num.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }

                                    function updateCatalogServicePreview(select) {
                                        var opt        = select.options[select.selectedIndex];
                                        var serviceId  = parseInt(opt.value) || 0;
                                        var hasDesigns = opt.dataset.hasDesigns === '1';

                                        // Reset design selection
                                        selectedDesignId    = 0;
                                        selectedDesignPrice = '';
                                        document.getElementById('catalog-design-id-input').value = '';

                                        var addBtn = document.getElementById('catalog-add-btn');

                                        if (hasDesigns && serviceId > 0) {
                                            // Services with designs: show design grid, price shown after design selection
                                            document.getElementById('catalog-service-price-preview').value = '';
                                            if (addBtn) addBtn.disabled = true;
                                            buildCatalogDesignGrid(serviceId);
                                        } else {
                                            // Regular service: show price immediately, hide design grid
                                            document.getElementById('catalog-service-price-preview').value = opt.dataset.formattedPrice || '';
                                            var wrap = document.getElementById('catalog-design-grid-wrap');
                                            if (wrap) { wrap.classList.add('d-none'); document.getElementById('catalog-design-grid').innerHTML = ''; }
                                            if (addBtn) addBtn.disabled = false;
                                        }

                                        // Vendor row
                                        var vendorTypeSlug = opt.dataset.vendorTypeSlug || '';
                                        var vendorRow      = document.getElementById('catalog-vendor-row');
                                        var vendorSelect   = document.getElementById('catalog-vendor-select');
                                        var vendorInput    = document.getElementById('catalog-vendor-id-input');

                                        vendorSelect.innerHTML = '<option value="">\u2014 No vendor (skip) \u2014</option>';
                                        vendorInput.value = '';

                                        if (vendorTypeSlug && catalogVendorsByType[vendorTypeSlug] && catalogVendorsByType[vendorTypeSlug].length > 0) {
                                            catalogVendorsByType[vendorTypeSlug].forEach(function(v) {
                                                var o = document.createElement('option');
                                                o.value = v.id;
                                                var label = v.name + (v.city ? ' (' + v.city + ')' : '');
                                                if (v.is_unapproved) {
                                                    label += ' \u2014 \u26a0\ufe0f Unverified Vendor';
                                                    o.style.color = '#856404';
                                                }
                                                o.textContent = label;
                                                vendorSelect.appendChild(o);
                                            });
                                            vendorRow.classList.remove('d-none');
                                        } else {
                                            vendorRow.classList.add('d-none');
                                        }
                                    }

                                    var serviceSelect = document.getElementById('catalog-service-select');
                                    if (serviceSelect) {
                                        serviceSelect.addEventListener('change', function() {
                                            updateCatalogServicePreview(this);
                                        });
                                    }

                                    var vendorSelect = document.getElementById('catalog-vendor-select');
                                    if (vendorSelect) {
                                        vendorSelect.addEventListener('change', function() {
                                            var input = document.getElementById('catalog-vendor-id-input');
                                            if (input) input.value = this.value;
                                        });
                                    }

                                    // Prevent form submit if a design-service is selected but no design chosen
                                    var form = document.getElementById('add-catalog-service-form');
                                    if (form) {
                                        form.addEventListener('submit', function(e) {
                                            var sel = document.getElementById('catalog-service-select');
                                            var opt = sel ? sel.options[sel.selectedIndex] : null;
                                            if (opt && opt.dataset.hasDesigns === '1' && !document.getElementById('catalog-design-id-input').value) {
                                                e.preventDefault();
                                                var wrap = document.getElementById('catalog-design-grid-wrap');
                                                if (wrap && !document.getElementById('catalog-design-required-msg')) {
                                                    var msg = document.createElement('div');
                                                    msg.id = 'catalog-design-required-msg';
                                                    msg.className = 'text-danger small mt-1';
                                                    msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Please select a design before adding this service.';
                                                    wrap.appendChild(msg);
                                                }
                                            }
                                        });
                                    }
                                })();
                                </script>
                                <?php else: ?>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-info-circle me-1 text-info"></i>
                                    No active services in catalog. <a href="<?php echo BASE_URL; ?>/admin/services/index.php">Manage services</a> to add catalog entries.
                                </p>
                                <?php endif; ?>
                                </div><!-- /#add-service-catalog-pane -->

                                <!-- Manual Entry Pane -->
                                <div id="add-service-manual-pane" style="display:none;">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="add_admin_service">
                                    <div class="row g-1 align-items-end">
                                        <div class="col">
                                            <input type="text" class="form-control form-control-sm" name="service_name"
                                                   placeholder="Service Name *" required style="font-size:.78rem;">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control form-control-sm" name="description"
                                                   placeholder="Description" style="font-size:.78rem;">
                                        </div>
                                        <div class="col-auto">
                                            <input type="number" class="form-control form-control-sm" name="quantity"
                                                   min="1" value="1" style="width:55px;font-size:.78rem;" required
                                                   title="Quantity" aria-label="Quantity">
                                        </div>
                                        <div class="col-auto">
                                            <input type="number" class="form-control form-control-sm" name="price"
                                                   min="0" step="0.01" style="width:90px;font-size:.78rem;" placeholder="Price *" required
                                                   aria-label="Price">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-success btn-sm" style="font-size:.78rem;">
                                                <i class="fas fa-plus me-1"></i>Add
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                </div><!-- /#add-service-manual-pane -->

                                <script>
                                function switchAddServiceTab(tab) {
                                    var catalogPane = document.getElementById('add-service-catalog-pane');
                                    var manualPane  = document.getElementById('add-service-manual-pane');
                                    var catalogBtn  = document.getElementById('tab-catalog-btn');
                                    var manualBtn   = document.getElementById('tab-manual-btn');
                                    if (tab === 'catalog') {
                                        catalogPane.style.display = 'block';
                                        manualPane.style.display  = 'none';
                                        catalogBtn.classList.add('active');
                                        manualBtn.classList.remove('active');
                                    } else {
                                        catalogPane.style.display = 'none';
                                        manualPane.style.display  = 'block';
                                        manualBtn.classList.add('active');
                                        catalogBtn.classList.remove('active');
                                    }
                                }
                                </script>
                            </div><!-- /add service form -->

                        </div><!-- /#unified-services -->
                        <?php endif; ?>

            </div>
        </div><!-- /section-services -->


        <!-- ===== PAYMENTS SECTION ===== -->
        <div class="card shadow-sm border-0 mb-3 booking-section-card" id="section-payments">
            <div class="card-header booking-section-header d-flex align-items-center">
                <i class="fas fa-credit-card me-2 text-success"></i>
                <span class="fw-bold">Payments</span>
                <?php if ($tab_payments_count > 0): ?>
                    <span class="badge bg-success ms-2"><?php echo $tab_payments_count; ?></span>
                <?php endif; ?>
                <button type="button" class="btn btn-success btn-sm ms-auto"
                        data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="fas fa-plus me-1"></i> Record Payment
                </button>
            </div>
            <div class="p-3">

                        <!-- Payment Methods -->
                        <?php if (count($booking_payment_methods) > 0): ?>
                        <div class="mb-4">
                            <div class="section-label-premium mb-3">
                                <span class="section-dot bg-primary"></span>
                                <span class="fw-bold text-uppercase text-muted">Payment Methods</span>
                            </div>
                            <?php foreach ($booking_payment_methods as $method): ?>
                            <div class="payment-method-item mb-3 pb-3 <?php echo ($method !== end($booking_payment_methods)) ? 'border-bottom' : ''; ?>">
                                <h6 class="fw-bold text-dark mb-2">
                                    <i class="fas fa-money-check-alt text-primary me-2"></i>
                                    <?php echo htmlspecialchars($method['name']); ?>
                                </h6>
                                <div class="row g-3">
                                    <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                                    <div class="col-md-4">
                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>"
                                             alt="<?php echo htmlspecialchars($method['name']); ?> QR Code"
                                             class="img-fluid rounded shadow-sm"
                                             style="max-width: 180px; border: 2px solid #dee2e6; padding: 8px; background: white;">
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['bank_details'])): ?>
                                    <div class="<?php echo !empty($method['qr_code']) ? 'col-md-8' : 'col-12'; ?>">
                                        <div class="alert alert-light mb-0 border">
                                            <small class="text-muted fw-semibold d-block mb-1">Bank Details:</small>
                                            <pre class="mb-0 text-dark" style="font-family: monospace; font-size: 0.82rem; white-space: pre-wrap;"><?php echo htmlspecialchars($method['bank_details']); ?></pre>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Transactions -->
                        <?php if ($tab_payments_count > 0): ?>
                        <div class="section-label-premium mb-3">
                            <span class="section-dot bg-success"></span>
                            <span class="fw-bold text-uppercase text-muted">Payment Transactions</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 border rounded">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold">Date</th>
                                        <th class="fw-semibold">Method</th>
                                        <th class="fw-semibold">Txn ID</th>
                                        <th class="fw-semibold text-end">Amount</th>
                                        <th class="fw-semibold text-center">Status</th>
                                        <th class="fw-semibold text-center">Slip</th>
                                        <th class="fw-semibold text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_transactions as $payment): ?>
                                    <tr id="payment-row-<?php echo (int)$payment['id']; ?>">
                                        <td>
                                            <span class="fw-semibold small"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                            <br><small class="text-muted"><?php echo convertToNepaliDate($payment['payment_date']); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                        </td>
                                        <td class="small"><?php echo !empty($payment['payment_method_name']) ? htmlspecialchars($payment['payment_method_name']) : '<em class="text-muted">N/A</em>'; ?></td>
                                        <td>
                                            <span class="badge bg-secondary small">
                                                <?php echo !empty($payment['transaction_id']) ? htmlspecialchars($payment['transaction_id']) : 'N/A'; ?>
                                            </span>
                                            <?php if (!empty($payment['notes'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo formatCurrency($payment['paid_amount']); ?></strong>
                                        </td>
                                        <td class="text-center" id="payment-status-cell-<?php echo (int)$payment['id']; ?>">
                                            <span class="badge bg-<?php
                                                echo $payment['payment_status'] == 'verified' ? 'success' :
                                                    ($payment['payment_status'] == 'pending' ? 'warning' : 'danger');
                                            ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($payment['payment_slip']) && validateUploadedFilePath($payment['payment_slip'])): ?>
                                                <button type="button" class="btn btn-sm btn-info py-0 px-2"
                                                        data-bs-toggle="modal" data-bs-target="#slipModal<?php echo $payment['id']; ?>">
                                                    <i class="fas fa-eye small"></i>
                                                </button>
                                                <!-- Payment Slip Modal -->
                                                <div class="modal fade" id="slipModal<?php echo $payment['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Payment Slip</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-center p-4">
                                                                <div class="mb-3">
                                                                    <span class="badge bg-secondary">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></span>
                                                                </div>
                                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>"
                                                                     alt="Payment Slip" class="img-fluid rounded shadow" style="max-height: 70vh;">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <a href="<?php echo UPLOAD_URL . htmlspecialchars($payment['payment_slip']); ?>"
                                                                   download class="btn btn-success">
                                                                    <i class="fas fa-download me-1"></i> Download
                                                                </a>
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="white-space:nowrap;">
                                            <?php if ($payment['payment_status'] !== 'verified'): ?>
                                                <button type="button" class="btn btn-success btn-sm py-0 px-1 me-1 payment-action-btn"
                                                        data-payment-id="<?php echo (int)$payment['id']; ?>"
                                                        data-action-status="verified"
                                                        title="Verify Payment">
                                                    <i class="fas fa-check small"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($payment['payment_status'] !== 'rejected'): ?>
                                                <button type="button" class="btn btn-danger btn-sm py-0 px-1 payment-action-btn"
                                                        data-payment-id="<?php echo (int)$payment['id']; ?>"
                                                        data-action-status="rejected"
                                                        title="Reject Payment">
                                                    <i class="fas fa-times small"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($payment['payment_status'] === 'rejected'): ?>
                                                <button type="button" class="btn btn-warning btn-sm py-0 px-1 payment-action-btn"
                                                        data-payment-id="<?php echo (int)$payment['id']; ?>"
                                                        data-action-status="pending"
                                                        title="Reset to Pending">
                                                    <i class="fas fa-undo small"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light border-top border-2">
                                        <td colspan="3" class="text-end fw-bold small">Total Paid:</td>
                                        <td colspan="4" class="text-end">
                                            <strong class="text-success fs-6"><?php echo formatCurrency($total_paid); ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end small">Grand Total:</td>
                                        <td colspan="4" class="text-end">
                                            <strong><?php echo formatCurrency($booking['grand_total']); ?></strong>
                                        </td>
                                    </tr>
                                    <?php if ($booking['payment_status'] !== 'paid'): ?>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end small">Balance Due:</td>
                                        <td colspan="4" class="text-end">
                                            <strong class="text-danger"><?php echo formatCurrency($balance_due); ?></strong>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end small">Payment Status:</td>
                                        <td colspan="4" class="text-end">
                                            <strong class="text-success"><i class="fas fa-check-circle me-1"></i>Fully Paid</strong>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-receipt fa-2x mb-2 d-block opacity-50"></i>
                            <small>No payment transactions yet.</small>
                        </div>
                        <?php endif; ?>

            </div>
        </div><!-- /section-payments -->
    </div><!-- /col-lg-8 -->

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="recordPaymentModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Record Payment Received
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="recordPaymentForm" enctype="multipart/form-data" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="record">
                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token_value, ENT_QUOTES, 'UTF-8'); ?>">

                    <div id="recordPaymentAlert" class="alert d-none mb-3" role="alert"></div>

                    <!-- Amount -->
                    <div class="mb-3">
                        <label for="rp_paid_amount" class="form-label fw-semibold">
                            Amount Received <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo htmlspecialchars(getSetting('currency', 'NPR'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <input type="number" class="form-control" id="rp_paid_amount" name="paid_amount"
                                   min="0.01" step="0.01" placeholder="0.00" required>
                        </div>
                        <?php if ($balance_due > 0): ?>
                            <div class="form-text">Balance due: <strong class="text-danger"><?php echo formatCurrency($balance_due); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label for="rp_payment_method_id" class="form-label fw-semibold">Payment Method</label>
                        <select class="form-select" id="rp_payment_method_id" name="payment_method_id">
                            <option value="">— Select method (optional) —</option>
                            <?php foreach ($active_payment_methods as $pm): ?>
                                <option value="<?php echo (int)$pm['id']; ?>"><?php echo htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Transaction ID -->
                    <div class="mb-3">
                        <label for="rp_transaction_id" class="form-label fw-semibold">Transaction ID / Reference</label>
                        <input type="text" class="form-control" id="rp_transaction_id" name="transaction_id"
                               placeholder="e.g. TXN123456 (optional)">
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="rp_notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="rp_notes" name="notes" rows="2"
                                  placeholder="Optional notes about this payment"></textarea>
                    </div>

                    <!-- Payment Slip -->
                    <div class="mb-1">
                        <label for="rp_payment_slip" class="form-label fw-semibold">Payment Slip / Screenshot</label>
                        <input type="file" class="form-control" id="rp_payment_slip" name="payment_slip"
                               accept="image/*">
                        <div class="form-text">Optional. JPEG, PNG, GIF, WebP — max 5 MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="recordPaymentSubmitBtn">
                        <i class="fas fa-save me-1"></i> Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div><!-- /recordPaymentModal -->

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <!-- Booking Overview Card -->
        <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px;">
            <div class="card-header bg-gradient-info text-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="fas fa-receipt me-2 opacity-75"></i> Booking Summary</h5>
            </div>
            <div class="card-body p-3">
                <!-- Status Summary Row -->
                <div class="mb-3 pb-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Booking Status</span>
                        <span class="badge bg-<?php echo $booking_status_color; ?>">
                            <i class="fas fa-circle-dot me-1"></i><?php echo $booking_status_display; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Payment Status</span>
                        <span class="badge bg-<?php echo $payment_status_color; ?>">
                            <i class="fas <?php echo $payment_status_icon; ?> me-1"></i><?php echo $payment_status_display; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">
                            <i class="far fa-calendar-plus me-1"></i>Booked On
                        </span>
                        <span class="small fw-semibold">
                            <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                            <br><small class="text-muted"><?php echo convertToNepaliDate($booking['created_at']); ?></small>
                        </span>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div>
                    <div class="payment-breakdown">
                        <div class="d-flex justify-content-between py-1 align-items-center">
                            <span class="text-muted small">Hall Price:</span>
                            <strong class="text-dark small"><?php echo formatCurrency($booking['hall_price']); ?></strong>
                        </div>
                        <?php if ($booking['menu_total'] > 0): ?>
                        <div class="d-flex justify-content-between py-1 align-items-center border-top">
                            <span class="text-muted small">Menu Total:</span>
                            <strong class="text-dark small"><?php echo formatCurrency($booking['menu_total']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['services_total'] > 0): ?>
                        <div class="d-flex justify-content-between py-1 align-items-center border-top">
                            <span class="text-muted small">Services Total:</span>
                            <strong class="text-dark small"><?php echo formatCurrency($booking['services_total']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($vendors_total > 0): ?>
                        <div class="d-flex justify-content-between py-1 align-items-center border-top">
                            <span class="text-muted small">Vendors Total:</span>
                            <strong class="text-dark small"><?php echo formatCurrency($vendors_total); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between py-1 align-items-center border-top">
                            <span class="text-muted small">Subtotal:</span>
                            <strong class="text-dark small"><?php echo formatCurrency($booking['subtotal']); ?></strong>
                        </div>
                        <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                        <div class="d-flex justify-content-between py-1 align-items-center border-top">
                            <span class="text-muted small">Tax (<?php echo getSetting('tax_rate', '13'); ?>%):</span>
                            <strong class="text-dark small"><?php echo formatCurrency($booking['tax_amount']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                            <span class="fw-bold text-dark">Grand Total:</span>
                            <span class="fw-bold text-success fs-5"><?php echo formatCurrency($booking['grand_total']); ?></span>
                        </div>
                    </div>

                    <!-- Venue Provider Payable & Payout Tracking -->
                    <?php if ($is_payout_flash && $success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert" style="font-size:.85rem;">
                        <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_payout_flash && $error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3 mb-0" role="alert" style="font-size:.85rem;">
                        <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <div class="mt-3 rounded border overflow-hidden" id="section-payout" style="font-size:.875rem;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-info bg-opacity-10 border-bottom">
                            <span class="fw-semibold text-info-emphasis">
                                <i class="fas fa-building me-2"></i>Venue Provider Payable
                                <small class="fw-normal text-muted d-block ms-4">Hall Price + Menu Total</small>
                            </span>
                            <strong class="text-info"><?php echo formatCurrency($venue_provider_payable); ?></strong>
                        </div>
                        <?php if ($venue_provider_payable > 0): ?>
                        <?php if ($venue_amount_paid_out > 0): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-success bg-opacity-10 border-bottom">
                            <span class="text-success small"><i class="fas fa-check-circle me-2"></i>Paid to Venue</span>
                            <strong class="text-success"><?php echo formatCurrency($venue_amount_paid_out); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 <?php echo $venue_due > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10'; ?> border-bottom">
                            <span class="fw-semibold <?php echo $venue_due > 0 ? 'text-warning-emphasis' : 'text-success'; ?>">
                                <i class="fas fa-<?php echo $venue_due > 0 ? 'clock' : 'check-double'; ?> me-2"></i>
                                <?php echo $venue_due > 0 ? 'Venue Due' : 'Venue Fully Paid'; ?>
                            </span>
                            <strong class="<?php echo $venue_due > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $venue_due > 0 ? formatCurrency($venue_due) : 'Cleared'; ?>
                            </strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Advance & Balance rows -->
                    <div class="mt-3 rounded border overflow-hidden" style="font-size:.875rem;">
                        <?php // $advance already calculated before the Quick Check Panel section ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-warning bg-opacity-10 border-bottom">
                            <span class="fw-semibold text-warning-emphasis">
                                <i class="fas fa-hand-holding-usd me-2"></i>Advance Required
                                <small class="fw-normal text-muted ms-1">(<?php echo htmlspecialchars($advance['percentage']); ?>%)</small>
                            </span>
                            <strong><?php echo formatCurrency($advance['amount']); ?></strong>
                        </div>
                        <?php if ($booking['advance_payment_received'] === 1): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-success bg-opacity-10 border-bottom">
                            <span class="fw-semibold text-success">
                                <i class="fas fa-check-circle me-2"></i>Advance Received
                            </span>
                            <strong class="text-success"><?php echo formatCurrency($advance_amount_received > 0 ? $advance_amount_received : $advance['amount']); ?></strong>
                        </div>
                        <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-danger bg-opacity-10 border-bottom">
                            <span class="fw-semibold text-danger">
                                <i class="fas fa-times-circle me-2"></i>Advance Not Received
                            </span>
                            <strong class="text-danger"><?php echo formatCurrency(0); ?></strong>
                        </div>
                        <?php endif; ?>
                        <!-- Balance Due / Fully Paid -->
                        <?php if ($booking['payment_status'] !== 'paid'): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-primary bg-opacity-10">
                            <div>
                                <span class="fw-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Balance Due</span>
                                <small class="text-muted d-block ms-4">
                                    <?php echo $booking['advance_payment_received'] === 1 ? 'After advance deduction' : 'Full amount outstanding'; ?>
                                </small>
                            </div>
                            <strong class="text-danger fs-5"><?php echo formatCurrency($balance_due); ?></strong>
                        </div>
                        <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-success bg-opacity-10">
                            <span class="fw-bold text-success"><i class="fas fa-check-double me-2"></i>Fully Paid</span>
                            <span class="badge bg-success px-3 py-2">All Cleared</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ====================================================
   BOOKING DETAIL — Professional Design System
   ==================================================== */

/* Header Colour Utilities (clean, muted tones) */
.bg-gradient-primary   { background: #1e3a5f; }
.bg-gradient-success   { background: #155a35; }
.bg-gradient-info      { background: #0c4a6e; }
.bg-gradient-warning   { background: #92400e; }
.bg-gradient-secondary { background: #374151; }

/* Cards */
.card { transition: box-shadow 0.2s ease; border-radius: 10px !important; }
.shadow-sm { box-shadow: 0 1px 4px rgba(0,0,0,.08), 0 2px 8px rgba(0,0,0,.04) !important; }

/* Quick Check */
.quick-check-item {
    padding: 0.5rem 0.65rem;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    transition: background .15s ease, border-color .15s ease;
}
.quick-check-item:hover {
    background: #f8fafc;
    border-color: #c7d3df;
}
/* Quick check section titles */
.quick-check-item .fw-bold.small.text-uppercase.text-muted {
    font-size: .75rem;
    letter-spacing: .05em;
}
.quick-check-currency { font-size: .75rem; }

/* Status Update Form */
.status-update-form .form-select {
    border: 1.5px solid #dee2e6;
    font-size: .875rem;
}
.status-update-form .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .2rem rgba(13,110,253,.12);
}

/* ─── Booking Section Cards ─── */
.booking-section-card {
    border: 1px solid #dde3ea !important;
    box-shadow: 0 1px 4px rgba(0,0,0,.06) !important;
    border-radius: 10px !important;
    overflow: hidden;
}

.booking-section-header {
    background: #f7f9fc;
    padding: .75rem 1.1rem;
    font-size: .9rem;
    color: #374151;
    border-bottom: 1px solid #e5eaf0;
    font-weight: 600;
}

/* ─── Section Label ─── */
.section-label-premium {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.section-label-premium .fw-bold {
    font-size: .78rem;
    letter-spacing: .06em;
}
.section-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ─── Compact Field Rows (Overview Tab) ─── */
.compact-field {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .5rem 0;
    border-bottom: 1px solid #f0f4f8;
    font-size: .875rem;
    line-height: 1.5;
}
.compact-field:last-child { border-bottom: none; }
.compact-field-label {
    flex: 0 0 110px;
    color: #64748b;
    font-size: .78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
    padding-top: .1rem;
}
.compact-field-value {
    flex: 1;
    color: #1e293b;
    word-break: break-word;
    font-weight: 500;
}

/* Border between two columns on md+ */
@media (min-width: 768px) {
    .border-end-md { border-right: 1px solid #e9ecef !important; }
}

/* ─── Payment Breakdown in Sidebar ─── */
.payment-breakdown {
    background: #f8fafc;
    padding: 1rem 1.1rem;
    border-radius: 8px;
    border: 1px solid #e8edf2;
}

/* ─── Service Description ─── */
.service-description {
    display: block;
    margin-top: .3rem;
    margin-left: 1.5rem;
    font-size: .8rem;
    color: #6c757d;
    line-height: 1.35;
}
.service-info-cell { vertical-align: top; }
.service-price-cell { vertical-align: top; }

/* ─── Payment method item ─── */
.payment-method-item { transition: background .15s ease; }
.payment-method-item:hover { background: #f8f9fa; border-radius: 8px; }

/* ─── Badges ─── */
.badge { font-weight: 500; letter-spacing: .03em; }

/* ─── Table tweaks ─── */
.table-hover tbody tr { transition: background .15s ease; }
.table th { font-size: .8rem; letter-spacing: .03em; }

/* ─── Service card row ─── */
.border.rounded.mb-2 { border-color: #e2e8f0 !important; }
.border.rounded.mb-2 .bg-light { background-color: #f8fafc !important; }

/* ─── Form controls sizing ─── */
.form-select-sm, .form-control-sm { font-size: .85rem; }

/* ─── Payment status select ─── */
.payment-status-select { font-size: .875rem; }

/* Print styles */
/* ============================================================
   PREMIUM INVOICE STYLES — Professional Luxury Design
   ============================================================ */

.print-invoice-only { display: none; }

/* ── Typography helpers ─────────────────────────────────────── */
.service-description-print {
    font-weight: 400;
    color: #5A7265;
    font-size: 8pt;
    line-height: 1.35;
    font-style: italic;
}
.service-category-print {
    font-weight: 700;
    color: #1B4332;
    font-size: 8.5px;
    margin-left: 5px;
    background: rgba(27,67,50,0.09);
    padding: 0 4px;
    border-radius: 2px;
}
.menu-items-print {
    font-weight: 400;
    color: #4A5D55;
    font-size: 8pt;
    line-height: 1.3;
}

/* ── Decorative top stripe ──────────────────────────────────── */
.invoice-top-stripe {
    height: 7px;
    background: linear-gradient(90deg, #1B4332 0%, #B7950B 40%, #F4D03F 50%, #B7950B 60%, #1B4332 100%);
    margin-bottom: 0;
}

/* ── Main Container ─────────────────────────────────────────── */
.invoice-container {
    font-family: 'Roboto', 'Arial', 'Helvetica Neue', sans-serif;
    color: #1A1A1A;
    line-height: 1.4;
    background: #FEFDF7;
}

/* ── Invoice Header ─────────────────────────────────────────── */
.invoice-header {
    padding-bottom: 0;
    margin-bottom: 7px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px 6px;
    background: #fff;
    border-left: 5px solid #1B4332;
    border-right: 5px solid #1B4332;
    border-top: 5px solid #1B4332;
}

.company-logo-space {
    text-align: right;
    flex-shrink: 0;
    margin-left: 12px;
}

.company-logo-img {
    max-width: 155px;
    max-height: 58px;
    object-fit: contain;
}

.logo-placeholder {
    border: 2px solid #B7950B;
    padding: 9px 22px;
    display: inline-block;
    font-weight: 900;
    font-size: 13.5px;
    color: #1B4332;
    background: linear-gradient(135deg, #FEFDF7 0%, #F4E9C0 100%);
    letter-spacing: 1px;
    font-family: 'Roboto', 'Arial', sans-serif;
}

.company-info {
    text-align: left;
    flex: 1;
}

.company-name {
    font-size: 21px;
    font-weight: 900;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 2.5px;
    color: #1B4332;
    line-height: 1.2;
    font-family: 'Roboto', 'Arial', sans-serif;
}

.company-details {
    font-size: 9.5px;
    margin: 3px 0 0;
    font-weight: 500;
    color: #2D6A4F;
    line-height: 1.45;
    letter-spacing: 0.2px;
}

/* Invoice Title Bar — dark green with gold text */
.invoice-title {
    text-align: center;
    padding: 6px 0;
    margin-top: 0;
    background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%);
    border-left: 5px solid #1B4332;
    border-right: 5px solid #1B4332;
    border-bottom: 5px solid #1B4332;
}

.invoice-title h2 {
    font-size: 11.5px;
    font-weight: 700;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 2.5px;
    color: #F4D03F;
    font-family: 'Roboto', 'Arial', sans-serif;
}

/* ── Invoice Details Bar ────────────────────────────────────── */
.invoice-details-bar {
    display: flex;
    justify-content: space-between;
    background: linear-gradient(135deg, #F5E9BE 0%, #EDD98A 100%);
    padding: 5px 10px;
    margin-bottom: 6px;
    border-left: 4px solid #B7950B;
    border-top: 1px solid #B7950B;
    border-right: 1px solid #B7950B;
    border-bottom: 1px solid #B7950B;
}

.invoice-detail-item {
    font-size: 9.5px;
    font-weight: 700;
    color: #1B4332;
}

.invoice-detail-item strong {
    font-weight: 900;
    color: #7D6608;
}

.invoice-detail-item small {
    color: #6B5520;
    font-size: 8.5pt;
}

/* ── Customer Section ───────────────────────────────────────── */
.customer-section {
    margin-bottom: 6px;
    border: 1px solid #A8C5B5;
    border-left: 4px solid #1B4332;
    padding: 6px 8px;
    background: #fff;
}

.customer-section h3 {
    font-size: 9.5px;
    font-weight: 900;
    margin: 0 0 5px 0;
    padding-bottom: 3px;
    border-bottom: 2px solid #B7950B;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #1B4332;
    font-family: 'Roboto', 'Arial', sans-serif;
}

.customer-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px;
}

.info-row {
    display: flex;
    font-size: 9.5px;
    font-weight: 500;
    line-height: 1.45;
}

.info-label {
    font-weight: 700;
    min-width: 92px;
    color: #1B4332;
}

.info-value {
    flex: 1;
    font-weight: 500;
    color: #1A1A1A;
}

/* ── Booking Table ──────────────────────────────────────────── */
.booking-table-section {
    margin-bottom: 6px;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
    font-weight: 500;
    line-height: 1.35;
}

.invoice-table th {
    background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%);
    color: #F4D03F;
    padding: 5px 7px;
    text-align: left;
    font-weight: 700;
    border: 1px solid #0D2B1F;
    font-size: 9.5px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.invoice-table td {
    padding: 4px 7px;
    border: 1px solid #C5D8CC;
    font-weight: 500;
    color: #1A1A1A;
}

.invoice-table tr:nth-child(even) td {
    background: #F2F9F5;
}

.invoice-table tr:nth-child(odd) td {
    background: #FFFFFF;
}

.invoice-table td strong {
    font-weight: 800;
    color: #1B4332;
}

.invoice-table .text-center { text-align: center; }
.invoice-table .text-right  { text-align: right; }

.invoice-table .subtotal-row td {
    background: linear-gradient(135deg, #F5E9BE 0%, #EDD98A 100%);
    font-weight: 900;
    font-size: 10px;
    color: #5A3E00;
    border-top: 2px solid #B7950B;
    border-bottom: 1px solid #B7950B;
    font-family: 'Roboto Mono', 'Courier New', Courier, monospace;
}

.invoice-table .total-row td {
    background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%);
    color: #F4D03F;
    font-weight: 900;
    font-size: 11px;
    padding: 5px 7px;
    letter-spacing: 0.4px;
    font-family: 'Roboto Mono', 'Courier New', Courier, monospace;
}

/* ── Payment Calculation Section ────────────────────────────── */
.payment-calculation-section {
    margin-bottom: 6px;
    border: 1px solid #A8C5B5;
    border-left: 4px solid #B7950B;
    padding: 6px 8px;
    background: linear-gradient(135deg, #FEFDF7 0%, #FAF5EB 100%);
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table td {
    padding: 2.5px 0;
    font-size: 9.5px;
    font-weight: 600;
    line-height: 1.45;
}

.payment-label {
    width: 55%;
    font-weight: 700;
    color: #1B4332;
}

.payment-value {
    text-align: right;
    font-size: 9.5px;
    font-weight: 700;
    color: #1A1A1A;
    font-family: 'Roboto Mono', 'Courier New', Courier, monospace;
}

.payment-value-words {
    text-align: right;
    font-style: italic;
    font-weight: 600;
    color: #4A4A4A;
    font-size: 9px;
}

.due-amount-row td {
    border-top: 2px solid #B7950B;
    padding-top: 5px;
    font-size: 11px;
    font-weight: 900;
}

.due-amount-row .payment-label { color: #1B4332; }
.due-amount-row .payment-value { color: #0D2B1F; font-size: 11px; font-family: 'Roboto Mono', 'Courier New', Courier, monospace; }

/* ── Cancellation Policy Section ────────────────────────────── */
.note-section {
    background: linear-gradient(135deg, #FFF9F2 0%, #FFF3E0 100%);
    border: 1px solid #D0834A;
    border-left: 4px solid #C0392B;
    padding: 5px 8px;
    margin-bottom: 6px;
}

.note-section h3 {
    font-size: 9.5px;
    font-weight: 900;
    margin: 0 0 4px 0;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #922B21;
    font-family: 'Roboto', 'Arial', sans-serif;
}

.note-section ul {
    margin: 0;
    padding-left: 14px;
    font-size: 8pt;
    line-height: 1.45;
    font-weight: 500;
    color: #6B3010;
}

.note-section li {
    margin-bottom: 2px;
}

/* ── Invoice Footer ─────────────────────────────────────────── */
.invoice-footer {
    border-top: none;
    padding-top: 0;
}

.invoice-footer::before {
    content: '';
    display: block;
    height: 3px;
    background: linear-gradient(90deg, #1B4332 0%, #B7950B 40%, #F4D03F 50%, #B7950B 60%, #1B4332 100%);
    margin-bottom: 7px;
}

.signature-section {
    margin-bottom: 4px;
}

.signature-line {
    text-align: right;
    font-size: 9px;
    font-weight: 600;
}

.signature-line p {
    margin: 2px 0;
    line-height: 1.35;
}

.signature-line strong {
    font-weight: 900;
    color: #1B4332;
}

.thank-you-section {
    text-align: center;
    font-size: 9px;
    font-weight: 500;
    color: #3A3A3A;
}

.thank-you-section p { margin: 2px 0; line-height: 1.3; }

.thank-you-section strong {
    font-weight: 900;
    color: #1B4332;
}

.disclaimer-note {
    margin-top: 5px;
    padding-top: 4px;
    border-top: 1px dashed #B7950B;
    text-align: center;
    font-size: 7.5pt;
    font-weight: 500;
    color: #666;
    font-style: italic;
}

/* ============================================================
   PRINT MEDIA QUERY — Preserve premium colors for PDF/print
   ============================================================ */
@media print {
    /* Force all elements to print with exact colors */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Remove non-invoice elements from document flow */
    .sidebar,
    .top-navbar {
        display: none !important;
    }

    .main-content > *:not(.print-invoice-only) {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }

    .print-invoice-only {
        display: block !important;
        position: static !important;
        width: 100%;
    }

    /* A4 page settings */
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    body {
        margin: 0;
        padding: 0;
    }

    .invoice-container {
        width: 100%;
        max-width: 190mm;
        margin: 0 auto;
        padding: 0;
        font-size: 9.5pt;
        line-height: 1.35;
        font-family: 'Roboto', 'Arial', 'Helvetica Neue', sans-serif;
    }

    /* Prevent page breaks inside sections */
    .invoice-header,
    .invoice-details-bar,
    .customer-section,
    .booking-table-section,
    .payment-calculation-section,
    .note-section,
    .invoice-footer {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }

    /* Header: center content for letterhead look in print */
    .header-content {
        flex-direction: column;
        align-items: center;
        border-left: 5px solid #1B4332 !important;
        border-right: 5px solid #1B4332 !important;
        border-top: 5px solid #1B4332 !important;
    }

    .company-logo-space {
        text-align: center;
        margin-bottom: 5px;
        margin-left: 0;
        order: -1;
    }

    .company-logo-img {
        max-width: 180px;
        max-height: 80px;
    }

    .logo-placeholder {
        font-size: 13pt;
        padding: 8px 20px;
        border: 2px solid #B7950B !important;
        background: linear-gradient(135deg, #FEFDF7 0%, #F4E9C0 100%) !important;
        color: #1B4332 !important;
    }

    .company-info {
        text-align: center;
        width: 100%;
    }

    .company-name {
        font-size: 15pt;
        letter-spacing: 2px;
        color: #1B4332 !important;
    }

    .company-details {
        font-size: 9pt;
        color: #2D6A4F !important;
    }

    .invoice-title {
        background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%) !important;
        border-left: 5px solid #1B4332 !important;
        border-right: 5px solid #1B4332 !important;
        border-bottom: 5px solid #1B4332 !important;
    }

    .invoice-title h2 {
        font-size: 10.5pt;
        color: #F4D03F !important;
        letter-spacing: 2px;
    }

    /* Details bar */
    .invoice-details-bar {
        background: linear-gradient(135deg, #F5E9BE 0%, #EDD98A 100%) !important;
        border-left: 4px solid #B7950B !important;
        border-top: 1px solid #B7950B !important;
        border-right: 1px solid #B7950B !important;
        border-bottom: 1px solid #B7950B !important;
        padding: 5px 10px;
        display: flex;
        justify-content: space-between;
    }

    .invoice-detail-item {
        font-size: 8.5pt;
        color: #1B4332 !important;
    }

    .invoice-detail-item strong { color: #7D6608 !important; }

    /* Customer section */
    .customer-section {
        border: 1px solid #A8C5B5 !important;
        border-left: 4px solid #1B4332 !important;
        background: #fff !important;
        padding: 5px 8px;
    }

    .customer-section h3 {
        font-size: 9pt;
        border-bottom: 2px solid #B7950B !important;
        color: #1B4332 !important;
        letter-spacing: 1px;
    }

    .customer-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px;
    }

    .info-row      { font-size: 8.5pt; line-height: 1.4; }
    .info-label    { font-weight: bold; color: #1B4332 !important; min-width: 88px; }
    .info-value    { font-weight: normal; color: #1A1A1A !important; }

    /* Table */
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
    }

    .invoice-table th {
        background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%) !important;
        color: #F4D03F !important;
        padding: 4px 6px;
        font-size: 8.5pt;
        border: 1px solid #0D2B1F !important;
        font-weight: bold;
        letter-spacing: 0.4px;
    }

    .invoice-table td {
        padding: 3px 6px;
        border: 1px solid #C5D8CC !important;
        font-size: 8.5pt;
        line-height: 1.3;
        color: #1A1A1A !important;
    }

    .invoice-table tr:nth-child(even) td { background: #F2F9F5 !important; }
    .invoice-table tr:nth-child(odd)  td { background: #FFFFFF !important; }

    .invoice-table td strong { color: #1B4332 !important; }

    .invoice-table .subtotal-row td {
        background: linear-gradient(135deg, #F5E9BE 0%, #EDD98A 100%) !important;
        font-size: 9pt;
        font-weight: bold;
        color: #5A3E00 !important;
        border-top: 2px solid #B7950B !important;
        font-family: 'Roboto Mono', 'Courier New', Courier, monospace !important;
    }

    .invoice-table .total-row td {
        background: linear-gradient(135deg, #1B4332 0%, #0D2B1F 100%) !important;
        color: #F4D03F !important;
        font-size: 10pt;
        font-weight: bold;
        padding: 5px 6px;
        font-family: 'Roboto Mono', 'Courier New', Courier, monospace !important;
    }

    /* Payment section */
    .payment-calculation-section {
        border: 1px solid #A8C5B5 !important;
        border-left: 4px solid #B7950B !important;
        background: linear-gradient(135deg, #FEFDF7 0%, #FAF5EB 100%) !important;
        padding: 5px 8px;
    }

    .payment-table td { font-size: 8.5pt; line-height: 1.4; padding: 2px 0; }
    .payment-label    { font-weight: bold; color: #1B4332 !important; }
    .payment-value    { font-size: 8.5pt; font-weight: bold; text-align: right; color: #1A1A1A !important; font-family: 'Roboto Mono', 'Courier New', Courier, monospace !important; }
    .payment-value-words { font-size: 8pt; font-style: italic; color: #4A4A4A !important; }

    .due-amount-row td {
        padding-top: 4px;
        font-size: 10pt;
        border-top: 2px solid #B7950B !important;
        font-weight: bold;
    }

    .due-amount-row .payment-label { color: #1B4332 !important; }
    .due-amount-row .payment-value { font-size: 10pt; color: #0D2B1F !important; font-family: 'Roboto Mono', 'Courier New', Courier, monospace !important; }

    /* Policy section */
    .note-section {
        background: linear-gradient(135deg, #FFF9F2 0%, #FFF3E0 100%) !important;
        border: 1px solid #D0834A !important;
        border-left: 4px solid #C0392B !important;
        padding: 4px 8px;
    }

    .note-section h3 { font-size: 8.5pt; color: #922B21 !important; letter-spacing: 0.8px; }
    .note-section ul  { font-size: 7.5pt; line-height: 1.35; color: #6B3010 !important; padding-left: 14px; margin: 2px 0; }
    .note-section li  { margin-bottom: 1px; }

    /* Footer */
    .invoice-footer { border-top: none; padding-top: 0; }

    .invoice-footer::before {
        background: linear-gradient(90deg, #1B4332 0%, #B7950B 40%, #F4D03F 50%, #B7950B 60%, #1B4332 100%) !important;
        height: 3px;
        margin-bottom: 6px;
    }

    .signature-line { font-size: 8.5pt; text-align: right; }
    .signature-line p { margin: 2px 0; line-height: 1.3; }
    .signature-line strong { color: #1B4332 !important; }

    .thank-you-section { font-size: 8pt; text-align: center; line-height: 1.3; }
    .thank-you-section p { margin: 2px 0; }
    .thank-you-section strong { color: #1B4332 !important; }

    .disclaimer-note {
        margin-top: 3px;
        padding-top: 3px;
        font-size: 7pt;
        border-top: 1px dashed #B7950B !important;
        text-align: center;
        line-height: 1.3;
        color: #666 !important;
        font-style: italic;
    }

    /* Typography helpers */
    .service-description-print { font-size: 7.5pt; line-height: 1.3; color: #5A7265 !important; }
    .menu-items-print           { font-size: 7.5pt; line-height: 1.3; color: #4A5D55 !important; }
    .service-category-print     { font-size: 8pt;   font-weight: 600; color: #1B4332 !important; margin-left: 4px; }

    /* Decorative stripe */
    .invoice-top-stripe {
        background: linear-gradient(90deg, #1B4332 0%, #B7950B 40%, #F4D03F 50%, #B7950B 60%, #1B4332 100%) !important;
        height: 7px;
    }

    /* Hide "no services" row */
    .no-services-row { display: none !important; }
}


/* Responsive Design */
@media (max-width: 768px) {
    .quick-action-section {
        margin-bottom: 1rem;
    }
    .compact-field-label {
        flex: 0 0 90px;
    }
    .payment-breakdown {
        font-size: 0.875rem;
    }
}

@media (max-width: 576px) {
    .compact-field-label {
        flex: 0 0 80px;
        font-size: .72rem;
    }
}

/* ─── Photo Zoom / Lens Effect ─── */
.photo-zoom-wrap {
    position: relative;
    display: inline-flex;
    cursor: zoom-in;
    flex-shrink: 0;
}
.photo-zoom-wrap .photo-zoom-popup {
    display: none;
    position: absolute;
    z-index: 9999;
    left: calc(100% + 8px);
    top: 50%;
    transform: translateY(-50%);
    width: 200px;
    height: 200px;
    border: 3px solid #fff;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,.35);
    overflow: hidden;
    background: #f8f9fa;
    pointer-events: none;
}
.photo-zoom-wrap:hover .photo-zoom-popup {
    display: block;
}
.photo-zoom-wrap .photo-zoom-popup img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
/* Vendor circular zoom popup */
.photo-zoom-wrap.vendor-zoom .photo-zoom-popup {
    width: 160px;
    height: 160px;
    border-radius: 50%;
}
.inline-va-vendor-photo-list {
    display: grid;
    gap: .45rem;
}
.inline-va-vendor-photo-item {
    width: 100%;
    border: 1px solid #dbe3ea;
    background: #fff;
    border-radius: .55rem;
    padding: .45rem .55rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    text-align: left;
    transition: all .18s ease;
}
.inline-va-vendor-photo-item:hover,
.inline-va-vendor-photo-item:focus {
    border-color: #198754;
    background: #f5fff8;
    box-shadow: 0 0 0 .12rem rgba(25,135,84,.12);
}
.inline-va-vendor-photo-item.active {
    border-color: #198754;
    background: #eaf8ef;
    box-shadow: inset 0 0 0 1px rgba(25,135,84,.18);
}
.inline-va-vendor-photo-thumb,
.inline-va-vendor-photo-placeholder {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    flex-shrink: 0;
}
.inline-va-vendor-photo-thumb {
    object-fit: cover;
}
.inline-va-vendor-photo-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #6c757d;
    color: #fff;
    font-size: .95rem;
}
</style>

<!-- Sequential WhatsApp Sender Modal -->
<div class="modal fade" id="sendAllWaModal" tabindex="-1" aria-labelledby="sendAllWaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#25D366;">
                <h6 class="modal-title text-white mb-0" id="sendAllWaModalLabel">
                    <i class="fab fa-whatsapp me-2"></i>Send WhatsApp to All
                </h6>
                <span class="badge bg-white text-success fw-bold ms-2" id="wa-step-counter" style="font-size:.8rem;"></span>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-2">
                <!-- Progress bar -->
                <div class="progress mb-3" style="height:5px;">
                    <div class="progress-bar" id="wa-progress-bar"
                         style="width:0%;background:#25D366;transition:width .4s ease;"></div>
                </div>
                <!-- Current recipient card -->
                <div id="wa-current-card" class="rounded border p-3 mb-3" style="background:#f0fdf4;">
                    <!-- populated by JS -->
                </div>
                <!-- Recipients list -->
                <div class="border rounded small" id="wa-recipients-list"
                     style="max-height:160px;overflow-y:auto;background:#fff;">
                    <!-- populated by JS -->
                </div>
            </div>
            <div class="modal-footer py-2 gap-2 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="wa-skip-btn">
                    <i class="fas fa-forward me-1"></i>Skip
                </button>
                <button type="button" class="btn btn-sm text-white fw-bold" id="wa-next-btn"
                        style="background:#25D366;" disabled>
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const WHATSAPP_REDIRECT_DELAY = 500; // milliseconds
    const whatsappForm = document.getElementById('whatsappForm');
    
    if (whatsappForm) {
        whatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Open WhatsApp with properly escaped and encoded values
            const phone = <?php echo json_encode($clean_phone); ?>;
            const message = <?php echo json_encode($whatsapp_text); ?>;
            const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
            window.open(whatsappUrl, '_blank');
            
            // Submit the form to log the activity
            setTimeout(function() {
                whatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }

    // Handle Booking Confirmation WhatsApp form submission
    const confirmationWhatsappForm = document.getElementById('confirmationWhatsappForm');
    if (confirmationWhatsappForm) {
        confirmationWhatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const phone = <?php echo json_encode($clean_phone); ?>;
            const message = <?php echo json_encode($confirmation_text); ?>;
            const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
            window.open(whatsappUrl, '_blank');

            setTimeout(function() {
                confirmationWhatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }

    // Handle Thank You WhatsApp form submission (after payment is fully paid)
    const thankyouWhatsappForm = document.getElementById('thankyouWhatsappForm');
    if (thankyouWhatsappForm) {
        thankyouWhatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const phone = <?php echo json_encode($clean_phone); ?>;
            const message = <?php echo json_encode($thankyou_text); ?>;
            const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
            window.open(whatsappUrl, '_blank');

            setTimeout(function() {
                thankyouWhatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }

    // Handle copy review link button
    const copyReviewLinkBtn = document.getElementById('copyReviewLinkBtn');
    if (copyReviewLinkBtn) {
        copyReviewLinkBtn.addEventListener('click', function() {
            const input = document.getElementById('review-link-input');
            if (input) {
                navigator.clipboard.writeText(input.value).then(function() {
                    const icon = copyReviewLinkBtn.querySelector('i');
                    if (icon) { icon.className = 'fas fa-check'; }
                    setTimeout(function() {
                        if (icon) { icon.className = 'fas fa-copy'; }
                    }, 2000);
                }).catch(function() {
                    input.select();
                    document.execCommand('copy');
                });
            }
        });
    }

    // Handle Venue Provider WhatsApp form submission
    const venueProviderWhatsappForm = document.getElementById('venueProviderWhatsappForm');
    if (venueProviderWhatsappForm) {
        venueProviderWhatsappForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const waUrl = <?php echo json_encode($venue_provider_wa_url); ?>;
            if (waUrl) {
                window.open(waUrl, '_blank');
            }

            setTimeout(function() {
                venueProviderWhatsappForm.submit();
            }, WHATSAPP_REDIRECT_DELAY);
        });
    }


})();

<?php if ($initial_tab === 'tab-services'): ?>
// Auto-scroll to the Services section after admin service add/delete
document.addEventListener('DOMContentLoaded', function() {
    var servicesSection = document.getElementById('section-services');
    if (servicesSection) {
        servicesSection.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
});
<?php endif; ?>

</script>


<!-- Inline Vendor Assignment: populate vendor dropdowns on collapse show -->
<?php if (!empty($vendor_types_available)): ?>
<script>
(function() {
    var vendorsByType  = <?php echo json_encode($vendors_by_type, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var vendorTypesMap = <?php echo json_encode(array_column(array_values($vendor_types_available), 'label', 'slug'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    /**
     * Resolve a vendor-type slug from a direct slug or a category label.
     */
    function resolveVendorTypeSlug(typeSlug, categoryLabel) {
        if (typeSlug && vendorsByType[typeSlug]) return typeSlug;
        if (!categoryLabel) return typeSlug || '';
        var catLower = categoryLabel.toLowerCase();
        for (var slug in vendorTypesMap) {
            if (slug.toLowerCase() === catLower || vendorTypesMap[slug].toLowerCase() === catLower) return slug;
        }
        for (var slug2 in vendorTypesMap) {
            var ls = slug2.toLowerCase(), ll = vendorTypesMap[slug2].toLowerCase();
            if (ls.indexOf(catLower) === 0 || catLower.indexOf(ls) === 0 ||
                ll.indexOf(catLower) === 0 || catLower.indexOf(ll) === 0) return slug2;
        }
        return typeSlug || '';
    }

    /**
     * Populate a vendor <select> with options for the given vendor type.
     */
    function populateVendorSelect(selectEl, typeSlug, categoryLabel) {
        if (selectEl.dataset.populated === '1') return; // already populated
        selectEl.dataset.populated = '1'; // set flag early to prevent concurrent calls
        var resolved = resolveVendorTypeSlug(typeSlug, categoryLabel);
        selectEl.innerHTML = '<option value="">\u2014 Select Vendor \u2014</option>';
        var listWrap = selectEl.parentElement ? selectEl.parentElement.querySelector('.inline-va-vendor-photo-list') : null;
        if (listWrap) {
            listWrap.innerHTML = '';
        }
        if (resolved && vendorsByType[resolved] && vendorsByType[resolved].length > 0) {
            vendorsByType[resolved].forEach(function(v) {
                var o = document.createElement('option');
                o.value = v.id;
                var label = v.name + (v.city ? ' (' + v.city + ')' : '');
                if (v.is_unapproved) {
                    label += ' \u2014 \u26a0\ufe0f Unverified Vendor';
                    o.style.color = '#856404';
                }
                o.textContent = label;
                if (v.photo) {
                    o.dataset.photo = v.photo;
                }
                if (v.description) {
                    o.dataset.description = v.description;
                }
                selectEl.appendChild(o);
                if (listWrap) {
                    var photoHtml = v.photo
                        ? '<img src="' + esc(v.photo) + '" alt="' + esc(v.name) + '" class="inline-va-vendor-photo-thumb">'
                        : '<span class="inline-va-vendor-photo-placeholder" aria-hidden="true"><i class="fas fa-user" aria-hidden="true"></i></span>';
                    var cityHtml = v.city ? '<div class="small text-muted">' + esc(v.city) + '</div>' : '';
                    var descHtml = v.description ? '<div class="small text-muted text-truncate">' + esc(v.description) + '</div>' : '';
                    var unverifiedBadge = v.is_unapproved
                        ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">\u26a0\ufe0f Unverified</span>'
                        : '';
                    listWrap.insertAdjacentHTML('beforeend',
                        '<button type="button" class="inline-va-vendor-photo-item' + (v.is_unapproved ? ' vendor-unverified' : '') + '" data-vendor-id="' + esc(String(v.id)) + '" aria-label="Select vendor: ' + esc(v.name) + '">' +
                            photoHtml +
                            '<span class="min-width-0 flex-grow-1">' +
                                '<span class="d-block fw-semibold text-truncate">' + esc(v.name) + unverifiedBadge + '</span>' +
                                cityHtml +
                                descHtml +
                            '</span>' +
                        '</button>'
                    );
                }
            });
        } else {
            // No vendor type match: show informational message
            var o = document.createElement('option');
            o.value = '';
            o.disabled = true;
            o.textContent = '\u2014 No vendors available for this service type \u2014';
            selectEl.appendChild(o);
            if (listWrap) {
                listWrap.innerHTML = '<div class="small text-muted border rounded px-2 py-2 bg-light">\u2014 No vendors available for this service type \u2014</div>';
            }
        }
    }

    function syncVendorPhotoSelection(selectEl) {
        var listWrap = selectEl.parentElement ? selectEl.parentElement.querySelector('.inline-va-vendor-photo-list') : null;
        if (!listWrap) return;
        var selectedValue = selectEl.value || '';
        listWrap.querySelectorAll('.inline-va-vendor-photo-item[data-vendor-id]').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-vendor-id') === selectedValue);
        });
    }

    // When a collapse relevant to inline vendor assignment opens, populate its vendor select
    document.addEventListener('show.bs.collapse', function(e) {
        if (!e.target || !e.target.id || e.target.id.indexOf('inline-va-') !== 0) return;
        var selectEl = e.target.querySelector('.inline-va-vendor-select');
        if (!selectEl) return;
        var typeSlug = selectEl.dataset.vendorTypeSlug || '';
        var category = selectEl.dataset.serviceCategory || '';
        populateVendorSelect(selectEl, typeSlug, category);
        syncVendorPhotoSelection(selectEl);
    });

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.inline-va-vendor-photo-item[data-vendor-id]');
        if (!btn) return;
        var wrap = btn.closest('.inline-va-vendor-wrap');
        var selectEl = wrap ? wrap.querySelector('.inline-va-vendor-select') : null;
        if (!selectEl) return;
        selectEl.value = btn.getAttribute('data-vendor-id') || '';
        syncVendorPhotoSelection(selectEl);
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.addEventListener('change', function(e) {
        if (!e.target || !e.target.classList || !e.target.classList.contains('inline-va-vendor-select')) return;
        syncVendorPhotoSelection(e.target);
    });

    // Manual / System vendor mode toggle
    document.addEventListener('click', function(e) {
        var modeBtn = e.target.closest('.inline-va-mode-btn');
        if (!modeBtn) return;
        var form = modeBtn.closest('form');
        if (!form) return;
        var mode = modeBtn.dataset.mode || 'system';
        var isManual = (mode === 'manual');

        // Update button active states
        form.querySelectorAll('.inline-va-mode-btn').forEach(function(b) {
            b.classList.toggle('active', b === modeBtn);
            b.classList.toggle('btn-primary', b === modeBtn);
            b.classList.toggle('btn-outline-secondary', b !== modeBtn);
        });

        // Show/hide field groups
        var systemFields = form.querySelector('.inline-va-system-fields');
        var manualFields = form.querySelector('.inline-va-manual-fields');
        if (systemFields) systemFields.classList.toggle('d-none', isManual);
        if (manualFields) manualFields.classList.toggle('d-none', !isManual);

        // Update required attributes and the hidden flag
        var vendorSelect = form.querySelector('.inline-va-vendor-select');
        var manualNameInput = form.querySelector('input[name="manual_vendor_name"]');
        var flagInput = form.querySelector('.inline-va-is-manual-flag');
        if (vendorSelect) vendorSelect.required = !isManual;
        if (manualNameInput) manualNameInput.required = isManual;
        if (flagInput) flagInput.value = isManual ? '1' : '0';
    });
})();
</script>
<?php endif; ?>

<?php if (!empty($combo_wa_urls)): ?>
<script>
(function() {
    var comboWaUrls = <?php echo json_encode($combo_wa_urls, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    window.sendAllVendorWhatsApp = function() {
        if (!comboWaUrls || comboWaUrls.length === 0) return;
        var btn = document.getElementById('combo-wa-btn');
        var total = comboWaUrls.length;
        var idx = 0;
        function openNext() {
            if (idx >= total) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i>WhatsApp All (' + total + ')';
                }
                return;
            }
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Opening ' + (idx + 1) + '/' + total + '...';
            }
            window.open(comboWaUrls[idx], '_blank');
            idx++;
            setTimeout(openNext, idx < total ? 1500 : 0);
        }
        openNext();
    };
})();
</script>
<?php endif; ?>

<script>
(function() {
    var recipients = <?php echo json_encode($all_combo_wa_data ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var COUNTDOWN_SECONDS = 10;
    var AUTO_OPEN_DELAY_MS = 1500; // delay between opening each recipient's WhatsApp
    var AUTO_DONE_DELAY_MS = 500;  // delay before showing the done state
    var DONE_REDIRECT_SECONDS = 3; // seconds before auto-redirecting to home after all sent
    var countdownTimer = null;
    var redirectTimer = null;
    var currentIdx = 0;
    var autoMode = false;
    var isDone = false;

    function esc(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function buildRecipientsList(activeIdx) {
        if (!recipients.length) return '';
        var html = '';
        for (var i = 0; i < recipients.length; i++) {
            var r = recipients[i];
            var icon, rowClass;
            if (i < activeIdx) {
                icon = '<i class="fas fa-check-circle" style="color:#25D366;"></i>';
                rowClass = 'text-muted';
            } else if (i === activeIdx) {
                icon = '<i class="fas fa-arrow-right text-primary"></i>';
                rowClass = 'fw-semibold';
            } else {
                icon = '<i class="far fa-circle text-secondary"></i>';
                rowClass = 'text-muted';
            }
            html += '<div class="d-flex align-items-center px-3 py-1' + (i === activeIdx ? ' bg-light' : '') + '">' +
                '<span class="me-2" style="width:16px;text-align:center;">' + icon + '</span>' +
                '<span class="' + rowClass + ' flex-grow-1 text-truncate">' +
                '<span class="badge bg-secondary me-1" style="font-size:.6rem;">' + esc(r.label) + '</span>' +
                esc(r.name) + '</span>' +
                '<small class="text-muted ms-2" style="white-space:nowrap;">' + esc(r.phone) + '</small>' +
                '</div>';
        }
        return html;
    }

    function buildCurrentCard(r) {
        var openBtnHtml = autoMode
            ? '<div class="mt-3 text-center"><span class="badge bg-success px-3 py-2" style="font-size:.85rem;"><i class="fab fa-whatsapp me-1"></i>Opening automatically…</span></div>'
            : '<div class="mt-3 d-grid"><button type="button" class="btn btn-sm text-white fw-bold" id="wa-open-btn" style="background:#25D366;"><i class="fab fa-whatsapp me-1"></i>Open WhatsApp</button></div>';
        return '<div class="d-flex align-items-center gap-3">' +
            '<div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white" ' +
            'style="width:46px;height:46px;font-size:1.3rem;background:#25D366;">' +
            '<i class="fab fa-whatsapp"></i></div>' +
            '<div class="flex-grow-1 overflow-hidden">' +
            '<div class="mb-1"><span class="badge bg-primary">' + esc(r.label) + '</span></div>' +
            '<div class="fw-bold text-truncate">' + esc(r.name) + '</div>' +
            '<div class="small text-muted"><i class="fas fa-phone me-1"></i>' + esc(r.phone) + '</div>' +
            '</div></div>' +
            openBtnHtml +
            '<div id="wa-countdown-row" class="text-center mt-2 small text-muted" style="min-height:1.2em;"></div>';
    }

    function getEl(id) { return document.getElementById(id); }

    function updateModal(idx, waOpened) {
        var r = recipients[idx];
        var total = recipients.length;
        getEl('wa-step-counter').textContent = (idx + 1) + ' / ' + total;
        var pb = getEl('wa-progress-bar');
        if (pb) pb.style.width = Math.round((idx / total) * 100) + '%';
        getEl('wa-current-card').innerHTML = buildCurrentCard(r);
        getEl('wa-recipients-list').innerHTML = buildRecipientsList(idx);
        getEl('wa-next-btn').disabled = !waOpened;
        getEl('wa-skip-btn').innerHTML = '<i class="fas fa-forward me-1"></i>Skip';

        var openBtn = getEl('wa-open-btn');
        if (openBtn) {
            openBtn.onclick = function() {
                window.open(r.url, '_blank');
                openBtn.innerHTML = '<i class="fas fa-check me-1"></i>Opened ✓';
                openBtn.disabled = true;
                getEl('wa-next-btn').disabled = false;
                startCountdown(idx);
            };
        }
    }

    function startCountdown(idx) {
        clearCountdown();
        var remaining = COUNTDOWN_SECONDS;
        var cdRow = getEl('wa-countdown-row');
        function tick() {
            if (!cdRow) { clearCountdown(); return; }
            if (remaining <= 0) {
                clearCountdown();
                goNext(idx);
                return;
            }
            cdRow.innerHTML = '<i class="fas fa-clock me-1"></i>Auto-advancing in <strong>' + remaining + 's</strong>…';
            remaining--;
            countdownTimer = setTimeout(tick, 1000);
        }
        tick();
    }

    function clearCountdown() {
        if (countdownTimer) { clearTimeout(countdownTimer); countdownTimer = null; }
    }

    function goNext(idx) {
        clearCountdown();
        var nextIdx = idx + 1;
        if (nextIdx >= recipients.length) {
            showDone();
        } else {
            currentIdx = nextIdx;
            updateModal(nextIdx, false);
        }
    }

    function clearRedirect() {
        if (redirectTimer) { clearTimeout(redirectTimer); redirectTimer = null; }
    }

    function showDone() {
        clearCountdown();
        isDone = true;
        var total = recipients.length;
        getEl('wa-step-counter').textContent = '✓ Done';
        var pb = getEl('wa-progress-bar');
        if (pb) pb.style.width = '100%';

        var doneList = '';
        for (var i = 0; i < recipients.length; i++) {
            var r = recipients[i];
            doneList += '<div class="d-flex align-items-center px-3 py-1">' +
                '<span class="me-2" style="width:16px;text-align:center;">' +
                '<i class="fas fa-check-circle" style="color:#25D366;"></i></span>' +
                '<span class="text-muted flex-grow-1 text-truncate">' +
                '<span class="badge bg-secondary me-1" style="font-size:.6rem;">' + esc(r.label) + '</span>' +
                esc(r.name) + '</span>' +
                '<small class="text-muted ms-2">' + esc(r.phone) + '</small></div>';
        }
        getEl('wa-recipients-list').innerHTML = doneList;

        getEl('wa-current-card').innerHTML =
            '<div class="text-center py-3">' +
            '<i class="fas fa-check-circle" style="font-size:2.8rem;color:#25D366;"></i>' +
            '<h6 class="mt-2 fw-bold" style="color:#25D366;">All ' + total + ' sent!</h6>' +
            '<p class="text-muted small mb-0">WhatsApp was opened for all recipients.</p>' +
            '<p class="small mt-2 mb-0 text-muted">Returning to home in <strong id="wa-done-secs">' + DONE_REDIRECT_SECONDS + '</strong>s…</p>' +
            '</div>';

        var skipBtn = getEl('wa-skip-btn');
        skipBtn.innerHTML = '<i class="fas fa-home me-1"></i>Home';
        skipBtn.onclick = function() {
            clearRedirect();
            window.location.href = 'index.php';
        };
        getEl('wa-next-btn').style.display = 'none';

        var mainBtn = getEl('send-all-whatsapp-btn');
        if (mainBtn) {
            mainBtn.disabled = false;
            mainBtn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send to All';
        }

        // Auto-redirect to home page after countdown
        var remaining = DONE_REDIRECT_SECONDS;
        function tickRedirect() {
            if (remaining <= 0) {
                window.location.href = 'index.php';
                return;
            }
            var secsEl = getEl('wa-done-secs');
            if (secsEl) secsEl.textContent = remaining;
            remaining--;
            redirectTimer = setTimeout(tickRedirect, 1000);
        }
        tickRedirect();
    }

    window.sendAllWhatsApp = function() {
        if (!recipients || recipients.length === 0) return;
        var modalEl = getEl('sendAllWaModal');
        if (!modalEl) return;

        currentIdx = 0;
        clearCountdown();
        autoMode = true;

        // Hide manual Next button – not needed in auto mode
        var nextBtn = getEl('wa-next-btn');
        if (nextBtn) nextBtn.style.display = 'none';

        // Skip/Close button closes the modal
        getEl('wa-skip-btn').onclick = function() {
            bootstrap.Modal.getInstance(modalEl).hide();
        };

        // Attach cleanup listener before showing so it is never missed
        modalEl.addEventListener('hidden.bs.modal', function onHide() {
            modalEl.removeEventListener('hidden.bs.modal', onHide);
            clearCountdown();
            clearRedirect();
            autoMode = false;
            isDone = false;
            if (nextBtn) nextBtn.style.display = '';
            var mainBtn = getEl('send-all-whatsapp-btn');
            if (mainBtn) {
                mainBtn.disabled = false;
                mainBtn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Send to All';
            }
        });

        updateModal(0, true);
        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();

        // Auto-open each recipient's WhatsApp URL in sequence.
        // First call is synchronous (within the user-click event) to satisfy
        // browser popup policies; subsequent calls use a 1.5 s delay.
        var autoIdx = 0;
        var total = recipients.length;

        function autoOpenNext() {
            if (!autoMode) return; // cancelled (modal closed)
            if (autoIdx >= total) {
                showDone();
                return;
            }
            currentIdx = autoIdx;
            updateModal(autoIdx, true);
            window.open(recipients[autoIdx].url, '_blank');
            autoIdx++;
            setTimeout(autoOpenNext, autoIdx < total ? AUTO_OPEN_DELAY_MS : AUTO_DONE_DELAY_MS);
        }

        autoOpenNext();
    };
})();

// ---- Record Payment Modal ----
(function () {
    var form = document.getElementById('recordPaymentForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var alertBox  = document.getElementById('recordPaymentAlert');
        var submitBtn = document.getElementById('recordPaymentSubmitBtn');
        var amountVal = document.getElementById('rp_paid_amount').value.trim();

        alertBox.className = 'alert d-none mb-3';
        alertBox.textContent = '';

        if (!amountVal || parseFloat(amountVal) <= 0) {
            alertBox.className = 'alert alert-danger mb-3';
            alertBox.textContent = 'Please enter a valid payment amount.';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';

        var formData = new FormData(form);

        fetch('add-payment.php', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Payment';

            if (data.success) {
                // Reload the page to reflect the new payment and updated statuses
                window.location.reload();
            } else {
                alertBox.className = 'alert alert-danger mb-3';
                alertBox.textContent = data.message || 'Failed to record payment. Please try again.';
            }
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Payment';
            alertBox.className = 'alert alert-danger mb-3';
            alertBox.textContent = 'A network error occurred. Please try again.';
        });
    });

    // Reset form and alert when modal is closed
    var modalEl = document.getElementById('recordPaymentModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            form.reset();
            var alertBox = document.getElementById('recordPaymentAlert');
            alertBox.className = 'alert d-none mb-3';
            alertBox.textContent = '';
        });
    }
})();

// ---- Verify / Reject individual payment transactions ----
(function () {
    var csrfToken = <?php echo json_encode($csrf_token_value); ?>;

    document.querySelectorAll('.payment-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var paymentId    = btn.dataset.paymentId;
            var actionStatus = btn.dataset.actionStatus;
            var labelMap     = { verified: 'Verify', rejected: 'Reject', pending: 'Reset to Pending' };
            var label        = labelMap[actionStatus] || actionStatus;

            if (!confirm('Are you sure you want to ' + label + ' this payment?')) return;

            btn.disabled = true;

            var formData = new FormData();
            formData.append('action',         'update_status');
            formData.append('payment_id',     paymentId);
            formData.append('payment_status', actionStatus);
            formData.append('csrf_token',     csrfToken);

            fetch('add-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.success) {
                    // Reload to reflect updated statuses and balances
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update payment status.'));
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('A network error occurred. Please try again.');
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
