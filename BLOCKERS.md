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
- **Status**: `IN PROGRESS / RESOLVED LOCALLY`
- **Details**: Windows XAMPP lacks built-in `sendmail`. FluentSMTP (v2.4.1) has been detected in `wp-content/plugins/fluent-smtp/`.
- **Action Required**: Configure FluentSMTP with a test SMTP server (e.g., Mailpit, SendGrid, or Gmail App Password) prior to Phase 3 OTP browser testing.

---

## 2. Server Secrets (Outside Database & Source Control)

### BLK-04: SSN Matching Secret (`FT_MATCH_SECRET_KEY`)
- **Impacted Phase**: Phase 7 (Authorized Tenant Search)
- **Status**: `ARCHITECTURE ESTABLISHED; PENDING CONFIGURATION FOR PHASE 7`
- **Details**: Plaintext SSN storage is strictly prohibited. `wp_ft_tenant_identities` stores only keyed HMAC tokens (`token_hash`) and masked `ssn_last_four`. `SecurityService` fails closed if `FT_MATCH_SECRET_KEY` is missing.
- **Action Required**: Add `define('FT_MATCH_SECRET_KEY', 'your-random-64-character-hex-secret');` to `wp-config.php` before Phase 7.

### BLK-06: Dedicated OTP HMAC Secret (`FT_OTP_SECRET_KEY`)
- **Impacted Phase**: Phase 3 (Email OTP Verification)
- **Status**: `REMEDIATED IN PHASE 1; PENDING CONFIGURATION FOR PHASE 3`
- **Details**: OTP hashing has been strengthened from standard unkeyed hashing to HMAC-SHA-256 using a dedicated server secret (`FT_OTP_SECRET_KEY`), strictly separated from `FT_MATCH_SECRET_KEY`. Fails closed if missing or invalid.
- **Action Required**: Add `define('FT_OTP_SECRET_KEY', 'your-random-64-character-hex-secret');` to `wp-config.php` before Phase 3.

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
- **Impacted Phase**: Phase 6 (Property Management & Document Uploads)
- **Status**: `ARCHITECTURE ESTABLISHED IN PHASE 1`
- **Details**: `wp_ft_documents` schema created with `file_path`, `access_permission`, and ownership constraints.
- **Resolution**: Use `wp-content/uploads/fraud-tenant-protected/` with `.htaccess` `Deny from all` and authorized PHP streaming controller during Phase 6.
