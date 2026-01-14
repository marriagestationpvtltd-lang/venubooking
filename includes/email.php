<?php
/**
 * Email Functions
 * Handles sending emails for bookings and notifications
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Send email using PHP mail function
 * In production, use PHPMailer or similar library with SMTP
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . env('EMAIL_FROM_NAME', APP_NAME) . ' <' . env('EMAIL_FROM_ADDRESS', 'noreply@venubooking.com') . '>' . "\r\n";
    
    // For production, implement proper SMTP email sending
    // For now, use basic mail function
    return mail($to, $subject, $body, $headers);
}

/**
 * Send booking confirmation email
 */
function sendBookingConfirmation($booking_id) {
    $db = getDB();
    
    // Get booking details
    $sql = "SELECT b.*, c.full_name, c.email, c.phone, 
            v.venue_name, h.hall_name
            FROM bookings b
            JOIN customers c ON b.customer_id = c.id
            JOIN venues v ON b.venue_id = v.id
            JOIN halls h ON b.hall_id = h.id
            WHERE b.id = :booking_id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if (!$booking) {
        return false;
    }
    
    // Prepare email content
    $subject = "Booking Confirmation - " . $booking['booking_number'];
    
    $body = getBookingEmailTemplate($booking);
    
    // Send email
    return sendEmail($booking['email'], $subject, $body);
}

/**
 * Get booking email template
 */
function getBookingEmailTemplate($booking) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
            .detail-row { margin: 10px 0; }
            .detail-label { font-weight: bold; color: #555; }
            .footer { text-align: center; padding: 20px; color: #777; font-size: 12px; }
            .button { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <p>Booking Confirmation</p>
            </div>
            
            <div class="content">
                <h2>Dear ' . clean($booking['full_name']) . ',</h2>
                <p>Thank you for booking with us! Your booking has been confirmed.</p>
                
                <div class="booking-details">
                    <h3>Booking Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Booking Number:</span> ' . clean($booking['booking_number']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Venue:</span> ' . clean($booking['venue_name']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Hall:</span> ' . clean($booking['hall_name']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span> ' . formatDate($booking['booking_date']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shift:</span> ' . ucfirst(str_replace('_', ' ', $booking['shift'])) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Guests:</span> ' . $booking['number_of_guests'] . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Event Type:</span> ' . clean($booking['event_type']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Cost:</span> ' . formatCurrency($booking['total_cost']) . '
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span> ' . ucfirst($booking['booking_status']) . '
                    </div>
                </div>
                
                <p>We look forward to hosting your event. If you have any questions, please don\'t hesitate to contact us.</p>
                
                <a href="' . APP_URL . '" class="button">Visit Our Website</a>
            </div>
            
            <div class="footer">
                <p>' . APP_NAME . '</p>
                <p>Email: ' . getSetting('site_email', 'info@venubooking.com') . '</p>
                <p>Phone: ' . getSetting('site_phone', '+977-1-4123456') . '</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}

/**
 * Send booking cancellation email
 */
function sendBookingCancellation($booking_id) {
    $db = getDB();
    
    // Get booking details
    $sql = "SELECT b.*, c.full_name, c.email 
            FROM bookings b
            JOIN customers c ON b.customer_id = c.id
            WHERE b.id = :booking_id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if (!$booking) {
        return false;
    }
    
    $subject = "Booking Cancelled - " . $booking['booking_number'];
    
    $body = '
    <html>
    <body style="font-family: Arial, sans-serif;">
        <h2>Booking Cancellation Notice</h2>
        <p>Dear ' . clean($booking['full_name']) . ',</p>
        <p>Your booking <strong>' . clean($booking['booking_number']) . '</strong> has been cancelled.</p>
        <p>If you have any questions, please contact us.</p>
        <p>Best regards,<br>' . APP_NAME . '</p>
    </body>
    </html>
    ';
    
    return sendEmail($booking['email'], $subject, $body);
}

/**
 * Send booking reminder email
 */
function sendBookingReminder($booking_id) {
    $db = getDB();
    
    $sql = "SELECT b.*, c.full_name, c.email, v.venue_name, h.hall_name
            FROM bookings b
            JOIN customers c ON b.customer_id = c.id
            JOIN venues v ON b.venue_id = v.id
            JOIN halls h ON b.hall_id = h.id
            WHERE b.id = :booking_id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if (!$booking) {
        return false;
    }
    
    $subject = "Booking Reminder - " . $booking['booking_number'];
    
    $body = '
    <html>
    <body style="font-family: Arial, sans-serif;">
        <h2>Event Reminder</h2>
        <p>Dear ' . clean($booking['full_name']) . ',</p>
        <p>This is a reminder for your upcoming event:</p>
        <ul>
            <li><strong>Booking Number:</strong> ' . clean($booking['booking_number']) . '</li>
            <li><strong>Venue:</strong> ' . clean($booking['venue_name']) . '</li>
            <li><strong>Hall:</strong> ' . clean($booking['hall_name']) . '</li>
            <li><strong>Date:</strong> ' . formatDate($booking['booking_date']) . '</li>
            <li><strong>Shift:</strong> ' . ucfirst(str_replace('_', ' ', $booking['shift'])) . '</li>
        </ul>
        <p>We look forward to hosting your event!</p>
        <p>Best regards,<br>' . APP_NAME . '</p>
    </body>
    </html>
    ';
    
    return sendEmail($booking['email'], $subject, $body);
}
