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
- **Details**: Windows XAMPP environment lacks built-in `sendmail`. In Phase 3 and Phase 4, `OtpService` and `AdminReviewService` utilize `wp_mail()` with graceful local fallback and database-backed verification (`wp_ft_email_otps`). FluentSMTP is present in `wp-content/plugins/fluent-smtp/` for optional live relay.
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
- **Phase 3 & Phase 5.1 Clarifications**:
  1. Registration forms and handlers represent standard agreement to the Terms of Service and acknowledgement of the Privacy Policy. Registration does not constitute blanket FCRA authorization or establish legal permissible purpose for tenant searches.
  2. Phase 5.1 dashboard hardening eliminated all absolute "clear rental record" claims. Dashboards display neutral notices ("No disclosable incident records are currently available for your account") with explicit disclosures that platform data may be incomplete and does not certify a clear rental history.
  3. Address labels reflect unverified registration inputs ("Current Address on File", "Previous Address Reported During Registration") and are not marked "verified".
- **Requirements for Phase 7**:
  1. Standalone permissible purpose attestation and certification workflow for property owners before initiating each screening request.
  2. Qualified legal review of tenant disclosure, authorization, and consent workflows prior to Phase 7 and production deployment.
  3. Pre-adverse action and adverse action notice procedures.
  4. 30-day dispute investigation and correction mandates.
  5. Prohibition against publishing unverified defamatory allegations or automated "fraudulent" tenant labels.
- **Resolution**: Production deployment must not occur without formal sign-off from qualified US legal counsel.

---

## 4. Protected Storage Infrastructure

### BLK-05: Private Document Storage Root Outside Webroot
- **Impacted Phase**: Phase 3 (Ownership Document Upload), Phase 4 (Admin Review), Phase 6 (Property Documents)
- **Status**: `VERIFIED & OPERATIONAL (PHASE 3, 4 & 6)`
- **Details**: Implemented `DocumentService` in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`. Uses private directory `wp-content/uploads/fraud-tenant-protected/` secured by Apache `.htaccess` (`Deny from all`, `Require all denied`), `index.php` (403 Exit), and IIS `web.config` (`<deny users="*" />`).
- **Phase 6 Operational Verification**: Extended authorized streaming endpoint in `DocumentService::stream_protected_document()` to allow verified document owners (`(int) $doc->user_id === $requesting_user_id`) as well as reviewers. Dual authorization, path traversal trapping, safe attachment headers, and IDOR isolation verified across 45 automated test assertions.
- **Web Server Protection Matrix**:
  1. **Apache**: Fully protected via generated `.htaccess` rules (`<IfModule mod_authz_core.c> Require all denied </IfModule>` and legacy `Order Deny,Allow` / `Deny from all`). Verified live via automated tests returning `HTTP/1.1 403 Forbidden`.
  2. **IIS**: Fully protected via auto-generated `web.config` with `<authorization><deny users="*" /></authorization>`.
  3. **Nginx**: Because Nginx ignores `.htaccess` files by design, Nginx deployments **must** implement either:
     - **Option A (Recommended)**: Define `FT_PROTECTED_STORAGE_DIR` in `wp-config.php` pointing to an absolute directory completely outside the web server document root (e.g. `define( 'FT_PROTECTED_STORAGE_DIR', '/var/www/fraud-tenant-protected' );`), eliminating any web server bypass exposure.
     - **Option B**: Add an explicit Nginx server block directive:
       ```nginx
       location ^~ /wp-content/uploads/fraud-tenant-protected/ {
           deny all;
           return 403;
       }
       ```
- **Phase 6.1 Hardening & Reconciliation**:
  1. Implemented ownership linkage cross-verification: `DocumentService::stream_protected_document()` cross-references property ownership with `wp_ft_properties.owner_id` to prevent cross-property document tampering.
  2. Enforced directory boundary normalization (`$normalized_base = rtrim($canonical_base, '/\\') . DIRECTORY_SEPARATOR;`), preventing sibling-prefix directory escapes.
  3. Scoped administrative property review to dedicated capabilities (`ft_review_properties`, `ft_verify_properties`).
  4. Outbound live SMTP delivery remains classified as `NOT TESTED` (5 assertions across phases) pending production email relay credentials.
- **Application-Level Security**: Zero public streaming routes or direct upload links exist. Authorized streaming controllers with capability and ownership verification are enforced for admin reviews (Phase 4) and property owner access (Phase 6).
