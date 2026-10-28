# FRAUD TENANT — Changelog

All notable changes to the Fraud Tenant platform will be documented in this file, structured phase by phase.

---

## [Phase 2] — Custom Theme, Design System & Public Website (2026-10-28)

### Added
- **Theme Foundation (`wp-content/themes/fraud-tenant-theme/`)**:
  - `style.css`: Theme header defining name, version 1.0.0, and CSS custom properties (Navy `#14263E`, Teal `#0E9488`, Background `#F2F5F9`, Card `#FFFFFF`, Muted Slate `#64748B`, Border `#E2E8F0`).
  - `assets/css/main.css`: Comprehensive, accessible stylesheet featuring CSS Grid/Flexbox layouts, typography, button variants, hero cards, floating badges, split audience cards, FAQ accordions, and responsive breakpoints down to 320px.
  - `assets/js/main.js`: Mobile drawer menu toggle with keyboard accessibility (ESC close, aria-expanded states), accessible accordion toggle for FAQs, and real-time form validation.
  - `header.php`: Semantic HTML5 header with SVG shield brand logo, skip-to-content accessibility link, navigation menu with fallback, public action buttons (Login, Get Started), and mobile slide-out drawer.
  - `footer.php`: 4-column footer featuring platform links, role-specific pages, transparency resources, legal drafts, copyright, and FCRA/non-affiliation disclaimer.
  - `front-page.php`: Full landing page matching `Fraud Tenant Product UI Showcase.png`:
    - Hero section with "A Safer Rental Community" floating badge and primary CTAs.
    - Hero visual showcase: "Safer Tenancies Stronger Communities" card and "Applicant Found - View Report (Locked)" preview card.
    - 4-step workflow: 1. Register -> 2. Get Verified -> 3. Access Features -> 4. Build Safer Rentals.
    - Trust & verification metrics bar.
    - Audience split cards for Property Owners and Tenants.
    - Interactive FAQ accordion section and bottom conversion CTA.
  - `page.php` & `index.php`: Standard WordPress page and template fallbacks with card wrappers.
  - `template-parts/page-header.php`: Reusable header banner for subpages.
  - `template-parts/contact-form.php`: Accessible contact form with CSRF nonce, honeypot field, and server error/success alert rendering.
  - `functions.php`: Theme setup, script/style enqueuing, fallback navigation, contact enquiry form handler (`admin_post_ft_submit_contact_enquiry`), and idempotent public page generator (`fraud_tenant_ensure_public_pages()`).
- **9 Public WordPress Pages**:
  - Home (`/`), How It Works (`/how-it-works/`), For Property Owners (`/for-property-owners/`), For Tenants (`/for-tenants/`), FAQ (`/faq/`), Contact & Support (`/contact/`), Privacy Policy (`/privacy-policy/`), Terms of Service (`/terms-of-service/`), Tenant Rights & Screening Information (`/tenant-rights/`).
- **Security & Privacy Safeguards in Public Forms**:
  - Anti-spam honeypot field (`ft_enquiry_hp`).
  - Cryptographic CSRF nonce verification (`wp_verify_nonce`).
  - Rate limiting transient cache preventing submission flood.
  - Privacy SSN pattern rejection: rejects enquiry messages containing Social Security Number patterns (`\d{3}[-\s]?\d{2}[-\s]?\d{4}`) to prevent accidental PII leakage in unencrypted emails.

### Fixed
- **Mobile Menu Visibility on Desktop**: Added base `.mobile-drawer { display: none; }` in `main.css` to prevent the drawer from appearing in normal document flow on desktop viewports.

---

## [Phase 1.1] — Corrective Security Review and Remediation (2026-10-28)

### Security Enhancements & Hardening
- **Task 1: Strengthened OTP Hashing**:
  - Replaced unkeyed OTP hashing with **HMAC-SHA-256** using a dedicated server-side secret key: `FT_OTP_SECRET_KEY`.
  - Implemented fail-closed behavior: `SecurityService::hash_otp()` and `verify_otp()` return `false` if `FT_OTP_SECRET_KEY` is missing or invalid.
  - Enforced constant-time comparison via PHP's `hash_equals()`.
  - Isolated `FT_OTP_SECRET_KEY` from `FT_MATCH_SECRET_KEY`.
  - Updated `DiagnosticController` at `/status` to report `otp_hashing_ready: false` safely without exposing secrets.
- **Task 2: Administrator & Role Capability Verification**:
  - Verified User ID 1 (`anik`) directly: possesses all 18 custom administrative capabilities while retaining all core WordPress capabilities (`manage_options`, `install_plugins`, etc.).
  - Confirmed strict capability isolation: `ft_owner` and `ft_tenant` have zero administrative access, and `ft_tenant` has zero owner capabilities.
- **Task 3: Audit Log & Payment Event Data Minimization**:
  - Confirmed application-level append-only design: `AuditLogger` provides no `update` or `delete` methods, and no application endpoints allow users to modify or delete logs.
  - Implemented `Sanitizer::minimize_payment_payload()` to strip raw card details, customer addresses, and customer emails from payment event payloads before storage in `wp_ft_payment_events`.
  - Verified schema-level deduplication: MySQL `UNIQUE KEY event_id` strictly prevents duplicate webhook processing.
- **Test Suite**: Executed 21 dedicated security tests across all three tasks; all 21 passed.

---

## [Phase 1] — Custom Plugin Foundation and Database Architecture (2026-10-28)

### Added
- Created custom plugin `fraud-tenant-core` in `wp-content/plugins/fraud-tenant-core/`.
- Implemented modular object-oriented architecture:
  - `Autoloader.php`: PSR-4 compatible autoloader for the `FraudTenant\` namespace.
  - `Constants.php`: System constants, database version (`1.0.0`), table names, account states, and REST namespace (`fraud-tenant/v1`).
  - `Sanitizer.php`: Redaction helpers for sensitive keys (passwords, OTPs, SSNs, Stripe secrets) and SSN maskers (`••••-1234`).
  - `Capabilities.php`: Granular capability constants for Owners, Tenants, and Administrators.
  - `RoleManager.php`: Managed registration of custom roles `ft_owner` and `ft_tenant` with strict capability isolation.
  - `Schema.php`: Normalized schema definition for 14 custom database tables formatted for `dbDelta()`.
  - `MigrationManager.php`: Safe, version-controlled database migrations with automatic default membership tier seeding (`free`, `standard_monthly`, `standard_yearly`).
  - `AuditLogger.php`: Append-only audit logging service with automatic sensitive data redaction.
  - `SecurityService.php`: Keyed HMAC-SHA256 SSN tokenization and OTP hashing infrastructure.
  - `BaseController.php`: REST API base controller with standard response wrappers and server-side permission callbacks (`check_authenticated`, `check_admin_permission`, `check_owner_permission`, `check_tenant_permission`).
  - `DiagnosticController.php`: Public operational health endpoint (`/status`) and protected test route (`/auth-check`).
  - `RestServer.php`: REST API coordinator hooking into `rest_api_init`.
  - `Activator.php` & `Deactivator.php`: Plugin activation and non-destructive deactivation handlers.
  - `Plugin.php`: Main orchestrator hooking into WordPress lifecycle.
- Created and verified 14 custom database tables using dynamic `$wpdb->prefix`.
- Seeded default membership plans into `wp_ft_membership_plans`.
- Verified REST endpoints via HTTP:
  - `GET /wp-json/fraud-tenant/v1/status` (200 OK, zero secrets exposed)
  - `GET /wp-json/fraud-tenant/v1/auth-check` (401 Unauthorized for anonymous callers)

---

## [Phase 0] — Repository Audit and Implementation Preparation (2026-10-28)

### Added
- Initialized local Git repository tracking in `c:\xampp\htdocs\fraudtenant`.
- Configured `.gitignore` to strictly exclude sensitive files (`wp-config.php`, uploads, logs, database dumps, OS files).
- Created core documentation suite (`AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, `CHANGELOG.md`).
- Cataloged approved design assets (`Fraud Tenant Product UI Showcase.png`).
- Verified local development URL (`http://localhost/fraudtenant`) returning HTTP 200 OK.
