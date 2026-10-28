# FRAUD TENANT

> A secure, modern, responsive WordPress-based tenant screening and rental incident reporting platform for the US rental housing market.

---

## 1. Project Overview

Fraud Tenant enables individual property owners and landlords to verify prospective tenants through secure, FCRA-aligned screening requests, view verified rental incident histories, and submit factual incident reports.

Tenants can view authorized records, exercise their FCRA dispute rights, submit corrections, and receive real-time notifications about their rental profile.

### Core Visual Design System
- **Primary Navy**: `#14263E` (Brand dark, sidebar, headings, strong text)
- **Primary Teal**: `#0E9488` (Accents, active states, primary CTA buttons, badges)
- **Background**: `#F2F5F9` (Light gray-blue application and page backdrop)
- **Card Background**: `#FFFFFF` (Clean white with subtle borders and shadows)
- **Typography**: Inter / System Sans-serif, crisp, highly legible modern PropTech SaaS aesthetic

---

## 2. Architecture & Repository Structure

This repository uses a clean **Monorepo** structure tracking only custom application assets and documentation. WordPress core files, third-party vendor plugins, uploads, and local configuration files are strictly excluded.

```
fraudtenant/
├── AGENTS.md                          # Architecture directives & operating rules
├── IMPLEMENTATION_PLAN.md             # 12-phase execution roadmap & status
├── TASKS.md                           # Detailed task tracking
├── QA_REPORT.md                       # Comprehensive testing evidence & QA matrix
├── BLOCKERS.md                        # External dependencies & compliance gates
├── SETUP.md                           # Local installation & verification guide
├── CHANGELOG.md                       # Phase-by-phase change record
├── README.md                          # This file
├── .gitignore                         # Strict exclusion rules (no core, no secrets)
└── wp-content/
    ├── plugins/
    │   └── fraud-tenant-core/         # Custom Core Plugin (Backend & Business Logic)
    │       ├── fraud-tenant-core.php  # Plugin bootstrap
    │       └── includes/
    │           ├── Core/              # Activator, Deactivator, Plugin orchestrator
    │           ├── Database/          # MigrationManager, Schema (14 custom tables)
    │           ├── Auth/              # RoleManager (ft_owner, ft_tenant), Capabilities
    │           ├── API/               # RestServer, BaseController, DiagnosticController
    │           ├── Services/          # AuditLogger, SecurityService (HMAC Tokenization)
    │           └── Utils/             # Constants, Sanitizer
    └── themes/
        └── fraud-tenant-theme/        # Custom Theme (Frontend, Shells & Templates)
            ├── style.css              # Theme definition & CSS variables
            ├── functions.php          # Theme setup, assets enqueueing, contact handler
            ├── header.php             # Semantic header, shield logo, mobile drawer
            ├── footer.php             # 4-column footer with FCRA disclaimers
            ├── front-page.php         # Landing page (matching UI Showcase)
            ├── page.php               # Content card template
            ├── index.php              # Fallback template
            ├── assets/
            │   ├── css/main.css       # Responsive design system CSS
            │   └── js/main.js         # Accessible drawer, FAQ accordions, form validation
            └── template-parts/
                ├── page-header.php    # Reusable page banner component
                └── contact-form.php   # Nonce-protected contact form with honeypot
```

---

## 3. Security & Privacy Guarantees

1. **Zero Plaintext SSN Storage**: Social Security Numbers are never stored in database tables, cookies, URLs, or application logs. Tenant matching uses a server-side keyed HMAC-SHA-256 token hash (`FT_MATCH_SECRET_KEY`) storing only masked values (`•••-••-1234`).
2. **Dedicated OTP HMAC Hashing**: 6-digit email OTPs use HMAC-SHA-256 with an isolated secret key (`FT_OTP_SECRET_KEY`) and constant-time verification (`hash_equals()`).
3. **Protected Document Storage**: Supporting incident documents are stored outside the public web root and served exclusively through an authorized streaming controller.
4. **Append-Only Audit Logging**: All security, screening, authentication, and administrative actions are permanently logged in `wp_ft_audit_log` with automatic secret redaction (`[REDACTED]`).
5. **Role & Capability Isolation**: Strict server-side capability checks isolate individual owners (`ft_owner`) and tenants (`ft_tenant`) from administrative functions.
6. **Data Minimization**: Payment webhooks and contact submissions strip sensitive fields before database persistence.

---

## 4. Current Implementation Status

- **Phase 0: Repository Audit and Preparation** — `COMPLETED`
- **Phase 1: Custom Plugin Foundation & Database (14 Tables)** — `COMPLETED`
- **Phase 1.1: Security Review & Remediation** — `COMPLETED`
- **Phase 2: Custom Theme, Design System & Public Website** — `COMPLETED`
- **Phase 3: Registration, Email OTP, Login and Password Recovery** — `NEXT UP`

For detailed setup instructions, testing commands, and verification procedures, refer to [SETUP.md](SETUP.md).
