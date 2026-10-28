# FRAUD TENANT — Quality Assurance & Testing Report

## Testing Standards & Conventions
- `PASS`: Test executed successfully with positive verification against acceptance criteria.
- `FAIL`: Test executed and failed acceptance criteria.
- `BLOCKED`: Test could not be executed due to missing credentials, infrastructure, or prerequisites.
- `NOT TESTED`: Item has not been tested. Untested items are never marked as passed.

---

## Environment & Test Matrix

| Component | Specification |
| :--- | :--- |
| **Operating System** | Windows 11 Pro 64-bit |
| **Local Web Server** | Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 |
| **Database Engine** | MySQL (MariaDB 10.4.32) |
| **PHP Version** | PHP 8.2.12 (CLI & Module) |
| **WordPress Core** | WordPress 7.1.3 |
| **Base Local URL** | `http://localhost/fraudtenant` |
| **Browser Testing Tool** | Google Chrome Headless (`chrome.exe --headless=new`) & PowerShell Web Client |
| **Node.js Environment** | Node.js v24.22.0 |
| **Version Control** | Git 2.45+ (`C:\Program Files\Git\cmd\git.exe`) |

---

## Phase 0 Test Execution & Verification

| Test ID | Test Name | Method | Result | Evidence |
| :--- | :--- | :--- | :---: | :--- |
| **0.1** | WP Core & DB Connectivity | CLI script via `wp-load.php` | **PASS** | WP 7.1.3, DB connected, 1 admin user (`anik`) |
| **0.2** | Local HTTP Site Availability | `Invoke-WebRequest http://localhost/fraudtenant` | **PASS** | HTTP 200 OK |
| **0.3** | Active Theme & Plugin Inventory | WP Query: Twenty Twenty-Five active, 0 plugins | **PASS** | Clean slate |
| **0.4** | Git Configuration & Secrets | `git init` + `.gitignore` check | **PASS** | `wp-config.php` ignored |
| **0.5** | Design References Catalog | UI Showcase & flowchart inspection | **PASS** | `#14263E`, `#0E9488`, `#F2F5F9` confirmed |
| **0.6** | Headless Chrome Execution | Headless screenshot capture | **PASS** | Chrome rendering confirmed |

---

## Phase 1 Test Execution & Verification

| Test ID | Test Name | Method | Result | Evidence |
| :--- | :--- | :--- | :---: | :--- |
| **1.1** | Plugin Activation | `activate_plugin()` | **PASS** | `is_plugin_active() === true` |
| **1.2** | Custom Tables Creation (14 Tables) | `SHOW TABLES LIKE 'wp_ft_%'` | **PASS** | Exactly 14 tables verified in MySQL |
| **1.3** | DB Versioning in `wp_options` | `get_option('fraud_tenant_db_version')` | **PASS** | Returns `'1.0.0'` |
| **1.4** | Membership Seed Data | SQL query on `wp_ft_membership_plans` | **PASS** | 3 plans (`free`, `standard_monthly`, `standard_yearly`) |
| **1.5** | Role Registration | Role existence check (`ft_owner`, `ft_tenant`) | **PASS** | Both custom roles present |
| **1.6** | Privilege Separation | `has_cap()` checks on Owner, Tenant, Admin | **PASS** | Owner & Tenant denied `ft_access_admin_portal` |
| **1.7** | Migration Idempotency | Forced rerun of `MigrationManager::migrate(true)` | **PASS** | Row counts preserved, zero duplicates/drops |
| **1.8** | Audit Logger Service | Insertion test with secret redaction | **PASS** | `wp_ft_audit_log` entry verified |
| **1.9** | Non-Destructive Deactivation | `deactivate_plugins()` test | **PASS** | Tables and records preserved |
| **1.10** | Safe REST Status Endpoint | `GET /wp-json/fraud-tenant/v1/status` | **PASS** | HTTP 200 OK, zero secrets exposed |
| **1.11** | Protected REST Callback | `GET /wp-json/fraud-tenant/v1/auth-check` | **PASS** | HTTP 401 Unauthorized for anonymous callers |
| **1.12** | Headless Chrome Screenshot | Chrome headless screenshot | **PASS** | Clean HTTP 200 front-end render |
| **1.13** | Git Security Audit | `git status --short` + `git status --ignored` | **PASS** | `wp-config.php` untracked |

---

## Phase 1 Corrective Security Review Execution & Verification

### Section 1: OTP HMAC Hashing & Fail-Closed Behavior
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **SEC-1.1** | Missing Secret Fail-Closed | `SecurityService::hash_otp('123456')` with unset key | Returns `false` | **PASS** |
| **SEC-1.2** | Missing Secret Verify Fail-Closed | `SecurityService::verify_otp('123456', 'hash')` with unset key | Returns `false` | **PASS** |
| **SEC-1.3** | Empty OTP Candidate Fail-Closed | `SecurityService::verify_otp('', 'hash')` | Returns `false` | **PASS** |
| **SEC-1.4** | Secret Configuration Detection | `SecurityService::is_otp_secret_configured()` | Returns `true` when set | **PASS** |
| **SEC-1.5** | HMAC-SHA256 Hash Generation | `SecurityService::hash_otp('841029')` with test secret | 64-character hex hash returned | **PASS** |
| **SEC-1.6** | Constant-Time Verification | `SecurityService::verify_otp('841029', $hash)` | Returns `true` via `hash_equals()` | **PASS** |
| **SEC-1.7** | Incorrect OTP Rejection | `SecurityService::verify_otp('999999', $hash)` | Returns `false` | **PASS** |
| **SEC-1.8** | Secret Change Invalidation | Verified after changing secret to different value | Returns `false` | **PASS** |
| **SEC-1.9** | Secret Isolation | Compare `FT_OTP_SECRET_KEY` and `FT_MATCH_SECRET_KEY` | Secrets are strictly independent | **PASS** |

### Section 2: Administrator & Role Capabilities
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **SEC-2.1** | Admin User ID 1 Verification | `get_user_by('id', 1)` | User exists (Login: `anik`, Role: `administrator`) | **PASS** |
| **SEC-2.2** | User 1 Custom Admin Capabilities | Loop `user_can(1, $cap)` for all 18 admin caps | User 1 possesses all 18 custom admin capabilities | **PASS** |
| **SEC-2.3** | User 1 Core WordPress Capabilities | Loop `user_can(1, $cap)` for `manage_options`, `install_plugins`, etc. | All core administrator capabilities retained | **PASS** |
| **SEC-2.4** | Owner Administrative Isolation | Loop capabilities on `ft_owner` | `ft_owner` denied all admin capabilities | **PASS** |
| **SEC-2.5** | Tenant Administrative Isolation | Loop capabilities on `ft_tenant` | `ft_tenant` denied all admin capabilities | **PASS** |
| **SEC-2.6** | Tenant Owner-Only Isolation | `ft_tenant->has_cap('ft_access_owner_dashboard')` | `ft_tenant` denied owner dashboard & properties | **PASS** |

### Section 3: Audit Logger & Payment Data Minimization
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **SEC-3.1** | Controlled Audit Record Creation | `AuditLogger::log()` | Inserted record successfully into `wp_ft_audit_log` | **PASS** |
| **SEC-3.2** | Sensitive Field Redaction | Pass array with passwords, OTPs, SSNs, cards, secrets | Stored `details_json` has all secrets replaced with `[REDACTED]` | **PASS** |
| **SEC-3.3** | Append-Only Application Interface | Reflection inspection on `AuditLogger` class | Zero `update()` or `delete()` methods exposed | **PASS** |
| **SEC-3.4** | Payment Payload Data Minimization | `Sanitizer::minimize_payment_payload()` on simulated Stripe event | Discards customer address, email, card details; preserves only event_id, status, amount | **PASS** |
| **SEC-3.5** | Payment Event Storage | `$wpdb->insert()` into `wp_ft_payment_events` | Minimized payload stored with status `processed` | **PASS** |
| **SEC-3.6** | Schema-Level Deduplication (Idempotency) | Duplicate `event_id` insert attempt | Rejected by MySQL `UNIQUE KEY event_id` constraint | **PASS** |

---

## Phase 2 Test Execution & Verification

### Section 1: Theme Activation, Asset Enqueueing & Database Integrity
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **2.1** | Theme Directory & Style Header | `wp_get_theme('fraud-tenant-theme')` | Theme name "Fraud Tenant Theme", Version 1.0.0 detected | **PASS** |
| **2.2** | Theme Activation | `switch_theme('fraud-tenant-theme')` | Active stylesheet and template set to `fraud-tenant-theme` | **PASS** |
| **2.3** | Static Front Page Configuration | `get_option('show_on_front')`, `get_option('page_on_front')` | Set to `'page'` and Page ID `9` (Home) | **PASS** |
| **2.4** | Core Plugin Continuity | `is_plugin_active()`, table count | `fraud-tenant-core` remains active; all 14 custom tables intact | **PASS** |
| **2.5** | CSS/JS Asset Enqueueing | Front page source inspection via HTTP | `fraud-tenant-style`, `fraud-tenant-main`, `fraud-tenant-script` loaded | **PASS** |

### Section 2: Public Routes & HTTP 200 Verification
| Test ID | Route / URL | HTTP Status | Title / Content Check | Result |
| :--- | :--- | :---: | :--- | :---: |
| **2.6** | `http://localhost/fraudtenant/` | **200 OK** | "A Safer Rental Community", Showcase cards rendered | **PASS** |
| **2.7** | `http://localhost/fraudtenant/how-it-works/` | **200 OK** | 4-step workflow rendered | **PASS** |
| **2.8** | `http://localhost/fraudtenant/for-property-owners/` | **200 OK** | Owner screening benefits rendered | **PASS** |
| **2.9** | `http://localhost/fraudtenant/for-tenants/` | **200 OK** | Tenant rights, disputes, and protection rendered | **PASS** |
| **2.10** | `http://localhost/fraudtenant/faq/` | **200 OK** | Interactive accordion FAQ rendered | **PASS** |
| **2.11** | `http://localhost/fraudtenant/contact/` | **200 OK** | Contact & Support form rendered with nonce | **PASS** |
| **2.12** | `http://localhost/fraudtenant/privacy-policy/` | **200 OK** | Privacy draft with SSN tokenization disclosure rendered | **PASS** |
| **2.13** | `http://localhost/fraudtenant/terms-of-service/` | **200 OK** | Terms draft with acceptable use terms rendered | **PASS** |
| **2.14** | `http://localhost/fraudtenant/tenant-rights/` | **200 OK** | Tenant rights, FCRA summary, dispute guidelines rendered | **PASS** |

### Section 3: Contact Form Server-Side Validation & Security
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **2.15** | Missing / Invalid CSRF Nonce | Simulated POST without valid `ft_contact_nonce` | Blocked via `wp_die(403)` with security message | **PASS** |
| **2.16** | Anti-Spam Honeypot Detection | Simulated POST with filled `ft_enquiry_hp` | Silently redirected to contact page without sending mail | **PASS** |
| **2.17** | Empty & Invalid Field Validation | Simulated POST with malformed email & 3-char message | Redirected with error code: `invalid_fields` | **PASS** |
| **2.18** | Privacy Safeguard (SSN Pattern Rejection) | Simulated POST containing `123-45-6789` in message | Redirected with privacy error code: `ssn_detected` | **PASS** |
| **2.19** | Legitimate Contact Submission | Simulated valid POST | Success notice set; `wp_mail()` invoked with sanitized data | **PASS** |
| **2.20** | Outbound SMTP Inbox Arrival | Local environment external delivery | Windows XAMPP environment lacks active SMTP relay | **NOT TESTED** |

### Section 4: Visual & Responsive Layout Verification (Headless Chrome)
| Test ID | Viewport | Capture Path / Artifact | Visual Verification Summary | Result |
| :--- | :--- | :--- | :--- | :---: |
| **2.21** | Desktop (1440x900) | `phase2-desktop-home.png` | Clean navy/teal branding, hero showcase, floating cards, mobile drawer hidden | **PASS** |
| **2.22** | Mobile (375x812) | `phase2-mobile-home.png` | Stacked hero, hamburger toggle, full mobile-first responsive layout | **PASS** |
| **2.23** | Tablet (768x1024) | `phase2-tablet-how-it-works.png` | Clean subpage header banner, responsive 2-column cards | **PASS** |
| **2.24** | Subpage: Contact (1280x800) | `phase2-contact-page.png` | Two-column grid: office contact details + accessible form | **PASS** |
| **2.25** | Subpage: Tenant Rights (1280x800) | `phase2-tenant-rights.png` | Professional legal layout, highlight notice card, typography | **PASS** |

---

## Phase 2 Final Audit and Corrective Review Execution & Verification

### Section 1: Public Claims & Content Audit (Task 1)
| Audit Item | Initial Finding | Corrective Action Applied | Verification Result |
| :--- | :--- | :--- | :---: |
| **Trust Metrics / User Counts** | "Trusted by thousands of property owners" lacked source backing | Replaced with truthful architectural claim: "Trusted platform architecture designed for property owners and tenants across the United States." | **PASS** |
| **Numerical Performance Claims** | No unverified success percentages (e.g. 99.4%) present | Verified zero unsupported percentages across all 9 pages | **PASS** |
| **Sample Report Preview** | Hero graphic contained applicant records without explicit sample label | Added prominent watermark: `Illustrative Sample Preview` with information badge icon | **PASS** |
| **Operational vs. Planned Claims** | Some landlord/tenant copy implied live screening was currently active | Updated copy to clearly state "Planned Landlord Features (Subject to Account Approval)" and "Planned Tenant Features" | **PASS** |
| **Contact / Business Information** | Invented phone number `+1 (833) 123-4567` and address `125 Business Ave, New York, NY` | Removed invented details; configured official project support channel `support@fraudtenant.com`, Digital Helpdesk form, and `United States (National Service)` | **PASS** |
| **Legal Draft Disclaimers** | Privacy Policy and Terms of Service needed prominent qualification | Verified prominent warning alert: `DRAFT DOCUMENT — FOR ARCHITECTURAL & DEMONSTRATION PURPOSES ONLY — REQUIRES QUALIFIED US LEGAL REVIEW` | **PASS** |
| **Tenant Rights & FCRA Disclaimer** | Statements on FCRA compliance needed legal counsel disclaimer | Added explicit disclaimer that information does not constitute formal legal counsel and requires attorney review | **PASS** |

### Section 2: Contact Form Hardening & Privacy Service (Task 2)
| Test ID | Test Description | Command / Execution Method | Observed Output | Result |
| :--- | :--- | :--- | :--- | :---: |
| **AUD-2.1** | Architecture Migration to Plugin | Moved handler to `FraudTenant\Services\ContactEnquiryHandler` in core plugin | Preserves form handling across any theme switch | **PASS** |
| **AUD-2.2** | Multi-Field SSN Check (Message) | `contains_ssn_pattern('SSN is 123-45-6789')` | SSN pattern detected and blocked | **PASS** |
| **AUD-2.3** | Multi-Field SSN Check (Subject) | `contains_ssn_pattern('Inquiry 123 45 6789')` | SSN pattern detected in subject and blocked | **PASS** |
| **AUD-2.4** | Multi-Field SSN Check (Name) | `contains_ssn_pattern('John 123456789')` | Contiguous 9-digit SSN detected in name and blocked | **PASS** |
| **AUD-2.5** | Pre-Mail Rejection | Simulated POST with SSN candidate | Rejected prior to `wp_mail()` invocation | **PASS** |
| **AUD-2.6** | Zero Sensitive Data Echoing | URL redirect query args inspection | No raw user message/name echoed in URL parameters; returns generic `ft_notice=ssn_detected` | **PASS** |
| **AUD-2.7** | Zero Logging of Rejected Candidates | Review of audit logging during rejection | No sensitive candidates or message payloads recorded to logs or DB | **PASS** |
| **AUD-2.8** | Spoof-Resistant Client IP | Inspection of `get_client_ip()` | Uses `$_SERVER['REMOTE_ADDR']` directly; ignores spoofable headers | **PASS** |
| **AUD-2.9** | Rate Limiting Enforcement | Second POST within 60-second window | Blocked with notice `rate_limit` | **PASS** |
| **AUD-2.10** | Outbound SMTP Inbox Arrival | Local environment mail transport | Windows XAMPP environment lacks active external SMTP relay | **NOT TESTED** |

### Section 3: 5-Viewport Responsive & Navigation QA (Task 3)
| Test ID | Viewport | Target URL | Verified Artifact Path | Result |
| :--- | :---: | :--- | :--- | :---: |
| **AUD-3.1** | 1440 × 900 | `http://localhost/fraudtenant/` | `phase2-audit-1440x900-home.png` | **PASS** |
| **AUD-3.2** | 1024 × 768 | `http://localhost/fraudtenant/` | `phase2-audit-1024x768-home.png` | **PASS** |
| **AUD-3.3** | 768 × 1024 | `http://localhost/fraudtenant/` | `phase2-audit-768x1024-home.png` | **PASS** |
| **AUD-3.4** | 375 × 812 | `http://localhost/fraudtenant/` | `phase2-audit-375x812-home.png` | **PASS** |
| **AUD-3.5** | 320 × 700 | `http://localhost/fraudtenant/` | `phase2-audit-320x700-home.png` | **PASS** |
| **AUD-3.6** | 1440 × 900 | `http://localhost/fraudtenant/contact/` | `phase2-audit-1440x900-contact.png` | **PASS** |
| **AUD-3.7** | 1440 × 900 | `http://localhost/fraudtenant/for-property-owners/` | `phase2-audit-1440x900-for-owners.png` | **PASS** |
| **AUD-3.8** | 1440 × 900 | `http://localhost/fraudtenant/for-tenants/` | `phase2-audit-1440x900-for-tenants.png` | **PASS** |
| **AUD-3.9** | 1440 × 900 | `http://localhost/fraudtenant/tenant-rights/` | `phase2-audit-1440x900-tenant-rights.png` | **PASS** |
| **AUD-3.10** | All Viewports | Horizontal Overflow Inspection | Zero horizontal scrolling (`scrollWidth <= innerWidth`) across all viewports | **PASS** |
| **AUD-3.11** | All Viewports | Console / PHP Errors Inspection | `wp-content/debug.log` clean; zero fatal or parse errors | **PASS** |

### Section 4: Theme, Plugin & Database Integrity (Task 4)
| Test ID | Component / Property | Check Method | Expected State | Result |
| :--- | :--- | :--- | :--- | :---: |
| **AUD-4.1** | Active Theme | `wp_get_theme()->get_stylesheet()` | `fraud-tenant-theme` | **PASS** |
| **AUD-4.2** | Active Core Plugin | `is_plugin_active()` | `fraud-tenant-core/fraud-tenant-core.php` | **PASS** |
| **AUD-4.3** | Custom Tables Intact | `SHOW TABLES LIKE 'wp_ft_%'` | Exactly 14 tables intact | **PASS** |
| **AUD-4.4** | Administrator Capabilities | `user_can(1, $cap)` | All 18 custom admin capabilities retained | **PASS** |
| **AUD-4.5** | Role Capability Isolation | `get_role('ft_owner')`, `get_role('ft_tenant')` | Zero admin capabilities assigned | **PASS** |
| **AUD-4.6** | Page Overwrite Prevention | Check existing page IDs (IDs 9-17) | Existing page IDs and content preserved | **PASS** |
| **AUD-4.7** | Phase Boundary Verification | Auth, screening, Stripe code check | Zero Phase 3+ features implemented | **PASS** |

---

## Phase 3 Test Execution & Verification

### Authentication, OTP Lifecycle & Security Verification
| Test ID | Test Name | Method / Assertion | Result | Evidence / Details |
| :--- | :--- | :--- | :---: | :--- |
| **3.1** | OTP Secret Key Configuration | `SecurityService::is_otp_secret_configured()` | **PASS** | Key configured in untracked `wp-config.php`, fails closed if absent |
| **3.2** | Registration Missing Name Guard | `AuthService::register()` with empty names | **PASS** | Returns `missing_name` error |
| **3.3** | Registration Invalid Email Guard | `AuthService::register()` with invalid email | **PASS** | Returns `invalid_email` error |
| **3.4** | Registration Password Length Guard | `AuthService::register()` with < 8 chars | **PASS** | Returns `weak_password` error |
| **3.5** | Registration Password Mismatch Guard | `AuthService::register()` with mismatched pw | **PASS** | Returns `password_mismatch` error |
| **3.6** | Registration Disallowed Role Guard | `AuthService::register()` with invalid role | **PASS** | Returns `invalid_account_type` error |
| **3.7** | Registration Terms Acknowledgment | `AuthService::register()` with unacknowledged terms | **PASS** | Returns `terms_unacknowledged` error |
| **3.8** | Successful Owner Registration | `AuthService::register()` with valid owner data | **PASS** | Creates WP user with `ft_owner` role & `wp_ft_user_profiles` row |
| **3.9** | Initial Account Status Verification | `AuthService::get_user_account_status()` | **PASS** | Verified initial status is `email_unverified` |
| **3.10** | Anti-Enumeration on Duplicate Email | `AuthService::register()` with existing email | **PASS** | Returns generic success without leaking account existence |
| **3.11** | Successful Tenant Registration | `AuthService::register()` with valid tenant data | **PASS** | Creates WP user with `ft_tenant` role & `wp_ft_user_profiles` row |
| **3.12** | Unverified Login Restriction | `AuthService::login()` with `email_unverified` user | **PASS** | Blocked with `email_unverified` error; zero auth cookie issued |
| **3.13** | Cryptographic 6-Digit OTP Generation | `SHOW * FROM wp_ft_email_otps` | **PASS** | 6-digit numeric OTP generated and stored via HMAC-SHA-256 hash |
| **3.14** | Incorrect OTP Verifier Guard | `OtpService::verify_otp()` with wrong code | **PASS** | Rejected; attempt counter incremented |
| **3.15** | Resend 60-Second Cooldown Guard | `OtpService::generate_otp()` during active cooldown | **PASS** | Rejected with `cooldown_active` error and remaining seconds |
| **3.16** | Valid HMAC OTP Verification | `OtpService::verify_otp()` with matching code | **PASS** | Constant-time `hash_equals()` HMAC comparison succeeds |
| **3.17** | Single-Use OTP Protection | Replaying verified OTP code | **PASS** | Rejected with `no_active_otp` error (`consumed_at` populated) |
| **3.18** | Status Transition to Pending Review | Status check post-verification | **PASS** | Profile transitions from `email_unverified` to `pending_approval` |
| **3.19** | Pending Approval Login Routing | `AuthService::login()` with verified user | **PASS** | Session cookie issued; routed safely to `/account-status/` |
| **3.20** | Rejected Account Login Guard | `AuthService::login()` with `rejected` status | **PASS** | Blocked with `account_rejected` error; zero session cookie |
| **3.21** | Suspended Account Login Guard | `AuthService::login()` with `suspended` status | **PASS** | Blocked with `account_suspended` error; zero session cookie |
| **3.22** | Administrator Access Preservation | `AuthService::login()` with User ID 1 (`anik`) | **PASS** | Session cookie issued; direct route to `admin_url()` preserved |
| **3.23** | Password Reset Request Enumeration Guard | `AuthService::request_password_reset()` | **PASS** | Generic dispatch confirmation returned for existing & non-existing emails |
| **3.24** | WP Core Password Reset Key Generation | `get_password_reset_key()` | **PASS** | Valid core WordPress key and reset URL generated |
| **3.25** | Password Reset Weak Password Guard | `AuthService::reset_password_with_key()` with short pw | **PASS** | Rejected with `weak_password` error |
| **3.26** | Password Reset Mismatch Guard | `AuthService::reset_password_with_key()` with mismatched pw | **PASS** | Rejected with `password_mismatch` error |
| **3.27** | Password Reset Success Verification | `AuthService::reset_password_with_key()` with valid key | **PASS** | Password updated via core `reset_password()`; audit log recorded |
| **3.28** | Post-Reset Authentication | `wp_authenticate()` with new credentials | **PASS** | User authenticates successfully with updated password |
| **3.29** | HTTP Endpoint Availability (6 Auth Pages) | HTTP GET checks on all 6 auth URLs | **PASS** | All 6 URLs return HTTP 200 OK |
| **3.30** | Multi-Viewport Design System & Fidelity | Headless Chrome screenshot verification | **PASS** | Verified matching UI Showcase cards, split login, and responsive layout |

---

## Phase 3 Correction Test Execution & Verification

### Distinct Registration Experiences, Secure Document Storage & Field Validation
| Test ID | Test Name | Method / Assertion | Result | Evidence / Details |
| :--- | :--- | :--- | :---: | :--- |
| **3C.1** | Phone Format Validator (Dashes) | `AuthService::validate_phone('555-123-4567')` | **PASS** | Valid 10-digit US phone passes |
| **3C.2** | Phone Format Validator (Parens) | `AuthService::validate_phone('(555) 123-4567')` | **PASS** | Valid formatted US phone passes |
| **3C.3** | Phone Format Validator (Country Code) | `AuthService::validate_phone('+1 555 123 4567')` | **PASS** | Valid 11-digit US phone with code passes |
| **3C.4** | Phone Short Digits Guard | `AuthService::validate_phone('12345')` | **PASS** | Short phone numbers rejected |
| **3C.5** | ZIP Code Validator (5 Digits) | `AuthService::validate_zip('90210')` | **PASS** | Valid standard 5-digit ZIP passes |
| **3C.6** | ZIP Code Validator (ZIP+4) | `AuthService::validate_zip('90210-1234')` | **PASS** | Valid 9-digit US ZIP+4 passes |
| **3C.7** | ZIP Code Truncated Guard | `AuthService::validate_zip('9021')` | **PASS** | 4-digit ZIP rejected |
| **3C.8** | ZIP Code Non-Numeric Guard | `AuthService::validate_zip('ABCDE')` | **PASS** | Non-numeric ZIP rejected |
| **3C.9** | Owner Registration Missing Name Guard | `AuthService::register_owner()` with empty name | **PASS** | Rejected with `missing_name` error |
| **3C.10** | Owner Registration Invalid Email Guard | `AuthService::register_owner()` with malformed email | **PASS** | Rejected with `invalid_email` error |
| **3C.11** | Owner Registration Invalid Phone Guard | `AuthService::register_owner()` with short phone | **PASS** | Rejected with `invalid_phone` error |
| **3C.12** | Owner Registration Password Mismatch | `AuthService::register_owner()` with mismatch pw | **PASS** | Rejected with `password_mismatch` error |
| **3C.13** | Owner Registration Missing Property Street | `AuthService::register_owner()` with empty street | **PASS** | Rejected with `missing_property_street` error |
| **3C.14** | Owner Registration Invalid Property ZIP | `AuthService::register_owner()` with 4-digit ZIP | **PASS** | Rejected with `invalid_property_zip` error |
| **3C.15** | Owner Registration Missing Document Guard | `AuthService::register_owner()` with no file | **PASS** | Rejected with `missing_document` error |
| **3C.16** | Owner Registration Disallowed Extension | Uploading `.php` / `.txt` file | **PASS** | Rejected with `invalid_file_extension` error |
| **3C.17** | Owner Registration Oversized File Guard | Uploading >10MB document file | **PASS** | Rejected with `file_size_limit_exceeded` error |
| **3C.18** | Owner Registration Terms Consent Guard | Unchecked `terms_accepted` | **PASS** | Rejected with `terms_unacknowledged` error |
| **3C.19** | Owner Registration Privacy Consent Guard | Unchecked `privacy_accepted` | **PASS** | Rejected with `privacy_unacknowledged` error |
| **3C.20** | Valid Property Owner Registration | `AuthService::register_owner()` with valid data | **PASS** | Core user, profile, property & document records created |
| **3C.21** | Owner Role Assignment | User roles inspection | **PASS** | User assigned `ft_owner` role strictly |
| **3C.22** | Owner Profile Initial Status | Profile inspection in `wp_ft_user_profiles` | **PASS** | Profile status set to `email_unverified` |
| **3C.23** | Terms & Privacy Timestamps Recorded | Profile audit inspection | **PASS** | `terms_accepted_at` and `privacy_accepted_at` recorded |
| **3C.24** | Owner Phone Number Persisted | Profile inspection | **PASS** | Phone number saved in `wp_ft_user_profiles.phone` |
| **3C.25** | Primary Property Persistence | `wp_ft_properties` inspection | **PASS** | Address recorded with `pending_verification` status |
| **3C.26** | Ownership Document Record Persistence | `wp_ft_documents` inspection | **PASS** | Document registered with `access_permission = 'private'` |
| **3C.27** | Physical Document Storage In Protected Dir | File system check in `fraud-tenant-protected/` | **PASS** | File stored with randomized name `doc_<token>_<time>.<ext>` |
| **3C.28** | Protected Storage Direct HTTP Access Block | `curl.exe -I http://.../fraud-tenant-protected/...` | **PASS** | Apache returns `HTTP 403 Forbidden` (`.htaccess` enforced) |
| **3C.29** | Tenant Registration Missing Name Guard | `AuthService::register_tenant()` with empty name | **PASS** | Rejected with `missing_name` error |
| **3C.30** | Tenant Registration Missing Current Street | `AuthService::register_tenant()` with empty street | **PASS** | Rejected with `missing_current_street` error |
| **3C.31** | Tenant Incomplete Previous Address Guard | Incomplete previous rental address | **PASS** | Rejected with `incomplete_previous_address` error |
| **3C.32** | Valid Tenant Registration Success | `AuthService::register_tenant()` with valid data | **PASS** | Core user and profile records created |
| **3C.33** | Tenant Role Assignment | User roles inspection | **PASS** | User assigned `ft_tenant` role strictly |
| **3C.34** | Tenant Profile Initial Status | Profile inspection in `wp_ft_user_profiles` | **PASS** | Profile status set to `email_unverified` |
| **3C.35** | Tenant Current Residence Persisted | Profile inspection | **PASS** | Street, city, state, and ZIP saved in profile |
| **3C.36** | Tenant Previous Residence Persisted | Profile inspection | **PASS** | `prev_address_line1`, `prev_city`, etc. saved in profile |
| **3C.37** | Date of Birth Exclusion Verification | Profile schema inspection | **PASS** | Zero date of birth field collected or stored |
| **3C.38** | Duplicate Registration Anti-Enumeration | Re-submitting registered email | **PASS** | Generic success returned; existence not disclosed |
| **3C.39** | OTP Generation Challenge Parameters | `wp_ft_email_otps` inspection | **PASS** | 6-digit code, 10-min expiry, max 5 attempts, 60s cooldown |
| **3C.40** | Incorrect OTP Verifier Guard | Submitting incorrect OTP | **PASS** | Rejected; attempt counter incremented |
| **3C.41** | Valid OTP Verification | Submitting matching HMAC OTP | **PASS** | Verification succeeds; single-use timestamp applied |
| **3C.42** | Status Transition to Pending Review | User status check | **PASS** | Status transitions from `email_unverified` to `pending_approval` |
| **3C.43** | Pending Approval Routing | `AuthService::login()` with verified user | **PASS** | User routed to `/account-status/` holding page |
| **3C.44** | Production SMTP Real Inbox Delivery | Live outbound SMTP relay check | **NOT TESTED** | Local XAMPP environment; mock/DB verification verified |

---

## Phase Test Summary Table

| Phase / Review | Total Tests | PASS | FAIL | BLOCKED | NOT TESTED |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Phase 0 Audit** | 6 | 6 | 0 | 0 | 0 |
| **Phase 1 Foundation** | 13 | 13 | 0 | 0 | 0 |
| **Phase 1 Security Review** | 21 | 21 | 0 | 0 | 0 |
| **Phase 2 Custom Theme & Website** | 25 | 24 | 0 | 0 | 1 |
| **Phase 2 Final Corrective Audit** | 35 | 34 | 0 | 0 | 1 |
| **Phase 3 Auth, OTP & Recovery** | 30 | 30 | 0 | 0 | 0 |
| **Phase 3 Correction: Forms & Docs** | 44 | 43 | 0 | 0 | 1 |
| **Total Verified** | **174** | **171** | **0** | **0** | **3** |
