<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Unified High-Performance Admin Dashboard
 */

$pageTitle = "Dashboard Overview";
$activeNav = 'dashboard';
require_once __DIR__ . '/admin-header.php';

$db = getDB();

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($token)) {
        $deleteId = (int)($_POST['player_id'] ?? 0);
        $fetchStmt = $db->prepare("SELECT photo_path FROM players WHERE id = ?");
        $fetchStmt->execute([$deleteId]);
        $delPhoto = $fetchStmt->fetchColumn();

        $delStmt = $db->prepare("DELETE FROM players WHERE id = ?");
        if ($delStmt->execute([$deleteId])) {
            if (!empty($delPhoto) && file_exists(ROOT_PATH . '/' . $delPhoto)) {
                @unlink(ROOT_PATH . '/' . $delPhoto);
            }
            setFlash('success', 'Player registration deleted successfully.');
        } else {
            setFlash('danger', 'Failed to delete player registration.');
        }
    } else {
        setFlash('danger', 'Invalid security token.');
    }
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

// 1. Statistics
$totalPlayers = (int)$db->query("SELECT COUNT(*) FROM players")->fetchColumn();
$successfulPayments = (int)$db->query("SELECT COUNT(*) FROM players WHERE payment_status = 'PAID'")->fetchColumn();
$pendingPayments = (int)$db->query("SELECT COUNT(*) FROM players WHERE payment_status = 'PENDING'")->fetchColumn();
$totalCollection = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'PAID'")->fetchColumn();

// 2. Search & Filter Parameters
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR registration_id LIKE ? OR primary_contact LIKE ? OR club_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($filterStatus)) {
    $where[] = "payment_status = ?";
    $params[] = $filterStatus;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) FROM players {$whereSql}";
$cStmt = $db->prepare($countSql);
$cStmt->execute($params);
$totalRecords = (int)$cStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch Player List
$dataSql = "SELECT * FROM players {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
$dStmt = $db->prepare($dataSql);
$dStmt->execute($params);
$players = $dStmt->fetchAll();
?>

<!-- 1. Statistics Cards (High-Contrast, Equal Heights, Responsive Grid) -->
<div class="adm-stat-grid">
  <div class="adm-stat-card">
    <div class="adm-stat-title">Total Players</div>
    <div class="adm-stat-number"><?= number_format($totalPlayers) ?></div>
  </div>

  <div class="adm-stat-card success-border">
    <div class="adm-stat-title success-text">Successful Payments</div>
    <div class="adm-stat-number green"><?= number_format($successfulPayments) ?></div>
  </div>

  <div class="adm-stat-card warning-border">
    <div class="adm-stat-title warning-text">Pending Payments</div>
    <div class="adm-stat-number amber"><?= number_format($pendingPayments) ?></div>
  </div>

  <div class="adm-stat-card highlight">
    <div class="adm-stat-title success-text">Total Collection</div>
    <div class="adm-stat-number green"><?= formatMoney($totalCollection) ?></div>
  </div>
</div>

<!-- 2. Player Management Section -->
<div class="adm-card">
  <!-- Search & Filter Bar -->
  <div class="adm-card-header">
    <h2 class="adm-card-title">
      Player List (<?= number_format($totalRecords) ?>)
    </h2>

    <form method="GET" action="<?= BASE_URL ?>/admin/dashboard.php" class="adm-filter-form">
      <input 
        type="text" 
        name="search" 
        class="adm-search-input" 
        placeholder="Search Name, Reg ID, Phone, Club" 
        value="<?= sanitize($search) ?>"
      >

      <select name="status" class="adm-select">
        <option value="">All Statuses</option>
        <option value="PAID" <?= $filterStatus === 'PAID' ? 'selected' : '' ?>>PAID</option>
        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
        <option value="FAILED" <?= $filterStatus === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
      </select>

      <button type="submit" class="adm-btn adm-btn-primary">
        Filter
      </button>

      <?php if (!empty($search) || !empty($filterStatus)): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-btn adm-btn-outline">
          Reset
        </a>
      <?php endif; ?>

      <a href="<?= BASE_URL ?>/admin/export-pdf.php" class="adm-btn adm-btn-primary" style="background:#0284c7;color:#ffffff;">
        Download Master PDF
      </a>

      <a href="<?= BASE_URL ?>/admin/gallery.php" class="adm-btn adm-btn-outline">
        Photo Gallery
      </a>

      <a href="<?= BASE_URL ?>/admin/export.php?<?= http_build_query($_GET) ?>" class="adm-btn adm-btn-csv">
        Download CSV
      </a>
    </form>
  </div>

  <!-- Player List Table -->
  <?php if (empty($players)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--adm-muted);font-size:0.9rem;">
      No player registrations found.
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th style="width:48px;">Photo</th>
            <th>Reg ID</th>
            <th>Player Name</th>
            <th>Position</th>
            <th>Club</th>
            <th>Size</th>
            <th>Primary Contact</th>
            <th>Alt Contact</th>
            <th>Address</th>
            <th>Status</th>
            <th>Payment ID</th>
            <th>Date</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $p): ?>
            <tr>
              <td>
                <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $p['id'] ?>" title="View details for <?= sanitize($p['full_name']) ?>" style="display:inline-block;text-decoration:none;">
                  <?php if (!empty($p['photo_path']) && file_exists(ROOT_PATH . '/' . $p['photo_path'])): ?>
                    <img src="<?= BASE_URL . '/' . sanitize($p['photo_path']) ?>" alt="<?= sanitize($p['full_name']) ?>" style="width:38px;height:46px;object-fit:cover;border-radius:4px;border:1px solid #334155;display:block;cursor:pointer;transition:transform 0.15s ease, border-color 0.15s ease;" onmouseover="this.style.borderColor='var(--adm-green)';this.style.transform='scale(1.06)';" onmouseout="this.style.borderColor='#334155';this.style.transform='none';">
                  <?php else: ?>
                    <div style="width:38px;height:46px;background:#142240;border-radius:4px;border:1px solid #334155;display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:#64748b;cursor:pointer;transition:border-color 0.15s ease;" onmouseover="this.style.borderColor='var(--adm-green)';" onmouseout="this.style.borderColor='#334155';">No Img</div>
                  <?php endif; ?>
                </a>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $p['id'] ?>" title="View details for <?= sanitize($p['full_name']) ?>" style="text-decoration:none;">
                  <strong style="color:var(--adm-green);font-family:monospace;"><?= sanitize($p['registration_id']) ?></strong>
                </a>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $p['id'] ?>" title="View details for <?= sanitize($p['full_name']) ?>" style="color:#ffffff;text-decoration:none;font-weight:600;" onmouseover="this.style.color='var(--adm-green)';" onmouseout="this.style.color='#ffffff';">
                  <?= sanitize($p['full_name']) ?>
                </a>
              </td>
              <td>
                <span style="background:rgba(255,255,255,0.06);padding:2px 7px;border-radius:4px;font-size:0.75rem;">
                  <?= sanitize($p['playing_position']) ?>
                </span>
              </td>
              <td><?= sanitize($p['club_name']) ?></td>
              <td><strong><?= sanitize($p['jersey_size']) ?></strong></td>
              <td><?= formatPhone($p['primary_contact']) ?></td>
              <td style="color:var(--adm-muted);font-size:0.8rem;">
                <?= !empty($p['alternative_contact']) ? formatPhone($p['alternative_contact']) : '—' ?>
              </td>
              <td style="max-width:180px;font-size:0.78rem;color:#cbd5e1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitize($p['full_address']) ?>">
                <?= sanitize($p['full_address']) ?>
              </td>
              <td>
                <?php if ($p['payment_status'] === 'PAID'): ?>
                  <span class="adm-status-badge paid">PAID</span>
                <?php elseif ($p['payment_status'] === 'FAILED'): ?>
                  <span class="adm-status-badge failed">FAILED</span>
                <?php else: ?>
                  <span class="adm-status-badge pending">PENDING</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($p['razorpay_payment_id'])): ?>
                  <code style="color:#34d399;font-size:0.75rem;"><?= sanitize($p['razorpay_payment_id']) ?></code>
                <?php else: ?>
                  <span style="color:#64748b;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.78rem;color:var(--adm-muted);white-space:nowrap;">
                <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?>
              </td>
              <td style="text-align:right;">
                <div class="adm-action-group">
                  <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $p['id'] ?>" class="adm-action-btn view">
                    View
                  </a>
                  <a href="<?= BASE_URL ?>/admin/player-edit.php?id=<?= $p['id'] ?>" class="adm-action-btn edit">
                    Edit
                  </a>
                  <form method="POST" action="<?= BASE_URL ?>/admin/dashboard.php" style="display:inline-block;" onsubmit="return confirm('Delete registration <?= sanitize($p['registration_id']) ?>?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="adm-action-btn delete">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="adm-pagination">
        <span style="color:var(--adm-muted);">
          Page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> players)
        </span>
        <div style="display:flex;gap:4px;flex-wrap:wrap;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
              $q = $_GET;
              $q['page'] = $i;
              $u = BASE_URL . '/admin/dashboard.php?' . http_build_query($q);
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

<?php require_once __DIR__ . '/admin-footer.php'; ?>
