/**
 * Fraud Tenant — Authenticated Dashboard Application Scripts
 */
document.addEventListener('DOMContentLoaded', function() {
  // 1. Mobile Sidebar Toggle
  var mobileToggle = document.getElementById('ftDashboardMobileToggle');
  var sidebar = document.getElementById('ftDashboardSidebar');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      sidebar.classList.toggle('is-open');
    });

    // Close when clicking outside on mobile
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 900 && sidebar.classList.contains('is-open')) {
        if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
          sidebar.classList.remove('is-open');
        }
      }
    });
  }

  // 2. Modal Handlers
  function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('is-open');
      document.body.style.overflow = '';
    }
  }

  // Open modal buttons
  document.querySelectorAll('[data-ft-modal-target]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var targetId = btn.getAttribute('data-ft-modal-target');
      openModal(targetId);
    });
  });

  // Close modal buttons
  document.querySelectorAll('.ft-modal-close, [data-ft-modal-close]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = btn.closest('.ft-modal-overlay');
      closeModal(modal);
    });
  });

  // Close on backdrop click
  document.querySelectorAll('.ft-modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        closeModal(overlay);
      }
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var openOverlays = document.querySelectorAll('.ft-modal-overlay.is-open');
      openOverlays.forEach(function(overlay) {
        closeModal(overlay);
      });
    }
  });

  // 3. Phase 7 SSN Preview is purely informational.
  // The input is disabled and readonly. Clicks on the preview button trigger ftScreeningModal directly via data-ft-modal-target.
  // Zero SSN values are accepted, processed, serialized, or transmitted.

  // 4. Phase 9.2: Membership Checkout & Billing Portal
  var membershipModal = document.getElementById('ftMembershipModal');
  if (membershipModal) {
    var restBase = membershipModal.getAttribute('data-rest-url') || '/wp-json/fraud-tenant/v1';
    var restNonce = membershipModal.getAttribute('data-rest-nonce') || '';
    var noticeBox = document.getElementById('ftCheckoutNotice');

    function showNotice(msg, isError) {
      if (noticeBox) {
        noticeBox.style.display = 'block';
        noticeBox.className = 'auth-alert ' + (isError ? 'auth-alert-error' : 'auth-alert-success');
        noticeBox.innerHTML = '<span>' + msg + '</span>';
      }
    }

    // Checkout buttons
    document.querySelectorAll('.ft-checkout-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var planCode = btn.getAttribute('data-plan-code');
        if (!planCode) return;

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Connecting to Stripe...';
        if (noticeBox) noticeBox.style.display = 'none';

        fetch(restBase + '/owner/membership/checkout', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': restNonce
          },
          body: JSON.stringify({ plan_code: planCode })
        })
        .then(function(res) {
          return res.json().then(function(data) {
            return { ok: res.ok, status: res.status, data: data };
          });
        })
        .then(function(result) {
          btn.disabled = false;
          btn.textContent = originalText;
          if (result.ok && result.data && result.data.data && result.data.data.checkout_url) {
            window.location.href = result.data.data.checkout_url;
          } else {
            var errMsg = (result.data && result.data.message) || (result.data && result.data.error) || 'Failed to initiate checkout. Please try again.';
            showNotice(errMsg, true);
          }
        })
        .catch(function(err) {
          btn.disabled = false;
          btn.textContent = originalText;
          showNotice('Connection error with server. Please try again.', true);
        });
      });
    });

    // Customer Portal button
    document.querySelectorAll('.ft-portal-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Loading Billing Portal...';
        if (noticeBox) noticeBox.style.display = 'none';

        fetch(restBase + '/owner/membership/portal', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': restNonce
          }
        })
        .then(function(res) {
          return res.json().then(function(data) {
            return { ok: res.ok, status: res.status, data: data };
          });
        })
        .then(function(result) {
          btn.disabled = false;
          btn.textContent = originalText;
          if (result.ok && result.data && result.data.data && result.data.data.portal_url) {
            window.location.href = result.data.data.portal_url;
          } else {
            var errMsg = (result.data && result.data.message) || (result.data && result.data.error) || 'Failed to open billing portal.';
            showNotice(errMsg, true);
          }
        })
        .catch(function(err) {
          btn.disabled = false;
          btn.textContent = originalText;
          showNotice('Connection error with server. Please try again.', true);
        });
      });
    });
  }
});

