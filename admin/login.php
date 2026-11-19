<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Authentication Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$errorMsg = null;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $errorMsg = "Security token expired. Please refresh and try again.";
    } elseif (isLoginLocked($clientIp)) {
        $errorMsg = "Too many failed login attempts. Temporarily locked for 15 minutes.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $errorMsg = "Please enter both username and password.";
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // Successful Login
                    @session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_user'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['name'];

                    // Update last login
                    $update = $db->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $update->execute([$admin['id']]);

                    clearLoginAttempts($clientIp);

                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    exit;
                } else {
                    recordLoginAttempt($clientIp, $username);
                    $errorMsg = "Invalid username or password.";
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errorMsg = "A database error occurred. Please try again.";
            }
        }
    }
}

$tournamentName = getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE');
$tournamentSeason = getSetting('tournament_season', 'SEASON 3 — 2026–27');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Admin Login | <?= sanitize($tournamentName) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bpl-crest.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=3.5.0">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px;background:#0b1329;">

  <div style="width:100%;max-width:380px;margin:auto;">
    <!-- Small BPL Crest & Title -->
    <div style="text-align:center;margin-bottom:20px;">
      <img src="<?= BASE_URL ?>/assets/images/bpl-crest.svg" alt="BPL Crest" width="44" height="44" style="margin-bottom:8px;display:inline-block;">
      <h1 style="font-size:1.1rem;font-weight:800;color:#ffffff;letter-spacing:0.5px;text-transform:uppercase;margin:0;">
        <?= sanitize($tournamentName) ?>
      </h1>
      <p style="font-size:0.75rem;color:#10b981;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;margin:3px 0 0 0;">
        <?= sanitize($tournamentSeason) ?> &bull; ADMIN PORTAL
      </p>
    </div>

    <!-- White Login Box: 10px radius, clean 1px border -->
    <div style="background:#ffffff;border-radius:10px;padding:26px 22px;border:1px solid #e2e8f0;box-shadow:0 6px 20px rgba(0,0,0,0.25);">
      <h2 style="font-size:1.05rem;font-weight:800;color:#0f172a;margin:0 0 16px 0;text-transform:uppercase;text-align:center;letter-spacing:0.3px;">
        Sign In
      </h2>

      <?php if (!empty($errorMsg)): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:6px;font-size:0.82rem;margin-bottom:16px;font-weight:600;">
          <?= sanitize($errorMsg) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/admin/login.php" novalidate>
        <?= csrfField() ?>

        <div class="form-group" style="margin-bottom:14px;">
          <label for="username" class="form-label" style="font-size:0.78rem;font-weight:700;color:#334155;margin-bottom:5px;display:block;text-transform:uppercase;">Admin ID / Username</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus style="border-radius:6px;padding:9px 12px;font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:20px;">
          <label for="password" class="form-label" style="font-size:0.78rem;font-weight:700;color:#334155;margin-bottom:5px;display:block;text-transform:uppercase;">Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="border-radius:6px;padding:9px 12px;font-size:0.9rem;">
        </div>

        <button type="submit" class="btn-proceed-payment" style="width:100%;border-radius:6px;font-size:0.9rem;padding:12px;font-weight:800;letter-spacing:0.4px;">
          <span>LOGIN TO DASHBOARD</span>
          <span>&rarr;</span>
        </button>
      </form>
    </div>
  </div>

</body>
</html>
