<?php
// Include dependencies before any HTML output to allow redirects
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if booking was completed
if (!isset($_SESSION['booking_completed'])) {
    header('Location: index.php');
    exit;
}

$booking_info = $_SESSION['booking_completed'];
$booking = getBookingDetails($booking_info['booking_id']);
$payment_submitted = $booking_info['payment_submitted'] ?? false;
$vendors = getBookingVendorAssignments($booking_info['booking_id']);

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Get payment details if payment was submitted
$payments = [];
if ($payment_submitted) {
    $payments = getBookingPayments($booking_info['booking_id']);
}

// Clear the booking completed session
unset($_SESSION['booking_completed']);

// Resolve display time – prefer saved start/end times; fall back to shift defaults so the
// booking time is always visible in the confirmation details and when printing.
$conf_shift_times      = getShiftDefaultTimes($booking['shift']);
$conf_display_start    = !empty($booking['start_time']) ? $booking['start_time'] : $conf_shift_times['start'];
$conf_display_end      = !empty($booking['end_time'])   ? $booking['end_time']   : $conf_shift_times['end'];
$conf_has_display_time = !empty($conf_display_start) && !empty($conf_display_end);

// WhatsApp confirmation button
$whatsapp_admin_number = getSetting('whatsapp_number', '');
$whatsapp_url = '';
if (!empty($whatsapp_admin_number)) {
    $wa_venue    = !empty($booking['venue_name']) ? $booking['venue_name'] : (!empty($booking['custom_venue_name']) ? $booking['custom_venue_name'] : '');
    $wa_hall     = !empty($booking['hall_name'])  ? $booking['hall_name']  : (!empty($booking['custom_hall_name'])  ? $booking['custom_hall_name']  : '');
    $wa_date     = !empty($booking['event_date']) ? date('d M Y', strtotime($booking['event_date'])) : '';
    $wa_name     = !empty($booking['customer_name']) ? $booking['customer_name'] : '';
    $wa_ref      = $booking['booking_number'];
    $wa_phone_clean = preg_replace('/[^0-9]/', '', $whatsapp_admin_number);
    if (!empty($wa_phone_clean)) {
        $wa_message  = "Hello! I have just made a booking. Please confirm my booking.\n\n";
        $wa_message .= "📋 Booking Reference: " . $wa_ref . "\n";
        if ($wa_name)  $wa_message .= "👤 Name: "  . $wa_name  . "\n";
        if ($wa_date)  $wa_message .= "📅 Date: "  . $wa_date  . "\n";
        if ($wa_venue) $wa_message .= "🏛️ Venue: " . $wa_venue . "\n";
        if ($wa_hall)  $wa_message .= "🚪 Hall: "  . $wa_hall  . "\n";
        $wa_message .= "\nThank you!";
        $whatsapp_url = 'https://wa.me/' . $wa_phone_clean . '?text=' . rawurlencode($wa_message);
    }
}
$page_title = 'Booking Confirmed';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════
     CONFIRMATION PAGE — Professional Redesign
     ══════════════════════════════════════════════════ -->

<style>
/* ── Confirmation Page Styles ── */
.conf-hero {
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 40%, #388E3C 70%, #43A047 100%);
    position: relative;
    overflow: hidden;
    padding: 4rem 0 3rem;
    text-align: center;
    color: #fff;
}
.conf-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(255,255,255,0.10) 0%, transparent 70%);
    pointer-events: none;
}
/* Decorative circle shapes */
.conf-hero .deco-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    pointer-events: none;
}
.conf-hero .deco-circle.c1 { width: 300px; height: 300px; top: -80px; left: -80px; }
.conf-hero .deco-circle.c2 { width: 200px; height: 200px; bottom: -60px; right: -40px; }
.conf-hero .deco-circle.c3 { width: 120px; height: 120px; top: 30px; right: 15%; }

.conf-check-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(8px);
    border: 2px solid rgba(255,255,255,0.3);
    animation: checkPop 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes checkPop {
    0%   { transform: scale(0) rotate(-30deg); opacity: 0; }
    70%  { transform: scale(1.12) rotate(5deg); opacity: 1; }
    100% { transform: scale(1) rotate(0deg); }
}
.conf-check-wrap i {
    font-size: 3rem;
    color: #fff;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.25));
}

.conf-hero h1 {
    font-family: var(--font-heading);
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 700;
    letter-spacing: -0.03em;
    color: #fff;
    margin-bottom: 0.75rem;
    text-shadow: 0 2px 12px rgba(0,0,0,0.18);
}

.conf-hero .lead-text {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.88);
    max-width: 540px;
    margin: 0 auto 1.5rem;
    line-height: 1.6;
}

.booking-ref-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.35);
    backdrop-filter: blur(10px);
    border-radius: 99px;
    padding: 0.6rem 1.5rem;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    animation: fadeSlideUp 0.5s 0.4s ease both;
    cursor: default;
}
.booking-ref-badge .ref-label {
    font-weight: 400;
    opacity: 0.8;
    font-size: 0.9rem;
}
.booking-ref-badge .ref-number {
    font-family: 'Courier New', Courier, monospace;
    font-size: 1.1rem;
    color: #fff;
    letter-spacing: 0.08em;
}

@keyframes fadeSlideUp {
    0%   { opacity: 0; transform: translateY(16px); }
    100% { opacity: 1; transform: translateY(0); }
}

/* ── Confetti dots ── */
.conf-hero .confetti-dots {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.conf-dot {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 2px;
    opacity: 0;
    animation: confettiFall linear both;
}
@keyframes confettiFall {
    0%   { opacity: 1; transform: translateY(-20px) rotate(0deg); }
    100% { opacity: 0; transform: translateY(200px) rotate(360deg); }
}

/* ── Ticket Card ── */
.booking-ticket {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 2rem;
}

.ticket-header {
    background: linear-gradient(135deg, #f8fdf9 0%, #e8f5e9 100%);
    border-bottom: 2px dashed rgba(76, 175, 80, 0.25);
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.ticket-header .ticket-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--dark-green);
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0;
}

.ticket-header .ticket-status-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.85rem;
    border-radius: 99px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.status-pill.booking { background: #FFF9C4; color: #F57F17; border: 1px solid #F9A825; }
.status-pill.payment { background: #FFEBEE; color: #C62828; border: 1px solid #EF9A9A; }
.status-pill.payment.submitted { background: #E8F5E9; color: #2E7D32; border: 1px solid #A5D6A7; }

.ticket-body {
    padding: 1.75rem 2rem;
}

/* ── Info Grid ── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.75rem;
    margin-bottom: 1.5rem;
}

.info-block {
    position: relative;
}

.info-block .info-block-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--primary-green);
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.info-block .info-block-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(76, 175, 80, 0.2);
}

.info-row {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
    font-size: 0.92rem;
    line-height: 1.5;
}

.info-row .info-key {
    color: var(--text-muted);
    font-weight: 500;
    min-width: 90px;
    flex-shrink: 0;
    font-size: 0.85rem;
}

.info-row .info-val {
    color: var(--text-dark);
    font-weight: 600;
    word-break: break-word;
}

/* ── Divider ── */
.ticket-divider {
    position: relative;
    height: 0;
    border-top: 2px dashed rgba(76, 175, 80, 0.2);
    margin: 1.5rem -2rem;
}
.ticket-divider::before,
.ticket-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    background: var(--bg-light);
    border-radius: 50%;
    border: 2px dashed rgba(76, 175, 80, 0.25);
}
.ticket-divider::before { left: -12px; }
.ticket-divider::after  { right: -12px; }

/* ── Items Lists ── */
.conf-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--primary-green);
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.conf-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(76, 175, 80, 0.2);
}

.conf-item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.6rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    font-size: 0.92rem;
}
.conf-item-row:last-child { border-bottom: none; }
.conf-item-row .item-name { font-weight: 600; color: var(--text-dark); }
.conf-item-row .item-price { font-weight: 600; color: var(--primary-green); white-space: nowrap; margin-left: 1rem; }
.conf-item-row .item-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; }

/* ── Receipt ── */
.receipt-card {
    background: linear-gradient(135deg, #f8fdf9 0%, #f1f8e9 100%);
    border: 1px solid rgba(76, 175, 80, 0.18);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0;
    font-size: 0.93rem;
    border-bottom: 1px solid rgba(76, 175, 80, 0.1);
}
.receipt-row:last-child { border-bottom: none; }
.receipt-row .r-label { color: var(--text-muted); font-weight: 500; }
.receipt-row .r-val   { font-weight: 600; color: var(--text-dark); }

.receipt-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0 0;
    margin-top: 0.5rem;
    border-top: 2px solid rgba(76, 175, 80, 0.25);
}
.receipt-total .t-label {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-dark);
}
.receipt-total .t-val {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--primary-green);
}

/* ── Payment submitted card ── */
.payment-submitted-card {
    border-radius: 16px;
    border: 1.5px solid rgba(76, 175, 80, 0.3);
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(46, 125, 50, 0.08);
}
.payment-submitted-card .psc-header {
    background: linear-gradient(135deg, #2E7D32 0%, #43A047 100%);
    color: #fff;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 700;
    font-size: 1rem;
}
.payment-submitted-card .psc-body { padding: 1.5rem; background: #fff; }

/* ── What Happens Next ── */
.next-steps-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid rgba(76, 175, 80, 0.15);
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
}
.next-steps-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.next-steps-timeline {
    position: relative;
    padding-left: 2rem;
}
.next-steps-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 12px;
    bottom: 12px;
    width: 2px;
    background: linear-gradient(180deg, var(--primary-green), rgba(76,175,80,0.2));
    border-radius: 99px;
}

.ns-step {
    position: relative;
    margin-bottom: 1.25rem;
    padding-left: 0.5rem;
    animation: fadeSlideUp 0.4s ease both;
}
.ns-step:last-child { margin-bottom: 0; }
.ns-step::before {
    content: '';
    position: absolute;
    left: -1.6rem;
    top: 5px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--primary-green);
    box-shadow: 0 0 0 3px rgba(76,175,80,0.12);
}
.ns-step.done::before {
    background: var(--primary-green);
    border-color: var(--primary-green);
}
.ns-step.done::after {
    content: '✓';
    position: absolute;
    left: calc(-1.6rem + 1px);
    top: 4px;
    width: 12px;
    height: 12px;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    text-align: center;
    line-height: 12px;
}

.ns-step .ns-label {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 0.95rem;
    margin-bottom: 0.2rem;
}
.ns-step .ns-desc {
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.5;
}

/* ── Action Buttons ── */
.conf-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 2rem;
}
.conf-actions .btn {
    padding: 0.75rem 1.75rem;
    border-radius: 99px;
    font-weight: 600;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.conf-actions .btn-primary-action {
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color: #fff;
    border: none;
    box-shadow: 0 4px 16px rgba(46, 125, 50, 0.35);
}
.conf-actions .btn-primary-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46, 125, 50, 0.45);
    color: #fff;
}
.conf-actions .btn-secondary-action {
    background: #fff;
    color: var(--primary-green);
    border: 2px solid rgba(76, 175, 80, 0.45);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.conf-actions .btn-secondary-action:hover {
    background: #f1f8f1;
    transform: translateY(-2px);
}
.conf-actions .btn-whatsapp-action {
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: #fff;
    border: none;
    box-shadow: 0 4px 16px rgba(37, 211, 102, 0.35);
}
.conf-actions .btn-whatsapp-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.50);
    color: #fff;
}

/* ── Important note ── */
.conf-note {
    background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
    border: 1px solid rgba(76, 175, 80, 0.2);
    border-left: 4px solid var(--primary-green);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
}
.conf-note strong { color: var(--dark-green); }
.conf-note ul { margin: 0.5rem 0 0; padding-left: 1.25rem; color: var(--text-muted); }
.conf-note ul li { margin-bottom: 0.35rem; }

/* ── Menu items display ── */
.menu-items-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
}
.menu-item-pill {
    background: #e8f5e9;
    color: var(--dark-green);
    border-radius: 99px;
    padding: 0.2rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 500;
    border: 1px solid rgba(46,125,50,0.15);
}

/* ── Vendors ── */
.vendor-card-mini {
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.07);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.88rem;
}
.vendor-card-mini .v-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.vendor-card-mini .v-name { font-weight: 700; color: var(--text-dark); }
.vendor-card-mini .v-meta { color: var(--text-muted); margin-top: 0.1rem; line-height: 1.4; }

/* ── Payment slip ── */
.payment-slip-preview {
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid rgba(76,175,80,0.2);
    max-width: 280px;
}

@media (max-width: 600px) {
    .ticket-body { padding: 1.25rem; }
    .ticket-header { padding: 1rem 1.25rem; }
    .info-grid { grid-template-columns: 1fr; gap: 1.25rem; }
    .conf-hero { padding: 3rem 0 2.5rem; }
    .next-steps-card { padding: 1.25rem; }
    .conf-actions .btn { width: 100%; justify-content: center; }
}

@media print {
    .conf-hero,
    .conf-actions,
    .next-steps-card,
    nav, footer, .mobile-bottom-nav {
        display: none !important;
    }
    .booking-ticket {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    .conf-note { break-inside: avoid; }
}
</style>

<!-- ── Hero ── -->
<div class="conf-hero">
    <div class="deco-circle c1"></div>
    <div class="deco-circle c2"></div>
    <div class="deco-circle c3"></div>
    <div class="confetti-dots" id="confettiDots"></div>

    <div class="container" style="position:relative;z-index:2;">
        <div class="conf-check-wrap">
            <i class="fas fa-check"></i>
        </div>
        <h1>Booking Confirmed!</h1>
        <p class="lead-text">Your reservation has been successfully placed. We look forward to making your event special.</p>
        <div class="booking-ref-badge">
            <span class="ref-label">Booking Ref</span>
            <span class="ref-number"><?php echo sanitize($booking['booking_number']); ?></span>
        </div>
    </div>
</div>

<!-- ── Main Content ── -->
<section class="py-5" style="background: var(--bg-light);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">

                <!-- ── Booking Ticket Card ── -->
                <div class="booking-ticket">
                    <div class="ticket-header">
                        <h5 class="ticket-title">
                            <i class="fas fa-ticket-alt text-success"></i> Booking Details
                        </h5>
                        <div class="ticket-status-pills">
                            <span class="status-pill booking">
                                <i class="fas fa-circle" style="font-size:0.5em"></i>
                                <?php echo ucfirst($booking['booking_status']); ?>
                            </span>
                            <span class="status-pill payment <?php echo ($payment_submitted) ? 'submitted' : ''; ?>">
                                <i class="fas fa-circle" style="font-size:0.5em"></i>
                                <?php echo $payment_submitted ? 'Payment Submitted' : ucfirst($booking['payment_status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="ticket-body">
                        <!-- Info grid: Customer + Event + Venue -->
                        <div class="info-grid">
                            <!-- Customer -->
                            <div class="info-block">
                                <div class="info-block-title"><i class="fas fa-user-circle"></i> Customer</div>
                                <div class="info-row">
                                    <span class="info-key">Name</span>
                                    <span class="info-val"><?php echo sanitize($booking['full_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Phone</span>
                                    <span class="info-val"><?php echo sanitize($booking['phone']); ?></span>
                                </div>
                                <?php if ($booking['email']): ?>
                                <div class="info-row">
                                    <span class="info-key">Email</span>
                                    <span class="info-val"><?php echo sanitize($booking['email']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($booking['address']): ?>
                                <div class="info-row">
                                    <span class="info-key">Address</span>
                                    <span class="info-val"><?php echo sanitize($booking['address']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Event -->
                            <div class="info-block">
                                <div class="info-block-title"><i class="fas fa-calendar-star"></i> Event</div>
                                <div class="info-row">
                                    <span class="info-key">Type</span>
                                    <span class="info-val"><?php echo sanitize($booking['event_type']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Date</span>
                                    <span class="info-val">
                                        <?php echo date('D, d M Y', strtotime($booking['event_date'])); ?>
                                        <small class="text-muted fw-normal d-block"><?php echo convertToNepaliDate($booking['event_date']); ?></small>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Shift</span>
                                    <span class="info-val">
                                        <?php echo ucfirst($booking['shift']); ?>
                                        <?php if ($conf_has_display_time): ?>
                                            <small class="text-muted fw-normal d-block"><?php echo formatBookingTime($conf_display_start); ?> – <?php echo formatBookingTime($conf_display_end); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Guests</span>
                                    <span class="info-val"><?php echo $booking['number_of_guests']; ?> persons</span>
                                </div>
                            </div>

                            <!-- Venue -->
                            <div class="info-block">
                                <div class="info-block-title"><i class="fas fa-map-marked-alt"></i> Venue &amp; Hall</div>
                                <div class="info-row">
                                    <span class="info-key">Venue</span>
                                    <span class="info-val"><?php echo sanitize($booking['venue_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Hall</span>
                                    <span class="info-val"><?php echo sanitize($booking['hall_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-key">Location</span>
                                    <span class="info-val">
                                        <?php echo sanitize($booking['location']); ?>
                                        <?php if (!empty($booking['map_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($booking['map_link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ms-1 text-success small">
                                                <i class="fas fa-map-marker-alt"></i> Map
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (!empty($booking['capacity'])): ?>
                                <div class="info-row">
                                    <span class="info-key">Capacity</span>
                                    <span class="info-val"><?php echo $booking['capacity']; ?> persons</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div><!-- /info-grid -->

                        <!-- ── Menus ── -->
                        <?php if (!empty($booking['menus'])): ?>
                        <div class="ticket-divider"></div>
                        <div class="conf-section-title"><i class="fas fa-utensils"></i> Selected Menus</div>
                        <?php foreach ($booking['menus'] as $menu): ?>
                            <div class="conf-item-row">
                                <div>
                                    <div class="item-name"><?php echo sanitize($menu['menu_name']); ?></div>
                                    <div class="item-sub"><?php echo formatCurrency($menu['price_per_person']); ?>/pax × <?php echo $menu['number_of_guests']; ?> guests</div>
                                    <?php if (!empty($menu['items'])): ?>
                                        <div class="menu-items-pills mt-1">
                                            <?php
                                            foreach ($menu['items'] as $item) {
                                                echo '<span class="menu-item-pill">' . sanitize($item['item_name']) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-price"><?php echo formatCurrency($menu['total_price']); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- ── Services ── -->
                        <?php
                        $user_services = [];
                        $admin_services = [];
                        if (!empty($booking['services']) && is_array($booking['services'])) {
                            foreach ($booking['services'] as $service) {
                                if (isset($service['added_by']) && $service['added_by'] === 'admin') {
                                    $admin_services[] = $service;
                                } else {
                                    $user_services[] = $service;
                                }
                            }
                        }
                        if (!empty($user_services)):
                        ?>
                        <div class="ticket-divider"></div>
                        <div class="conf-section-title"><i class="fas fa-star"></i> Additional Services</div>
                        <?php foreach ($user_services as $service):
                            $s_price = floatval($service['price'] ?? 0);
                            $s_qty   = intval($service['quantity'] ?? 1);
                        ?>
                            <div class="conf-item-row">
                                <div>
                                    <div class="item-name"><?php echo sanitize($service['service_name']); ?></div>
                                    <?php if ($s_qty > 1): ?><div class="item-sub">Qty: <?php echo $s_qty; ?> × <?php echo formatCurrency($s_price); ?></div><?php endif; ?>
                                </div>
                                <div class="item-price"><?php echo formatCurrency($s_price * $s_qty); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($admin_services)): ?>
                        <div class="ticket-divider"></div>
                        <div class="conf-section-title"><i class="fas fa-user-shield"></i> Admin Added Services</div>
                        <?php foreach ($admin_services as $service):
                            $s_price = floatval($service['price'] ?? 0);
                            $s_qty   = intval($service['quantity'] ?? 1);
                        ?>
                            <div class="conf-item-row">
                                <div>
                                    <div class="item-name"><?php echo sanitize($service['service_name']); ?></div>
                                    <?php if (!empty($service['description'])): ?><div class="item-sub"><?php echo sanitize($service['description']); ?></div><?php endif; ?>
                                </div>
                                <div class="item-price"><?php echo formatCurrency($s_price * $s_qty); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- ── Vendors ── -->
                        <?php if (!empty($vendors)): ?>
                        <div class="ticket-divider"></div>
                        <div class="conf-section-title"><i class="fas fa-user-tie"></i> Assigned Vendors</div>
                        <div class="row g-2 mb-1">
                            <?php foreach ($vendors as $vendor): ?>
                            <div class="col-md-6">
                                <div class="vendor-card-mini">
                                    <div class="v-icon"><i class="fas fa-user-tie"></i></div>
                                    <div>
                                        <div class="v-name"><?php echo sanitize($vendor['vendor_name']); ?>
                                            <span class="fw-normal text-muted"> · <?php echo sanitize(getVendorTypeLabel($vendor['vendor_type'])); ?></span>
                                        </div>
                                        <div class="v-meta">
                                            <?php if (!empty($vendor['vendor_phone'])): ?><i class="fas fa-phone me-1"></i><?php echo sanitize($vendor['vendor_phone']); ?> &ensp;<?php endif; ?>
                                            <?php if (!empty($vendor['vendor_city'])): ?><i class="fas fa-map-marker-alt me-1"></i><?php echo sanitize($vendor['vendor_city']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- ── Special Requests ── -->
                        <?php if ($booking['special_requests']): ?>
                        <div class="ticket-divider"></div>
                        <div class="conf-section-title"><i class="fas fa-comment-alt"></i> Special Requests</div>
                        <p class="mb-0 text-muted small" style="font-style:italic;"><?php echo nl2br(sanitize($booking['special_requests'])); ?></p>
                        <?php endif; ?>

                    </div><!-- /ticket-body -->
                </div><!-- /booking-ticket -->

                <!-- ── Receipt / Cost Breakdown ── -->
                <div class="receipt-card">
                    <div class="conf-section-title" style="margin-bottom:1rem;"><i class="fas fa-receipt"></i> Cost Summary</div>
                    <?php if ($booking['hall_price'] > 0): ?>
                    <div class="receipt-row">
                        <span class="r-label"><i class="fas fa-building me-1 text-success"></i> Hall Cost</span>
                        <span class="r-val"><?php echo formatCurrency($booking['hall_price']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['menu_total'] > 0): ?>
                    <div class="receipt-row">
                        <span class="r-label"><i class="fas fa-utensils me-1 text-success"></i> Menu Cost</span>
                        <span class="r-val"><?php echo formatCurrency($booking['menu_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['services_total'] > 0): ?>
                    <div class="receipt-row">
                        <span class="r-label"><i class="fas fa-star me-1 text-success"></i> Services Cost</span>
                        <span class="r-val"><?php echo formatCurrency($booking['services_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['packages_total']) && $booking['packages_total'] > 0): ?>
                    <div class="receipt-row">
                        <span class="r-label"><i class="fas fa-box me-1 text-success"></i> Packages Cost</span>
                        <span class="r-val"><?php echo formatCurrency($booking['packages_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="receipt-row">
                        <span class="r-label">Subtotal</span>
                        <span class="r-val"><?php echo formatCurrency($booking['subtotal']); ?></span>
                    </div>
                    <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                    <div class="receipt-row">
                        <span class="r-label">Tax (<?php echo getSetting('tax_rate', '13'); ?>%)</span>
                        <span class="r-val"><?php echo formatCurrency($booking['tax_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="receipt-total">
                        <span class="t-label">Grand Total</span>
                        <span class="t-val"><?php echo formatCurrency($booking['grand_total']); ?></span>
                    </div>
                </div>

                <!-- ── Payment Submitted Card ── -->
                <?php if ($payment_submitted && !empty($payments)): ?>
                <div class="payment-submitted-card">
                    <div class="psc-header"><i class="fas fa-check-circle"></i> Payment Submitted</div>
                    <div class="psc-body">
                        <div class="alert alert-success border-0 rounded-3 mb-3 py-2 px-3 d-flex align-items-start gap-2" style="font-size:0.9rem;">
                            <i class="fas fa-info-circle mt-1"></i>
                            <span>Payment details submitted! Our team will verify and update your booking status shortly.</span>
                        </div>
                        <?php foreach ($payments as $payment): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="receipt-row"><span class="r-label">Method</span><span class="r-val"><?php echo sanitize($payment['payment_method_name'] ?? '—'); ?></span></div>
                                <?php if (!empty($payment['transaction_id'])): ?>
                                <div class="receipt-row"><span class="r-label">Transaction ID</span><span class="r-val" style="font-family:monospace"><?php echo sanitize($payment['transaction_id']); ?></span></div>
                                <?php endif; ?>
                                <div class="receipt-row"><span class="r-label">Paid Amount</span><span class="r-val text-success fw-bold"><?php echo formatCurrency($payment['paid_amount']); ?></span></div>
                                <div class="receipt-row"><span class="r-label">Date</span><span class="r-val"><?php echo date('d M Y, g:i A', strtotime($payment['payment_date'])); ?><br><small class="text-muted"><?php echo convertToNepaliDate($payment['payment_date']); ?></small></span></div>
                                <div class="receipt-row"><span class="r-label">Status</span>
                                    <span class="status-pill payment" style="font-size:0.72rem;"><?php echo ucfirst($payment['payment_status']); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($payment['payment_slip']) && validateUploadedFilePath($payment['payment_slip'])): ?>
                            <div class="col-md-6 text-center">
                                <p class="text-muted small mb-2">Payment Slip</p>
                                <div class="payment-slip-preview mx-auto">
                                    <img src="<?php echo UPLOAD_URL . sanitize($payment['payment_slip']); ?>" alt="Payment Slip" class="img-fluid">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── What Happens Next ── -->
                <div class="next-steps-card">
                    <div class="next-steps-title"><i class="fas fa-route text-success"></i> What Happens Next</div>
                    <div class="next-steps-timeline">
                        <div class="ns-step done">
                            <div class="ns-label">Booking Received</div>
                            <div class="ns-desc">Your booking has been submitted and saved in our system.</div>
                        </div>
                        <div class="ns-step">
                            <div class="ns-label">Team Review & Confirmation</div>
                            <div class="ns-desc">Our team will review and contact you within 24 hours to confirm details.</div>
                        </div>
                        <div class="ns-step">
                            <div class="ns-label">Payment Verification</div>
                            <div class="ns-desc">If you submitted payment, it will be verified and your status updated.</div>
                        </div>
                        <div class="ns-step">
                            <div class="ns-label">Your Big Day!</div>
                            <div class="ns-desc">Sit back and enjoy your special event — we handle the rest.</div>
                        </div>
                    </div>
                </div>

                <!-- ── Action Buttons ── -->
                <div class="conf-actions">
                    <button onclick="window.print()" class="btn btn-secondary-action">
                        <i class="fas fa-print"></i> Print Booking
                    </button>
                    <?php if (!empty($whatsapp_url)): ?>
                    <a href="<?php echo htmlspecialchars($whatsapp_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp-action">
                        <i class="fab fa-whatsapp"></i> Confirm via WhatsApp
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary-action">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>

                <!-- ── Important Note ── -->
                <div class="conf-note">
                    <strong><i class="fas fa-info-circle me-1"></i> Important Information</strong>
                    <ul>
                        <li>Save your booking reference: <strong><?php echo sanitize($booking['booking_number']); ?></strong></li>
                        <li>Our team will contact you within 24 hours to confirm your booking and payment details.</li>
                        <li>For any queries, call us at <strong><?php echo getSetting('contact_phone'); ?></strong></li>
                    </ul>
                </div>

            </div><!-- /col -->
        </div><!-- /row -->
    </div><!-- /container -->
</section>

<script>
// Confetti dots — container uses overflow:hidden so dots start at top:0 and animate downward
(function () {
    var container = document.getElementById('confettiDots');
    if (!container) return;
    var colors = ['#ffffff','#a5d6a7','#c8e6c9','#fff9c4','#ffe082'];
    for (var i = 0; i < 40; i++) {
        var dot = document.createElement('div');
        dot.className = 'conf-dot';
        dot.style.cssText = [
            'left:'   + (Math.random() * 100) + '%',
            'top:0',
            'background:' + colors[Math.floor(Math.random() * colors.length)],
            'width:'  + (Math.random() * 8 + 4)  + 'px',
            'height:' + (Math.random() * 8 + 4)  + 'px',
            'animation-duration:' + (Math.random() * 2 + 1.5) + 's',
            'animation-delay:'    + (Math.random() * 1.5)     + 's',
            'border-radius:' + (Math.random() > 0.5 ? '50%' : '2px')
        ].join(';');
        container.appendChild(dot);
    }
})();
// Clear saved booking form draft after a booking is successfully confirmed
try { localStorage.removeItem('bookingDraft'); } catch(e) {}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
