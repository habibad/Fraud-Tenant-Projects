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
});

