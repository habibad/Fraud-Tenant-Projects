# Fraud Tenant — Final Production Readiness Report (Claude Completion Pass)

Date: 2026-11-04 · Classification legend: **VERIFIED PASS / FAIL / NOT TESTED / BLOCKED**

## 0. Bottom line
- **Development-complete for Test mode:** VERIFIED PASS, using genuine Stripe Test objects, Stripe-CLI-delivered webhooks, isolated QA and headed Chrome.
- **Live mode:** BLOCKED. No Live API keys or Live webhook secret are configured. Live Price/Product IDs were entered by the user but cannot be validated without keys.
- **Legally approved:** BLOCKED (BLK-03A–F, external).
- **Fully production-ready:** **NO.** Open gates: BLK-04, BLK-08, Live Stripe, BLK-03, HTTPS, and the inbox-delivery part of SMTP.

## 1. Environment (verified)
`C:\xampp\htdocs\fraudtenant` is the only copy (`fraud-tenant` does not exist). URL `http://localhost/fraudtenant`, DB `fraudtenant` (MariaDB 10.4.32, all `wp_ft_*` InnoDB), schema 1.7.0, PHP 8.2.12, WP 7.1.4, env `local`.
Git is present on `main`. During this session another actor staged many files, including WPvivid backup **logs**. Nothing was committed by me.

## 2. Workflow-diagram compliance (all VERIFIED PASS)
CLI suite `test_workflow_and_admin.php` 37/37 plus headed Chrome BJ-01..BJ-14:
Open website → Register → role selection (Owner/Tenant) → registration form → OTP email → invalid OTP (error, retry) → expired OTP → resend cooldown → resend → valid OTP → email verified → pending approval page → pending login held on Account Status → admin approve (via UI) / reject (with reason) → approval/rejection emails → role routing (Admin → Command Center, Owner → Owner Dashboard, Tenant → Tenant Dashboard) → invalid credentials (single generic error) → cross-role denial (302/403) → forgot password → reset OTP → set new password (no token in URL, HttpOnly cookie) → old password rejected / new accepted. OTP lockout after 5 attempts also verified.
Phase 3/4 code was preserved; only the reset-token transport changed (D9).

## 3. Stripe (Test mode: VERIFIED PASS · Live mode: BLOCKED)
| Item | Result | Evidence |
|---|---|---|
| Dual encrypted credentials, mode switch, masking | Pre-existing; preserved | Status output with redacted values |
| Test API connectivity (`/v1/balance`, livemode=false) | VERIFIED PASS | baseline + QA provisioning |
| Checkout Session ($29/mo, $290/yr, livemode, owner/plan binding) | VERIFIED PASS | CHK-01..07 |
| Reuse only after remote verification; expired never reused; retry reuses idempotency key; new attempt gets new key | VERIFIED PASS | CHK-05, 08..11 |
| Atomic DB lock — 12 independent PHP processes, fresh + expired takeover: exactly 1 winner each | VERIFIED PASS | CONC-01..04 |
| 8 concurrent real HTTP checkout requests → 1×200, 7×429, **1 session in Stripe** | VERIFIED PASS | CONC-10..13 |
| Real hosted-Checkout payment with Test card in headed Chrome → verified return → Standard active | VERIFIED PASS | BJ-17, BJ-18, screenshots 17/18 |
| Genuine webhook delivery (Stripe CLI 1.54.1 `listen --forward-to` QA) | VERIFIED PASS | SUB-04, LIFE-01/03, FAIL-02; listener log |
| Signature: wrong secret, stale ts, missing/malformed header, multi-v1, body tamper, Live event without Live secret | VERIFIED PASS | WHK-01..08 |
| Idempotent duplicates; stale-processing reclaim; 409 while in progress | VERIFIED PASS | WHK-09..11 |
| Forged/out-of-order payloads cannot change state (authoritative re-fetch) | VERIFIED PASS | WHK-06, LIFE-09 |
| Cross-owner subscription claim fails closed | VERIFIED PASS | XOWN-01..03 |
| cancel_at_period_end keeps access; deletion revokes; history preserved | VERIFIED PASS | LIFE-01..08 |
| Declined payment → incomplete → no entitlement; failure in billing history | VERIFIED PASS | FAIL-01..05 |
| Customer Portal (own Test customer only) | VERIFIED PASS | PORTAL-01..03, BJ-19 |
| Mode isolation: Live active → Test subs grant nothing | VERIFIED PASS | ISO-01..04 |
| Renewal across a real billing period | NOT TESTED | Needs Stripe test clocks; the logic is the same `sync_subscription` path |
| Live credentials, Live price validation, Live webhook | BLOCKED | No Live keys or secret; no real charge was authorized |

**Operational Test webhook secret:** treat it as **unknown provenance** (D13: the legacy test suite overwrote it with a random value; the user re-saved credentials at 08:20 UTC today). To get genuine local delivery on the operational site, run `stripe listen --forward-to http://localhost/fraudtenant/?rest_route=/fraud-tenant/v1/stripe/webhook` and paste the printed secret into Stripe Settings.
**Do not run `scratch/test_stripe_webhooks.php`**: it overwrites the configured webhook secret.

## 4. Membership enforcement (VERIFIED PASS, server-side and REST)
Free: 1 active property (2nd rejected), no incidents (REST 403 `rest_membership_required`). Standard: unlimited properties, incidents allowed. Downgrade keeps properties but blocks new ones. Tenants and admins are never paid entitlements. Unapproved owners cannot check out. Evidence: MEM-01..15, SUB-07..10, LIFE-04..07.
Note: incident submission stays globally locked by **BLK-08** (`FT_INCIDENT_TEST_MODE=false`) in the operational install. This is by design; the QA install enables it for synthetic data.

## 5. Profile & account settings (VERIFIED PASS)
Password change (current-password check, confirmation, nonce/CSRF, IDOR guard, other sessions destroyed). Verified email change (code to the new address only, hashed, single-use, wrong code rejected, uniqueness re-checked, old address notified, masked audit). Reset flow as in §2. Evidence: PWR-00..18, PWC-01..05, EML-01..14, BJ-12..14.
Not implemented: profile photo upload (no existing architecture for it; not added, to avoid new upload surface).

## 6. Dashboards
**Owner:** VERIFIED PASS for real-data membership panel (plan, limit, renewal/cancel date, failed-payment notice, Test-mode badge, payment history), verified checkout return banner, auto-open on `ft_tab=membership`, incident upgrade gate, "Manage" vs "Upgrade" pill, styled secondary buttons, WP toolbar hidden for members. Desktop/tablet/mobile screenshots captured.
**Tenant:** VERIFIED PASS for routing, isolation, settings, responsive render. Disputes/visibility workflows are covered by legacy suites 8.2/8.1.1 (green on QA).
**Not done (honest scope):** no full visual redesign. The dashboards still mix server tabs and modals. Owner overview copy still contains internal wording ("Screening Engine in Phase 7").

## 7. Admin Command Center
VERIFIED PASS: correct KPIs (D11), mode-correct MRR/active count, per-section capability map enforced **before output (true 403)**, hidden tabs, user search/role/status filters + pagination, billing-QA flag (admin-only, audited), subscription list with mode/status filters and "Sync from Stripe", webhook event log (sanitized errors), audit trail filters/pagination without payloads. Evidence: ADM-01..09, BJ-08/09/21.
Existing queue screens (Applications, Properties, Incidents, Visibility, Disputes, Plans, Stripe Settings) were preserved and linked, not rebuilt.

## 8. Security findings
| ID | Severity | Status |
|---|---|---|
| D1 `scratch/` helpers mint admin cookies / HTTP-executable test scripts | Critical | **Mitigated** (`Require all denied`, verified 403). Never deploy `scratch/` or `tests/`. |
| D9 reset token in URL / stored raw | High | Fixed + verified |
| D10 unverified email change | High | Fixed + verified |
| D2/D3/D4/D5 entitlement bypasses | High | Fixed + verified |
| AuditLogger trusts `Client-IP`/`X-Forwarded-For` (spoofable audit IP) | Low | **Open** — use REMOTE_ADDR unless behind a known proxy |
| D14 WPvivid full-site zip inside web root (Apache-denied, unencrypted) | Medium | **Open** — move it outside the web root (needs owner authorization); its logs are now staged in git |
| Static asset `?ver` caused stale CSS | Low | Bumped to 1.0.8; consider filemtime versioning |

## 9. Automated results (this pass, executed on QA)
| Suite | Result |
|---|---|
| test_billing_membership | 78/78 |
| test_checkout_concurrency | 13/13 |
| test_auth_account_settings | 38/38 |
| test_workflow_and_admin | 37/37 (2 consecutive runs) |
| Headed Chrome journeys | 22/22 (44 screenshots) |
| **Total new** | **188 checks, 0 failures** |

Legacy suites re-run against QA (not counted above):
- Clean: BLK-09 27/0, 6.1.1 15/0, 6.1 33/0, 7.1 58/0, 8.1.1 42/0, 8.2 61/0, workflow_compliance 49/0.
- 8.1: 71/0 with the operational flag value (1 environment-only difference with QA's flag).
- 6: 1 stale schema-version assertion.
- **Expected failures due to intentional fixes:** old profile suite (immediate email change) and old webhook suite (fabricated `sub_123` IDs, and it overwrites secrets).
- **Environment-bound (expect operational fixtures):** 4/5, 5.1, 5.1.1.

Prior AI totals (49/232/1,278) are not relied on.

## 10. QA isolation evidence
QA install: `C:\xampp\htdocs\fraudtenant-qa`, DB `fraudtenant_qa`, own uploads, mail captured to `C:\Users\Md Anikur Rahaman\ft_qa_mail` (outside the web root), QA-only webhook secret, code via junctions. Every harness aborts unless `DB_NAME === 'fraudtenant_qa'`.
Operational checksums (`evidence/opdb_checksum_*.txt`) changed only from **interactive browser edits by admin #1 at 08:19–08:21 UTC**: plan Live IDs saved, Stripe credentials updated, FluentSMTP test email. Users (98), subscriptions (0) and payment events (0) are unchanged; there were no further changes afterward.

## 11. Blockers (unchanged IDs)
BLK-01 Stripe: Test **resolved on QA**; Live BLOCKED. · BLK-02 SMTP: the operational FluentSMTP log shows a test email `sent` (provider accepted) at 08:21 UTC; **inbox delivery NOT TESTED**. · BLK-03A–F legal: BLOCKED. · BLK-04 `FT_MATCH_SECRET_KEY` absent: BLOCKED (fail-closed preserved). · BLK-07 operational data: unchanged. · BLK-08 at-rest/backup encryption: BLOCKED (incident submission lock preserved). · BLK-09: preserved (27/27).

## 12. Production deployment requirements
1. HTTPS; `WP_ENVIRONMENT_TYPE=production`.
2. Live keys + Live webhook endpoint secret in Stripe Settings, then a Live connection test.
3. Re-validate Live prices via the plan-mapping screen.
4. Exclude `scratch/`, `tests/`, `wpvividbackups/`; keep the deny rules.
5. Provision `FT_MATCH_SECRET_KEY` and the BLK-08 controls before enabling incidents/identity matching.
6. Confirm inbox delivery.
7. Obtain the legal determinations.
8. Fix the audit-IP finding.

## Remaining work
Live-mode verification (needs keys); renewal via Stripe test clocks; dashboard visual consolidation and copy cleanup; profile photo (if wanted); AuditLogger IP source; relocating the WPvivid archive; updating stale legacy assertions (schema version, immediate email change, fabricated webhook IDs).
