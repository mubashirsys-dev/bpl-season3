<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Admin Player Information Editor
 */

$pageTitle = "Edit Player Registration";
$activeNav = 'dashboard';
require_once __DIR__ . '/admin-header.php';

$playerId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM players WHERE id = ?");
$stmt->execute([$playerId]);
$player = $stmt->fetch();

if (!$player) {
    setFlash('danger', 'Player not found.');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $errors[] = "Security token invalid or expired.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $clubName = trim($_POST['club_name'] ?? '');
        $position = trim($_POST['playing_position'] ?? '');
        $jerseySize = trim($_POST['jersey_size'] ?? '');
        $primaryContact = cleanPhone($_POST['primary_contact'] ?? '');
        $altContact = cleanPhone($_POST['alternative_contact'] ?? '');
        $fullAddress = trim($_POST['full_address'] ?? '');
        $regStatus = trim($_POST['registration_status'] ?? '');
        $pmtStatus = trim($_POST['payment_status'] ?? '');

        if (empty($fullName)) $errors[] = "Full Name is required.";
        if (empty($clubName)) $errors[] = "Club Name is required.";
        if (!isValidPhone($primaryContact)) $errors[] = "Valid 10-digit primary contact is required.";

        $photoPath = $player['photo_path'];
        // Optional photo replacement
        if (isset($_FILES['player_photo']) && $_FILES['player_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handlePhotoUpload($_FILES['player_photo']);
            if ($uploadResult['success']) {
                if (!empty($photoPath) && file_exists(ROOT_PATH . '/' . $photoPath)) {
                    @unlink(ROOT_PATH . '/' . $photoPath);
                }
                $photoPath = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            $upStmt = $db->prepare("
                UPDATE players 
                SET full_name = ?,
                    club_name = ?,
                    playing_position = ?,
                    jersey_size = ?,
                    primary_contact = ?,
                    alternative_contact = ?,
                    full_address = ?,
                    photo_path = ?,
                    registration_status = ?,
                    payment_status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            if ($upStmt->execute([
                $fullName, $clubName, $position, $jerseySize,
                $primaryContact, !empty($altContact) ? $altContact : null,
                $fullAddress, $photoPath, $regStatus, $pmtStatus,
                $player['id']
            ])) {
                setFlash('success', 'Player registration updated successfully.');
                header('Location: ' . BASE_URL . '/admin/player-view.php?id=' . $player['id']);
                exit;
            } else {
                $errors[] = "Failed to update record in database.";
            }
        }
    }
}
?>

<div style="max-width:780px;">
  <div style="margin-bottom:16px;">
    <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $player['id'] ?>" class="adm-btn adm-btn-outline">
      &larr; Back to Profile
    </a>
  </div>

  <div class="adm-form-card">
    <div style="margin-bottom:20px;border-bottom:1px solid var(--adm-border);padding-bottom:12px;">
      <h1 style="font-size:1.35rem;font-weight:800;color:#ffffff;margin:0 0 4px 0;text-transform:uppercase;">
        Edit Player: <?= sanitize($player['registration_id']) ?>
      </h1>
      <p style="color:var(--adm-muted);font-size:0.82rem;margin:0;">
        Modify tournament registration details and administrative status
      </p>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="background:#450a0a;border:1px solid #f87171;color:#fecaca;padding:12px 16px;border-radius:6px;font-size:0.85rem;margin-bottom:20px;">
        <?= implode('<br>', array_map('sanitize', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Player Full Name</label>
          <input type="text" name="full_name" class="adm-input" value="<?= sanitize($player['full_name']) ?>" required>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label">Club Name</label>
          <input type="text" name="club_name" class="adm-input" value="<?= sanitize($player['club_name']) ?>" required>
        </div>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Playing Position</label>
          <select name="playing_position" class="adm-select" style="width:100%;">
            <option value="Goalkeeper" <?= $player['playing_position'] === 'Goalkeeper' ? 'selected' : '' ?>>Goalkeeper</option>
            <option value="Defence" <?= $player['playing_position'] === 'Defence' ? 'selected' : '' ?>>Defence</option>
            <option value="Midfielder" <?= $player['playing_position'] === 'Midfielder' ? 'selected' : '' ?>>Midfielder</option>
            <option value="Striker" <?= $player['playing_position'] === 'Striker' ? 'selected' : '' ?>>Striker</option>
          </select>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label">Jersey Size</label>
          <select name="jersey_size" class="adm-select" style="width:100%;">
            <option value="S" <?= $player['jersey_size'] === 'S' ? 'selected' : '' ?>>S</option>
            <option value="M" <?= $player['jersey_size'] === 'M' ? 'selected' : '' ?>>M</option>
            <option value="L" <?= $player['jersey_size'] === 'L' ? 'selected' : '' ?>>L</option>
            <option value="XL" <?= $player['jersey_size'] === 'XL' ? 'selected' : '' ?>>XL</option>
            <option value="XXL" <?= $player['jersey_size'] === 'XXL' ? 'selected' : '' ?>>XXL</option>
          </select>
        </div>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Primary Contact</label>
          <input type="tel" name="primary_contact" class="adm-input" value="<?= sanitize($player['primary_contact']) ?>" required>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label">Alternative Contact</label>
          <input type="tel" name="alternative_contact" class="adm-input" value="<?= sanitize($player['alternative_contact'] ?? '') ?>">
        </div>
      </div>

      <div class="adm-form-group">
        <label class="adm-form-label">Full Residential Address</label>
        <textarea name="full_address" class="adm-input" rows="3" required style="resize:vertical;"><?= sanitize($player['full_address']) ?></textarea>
      </div>

      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label">Registration Status</label>
          <select name="registration_status" class="adm-select" style="width:100%;">
            <option value="PENDING_PAYMENT" <?= $player['registration_status'] === 'PENDING_PAYMENT' ? 'selected' : '' ?>>PENDING_PAYMENT</option>
            <option value="CONFIRMED" <?= $player['registration_status'] === 'CONFIRMED' ? 'selected' : '' ?>>CONFIRMED</option>
            <option value="CANCELLED" <?= $player['registration_status'] === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
          </select>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label">Payment Status</label>
          <select name="payment_status" class="adm-select" style="width:100%;">
            <option value="PENDING" <?= $player['payment_status'] === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
            <option value="PAID" <?= $player['payment_status'] === 'PAID' ? 'selected' : '' ?>>PAID</option>
            <option value="FAILED" <?= $player['payment_status'] === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
            <option value="REFUNDED" <?= $player['payment_status'] === 'REFUNDED' ? 'selected' : '' ?>>REFUNDED</option>
          </select>
        </div>
      </div>

      <div class="adm-form-group">
        <label class="adm-form-label">Replace Photograph (Optional)</label>
        <input type="file" name="player_photo" accept="image/jpeg,image/png,image/webp" class="adm-input">
      </div>

      <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="adm-btn adm-btn-primary" style="padding:10px 24px;">
          Save Changes
        </button>
        <a href="<?= BASE_URL ?>/admin/player-view.php?id=<?= $player['id'] ?>" class="adm-btn adm-btn-outline" style="padding:10px 20px;">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
