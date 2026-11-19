<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * API: Check Payment and Registration Status
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$regId = trim($_GET['reg_id'] ?? $_POST['reg_id'] ?? '');
$orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');

if (empty($regId) && empty($orderId)) {
    jsonResponse(['success' => false, 'error' => 'Registration ID or Order ID is required.'], 400);
}

try {
    $db = getDB();
    if (!empty($regId)) {
        $stmt = $db->prepare("SELECT id, registration_id, full_name, playing_position, club_name, registration_fee, registration_status, payment_status, razorpay_order_id, razorpay_payment_id, created_at, updated_at FROM players WHERE registration_id = ?");
        $stmt->execute([$regId]);
    } else {
        $stmt = $db->prepare("SELECT id, registration_id, full_name, playing_position, club_name, registration_fee, registration_status, payment_status, razorpay_order_id, razorpay_payment_id, created_at, updated_at FROM players WHERE razorpay_order_id = ?");
        $stmt->execute([$orderId]);
    }

    $player = $stmt->fetch();

    if (!$player) {
        jsonResponse(['success' => false, 'error' => 'No registration record found.'], 404);
    }

    $isConfirmed = ($player['registration_status'] === 'CONFIRMED' && $player['payment_status'] === 'PAID');

    jsonResponse([
        'success'             => true,
        'is_confirmed'        => $isConfirmed,
        'registration_id'     => $player['registration_id'],
        'player_name'         => $player['full_name'],
        'playing_position'    => $player['playing_position'],
        'club_name'           => $player['club_name'],
        'registration_fee'    => (float)$player['registration_fee'],
        'registration_status' => $player['registration_status'],
        'payment_status'      => $player['payment_status'],
        'razorpay_order_id'   => $player['razorpay_order_id'],
        'razorpay_payment_id' => $player['razorpay_payment_id'],
        'created_at'          => $player['created_at'],
        'updated_at'          => $player['updated_at']
    ]);

} catch (Exception $e) {
    error_log("Check payment status error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error while checking payment status.'], 500);
}

