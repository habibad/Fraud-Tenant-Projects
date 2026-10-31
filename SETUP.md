# FRAUD TENANT — Local Setup & Configuration Guide

This guide details the setup, configuration, and verification procedures for the Fraud Tenant platform on a local Windows XAMPP environment.

---

## 1. Prerequisites

- **XAMPP**: Running Apache 2.4.x and MySQL / MariaDB 10.4.x.
- **PHP**: PHP 8.2+ with `curl`, `mbstring`, `openssl`, `mysqli`, `pdo_mysql` extensions enabled.
- **WordPress**: 7.1.3 installed in `c:\xampp\htdocs\fraudtenant` (verified via `wp-includes/version.php` and `get_bloginfo('version')`).
- **Git**: Git 2.x installed at `C:\Program Files\Git\cmd\git.exe`.
- **Node.js**: Node.js 18+ (verified v24.22.0) for frontend build tools and test scripts.
- **Browser Automation**: Google Chrome installed at `C:\Program Files\Google\Chrome\Application\chrome.exe`.

---

## 2. Environment Configuration

### Database Settings
Database configuration is defined in `wp-config.php`:
```php
define( 'DB_NAME', 'fraudtenant' );
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
c:\xampp\htdocs\fraudtenant/
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
    "schema_version": "1.2.0",
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

