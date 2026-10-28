# FRAUD TENANT — Task Tracker

## Task Status Legend
- `[x]` DONE
- `[/]` IN PROGRESS
- `[ ]` TODO
- `[!]` BLOCKED

---

## Phase 0: Repository Audit and Implementation Preparation
- [x] **Task 0.1**: Inspect directory, WordPress installation, and Git status. *(Completed: WordPress 7.1.3 on PHP 8.2.12 detected; Git initialized; `.gitignore` created)*
- [x] **Task 0.2**: Identify active theme, installed plugins, custom code, and database schema. *(Completed: Twenty Twenty-Five active, 0 plugins, standard 12 WP core tables)*
- [x] **Task 0.3**: Determine local development URL and verify browser testing tools. *(Completed: `http://localhost/fraudtenant` returns HTTP 200; Headless Chrome verified)*
- [x] **Task 0.4**: Review approved design reference and authentication workflow diagrams. *(Completed: `Fraud Tenant Product UI Showcase.png` and flowcharts cataloged)*
- [x] **Task 0.5**: Identify reusable assets, conflicts, and configuration requirements. *(Completed: Standard WP functions, clean environment, no conflicting plugins)*
- [x] **Task 0.6**: Formulate system architecture and technical boundaries. *(Completed: Documented in `AGENTS.md` and `IMPLEMENTATION_PLAN.md`)*
- [x] **Task 0.7**: Confirm exclusion of secrets and environment config from Git. *(Completed: `wp-config.php`, logs, and uploads excluded in `.gitignore`)*
- [x] **Task 0.8**: Create project documentation suite (`AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, `CHANGELOG.md`). *(Completed)*
- [x] **Task 0.9**: Produce Phase 0 Audit Report and await user review. *(Completed)*

---

## Phase 1: Custom Plugin Foundation and Database Architecture
- [x] **Task 1.1**: Initialize `fraud-tenant-core` plugin structure (`wp-content/plugins/fraud-tenant-core/`). *(Completed: Modular PSR-4 compatible structure with Autoloader)*
- [x] **Task 1.2**: Implement plugin activation and deactivation hooks. *(Completed: `Activator` runs migrations/roles/audit; `Deactivator` non-destructively flushes rewrites)*
- [x] **Task 1.3**: Build database migration manager using dynamic `$wpdb->prefix`. *(Completed: `MigrationManager` with `dbDelta` version checking `fraud_tenant_db_version` = `1.0.0`)*
- [x] **Task 1.4**: Define schema for 14 custom application tables with indexes and foreign keys. *(Completed: 14 tables verified in MySQL, zero plaintext SSN/OTP)*
- [x] **Task 1.5**: Register custom roles (`ft_owner`, `ft_tenant`) and custom capabilities. *(Completed: Registered on activation; admin gets management capabilities; owners/tenants denied admin portal)*
- [x] **Task 1.6**: Establish REST API namespace `fraud-tenant/v1` and base controller. *(Completed: `BaseController`, safe `DiagnosticController` at `/status`, permission callbacks)*
- [x] **Task 1.7**: Implement secure error logging service without leaking PII or secrets. *(Completed: `AuditLogger` with `Sanitizer::redact_sensitive_data`)*
- [x] **Task 1.8**: Test activation, table creation, role verification, and migration idempotency. *(Completed: 10 automated and CLI/HTTP tests executed, all PASS)*

---

## Phase 2: Custom Theme, Design System and Public Website
- [x] **Task 2.1**: Initialize `fraud-tenant-theme` (`wp-content/themes/fraud-tenant-theme/`). *(Completed: Complete theme files, style.css, functions.php, header, footer, templates)*
- [x] **Task 2.2**: Implement core CSS/JS assets matching approved design system (`#14263E`, `#0E9488`, `#F2F5F9`). *(Completed: CSS variables, components, typography, accessible mobile drawer and FAQ accordions in main.js)*
- [x] **Task 2.3**: Build reusable header, navigation bar, and footer components. *(Completed: Semantic header with SVG shield, responsive navigation, and comprehensive 4-column footer)*
- [x] **Task 2.4**: Implement Home/Landing page matching approved showcase layout. *(Completed: Hero banner, floating status cards, 4-step workflow, trust metrics, audience split cards)*
- [x] **Task 2.5**: Implement public content pages (How It Works, For Property Owners, For Tenants, Pricing, FAQ). *(Completed: 9 published WordPress pages with full PropTech content)*
- [x] **Task 2.6**: Implement Contact & Enquiry form with server-side validation and anti-spam. *(Completed: CSRF nonce, honeypot trap, rate limiting, and SSN detection privacy rejection)*
- [x] **Task 2.7**: Implement Privacy Policy, Terms of Service, and Tenant Rights disclosure drafts. *(Completed: Comprehensive legal drafts emphasizing FCRA readiness and tenant rights)*
- [x] **Task 2.8**: Browser testing across desktop, tablet, and mobile breakpoints. *(Completed: Headless Chrome screenshots captured at 1440px, 768px, and 375px)*

---

## Phase 3: Registration, Email OTP, Login and Password Recovery
- [ ] **Task 3.1**: Build custom registration templates with Property Owner vs. Tenant selection.
- [ ] **Task 3.2**: Implement 6-digit email OTP generation, HMAC hashing, and expiration logic.
- [ ] **Task 3.3**: Build 6-digit OTP verification screen with countdown cooldown timer and attempt limits.
- [ ] **Task 3.4**: Implement custom login page with role-based redirection.
- [ ] **Task 3.5**: Build forgot-password and reset-password flows with enumeration protection.
- [ ] **Task 3.6**: Implement account status lifecycle (`email_unverified`, `pending_approval`, `approved`, `rejected`, `suspended`).
- [ ] **Task 3.7**: Test full authentication cycle in browser.

---

## Phase 4: Administrative User Review and Approval Portal
- [ ] **Task 4.1**: Build administrative portal shell and menu integration.
- [ ] **Task 4.2**: Implement pending user review queue for Owners and Tenants.
- [ ] **Task 4.3**: Build application detail view displaying submitted business and property proof.
- [ ] **Task 4.4**: Implement approve, reject (with recorded reason), request info, and suspend actions.
- [ ] **Task 4.5**: Connect decision triggers to automated email notifications.
- [ ] **Task 4.6**: Implement administrative decision audit logging.
- [ ] **Task 4.7**: Test approval/rejection workflows and authorization guards.

---

## Phase 5: Owner and Tenant Dashboards
- [ ] **Task 5.1**: Build dedicated responsive dashboard application shell (isolated from marketing layout).
- [ ] **Task 5.2**: Implement Property Owner Dashboard (Overview, Properties, Tenant Search, Reports, Membership, Notifications, Settings).
- [ ] **Task 5.3**: Implement Tenant Dashboard (Overview, Rental Records, Report Details, Disputes, Notifications, Security).
- [ ] **Task 5.4**: Wire dynamic database counts, real user metadata, and empty states.
- [ ] **Task 5.5**: Enforce server-side role and capability verification on all dashboard routes.
- [ ] **Task 5.6**: Execute browser testing for both roles.

---

## Phase 6: Property Management and Secure Document Uploads
- [ ] **Task 6.1**: Implement property CRUD interface and database handling for owners.
- [ ] **Task 6.2**: Build secure document upload pipeline with MIME, size, and extension validation.
- [ ] **Task 6.3**: Implement protected document storage outside webroot / `.htaccess` denied path.
- [ ] **Task 6.4**: Create authorized download controller with record-level permission checks.
- [ ] **Task 6.5**: Implement IDOR prevention and document access audit logging.
- [ ] **Task 6.6**: Test property management and document download protection.

---

## Phase 7: Authorized Tenant Search Using SSN
- [ ] **Task 7.1**: Build tenant search UI with SSN input mask and permissible-purpose attestation.
- [ ] **Task 7.2**: Implement server-side keyed HMAC matching token generator.
- [ ] **Task 7.3**: Enforce strict no-plaintext-SSN storage and URL exclusion rules.
- [ ] **Task 7.4**: Implement tiered disclosure logic (Free tier neutral status vs. Paid tier verified details).
- [ ] **Task 7.5**: Add search rate limiting, access auditing, and error handling.
- [ ] **Task 7.6**: Test search workflows across free, paid, authorized, and unauthorized accounts.

---

## Phase 8: Incident Reporting, Evidence Review and Tenant Disputes
- [ ] **Task 8.1**: Build owner incident reporting submission form with evidence attachment.
- [ ] **Task 8.2**: Implement administrative review workflow for incident reports.
- [ ] **Task 8.3**: Build tenant notification and authorized report inspection interface.
- [ ] **Task 8.4**: Implement tenant dispute submission and evidence upload workflow.
- [ ] **Task 8.5**: Implement administrative dispute resolution and audit trail correction.
- [ ] **Task 8.6**: Test complete lifecycle from report submission to dispute resolution.

---

## Phase 9: Membership Plans and Stripe Subscriptions
- [!] **Task 9.1**: Configure Stripe API test credentials and webhook signing secret. *(Requires test keys)*
- [ ] **Task 9.2**: Implement admin membership plan manager (monthly/yearly tiers).
- [ ] **Task 9.3**: Implement Stripe Checkout integration for subscription creation.
- [ ] **Task 9.4**: Implement webhook controller with signature verification and idempotency checks.
- [ ] **Task 9.5**: Build server-side subscription validation enforcing feature access.
- [ ] **Task 9.6**: Test checkout, renewal, cancellation, and payment failure handling.

---

## Phase 10: Notifications, Email Workflows and Audit Trail
- [ ] **Task 10.1**: Implement unified notification service supporting in-app and `wp_mail()`.
- [ ] **Task 10.2**: Wire notification dispatchers for all critical lifecycle events.
- [ ] **Task 10.3**: Implement in-dashboard notification center with read/unread tracking.
- [ ] **Task 10.4**: Implement tamper-evident audit logging for security and compliance events.
- [ ] **Task 10.5**: Test notification delivery and audit trail completeness.

---

## Phase 11: Integration, Security, Accessibility and Production Readiness
- [ ] **Task 11.1**: Execute end-to-end user journeys for Owner, Tenant, and Admin.
- [ ] **Task 11.2**: Conduct security testing (SQLi, XSS, CSRF, IDOR, brute-force, data leakage).
- [ ] **Task 11.3**: Conduct accessibility audit (WCAG AA compliance, keyboard navigation).
- [ ] **Task 11.4**: Compile final QA report, legal launch gate review, and deployment guide.
