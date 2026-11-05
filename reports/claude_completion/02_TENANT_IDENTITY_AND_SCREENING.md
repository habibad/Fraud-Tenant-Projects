# Tenant Identity on Incidents & Owner Screening (Schema 1.8.0)

Date: 2026-11-04 · Status: **VERIFIED PASS on the isolated QA install** · Live site: code deployed, **activation pending owner steps** (below).

## The problem this solves
Before this change an incident report recorded only the property; it never identified the tenant (`tenant_identity_id` always NULL). Owner "screening" stored a request and always returned `match_found = 0`. Reports therefore could not follow a tenant, and other owners could never see them.

## How it works now
1. **Owner files an incident and names the tenant**: legal first/last name and SSN (from the rental application), plus an identity-source attestation.
   - Stored: HMAC-SHA256 SSN token (`FT_MATCH_SECRET_KEY`), last 4 digits, and the name encrypted with AES-256-GCM.
   - The full SSN is never stored or logged (verified by scanning the incident, audit, screening, notification and option tables).
2. **Admin reviews.** The review screen shows "Reported Tenant: name · SSN ending NNNN". An administrator approves or rejects as before.
3. **Admin releases** an *approved* report to owner screening (new "Owner Screening Disclosure" card).
   - Nothing is ever released automatically.
   - Withdrawal hides the report immediately, including from saved results.
   - Rejected reports never appear, even if the flag is forced in the database.
4. **Another owner searches**, using a verified property, a permissible purpose and the applicant's written consent, with name + SSN.
   - A record matches only if **both** the SSN token and the normalized last name match.
   - **Free:** "N reviewed records found" plus an upgrade prompt.
   - **Standard:** category, month, amount, city/state, dispute state and the reviewed narrative.
   - Never shown: the filing owner, the street address, evidence files, or the SSN.
   - The URL carries only a request ID.
5. **Tenant transparency.** A tenant account linked to a matched report is notified of the inquiry. Open disputes are shown to searching owners.
6. **Gates.** Owner search runs only when all four are true (status shown in Admin → Platform Settings → Compliance Gates):
   - match key (BLK-04);
   - field encryption available;
   - data gate open (BLK-08 attestation, or synthetic test mode);
   - BLK-03 legal attestation.

   Attestations are audited administrator statements; the platform does not certify legal compliance.

Also added: an **hourly Stripe reconciliation job** that re-verifies active subscriptions not synced for 6 hours or more. It closes the "missed cancellation webhook keeps access on" gap.

## Evidence (QA)
- `tests/claude/test_identity_screening.php`: **48/48**; repeated runs pass (unique synthetic SSN per run).
- Headed Chrome `browser/run_identity_journeys.mjs`: **7/7** (file → review → approve → release → Standard search → Free search → no-match → gate panel). Screenshots in `screenshots/identity/`.
- Regression on current code:
  - billing 83/83 (incl. reconciliation);
  - concurrency 13/13;
  - auth/settings 38/38;
  - workflow/admin 37/37;
  - legacy 6.1.1, 6.1, 8.1.1 and workflow_compliance all green; 8.2 is 61/61 after the harness load fix.
- Expected legacy differences (caused by the requested change): BLK-09 2.x and P71-7.5/7.6 assert the match key is *absent*; P81-2.2 is a QA-flag difference.
- Bug found and fixed during this work: a bulk edit had stripped namespace separators in 40 BLK-08 gate calls. All are fixed, and a static class-reference check (38 references, 0 problems) now guards against it.

## Activating on the live site (owner steps — not done by Claude)
The safety policy blocked Claude from writing the live secret key; this must be done by the owner.
1. **Back up first** (done at 18:10 local: `ft_backups/fraudtenant_20261104_181037.sql.enc`, restore-verified).
2. Generate a key and add it to `C:\xampp\htdocs\fraudtenant\wp-config.php` above "That's all, stop editing":
   ```
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   define( 'FT_MATCH_SECRET_KEY', '<the 64-character value>' );
   ```
   Keep it secret, never commit it, and never change it later (changing it makes every stored tenant token unmatchable). Production needs its **own** key, held in a secret manager.
3. Open wp-admin once. The plugin migrates the schema to 1.8.0 automatically: additive columns only, no data removed.
4. Admin → Fraud Tenant → Dashboard → **Platform Settings → Compliance Gates**:
   - record the **BLK-08** attestation (database/disk + backup encryption confirmed);
   - then record the **BLK-03** attestation (written legal approval reference).

   Only record what is actually true. Each attestation is logged under your account.
5. Reports filed **before** this change have no tenant identity. They can still be linked manually under Report Visibility, but they cannot be matched in screening.
