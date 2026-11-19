<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Vercel Serverless Function Entry Point & Front Controller
 * Routes incoming web requests to the appropriate root or admin PHP script.
 */

// Determine project root directory (one level up from /api)
$rootDir = dirname(__DIR__);

// Extract requested path from REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Normalize path: strip leading/trailing slashes
$path = trim($parsedPath, '/');

// 1. Static file check (e.g. assets, favicon, robots.txt, etc.)
if ($path !== '' && !str_ends_with($path, '.php')) {
    $filePath = $rootDir . '/' . $path;
    if (file_exists($filePath) && !is_dir($filePath)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = [
            'css'  => 'text/css; charset=UTF-8',
            'js'   => 'application/javascript; charset=UTF-8',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'json' => 'application/json',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain; charset=UTF-8',
        ];
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        header("Content-Type: {$contentType}");
        header("Cache-Control: public, max-age=31536000, immutable");
        readfile($filePath);
        exit;
    }

    // If an asset path was requested (e.g. starts with assets/ or uploads/) but wasn't found,
    // return a clean 404 text response — NEVER fall through to index.php HTML!
    if (str_starts_with($path, 'assets/') || str_starts_with($path, 'uploads/')) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "404 Not Found: Static asset not available.";
        exit;
    }
}

// 2. Map clean URL paths to physical PHP files
if ($path === '' || $path === 'index.php') {
    $targetFile = $rootDir . '/index.php';
} elseif ($path === 'admin' || $path === 'admin/' || $path === 'admin/index.php') {
    $targetFile = $rootDir . '/admin/index.php';
} elseif ($path === 'admin.php') {
    $targetFile = $rootDir . '/admin.php';
} elseif (str_starts_with($path, 'admin/')) {
    $subPath = substr($path, 6);
    if (!str_ends_with($subPath, '.php')) {
        $subPath .= '.php';
    }
    $targetFile = $rootDir . '/admin/' . basename($subPath);
} elseif (str_starts_with($path, 'api/')) {
    $subPath = substr($path, 4);
    if (!str_ends_with($subPath, '.php')) {
        $subPath .= '.php';
    }
    $targetFile = $rootDir . '/api/' . basename($subPath);
} else {
    $fileName = basename($path);
    if (!str_ends_with($fileName, '.php')) {
        $fileName .= '.php';
    }
    $targetFile = $rootDir . '/' . $fileName;
}

// Fallback: If target doesn't exist, try index.php or return 404
if (!file_exists($targetFile) || is_dir($targetFile)) {
    if (file_exists($rootDir . '/index.php')) {
        $targetFile = $rootDir . '/index.php';
    } else {
        http_response_code(404);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "404 Not Found";
        exit;
    }
}

// Ensure working directory is set to target file's directory
chdir(dirname($targetFile));

// Adjust $_SERVER environment variables so the included script behaves naturally
$_SERVER['SCRIPT_FILENAME'] = $targetFile;
$_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace($rootDir, '', $targetFile), '/\\');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

// Include and execute the requested script
require $targetFile;
