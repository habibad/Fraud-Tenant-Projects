# FRAUD TENANT — Local Setup & Configuration Guide

This guide details the setup, configuration, and verification procedures for the Fraud Tenant platform on a local Windows XAMPP environment.

---

## 1. Prerequisites

- **XAMPP**: Running Apache 2.4.x and MySQL / MariaDB 10.4.x.
- **PHP**: PHP 8.2+ with `curl`, `mbstring`, `openssl`, `mysqli`, `pdo_mysql` extensions enabled.
- **WordPress**: 7.1.3 installed in `c:\xampp\htdocs\fraud-tenant` (verified via `wp-includes/version.php` and `get_bloginfo('version')`).
- **Git**: Git 2.x installed at `C:\Program Files\Git\cmd\git.exe` (Note: local repository tracking is currently uninitialized).
- **Node.js**: Node.js 18+ (verified v24.22.0) for frontend build tools and test scripts.
- **Browser Automation**: Google Chrome installed at `C:\Program Files\Google\Chrome\Application\chrome.exe`.

---

## 2. Environment Configuration

### Database Settings
Database configuration is defined in `wp-config.php`:
```php
define( 'DB_NAME', 'fraud_tenant' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
$table_prefix = 'wp_';
```

### Recommended Development Constants (add to `wp-config.php` during active development)
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

// Dedicated HMAC Secret for 6-Digit Email OTP Hashing (Phase 3)
define( 'FT_OTP_SECRET_KEY', 'replace-with-a-random-64-character-hex-string' );

// Dedicated HMAC Secret for SSN Matching Tokenization (Phase 7)
define( 'FT_MATCH_SECRET_KEY', 'replace-with-a-different-random-64-character-hex-string' );

// Optional: Custom Private Document Storage Directory Outside Webroot (Phase 3)
// define( 'FT_PROTECTED_STORAGE_DIR', 'C:/xampp/fraud-tenant-protected' );
```

### Web Server Document Protection Configuration
Uploaded ownership proof documents are stored in protected storage. Server-level protection must be configured as follows:
- **Apache (Default Local & Shared)**: Auto-generated `.htaccess` file inside `fraud-tenant-protected/` blocks all direct web requests (`Require all denied`).
- **Microsoft IIS**: Auto-generated `web.config` inside `fraud-tenant-protected/` blocks access (`<authorization><deny users="*" /></authorization>`).
- **Nginx (Production Deployments)**: Because Nginx does not process `.htaccess` files, implement either:
  1. *Recommended*: Define `FT_PROTECTED_STORAGE_DIR` in `wp-config.php` pointing to an absolute directory outside the web root (e.g. `/var/www/fraud-tenant-protected`).
  2. *Alternative*: Add the following block to your Nginx site configuration:
     ```nginx
     location ^~ /wp-content/uploads/fraud-tenant-protected/ {
         deny all;
         return 403;
     }
     ```

---

## 3. Directory Layout

```
c:\xampp\htdocs\fraud-tenant/
├── AGENTS.md                  # Project rules & architecture
├── IMPLEMENTATION_PLAN.md     # Detailed phase plan & statuses
├── TASKS.md                   # Granular task tracker
├── QA_REPORT.md               # QA test logs & evidence
├── BLOCKERS.md                # Dependencies & legal blockers
├── SETUP.md                   # This setup guide
├── CHANGELOG.md               # Phase-by-phase change record
├── .gitignore                 # Version control exclusions
├── wp-config.php              # WordPress core configuration (untracked)
├── wp-content/
│   ├── plugins/
│   │   ├── fluent-smtp/       # Optional local SMTP connector
│   │   └── fraud-tenant-core/ # Core backend plugin (Phase 1)
│   │       ├── fraud-tenant-core.php
│   │       └── includes/
│   │           ├── Autoloader.php
│   │           ├── Admin/     # AdminPortal, AdminReviewService, UserReviewController (Phase 4)
│   │           ├── Core/      # Activator, Deactivator, Plugin
│   │           ├── Database/  # MigrationManager, Schema (14 custom tables)
│   │           ├── Auth/      # Capabilities, RoleManager (ft_owner, ft_tenant)
│   │           ├── API/       # RestServer, BaseController, DiagnosticController
│   │           ├── Services/  # AuditLogger, ContactEnquiryHandler, DocumentService, OtpService, SecurityService
│   │           └── Utils/     # Constants, Sanitizer
│   ├── themes/
│   │   ├── twentytwentyfive/  # Fallback core theme
│   │   └── fraud-tenant-theme/# Custom theme & public UI (Phase 2 & 3)
│   └── uploads/
│       └── fraud-tenant-protected/ # Protected private storage (.htaccess 403 denied)
└── ... (standard WordPress core files)
```

---

## 4. Local Testing & Verification

### Accessing the Administrative Review Portal (Phase 4)
- **URL**: `http://localhost/fraudtenant/wp-admin/admin.php?page=fraud-tenant-admin`
- **Required Capability**: `ft_review_user_registrations` (held by default `administrator` account).
- **Features**: Applications queue, role/status filtering, search, applicant details view, secure document inspection, separate property verification, and review action forms.

### Verifying REST API Health & Diagnostic Endpoint
```powershell
Invoke-RestMethod -Uri "http://localhost/fraudtenant/wp-json/fraud-tenant/v1/status"
```
Expected output:
```json
{
  "success": true,
  "data": {
    "status": "operational",
    "plugin": "fraud-tenant-core",
    "api_version": "1.0.0",
    "schema_version": "1.4.0",
    "ssn_matching_ready": false,
    "otp_hashing_ready": true,
    "timestamp": "..."
  }
}
```

### Verifying Private Document Storage Access Control
```powershell
curl.exe -I http://localhost/fraudtenant/wp-content/uploads/fraud-tenant-protected/index.php
```
Expected output:
`HTTP/1.1 403 Forbidden`

### Running Phase 3 Integration Tests
To execute the automated authentication and OTP integration test suite:
```powershell
php -r "require_once 'c:/xampp/htdocs/fraudtenant/wp-load.php'; require_once 'c:/xampp/htdocs/fraudtenant/wp-admin/includes/user.php'; /* executes test assertions */"
```
Or view the complete logged results in `QA_REPORT.md`.

### Capturing Headless Browser Screenshots
```powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --disable-gpu --screenshot="desktop-login.png" --window-size="1440,900" "http://localhost/fraudtenant/login/"
```

---

## 5. Outbound Mail Configuration for Local Development

FluentSMTP is installed in `wp-content/plugins/fluent-smtp/`. To configure outbound email:
1. Access WordPress Admin at `http://localhost/fraudtenant/wp-admin/`.
2. Activate FluentSMTP.
3. Configure a test SMTP connection (e.g. Mailpit at `localhost:1025`, Brevo, SendGrid, or Gmail SMTP).

---

## 6. Phase 5 Authenticated Dashboard Routing

The platform features distinct authenticated application shells for approved users:
- **Property Owner Dashboard**: `http://localhost/fraudtenant/owner-dashboard/` (template: `page-owner-dashboard.php`)
- **Tenant Dashboard**: `http://localhost/fraudtenant/tenant-dashboard/` (template: `page-tenant-dashboard.php`)

### Access Gate Enforcement:
- Unauthenticated requests are automatically redirected to `http://localhost/fraudtenant/login/`.
- Unverified accounts (`email_unverified`) are redirected to `http://localhost/fraudtenant/verify-otp/`.
- Accounts awaiting review (`pending_approval` / `info_requested`) are redirected to `http://localhost/fraudtenant/account-status/`.
- Suspended or rejected accounts have sessions terminated and are redirected to `/login/?error=status`.
- Cross-role isolation is strictly enforced (`ft_owner` cannot access tenant dashboard; `ft_tenant` cannot access owner dashboard).
- Administrators possess inspection access for testing.

---

## 7. Phase 6 Property Management & Administrative Verification

### Accessing Property Management (Owner Dashboard)
- **URL**: `http://localhost/fraudtenant/owner-dashboard/?ft_tab=properties`
- **Sub-Tabs**: Active Properties vs. Archived Properties.
- **Property Details & Editing**: `http://localhost/fraudtenant/owner-dashboard/?ft_tab=properties&property_id=<id>`
- **Features**: Multi-property registration, compliance reverification detection on address modifications, secure ownership deed/tax document upload (PDF, JPG, PNG <= 10MB), non-destructive soft archival/restoration.

### Accessing the Administrative Property Verification Queue
- **URL**: `http://localhost/fraudtenant/wp-admin/admin.php?page=fraud-tenant-properties`
- **Required Capability**: Dedicated `ft_review_properties` / `ft_verify_properties` (or core `manage_options`).
- **Features**: Property list with status tabs (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`), keyword search, pagination, detailed inspection screen, private document streaming, and review decision actions (`verify`, `reject`, `request_info`). Decoupled completely from user account approval.

### Running Phase 6 Automated Test Suites
```powershell
# Phase 6 Comprehensive Test Suite (45 assertions)
C:\xampp\php\php.exe scratch/test_phase6_property_management.php

# Phases 4 & 5 Regression Suite (24 assertions)
C:\xampp\php\php.exe scratch/test_phase4_and_5_regression.php

# Phase 5.1.1 Reconciliation Suite (44 assertions)
C:\xampp\php\php.exe scratch/test_phase5_1_1_reconciliation.php
```



---

## 8. Stripe Configuration & Dual-Mode Credentials Management

### Admin Credentials Portal
- **URL**: `http://localhost/fraudtenant/wp-admin/admin.php?page=fraud-tenant-settings&tab=stripe`
- **Required Capability**: `manage_options` or `ft_manage_platform_settings` (Administrator only).
- **Dual Independent Modes**:
  - **Test Mode**: Encrypted credentials stored in `ft_stripe_credentials_test` (`sk_test_...`, `pk_test_...`, `whsec_test_...`).
  - **Live Mode**: Encrypted credentials stored in `ft_stripe_credentials_live` (`sk_live_...`, `pk_live_...`, `whsec_live_...`).
- **Active Mode Switcher**: Select between `test` (Sandbox) and `live` (Production) directly from the settings interface.
- **Encryption**: AES-256-GCM authenticated encryption using keys derived from WordPress secret salts (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`) via HKDF-SHA256.
- **Option Storage**: Ciphertext stored in `wp_options` under `ft_stripe_credentials_test` and `ft_stripe_credentials_live` with autoload strictly disabled (`autoload = 'no'`). Zero plaintext stored.
- **Environment & Safety Policy**:
  - `local`, `development`, `staging`: Live Mode charges are strictly prohibited (`is_qa_mode_restricted()`). All local checkout and tests execute in Test Mode.
  - `production`: Live keys required. HTTPS (`is_ssl()`) is strictly enforced.
- **Webhook Endpoint**: `http://localhost/fraudtenant/wp-json/fraud-tenant/v1/stripe/webhook`
  - Verifies HMAC-SHA256 signatures with 300s replay tolerance.
  - Automatically selects Test or Live signing secret based on payload `livemode` boolean.
  - Processes `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, and invoice events.
- **Test Connection**: Provides an explicit administrator-triggered, read-only balance inquiry (`GET /v1/balance`) to verify secret key connectivity without creating customers or charges.
- **Plan Mappings**: Displays authoritative membership plans with independent Test and Live Product and Price IDs configured via `Fraud Tenant -> Membership Plans`.
- **Database Schema**: Version `1.7.0` (with `wp_ft_checkout_locks`, dual Stripe columns in `wp_ft_membership_plans`, and `livemode` flags).

---

## 9. Custom SaaS Enterprise Admin Command Center

- **URL**: `http://localhost/fraudtenant/wp-admin/admin.php?page=fraud-tenant-admin`
- **Required Capability**: `manage_options` or `ft_access_admin_portal`.
- **Sections (15 Tabs)**:
  1. `overview`: Enterprise Command Center, MRR calculation, 8 queue metrics, recent subscriptions.
  2. `users`: User Directory with role filters and approval status.
  3. `account_approval`: Pending Account Approval Queue with direct review actions.
  4. `owner_verification`: Owner Identity Verification and business entity documents.
  5. `property_verification`: Property Verification Queue with deed inspection.
  6. `properties`: Platform Property Directory with ownership mapping.
  7. `screening`: Screening Requests and permissible purpose audit records.
  8. `incidents`: Incident Review Queue with evidence streaming.
  9. `disputes`: Tenant Disputes Queue and resolution controls.
  10. `visibility`: Report Association and Disclosable Visibility Management.
  11. `subscriptions`: Owner Subscriptions and Stripe ID mappings.
  12. `plans`: Membership Plan Tiers and pricing overview.
  13. `stripe`: Stripe Payment Gateway Overview and dual status.
  14. `audit_logs`: Compliance & Security Audit Log with CSV export.
  15. `platform_settings`: Platform Architecture and System Configuration.


---

## 10. Mandatory Workflow Compliance Verification & Headed Browser QA

### Testing Prerequisites
- PHP CLI: `C:\xampp\php\php.exe`
- Node.js: v18+ (tested v24.22.0)
- Google Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe`

### 1. Automated Integration Suite (Disposable Database)
Executes all 49 workflow unit/integration assertions in an isolated, ephemeral MySQL database (`ft_qa_wf_*`). The operational database `fraud_tenant` is verified 100% untouched.
```powershell
& "C:\xampp\php\php.exe" -f "scratch/test_workflow_compliance.php"
```
- Assertions verified: Role selection, `email_unverified` initialization, 6-digit OTP generation, HMAC verification, replay rejection, 60s cooldown, holding state `/account-status/`, administrative approval/rejection with email notifications, server-side role routing, invalid credentials rejection, 6-digit password reset OTP generation, anti-enumeration, single-use 15m transient token, and password reset completion.

### 2. Headed Google Chrome Browser QA
Launches visible Google Chrome on the Windows desktop, navigates to each of the 20 compliance journeys via Chrome DevTools Protocol (CDP), captures pixel-perfect PNG screenshots across desktop and mobile viewports, and mirrors them to the reports and artifact directories:
```powershell
node scratch/run_headed_workflow_qa.js
```
- Screenshots output: `reports/workflow_compliance_qa/wf-*.png`
- Mirrored to: `<appDataDir>\brain\<conversation-id>\wf-*.png`

### 3. Master Platform Regression Suite
Verifies zero regressions across the 8 existing platform test suites:
```powershell
& "C:\xampp\php\php.exe" -f "scratch/run_master_regression.php"
```
- Assertions: 232 / 232 PASS (100%).

## Isolated QA installation & test commands (2026-11-04)
- QA site: http://localhost/fraudtenant-qa (DB `fraudtenant_qa`; plugin and theme are junctions to this repo; mail captured to `%USERPROFILE%\ft_qa_mail`; QA credentials in `%USERPROFILE%\ft_qa_secrets`).
- Genuine webhooks for QA: `php tests/claude/tools/stripe_cli_setup.php secret`, then `php tests/claude/tools/stripe_cli_setup.php listen` (Stripe CLI via `%APPDATA%\npm\stripe.cmd`).
- Suites (CLI only, refuse non-QA DBs): `php tests/claude/test_billing_membership.php`, `test_checkout_concurrency.php`, `test_auth_account_settings.php`, `test_workflow_and_admin.php`; browser: `php tests/claude/browser/prepare_fixtures.php && node tests/claude/browser/run_journeys.mjs`; cleanup: `php tests/claude/tools/cancel_qa_subscriptions.php`.
- Never run `scratch/test_stripe_webhooks.php` (it overwrites the webhook secret).
- Encrypted pre-change backup: `%USERPROFILE%\ft_backups\fraudtenant_20261104_140707.sql.enc` (key in `%USERPROFILE%\ft_backup_keys`).
