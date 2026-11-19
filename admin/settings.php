<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Tournament & Razorpay Configuration
 */

$pageTitle = "Tournament & Gateway Settings";
$activeNav = 'settings';
require_once __DIR__ . '/admin-header.php';
require_once __DIR__ . '/../config/razorpay.php';

$db = getDB();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = "Security token expired. Please reload and try again.";
    } else {
        $tournamentName = trim($_POST['tournament_name'] ?? '');
        $tournamentSeason = trim($_POST['tournament_season'] ?? '');
        $organisedBy = trim($_POST['organised_by'] ?? '');
        $regFee = (float)($_POST['registration_fee'] ?? 100);
        $regOpen = isset($_POST['registration_open']) ? '1' : '0';
        $regDeadline = trim($_POST['registration_deadline'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $venue = trim($_POST['tournament_venue'] ?? '');

        // Razorpay settings
        $rzpMode = trim($_POST['razorpay_mode'] ?? 'test');
        $rzpKeyId = trim($_POST['razorpay_key_id'] ?? '');
        $rzpKeySecret = trim($_POST['razorpay_key_secret'] ?? '');
        $rzpWebhookSecret = trim($_POST['razorpay_webhook_secret'] ?? '');

        if ($regFee < 1) {
            $error = "Registration fee must be at least ₹1.00";
        } else {
            updateSetting('tournament_name', $tournamentName);
            updateSetting('tournament_season', $tournamentSeason);
            updateSetting('organised_by', $organisedBy);
            updateSetting('registration_fee', (string)$regFee);
            updateSetting('registration_open', $regOpen);
            updateSetting('registration_deadline', $regDeadline);
            updateSetting('contact_phone', $contactPhone);
            updateSetting('contact_email', $contactEmail);
            updateSetting('tournament_venue', $venue);

            // Razorpay updates
            updateSetting('razorpay_mode', $rzpMode);
            if (!empty($rzpKeyId)) {
                updateSetting('razorpay_key_id', $rzpKeyId);
            }
            if (!empty($rzpKeySecret) && strpos($rzpKeySecret, '•') === false) {
                updateSetting('razorpay_key_secret', $rzpKeySecret);
            }
            if (!empty($rzpWebhookSecret) && strpos($rzpWebhookSecret, '•') === false) {
                updateSetting('razorpay_webhook_secret', $rzpWebhookSecret);
            }

            setFlash('success', 'Tournament settings updated successfully.');
            header('Location: ' . BASE_URL . '/admin/settings.php');
            exit;
        }
    }
}

// Reload current settings
$currentName = getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE');
$currentSeason = getSetting('tournament_season', 'SEASON 3 (2026–27)');
$currentOrganiser = getSetting('organised_by', 'BUDDILANE FRIENDS GROUP');
$currentFee = getSetting('registration_fee', '100');
$currentOpen = (bool)(int)getSetting('registration_open', '1');
$currentDeadline = getSetting('registration_deadline', '2027-01-31');
$currentPhone = getSetting('contact_phone', '+91 98450 12345');
$currentEmail = getSetting('contact_email', 'contact@buddilanepremierleague.com');
$currentVenue = getSetting('tournament_venue', 'Buddilane Football Ground');

$rzpConfig = RazorpayService::getConfig();
$maskedSecret = !empty($rzpConfig['key_secret']) ? substr($rzpConfig['key_secret'], 0, 4) . '••••••••••••••••' : '';
$maskedWebhook = !empty($rzpConfig['webhook_secret']) ? substr($rzpConfig['webhook_secret'], 0, 4) . '••••••••••••••••' : '';
$webhookEndpointUrl = BASE_URL . '/api/razorpay-webhook.php';
?>

<div style="max-width:880px;">
  <div style="margin-bottom:20px;">
    <h1 style="font-size:1.45rem;font-weight:800;color:#ffffff;text-transform:uppercase;letter-spacing:0.4px;margin:0 0 4px 0;">
      Tournament & Gateway Settings
    </h1>
    <p style="color:var(--adm-muted);font-size:0.85rem;margin:0;">
      Manage tournament rules, registration window status and Razorpay credentials
    </p>
  </div>

  <?php if (!empty($error)): ?>
    <div style="background:#450a0a;border:1px solid #f87171;color:#fecaca;padding:12px 16px;border-radius:6px;font-size:0.85rem;margin-bottom:20px;">
      <?= sanitize($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
    <?= csrfField() ?>

    <!-- 1. Tournament Details Section -->
    <div class="adm-form-card">
      <h2 style="font-size:1.05rem;font-weight:800;color:#ffffff;text-transform:uppercase;margin:0 0 16px 0;border-bottom:1px solid var(--adm-border);padding-bottom:10px;letter-spacing:0.3px;">
        1. Tournament Information
      </h2>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Tournament Name</label>
          <input type="text" name="tournament_name" class="adm-input" value="<?= sanitize($currentName) ?>" required>
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label">Season / Year</label>
          <input type="text" name="tournament_season" class="adm-input" value="<?= sanitize($currentSeason) ?>" required>
        </div>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Organised By</label>
          <input type="text" name="organised_by" class="adm-input" value="<?= sanitize($currentOrganiser) ?>" required>
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label">Registration Fee (₹ INR)</label>
          <input type="number" name="registration_fee" class="adm-input" value="<?= (float)$currentFee ?>" step="1" min="1" required>
        </div>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Support Contact Phone</label>
          <input type="text" name="contact_phone" class="adm-input" value="<?= sanitize($currentPhone) ?>" required>
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label">Support Email</label>
          <input type="email" name="contact_email" class="adm-input" value="<?= sanitize($currentEmail) ?>">
        </div>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Tournament Venue</label>
          <input type="text" name="tournament_venue" class="adm-input" value="<?= sanitize($currentVenue) ?>">
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label">Registration Deadline Date</label>
          <input type="date" name="registration_deadline" class="adm-input" value="<?= sanitize($currentDeadline) ?>">
        </div>
      </div>

      <div style="margin-top:8px;background:#091226;padding:14px 18px;border-radius:6px;border:1px solid var(--adm-border);">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
          <input type="checkbox" name="registration_open" value="1" <?= $currentOpen ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--adm-green);">
          <strong style="color:#ffffff;font-size:0.9rem;">Player Registration Window is Open</strong>
        </label>
        <p style="margin:4px 0 0 28px;font-size:0.78rem;color:var(--adm-muted);">
          When unchecked, the public registration form is closed and players cannot submit new registrations.
        </p>
      </div>
    </div>

    <!-- 2. Razorpay API Gateway Configuration -->
    <div class="adm-form-card">
      <h2 style="font-size:1.05rem;font-weight:800;color:#ffffff;text-transform:uppercase;margin:0 0 16px 0;border-bottom:1px solid var(--adm-border);padding-bottom:10px;letter-spacing:0.3px;">
        2. Razorpay Payment Gateway Settings
      </h2>

      <div class="adm-form-group">
        <label class="adm-form-label">Gateway Environment Mode</label>
        <div style="display:flex;gap:20px;margin-top:6px;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="razorpay_mode" value="test" <?= $rzpConfig['mode'] === 'test' ? 'checked' : '' ?> style="accent-color:var(--adm-green);">
            <strong style="color:#fbbf24;font-size:0.85rem;">TEST Mode (Sandbox Testing)</strong>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="razorpay_mode" value="live" <?= $rzpConfig['mode'] === 'live' ? 'checked' : '' ?> style="accent-color:var(--adm-green);">
            <strong style="color:var(--adm-green-light);font-size:0.85rem;">LIVE Production Mode</strong>
          </label>
        </div>
      </div>

      <div class="adm-form-group">
        <label class="adm-form-label">Razorpay Key ID</label>
        <input type="text" name="razorpay_key_id" class="adm-input" value="<?= sanitize($rzpConfig['key_id']) ?>" placeholder="rzp_test_... or rzp_live_..." required>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Razorpay Key Secret</label>
          <input type="text" name="razorpay_key_secret" class="adm-input" placeholder="<?= !empty($maskedSecret) ? $maskedSecret : 'Enter new secret to update' ?>">
          <div style="font-size:0.75rem;color:var(--adm-muted);margin-top:4px;">Secret is masked for security. Leave blank to retain existing.</div>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label">Razorpay Webhook Secret</label>
          <input type="text" name="razorpay_webhook_secret" class="adm-input" placeholder="<?= !empty($maskedWebhook) ? $maskedWebhook : 'Enter new webhook secret' ?>">
          <div style="font-size:0.75rem;color:var(--adm-muted);margin-top:4px;">Configured in Razorpay Webhook settings.</div>
        </div>
      </div>

      <!-- Webhook Guide Box -->
      <div style="background:#091226;border:1px solid var(--adm-border);border-radius:6px;padding:14px 16px;margin-top:10px;">
        <div style="font-size:0.75rem;color:var(--adm-green);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
          Razorpay Webhook Configuration URL
        </div>
        <div style="background:#040914;padding:8px 12px;border-radius:4px;font-family:monospace;font-size:0.82rem;color:var(--adm-blue);border:1px solid var(--adm-border);word-break:break-all;">
          <?= $webhookEndpointUrl ?>
        </div>
        <p style="margin:8px 0 0 0;font-size:0.76rem;color:var(--adm-muted);line-height:1.4;">
          In Razorpay Dashboard &rarr; <strong>Settings &rarr; Webhooks &rarr; Add New Webhook</strong>, paste this URL and subscribe to:
          <code style="color:#a7f3d0;">order.paid</code>, <code style="color:#a7f3d0;">payment.captured</code>, and <code style="color:#a7f3d0;">payment.failed</code>.
        </p>
      </div>
    </div>

    <!-- Save Button -->
    <div>
      <button type="submit" class="adm-btn adm-btn-primary" style="padding:10px 24px;font-size:0.9rem;">
        Save All Settings
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
