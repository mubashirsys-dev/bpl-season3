<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Simple Status & Retry Payment Portal
 */

$pageTitle = "Registration Status & Payment Retry";
require_once __DIR__ . '/includes/header.php';

$searchQuery = trim($_GET['reg_id'] ?? $_GET['search'] ?? '');
$player = null;
$errorMsg = null;

if (!empty($searchQuery)) {
    $db = getDB();
    $cleanPhone = cleanPhone($searchQuery);
    
    $stmt = $db->prepare("SELECT * FROM players WHERE registration_id = ? OR primary_contact = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$searchQuery, $cleanPhone]);
    $player = $stmt->fetch();

    if (!$player) {
        $errorMsg = "No registration found matching '" . sanitize($searchQuery) . "'.";
    }
}
?>

<main class="bpl-page-wrapper">
  <div class="bpl-card">
    <div class="bpl-card-header" style="margin-bottom:16px;">
      <h2 class="bpl-form-title">CHECK STATUS / RETRY PAYMENT</h2>
    </div>

    <!-- Search Form -->
    <form method="GET" action="<?= BASE_URL ?>/payment-status.php" style="display:flex;gap:8px;margin-bottom:18px;">
      <input 
        type="text" 
        name="search" 
        class="form-control" 
        placeholder="Enter Registration ID or Phone Number" 
        value="<?= sanitize($searchQuery) ?>" 
        required 
        style="flex:1;"
      >
      <button type="submit" class="btn-proceed-payment" style="width:auto;margin:0;padding:10px 18px;font-size:0.85rem;">
        Search
      </button>
    </form>

    <?php if (!empty($errorMsg)): ?>
      <div class="alert-bpl alert-danger" style="margin-bottom:18px;">
        <div><?= $errorMsg ?></div>
      </div>
    <?php endif; ?>

    <?php if ($player): ?>
      <?php $isPaid = ($player['registration_status'] === 'CONFIRMED' && $player['payment_status'] === 'PAID'); ?>

      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <strong style="color:#0f172a;font-size:1.05rem;"><?= sanitize($player['full_name']) ?></strong>
          <span class="badge-status <?= $isPaid ? 'badge-paid' : 'badge-pending' ?>">
            <?= sanitize($player['payment_status']) ?>
          </span>
        </div>
        <div style="font-size:0.85rem;color:#64748b;margin-bottom:4px;">
          Reg ID: <strong style="color:#059669;font-family:monospace;"><?= sanitize($player['registration_id']) ?></strong>
        </div>
        <div style="font-size:0.85rem;color:#64748b;">
          Position: <?= sanitize($player['playing_position']) ?> • Club: <?= sanitize($player['club_name']) ?>
        </div>
      </div>

      <?php if ($isPaid): ?>
        <a href="<?= BASE_URL ?>/success.php?reg_id=<?= urlencode($player['registration_id']) ?>" class="btn-proceed-payment" style="margin-top:0;">
          VIEW CONFIRMATION RECEIPT
        </a>
      <?php else: ?>
        <button type="button" id="btn_retry_payment" data-reg-id="<?= sanitize($player['registration_id']) ?>" class="btn-proceed-payment" style="margin-top:0;">
          RETRY PAYMENT (₹<?= (int)$player['registration_fee'] ?>)
        </button>
      <?php endif; ?>

    <?php endif; ?>

    <div style="text-align:center;margin-top:18px;">
      <a href="<?= BASE_URL ?>/index.php" style="color:#64748b;font-size:0.85rem;text-decoration:none;">
        ← Back to Registration Form
      </a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
