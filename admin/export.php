<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Player CSV Export
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminAuth();

$db = getDB();

// Apply any active filters from query parameters
$search = trim($_GET['search'] ?? '');
$filterClub = trim($_GET['club'] ?? '');
$filterPos = trim($_GET['position'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterDate = trim($_GET['date'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $cleanPhone = cleanPhone($search);
    $where[] = "(full_name LIKE ? OR registration_id LIKE ? OR primary_contact LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$cleanPhone}%";
}

if (!empty($filterClub)) {
    $where[] = "club_name = ?";
    $params[] = $filterClub;
}

if (!empty($filterPos)) {
    $where[] = "playing_position = ?";
    $params[] = $filterPos;
}

if (!empty($filterStatus)) {
    $where[] = "payment_status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterDate)) {
    $where[] = "DATE(created_at) = ?";
    $params[] = $filterDate;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT * FROM players {$whereSql} ORDER BY id ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);

$filename = 'bpl_players_season3_' . date('Ymd_His') . '.csv';

// Output CSV headers
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header Row
fputcsv($output, [
    'Registration ID',
    'Player Full Name',
    'Playing Position',
    'Club Name',
    'Jersey Size',
    'Primary Contact',
    'Alternative Contact',
    'Full Address',
    'Fee (INR)',
    'Registration Status',
    'Payment Status',
    'Razorpay Order ID',
    'Razorpay Payment ID',
    'Photograph URL',
    'Registration Date'
]);

while ($row = $stmt->fetch()) {
    $photoUrl = !empty($row['photo_path']) ? BASE_URL . '/' . $row['photo_path'] : '';
    fputcsv($output, [
        $row['registration_id'],
        $row['full_name'],
        $row['playing_position'],
        $row['club_name'],
        $row['jersey_size'],
        $row['primary_contact'],
        $row['alternative_contact'] ?? '',
        str_replace(["\r", "\n"], ' ', $row['full_address']),
        $row['registration_fee'],
        $row['registration_status'],
        $row['payment_status'],
        $row['razorpay_order_id'] ?? '',
        $row['razorpay_payment_id'] ?? '',
        $photoUrl,
        $row['created_at']
    ]);
}

fclose($output);
exit;

