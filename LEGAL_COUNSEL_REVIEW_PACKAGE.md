# FRAUD TENANT — LEGAL COUNSEL REVIEW PACKAGE
## Regulatory Assessment, Architecture Evidence & Phase 7 Prerequisite Briefing
### Controlled Documentation Revision — Version 2

**Document Title:** Legal Counsel Review Package & Regulatory Briefing  
**Platform Name:** Fraud Tenant (PropTech Tenant Screening & Incident Reporting Platform)  
**Project Path:** `C:\xampp\htdocs\fraudtenant`  
**Local Test URL:** `http://localhost/fraudtenant`  
**Current Branch:** `main` | **Git Commit Baseline:** `e9594390f11b8f4d7968def92f42b1d62f46d2ac`  
**Database Schema Version:** `1.3.0` (14 Custom Application Tables)  
**Revision Date:** November 1, 2026 (Controlled Revision Pass — Version 2)  
**Audience:** Qualified U.S. Legal Counsel (Specializing in FCRA, FHA, PropTech, Data Privacy, and State Consumer Reporting Statutes)  
**Legal Disclaimer:** *This document was prepared by technical and security engineering staff for review by qualified legal counsel. It compiles verified system architecture facts, data inventories, technical safeguards, and statutory questions identified in project records. Nothing in this document constitutes legal advice or establishes binding legal conclusions.*

---

## 1. Executive Summary

### 1.1 Purpose of This Review Package
Fraud Tenant is a WordPress-based PropTech web application designed to allow residential property owners to verify tenant applicant histories, share factual lease incident records, and order tenant screening reports.

The project has completed its core architectural foundation through **Phase 6.1.1** (Repository Foundation, Custom Database Schema, Public Web Interface, Two-Tier Registration & Authentication, Private Document Storage, Admin Review Queue, Role-Isolated Dashboards, and Multi-Property Management). Phase 6 is formally closed.

Before the platform enters **Phase 7** (Authorized Tenant Search Using SSN and Screening Report Generation), project leadership requires a formal, comprehensive legal review by qualified U.S. legal counsel. The purpose of this package is to provide counsel with an accurate, evidence-backed inventory of the platform's proposed business model, data flows, cryptographic safeguards, and technical constraints so counsel can:
1. Formally determine the platform's regulatory status under the **Fair Credit Reporting Act (FCRA), 15 U.S.C. § 1681 et seq.**;
2. Formally determine the platform's obligations under the **Fair Housing Act (FHA), 42 U.S.C. §§ 3601–3619**;
3. Identify all applicable **state-specific consumer reporting, registration, and tenant-screening statutes**;
4. Establish mandatory operational policies, tenant disclosure/authorization forms, adverse action notices, dispute procedures, and record obsolescence schedules; and
5. Deliver written **Go/No-Go conditions** for beginning Phase 7 development and operational report disclosure.

### 1.2 Current Architecture & Readiness Status
The platform's current readiness verdict is:

```
========================================================================================
                      PHASE 7 READINESS & ARCHITECTURE VERDICT:
                       BLOCKED — PREREQUISITES NOT SATISFIED
========================================================================================
```

- **Technical Scope**: Phase 0 through Phase 6.1.1 is fully implemented, verified, and regression-tested (**188 / 188 automated test assertions PASS** across active test suites: 161 baseline regression + 27 BLK-09 assertions; cumulative historical test catalog in `QA_REPORT.md` records 548 total assertions across all phases, of which 543 pass and 5 outbound SMTP delivery assertions remain classified as `NOT TESTED` pending production mail server configuration under BLK-02).
- **Technical Blocker BLK-09**: SSN input pre-validation regex hardening (`validate_ssn_format()` enforcing `\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z` and `\A[0-9]{9}\z` using strict ASCII digits) is **RESOLVED & VERIFIED**.
- **Pending Blockers**: **6 critical legal blockers** (BLK-03A through BLK-03F), **2 technical/security blockers** (BLK-04 missing matching secret, BLK-08 PII encryption at rest), **1 operational disclosure blocker** (BLK-07 data source & dispute workflows), and **2 integration gates** (BLK-02 SMTP release gate, BLK-01 Stripe payment dependency) remain active.
- **Strict Implementation Freeze**: Phase 7 feature development, SSN search interface wiring, runtime matching secret provisioning, and operational screening result disclosures are **strictly frozen** until counsel provides written guidance and project ownership issues formal authorization.

---

## 2. Platform Business Model

### 2.1 Mission & Two-Sided Accountability
Fraud Tenant is conceived as a two-sided rental accountability platform. It seeks to reduce rental default and fraudulent tenancy by providing property owners with verified historical rental experience data, while affording tenants transparency, notifications, and dispute mechanisms to correct inaccurate records.

### 2.2 Approved Product Workflow (Source of Truth)
The project documentation establishes a single approved 4-step progressive lifecycle (derived from `Fraud Tenant Product UI Showcase.png`):

```
[ Step 1: Register ] ──> [ Step 2: Get Verified ] ──> [ Step 3: Access Features ] ──> [ Step 4: Build Safer Rentals ]
```

1. **Step 1: Register**: Individual Property Owners and Tenants register through separate, role-specific registration templates with distinct data collection fields.
2. **Step 2: Get Verified**:
   - **Email Verification**: Both roles must verify email ownership via a 6-digit numeric OTP hashed via HMAC-SHA-256 before accessing any portal features.
   - **Administrative Account Review**: Property Owners submit identity and property ownership proof documents (deed, tax statement) to an administrative queue for human verification.
   - **Property Verification**: Property deed verification is decoupled from user account approval, with independent review lifecycles.
3. **Step 3: Access Features**:
   - Approved Owners access property management, document replacement, and planned tenant screening.
   - Approved Tenants access residential records on file, notifications, and planned dispute workflows.
4. **Step 4: Build Safer Rentals**:
   - Owners report verified, factual lease breach incidents (Phase 8).
   - Tenants receive notifications of reported records and have the right to submit disputes with supporting documentation (Phase 8).
   - Owners initiate permissible-purpose tenant screening searches backed by subscriptions (Phase 7 & 9).

### 2.3 Proposed Monetization & Membership Model (`PROPOSED — OWNER CONFIRMATION REQUIRED`)
The platform's planned commercial model includes tiered memberships for Property Owners. All pricing figures and tier allocations are **PROPOSED — OWNER CONFIRMATION REQUIRED** (they reflect project planning documents and database seed defaults, but have not been formally finalized by business ownership):
- **Free Tier ($0/month)**: Proposed to allow property registration and account management; planned to provide neutral tenant identity indicators without negative report disclosures.
- **Standard Monthly ($29/month)**: Proposed to provide full tenant screening searches and disclosable incident report access.
- **Standard Annual ($290/year)**: Proposed discounted annual screening subscription.
- **Tenants ($0)**: Proposed free access to inspect own disclosures, file disputes, and manage profile data.

---

## 3. Current vs. Planned Functionality

The following table distinguishes what is currently implemented and verified in code from schema-only definitions, planned features, and blocked items:

| Functional Domain | Feature Description | Implementation Status | Code / Architecture Evidence |
| :--- | :--- | :---: | :--- |
| **Authentication** | Two-tier registration forms (Owner vs. Tenant) | `Implemented and verified` | `page-register.php`, `AuthService.php` |
| **Authentication** | 6-digit email OTP generation & HMAC-SHA-256 verification | `Implemented and verified` | `OtpService.php`, `SecurityService.php` |
| **Authentication** | Zero Date of Birth collection during tenant registration | `Implemented and verified` | Verified in `AuthService::register_tenant()` |
| **Authentication** | Anti-enumeration and IP brute-force rate limiting | `Implemented and verified` | `AuthService.php`, `page-login.php` |
| **Document Storage** | Private storage root outside public web access | `Implemented and verified` | `DocumentService.php`, `fraud-tenant-protected/` |
| **Document Storage** | Web server barriers (.htaccess, index.php, web.config) | `Implemented and verified` | Verified 403 Forbidden on direct HTTP access |
| **Document Storage** | Authorized streaming controller with dual authorization | `Implemented and verified` | `DocumentService::stream_protected_document()` |
| **Administration** | Admin User Review Queue with approve/reject/info actions | `Implemented and verified` | `AdminPortal.php`, `UserReviewController.php` |
| **Administration** | Dedicated Property Verification Queue & detail inspection | `Implemented and verified` | `PropertyReviewController.php`, `AdminReviewService.php` |
| **Administration** | Decoupling of user account approval from property verification | `Implemented and verified` | `AdminReviewService.php` (independent lifecycles) |
| **Administration** | Least-privilege capability separation (`ft_review_properties` vs `ft_verify_properties`) | `Implemented and verified` | Enforced in `AdminReviewService::verify_property()` |
| **Dashboards** | Dedicated isolated application shells (.dashboard-topbar / .auth-footer) | `Implemented and verified` | `page-owner-dashboard.php`, `page-tenant-dashboard.php` |
| **Dashboards** | Multi-property CRUD, soft archival, and unit change reverification | `Implemented and verified` | `PropertyService.php`, `dashboard.css` |
| **Dashboards** | Neutral disclosable incident double-gate (`status = 'approved' AND disclosable = 1`) | `Implemented and verified` | `DashboardService::get_tenant_dashboard_data()` |
| **Dashboards** | Safe tenant activity timeline allowlist (zero admin note or telemetry leaks) | `Implemented and verified` | `DashboardService.php`, `wp_ft_audit_log` |
| **Dashboards** | Owner dashboard SSN input preview immunity (disabled, readonly, no transmission) | `Implemented and verified` | Verified in `page-owner-dashboard.php`, `dashboard.js` |
| **Security** | SSN input pre-validation regex (`validate_ssn_format`) | `Implemented and verified` | `SecurityService::validate_ssn_format()` (BLK-09) |
| **Security** | Fail-closed token generator when `FT_MATCH_SECRET_KEY` missing | `Implemented and verified` | `SecurityService::generate_ssn_match_token()` |
| **Security** | Append-only application-level audit logging with secret redaction | `Implemented and verified` | `AuditLogger.php` (redacts password, SSN, OTP, card) |
| **Database Schema** | `wp_ft_tenant_identities` (2 synthetic test records; operational ingestion unbuilt) | `Schema only — operational workflow not implemented` | Schema `1.3.0`; 2 test fixtures only |
| **Database Schema** | `wp_ft_screening_requests` (0 operational rows) | `Schema only — operational workflow not implemented` | Schema `1.3.0`; zero operational records |
| **Database Schema** | `wp_ft_incident_reports` (0 operational rows) | `Schema only — operational workflow not implemented` | Schema `1.3.0`; zero operational records |
| **Database Schema** | `wp_ft_tenant_disputes` (2 synthetic fixture rows; `report_id = 0`) | `Schema only — operational workflow not implemented` | Schema `1.3.0`; 2 test fixtures only |
| **Database Schema** | `wp_ft_subscriptions` & `wp_ft_payment_events` (0 operational rows) | `Schema only — operational workflow not implemented` | Schema `1.3.0`; blocked by BLK-01 |
| **Tenant Search (Phase 7)** | SSN matching search UI & permissible purpose attestation modal | `Planned` | Phase 7 Planned Scope |
| **Tenant Search (Phase 7)** | Screening request database recording (`wp_ft_screening_requests`) | `Planned` | Schema exists (`1.3.0`); zero operational records |
| **Tenant Search (Phase 7)** | Screening report generation and tiered result disclosure | `Blocked` | Blocked by BLK-03A–03F, BLK-07 |
| **Tenant Search (Phase 7)** | Housing adverse-action notice generation (§ 1681m(a)) | `Blocked` | Blocked by BLK-03E |
| **Incident Reports (Phase 8)** | Owner incident report submission with evidence attachment | `Planned` | Phase 8 Planned Scope |
| **Incident Reports (Phase 8)** | Admin incident evidence review, verification & moderation | `Planned` | Phase 8 Planned Scope |
| **Disputes (Phase 8)** | Tenant incident notification and disclosure inspection | `Planned` | Phase 8 Planned Scope |
| **Disputes (Phase 8)** | Tenant dispute submission, statement, and rebuttal evidence | `Planned` | Phase 8 Planned Scope |
| **Disputes (Phase 8)** | Admin dispute reinvestigation, correction & audit logging | `Planned` | Phase 8 Planned Scope |
| **Subscriptions (Phase 9)** | Stripe Checkout integration & recurring subscription billing | `Blocked` | Blocked by BLK-01 (Stripe test credentials) |
| **External Email** | Live outbound SMTP delivery (OTP, adverse action, disputes) | `Unverified` | Blocked by BLK-02 (5 assertions NOT TESTED) |

---

## 4. User Roles and Data Flow

### 4.1 System User Roles
The platform architecture establishes three primary operational roles:
1. **`administrator` (System Administrator / Reviewer)**:
   - Evaluates owner account registration details and business proof documents.
   - Evaluates property deeds and ownership evidence.
   - Decides property verification status (`verified`, `rejected`, `info_requested`).
   - Planned: Reviews incident allegations submitted by owners and adjudicates tenant disputes.
2. **`ft_owner` (Individual Property Owner / Landlord)**:
   - Registers rental properties and uploads ownership proof.
   - Accesses property management and updates property data.
   - Planned: Submits permissible-purpose tenant screening requests using applicant SSN.
   - Planned: Receives tenant screening reports.
   - Planned: Submits factual incident reports regarding lease violations.
3. **`ft_tenant` (Tenant / Rental Applicant)**:
   - Registers current residential address and optional past rental history (zero DOB collected at registration).
   - Inspects own disclosable records and account status.
   - Planned: Receives notifications of screening inquiries or adverse incident reports.
   - Planned: Submits formal disputes, explanations, and rebuttal evidence.

### 4.2 Proposed Screening Data Flow (Phase 7 Design)

```
[ Prospective Tenant ] ──( Rental Application )──> [ Property Owner ]
                                                          │
                                         (1) Submits SSN & Certifies Purpose
                                                          ▼
                                            [ Fraud Tenant Platform ]
                                                          │
                                         (2) Validates ASCII Format (\A[0-9]{3}...)
                                         (3) Computes Keyed HMAC Token
                                         (4) Discards Plaintext SSN (Proposed Design)
                                                          ▼
                                            [ HMAC Identity Matching ]
                                                          │
                                ┌─────────────────────────┴─────────────────────────┐
                        [ Match Found ]                                     [ No Match ]
                                │                                                   │
             ┌──────────────────┴──────────────────┐                                ▼
       [ Free Plan ]                         [ Paid Plan ]                 [ Neutral No-Match:
             │                                     │                        "No matching record
             ▼                                     ▼                        found in platform's
   [ Indicator Only:                     [ Disclosed Report:                available records as of
   "Record on File —                     Verified Factual Incidents         the search date. Does not
    Upgrade to View"                      (Approved Phase 8 Records Only) ]  establish applicant has no
    (Note: carries FCRA                               │                     adverse history or is cleared
    implications) ]                        (5) If Adverse Action Taken:     for tenancy." ]
                                               Landlord Sends Notice (§ 1681m)
                                               Tenant Accesses Dispute Portal (§ 1681i)
```

### 4.3 Critical Architectural Boundary Disclosures
1. **Owner-Uploaded Data Does Not Establish Tenant Consent**: An owner uploading a lease agreement or rental application does not independently verify that the applicant gave legally sufficient written instructions under 15 U.S.C. § 1681b(a)(2).
2. **Uploader IP Address Does Not Prove Consumer Attribution**: The IP address captured during screening request submission belongs to the property owner, not the tenant applicant.
3. **HMAC Match Represents Presence, Not Default**: A cryptographic token match in `wp_ft_tenant_identities` confirms only that the identity exists in the database; it does not indicate lease breach, default, or adverse rental behavior.
4. **Search Result Interpretation**: The result of a negative search is strictly defined as: *"No matching record found in the platform's available records as of the search date. This does not establish that the applicant has no adverse rental history or is cleared for tenancy."* Even limited match indicators (e.g. "Record on File — Upgrade to View") require legal counsel evaluation under FCRA § 1681a(d).

### 4.4 Actor-Specific Statutory Duties Under FCRA
The platform's workflows touch distinct statutory actor roles defined under federal law. Counsel must evaluate which role applies to Fraud Tenant and which applies to its users:
- **Consumer Reporting Agency (CRA)**: Duties under 15 U.S.C. §§ 1681a(f), 1681e (reasonable procedures to assure maximum possible accuracy, certification of permissible purpose, verification of user identity), and 1681i (dispute reinvestigations, 30-day resolution, notification of results, deletion of inaccurate items).
- **Report User / Landlord**: Duties under 15 U.S.C. §§ 1681b(f) (permissible purpose certification and use restriction) and 1681m(a) (written adverse action notice including CRA identity, dispute rights, 60-day free report right, and § 1681m(a)(2) credit score disclosures only if a numerical score was used).
- **Furnisher of Information**: Duties under 15 U.S.C. § 1681s-2 (duty to provide accurate information, duty to investigate disputes upon notice from a CRA).
- **Reseller**: Duties under 15 U.S.C. § 1681e(e) (identity and permissible purpose verification for re-disclosure).

---

## 5. Data Inventory and Security Controls

### 5.1 Comprehensive Data Inventory
The platform collects or proposes to collect the following categories of information:

| Data Category | Specific Fields | Supplier | Storage Location | Protection / Transformation Method | Access Authorization | Status | Legal / Privacy Concerns |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **Applicant SSN (Candidate)** | 9-digit Social Security Number | Property Owner (Applicant-supplied) | Volatile memory only; NEVER persisted | Volatile execution handling; `PROPOSED DESIGN REQUIREMENT — NOT YET IMPLEMENTED OR OPERATIONALLY VERIFIED` | Volatile service execution | Planned | Must never be cached, logged, or placed in URLs. Finite domain (\(\le 10^9\)) candidate space risk. |
| **HMAC Matching Token** | 64-character SHA-256 hex string | Computed by system from SSN + secret key | `wp_ft_tenant_identities.token_hash` | Keyed deterministic cryptographic hash (`FT_MATCH_SECRET_KEY`) — NOT encryption | Internal matching engine | Implemented in service; zero operational rows | Deterministic token; if secret key is compromised, candidate space can be precomputed offline. |
| **Masked SSN** | Last 4 digits only (`ssn_last_four`) | Computed by system from SSN | `wp_ft_tenant_identities`, `wp_ft_screening_requests` | Plaintext column | Property Owner (own searches), Admin | Schema only | Displayed as `••••-XXXX` for identification confirmation. |
| **Tenant Legal Name** | First Name, Last Name | Property Owner (Screening) or Tenant (Reg) | `wp_ft_tenant_identities.first_name`, `last_name` | Plaintext column | Owner (Screening), Admin, Tenant (Self) | Schema only | PII at rest; lacks application-level envelope encryption (BLK-08). |
| **Tenant Date of Birth** | YYYY-MM-DD | Property Owner (Screening) | `wp_ft_tenant_identities.date_of_birth` | Plaintext column | Owner (Screening), Admin | Schema only | Sensitive PII; excluded from tenant registration; lacks encryption at rest. |
| **Residential Addresses** | Street, City, State, ZIP | Tenant (Registration) | `wp_ft_user_profiles.address_line1`, `prev_address_*` | Plaintext column | Tenant (Self), Admin | Implemented and verified | Labeled as "Address on File"; not certified as verified. |
| **Property Details** | Street, Unit, City, State, ZIP, Units Count | Property Owner | `wp_ft_properties` | Plaintext column | Owner (Self), Admin | Implemented and verified | Changes to unit/address trigger automatic reverification. |
| **Ownership Proof Documents** | Deed, Tax Assessment, Utility Bills (PDF/JPG/PNG) | Property Owner | Protected storage directory (`fraud-tenant-protected/`) | Filesystem access controls; unverified host disk encryption | Document Owner, Admin Verifier | Implemented and verified | Direct access blocked by web server barriers; served via authorized streaming controller. |
| **Incident Allegations** | Category, Narrative, Lease Date, Dollar Amount | Property Owner | `wp_ft_incident_reports` | Plaintext column | Reporting Owner, Admin Reviewer, Matched Tenant | Schema only (0 rows) | Material defamation and FCRA liability if unverified or inaccurate. |
| **Incident Supporting Evidence** | Court judgments, lease docs, ledgers (PDF/JPG/PNG) | Property Owner | Protected storage directory | Filesystem access controls | Reporting Owner, Admin Reviewer, Disputing Tenant | Schema only (0 files) | Sensitive evidence; requires strict access isolation and streaming authorization. |
| **Tenant Dispute Submissions** | Dispute Reason, Statement, Evidence Files | Tenant | `wp_ft_tenant_disputes` | Plaintext column & protected files | Submitting Tenant, Admin Reviewer | Schema only (2 test fixtures) | Mandatory FCRA § 1681i reinvestigation procedures must govern handling. |
| **Authentication Credentials** | Email, Hashed Password (`wp_hash_password`), Phone | User (Owner / Tenant) | `wp_users`, `wp_ft_user_profiles` | Phpass / Bcrypt for passwords | System authentication, User (Self), Admin | Implemented and verified | Core WordPress password security standard. |
| **Email Verification OTPs** | 6-digit numeric code | System generated via `random_int` | `wp_ft_email_otps.otp_hash` | Keyed HMAC-SHA-256 (`FT_OTP_SECRET_KEY`) — NOT encryption | Internal auth service only | Implemented and verified | 10m expiry, 60s cooldown, max 5 attempts, constant-time `hash_equals()`. |
| **Application Audit Trail** | User ID, Action, Entity Type, Client IP, Redacted JSON | System generated | `wp_ft_audit_log` | Sensitive keys automatically replaced with `[REDACTED]` | Admin (Review details), Tenant (Allowlisted timeline) | Implemented and verified | Append-only application interface; database-level immutability not verified. |
| **Payment & Billing Data** | Stripe Customer ID, Subscription ID, Plan Code | Stripe Webhooks / User | `wp_ft_subscriptions`, `wp_ft_payment_events` | Minimized event payload (zero card/address data) | System billing service, Admin | Schema only (0 rows) | Minimization removes card numbers and customer addresses prior to storage. |

### 5.2 Technical and Cryptographic Safeguards
The codebase contains multiple established, automated security controls:
1. **SSN Input Pre-Validation Regex Hardening (BLK-09 — RESOLVED & VERIFIED)**:
   - In `wp-content/plugins/fraud-tenant-core/includes/Services/SecurityService.php`, `validate_ssn_format()` enforces strict whole-string anchored regular expressions using ASCII digit character sets `[0-9]`:
     ```regex
     \A[0-9]{3}-[0-9]{2}-[0-9]{4}\z    (Formatted SSN)
     \A[0-9]{9}\z                      (Raw 9-digit SSN)
     ```
   - Malformed inputs (trailing letters, trailing digits, Unicode digits, misplaced hyphens, spaces) are rejected *prior* to character stripping and hashing.
   - Tested across **27 automated assertions** in `scratch/test_blk09_ssn_validation.php` (**27/27 PASS**).
2. **Continuous Fail-Closed Behavior**:
   - `SecurityService::generate_ssn_match_token()` checks for the presence of `FT_MATCH_SECRET_KEY`. If the secret key is unconfigured or empty, the function immediately returns `false` without computing a hash or throwing unhandled exceptions.
3. **Private Protected Document Storage**:
   - Ownership documents are stored in `wp-content/uploads/fraud-tenant-protected/` (configurable outside the webroot via `FT_PROTECTED_STORAGE_DIR`).
   - Direct web server access is blocked by generated barrier files:
     - Apache: `.htaccess` with `Require all denied` and legacy `Deny from all`. Verified returning `HTTP 403 Forbidden`.
     - Microsoft IIS: `web.config` with `<deny users="*" />`.
     - Directory Traversal: `index.php` with 403 exit.
   - Streaming is controlled via `DocumentService::stream_protected_document()` in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`:
     - Dual authorization check: Requires reviewer capability OR document owner cross-verified with `property->owner_id`.
     - Traversal trapping: Enforces canonical path containment with directory separator boundary (`rtrim($canonical_base, '/\\') . DIRECTORY_SEPARATOR`).
     - Safe header handling: Aborts safely before `readfile()` if HTTP headers were already sent.
     - Single-action CSRF nonces (`ft_view_doc_{id}`) enforced.
     - Zero server filesystem paths exposed in error messages or logs.
4. **Granular Capability Separation**:
   - Dedicated property capabilities: `Capabilities::REVIEW_PROPERTIES` controls queue and detail inspection; `Capabilities::VERIFY_PROPERTIES` is strictly required for the final verification decision. Reviewers lacking verify capability are denied and audited.
5. **Tenant Dashboard Double-Gate & Neutral Phrasing**:
   - Reports must satisfy `status = 'approved' AND disclosable = 1` to appear in tenant dashboards.
   - Absolute clean-record claims were eliminated and replaced with factual, neutral wording: *"No Disclosable Incident Records Available"*, accompanied by an explicit disclaimer that platform data may be incomplete.
6. **Audit Trail Data Minimization & Redaction**:
   - `wp-content/plugins/fraud-tenant-core/includes/Services/AuditLogger.php` automatically redacts sensitive dictionary keys (`password`, `ssn`, `otp`, `token`, `secret`, `card`, etc.) to `[REDACTED]` before serialization to MySQL.
7. **PII Protection at Rest (BLK-08 — PENDING)**:
   - Sensitive PII (tenant legal names, DOBs, incident narratives) currently resides in standard database columns without application-level encryption at rest. Host filesystem encryption, database tablespace encryption, and backup encryption remain `NOT VERIFIED`.

---

## 6. Legal and Regulatory Questions

Project leadership requests that qualified U.S. legal counsel evaluate and provide written determinations for the following structured legal questions. *(Counsel is asked not to treat technical safeguards as substitutes for statutory compliance.)*

### Area 1: CRA Classification Under 15 U.S.C. § 1681a(f)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
1. Does Fraud Tenant qualify as a **Consumer Reporting Agency (CRA)** under 15 U.S.C. § 1681a(f) by assembling, evaluating, or furnishing tenant rental histories to third-party property owners for monetary fees, dues, or on a cooperative basis?
2. Does operating an automated matching engine that links prospective tenant SSN tokens to historical landlord records trigger CRA status regardless of whether reports are compiled manually or programmatically?

### Area 2: Consumer Report Definition Under 15 U.S.C. § 1681a(d)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
3. Does the transmission of landlord lease violation records or default narratives constitute a **Consumer Report** under 15 U.S.C. § 1681a(d)?
4. Does displaying a preliminary indicator that a record exists ("Record on File — Upgrade to View") or that no record was found constitute a consumer report under federal or state law?

### Area 3: Transactions-or-Experiences Exclusion Under 15 U.S.C. § 1681a(d)(2)(A)(i)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
5. Can Fraud Tenant invoke the **transactions-or-experiences exclusion** under 15 U.S.C. § 1681a(d)(2)(A)(i) as an intermediary platform aggregating and disseminating first-hand landlord experiences to third-party prospective landlords, or does the exclusion apply solely to direct, bilateral communications between the reporting landlord and the prospective landlord?

### Area 4: Cooperative & Free Tier Impact on CRA Classification
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
6. Does offering a free membership tier ($0/month) or an informal data-sharing arrangement exempt or alter the platform's classification as a CRA under 15 U.S.C. § 1681a(f) ("for monetary fees, dues, or on a cooperative nonprofit basis")?

### Area 5: Permissible Purpose Under 15 U.S.C. § 1681b
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
7. What constitutes a valid **permissible purpose** under 15 U.S.C. § 1681b for a property owner conducting a rental screening search?
8. Does a property owner's legitimate business need in connection with a rental application under 15 U.S.C. § 1681b(a)(3)(F)(i) provide sufficient statutory authority by itself, or is separate **written authorization from the consumer** under 15 U.S.C. § 1681b(a)(2) required under federal law, state law, or risk-mitigation best practice?

### Area 6: Standalone Written Consent & Authorization Form Requirements
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
9. What specific statutory language, disclosure elements, and electronic attribution records must be included in a standalone tenant screening authorization form?
10. Does an applicant's signature on a general paper lease application satisfy the platform's certification obligations under 15 U.S.C. § 1681e(a) without independent digital verification by the platform?

### Area 7: Landlord Identity and Permissible Purpose Certification (§ 1681e(a))
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
11. What specific identity verification and permissible purpose certification procedures must Fraud Tenant enforce under 15 U.S.C. § 1681e(a) prior to granting property owners access to screening reports?
12. Is the platform's existing two-step verification (email OTP + administrative deed review) legally sufficient to satisfy § 1681e(a) requirements?

### Area 8: Fair Housing Act & Screening Criteria (42 U.S.C. §§ 3601–3619)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
13. How does the **Fair Housing Act (42 U.S.C. §§ 3601–3619)** restrict the categories of tenant incident data that Fraud Tenant may collect, verify, and furnish?
14. In light of the withdrawal of the HUD 2016 Criminal Records Guidance (effective September 25, 2025; 91 FR 44867) and the pending status of HUD's 2026 disparate-impact proposed rulemaking (91 FR 1475 and 91 FR 51416), what legal standard governs criminal history records, arrest records, and eviction filings in rental screening under *Texas Dept. of Housing and Community Affairs v. Inclusive Communities Project, Inc.*, 576 U.S. 519 (2015)?
15. What policies must be established to prevent property owners from using platform reports in a discriminatory manner or establishing unlawful blanket exclusion policies?

### Area 9: Housing Adverse Action Notice Requirements (15 U.S.C. § 1681m(a))
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
16. Under 15 U.S.C. § 1681m(a) and 15 U.S.C. § 1681a(k)(1)(B)(iv), what specific disclosures must be provided to a rental applicant when a landlord denies tenancy or increases rent/deposits based in whole or in part on a Fraud Tenant report?
17. Since the statutory obligation to issue adverse action notices falls on the *user* of the report (the landlord), what legal responsibility or exposure does Fraud Tenant have to provide notice generation tools or templates?
18. If Fraud Tenant does not calculate or furnish a numerical credit score or score-like risk score, what credit score disclosure exemptions apply under 15 U.S.C. § 1681m(a)(2)?

### Area 10: Multi-Tiered Record Obsolescence Schedule (15 U.S.C. § 1681c)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
19. What specific multi-tiered obsolescence schedule must be enforced under 15 U.S.C. § 1681c for:
    - Eviction records and court judgments (§ 1681c(a)(2): 7 years from entry or statute of limitations, whichever longer)?
    - Unpaid rent, collections, and charge-offs subject to the 180-day delinquency commencement rule (§ 1681c(c)(1))?
    - Non-monetary lease violations and property damage claims (§ 1681c(a)(5): 7 years from occurrence)?
    - Criminal conviction and non-conviction records under federal law (§ 1681c(a)(5) conviction exception) versus restrictive state statutes?

### Area 11: Data Retention vs. Civil Liability Statute of Limitations (§ 1681p)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
20. How should the statute of limitations for civil liability under 15 U.S.C. § 1681p (earlier of 2 years from discovery or 5 years from violation) be distinguished from a lawful data retention and destruction schedule? Does § 1681p require or justify retaining consumer records after they become obsolete under § 1681c?

### Area 12: Tenant Dispute, Reinvestigation & Deletion Procedures (15 U.S.C. § 1681i)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
21. What statutory procedures must Fraud Tenant establish under 15 U.S.C. § 1681i to receive, investigate, and resolve tenant disputes regarding inaccurate or incomplete incident reports?
22. What are the mandatory statutory timeframes for completing reinvestigations (e.g. 30 days under federal law; shorter state timelines)?
23. What evidence must a reporting landlord provide to substantiate a challenged incident report before it may be retained or republished?
24. What obligations exist to notify prior recipients of a screening report under 15 U.S.C. § 1681i(d) if an incident is subsequently modified or deleted?

### Area 13: Furnisher Obligations (15 U.S.C. § 1681s-2)
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
25. What legal duties and liabilities apply to reporting property owners as **Furnishers of Information** under 15 U.S.C. § 1681s-2?
26. What written notices and instructions must Fraud Tenant provide to property owners regarding their statutory furnisher duties (e.g. duty to provide accurate data, duty to investigate upon notice of dispute)?

### Area 14: State Consumer Reporting & Screening Statutes
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
27. Which state consumer reporting and screening statutes apply to Fraud Tenant based on:
    - State of incorporation of Fraud Tenant?
    - State where the rental property is located?
    - State of residence of the tenant applicant?
    - State where the property owner is located?
28. Does the platform trigger mandatory annual registration, fee schedules, or bonding under **Maine Fair Credit Reporting Act (10 M.R.S. § 1310-A(1)(G))** and Bureau Rule Chapter 704 if users or properties are in Maine?
29. How do the substantive consumer reporting regulations of **Texas Business & Commerce Code Chapter 20** apply to Fraud Tenant screening operations?
30. What specific restrictions apply under **California Civil Code § 1785.1 et seq.**, **New York General Business Law § 380 et seq.**, and **Washington RCW 19.182 / RCW 59.18**?

### Area 15: Data Privacy & Breach Notification Mandates
`LEGAL QUESTION FOR COUNSEL — DETERMINATION REQUIRED`
31. Does storing keyed HMAC tokens derived from SSNs, alongside applicant legal names and dates of birth, trigger state data breach notification laws or identity theft protection mandates if a database breach occurs?
32. What consumer privacy disclosures, opt-out mechanisms, or data deletion rights apply under state privacy statutes (e.g. California CCPA/CPRA, Texas TDPSA)?

---

## 7. Technical and Operational Blockers

The 12 project blockers and dependencies are classified into four distinct categories:

### 7.1 Legal Authorization Blockers (6 Items — PENDING COUNSEL DETERMINATION)

1. **BLK-03A: FCRA CRA (§ 1681a(f)), Consumer Report (§ 1681a(d)) & Intermediary Exclusion (§ 1681a(d)(2)(A)(i))**
   - *Issue*: Operating an intermediary platform where landlords share tenant rental records creates material legal exposure of qualifying as a Consumer Reporting Agency under 15 U.S.C. § 1681a(f). The direct transactions-or-experiences exclusion (§ 1681a(d)(2)(A)(i)) applies to direct communications regarding first-hand experience; whether an intermediary platform can invoke or is excluded from this provision is an unresolved question for legal counsel.
   - *Status*: `PENDING QUALIFIED U.S. LEGAL COUNSEL DETERMINATION`.

2. **BLK-03B: State-Specific Consumer Reporting & Screening Obligations**
   - *Issue*: State consumer reporting statutes impose distinct licensing, registration, and procedural requirements:
     - **Maine**: 10 M.R.S. § 1310-A(1)(G) and Bureau Rule Chapter 704 require annual registration and fees for CRAs operating in Maine.
     - **Texas**: Business & Commerce Code Chapter 20 regulates credit reporting substantively without a separate state CRA registration bureau.
     - **California, New York, Washington**: Enforce strict state-level FCRA analogues with unique disclosure and dispute timelines.
   - *Status*: `PENDING QUALIFIED U.S. LEGAL COUNSEL DETERMINATION`.

3. **BLK-03C: Fair Housing Act Authority Status, Withdrawn Guidance, 2026 Rulemaking & Disparate Impact**
   - *Issue*:
     - Statutory classes remain governed by 42 U.S.C. §§ 3601–3619.
     - HUD 2016 Criminal Records Guidance is `WITHDRAWN` effective September 25, 2025 (Federal Register notice July 17, 2026, 91 FR 44867). Status: Non-binding historical guidance.
     - HUD April 29, 2024 Screening Guidance was removed from the HUD website in February 2025; status documented as removed per GAO-25-107196 with HUD March 2025 update. Non-binding guidance.
     - HUD 2026 Disparate-Impact Rulemaking (January 14, 2026, 91 FR 1475; August 10, 2026, 91 FR 51416) remains `PROPOSED RULEMAKING — NOT OPERATIVE REGULATION` (`FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`). Governing judicial precedent remains *Inclusive Communities*, 576 U.S. 519 (2015).
   - *Status*: `PENDING QUALIFIED U.S. LEGAL COUNSEL DETERMINATION`.

4. **BLK-03D: Permissible Purpose vs. Written Instructions vs. Internal Policy (§ 1681b)**
   - *Issue*: FCRA § 1681b provides two potential permissible purpose routes for housing screening: § 1681b(a)(3)(F)(i) (legitimate business need for a consumer-initiated transaction) and § 1681b(a)(2) (written instructions of the consumer). FCRA § 1681b(b)(2) applies to employment, not housing. Whether written consent is strictly required, advisable as risk mitigation, or mandated by state law is a question for counsel.
   - *Status*: `PENDING QUALIFIED U.S. LEGAL COUNSEL DETERMINATION`.

5. **BLK-03E: Housing Adverse-Action Notice & Credit Score Disclosures (§ 1681m(a))**
   - *Issue*: 15 U.S.C. § 1681m(a) mandates adverse action notices when a housing decision is based in whole or in part on a consumer report. The duty falls on the *user* of the report (the landlord). Credit score disclosures (§ 1681m(a)(2)) apply only if a numerical score was used. Employment pre-adverse waiting periods (§ 1681b(b)(3)) do not apply to housing.
   - *Status*: `PENDING QUALIFIED U.S. LEGAL COUNSEL DETERMINATION`.

6. **BLK-03F: Differentiated Record Obsolescence (§ 1681c, 180-Day Rule) & Retention (§ 1681p)**
   - *Issue*: FCRA § 1681c limits what a CRA may furnish in reports: bankruptcies (10 yrs); civil judgments and arrest records (7 yrs or statute of limitations, whichever is longer under § 1681c(a)(2)); charge-offs / collections (7 yrs + 180 days under § 1681c(c)(1)); other adverse items (7 yrs under § 1681c(a)(5)). Under federal law, criminal convictions are excluded from the 7-year cap, but state statutes strictly restrict conviction reporting. Section 1681p is a civil statute of limitations, not a data destruction schedule.
   - *Status*: `NOT APPROVED FOR IMPLEMENTATION — PENDING LEGAL COUNSEL DETERMINATION`.

### 7.2 Technical Design & Security Blockers (3 Items)

7. **BLK-04: SSN Matching Secret (`FT_MATCH_SECRET_KEY`) Missing — Tiered Provisioning Policy**
   - *Status*: `RUNTIME MATCHING BLOCKED BY MISSING SECRET`.
   - *Candidate Space Analysis*: The SSN domain is finite (at most \(10^9\) possible 9-digit values). HMAC-SHA-256 tokens are deterministic. If `FT_MATCH_SECRET_KEY` is compromised, an attacker can precompute the full candidate space offline.
   - *Policy*: Separate isolated development key in `wp-config.php` only when authorized development begins; production secret must be managed via an approved enterprise secrets manager (AWS Secrets Manager, HashiCorp Vault, Azure Key Vault). No production secret may be stored in local XAMPP.

8. **BLK-09: SSN Input Pre-Validation Regex Hardening**
   - *Status*: **`RESOLVED & VERIFIED`**.
   - *Evidence*: Implemented `validate_ssn_format()` with strict ASCII digit regular expressions (`\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z` and `\A[0-9]{9}\z`). Tested across 27 automated assertions (**27/27 PASS**).

9. **BLK-08: Sensitive PII Protection at Rest & Database Backup Policy**
   - *Status*: `BLOCKED — TECHNICAL POLICY & ENCRYPTION PENDING`.
   - *Issue*: Legal names, dates of birth, and incident descriptions in `wp_ft_tenant_identities` and `wp_ft_incident_reports` reside in standard database columns without application-level field encryption. Prepared SQL statements protect against injection but provide no encryption at rest. Database engine, host disk, and backup encryption remain `NOT VERIFIED`.

### 7.3 Operational Screening & Disclosure Blockers (1 Item)

10. **BLK-07: Screening Data Source & Phase 8 Dependency (Negative Screening Disclosure Freeze)**
    - *Status*: `BLOCKED — DATA SOURCE & VERIFICATION WORKFLOWS NOT IMPLEMENTED`.
    - *Evidence*: The live database contains exactly 2 synthetic test identities, 0 operational screening requests, 0 operational incident reports, and 2 synthetic dispute fixtures (`report_id = 0`).
    - *Clarification on Internal Release Gate*: The prohibition on negative screening result disclosure is an internal platform release gate and risk-mitigation policy established by project leadership to prevent potential consumer harm, reputational damage, and operational exposure while Phase 8 incident verification and dispute workflows remain unbuilt. It is not an automatic per se FCRA violation in all contexts. Counsel must evaluate the legal boundaries of this policy under FCRA §§ 1681e(b) and 1681i.

### 7.4 Release & Phase-Specific Integration Gates (2 Items)

11. **BLK-02: Outbound Email / SMTP Provider Configuration**
    - *Status*: `5 ASSERTIONS NOT TESTED — MANDATORY PRODUCTION RELEASE GATE`.
    - *Scope*: Local development uses `wp_mail()` with local fallback. Working live SMTP is a mandatory production release gate for live delivery of OTPs, statutory adverse action notices, dispute confirmations, and platform notifications.

12. **BLK-01: Stripe API Keys and Webhook Secret**
    - *Status*: `PHASE 9 INTEGRATION DEPENDENCY (PENDING STRIPE CREDENTIALS)`.
    - *Scope*: Requires `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, and `STRIPE_WEBHOOK_SECRET`. This is a phase-specific external service integration dependency for Phase 9 (Membership Plans & Subscriptions); it does not block Phase 7 screening design.

---

## 8. State and Launch Jurisdiction Review

### 8.1 Launch Jurisdiction — Owner Confirmation Required
The Fraud Tenant codebase and technical documentation reference multiple jurisdictions (Maine, Texas, California, New York, Washington, and federal frameworks). However, **the repository records do not establish a confirmed corporate launch jurisdiction or phased roll-out plan.**

> [!IMPORTANT]
> **Owner Confirmation Required**: Project ownership must formally confirm the initial launch geography before legal counsel can provide operative state-specific screening rules, disclosures, and registration filings. Until project ownership confirms the launch states, this review covers these jurisdictions as representative frameworks for counsel evaluation.

### 8.2 Jurisdictional Evaluation Matrix for Counsel Review

| Jurisdiction | Status in Documentation | Relevant Statutes / Regulatory Regimes | Counsel Review Scope |
| :--- | :--- | :--- | :--- |
| **Federal (Nationwide)** | Primary Framework | FCRA (15 U.S.C. § 1681 et seq.), FHA (42 U.S.C. § 3601 et seq.) | Baseline CRA, permissible purpose, adverse action, obsolescence |
| **Maine** | Referenced in BLK-03B | Maine FCRA (10 M.R.S. Ch. 209-B § 1310-A(1)(G)), Bureau Rule Ch. 704 | Annual CRA registration, recordkeeping, fee schedules |
| **Texas** | Referenced in BLK-03B | Texas Business & Commerce Code Ch. 20, Texas TDPSA | Substantive reporting rules, 30-day dispute reinvestigation, privacy compliance |
| **California** | Referenced in BLK-03B | California Consumer Credit Reporting Agencies Act (Civ. Code § 1785.1 et seq.), Investigative Consumer Reporting Agencies Act (Civ. Code § 1786 et seq.), CCPA/CPRA | Strict 7-year limits, non-conviction arrest bans, standalone disclosures, state privacy |
| **New York** | Referenced in BLK-03B | NY General Business Law Article 25 (§ 380 et seq.), NY Housing Stability and Tenant Protection Act of 2019 (Real Prop. Law § 227-f) | Conviction reporting limits, application fee caps, court/eviction record restrictions |
| **Washington** | Referenced in BLK-03B | Fair Credit Reporting Act (RCW 19.182), Residential Landlord-Tenant Act (RCW 59.18) | Notice requirements, tenant screening criteria disclosures, adverse action |
| **Other Target States** | **UNVERIFIED** | State-specific landlord-tenant and consumer reporting acts | To be evaluated once owner confirms launch roster |

---

## 9. Counsel Deliverables

To transition Fraud Tenant from `BLOCKED` to `READY` for Phase 7 implementation, project leadership requests that counsel provide the following concrete deliverables:

### A. Mandatory Written Legal Determinations
- [ ] **DELIV-01: Formal CRA & Consumer Report Classification Opinion**: Written opinion determining whether the platform is a CRA and whether screening outputs are consumer reports under 15 U.S.C. § 1681a.
- [ ] **DELIV-02: Permissible Purpose & Housing Consent Policy**: Legal determination of permissible purpose requirements under 15 U.S.C. § 1681b and required applicant authorization procedures.
- [ ] **DELIV-03: Launch Jurisdiction Compliance Matrix**: State-by-state matrix covering registration, licensing, bonding, and unique procedural rules for each confirmed launch state.
- [ ] **DELIV-04: Fair Housing Screening Criteria Guidelines**: Formal guidelines establishing lawful rental incident reporting and screening criteria under 42 U.S.C. §§ 3601–3619.
- [ ] **DELIV-05: Multi-Tiered Record Obsolescence & Data Retention Schedule**: Formal schedule defining maximum reporting periods under 15 U.S.C. § 1681c and lawful document destruction rules.

### B. Standardized Legal Notice & Form Templates
- [ ] **DELIV-06: Standalone Tenant Disclosure & Screening Authorization Form**: Legally compliant template for property owners to obtain written authorization from applicants.
- [ ] **DELIV-07: Standardized Housing Adverse-Action Notice Template**: Legally compliant notice template conforming to 15 U.S.C. § 1681m(a) and applicable state laws.
- [ ] **DELIV-08: Data Dispute, Reinvestigation & Correction Workflow**: Standard operating procedure governing tenant disputes under 15 U.S.C. § 1681i, including notification to prior report recipients.
- [ ] **DELIV-09: Platform Terms of Service & Privacy Policy Updates**: Review and sign-off on public terms, disclaimers, and privacy policies.

### C. Operational & Governance Preconditions
- [ ] **DELIV-10: Written Conditions Precedent for Phase 7 Implementation**: Clear conditions specifying what technical and operational controls must be in place before SSN search development may commence.
- [ ] **DELIV-11: Written Conditions Precedent for Negative-Result Disclosure**: Clear conditions specifying what Phase 8 verification and dispute mechanisms must be operational before negative reports may be delivered to property owners.

---

## 10. Phase 7 Go/No-Go Checklist

The following gate checklist governs transition into Phase 7:

| Prerequisite ID | Category | Requirement Description | Verification Evidence Required | Current Status |
| :--- | :--- | :--- | :--- | :---: |
| **GATE-01** | Legal | Written legal determination of CRA status and FCRA applicability (BLK-03A) | Formal written opinion letter from qualified U.S. legal counsel | `NOT SATISFIED` |
| **GATE-02** | Legal | Confirmed launch jurisdictions and state compliance review (BLK-03B) | Owner launch roster confirmation + Counsel state compliance review | `NOT SATISFIED` |
| **GATE-03** | Legal | Fair Housing Act screening criteria and disparate impact policy (BLK-03C) | Counsel-approved screening criteria guidelines | `NOT SATISFIED` |
| **GATE-04** | Legal | Approved tenant disclosure and authorization templates (BLK-03D) | Counsel-drafted standalone authorization template | `NOT SATISFIED` |
| **GATE-05** | Legal | Standardized housing adverse-action notice templates (BLK-03E) | Counsel-drafted adverse-action notice template | `NOT SATISFIED` |
| **GATE-06** | Legal | Multi-tiered obsolescence schedule conforming to § 1681c (BLK-03F) | Counsel-approved obsolescence and retention schedule | `NOT SATISFIED` |
| **GATE-07** | Technical | Strict ASCII SSN format validation regex (BLK-09) | `SecurityService::validate_ssn_format()` implemented; 27 tests PASS | **`SATISFIED`** |
| **GATE-08** | Technical | Fail-closed SSN matching engine without secret key | `SecurityService::generate_ssn_match_token()` verified failing closed | **`SATISFIED`** |
| **GATE-09** | Security | Tiered secret key provisioning policy for `FT_MATCH_SECRET_KEY` (BLK-04) | Non-production dev key defined only upon authorization; vault for prod | `NOT SATISFIED` |
| **GATE-10** | Security | Application-level sensitive PII encryption-at-rest strategy (BLK-08) | Documented encryption policy for tenant legal names, DOBs, incidents | `NOT SATISFIED` |
| **GATE-11** | Operational | Phase 8 incident verification and tenant dispute workflows (BLK-07) | Phase 8 moderation queue and dispute procedures verified operational | `NOT SATISFIED` |
| **GATE-12** | Governance | Explicit project leadership authorization to begin Phase 7 | Written authorization from Fraud Tenant project leadership | `NOT SATISFIED` |

**Gate Decision**: **10 of 12 prerequisites remain NOT SATISFIED**. Phase 7 implementation remains strictly **BLOCKED**.

---

## 11. Legal Source Register

The following register compiles the primary statutes, administrative rules, court decisions, and regulatory agency documents cited in this review package:

| Official Citation | Short Description | Official URL / Source Reference | Date Checked | Legal Authority Status |
| :--- | :--- | :--- | :---: | :--- |
| **15 U.S.C. § 1681a** | FCRA Definitions: CRA (§ 1681a(f)), Consumer Report (§ 1681a(d)), Transactions-or-Experiences Exclusion (§ 1681a(d)(2)(A)(i)) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681a&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681b** | FCRA Permissible Purposes: Written Instructions (§ 1681b(a)(2)), Business Transaction (§ 1681b(a)(3)(F)(i)) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681b&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681c** | FCRA Reporting Obsolescence: Civil Suits/Judgments (§ 1681c(a)(2)), 180-Day Rule (§ 1681c(c)(1)), 7-Year Cap & Convictions (§ 1681c(a)(5)) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681c&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681e** | FCRA Compliance Procedures: Maximum Possible Accuracy (§ 1681e(b)), Permissible Purpose Certification (§ 1681e(a)), Resellers (§ 1681e(e)) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681e&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681i** | FCRA Dispute Procedures: 30-Day Reinvestigation, Mandatory Deletion, Prior Recipient Notification | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681i&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681m(a)** | FCRA User Duties: Written Adverse Action Notice, CRA Identity, Credit Score Exception (§ 1681m(a)(2)) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681m&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681p** | FCRA Statute of Limitations: Civil Liability Limitation (2 Years Discovery / 5 Years Occurrence) | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681p&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **15 U.S.C. § 1681s-2** | FCRA Furnisher Responsibilities: Duty to Provide Accurate Data, Investigation of Disputed Items | [uscode.house.gov](https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title15-section1681s-2&num=0&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **42 U.S.C. §§ 3601–3619** | Fair Housing Act: Prohibition on Housing Discrimination in Rental, Screening, and Terms | [uscode.house.gov](https://uscode.house.gov/view.xhtml?path=/prelim@title42/chapter45&edition=prelim) | 2026-11-01 | Binding Federal Statute |
| **576 U.S. 519 (2015)** | *Texas Dept. of Housing & Community Affairs v. Inclusive Communities Project, Inc.*: Disparate Impact Standard | [supreme.justia.com](https://supreme.justia.com/cases/federal/us/576/519/) | 2026-11-01 | Binding U.S. Supreme Court Precedent |
| **91 FR 44867 (2026)** | HUD Notice of Withdrawal of 2016 Criminal Guidance (Effective Sept 25, 2025; Published July 17, 2026) | [federalregister.gov](https://www.federalregister.gov/documents/2026/07/17/2026-15123/withdrawal-of-guidance-on-application-of-fair-housing-act-standards) | 2026-11-01 | Withdrawn Agency Guidance |
| **GAO-25-107196 (2025)** | GAO Report on HUD Screening Guidance Removal (Guidance Removed Feb 2025; HUD March 2025 Update) | [gao.gov](https://www.gao.gov/products/gao-25-107196) | 2026-11-01 | Non-Binding Agency Guidance / Informational |
| **91 FR 1475 & 91 FR 51416 (2026)** | HUD Proposed Disparate Impact Rulemakings (Jan 14, 2026 & Aug 10, 2026) | [federalregister.gov](https://www.federalregister.gov/documents/2026/01/14/2026-00512/discriminatory-effects-standard) | 2026-11-01 | Proposed Rulemaking — Not Operative Regulation |
| **10 M.R.S. § 1310-A(1)(G)** | Maine Fair Credit Reporting Act: Mandatory Annual Registration of Consumer Reporting Agencies | [legislature.maine.gov](https://legislature.maine.gov/statutes/10/title10sec1310-A.html) | 2026-11-01 | Binding State Statute |
| **Maine Bureau Rule Ch. 704** | Maine Bureau of Consumer Credit Protection: Consumer Reporting Agency Registration & Fee Schedule | [maine.gov/sos/cec/rules/02/chaps02.htm](https://www.maine.gov/sos/cec/rules/02/chaps02.htm) | 2026-11-01 | Binding State Administrative Rule |
| **Tex. Bus. & Com. Code Ch. 20** | Texas Regulation of Consumer Credit Reporting Agencies: Substantive Requirements & Disputes | [statutes.capitol.texas.gov](https://statutes.capitol.texas.gov/Docs/BC/htm/BC.20.htm) | 2026-11-01 | Binding State Statute |
| **Cal. Civ. Code § 1785.1 et seq.** | California Consumer Credit Reporting Agencies Act: Commercial Reporting, Disclosures, Disputes | [leginfo.legislature.ca.gov](https://leginfo.legislature.ca.gov/faces/codes_displaySection.xhtml?lawCode=CIV&sectionNum=1785.1) | 2026-11-01 | Binding State Statute |
| **Cal. Civ. Code § 1786 et seq.** | California Investigative Consumer Reporting Agencies Act: Character & Background Reporting | [leginfo.legislature.ca.gov](https://leginfo.legislature.ca.gov/faces/codes_displaySection.xhtml?lawCode=CIV&sectionNum=1786) | 2026-11-01 | Binding State Statute |
| **N.Y. Gen. Bus. Law § 380 et seq.** | New York Fair Credit Reporting Act: Reporting Limits, Tenant Screening, Obsolescence | [nysenate.gov](https://www.nysenate.gov/legislation/laws/GBS/A25) | 2026-11-01 | Binding State Statute |
| **N.Y. Real Prop. Law § 227-f** | New York Housing Stability and Tenant Protection Act of 2019: Eviction Record Screening Limits | [nysenate.gov](https://www.nysenate.gov/legislation/laws/RPP/227-F) | 2026-11-01 | Binding State Statute |
| **Wash. Rev. Code § 19.182** | Washington Fair Credit Reporting Act: Commercial Screening, Disclosure, and Dispute Rules | [app.leg.wa.gov](https://app.leg.wa.gov/RCW/default.aspx?cite=19.182) | 2026-11-01 | Binding State Statute |
| **Wash. Rev. Code § 59.18.257** | Washington Residential Landlord-Tenant Act: Screening Criteria Disclosures & Adverse Action | [app.leg.wa.gov](https://app.leg.wa.gov/RCW/default.aspx?cite=59.18.257) | 2026-11-01 | Binding State Statute |

---

## 12. Unresolved Owner Decisions

The following foundational business and governance decisions remain unconfirmed in the repository and require formal resolution by project ownership:

### 12.1 Target Launch Geography
- **Current State in Repository**: Multiple state frameworks (Maine, Texas, California, New York, Washington) are referenced, but no single launch state or prioritized sequence of states has been formally designated.
- **Owner Action Required**: Designate the specific initial state(s) of operation so that counsel can focus state-specific licensing, registration, and disclosure requirements.

### 12.2 Corporate Structure and Entity Jurisdiction
- **Current State in Repository**: The legal entity type (LLC, C-Corp) and state of organization of Fraud Tenant are not specified in repository documentation.
- **Owner Action Required**: Confirm the operating entity name, form of organization, and domicile state to establish the platform's primary corporate jurisdiction for regulatory oversight and interstate commerce analysis.

### 12.3 Confirmation of Commercial Tiers and Pricing
- **Current State in Repository**: Pricing figures ($0 Free Tier, $29/month Standard Monthly, $290/year Standard Annual) appear in seed data and planning documentation, but have not received commercial sign-off.
- **Owner Action Required**: Formally confirm or revise the pricing schedule, billing cycles, and feature access matrix for each commercial tier.

### 12.4 Enterprise Secrets Management Infrastructure
- **Current State in Repository**: `FT_MATCH_SECRET_KEY` is intentionally unprovisioned in all environments. A tiered provisioning policy requires an enterprise key vault for production, but no cloud key management service has been selected.
- **Owner Action Required**: Select and provision an enterprise key management provider (e.g. AWS Secrets Manager, HashiCorp Vault, Azure Key Vault) for production runtime secrets.

### 12.5 Record Retention & Purge Policy
- **Current State in Repository**: Technical database schema supports record creation, but automated deletion or archiving routines based on statutory obsolescence periods have not been defined or implemented.
- **Owner Action Required**: Establish an operational business retention policy aligned with counsel's advice on 15 U.S.C. § 1681c obsolescence periods and 15 U.S.C. § 1681p civil liability windows.

---
*End of Legal Counsel Review Package (Version 2). Awaiting formal evaluation by qualified U.S. legal counsel and project ownership.*
