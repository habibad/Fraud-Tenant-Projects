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

  // 3. SSN Formatter Helper
  var ssnInput = document.getElementById('ftSsnSearchInput');
  if (ssnInput) {
    ssnInput.addEventListener('input', function(e) {
      var val = e.target.value.replace(/\D/g, '').substring(0, 9);
      var formatted = '';
      if (val.length > 5) {
        formatted = val.substring(0, 3) + '-' + val.substring(3, 5) + '-' + val.substring(5);
      } else if (val.length > 3) {
        formatted = val.substring(0, 3) + '-' + val.substring(3);
      } else {
        formatted = val;
      }
      e.target.value = formatted;
    });

    var ssnForm = document.getElementById('ftSsnSearchForm');
    if (ssnForm) {
      ssnForm.addEventListener('submit', function(e) {
        e.preventDefault();
        openModal('ftScreeningModal');
      });
    }
  }
});
