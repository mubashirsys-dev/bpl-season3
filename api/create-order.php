<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * API: Create Razorpay Order & Pending Registration
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/razorpay.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

// Check if registration is open
$isRegOpen = (bool)(int)getSetting('registration_open', '1');
if (!$isRegOpen) {
    jsonResponse(['success' => false, 'error' => 'Player registrations are currently closed.'], 403);
}

// Check for JSON request body (e.g. for retrying an existing pending order)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$retryRegId = $_POST['retry_registration_id'] ?? $jsonData['retry_registration_id'] ?? null;

$db = getDB();

// -------------------------------------------------------------------------
// RETRY FLOW FOR EXISTING PENDING REGISTRATION
// -------------------------------------------------------------------------
if (!empty($retryRegId)) {
    $retryRegId = trim($retryRegId);
    $stmt = $db->prepare("SELECT * FROM players WHERE registration_id = ?");
    $stmt->execute([$retryRegId]);
    $player = $stmt->fetch();

    if (!$player) {
        jsonResponse(['success' => false, 'error' => 'Registration ID not found.'], 404);
    }

    if ($player['registration_status'] === 'CONFIRMED' || $player['payment_status'] === 'PAID') {
        jsonResponse([
            'success' => false,
            'error'   => 'This registration is already paid and confirmed.',
            'redirect' => BASE_URL . '/success.php?reg_id=' . urlencode($retryRegId)
        ], 400);
    }

    $feeAmount = (float)$player['registration_fee'];
    $amountInPaise = (int)round($feeAmount * 100);

    // Reuse or create order
    $orderResult = RazorpayService::createOrder(
        $amountInPaise,
        $player['registration_id'],
        [
            'registration_id' => $player['registration_id'],
            'player_name'     => $player['full_name'],
            'contact'         => $player['primary_contact']
        ]
    );

    if (!$orderResult['success']) {
        jsonResponse(['success' => false, 'error' => $orderResult['error'] ?? 'Failed to initialize payment.'], 500);
    }

    $orderId = $orderResult['order_id'];

    // Update player and insert payment attempt
    $updateStmt = $db->prepare("UPDATE players SET razorpay_order_id = ?, payment_status = 'PENDING', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$orderId, $player['id']]);

    $pmtStmt = $db->prepare("INSERT INTO payments (player_id, registration_id, razorpay_order_id, amount, currency, status) VALUES (?, ?, ?, ?, 'INR', 'CREATED')");
    $pmtStmt->execute([$player['id'], $player['registration_id'], $orderId, $feeAmount]);

    $rzpConfig = RazorpayService::getConfig();

    jsonResponse([
        'success'         => true,
        'order_id'        => $orderId,
        'key_id'          => $rzpConfig['key_id'],
        'amount'          => $amountInPaise,
        'registration_id' => $player['registration_id'],
        'player_name'     => $player['full_name'],
        'contact'         => $player['primary_contact'],
        'tournament_name' => getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE'),
    ]);
}

// -------------------------------------------------------------------------
// NEW REGISTRATION SUBMISSION FLOW
// -------------------------------------------------------------------------
// Verify CSRF Token
$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    jsonResponse(['success' => false, 'error' => 'Security token expired or invalid. Please refresh the page.'], 403);
}

// Validate required fields
$fullName = trim($_POST['full_name'] ?? '');
$clubName = trim($_POST['club_name'] ?? '');
$playingPosition = trim($_POST['playing_position'] ?? '');
$jerseySize = trim($_POST['jersey_size'] ?? '');
$primaryContact = trim($_POST['primary_contact'] ?? '');
$alternativeContact = trim($_POST['alternative_contact'] ?? '');
$fullAddress = trim($_POST['full_address'] ?? '');

$errors = [];

if (empty($fullName) || mb_strlen($fullName) < 2) {
    $errors[] = 'Player Full Name is required (minimum 2 characters).';
}

$validPositions = ['Goalkeeper', 'Defence', 'Midfielder', 'Striker'];
if (!in_array($playingPosition, $validPositions, true)) {
    $errors[] = 'Please select a valid Playing Position (Goalkeeper, Defence, Midfielder, or Striker).';
}

if (empty($clubName)) {
    $errors[] = 'Club Name is required.';
}

$validSizes = ['S', 'M', 'L', 'XL', 'XXL'];
if (!in_array($jerseySize, $validSizes, true)) {
    $errors[] = 'Please select a valid Jersey Size (S, M, L, XL, XXL).';
}

$cleanPrimary = cleanPhone($primaryContact);
if (!isValidPhone($cleanPrimary)) {
    $errors[] = 'Please provide a valid 10-digit Indian primary contact number.';
}

$cleanAlt = null;
if (!empty($alternativeContact)) {
    $cleanAlt = cleanPhone($alternativeContact);
    if (!empty($cleanAlt) && strlen($cleanAlt) < 10) {
        $errors[] = 'Alternative contact number must be at least 10 digits.';
    }
}

if (empty($fullAddress) || mb_strlen($fullAddress) < 5) {
    $errors[] = 'Full residential address is required.';
}

// Photograph validation and upload
if (!isset($_FILES['player_photo']) || $_FILES['player_photo']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Player photograph is required.';
}

if (!empty($errors)) {
    jsonResponse(['success' => false, 'error' => implode(' ', $errors)], 422);
}

// Process Photo Upload
$photoUpload = handlePhotoUpload($_FILES['player_photo']);
if (!$photoUpload['success']) {
    jsonResponse(['success' => false, 'error' => $photoUpload['error']], 422);
}
$photoPath = $photoUpload['path'];

// Registration Fee from Settings (Never trust browser values)
$feeSetting = (float)getSetting('registration_fee', '100');
if ($feeSetting <= 0) {
    $feeSetting = 100.00;
}
$amountInPaise = (int)round($feeSetting * 100);

// Generate Unique Registration ID
$registrationId = generateRegistrationId();

try {
    $db->beginTransaction();

    // Insert Player as PENDING_PAYMENT
    $insertPlayer = $db->prepare("
        INSERT INTO players (
            registration_id, full_name, photo_path, playing_position,
            club_name, jersey_size, primary_contact, alternative_contact,
            full_address, registration_fee, registration_status, payment_status
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, 'PENDING_PAYMENT', 'PENDING'
        )
    ");

    $insertPlayer->execute([
        $registrationId,
        $fullName,
        $photoPath,
        $playingPosition,
        $clubName,
        $jerseySize,
        $cleanPrimary,
        $cleanAlt,
        $fullAddress,
        $feeSetting
    ]);

    $playerId = (int)$db->lastInsertId();

    // Call Razorpay Orders API
    $orderResult = RazorpayService::createOrder(
        $amountInPaise,
        $registrationId,
        [
            'tournament'      => getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE'),
            'registration_id' => $registrationId,
            'player_name'     => $fullName,
            'contact'         => $cleanPrimary,
        ]
    );

    if (!$orderResult['success']) {
        $db->rollBack();
        // Remove uploaded photo if DB transaction fails
        if (file_exists($photoUpload['full_path'])) {
            @unlink($photoUpload['full_path']);
        }
        jsonResponse(['success' => false, 'error' => $orderResult['error'] ?? 'Could not create Razorpay order.'], 500);
    }

    $orderId = $orderResult['order_id'];

    // Update Player with Order ID
    $updatePlayer = $db->prepare("UPDATE players SET razorpay_order_id = ? WHERE id = ?");
    $updatePlayer->execute([$orderId, $playerId]);

    // Record Payment Entry
    $insertPmt = $db->prepare("
        INSERT INTO payments (
            player_id, registration_id, razorpay_order_id, amount, currency, status
        ) VALUES (
            ?, ?, ?, ?, 'INR', 'CREATED'
        )
    ");
    $insertPmt->execute([$playerId, $registrationId, $orderId, $feeSetting]);

    $db->commit();

    $rzpConfig = RazorpayService::getConfig();

    jsonResponse([
        'success'         => true,
        'order_id'        => $orderId,
        'key_id'          => $rzpConfig['key_id'],
        'amount'          => $amountInPaise,
        'registration_id' => $registrationId,
        'player_name'     => $fullName,
        'contact'         => $cleanPrimary,
        'tournament_name' => getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE'),
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if (isset($photoUpload['full_path']) && file_exists($photoUpload['full_path'])) {
        @unlink($photoUpload['full_path']);
    }
    error_log("Registration Order creation error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'An unexpected server error occurred. Please try again.'], 500);
}

