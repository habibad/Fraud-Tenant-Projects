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
- [x] **Task 7.1**: Phase 7.1 & 7.1.1 — Authorized Property Owner Screening Request Foundation. *(Completed: `ScreeningService`, `ScreeningHandler`, `ScreeningController` REST API, owner dashboard screening request modal; identity-free foundation with zero SSN or applicant name collection; `ssn_last_four` strictly unwritten; action-specific, time-limited WordPress nonce validation combined with independent capability, account-status, and ownership authorization; unverified owner-provided attestation record v1.0; verified property requirement; fixed-window/sliding-TTL transient rate limiting; application-level append-only audit logging; initial status pending, disclosure level locked; 58/58 Phase 7.1 assertions PASS, 224/224 regression assertions PASS, total 282/282 PASS)*
- [ ] **Task 7.2**: Implement server-side keyed HMAC matching token generator & identity matching.
- [ ] **Task 7.3**: Enforce strict no-plaintext-SSN storage and URL exclusion rules.
- [ ] **Task 7.4**: Implement tiered disclosure logic (Free tier neutral status vs. Paid tier verified details).
- [ ] **Task 7.5**: Add search rate limiting, access auditing, and error handling.
- [ ] **Task 7.6**: Test search workflows across free, paid, authorized, and unauthorized accounts.

---

## Phase 8: Incident Reporting, Evidence Review and Tenant Disputes
- [x] **Task 8.1**: Phase 8.1 — Owner Incident Submission Foundation. *(Completed: `IncidentService`, `IncidentHandler`, `IncidentController` REST API, owner dashboard Incident Reports panel and submission modal; BLK-08 Sensitive PII Encryption Gate enforced fail-closed in production with synthetic test-mode gate `is_test_mode_enabled()`; strict verified active property requirement; approved owner authorization with capability `ft_submit_incident_report`; 7 approved categories: unpaid_rent, property_damage, late_rent, lease_violation, unauthorized_pet, noise_complaint, other_violation; safe reference generation `INC-%06d`; initial status `submitted` with `disclosable = 0`; supporting evidence upload via `DocumentService::store_incident_document()` with protected storage and streaming authorization; action-specific WordPress nonce validation combined with independent capability, account-status, and ownership authorization; rate limiting; application-level append-only audit logging `incident.report_submitted` with safe metadata and zero sensitive narrative or PII leakage; 71/71 Phase 8.1 assertions PASS, 282/282 regression assertions PASS, total 353/353 PASS; Headless Chrome desktop, tablet, and mobile visual screenshots captured)*
- [x] **Task 8.1.1**: Phase 8.1.1 — Security Reconciliation and Production Privacy Gate Verification. *(Completed: Eliminated WordPress filter spoofing vulnerability by removing all calls to `wp_get_environment_type()` in favor of direct PHP constant evaluation (`constant('WP_ENVIRONMENT_TYPE')`); strictly enforced two-condition gate requiring `FT_INCIDENT_TEST_MODE === true` AND direct constant `WP_ENVIRONMENT_TYPE` in whitelist `['local', 'development', 'staging']`; guaranteed immunity against parameter/cookie/capability/filter tampering; pre-processing fail-closed rejection across REST `permission_callback` (423), AJAX (423), Web POST (423), and direct `DocumentService::store_incident_document()` (submission_locked_blk08) before reading payload or files; verified deployment boundary: local `wp-config.php` untracked in Git (.gitignore line 9) explicitly defines `WP_ENVIRONMENT_TYPE = 'local'`, while shared/production fallback policy enforces fail-closed `'production'`; non-destructive schema migration on `tenant_identity_id` to `bigint(20) unsigned DEFAULT NULL` converting legacy `0` to `NULL`; documented sliding inactivity TTL rate-limiter semantics; narrowed Zero-PII claims with audit logs explicitly verified via assertions (`P811-6.3`, `P811-6.4`), transients integer-only by design, and locked REST endpoints fail-closed; reconciled arithmetic discrepancy ($421 - 393 = 28$); executed 42/42 Phase 8.1.1 assertions PASS, 395/395 executed assertions across all 10 suites PASS (100%))*
- [x] **Task 8.2**: Phase 8.2 — Administrative Incident Review Workflow & Final Security Reconciliation. *(Completed: Built `IncidentReviewService`, `IncidentReviewController`, `IncidentReviewRestController` REST API; registered admin submenu `fraud-tenant-incidents`; enforced strict self-review separation: self-decision strictly blocked with `self_review_forbidden` and audit log `incident_self_review_blocked`, while self-inspection and evidence streaming remain accessible for submitting owners; BLK-08 Sensitive PII Encryption Gate fail-closed in production with HTTP 423 `incident_review_locked_blk08`; controlled reason taxonomies for approval, rejection, and clarification; replaced legally sensitive `statute_exceeded` with internal-policy reason `policy_window_exceeded` and disabled unsupported statutory determinations (`statutory_determination_unsupported`) pending counsel review while preserving BLK-03A through BLK-03F; corrected inaccurate "Zero-PII" audit log claims, documenting that actor user IDs are retained for accountability while narratives, SSNs, and applicant identity data are strictly excluded; incremented DB version to `1.4.0` in `Constants.php` with idempotent schema migration for fresh and existing installations; verified all 14 tables use `InnoDB` engine with atomic transaction rollback; verified transaction rollback on history INSERT failure via synthetic failure injection; distinguished sequential stale-state tests (`invalid_status_transition`) from atomic conditional database update guards (`WHERE id = %d AND status = 'submitted'`); durable review history in `wp_ft_report_reviews`; disclosable remains strictly 0 and tenant_identity_id remains NULL; added capabilities `ft_review_incidents` and `ft_decide_incidents`; 61/61 Phase 8.2 functional assertions PASS, 30/30 reconciliation assertions PASS, total 91/91 Phase 8.2 assertions PASS; 5 headless Chrome visual screenshots captured. Tasks 8.3 and 8.4 remain planned and untouched)*
- [x] **Task 8.3**: Phase 8.3 — Tenant Dispute Workflow. *(Completed: Built `DisputeService`, `DisputeHandler`, `DisputeAdminController`, `DisputeRestController` REST API; registered admin submenu `fraud-tenant-disputes` ("Tenant Disputes"); registered capabilities `ft_submit_tenant_dispute`, `ft_view_own_disputes`, `ft_review_tenant_disputes`, and `ft_resolve_tenant_disputes`; enforced pre-condition eligibility requiring authenticated tenant, active association, visible status, approved report status, and no active pending/under-review dispute; captured tenant statement (>=10 chars), standardized reason taxonomy, formatted reference `DSP-%06d`, and persisted to `wp_ft_tenant_disputes`; implemented anti-duplicate dispute guard rejecting concurrent disputes with `duplicate_active_dispute`; extended `DocumentService::store_dispute_document()` to secure counter-evidence in `fraud-tenant-protected/dispute_evidence/{user_id}/` and updated streaming controller with BLK-08 fail-closed gate, dispute owner authorization, administrative reviewer access, and landlord/unrelated tenant denial; built admin moderation queue with status tabs (`all`, `pending`, `under_review`, `resolved`, `rejected`), search, pagination, detailed inspection view with incident report context and evidence links, "Mark Under Review" action, and resolution determination form with mandatory notes (>=5 chars) supporting `resolve_upheld`, `resolve_rejected`, `resolve_corrected`; integrated tenant dashboard "My Disputes" panel and accessible `#ftDisputeSubmitModal-{report_id}` with neutral platform review notice ("Neutral Review Notice: Disputes are reviewed individually by an authorized administrator...") and detail modal `#ftDisputeDetailModal-{dispute_id}`; in-app notifications on determination; registered 7 REST API routes under `fraud-tenant/v1`; guaranteed parent incident report invariance (report status, landlord attestation, evidence unaltered); append-only audit logging with data minimization (actor user IDs retained, free-text statements and notes excluded); preserved all 12 legacy dispute rows untouched; 59/59 Phase 8.3 automated assertions PASS, 199/199 regression assertions PASS across Phases 8.2, 8.4, 8.4.1, and 8.3, 22/22 live headed Google Chrome QA journeys PASS with 14 screenshots saved in `reports/phase8_3_browser_qa/`)*
- [x] **Task 8.4**: Phase 8.4 — Approved Report Visibility. *(Completed: Built `ReportVisibilityService`, `ReportVisibilityController`, `ReportVisibilityRestController` REST API; registered admin submenu `fraud-tenant-visibility`; incremented `Constants::DB_VERSION` to `1.5.0` with migration for Table 15 `wp_ft_report_associations` and Table 16 `wp_ft_report_disclosure_history`; registered capabilities `ft_manage_report_visibility` and `ft_associate_tenant_reports`; administrative reconciliation queue with 6 status filter tabs and search; administrative tenant association with reviewer, timestamp, and justification logging; Phase 8.2 guard enforcing visibility approval strictly for status `approved`; non-destructive visibility revocation setting `disclosable = 0` while preserving report and evidence; streaming authorization in `DocumentService` for associated tenants on disclosable reports; tenant dashboard "My Rental Records" rendering `INC-%06d`, property details, amount, detail modal with full description and nonced evidence links, and dispute entry point modal; cross-tenant anti-IDOR isolation; 43/43 automated assertions PASS, 61/61 Phase 8.2 regression assertions PASS, 12/12 live headed Google Chrome QA journeys PASS with 13 screenshots; 12 legacy dispute rows preserved untouched)*
- [x] **Task 8.4.1**: Phase 8.4.1 — Association, Disclosure & Security Integrity Reconciliation. *(Completed: Enforced single-active-association model with transaction-safe checks and row lock FOR UPDATE; rejected second active association with `report_already_associated` / `already_associated`; preserved historical association rows upon disassociation with status `disassociated` and visibility `revoked`; enabled clean disassociation and reassociation with different tenants; enforced strict authoritative invariant for tenant access requiring active association, visible status, approved report status, disclosable=1, and BLK-08 test mode gate across dashboard, detail, REST, and streaming; corrected tenant dashboard dispute modal notice to neutral platform review statement removing all statutory FCRA claims; enforced granular capabilities `ft_associate_tenant_reports` and `ft_manage_report_visibility` across services, REST API, and admin portal; verified transaction atomicity and rollback integrity on history insert failure; 36/36 Phase 8.4.1 unit/integration assertions PASS, 43/43 Phase 8.4 regression assertions PASS, 61/61 Phase 8.2 regression assertions PASS, 14/14 live headed Google Chrome QA journeys PASS with 13 screenshots saved in `reports/phase8_4_1_browser_qa/`; fixtures cleaned up; Phase 8.3 remains pending and untouched)*
- [x] **Task 8.3.1**: Phase 8.3.1 — Dispute Ownership & Security Integrity Verification. *(Completed: Formally verified dispute ownership and evidence access retention post-disassociation, ensuring disassociated Tenant A retains own dispute record, statement, and counter-evidence streaming while strictly isolating parent landlord narrative, claim amount, and landlord evidence; enforced anti-IDOR denying Tenant B and bystanders access to Tenant A's dispute/evidence; clarified and enforced resolve_corrected determination semantics distinguishing administrative resolutions from incident report alterations via updated admin dropdown label "Correction Acknowledged (Administrative Determination Recorded)", guidance notice box, standardized stored resolution notices, and neutral tenant dashboard notice; verified 100% invariance of parent incident report and landlord evidence across all determinations; implemented genuine multi-process concurrent duplicate-dispute prevention with explicit database transactions, row-level locks FOR UPDATE, and zero orphaned evidence files; enforced exact Phase 8.1.1 BLK-08 fail-closed gate via direct PHP constant evaluation across 10 isolated environments with all protected operations failing closed in production; preserved all 12 canonical legacy dispute rows untouched; executed 49/49 Phase 8.3.1 automated assertions PASS, 187/187 full regression assertions PASS across Phases 8.3, 8.4, and 8.4.1, 16/16 live headed Google Chrome QA journeys PASS with 10 visual screenshots saved in `reports/phase8_3_1_browser_qa/`; synthetic fixtures cleanly removed)*

---

## Phase 9: Membership Plans and Stripe Subscriptions
- [x] **Task 9.1**: Phase 9.1 — Membership Data Layer, Entitlement Architecture & Admin Plan Management. *(Completed: Implemented `MembershipService` in `includes/Services/MembershipService.php` as single authoritative source for plan resolution, active subscription validation, feature entitlement evaluation, and property quota calculations; formalized Free plan property quota in `features_json` as `max_active_properties: 1`, while Standard plans retain `unlimited_properties: true` represented as `-1`; reconciled `wp_ft_membership_plans` and added composite index `user_id_status` on `(user_id, status)` in `wp_ft_subscriptions` via `MigrationManager.php`; implemented `MembershipAdminController.php` registered under `Fraud Tenant -> Membership Plans` with capability `ft_manage_membership_plans`, providing comprehensive plan table, immutable plan codes/pricing, Free plan deactivation lock, and local regex validation on Stripe IDs `^prod_[a-zA-Z0-9]+$` and `^price_[a-zA-Z0-9]+$`; refactored `DashboardService::get_owner_dashboard_data()` to query `MembershipService::get_owner_plan()`; preserved tenant accounts as 100% free and exempt; strictly excluded Stripe API calls, webhooks, and premature quota blocking; executed 25/25 dedicated Phase 9.1 automated assertions PASS, 248/248 Phase 8 regression catalog assertions PASS, 10/10 live headed Google Chrome QA journeys PASS with 10 screenshots in `reports/phase9_1_browser_qa/`; completed isolated runtime test closure via `scratch/test_phase9_1_runtime_closure.php` with 16/16 PASS across Test A [Admin Entitlement Separation], Test B [Deactivated Plan Subscription Lifecycle], and Test C [Operational Baseline Invariance]; operational database preserved with zero mutation; stopping before Phase 9.2)*
- [x] **Task 9.2**: Phase 9.2 — Stripe API Integration, Hosted Checkout, Customer Management & Billing Portal. *(Completed: Implemented `StripeService.php` in `FraudTenant\Services` for secure API communication with `wp_remote_request`, HTTPS only, bounded 15s timeout, Bearer authorization, API version pinning, test vs live secret key validation, server-side price verification against DB plan, customer create/get/get_or_create with idempotency key, and checkout/portal session generation; implemented `CheckoutService.php` managing concurrency locks `ft_chk_lock_{user_id}`, open session reuse from user meta `_ft_pending_checkout_session`, stale session purging, return state verification, and customer portal initiation; implemented `MembershipCheckoutController.php` under `fraud-tenant/v1` registering `POST /owner/membership/checkout`, `POST /owner/membership/portal`, `GET /owner/membership/session-status` with REST cookie nonces and approved owner permission callbacks; updated `page-owner-dashboard.php` and `dashboard.js` with checkout triggers, customer portal button, and pending activation banners; verified return to `/owner-dashboard/?checkout_status=success` strictly displays pending message and does NOT activate Standard membership; executed 47/47 dedicated automated assertions PASS in isolated disposable database environment; executed 289 baseline regression catalog assertions PASS; executed 11/11 live headed Google Chrome QA user journeys PASS across desktop 1280x800 and mobile 390x844 viewports with 11 screenshots saved in `reports/phase9_2_browser_qa/`; verified operational database `fraud_tenant` baseline 100% invariant; stopping before Phase 9.3)*
- [x] **Task 9.2.1**: Stage B Checkout Concurrency & Atomic Database Locking. *(Completed: Implemented `CheckoutLockService.php` backed by new database table `wp_ft_checkout_locks`; atomic lock acquisition with 120s TTL and logical attempt ID; rejects double-click race conditions with HTTP 429 and `retry_after: 120`; stale expired locks reclaimed atomically; 8/8 assertions PASS)*
- [x] **Task 9.2.2**: Secure Stripe Admin Configuration & Encrypted Key Storage. *(Completed: Implemented `StripeCredentialService.php` using AES-256-GCM authenticated encryption and domain-separated HKDF-SHA256 key derivation from genuine WordPress secret salts; stored only ciphertext in dedicated WordPress option `ft_stripe_credentials` with autoload disabled; enforced environment policies and key prefixes; implemented blank-field secret preservation and dedicated removal; created `StripeSettingsAdminController.php` under `Fraud Tenant -> Settings -> Stripe` with administrator-only access control, secret masking on page load, read-only GET /v1/balance connection testing, and real-time membership plan Stripe mappings table; updated `StripeService.php` to resolve credentials securely; verified 19/19 security assertions PASS in isolated disposable database; verified 47/47 existing Phase 9.2 tests PASS; completed 9/9 headed Chrome QA journeys with screenshots; verified operational database 100% intact).*
- [x] **Task 9.3**: Phase 9.3 — Stripe Webhook Handling, Event Processing & Subscription Lifecycle Synchronization. *(Completed: Built `StripeWebhookService.php` and `StripeWebhookController.php` registering REST endpoint `/fraud-tenant/v1/stripe/webhook`; constant-time HMAC-SHA256 signature verification matching mode via `livemode` payload field; 300s timestamp tolerance against replay attacks; idempotent event processing in `wp_ft_payment_events`; handled `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, and invoice events; synchronized subscription status in `wp_ft_subscriptions`; enqueued in-app lifecycle notifications; 17/17 assertions PASS)*
- [x] **Task 9.4**: Phase 9.4 — Owner Feature Entitlement Enforcement & Property Quota Enforcement Integration. *(Completed: Integrated active membership enforcement across services; `PropertyService` strictly restricts Free tier to 1 active property and grants unlimited properties to Standard tiers; `IncidentService` restricts incident reporting to Standard tiers with active subscriptions; regression tests verify clean enforcement without mock data)*
- [x] **Task 9.5**: Phase 9.5 — End-to-End Subscription Lifecycle Verification, Renewal/Cancellation Testing & Security Hardening. *(Completed: Full automated regression pass across all subscription states: active, canceled, past_due; verified downgrades revert entitlements safely; verified tenant accounts remain 100% exempt from payment flows)*

---

## Controlling Addendum & Master Production Readiness Deliverables
- [x] **Addendum Task 1**: Dual Stripe Mode Support (Test Mode and Live Mode). *(Completed: Switchable modes via admin; isolated encrypted credentials in `ft_stripe_credentials_test` and `ft_stripe_credentials_live`; livemode field in subscriptions/events; isolated Stripe Customer IDs by mode; local QA safety restriction; 12/12 assertions PASS)*
- [x] **Addendum Task 2**: User Profile & Account Settings (Workstream 5). *(Completed: `DashboardService::update_safe_profile()` with personal info, entity details, current-password-validated password changes, current-password-validated email updates with duplicate collision rejection, and `Sanitizer::mask_email()`; 4-tab responsive modal in owner and tenant dashboards; 21/21 assertions PASS)*
- [x] **Addendum Task 3**: Custom SaaS Enterprise Admin Dashboard (Workstream 6). *(Completed: Built 15-section command center in `AdminDashboardController.php` covering Overview & MRR, Users, Account Approval, Owner Verification, Property Verification, Property Directory, Screening Requests, Incident Review, Disputes, Report Visibility, Subscriptions & MRR, Membership Plans, Stripe Gateway, Compliance Audit Logs, Platform Settings; backward-compatible applicant review routing; 18/18 assertions PASS)*
- [x] **Addendum Task 4**: Master Visual Quality Assurance Matrix (Workstream 8). *(Completed: Captured 9 full-page Headless Chrome screenshots across Desktop 1440x900, Tablet 768x1024, and Mobile 390x844 viewports covering Admin Dashboard, Stripe Settings, Owner Profile Modal, Tenant Profile Modal; 9/9 PASS)*
- [x] **Addendum Task 5**: Master Completion & Production Readiness Documentation (Workstream 9). *(Completed: Authored authoritative report `MASTER_COMPLETION_AND_PRODUCTION_READINESS_REPORT.md` and user-facing artifact; updated all project documentation suite files)*

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

## Phase 6.1.1: Final Capability Enforcement Verification
- [x] REVIEW_PROPERTIES vs VERIFY_PROPERTIES least-privilege separation <!-- id: FT-P6-CAP-01 -->
- [x] Reviewer with REVIEW_PROPERTIES cannot verify property <!-- id: FT-P6-CAP-02 -->
- [x] Unauthorized verification attempts return denial and audit admin_property_verify_denied <!-- id: FT-P6-CAP-03 -->
- [x] Authorized verifier with VERIFY_PROPERTIES can verify property <!-- id: FT-P6-CAP-04 -->
- [x] Administrator (manage_options) retains full review and verification capabilities <!-- id: FT-P6-CAP-05 -->
- [x] Property document streaming requires property-specific capabilities <!-- id: FT-P6-CAP-06 -->
- [x] Registration reviewer (REVIEW_USER_REGISTRATIONS) cannot stream property documents <!-- id: FT-P6-CAP-07 -->
- [x] Property reviewer (REVIEW_PROPERTIES) can stream property documents <!-- id: FT-P6-CAP-08 -->
- [x] Nonce CSRF protection documented as time-windowed token, not consumable single-use <!-- id: FT-P6-CAP-09 -->
- [x] Independent server-side authorization enforced on all document and property requests <!-- id: FT-P6-CAP-10 -->
- [x] Execute Phase 6.1.1 test suite (13 assertions PASS) <!-- id: FT-P6-CAP-11 -->
- [x] Update QA_REPORT.md, BLOCKERS.md, CHANGELOG.md, TASKS.md <!-- id: FT-P6-CAP-12 -->

---

## Mandatory Workflow Compliance Verification
- [x] **Task WF.1**: Registration role selection mandatory (Property Owner vs. Tenant) with distinct profile fields <!-- id: FT-WF-01 -->
- [x] **Task WF.2**: Account created in `email_unverified` state upon registration submission <!-- id: FT-WF-02 -->
- [x] **Task WF.3**: 6-digit email OTP generation and dispatch with HMAC verification and 10m expiry <!-- id: FT-WF-03 -->
- [x] **Task WF.4**: Invalid OTP rejected with remaining attempts counter reported <!-- id: FT-WF-04 -->
- [x] **Task WF.5**: Single-use OTP consumption prevents replay attacks <!-- id: FT-WF-05 -->
- [x] **Task WF.6**: 60-second resend cooldown timer and rate limiting enforced <!-- id: FT-WF-06 -->
- [x] **Task WF.7**: Transition to `pending_approval` holding state `/account-status/` upon email verification <!-- id: FT-WF-07 -->
- [x] **Task WF.8**: Holding state prevents dashboard access while pending administrative review <!-- id: FT-WF-08 -->
- [x] **Task WF.9**: Admin review portal approves or rejects registration applications <!-- id: FT-WF-09 -->
- [x] **Task WF.10**: Approval enables account login and dispatches approval email <!-- id: FT-WF-10 -->
- [x] **Task WF.11**: Rejection prevents dashboard access with recorded reason and dispatches notice <!-- id: FT-WF-11 -->
- [x] **Task WF.12**: Password reset OTP flow implemented: Forgot Password -> Request OTP -> Verify OTP -> Set New Password -> Login <!-- id: FT-WF-12 -->
- [x] **Task WF.13**: Generic anti-enumeration response for non-existent reset email requests <!-- id: FT-WF-13 -->
- [x] **Task WF.14**: Single-use 15-minute transient reset token issued upon valid reset OTP verification <!-- id: FT-WF-14 -->
- [x] **Task WF.15**: Password updated via `wp_set_password()` and transient token consumed <!-- id: FT-WF-15 -->
- [x] **Task WF.16**: Server-side role resolution routes Admin, Owner, and Tenant to respective dashboards <!-- id: FT-WF-16 -->
- [x] **Task WF.17**: Cross-role access strictly blocked across all dashboards <!-- id: FT-WF-17 -->
- [x] **Task WF.18**: Execute automated 49-assertion workflow compliance test suite in disposable database <!-- id: FT-WF-18 -->
- [x] **Task WF.19**: Execute headed visible Google Chrome on desktop to capture 20 visual compliance journey screenshots <!-- id: FT-WF-19 -->
- [x] **Task WF.20**: Execute master regression suite (232/232 assertions PASS, zero regressions) <!-- id: FT-WF-20 -->
- [x] **Task WF.21**: Update project documentation and compile verification report <!-- id: FT-WF-21 -->

## 2026-11-04 — Claude completion pass
- [x] 9.2.1 Stage B atomic lock + idempotency (verified with independent processes)
- [x] 9.3 webhooks (verified with genuine Stripe CLI delivery on QA)
- [x] 9.4 membership enforcement (service + REST)
- [x] Secure reset flow; verified email change; password-change session handling
- [x] Admin Command Center fixes
- [ ] Live Stripe verification (needs Live keys)
- [ ] Renewal test via Stripe test clocks
- [ ] Dashboard visual consolidation & copy cleanup
- [ ] AuditLogger IP source; relocate WPvivid archive
- [ ] Update stale legacy assertions (schema version, immediate email change, fabricated webhook IDs)
