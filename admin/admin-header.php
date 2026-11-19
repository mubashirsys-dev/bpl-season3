<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Unified Admin Panel Header
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminAuth();

$activeNav = $activeNav ?? 'dashboard';
$tournamentName = getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE');
$tournamentSeason = getSetting('tournament_season', 'SEASON 3 — 2026–27');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= sanitize($pageTitle ?? 'Admin Dashboard') ?> | BPL Admin</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bpl-crest.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Dedicated Admin Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=3.5.0">
</head>
<body class="admin-body">

  <!-- Compact Responsive Navbar -->
  <header class="adm-navbar">
    <div class="adm-nav-inner">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-brand">
        <img src="<?= BASE_URL ?>/assets/images/bpl-crest.svg" alt="BPL Crest" class="adm-logo">
        <div class="adm-brand-text">
          <span class="adm-title"><?= sanitize($tournamentName) ?></span>
          <span class="adm-badge">ADMIN</span>
        </div>
      </a>

      <!-- Hamburger toggle on mobile -->
      <button type="button" class="adm-nav-toggle" id="adm_nav_toggle" aria-label="Toggle Navigation">
        ☰
      </button>

      <!-- Navigation links -->
      <nav>
        <ul class="adm-nav-menu" id="adm_nav_menu">
          <li>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
              Dashboard
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/gallery.php" class="adm-nav-link <?= $activeNav === 'gallery' ? 'active' : '' ?>">
              All Players
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/payments.php" class="adm-nav-link <?= $activeNav === 'payments' ? 'active' : '' ?>">
              Payments
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="adm-nav-link <?= $activeNav === 'settings' ? 'active' : '' ?>">
              Settings
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/export-pdf.php" class="adm-nav-link" style="color:var(--adm-green-light);">
              Master PDF
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/export.php" class="adm-nav-link" style="color:var(--adm-blue);">
              Download CSV
            </a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/admin/logout.php" class="adm-nav-link danger">
              Logout
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Mobile nav toggle script -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const toggle = document.getElementById('adm_nav_toggle');
      const menu = document.getElementById('adm_nav_menu');
      if (toggle && menu) {
        toggle.addEventListener('click', function () {
          menu.classList.toggle('show');
          toggle.textContent = menu.classList.contains('show') ? '✕' : '☰';
        });
      }
    });
  </script>

  <main class="adm-container">
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
      <div class="alert-bpl alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:20px;padding:12px;border-radius:6px;font-size:0.85rem;background:<?= $flash['type'] === 'success' ? '#064e3b' : '#450a0a' ?>;color:#ffffff;border:1px solid <?= $flash['type'] === 'success' ? '#34d399' : '#f87171' ?>;">
        <?= sanitize($flash['message']) ?>
      </div>
    <?php endif; ?>
