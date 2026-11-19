<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * API: Server-Side Razorpay Payment Verification
 *
 * Implements HMAC-SHA256 signature verification and independent REST API capture confirmation.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/razorpay.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

// Read JSON payload or POST
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$registrationId = trim($data['registration_id'] ?? '');
$razorpayOrderId = trim($data['razorpay_order_id'] ?? '');
$razorpayPaymentId = trim($data['razorpay_payment_id'] ?? '');
$razorpaySignature = trim($data['razorpay_signature'] ?? '');

if (empty($registrationId) || empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
    jsonResponse(['success' => false, 'error' => 'Missing required payment verification parameters.'], 400);
}

$db = getDB();

try {
    // 1. Fetch player record by Registration ID
    $stmt = $db->prepare("SELECT * FROM players WHERE registration_id = ?");
    $stmt->execute([$registrationId]);
    $player = $stmt->fetch();

    if (!$player) {
        jsonResponse(['success' => false, 'error' => 'Invalid registration ID.'], 404);
    }

    // Idempotency: If already confirmed, return success immediately
    if ($player['registration_status'] === 'CONFIRMED' && $player['payment_status'] === 'PAID') {
        jsonResponse([
            'success'         => true,
            'message'         => 'Payment already verified and registration confirmed.',
            'registration_id' => $registrationId,
            'payment_id'      => $player['razorpay_payment_id']
        ]);
    }

    // Verify order ID matches registration
    if (!empty($player['razorpay_order_id']) && $player['razorpay_order_id'] !== $razorpayOrderId) {
        jsonResponse(['success' => false, 'error' => 'Order ID does not match the registration record.'], 400);
    }

    // 2. STEP 7: Verify HMAC-SHA256 signature
    $isSignatureValid = RazorpayService::verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);

    if (!$isSignatureValid) {
        // Record failed attempt
        $failStmt = $db->prepare("
            UPDATE payments 
            SET status = 'FAILED', 
                razorpay_payment_id = ?, 
                razorpay_signature = ?, 
                error_code = 'BAD_SIGNATURE', 
                error_description = 'HMAC signature verification failed', 
                updated_at = CURRENT_TIMESTAMP
            WHERE razorpay_order_id = ?
        ");
        $failStmt->execute([$razorpayPaymentId, $razorpaySignature, $razorpayOrderId]);

        jsonResponse(['success' => false, 'error' => 'Payment signature verification failed. Untrusted transaction.'], 400);
    }

    // 3. STEP 8: Independent Verification via Razorpay REST API
    $paymentCheck = RazorpayService::fetchPayment($razorpayPaymentId);

    if (!$paymentCheck['success']) {
        jsonResponse(['success' => false, 'error' => 'Could not verify payment status with Razorpay API.'], 500);
    }

    $paymentDetails = $paymentCheck['data'];
    $expectedAmountPaise = (int)round((float)$player['registration_fee'] * 100);

    // Verify amount matches exactly
    if (isset($paymentDetails['amount']) && (int)$paymentDetails['amount'] !== $expectedAmountPaise) {
        jsonResponse(['success' => false, 'error' => 'Payment amount mismatch. Expected: ' . $expectedAmountPaise], 400);
    }

    // Verify currency is INR
    if (isset($paymentDetails['currency']) && strtoupper($paymentDetails['currency']) !== 'INR') {
        jsonResponse(['success' => false, 'error' => 'Currency mismatch. Expected INR.'], 400);
    }

    // Verify payment status is captured
    $isCaptured = false;
    if (isset($paymentDetails['status']) && $paymentDetails['status'] === 'captured') {
        $isCaptured = true;
    } elseif (isset($paymentDetails['captured']) && $paymentDetails['captured'] === true) {
        $isCaptured = true;
    }

    if (!$isCaptured) {
        // Payment authorized but not yet captured
        $holdStmt = $db->prepare("
            UPDATE payments 
            SET status = 'PENDING', 
                razorpay_payment_id = ?, 
                razorpay_signature = ?, 
                error_description = 'Payment authorized but not captured', 
                updated_at = CURRENT_TIMESTAMP
            WHERE razorpay_order_id = ?
        ");
        $holdStmt->execute([$razorpayPaymentId, $razorpaySignature, $razorpayOrderId]);

        jsonResponse([
            'success' => false,
            'error'   => 'Payment is authorized but not yet captured by Razorpay. Please wait a moment.'
        ], 202);
    }

    // 4. STEP 9: Update Database within a Transaction
    $db->beginTransaction();

    // Update Player Status to CONFIRMED and PAID
    $updatePlayer = $db->prepare("
        UPDATE players 
        SET registration_status = 'CONFIRMED',
            payment_status = 'PAID',
            razorpay_order_id = ?,
            razorpay_payment_id = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $updatePlayer->execute([$razorpayOrderId, $razorpayPaymentId, $player['id']]);

    // Update or Insert Payment Record
    $checkPayment = $db->prepare("SELECT id FROM payments WHERE razorpay_order_id = ?");
    $checkPayment->execute([$razorpayOrderId]);
    $existingPmtId = $checkPayment->fetchColumn();

    $paymentMethod = $paymentDetails['method'] ?? 'unknown';

    if ($existingPmtId) {
        $updatePmt = $db->prepare("
            UPDATE payments 
            SET razorpay_payment_id = ?,
                razorpay_signature = ?,
                status = 'PAID',
                payment_method = ?,
                error_code = NULL,
                error_description = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updatePmt->execute([$razorpayPaymentId, $razorpaySignature, $paymentMethod, $existingPmtId]);
    } else {
        $insertPmt = $db->prepare("
            INSERT INTO payments (
                player_id, registration_id, razorpay_order_id, razorpay_payment_id,
                razorpay_signature, amount, currency, status, payment_method
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, 'INR', 'PAID', ?
            )
        ");
        $insertPmt->execute([
            $player['id'],
            $registrationId,
            $razorpayOrderId,
            $razorpayPaymentId,
            $razorpaySignature,
            $player['registration_fee'],
            $paymentMethod
        ]);
    }

    $db->commit();

    // Return Confirmed Success
    jsonResponse([
        'success'         => true,
        'message'         => 'Payment verified successfully and registration confirmed.',
        'registration_id' => $registrationId,
        'payment_id'      => $razorpayPaymentId,
        'status'          => 'CONFIRMED'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Payment verification error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'An error occurred during verification. Please contact support.'], 500);
}

