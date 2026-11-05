# FRAUD TENANT — Unresolved Dependencies & Blockers
## Reconciled Architecture, Legal & Security Blocker Matrix (Round 4 Blocker Resolution)

This document tracks all external dependencies, missing credentials, infrastructure requirements, and legal compliance gates for the Fraud Tenant platform. There are currently **12 distinct project blockers and integration dependencies** categorized into four functional groups: 6 Legal Authorization Blockers, 3 Technical Design and Implementation Blockers (of which BLK-09 is **RESOLVED & VERIFIED**; BLK-04 and BLK-08 remain pending), 1 Operational Screening and Disclosure Blocker, and 2 Release / Phase-Specific Integration Gates. Two verified security controls (BLK-05 and BLK-06) are preserved for architectural reference.

---

## 1. Legal Authorization Blockers
*These blockers strictly prevent operational tenant screening, report generation, and negative result disclosure.*

### BLK-03A: FCRA CRA (§ 1681a(f)), Consumer Report (§ 1681a(d)) & § 1681a(d)(2)(A)(i) Exclusion Analysis
- **Impacted Phases**: Phase 7 (Screening), Phase 8 (Incident Reports & Disputes), Phase 9 (Subscriptions)
- **Status**: `CRITICAL LEGAL BLOCKER — PENDING QUALIFIED U.S. LEGAL COUNSEL REVIEW`
- **Classification Status**:
  - `CURRENT OPERATIONAL DATA: NO OPERATIONAL SCREENING RECORDS EVIDENCED`
  - `LEGAL CLASSIFICATION: NOT DETERMINED — FORMAL COUNSEL REVIEW REQUIRED`
  - `RISK PROFILE: MATERIAL CRA CLASSIFICATION RISK — FORMAL COUNSEL DETERMINATION REQUIRED`
- **Details**: Operating a platform that collects, evaluates, and furnishes tenant rental history records to prospective property owners creates a substantial risk of qualifying as a Consumer Reporting Agency under 15 U.S.C. § 1681a(f). Commercial labels such as "technology platform" or "SaaS portal" do not exclude an entity from CRA obligations if statutory elements are met.
- **Statutory Nuances**:
  - **Transaction-or-Experience Exclusion (§ 1681a(d)(2)(A)(i))**: A landlord's direct account of its own first-hand lease experience with a tenant may fall within the exclusion if statutory conditions are met. However, the exclusion cannot be assumed where communications include third-party data, aggregation, or external evaluations. Because Fraud Tenant conveys third-party experience information, the direct transaction/experience exclusion does not readily shield an intermediary platform.
  - **Cooperative Basis & Interstate Commerce (§ 1681a(f))**: The statute references monetary fees, dues, or a "cooperative nonprofit basis." A cooperative arrangement may be relevant, but actual organizational structure, operating model, and legal relationships must be examined by counsel; an informal sharing network alone does not establish this element. Similarly, use of web/cloud facilities must be evaluated against statutory interstate-commerce elements.
- **Action Required**: Retain qualified U.S. legal counsel to formally determine CRA status, consumer report scope, and intermediary exclusion applicability under 15 U.S.C. § 1681 et seq.

### BLK-03B: State-Specific Consumer Reporting & Screening Obligations
- **Impacted Phases**: Phase 7 (Screening), Phase 8 (Incidents)
- **Status**: `STATE-SPECIFIC APPLICABILITY — COUNSEL REVIEW REQUIRED`
- **Details**: State consumer reporting statutes impose unique registration, procedural, and disclosure obligations that vary significantly:
  - **Maine**: Under the Maine Fair Credit Reporting Act (10 M.R.S. Chapter 209-B, § 1310-A(1)(G)) and Maine Bureau of Consumer Credit Protection Rule Chapter 704 (*Consumer Reporting Agencies: Registration and Recordkeeping*), consumer reporting agencies that are **located in Maine or serving users within Maine** are subject to annual registration and fee schedules. This requirement is specific to entities located in Maine or serving users in Maine; it is not a universal nationwide registration mandate.
  - **Texas**: Texas Business & Commerce Code Chapter 20 regulates consumer credit reporting agencies substantively, but does not operate an independent agency registration bureau for nationwide CRAs. Substantive statutory requirements must not be conflated with a separate state registration program.
  - **Other States**: California (Civil Code § 1785.1 et seq.), New York (General Business Law § 380 et seq.), and Washington (RCW 19.182) enforce state-specific FCRA analogues with unique procedural and disclosure rules.
- **Action Required**: Legal counsel must conduct a state-by-state analysis of screening, registration, and disclosure requirements.

### BLK-03C: Current Fair Housing Act Authority Status, Withdrawn Guidance, 2026 Rulemaking & Disparate Impact
- **Impacted Phases**: Phase 7 (Screening), Phase 8 (Incident Reports)
- **Status**: `PENDING LEGAL COUNSEL REVIEW`
- **Details**:
  - **Statutory Framework**: 42 U.S.C. §§ 3601–3619 protects 7 express statutory classes (race, color, national origin, religion, sex, familial status, disability). *Bostock v. Clayton County*, 590 U.S. 644 (2020) interpreted Title VII; HUD's Feb 9, 2021 memorandum extended sex protections under the FHA as an agency interpretation (`CURRENT STATUS NOT VERIFIED — COUNSEL REVIEW REQUIRED`).
  - **HUD 2016 Criminal Records Guidance**: Withdrawn effective **September 25, 2025**; Federal Register notice published **July 17, 2026** (91 FR 44867). Status: `WITHDRAWN` [Non-binding historical guidance].
  - **HUD April 29, 2024 Rental Screening Guidance**: Removed from HUD public website in February 2025; HUD communicated review status in March 2025; subsequently documented in GAO-25-107196. Status remains unverified non-binding agency guidance (`CURRENT STATUS NOT VERIFIED — COUNSEL REVIEW REQUIRED`).
  - **HUD 2026 Disparate-Impact Rulemaking**: The January 14, 2026 proposed rule (91 FR 1475, Docket FR-6540-P-01; 24 CFR Part 100) and August 10, 2026 supplemental proposed rule (91 FR 51416, Docket FR-6540-P-02; 24 CFR Part 1) both remain `PROPOSED RULEMAKING — NOT OPERATIVE REGULATION` (`FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`). Controlling precedent remains *Inclusive Communities*, 576 U.S. 519 (2015).
  - **Internal Product Safeguards**: Automated rejection algorithms and risk scoring are strictly prohibited by Fraud Tenant internal policy.
- **Action Required**: Counsel review of screening criteria, allegation handling, withdrawn guidance impact, and adherence to objective factual records.

### BLK-03D: Permissible Purpose vs. Written Instructions vs. Internal Policy (§ 1681b)
- **Impacted Phase**: Phase 7 (Tenant Screening)
- **Status**: `PENDING LEGAL COUNSEL REVIEW`
- **Details**:
  - **Section 1681b(a)(2)**: Written instructions of the consumer independently establish a permissible purpose under federal statute if properly documented and attributable to the consumer.
  - **Section 1681b(a)(3)(F)(i)**: Legitimate business need in connection with a business transaction initiated by the consumer (rental application) provides a potential permissible purpose.
  - **Reconciliation**: The two routes are distinct. An uploaded document does not automatically constitute valid written instructions without verifiable applicant attribution.
  - **Section 1681b(f) & § 1681e(a)**: Restrictions on obtaining/using reports without certified permissible purpose and CRA prospective user verification duties.
  - **Employment Distinction**: FCRA § 1681b(b)(2) applies exclusively to employment-purpose reports and does not universally mandate employment disclosure forms for housing. However, written applicant authorization is an essential evidentiary safeguard and may be required by state law or internal risk policy.
- **Action Required**: Counsel must approve standalone tenant disclosure and authorization templates, permissible purpose attestations, and internal consent requirements.

### BLK-03E: Housing Adverse-Action Notice & Credit Score Disclosures (§ 1681m(a))
- **Impacted Phase**: Phase 7 (Tenant Screening)
- **Status**: `PENDING LEGAL COUNSEL REVIEW`
- **Details**:
  - **Adverse Action Definition**: 15 U.S.C. § 1681a(k)(1)(B)(iv) defines adverse action for consumer-initiated applications and business transactions.
  - **Notice Obligations**: When an adverse action is taken based in whole or in part on a consumer report, § 1681m(a) mandates written notice, CRA contact info, 60-day free report disclosure notice, dispute right notice, and § 1681m(a)(2) credit score disclosures (only if a numerical credit score was used).
  - **Employment Distinction**: FCRA § 1681b(b)(3) pre-adverse action waiting periods apply to employment, not housing.
- **Action Required**: Counsel must draft and approve housing adverse-action notice templates, CRA contact disclosures, dispute rights, and credit score disclosure rules.

### BLK-03F: Differentiated Record Obsolescence (§ 1681c, 180-day rule) & Retention (§ 1681p)
- **Impacted Phases**: Phase 7 (Screening), Phase 8 (Incident Reports)
- **Status**: `NOT APPROVED FOR IMPLEMENTATION — PENDING LEGAL COUNSEL REVIEW`
- **Details**:
  - **Reporting Limits vs. Retention**: FCRA § 1681c regulates what a CRA may furnish in consumer reports; it does not serve as a universal data retention or destruction schedule for landlords.
  - **Differentiated Periods**: Bankruptcy (10 yrs, § 1681c(a)(1)); civil suits, civil judgments, and records of arrest that, from date of entry, antedate the report by more than seven years or until the governing statute of limitations has expired, whichever is longer (§ 1681c(a)(2)); tax liens (7 yrs from payment, § 1681c(a)(3)); collection accounts / charge-offs (7 yrs beginning 180 days after delinquency commencement under § 1681c(c)(1)); other adverse items (7 yrs, § 1681c(a)(5)).
  - **Criminal Convictions**: Excluded from the 7-year federal cap under § 1681c(a)(5), but strictly limited by state statutes and FHA disparate impact standards.
  - **Section 1681c(b) Exceptions**: Applies to credit/life insurance >= $150k or employment >= $75k; not applicable to standard rental screening.
  - **Section 1681p**: Statute of limitations for civil liability (earlier of 2 yrs from discovery or 5 yrs from violation); not a data retention mandate.
  - **Phase 8.2 Reconciliation Safeguard**: In Phase 8.2 Reconciliation, the platform retired the legally sensitive reason code `statute_exceeded` and replaced it with internal-policy reason code `policy_window_exceeded`. Direct submission of `statute_exceeded` fails closed with `statutory_determination_unsupported`. No statutory rules or obsolescence determinations are implemented as automated platform decisions pending qualified U.S. legal counsel review.
- **Action Required**: Counsel approval of a multi-tiered obsolescence and data retention schedule.

---

## 2. Technical Design and Implementation Blockers
*These blockers must be resolved before authorized Phase 7 code and feature implementation begins.*

### BLK-04: SSN Matching Secret (`FT_MATCH_SECRET_KEY`) Missing — Tiered Provisioning Policy
- **Impacted Phase**: Phase 7 (Authorized Tenant Search)
- **Status**: `HMAC IMPLEMENTATION PRESENT — RUNTIME MATCHING BLOCKED BY MISSING SECRET`
- **Details**: Plaintext SSN storage is strictly prohibited. `wp_ft_tenant_identities` stores only keyed HMAC tokens (`token_hash`) and masked `ssn_last_four`. `SecurityService::generate_ssn_match_token()` fails closed if `FT_MATCH_SECRET_KEY` is missing. No production key may be copied into local XAMPP. Key rotation cannot re-hash existing tokens without original SSNs or an independently approved identity vault.
- **Candidate Enumeration Risk**: SSN candidate space is finite (at most \(10^9\) formatted values); secret compromise enables offline candidate precomputation against deterministic tokens.
- **Tiered Management Policy**:
  1. *Development*: Separate non-production test key configured in local `wp-config.php` only when authorized development begins.
  2. *Testing*: Dedicated isolated test key.
  3. *Production*: Production secret must be provisioned via an approved secure secret-management system (e.g., AWS Secrets Manager, HashiCorp Vault, Azure Key Vault, or protected environment variables). Production `wp-config.php` is not prescribed as the sole permissible secret-management mechanism.
- **Action Required**: Provision secret keys according to the tiered policy only after legal, data-source, and security prerequisites are fulfilled.

### BLK-09: SSN Input Pre-Validation Regex Hardening
- **Impacted Phase**: Phase 7 (Authorized Tenant Search)
- **Status**: `RESOLVED & VERIFIED`
- **Details**: Implemented strict pre-normalization ASCII regex validation in `SecurityService::validate_ssn_format()` and `SecurityService::generate_ssn_match_token()`. All candidate inputs must strictly conform to whole-string anchored regular expressions using ASCII digit character classes `[0-9]`:
  - Formatted: `/\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z/`
  - Raw 9-digit: `/\A[0-9]{9}\z/`
  Any malformed strings containing non-ASCII characters, trailing letters (e.g., `123-45-6789abc`), trailing digits (e.g., `123456789extra`), misplaced hyphens (`123-456-789`), spaces, or Unicode numerals are strictly rejected before normalization and before hashing. Confirmed fail-closed behavior when `FT_MATCH_SECRET_KEY` is missing.
- **Verification Evidence**: Automated test suite `scratch/test_blk09_ssn_validation.php` executed (**27 assertions PASS**). All 5 regression suites executed (**161 assertions PASS**). Total: **188 / 188 PASS (100%)**.

### BLK-08: Sensitive PII Protection at Rest & Database Backup Policy
- **Impacted Phases**: Phase 7 (Screening), Phase 8 (Incidents)
- **Status**: `BLOCKED — TECHNICAL POLICY & ENCRYPTION PENDING (PHASE 8.1.1 GATE HARDENED)`
- **Details**: While SSNs are protected via one-way keyed HMAC hashes, applicant legal names, dates of birth, and incident descriptions reside in database columns without application-level field encryption. Prepared SQL statements prevent SQL injection but do not provide encryption at rest. Database engine, disk volume, and backup encryption remain `NOT VERIFIED`.
- **Phase 8.1 & 8.1.1 Security Gate Architecture**:
  - In Phase 8.1 and hardened in Phase 8.1.1, real-data incident submissions are fail-closed locked pending completion of sensitive PII encryption infrastructure.
  - Filter-based overrides (`apply_filters('ft_incident_test_mode_enabled')`) have been completely removed to eliminate any potential filter-based bypass in production.
  - Strict two-condition security gate in `IncidentService::is_test_mode_enabled()` requires direct PHP constant evaluation with zero WordPress filter reliance:
    1. Constant `FT_INCIDENT_TEST_MODE` must be explicitly defined and strictly boolean `true`.
    2. Environment constant `WP_ENVIRONMENT_TYPE` must be explicitly defined as a PHP constant and directly evaluated, strictly matching an allowed non-production environment: `'local'`, `'development'`, or `'staging'`. Missing, undefined, unknown, or `'production'` environments fail-closed.
  - Complete elimination of WordPress filter vulnerabilities: `wp_get_environment_type()` was removed to prevent third-party plugins or themes from spoofing non-production environments via the `wp_get_environment_type` filter hook. Direct PHP constant evaluation guarantees environment tamper-resistance.
  - Immunity against parameter/cookie tampering: query parameters (`?test_mode=1`), POST fields, cookies, and user capabilities (including administrator `manage_options`) cannot override the privacy gate.
  - Pre-processing fail-closed enforcement: evaluated before reading form values, narrative text, or `$_FILES` across all entry points: REST API `permission_callback` (HTTP 423 `submission_locked_blk08`), AJAX handler (HTTP 423 `submission_locked_blk08`), Web POST handler (`wp_die` HTTP 423), and direct `DocumentService::store_incident_document()` calls.
  - Deployment boundary verified: `wp-config.php` is strictly local-only and excluded from Git (.gitignore line 9). Local development explicitly configures `WP_ENVIRONMENT_TYPE = 'local'`. Shared and production deployment policy enforces fail-closed fallback to `'production'` if undefined.
  - Tenant identity semantics corrected: `wp_ft_incident_reports.tenant_identity_id` altered to `bigint(20) unsigned DEFAULT NULL`, ensuring unlinked records store strictly `NULL` rather than legacy `0`.
  - Audit Log Data Minimization: Application-level append-only audit logging. Existing audit records were preserved during browser QA fixture cleanup. This does not prevent privileged database administrators from directly modifying database records. Zero sensitive narrative description or tenant PII is stored in audit logs (demonstrably verified via automated tests `P82R-3.3`, `P82R-3.4`, `P811-6.3`, `P811-6.4`), while actor IDs are retained for accountability; transients store exclusively an integer counter by structural code design; REST endpoints fail-closed before reading payloads in locked mode.
  - In Phase 8.2 (Administrative Incident Review Workflow), the BLK-08 fail-closed privacy gate was extended to all review operations: `get_review_queue()`, `get_incident_for_review()`, `decide_incident_report()`, and evidence streaming all fail-closed in production returning HTTP 423 `incident_review_locked_blk08`.
- **Action Required**: Formulate and implement an application-level encryption-at-rest strategy for sensitive PII and secure database backup procedures prior to opening real-data production submissions.

---

## 3. Operational Screening and Disclosure Blockers
*These dependencies do not prevent isolated Phase 7 code development but strictly block operational negative-result disclosure.*

### BLK-07: Screening Data Source & Phase 8 Dependency
- **Impacted Phase**: Phase 7 (Tenant Screening)
- **Status**: `BLOCKED — DATA SOURCE & VERIFICATION WORKFLOWS NOT IMPLEMENTED`
- **Details**: The database foundation contains zero operational tenant identity records (`wp_ft_tenant_identities` has only 2 synthetic test rows). Phase 8 incident reporting, administrative review gates, and tenant dispute workflows are not implemented. No external screening provider is connected. A matching engine without a populated, verified data source cannot provide meaningful screening results.
- **Prohibition on Disclosure**: An HMAC match indicates identity presence, not guilt or default. No negative screening result may be operationally disclosed until Phase 8 incident verification, admin review moderation, and tenant dispute workflows are operational.
- **Action Required**: Complete Phase 8 incident verification and tenant dispute workflows before enabling negative screening result disclosure in Phase 7.

---

## 4. Release / Phase-Specific Integration Gates
*These items govern feature-specific external integration and mandatory production release readiness.*

### BLK-01: Stripe API Keys and Webhook Secret — Dual-Mode Stripe Integration
- **Impacted Phase**: Phase 9 (Membership Plans and Stripe Subscriptions)
- **Status**: `RESOLVED VIA DUAL STRIPE MODE (TEST & LIVE ENCRYPTED VAULT)`
- **Details**: Implemented dedicated dual-mode credential management service (`StripeCredentialService.php`) and admin configuration portal (`Fraud Tenant -> Settings -> Stripe`). Credentials for both Test Mode and Live Mode are stored independently using AES-256-GCM authenticated encryption with HKDF-SHA256 key derivation from WordPress secret salts in `wp-config.php`, stored only as ciphertext with disabled autoloading in separate options (`ft_stripe_credentials_test` and `ft_stripe_credentials_live`). Enforces strict test/live prefix rules (`sk_test_`, `pk_test_`, `whsec_test_` vs `sk_live_`, `pk_live_`, `whsec_live_`). Active mode switcher enables seamless switching between sandbox testing and live production. In non-production environments (`local`, `development`, `staging`), Live Mode charges are strictly prohibited by fail-closed policy (`is_qa_mode_restricted()`). Live mode strictly enforces HTTPS (`is_ssl()`).
- **Action Required**: Test Mode is fully configured and operational with live Stripe API balance verification. Live production keys to be provisioned prior to commercial deployment.

### BLK-02: Outbound Email / SMTP Provider Configuration — Mandatory Production Release Gate
- **Impacted Phases**: Phase 2 (Contact form), Phase 3 (Email OTP), Phase 4 (Admin approvals), Phase 7 (Adverse Action Notices), Phase 8 (Disputes), Phase 10 (Notifications)
- **Status**: `5 ASSERTIONS NOT TESTED — MANDATORY PRODUCTION RELEASE GATE`
- **Details**: Windows XAMPP environment lacks built-in `sendmail`. In Phase 3 and Phase 4, `OtpService` and `AdminReviewService` utilize `wp_mail()` with graceful local fallback and database-backed verification (`wp_ft_email_otps`). FluentSMTP is present in `wp-content/plugins/fluent-smtp/` for optional live relay. 5 historical assertions remain classified as `NOT TESTED`.
- **Release Gate Classification**:
  1. *Local Development*: Logic and unit testing can proceed with local fallback and database checks.
  2. *Feature Integration*: Features requiring live email dispatch (OTP delivery, email notifications, dispute notices, and statutory disclosures) require working SMTP relay.
  3. *Mandatory Production Release Gate*: Live outbound SMTP provider configuration is a mandatory production release gate for all live outbound communications.
- **Action Required**: Configure production SMTP credentials (e.g. Amazon SES, Postmark, SendGrid) prior to Phase 11 production launch.

---

## 5. Verified & Operational Controls (Reference Only)

### BLK-05: Private Document Storage Root Outside Webroot
- **Impacted Phase**: Phase 3 (Ownership Document Upload), Phase 4 (Admin Review), Phase 6 (Property Documents)
- **Status**: `VERIFIED & OPERATIONAL (PHASES 3, 4 & 6)`
- **Details**: Implemented `DocumentService` in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`. Private directory `wp-content/uploads/fraud-tenant-protected/` secured by Apache `.htaccess` (`Deny from all`, `Require all denied`), `index.php` (403 Exit), and IIS `web.config` (`<deny users="*" />`).
- **Phase 6 / 6.1 / 6.1.1 Hardening**:
  - Ownership linkage cross-verification (`$doc->user_id === $requesting_user_id` cross-referenced with `wp_ft_properties.owner_id`).
  - Directory boundary normalization (`rtrim($canonical_base, '/\\') . DIRECTORY_SEPARATOR`).
  - Capability separation: `ft_review_properties` for queue review, `ft_verify_properties` strictly required for property verification.
  - Safe header check: aborts safely before `readfile()` if headers sent (`headers_sent()`).
  - Document nonces: `'ft_view_doc_' . $document_id` for time-windowed CSRF defense.

### BLK-06: Dedicated OTP HMAC Secret (`FT_OTP_SECRET_KEY`)
- **Impacted Phase**: Phase 3 (Email OTP Verification)
- **Status**: `VERIFIED & OPERATIONAL (PHASE 3)`
- **Details**: OTP hashing uses HMAC-SHA-256 with constant-time verification via `hash_equals()`. Secret is defined in untracked local `wp-config.php` and verified via automated integration tests (all 30 tests passing). Fails closed if missing.

## Status update — 2026-11-04 (Claude completion pass)
- BLK-01: Stripe **Test** mode verified end-to-end on the isolated QA install (genuine Checkout payment, genuine Stripe CLI webhook delivery). **Live** remains BLOCKED: no Live API keys / Live webhook secret. Live Price IDs entered but unvalidated. The operational Test webhook secret is of unknown provenance (the legacy suite overwrote it); regenerate it with `stripe listen`.
- BLK-02: FluentSMTP logged a test email as sent (provider accepted) on 2026-11-04; inbox delivery NOT TESTED.
- BLK-03A–F, BLK-04, BLK-07, BLK-08: unchanged; fail-closed gates preserved. BLK-09: preserved.
- NEW (low): AuditLogger derives IP from client-controlled headers (spoofable).
- NEW (medium): WPvivid full-site backup zip inside the web root (Apache-denied, unencrypted); move it outside the web root.

## Status update — 2026-11-04 (identity screening, schema 1.8.0)
- BLK-04: implemented and verified on QA. **Live: owner must add FT_MATCH_SECRET_KEY to wp-config** (Claude was not permitted to write the live secret).
- BLK-08: application-layer field encryption implemented (AES-256-GCM). Infrastructure encryption remains an **administrator attestation** in Platform Settings → Compliance Gates; not verifiable by software.
- BLK-03: owner disclosure requires an audited administrator attestation of written legal approval; disabled until recorded. No legal compliance is claimed.
