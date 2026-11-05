# FRAUD TENANT — Changelog

All notable changes to the Fraud Tenant platform will be documented in this file, structured phase by phase.

## [1.8.0] — Mandatory Workflow Compliance: 6-Digit Password Reset OTP, Role Selection & 20 Journey Verification (2026-11-03)

### Summary of Delivery
Implemented and comprehensively verified the authoritative user journey workflow for the Fraud Tenant platform across all 20 lifecycle journeys. Standardized the password recovery architecture from legacy core email links to a 6-digit numeric OTP verification pipeline matching the authoritative workflow diagram: **Forgot Password → Request Reset OTP → Verify Reset OTP → Set New Password → Login Page**. Executed automated integration testing in an isolated disposable QA database (49/49 assertions PASS), ran visual verification with real headed Google Chrome on desktop capturing 20 pixel-perfect screenshots across desktop and mobile viewports (20/20 PASS), and verified complete platform regression safety across 8 test suites (232/232 assertions PASS).

### Key Architectural & Implementation Deliverables
- **Purpose-Aware OTP Architecture (`OtpService.php`)**:
  - Enhanced `OtpService::dispatch_email()` with purpose-aware email templating for `password_reset` vs `registration`.
  - Registration email: "Your Fraud Tenant Verification Code" with email-verification instructions.
  - Password reset email: "Fraud Tenant — Password Reset Code" with security notices and 10-minute expiry guidance.
  - Strict secret isolation, HMAC-SHA256 candidate verification, and 60-second cooldown enforcement across all purposes.
- **Two-Step Password Reset Pipeline (`AuthService.php`, `AuthHandler.php`)**:
  - Implemented `AuthService::request_password_reset()`: Generates 6-digit numeric OTP, enforces IP and email rate limiting, provides generic anti-enumeration response for non-existent accounts, and redirects user to `/reset-password/?email=...&step=otp`.
  - Implemented `AuthService::verify_password_reset_otp()`: Validates candidate 6-digit OTP against HMAC hash in `wp_ft_email_otps`, marks single-use OTP consumed, and issues a 64-character cryptographically secure reset token in a transient with a 15-minute TTL (`ft_pw_reset_token_{token}`).
  - Implemented `AuthService::complete_password_reset()`: Enforces minimum 8-character password complexity, password confirmation matching, updates password hash using `wp_set_password()`, destroys the transient token to prevent replay, deletes open reset OTPs for the user, logs `user.password_reset` audit event, and redirects to `/login/?pw_reset=success`.
  - Registered web post actions: `ft_verify_reset_otp`, `ft_resend_reset_otp`, and `ft_set_new_password` with CSRF nonce verification.
- **Workflow Compliance UI Upgrades (`fraud-tenant-theme`)**:
  - `page-forgot-password.php`: Updated form UI and messaging to request a 6-digit verification code with "Send Verification Code" CTA.
  - `page-reset-password.php`: Rewritten to support the 2-step flow:
    - Step 1 (`step=otp`): 6 individual auto-advancing `.otp-digit-input` boxes, paste handling, backspace navigation, remaining attempt count alerts, and 60-second cooldown resend countdown timer.
    - Step 2 (`step=new_password`): Secure password setting form with token validation, password strength requirements, and show/hide password toggles.
  - `page-login.php`: Added detection for `pw_reset=success` with green success banner: "Your password has been successfully updated! You can now log in." Server-side role routing preserves Administrator to `/wp-admin/admin.php?page=fraud-tenant-admin`, Property Owner to `/owner-dashboard/`, and Tenant to `/tenant-dashboard/`.
- **Quality Assurance & Verification**:
  - Created automated workflow verification test suite `scratch/test_workflow_compliance.php` running in an isolated disposable database: 49/49 assertions PASS.
  - Built headed Google Chrome QA automation script `scratch/run_headed_workflow_qa.js` using Chrome DevTools Protocol to capture 20 visual screenshots in visible browser mode on desktop.
  - Stored all 20 screenshots in `reports/workflow_compliance_qa/` and mirrored to the artifact directory.
  - Executed master regression suite `scratch/run_master_regression.php`: 232/232 assertions PASS across all 8 existing suites.
  - Grand total catalog test assertions increased from 1,209 to 1,278 (1,273 PASS, 0 FAIL, 5 NOT TESTED).

---

## [1.7.0] — Master Completion & Controlling Addendum: Dual Stripe Mode, Webhook Engine & SaaS Admin Portal (2026-11-03)

### Summary of Delivery
Executed the Controlling Addendum and Master Production Completion for the Fraud Tenant platform. Delivered complete dual-mode Stripe infrastructure (Test Mode and Live Mode) with isolated encrypted key storage, mode-aware customer and subscription tracking, atomic checkout concurrency locking in MySQL, production-grade webhook event processing, active membership feature enforcement (property quotas and incident reporting gates), full user profile and account security settings, a 15-section SaaS Enterprise Admin Dashboard, and full regression testing across all platform components with 1,209 cataloged assertions.

### Key Architectural & Implementation Deliverables
- **Dual-Mode Stripe Architecture (`StripeCredentialService.php`, `StripeService.php`, `StripeSettingsAdminController.php`)**:
  - Implemented two fully independent, switchable Stripe environments: **Test Mode (Sandbox)** and **Live Mode (Production)**.
  - Stored separate encrypted credentials using AES-256-GCM with HKDF-SHA256 key derivation from genuine WordPress salts in isolated options (`ft_stripe_credentials_test` and `ft_stripe_credentials_live`) with autoload strictly disabled.
  - Enforced strict key prefix validation per mode (`sk_test_`, `pk_test_`, `whsec_test_` vs `sk_live_`, `pk_live_`, `whsec_live_`).
  - Built active environment switcher (`test` vs `live`) with local QA protection: local/staging environments are restricted from live charges, and Live Mode strictly enforces HTTPS.
  - Implemented mode-isolated Stripe Customer tracking: `_ft_stripe_customer_id_test` and `_ft_stripe_customer_id_live` are isolated in user meta.
  - Expanded `wp_ft_membership_plans` schema to store independent Stripe IDs for both modes (`stripe_test_product_id`, `stripe_test_price_id`, `stripe_live_product_id`, `stripe_live_price_id`).
  - Added `livemode` boolean flag to `wp_ft_subscriptions` and `wp_ft_payment_events`.
- **Checkout Concurrency & Atomic Locking (`wp_ft_checkout_locks`, `CheckoutLockService.php`, `CheckoutService.php`)**:
  - Created custom database table `wp_ft_checkout_locks` with unique index on `owner_id`.
  - Implemented database-backed atomic lock acquisition with 120-second TTL and logical attempt ID.
  - Concurrent checkout requests reject double-click race conditions with HTTP 429 and `retry_after: 120`.
  - Stale expired locks (>120s) reclaimed atomically with ownership verification.
- **Stripe Webhook Processing Engine (`StripeWebhookService.php`, `StripeWebhookController.php`)**:
  - Registered REST endpoint `/fraud-tenant/v1/stripe/webhook` with public permission callback.
  - Dynamic mode resolution from webhook payload `livemode` field to match signing secret (`whsec_test_...` vs `whsec_live_...`).
  - Enforced constant-time HMAC-SHA256 signature verification and 300-second timestamp tolerance against replay attacks.
  - Idempotent event processing in `wp_ft_payment_events` with unique `event_id` constraint and status transitions (`received` -> `processing` -> `processed`).
  - Handled complete subscription lifecycle:
    - `checkout.session.completed`: creates/activates subscription in `wp_ft_subscriptions`, updates user plan, releases checkout locks.
    - `customer.subscription.updated`: synchronizes plan status, active periods, and cancellation state.
    - `customer.subscription.deleted`: sets status to `canceled` and downgrades owner to Free plan.
    - `invoice.payment_succeeded` & `invoice.payment_failed`: logs payment events and updates past-due states.
  - Mode-aware in-app notifications generated for activation and cancellation.
- **Membership & Feature Enforcement (`PropertyService.php`, `IncidentService.php`)**:
  - Property quota enforcement: Free tier restricted to 1 active property; Standard tier granted unlimited properties.
  - Incident reporting gating: Free tier restricted from submitting incident reports; Standard tier allowed.
- **User Profile & Account Settings (`DashboardService.php`, `Sanitizer.php`, `page-owner-dashboard.php`, `page-tenant-dashboard.php`)**:
  - Enhanced `DashboardService::update_safe_profile()` with IDOR prevention, nonce checking, and audit logging.
  - Personal & entity profile updates (first name, last name, phone, business name, registration, address).
  - Password mutation requiring current password verification via `wp_check_password()`, minimum 8 characters, and confirmation check.
  - Email mutation requiring current password verification, syntax validation, duplicate collision rejection, and masking utilities (`Sanitizer::mask_email()`).
  - Comprehensive 4-tab modal interface in `page-owner-dashboard.php` and `page-tenant-dashboard.php`.
- **Custom SaaS Enterprise Admin Dashboard (`AdminDashboardController.php`, `AdminPortal.php`)**:
  - Implemented `AdminDashboardController.php` with 15 complete sections: Overview & MRR, Users, Account Approval, Owner Verification, Property Verification, Property Directory, Screening Requests, Incident Review, Tenant Disputes, Report Visibility, Subscriptions & MRR, Membership Plans, Stripe Gateway, Compliance Audit Logs, Platform Settings.
  - Unified with `AdminPortal.php` while preserving backward compatibility for `action=review` direct applicant routing.
- **Verification & Testing**:
  - Database schema upgraded to version `1.7.0`.
  - Master regression suite executed: 232 / 232 assertions passed across all suites (100%).
  - Total catalog test assertions: 1,209 (1,204 PASS, 0 FAIL, 5 NOT TESTED).
  - 9 high-resolution Headless Chrome screenshots captured across desktop (1440x900), tablet (768x1024), and mobile (390x844) viewports.

---

## [Secure Stripe Admin Configuration] — Encrypted Stripe Credentials & Admin Settings Portal (2026-11-02)

### Summary of Delivery
Implemented a dedicated, production-grade Stripe Credentials Management interface in the WordPress Admin Dashboard under `Fraud Tenant -> Settings -> Stripe`. Securely encrypts sensitive Stripe API keys and secrets using AES-256-GCM authenticated encryption with a fresh 12-byte IV per encryption and a 16-byte authentication tag. Cryptographic keys are dynamically derived from existing genuine WordPress secret salts via domain-separated HKDF-SHA256. Plaintext credentials are never stored in the database, never returned to browser clients, and never output in logs or audit events.

### Key Architectural & Implementation Deliverables
- **Stripe Credential Service (`StripeCredentialService.php`)**:
  - Implemented authenticated encryption via AES-256-GCM.
  - Implemented domain-separated key derivation from WordPress salts via HKDF-SHA256 (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`).
  - Stored encrypted credentials in WordPress option `ft_stripe_credentials` with autoload strictly disabled (`autoload = 'no'`).
  - Implemented strict key prefix and environment validation: test mode requires `sk_test_`, `rk_test_`, `pk_test_`, `whsec_` and strictly rejects live keys; production requires live keys and HTTPS (`is_ssl()`).
  - Implemented blank-field preservation: submitting empty credential fields preserves existing stored secrets.
  - Implemented dedicated, confirmed credential removal action (`remove_credentials()`).
  - Sanitized status reporting: exposes configuration state and environment metadata without leaking keys or fragments.
- **Admin Dashboard Settings Controller (`StripeSettingsAdminController.php`, `AdminPortal.php`)**:
  - Registered `fraud-tenant-settings` submenu page under `fraud-tenant-admin`.
  - Enforced administrator-only access (`manage_options`, `ft_manage_platform_settings`). Owners and tenants receive HTTP 403.
  - Integrated POST mutation handlers with WordPress nonces: `admin_post_ft_save_stripe_settings`, `admin_post_ft_remove_stripe_settings`, `admin_post_ft_test_stripe_connection`.
  - Built responsive Admin UI matching Fraud Tenant design system: Environment Policy Card, Credentials Form with blank-on-load secret inputs, Connection Test Card, Membership Plan Stripe Mappings Table, and Danger Zone Card.
- **Stripe Service Integration (`StripeService.php`)**:
  - Updated `get_secret_key()` and `get_publishable_key()` to resolve from `StripeCredentialService` first, falling back to runtime constants/environment variables for existing headless test suites.
  - Added `get_webhook_secret()` for future Phase 9.3 webhook processing.
  - Added `test_connection()`: executes server-side read-only GET `/v1/balance` inquiry with bounded timeout and SSL certificate verification.
  - Added transient-gated mock response support for local QA testing.

### Verification & Testing
- **Automated Security Test Suite (`scratch/test_secure_stripe_admin_config.php`)**:
  - 19 / 19 Assertions PASS (100%) in an isolated disposable database (`fraud_tenant_sec_stripe_<uniq>`).
  - Verified admin save, owner/tenant 403 blocking, unauthorized rejection, nonce validation, DB ciphertext storage with disabled autoload, server-side decryption, auth tag tampering rejection, prefix/env rejection, blank-field preservation, removal, mocked connection test, and zero audit/HTML leaks.
  - Verified operational database remained 100% untouched.
- **Existing Phase 9.2 Test Suite Non-Regression (`scratch/test_phase9_2_stripe_checkout.php`)**:
  - 47 / 47 Assertions PASS (100%).
- **Live Chrome QA (`scratch/run_stripe_admin_live_chrome_qa.js`)**:
  - Executed 9 visible headed Google Chrome journeys and captured screenshots in `reports/phase9/stripe_settings_qa/`.

---

## [Phase 9.2] — Stripe API Integration, Hosted Checkout, Customer Management & Billing Portal (2026-11-02)

### Summary of Delivery
Implemented Phase 9.2 of Fraud Tenant, delivering the Stripe API communication layer, Stripe Customer management, hosted Checkout Session generation, duplicate checkout session protection, customer billing portal access, and checkout return verification. Built directly upon the Phase 9.1 membership foundation, enabling approved property owners to select active Standard Monthly or Standard Annual membership tiers and proceed securely to Stripe-hosted subscription checkout.

### Key Architectural & Implementation Deliverables
- **Stripe API Client Service (`StripeService.php`)**:
  - Implemented `StripeService` in `FraudTenant\Services` utilizing WordPress HTTP API (`wp_remote_request`) with HTTPS enforcement, 15-second bounded timeouts, Bearer authorization, and API version pinning (`2023-10-16`).
  - Implemented secret key retrieval and validation: fails closed on missing/empty keys (`stripe_config_missing`), enforces `sk_...` format (`stripe_invalid_key_format`), and strictly rejects live keys (`sk_live_...`) in local development environments.
  - Implemented server-side price verification gate (`validate_price_against_plan()`): retrieves live Stripe Price object and validates active status, amount match, currency match, billing interval match, interval count match, and product ID match before creating checkout sessions.
  - Implemented customer management (`create_customer()`, `get_customer()`, `get_or_create_customer()`): associates owner with single Stripe Customer stored in `_ft_stripe_customer_id` user meta with deterministic idempotency keys (`ft_cus_u{user_id}_{hash}`).
  - Implemented checkout session generation (`create_checkout_session()`, `get_checkout_session()`): passes server-resolved Price ID and client_reference_id.
  - Implemented Customer Portal session generation (`create_portal_session()`): generates Stripe-hosted billing portal URLs for owners with associated customer records.
- **Checkout Orchestration Service (`CheckoutService.php`)**:
  - Implemented `CheckoutService` in `FraudTenant\Services` managing the complete owner checkout lifecycle.
  - Implemented concurrency protection via transient locks (`ft_chk_lock_{user_id}`) preventing double-click race conditions.
  - Implemented open session reuse: checks user meta `_ft_pending_checkout_session`, queries Stripe API for session status, reuses active URLs, and purges expired sessions.
  - Implemented duplicate checkout prevention: active subscriptions or sessions pending webhook synchronization block new sessions with HTTP 409 Conflict.
  - Implemented return state verification (`verify_checkout_return()`): verifies owner ownership of session, returning safe status `pending_verification` without prematurely activating subscriptions.
  - Implemented Customer Portal routing (`initiate_customer_portal()`).
- **REST API Controller (`MembershipCheckoutController.php`, `RestServer.php`)**:
  - Registered controller in `RestServer.php` under namespace `fraud-tenant/v1`.
  - Registered endpoints: `POST /owner/membership/checkout`, `POST /owner/membership/portal`, `GET /owner/membership/session-status`.
  - Enforced REST cookie nonces (`wp_rest`), user authentication, approved owner verification (`MembershipService::is_eligible_owner`), and explicit 403 rejection of tenant and administrator accounts.
- **Dashboard Application & UI Integration (`page-owner-dashboard.php`, `dashboard.js`)**:
  - Added checkout return notice banners: green pending activation banner for `checkout_status=success` (`"Payment submitted. Membership activation is pending server-side payment and subscription verification."`) and amber warning banner for `checkout_status=cancelled`.
  - Enhanced `ftMembershipModal`: added plan data attributes (`data-plan-code`), client notice element (`#ftCheckoutNotice`), and conditional Stripe Billing Portal button (`.ft-portal-btn`).
  - Added AJAX click handlers in `dashboard.js` with loading state transitions and safe client error notices.
  - Confirmed that returning from checkout strictly leaves plan as Free; subscription activation is deferred to Phase 9.3 webhooks.
- **Strict Phase Boundary Adherence**:
  - Strictly no webhook handlers, cryptographic webhook signature verification, event persistence in `wp_ft_payment_events`, or automatic subscription activation were implemented (reserved for Phase 9.3).

### Verification & Testing
- **Dedicated Phase 9.2 Automated Test Suite (`scratch/test_phase9_2_stripe_checkout.php`)**:
  - Total assertions executed: **47 / 47 PASS (100%)** within an isolated disposable database (`fraud_tenant_p92_isolated_<uniq>`).
  - Operational database (`fraud_tenant`) remained 100% untouched throughout test execution.
- **Full Regression Catalog Execution**:
  - Full 5-Suite Regression (`scratch/run_full_catalog_248.php`): **248 / 248 PASS (100%)**.
  - Phase 9.1 Membership Foundation (`scratch/test_phase9_1_membership_foundation.php`): **25 / 25 PASS (100%)**.
  - Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`): **16 / 16 PASS (100%)**.
  - Total combined automated test assertions: **336 / 336 PASS (100%)**.
- **Live Google Chrome Headed Browser QA (`scratch/run_p92_live_chrome_qa.js`)**:
  - Executed 11 user journeys in visible Google Chrome across desktop (1280x800) and mobile (390x844) viewports: **11 / 11 PASS (100%)**.
  - Captured 11 visual screenshots in `reports/phase9_2_browser_qa/` and mirrored to brain artifacts.
- **Operational Database Integrity**:
  - Baseline verified 100% invariant (0 subscriptions, 3 plans, 12 disputes, 10 reports, 151 documents, 97 WP users).
- **Reports & Artifacts**:
  - Authored authoritative report: `reports/phase9/phase9_2_implementation_report.md`
  - Created user-facing artifact: `phase9_2_implementation_report.md`

---

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 9.1] — Membership Data Layer, Entitlement Architecture & Admin Plan Management (2026-11-02)

### Summary of Delivery
Implemented Phase 9.1 of Fraud Tenant, delivering the server-side membership data layer, authoritative entitlement architecture, Free property quota baseline, and administrator membership plan management portal. Established the groundwork for future Stripe Checkout (Phase 9.2) and webhook subscription lifecycle synchronization (Phase 9.3) while preserving all existing Phase 8 workflows, roles, and security boundaries.

### Key Architectural & Implementation Deliverables
- **Authoritative Entitlement Architecture (`MembershipService.php`)**:
  - Implemented `MembershipService` in `FraudTenant\Services` as the single authoritative domain service for plan lookups, active subscription validation, feature entitlement resolution, and property quotas.
  - Implemented `get_plan_by_code()`, `get_plan_by_id()`, `get_free_plan()`, and `get_plans()`.
  - Implemented `is_eligible_owner()`: strictly enforces user existence, `ft_owner` role, exclusion of tenants and administrators from automatic paid owner entitlements, and `approval_status === 'approved'`.
  - Implemented `get_active_subscription()` and `has_active_subscription()`: joins subscriptions and plans for `status = 'active'`, enforces non-empty future `current_period_end` (UTC datetime validation), honors `cancel_at_period_end = 1` until expiration, and fails closed to Free Plan on past-due, canceled, expired, or malformed records.
  - Implemented `get_owner_plan()`: resolves effective plan for property owners, failing closed to Free Plan.
  - Implemented `can_access_feature()`: safe boolean feature evaluator. Malformed JSON or unentitled statuses fail closed safely.
  - Implemented `get_property_limit()`: returns `-1` for unlimited properties (`unlimited_properties: true`), or the numeric quota for Free accounts (minimum 1). Corrupted or missing quotas fail safely to 1 (never -1).
  - Implemented `update_plan()`: administrative plan update handler enforcing immutability of plan codes, Free plan active lock, regex validation of Stripe IDs, and audit logging.
- **Database Schema Expansion & Reconciliation (`Schema.php`, `MigrationManager.php`)**:
  - Maintained schema version at `1.5.0` on InnoDB engine.
  - Added composite index `user_id_status` on `(user_id, status)` in `wp_ft_subscriptions` to optimize active subscription lookups.
  - Reconciled Free plan in `wp_ft_membership_plans`: updated `features_json` to include `"max_active_properties": 1`.
  - Updated `seed_default_plans()` baseline array with `'max_active_properties' => 1`.
- **Administrator Membership Plan Management (`MembershipAdminController.php`, `AdminPortal.php`)**:
  - Registered **Fraud Tenant → Membership Plans** submenu under existing capability `ft_manage_membership_plans` (`Capabilities::MANAGE_MEMBERSHIP_PLANS`).
  - Built comprehensive admin overview table displaying plan name, code, pricing/interval, status badge, property quota, feature flags, Stripe Product ID, Stripe Price ID, and Edit action.
  - Built secure Edit Plan form: plan codes, pricing, and intervals remain immutable; Free plan `is_active` toggle is strictly locked; Stripe IDs are strictly validated with local regex `^prod_[a-zA-Z0-9]+$` and `^price_[a-zA-Z0-9]+$`.
  - Registered POST action handler `admin_post_ft_admin_membership_plan_action` with nonce verification and audit logging via `AuditLogger::log( 'membership_plan_updated', ... )`.
- **Dashboard & User Role Integration (`DashboardService.php`, `page-tenant-dashboard.php`)**:
  - Refactored `DashboardService::get_owner_dashboard_data()` to query `MembershipService::get_owner_plan( $owner_id )`, eliminating redundant inline SQL while preserving response keys `membership_plan_name` and `membership_plan_code`.
  - Confirmed tenant accounts remain 100% free and exempt from any subscription requirements, payment checks, or upgrade prompts.
- **Strict Phase Boundary Adherence**:
  - Zero Stripe API calls, HTTP clients, customer creation, or webhook handlers were introduced in Phase 9.1 (reserved for Phase 9.2 & 9.3).
  - Actual property creation blocking and incident blocking deferred to Phase 9.4.

### Verification & Testing
- **Dedicated Phase 9.1 Automated Test Suite (`scratch/test_phase9_1_membership_foundation.php`)**:
  - Total assertions executed: **25 / 25 PASS (100%)**.
  - Covered: plan lookups (Free, Monthly, Yearly, missing), malformed JSON handling, Free vs Standard feature flags, tenant exemption, unapproved owner entitlement denial, subscription lifecycle states (future end, expired end, missing end, cancel at period end, past due, canceled), property quotas (Free = 1, Standard = -1, missing quota fail-safe = 1), cross-user data isolation, admin capability gates, plan code immutability, Free plan deactivation lock, Stripe ID format validation, and DashboardService compatibility.
- **Full Phase 8 Regression Catalog (`scratch/run_full_catalog_248.php`)**:
  - Phase 8.2 (Incident Review Workflow): **61 / 61 PASS**.
  - Phase 8.3 (Tenant Dispute Workflow): **59 / 59 PASS**.
  - Phase 8.4 (Report Visibility & Tenant Association): **43 / 43 PASS**.
  - Phase 8.4.1 (Integrity Reconciliation): **36 / 36 PASS**.
  - Phase 8.3.1 (Dispute Ownership & Gate Integrity): **49 / 49 PASS**.
  - Combined suite passing assertions: **248 / 248 PASS (100%)**.
- **Live Google Chrome Headed Browser Verification (`scratch/run_p91_live_chrome_qa.js`)**:
  - Connected to visible Google Chrome (`Chrome/156.0.8078.74`) via CDP: **10 / 10 Journeys PASS (100%)**.
  - Captured 10 screenshots (1280x800 desktop and 390x844 mobile viewports) in `reports/phase9_1_browser_qa/` and mirrored to artifacts directory.
- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---
## [Phase 8.3.1] — Dispute Ownership & Security Integrity Verification (2026-11-02)

### Summary of Delivery
Executed a targeted security audit, integrity verification, and comprehensive testing suite for Phase 8.3.1. Verified and enforced the four core integrity areas: (1) dispute ownership and counter-evidence access retention post-disassociation alongside strict parent incident narrative isolation, (2) precise legal and operational semantics for `resolve_corrected` determinations distinguishing administrative resolutions from incident report alterations, (3) genuine multi-process concurrent duplicate-dispute prevention via row-level locks and zero orphaned evidence files, and (4) strict Phase 8.1.1 BLK-08 fail-closed security gating across all dispute submission, review, resolution, and evidence streaming endpoints.

### Key Architectural & Implementation Deliverables
- **Dispute Ownership & Evidence Access After Disassociation (`DocumentService.php`, `DisputeService.php`)**:
  - Enhanced `DocumentService::stream_protected_document()` for `dispute_evidence`: strictly cross-verifies that document `object_id > 0`, row exists in `wp_ft_tenant_disputes`, `dispute->tenant_id === requesting_user_id`, and `doc->user_id === dispute->tenant_id`. Unlinked dispute documents strictly rejected.
  - Enhanced `DisputeService::get_dispute_detail()`: When a disputant is no longer actively associated with the parent report, dispute details return isolated context containing only non-sensitive parent references (`reference: INC-XXXXXX`), while strictly withholding landlord factual description narrative, amount involved, and landlord evidence.
  - Enforced anti-IDOR: Unrelated tenants and newly associated tenants (Tenant B) are strictly denied access to Tenant A's dispute records (returns null) and dispute counter-evidence (HTTP 403).
  - Authorized dispute reviewers retain full evidence streaming access; property owners strictly denied counter-evidence streaming (HTTP 403).
- **`resolve_corrected` Determination Semantics & Parent Report Invariance (`DisputeService.php`, `DisputeAdminController.php`, `page-tenant-dashboard.php`)**:
  - Updated WP-Admin resolution UI dropdown label in `DisputeAdminController.php` to `Correction Acknowledged (Administrative Determination Recorded)`.
  - Added an administrative guidance notice box in `DisputeAdminController.php` explaining that resolving a dispute records an administrative resolution and does not modify or delete the landlord's original incident report narrative, claim amount, or attached evidence.
  - Standardized stored `resolution_notes` in `DisputeService::resolve_dispute()`: automatically prepends formal determination notices (`[Determination: Correction Acknowledged ...]`, `[Determination: Dispute Upheld ...]`, `[Determination: Dispute Dismissed ...]`).
  - Added neutral determination notice in tenant dashboard modal (`page-tenant-dashboard.php`): *"Notice: An administrator recorded an administrative determination. The original incident report remains unchanged by this dispute action."*
  - Verified 100% invariance of parent incident report across all fields: narrative description, amount involved, incident date, status (`approved`), and landlord evidence rows and files remain identical.
- **Genuine Concurrent Duplicate-Dispute Protection (`DisputeService.php`)**:
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

### Verification & Testing
- **Targeted Integrity Test Suite (`scratch/test_phase8_3_1_integrity.php`)**:
  - Total assertions executed: **49 / 49 PASS (100%)**.
  - Verification A (Ownership & Isolation): 10 / 10 PASS.
  - Verification B (`resolve_corrected` & Invariance): 10 / 10 PASS.
  - Verification C (Genuine Concurrency & Zero Orphans): 4 / 4 PASS.
  - Verification D (BLK-08 Fail-Closed Gate): 13 / 13 PASS.
  - Data Preservation: 3 / 3 PASS.
- **Full Automated Regression Catalog**:
  - Phase 8.3 Dispute Suite (`scratch/test_phase8_3_dispute.php`): **59 / 59 PASS (100%)**.
  - Phase 8.4 Report Visibility Suite (`scratch/test_phase8_4_report_visibility.php`): **43 / 43 PASS (100%)**.
  - Phase 8.4.1 Integrity Suite (`scratch/test_phase8_4_1_integrity.php`): **36 / 36 PASS (100%)**.
  - Combined suite passing assertions: **187 / 187 PASS (100%)**.
- **Live Google Chrome Verification (`scratch/run_p83_1_live_chrome_qa.js`)**:
  - Executed visible headed Google Chrome test across 8 real user journeys (16 assertions): **16 / 16 PASS (100%)**.
  - Captured 10 full-resolution screenshots in `reports/phase8_3_1_browser_qa/`.


- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.3] — Tenant Dispute Workflow (2026-11-02)

### Summary of Delivery
Implemented the complete, production-grade Tenant Dispute Workflow allowing authorized tenants to submit factual disputes and counter-evidence against manually associated, visible, approved incident reports, while providing authorized administrators with an administrative review and determination moderation queue. The implementation enforces strict pre-condition eligibility, anti-duplicate dispute protections, single-responsibility document storage and streaming access control, parent report invariance, append-only data-minimized audit logging, and neutral platform review policies without statutory FCRA claims.

### Key Architectural & Implementation Deliverables
- **Granular Capabilities & Roles**:
  - Registered `Capabilities::SUBMIT_TENANT_DISPUTE` (`ft_submit_tenant_dispute`) and `Capabilities::VIEW_OWN_DISPUTES` (`ft_view_own_disputes`) granted to `ft_tenant` and `administrator`.
  - Registered `Capabilities::REVIEW_TENANT_DISPUTES` (`ft_review_tenant_disputes`) and `Capabilities::RESOLVE_TENANT_DISPUTES` (`ft_resolve_tenant_disputes`) granted exclusively to `administrator`.
  - Mapped capabilities in `Capabilities.php` and synchronized to WordPress roles via `RoleManager::register_roles()`.
- **Core Dispute Service Layer (`DisputeService.php`)**:
  - **Status Lifecycle Constants**: `STATUS_PENDING`, `STATUS_UNDER_REVIEW`, `STATUS_RESOLVED`, `STATUS_REJECTED`.
  - **Reason Taxonomy Constants**: `REASON_INACCURATE`, `REASON_INCORRECT_AMOUNT`, `REASON_IDENTITY_MISTAKE`, `REASON_ALREADY_PAID`, `REASON_UNAUTHORIZED`, `REASON_OTHER`.
  - **Determination Action Constants**: `ACTION_UPHELD`, `ACTION_REJECTED`, `ACTION_CORRECTED`.
  - **Eligibility Guard (`can_submit_dispute()`)**: Authenticates caller as tenant, verifies report is approved (`status = 'approved'`), confirms active tenant association (`status = 'active'`), verifies report visibility (`visibility_status = 'visible'` and `disclosable = 1`), and verifies no active dispute (`pending` or `under_review`) already exists.
  - **Dispute Submission (`submit_dispute()`)**: Validates statement length (min 10 chars), checks allowed reason taxonomy, formats reference code `DSP-%06d`, saves record to `wp_ft_tenant_disputes`, uploads optional evidence, and logs data-minimized audit entry.
  - **Anti-Duplicate Guard**: Enforces at most one active dispute per report/tenant, returning `duplicate_active_dispute` if a pending or under-review dispute is active.
  - **Administrative Queue & Details (`get_admin_disputes_queue()`, `get_dispute_detail()`)**: Provides filtered status queries (`all`, `pending`, `under_review`, `resolved`, `rejected`), search, pagination, detailed report context, disputing tenant profile, and attached evidence files.
  - **Administrative Review & Resolution (`review_dispute()`, `resolve_dispute()`)**: Supports status progression to `under_review`, and formal determination (`resolve_upheld`, `resolve_rejected`, `resolve_corrected`) with mandatory determination notes (min 5 chars). Records `resolved_by`, `resolved_at`, and delivers in-app tenant notification.
- **Document Management & Protected Streaming (`DocumentService.php`)**:
  - Implemented `store_dispute_document()` placing counter-evidence in protected directory `fraud-tenant-protected/dispute_evidence/{user_id}/`.
  - Extended `stream_protected_document()` for `dispute_evidence`: validates action nonce, enforces BLK-08 fail-closed gate, allows dispute owner and reviewers (`ft_review_tenant_disputes` / `ft_resolve_tenant_disputes`), and strictly denies landlords/owners and unrelated tenants (anti-IDOR).
- **Web Submission & Form Handlers (`DisputeHandler.php`)**:
  - Handled web form POST `admin_post_ft_submit_tenant_dispute` and AJAX `wp_ajax_ft_submit_tenant_dispute`.
  - Enforced BLK-08 fail-closed gate, CSRF nonces (`ft_submit_dispute_{tenant_id}`), input sanitization, flash notices via `AuthHandler::set_flash_notice()`, and safe redirect to `#my-disputes`.
- **Administrative Portal UI (`DisputeAdminController.php` & `AdminPortal.php`)**:
  - Registered submenu "Tenant Disputes" (`fraud-tenant-disputes`) under `Capabilities::REVIEW_TENANT_DISPUTES`.
  - Moderation queue listing disputes with reference `DSP-%06d`, related incident ref, reason, disputing tenant, submission date, status badge, and review action link.
  - Comprehensive detail view rendering tenant statement, download links for attached counter-evidence, associated report summary, tenant identity, "Mark Under Review" action, and resolution determination form with mandatory notes.
- **Tenant Dashboard Integration (`page-tenant-dashboard.php` & `DashboardService.php`)**:
  - Added "My Disputes" panel to tenant dashboard displaying submitted disputes table or clean empty state.
  - Integrated interactive Dispute Submission Modal (`#ftDisputeSubmitModal-{report_id}`) accessible directly from "My Rental Records" table with required neutral review policy notice, reason dropdown, statement textarea, and file attachment uploader.
  - Integrated Dispute Detail Modal (`#ftDisputeDetailModal-{dispute_id}`) displaying full dispute statement, attached evidence links, and administrator determination notes upon resolution.
- **REST API Endpoints (`DisputeRestController.php`)**:
  - Registered 7 REST endpoints under `fraud-tenant/v1` namespace with strict `permission_callback` checks:
    - `POST /tenant/disputes`: Tenant dispute submission.
    - `GET /tenant/disputes`: Retrieve tenant's own disputes.
    - `GET /tenant/disputes/{id}`: Single dispute detail with owner isolation (404 on cross-tenant IDOR).
    - `GET /admin/disputes`: Admin dispute moderation queue.
    - `GET /admin/disputes/{id}`: Admin dispute detail view.
    - `POST /admin/disputes/{id}/review`: Mark dispute under review.
    - `POST /admin/disputes/{id}/resolve`: Record formal dispute determination.
- **Parent Incident Report Invariance & Data Minimization**:
  - Verified that dispute submissions and determinations NEVER alter parent incident report records, landlord attestations, approval status, or landlord evidence.
  - Append-only audit logging: records `tenant_dispute_submitted` and `admin_dispute_resolved` with actor user IDs for forensic provenance, while strictly excluding free-text statements, determination notes, and PII.
  - Database preservation: All 12 canonical legacy dispute rows (`report_id = 0`) preserved untouched.

### Verification & Testing
- **Automated Dispute Test Suite (`scratch/test_phase8_3_dispute.php`)**: 59 unit and integration assertions covering capability checks, eligibility guards, submission validation, evidence storage, streaming security, admin queue, status transitions, notifications, audit minimization, parent invariance, legacy preservation, and REST API. **Result: 59 / 59 PASS (100%)**.
- **Automated Regression Test Suites**:
  - Phase 8.2 Incident Review Suite (`scratch/test_phase8_2_incident_review.php`): **61 / 61 PASS (100%)**.
  - Phase 8.4 Report Visibility Suite (`scratch/test_phase8_4_report_visibility.php`): **43 / 43 PASS (100%)**.
  - Phase 8.4.1 Integrity Suite (`scratch/test_phase8_4_1_integrity.php`): **36 / 36 PASS (100%)**.
  - Total automated regression catalog: **199 / 199 PASS (100%)**.
- **Live Google Chrome Headed Browser QA (`scratch/run_p83_live_chrome_qa.js`)**: 13 journeys, 22 verification checks executed in headed Chrome (v156.0.8078.74) via native Chrome DevTools Protocol. **Result: 22 / 22 PASSED (100%)**. All 14 visual screenshots captured and verified in `reports/phase8_3_browser_qa/`.
- **Database Hygiene**: All synthetic fixtures cleanly removed via `scratch/cleanup_p83_browser_qa_fixtures.php`. Exactly 12 legacy dispute rows remain in `wp_ft_tenant_disputes`.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [1.7.0] — Master Completion & Controlling Addendum: Dual Stripe Mode, Webhook Engine & SaaS Admin Portal (2026-11-03)

### Summary of Delivery
Executed the Controlling Addendum and Master Production Completion for the Fraud Tenant platform. Delivered complete dual-mode Stripe infrastructure (Test Mode and Live Mode) with isolated encrypted key storage, mode-aware customer and subscription tracking, atomic checkout concurrency locking in MySQL, production-grade webhook event processing, active membership feature enforcement (property quotas and incident reporting gates), full user profile and account security settings, a 15-section SaaS Enterprise Admin Dashboard, and full regression testing across all platform components with 1,209 cataloged assertions.

### Key Architectural & Implementation Deliverables
- **Dual-Mode Stripe Architecture (`StripeCredentialService.php`, `StripeService.php`, `StripeSettingsAdminController.php`)**:
  - Implemented two fully independent, switchable Stripe environments: **Test Mode (Sandbox)** and **Live Mode (Production)**.
  - Stored separate encrypted credentials using AES-256-GCM with HKDF-SHA256 key derivation from genuine WordPress salts in isolated options (`ft_stripe_credentials_test` and `ft_stripe_credentials_live`) with autoload strictly disabled.
  - Enforced strict key prefix validation per mode (`sk_test_`, `pk_test_`, `whsec_test_` vs `sk_live_`, `pk_live_`, `whsec_live_`).
  - Built active environment switcher (`test` vs `live`) with local QA protection: local/staging environments are restricted from live charges, and Live Mode strictly enforces HTTPS.
  - Implemented mode-isolated Stripe Customer tracking: `_ft_stripe_customer_id_test` and `_ft_stripe_customer_id_live` are isolated in user meta.
  - Expanded `wp_ft_membership_plans` schema to store independent Stripe IDs for both modes (`stripe_test_product_id`, `stripe_test_price_id`, `stripe_live_product_id`, `stripe_live_price_id`).
  - Added `livemode` boolean flag to `wp_ft_subscriptions` and `wp_ft_payment_events`.
- **Checkout Concurrency & Atomic Locking (`wp_ft_checkout_locks`, `CheckoutLockService.php`, `CheckoutService.php`)**:
  - Created custom database table `wp_ft_checkout_locks` with unique index on `owner_id`.
  - Implemented database-backed atomic lock acquisition with 120-second TTL and logical attempt ID.
  - Concurrent checkout requests reject double-click race conditions with HTTP 429 and `retry_after: 120`.
  - Stale expired locks (>120s) reclaimed atomically with ownership verification.
- **Stripe Webhook Processing Engine (`StripeWebhookService.php`, `StripeWebhookController.php`)**:
  - Registered REST endpoint `/fraud-tenant/v1/stripe/webhook` with public permission callback.
  - Dynamic mode resolution from webhook payload `livemode` field to match signing secret (`whsec_test_...` vs `whsec_live_...`).
  - Enforced constant-time HMAC-SHA256 signature verification and 300-second timestamp tolerance against replay attacks.
  - Idempotent event processing in `wp_ft_payment_events` with unique `event_id` constraint and status transitions (`received` -> `processing` -> `processed`).
  - Handled complete subscription lifecycle:
    - `checkout.session.completed`: creates/activates subscription in `wp_ft_subscriptions`, updates user plan, releases checkout locks.
    - `customer.subscription.updated`: synchronizes plan status, active periods, and cancellation state.
    - `customer.subscription.deleted`: sets status to `canceled` and downgrades owner to Free plan.
    - `invoice.payment_succeeded` & `invoice.payment_failed`: logs payment events and updates past-due states.
  - Mode-aware in-app notifications generated for activation and cancellation.
- **Membership & Feature Enforcement (`PropertyService.php`, `IncidentService.php`)**:
  - Property quota enforcement: Free tier restricted to 1 active property; Standard tier granted unlimited properties.
  - Incident reporting gating: Free tier restricted from submitting incident reports; Standard tier allowed.
- **User Profile & Account Settings (`DashboardService.php`, `Sanitizer.php`, `page-owner-dashboard.php`, `page-tenant-dashboard.php`)**:
  - Enhanced `DashboardService::update_safe_profile()` with IDOR prevention, nonce checking, and audit logging.
  - Personal & entity profile updates (first name, last name, phone, business name, registration, address).
  - Password mutation requiring current password verification via `wp_check_password()`, minimum 8 characters, and confirmation check.
  - Email mutation requiring current password verification, syntax validation, duplicate collision rejection, and masking utilities (`Sanitizer::mask_email()`).
  - Comprehensive 4-tab modal interface in `page-owner-dashboard.php` and `page-tenant-dashboard.php`.
- **Custom SaaS Enterprise Admin Dashboard (`AdminDashboardController.php`, `AdminPortal.php`)**:
  - Implemented `AdminDashboardController.php` with 15 complete sections: Overview & MRR, Users, Account Approval, Owner Verification, Property Verification, Property Directory, Screening Requests, Incident Review, Tenant Disputes, Report Visibility, Subscriptions & MRR, Membership Plans, Stripe Gateway, Compliance Audit Logs, Platform Settings.
  - Unified with `AdminPortal.php` while preserving backward compatibility for `action=review` direct applicant routing.
- **Verification & Testing**:
  - Database schema upgraded to version `1.7.0`.
  - Master regression suite executed: 232 / 232 assertions passed across all suites (100%).
  - Total catalog test assertions: 1,209 (1,204 PASS, 0 FAIL, 5 NOT TESTED).
  - 9 high-resolution Headless Chrome screenshots captured across desktop (1440x900), tablet (768x1024), and mobile (390x844) viewports.

---

## [Secure Stripe Admin Configuration] — Encrypted Stripe Credentials & Admin Settings Portal (2026-11-02)

### Summary of Delivery
Implemented a dedicated, production-grade Stripe Credentials Management interface in the WordPress Admin Dashboard under `Fraud Tenant -> Settings -> Stripe`. Securely encrypts sensitive Stripe API keys and secrets using AES-256-GCM authenticated encryption with a fresh 12-byte IV per encryption and a 16-byte authentication tag. Cryptographic keys are dynamically derived from existing genuine WordPress secret salts via domain-separated HKDF-SHA256. Plaintext credentials are never stored in the database, never returned to browser clients, and never output in logs or audit events.

### Key Architectural & Implementation Deliverables
- **Stripe Credential Service (`StripeCredentialService.php`)**:
  - Implemented authenticated encryption via AES-256-GCM.
  - Implemented domain-separated key derivation from WordPress salts via HKDF-SHA256 (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`).
  - Stored encrypted credentials in WordPress option `ft_stripe_credentials` with autoload strictly disabled (`autoload = 'no'`).
  - Implemented strict key prefix and environment validation: test mode requires `sk_test_`, `rk_test_`, `pk_test_`, `whsec_` and strictly rejects live keys; production requires live keys and HTTPS (`is_ssl()`).
  - Implemented blank-field preservation: submitting empty credential fields preserves existing stored secrets.
  - Implemented dedicated, confirmed credential removal action (`remove_credentials()`).
  - Sanitized status reporting: exposes configuration state and environment metadata without leaking keys or fragments.
- **Admin Dashboard Settings Controller (`StripeSettingsAdminController.php`, `AdminPortal.php`)**:
  - Registered `fraud-tenant-settings` submenu page under `fraud-tenant-admin`.
  - Enforced administrator-only access (`manage_options`, `ft_manage_platform_settings`). Owners and tenants receive HTTP 403.
  - Integrated POST mutation handlers with WordPress nonces: `admin_post_ft_save_stripe_settings`, `admin_post_ft_remove_stripe_settings`, `admin_post_ft_test_stripe_connection`.
  - Built responsive Admin UI matching Fraud Tenant design system: Environment Policy Card, Credentials Form with blank-on-load secret inputs, Connection Test Card, Membership Plan Stripe Mappings Table, and Danger Zone Card.
- **Stripe Service Integration (`StripeService.php`)**:
  - Updated `get_secret_key()` and `get_publishable_key()` to resolve from `StripeCredentialService` first, falling back to runtime constants/environment variables for existing headless test suites.
  - Added `get_webhook_secret()` for future Phase 9.3 webhook processing.
  - Added `test_connection()`: executes server-side read-only GET `/v1/balance` inquiry with bounded timeout and SSL certificate verification.
  - Added transient-gated mock response support for local QA testing.

### Verification & Testing
- **Automated Security Test Suite (`scratch/test_secure_stripe_admin_config.php`)**:
  - 19 / 19 Assertions PASS (100%) in an isolated disposable database (`fraud_tenant_sec_stripe_<uniq>`).
  - Verified admin save, owner/tenant 403 blocking, unauthorized rejection, nonce validation, DB ciphertext storage with disabled autoload, server-side decryption, auth tag tampering rejection, prefix/env rejection, blank-field preservation, removal, mocked connection test, and zero audit/HTML leaks.
  - Verified operational database remained 100% untouched.
- **Existing Phase 9.2 Test Suite Non-Regression (`scratch/test_phase9_2_stripe_checkout.php`)**:
  - 47 / 47 Assertions PASS (100%).
- **Live Chrome QA (`scratch/run_stripe_admin_live_chrome_qa.js`)**:
  - Executed 9 visible headed Google Chrome journeys and captured screenshots in `reports/phase9/stripe_settings_qa/`.

---

## [Phase 9.2] — Stripe API Integration, Hosted Checkout, Customer Management & Billing Portal (2026-11-02)

### Summary of Delivery
Implemented Phase 9.2 of Fraud Tenant, delivering the Stripe API communication layer, Stripe Customer management, hosted Checkout Session generation, duplicate checkout session protection, customer billing portal access, and checkout return verification. Built directly upon the Phase 9.1 membership foundation, enabling approved property owners to select active Standard Monthly or Standard Annual membership tiers and proceed securely to Stripe-hosted subscription checkout.

### Key Architectural & Implementation Deliverables
- **Stripe API Client Service (`StripeService.php`)**:
  - Implemented `StripeService` in `FraudTenant\Services` utilizing WordPress HTTP API (`wp_remote_request`) with HTTPS enforcement, 15-second bounded timeouts, Bearer authorization, and API version pinning (`2023-10-16`).
  - Implemented secret key retrieval and validation: fails closed on missing/empty keys (`stripe_config_missing`), enforces `sk_...` format (`stripe_invalid_key_format`), and strictly rejects live keys (`sk_live_...`) in local development environments.
  - Implemented server-side price verification gate (`validate_price_against_plan()`): retrieves live Stripe Price object and validates active status, amount match, currency match, billing interval match, interval count match, and product ID match before creating checkout sessions.
  - Implemented customer management (`create_customer()`, `get_customer()`, `get_or_create_customer()`): associates owner with single Stripe Customer stored in `_ft_stripe_customer_id` user meta with deterministic idempotency keys (`ft_cus_u{user_id}_{hash}`).
  - Implemented checkout session generation (`create_checkout_session()`, `get_checkout_session()`): passes server-resolved Price ID and client_reference_id.
  - Implemented Customer Portal session generation (`create_portal_session()`): generates Stripe-hosted billing portal URLs for owners with associated customer records.
- **Checkout Orchestration Service (`CheckoutService.php`)**:
  - Implemented `CheckoutService` in `FraudTenant\Services` managing the complete owner checkout lifecycle.
  - Implemented concurrency protection via transient locks (`ft_chk_lock_{user_id}`) preventing double-click race conditions.
  - Implemented open session reuse: checks user meta `_ft_pending_checkout_session`, queries Stripe API for session status, reuses active URLs, and purges expired sessions.
  - Implemented duplicate checkout prevention: active subscriptions or sessions pending webhook synchronization block new sessions with HTTP 409 Conflict.
  - Implemented return state verification (`verify_checkout_return()`): verifies owner ownership of session, returning safe status `pending_verification` without prematurely activating subscriptions.
  - Implemented Customer Portal routing (`initiate_customer_portal()`).
- **REST API Controller (`MembershipCheckoutController.php`, `RestServer.php`)**:
  - Registered controller in `RestServer.php` under namespace `fraud-tenant/v1`.
  - Registered endpoints: `POST /owner/membership/checkout`, `POST /owner/membership/portal`, `GET /owner/membership/session-status`.
  - Enforced REST cookie nonces (`wp_rest`), user authentication, approved owner verification (`MembershipService::is_eligible_owner`), and explicit 403 rejection of tenant and administrator accounts.
- **Dashboard Application & UI Integration (`page-owner-dashboard.php`, `dashboard.js`)**:
  - Added checkout return notice banners: green pending activation banner for `checkout_status=success` (`"Payment submitted. Membership activation is pending server-side payment and subscription verification."`) and amber warning banner for `checkout_status=cancelled`.
  - Enhanced `ftMembershipModal`: added plan data attributes (`data-plan-code`), client notice element (`#ftCheckoutNotice`), and conditional Stripe Billing Portal button (`.ft-portal-btn`).
  - Added AJAX click handlers in `dashboard.js` with loading state transitions and safe client error notices.
  - Confirmed that returning from checkout strictly leaves plan as Free; subscription activation is deferred to Phase 9.3 webhooks.
- **Strict Phase Boundary Adherence**:
  - Strictly no webhook handlers, cryptographic webhook signature verification, event persistence in `wp_ft_payment_events`, or automatic subscription activation were implemented (reserved for Phase 9.3).

### Verification & Testing
- **Dedicated Phase 9.2 Automated Test Suite (`scratch/test_phase9_2_stripe_checkout.php`)**:
  - Total assertions executed: **47 / 47 PASS (100%)** within an isolated disposable database (`fraud_tenant_p92_isolated_<uniq>`).
  - Operational database (`fraud_tenant`) remained 100% untouched throughout test execution.
- **Full Regression Catalog Execution**:
  - Full 5-Suite Regression (`scratch/run_full_catalog_248.php`): **248 / 248 PASS (100%)**.
  - Phase 9.1 Membership Foundation (`scratch/test_phase9_1_membership_foundation.php`): **25 / 25 PASS (100%)**.
  - Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`): **16 / 16 PASS (100%)**.
  - Total combined automated test assertions: **336 / 336 PASS (100%)**.
- **Live Google Chrome Headed Browser QA (`scratch/run_p92_live_chrome_qa.js`)**:
  - Executed 11 user journeys in visible Google Chrome across desktop (1280x800) and mobile (390x844) viewports: **11 / 11 PASS (100%)**.
  - Captured 11 visual screenshots in `reports/phase9_2_browser_qa/` and mirrored to brain artifacts.
- **Operational Database Integrity**:
  - Baseline verified 100% invariant (0 subscriptions, 3 plans, 12 disputes, 10 reports, 151 documents, 97 WP users).
- **Reports & Artifacts**:
  - Authored authoritative report: `reports/phase9/phase9_2_implementation_report.md`
  - Created user-facing artifact: `phase9_2_implementation_report.md`

---

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.4.1] — Association, Disclosure & Security Integrity Reconciliation (2026-11-02)

### Summary of Delivery
Audited and reconciled Phase 8.4 implementation to enforce single-active-association cardinality, transaction-safe concurrency checks with row locks, historical association preservation upon disassociation, reassociation support, strict authoritative access invariants across all tenant read queries and evidence streaming, granular capability isolation, transaction rollback on history failure, and neutral dispute modal wording removing all statutory FCRA claims.

### Key Architectural & Implementation Deliverables
- **Single-Active-Association Cardinality & Concurrency Protection**:
  - Enforced single-active-association model (`at most one active tenant association per incident report`).
  - Added transaction-safe pre-checks and row locks (`SELECT ... FOR UPDATE`) in `ReportVisibilityService::associate_tenant()`.
  - Rejects attempts to associate a second active tenant with `report_already_associated`, and duplicate attempts for the same tenant with `already_associated`.
- **Historical Provenance & Reassociation**:
  - Disassociating a tenant (`disassociate_tenant()`) preserves the historical association record in `wp_ft_report_associations` by setting `status = 'disassociated'` and `visibility_status = 'revoked'`.
  - Report `tenant_identity_id` is reset to `NULL` and `disclosable` to `0` upon disassociation.
  - Successfully allows disassociated reports to be subsequently reassociated with different tenants while maintaining complete chronological history in `wp_ft_report_disclosure_history`.
- **Strict Authoritative Tenant Access & Evidence Streaming Invariant**:
  - A tenant can access an incident report or stream attached evidence documents ONLY when:
    1. Tenant is the currently authorized active association account (`ra.tenant_user_id = %d AND ra.status = 'active'`).
    2. Association visibility status is `visible` (`ra.visibility_status = 'visible'`).
    3. Incident report status is `approved`.
    4. Incident report disclosure state is enabled (`r.disclosable = 1`).
    5. Security gate permits operation (`IncidentService::is_test_mode_enabled()`).
  - Removed loose fallback clauses; enforced strict `INNER JOIN` across `get_tenant_disclosed_reports()`, `get_tenant_report_detail()`, and `is_tenant_associated_and_visible()`.
- **Dispute Modal Notice Neutrality (`page-tenant-dashboard.php`)**:
  - Removed unsupported statutory FCRA assertions, 30-day resolution timeline claims, and statutory rights text from `page-tenant-dashboard.php`.
  - Replaced with neutral administrative review notice:
    *"You may submit a dispute regarding factual information in this report through the platform's dispute process. Your statement and supporting evidence will be reviewed by an authorized administrator. Submitting a dispute does not automatically change the report or guarantee a particular outcome."*
  - Replaced dispute card and sidebar footer text with neutral platform review wording; verified zero occurrences of FCRA in `page-tenant-dashboard.php`.
- **Granular Capabilities Enforcement**:
  - Enforced `Capabilities::ASSOCIATE_TENANT_REPORTS` specifically for association and disassociation actions.
  - Enforced `Capabilities::MANAGE_REPORT_VISIBILITY` specifically for visibility approval and revocation actions.
  - Updated permission callbacks in `ReportVisibilityRestController` (`check_associate_permission`, `check_visibility_permission`) and POST action handlers in `AdminPortal::handle_visibility_action()`.
- **Transaction & Rollback Integrity**:
  - Wrapped association, disassociation, approval, and revocation in explicit `START TRANSACTION` / `COMMIT` / `ROLLBACK` blocks with `try ... catch (\Throwable)`.
  - Any failure during disclosure history insertion immediately triggers `$wpdb->query('ROLLBACK')`, guaranteeing zero partial database updates.

### Verification & Testing
- **Automated Test Suite (`scratch/test_phase8_4_1_integrity.php`)**: 36 assertions covering single-active-association rejection, duplicate association, historical preservation, reassociation, tenant isolation, revocation immediacy, anti-IDOR detail protection, granular capabilities, transaction rollback atomicity, and neutral dispute modal notice. **Result: 36 / 36 PASS (100%)**.
- **Phase 8.4 Regression Test Suite (`scratch/test_phase8_4_report_visibility.php`)**: 43 assertions covering visibility queue, status breakdowns, approval gate, revocation, disassociation, anti-IDOR, REST routes, and schema resilience. **Result: 43 / 43 PASS (100%)**.
- **Phase 8.2 Regression Test Suite (`scratch/test_phase8_2_incident_review.php`)**: 61 assertions covering incident review workflow, self-review separation, reason codes, and privacy gates. **Result: 61 / 61 PASS (100%)**.
- **Live Google Chrome Headed Browser QA (`scratch/run_p84_1_live_chrome_qa.js`)**: 14 journeys executed in headed Chrome (v156.0.8078.74) via native Chrome DevTools Protocol. **Result: 14 / 14 PASSED (100%)**. All 13 visual screenshots captured and verified in `reports/phase8_4_1_browser_qa/`.
- **Data Preservation**: All 12 legacy dispute rows (`report_id = 0`) preserved untouched. All synthetic test fixtures cleanly removed via `scratch/cleanup_p84_1_browser_qa_fixtures.php`.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.4] — Admin-Controlled Approved Report Visibility (2026-11-02)

### Summary of Delivery
Implemented end-to-end admin-controlled report visibility workflow enabling authorized administrators to manually associate approved incident reports with verified tenant accounts, approve tenant-facing disclosure (`disclosable = 1`), revoke visibility non-destructively (`disclosable = 0`), track complete chronological association/disclosure audit histories, and surface disclosable reports on the Tenant Dashboard ("My Rental Records") with detail modals, evidence streaming links, anti-IDOR isolation, and entry points to the Phase 8.3 tenant dispute flow.

### Key Architectural & Implementation Deliverables
- **Database Schema Expansion (Version 1.5.0)**:
  - Incremented `Constants::DB_VERSION` to `'1.5.0'` in `includes/Utils/Constants.php`.
  - Added constants `TABLE_REPORT_ASSOCIATIONS = 'ft_report_associations'` and `TABLE_REPORT_DISCLOSURE_HISTORY = 'ft_report_disclosure_history'`.
  - Added Table 15 (`{$wpdb->prefix}ft_report_associations`) and Table 16 (`{$wpdb->prefix}ft_report_disclosure_history`) in `includes/Database/Schema.php` with foreign key indexes and `InnoDB` ACID transaction compliance. Total custom tables: **16**.
  - Migrated live database via `MigrationManager::migrate(true)` and updated `MigrationManager` to synchronize role capabilities on each migration run.
- **Capabilities Registry**:
  - Registered `Capabilities::MANAGE_REPORT_VISIBILITY` (`ft_manage_report_visibility`) and `Capabilities::ASSOCIATE_TENANT_REPORTS` (`ft_associate_tenant_reports`) in `includes/Auth/Capabilities.php`.
  - Mapped capabilities to `get_admin_caps()` and synchronized to the `administrator` role.
- **Core Service Layer (`ReportVisibilityService.php`)**:
  - Implemented `can_manage_visibility()`, `get_visibility_queue()`, `get_queue_status_counts()`, `get_report_visibility_detail()`, `get_tenant_candidates()`, `associate_tenant()`, `disassociate_tenant()`, `approve_visibility()`, `revoke_visibility()`, `get_tenant_disclosed_reports()`, `get_tenant_report_detail()`, and `is_tenant_associated_and_visible()`.
  - Strict Phase 8.2 Guard: Enforces that visibility approval is strictly permitted only for incident reports in status `approved`. Attempts on `submitted`, `rejected`, or `needs_clarification` fail closed with `report_not_approved` (HTTP 409).
  - Non-Destructive Revocation: Visibility revocation sets `disclosable = 0` and association `visibility_status = 'revoked'` while preserving the underlying incident report and attached evidence documents completely intact.
  - Fail-closed BLK-08 Privacy Gate integration via `IncidentService::is_test_mode_enabled()`.
- **Document Streaming Authorization (`DocumentService.php`)**:
  - Updated `stream_protected_document()` in `includes/Services/DocumentService.php` to authorize associated tenants to stream attached incident evidence documents when the report is approved and `disclosable = 1` via `ReportVisibilityService::is_tenant_associated_and_visible()`.
  - Updated audit logging to record `tenant.document_viewed` for authorized tenant downloads.
- **Admin Interface & Menu Orchestration (`ReportVisibilityController.php` & `AdminPortal.php`)**:
  - Registered admin submenu `fraud-tenant-visibility` ("Report Visibility") under the Fraud Tenant menu.
  - Queue View: Subsubsub status tabs (`all`, `unassociated`, `associated`, `awaiting_visibility`, `tenant_visible`, `visibility_revoked`), keyword search box, and responsive items table styled in PropTech navy/teal design palette.
  - Detail View: Comprehensive report summary, landlord attestation status, attached evidence list, verified tenant association selector form, visibility approval/revocation action forms, and chronological disclosure history timeline.
  - Action POST handler in `AdminPortal::handle_visibility_action()` verifying action-specific nonces and administrative capabilities.
- **REST API Endpoints (`ReportVisibilityRestController.php` & `RestServer.php`)**:
  - Registered 8 REST API routes under `fraud-tenant/v1`:
    - `GET /admin/report-visibility` (Queue listing)
    - `GET /admin/report-visibility/(?P<id>[\d]+)` (Report visibility detail)
    - `GET /admin/tenant-candidates` (Verified tenant search)
    - `POST /admin/report-visibility/(?P<id>[\d]+)/associate` (Tenant association)
    - `POST /admin/report-visibility/(?P<id>[\d]+)/disassociate` (Tenant disassociation)
    - `POST /admin/report-visibility/(?P<id>[\d]+)/approve` (Approve visibility disclosure)
    - `POST /admin/report-visibility/(?P<id>[\d]+)/revoke` (Revoke visibility disclosure)
    - `GET /tenant/disclosed-reports` (Tenant's own disclosed incident reports)
  - Strict `permission_callback` checks enforcing capability isolation and anti-IDOR protections.
- **Tenant Dashboard Integration (`page-tenant-dashboard.php` & `DashboardService.php`)**:
  - Updated `DashboardService::get_tenant_dashboard_data()` to query genuine disclosed reports via `ReportVisibilityService::get_tenant_disclosed_reports()`.
  - Styled "My Rental Records" panel displaying reference `INC-%06d`, property address, incident date, amount, and badges.
  - Built accessible report detail modal (`#ftReportDetailModal-{id}`) showing full factual description and attached evidence files with nonced streaming links.
  - Integrated "Dispute This Record" button linking directly to `#ftDisputeModal` with FCRA rights notice as the natural entry point for Phase 8.3.
  - Clean empty state displayed when no disclosed records are available.
- **Cross-Tenant Isolation & Anti-IDOR Enforcement**:
  - Unrelated tenants receive 0 records and are strictly denied access to unassociated or unapproved records across service, REST API, and document streaming layers.

### Verification & Testing
- **Automated Test Suite (`scratch/test_phase8_4_report_visibility.php`)**: 43 assertions covering roles, capabilities, queue filtering, association, Phase 8.2 guard (`report_not_approved`), visibility approval, revocation, disassociation, Anti-IDOR isolation, REST endpoints, and schema resilience. **Result: 43 / 43 PASS (100%)**.
- **Phase 8.2 Regression Test Suite (`scratch/test_phase8_2_incident_review.php`)**: 61 assertions covering full incident review workflow, self-review separation, reason codes, and fail-closed privacy gating. **Result: 61 / 61 PASS (100%)**.
- **Live Google Chrome Headed Browser QA (`scratch/run_p84_live_chrome_qa.js`)**: 12 journeys executed in headed Chrome (v156.0.8078.74) via native Chrome DevTools Protocol. **Result: 12 / 12 PASSED (100%)**. All 13 visual screenshots captured and verified in `reports/phase8_4_browser_qa/`.
- **Database & Data Preservation**: Exactly 12 legacy synthetic dispute rows (`report_id = 0`) preserved untouched. All synthetic QA fixtures cleanly removed via `scratch/cleanup_p84_browser_qa_fixtures.php`.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.2 Reconciliation] — Final Security, Concurrency & Documentation Reconciliation (2026-11-02)

### Workspace Identity & Version Control Verification
- **Active Workspace**: Confirmed active workspace root at `C:\xampp\htdocs\fraud-tenant` containing active WordPress core 7.1.3, `fraud-tenant-core` plugin, and `fraud-tenant-theme`. Verified that `C:\xampp\htdocs\fraudtenant` does not exist on filesystem.
- **Version Control Status**: Confirmed that `.git` does not exist in `C:\xampp\htdocs\fraud-tenant` or parent directories. Version-control tracking is unavailable, and files are not described as Git-tracked.

### Restored Approved Phase 8 Roadmap (`IMPLEMENTATION_PLAN.md`, `TASKS.md`)
- **Restored Approved Roadmap**: Reconciled `IMPLEMENTATION_PLAN.md` and `TASKS.md` against the established approved roadmap:
  - **Phase 8.3 — Tenant Dispute Workflow** (Planned)
  - **Phase 8.4 — Approved Report Visibility** (Planned)
- Removed unauthorized sub-phases (Tenant Notification split, 8.5 Administrative Dispute Resolution, and 8.6 Lifecycle Verification).
- Confirmed strictly zero Phase 8.3+ functionality was implemented.

### Blocker ID Mapping Reconciliation (`BLOCKERS.md`, `QA_REPORT.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`)
- **Strict Canonical Mapping Restored**:
  - **BLK-01**: Stripe API test credentials and webhook signing secret (`STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`) — Phase 9 payment integration dependency.
  - **BLK-02**: Live outbound SMTP credentials — mandatory production release gate (5 assertions remain classified as `NOT TESTED`).
- Preserved BLK-03A through BLK-03F as unresolved legal compliance blockers.
- Preserved BLK-08 as fail-closed sensitive PII protection gate.

### Self-Review Policy Reconciliation (`IncidentReviewService.php`, `IncidentReviewController.php`, `IncidentReviewRestController.php`, `DocumentService.php`)
- **Precise Policy Separation**: Enforced approved self-review boundaries:
  - **Self-Decision Strictly Prohibited**: If `(int) $report->owner_id === $reviewer_id`, any decision attempt (`approve`, `reject`, `request_clarification`) is strictly blocked in both service (`IncidentReviewService::decide_incident_report`) and REST API (`IncidentReviewRestController::submit_review_decision`), returning `self_review_forbidden` (HTTP 403) and audited with action `incident_self_review_blocked`.
  - **Self-Inspection & Evidence Streaming Permitted**: Submitting owners possessing reviewer capabilities can view their own reports in the queue (`is_self_report = true`), inspect report details (`get_incident_for_review`), retrieve attached evidence metadata (`GET /admin/incident-reports/{id}/evidence`), and stream their own evidence files (`DocumentService::stream_protected_document`) via verified ownership cross-checks.
  - **Admin UI Hardening**: Updated Admin detail screen to display explicit "Self-Decision Blocked" alert banner: *"You submitted this incident report. In accordance with platform governance, an owner cannot decide the outcome of their own report. You may inspect the details and evidence, but another authorized administrator must review and decide this record."* Decision action controls are completely suppressed.
  - **Documentation Correction**: Corrected ambiguous documentation that previously claimed self-inspection was prohibited.

### Legal-Sensitive Reason Code Governance (`IncidentReviewService.php`, `BLOCKERS.md`)
- **Retirement of Unsupported Statutory Determination**:
  - Replaced unsupported legal conclusion `statute_exceeded` with internal-policy reason code `policy_window_exceeded` (`Alleged incident occurred outside platform reporting time window (internal policy)`) in `IncidentReviewService::REJECTION_REASONS`.
  - Added server-side guard in `decide_incident_report()` that explicitly blocks any submission of `statute_exceeded` with error `statutory_determination_unsupported`: *"Statutory determinations (such as statute of limitations) cannot be used as an automated platform reason code pending qualified legal counsel approval. Use 'policy_window_exceeded' for internal platform policy time limits."*
  - Preserved legal blockers BLK-03A through BLK-03F as strictly unresolved pending formal U.S. legal counsel review.

### Audit Log Privacy & Accountability Reconciliation (`AuditLogger.php`, `IncidentReviewService.php`, `QA_REPORT.md`)
- **Accurate Privacy Scope**: Corrected inaccurate "Zero-PII Audit Logging" claims across documentation.
- **Accountability Retention**: Explicitly documented that actor IDs (`user_id`, `reviewer_id`, `owner_id`) are intentionally retained in `wp_ft_audit_log` and `wp_ft_report_reviews` for provenance, accountability, and security forensics. Accountability fields are preserved.
- **Data Minimization Verified**: Confirmed via dedicated test assertions (`P82R-3.3`, `P82R-3.4`) that free-text incident narratives (`factual_description`), SSNs, passwords, and applicant identity data are strictly excluded from audit details.

### Database Migration, Concurrency & Transaction Integrity (`Constants.php`, `MigrationManager.php`, `IncidentReviewService.php`)
- **Schema Version Bump**: Incremented `Constants::DB_VERSION` from `1.3.0` to `1.4.0` in `Constants.php`. Guaranteed that existing installations running `1.3.0` automatically trigger `MigrationManager::migrate()` and execute `reconcile_report_reviews_schema()` and `reconcile_incident_schema()`.
- **Migration Idempotency**: Verified that `MigrationManager::migrate()` operates idempotently across both fresh and existing installations without table drops or duplicate data.
- **Storage Engine Verification**: Confirmed that all 14 custom tables in MariaDB utilize the `InnoDB` storage engine supporting ACID transactions.
- **Transaction Failure Handling**: Hardened `IncidentReviewService::decide_incident_report()`:
  - Checked return value of `START TRANSACTION` (`false === $trans_started` returns `transaction_start_failed`).
  - Checked return value of conditional `UPDATE` (`false === $updated` triggers rollback returning `status_update_failed`; `1 !== $updated` triggers rollback returning `conflict_already_decided`).
  - Checked return value of review history `INSERT` (`false === $history_inserted` triggers rollback returning `history_record_failed`).
  - Checked return value of `COMMIT` (`false === $committed` triggers rollback returning `transaction_commit_failed`).
  - Wrapped execution in `try ... catch (\Throwable)` with rollback on unexpected PHP exceptions (`transaction_failed`).
  - Authored and verified synthetic failure-injection test `P82R-4.5` proving that a database failure on review history insert rolls back the update, leaving status in `submitted` and creating zero history rows.
- **Accurately Scoped Concurrency Claims**:
  - Distinguished sequential stale-state rejection (pre-condition status check returning `invalid_status_transition`, `P82R-4.6`) from atomic conditional database update guards (`WHERE id = %d AND status = 'submitted'`, `P82R-4.7`).
  - Removed all claims of simultaneous multi-process race testing, distributed locks, or row-level locking beyond standard conditional update atomicity.
  - Application-level append-only audit logging. Existing audit records were preserved during browser QA fixture cleanup. This does not prevent privileged database administrators from directly modifying database records.

### Automated Testing & Verification
- Authored and executed dedicated Phase 8.2 reconciliation test suite `scratch/test_phase8_2_reconciliation.php`: **30 / 30 assertions PASS (100%)**.
- Executed full Phase 8.2 administrative review test suite `scratch/test_phase8_2_incident_review.php`: **61 / 61 assertions PASS (100%)**.
- Total physical assertions executed in current reconciliation pass: **91 / 91 assertions PASS (100%)**.
- Cumulative platform catalog: **810 unique assertions** (805 PASS, 0 FAIL, 0 BLOCKED, 5 NOT TESTED).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.2] — Administrative Incident Review Workflow (2026-11-01)

### Administrative Incident Review Engine (`IncidentReviewService.php`)
- **Review Service Layer**: Built `FraudTenant\Services\IncidentReviewService` providing full administrative moderation workflow:
  - **Queue Querying & Filtering**: Paginated querying (`get_review_queue`) supporting status filters (`submitted`, `approved`, `rejected`, `needs_clarification`, `all`), text search over reference/address/category, pagination controls (`page`, `per_page`), and live status breakdown counts.
  - **Detail Inspection**: Single-report retrieval (`get_incident_for_review`) projecting property address, owner business/display details, attached evidence document metadata with time-limited streaming nonces, chronological review history, and allowed reason codes.
  - **Self-Review Prevention**: Enforced strict self-review guardrail. If `(int) $report->owner_id === $reviewer_id`, any decision attempt is strictly blocked returning `self_review_forbidden` and logged to audit log with action `incident_self_review_blocked`.
  - **Controlled Reason Taxonomies**: Enforced server-side allowlists for decisions:
    - Rejection reasons: `insufficient_evidence`, `ineligible_claim`, `lease_discrepancy`, `duplicate_submission`, `statute_exceeded`, `other_factual_grounds`.
    - Clarification reasons: `clarify_dates`, `clarify_accounting`, `clarify_documentation`, `clarify_parties`, `general_clarification`.
    - Approval reasons: `evidence_verified`, `factual_standard_met`, `administrative_acceptance`.
  - **Atomic Concurrency & Status Locking**: Wrapped state transitions in MySQL transactions (`START TRANSACTION`, `COMMIT`, `ROLLBACK`) with optimistic concurrency locking (`WHERE id = %d AND status = 'submitted'`). Race conditions or concurrent modifications trigger `conflict_already_decided`.
  - **Durable Review History**: Every decision records an append-only row in `wp_ft_report_reviews` capturing `report_id`, `reviewer_id`, `decision`, `previous_status`, `new_status`, and `reason_code`.
  - **Zero-Disclosure Guarantee**: Verified that across all states (`submitted` -> `approved`, `rejected`, `needs_clarification`), `disclosable` strictly remains `0` and `tenant_identity_id` remains `NULL`.
  - **BLK-08 Production Privacy Gate**: Direct constant fail-closed evaluation at the head of every review workflow method (`get_review_queue`, `get_incident_for_review`, `decide_incident_report`), returning `incident_review_locked_blk08` (HTTP 423) in production.

### Administrative UI & Controller (`IncidentReviewController.php`, `AdminPortal.php`)
- **Incident Moderation Portal Screen**:
  - Registered admin submenu page `fraud-tenant-incidents` under Fraud Tenant Admin in `AdminPortal.php`.
  - Built responsive queue screen with tab navigation (`All`, `Submitted`, `Approved`, `Rejected`, `Needs Clarification`), keyword search, and item cards/table.
  - Built comprehensive detail review screen with owner details, property address, incident dates, amount involved, full factual description, and attached evidence documents.
  - Built interactive decision forms for **Approve Report**, **Reject Report**, and **Request Clarification** with controlled reason dropdowns and optional notes.
  - Rendered prominent self-review warning notice when the logged-in administrator is the owner of the report, disabling all decision action controls.
  - Built production locked screen (`render_locked_production_screen`) with BLK-08 notice when running in production mode.
  - Added web action handler `admin_post_ft_admin_incident_review_action` in `AdminPortal.php` with CSRF nonce verification and feedback transients.

### REST API Endpoints (`IncidentReviewRestController.php`, `RestServer.php`)
- Implemented `IncidentReviewRestController` registering 5 administrative REST endpoints under `/fraud-tenant/v1`:
  - `GET /admin/incident-reports`: Paginated queue with status and search parameters.
  - `GET /admin/incident-reports/{id}`: Single incident report detail.
  - `GET /admin/incident-reports/{id}/evidence`: Attached evidence metadata list.
  - `POST /admin/incident-reports/{id}/review`: Decision execution (`approve`, `reject`, `request_clarification`).
  - `GET /admin/incident-reports/{id}/history`: Chronological review history trail.
- Registered controller in `RestServer.php`.
- Enforced strict authorization: unauthenticated requests return 401; non-reviewers return 403; read-only reviewers attempting decisions return 403; production mode returns 423 `incident_review_locked_blk08`.

### Capabilities & Schema Enhancements (`Capabilities.php`, `Constants.php`, `Schema.php`, `MigrationManager.php`)
- Added capabilities `ft_review_incidents` (`Capabilities::REVIEW_INCIDENTS`) and `ft_decide_incidents` (`Capabilities::DECIDE_INCIDENTS`), included in `Capabilities::get_admin_caps()`.
- Added constant `INCIDENT_STATUS_NEEDS_CLARIFICATION = 'needs_clarification'`.
- Updated schema for `wp_ft_report_reviews` with columns `previous_status`, `new_status`, and `reason_code`.
- Implemented and executed `MigrationManager::reconcile_report_reviews_schema()` non-destructively; all 10 columns verified in MariaDB.
- Hardened `DocumentService::stream_protected_document()` for `incident_evidence` with BLK-08 production privacy gate check.

### Automated Test Suite & Visual Verification
- Authored and executed dedicated Phase 8.2 automated test suite `scratch/test_phase8_2_incident_review.php`: **61 / 61 assertions PASS (100%)**.
- Executed multi-suite historical regression (11 test suites): **456 / 456 physical assertions PASS (100%)**.
- Reconciled cumulative platform QA catalog: **780 assertions total** (775 PASS, 0 FAIL, 0 BLOCKED, 5 NOT TESTED).
- Captured 5 headless Chrome visual screenshots:
  - `phase8_2-admin-incident-queue-desktop.png` (1440x900)
  - `phase8_2-admin-incident-detail-desktop.png` (1440x1100)
  - `phase8_2-admin-incident-detail-tablet.png` (768x1024)
  - `phase8_2-admin-incident-detail-mobile.png` (375x812)
  - `phase8_2-admin-incident-locked-desktop.png` (1440x900)

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.1.1] — Security Reconciliation and Production Privacy Gate Verification (2026-11-01)

### Environment Filter Vulnerability Elimination & Direct Constant Hardening (`IncidentService.php`, `wp-config.php`)
- **Direct PHP Constant Evaluation**:
  - Removed all calls to `wp_get_environment_type()` in `IncidentService::is_test_mode_enabled()` because core WordPress allows third parties to filter environment resolution via `apply_filters('wp_get_environment_type', ...)`.
  - Replaced with direct, strictly typed constant checks:
    - Constant `FT_INCIDENT_TEST_MODE` must be explicitly defined and strictly boolean `true`.
    - Constant `WP_ENVIRONMENT_TYPE` must be explicitly defined as a PHP constant and directly evaluated via `constant('WP_ENVIRONMENT_TYPE')`.
    - Allowed values strictly whitelisted to `'local'`, `'development'`, or `'staging'` (case-insensitive, trimmed).
    - Environment values of `'production'`, unknown, undefined, or missing values fail-closed.
  - Zero WordPress filters, query parameters, POST fields, cookies, or user capabilities can override or enable the gate.
  - Verified deployment boundary: `wp-config.php` is strictly local-only and excluded from Git (.gitignore line 9). Local development explicitly configures `WP_ENVIRONMENT_TYPE = 'local'`. Shared and production deployment policy enforces fail-closed fallback to `'production'` if undefined.

### Production Privacy Gate Hardening (`IncidentService.php`, `IncidentController.php`, `IncidentHandler.php`, `DocumentService.php`)
- Hardened `IncidentService::is_test_mode_enabled()`:
  - Completely removed `apply_filters('ft_incident_test_mode_enabled')` to eliminate any possibility of filter-based bypass in production.
  - Pre-processing fail-closed privacy gate: evaluated before reading form values, narrative text, or `$_FILES` payload across all entry points:
    - REST API `IncidentController::check_owner_submit_permission()`: immediately returns HTTP 423 `submission_locked_blk08`.
    - AJAX handler `IncidentHandler::handle_ajax_submit_incident()`: immediately returns HTTP 423 `submission_locked_blk08`.
    - Web POST handler `IncidentHandler::handle_submit_incident()`: immediately invokes `wp_die()` with HTTP 423.
    - Direct calls to `DocumentService::store_incident_document()`: immediately fail closed returning `['success' => false, 'error' => 'submission_locked_blk08']`.

### Tenant Identity Semantics & Schema Migration (`Schema.php`, `MigrationManager.php`, `IncidentService.php`)
- Aligned `wp_ft_incident_reports.tenant_identity_id` to `bigint(20) unsigned DEFAULT NULL` in `Schema.php`.
- Implemented `MigrationManager::reconcile_incident_schema()` to execute non-destructive database column alteration (`ALTER TABLE ... MODIFY tenant_identity_id bigint(20) unsigned DEFAULT NULL`) and converted legacy `0` values to `NULL`.
- Updated `IncidentService::record_incident_report()` to insert `'tenant_identity_id' => null` (strictly `NULL`, not `0`) on unlinked Phase 8.1 reports.

### Rate Limiting & Concurrency Documentation (`IncidentService.php`)
- Determined and documented exact rate-limiting semantics: `check_rate_limit()` operates as a sliding inactivity TTL transient counter (resets 300-second window on every hit, requiring 300 seconds of inactivity to expire), NOT a fixed clock-window counter.
- Documented concurrency and race condition characteristics: noted that standard WordPress transients stored in `wp_options` exhibit read-modify-write race conditions under concurrent requests; production enterprise scaling with strict atomic guarantees requires persistent object caching (e.g. Redis `INCR`).

### QA Count Reconciliation & Arithmetic Correction
- **Exact Arithmetic Reconciliation**: Resolved the previous arithmetic discrepancy. Previous physical execution was 393 assertions ($27 + 24 + 36 + 44 + 45 + 33 + 15 + 58 + 71 + 40 = 393$). Original reported total was 421. The discrepancy was $421 - 393 = \mathbf{28}$ assertions (due to reporting errors in Phase 6.1 of -12, Phase 6.1.1 of -15, and Phase 4/5 of -1).
- **Two Unique New Assertions (40 to 42)**: Added `P811-1.5c` (staging whitelist check) and `P811-1.6b` (staging fail-closed check).
- **Current Physical Execution Total**: Exactly **395 executed assertions** ($393 + 2 = 395$; 100% PASS across 10 test suites).
- **Cumulative QA Catalog**: **719 items** (714 PASS, 5 NOT TESTED for live SMTP). The two newly added entries are non-duplicate and verified.
- **Narrowed Zero-PII Claims**: Confirmed zero unencrypted narrative descriptions or tenant PII are stored in audit logs via explicit assertions (`P811-6.3`, `P811-6.4`); documented that transients store only an integer counter by structural code design, and REST endpoints fail-closed before reading payloads in locked mode.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 8.1] — Owner Incident Submission Foundation (2026-11-01)

### Incident Submission Architecture (`IncidentService.php`, `IncidentHandler.php`, `IncidentController.php`)
- Implemented `IncidentService` in `wp-content/plugins/fraud-tenant-core/includes/Services/IncidentService.php`:
  - Enforced strict owner authorization: role `ft_owner` or `administrator`, capability `ft_submit_incident_report`, approved account status, and approved user profile.
  - Implemented BLK-08 Sensitive PII Encryption Gate: in production / default mode, submissions are locked fail-closed (`submission_locked_blk08`). Non-production test mode is supported via `is_test_mode_enabled()` for synthetic evaluation.
  - Enforced verified active property requirement: property must belong to owner (prevents IDOR), must not be archived (`empty($property->archived_at)`), and must have status `verified` (`Constants::PROP_STATUS_VERIFIED`).
  - Supported 7 approved incident categories: `unpaid_rent`, `property_damage`, `late_rent`, `lease_violation`, `unauthorized_pet`, `noise_complaint`, `other_violation`.
  - Date validation: format `YYYY-MM-DD`, valid calendar date, and strictly not in the future.
  - Amount validation: non-negative numeric decimal value rounded to 2 decimal places.
  - Factual narrative validation: sanitized textarea, min length 10 characters.
  - Owner attestation validation: mandatory factual attestation statement v1.0.
  - Initial status: strictly `Constants::INCIDENT_STATUS_SUBMITTED` (`'submitted'`) with `disclosable = 0` (never automatically approved or verified).
  - Safe display reference generation: `sprintf('INC-%06d', $incident_id)`.
  - Application-level append-only audit logging: action `incident.report_submitted` (`incident_report_submitted`) with safe metadata (property ID, category, date, amount, status) and zero sensitive narrative or PII written into logs, transients, or responses.
  - Fixed-window / sliding-TTL transient rate limiting (10 submissions per 300 seconds).
  - Strict owner-isolated listing (`get_owner_incident_reports`) and retrieval (`get_incident_report`) preventing cross-owner IDOR.
- Implemented `IncidentHandler` in `wp-content/plugins/fraud-tenant-core/includes/Services/IncidentHandler.php`:
  - Web form POST handler (`admin_post_ft_submit_incident`) with action-specific nonce `ft_submit_incident_action` and safe redirect to `/owner-dashboard/#my-reports`.
  - AJAX handler (`wp_ajax_ft_submit_incident`) with error mapping (403 unauthorized, 423 locked blk08, 429 rate limited, 400 bad request).
- Implemented `IncidentController` in `wp-content/plugins/fraud-tenant-core/includes/API/IncidentController.php`:
  - `POST /fraud-tenant/v1/owner/incident-reports`: 201 Created with safe reference `INC-%06d` and initial status `submitted`.
  - `GET /fraud-tenant/v1/owner/incident-reports`: 200 OK with owner's reports.
  - `GET /fraud-tenant/v1/owner/incident-reports/(?P<id>\d+)`: 200 OK for own report, 404 for unowned report (IDOR defense).
- Integrated Supporting Evidence Storage in `DocumentService.php`:
  - Added `store_incident_document( int $user_id, int $incident_id, array $file ): array` with 10MB limit, PDF/PNG/JPEG validation, randomized destination, and protected storage.
  - Updated `stream_protected_document` to authorize incident reports for submitting owner or administrative reviewer with `REVIEW_INCIDENT_REPORTS`.
- Registered `IncidentController` in `RestServer.php` and `IncidentHandler` in `Plugin.php`.
- Added incident status and category constants in `Constants.php` and added `SUBMIT_INCIDENT_REPORT` to `Capabilities::get_admin_caps()`.

### Owner Dashboard Integration (`page-owner-dashboard.php`, `DashboardService.php`)
- Added Incident Reports section (`#my-reports`) with clean PropTech styling:
  - BLK-08 notice banner explaining real-data encryption gate in production, or test-mode active badge.
  - Table of submitted reports with reference (`INC-XXXXXX`), property address, category label, incident date, formatted amount, status badge (`Submitted`), and disclosure status.
  - UI Showcase match empty state when no reports submitted.
  - Modal form (`#ftIncidentModal`) with verified property selector, category select, date input, amount, description, evidence file upload, attestation checkbox, and CSRF protection.
- Updated `DashboardService::get_owner_dashboard_data()` to query owner's incident reports and eligible verified properties.

### Visual & Automated Verification
- Headless Chrome visual verification captured at:
  - Desktop (1440x900): `phase8_1-owner-incident-desktop.png`
  - Tablet (768x1024): `phase8_1-owner-incident-tablet.png`
  - Mobile (375x812): `phase8_1-owner-incident-mobile.png`
- Automated test suite (`scratch/test_phase8_1_incident_submission.php`): **71/71 assertions PASS**.
- Historical regression suites (Phases 0–7.1.1): **282/282 assertions PASS**.
- Total combined assertions: **353 / 353 PASS (100%)**.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 7.1.1] — Privacy, Data-Minimization & Technical Evidence Correction (2026-11-01)

### Screening Request Architecture (`ScreeningService.php`, `ScreeningHandler.php`, `ScreeningController.php`)
- Hardened `ScreeningService` for privacy and data-minimization:
  - Strict least-privilege identity-free foundation: completely removed real applicant identity collection (no 9-digit SSN, no 4-digit applicant identifier, no applicant name).
  - Strictly omitted `ssn_last_four` from database persistence (column remains empty/default; zero plaintext or fragmented SSN persistence).
  - Strictly excluded SSN fragments and applicant names from API responses and audit logs (`request_reference` formatted as `REQ-XXXXXX`).
  - Corrected nonce validation: action-specific, time-limited WordPress nonce validation combined with independent capability, account-status, and ownership authorization.
  - Corrected rate limiting: fixed-window / sliding-TTL transient counter (`ft_scr_rate_{owner_id}`) expiring after 300 seconds.
  - Corrected attestation record: recorded as an unverified owner-provided statement v1.0 without legal determination or claim of independently verified tenant consent.
  - Maintained application-level append-only audit logging (`screening_request_initiated`) with note that database-level immutability has not been independently verified.
  - Active verified property requirement strictly enforced before verification status check.
  - Preserved fail-closed baseline: `tenant_identity_id` remains unset/null; `match_found = 0`; `records_count = 0`; `disclosure_level = 'locked'`.
- Hardened `ScreeningController`:
  - Removed `applicant_ssn` and `applicant_reference` from REST request schemas.
  - Excluded sensitive identity fields from all response payloads.
- Updated Owner Dashboard (`page-owner-dashboard.php`):
  - Removed applicant name and SSN inputs from `ftScreeningModal`.
  - Retained verified property selector, purpose dropdown, and owner attestation checkbox.

### Verification & Testing
- Automated Phase 7.1 verification suite (`scratch/test_phase7_1_screening_foundation.php`): **58/58 assertions PASS**.
- Historical regression suites (Phases 0–6.1.1): **224/224 assertions PASS**.
- Total combined assertions: **282 / 282 PASS (100%)**.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6.1.1] — Final Capability Enforcement Verification (2026-10-31)

### Safe Header Handling & Document Streaming Protection (`DocumentService.php`)
- Hardened `DocumentService::stream_protected_document()`:
  - If HTTP headers have already been sent, the service now aborts execution safely before `readfile()`, returning HTTP 500 without exposing local server filesystem paths.
  - Audits aborted streaming attempts with `document_streaming_aborted_headers_sent` in `wp_ft_audit_log`.
  - Confirmed normal web streaming sends all required security headers: `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`.

### Document Nonce Consistency Confirmation (`PropertyReviewController.php`, `UserReviewController.php`, `page-owner-dashboard.php`, `AdminPortal.php`)
- Verified that all document streaming URL generation functions and verification handlers use the exact same action identifier: `'ft_view_doc_' . $document_id`.
- Confirmed nonces serve as time-windowed CSRF protection tokens while capability, ownership, and role checks are independently enforced on every request.

### QA Catalog Traceability & Reconciliation
- Reconciled cumulative test catalog: 500 assertions through Phase 6 + 33 Phase 6.1 assertions + 15 Phase 6.1.1 assertions = **548 catalog assertions (543 PASS, 5 NOT TESTED)**.
- Reconciled automated regression execution count: **161 executed test assertions** across 5 test suites with 100% PASS rate.
- Preserved the 5 outstanding SMTP `NOT TESTED` items pending production mail server configuration.


### Property Review vs. Verification Capability Differentiation (`AdminReviewService.php`, `AdminPortal.php`, `PropertyReviewController.php`)
- Enforced strict least-privilege separation between `REVIEW_PROPERTIES` and `VERIFY_PROPERTIES`:
  - `Capabilities::REVIEW_PROPERTIES`: Controls access to the Property Verification Queue, property review detail views, document inspection streaming, and info requests.
  - `Capabilities::VERIFY_PROPERTIES`: Strictly enforced before the final property verification action (`verify_property`).
  - Added helper `AdminReviewService::can_verify_property( int $user_id ): bool` requiring `VERIFY_PROPERTIES` or core `manage_options`.
  - Hardened `AdminReviewService::verify_property()`: A reviewer with `REVIEW_PROPERTIES` but without `VERIFY_PROPERTIES` is strictly denied with an `unauthorized` error, and the attempt is audited with `admin_property_verify_denied` in `wp_ft_audit_log`.
  - Hardened `AdminPortal::handle_property_review_action()`: Verification actions via POST require `VERIFY_PROPERTIES` (or `manage_options`), dying fail-closed with 403 and logging to audit if attempted without capability.
  - Hardened `PropertyReviewController.php`: Rendered the "Verify Property Ownership" action form only to actors possessing `VERIFY_PROPERTIES` (or `manage_options`). Reviewers with review-only capabilities see a clear informational notice.
  - Confirmed existing administrator role retains all permissions without regression.

### Property Document Streaming Permissions Scoping (`DocumentService.php`)
- Hardened `DocumentService::stream_protected_document()`:
  - Scoped property ownership document (`object_type = 'property_ownership'`) streaming strictly to property-specific capabilities (`REVIEW_PROPERTIES`, `VERIFY_PROPERTIES`, or `manage_options`).
  - Eliminated broad dependency on `REVIEW_USER_REGISTRATIONS`: user registration reviewers cannot stream property deeds.
  - Wrapped header streaming with `if ( ! headers_sent() )` to ensure clean execution across CLI testing and production web servers.
  - Maintained bidirectional cross-owner isolation, tenant blocking, and unauthenticated 403 access denial.

### Nonce CSRF Protection Architecture
- Documented and verified that WordPress nonces (`ft_view_doc_{id}`, `ft_admin_prop_review_{id}`) serve as session- and time-windowed CSRF defense mechanisms (valid 12–24 hours for the current user) rather than consumable single-use tokens.
- Server-side authorization, user capability verification, and property-to-owner linkage are independently enforced on every request regardless of nonce validity.

### Automated Verification
- Created and executed Phase 6.1.1 Capability Enforcement Suite (`scratch/test_phase6_1_1_capability_enforcement.php`): **13 / 13 assertions PASS (100%)**.
- Executed full test suite regression across all 5 suites: **159 / 159 assertions PASS (100%)**.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---
## [Phase 6.1] — Final Security & QA Reconciliation (2026-10-31)

### Document Authorization & Ownership Linkage Cross-Verification (`DocumentService.php`)
- Hardened `DocumentService::stream_protected_document()`:
  - Added ownership linkage cross-verification: when an owner requests an ownership document (`object_type = 'property_ownership'`), the service now queries `wp_ft_properties` to confirm that the property exists AND that `property->owner_id === `. Prevents cross-property desync or parameter tampering.
  - Enforced single-action CSRF nonces (`ft_stream_doc_{id}`) on document streaming requests.
  - Bidirectional verification: Owner B is blocked from Owner A documents, and Owner A is blocked from Owner B documents.
  - Confirmed tenants and unauthenticated visitors are blocked fail-closed with HTTP 403 Forbidden.
  - Audited all access denials with `document_access_denied` events.

### File Path Security & Directory Traversal Confinement (`DocumentService.php`)
- Enforced strict canonical directory separator boundary on protected storage:
  `$normalized_base = rtrim( $canonical_base, '/\' ) . DIRECTORY_SEPARATOR;`
- Guaranteed that relative traversal (`../../`), symlink escapes, and sibling-prefix directories sharing identical base names (e.g. `/fraud-tenant-protected_evil/`) cannot bypass confinement.
- Verified zero internal server filesystem path leakage in error responses and exception messages.

### Dedicated Administrative Capabilities & Least-Privilege Scoping (`Capabilities.php`, `RoleManager.php`, `AdminPortal.php`, `AdminReviewService.php`, `PropertyReviewController.php`)
- Introduced dedicated property review capabilities:
  - `Capabilities::REVIEW_PROPERTIES = 'ft_review_properties'`
  - `Capabilities::VERIFY_PROPERTIES = 'ft_verify_properties'`
- Added dedicated capabilities to `RoleManager::get_admin_caps()` and registered to the administrator role.
- Updated `AdminPortal.php` menu registration and action dispatcher to check `REVIEW_PROPERTIES` (with fallback to `manage_options`).
- Updated `PropertyReviewController.php` to require `REVIEW_PROPERTIES`.
- Added helper `AdminReviewService::can_review_property( int $user_id ): bool` and enforced across verification, rejection, and info request actions.

### Document Version History & Review Status Lifecycle (`Constants.php`, `AdminReviewService.php`)
- Added document status constant `Constants::DOC_STATUS_SUPERSEDED = 'superseded'`.
- Hardened `AdminReviewService::verify_property()`:
  - Selects only the latest document version (`ORDER BY id DESC LIMIT 1`) and marks it `approved`.
  - Automatically updates any older prior documents for that property to `superseded`, preserving historical audit record integrity.
- Hardened `AdminReviewService::reject_property()` to mark only the latest document as `rejected`.
- Confirmed that replacement uploads create new distinct records and that prior physical files remain intact on disk without unlinking or overwriting.

### Compliance Reverification Triggers (`PropertyService.php`)
- Added unit number modification (`$unit !== $existing->unit_number`) to compliance-critical address fields.
- Verified that changing unit numbers resets verified property status back to `pending_verification` and triggers an audit log.
- Verified that non-critical metadata updates (e.g. title) preserve verified status, and owners cannot alter verification metadata through update payloads.

### Cross-User Data Isolation Verification (`DashboardService.php`)
- Verified bidirectional isolation between Property Owner A and Property Owner B: properties, documents, updates, archives, and dashboard metrics.
- Verified bidirectional isolation between Tenant A and Tenant B: identity tokens, notifications, disputes, and audit timeline feeds.

### Production Storage & External Environment Guidelines (`BLOCKERS.md`, `SETUP.md`)
- Documented `FT_PROTECTED_STORAGE_DIR` for production deployments outside webroot.
- Documented Nginx configuration for environments where `.htaccess` is unparsed.
- Documented local Apache verification vs. production server verification.

### Automated Testing & Quality Assurance
- Created and executed Phase 6.1 Security Reconciliation Suite (`scratch/test_phase6_1_security_reconciliation.php`): **33 / 33 assertions PASS (100%)**.
- Executed full test suite regression across all 4 suites: **146 / 146 assertions PASS (100%)**:
  - `test_phase6_1_security_reconciliation.php`: 33 / 33 PASS
  - `test_phase6_property_management.php`: 45 / 45 PASS
  - `test_phase4_and_5_regression.php`: 24 / 24 PASS
  - `test_phase5_1_1_reconciliation.php`: 44 / 44 PASS
- Cumulative unique test catalog: **500 total, 495 PASS, 0 FAIL, 0 BLOCKED, 5 NOT TESTED (outbound SMTP delivery awaiting production mail server credentials)**.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---
## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 5.1] — Dashboard Hardening and Release Gate (2026-10-29)

### Tenant Record Messaging Hardening (`page-tenant-dashboard.php`, `DashboardService.php`)
- Eliminated all statements claiming or implying a guaranteed clean rental history:
  - Replaced metric card tag `"Clear Record on File"` with `"No Disclosed Incidents"`.
  - Replaced empty records panel heading `"No Negative Incident Records on File"` and text `"Your rental record with Fraud Tenant is currently clear"` with:
    - Heading: `"No Disclosable Incident Records Available"`.
    - Body: `"No disclosable incident records are currently available for your account. Please note: Available platform information may be incomplete and does not certify a completely clear rental history."`
- Hardened `DashboardService::get_tenant_dashboard_data()` to strictly require `status = 'approved' AND disclosable = 1` on incident record queries. Pending, submitted, rejected, or unapproved records are never disclosed or counted.

### Address Verification Label Corrections (`page-tenant-dashboard.php`)
- Relabeled residential address fields:
  - Current residence relabeled to `"Current Address on File"`.
  - Previous residence relabeled to `"Previous Address Reported During Registration"`.
  - Removed all misleading "verified" address claims or badges; address verification is not inferred from account approval.
  - Relabeled identity security badge to `"Identity Matching Token Active (HMAC Protected)"`.

### Phase 7 SSN Preview Immunity (`page-owner-dashboard.php`, `dashboard.js`)
- Converted the owner dashboard SSN search input to `disabled readonly aria-disabled="true"` with placeholder `"XXX-XX-XXXX (Preview — Screening Engine in Phase 7)"`.
- Converted the search action to `type="button" data-ft-modal-target="ftScreeningModal"`, triggering the informational compliance modal directly.
- Removed SSN input event listeners and form submission interceptors from `assets/js/dashboard.js`. Verified zero SSN values are accepted, processed, serialized, logged, or transmitted, and zero screening requests are generated in `wp_ft_screening_requests`.

### Secure Tenant Activity Timeline (`DashboardService.php`, `page-tenant-dashboard.php`)
- Refined audit query in `DashboardService::get_tenant_dashboard_data()`:
  - Restricted query strictly to `object_type = 'user' AND object_id = %d` where the target subject is the current tenant.
  - Restricts actions strictly to a safe user-facing allowlist: `admin_application_approved`, `auth_otp_verified`, `auth_tenant_registered`, `user_profile_updated`.
  - Queries ONLY `id, action, created_at` from `wp_ft_audit_log`, excluding `details_json`, `ip_address`, `user_agent`, and admin notes.
  - Returns human-friendly, escaped display titles.
  - When no allowlisted events exist, displays a neutral empty state (`"No recent activity recorded for your account."`) instead of hardcoded fallback events.

### Real Membership and Owner Data Scoping (`DashboardService.php`, `page-owner-dashboard.php`)
- Enforced strict `owner_id = %d` scoping on all owner queries (properties, searches, reports).
- Ensured recent searches display only masked `••••-XXXX` last 4 digits, never HMAC token.
- Verified real membership plan data (`Free Plan`, `Standard Monthly`, `Standard Annual`) with zero fabricated active benefits.

### Admin Review Historical Continuity (`UserReviewController.php`, `AdminReviewService.php`)
- Verified that repeated rejection or info-request actions preserve an append-only application-level audit history in `wp_ft_audit_log` without overwriting history.
- Enhanced the admin review history timeline in `UserReviewController.php` to cleanly display and highlight past reviewer notes and reasons.

### Testing & Screenshots
- Executed 34 Phase 5.1 hardening assertions (**34/34 PASS**).
- Executed 24 Phase 4 & 5 regression assertions (**24/24 PASS**).
- Recaptured Headless Chrome desktop (1440x1050) and mobile (390x844) screenshots reflecting hardened UI states.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 5] — Owner and Tenant Dashboards (2026-10-29)

### Separation of Shells & Dashboard Architecture (`header.php`, `footer.php`, `dashboard.css`, `dashboard.js`)
- Completely decoupled the authenticated application shell from the public marketing website: suppressed the public marketing header and oversized marketing footer on authenticated dashboard pages.
- Built sticky application top bar (`.dashboard-topbar`) featuring brand logo, role title ("Property Owner Dashboard" / "Tenant Dashboard"), notification bell with unread indicator badge, user capsule with avatar initials/name/role, and secure sign-out action.
- Built responsive mobile navigation drawer with hamburger toggle button and outside-click dismissal.
- Built minimal, compliant dashboard footer (`.auth-footer`) presenting copyright and direct legal links (Terms of Service, Privacy Policy, Tenant Rights, Support).

### Dashboard Service & Real Database Integration (`DashboardService.php`)
- Built `DashboardService` with single-responsibility queries retrieving only authentic database records with zero mock data.
- `get_owner_dashboard_data(int $owner_id)`: Queries properties from `wp_ft_properties`, screening searches from `wp_ft_screening_requests`, incident reports from `wp_ft_incident_reports`, membership subscription from `wp_ft_subscriptions` / `wp_ft_membership_plans`, unread notifications, and user profile.
- `get_tenant_dashboard_data(int $tenant_id)`: Queries disclosed incident records, disputes, unread notifications, residential addresses, and recent audit activity timeline from `wp_ft_audit_log`.
- `update_safe_profile(int $user_id, array $data)`: Safe profile field updates (phone number) protected by user-specific CSRF nonces (`ft_update_profile_<user_id>`) and IDOR ownership verification.
- Protected email, role, and SSN tokenization from silent modification.

### Strict Access Gate & Role Isolation (`DashboardService::enforce_access()`, `AuthService::login()`)
- Unauthenticated requests to `/owner-dashboard/` or `/tenant-dashboard/` strictly redirect to `/login/`.
- Unverified accounts (`email_unverified`) redirect to `/verify-otp/`.
- Accounts in review (`pending_approval`, `info_requested`) redirect to holding page `/account-status/`.
- Suspended or rejected accounts have sessions terminated and redirect to `/login/?error=status`.
- Role boundaries strictly enforced: `ft_owner` attempting to view `/tenant-dashboard/` redirects to `/owner-dashboard/`; `ft_tenant` attempting to view `/owner-dashboard/` redirects to `/tenant-dashboard/`. Administrators possess inspection privileges.
- Updated `AuthService::login()` to route approved owners to `/owner-dashboard/` and approved tenants to `/tenant-dashboard/`.
- Updated holding page `page-account-status.php` with direct CTA for approved users: "Go to Your Dashboard →".

### Property Owner Dashboard (`page-owner-dashboard.php`)
- Pixel-matched the approved design showcase (`Fraud Tenant Product UI Showcase.png`):
  - Left Navy sidebar (`#14263E`) with active teal `Dashboard` state and navigation items (Dashboard, My Properties, Search Tenants, My Reports, Membership, Notifications, Profile Settings).
  - Welcome greeting banner with owner first name and "+ Add Property" button triggering Phase 6 informational modal.
  - 4-column Stat Metrics bar: My Properties (real count + verification tag), Tenant Searches (real count), My Reports (real count), Membership ("Free Plan" badge + "Upgrade" pill).
  - "Search Tenant Rental History" box with formatted `XXX-XX-XXXX` input, lock indicator, Search button, and Phase 7 compliance modal.
  - Two-column grid: "My Properties" table displaying real street, city, state, zip, and status badge (`pending_verification` / `verified`), Property Verification Notice; "Recent Searches" table; "Upgrade Your Membership" promo card with checklist; and Account profile card.

### Tenant Dashboard (`page-tenant-dashboard.php`)
- Pixel-matched the approved design showcase:
  - Left Navy sidebar with active Dashboard, My Records, My Disputes, Notifications, Profile Settings, and "Know Your Rights" FCRA info in the footer.
  - Welcome greeting banner with tenant first name.
  - 3-column Stat Metrics bar: My Records (disclosed count or clean record status), My Disputes (real count), Notifications (account alerts).
  - Two-column grid: "Recent Activity" timeline with real audit log timestamps; "Residential Information on File" displaying current and previous addresses; "My Rental Records" with clean status state; "Your Rights Matter" card with "View My Records" CTA; and FCRA Dispute Resolution card.

### Account Reactivation Implementation (`AdminReviewService.php`, `AdminPortal.php`, `UserReviewController.php`)
- Added `AdminReviewService::reactivate_account(int $user_id, int $reviewer_id, string $reason)` enabling administrators to restore suspended accounts back to `approved` status.
- Added "Reactivate Suspended Account" form and button in `UserReviewController.php` when inspecting suspended applicants.
- Handled `reactivate` action in `AdminPortal.php` and recorded `admin.account_reactivated` in the audit log.

### Testing & Verification
- Executed 45 Step 0 security preflight assertions (**45/45 PASS**).
- Executed 32 Phase 5 functional suite assertions covering routing, role boundaries, data isolation, IDOR defense, and template integrity (**32/32 PASS**).
- Executed 35 Phase 4 regression assertions (**35/35 PASS**).
- Captured Headless Chrome screenshots across desktop (1440x900) and mobile (375x812) for both Owner and Tenant dashboards.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 4] — Administrative User Review and Approval Portal (2026-10-29)

### Administrative Portal Shell & Review Queue (`AdminPortal.php`, `UserReviewController.php`)
- Registered top-level WordPress admin menu `Fraud Tenant` with `dashicons-shield` icon, protected by capability `Capabilities::REVIEW_USER_REGISTRATIONS` (`ft_review_user_registrations`).
- Built pending applications queue table (`#ft-admin-queue`) with status filter tabs (All Active, Pending Review, Info Requested, Approved, Rejected, Suspended) featuring live database counter badges.
- Built account type filter (All, Property Owners, Tenants), real-time applicant search input (matches name, email, phone, login), pagination (20 items/page), and empty states.
- Implemented color-coded badges matching the approved Design System (Navy `#14263E`, Teal `#0E9488`, Amber `#D97706`, Red `#DC2626`).

### Detailed Applicant Review Screen (`UserReviewController::render_detail()`)
- Built two-column responsive interface presenting only real collected and stored registration data.
- **Property Owner View**: Legal name, contact details, primary rental property address (street, unit, city, state, zip), property verification badge, attached ownership proof document metadata card, and separate property ownership decision box.
- **Tenant View**: Legal name, contact details, current residential address, and optional previous rental history (street, city, state, zip).
- **Compliance Audit Timeline**: Real-time activity history displaying actor, target, timestamp, and sanitized details JSON.

### Secure Private Document Streaming (`DocumentService.php`, `AdminPortal.php`)
- Implemented authorized streaming controller `DocumentService::stream_protected_document()` invoked via `admin_post_ft_view_document`.
- Enforced reviewer capability check (`REVIEW_USER_REGISTRATIONS` or `manage_options`) on every request.
- Verified one-time action nonces (`ft_view_doc_<id>`).
- Enforced strict database lookup from `wp_ft_documents` and directory canonicalization via `realpath()`.
- Implemented path traversal trapping (`strpos($canonical_file, $canonical_base) === 0`) rejecting any relative traversal (`../`).
- Set safe security headers (`X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Expires: 0`).
- Recorded `admin.document_viewed` in `wp_ft_audit_log` without exposing physical paths or sensitive contents.

### Review Actions & Strict Property Separation (`AdminReviewService.php`)
- **Approve Application**: Transitions status to `approved`, activates platform login access, logs `admin.application_approved`, and dispatches approval email via `wp_mail()`.
- **Reject Application**: Requires mandatory meaningful rejection reason (>= 10 characters), transitions status to `rejected`, stores reason in `wp_ft_user_profiles.rejection_reason`, logs `admin.application_rejected`, and dispatches rejection email.
- **Request Additional Information**: Requires clear request explanation (>= 10 characters), transitions status to `info_requested`, stores request note in profile, keeps account restricted from dashboard and screening features, logs `admin.info_requested`, and dispatches notification email.
- **Strict Separation of Account and Property Decisions**: Approving an owner's account deliberately leaves attached primary properties in `pending_verification` status. Separate property verification action (`verify_property()` / `reject_property()`) updates `wp_ft_properties.status` (`verified` / `rejected`) and logs `admin.property_verified` without modifying user account status.

### Database Schema Version 1.2.0
- Added `Constants::STATUS_INFO_REQUESTED = 'info_requested'`.
- Updated `Constants::DB_VERSION` to `1.2.0`.
- Applied idempotent `dbDelta()` migration adding `admin_notes (text)` column to `wp_ft_user_profiles`.
- Updated `page-account-status.php` holding page to present clear instructions and reviewer request notes when an account is in `info_requested` status.

### Testing & Verification
- Executed 23 Step 0 preflight assertions covering nonces, open redirects, logout, role isolation, and lifecycle guards (**23/23 PASS**).
- Executed 35 Phase 4 integration assertions covering queue filtering, search, applicant detail fidelity, document traversal protection, state transitions, property separation, and audit logging (**35/35 PASS**).
- Captured and verified 4 Headless Chrome screenshots across desktop (1440px) and mobile (375px) for queue and review views.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 3.2] — Phase 3 Final Release-Gate Audit & Hardening (2026-10-29)

### Privacy and Terms Consent Alignment
- **`page-register.php`**:
  - Simplified Property Owner Terms of Service checkbox label to strictly represent agreement to the Terms of Service.
  - Simplified Property Owner Privacy Policy checkbox label to acknowledge the Privacy Policy, removing premature wording regarding blanket FCRA permissible purpose requirements.
  - Simplified Tenant Terms of Service and Privacy Policy checkbox labels to standard agreement and acknowledgement, removing premature FCRA dispute claims.
- **`AuthService.php`**:
  - Aligned server-side validation error messages to neutral, standard wording (`"You must agree to the Terms of Service to register."` and `"You must acknowledge the Privacy Policy to register."`).
  - Documented that screening-specific permissible purpose certification belongs to Phase 7 and requires US legal review.

### Protected Document Storage Hardening
- **`DocumentService.php`**:
  - Added support for `FT_PROTECTED_STORAGE_DIR` configuration constant, allowing production environments to place the protected document repository completely outside the web server root.
  - Retained triple-layer server protections (Apache `.htaccess` `Require all denied`, IIS `web.config`, `index.php` 403 exit).
  - Documented web server deployment requirements for Apache, Microsoft IIS, and Nginx.

### OTP Invalidation on Resend
- **`OtpService.php`**:
  - Relocated unconsumed OTP cancellation query to execute unconditionally on every code generation call, ensuring any prior unconsumed verifiers are invalidated upon resend.

### Quality Assurance & Release Gate
- Executed 32 automated integration test assertions via `scratch/run_release_gate_audit.php` covering privacy wording, OTP lifecycle, login throttling, status restrictions, role isolation, document access control, password recovery, and public regression (`Passed: 32 | Failed: 0`).
- Recommendation: **`READY FOR PHASE 4`**.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 3.1] — Phase 3 Correction: Distinct Registration Forms & Secure Document Storage (2026-10-28)

### Distinct Role-Specific Registration Experiences
- **Property Owner Registration Form (`#ft-register-owner-form`)**:
  - Full legal name (first/last), email address, phone number, password & confirmation with visibility eye toggles.
  - Primary rental property address details: street address, city, US state selection, and 5-digit ZIP code.
  - Property ownership verification document upload: supports genuine PDF, PNG, and JPEG files under 10MB.
  - Mandatory separate check-boxes for Terms of Service acceptance and Privacy Policy / FCRA permissible purpose acknowledgement.
  - Automatically provisions records across WordPress users (`ft_owner`), `wp_ft_user_profiles`, `wp_ft_properties` (status `pending_verification`), and `wp_ft_documents` (permission `private`).
- **Tenant Registration Form (`#ft-register-tenant-form`)**:
  - Full legal name (first/last), email address, phone number, password & confirmation with visibility eye toggles.
  - Current residential street address, city, US state selection, and 5-digit ZIP code.
  - Conditional previous rental history section: reveals previous street, city, state, and ZIP when opted in.
  - Strictly excludes Date of Birth collection per directives.
  - Mandatory separate check-boxes for Terms of Service and Privacy Policy agreements.
  - Automatically provisions records across WordPress users (`ft_tenant`) and `wp_ft_user_profiles`.

### Secure Document Management Pipeline (`DocumentService.php`)
- Built `FraudTenant\Services\DocumentService` located in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`.
- Established protected storage root at `wp-content/uploads/fraud-tenant-protected/`.
- Enforced multi-layered web access protection:
  - Apache `.htaccess` with `Order Deny,Allow`, `Deny from all`, `<IfModule mod_authz_core.c> Require all denied </IfModule>`, and explicit script extension execution blocking.
  - Directory index traversal prevention via `index.php` returning `HTTP 403 Forbidden`.
  - Microsoft IIS `web.config` with `<deny users="*" />`.
  - Direct HTTP verification: confirmed `curl.exe` returns `HTTP/1.1 403 Forbidden` on direct web access.
- Server-side MIME verification using PHP `finfo`, strict extension checking (`pdf`, `png`, `jpg`, `jpeg`), and 10MB size limit.
- Unguessable randomized file naming: `doc_<token>_<timestamp>.<ext>`.
- Zero public document URL exposure.

### Database Schema Version 1.1.0
- Updated `Constants::DB_VERSION` to `1.1.0`.
- Applied idempotent `dbDelta()` migration adding columns to `wp_ft_user_profiles`:
  - `prev_address_line1 (varchar(255))`
  - `prev_city (varchar(100))`
  - `prev_state (varchar(50))`
  - `prev_postal_code (varchar(20))`
  - `terms_accepted_at (datetime)`
  - `privacy_accepted_at (datetime)`

### Testing & Verification
- Executed 43 dedicated automated integration test assertions covering all field validation, role assignment, file upload constraints, protected storage, and account status transitions (`Passed: 43 | Failed: 0`).
- Captured Headless Chrome screenshots for Desktop Owner, Desktop Tenant, Mobile Owner, and Mobile Tenant registration views.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 3] — Registration, Email OTP, Login & Password Recovery (2026-10-28)

### Core Backend Services (`fraud-tenant-core`)
- **`OtpService` (`includes/Services/OtpService.php`)**:
  - Implemented cryptographically secure 6-digit numeric OTP generator using `random_int(100000, 999999)`.
  - Enforced HMAC-SHA-256 verifier hashing via `SecurityService::hash_otp()` using dedicated server secret `FT_OTP_SECRET_KEY`.
  - Built fail-closed validation when `FT_OTP_SECRET_KEY` is undefined or empty.
  - Implemented 60-second cooldown enforcement (`resend_available_at`), 10-minute challenge expiry (`expires_at`), max 5 verification attempts, constant-time `hash_equals()` verification, and single-use challenge consumption (`consumed_at`).
  - Added user lifecycle progression transitioning `approval_status` from `email_unverified` to `pending_approval` upon successful OTP confirmation.
- **`AuthService` (`includes/Auth/AuthService.php`)**:
  - Built user registration pipeline supporting `ft_owner` and `ft_tenant` roles, with default status `email_unverified` in `wp_ft_user_profiles`.
  - Added anti-enumeration response logic returning generic success message on duplicate email registration attempts without revealing existing account presence.
  - Added brute-force lockout defense enforcing a 15-minute temporary lockout after 5 consecutive failed login attempts from an IP.
  - Implemented lifecycle status authentication gateway:
    - Users with `email_unverified` redirected to `/verify-otp/`.
    - Users with `pending_approval` redirected to `/account-status/`.
    - Users with `rejected` or `suspended` status blocked with explanatory message.
    - System administrators retain immediate, unrestricted access.
  - Added password recovery request workflow with anti-enumeration protection and core WordPress reset key generation.
  - Implemented secure password reset endpoint using constant-time key validation.
- **`AuthHandler` (`includes/Auth/AuthHandler.php`)**:
  - Registered `admin_post_*` and `admin_post_nopriv_*` hooks for registration, OTP verification, OTP resend, login, logout, password recovery, and password reset.
  - Enforced cryptographic CSRF nonce verification on all POST actions.
  - Implemented session-isolated flash messaging via transients.

### Custom Theme Templates & UI (`fraud-tenant-theme`)
- **Showcase Fidelity & Auth Templates**:
  - `page-login.php`: Split auth card matching showcase mockup, left form column with email and eye toggle password inputs, right photographic column with modern suburban home visual and floating glassmorphism card ("Safer Tenancies Stronger Communities").
  - `page-register.php`: Dual role selector cards (Property Owner vs. Tenant with SVG icons and active state), first/last name, email, password & confirm with visibility toggle, terms checkbox, and full-width teal "Continue" CTA.
  - `page-verify-otp.php`: Envelope icon in teal circle, target email display, 6 individual numeric inputs with auto-advance and clipboard paste support, Verify OTP CTA, and live countdown timer toggling to "Resend Code".
  - `page-forgot-password.php`: Centered card with email input and generic dispatch confirmation.
  - `page-reset-password.php`: Secure key & login parameter verification, new password inputs with eye toggles, and update submission.
  - `page-account-status.php`: Holding page for pending/suspended/rejected accounts with status badge, 4-step progress indicator, user account details grid, and sign-out button.
- **Separation of Shells**:
  - Suppressed marketing header and 4-column footer on authentication pages, displaying a focused brand header and minimal legal footer.
- **Client-Side UX & Responsive Layouts (`assets/js/main.js`, `assets/css/main.css`)**:
  - Interactive password visibility eye toggles.
  - Dual role selection cards with synced hidden input.
  - 6-digit OTP input auto-advance, backspace navigation, numeric sanitization, and clipboard paste support.
  - 60-second live countdown timer for OTP resend.
  - Fully responsive styling verified down to 320px with zero horizontal scroll.

### Testing & Verification
- **Automated Integration Test Suite**: 30 comprehensive assertions executed via PHP CLI verifying registration, role assignment, initial unverified status, anti-enumeration, login blocking, OTP hashing, cooldowns, single-use replays, status transitions, administrator bypass, password reset keys, and password updates (`Passed: 30 | Failed: 0`).
- **Visual Capture**: Captured and verified desktop and mobile (375x812) Headless Chrome screenshots across all auth views.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 2.1] — Phase 2 Final Audit & Corrective Review (2026-10-28)

### UI Alignment & Showcase Fidelity
- **UI Match with Design Showcase (`Fraud Tenant Product UI Showcase.png`)**:
  - Re-architected `front-page.php` and `assets/css/main.css` to match the approved design showcase layout precisely:
    - Integrated photographic hero showcase composition with floating badges and an explicit `Illustrative Sample Preview` watermark.
    - Added bottom organic curved wave divider to the hero section.
    - Updated How It Works section with 4-step layout and directional transition arrows.
    - Implemented dual audience split cards for Property Owners and Tenants with dedicated card visuals (`owner-card-visual.png` and `tenant-card-visual.png`).
    - Implemented 4-column value proposition grid for "Why Choose Fraud Tenant?".
    - Built unified 3-column contact card featuring suburban residential photography (`contact-house-visual.png`), 3-input row form layout, and light teal-tinted sidebar details.
    - Updated header actions with crisp `Sign In` text link and `Get Started` teal CTA.

### Public Content & Legal Claims Audit (Task 1)
- Removed unsupported statistics and unverified claims ("Trusted by thousands" replaced with truthful architectural description; confirmed zero fabricated percentages).
- Added explicit `Illustrative Sample Preview` badge to hero applicant preview card.
- Clarified that screening, incident documentation, and disputes are planned capabilities subject to account verification and permissible purpose certification.
- Removed invented contact information (fake street address and phone numbers) in favor of genuine support channels (`support@fraudtenant.com`, Digital Helpdesk, and National US Service Area).
- Verified legal draft warnings on Privacy Policy and Terms of Service, and explicit disclaimer that platform information does not constitute formal legal counsel.

### Contact Form Privacy & Architecture Hardening (Task 2)
- Migrated contact enquiry handler to `FraudTenant\Services\ContactEnquiryHandler` inside the custom plugin `fraud-tenant-core` to ensure survival across theme switches.
- Implemented multi-field SSN pattern detection across all free-text fields (`enquiry_name`, `enquiry_subject`, `enquiry_message`).
- Enforced rejection prior to `wp_mail()` dispatch without logging sensitive candidates or echoing user input in URL parameters.
- Replaced spoofable IP header parsing with reliable `$_SERVER['REMOTE_ADDR']` for rate limiting.

### Multi-Device Responsive QA (Task 3)
- Verified and captured headless Chrome screenshots across 5 viewports:
  - 1440 × 900 (`phase2-audit-1440x900-home.png`)
  - 1024 × 768 (`phase2-audit-1024x768-home.png`)
  - 768 × 1024 (`phase2-audit-768x1024-home.png`)
  - 375 × 812 (`phase2-audit-375x812-home.png`)
  - 320 × 700 (`phase2-audit-320x700-home.png`)
  - Subpages at 1440 × 900 (`/contact/`, `/for-property-owners/`, `/for-tenants/`, `/tenant-rights/`)
- Confirmed zero horizontal overflow (`scrollWidth <= innerWidth`) and zero PHP/console errors.

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

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

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

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

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

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

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

- **Phase 9.1 Isolated Runtime Test Closure (`scratch/test_phase9_1_runtime_closure.php`)**:
  - Total assertions executed: **16 / 16 PASS (100%)**.
  - Covered:
    - **Test A (Administrator Entitlement & Capability Separation)**: Verified `is_eligible_owner()` returns false, `get_owner_plan()` resolves to Free, feature access (`full_reports`, `incident_reporting`) denied, property limit returns safe default 1 (never unlimited -1), admin capability `ft_manage_membership_plans` preserved, and injected active subscription row fails closed.
    - **Test B (Deactivated Standard Plan with Existing Subscription)**: Verified active subscription on inactive tier (`is_active = 0`) retains Standard entitlements while `current_period_end` is in future; `cancel_at_period_end = 1` retains entitlement until expiration; expired subscription fails closed to Free; inactive plan excluded from `get_plans(true)` but present in `get_plans(false)`; active plans continue to resolve; missing/null period end fails closed to Free.
    - **Test C (Operational Baseline Invariance)**: Verified 0 subscriptions, 3 operational plans, and 12 legacy disputes preserved with zero database pollution.
- **Reports & Artifacts**:
  - Authored comprehensive runtime closure report: `reports/phase9/phase9_1_runtime_test_closure.md`
  - Created user-facing artifact: `phase9_1_runtime_test_closure.md`

---

## [Phase 0] — Repository Audit and Implementation Preparation (2026-10-28)

### Added
- Initialized local Git repository tracking in `c:\xampp\htdocs\fraudtenant`.
- Configured `.gitignore` to strictly exclude sensitive files (`wp-config.php`, uploads, logs, database dumps, OS files).
- Created core documentation suite (`AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, `CHANGELOG.md`).
- Cataloged approved design assets (`Fraud Tenant Product UI Showcase.png`).
- Verified local development URL (`http://localhost/fraudtenant`) returning HTTP 200 OK.

## 2026-11-04 — Claude completion pass (Stripe 9.2.1-B/9.3/9.4, settings, admin)
- Webhooks rewritten: authoritative Stripe re-fetch; owner/customer/price/plan/mode verification; INSERT IGNORE claim; stale-processing reclaim; 409 while in progress; minimized payloads; sanitized errors; checkout.session.* (incl. async/expired), invoice.paid/payment_failed.
- Entitlements are mode-scoped (Test subscriptions never grant access while Live is active); billing-QA account flag for production Test mode.
- Checkout: persisted logical attempts (stable idempotency key on retry), strict session reuse, verified return reconciliation; lock timestamps UTC.
- Incident reporting requires Standard by default (BLK-08 lock still evaluated first).
- Password reset authorization moved from URL to HttpOnly SameSite=Strict cookie (hashed at rest, single-use, all sessions destroyed).
- Email change now requires a code sent to the new address; old address notified.
- Admin Command Center: correct KPIs, per-section capabilities (pre-output 403), search/filters/pagination, subscription sync, webhook event log.
- Owner dashboard: membership status/renewal/payment history, verified checkout banner, incident upgrade gate; WP toolbar hidden for members; theme assets v1.0.8.
- `scratch/` and `tests/` denied over HTTP. No schema change (1.7.0).
- Evidence: reports/claude_completion/.

## 2026-11-04 — Tenant identity on incidents & owner screening (schema 1.8.0)
- Incidents capture the reported tenant: HMAC SSN token + last 4 + AES-256-GCM-encrypted name (full SSN never stored).
- Admin "Release to Owner Screening" control for approved reports; withdrawal is immediate.
- Owner identity search (name + SSN, consent, permissible purpose, verified property): Free = count, Standard = summaries; tenant inquiry notifications; dispute state shown.
- Compliance Gates panel (BLK-04 status, BLK-08/BLK-03 audited attestations); all BLK-08 checks routed through ComplianceService::is_data_gate_open().
- Hourly Stripe subscription reconciliation (missed-webhook safety net).
- Dedicated `qa_test_admin` for automated tests. Details: reports/claude_completion/02_TENANT_IDENTITY_AND_SCREENING.md
