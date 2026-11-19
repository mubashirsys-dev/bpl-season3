<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * API: Razorpay Webhook Listener
 *
 * Handles asynchronous Razorpay events (order.paid, payment.captured, payment.failed)
 * with raw payload HMAC verification, idempotency, and downgrade protection.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/razorpay.php';
require_once __DIR__ . '/../includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// 1. Get raw request body and signature header
$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty request payload.']);
    exit;
}

// 2. Verify signature with separate Razorpay Webhook Secret
$isWebhookValid = RazorpayService::verifyWebhookSignature($rawPayload, $signatureHeader);

if (!$isWebhookValid) {
    http_response_code(400);
    error_log("Razorpay Webhook signature verification failed!");
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature.']);
    exit;
}

// Parse JSON payload
$data = json_decode($rawPayload, true);
if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook data structure.']);
    exit;
}

$eventType = $data['event'];
$eventId = $data['event_id'] ?? ('evt_' . hash('sha256', $rawPayload));

$db = getDB();

try {
    // 3. Idempotency Check: Prevent duplicate webhook processing
    $stmt = $db->prepare("SELECT id, status FROM webhook_events WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $existingEvent = $stmt->fetch();

    if ($existingEvent) {
        // Already processed
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Event already processed.']);
        exit;
    }

    // Extract Order and Payment details
    $orderId = null;
    $paymentId = null;
    $paymentEntity = null;
    $orderEntity = null;

    if (isset($data['payload']['payment']['entity'])) {
        $paymentEntity = $data['payload']['payment']['entity'];
        $paymentId = $paymentEntity['id'] ?? null;
        $orderId = $paymentEntity['order_id'] ?? null;
    }

    if (isset($data['payload']['order']['entity'])) {
        $orderEntity = $data['payload']['order']['entity'];
        if (!$orderId) {
            $orderId = $orderEntity['id'] ?? null;
        }
    }

    // Log incoming event into webhook_events table
    $logStmt = $db->prepare("
        INSERT INTO webhook_events (event_id, event_type, order_id, payment_id, payload, status)
        VALUES (?, ?, ?, ?, ?, 'PROCESSED')
    ");
    $logStmt->execute([$eventId, $eventType, $orderId, $paymentId, $rawPayload]);

    // If there is no Order ID, we cannot map to player
    if (empty($orderId)) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'No order_id associated with event.']);
        exit;
    }

    // Find Player associated with this Order ID
    $pStmt = $db->prepare("SELECT * FROM players WHERE razorpay_order_id = ?");
    $pStmt->execute([$orderId]);
    $player = $pStmt->fetch();

    if (!$player) {
        // Could be receipt matching
        if (isset($orderEntity['receipt']) && !empty($orderEntity['receipt'])) {
            $rStmt = $db->prepare("SELECT * FROM players WHERE registration_id = ?");
            $rStmt->execute([$orderEntity['receipt']]);
            $player = $rStmt->fetch();
        }
    }

    if (!$player) {
        // Order not found in database; acknowledge webhook to avoid retries
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Player record not found for order ' . $orderId]);
        exit;
    }

    // 4. Handle Specific Events
    switch ($eventType) {
        case 'order.paid':
        case 'payment.captured':
            // Verify payment capture if payment entity is present
            $isCaptured = true;
            if ($paymentEntity && isset($paymentEntity['status']) && $paymentEntity['status'] !== 'captured') {
                $isCaptured = false;
            }

            if ($isCaptured) {
                $db->beginTransaction();

                // Update Player
                $updatePlayer = $db->prepare("
                    UPDATE players 
                    SET registration_status = 'CONFIRMED',
                        payment_status = 'PAID',
                        razorpay_payment_id = COALESCE(?, razorpay_payment_id),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updatePlayer->execute([$paymentId, $player['id']]);

                // Update or Insert Payment
                $pmtCheck = $db->prepare("SELECT id FROM payments WHERE razorpay_order_id = ?");
                $pmtCheck->execute([$orderId]);
                $pmtId = $pmtCheck->fetchColumn();

                $method = $paymentEntity['method'] ?? 'webhook';

                if ($pmtId) {
                    $updatePmt = $db->prepare("
                        UPDATE payments 
                        SET razorpay_payment_id = COALESCE(?, razorpay_payment_id),
                            status = 'PAID',
                            payment_method = ?,
                            error_code = NULL,
                            error_description = NULL,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updatePmt->execute([$paymentId, $method, $pmtId]);
                } else {
                    $insertPmt = $db->prepare("
                        INSERT INTO payments (
                            player_id, registration_id, razorpay_order_id, razorpay_payment_id,
                            amount, currency, status, payment_method
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, 'INR', 'PAID', ?
                        )
                    ");
                    $insertPmt->execute([
                        $player['id'],
                        $player['registration_id'],
                        $orderId,
                        $paymentId,
                        $player['registration_fee'],
                        $method
                    ]);
                }

                $db->commit();
            }
            break;

        case 'payment.failed':
            // CRITICAL RULE 8: Never downgrade an already captured/confirmed registration!
            if ($player['registration_status'] === 'CONFIRMED' || $player['payment_status'] === 'PAID') {
                error_log("Ignoring payment.failed event for already CONFIRMED player {$player['registration_id']}");
                break;
            }

            // Record failure details but KEEP registration in PENDING_PAYMENT
            $errorCode = $paymentEntity['error_code'] ?? 'PAYMENT_FAILED';
            $errorDesc = $paymentEntity['error_description'] ?? 'Transaction failed at gateway';

            $db->beginTransaction();

            $updatePmt = $db->prepare("
                UPDATE payments 
                SET status = 'FAILED',
                    razorpay_payment_id = COALESCE(?, razorpay_payment_id),
                    error_code = ?,
                    error_description = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE razorpay_order_id = ?
            ");
            $updatePmt->execute([$paymentId, $errorCode, $errorDesc, $orderId]);

            // Keep player in PENDING_PAYMENT so they can retry
            $updatePlayer = $db->prepare("
                UPDATE players 
                SET payment_status = 'FAILED',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND payment_status != 'PAID'
            ");
            $updatePlayer->execute([$player['id']]);

            $db->commit();
            break;

        default:
            // Unhandled event acknowledged
            break;
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => "Webhook event {$eventType} processed successfully."]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Webhook processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error processing webhook.']);
}

