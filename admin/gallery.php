<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * All Players Visual Gallery
 * Organised by: BUDDILANE FRIENDS GROUP
 */

$pageTitle = "All Players Gallery";
$activeNav = 'gallery';
require_once __DIR__ . '/admin-header.php';

$db = getDB();

// Search & Filter Parameters
$search       = trim($_GET['search'] ?? '');
$filterPos    = trim($_GET['position'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR registration_id LIKE ? OR club_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($filterPos)) {
    $where[] = "playing_position = ?";
    $params[] = $filterPos;
}

if (!empty($filterStatus)) {
    $where[] = "payment_status = ?";
    $params[] = $filterStatus;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch all registered players matching filters
$stmt = $db->prepare("SELECT * FROM players {$whereSql} ORDER BY id DESC");
$stmt->execute($params);
$players = $stmt->fetchAll();

$totalCount = count($players);

// Position badge colors
$positionClasses = [
    'Goalkeeper' => 'background:#0369a1;color:#bae6fd;border-color:rgba(56,189,248,0.3);',
    'Defence'    => 'background:#1e3a8a;color:#bfdbfe;border-color:rgba(96,165,250,0.3);',
    'Midfielder' => 'background:#14532d;color:#bbf7d0;border-color:rgba(74,222,128,0.3);',
    'Striker'    => 'background:#701a75;color:#f5d0fe;border-color:rgba(232,121,249,0.3);',
];
?>

<div>
  <!-- Header Bar -->
  <div class="adm-gallery-header">
    <div>
      <h1 style="font-size:1.45rem;font-weight:800;color:#ffffff;text-transform:uppercase;margin:0 0 4px 0;letter-spacing:0.4px;">
        All Players Gallery
      </h1>
      <p style="color:var(--adm-muted);font-size:0.85rem;margin:0;">
        Visual tournament roster directory &bull; 
        <strong style="color:var(--adm-green-light);"><?= number_format($totalCount) ?> Registered <?= $totalCount === 1 ? 'Player' : 'Players' ?></strong>
      </p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/admin/export-pdf.php" class="adm-btn adm-btn-primary" style="background:var(--adm-green);color:#ffffff;">
        Download Master Report PDF
      </a>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-btn adm-btn-outline">
        Table View
      </a>
    </div>
  </div>

  <!-- Search & Filter Controls -->
  <div class="adm-card" style="padding:14px 18px;margin-bottom:24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/gallery.php" class="adm-filter-form">
      <input 
        type="text" 
        name="search" 
        class="adm-search-input" 
        placeholder="Search Player Name, Reg ID, Club" 
        value="<?= sanitize($search) ?>"
      >

      <select name="position" class="adm-select">
        <option value="">All Positions</option>
        <option value="Goalkeeper" <?= $filterPos === 'Goalkeeper' ? 'selected' : '' ?>>Goalkeeper</option>
        <option value="Defence" <?= $filterPos === 'Defence' ? 'selected' : '' ?>>Defence</option>
        <option value="Midfielder" <?= $filterPos === 'Midfielder' ? 'selected' : '' ?>>Midfielder</option>
        <option value="Striker" <?= $filterPos === 'Striker' ? 'selected' : '' ?>>Striker</option>
      </select>

      <select name="status" class="adm-select">
        <option value="">All Statuses</option>
        <option value="PAID" <?= $filterStatus === 'PAID' ? 'selected' : '' ?>>PAID</option>
        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
        <option value="FAILED" <?= $filterStatus === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
      </select>

      <button type="submit" class="adm-btn adm-btn-primary">
        Filter
      </button>

      <?php if (!empty($search) || !empty($filterPos) || !empty($filterStatus)): ?>
        <a href="<?= BASE_URL ?>/admin/gallery.php" class="adm-btn adm-btn-outline">
          Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Empty State -->
  <?php if (empty($players)): ?>
    <div class="adm-card" style="padding:50px 20px;text-align:center;">
      <div style="font-size:1.1rem;font-weight:700;color:#ffffff;margin-bottom:6px;text-transform:uppercase;">
        No Players Found
      </div>
      <p style="color:var(--adm-muted);font-size:0.85rem;margin:0 0 16px 0;">
        <?= !empty($search) ? 'No registered players match your current filter criteria.' : 'No players have registered for Season 3 yet.' ?>
      </p>
      <?php if (!empty($search) || !empty($filterPos) || !empty($filterStatus)): ?>
        <a href="<?= BASE_URL ?>/admin/gallery.php" class="adm-btn adm-btn-outline">
          Clear All Filters
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <!-- Image-Card Gallery Grid -->
    <div class="adm-gallery-grid">
      <?php foreach ($players as $p): ?>
        <?php
          $hasPhoto = (!empty($p['photo_path']) && file_exists(ROOT_PATH . '/' . $p['photo_path']));
          $photoUrl = $hasPhoto ? BASE_URL . '/' . sanitize($p['photo_path']) : '';
          $posStyle = $positionClasses[$p['playing_position']] ?? 'background:#1e293b;color:#cbd5e1;';
        ?>
        <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $p['id'] ?>" class="adm-gallery-card" title="Click to view details for <?= sanitize($p['full_name']) ?>">
          
          <!-- Card Background Image or Placeholder -->
          <?php if ($hasPhoto): ?>
            <img 
              src="<?= $photoUrl ?>" 
              alt="<?= sanitize($p['full_name']) ?>" 
              class="adm-gallery-card-bg"
              loading="lazy"
            >
          <?php else: ?>
            <div class="adm-gallery-card-placeholder">
              <div style="font-size:2.2rem;font-weight:800;color:#334155;letter-spacing:1px;text-transform:uppercase;">
                <?= substr(sanitize($p['full_name']), 0, 2) ?>
              </div>
              <div style="font-size:0.75rem;color:#64748b;margin-top:6px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
                No Photo
              </div>
            </div>
          <?php endif; ?>

          <!-- Dark Gradient Overlay for Readability -->
          <div class="adm-gallery-card-overlay"></div>

          <!-- Top Tags: Position and Payment Status -->
          <div class="adm-gallery-card-top">
            <span style="font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;padding:3px 8px;border-radius:4px;border:1px solid;<?= $posStyle ?>">
              <?= sanitize($p['playing_position']) ?>
            </span>

            <span class="adm-status-badge <?= $p['payment_status'] === 'PAID' ? 'paid' : ($p['payment_status'] === 'FAILED' ? 'failed' : 'pending') ?>">
              <?= sanitize($p['payment_status']) ?>
            </span>
          </div>

          <!-- Bottom Overlay: Name, Reg ID, Club -->
          <div class="adm-gallery-card-bottom">
            <div class="adm-gallery-card-regid">
              <?= sanitize($p['registration_id']) ?>
            </div>
            
            <div class="adm-gallery-card-name">
              <?= sanitize($p['full_name']) ?>
            </div>

            <div class="adm-gallery-card-meta">
              <span style="color:#e2e8f0;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;">
                <?= sanitize($p['club_name']) ?>
              </span>
              <span style="color:#94a3b8;font-weight:700;">
                Size <?= sanitize($p['jersey_size']) ?>
              </span>
            </div>
          </div>

        </a>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>

