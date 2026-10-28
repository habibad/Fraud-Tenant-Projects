# FRAUD TENANT — Unresolved Dependencies & Blockers

This document tracks all external dependencies, missing credentials, infrastructure requirements, and legal compliance gates. Items listed here must be addressed prior to the corresponding phase or production deployment.

---

## 1. Credentials & External Services

### BLK-01: Stripe API Keys and Webhook Secret
- **Impacted Phase**: Phase 9 (Membership Plans and Stripe Subscriptions)
- **Status**: `BLOCKED` (Pending credentials from client/admin)
- **Details**: Requires `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, and `STRIPE_WEBHOOK_SECRET` for Stripe test mode.
- **Action Required**: Provide Stripe sandbox keys or configure them in server environment variables prior to Phase 9 testing.
- **Workaround for Development**: Use mock payment test harness during initial service implementation, but real Stripe checkout and webhook verification will remain blocked until test keys are configured.

### BLK-02: Outbound Email / SMTP Provider Configuration
- **Impacted Phases**: Phase 2 (Contact form), Phase 3 (Email OTP), Phase 4 (Admin approvals), Phase 8 (Disputes), Phase 10 (Notifications)
- **Status**: `DEVELOPMENT COMPLIANT / PRODUCTION CREDENTIALS PENDING`
- **Details**: Windows XAMPP environment lacks built-in `sendmail`. In Phase 3, `OtpService` utilizes `wp_mail()` with graceful local fallback and database-backed verification (`wp_ft_email_otps`). FluentSMTP is present in `wp-content/plugins/fluent-smtp/` for optional live relay.
- **Action Required**: Configure production SMTP credentials (e.g. Amazon SES, Postmark, SendGrid) prior to Phase 11 production launch.

---

## 2. Server Secrets (Outside Database & Source Control)

### BLK-04: SSN Matching Secret (`FT_MATCH_SECRET_KEY`)
- **Impacted Phase**: Phase 7 (Authorized Tenant Search)
- **Status**: `ARCHITECTURE ESTABLISHED; PENDING CONFIGURATION FOR PHASE 7`
- **Details**: Plaintext SSN storage is strictly prohibited. `wp_ft_tenant_identities` stores only keyed HMAC tokens (`token_hash`) and masked `ssn_last_four`. `SecurityService` fails closed if `FT_MATCH_SECRET_KEY` is missing.
- **Action Required**: Add `define('FT_MATCH_SECRET_KEY', 'your-random-64-character-hex-secret');` to `wp-config.php` before Phase 7.

### BLK-06: Dedicated OTP HMAC Secret (`FT_OTP_SECRET_KEY`)
- **Impacted Phase**: Phase 3 (Email OTP Verification)
- **Status**: `VERIFIED & OPERATIONAL (PHASE 3)`
- **Details**: OTP hashing uses HMAC-SHA-256 with constant-time verification via `hash_equals()`. Secret is defined in untracked local `wp-config.php` and verified via automated integration tests (all 30 tests passing). Fails closed if missing.
- **Action Required**: Maintain secret outside version control; configure in production `wp-config.php` prior to live deployment.

---

## 3. Legal & Regulatory Compliance Gates

### BLK-03: FCRA Legal Compliance & Permissible Purpose Framework
- **Impacted Phases**: Phase 7 (SSN Search), Phase 8 (Incident Reports & Disputes), Phase 11 (Launch)
- **Status**: `PRODUCTION LAUNCH BLOCKER`
- **Details**: Operating an SSN-based tenant screening and rental incident reporting service in the US subjects the platform to the Fair Credit Reporting Act (FCRA), state privacy regulations, and fair housing laws.
- **Requirements**:
  1. Permissible purpose certification for property owners before initiating a screening request.
  2. Clear tenant authorization and disclosure requirements.
  3. Pre-adverse action and adverse action notice procedures.
  4. 30-day dispute investigation and correction mandates.
  5. Prohibition against publishing unverified defamatory allegations or automated "fraudulent" tenant labels.
- **Resolution**: Production deployment must not occur without sign-off from qualified US legal counsel.

---

## 4. Protected Storage Infrastructure

### BLK-05: Private Document Storage Root Outside Webroot
- **Impacted Phase**: Phase 3 (Ownership Document Upload), Phase 4 (Admin Review), Phase 6 (Property Documents)
- **Status**: `IMPLEMENTED & VERIFIED IN PHASE 3`
- **Details**: Implemented `DocumentService` in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`. Uses private directory `wp-content/uploads/fraud-tenant-protected/` secured by Apache `.htaccess` (`Deny from all`, `Require all denied`), `index.php` (403 Exit), and IIS `web.config`.
- **Verification**: Verified live via `curl.exe` returning `HTTP/1.1 403 Forbidden` on direct web access. Authorized PHP streaming controller to be utilized in Phase 4 admin review and Phase 6 document access.
