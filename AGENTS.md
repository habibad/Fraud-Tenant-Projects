# FRAUD TENANT — Agent Directives & Architecture Rules

## 1. Operating Rules for AI Agents

1. **Strict Phase-by-Phase Execution**: Never proceed to a subsequent phase without explicit approval from the user. Complete the current phase, document results, and stop.
2. **Never Fabricate or Assume**: Do not claim a feature is implemented or tested unless verified. If dependencies or credentials are unavailable, record them in `BLOCKERS.md`.
3. **Preserve Existing Code**: Inspect before modifying. Do not remove or overwrite working functionality or core assets without explicit justification.
4. **No Mock Data in Place of Logic**: Real database queries, server-side validation, authentication checks, and business logic must back all screens.
5. **Security & Privacy First**:
   - Never log, expose in URLs, or leak SSNs, OTPs, auth tokens, passwords, or Stripe secret keys.
   - All protected actions, REST endpoints, and document downloads must enforce server-side capability and ownership verification.
   - Nonces must be used for CSRF protection, but never as a substitute for authorization checks.
6. **Separation of Shells**:
   - The public marketing website is separate from the authenticated dashboard application shell.
   - Keep the oversized marketing header and footer off authenticated dashboard pages.
7. **Maintain Project Documentation**:
   - Keep `AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, and `CHANGELOG.md` updated after every phase.

---

## 2. Approved Design System

- **Primary Navy**: `#14263E` (Brand dark, sidebar, headings, strong text)
- **Primary Teal**: `#0E9488` (Accents, active states, primary CTA buttons, badges)
- **Background**: `#F2F5F9` (Light gray-blue application and page backdrop)
- **Card Background**: `#FFFFFF` (Clean white with subtle borders/shadows)
- **Typography**: Inter / System Sans-serif, crisp, highly legible, modern PropTech aesthetic
- **Design Source of Truth**: `Fraud Tenant Product UI Showcase.png`
  - Floating badges ("A Safer Rental Community")
  - Hero visual cards ("Safer Tenancies Stronger Communities", "Applicant Found - View Report (Locked)")
  - Step progress indicators: 1. Register -> 2. Get Verified -> 3. Access Features -> 4. Build Safer Rentals
  - Distinct dashboard shells for Property Owner and Tenant
  - Specific authentication modals/pages (Login, Role Selection Register, 6-digit OTP Input)

---

## 3. Platform Architecture

### Custom Theme: `fraud-tenant-theme`
- Location: `wp-content/themes/fraud-tenant-theme`
- Purpose: Presentation layer for the public marketing website, authentication templates, and base template shell for dashboards.
- Theme switching must never destroy core application data or database tables.

### Custom Core Plugin: `fraud-tenant-core`
- Location: `wp-content/plugins/fraud-tenant-core`
- Purpose: All business logic, custom tables, REST API endpoints, roles/capabilities, OTP handling, screening logic, incident reports, payment webhooks, notifications, and audit logging.
- Object-Oriented architecture with single-responsibility service classes.

### REST API Namespace
- Namespace: `fraud-tenant/v1`
- Server-side permission callbacks (`permission_callback`) mandatory on every endpoint.

### Custom Database Architecture
All custom tables dynamically use `$wpdb->prefix` (14 tables created in Phase 1):
- `{$wpdb->prefix}ft_user_profiles`: Extended profile, approval status, business details
- `{$wpdb->prefix}ft_email_otps`: Hashed OTPs, purpose, expiration, attempt count
- `{$wpdb->prefix}ft_properties`: Property listings owned by property owners
- `{$wpdb->prefix}ft_documents`: Metadata and access rules for uploaded evidence/documents
- `{$wpdb->prefix}ft_tenant_identities`: Keyed HMAC tokens for SSN matching, last 4 digits, legal name
- `{$wpdb->prefix}ft_screening_requests`: Authorized tenant search records and permissible-purpose tracking
- `{$wpdb->prefix}ft_incident_reports`: Factual incident records submitted by owners
- `{$wpdb->prefix}ft_report_reviews`: Admin review decisions and notes
- `{$wpdb->prefix}ft_tenant_disputes`: Tenant dispute submissions, statements, evidence
- `{$wpdb->prefix}ft_membership_plans`: Available membership tiers and pricing
- `{$wpdb->prefix}ft_subscriptions`: Owner subscription state, Stripe customer/subscription IDs
- `{$wpdb->prefix}ft_payment_events`: Stripe webhook events log and idempotency tracking
- `{$wpdb->prefix}ft_notifications`: In-app notification queue
- `{$wpdb->prefix}ft_audit_log`: Tamper-evident audit trail for security and regulatory compliance

### User Roles & Custom Capabilities
- `administrator`: Full administrative portal, approval queue, review controls, plan management
- `ft_owner` (Individual Property Owner): Manage properties, initiate authorized screening, submit incident reports, manage membership
- `ft_tenant` (Tenant): View own authorized records, submit disputes and corrections, view notifications

### Security & Privacy Directives
1. **SSN Tokenization**: Plaintext SSNs must never be stored in persistent database tables or included in logs. A keyed HMAC hash (`hash_hmac('sha256', $ssn, $secret_key)`) must be used for tenant matching.
2. **Document Protection**: Supporting documents must not be served directly through public URLs. They must be stored in a protected folder and served via an authorized streaming controller that validates current user permissions.
3. **No Automatic Defamation / Fraud Labels**: Incident reports are treated as factual dispute/incident allegations pending review. Allegations must never be presented as proven guilt without human administrative review.
4. **FCRA & Legal Readiness**: Screening access requires permissible purpose attestation and server-side verification.
