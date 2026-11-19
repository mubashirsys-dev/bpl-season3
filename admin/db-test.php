<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Protected MilesWeb MariaDB Connection Diagnostics
 * STRICT SECURITY: Requires Admin Authentication or Secret Token.
 * NEVER exposes passwords or internal credentials.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Strict Access Protection: Requires authenticated admin session OR valid secret token
$secretToken = 'bpl_milesweb_2026';
$providedToken = $_GET['token'] ?? '';
$isAuthenticated = isAdminLoggedIn() || ($providedToken === $secretToken);

if (!$isAuthenticated) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>403 Forbidden | Database Diagnostics</title>
      <style>
        body { background:#070d1e; color:#f1f5f9; font-family:sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .box { background:#0e172e; padding:30px; border-radius:8px; border:1px solid #1e293b; max-width:420px; text-align:center; }
        h2 { color:#ef4444; margin-top:0; }
        a { color:#10b981; }
      </style>
    </head>
    <body>
      <div class="box">
        <h2>Access Denied (403)</h2>
        <p>Database diagnostics are protected for security. Please log in to the Admin Dashboard or provide the diagnostic token.</p>
        <p><a href="<?= BASE_URL ?>/admin/login.php">&larr; Return to Admin Login</a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Run Diagnostics
$diagnostics = [];
$isConnected = false;
$errorMessage = null;

try {
    $db = Database::getConnection();
    $isConnected = true;
    $method = Database::getConnectionMethod();

    // Database metadata
    $verStmt = $db->query("SELECT VERSION()");
    $dbVersion = $verStmt->fetchColumn();

    $nameStmt = $db->query("SELECT DATABASE()");
    $activeDb = $nameStmt->fetchColumn() ?: DB_NAME;

    $charsetStmt = $db->query("SELECT @@character_set_database, @@collation_database");
    $charsetRow = $charsetStmt->fetch(PDO::FETCH_NUM);
    $activeCharset = $charsetRow[0] ?? 'utf8mb4';
    $activeCollation = $charsetRow[1] ?? 'utf8mb4_unicode_ci';

    // Tables Check
    $tables = ['players', 'payments', 'settings', 'admins'];
    $tableCounts = [];
    foreach ($tables as $t) {
        try {
            $cntStmt = $db->query("SELECT COUNT(*) FROM `{$t}`");
            $tableCounts[$t] = (int)$cntStmt->fetchColumn();
        } catch (Exception $e) {
            $tableCounts[$t] = 'Table not found';
        }
    }
} catch (PDOException $e) {
    $isConnected = false;
    $errorMessage = "Could not establish database connection. Please verify credentials and server socket availability.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Connection Diagnostic | BPL Admin</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bpl-crest.svg">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=3.5.0">
  <style>
    .diag-card { max-width: 760px; margin: 40px auto; background: #0e172e; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    .diag-status-banner { padding: 14px 18px; border-radius: 6px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
    .diag-status-banner.success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .diag-status-banner.error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    .diag-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
    @media (max-width: 600px) { .diag-grid { grid-template-columns: 1fr; } }
    .diag-item { background: #081022; padding: 12px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); }
    .diag-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .diag-val { font-size: 0.95rem; font-weight: 700; color: #ffffff; font-family: monospace; }
    .diag-val.accent { color: #34d399; }
    .diag-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .diag-table th, .diag-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
    .diag-table th { color: #94a3b8; font-size: 0.72rem; text-transform: uppercase; background: #070d1e; }
  </style>
</head>
<body class="admin-body">

  <div class="diag-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h1 style="font-size:1.4rem; font-weight:800; color:#ffffff; margin:0;">
        MilesWeb MariaDB Diagnostic Test
      </h1>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="adm-btn adm-btn-outline" style="font-size:0.75rem;">
        &larr; Admin Dashboard
      </a>
    </div>

    <?php if ($isConnected): ?>
      <div class="diag-status-banner success">
        <span>&#10003; Database Connection Successful</span>
        <span style="font-size:0.8rem; font-weight:600; text-transform:uppercase;">Method: <?= htmlspecialchars($method ?? 'Active') ?></span>
      </div>

      <div class="diag-grid">
        <div class="diag-item">
          <div class="diag-label">Active Connection Target</div>
          <div class="diag-val accent"><?= htmlspecialchars($method === 'unix_socket' ? DB_SOCKET : DB_HOST . ':' . DB_PORT) ?></div>
        </div>

        <div class="diag-item">
          <div class="diag-label">Database Name</div>
          <div class="diag-val"><?= htmlspecialchars($activeDb) ?></div>
        </div>

        <div class="diag-item">
          <div class="diag-label">Database Username</div>
          <div class="diag-val"><?= htmlspecialchars(DB_USER) ?></div>
        </div>

        <div class="diag-item">
          <div class="diag-label">Password Status</div>
          <div class="diag-val" style="color:<?= !empty(DB_PASS) ? '#34d399' : '#fbbf24' ?>;">
            <?= !empty(DB_PASS) ? '[CONFIGURED - Hidden]' : '[NOT SET]' ?>
          </div>
        </div>

        <div class="diag-item">
          <div class="diag-label">Server Version</div>
          <div class="diag-val"><?= htmlspecialchars($dbVersion) ?></div>
        </div>

        <div class="diag-item">
          <div class="diag-label">Character Encoding &amp; Collation</div>
          <div class="diag-val"><?= htmlspecialchars($activeCharset . ' (' . $activeCollation . ')') ?></div>
        </div>
      </div>

      <h3 style="font-size:0.95rem; font-weight:700; color:#ffffff; margin-bottom:8px;">
        Table Verification &amp; Data Records
      </h3>
      <table class="diag-table">
        <thead>
          <tr>
            <th>Table Name</th>
            <th>Status</th>
            <th>Record Count</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tableCounts as $tbl => $cnt): ?>
            <tr>
              <td><code><?= htmlspecialchars($tbl) ?></code></td>
              <td>
                <span class="adm-status-badge <?= is_int($cnt) ? 'paid' : 'failed' ?>">
                  <?= is_int($cnt) ? 'VERIFIED' : 'MISSING' ?>
                </span>
              </td>
              <td><strong><?= is_int($cnt) ? number_format($cnt) : $cnt ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php else: ?>
      <div class="diag-status-banner error">
        <span>&#9888; Connection Failed</span>
      </div>
      <p style="color:#f87171; font-size:0.9rem;"><?= htmlspecialchars($errorMessage) ?></p>
      <div class="diag-item" style="margin-top:16px;">
        <div class="diag-label">Configured Target</div>
        <div class="diag-val">Unix Socket: <?= htmlspecialchars(DB_SOCKET) ?></div>
        <div class="diag-val" style="margin-top:4px;">TCP Host: <?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></div>
        <div class="diag-val" style="margin-top:4px;">Database: <?= htmlspecialchars(DB_NAME) ?></div>
        <div class="diag-val" style="margin-top:4px;">User: <?= htmlspecialchars(DB_USER) ?></div>
      </div>
      <p style="font-size:0.8rem; color:#94a3b8; margin-top:16px;">
        Tip: Please ensure that your MariaDB password is set in <code>.env</code> as <code>DB_PASS=...</code> or in <code>config/config.local.php</code>.
      </p>
    <?php endif; ?>

    <div style="margin-top:24px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.08); font-size:0.75rem; color:#64748b; text-align:center;">
      Note: This diagnostic page is protected. You can safely remove <code>admin/db-test.php</code> after confirming hosting connectivity.
    </div>
  </div>

</body>
</html>
