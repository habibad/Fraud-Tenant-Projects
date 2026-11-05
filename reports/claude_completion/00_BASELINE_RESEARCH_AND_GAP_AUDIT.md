# 00 — Baseline Research & Gap Audit (Claude Code)

Date: 2026-11-04 · Author: Claude Code (Opus 5.5) · Method: direct source, schema and runtime inspection. Previous AI reports were treated as claims to check, not as evidence.

## 1. Confirmed environment

| Item | Verified value | How it was checked |
|---|---|---|
| Project path | `C:\xampp\htdocs\fraudtenant` (the only copy) | `C:\xampp\htdocs\fraud-tenant` **does not exist** |
| Served URL | `http://localhost/fraudtenant` | `siteurl`/`home` options |
| Database | `fraudtenant` (MariaDB 10.4.32), prefix `wp_` | `wp-config.php`, `SELECT VERSION()` |
| Schema version | `fraud_tenant_db_version = 1.7.0` (matches `Constants::DB_VERSION`) | wp_options |
| PHP | 8.2.12 ZTS; openssl, mysqli, curl, sodium **not loaded** (openssl is used) | `php -v`, `php -m` |
| WP environment | `WP_ENVIRONMENT_TYPE = local`, `WP_DEBUG = false`, gmt_offset 0 | wp-config / options |
| Active theme | `fraud-tenant-theme` | options |
| Active plugins | fluent-smtp, fraud-tenant-core, wpvivid-backuprestore | options |
| Git | **Present** (contrary to earlier reports). `main` at `e959439` (Phase 6 closure). Phases 7–9 work (≈25 files) is **uncommitted/untracked** | `git status` |
| Storage engines | All 17 `wp_ft_*` tables InnoDB (transactions available) | information_schema |
| Stripe CLI | **Not installed** | `which stripe` |

## 2. Architecture (as built)

- Plugin `fraud-tenant-core`: PSR-4-style autoloader; ~22k LOC; `Core/Plugin` wires handlers (`admin_post_*`) and `RestServer` (8 controllers, namespace `fraud-tenant/v1`).
- Theme `fraud-tenant-theme`: marketing pages, auth pages (login/register/verify-otp/forgot/reset/account-status), front-end Owner and Tenant dashboards (`page-owner-dashboard.php` 1,646 LOC, `page-tenant-dashboard.php` 864 LOC).
- Admin: wp-admin menu "Fraud Tenant" → `AdminDashboardController` (15-tab "Command Center") plus the existing queue controllers (Applications, Properties, Incidents, Visibility, Disputes, Plans, Settings/Stripe).

## 3. Data baseline (counts only, no PII read)

Users: 7 admins, 43 `ft_owner`, 26 `ft_tenant` and several ad-hoc capability mixes (residue of earlier QA). ft tables: profiles 34, properties 128, documents 151, incidents 10, disputes 12, identities 21, audit 1,775, notifications 759, subscriptions 0, payment_events 0, screening_requests 0, associations 0.
**The operational DB already contains a lot of earlier QA residue.** Per directive it is not cleaned or modified; all new QA runs on a separate installation.

## 4. Status by area

### Completed and working (verified by reading the code; runtime checks are in later reports)
- Registration (owner/tenant forms), hashed email OTP (10-min expiry, 5 attempts, 60 s resend cooldown), approval lifecycle, role-based login routing.
- Property CRUD, private document storage (`uploads/fraud-tenant-protected`), admin verification queue.
- Identity-free screening request records; incident submission/review; tenant disputes; manual report association/visibility.
- Membership plans table with dual Test/Live price/product columns; AES-256-GCM credential storage with HKDF from salts; mode switch; Stripe REST wrapper with price-vs-plan validation; Checkout & Portal REST endpoints; atomic DB checkout lock table.
- **Stripe Test credentials are genuine.** A `GET /v1/balance` connection test succeeded with `livemode=false`. Live mode: **not configured**.

### Defects found (all reproducible from the source)

| # | Severity | Area | Defect |
|---|---|---|---|
| D1 | **Critical** | Security | `scratch/` (web root) had no access rule. View helpers call `wp_set_auth_cookie(admin)` for any request where `REMOTE_ADDR` is 127.0.0.1 (true behind any reverse proxy), and DB/credential-mutating test scripts could be run over HTTP. **Mitigated now** with `scratch/.htaccess` (`Require all denied`); verified 403. These scripts must never be deployed. |
| D2 | High | Membership | `IncidentService` incident entitlement filter defaults to `false`, so **Free owners can submit incidents**. This breaks the rule "incident reporting requires Standard". |
| D3 | High | Membership/Stripe | `get_active_subscription()` ignores `livemode`. **Test-mode subscriptions would grant real entitlements while Live mode is active.** |
| D4 | High | Webhooks | `checkout.session.completed` activates on payload trust alone. It never checks `payment_status`, never fetches the remote subscription, invents a period end (+30/365 days), **falls back to `standard_monthly`** when metadata is missing, and never cross-checks the customer against the owner. |
| D5 | High | Webhooks | `invoice.paid`, `checkout.session.async_payment_succeeded/failed` and `checkout.session.expired` are not handled. `subscription.updated` with a missing period end fabricates +30 days (should fail closed). Payment-failed sets `past_due` without Stripe's state. Updates arriving before the subscription row exists are dropped. |
| D6 | Medium | Webhooks | Events stuck in `processing` after a crash are never reclaimed. Raw exception text is stored in `error_message`. |
| D7 | Medium | Checkout lock | `$now` is site-local time (`current_time('mysql')`) but `expires_at` is UTC (`date()`). Any non-UTC site timezone makes every lock look expired, which silently disables mutual exclusion. Harmless only because gmt_offset is currently 0. |
| D8 | Medium | Checkout reuse | Session reuse does not check the session's `livemode`, price or `metadata.wp_user_id`. |
| D9 | **High** | Auth | Password-reset bearer token goes in the **URL query string** (`?token=…`) and is stored raw as a transient key. Other sessions are not destroyed after reset. |
| D10 | High | Account settings | Email change takes effect **immediately** without verifying the new address, and the old address is not notified. |
| D11 | Medium | Admin | Command Center KPIs query non-existent statuses (`pending_review` for incidents; `submitted` for disputes, whose real values are `pending`/`under_review`), so the counts are wrong. Sections do not check their own capabilities: any user with `ft_review_user_registrations` sees audit logs, subscriptions with emails, and Stripe status. Users list has a hard 50-row cap, no search, no pagination. |
| D12 | Medium | Billing QA | In production with Test mode on, checkout is limited to `manage_options` users, but `is_eligible_owner()` rejects administrators, so **nobody** can run QA checkout. |
| D13 | Medium | Evidence quality | The earlier "20/20 headed Chrome journeys" load `scratch/workflow_view_helper.php?target=…`, which renders templates directly (forcing an admin cookie). They are **rendering snapshots, not interactive journeys**. The webhook test suite **overwrote the Test webhook secret with a random value** in the operational DB, so the stored Test webhook secret is **of unknown provenance**. Treat it as non-genuine. |
| D14 | Low | Ops | A 42 MB WPvivid full-site backup (`wp-content/wpvividbackups/*.zip`) sits inside the web root. Apache `.htaccess` denies it (verified 403), but it is unencrypted and inside the public tree. Recommendation: move it outside the web root (needs user authorization, since it is the user's backup). |

### Not implemented (legitimately blocked)
- Full screening reports and identity matching: `FT_MATCH_SECRET_KEY` is **absent** (BLK-04); at-rest/backup encryption unverified (BLK-08). These stay fail-closed.
- Genuine local webhook delivery: no Stripe CLI (BLK-01 partial).
- SMTP: FluentSMTP is configured with provider `smtp`; real inbox delivery is unverified (BLK-02).
- Legal blockers BLK-03A–F: unchanged and external.

## 5. Existing test assets
`scratch/test_*.php` (≈20 suites) run against the **operational DB** and clean up with scoped deletes. They prove logic paths, not isolated QA. `FT_DOING_TESTS` disables the property quota inside those suites. Earlier totals (49/232/1,278) are **not accepted** as new evidence.

## 6. Execution sequence (being carried out now)
1. Encrypted DB backup outside the web root, then build an isolated QA installation (separate directory + DB).
2. Stripe: fix D3, D4, D5, D6, D7, D8 and D12; webhook re-fetches authoritative Stripe state; mode-scoped entitlement.
3. Membership: D2 (incident gate on by default); check the property quota and screening gates through REST.
4. Auth/settings: D9 (HttpOnly cookie-bound hashed reset authorization, session invalidation); D10 (verified email change with hashed single-use token and old-address notice).
5. Admin Command Center: D11 (correct KPIs, per-section caps, search/pagination), plus subscription/payment-event visibility.
6. Owner/Tenant dashboards: billing history, and checks that every control has a live backend.
7. Isolated automated tests (unit/integration/REST/concurrency with independent processes/signed webhook fixtures), genuine Stripe Test-mode API E2E, and headed Chrome interaction journeys on the QA install.
8. Final evidence report.
