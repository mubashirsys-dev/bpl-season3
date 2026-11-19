/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Minimal Frontend Script & Razorpay Checkout Integration
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Photo Upload Live Preview
  const photoInput = document.getElementById('player_photo');
  const photoDropzone = document.getElementById('photo_dropzone');
  const uploadPlaceholder = document.getElementById('upload_placeholder');
  const uploadPreviewWrap = document.getElementById('upload_preview_wrap');
  const previewImg = document.getElementById('photo_preview_img');
  const previewFilename = document.getElementById('preview_filename');
  const previewFilesize = document.getElementById('preview_filesize');
  const btnChangePhoto = document.getElementById('btn_change_photo');

  if (photoInput && photoDropzone) {
    function handleSelectedFile(file) {
      if (!file) return;

      // Validate 5MB max
      const maxSize = 5 * 1024 * 1024;
      if (file.size > maxSize) {
        alert('File exceeds 5MB limit. Please choose a smaller photo.');
        photoInput.value = '';
        return;
      }

      // Validate type
      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type.toLowerCase())) {
        alert('Please upload a JPG, JPEG, PNG, or WebP photo.');
        photoInput.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        if (previewImg) previewImg.src = e.target.result;
        if (previewFilename) previewFilename.textContent = file.name;
        if (previewFilesize) previewFilesize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (uploadPreviewWrap) uploadPreviewWrap.style.display = 'flex';
      };
      reader.readAsDataURL(file);
    }

    photoInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        handleSelectedFile(this.files[0]);
      }
    });

    if (btnChangePhoto) {
      btnChangePhoto.addEventListener('click', function (e) {
        e.preventDefault();
        photoInput.click();
      });
    }
  }

  // 2. Form Submission & Razorpay Modal
  const regForm = document.getElementById('bpl_registration_form');
  const submitBtn = document.getElementById('btn_submit_registration');
  const submitBtnText = document.getElementById('submit_btn_text');
  const submitBtnSpinner = document.getElementById('submit_btn_spinner');
  const formAlert = document.getElementById('form_alert_box');

  const defaultFee = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.registrationFee) ? APP_CONFIG.registrationFee : 100;

  function showAlert(msg) {
    if (!formAlert) return;
    formAlert.innerHTML = `<div>${msg}</div>`;
    formAlert.style.display = 'block';
    formAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function hideAlert() {
    if (!formAlert) return;
    formAlert.style.display = 'none';
    formAlert.innerHTML = '';
  }

  function setSubmitting(isSubmitting) {
    if (!submitBtn) return;
    submitBtn.disabled = isSubmitting;
    if (submitBtnSpinner) {
      submitBtnSpinner.style.display = isSubmitting ? 'inline-block' : 'none';
    }
    if (submitBtnText) {
      submitBtnText.textContent = isSubmitting ? 'PROCESSING...' : `SUBMIT AND PAY ₹${defaultFee}`;
    }
  }

  if (regForm) {
    regForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      hideAlert();

      const fullName = document.getElementById('full_name')?.value.trim();
      const clubName = document.getElementById('club_name')?.value.trim();
      const primaryContact = document.getElementById('primary_contact')?.value.trim();
      const address = document.getElementById('full_address')?.value.trim();
      const position = document.querySelector('input[name="playing_position"]:checked')?.value;
      const jerseySize = document.querySelector('input[name="jersey_size"]:checked')?.value;
      const photo = photoInput?.files?.[0];

      if (!fullName) {
        showAlert('Please enter Player Full Name.');
        return;
      }
      if (!photo) {
        showAlert('Please upload a Player Photograph.');
        return;
      }
      if (!position) {
        showAlert('Please select Playing Position.');
        return;
      }
      if (!clubName) {
        showAlert('Please enter Club Name.');
        return;
      }
      if (!jerseySize) {
        showAlert('Please select Jersey Size.');
        return;
      }
      if (!primaryContact || !/^[6-9]\d{9}$/.test(primaryContact.replace(/\D/g, ''))) {
        showAlert('Please enter a valid 10-digit primary mobile number.');
        return;
      }
      if (!address) {
        showAlert('Please enter residential address.');
        return;
      }

      setSubmitting(true);

      const formData = new FormData(regForm);

      try {
        const createOrderUrl = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/api/create-order.php';
        const response = await fetch(createOrderUrl, {
          method: 'POST',
          body: formData
        });

        const resData = await response.json();

        if (!resData.success) {
          setSubmitting(false);
          showAlert(resData.error || 'Failed to initialize payment.');
          return;
        }

        const { order_id, key_id, amount, registration_id, player_name, contact, tournament_name } = resData;

        if (typeof Razorpay === 'undefined') {
          setSubmitting(false);
          showAlert('Razorpay Checkout SDK is loading. Please check your internet connection.');
          return;
        }

        const options = {
          key: key_id,
          amount: amount,
          currency: 'INR',
          name: tournament_name || 'BUDDILANE PREMIER LEAGUE',
          description: `Registration Fee (${registration_id})`,
          order_id: order_id,
          prefill: {
            name: player_name,
            contact: contact
          },
          theme: {
            color: '#10b981'
          },
          handler: async function (paymentResponse) {
            setSubmitting(true);
            if (submitBtnText) submitBtnText.textContent = 'VERIFYING PAYMENT...';

            try {
              const verifyUrl = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/api/verify-payment.php';
              const verifyResp = await fetch(verifyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  registration_id: registration_id,
                  razorpay_order_id: paymentResponse.razorpay_order_id,
                  razorpay_payment_id: paymentResponse.razorpay_payment_id,
                  razorpay_signature: paymentResponse.razorpay_signature
                })
              });

              const verifyResult = await verifyResp.json();

              if (verifyResult.success) {
                window.location.href = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/success.php?reg_id=' + encodeURIComponent(registration_id);
              } else {
                setSubmitting(false);
                showAlert(verifyResult.error || 'Server verification failed. Please contact organisers.');
              }
            } catch (err) {
              setSubmitting(false);
              showAlert('Verification network error. Redirecting to status check...');
              window.location.href = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/payment-status.php?reg_id=' + encodeURIComponent(registration_id);
            }
          },
          modal: {
            ondismiss: function () {
              setSubmitting(false);
              showAlert(`Payment closed. Registration saved as PENDING. <a href="${APP_CONFIG.baseUrl}/payment-status.php?reg_id=${encodeURIComponent(registration_id)}" style="color:#0f172a;text-decoration:underline;font-weight:700;">Click to Retry</a>.`);
            }
          }
        };

        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function (failResponse) {
          setSubmitting(false);
          const reason = failResponse.error?.description || 'Payment was declined or failed.';
          showAlert(`Payment Failed: ${reason}. <a href="${APP_CONFIG.baseUrl}/payment-status.php?reg_id=${encodeURIComponent(registration_id)}" style="color:#0f172a;text-decoration:underline;font-weight:700;">Click to Retry</a>.`);
        });

        rzp.open();

      } catch (err) {
        setSubmitting(false);
        showAlert('A network error occurred. Please try again.');
        console.error(err);
      }
    });
  }

  // 3. Retry Button on payment-status.php
  const retryBtn = document.getElementById('btn_retry_payment');
  if (retryBtn) {
    retryBtn.addEventListener('click', async function () {
      const regId = retryBtn.getAttribute('data-reg-id');
      if (!regId) return;

      retryBtn.disabled = true;
      retryBtn.textContent = 'CONNECTING...';

      try {
        const retryUrl = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/api/create-order.php';
        const resp = await fetch(retryUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ retry_registration_id: regId })
        });

        const resData = await resp.json();
        if (!resData.success) {
          alert(resData.error || 'Could not initiate retry.');
          retryBtn.disabled = false;
          retryBtn.textContent = 'RETRY PAYMENT NOW';
          return;
        }

        const options = {
          key: resData.key_id,
          amount: resData.amount,
          currency: 'INR',
          name: resData.tournament_name || 'BUDDILANE PREMIER LEAGUE',
          description: `Registration Fee (${resData.registration_id})`,
          order_id: resData.order_id,
          prefill: {
            name: resData.player_name,
            contact: resData.contact
          },
          theme: { color: '#10b981' },
          handler: async function (paymentResponse) {
            retryBtn.textContent = 'VERIFYING...';
            const verifyUrl = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/api/verify-payment.php';
            const verifyResp = await fetch(verifyUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                registration_id: resData.registration_id,
                razorpay_order_id: paymentResponse.razorpay_order_id,
                razorpay_payment_id: paymentResponse.razorpay_payment_id,
                razorpay_signature: paymentResponse.razorpay_signature
              })
            });
            const vData = await verifyResp.json();
            if (vData.success) {
              window.location.href = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.baseUrl ? APP_CONFIG.baseUrl : '') + '/success.php?reg_id=' + encodeURIComponent(resData.registration_id);
            } else {
              alert(vData.error || 'Verification failed.');
              location.reload();
            }
          },
          modal: {
            ondismiss: function () {
              retryBtn.disabled = false;
              retryBtn.textContent = 'RETRY PAYMENT NOW';
            }
          }
        };

        const rzp = new Razorpay(options);
        rzp.open();
      } catch (err) {
        alert('Network error while retrying payment.');
        retryBtn.disabled = false;
        retryBtn.textContent = 'RETRY PAYMENT NOW';
      }
    });
  }
});
