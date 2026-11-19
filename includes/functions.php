<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Core Utility Functions & Helpers
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Generate CSRF Token and store in session
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render hidden CSRF input field for forms
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Sanitize string output
 */
function sanitize(?string $str): string {
    if ($str === null) {
        return '';
    }
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean phone number to 10 digits
 */
function cleanPhone(?string $phone): string {
    if (empty($phone)) {
        return '';
    }
    // Remove all non-numeric characters
    $digits = preg_replace('/\D/', '', $phone);
    // If it starts with 91 and has 12 digits, strip country code
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    // If it starts with 0 and has 11 digits, strip 0
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    return $digits;
}

/**
 * Validate Indian phone number (10 digits starting with 6-9)
 */
function isValidPhone(string $phone): bool {
    $cleaned = cleanPhone($phone);
    return (bool)preg_match('/^[6-9]\d{9}$/', $cleaned);
}

/**
 * Format phone number for display
 */
function formatPhone(?string $phone): string {
    $cleaned = cleanPhone($phone);
    if (strlen($cleaned) === 10) {
        return '+91 ' . substr($cleaned, 0, 5) . ' ' . substr($cleaned, 5);
    }
    return sanitize($phone);
}

/**
 * Format currency in Indian Rupees
 */
function formatMoney($amount): string {
    return '₹' . number_format((float)$amount, 0);
}

/**
 * Retrieve setting from database with static caching
 */
function getSetting(string $key, string $default = ''): string {
    static $settingsCache = null;

    if ($settingsCache === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            error_log("Failed to load settings: " . $e->getMessage());
            $settingsCache = [];
        }
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Update or insert a setting
 */
function updateSetting(string $key, ?string $value): bool {
    try {
        $db = getDB();
        $dbType = DB_TYPE;
        if ($dbType === 'sqlite') {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP");
        } else {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
        }
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("Failed to update setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate Next Unique Registration ID
 * Format: BPL-2026-0001, BPL-2026-0002...
 */
function generateRegistrationId(): string {
    $db = getDB();
    $year = '2026';
    $prefix = "BPL-{$year}-";

    // Find the highest sequence number for this season
    $stmt = $db->prepare("SELECT registration_id FROM players WHERE registration_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();

    $nextNum = 1;
    if ($lastId) {
        $parts = explode('-', $lastId);
        if (isset($parts[2]) && is_numeric($parts[2])) {
            $nextNum = ((int)$parts[2]) + 1;
        }
    }

    // Ensure uniqueness
    do {
        $candidate = sprintf("%s%04d", $prefix, $nextNum);
        $check = $db->prepare("SELECT COUNT(*) FROM players WHERE registration_id = ?");
        $check->execute([$candidate]);
        if ($check->fetchColumn() == 0) {
            return $candidate;
        }
        $nextNum++;
    } while (true);
}

/**
 * Secure Player Photo Upload Handler
 * - Max size: 5MB
 * - Valid formats: JPG, JPEG, PNG, WEBP
 * - Verifies real MIME type using finfo
 * - Stores unique filename in uploads/players/
 */
function handlePhotoUpload(array $file): array {
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'Player photograph is required.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File size exceeds server upload limit (Maximum 5MB).'];
        default:
            return ['success' => false, 'error' => 'An error occurred during file upload.'];
    }

    // Max 5MB (5,242,880 bytes)
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed 5 MB.'];
    }

    // Verify MIME Type with finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return [
            'success' => false,
            'error'   => 'Invalid file format. Only JPG, JPEG, PNG, and WebP photographs are accepted.'
        ];
    }

    // Verify that the file is an actual image
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'Uploaded file is not a valid image.'];
    }

    $ext = $allowedMimes[$mimeType];
    $uploadDir = UPLOAD_DIR;

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Upload directory could not be created.'];
        }
    }

    // Generate cryptographically unique filename
    $uniqueName = 'player_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = $uploadDir . $uniqueName;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded photo to storage directory.'];
    }

    // Relative web path to store in DB
    $relativeDbPath = 'uploads/players/' . $uniqueName;

    return [
        'success'   => true,
        'filename'  => $uniqueName,
        'path'      => $relativeDbPath,
        'full_path' => $destination,
    ];
}

/**
 * Set a session flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message,
    ];
}

/**
 * Get and clear session flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check if Admin is logged in
 */
function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_user']);
}

/**
 * Require Admin authentication; redirect to login if unauthorized
 */
function requireAdminAuth(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Brute force login protection
 */
function isLoginLocked(string $ip): bool {
    try {
        $db = getDB();
        $windowTime = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?");
        $stmt->execute([$ip, $windowTime]);
        $attempts = (int)$stmt->fetchColumn();
        return $attempts >= 5;
    } catch (Exception $e) {
        return false;
    }
}

function recordLoginAttempt(string $ip, string $username): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
        // Silently log
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

function clearLoginAttempts(string $ip): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    } catch (Exception $e) {
        error_log("Failed to clear login attempts: " . $e->getMessage());
    }
}

/**
 * Output JSON API response and terminate
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

