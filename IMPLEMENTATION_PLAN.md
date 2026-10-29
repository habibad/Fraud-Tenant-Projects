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
| **Phase 4** | Administrative User Review and Approval Portal | **COMPLETED** | Compliance & Approval Queue |
| **Phase 5** | Owner and Tenant Dashboards | Planned | Pending User Authorization |
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
- **Objective**: Build secure authentication for Owners and Tenants with two distinct role-specific registration experiences, secure ownership document storage, 6-digit email OTP verification, password recovery, anti-enumeration safeguards, and lifecycle routing.
- **Status**: **COMPLETED, HARDENED & RELEASE-GATE AUDITED** (2026-10-29)
- **Delivered**:
  - **Two Distinct Registration Forms**:
    - **Property Owner Form (`#ft-register-owner-form`)**: Full legal name (first/last), email address, phone number, password & confirmation, primary rental property street address, city, US state select, ZIP code, property ownership document upload (`ownership_document`), Terms of Service acceptance, and Privacy Policy acknowledgement.
    - **Tenant Form (`#ft-register-tenant-form`)**: Full legal name (first/last), email address, phone number, password & confirmation, current residential street address, city, US state select, ZIP code, conditional previous rental history toggle & fields (`prev_street`, `prev_city`, `prev_state`, `prev_zip`), Terms of Service acceptance, and Privacy Policy acknowledgement. Explicitly zero Date of Birth field.
    - **Consent & FCRA Alignment**: Form disclosures and handlers represent standard agreement to Terms of Service and acknowledgement of the Privacy Policy, removing premature screening permissible purpose assertions. Screening-specific permissible purpose certification is reserved for Phase 7.
  - **Secure Document Management Service (`DocumentService`)**:
    - Private protected storage located at `wp-content/uploads/fraud-tenant-protected/` (configurable outside webroot via `FT_PROTECTED_STORAGE_DIR`).
    - Protected by Apache `.htaccess` (`Order Deny,Allow`, `Deny from all`, `<IfModule mod_authz_core.c> Require all denied </IfModule>`), `index.php` blocking directory traversal, and IIS `web.config`.
    - Strictly verified direct HTTP access blocking returning `HTTP 403 Forbidden`.
    - Strict server-side validation: MIME type check via PHP `finfo` (`application/pdf`, `image/jpeg`, `image/png`), extension check (`.pdf`, `.png`, `.jpg`, `.jpeg`), and maximum 10MB file size limit.
    - Unguessable randomized file naming: `doc_<token>_<timestamp>.<ext>`.
    - Insertion into `wp_ft_documents` with `access_permission = 'private'`; zero public URLs exposed.
  - **Database Schema Version 1.1.0 (`Schema.php`, `MigrationManager.php`)**:
    - Non-destructively migrated `wp_ft_user_profiles` table adding: `prev_address_line1`, `prev_city`, `prev_state`, `prev_postal_code`, `terms_accepted_at`, and `privacy_accepted_at`.
  - **Core Authentication Services**:
    - `OtpService`: 6-digit numeric generation, HMAC-SHA-256 verifier with `FT_OTP_SECRET_KEY`, 60-second cooldown, 10-minute expiry, max 5 attempts, constant-time `hash_equals()`, single-use invalidation, and status transition to `pending_approval`.
    - `AuthService`: `register_owner()`, `register_tenant()`, server-side validation, duplicate email anti-enumeration, IP brute-force lockout, lifecycle status routing, password recovery.
    - `AuthHandler`: Web POST actions, flash messaging, and form draft repopulation on validation errors.
  - **Presentation & Responsive UX (`fraud-tenant-theme`)**:
    - `page-register.php`: Showcase role cards acting as accessible tab switchers between Owner and Tenant forms; direct query parameter support (`?role=owner`, `?role=tenant`); document dropzone; conditional previous address toggle; field-level validation feedback.
    - `page-account-status.php`: Enhanced with registered property address, ownership document status badge, phone, and tenant rental history.
    - `assets/js/main.js` & `assets/css/main.css`: Responsive 3-col and 2-col grids collapsing on mobile; live document filename feedback; OTP auto-advance; countdown timers.
  - **Testing & Verification**:
    - Comprehensive 43-test automated integration suite covering helper validators, server-side validation rejections, file extension/size guards, private document storage, and account lifecycles (**43/43 PASS**).
    - Multi-device Headless Chrome screenshots captured across desktop and mobile.
- **Acceptance Criteria**: Both distinct forms validated server-side; ownership document safely stored in private protected directory; Apache returns 403 on direct access; OTP verification functions as specified; no date of birth collected; automated tests pass.

---

### PHASE 4 — Administrative User Review and Approval Portal
- **Objective**: Build admin management portal for reviewing, approving, rejecting, requesting additional info, and suspending owner and tenant applications, with private document inspection and strict property verification separation.
- **Status**: **COMPLETED** (2026-10-29)
- **Delivered**:
  - **Menu & Portal Integration (`AdminPortal.php`)**:
    - Registered top-level menu "Fraud Tenant" with `dashicons-shield` icon and capability guard `Capabilities::REVIEW_USER_REGISTRATIONS`.
    - Integrated "Applications" submenu and registered secure action POST handlers for review and property actions.
  - **Review Queue Interface (`UserReviewController::render_list()`)**:
    - Status tabs (All Active, Pending Review, Info Requested, Approved, Rejected, Suspended) with real-time counter badges.
    - Role filter dropdown (All, Property Owners, Tenants), search bar (name, email, phone), pagination (20/page), and empty states.
    - Color-coded badges matching design system (Navy, Teal, Amber, Red).
  - **Applicant Detail Screen (`UserReviewController::render_detail()`)**:
    - Two-column responsive interface displaying full collected registration data.
    - Property Owner view: Primary rental property address, property verification status, ownership document metadata card with secure inspection button, and separate property ownership decision box.
    - Tenant view: Current residential address, previous rental history (if supplied), and identity tokenization status.
    - Decision history & compliance audit trail timeline for the applicant.
  - **Secure Document Streaming (`DocumentService::stream_protected_document()`)**:
    - Enforced capability check (`REVIEW_USER_REGISTRATIONS` / `manage_options`).
    - One-time nonce validation (`ft_view_doc_<id>`).
    - Strict database resolution and directory canonicalization with path traversal protection (`strpos($canonical_file, $canonical_base) === 0`).
    - Emitted safe headers (`X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Expires: 0`).
    - Logged `admin.document_viewed` in `AuditLogger`.
  - **Review Action Service (`AdminReviewService.php`)**:
    - Application approval: Updates status to `approved`, logs `admin.application_approved`, and notifies applicant via `wp_mail()`.
    - Application rejection: Requires meaningful rejection reason (>= 10 chars), updates status to `rejected`, stores reason, logs `admin.application_rejected`, and notifies applicant.
    - Request additional information: Requires clear request message (>= 10 chars), updates status to `info_requested`, stores request note in profile, keeps account restricted from dashboard features, logs `admin.info_requested`, and notifies applicant.
    - Separate property verification: Explicitly isolates property deed verification from user account approval. Verifying/rejecting property updates `wp_ft_properties.status` (`verified` / `rejected`) and logs `admin.property_verified` / `admin.property_rejected` without modifying user account status.
  - **Database Schema Version 1.2.0**:
    - Non-destructively added `admin_notes` column to `wp_ft_user_profiles`.
    - Added `STATUS_INFO_REQUESTED = 'info_requested'` in `Constants.php`.
    - Updated `page-account-status.php` holding page to display information request callout with reviewer notes.
  - **Testing & Verification**:
    - Executed 23 Step 0 preflight assertions (**23/23 PASS**).
    - Executed 35 Phase 4 integration assertions covering queues, filters, applicant details, document security, state transitions, property separation, and audit logging (**35/35 PASS**).
    - Captured Headless Chrome screenshots across desktop (1440px) and mobile (375px) for queue list and review detail views.

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
