-- ============================================================================
-- POLICY PAGES: Terms & Conditions, Privacy Policy, Refund Policy, etc.
-- ============================================================================
-- Creates the policy_pages table and seeds three default policy pages.
-- Run this migration once on existing installations.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `policy_pages` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `title`              VARCHAR(255)                    NOT NULL,
    `slug`               VARCHAR(255)                    NOT NULL,
    `content`            LONGTEXT                        NOT NULL DEFAULT '',
    `status`             ENUM('active','inactive')       NOT NULL DEFAULT 'active',
    `require_acceptance` TINYINT(1)                      NOT NULL DEFAULT 0
                         COMMENT 'When 1, users must accept this policy before completing a booking',
    `sort_order`         INT                             NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_policy_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- Seed: Terms and Conditions
-- ────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
'Terms and Conditions',
'terms-and-conditions',
'<h2>Terms and Conditions</h2>
<p>Welcome to our venue booking platform. By accessing or using our services, you agree to be bound by these Terms and Conditions. Please read them carefully before proceeding with a booking.</p>

<h3>1. Acceptance of Terms</h3>
<p>By making a booking, you confirm that you have read, understood, and agreed to these Terms and Conditions in their entirety. If you do not agree, please do not proceed with a booking.</p>

<h3>2. Booking and Confirmation</h3>
<p>All bookings are subject to availability. A booking is only confirmed once you receive a written confirmation from us. We reserve the right to decline any booking at our discretion.</p>

<h3>3. Payment</h3>
<p>An advance payment is required to secure your booking. The balance amount is due on or before the event date. Payment terms are outlined in your booking confirmation.</p>

<h3>4. Cancellation Policy</h3>
<p>Cancellations must be made in writing. Cancellation charges may apply depending on how far in advance the cancellation is made. Please refer to our Refund Policy for details.</p>

<h3>5. Liability</h3>
<p>We shall not be held liable for any loss, damage, or injury that occurs on the premises beyond our reasonable control. Clients are responsible for the conduct of their guests.</p>

<h3>6. Force Majeure</h3>
<p>We shall not be liable for any failure to perform our obligations where such failure results from circumstances beyond our reasonable control, including but not limited to natural disasters, government orders, or pandemic-related restrictions.</p>

<h3>7. Amendments</h3>
<p>We reserve the right to amend these Terms and Conditions at any time. Updates will be posted on this page and take effect immediately.</p>

<h3>8. Governing Law</h3>
<p>These Terms and Conditions are governed by the laws of Nepal. Any disputes shall be resolved through mutual discussion, and if unresolved, through the courts of Nepal.</p>

<p>If you have any questions about these Terms and Conditions, please contact us.</p>',
'active',
1,
10
);

-- ────────────────────────────────────────────────────────────────────────────
-- Seed: Privacy Policy
-- ────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
'Privacy Policy',
'privacy-policy',
'<h2>Privacy Policy</h2>
<p>Your privacy is important to us. This Privacy Policy explains how we collect, use, and protect your personal information when you use our venue booking services.</p>

<h3>1. Information We Collect</h3>
<p>We collect personal information that you provide directly to us, including:</p>
<ul>
  <li>Name, phone number, and email address</li>
  <li>Event details (date, type, number of guests)</li>
  <li>Payment information (transaction references; we do not store full card details)</li>
  <li>Any special requests or notes you provide</li>
</ul>

<h3>2. How We Use Your Information</h3>
<p>We use the information we collect to:</p>
<ul>
  <li>Process and manage your bookings</li>
  <li>Communicate with you about your event</li>
  <li>Send booking confirmations and reminders</li>
  <li>Improve our services and customer experience</li>
</ul>

<h3>3. Information Sharing</h3>
<p>We do not sell, trade, or transfer your personal information to third parties without your consent, except as required to provide our services (e.g., processing payments) or as required by law.</p>

<h3>4. Data Security</h3>
<p>We implement appropriate technical and organisational measures to protect your personal information against unauthorised access, alteration, disclosure, or destruction.</p>

<h3>5. Cookies</h3>
<p>We use cookies and similar technologies to enhance your experience on our website. You may disable cookies in your browser settings, though some features may not function correctly.</p>

<h3>6. Your Rights</h3>
<p>You have the right to access, correct, or request deletion of your personal information. To exercise these rights, please contact us.</p>

<h3>7. Changes to This Policy</h3>
<p>We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated effective date.</p>

<p>If you have any questions about this Privacy Policy, please contact us.</p>',
'active',
0,
20
);

-- ────────────────────────────────────────────────────────────────────────────
-- Seed: Refund Policy
-- ────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `policy_pages` (`title`, `slug`, `content`, `status`, `require_acceptance`, `sort_order`) VALUES (
'Refund Policy',
'refund-policy',
'<h2>Refund Policy</h2>
<p>We understand that plans can change. Please review our refund policy carefully before making a booking.</p>

<h3>1. Advance Payment</h3>
<p>The advance payment is required to confirm and hold your booking date. This amount is applied toward your total bill.</p>

<h3>2. Cancellation and Refund Schedule</h3>
<p>Refunds are processed based on the notice given before the event date:</p>
<ul>
  <li><strong>More than 30 days before the event:</strong> Full refund of the advance payment, less any administrative fees.</li>
  <li><strong>15–30 days before the event:</strong> 50% of the advance payment will be refunded.</li>
  <li><strong>Less than 15 days before the event:</strong> No refund will be issued.</li>
</ul>

<h3>3. How to Request a Refund</h3>
<p>To request a cancellation and refund, please contact us in writing (email or letter) with your booking number and the reason for cancellation. Verbal cancellations are not accepted.</p>

<h3>4. Refund Processing</h3>
<p>Approved refunds will be processed within 7–14 business days via the original payment method or bank transfer.</p>

<h3>5. Force Majeure</h3>
<p>In cases of events beyond our control (natural disasters, government-imposed restrictions, etc.), we will work with you to reschedule. Refunds in such cases will be assessed on a case-by-case basis.</p>

<h3>6. No-Show Policy</h3>
<p>If you fail to attend the event without prior cancellation notice, no refund will be issued.</p>

<p>For any refund-related queries, please contact us with your booking details.</p>',
'active',
1,
30
);
