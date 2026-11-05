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
| **Phase 5** | Owner and Tenant Dashboards | **COMPLETED** | Role-Isolated Dashboards |
| **Phase 6** | Property Management and Secure Document Uploads | **COMPLETED** | Property CRUD & Verification |
| **Phase 7** | Authorized Tenant Search Using SSN | In Progress | Phase 7.1 & 7.1.1 Completed; Phase 7.2 Pending |
| **Phase 8** | Incident Reporting, Evidence Review and Tenant Disputes | **COMPLETED** | Phase 8.1, 8.1.1, 8.2, 8.3, 8.4 & 8.4.1 Completed |
| **Phase 9** | Membership Plans and Stripe Subscriptions | **COMPLETED** | Full Pipeline, Webhooks & Entitlements |
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
  - Foundational services: `AuditLogger` (append-only application-level audit history with automated secret redaction) and `SecurityService` (keyed HMAC SSN tokenization and OTP hashing).
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
- **Status**: Completed (2026-10-29)
- **Key Deliverables & Architecture**:
  - **Separation of Shells**:
    - Created dedicated dashboard top header bar (`.dashboard-topbar`) and single-line clean footer (`.auth-footer`) for dashboard templates.
    - Completely suppressed the oversized public marketing navigation bar and large public footer on authenticated dashboard pages.
  - **Access Control & Lifecycle Gate (`DashboardService::enforce_access()`)**:
    - Enforced strict authentication: Unauthenticated visitors redirected to `/login/`.
    - Lifecycle enforcement: `email_unverified` redirected to `/verify-otp/`, `pending_approval` / `info_requested` redirected to `/account-status/`, `rejected` / `suspended` logged out and redirected to `/login/?error=status`.
    - Role isolation: `ft_owner` attempting to access `/tenant-dashboard/` redirected to `/owner-dashboard/`; `ft_tenant` attempting to access `/owner-dashboard/` redirected to `/tenant-dashboard/`. Administrators permitted inspection access.
  - **Service Layer (`DashboardService.php`)**:
    - `get_owner_dashboard_data(int $owner_id)`: Queries real registered properties from `wp_ft_properties`, screening searches from `wp_ft_screening_requests`, incident reports from `wp_ft_incident_reports`, membership plan from `wp_ft_subscriptions` / `wp_ft_membership_plans`, and profile metadata with zero mock data.
    - `get_tenant_dashboard_data(int $tenant_id)`: Queries disclosed incident records, disputes, notifications, residential addresses, and recent audit activity timeline.
    - `update_safe_profile(int $user_id, array $data)`: Safe profile field updates (phone) protected by CSRF nonce and IDOR verification.
  - **Property Owner Dashboard Template (`page-owner-dashboard.php`)**:
    - Matches approved design showcase (`Fraud Tenant Product UI Showcase.png`).
    - Navy `#14263E` sidebar with active teal `Dashboard` state and icons for My Properties, Search Tenants, My Reports, Membership, Notifications, Profile Settings.
    - Top greeting banner: "Welcome back, {First Name}!" with "+ Add Property" button triggering Phase 6 informational modal.
    - 4-column Stat Metrics bar: My Properties (real count + verification tag), Tenant Searches (real count), My Reports (real count), Membership ("Free Plan" badge + "Upgrade" CTA).
    - "Search Tenant Rental History" box with formatted `XXX-XX-XXXX` input, lock indicator, Search button, and Phase 7 compliance modal.
    - Two-column grid: "My Properties" table displaying real street, city, state, zip, and status badge (`pending_verification` / `verified`), Property Verification Notice; "Recent Searches" table; "Upgrade Your Membership" promo card with checklist; and Account profile card.
  - **Tenant Dashboard Template (`page-tenant-dashboard.php`)**:
    - Navy `#14263E` sidebar with active Dashboard, My Records, My Disputes, Notifications, Profile Settings, and "Know Your Rights" FCRA info in the footer.
    - Top greeting banner: "Welcome back, {First Name}! Manage your rental information and account activity."
    - 3-column Stat Metrics bar: My Records (disclosed count or clean record status), My Disputes (real count), Notifications (account alerts).
    - Two-column grid: "Recent Activity" timeline with real audit log timestamps; "Residential Information on File" displaying current and previous addresses; "My Rental Records" with clean status state; "Your Rights Matter" card with "View My Records" CTA; and FCRA Dispute Resolution card.
  - **Interactive Modals & Interactivity (`assets/js/dashboard.js`, `assets/css/dashboard.css`)**:
    - Add Property informational modal (Phase 6 placeholder).
    - Tenant Screening compliance modal (Phase 7 permissible purpose notice).
    - Membership Plans modal showing Free ($0), Standard Monthly ($29/mo), Standard Annual ($290/yr).
    - Safe Profile Edit modal (phone number update via CSRF nonce).
    - Mobile sidebar drawer toggle with backdrop dismissal.
  - **Testing & Verification**:
    - Executed 45 Step 0 security preflight assertions (**45/45 PASS**).
    - Executed 32 Phase 5 functional suite assertions covering access control, role boundaries, data isolation, IDOR defense, and template integrity (**32/32 PASS**).
    - Executed 35 Phase 4 regression assertions (**35/35 PASS**).
    - Captured Headless Chrome screenshots across desktop (1440x900) and mobile (375x812) for both Owner and Tenant dashboards.

---

### PHASE 5.1 — Dashboard Hardening and Release Gate
- **Objective**: Conduct strict security, privacy, and data-accuracy hardening of owner and tenant dashboards prior to Phase 6 authorization.
- **Status**: **COMPLETED** (2026-10-29)
- **Delivered**:
  - **Tenant Record Messaging Accuracy**:
    - Eliminated all statements implying guaranteed clean rental records ("Clear Record on File", "Your record with Fraud Tenant is currently clear").
    - Replaced with neutral, factual wording: *"No Disclosable Incident Records Available"* and *"No Disclosed Incidents"*.
    - Added explicit legal caveat: *"Please note: Available platform information may be incomplete and does not certify a completely clear rental history."*
    - Enforced strict double-gate in `DashboardService::get_tenant_dashboard_data`: reports must have `disclosable = 1 AND status = 'approved'`.
  - **Address Verification Labels**:
    - Relabeled addresses in tenant dashboard to *"Current Address on File"* and *"Previous Address Reported During Registration"*.
    - Removed any wording inferring address verification from account approval.
    - Updated identity token status to *"Identity Matching Token Active (HMAC Protected)"*.
  - **Phase 7 SSN Preview Immunity**:
    - Converted Owner Dashboard SSN search input to `disabled` and `readonly` with preview placeholder.
    - Changed search action button to `type="button" data-ft-modal-target="ftScreeningModal"`, triggering informational compliance modal directly.
    - Removed input and submit listeners from `assets/js/dashboard.js`. Verified zero SSN collection, serialization, logging, or transmission, and zero screening requests created.
  - **Secure Tenant Activity Timeline**:
    - Restricted query in `DashboardService::get_tenant_dashboard_data` to `object_type = 'user' AND object_id = %d` and an explicit safe allowlist: `admin_application_approved`, `auth_otp_verified`, `auth_tenant_registered`, `user_profile_updated`.
    - Sanitized and mapped to user-facing titles, selecting only `id, action, created_at` from `wp_ft_audit_log`. Zero leak of admin notes, reviewer IDs, client telemetry, or raw JSON.
    - Clean neutral empty state displayed when no safe activity exists.
  - **Owner Data Scoping & Real Membership Verification**:
    - Enforced strict `owner_id = %d` on all owner queries (properties, searches, reports).
    - Verified recent searches display only masked `••••-XXXX` last 4 digits, never HMAC token.
    - Verified genuine plan data (`Free Plan`, `Standard Monthly`, `Standard Annual`) with zero fabricated active benefits.
  - **Admin Review Historical Continuity**:
    - Verified that repeated rejection or info-request actions preserve an append-only application-level audit history in `wp_ft_audit_log` without overwriting history.
    - Enhanced admin detail review history view in `UserReviewController.php` to cleanly display and highlight past reviewer notes and reasons.
  - **Testing & Verification**:
    - Executed 36 Phase 5.1 hardening assertions (**36/36 PASS**).
    - Executed 24 Phase 4 & 5 regression assertions (**24/24 PASS**).
    - Executed 44 Phase 5.1.1 reconciliation assertions (**44/44 PASS**).
    - Recaptured Headless Chrome desktop (1440x1050) and mobile (390x844) screenshots reflecting hardened UI states.

---

### PHASE 6 — Property Management and Secure Document Uploads
- **Objective**: Implement multi-property management for approved Property Owners, private ownership-document uploads, and a dedicated administrative property verification workflow completely decoupled from user account approval.
- **Status**: **COMPLETED** (2026-10-31)
- **Key Deliverables & Architecture**:
  - **Database Schema Version 1.3.0 (`MigrationManager::migrate(true)`)**:
    - Non-destructively augmented `{$wpdb->prefix}ft_properties` with: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
    - Non-destructively augmented `{$wpdb->prefix}ft_documents` with: `review_status`, `admin_notes`.
    - Updated `Constants::DB_VERSION = '1.3.0'` and added status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`, `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
  - **Core Service Layer (`PropertyService.php`)**:
    - `create_property()`: Strict server-side validation, sets initial status to `pending_verification`, logs `owner_property_created`.
    - `get_owner_properties()`: Scoped strictly to authenticated `owner_id`, supports active and archived subviews, aggregates document counts.
    - `get_property()`: Single property retrieval with strict ownership verification.
    - `update_property()`: Modifies non-critical attributes safely; automatically resets status from `verified` to `pending_verification` when compliance-critical address fields (street, city, state, zip) are altered. Protects `owner_id` from modification.
    - `archive_property()` & `unarchive_property()`: Non-destructive soft archival using `archived_at` timestamp. Unarchiving restores property to `pending_verification`.
    - `upload_property_document()`: Uploads ownership evidence (deed, title, tax receipt) with strict 10MB limit, allowed MIME types (`application/pdf`, `image/jpeg`, `image/png`), magic byte inspection, double-extension defense, and storage in protected directory.
  - **Secure Document Streaming (`DocumentService::stream_protected_document()`)**:
    - Dual authorization check: Allows reviewer capability OR document owner (`(int) $doc->user_id === $requesting_user_id`).
    - Enforces path traversal confinement within canonical storage root (`strpos( $canonical_file, $canonical_base ) === 0`).
    - Protected storage barrier files verified: `.htaccess`, `index.php`, `web.config`.
    - Emits private, no-store cache headers and `nosniff` protection. Logs `document_access_denied`, `owner_document_viewed`, and `admin_document_viewed`.
  - **Administrative Verification Portal (`PropertyReviewController.php` & `AdminReviewService.php`)**:
    - Registered submenu under Fraud Tenant: **Properties** (`fraud-tenant-properties`).
    - Verification Queue: Filter tabs (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`), address search, pagination, and document status indicators.
    - Detail Inspection Screen: Displays property details, owner information, uploaded ownership documents, secure streaming links, decision history notes, and audit trail.
    - Review Actions:
      - `verify_property()`: Sets property to `verified` and document to `approved`.
      - `reject_property()`: Requires >= 10 character explanation, sets property to `rejected` and document to `rejected`.
      - `request_property_info()`: Requires >= 10 character note, sets property to `info_requested`.
    - **Decoupled Lifecycle**: Verified that rejecting, requesting info, or verifying a property operates independently of user account approval (`wp_ft_user_profiles.approval_status` remains unaffected).
  - **Frontend Theme Integration (`page-owner-dashboard.php` & `dashboard.css`)**:
    - Clean tab navigation: Overview vs Properties tab (`?ft_tab=properties`).
    - Properties List subtabs: Active Properties vs Archived Properties.
    - Property Detail & Edit view: Full address editing, property type, units count, document upload section with replacement preservation.
    - Functional "+ Add Property" modal submitting directly to `PropertyHandler`.
  - **Testing & Verification**:
    - Executed 45 Phase 6 comprehensive assertions (**45/45 PASS**).
    - Executed 24 Phase 4 & 5 regression assertions (**24/24 PASS**).
    - Executed 44 Phase 5.1.1 reconciliation assertions (**44/44 PASS**).
    - **Total verified assertions: 113 / 113 PASS (100%)**.
    - Captured 11 Headless Chrome screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
  - **Boundary Integrity**: Strictly zero Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments) logic implemented.

---

### PHASE 7 — Authorized Tenant Search Using SSN
- **Objective**: Implement SSN-based tenant matching interface under strict privacy, security, and permissible purpose controls.
- **Status**: In Progress (Phase 7.1 & 7.1.1 Completed; Phase 7.2 Pending)

#### Phase 7.1 & 7.1.1 — Authorized Property Owner Screening Request Foundation
- **Objective**: Implement authorized property owner screening request foundation with permissible purpose attestation and active verified property validation.
- **Status**: **COMPLETED** (2026-11-01)
- **Delivered**: Identity-free foundation with zero real applicant identity collection; `ssn_last_four` strictly unwritten; action-specific time-limited nonce CSRF defense combined with independent server authorization; unverified owner attestation statement v1.0; verified property requirement; rate limiting; audit logging (`screening_request_initiated`); initial status `pending`, disclosure level `locked`.
- **Testing**: 58/58 Phase 7.1 assertions PASS; 224/224 regression PASS; total 282/282 PASS.

---

### PHASE 8 — Incident Reporting, Evidence Review and Tenant Disputes
- **Objective**: Document rental incidents, support administrative review, and provide tenant dispute resolution.
- **Status**: **COMPLETED** (2026-11-02) (Phase 8.1, 8.1.1, 8.2, 8.3, 8.4, and 8.4.1 Completed)

#### Phase 8.1 — Owner Incident Submission Foundation
- **Objective**: Implement authorized property owner incident report submission with verified active property validation, 7 approved categories, factual attestation, BLK-08 privacy gating, and evidence document attachments.
- **Status**: **COMPLETED** (2026-11-01)
- **Delivered**:
  - `IncidentService`, `IncidentHandler`, `IncidentController` REST API.
  - BLK-08 Sensitive PII Encryption Gate: fail-closed lock in production (`submission_locked_blk08`); synthetic test-mode gate (`IncidentService::is_test_mode_enabled()`).
  - Active verified property validation with IDOR prevention.
  - 7 approved incident categories (`unpaid_rent`, `property_damage`, `late_rent`, `lease_violation`, `unauthorized_pet`, `noise_complaint`, `other_violation`).
  - Strict date validation (past/today) and amount validation (non-negative numeric).
  - Factual description validation (min 10 chars) and mandatory owner attestation statement v1.0.
  - Initial status strictly `submitted` with `disclosable = 0` (never automatically approved or disclosable).
  - Safe reference generation `INC-%06d`.
  - Supporting evidence upload via `DocumentService::store_incident_document()` with protected storage and streaming authorization.
  - Append-only application audit logging `incident.report_submitted` with safe metadata and zero sensitive narrative or PII leakage.
  - Owner dashboard Incident Reports section (`#my-reports`) and submission modal (`#ftIncidentModal`).
- **Testing**: 71/71 Phase 8.1 assertions PASS; 282/282 regression PASS; total 353/353 PASS; Headless Chrome desktop, tablet, and mobile visual screenshots captured.

#### Phase 8.1.1 — Security Reconciliation and Production Privacy Gate Verification
- **Objective**: Conduct security reconciliation, eliminate filter-based bypass vulnerabilities, harden privacy gating with direct PHP constant evaluation, enforce tenant identity nullability semantics, document rate-limiter concurrency, and verify production fail-closed behavior across all entry points.
- **Status**: **COMPLETED** (2026-11-01)
- **Delivered**:
  - Eliminated WordPress filter vulnerability: removed all calls to `wp_get_environment_type()` and `apply_filters` from `IncidentService::is_test_mode_enabled()` to prevent environment spoofing via third-party filter hooks.
  - Enforced strict two-condition security gate via direct PHP constant evaluation: `FT_INCIDENT_TEST_MODE === true` AND direct constant `WP_ENVIRONMENT_TYPE` strictly in `['local', 'development', 'staging']`. Missing, undefined, unknown, or `'production'` environments fail-closed.
  - Immunity against parameter/cookie/capability/filter tampering confirmed across all entry points.
  - Verified deployment boundary: `wp-config.php` is strictly local-only and excluded from Git (.gitignore line 9). Local development explicitly configures `WP_ENVIRONMENT_TYPE = 'local'`. Shared and production deployment policy enforces fail-closed fallback to `'production'` if undefined.
  - Pre-processing fail-closed privacy gating: REST API `permission_callback` (423 `submission_locked_blk08`), AJAX handler (423 `submission_locked_blk08`), Web POST handler (`wp_die` 423), and direct `DocumentService::store_incident_document()` calls (submission_locked_blk08) reject before inspecting form fields, narrative, or files.
  - Tenant identity semantics: updated `Schema.php` and migrated live table column `tenant_identity_id` to `bigint(20) unsigned DEFAULT NULL`, converting legacy `0` values to `NULL`.
  - Rate limiting & concurrency: documented sliding inactivity TTL transient behavior (300s window reset on each hit) and concurrency characteristics under standard `wp_options` storage.
  - Supporting evidence upload: strict ownership/capability verification with tenant/unauthorized streaming denial (HTTP 403) and fail-closed extension validation.
  - Audit Log Data Minimization: verified zero unencrypted narrative descriptions or tenant PII in audit logs via explicit test assertions (`P811-6.3`, `P811-6.4`); documented that transients store only an integer counter by structural code design, and REST endpoints fail-closed before reading payloads in locked mode.
  - QA Count Reconciliation: resolved historical arithmetic discrepancy ($421 - 393 = 28$). Previous physical execution was 393 assertions; two new unique assertions added to Phase 8.1.1 (`P811-1.5c` and `P811-1.6b`, bringing it from 40 to 42); current physical execution is exactly 395 assertions (100% PASS across 10 suites); cumulative catalog is 719 assertions (714 PASS, 5 NOT TESTED).
- **Testing**: 42/42 Phase 8.1.1 assertions PASS; 71/71 Phase 8.1 assertions PASS; 282 historical regression assertions PASS; total 395/395 physical executed assertions PASS (100%). Cumulative platform catalog: 719 assertions (714 PASS, 5 NOT TESTED for live SMTP).

---

#### Phase 8.2 — Administrative Incident Review Workflow & Security Reconciliation
- **Objective**: Implement administrative review queue, report detail inspection, evidence examination, and moderation decision state transitions (Approve, Reject, Request Clarification) with controlled reason codes, atomic concurrency locking, self-review prevention, durable history tracking, and BLK-08 fail-closed production gating.
- **Status**: **COMPLETED** (2026-11-01; Reconciled 2026-11-02)
- **Delivered**:
  - `IncidentReviewService`, `IncidentReviewController`, `IncidentReviewRestController` REST API.
  - Dedicated reviewer capabilities: `ft_review_incidents` (queue/detail view) and `ft_decide_incidents` (decision mutation).
  - Admin portal moderation screen under `fraud-tenant-incidents` with status filter tabs (`submitted`, `approved`, `rejected`, `needs_clarification`, `all`), keyword search, and responsive cards/tables.
  - **Self-Review Policy Reconciliation**: Enforced strict self-review separation. Reviewers who submitted a report can inspect report details and stream attached evidence documents, but are strictly prohibited from deciding the outcome (`self_review_forbidden`, audited with `incident_self_review_blocked`). Admin UI displays an explicit "Self-Decision Blocked" warning and suppresses decision action controls.
  - **Legal-Sensitive Reason Code Governance**: Replaced unsupported statutory reason code `statute_exceeded` with internal-policy reason code `policy_window_exceeded` (`Alleged incident occurred outside platform reporting time window (internal policy)`). Automated statutory determinations fail closed (`statutory_determination_unsupported`) pending qualified U.S. legal counsel review. Blockers BLK-03A through BLK-03F preserved as unresolved.
  - **Audit Log Privacy & Accountability Reconciliation**: Application-level append-only audit logging. Existing audit records were preserved during browser QA fixture cleanup. This does not prevent privileged database administrators from directly modifying database records. Corrected inaccurate "Zero-PII Audit Logging" claims. Explicitly documented that actor user IDs (`user_id`, `reviewer_id`) are intentionally retained for accountability and provenance, while free-text incident narratives (`factual_description`), SSNs, and applicant identity data are strictly excluded from audit details.
  - **Database Migration & Transaction Integrity**: Incremented `Constants::DB_VERSION` from `1.3.0` to `1.4.0` in `Constants.php` to guarantee automatic, idempotent schema migration across both existing and fresh installations. Confirmed all 14 custom tables utilize the `InnoDB` storage engine supporting ACID transactions. Verified transaction rollback on history INSERT failure via synthetic failure injection and distinguished sequential stale-state checks (`invalid_status_transition`) from atomic conditional database update guards (`WHERE id = %d AND status = 'submitted'`).
  - Durable review history recorded in `wp_ft_report_reviews` table.
  - Strict zero-disclosure guarantee: `disclosable = 0` permanently preserved across all state transitions; `tenant_identity_id` remains NULL.
  - BLK-08 Production Privacy Gate: review workflow immediately fails closed (`incident_review_locked_blk08`, HTTP 423) in production.
  - Evidence document streaming authorization and protected path confinement.
- **Testing**: 61/61 Phase 8.2 functional assertions PASS; 30/30 Phase 8.2 reconciliation assertions PASS; cumulative platform catalog: 810 assertions (805 PASS, 5 NOT TESTED). 5 Headless Chrome visual screenshots captured.

---

#### Phase 8.3 — Tenant Dispute Workflow
- **Objective**: Implement tenant dispute filing, statement capture, counter-evidence document upload, and administrative dispute resolution under internal platform safeguards, neutral review policies, strict parent report invariance, and BLK-08 fail-closed protection.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - **Capabilities & Access Control**: Registered granular capabilities `Capabilities::SUBMIT_TENANT_DISPUTE` (`ft_submit_tenant_dispute`), `Capabilities::VIEW_OWN_DISPUTES` (`ft_view_own_disputes`), `Capabilities::REVIEW_TENANT_DISPUTES` (`ft_review_tenant_disputes`), and `Capabilities::RESOLVE_TENANT_DISPUTES` (`ft_resolve_tenant_disputes`). Mapped to roles (`ft_tenant` has submit/view own; `administrator` has review/resolve/manage) and synchronized via `RoleManager::register_roles()`.
  - **Core Business Logic (`DisputeService.php`)**:
    - Pre-condition eligibility checks (`can_submit_dispute()`): strictly verifies caller is authenticated tenant, report is approved, report is associated with tenant, association is visible, and no active pending/under-review dispute already exists for this report/tenant.
    - Factual submission handling (`submit_dispute()`): captures tenant statement (minimum 10 characters), standard dispute reason (`inaccurate_record`, `incorrect_amount`, `identity_mistake`, `already_paid`, `unauthorized_report`, `other`), generates formatted reference `DSP-%06d`, saves record to `wp_ft_tenant_disputes`, and records data-minimized audit entry.
    - Anti-duplicate guard: enforces single active dispute per report/tenant; subsequent attempts while status is `pending` or `under_review` fail closed with `duplicate_active_dispute`.
    - Administrative review queue & details (`get_admin_disputes_queue()`, `get_dispute_detail()`): retrieves disputes with filter tabs (`all`, `pending`, `under_review`, `resolved`, `rejected`), search, pagination, attached incident report summary, tenant profile, and attached counter-evidence documents.
    - Dispute status transitions (`review_dispute()`, `resolve_dispute()`): transitions `pending` -> `under_review` -> `resolved` / `rejected`. Formal resolution requires determination action (`resolve_upheld`, `resolve_rejected`, `resolve_corrected`) and mandatory determination notes (minimum 5 characters).
    - In-app notification queue (`add_notification()`): delivers real-time notifications to the disputing tenant upon administrative determination.
  - **Protected Document Management (`DocumentService.php`)**:
    - Added `store_dispute_document()` uploading evidence to protected directory `fraud-tenant-protected/dispute_evidence/{user_id}/`.
    - Protected streaming controller `stream_protected_document()` extended for `dispute_evidence`: enforces BLK-08 fail-closed gate, permits disputing tenant owner and authorized reviewers, blocks landlords/owners and unrelated tenants (anti-IDOR), and revokes access if tenant is disassociated.
  - **Administrative UI (`DisputeAdminController.php`)**:
    - Admin portal submenu "Tenant Disputes" (`fraud-tenant-disputes`) under `Capabilities::REVIEW_TENANT_DISPUTES`.
    - Complete review queue with status tabs, search by dispute reference/report/address/tenant, and item breakdown.
    - Detail view with tenant dispute statement, counter-evidence document download links, associated report context, tenant profile, "Mark Under Review" action, and resolution determination form with mandatory notes.
  - **Tenant Dashboard Integration (`page-tenant-dashboard.php` & `DashboardService.php`)**:
    - Interactive "My Disputes" panel displaying submitted disputes, reference `DSP-%06d`, related incident reference, reason, submitted date, status badges, and detail inspection modal.
    - Interactive Dispute Submission Modal (`#ftDisputeSubmitModal-{report_id}`) accessible directly from "My Rental Records" table with required neutral platform review notice ("Neutral Review Notice: Disputes are reviewed individually by an authorized administrator..."), reason selection, statement input, and counter-evidence file uploader.
    - Interactive Dispute Detail Modal (`#ftDisputeDetailModal-{dispute_id}`) displaying full statement, attached evidence links, and administrator determination notes once resolved.
  - **REST API Endpoints (`DisputeRestController.php`)**:
    - Registered 7 REST endpoints under `fraud-tenant/v1` namespace with strict `permission_callback` checks:
      - `POST /tenant/disputes`: Submit tenant dispute with optional file attachment.
      - `GET /tenant/disputes`: Retrieve tenant's own submitted disputes.
      - `GET /tenant/disputes/{id}`: Retrieve single dispute detail (owner-only, anti-IDOR).
      - `GET /admin/disputes`: Retrieve administrative dispute queue.
      - `GET /admin/disputes/{id}`: Retrieve dispute detail for admin review.
      - `POST /admin/disputes/{id}/review`: Mark dispute under active review.
      - `POST /admin/disputes/{id}/resolve`: Record formal dispute determination.
  - **Parent Report Invariance & Data Minimization**:
    - Parent incident report record, landlord attestation, approved status, and landlord evidence remain completely unaltered and invariant under disputes.
    - Append-only audit logging: actor user IDs recorded for forensic accountability; free-text tenant statements, dispute resolution notes, and PII strictly excluded from audit payloads.
    - Database integrity: All 12 canonical legacy dispute rows (`report_id = 0`) preserved untouched.
- **Testing**: 59/59 Phase 8.3 automated unit/integration assertions PASS (100%); 199/199 cumulative Phase 8 regression suite assertions PASS (100%); 22/22 live headed Google Chrome QA journeys PASS (100%) with 14 visual screenshots captured in `reports/phase8_3_browser_qa/`. Legacy dispute rows (12 rows with `report_id = 0`) preserved untouched. Cumulative platform catalog: 912 automated assertions (907 PASS, 5 NOT TESTED).

---

#### Phase 8.4 — Approved Report Visibility
- **Objective**: Implement controlled report visibility and permissible disclosure criteria for approved incident records across authorized platform interfaces, featuring administrative manual reconciliation, verified tenant association, non-destructive visibility revocation, full chronological audit history tracking, anti-IDOR isolation, and seamless tenant dashboard integration with Phase 8.3 dispute modal entry points.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - **Database Architecture & Migration**: Incremented `Constants::DB_VERSION` to `1.5.0` in `Constants.php`. Created Table 15 (`{$wpdb->prefix}ft_report_associations`) and Table 16 (`{$wpdb->prefix}ft_report_disclosure_history`) via `Schema.php` and executed schema migration via `MigrationManager::migrate(true)`. Total custom tables: **16**.
  - **Role & Capabilities Registry**: Registered `Capabilities::MANAGE_REPORT_VISIBILITY` (`ft_manage_report_visibility`) and `Capabilities::ASSOCIATE_TENANT_REPORTS` (`ft_associate_tenant_reports`) in `Capabilities.php`. Mapped to `get_admin_caps()` and synchronized to the `administrator` role via `RoleManager::register_roles()`.
  - **Core Service Layer (`ReportVisibilityService.php`)**: Built single-responsibility service encapsulating:
    - Queue querying with 6 filter tabs (`all`, `unassociated`, `associated`, `awaiting_visibility`, `tenant_visible`, `visibility_revoked`) and search by reference/address/tenant.
    - Tenant candidate search returning verified, approved tenant profiles.
    - Administrative tenant association (`associate_tenant()`) with reviewer ID, timestamp, and mandatory justification recording.
    - Association removal (`disassociate_tenant()`) with report `tenant_identity_id` reset to NULL when all active associations are removed.
    - Phase 8.2 Guarded Visibility Approval (`approve_visibility()`): strictly enforces prerequisite that incident report status must be `approved`. Attempts on `submitted`, `rejected`, or `needs_clarification` fail closed with `report_not_approved` (HTTP 409). Sets `disclosable = 1` and association `visibility_status = 'visible'`.
    - Non-Destructive Visibility Revocation (`revoke_visibility()`): resets `disclosable = 0` and association `visibility_status = 'revoked'` while preserving the underlying incident report and all attached evidence documents intact.
    - Chronological disclosure audit logging in `wp_ft_report_disclosure_history` and application audit log.
    - Fail-closed BLK-08 privacy gate integration via `IncidentService::is_test_mode_enabled()`.
  - **Document Streaming Authorization (`DocumentService.php`)**: Updated `stream_protected_document()` to authorize associated tenants to stream attached incident evidence documents when the report is approved and `disclosable = 1` via `ReportVisibilityService::is_tenant_associated_and_visible()`.
  - **Administrative UI (`ReportVisibilityController.php`)**: Admin interface registered under `fraud-tenant-visibility` featuring status breakdown tabs, search, item table with reference `INC-%06d`, and detail view with report summary, landlord attestation, attached evidence docs, tenant association selector, visibility approval/revocation action forms, and chronological audit history timeline.
  - **REST API Endpoints (`ReportVisibilityRestController.php`)**: Registered 8 REST routes under namespace `fraud-tenant/v1` with strict `permission_callback` enforcement for admin management and tenant viewing.
  - **Tenant Dashboard Integration (`page-tenant-dashboard.php`)**: Updated "My Rental Records" panel to display disclosable incident records with reference `INC-%06d`, property address, incident date, amount, and status badges. Integrated accessible detail modal displaying full factual narrative and nonced evidence file streaming links. Connected "Dispute This Record" button to `#ftDisputeModal` with FCRA rights notice as the natural entry point for Phase 8.3. Clean empty state rendered when 0 records are disclosable.
  - **Anti-IDOR & Cross-Tenant Isolation**: Enforced across service, REST API, and document streaming layers. Unrelated tenants receive 0 records and are denied access to unassociated or unapproved records.
- **Testing**: 43/43 automated unit and integration assertions PASS (100%); 61/61 Phase 8.2 regression assertions PASS (100%); 12/12 live headed Google Chrome QA journeys PASS (100%) with 13 visual screenshots captured in `reports/phase8_4_browser_qa/`. Legacy dispute rows (12 rows with `report_id = 0`) preserved untouched. Cumulative catalog: 853 automated assertions (848 PASS, 5 NOT TESTED).

#### Phase 8.4.1 — Post-Implementation Integrity Reconciliation
- **Objective**: Reconcile association cardinality, authoritative access invariants, granular administrative capabilities, transaction atomicity, and dispute notice neutrality following Phase 8.4.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - **Single-Active-Association Cardinality**:
    - Strictly enforced rule that at most ONE active tenant association may exist per incident report at any given time.
    - Transaction-safe concurrency checks with `SELECT ... FOR UPDATE` reject second association attempts with `report_already_associated` or `already_associated`.
    - Historical association records are preserved in `wp_ft_report_associations` upon disassociation (`status = 'disassociated'`, `visibility_status = 'revoked'`).
    - Reports can be cleanly disassociated and subsequently reassociated with different tenants with full chronological provenance.
    - Report's `tenant_identity_id` strictly matches the active association or resets to `NULL` upon disassociation.
    - Report's `disclosable` flag strictly resets to `0` upon disassociation or revocation.
  - **Authoritative Invariant for Tenant Access & Evidence Streaming**:
    - Tenant access to reports and attached evidence documents strictly requires:
      1. Tenant is the currently authorized active association account (`ra.tenant_user_id = %d AND ra.status = 'active'`).
      2. Association visibility status is `visible` (`ra.visibility_status = 'visible'`).
      3. Incident report status is `approved`.
      4. Incident report disclosure state is enabled (`r.disclosable = 1`).
      5. Security gate permits operation (`IncidentService::is_test_mode_enabled()`).
    - Removed all loose fallback clauses; enforced strict `INNER JOIN` on active, visible associations across dashboard queries, detail endpoints, REST controllers, and evidence streaming.
  - **Granular Capability Enforcement**:
    - `ft_associate_tenant_reports`: Grants permission specifically to associate and disassociate tenant reports.
    - `ft_manage_report_visibility`: Grants permission specifically to approve and revoke report visibility.
    - Enforced across `ReportVisibilityService`, `ReportVisibilityRestController`, and `AdminPortal`.
  - **Transaction & Rollback Integrity**:
    - Wrapped all mutation methods in explicit `START TRANSACTION` / `COMMIT` / `ROLLBACK` blocks with `try ... catch (\Throwable)`.
    - Failed history insertion triggers full database rollback, ensuring zero partial updates.
  - **Dispute Modal Neutral Notice (`page-tenant-dashboard.php`)**:
    - Removed unsupported statutory FCRA claims and 30-day timeline assertions.
    - Replaced with neutral administrative review notice:
      *"You may submit a dispute regarding factual information in this report through the platform's dispute process. Your statement and supporting evidence will be reviewed by an authorized administrator. Submitting a dispute does not automatically change the report or guarantee a particular outcome."*
- **Testing**:
  - 36/36 Phase 8.4.1 automated unit and integration assertions PASS (100%).
  - 43/43 Phase 8.4 regression assertions PASS (100%).
  - 61/61 Phase 8.2 regression assertions PASS (100%).
  - 14/14 live headed Google Chrome QA journeys PASS (100%) with 13 visual screenshots captured in `reports/phase8_4_1_browser_qa/`.
  - Cumulative catalog: 889 automated assertions (884 PASS, 5 NOT TESTED). Legacy dispute rows (12 rows with `report_id = 0`) preserved untouched.

---


#### Phase 8.3.1 — Dispute Ownership & Security Integrity Verification
- **Objective**: Target and resolve the four critical dispute ownership and security integrity areas identified in post-Phase 8.3 review: (1) dispute ownership and evidence streaming retention after tenant disassociation with strict parent incident narrative isolation, (2) formal definition and operational enforcement of `resolve_corrected` determination semantics distinguishing administrative resolutions from modifying incident reports, (3) genuine multi-process concurrent duplicate-dispute prevention via row-level locks and zero orphaned evidence files, and (4) strict Phase 8.1.1 BLK-08 fail-closed security gating across all dispute submission, review, resolution, and evidence streaming endpoints.
- **Status**: Completed (2026-11-02)
- **Key Deliverables**:
  - **Dispute Ownership & Evidence Access Post-Disassociation**:
    - Enhanced `DocumentService::stream_protected_document()` for `dispute_evidence`: strictly cross-verifies that document `object_id > 0`, dispute exists in `wp_ft_tenant_disputes`, `dispute->tenant_id === requesting_user_id`, and `doc->user_id === dispute->tenant_id`. Unlinked dispute documents strictly denied.
    - Enhanced `DisputeService::get_dispute_detail()`: When a disputant is no longer actively associated with the parent report, dispute details return isolated context containing only non-sensitive parent references (`reference: INC-XXXXXX`), while strictly withholding landlord factual description narrative, amount involved, and landlord evidence.
    - Anti-IDOR enforcement: Newly associated tenants (Tenant B) and unrelated bystanders are strictly denied access to Tenant A's dispute records (returns null) and dispute counter-evidence (HTTP 403).
    - Authorized dispute reviewers retain full evidence streaming access; property owners strictly denied counter-evidence streaming (HTTP 403).
  - **`resolve_corrected` Determination Semantics & Parent Invariance**:
    - Updated WP-Admin resolution UI dropdown label in `DisputeAdminController.php` to `Correction Acknowledged (Administrative Determination Recorded)`.
    - Added an administrative guidance notice box in `DisputeAdminController.php` explaining that resolving a dispute records an administrative resolution and does not modify or delete the landlord's original incident report narrative, claim amount, or attached evidence.
    - Standardized stored `resolution_notes` in `DisputeService::resolve_dispute()`: automatically prepends formal determination notices (`[Determination: Correction Acknowledged ...]`, `[Determination: Dispute Upheld ...]`, `[Determination: Dispute Dismissed ...]`).
    - Added neutral determination notice in tenant dashboard modal (`page-tenant-dashboard.php`): *"Notice: An administrator recorded an administrative determination. The original incident report remains unchanged by this dispute action."*
    - Verified 100% invariance of parent incident report across all fields: narrative description, amount involved, incident date, status (`approved`), and landlord evidence rows and files remain identical.
  - **Genuine Concurrent Duplicate-Dispute Protection**:
    - Wrapped `submit_dispute()` in explicit database transactions (`START TRANSACTION`, `COMMIT`, `ROLLBACK`).
    - Enforced pessimistic row-level locking (`SELECT ... FOR UPDATE`) on parent incident report, active association, and active disputes (`status IN ('pending', 'under_review')`).
    - Implemented automatic file cleanup: if transaction fails or rolls back, staged counter-evidence documents and files are deleted via `DocumentService::delete_document()`, guaranteeing zero orphaned files.
    - Validated via real multi-process concurrency test: two independent PHP subprocesses running simultaneously; exactly one dispute created, competing submission trapped with `duplicate_active_dispute`, database confirms exactly 1 row and 1 linked document.
  - **Exact BLK-08 Fail-Closed Security Gate Integration**:
    - Integrated `IncidentService::is_test_mode_enabled()` checks directly into `DisputeService::can_submit_dispute()`, `DisputeService::review_dispute()`, `DisputeService::resolve_dispute()`, `DocumentService::stream_protected_document()`, and `DisputeRestController.php` REST permission callbacks.
    - Evaluated across 10 isolated environments (local, dev, staging, production, missing flag, missing env, boolean false, integer 1, unwhitelisted env, filter hook spoofing). Direct constant evaluation immunizes against WordPress filter spoofing.
    - In production mode, all protected dispute operations fail closed immediately (`submission_locked_blk08`, `dispute_review_locked_blk08`, HTTP 423).
  - **Data Preservation & Cleanup Integrity**:
    - Preserved all 12 canonical legacy dispute rows (`report_id = 0`) untouched in `wp_ft_tenant_disputes`.
    - Synthetic test fixtures created and destroyed with zero pollution of production or persistent tables.
- **Testing**:
  - 49/49 Phase 8.3.1 automated unit and integration assertions PASS (100%).
  - 59/59 Phase 8.3 regression assertions PASS (100%).
  - 43/43 Phase 8.4 regression assertions PASS (100%).
  - 36/36 Phase 8.4.1 regression assertions PASS (100%).
  - 187/187 combined automated regression catalog assertions PASS (100%).
  - 16/16 live headed Google Chrome QA user journeys PASS (100%) with 10 visual screenshots captured in `reports/phase8_3_1_browser_qa/`.

---

### PHASE 9 — Membership Plans and Stripe Subscriptions
- **Objective**: Manage paid subscription plans, feature entitlements, and recurring Stripe billing.
- **Status**: **COMPLETED** (2026-11-03)

#### Phase 9.1 — Membership Data Layer, Entitlement Architecture & Admin Plan Management
- **Objective**: Build server-side membership data layer, authoritative entitlement architecture, Free property quota baseline, and administrator plan management controls.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - Implemented `MembershipService` in `includes/Services/MembershipService.php` as single authoritative domain service for plan lookups, active subscription validation, feature entitlement evaluation, and property quotas.
  - Formalized Free plan property quota in `features_json` as `max_active_properties: 1`, while Standard plans retain `unlimited_properties: true` (represented internally as `-1`).
  - Added composite index `user_id_status` on `(user_id, status)` in `wp_ft_subscriptions` and reconciled Free plan features via `MigrationManager.php`.
  - Implemented `MembershipAdminController.php` registered under `Fraud Tenant -> Membership Plans` with capability `ft_manage_membership_plans`, providing comprehensive plan table, immutable plan codes/pricing, Free plan deactivation lock, and local regex validation on Stripe IDs `^prod_[a-zA-Z0-9]+$` and `^price_[a-zA-Z0-9]+$`.
  - Refactored `DashboardService::get_owner_dashboard_data()` to query `MembershipService::get_owner_plan()`, eliminating redundant inline SQL while preserving response keys `membership_plan_name` and `membership_plan_code`.
  - Preserved tenant accounts as 100% free and exempt from any subscription requirements, payment checks, or upgrade prompts.
  - Strictly excluded Stripe API calls, webhooks, and premature quota blocking.
- **Testing**:
  - 25/25 dedicated Phase 9.1 automated assertions PASS (100%).
  - 16/16 isolated runtime closure assertions PASS (100%) across Test A (Admin Entitlement Separation), Test B (Deactivated Plan Subscription Lifecycle), and Test C (Operational Baseline Invariance).
  - 248/248 Phase 8 full catalog regression assertions PASS (100%).
  - 10/10 live headed Google Chrome QA user journeys PASS (100%) with 10 visual screenshots captured in `reports/phase9_1_browser_qa/`.
  - Synthetic QA fixtures cleanly removed; operational database verified 100% pristine (0 subscriptions, 3 plans, 12 disputes).

#### Phase 9.2 — Stripe API Integration, Hosted Checkout, Customer Management & Billing Portal
- **Objective**: Implement secure Stripe API HTTP client, map local plan codes to Stripe Price IDs, generate authenticated Stripe Checkout sessions for property owners, provide Stripe Billing Portal access, and handle checkout return states.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - Implemented `StripeService` (`wp_remote_request`, HTTPS only, bounded timeout, Bearer authorization, test/live key validation, server-side price verification gate, customer create/get/get_or_create with idempotency key, checkout session create/get, and customer portal session create).
  - Implemented `CheckoutService` (concurrency locks `ft_chk_lock_{user_id}`, open session reuse from user meta `_ft_pending_checkout_session`, stale session purging, return state verification, customer portal routing).
  - Implemented `MembershipCheckoutController` under `fraud-tenant/v1` registering `POST /owner/membership/checkout`, `POST /owner/membership/portal`, `GET /owner/membership/session-status` with REST cookie nonces and approved owner permission callbacks.
  - Enhanced `page-owner-dashboard.php` and `dashboard.js` with checkout return banners (`checkout_status=success` pending banner, `checkout_status=cancelled`), modal plan buttons with `data-plan-code`, accessible notice container `#ftCheckoutNotice`, and conditional Stripe Billing Portal button.
  - Verified checkout return displays pending message and strictly leaves plan as Free; subscription activation deferred to Phase 9.3 webhooks.
- **Testing**:
  - 47/47 dedicated automated assertions PASS in isolated disposable database environment (`scratch/test_phase9_2_stripe_checkout.php`).
  - 248/248 Phase 8 regression catalog assertions PASS (`scratch/run_full_catalog_248.php`).
  - 25/25 Phase 9.1 membership foundation assertions PASS (`scratch/test_phase9_1_membership_foundation.php`).
  - 16/16 Phase 9.1 isolated runtime closure assertions PASS (`scratch/test_phase9_1_runtime_closure.php`).
  - 336/336 combined automated test assertions PASS (100%).
  - 11/11 live headed Google Chrome QA user journeys PASS (100%) with 11 visual screenshots captured in `reports/phase9_2_browser_qa/`.
  - Operational database baseline verified 100% pristine and invariant (0 subscriptions, 3 plans, 12 disputes).

#### Secure Stripe Admin Configuration — Encrypted Stripe Credentials & Admin Settings Portal
- **Objective**: Build a secure Stripe Credentials Management interface inside the WordPress Admin Dashboard under `Fraud Tenant -> Settings -> Stripe`.
- **Status**: **COMPLETED** (2026-11-02)
- **Delivered**:
  - `StripeCredentialService.php`: Encrypts credentials using AES-256-GCM authenticated encryption with a fresh 12-byte IV per encryption and 16-byte auth tag. Derives cryptographic keys dynamically via domain-separated HKDF-SHA256 from WordPress secret salts (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`).
  - Stored ciphertext in `wp_options` under option `ft_stripe_credentials` with autoload disabled (`autoload = 'no'`). Plaintext credentials are never stored, logged, or returned to clients.
  - Strict key prefix & environment validation: test mode requires `sk_test_`, `rk_test_`, `pk_test_`, `whsec_` and rejects live keys; production requires live keys and HTTPS (`is_ssl()`).
  - Blank-field secret preservation: submitting empty inputs preserves existing stored credentials without blank overwrites.
  - Dedicated confirmed removal action (`remove_credentials()`).
  - `StripeSettingsAdminController.php` & `AdminPortal.php`: Registered `fraud-tenant-settings` submenu under `fraud-tenant-admin`. Administrator-only access control (`manage_options`, `ft_manage_platform_settings`); owners and tenants receive HTTP 403.
  - UI matching Fraud Tenant design system: Environment Policy Card, Credentials Form with blank-on-load secret inputs, Connection Test Card, Membership Plan Stripe Mappings Table, and Danger Zone Card.
  - `StripeService.php`: Updated `get_secret_key()` and `get_publishable_key()` to resolve from `StripeCredentialService` first; added `get_webhook_secret()`; added `test_connection()` for server-side read-only GET `/v1/balance` inquiry with bounded timeout.
- **Testing**:
  - 19/19 dedicated automated security assertions PASS in isolated disposable database (`scratch/test_secure_stripe_admin_config.php`).
  - 47/47 Phase 9.2 checkout regression assertions PASS (`scratch/test_phase9_2_stripe_checkout.php`).
  - 9/9 live headed Google Chrome QA user journeys PASS with 9 visual screenshots saved in `reports/phase9/stripe_settings_qa/`.
  - Operational database `fraud_tenant` verified 100% intact (0 subscriptions, 3 plans, 1,690 audit records).

#### Phase 9.2.1 Stage B — Checkout Concurrency & Atomic Database Locking
- **Objective**: Implement atomic database locking in MySQL to eliminate checkout race conditions and double-billing.
- **Status**: **COMPLETED** (2026-11-03)
- **Delivered**:
  - Created table `wp_ft_checkout_locks` with unique index on `owner_id`.
  - Implemented `CheckoutLockService` managing atomic acquisition, 120s TTL, logical attempt ID issuance, and stale lock takeover.
  - Rejects concurrent checkout requests with HTTP 429 and `retry_after: 120`.
- **Testing**:
  - 8/8 dedicated unit assertions PASS (`scratch/test_checkout_concurrency.php`).

#### Phase 9.3 — Stripe Webhook Handling, Event Processing & Subscription Lifecycle Synchronization
- **Objective**: Implement Stripe webhook receiver with cryptographic signature verification, event logging (`wp_ft_payment_events`), idempotency controls, and subscription state synchronization.
- **Status**: **COMPLETED** (2026-11-03)
- **Delivered**:
  - Registered REST endpoint `/fraud-tenant/v1/stripe/webhook` with public permission callback.
  - Implemented constant-time HMAC-SHA256 signature verification matching mode dynamically from payload `livemode` flag.
  - Enforced 300s timestamp tolerance against replay attacks.
  - Idempotent event processing in `wp_ft_payment_events` with unique `event_id` constraint and state machine transitions (`received` -> `processing` -> `processed`).
  - Handled lifecycle events: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_succeeded`, `invoice.payment_failed`.
  - Enqueued lifecycle notifications in `wp_ft_notifications`.
- **Testing**:
  - 17/17 dedicated webhook assertions PASS (`scratch/test_stripe_webhooks.php`).

#### Phase 9.4 — Owner Feature Entitlement Enforcement & Quota Enforcement Integration
- **Objective**: Integrate `MembershipService` entitlement checks and property quotas directly into property creation, incident report submission, and screening workflows.
- **Status**: **COMPLETED** (2026-11-03)
- **Delivered**:
  - `PropertyService`: Enforced property quotas (Free: max 1 active property; Standard: unlimited properties).
  - `IncidentService`: Gated incident report submissions by plan feature privilege (`can_submit_incidents`).
- **Testing**:
  - Regression verified in `scratch/test_phase8_1_incident_submission.php` (71/71 PASS).

#### Phase 9.5 — End-to-End Subscription Lifecycle Verification, Renewal/Cancellation Testing & Security Hardening
- **Objective**: End-to-end integration testing of checkout, renewal, cancellation, downgrade, and past-due handling with live browser verification.
- **Status**: **COMPLETED** (2026-11-03)
- **Delivered**:
  - Comprehensive lifecycle regression covering activation, renewal, and cancellation states.
  - Downgrade enforcement safely reverting accounts to Free quotas without orphaned records.
  - Tenant account exemption preserved 100%.

#### Controlling Addendum Deliverables (Workstreams 1, 5, 6, 8)
- **Workstream 1 — Dual-Mode Stripe Implementation**:
  - Dual independent modes (Test Mode and Live Mode) switchable in admin.
  - Isolated AES-256-GCM encrypted options (`ft_stripe_credentials_test` and `ft_stripe_credentials_live`) with HKDF-SHA256 key derivation.
  - Separate Stripe Customer IDs in user meta (`_ft_stripe_customer_id_test` vs `_ft_stripe_customer_id_live`).
  - Expanded `wp_ft_membership_plans` schema to version `1.7.0` with test & live Product/Price IDs.
- **Workstream 5 — User Profile & Account Settings**:
  - Enhanced `DashboardService::update_safe_profile()` for personal and entity data.
  - Password mutation requiring current password check via `wp_check_password()`, minimum 8 characters, and confirmation check.
  - Email mutation requiring current password verification, syntax validation, duplicate rejection, and masking utilities.
  - 4-tab modal interface in owner and tenant dashboards. Tested: 21/21 PASS (`scratch/test_profile_and_account_settings.php`).
- **Workstream 6 — Custom SaaS Enterprise Admin Dashboard**:
  - Built comprehensive 15-section command center in `AdminDashboardController.php`.
  - Overview & MRR, Users, Account Approval, Owner Verification, Property Verification, Property Directory, Screening Requests, Incident Review, Tenant Disputes, Report Visibility, Subscriptions & MRR, Membership Plans, Stripe Gateway, Compliance Audit Logs, Platform Settings.
  - Backward compatibility preserved for direct applicant reviews (`action=review`). Tested: 18/18 PASS (`scratch/test_admin_dashboard.php`).
- **Workstream 8 — Master Visual Quality Assurance**:
  - 9 high-resolution Headless Chrome screenshots captured across Desktop (1440x900), Tablet (768x1024), and Mobile (390x844).
  - Master platform regression: 232/232 assertions PASS (100%). Total catalog assertions: 1,209.

---

#### Mandatory Workflow Compliance Verification (2026-11-03)
- **Objective**: Strictly align and verify platform user journeys against the authoritative workflow diagram: Registration role selection, 6-digit email OTP verification, pending administrative approval queue, and 6-digit password reset OTP pipeline.
- **Status**: **COMPLETED** (2026-11-03)
- **Delivered**:
  - 6-digit password reset OTP generation, dispatch, verification, and single-use transient reset token pipeline (`OtpService.php`, `AuthService.php`, `AuthHandler.php`).
  - Generic anti-enumeration response for password reset requests on non-existent accounts.
  - Updated theme templates: `page-forgot-password.php`, `page-reset-password.php` (2-step OTP -> New Password), `page-login.php`.
  - Server-side role routing strictly enforced (Administrator -> `/wp-admin/admin.php?page=fraud-tenant-admin`, Owner -> `/owner-dashboard/`, Tenant -> `/tenant-dashboard/`).
- **Testing**:
  - Automated unit/integration test suite in isolated disposable database: 49/49 assertions PASS (`scratch/test_workflow_compliance.php`).
  - Headed visible Google Chrome QA automation across desktop and mobile: 20/20 screenshots PASS (`reports/workflow_compliance_qa/`).
  - Master platform regression verified: 232/232 assertions PASS (`scratch/run_master_regression.php`).

### PHASE 10 — Notifications, Email Workflows and Audit Trail
- **Objective**: Centralized notification delivery and append-only application-level audit logging.
- **Status**: Planned

---

### PHASE 11 — Integration, Security, Accessibility and Production Readiness
- **Objective**: Comprehensive end-to-end verification, security audits, accessibility checks, and launch readiness sign-off.
- **Status**: Planned
