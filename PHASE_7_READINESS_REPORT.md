# FRAUD TENANT — PHASE 7 READINESS & ARCHITECTURE REPORT
## Antigravity Pro — Final Legal & Technical Documentation Reconciliation Pass (Round 4)
### Comprehensive Architecture Audit, Statutory Analysis & Compliance Review

---

**Project Name:** Fraud Tenant — WordPress PropTech Platform  
**Local Environment URL:** `http://localhost/fraudtenant`  
**Repository Working Directory:** `C:\xampp\htdocs\fraudtenant`  
**Git Branch:** `main`  
**Git Commit HEAD:** `e9594390f11b8f4d7968def92f42b1d62f46d2ac`  
**Database Schema Version:** `1.3.0` (14 Custom Tables)  
**WordPress Core Version:** `7.1.3`  
**PHP Runtime Version:** `8.2.12` (XAMPP Windows x64)  
**Audit Baseline Date (Preserved):** October 31, 2026  
**Documentation Freeze & Final Review Date:** November 1, 2026  
**Document Type:** Architecture Audit, Statutory Analysis, Security Review & Blocker Classification  
**Operational Authorization Status:** READ-ONLY AUDIT — ZERO PHASE 7 IMPLEMENTATION AUTHORIZED  

---

## 1. Executive Summary

A comprehensive, evidence-based architecture and legal readiness audit was conducted on the Fraud Tenant repository to evaluate readiness for **Phase 7 (Authorized Tenant Search & Matching Engine)**.

Fraud Tenant has successfully completed Phase 0 through Phase 6.1.1, with Phase 6 formally closed. The platform possesses a robust, secure architectural shell, an established 14-table database foundation, isolated document streaming controls, and an established regression test baseline (543 passing assertions out of 548 unique catalog assertions, with 5 outbound SMTP assertions properly classified as `NOT TESTED`).

However, technical capability and architectural foundations do **not** establish readiness for operational tenant screening or disclosure of consumer records. Significant legal, regulatory, security, data-source, and operational dependencies remain unresolved:

1. **CRA Classification Unresolved**:
   - `CURRENT OPERATIONAL DATA: NO OPERATIONAL SCREENING RECORDS EVIDENCED`
   - `LEGAL CLASSIFICATION: NOT DETERMINED — FORMAL COUNSEL REVIEW REQUIRED`
   - Aggregating landlord incident reports and disclosing them to other property owners creates a material risk of classification as a Consumer Reporting Agency (CRA) under 15 U.S.C. § 1681a(f), because the direct transaction-or-experience exclusion under § 1681a(d)(2)(A)(i) does not readily shield an intermediary platform.
2. **Missing Qualified Legal Counsel Written Determination**: No formal legal determination has been rendered by qualified U.S. consumer reporting counsel regarding CRA status, permissible purpose workflows under § 1681b(a)(2) and § 1681b(a)(3)(F), housing adverse action notices under § 1681m(a), state-specific registration (such as Maine 10 M.R.S. § 1310-A(1)(G)), or differentiated obsolescence schedules under § 1681c.
3. **Missing Server Secret Key (`FT_MATCH_SECRET_KEY`)**: The runtime HMAC-SHA256 matching engine in `SecurityService::generate_ssn_match_token()` fails closed because the server secret key is unconfigured in the inspected environment.
4. **SSN Raw Input Validation Deficiency**: Code inspection confirms that `SecurityService::generate_ssn_match_token()` strips non-digits before validating length, allowing malformed strings with trailing characters or unexpected formatting (e.g., `123-45-6789abc` or `123456789extra`) to be hashed without error.
5. **PII Protection & Encryption Architecture Unresolved**: While SSNs are protected via one-way HMAC-SHA256 hashes, applicant legal names, dates of birth, and incident details lack application-level field encryption. Database tablespace, host disk volume, and backup encryption remain `NOT VERIFIED`.
6. **Data Source Void & Phase 8 Dependency**: The database contains zero operational screening records, zero operational incident reports, and zero operational review decisions. Disclosing negative screening information before Phase 8 incident verification, human review gates, and dispute workflows exist creates severe defamation and regulatory liability.
7. **Release & Integration Gates Unresolved**: Live outbound SMTP delivery remains `NOT TESTED` (5 assertions), which serves as a mandatory production release gate for statutory notifications and OTP dispatches, and Stripe credentials remain pending for Phase 9.

### Categorical Audit Verdict:
# **`BLOCKED — PREREQUISITES NOT SATISFIED`**

Phase 7 implementation must **not** be authorized or commenced. Fraud Tenant must not be described as production-ready, legally compliant, or authorized to disclose tenant screening results.

---

## 2. Repository and Environment State

Inspection of the physical environment, Git repository, and database confirmed the following exact baseline:

| Attribute | Verified Value | Evidence / Verification Method |
|---|---|---|
| **Git Working Directory** | `C:\xampp\htdocs\fraudtenant` | `git.exe status`, working tree inspection |
| **Git Active Branch** | `main` | `git.exe branch --show-current` |
| **Git Commit HEAD** | `e9594390f11b8f4d7968def92f42b1d62f46d2ac` | `git.exe rev-parse HEAD` |
| **Working Tree State** | Modified `BLOCKERS.md`, Untracked `PHASE_7_READINESS_REPORT.md` (and pre-existing untracked backup folders) | `git.exe status --short` (Zero code or database changes) |
| **WordPress Core** | `7.1.3` | `get_bloginfo('version')` via XAMPP PHP runtime |
| **PHP Runtime** | `8.2.12 (cli) ZTS Visual C++ 2019 x64` | `php.exe -v` |
| **Database Schema** | `1.3.0` across 14 custom tables | `fraud_tenant_db_version` in `wp_options` |
| **Operational Records** | 2 synthetic identity fixtures, 2 synthetic dispute fixtures (`report_id = 0`), 0 operational screening requests, 0 operational incident reports | Direct `$wpdb` count query inspection |
| **Historical QA Baseline** | 548 unique assertions: 543 PASS, 5 SMTP NOT TESTED, 161/161 regression executions PASS | Phase 6 Final Closure Report reconciliation |

---

## 3. Verified Technical Architecture

### 3.1 Database Tables: Operational Foundations vs. Placeholder Schemas

The 14 custom database tables created in Phase 1 dynamically use `$wpdb->prefix`. Inspection distinguishes operational tables backed by active Phase 1–6 application logic from schema foundations reserved for future phases:

```
├── OPERATIONAL TABLES (Phases 1–6 Logic Verified)
│   ├── wp_ft_user_profiles       (User metadata, approval statuses, business details)
│   ├── wp_ft_email_otps          (Hashed OTPs, rate-limiting, expiration, purpose)
│   ├── wp_ft_properties          (Owner property listings, verification statuses)
│   ├── wp_ft_documents           (Protected document metadata, mime types, access rules; application encryption NOT IMPLEMENTED)
│   └── wp_ft_audit_log           (APPLICATION-LEVEL APPEND-ONLY AUDIT LOG — DATABASE-LEVEL IMMUTABILITY NOT VERIFIED)
│
└── SCHEMA FOUNDATIONS (Future Phase Logic — Zero Operational Rows)
    ├── wp_ft_tenant_identities   (2 synthetic test fixtures; zero operational applicant records)
    ├── wp_ft_screening_requests  (0 rows; Phase 7 search history and permissible purpose tracking)
    ├── wp_ft_incident_reports    (0 rows; Phase 8 incident allegations and financial claims)
    ├── wp_ft_report_reviews      (0 rows; Phase 8 admin moderation and provenance decisions)
    ├── wp_ft_tenant_disputes     (2 synthetic test fixtures with report_id=0; Phase 8 dispute queue)
    ├── wp_ft_membership_plans   (Phase 9 subscription tiers and pricing definitions)
    ├── wp_ft_subscriptions       (Phase 9 Stripe customer and subscription mapping)
    ├── wp_ft_payment_events      (Phase 9 Stripe webhook idempotency log)
    └── wp_ft_notifications       (Phase 10 in-app notification queue)
```

#### Audit Log Immutability Clarification:
Inspection of `AuditLogger.php` verifies that application code exposes only `log()` (insert) and `get_logs()` (select). No application methods exist to update or delete audit records. However, no database-level constraints (e.g., MySQL triggers blocking `UPDATE`/`DELETE`, read-only database user grants, cryptographic hash chaining, or external write-once archival) are implemented or verified. Therefore, it is accurately designated:  
`APPLICATION-LEVEL APPEND-ONLY AUDIT LOG — DATABASE-LEVEL IMMUTABILITY NOT VERIFIED`.

### 3.2 Private Document Streaming & HTTP Header Safety

The document protection subsystem implemented in `DocumentService::stream_protected_document()` (`wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`) was verified in Phase 6.1 and 6.1.1:

1. **Storage Confinement**: Documents reside in `wp-content/uploads/fraud-tenant-protected/`.
2. **Web Server Direct-Access Denial**:
   - **Apache**: Protected by `.htaccess` containing `<IfModule mod_authz_core.c> Require all denied </IfModule>` and `Deny from all`. Verified returning `403 Forbidden` on direct URL access.
   - **IIS**: Protected by auto-generated `web.config` with `<deny users="*" />`.
   - **Nginx**: Recommended deployment requires defining `FT_PROTECTED_STORAGE_DIR` outside the document root (e.g., `/var/www/fraud-tenant-protected`) or adding an explicit `location ^~ /wp-content/uploads/fraud-tenant-protected/ { deny all; return 403; }` block.
3. **Directory Separator Boundary Normalization**: Path traversal is trapped using `$normalized_base = rtrim($canonical_base, '/\\') . DIRECTORY_SEPARATOR;` preventing sibling-prefix directory escapes.
4. **Dual Authorization & IDOR Trapping**: Access requires explicit user capability (`ft_review_properties`, `ft_verify_properties`, `manage_options`) or direct ownership verification (`(int) $doc->user_id === $requesting_user_id`). Property deeds require cross-referencing `wp_ft_properties.owner_id`.
5. **CSRF Nonce Limitation**: Document URLs utilize WordPress nonces generated with action `'ft_view_doc_' . $document_id`. A WordPress nonce is a time-windowed CSRF defense mechanism; it does not replace server-side authentication, capability enforcement, or ownership checks.
6. **HTTP Header & Streaming Safety**: The service inspects `headers_sent()`. If headers have already been sent, the application aborts safely before `readfile()`, logging `document.streaming_aborted_headers_sent` in `wp_ft_audit_log` without leaking filesystem paths. Note: If headers were already flushed to the client, the application cannot alter the HTTP status code (e.g., from 200 to 500), but `wp_die(..., 500)` terminates execution and prevents protected byte streaming.

---

## 4. Corrected FCRA Analysis

The Fair Credit Reporting Act (FCRA, 15 U.S.C. § 1681 et seq.) regulates the collection, dissemination, and use of consumer report information. The analysis below reflects primary statutory text and current regulatory authority.

### 4.1 Permissible Purpose vs. Written Authorization (§ 1681b)

Under the FCRA, consumer reporting agencies may furnish consumer reports only for enumerated permissible purposes. Two distinct permissible-purpose provisions are relevant to rental housing:

#### A. Section 1681b(a)(2): Written Instructions of the Consumer
A consumer reporting agency may furnish a consumer report *"in accordance with the written instructions of the consumer to whom it relates."* Written applicant instructions independently establish a valid permissible purpose under federal statute if properly documented and attributable to that consumer. A property owner's unilateral statement or an uploaded document does not automatically establish that the applicant personally provided written instructions.

#### B. Section 1681b(a)(3)(F)(i): Legitimate Business Need
A consumer reporting agency may furnish a consumer report to a person it has reason to believe *"has a legitimate business need for the information in connection with a business transaction that is initiated by the consumer."* An individual applying to lease real property initiates a business transaction, providing a potential statutory permissible purpose for rental screening, subject to actual facts and counsel's determination.

#### C. Reconciliation of Permissible-Purpose Routes
The two routes are distinct and not interchangeable:
1. **Under § 1681b(a)(3)(F)(i)**, the user's business need must arise from a transaction initiated by the consumer (such as a submitted rental application).
2. **Under § 1681b(a)(2)**, the written instructions must be genuinely attributable to the consumer. A property owner's unilateral declaration or an uploaded document does not automatically constitute valid written instructions of the consumer without verifiable attribution.
3. The specific route (or combination of routes) relied upon must be formally reviewed and approved by qualified legal counsel.

#### D. Section 1681b(f) & Section 1681e(a): User Certification and CRA Verification Duties
- **User Restrictions (§ 1681b(f))**: A person may not obtain or use a consumer report unless it is obtained for an authorized permissible purpose under § 1681b and the purpose is certified in accordance with § 1681e by the prospective user.
- **CRA Compliance Procedures (§ 1681e(a))**: A CRA must maintain reasonable procedures to identify prospective users, obtain certifications that reports will be used solely for authorized purposes, and make reasonable efforts to verify each new prospective user and their certified uses prior to furnishing reports.

#### E. Employment-Specific Authorization vs. Housing Screening
FCRA 15 U.S.C. § 1681b(b)(2) mandates standalone written disclosures and written authorizations specifically for **employment purposes**. Federal statute does not universally mandate this employment-specific procedure for housing screening transactions under § 1681b(a)(3)(F). However, obtaining written tenant authorization is an essential evidentiary safeguard to document the landlord's permissible purpose and may be independently required by state tenant privacy statutes, upstream data provider agreements, or Fraud Tenant's internal risk policy.

**Status:** `PENDING LEGAL COUNSEL REVIEW`.

### 4.2 Complete Adverse-Action Notice Requirements (§ 1681m(a))

Under **15 U.S.C. § 1681a(k)(1)(B)(iv)**, an adverse action includes:
> *"an action taken or determination that is— (I) made in connection with an application that was made by any consumer, or in connection with a business transaction that is initiated by any consumer; and (II) adverse to the interests of the consumer."*

When a property owner takes adverse action (e.g., rejecting an applicant, requiring a co-signer, or demanding an increased deposit) based in whole or in part on information contained in a consumer report, **15 U.S.C. § 1681m(a)** governs the required disclosures:

1. **Notice of Adverse Action**: Oral, written, or electronic notice of the action to the consumer (§ 1681m(a)(1)).
2. **Numerical Credit Score Disclosures (§ 1681m(a)(2))**: If an adverse action is based in whole or in part on a numerical credit score, the notice must disclose the credit score, score range, date created, provider entity, and up to 4 key adverse factors (or 5 if inquiries were a factor). Non-credit rental history reports containing only factual lease incidents do not trigger numerical credit score disclosures unless a numerical credit score was used in taking the adverse action.
3. **CRA Contact Information**: The name, address, and telephone number of the CRA that furnished the report (§ 1681m(a)(3)(A)).
4. **Toll-Free Number**: A toll-free telephone number if the agency is a nationwide CRA under § 1681a(p) (§ 1681m(a)(3)(A)).
5. **Non-Involvement Disclaimer**: A statement that the CRA did not make the decision to take the adverse action and cannot provide the reasons why the action was taken (§ 1681m(a)(3)(B)).
6. **60-Day Free Disclosure Right**: Notice of the consumer's right to obtain a free copy of the consumer report within 60 days under § 1681j (§ 1681m(a)(4)(A)).
7. **Dispute Rights**: Notice of the consumer's right to dispute the accuracy or completeness of any information with the CRA under § 1681i (§ 1681m(a)(4)(B)).

**Distinction from Employment**: The employment-specific pre-adverse action notice and waiting period under § 1681b(b)(3) does not govern housing rental transactions under federal statute.

**Status:** `PENDING LEGAL COUNSEL REVIEW`.

### 4.3 Differentiated Record Obsolescence & Retention (§ 1681c)

FCRA 15 U.S.C. § 1681c establishes **restrictions on what a consumer reporting agency may include in a consumer report**. It is **not** a universal data retention or destruction mandate for landlords or property owners holding historical business records.

#### Mandatory Distinction:
1. **Reporting Restrictions**: Statutory limits on including obsolete negative information in a furnished consumer report.
2. **System Retention**: Retention of underlying source records within a CRA's systems for audit, compliance, and dispute verification.
3. **Owner Evidence Retention**: Retention of original lease documents and evidence by individual property owners.
4. **State / Local Requirements**: Separate state or local record-retention statutes.

#### Statutory Reporting Limits (§ 1681c(a)):
- **Bankruptcy (§ 1681c(a)(1))**: Cases under title 11 or under the Bankruptcy Act that, from the date of entry of the order for relief or the date of adjudication, as the case may be, antedate the report by more than 10 years.
- **Civil Suits, Civil Judgments, and Records of Arrest (§ 1681c(a)(2))**: Civil suits, civil judgments, and records of arrest that, from date of entry, antedate the report by more than seven years or until the governing statute of limitations has expired, whichever is the longer period.
- **Paid Tax Liens (§ 1681c(a)(3))**: Paid tax liens which, from date of payment, antedate the report by more than seven years.
- **Accounts Placed for Collection or Charged to Profit and Loss (§ 1681c(a)(4) & § 1681c(c)(1))**:
  - The 7-year period is **not** measured directly from the date of collection referral or charge-off.
  - Under **15 U.S.C. § 1681c(c)(1)**, the 7-year period begins **upon the expiration of the 180-day period beginning on the date of the commencement of the delinquency** which immediately preceded the collection activity, charge-off, or similar action. This yields a maximum reporting window of approximately 7.5 years from the initial delinquency.
- **Other Adverse Information (§ 1681c(a)(5))**: Any other adverse item of information, other than records of convictions of crimes, which antedates the report by more than seven years.
- **Criminal Convictions**: Under federal FCRA § 1681c(a)(5), records of convictions of crimes are excluded from the 7-year reporting limit and are not restricted by federal FCRA reporting time limits. However, state statutes differ significantly in their treatment of criminal records; certain states establish specific time limits or restrict the reporting of criminal convictions and unadjudicated arrest records (e.g., California Civil Code § 1785.13(a)(6) restricts reporting of criminal convictions beyond seven years from disposition, release, or parole, and New York General Business Law § 380-j restricts certain arrest and conviction reporting subject to statutory compensation thresholds). Because state-level criminal reporting restrictions, exceptions, and fair-chance housing laws vary widely by jurisdiction, state-law applicability must be evaluated on a state-by-state basis by qualified legal counsel rather than generalized into a single rule. Furthermore, Fair Housing Act disparate-impact standards govern the use of criminal records in rental screening.
- **Section 1681c(b) Monetary Exceptions**: The reporting restrictions of § 1681c(a)(1)–(5) do not apply to credit transactions involving a principal amount of $150,000 or more, life insurance underwriting involving a face amount of $150,000 or more, or employment of an individual at an annual salary of $75,000 or more. These exceptions do not apply to typical residential rental screening.

**Technical Defect Trap**: A simplistic SQL filter like `WHERE incident_date >= NOW() - INTERVAL 7 YEAR` is legally defective because it violates the statutory 180-day rule for charge-offs, misapplies judgment limitation rules, and ignores state-specific restrictions.

**Status:** `NOT APPROVED FOR IMPLEMENTATION — PENDING LEGAL COUNSEL REVIEW`.

### 4.4 Limitation Period vs. Data Retention Mandate (§ 1681p)

FCRA 15 U.S.C. § 1681p governs the **statute of limitations for civil liability**:
> An action must be brought not later than the earlier of (1) 2 years after the date of discovery by the plaintiff of the violation that is the basis for such liability; or (2) 5 years after the date on which the violation that is the basis for such liability occurs.

**Clarification**: Section 1681p does **not** prescribe a universal 5-year retention period for consent forms, screening logs, or tenant records. Any proposed 5-year data retention schedule represents an internal risk-management policy requiring counsel approval, not a statutory retention mandate under § 1681p.

### 4.5 Consumer Dispute Procedures (§ 1681i)

If Fraud Tenant is classified as a CRA, it must comply with statutory dispute reinvestigation procedures under **15 U.S.C. § 1681i**:
- **30-Day Reinvestigation Window (§ 1681i(a)(1)(A))**: The agency must reinvestigate disputed items free of charge within 30 days of receiving notice from the consumer.
- **Conditional 15-Day Extension (§ 1681i(a)(1)(B))**: The period may be extended for up to 15 additional days *if* the consumer provides relevant information during the initial 30-day period.
- **Bar on Extension (§ 1681i(a)(1)(C))**: The 15-day extension is **expressly prohibited** if, during the initial 30 days, the disputed information is found to be inaccurate or incomplete, or the agency determines that the information cannot be verified.
- **Mandatory Deletion / Modification (§ 1681i(a)(5)(A))**: If disputed information is found inaccurate, incomplete, or unverifiable, the agency must promptly delete the item or modify it accordingly.

**Status:** `PENDING LEGAL COUNSEL REVIEW` (Direct applicability is conditional on formal CRA classification).

---

## 5. CRA and Consumer Report Classification Analysis

### 5.1 Analysis Under Both Statutory Definitions

#### 5.1.1 Consumer Report Definition — 15 U.S.C. § 1681a(d)
A **Consumer Report** is any written, oral, or other communication of any information by a CRA bearing on a consumer's credit standing, character, general reputation, personal characteristics, or mode of living, used or expected to be used as a factor in establishing eligibility for credit, insurance, employment, or other authorized purposes (including housing rental transactions under § 1681b(a)(3)(F)).

Tenant incident reports (property damage, non-payment, lease violations) directly reflect character, reputation, and mode of living and are collected to determine housing eligibility.

#### 5.1.2 Transaction or Experience Exclusion — 15 U.S.C. § 1681a(d)(2)(A)(i)
The statute excludes from the definition of a consumer report:
> *(i) any report containing information solely as to transactions or experiences between the consumer and the person making the report;*

**Application to Fraud Tenant**:
1. **Direct Landlord Experience**: A landlord's direct account of its own first-hand lease experience with a tenant may fall within the exclusion if statutory conditions are met. However, the exclusion should not be assumed where the communication includes third-party information, aggregation, external records, or evaluations beyond the landlord's own transactions or experiences.
2. **Third-Party Intermediary Platform**: Fraud Tenant is not the landlord. Fraud Tenant proposes to assemble incident reports from Property Owner A and disclose them to Property Owner B.
3. **Inapplicability to Intermediaries**: Because Fraud Tenant conveys third-party experience information, the direct transaction/experience exclusion under § 1681a(d)(2)(A)(i) does **not** readily shield an intermediary platform.
4. **Counsel Determination Required**: The precise data flow, reporting structure, and furnisher relationships must be evaluated by qualified legal counsel.

#### 5.1.3 Consumer Reporting Agency Definition — 15 U.S.C. § 1681a(f)
A **Consumer Reporting Agency (CRA)** is defined as:
> *"any person which, for monetary fees, dues, or on a cooperative nonprofit basis, regularly engages in whole or in part in the practice of assembling or evaluating consumer credit information or other information on consumers for the purpose of furnishing consumer reports to third parties, and which uses any means or facility of interstate commerce for the purpose of preparing or furnishing consumer reports."*

**Statutory Elements Analyzed**:
1. **Assembling / Evaluating**: Aggregating incident records and indexing tenant matching tokens.
2. **Furnishing to Third Parties**: Disclosing tenant incident history to prospective property owners.
3. **Monetary Fees / Dues / Cooperative Nonprofit Basis**: Phase 9 introduces paid membership subscriptions. The statute also references dues or a "cooperative nonprofit basis." A cooperative arrangement may be relevant to the statutory element; however, the actual organizational structure, operating model, purpose, and legal relationships must be examined by counsel. An informal sharing arrangement alone does not automatically establish this element.
4. **Interstate Commerce Facilities**: The platform's actual use of internet, cloud, and telecommunications facilities must be evaluated against the statutory interstate-commerce language.

### 5.2 Operational Reality vs. Legal Status

- **Current Operational Data State**:
  `CURRENT OPERATIONAL DATA: NO OPERATIONAL SCREENING RECORDS EVIDENCED`  
  The inspected database contains only 2 synthetic test fixtures, zero operational screening requests, and zero operational incident reports. No operational consumer-report furnishing activity was evidenced.
- **Legal Classification**:
  `LEGAL CLASSIFICATION: NOT DETERMINED — FORMAL COUNSEL REVIEW REQUIRED`  
  Database row counts alone do not determine whether the platform meets the CRA definition. CRA classification requires examination of the intended business model, regularity of activity, assembly/evaluation of consumer data, intended furnishing to third parties, compensation or cooperative basis, and interstate-commerce elements.
- **Marketing Terminology Trap**: Statutory classification depends on the applicable statutory definitions and actual business operations, not merely the platform's self-selected marketing labels or software product descriptions. An entity that satisfies the statutory elements of § 1681a(f) remains subject to CRA obligations regardless of whether it characterizes itself as a technology portal, SaaS provider, or peer-to-peer registry. Conversely, paid membership alone does not automatically make a platform a CRA, nor does a free or cooperative model automatically avoid CRA classification; each element of § 1681a(f) must be evaluated against the entity's actual activities and data flows.
- **Classification Status**: **`MATERIAL CRA CLASSIFICATION RISK — FORMAL COUNSEL DETERMINATION REQUIRED`**.

### 5.3 State Registration and Licensing Realities

State consumer reporting laws impose varying registration and operational requirements:
- **Maine Fair Credit Reporting Act (10 M.R.S. Chapter 209-B, § 1310-A(1)(G)) & Rule Chapter 704**:
  - The Maine statute authorizes registration requirements for consumer reporting agencies that are **located in Maine or serving users within Maine**.
  - Maine Bureau of Consumer Credit Protection Rule Chapter 704 establishes annual registration and fee obligations.
  - This requirement is specific to entities located in Maine or serving users in Maine; it does not constitute a universal nationwide rule.
- **Texas**: Texas Business & Commerce Code Chapter 20 governs consumer reporting agencies and credit disclosures, but does not operate an independent agency registration bureau for nationwide CRAs. Substantive statutory requirements must not be conflated with a separate state registration scheme.
- **Other Jurisdictions**: States including California (Civil Code § 1785.1 et seq.), New York (General Business Law § 380 et seq.), and Washington (RCW 19.182) enforce state-specific FCRA analogues with unique disclosure, reinvestigation, and permissible-purpose mandates.
- **Status**: **`STATE-SPECIFIC APPLICABILITY — COUNSEL REVIEW REQUIRED`**.

---

## 6. FHA and Current HUD Authority Status

### 6.1 Statutory Framework & Protected Classes

The Fair Housing Act (42 U.S.C. §§ 3601–3619) prohibits housing discrimination based on seven express statutory protected classes:
1. **Race**
2. **Color**
3. **National origin**
4. **Religion**
5. **Sex**
6. **Familial status**
7. **Disability** (handicap under 42 U.S.C. § 3602(h))

**Sex Discrimination Scope & Bostock Distinction**:
- The statutory text of the FHA does not expressly list "gender identity" or "sexual orientation".
- In *Bostock v. Clayton County*, 590 U.S. 644 (2020), the Supreme Court interpreted Title VII of the Civil Rights Act of 1964, holding that sex discrimination encompasses sexual orientation and gender identity.
- On February 9, 2021, HUD issued a memorandum interpreting the Fair Housing Act's sex-discrimination provisions to include sexual orientation and gender identity based on *Bostock*.
- This memorandum represents an administrative agency interpretation, not a direct Supreme Court ruling on the Fair Housing Act.
- Status: **`CURRENT STATUS NOT VERIFIED — COUNSEL REVIEW REQUIRED`** (subject to ongoing federal litigation, executive orders, and explicit state/local fair housing protections).

### 6.2 Status of HUD Administrative Authorities as of October 31, 2026

#### 6.2.1 HUD 2016 Criminal Records Guidance — WITHDRAWN
- **Official Title**: *Office of General Counsel Guidance on Application of Fair Housing Act Standards to the Use of Criminal Records by Providers of Housing and Real Estate-Related Transactions* (April 4, 2016).
- **Effective Withdrawal Date**: **September 25, 2025**.
- **Federal Register Notice Publication**: **July 17, 2026** (91 FR 44867).
- **Current Legal Status**: **`WITHDRAWN (Effective September 25, 2025; Published July 17, 2026, 91 FR 44867)`** [Non-binding historical guidance].
- **Analysis**: The guidance is no longer operative HUD guidance and cannot be cited as binding agency policy. However, withdrawal of agency guidance does not repeal or displace the underlying Fair Housing Act statute or Supreme Court disparate-impact precedent.

#### 6.2.2 HUD April 29, 2024 Rental Screening Guidance — CURRENT STATUS NOT VERIFIED
- **Official Title**: *Guidance on the Application of the Fair Housing Act to the Screening of Applicants for Rental Housing* (April 29, 2024).
- **Chronology**:
  - In February 2025, HUD's guidance was observed to have been removed from its public website.
  - In March 2025, HUD communicated to GAO that the guidance had been taken down as part of an agencywide review pursuant to deregulatory executive orders, while stating it technically remained in effect at that time.
  - GAO subsequently documented this chronology in GAO Report GAO-25-107196.
- **Current Legal Status**: **`CURRENT STATUS NOT VERIFIED — COUNSEL REVIEW REQUIRED`** [Non-binding agency guidance].
- **Analysis**: Removal from a public website does not constitute formal legal withdrawal. However, because its current operative status has not been confirmed by authoritative post-review agency notice as of November 1, 2026, it remains unverified pending counsel review.

#### 6.2.3 HUD 2026 Disparate-Impact Rulemaking — PROPOSED RULES ONLY

The two distinct 2026 HUD rulemaking proceedings must be separated:

##### A. January 14, 2026 Proposed Rule — 91 FR 1475
- **Document Title**: *HUD's Implementation of the Fair Housing Act's Disparate Impact Standard*.
- **Federal Register Citation**: 91 FR 1475 (Document 2026-00590).
- **Docket Number**: FR-6540-P-01.
- **Regulatory Target**: 24 CFR Part 100 (Fair Housing Act regulations).
- **Action**: Proposed rule.
- **Comment Period**: Closed February 13, 2026.

##### B. August 10, 2026 Supplemental Proposed Rule — 91 FR 51416
- **Document Title**: *HUD's Implementation of the Fair Housing Act's Disparate Impact Standard; Amendments to HUD's Title VI Regulations*.
- **Federal Register Citation**: 91 FR 51416 (Document 2026-16228).
- **Docket Number**: FR-6540-P-02.
- **Regulatory Target**: 24 CFR Part 1 (Title VI regulations).
- **Action**: Supplemental notice of proposed rulemaking.
- **Comment Period**: Closed October 9, 2026.

**Current Legal Status**:
- `FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`
- `PROPOSED RULEMAKING — NOT OPERATIVE REGULATION`  
Neither proceeding was evidenced as an operative final rule through the November 1, 2026 documentation freeze date. A closed public comment period does not transform a proposed rule into an operative regulation.  
**Controlling Precedent**: Disparate-impact claims remain governed by *Texas Department of Housing and Community Affairs v. Inclusive Communities Project, Inc.*, 576 U.S. 519 (2015), which established the robust causality requirement protecting housing providers from liability for racial disparities they did not cause.

### 6.3 CFPB Advisory Opinion on Background Screening — WITHDRAWN

- **Official Title**: *Fair Credit Reporting; Background Screening*, 89 FR 4171 (January 23, 2024).
- **Current Legal Status**: **`WITHDRAWN (Effective May 12, 2025)`** [Non-binding agency guidance].
- **Analysis**: The advisory opinion was non-binding agency guidance. Its withdrawal does not repeal, suspend, or alter statutory obligations under the FCRA.

### 6.4 Separation of Legal Duties from Fraud Tenant Internal Product Policy

#### 6.4.1 Fraud Tenant Internal Product Safeguards
Fraud Tenant implements strict internal risk controls to prevent abusive screening:
1. **No Automated Accept/Reject Recommendations**: Never issue algorithmic "Deny" or "Accept" recommendations.
2. **No Automated Risk Scores**: Never generate numerical tenant risk scores.
3. **Factual Presentation Only**: Display factual incident reports without defamatory labels.
4. **Mandatory Human Review**: Negative incident reports must undergo administrative review before disclosure.
5. **Clear Distinction**: Clearly differentiate unadjudicated owner claims from final court judgments.

#### 6.4.2 Legal Boundaries & Individualized Assessment
These policies are **Fraud Tenant product safeguards**, not statutory mandates under the Fair Housing Act. The FHA does not categorically prohibit every algorithmic score or decision-support tool. Similarly, individualized assessments are internal risk-mitigation measures, not universal federal statutory requirements for every rental screening decision. These safeguards must not be weakened merely because particular agency guidance has been withdrawn.

---

## 7. SSN / HMAC Security Review

### 7.1 Tiered Secret Key Management Policy

Inspection of the environment and cryptographic codebase reveals:
- **Code Implementation**: `SecurityService::generate_ssn_match_token()` implements HMAC-SHA256 token generation.
- **Runtime Environment**: `FT_MATCH_SECRET_KEY` is completely unconfigured in the inspected local environment.
- **Fail-Closed Behavior**: The service returns `false` when the key is missing, blocking token generation.
- **Status**: **`HMAC IMPLEMENTATION PRESENT — RUNTIME MATCHING BLOCKED BY MISSING SECRET`**.

#### Tiered Environment Provisioning Policy:
1. **Development Environment**: A separate non-production matching secret may be configured in local `wp-config.php` *only* when authorized Phase 7 implementation begins. Production keys must never be introduced into local XAMPP.
2. **Testing Environment**: Dedicated test key, strictly separated from dev and production.
3. **Production Environment**: Production secrets must be provisioned via an approved secure secret-management system (e.g., AWS Secrets Manager, HashiCorp Vault, Azure Key Vault, or protected server environment variables). Production `wp-config.php` is not prescribed as the sole permissible secret-management mechanism. No secret is generated, provisioned, or displayed during this audit pass.

### 7.2 HMAC Cryptographic Properties & Identification Risks

The cryptographic realities of HMAC-SHA256 must be accurately documented:
1. **Keyed One-Way Hash**: HMAC-SHA256 is a keyed cryptographic hash, not encryption. It cannot be decrypted.
2. **Deterministic Output**: The same secret key and identical normalized SSN always produce the identical 64-character hex token.
3. **Equality Matching**: Deterministic tokens allow database equality lookups, revealing whether multiple records share the same identifier.
4. **Pseudonymization vs. Anonymization**: Deterministic HMAC tokens linked to names, dates of birth, or rental history records are **pseudonymous identifiers**, not anonymous data. Under applicable privacy frameworks (where governing law applies), pseudonymized data remains personal information because individuals remain identifiable.
5. **Candidate Enumeration Risk**: The formatted 9-digit SSN candidate space is finite (at most \(10^9\) possible values). If `FT_MATCH_SECRET_KEY` is compromised, an attacker can precompute HMAC tokens for candidate values and match them against stored tokens. While not all nine-digit numbers are validly issued SSNs, this finite domain creates a serious candidate-enumeration and re-identification risk.
6. **Collision Search vs. Pairwise Probability**:
   - The probability that two specific, independent inputs produce the same 256-bit token is approximately \(2^{-256}\).
   - The generic birthday attack work factor to find *any* arbitrary collision across multiple inputs is approximately \(2^{128}\) operations. While computationally infeasible, this distinction must not be conflated.

### 7.3 Key Rotation Realities

> [!IMPORTANT]
> **Existing HMAC tokens cannot be mathematically re-hashed with a new key if original plaintext SSNs are not retained.**

If `FT_MATCH_SECRET_KEY` is rotated, existing `token_hash` values in `wp_ft_tenant_identities` cannot be migrated. Future key rotation requires either:
1. Re-collecting the SSN from the tenant under a legally approved consent workflow; or
2. Maintaining a separately approved, encrypted, recoverable identity vault (which introduces substantial security, compliance, and breach liability).

Future implementation must include key-version identifiers (e.g., `v1:hash`) to support multi-key transition windows.

### 7.4 SSN Raw Input Validation — Strict ASCII Specification

Inspection of `SecurityService::generate_ssn_match_token()` reveals an input sanitization deficiency:

```php
// Existing Code in SecurityService.php
$normalized = preg_replace( '/\D/', '', $ssn );
if ( strlen( $normalized ) !== 9 ) {
    return false;
}
return hash_hmac( 'sha256', $normalized, $secret_key );
```

#### Technical Defect & Resolution:
Previously, the function stripped all non-digits *before* checking the length. Consequently, malformed inputs containing trailing text or unexpected characters (e.g., `123-45-6789abc` or `123456789extra`) would have non-digits stripped to 9 digits and would be accepted and hashed without error.

**Resolution Status**: `RESOLVED & VERIFIED` (BLK-09).  
Implemented `SecurityService::validate_ssn_format()` and updated `SecurityService::generate_ssn_match_token()`:
1. **Raw-Format Pre-Validation**: Validates the candidate input string against approved ASCII digit patterns with whole-string anchors before normalization:
   ```regex
   \A[0-9]{3}-[0-9]{2}-[0-9]{4}\z    (Formatted SSN)
   \A[0-9]{9}\z                      (Raw 9-digit SSN)
   ```
2. **Immediate Rejection**: Rejects malformed strings, invalid separators, Unicode digits, or trailing text immediately before normalization and before hashing.
3. **Format vs. Issuance Validity**: A formatting check confirms input structure only; it does **not** prove that the number was validly issued by the Social Security Administration.
4. **Verification**: Verified across 27 automated assertions in `scratch/test_blk09_ssn_validation.php` (27/27 PASS) plus 161 regression assertions (161/161 PASS).
5. **Zero Persistence / Zero Logging**: Raw SSNs must never be persisted, cached, written to application logs, placed in URLs, or transmitted to third-party analytics.

### 7.5 PHP Memory Handling and `unset()` Realities

- Calling `unset($variable)` in PHP (Zend Engine) frees the symbol name and decrements the internal reference count. It does **not** guarantee physical memory zeroization.
- PHP's memory allocator and string-copy behavior prevent application-level guarantees of secure memory erasure.
- Mitigations: Minimize plaintext SSN variable lifetime, avoid redundant copies, never store raw SSNs in sessions, cookies, caches, or temporary files, and invoke `unset()` promptly without claiming guaranteed zero-second memory wiping.

---

## 8. PII and Consent Evidence Review

### 8.1 Multi-Layered Data Protection Matrix

The technical audit examined data protection controls across all architectural tiers, distinguishing verified code facts from unverified infrastructure:

| Data Category / Control | Relational Location | Application-Layer Field Encryption | Database Engine / Tablespace Encryption | Host Disk / Volume Encryption | Backup Encryption | Verified Implementation Status |
|---|---|---|---|---|---|---|
| **Legal Names** | `wp_ft_tenant_identities` | `NOT IMPLEMENTED` | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | Application-level field encryption not implemented; infrastructure encryption not verified |
| **Dates of Birth** | `wp_ft_tenant_identities` | `NOT IMPLEMENTED` | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | Application-level field encryption not implemented; infrastructure encryption not verified |
| **SSN Last 4** | `wp_ft_tenant_identities` | `NOT IMPLEMENTED` | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | Application-level field encryption not implemented; infrastructure encryption not verified |
| **HMAC Matching Tokens** | `wp_ft_tenant_identities` | Keyed HMAC-SHA256 code path | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | HMAC-SHA256 code path present; matching secret missing; runtime matching blocked |
| **Consent Documents** | `/protected/` filesystem | N/A (Filesystem access control) | N/A | `NOT VERIFIED` | `NOT VERIFIED` | Protected storage access controls verified in code; encryption at rest not verified |
| **Incident Reports** | `wp_ft_incident_reports` | `NOT IMPLEMENTED` | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | ZERO OPERATIONAL ROWS; application-level encryption not implemented; infrastructure encryption not verified |
| **Tenant Disputes** | `wp_ft_tenant_disputes` | `NOT IMPLEMENTED` | `NOT VERIFIED` | `NOT VERIFIED` | `NOT VERIFIED` | ZERO OPERATIONAL ROWS (synthetic fixtures only); application-level encryption not implemented; infrastructure encryption not verified |

**Technical Distinctions**:
- `$wpdb->prepare()` prevents SQL injection vulnerabilities; it is **not** data encryption.
- Web server directory access controls (`.htaccess`, `web.config`) restrict HTTP access; they do not encrypt files on disk.
- Protected filesystem storage does not establish encryption at rest.
- HMAC matching tokens are deterministic hashes, not encrypted fields.
- The presence of zero operational rows does not establish encryption at rest or security verification.

### 8.2 Consent Evidence Accuracy and Attribution

The legal basis for requesting and furnishing a consumer report must be evaluated under the distinct provisions of the FCRA:
- **Section 1681b(a)(2)** provides a permissible purpose where reports are furnished in accordance with the written instructions of the consumer; if this route is relied upon, written instructions must be genuinely attributable to that consumer.
- **Section 1681b(a)(3)(F)(i)** provides a permissible purpose where the user has a legitimate business need in connection with a business transaction initiated by the consumer (such as a submitted rental application).
- **Section 1681b(f)** restricts obtaining or using reports without an authorized purpose and the prospective user's purpose certification.
- **Section 1681e(a)** requires a CRA to maintain reasonable procedures to verify new prospective users and their certified purposes.

These provisions do **not** collectively impose a universal federal written-consent mandate for every residential rental screening transaction. However, written authorization may be independently required by state tenant privacy laws, upstream data provider agreements, or Fraud Tenant internal risk policy, and serves as vital evidence documenting permissible purpose. The final screening authorization workflow must be reviewed and approved by qualified legal counsel.

#### Technical Attribution Distinctions:
1. **Owner Upload Limitations**: An uploaded document provided by a property owner does **not**, by itself, prove that the applicant signed, understood, or authorized the screening transaction.
2. **IP Attribution**: If a property owner uploads an authorization document, the recorded IP address is the **owner's IP**, not the applicant's digital signing IP.
3. **Proposed Technical Evidence Controls (Subject to Legal Counsel Approval)**:
   - Specific applicant association;
   - Requesting owner identity;
   - Verified property association;
   - Specific screening transaction link;
   - Screening purpose certification;
   - Exact disclosure text version and reliable timestamps;
   - Signature or authorization evidence (distinguishing uploader IP from applicant signing IP if a digital signature process is used).
4. **Current Status**: **`NOT IMPLEMENTED`**.

---

## 9. Data-Source and Phase 8 Dependencies

### 9.1 Data-Source Void

Inspection of the database confirms that Fraud Tenant currently contains **zero operational screening data**:
- `wp_ft_tenant_identities`: Contains only 2 synthetic test fixture rows.
- `wp_ft_screening_requests`: Contains 0 operational search records.
- `wp_ft_incident_reports`: Contains 0 operational incident reports.
- `wp_ft_report_reviews`: Contains 0 admin review records.
- `wp_ft_tenant_disputes`: Contains 2 synthetic test fixture rows with `report_id = 0`.
- **External Screening Providers**: Zero third-party credit, eviction, or criminal data feeds are integrated.

### 9.2 Structural Dependency on Phase 8

A matching engine without verified screening data cannot furnish operational screening results:
1. **Matching Token Is Not Guilt**: An HMAC token match in `wp_ft_tenant_identities` indicates only that an identity record exists. It does **not** prove wrongdoing, lease default, or fraud.
2. **Defamation & FCRA Liability**: Disclosing unverified owner allegations to prospective landlords creates severe legal liability for defamation and FCRA non-compliance.
3. **Distinction of Claims**: Unadjudicated owner claims, pending disputes, and final court judgments must be strictly separated.
4. **Police Reports Are Allegations**: A police report records an unproven complaint or investigation; it does not constitute proof of guilt or civil liability.
5. **No Universal Evidence Whitelist**: Supporting evidence must be evaluated under counsel-approved provenance standards, not an arbitrary automated whitelist.
6. **Internal Dispute Safeguards**: Phase 8 dispute workflows serve as internal product safeguards unless and until formal legal classification determines statutory FCRA dispute rights.
7. **Negative Result Disclosure Prohibition**: **No negative screening result may be operationally disclosed until Phase 8 incident verification, admin review moderation, and tenant dispute workflows are fully operational.**

---

## 10. Capability and Authorization Design

### 10.1 Preservation of Existing Phase 6 Security Controls

Phase 6, 6.1, and 6.1.1 established strict role separation:
- Property review (`ft_review_properties`) and property verification (`ft_verify_properties`) remain separate capabilities.
- Document streaming enforces ownership checks (`$doc->user_id === $requesting_user_id`) and capability checks.
- Cross-owner document streaming is blocked and audited.

### 10.2 Proposed Phase 7 Least-Privilege Capabilities & Internal Access Scope

The design of Phase 7 screening capabilities must enforce strict least-privilege principles:
1. **Membership Payment Alone Grants No Legal Authority**: Subscribing to a paid plan does **not** establish permissible purpose or legal authorization to screen.
2. **Ownership Relationship Required**: Screening requests must be restricted to verified property owners for verified properties.
3. **Permissible Purpose Alignment**:
   - If relying on § 1681b(a)(3)(F)(i), the screening request must be tied to an active rental application initiated by the consumer.
   - If relying on § 1681b(a)(2), verifiable written instructions attributable to the consumer must be documented.
4. **Proposed Screening Capabilities**:
   - `ft_initiate_screening`: Permitted only for verified property owners with verified permissible-purpose certification.
   - `ft_view_screening_reports`: Access strictly limited to the requesting property owner for the specific screening transaction.
5. **Administrative Access Scoping**: Administrative staff must not possess a default screening-initiation capability. Admin access must be restricted to compliance audits, dispute investigations, and system maintenance, with all access logged to `wp_ft_audit_log`.

---

## 11. SMTP and QA Status

### 11.1 Re-Affirmation of Phase 6 Final QA Baseline

The historical QA catalog established at Phase 6 closure remains fully intact:
- **Total Deduplicated Unique Assertions**: **548**
- **Passed Assertions**: **543**
- **Untested Assertions**: **5** (Live outbound SMTP delivery)
- **Historical Regression Executions**: **161 / 161 PASS**
- **Test Integrity**: These tests were **not rerun** during this documentation-only audit pass. No new test passes are claimed, and zero database mutations were executed.

### 11.2 Historical Test Execution & Release Gate Classification

The 5 untested SMTP assertions are classified as follows:
1. **Local Development**: Logic and unit testing proceed with local fallbacks and database-backed verification (`wp_ft_email_otps`).
2. **Feature Integration**: Features requiring live email dispatch (OTP delivery, dispute notices, adverse action notices) require a working SMTP relay.
3. **Production Release Gate**: Live outbound SMTP configuration is a **mandatory production release gate**. It does not block isolated, non-production code implementation where email delivery is not under active development, but it strictly blocks production release.

---

## 12. Reconciled Critical Blockers & Integration Dependencies

The **12 distinct project blockers and integration dependencies** are categorized below according to their actual operational, technical, and regulatory impact, distinguishing code implementation blockers from operational disclosure gates and release/phase-specific integration gates:

### A. Legal Authorization Blockers
*These blockers strictly prevent operational tenant screening, report generation, and negative result disclosure.*

1. **BLK-03A: CRA (§ 1681a(f)), Consumer Report (§ 1681a(d)) & § 1681a(d)(2)(A)(i) Exclusion Analysis**
   - *Status*: `CRITICAL LEGAL BLOCKER — PENDING COUNSEL REVIEW`
   - *Classification*: `MATERIAL CRA CLASSIFICATION RISK — FORMAL COUNSEL DETERMINATION REQUIRED`
   - *Impact*: Intermediary platform does not readily qualify for the direct transaction/experience exclusion. Operational screening cannot occur without formal counsel determination.
   - *Required Action*: Formal written legal opinion from qualified U.S. consumer reporting counsel.
2. **BLK-03B: State-Specific Consumer Reporting & Screening Obligations**
   - *Status*: `STATE-SPECIFIC APPLICABILITY — COUNSEL REVIEW REQUIRED`
   - *Impact*: State laws (e.g., Maine 10 M.R.S. § 1310-A(1)(G) for CRAs located in Maine or serving Maine users; CA; NY; WA) impose registration and procedural mandates.
   - *Required Action*: Counsel evaluation of state registration, licensing, and procedural rules on a state-by-state basis.
3. **BLK-03C: Current FHA/HUD Authority Status, Withdrawn Guidance & Disparate Impact**
   - *Status*: `PENDING LEGAL COUNSEL REVIEW`
   - *Impact*: Withdrawn HUD 2016 guidance and proposed 2026 rules (91 FR 1475, 91 FR 51416) require legal reconciliation under *Inclusive Communities* disparate impact standards.
   - *Required Action*: Counsel review of screening criteria, withdrawn guidance impact, proposed rulemaking status, and disparate impact standards.
4. **BLK-03D: Permissible Purpose vs. Written Instructions vs. Internal Policy (§ 1681b)**
   - *Status*: `PENDING LEGAL COUNSEL REVIEW`
   - *Impact*: Establishing permissible purpose under § 1681b(a)(2) written instructions vs. § 1681b(a)(3)(F) legitimate business need.
   - *Required Action*: Counsel approval of standalone tenant disclosure templates, permissible purpose attestations, and internal consent requirements.
5. **BLK-03E: Housing Adverse-Action Notice & Credit Score Disclosures (§ 1681m(a))**
   - *Status*: `PENDING LEGAL COUNSEL REVIEW`
   - *Impact*: Adverse action notices under § 1681a(k)(1)(B)(iv) and § 1681m(a) must be standardized before any adverse determinations are supported.
   - *Required Action*: Counsel drafting and approval of housing adverse-action notice templates, CRA contact disclosures, dispute rights, and credit score rules.
6. **BLK-03F: Differentiated Record Obsolescence (§ 1681c, 180-day rule) & Retention (§ 1681p)**
   - *Status*: `NOT APPROVED FOR IMPLEMENTATION — PENDING LEGAL COUNSEL REVIEW`
   - *Impact*: Blanket 7-year filtering violates § 1681c(c)(1) 180-day charge-off rules and state restrictions. Section 1681p is a limitation period, not a retention schedule.
   - *Required Action*: Counsel approval of a multi-tiered obsolescence and data retention schedule.

### B. Technical Design and Implementation Blockers
*These blockers must be resolved before authorized Phase 7 code and feature implementation begins.*

7. **BLK-04: SSN Matching Secret (`FT_MATCH_SECRET_KEY`) Missing — Tiered Provisioning Policy**
   - *Status*: `HMAC IMPLEMENTATION PRESENT — RUNTIME MATCHING BLOCKED BY MISSING SECRET`
   - *Impact*: Runtime matching engine fails closed. Non-production key required for development; production key required for deployment.
   - *Required Action*: Provision non-production secret in dev/test only when authorized implementation begins; manage production key via approved vault.
8. **BLK-09: SSN Input Pre-Validation Regex Hardening**
   - *Status*: `RESOLVED & VERIFIED`
   - *Implementation*: Implemented strict pre-normalization regex validation in `SecurityService::validate_ssn_format()` enforcing ASCII digit character sets `[0-9]` with whole-string anchors (`\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z` and `\A[0-9]{9}\z`).
   - *Verification Evidence*: Verified across 27 automated test assertions in `scratch/test_blk09_ssn_validation.php` (27/27 PASS) plus 161 regression assertions (161/161 PASS). Fail-closed behavior confirmed when `FT_MATCH_SECRET_KEY` is missing.
9. **BLK-08: Sensitive PII Protection at Rest & Database Backup Policy**
   - *Status*: `BLOCKED — TECHNICAL POLICY & ENCRYPTION PENDING`
   - *Impact*: Legal names and DOB reside in database columns without application-level field encryption; infrastructure encryption remains unverified.
   - *Required Action*: Formulate technical application-layer or database encryption policy for sensitive PII and secure backup procedures.

### C. Operational Screening and Disclosure Blockers
*These dependencies do not prevent isolated Phase 7 code development but strictly prevent operational result disclosure.*

10. **BLK-07: Screening Data Source & Phase 8 Dependency**
    - *Status*: `BLOCKED — DATA SOURCE & VERIFICATION WORKFLOWS NOT IMPLEMENTED`
    - *Impact*: Database contains zero operational screening rows. Phase 8 incident verification, admin moderation, and tenant dispute workflows do not exist.
    - *Required Action*: Implement Phase 8 incident verification, admin review gate, and tenant dispute workflows before disclosing screening results.

### D. Release / Phase-Specific Integration Gates
*These items govern final production readiness and phase-specific payment dependencies.*

11. **BLK-02: Live Outbound SMTP Relay Provider (5 assertions `NOT TESTED`)**
    - *Status*: `5 ASSERTIONS NOT TESTED — INTEGRATION & PRODUCTION RELEASE GATE`
    - *Impact*: Windows XAMPP environment lacks built-in mail transport. Core OTP and admin review functions use local fallbacks. Live SMTP is a mandatory production release gate for features requiring live outbound email delivery (statutory adverse action notices, dispute notices, OTP delivery). It does not block isolated non-production code development where live delivery is not under active development.
    - *Required Action*: Configure production SMTP credentials (e.g., Amazon SES, Postmark, SendGrid) prior to live production deployment.
12. **BLK-01: Stripe API Keys and Webhook Secret**
    - *Status*: `BLOCKED — PENDING CREDENTIALS`
    - *Impact*: Phase 9 subscription and billing integration dependency. Required prior to Phase 9 live testing; not a general Phase 7 code implementation blocker or universal release gate if payment functionality is phased.
    - *Required Action*: Provide Stripe sandbox keys or configure them in server environment variables prior to Phase 9 testing.

---

## 13. Prerequisites for Future Authorization and Implementation Sequencing

To prevent contradictions regarding Phase 7 and Phase 8 sequencing, the following phased policy is established:

### A. Current Authorization Status
**No Phase 7, Phase 8, or Phase 9 implementation is authorized by this audit.** The project remains strictly in a documentation and review freeze.

### B. Future Isolated Technical Development
A future, separately authorized, non-production technical prototype may be considered only within a counsel-approved scope, utilizing isolated test keys and synthetic test fixtures, without operational screening, real consumer data processing, or report disclosure.

### C. Operational Screening Activation
Before operational tenant screening is activated, all applicable legal and technical prerequisites must be satisfied:
1. Written legal counsel determination of CRA status under § 1681a(d) and § 1681a(f);
2. Counsel-approved permissible-purpose workflows under § 1681b(a)(2) or § 1681b(a)(3)(F);
3. State-specific legal review for Maine (10 M.R.S. § 1310-A(1)(G)), Texas, California, New York, Washington;
4. Counsel-approved adverse action notice templates under § 1681m(a);
5. Counsel-approved obsolescence and retention schedules under § 1681c and § 1681p;
6. Implementation of strict raw SSN input pre-validation (BLK-09);
7. Formulation and implementation of sensitive PII encryption-at-rest architecture (BLK-08);
8. Approved secret-management design for non-production and production environments (BLK-04);
9. Verified owner and property authorization workflows;
10. Required notification and delivery processes.

### D. Negative Result Disclosure Prohibition
**No negative screening result may be disclosed to any user until:**
1. The incident source is verified and traceable under counsel-approved provenance standards;
2. The report has passed the mandatory human administrative review process;
3. Tenant dispute and correction workflows are fully operational;
4. Applicable legal obligations are satisfied;
5. Counsel has formally approved the disclosure workflow.

---

## 14. Official References and Legal Review Disclaimer

### 14.1 Authoritative Primary References

1. **Fair Credit Reporting Act (FCRA)**:
   - 15 U.S.C. § 1681a (*Definitions; rules of construction*): https://uscode.house.gov/
   - 15 U.S.C. § 1681b (*Permissible purposes of consumer reports*): https://uscode.house.gov/
   - 15 U.S.C. § 1681c (*Requirements relating to information contained in consumer reports*): https://uscode.house.gov/
   - 15 U.S.C. § 1681e (*Compliance procedures*): https://uscode.house.gov/
   - 15 U.S.C. § 1681i (*Procedure in case of disputed accuracy*): https://uscode.house.gov/
   - 15 U.S.C. § 1681m (*Requirements on users of consumer reports*): https://uscode.house.gov/
   - 15 U.S.C. § 1681p (*Jurisdiction of courts; limitation of actions*): https://uscode.house.gov/
2. **Federal Trade Commission (FTC)**:
   - *Using Consumer Reports: What Landlords Need to Know*: https://www.ftc.gov/business-guidance/resources/using-consumer-reports-what-landlords-need-know
   - *What Tenant Background Screening Companies Need to Know About the Fair Credit Reporting Act*: https://www.ftc.gov/business-guidance/resources/what-tenant-background-screening-companies-need-know-about-fair-credit-reporting-act
3. **Consumer Financial Protection Bureau (CFPB)**:
   - Advisory Opinion: *Fair Credit Reporting; Background Screening*, 89 FR 4171 (January 23, 2024). Status: Withdrawn effective May 12, 2025. https://www.consumerfinance.gov/compliance/guidance/withdrawn-guidance/
4. **Fair Housing Act (FHA) & Judicial Precedent**:
   - 42 U.S.C. §§ 3601–3619 (*Fair Housing Act of 1968, as amended*): https://uscode.house.gov/
   - *Texas Department of Housing and Community Affairs v. Inclusive Communities Project, Inc.*, 576 U.S. 519 (2015).
   - *Bostock v. Clayton County*, 590 U.S. 644 (2020) (Title VII precedent).
5. **Department of Housing and Urban Development (HUD)**:
   - Notice of Withdrawal: *Office of General Counsel Guidance on Criminal Records*, 91 FR 44867 (Published July 17, 2026; effective September 25, 2025). https://www.federalregister.gov/
   - GAO Report GAO-25-107196 (*Rental Screening Guidance Website Removal*): https://www.gao.gov/products/gao-25-107196
   - Proposed Rule: *HUD's Implementation of the Fair Housing Act's Disparate Impact Standard*, 91 FR 1475 (January 14, 2026; Docket FR-6540-P-01; 24 CFR Part 100).
   - Supplemental Proposed Rule: *Amendments to HUD's Title VI Regulations*, 91 FR 51416 (August 10, 2026; Docket FR-6540-P-02; 24 CFR Part 1).
6. **State Statutes and Administrative Codes**:
   - Maine Fair Credit Reporting Act, 10 M.R.S. Chapter 209-B, § 1310-A(1)(G): https://mainelegislature.org/statutes/10/title10sec1310-A.html
   - Maine Bureau of Consumer Credit Protection Rule Chapter 704: https://www1.maine.gov/pfr/consumercredit/industry/licensing/consumer_reporting.htm
   - Texas Business & Commerce Code Chapter 20: https://statutes.capitol.texas.gov/Docs/BC/htm/BC.20.htm
   - California Civil Code § 1785.13: https://leginfo.legislature.ca.gov/
   - New York General Business Law § 380-j: https://www.nysenate.gov/legislation/laws/GBS/380-J

### 14.2 Legal Review Disclaimer

> [!CAUTION]
> **LEGAL NOTICE**: This document constitutes a technical architecture audit, engineering risk assessment, and statutory research synthesis prepared for software development planning. It does **not** constitute formal legal advice, a binding legal opinion, or an attorney-client communication. Formal written determination by qualified U.S. consumer reporting and fair housing legal counsel is mandatory prior to operational launch.

---

## 15. Authority Status Change Log

The table below documents official regulatory actions and status changes verified during the audit passes:

| Legal / Administrative Authority | Citation / Identifier | Issuing Body | Original Date | Verified Status as of Baseline (2026-10-31) | Legal Nature |
|---|---|---|---|---|---|
| **CFPB Advisory Opinion: Background Screening** | 89 FR 4171 | CFPB | Jan 23, 2024 | **`WITHDRAWN (Effective May 12, 2025)`** | Non-binding agency guidance (Withdrawn) |
| **HUD Criminal Records Guidance** | OGC Guidance | HUD | Apr 4, 2016 | **`WITHDRAWN (Effective Sep 25, 2025; Published Jul 17, 2026, 91 FR 44867)`** | Non-binding agency guidance (Withdrawn) |
| **HUD Rental Screening Guidance** | Agency Guidance | HUD | Apr 29, 2024 | **`CURRENT STATUS NOT VERIFIED — COUNSEL REVIEW REQUIRED`** (Observed absent Feb 2025; GAO-25-107196) | Non-binding agency guidance (Status unsettled) |
| **HUD Disparate Impact Proposed Rule** | 91 FR 1475 (FR-6540-P-01) | HUD | Jan 14, 2026 | **`PROPOSED RULEMAKING — NOT OPERATIVE REGULATION`** (`FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`; 24 CFR Part 100) | Proposed Administrative Rule |
| **HUD Disparate Impact Supplemental Rule** | 91 FR 51416 (FR-6540-P-02)| HUD | Aug 10, 2026 | **`PROPOSED RULEMAKING — NOT OPERATIVE REGULATION`** (`FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`; 24 CFR Part 1) | Supplemental Proposed Rule |
| **Maine CRA Registration Requirement** | 10 M.R.S. § 1310-A(1)(G) & Rule Ch. 704 | Maine Legislature / BCCP | Multi-year | **`ACTIVE STATUTE & RULE`** (Applies to CRAs located in Maine or serving users within Maine) | State Statute & Administrative Rule |

---

## 16. Change Log (Round 4 Final Reconciliation Pass)

The following refinements and reconciliations were enacted during the Round 4 final documentation review:
1. **Reconciled Blocker Count to 12**: Corrected the count in Section 12 and `BLOCKERS.md` from 11 to 12 distinct entries across four functional categories (6 Legal Authorization Blockers, 3 Technical Design & Implementation Blockers, 1 Operational Screening & Disclosure Blocker, and 2 Release / Phase-Specific Integration Gates).
2. **SMTP vs. Phase 9 Stripe Distinction**: Explicitly distinguished BLK-02 (mandatory production release gate for live outbound email) from BLK-01 (Phase 9 subscription integration dependency required for Phase 9 testing, but not a general Phase 7 code blocker or universal release gate).
3. **Consent Evidence Wording Reconciliation (Section 8.2)**: Replaced the opening paragraph of Section 8.2 to distinguish the separate roles of §§ 1681b(a)(2), 1681b(a)(3)(F), 1681b(f), and 1681e(a), ensuring it does not imply a collective universal federal written-consent mandate for housing.
4. **Adverse Action Statutory Text (§ 1681a(k)(1)(B)(iv))**: Aligned quotation verbatim to the statutory text: *"in connection with a business transaction that is initiated by any consumer"*.
5. **State-Law Criminal Record Examples (§ 1681c)**: Refined discussion of California Civil Code § 1785.13(a)(6) and New York General Business Law § 380-j to avoid nationwide generalizations regarding criminal reporting limits, highlighting jurisdiction-specific variations.
6. **CRA Classification & Marketing Terminology (§ 1681a(f))**: Refined Section 5.2 to emphasize that statutory classification depends on statutory definitions and actual operations rather than marketing labels, without overbroad agency enforcement assertions.
7. **SSN Pre-Validation Regex Hardening (Section 7.4 & BLK-09)**: Implemented and verified strict ASCII digit whole-string anchored pre-validation regex (`\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z` and `\A[0-9]{9}\z`) in `SecurityService.php`, rejecting malformed inputs prior to normalization. Verified across 27 automated test assertions (27/27 PASS) plus 161 regression assertions (161/161 PASS). BLK-09 status updated to `RESOLVED & VERIFIED`.
8. **Documentation Freeze & Regulatory Verification Dates**: Preserved the historical October 31, 2026 audit baseline while recording the November 1, 2026 documentation freeze date and verifying regulatory statuses through November 1, 2026 (`FINAL RULE STATUS AS OF 2026-11-01: NOT VERIFIED`).
9. **QA Baseline Preservation**: Explicitly confirmed historical Phase 6 QA figures (548 unique assertions, 543 PASS, 5 SMTP NOT TESTED, 161/161 regression PASS) and executed 188 automated test assertions in Phase 7 blocker reconciliation (188/188 PASS).
10. **Final Verdict Preservation**: Categorical verdict retained: `BLOCKED — PREREQUISITES NOT SATISFIED`.

---

## 17. Final Verdict

### Categorical Verdict:
# **`BLOCKED — PREREQUISITES NOT SATISFIED`**

Fraud Tenant has established a robust technical foundation through Phase 6.1.1, and technical blocker BLK-09 (SSN pre-validation regex) is resolved and verified. However, Phase 7 remains strictly blocked because essential legal, operational, and cryptographic prerequisites remain unsatisfied.

The following remain unresolved:
- Formal CRA and consumer-report classification under 15 U.S.C. §§ 1681a(d) and 1681a(f);
- Qualified U.S. legal counsel's formal written determination;
- Counsel-approved permissible-purpose, authorization, and adverse action disclosure policies;
- State-specific legal review (including Maine, Texas, California, New York, Washington);
- Approved source provenance and verification policy;
- PII protection and encryption architecture for sensitive identity fields (BLK-08);
- Secure non-production and production secret key-management provisioning (BLK-04);
- Phase 8 verification, admin review, and dispute safeguards before negative-result disclosure (BLK-07);
- Required production email integration before production release (BLK-02);
- Explicit project leadership authorization.

**Do not describe the project as production-ready, legally compliant, or authorized to disclose screening results.**  
**Zero Phase 7, Phase 8, or Phase 9 feature implementation is authorized.**
