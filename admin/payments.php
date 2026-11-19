<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Payment Transactions & Audit Ledger
 */

$pageTitle = "Payment Transactions";
$activeNav = 'payments';
require_once __DIR__ . '/admin-header.php';

$db = getDB();

$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if (!empty($filterStatus)) {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $where[] = "(p.registration_id LIKE ? OR p.razorpay_order_id LIKE ? OR p.razorpay_payment_id LIKE ? OR pl.full_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) FROM payments p LEFT JOIN players pl ON p.player_id = pl.id {$whereSql}";
$cStmt = $db->prepare($countSql);
$cStmt->execute($params);
$totalRecords = (int)$cStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

$sql = "
    SELECT p.*, pl.full_name, pl.playing_position, pl.club_name, pl.primary_contact 
    FROM payments p 
    LEFT JOIN players pl ON p.player_id = pl.id 
    {$whereSql} 
    ORDER BY p.id DESC 
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Metrics summary
$totalCollected = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'PAID'")->fetchColumn();
$paidCount = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'PAID'")->fetchColumn();
$failedCount = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'FAILED'")->fetchColumn();
?>

<div>
  <!-- Header with Inflow summary -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:16px;">
    <div>
      <h1 style="font-size:1.45rem;font-weight:800;color:#ffffff;text-transform:uppercase;margin:0 0 4px 0;letter-spacing:0.4px;">
        Payment Transactions (<?= number_format($totalRecords) ?>)
      </h1>
      <p style="color:var(--adm-muted);font-size:0.85rem;margin:0;">
        Razorpay Standard Checkout transaction ledger &amp; automated verification logs
      </p>
    </div>
    <div class="adm-stat-card highlight" style="min-width:180px;padding:10px 16px;">
      <div class="adm-stat-title success-text">Verified Inflow</div>
      <div class="adm-stat-number green"><?= formatMoney($totalCollected) ?></div>
    </div>
  </div>

  <!-- Search & Filter Controls -->
  <div class="adm-card" style="padding:14px 18px;margin-bottom:20px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/payments.php" class="adm-filter-form">
      <input 
        type="text" 
        name="search" 
        class="adm-search-input" 
        placeholder="Search Order ID, Payment ID, Reg ID, Player" 
        value="<?= sanitize($search) ?>" 
      >

      <select name="status" class="adm-select">
        <option value="">All Statuses</option>
        <option value="PAID" <?= $filterStatus === 'PAID' ? 'selected' : '' ?>>PAID (<?= $paidCount ?>)</option>
        <option value="CREATED" <?= $filterStatus === 'CREATED' ? 'selected' : '' ?>>CREATED</option>
        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
        <option value="FAILED" <?= $filterStatus === 'FAILED' ? 'selected' : '' ?>>FAILED (<?= $failedCount ?>)</option>
        <option value="REFUNDED" <?= $filterStatus === 'REFUNDED' ? 'selected' : '' ?>>REFUNDED</option>
      </select>

      <button type="submit" class="adm-btn adm-btn-primary">
        Filter
      </button>
      <a href="<?= BASE_URL ?>/admin/payments.php" class="adm-btn adm-btn-outline">
        Reset
      </a>
    </form>
  </div>

  <!-- Ledger Table -->
  <div class="adm-card" style="padding:0;">
    <?php if (empty($payments)): ?>
      <div style="text-align:center;padding:40px 20px;color:var(--adm-muted);font-size:0.88rem;">
        No payment records found.
      </div>
    <?php else: ?>
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Player &amp; Reg ID</th>
              <th>Order ID</th>
              <th>Payment ID</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th>Timestamp</th>
              <th>Audit Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $pmt): ?>
              <tr>
                <td>
                  <strong style="color:#ffffff;"><?= sanitize($pmt['full_name'] ?? 'Unknown Player') ?></strong><br>
                  <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $pmt['player_id'] ?>" style="color:var(--adm-green-light);font-family:monospace;font-size:0.8rem;text-decoration:none;">
                    <?= sanitize($pmt['registration_id']) ?>
                  </a>
                </td>
                <td>
                  <code style="color:var(--adm-blue);font-size:0.8rem;"><?= sanitize($pmt['razorpay_order_id']) ?></code>
                </td>
                <td>
                  <?php if (!empty($pmt['razorpay_payment_id'])): ?>
                    <code style="color:var(--adm-green-light);font-size:0.8rem;"><?= sanitize($pmt['razorpay_payment_id']) ?></code>
                  <?php else: ?>
                    <span style="color:#64748b;">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color:#ffffff;"><?= formatMoney($pmt['amount']) ?></strong>
                </td>
                <td>
                  <span style="background:rgba(255,255,255,0.06);padding:2px 8px;border-radius:4px;font-size:0.72rem;letter-spacing:0.3px;">
                    <?= sanitize(strtoupper($pmt['payment_method'] ?? 'ONLINE')) ?>
                  </span>
                </td>
                <td>
                  <span class="adm-status-badge <?= $pmt['status'] === 'PAID' ? 'paid' : ($pmt['status'] === 'FAILED' ? 'failed' : 'pending') ?>">
                    <?= sanitize($pmt['status']) ?>
                  </span>
                </td>
                <td style="font-size:0.78rem;color:var(--adm-muted);white-space:nowrap;">
                  <?= date('d M Y, h:i A', strtotime($pmt['created_at'])) ?>
                </td>
                <td style="font-size:0.78rem;max-width:200px;">
                  <?php if (!empty($pmt['error_description'])): ?>
                    <span style="color:#f87171;"><?= sanitize($pmt['error_description']) ?></span>
                  <?php elseif ($pmt['status'] === 'PAID'): ?>
                    <span style="color:#34d399;">Captured via Razorpay</span>
                  <?php else: ?>
                    <span style="color:var(--adm-muted);">Awaiting payment</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="adm-pagination">
          <span style="color:var(--adm-muted);">
            Page <?= $page ?> of <?= $totalPages ?> (Total <?= number_format($totalRecords) ?>)
          </span>
          <div style="display:flex;gap:6px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php
                $q = $_GET;
                $q['page'] = $i;
                $u = BASE_URL . '/admin/payments.php?' . http_build_query($q);
              ?>
              <a href="<?= $u ?>" class="adm-page-link <?= $i === $page ? 'active' : '' ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
