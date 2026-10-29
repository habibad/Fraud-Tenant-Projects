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
});

