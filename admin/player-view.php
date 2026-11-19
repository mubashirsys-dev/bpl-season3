<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Player Profile & Payment History View
 * Classic High-End Sports UI — Structured & Responsive
 */

$pageTitle = "Player Profile Details";
$activeNav = 'dashboard';
require_once __DIR__ . '/admin-header.php';

$playerId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM players WHERE id = ?");
$stmt->execute([$playerId]);
$player = $stmt->fetch();

if (!$player) {
    setFlash('danger', 'Player record not found.');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

// Fetch all associated payments
$pmtStmt = $db->prepare("SELECT * FROM payments WHERE player_id = ? ORDER BY id DESC");
$pmtStmt->execute([$player['id']]);
$payments = $pmtStmt->fetchAll();

$hasPhoto = (!empty($player['photo_path']) && file_exists(ROOT_PATH . '/' . $player['photo_path']));
$photoUrl = $hasPhoto ? BASE_URL . '/' . sanitize($player['photo_path']) : '';
$isPaid = ($player['payment_status'] === 'PAID');
$isPending = ($player['payment_status'] === 'PENDING');
?>

<div class="adm-pv-container">
  
  <!-- Navigation & Action Bar -->
  <div class="adm-pv-topbar">
    <div class="adm-pv-nav-links">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-btn adm-btn-outline">
        &larr; Table View
      </a>
      <a href="<?= BASE_URL ?>/admin/gallery.php" class="adm-btn adm-btn-outline">
        &larr; All Players
      </a>
    </div>

    <div class="adm-pv-actions">
      <a href="<?= BASE_URL ?>/admin/export-pdf.php?id=<?= $player['id'] ?>" class="adm-btn adm-btn-pdf" title="Download Official Player Profile PDF">
        Download PDF
      </a>
      <a href="<?= BASE_URL ?>/success.php?reg_id=<?= urlencode($player['registration_id']) ?>" target="_blank" class="adm-btn adm-btn-outline">
        Public Receipt &nearr;
      </a>
      <a href="<?= BASE_URL ?>/admin/player-edit.php?id=<?= $player['id'] ?>" class="adm-btn adm-btn-primary">
        Edit Player
      </a>
    </div>
  </div>

  <!-- Player Identity Card (Hero Pass) -->
  <div class="adm-pv-hero-card">
    <div class="adm-pv-hero-layout">
      
      <!-- Player Photograph -->
      <div class="adm-pv-photo-frame">
        <?php if ($hasPhoto): ?>
          <a href="<?= $photoUrl ?>" target="_blank" title="Click to view full photo">
            <img 
              src="<?= $photoUrl ?>" 
              alt="<?= sanitize($player['full_name']) ?>" 
              class="adm-pv-photo-img"
            >
          </a>
        <?php else: ?>
          <div class="adm-pv-photo-empty">
            <span class="adm-pv-empty-initials"><?= strtoupper(substr(sanitize($player['full_name']), 0, 2)) ?></span>
            <span class="adm-pv-empty-label">No Photo</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Player Identity Info -->
      <div class="adm-pv-hero-details">
        <div class="adm-pv-hero-badges">
          <span class="adm-pv-reg-badge">
            <?= sanitize($player['registration_id']) ?>
          </span>
          <?php if ($isPaid): ?>
            <span class="adm-pv-status-pill paid">
              VERIFIED PAID
            </span>
          <?php elseif ($isPending): ?>
            <span class="adm-pv-status-pill pending">
              PAYMENT PENDING
            </span>
          <?php else: ?>
            <span class="adm-pv-status-pill failed">
              <?= sanitize($player['payment_status']) ?>
            </span>
          <?php endif; ?>
          <span class="adm-pv-fee-pill">
            Fee: <?= formatMoney($player['registration_fee']) ?>
          </span>
        </div>

        <h1 class="adm-pv-player-name">
          <?= sanitize($player['full_name']) ?>
        </h1>

        <!-- Quick Summary Chips -->
        <div class="adm-pv-chip-row">
          <div class="adm-pv-chip">
            <span class="adm-pv-chip-key">Position</span>
            <span class="adm-pv-chip-val accent-green"><?= sanitize($player['playing_position']) ?></span>
          </div>
          <div class="adm-pv-chip">
            <span class="adm-pv-chip-key">Club</span>
            <span class="adm-pv-chip-val"><?= sanitize($player['club_name']) ?></span>
          </div>
          <div class="adm-pv-chip">
            <span class="adm-pv-chip-key">Jersey Size</span>
            <span class="adm-pv-chip-val"><?= sanitize($player['jersey_size']) ?></span>
          </div>
          <div class="adm-pv-chip">
            <span class="adm-pv-chip-key">Status</span>
            <span class="adm-pv-chip-val"><?= sanitize($player['registration_status'] ?? 'CONFIRMED') ?></span>
          </div>
        </div>

        <div class="adm-pv-reg-date">
          Registered on: <strong><?= date('d M Y, h:i A', strtotime($player['created_at'])) ?></strong>
        </div>
      </div>

    </div>
  </div>

  <!-- Segmented Structured Sections (Bento Grid) -->
  <div class="adm-pv-bento-grid">
    
    <!-- Card 1: Football Profile -->
    <div class="adm-pv-box">
      <div class="adm-pv-box-title">
        Football &amp; League Details
      </div>
      <div class="adm-pv-tile-grid">
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Playing Position</div>
          <div class="adm-pv-tile-val accent-green"><?= sanitize($player['playing_position']) ?></div>
        </div>
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Club / Team</div>
          <div class="adm-pv-tile-val"><?= sanitize($player['club_name']) ?></div>
        </div>
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Jersey Size</div>
          <div class="adm-pv-tile-val"><span class="adm-pv-jersey-pill"><?= sanitize($player['jersey_size']) ?></span></div>
        </div>
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Registration Status</div>
          <div class="adm-pv-tile-val"><?= sanitize($player['registration_status'] ?? 'CONFIRMED') ?></div>
        </div>
      </div>
    </div>

    <!-- Card 2: Contact Information -->
    <div class="adm-pv-box">
      <div class="adm-pv-box-title">
        Contact Details
      </div>
      <div class="adm-pv-tile-grid">
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Primary Mobile</div>
          <div class="adm-pv-tile-val">
            <a href="tel:<?= sanitize($player['primary_contact']) ?>" class="adm-pv-phone-link">
              <?= formatPhone($player['primary_contact']) ?>
            </a>
          </div>
        </div>
        <div class="adm-pv-tile">
          <div class="adm-pv-tile-label">Alternative Contact</div>
          <div class="adm-pv-tile-val">
            <?php if (!empty($player['alternative_contact'])): ?>
              <a href="tel:<?= sanitize($player['alternative_contact']) ?>" class="adm-pv-phone-link">
                <?= formatPhone($player['alternative_contact']) ?>
              </a>
            <?php else: ?>
              <span class="adm-pv-muted-dash">— None —</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Residential Address Box -->
      <div class="adm-pv-address-card">
        <div class="adm-pv-tile-label">Full Residential Address</div>
        <div class="adm-pv-address-text"><?= nl2br(sanitize($player['full_address'])) ?></div>
      </div>
    </div>

  </div>

  <!-- Card 3: Razorpay Payment & Verification Details -->
  <div class="adm-pv-box" style="margin-top:20px;">
    <div class="adm-pv-box-title">
      Payment &amp; Transaction Details
    </div>
    <div class="adm-pv-tile-grid four-cols">
      <div class="adm-pv-tile">
        <div class="adm-pv-tile-label">Payment Status</div>
        <div class="adm-pv-tile-val">
          <?php if ($isPaid): ?>
            <span class="adm-status-badge paid">PAID</span>
          <?php elseif ($isPending): ?>
            <span class="adm-status-badge pending">PENDING</span>
          <?php else: ?>
            <span class="adm-status-badge failed"><?= sanitize($player['payment_status']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="adm-pv-tile">
        <div class="adm-pv-tile-label">Registration Fee</div>
        <div class="adm-pv-tile-val" style="color:var(--adm-green-light);">
          <?= formatMoney($player['registration_fee']) ?>
        </div>
      </div>

      <div class="adm-pv-tile">
        <div class="adm-pv-tile-label">Razorpay Order ID</div>
        <div class="adm-pv-tile-val">
          <?php if (!empty($player['razorpay_order_id'])): ?>
            <code class="adm-pv-code blue"><?= sanitize($player['razorpay_order_id']) ?></code>
          <?php else: ?>
            <span class="adm-pv-muted-dash">—</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="adm-pv-tile">
        <div class="adm-pv-tile-label">Razorpay Payment ID</div>
        <div class="adm-pv-tile-val">
          <?php if (!empty($player['razorpay_payment_id'])): ?>
            <code class="adm-pv-code green"><?= sanitize($player['razorpay_payment_id']) ?></code>
          <?php else: ?>
            <span class="adm-pv-muted-dash">—</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: Complete Payment Audit Trail -->
  <div class="adm-pv-box" style="margin-top:20px;">
    <div class="adm-pv-box-title" style="display:flex;justify-content:space-between;align-items:center;">
      <span>Razorpay Payment Attempts &amp; Logs</span>
      <span style="font-size:0.75rem;font-weight:600;color:var(--adm-muted);">
        <?= count($payments) ?> <?= count($payments) === 1 ? 'Attempt' : 'Attempts' ?>
      </span>
    </div>

    <?php if (empty($payments)): ?>
      <div style="text-align:center;padding:28px;color:var(--adm-muted);font-size:0.85rem;">
        No transaction attempts recorded for this player yet.
      </div>
    <?php else: ?>
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Payment ID</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th>Timestamp</th>
              <th>Audit Log</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $pmt): ?>
              <tr>
                <td><code class="adm-pv-code blue"><?= sanitize($pmt['razorpay_order_id']) ?></code></td>
                <td><code class="adm-pv-code green"><?= sanitize($pmt['razorpay_payment_id'] ?? '—') ?></code></td>
                <td><strong style="color:#ffffff;"><?= formatMoney($pmt['amount']) ?></strong></td>
                <td><span class="adm-pv-method-tag"><?= sanitize(strtoupper($pmt['payment_method'] ?? 'ONLINE')) ?></span></td>
                <td>
                  <span class="adm-status-badge <?= $pmt['status'] === 'PAID' ? 'paid' : ($pmt['status'] === 'FAILED' ? 'failed' : 'pending') ?>">
                    <?= sanitize($pmt['status']) ?>
                  </span>
                </td>
                <td style="font-size:0.78rem;color:var(--adm-muted);white-space:nowrap;">
                  <?= date('d M Y, h:i A', strtotime($pmt['created_at'])) ?>
                </td>
                <td style="font-size:0.78rem;color:<?= $pmt['status'] === 'PAID' ? '#34d399' : '#f87171' ?>;">
                  <?= sanitize($pmt['error_description'] ?? 'Payment verified successfully') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
