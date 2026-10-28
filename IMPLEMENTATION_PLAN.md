# FRAUD TENANT — Implementation Plan & Phase Roadmap

## Project Summary
Fraud Tenant is a secure, modern, responsive WordPress-based rental incident reporting and tenant screening platform tailored for the US market. The system connects property owners and tenants with verified rental histories, incident documentation, dispute resolution, and subscription-backed screening capabilities.

---

## Phase Status Summary

| Phase | Description | Status | Completion Target |
| :--- | :--- | :--- | :--- |
| **Phase 0** | Repository Audit and Implementation Preparation | **COMPLETED** | Initial Step |
| **Phase 1** | Custom Plugin Foundation and Database Architecture | **COMPLETED** | Core Backend Foundation |
| **Phase 2** | Custom Theme, Design System and Public Website | **COMPLETED** | Presentation & Content Layer |
| **Phase 3** | Registration, Email OTP, Login and Password Recovery | **COMPLETED** | Authentication & Lifecycle |
| **Phase 4** | Administrative User Review and Approval Portal | Planned | Pending User Authorization |
| **Phase 5** | Owner and Tenant Dashboards | Planned | Pending Phase 4 |
| **Phase 6** | Property Management and Secure Document Uploads | Planned | Pending Phase 5 |
| **Phase 7** | Authorized Tenant Search Using SSN | Planned | Pending Phase 6 |
| **Phase 8** | Incident Reporting, Evidence Review and Tenant Disputes | Planned | Pending Phase 7 |
| **Phase 9** | Membership Plans and Stripe Subscriptions | Planned | Pending Phase 8 |
| **Phase 10** | Notifications, Email Workflows and Audit Trail | Planned | Pending Phase 9 |
| **Phase 11** | Integration, Security, Accessibility and Production Readiness | Planned | Final Phase |

---

## Detailed Phase Breakdown

### PHASE 0 — Repository Audit and Implementation Preparation
- **Objective**: Assess existing WordPress environment, inspect assets, establish documentation, exclude secrets, and verify local development and testing tools.
- **Status**: **COMPLETED** (2026-10-28)

---

### PHASE 1 — Custom Plugin Foundation and Database Architecture
- **Objective**: Build `fraud-tenant-core` plugin with versioned custom tables, role definitions, and service architecture.
- **Status**: **COMPLETED** (2026-10-28)
- **Delivered**:
  - `wp-content/plugins/fraud-tenant-core/` modular structure.
  - Class autoloader (`Autoloader.php`) mapping `FraudTenant\` namespace.
  - Core bootstrap, activator, and non-destructive deactivator (`Plugin.php`, `Activator.php`, `Deactivator.php`).
  - Database schema manager (`Schema.php`, `MigrationManager.php`) with version `1.0.0` managing 14 relational tables.
  - Initial seed data: 3 default membership tiers (`free`, `standard_monthly`, `standard_yearly`).
  - Custom roles registered: `ft_owner` (Property Owner) and `ft_tenant` (Tenant) with granular capabilities.
  - Administrative capabilities mapped to core `administrator` role.
  - REST API foundation under namespace `fraud-tenant/v1` with safe diagnostic `/status` endpoint and authenticated test endpoint `/auth-check`.
  - Foundational services: `AuditLogger` (tamper-evident audit trail with automated secret redaction) and `SecurityService` (keyed HMAC SSN tokenization and OTP hashing).
- **Custom Database Tables Created (14)**:
  1. `{$wpdb->prefix}ft_user_profiles`
  2. `{$wpdb->prefix}ft_email_otps`
  3. `{$wpdb->prefix}ft_properties`
  4. `{$wpdb->prefix}ft_documents`
  5. `{$wpdb->prefix}ft_tenant_identities` (keyed HMAC token hash, last 4 digits only)
  6. `{$wpdb->prefix}ft_screening_requests`
  7. `{$wpdb->prefix}ft_incident_reports`
  8. `{$wpdb->prefix}ft_report_reviews`
  9. `{$wpdb->prefix}ft_tenant_disputes`
  10. `{$wpdb->prefix}ft_membership_plans`
  11. `{$wpdb->prefix}ft_subscriptions`
  12. `{$wpdb->prefix}ft_payment_events` (Stripe idempotency tracking)
  13. `{$wpdb->prefix}ft_notifications`
  14. `{$wpdb->prefix}ft_audit_log`
- **Acceptance Criteria**: Plugin activates without PHP errors; all 14 tables verified; rerun idempotency confirmed; role isolation verified; unauthenticated REST requests blocked; no secrets exposed.

---

### PHASE 2 — Custom Theme, Design System and Public Website
- **Objective**: Develop `fraud-tenant-theme` adhering precisely to the approved design system (`#14263E` Navy, `#0E9488` Teal, `#F2F5F9` Background).
- **Status**: **COMPLETED** (2026-10-28)
- **Delivered**:
  - `wp-content/themes/fraud-tenant-theme/` custom theme structure.
  - CSS Custom Properties matching approved PropTech design tokens (`#14263E` Navy, `#0E9488` Teal, `#F2F5F9` Gray, `#FFFFFF` Card).
  - Responsive layout system supporting breakpoints from 320px mobile to 1440px desktop.
  - Accessible header with brand logo, desktop nav, action CTAs, and mobile drawer menu with keyboard navigation (ESC key support).
  - 4-column footer featuring platform links, role-specific pages, transparency resources, legal drafts, and regulatory disclaimers.
  - Landing page (`front-page.php`) reflecting `Fraud Tenant Product UI Showcase.png`:
    - Hero section with badge "A Safer Rental Community", value proposition, and primary CTAs.
    - Hero visual showcase: floating "Safer Tenancies Stronger Communities" and "Applicant Found - View Report (Locked)" cards.
    - 4-step workflow: 1. Register -> 2. Get Verified -> 3. Access Features -> 4. Build Safer Rentals.
    - Trust & verification metrics bar.
    - Audience split cards for Property Owners and Tenants with distinct feature lists.
    - Accessible interactive FAQ accordion section.
  - 9 published WordPress public pages returning HTTP 200:
    1. Home (`/`)
    2. How It Works (`/how-it-works/`)
    3. For Property Owners (`/for-property-owners/`)
    4. For Tenants (`/for-tenants/`)
    5. Frequently Asked Questions (`/faq/`)
    6. Contact & Support (`/contact/`)
    7. Privacy Policy (Draft) (`/privacy-policy/`)
    8. Terms of Service (Draft) (`/terms-of-service/`)
    9. Tenant Rights & Screening Information (`/tenant-rights/`)
  - Server-side contact form handler with CSRF nonce verification, honeypot spam protection, input length validation, and a privacy safeguard that detects and rejects SSN patterns in enquiry messages.
  - Preserved `fraud-tenant-core` plugin integrity, all 14 custom tables, and role capabilities.
- **Acceptance Criteria**: Theme activated cleanly; static front page set; 9 public pages return HTTP 200; headless browser screenshots verified across desktop, tablet, and mobile; contact form validation tests pass.

---

### PHASE 3 — Registration, Email OTP, Login and Password Recovery
- **Objective**: Build secure authentication for Owners and Tenants with 6-digit email OTP verification, password recovery, anti-enumeration safeguards, and lifecycle routing.
- **Status**: **COMPLETED** (2026-10-28)
- **Delivered**:
  - `OtpService` in `wp-content/plugins/fraud-tenant-core/includes/Services/OtpService.php`:
    - Cryptographically secure 6-digit numeric OTP generation (`random_int(100000, 999999)`).
    - Keyed HMAC-SHA-256 verifier hashing via `SecurityService::hash_otp()` using dedicated server-side secret `FT_OTP_SECRET_KEY`.
    - Fail-closed security architecture when secret is missing or empty.
    - Constant-time verification using `hash_equals()`.
    - 60-second resend cooldown (`resend_available_at`), 10-minute expiry (`expires_at`), max 5 failed attempts per challenge, and single-use invalidation (`consumed_at`).
    - Automatic user status transition from `email_unverified` to `pending_approval` upon successful OTP confirmation.
  - `AuthService` in `wp-content/plugins/fraud-tenant-core/includes/Auth/AuthService.php`:
    - Dual role registration supporting `ft_owner` (Property Owner) and `ft_tenant` (Tenant).
    - Initial registration status set to `email_unverified` with profile created in `wp_ft_user_profiles`.
    - Generic anti-enumeration response on duplicate email submissions.
    - IP-based brute-force throttling (5 failed attempts within 15 minutes enforces a 15-minute temporary lockout).
    - Lifecycle-aware login gateway: `email_unverified` redirected to `/verify-otp/`, `pending_approval` redirected to `/account-status/`, and `rejected`/`suspended` accounts blocked.
    - Password recovery dispatch with enumeration protection, secure reset key generation via WordPress core mechanisms, and constant-time key validation.
  - `AuthHandler` in `wp-content/plugins/fraud-tenant-core/includes/Auth/AuthHandler.php`:
    - Registered `admin_post_*` and `admin_post_nopriv_*` hooks for registration, OTP verification, OTP resend, login, logout, password recovery, and password reset.
    - Strict CSRF nonce validation on all form submissions.
    - Session-isolated flash message handling via transients for clean redirects.
  - Custom Theme Authentication Templates in `wp-content/themes/fraud-tenant-theme/`:
    - `page-login.php`: Split auth card matching showcase mockup, left form column with email and toggleable password inputs, right photographic column with modern suburban visual and floating glassmorphism card ("Safer Tenancies Stronger Communities").
    - `page-register.php`: Dual role selector cards (Property Owner vs. Tenant with SVG icons and active state), name, email, password confirmation, terms acceptance checkbox, and full-width teal CTA.
    - `page-verify-otp.php`: Centered card with email address display, 6 individual numeric inputs with auto-advance and clipboard paste support, Verify OTP CTA, and live countdown timer toggling to "Resend Code".
    - `page-forgot-password.php`: Centered card with email input and generic dispatch confirmation.
    - `page-reset-password.php`: Secure key & login parameter verification, new password inputs with eye toggles, and update submission.
    - `page-account-status.php`: Holding page for pending/suspended/rejected accounts with status badge, 4-step progress indicator, user account details grid, and sign-out button.
    - Separation of shells: dedicated minimal auth header and footer suppressing marketing navigation.
    - Interactive UX in `assets/js/main.js`: password visibility eye toggles, role selector card switcher, 6-digit OTP input auto-advance, backspace navigation, clipboard paste handler, and countdown timer.
  - Automated Integration Testing:
    - 30 comprehensive automated integration test assertions executed via PHP CLI covering all registration validation, role assignment, initial `email_unverified` status, duplicate email anti-enumeration, login blocking, OTP hashing, cooldowns, single-use replays, status transitions, administrator bypass, password reset keys, and password updates (`Passed: 30 | Failed: 0`).
  - Visual Verification:
    - Headless Chrome captures on desktop and mobile viewports verifying pixel-level styling, typography, colors, and zero horizontal scroll.
- **Acceptance Criteria**: All 30 tests pass; registration creates user with proper role and `email_unverified` status; OTP generated and verified with HMAC-SHA-256; login routes correctly based on lifecycle status; password recovery functions securely; theme templates match design system and showcase layout.

---

### PHASE 4 — Administrative User Review and Approval Portal
- **Objective**: Build admin management portal for reviewing, approving, rejecting, and suspending owner and tenant applications.
- **Status**: Planned

---

### PHASE 5 — Owner and Tenant Dashboards
- **Objective**: Construct role-specific application shells for approved Property Owners and Tenants.
- **Status**: Planned

---

### PHASE 6 — Property Management and Secure Document Uploads
- **Objective**: Allow owners to manage rental units and securely upload/view verification documents.
- **Status**: Planned

---

### PHASE 7 — Authorized Tenant Search Using SSN
- **Objective**: Implement SSN-based tenant matching interface under strict privacy, security, and permissible purpose controls.
- **Status**: Planned

---

### PHASE 8 — Incident Reporting, Evidence Review and Tenant Disputes
- **Objective**: Document rental incidents, support administrative review, and provide tenant dispute resolution.
- **Status**: Planned

---

### PHASE 9 — Membership Plans and Stripe Subscriptions
- **Objective**: Manage paid subscription plans and recurring Stripe billing.
- **Status**: Planned

---

### PHASE 10 — Notifications, Email Workflows and Audit Trail
- **Objective**: Centralized notification delivery and tamper-evident audit logging.
- **Status**: Planned

---

### PHASE 11 — Integration, Security, Accessibility and Production Readiness
- **Objective**: Comprehensive end-to-end verification, security audits, accessibility checks, and launch readiness sign-off.
- **Status**: Planned
