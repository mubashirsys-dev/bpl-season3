<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Minimal Header Component
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Buddilane Premier League — Season 3 (2026–27)';
$tournamentName = getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE');
$tournamentSeason = getSetting('tournament_season', 'SEASON 3 — 2026–27');
$regFee = getSetting('registration_fee', '100');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= sanitize($pageTitle) ?></title>
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bpl-crest.svg">
  
  <!-- Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Minimal Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=3.3.0">

  <!-- Official Razorpay Standard Checkout SDK -->
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

  <script>
    const APP_CONFIG = {
      baseUrl: '<?= rtrim(BASE_URL, '/') ?>',
      registrationFee: <?= (int)$regFee ?>
    };
  </script>
</head>
<body>

  <!-- Minimal Brand Header -->
  <header class="bpl-header">
    <div class="bpl-header-brand">
      <img src="<?= BASE_URL ?>/assets/images/bpl-crest.svg" alt="BPL Logo" class="bpl-logo">
      <div class="bpl-title-group">
        <h1 class="bpl-main-title"><?= sanitize($tournamentName) ?></h1>
        <p class="bpl-season-title"><?= sanitize($tournamentSeason) ?></p>
      </div>
    </div>
  </header>
