<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$current_user = getCurrentUser();
$db = getDB();

/**
 * Recalculate and apply booking payment_status, booking_status, and
 * advance_payment_received based on the sum of verified payments.
 * Must be called inside an active transaction.
 *
 * @param PDO $db
 * @param int $booking_id
 * @param float $grand_total
 * @return array ['payment_status', 'booking_status', 'advance_payment_received', 'total_verified']
 */
function applyVerifiedPaymentStatus($db, $booking_id, $grand_total) {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(paid_amount), 0) AS total_verified
         FROM payments WHERE booking_id = ? AND payment_status = 'verified'"
    );
    $stmt->execute([$booking_id]);
    $row = $stmt->fetch();
    $total_verified = floatval($row['total_verified']);

    if ($total_verified >= $grand_total && $total_verified > 0) {
        $new_payment_status = 'paid';
    } elseif ($total_verified > 0) {
        $new_payment_status = 'partial';
    } else {
        $new_payment_status = 'pending';
    }

    $auto = getAutoStatusByPaymentStatus($new_payment_status);

    $stmt = $db->prepare(
        "UPDATE bookings SET payment_status = ?, booking_status = ?, advance_payment_received = ? WHERE id = ?"
    );
    $stmt->execute([$new_payment_status, $auto['booking_status'], $auto['advance_payment_received'], $booking_id]);

    return [
        'payment_status'           => $new_payment_status,
        'booking_status'           => $auto['booking_status'],
        'advance_payment_received' => $auto['advance_payment_received'],
        'total_verified'           => $total_verified,
    ];
}

$action = isset($_POST['action']) ? trim($_POST['action']) : 'record';

try {
    if ($action === 'record') {
        // --- Record a new payment received ---
        $booking_id        = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $paid_amount_raw   = isset($_POST['paid_amount']) ? trim($_POST['paid_amount']) : '';
        $payment_method_id = (isset($_POST['payment_method_id']) && intval($_POST['payment_method_id']) > 0)
            ? intval($_POST['payment_method_id']) : null;
        $transaction_id    = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : null;
        $notes             = isset($_POST['notes']) ? trim($_POST['notes']) : null;

        if ($booking_id <= 0) {
            throw new Exception('Invalid booking ID.');
        }
        if (!is_numeric($paid_amount_raw) || floatval($paid_amount_raw) <= 0) {
            throw new Exception('Please enter a valid payment amount greater than zero.');
        }
        $paid_amount = floatval($paid_amount_raw);

        // Verify booking exists
        $stmt = $db->prepare("SELECT id, grand_total, booking_number FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            throw new Exception('Booking not found.');
        }

        // Handle optional payment slip upload
        $payment_slip_filename = null;
        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['payment_slip'], 'payment-slips');
            if (!$upload_result['success']) {
                throw new Exception('Payment slip upload failed: ' . $upload_result['message']);
            }
            $payment_slip_filename = $upload_result['filename'];
        } elseif (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_error = $_FILES['payment_slip']['error'];
            if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                throw new Exception('Payment slip file is too large. Maximum size is 5MB.');
            }
            throw new Exception('Payment slip upload error (code ' . $upload_error . '). Please try again.');
        }

        $db->beginTransaction();

        // Insert payment as 'verified' since admin is directly confirming receipt
        $stmt = $db->prepare(
            "INSERT INTO payments (booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, payment_status, notes)
             VALUES (?, ?, ?, ?, ?, 'verified', ?)"
        );
        $stmt->execute([
            $booking_id,
            $payment_method_id,
            ($transaction_id !== '' ? $transaction_id : null),
            $paid_amount,
            $payment_slip_filename,
            ($notes !== '' ? $notes : null),
        ]);
        $payment_id = $db->lastInsertId();

        $result = applyVerifiedPaymentStatus($db, $booking_id, floatval($booking['grand_total']));

        $db->commit();

        logActivity(
            $current_user['id'],
            'Recorded payment received',
            'bookings',
            $booking_id,
            'Recorded payment of ' . number_format($paid_amount, 2)
                . ' for booking: ' . $booking['booking_number']
                . '; payment_status set to ' . $result['payment_status']
        );

        echo json_encode([
            'success'                  => true,
            'message'                  => 'Payment recorded successfully.',
            'payment_id'               => (int)$payment_id,
            'new_payment_status'       => $result['payment_status'],
            'booking_status'           => $result['booking_status'],
            'advance_payment_received' => $result['advance_payment_received'],
            'total_verified'           => $result['total_verified'],
        ]);

    } elseif ($action === 'update_status') {
        // --- Update status of an existing payment transaction (verify / reject / pending) ---
        $payment_id        = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $new_status        = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';
        $allowed_statuses  = ['pending', 'verified', 'rejected'];

        if ($payment_id <= 0) {
            throw new Exception('Invalid payment ID.');
        }
        if (!in_array($new_status, $allowed_statuses, true)) {
            throw new Exception('Invalid payment status.');
        }

        // Fetch existing payment and its booking
        $stmt = $db->prepare(
            "SELECT p.*, b.grand_total, b.booking_number
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             WHERE p.id = ?"
        );
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        if (!$payment) {
            throw new Exception('Payment record not found.');
        }
        $booking_id = (int)$payment['booking_id'];

        $db->beginTransaction();

        // Update the payment's own status
        $stmt = $db->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
        $stmt->execute([$new_status, $payment_id]);

        $result = applyVerifiedPaymentStatus($db, $booking_id, floatval($payment['grand_total']));

        $db->commit();

        logActivity(
            $current_user['id'],
            'Updated payment transaction status',
            'bookings',
            $booking_id,
            'Payment #' . $payment_id . ' marked as ' . $new_status
                . ' for booking: ' . $payment['booking_number']
                . '; booking payment_status set to ' . $result['payment_status']
        );

        echo json_encode([
            'success'                  => true,
            'message'                  => 'Payment status updated.',
            'payment_id'               => $payment_id,
            'payment_status'           => $new_status,
            'new_payment_status'       => $result['payment_status'],
            'booking_status'           => $result['booking_status'],
            'advance_payment_received' => $result['advance_payment_received'],
            'total_verified'           => $result['total_verified'],
        ]);

    } else {
        throw new Exception('Unknown action.');
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
