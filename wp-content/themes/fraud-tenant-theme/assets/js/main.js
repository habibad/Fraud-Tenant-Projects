/**
 * Fraud Tenant Theme — Main JavaScript
 * Lightweight, accessible interactions
 */

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  // 1. Mobile Menu Toggle
  const menuToggle = document.querySelector('.mobile-menu-toggle');
  const mobileDrawer = document.querySelector('.mobile-drawer');

  if (menuToggle && mobileDrawer) {
    menuToggle.addEventListener('click', function () {
      const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', !isExpanded);
      mobileDrawer.classList.toggle('active');
      document.body.classList.toggle('mobile-menu-open');
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && mobileDrawer.classList.contains('active')) {
        menuToggle.setAttribute('aria-expanded', 'false');
        mobileDrawer.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
        menuToggle.focus();
      }
    });

    // Close when clicking a mobile link
    const mobileLinks = mobileDrawer.querySelectorAll('a');
    mobileLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        menuToggle.setAttribute('aria-expanded', 'false');
        mobileDrawer.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
      });
    });
  }

  // 2. Accessible FAQ Accordions
  const faqQuestions = document.querySelectorAll('.faq-question');
  faqQuestions.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const parentItem = btn.closest('.faq-item');
      const isExpanded = btn.getAttribute('aria-expanded') === 'true';

      // Toggle current
      btn.setAttribute('aria-expanded', !isExpanded);
      if (parentItem) {
        parentItem.classList.toggle('active');
      }
    });
  });

  // 3. Contact Form Client-Side Helper
  const contactForm = document.querySelector('.ft-contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      const name = contactForm.querySelector('input[name="enquiry_name"]');
      const email = contactForm.querySelector('input[name="enquiry_email"]');
      const message = contactForm.querySelector('textarea[name="enquiry_message"]');

      let hasError = false;

      if (name && name.value.trim().length < 2) {
        hasError = true;
        name.focus();
      } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        hasError = true;
        email.focus();
      } else if (message && message.value.trim().length < 10) {
        hasError = true;
        message.focus();
      }

      if (hasError) {
        e.preventDefault();
        const alertBox = contactForm.querySelector('.form-alert-client');
        if (alertBox) {
          alertBox.style.display = 'block';
          alertBox.textContent = 'Please fill in all required fields with valid information.';
        }
      }
    });
  }

  // 4. Password Visibility Toggle
  const pwToggles = document.querySelectorAll('.btn-toggle-pw');
  pwToggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const eyeIcon = btn.querySelector('.icon-eye');
      const eyeOffIcon = btn.querySelector('.icon-eye-off');

      if (input.type === 'password') {
        input.type = 'text';
        if (eyeIcon) eyeIcon.style.display = 'none';
        if (eyeOffIcon) eyeOffIcon.style.display = 'block';
        btn.setAttribute('aria-label', 'Hide password');
      } else {
        input.type = 'password';
        if (eyeIcon) eyeIcon.style.display = 'block';
        if (eyeOffIcon) eyeOffIcon.style.display = 'none';
        btn.setAttribute('aria-label', 'Show password');
      }
    });
  });

  // 5. Distinct Role Registration Tabs & Interactions (Registration Page)
  const roleTabButtons = document.querySelectorAll('.role-tab-btn');
  const ownerContainer = document.getElementById('form-container-owner');
  const tenantContainer = document.getElementById('form-container-tenant');

  if (roleTabButtons.length > 0 && ownerContainer && tenantContainer) {
    const switchRegistrationRole = function (role) {
      roleTabButtons.forEach(function (btn) {
        const isTarget = btn.getAttribute('data-role') === role;
        btn.classList.toggle('is-active', isTarget);
        btn.setAttribute('aria-selected', isTarget ? 'true' : 'false');
      });

      if (role === 'owner') {
        ownerContainer.classList.remove('is-hidden');
        ownerContainer.classList.add('is-visible');
        tenantContainer.classList.remove('is-visible');
        tenantContainer.classList.add('is-hidden');
      } else {
        tenantContainer.classList.remove('is-hidden');
        tenantContainer.classList.add('is-visible');
        ownerContainer.classList.remove('is-visible');
        ownerContainer.classList.add('is-hidden');
      }

      // Update URL query string without reloading page
      if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('role', role);
        window.history.replaceState({}, '', url);
      }
    };

    roleTabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const role = btn.getAttribute('data-role');
        switchRegistrationRole(role);
      });
    });
  }

  // 5b. Tenant Previous Rental Address Conditional Toggle
  const prevAddressToggle = document.getElementById('toggle-previous-address');
  const prevAddressFields = document.getElementById('prev-address-fields');
  if (prevAddressToggle && prevAddressFields) {
    prevAddressToggle.addEventListener('change', function () {
      if (prevAddressToggle.checked) {
        prevAddressFields.classList.remove('is-hidden');
        prevAddressFields.classList.add('is-visible');
        const firstPrevInput = prevAddressFields.querySelector('input');
        if (firstPrevInput) firstPrevInput.focus();
      } else {
        prevAddressFields.classList.remove('is-visible');
        prevAddressFields.classList.add('is-hidden');
      }
    });
  }

  // 5c. Ownership Document Upload Visual Feedback
  const ownerDocInput = document.getElementById('owner-document');
  if (ownerDocInput) {
    ownerDocInput.addEventListener('change', function () {
      const uploadText = document.querySelector('.upload-primary-text');
      const uploadHint = document.querySelector('.upload-hint-text');
      if (ownerDocInput.files && ownerDocInput.files.length > 0) {
        const file = ownerDocInput.files[0];
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        if (uploadText) {
          uploadText.textContent = file.name;
          uploadText.style.fontWeight = '600';
          uploadText.style.color = '#0E9488';
        }
        if (uploadHint) {
          uploadHint.textContent = 'Selected document (' + sizeMb + ' MB). Ready to verify upon submission.';
        }
      }
    });
  }

  // 6. 6-Digit OTP Inputs Auto-advance & Paste (Verify OTP Page)
  const otpInputs = document.querySelectorAll('.otp-digit-input');
  const otpFullCode = document.getElementById('ft-otp-full-code');

  if (otpInputs.length === 6) {
    const syncOtpValue = function () {
      let full = '';
      otpInputs.forEach(function (inp) {
        full += inp.value.trim();
      });
      if (otpFullCode) {
        otpFullCode.value = full;
      }
    };

    otpInputs.forEach(function (input, index) {
      // Input event: advance forward
      input.addEventListener('input', function (e) {
        const val = input.value.replace(/[^0-9]/g, '');
        input.value = val.slice(0, 1);

        if (input.value && index < 5) {
          otpInputs[index + 1].focus();
        }
        syncOtpValue();
      });

      // Keydown event: handle backspace navigation
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace') {
          if (!input.value && index > 0) {
            otpInputs[index - 1].focus();
          }
        }
      });

      // Paste event: distribute 6 digits across boxes
      input.addEventListener('paste', function (e) {
        e.preventDefault();
        const clipboard = (e.clipboardData || window.clipboardData).getData('text') || '';
        const digits = clipboard.replace(/[^0-9]/g, '').slice(0, 6);

        if (digits.length > 0) {
          for (let i = 0; i < 6; i++) {
            if (i < digits.length) {
              otpInputs[i].value = digits[i];
            }
          }
          const focusIndex = Math.min(digits.length, 5);
          otpInputs[focusIndex].focus();
          syncOtpValue();
        }
      });
    });

    // Ensure synced on submit
    const otpForm = document.getElementById('ft-otp-form');
    if (otpForm) {
      otpForm.addEventListener('submit', function () {
        syncOtpValue();
      });
    }
  }

  // 7. OTP Countdown Timer & Resend Button
  const resendContainer = document.getElementById('otp-resend-container');
  if (resendContainer) {
    let seconds = parseInt(resendContainer.getAttribute('data-seconds'), 10);
    const timerBlock = document.getElementById('otp-timer-block');
    const displaySpan = document.getElementById('otp-countdown-display');
    const resendBtn = document.getElementById('btn-resend-otp');

    const updateTimerDisplay = function () {
      if (seconds <= 0) {
        if (timerBlock) timerBlock.style.display = 'none';
        if (resendBtn) {
          resendBtn.style.display = 'inline-block';
          resendBtn.removeAttribute('disabled');
        }
      } else {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        const formatted = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        if (displaySpan) displaySpan.textContent = formatted;
        seconds--;
        setTimeout(updateTimerDisplay, 1000);
      }
    };

    if (seconds > 0) {
      updateTimerDisplay();
    } else {
      if (timerBlock) timerBlock.style.display = 'none';
      if (resendBtn) {
        resendBtn.style.display = 'inline-block';
        resendBtn.removeAttribute('disabled');
      }
    }
  }
});

