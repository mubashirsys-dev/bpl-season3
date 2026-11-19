<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Registration & Payment Confirmation
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$regId = trim($_GET['reg_id'] ?? '');

if (empty($regId)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM players WHERE registration_id = ?");
$stmt->execute([$regId]);
$player = $stmt->fetch();

if (!$player) {
    $pageTitle = "Registration Not Found";
    require_once __DIR__ . '/includes/header.php';
    echo '<main class="bpl-page-wrapper"><div class="bpl-card" style="text-align:center;padding:30px;"><div class="alert-bpl alert-danger">Registration record not found.</div></div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$isConfirmed = ($player['registration_status'] === 'CONFIRMED' && $player['payment_status'] === 'PAID');
$pageTitle = $isConfirmed ? "Registration Successful — " . $player['registration_id'] : "Payment Processing";
require_once __DIR__ . '/includes/header.php';
?>

<main class="bpl-page-wrapper">
  <div class="bpl-card" style="text-align:center;">
    
    <?php if ($isConfirmed): ?>
      <!-- GREEN SUCCESS ICON -->
      <div class="success-icon-wrap">✓</div>

      <!-- REGISTRATION SUCCESSFUL! -->
      <h2 style="font-size:1.45rem;font-weight:800;color:#0f172a;text-transform:uppercase;margin:0 0 6px 0;">
        REGISTRATION SUCCESSFUL!
      </h2>

      <p style="color:#475569;font-size:0.9rem;margin:0 0 20px 0;line-height:1.4;">
        Thank you for registering for Buddilane Premier League Season 3.<br>
        Your payment has been successfully received and your registration is confirmed.
      </p>

      <!-- Details List -->
      <div class="success-details-box">
        <div class="success-detail-row">
          <span class="detail-label">Player Name</span>
          <span class="detail-val"><?= sanitize($player['full_name']) ?></span>
        </div>

        <div class="success-detail-row">
          <span class="detail-label">Registration ID</span>
          <span class="detail-val" style="color:#059669;font-family:monospace;font-size:1.05rem;"><?= sanitize($player['registration_id']) ?></span>
        </div>

        <div class="success-detail-row">
          <span class="detail-label">Payment Amount</span>
          <span class="detail-val"><?= formatMoney($player['registration_fee']) ?></span>
        </div>

        <div class="success-detail-row">
          <span class="detail-label">Payment Status</span>
          <span class="detail-val"><span class="badge-status badge-paid">PAID</span></span>
        </div>

        <div class="success-detail-row">
          <span class="detail-label">Registration Status</span>
          <span class="detail-val"><span class="badge-status badge-paid">CONFIRMED</span></span>
        </div>
      </div>

      <!-- THANK YOU Message -->
      <div class="thank-you-note">
        <strong>THANK YOU!</strong><br>
        We look forward to seeing you on the pitch!
      </div>

      <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;">
        <button type="button" onclick="window.print()" class="btn-receipt btn-receipt-primary" style="padding:10px 18px;font-size:0.85rem;">
          Print Confirmation
        </button>
      </div>

    <?php else: ?>
      <!-- Payment Pending / Processing Screen -->
      <h2 style="font-size:1.3rem;font-weight:800;color:#0f172a;margin:0 0 8px 0;text-transform:uppercase;">
        PAYMENT PROCESSING
      </h2>
      <p style="color:#64748b;font-size:0.88rem;margin:0 0 18px 0;">
        Payment confirmation is currently being verified for registration <strong><?= sanitize($player['registration_id']) ?></strong>.
      </p>
      
      <div style="background:#f8fafc;padding:14px;border-radius:8px;margin-bottom:18px;font-size:0.85rem;text-align:left;">
        <div>Status: <strong><?= sanitize($player['payment_status']) ?></strong></div>
        <div>Player: <strong><?= sanitize($player['full_name']) ?></strong></div>
      </div>

      <a href="<?= BASE_URL ?>/payment-status.php?reg_id=<?= urlencode($player['registration_id']) ?>" class="btn-proceed-payment" style="max-width:240px;margin:0 auto;font-size:0.9rem;padding:12px;">
        Check Status / Retry
      </a>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
