<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Minimal Single-Page Player Registration Website
 */

$pageTitle = "Player Registration | Buddilane Premier League Season 3";
require_once __DIR__ . '/includes/header.php';

$regOpen = (bool)(int)getSetting('registration_open', '1');
?>

<main class="bpl-page-wrapper">
  <div class="bpl-card">
    
    <!-- Form Card Title & Fee Badge -->
    <div class="bpl-card-header">
      <h2 class="bpl-form-title">PLAYER REGISTRATION FORM</h2>
      <div class="bpl-fee-badge">Registration Fee: ₹<?= (int)$regFee ?></div>
    </div>

    <!-- Live Alert Box -->
    <div id="form_alert_box" class="alert-bpl alert-danger" style="display:none;"></div>

    <?php if (!$regOpen): ?>
      <div style="text-align:center;padding:24px 10px;">
        <h3 style="color:#0f172a;font-size:1.15rem;margin-bottom:6px;text-transform:uppercase;">REGISTRATIONS CLOSED</h3>
        <p style="color:#64748b;font-size:0.88rem;margin:0;">
          Player registration is currently closed by the tournament committee.
        </p>
      </div>
    <?php else: ?>

      <!-- Single Registration Form -->
      <form id="bpl_registration_form" method="POST" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <!-- 1. Player Full Name -->
        <div class="form-group">
          <label for="full_name" class="form-label">
            Player Full Name <span class="req">*</span>
          </label>
          <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter player's full name" required maxlength="150" autocomplete="name">
        </div>

        <!-- 2. Player Photograph Upload with Preview -->
        <div class="form-group">
          <label class="form-label">
            Player Photograph <span class="req">*</span>
          </label>
          
          <div id="photo_dropzone" class="photo-uploader">
            <input type="file" id="player_photo" name="player_photo" accept="image/jpeg,image/png,image/webp" required>
            
            <div id="upload_placeholder">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon-svg">
                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
                <circle cx="12" cy="13" r="3"/>
              </svg>
              <div class="upload-text">Upload Player Photograph</div>
              <div class="upload-hint">JPG, PNG, WebP · Maximum 5 MB</div>
            </div>

            <div id="upload_preview_wrap" class="photo-preview-wrap" style="display:none;">
              <img id="photo_preview_img" class="preview-thumb" src="" alt="Preview">
              <div class="preview-info">
                <div id="preview_filename" class="preview-filename">photo.jpg</div>
                <div id="preview_filesize" class="preview-filesize">0.00 MB</div>
                <button type="button" id="btn_change_photo" class="btn-change-photo">Change Photo</button>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Playing Position -->
        <div class="form-group">
          <label class="form-label">Playing Position <span class="req">*</span></label>
          <div class="position-grid">
            <label class="position-card">
              <input type="radio" name="playing_position" value="Goalkeeper" required>
              <div class="position-box">
                <span class="pos-name">Goalkeeper</span>
              </div>
            </label>

            <label class="position-card">
              <input type="radio" name="playing_position" value="Defence">
              <div class="position-box">
                <span class="pos-name">Defence</span>
              </div>
            </label>

            <label class="position-card">
              <input type="radio" name="playing_position" value="Midfielder">
              <div class="position-box">
                <span class="pos-name">Midfielder</span>
              </div>
            </label>

            <label class="position-card">
              <input type="radio" name="playing_position" value="Striker">
              <div class="position-box">
                <span class="pos-name">Striker</span>
              </div>
            </label>
          </div>
        </div>

        <!-- 4. Club Name -->
        <div class="form-group">
          <label for="club_name" class="form-label">
            Club Name <span class="req">*</span>
          </label>
          <input type="text" id="club_name" name="club_name" class="form-control" placeholder="Enter club name" required maxlength="100">
        </div>

        <!-- 5. Jersey Size -->
        <div class="form-group">
          <label class="form-label">Jersey Size <span class="req">*</span></label>
          <div class="size-grid">
            <label class="size-pill">
              <input type="radio" name="jersey_size" value="S" required>
              <div class="size-box">S</div>
            </label>
            <label class="size-pill">
              <input type="radio" name="jersey_size" value="L">
              <div class="size-box">L</div>
            </label>
            <label class="size-pill">
              <input type="radio" name="jersey_size" value="XL">
              <div class="size-box">XL</div>
            </label>
            <label class="size-pill">
              <input type="radio" name="jersey_size" value="XXL">
              <div class="size-box">XXL</div>
            </label>
          </div>
        </div>

        <!-- 6. Primary Contact Number -->
        <div class="form-group">
          <label for="primary_contact" class="form-label">
            Primary Contact Number <span class="req">*</span>
          </label>
          <input type="tel" id="primary_contact" name="primary_contact" class="form-control" placeholder="10-digit mobile number" required maxlength="10" inputmode="numeric">
        </div>

        <!-- 7. Alternative Contact Number (Optional) -->
        <div class="form-group">
          <label for="alternative_contact" class="form-label">
            Alternative Contact Number <span class="opt">(Optional)</span>
          </label>
          <input type="tel" id="alternative_contact" name="alternative_contact" class="form-control" placeholder="Optional contact number" maxlength="15" inputmode="numeric">
        </div>

        <!-- 8. Address -->
        <div class="form-group">
          <label for="full_address" class="form-label">
            Address <span class="req">*</span>
          </label>
          <textarea id="full_address" name="full_address" class="form-control" placeholder="Enter residential address" required rows="2"></textarea>
        </div>

        <!-- Submit & Pay Button -->
        <button type="submit" id="btn_submit_registration" class="btn-proceed-payment">
          <span id="submit_btn_spinner" class="spinner-inline" style="display:none;"></span>
          <span id="submit_btn_text">SUBMIT AND PAY ₹<?= (int)$regFee ?></span>
        </button>
      </form>

    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
