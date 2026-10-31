# FRAUD TENANT — Task Tracker

## Task Status Legend
- `[x]` DONE
- `[/]` IN PROGRESS
- `[ ]` TODO
- `[!]` BLOCKED

---

## Phase 0: Repository Audit and Implementation Preparation
- [x] **Task 0.1**: Inspect directory, WordPress installation, and Git status. *(Completed: WordPress 7.1.3 on PHP 8.2.12 detected; Git initialized; `.gitignore` created)*
- [x] **Task 0.2**: Identify active theme, installed plugins, custom code, and database schema. *(Completed: Twenty Twenty-Five active, 0 plugins, standard 12 WP core tables)*
- [x] **Task 0.3**: Determine local development URL and verify browser testing tools. *(Completed: `http://localhost/fraudtenant` returns HTTP 200; Headless Chrome verified)*
- [x] **Task 0.4**: Review approved design reference and authentication workflow diagrams. *(Completed: `Fraud Tenant Product UI Showcase.png` and flowcharts cataloged)*
- [x] **Task 0.5**: Identify reusable assets, conflicts, and configuration requirements. *(Completed: Standard WP functions, clean environment, no conflicting plugins)*
- [x] **Task 0.6**: Formulate system architecture and technical boundaries. *(Completed: Documented in `AGENTS.md` and `IMPLEMENTATION_PLAN.md`)*
- [x] **Task 0.7**: Confirm exclusion of secrets and environment config from Git. *(Completed: `wp-config.php`, logs, and uploads excluded in `.gitignore`)*
- [x] **Task 0.8**: Create project documentation suite (`AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, `CHANGELOG.md`). *(Completed)*
- [x] **Task 0.9**: Produce Phase 0 Audit Report and await user review. *(Completed)*

---

## Phase 1: Custom Plugin Foundation and Database Architecture
- [x] **Task 1.1**: Initialize `fraud-tenant-core` plugin structure (`wp-content/plugins/fraud-tenant-core/`). *(Completed: Modular PSR-4 compatible structure with Autoloader)*
- [x] **Task 1.2**: Implement plugin activation and deactivation hooks. *(Completed: `Activator` runs migrations/roles/audit; `Deactivator` non-destructively flushes rewrites)*
- [x] **Task 1.3**: Build database migration manager using dynamic `$wpdb->prefix`. *(Completed: `MigrationManager` with `dbDelta` version checking `fraud_tenant_db_version` = `1.0.0`)*
- [x] **Task 1.4**: Define schema for 14 custom application tables with indexes and foreign keys. *(Completed: 14 tables verified in MySQL, zero plaintext SSN/OTP)*
- [x] **Task 1.5**: Register custom roles (`ft_owner`, `ft_tenant`) and custom capabilities. *(Completed: Registered on activation; admin gets management capabilities; owners/tenants denied admin portal)*
- [x] **Task 1.6**: Establish REST API namespace `fraud-tenant/v1` and base controller. *(Completed: `BaseController`, safe `DiagnosticController` at `/status`, permission callbacks)*
- [x] **Task 1.7**: Implement secure error logging service without leaking PII or secrets. *(Completed: `AuditLogger` with `Sanitizer::redact_sensitive_data`)*
- [x] **Task 1.8**: Test activation, table creation, role verification, and migration idempotency. *(Completed: 10 automated and CLI/HTTP tests executed, all PASS)*

---

## Phase 2: Custom Theme, Design System and Public Website
- [x] **Task 2.1**: Initialize `fraud-tenant-theme` (`wp-content/themes/fraud-tenant-theme/`). *(Completed: Complete theme files, style.css, functions.php, header, footer, templates)*
- [x] **Task 2.2**: Implement core CSS/JS assets matching approved design system (`#14263E`, `#0E9488`, `#F2F5F9`). *(Completed: CSS variables, components, typography, accessible mobile drawer and FAQ accordions in main.js)*
- [x] **Task 2.3**: Build reusable header, navigation bar, and footer components. *(Completed: Semantic header with SVG shield, responsive navigation, and comprehensive 4-column footer)*
- [x] **Task 2.4**: Implement Home/Landing page matching approved showcase layout. *(Completed: Hero banner, floating status cards, 4-step workflow, trust metrics, audience split cards)*
- [x] **Task 2.5**: Implement public content pages (How It Works, For Property Owners, For Tenants, Pricing, FAQ). *(Completed: 9 published WordPress pages with full PropTech content)*
- [x] **Task 2.6**: Implement Contact & Enquiry form with server-side validation and anti-spam. *(Completed: CSRF nonce, honeypot trap, rate limiting, and SSN detection privacy rejection)*
- [x] **Task 2.7**: Implement Privacy Policy, Terms of Service, and Tenant Rights disclosure drafts. *(Completed: Comprehensive legal drafts emphasizing FCRA readiness and tenant rights)*
- [x] **Task 2.8**: Browser testing across desktop, tablet, and mobile breakpoints. *(Completed: Headless Chrome screenshots captured at 1440px, 768px, and 375px)*
- [x] **Task 2.9**: UI alignment with approved design showcase (`Fraud Tenant Product UI Showcase.png`). *(Completed: Hero photographic showcase with Illustrative Sample Preview watermark, organic curved wave, 4-step workflow, dual audience split cards with photos, 4-card value grid, and 3-column contact block)*
- [x] **Task 2.10**: Content audit & verification of all public-facing claims across 9 pages. *(Completed: Removed unsupported stats/percentages, labeled sample data, removed fake office address/phone, verified legal draft alerts)*
- [x] **Task 2.11**: Contact form migration to core plugin & multi-field SSN pattern detection. *(Completed: Moved to ContactEnquiryHandler in fraud-tenant-core; checks name, subject, and message; rejects pre-dispatch; spoof-resistant IP rate limiting)*
- [x] **Task 2.12**: Complete 5-viewport responsive QA (1440x900, 1024x768, 768x1024, 375x812, 320x700). *(Completed: Zero horizontal overflow; screenshots captured and verified; zero PHP/console errors)*

---

## Phase 3: Registration, Email OTP, Login and Password Recovery
- [x] **Task 3.1**: Build custom registration templates with Property Owner vs. Tenant selection. *(Completed: `page-register.php` with interactive role selector cards, SVGs, validation, CSRF nonces, and anti-enumeration handling)*
- [x] **Task 3.2**: Implement 6-digit email OTP generation, HMAC hashing, and expiration logic. *(Completed: `OtpService` in `fraud-tenant-core` using cryptographically secure RNG `random_int(100000, 999999)`, HMAC-SHA-256 via `SecurityService::hash_otp()`, 10-minute expiry, 60s cooldown, max 5 attempts)*
- [x] **Task 3.3**: Build 6-digit OTP verification screen with countdown cooldown timer and attempt limits. *(Completed: `page-verify-otp.php` with 6 auto-advancing numeric inputs, paste support, live countdown timer, and safe status transition to `pending_approval`)*
- [x] **Task 3.4**: Implement custom login page with role-based redirection. *(Completed: `page-login.php` with split card matching showcase, home visual, eye password toggle, IP rate limiting, and lifecycle-aware status routing)*
- [x] **Task 3.5**: Build forgot-password and reset-password flows with enumeration protection. *(Completed: `page-forgot-password.php` and `page-reset-password.php` using WordPress core password reset keys, constant-time validation, and audit logging)*
- [x] **Task 3.6**: Implement account status lifecycle (`email_unverified`, `pending_approval`, `approved`, `rejected`, `suspended`). *(Completed: Lifecycle enforced in `AuthService`, holding page `page-account-status.php` with 4-step progress indicator and account details)*
- [x] **Task 3.7**: Test full authentication cycle in browser. *(Completed: 30 automated integration test assertions PASS; Headless Chrome screenshots captured and verified across desktop and mobile)*
- [x] **Task 3.8**: Implement two distinct registration experiences for Property Owner and Tenant. *(Completed: Form A with primary property address & ownership doc; Form B with current residence & conditional rental history; interactive role tab switching and distinct form endpoints)*
- [x] **Task 3.9**: Implement secure property ownership document upload pipeline (`DocumentService`). *(Completed: Private storage in `fraud-tenant-protected/` with `.htaccess` `Deny from all`, `Require all denied`, index.php, web.config, MIME/extension/size validation, randomized filenames, and zero public URL exposure)*
- [x] **Task 3.10**: Update schema to version `1.1.0` and migrate new profile columns. *(Completed: Added `prev_address_line1`, `prev_city`, `prev_state`, `prev_postal_code`, `terms_accepted_at`, `privacy_accepted_at` via versioned `dbDelta()`)*
- [x] **Task 3.11**: Execute comprehensive 43-test suite covering distinct registration forms, validation rejections, document storage security, and OTP lifecycle. *(Completed: 43/43 assertions PASS)*
- [x] **Task 3.12**: Phase 3 Final Release-Gate Audit & Hardening. *(Completed: Refined Terms and Privacy wording to remove blanket FCRA claims; added FT_PROTECTED_STORAGE_DIR support; hardened OTP invalidation on resend; executed 32-assertion release-gate audit covering consent wording, OTP lifecycle, login throttling, status restrictions, role isolation, document access controls, password recovery, and public regression; 32/32 assertions PASS)*

---

## Phase 4: Administrative User Review and Approval Portal
- [x] **Task 4.1**: Build administrative portal shell and menu integration. *(Completed: Registered top-level Fraud Tenant menu with shield icon, capability guard ft_review_user_registrations, and User Applications submenu in `AdminPortal.php`)*
- [x] **Task 4.2**: Implement pending user review queue for Owners and Tenants. *(Completed: `UserReviewController::render_list()` with status tabs, count badges, role filter, search input, pagination, and empty states)*
- [x] **Task 4.3**: Build application detail view displaying submitted business and property proof. *(Completed: `UserReviewController::render_detail()` with two-column responsive layout, owner property details & deed card, tenant residence & history, and decision audit timeline)*
- [x] **Task 4.4**: Implement approve, reject (with recorded reason), request info, and suspend actions. *(Completed: `AdminReviewService.php` with server-side validation, minimum reason lengths, status transitions, and CSRF nonce verification)*
- [x] **Task 4.5**: Connect decision triggers to automated email notifications. *(Completed: Integrated `wp_mail()` dispatches for approvals, rejections, and information requests with graceful fallback)*
- [x] **Task 4.6**: Implement administrative decision audit logging. *(Completed: Logged `admin.application_approved`, `admin.application_rejected`, `admin.info_requested`, `admin.property_verified`, `admin.property_rejected`, `admin.document_viewed`, and `admin.document_access_denied` via `AuditLogger`)*
- [x] **Task 4.7**: Test approval/rejection workflows and authorization guards. *(Completed: 23 preflight assertions and 35 Phase 4 integration assertions PASS; Headless Chrome screenshots captured at 1440px and 375px; strict separation of account and property decisions verified)*

---

## Phase 5: Owner and Tenant Dashboards
- [x] **Task 5.1**: Build dedicated responsive dashboard application shell (isolated from marketing layout). *(Completed: Dedicated header top bar, Navy #14263E sidebar, single-line clean footer, separated from public marketing navigation)*
- [x] **Task 5.2**: Implement Property Owner Dashboard (Overview, Properties, Tenant Search, Reports, Membership, Notifications, Settings). *(Completed: `page-owner-dashboard.php` matching showcase UI with 4 metric cards, Search SSN box, My Properties table, Recent Searches, and Upgrade Membership card)*
- [x] **Task 5.3**: Implement Tenant Dashboard (Overview, Rental Records, Report Details, Disputes, Notifications, Security). *(Completed: `page-tenant-dashboard.php` matching showcase UI with 3 metric cards, Recent Activity timeline, Residential Information on file, Clear Records state, and Your Rights Matter FCRA card)*
- [x] **Task 5.4**: Wire dynamic database counts, real user metadata, and empty states. *(Completed: `DashboardService.php` queries real database records from properties, screening requests, incident reports, subscriptions, audit log, and profiles with zero mock data)*
- [x] **Task 5.5**: Enforce server-side role and capability verification on all dashboard routes. *(Completed: `DashboardService::enforce_access()` strictly redirects unauthenticated users to `/login/`, unverified to `/verify-otp/`, pending/info_requested to `/account-status/`, and enforces strict cross-role isolation)*
- [x] **Task 5.6**: Execute browser testing for both roles. *(Completed: 45 preflight assertions and 32 Phase 5 functional suite assertions PASS; Headless Chrome screenshots captured at 1440x900 and 375x812 for both Owner and Tenant dashboards)*
- [x] **Task 5.7**: Tenant record messaging hardening. *(Completed: Replaced all absolute clean record claims with neutral wording 'No Disclosable Incident Records Available' and 'No Disclosed Incidents'; added incomplete platform data disclaimer; enforced `status = 'approved' AND disclosable = 1` in `DashboardService.php`)*
- [x] **Task 5.8**: Address verification label corrections. *(Completed: Relabeled addresses to 'Current Address on File' and 'Previous Address Reported During Registration'; removed all misleading 'verified' address badges)*
- [x] **Task 5.9**: Owner dashboard SSN search preview hardening. *(Completed: Converted SSN search input to disabled and readonly with preview placeholder; changed button to direct modal opener; removed all input listeners and submission handlers from `dashboard.js`; verified zero collection, serialization, or screening request generation)*
- [x] **Task 5.10**: Secure tenant activity timeline. *(Completed: Refined audit query to filter by `object_type = 'user' AND object_id = %d` and an explicit allowlist of safe actions; mapped to human-readable labels; excluded all admin notes, client telemetry, and raw JSON; fallback to neutral empty state)*
- [x] **Task 5.11**: Owner data scoping and real membership verification. *(Completed: Verified strict `owner_id = %d` scoping on properties, searches, reports; ensured searches display only masked `••••-XXXX` last 4 digits; verified genuine plan data with zero fabricated benefits)*
- [x] **Task 5.12**: Admin review historical continuity verification. *(Completed: Verified that repeated rejection or info-request actions preserve an append-only application-level audit history in `wp_ft_audit_log`; enhanced admin review timeline display to cleanly highlight past notes and reasons)*
- [x] **Task 5.13**: Phase 5.1 Hardening & Release Gate Verification Suite. *(Completed: 36/36 Phase 5.1 assertions PASS; 24/24 Phase 4 & 5 regression assertions PASS; updated Headless Chrome screenshots at 1440px and 390px captured)*
- [x] **Task 5.14**: Phase 5.1.1 Final Release-Gate Reconciliation. *(Completed: Reconciled 36 Phase 5.1 assertions; verified WordPress core 7.1.3 runtime; executed 44 Phase 5.1.1 reconciliation assertions covering owner/tenant cross-user data isolation, dashboard lifecycle states, SSN preview end-to-end immunity, and tenant incident record association; updated audit log documentation to 'append-only application-level audit history'; 104/104 total assertions PASS)*

---

## Phase 6: Property Management and Secure Document Uploads
- [x] **Task 6.1**: Implement property CRUD interface and database handling for owners (`PropertyService.php`, `PropertyHandler.php`). *(Completed: create, list, edit, soft archival, restoration, reverification on critical address change)*
- [x] **Task 6.2**: Build secure document upload pipeline with MIME, size, and extension validation (`PropertyService::upload_property_document()`). *(Completed: PDF, JPG, PNG <= 10MB, magic byte check, double extension defense, unguessable filenames)*
- [x] **Task 6.3**: Implement protected document storage with active barrier files (`DocumentService.php`). *(Completed: .htaccess, index.php, web.config, private directory isolation)*
- [x] **Task 6.4**: Create authorized download controller with record-level permission checks (`DocumentService::stream_protected_document()`). *(Completed: reviewer capability OR document owner validation, canonical path traversal defense, attachment disposition, no-store headers)*
- [x] **Task 6.5**: Implement IDOR prevention and document access audit logging (`AuditLogger`). *(Completed: blocked cross-owner access, blocked tenant access, logged owner_property_*, admin_property_*, document_access_denied)*
- [x] **Task 6.6**: Build dedicated administrative property verification queue and inspection screen (`PropertyReviewController.php`, `AdminReviewService.php`). *(Completed: status filter tabs, search, pagination, verify action, reject with >=10 char reason, request info, decoupled from user account approval)*
- [x] **Task 6.7**: Integrate multi-property views into owner dashboard (`page-owner-dashboard.php`, `dashboard.css`). *(Completed: clean tab routing ?ft_tab=properties, active vs archived subtabs, property edit view, functional + Add Property modal)*
- [x] **Task 6.8**: Execute Phase 6 test suite and regression tests. *(Completed: 45 Phase 6 assertions PASS, 24 Phase 4/5 regression assertions PASS, 44 Phase 5.1.1 reconciliation assertions PASS; total 113/113 PASS; 11 Headless Chrome visual screenshots across all required viewports captured)*

---

## Phase 7: Authorized Tenant Search Using SSN
- [ ] **Task 7.1**: Build tenant search UI with SSN input mask and permissible-purpose attestation.
- [ ] **Task 7.2**: Implement server-side keyed HMAC matching token generator.
- [ ] **Task 7.3**: Enforce strict no-plaintext-SSN storage and URL exclusion rules.
- [ ] **Task 7.4**: Implement tiered disclosure logic (Free tier neutral status vs. Paid tier verified details).
- [ ] **Task 7.5**: Add search rate limiting, access auditing, and error handling.
- [ ] **Task 7.6**: Test search workflows across free, paid, authorized, and unauthorized accounts.

---

## Phase 8: Incident Reporting, Evidence Review and Tenant Disputes
- [ ] **Task 8.1**: Build owner incident reporting submission form with evidence attachment.
- [ ] **Task 8.2**: Implement administrative review workflow for incident reports.
- [ ] **Task 8.3**: Build tenant notification and authorized report inspection interface.
- [ ] **Task 8.4**: Implement tenant dispute submission and evidence upload workflow.
- [ ] **Task 8.5**: Implement administrative dispute resolution and audit trail correction.
- [ ] **Task 8.6**: Test complete lifecycle from report submission to dispute resolution.

---

## Phase 9: Membership Plans and Stripe Subscriptions
- [!] **Task 9.1**: Configure Stripe API test credentials and webhook signing secret. *(Requires test keys)*
- [ ] **Task 9.2**: Implement admin membership plan manager (monthly/yearly tiers).
- [ ] **Task 9.3**: Implement Stripe Checkout integration for subscription creation.
- [ ] **Task 9.4**: Implement webhook controller with signature verification and idempotency checks.
- [ ] **Task 9.5**: Build server-side subscription validation enforcing feature access.
- [ ] **Task 9.6**: Test checkout, renewal, cancellation, and payment failure handling.

---

## Phase 10: Notifications, Email Workflows and Audit Trail
- [ ] **Task 10.1**: Implement unified notification service supporting in-app and `wp_mail()`.
- [ ] **Task 10.2**: Wire notification dispatchers for all critical lifecycle events.
- [ ] **Task 10.3**: Implement in-dashboard notification center with read/unread tracking.
- [ ] **Task 10.4**: Implement append-only application-level audit logging for security and compliance events.
- [ ] **Task 10.5**: Test notification delivery and audit trail completeness.

---

## Phase 11: Integration, Security, Accessibility and Production Readiness
- [ ] **Task 11.1**: Execute end-to-end user journeys for Owner, Tenant, and Admin.
- [ ] **Task 11.2**: Conduct security testing (SQLi, XSS, CSRF, IDOR, brute-force, data leakage).
- [ ] **Task 11.3**: Conduct accessibility audit (WCAG AA compliance, keyboard navigation).
- [ ] **Task 11.4**: Compile final QA report, legal launch gate review, and deployment guide.


## Phase 6.1: Final Security & QA Reconciliation
- [x] Document authorization: verify authenticated user ownership, property_id vs owner_id cross-verification <!-- id: FT-P6-SEC-01 -->
- [x] Bidirectional cross-owner document streaming denial <!-- id: FT-P6-SEC-02 -->
- [x] Tenant & anonymous document streaming denial <!-- id: FT-P6-SEC-03 -->
- [x] Single-action nonce enforcement on document endpoints <!-- id: FT-P6-SEC-04 -->
- [x] File path security: realpath() confinement, trailing directory separator boundary <!-- id: FT-P6-SEC-05 -->
- [x] Traversal, symlink escape, and sibling-prefix path defense <!-- id: FT-P6-SEC-06 -->
- [x] Zero filesystem path disclosure in errors/exceptions <!-- id: FT-P6-SEC-07 -->
- [x] Bidirectional Owner A/B property & dashboard isolation <!-- id: FT-P6-SEC-08 -->
- [x] Bidirectional Tenant A/B identity, notification, dispute & activity isolation <!-- id: FT-P6-SEC-09 -->
- [x] Document version history: replacement creates new record, prior files preserved <!-- id: FT-P6-SEC-10 -->
- [x] Property review updates latest document to approved and prior to superseded <!-- id: FT-P6-SEC-11 -->
- [x] Dedicated property review capabilities: ft_review_properties, ft_verify_properties <!-- id: FT-P6-SEC-12 -->
- [x] Compliance reverification: unit number change triggers reset to pending_verification <!-- id: FT-P6-SEC-13 -->
- [x] Production outside-webroot storage & Nginx configuration documented <!-- id: FT-P6-SEC-14 -->
- [x] Reconcile 113 Phase 6 test execution runs and 33 Phase 6.1 assertions <!-- id: FT-P6-SEC-15 -->
- [x] Maintain cumulative unique count: 500 total, 495 PASS, 5 NOT TESTED (SMTP) <!-- id: FT-P6-SEC-16 -->
- [x] Update documentation: QA_REPORT.md, BLOCKERS.md, SETUP.md, CHANGELOG.md, TASKS.md <!-- id: FT-P6-SEC-17 -->