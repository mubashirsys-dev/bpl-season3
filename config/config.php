<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Core Configuration
 */

// Strict error reporting for development, logs to file
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logTarget = is_writable(__DIR__ . '/..') ? __DIR__ . '/../error.log' : sys_get_temp_dir() . '/bpl_error.log';
ini_set('error_log', $logTarget);

// Set Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!getenv($key) && !isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Database Credentials
define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql'); // 'mysql' or 'sqlite'
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'buddilane_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$defaultSqlite = is_writable(__DIR__ . '/..') 
    ? __DIR__ . '/../buddilane.sqlite' 
    : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'buddilane.sqlite';
define('DB_SQLITE_PATH', getenv('DB_SQLITE_PATH') ?: $defaultSqlite);

// App Paths & URLs
define('ROOT_PATH', dirname(__DIR__));

$defaultUploadDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'players' . DIRECTORY_SEPARATOR;
if (!is_dir($defaultUploadDir)) {
    @mkdir($defaultUploadDir, 0755, true);
}
if (!is_writable($defaultUploadDir) && !is_writable(ROOT_PATH)) {
    $defaultUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'players' . DIRECTORY_SEPARATOR;
    if (!is_dir($defaultUploadDir)) {
        @mkdir($defaultUploadDir, 0755, true);
    }
}
define('UPLOAD_DIR', $defaultUploadDir);

// Dynamically determine Base URL for Apache/XAMPP subfolders
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Normalize SCRIPT_NAME with forward slashes and ensure leading slash
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!str_starts_with($scriptName, '/')) {
        $scriptName = '/' . $scriptName;
    }
    
    // Check if BUDDILANE is in the path
    if (preg_match('#^(.*?(?:/BUDDILANE|/buddilane))#i', $scriptName, $matches)) {
        $basePath = rtrim($matches[1], '/');
    } else {
        $dir = dirname($scriptName);
        $basePath = preg_replace('#/(?:admin|api|includes|assets|tests)(?:/.*)?$#i', '', $dir);
        $basePath = rtrim($basePath, '/');
    }
    
    if (!empty($basePath) && !str_starts_with($basePath, '/')) {
        $basePath = '/' . $basePath;
    }
    
    define('BASE_URL', $protocol . $host . $basePath);
}

define('UPLOAD_URL', BASE_URL . '/uploads/players/');

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $tempDir = sys_get_temp_dir();
    if (is_dir($tempDir) && is_writable($tempDir)) {
        @session_save_path($tempDir);
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name('BPL_SESSID');
    @session_start();
}
