<?php
/**
 * Redirect /admin/players.php to the unified /admin/dashboard.php
 */
require_once __DIR__ . '/../config/config.php';
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . BASE_URL . '/admin/dashboard.php' . $queryString);
exit;
