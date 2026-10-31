# FRAUD TENANT — Changelog

All notable changes to the Fraud Tenant platform will be documented in this file, structured phase by phase.

---

## [Phase 6.1.1] — Final Capability Enforcement Verification (2026-10-31)

### Property Review vs. Verification Capability Differentiation (`AdminReviewService.php`, `AdminPortal.php`, `PropertyReviewController.php`)
- Enforced strict least-privilege separation between `REVIEW_PROPERTIES` and `VERIFY_PROPERTIES`:
  - `Capabilities::REVIEW_PROPERTIES`: Controls access to the Property Verification Queue, property review detail views, document inspection streaming, and info requests.
  - `Capabilities::VERIFY_PROPERTIES`: Strictly enforced before the final property verification action (`verify_property`).
  - Added helper `AdminReviewService::can_verify_property( int $user_id ): bool` requiring `VERIFY_PROPERTIES` or core `manage_options`.
  - Hardened `AdminReviewService::verify_property()`: A reviewer with `REVIEW_PROPERTIES` but without `VERIFY_PROPERTIES` is strictly denied with an `unauthorized` error, and the attempt is audited with `admin_property_verify_denied` in `wp_ft_audit_log`.
  - Hardened `AdminPortal::handle_property_review_action()`: Verification actions via POST require `VERIFY_PROPERTIES` (or `manage_options`), dying fail-closed with 403 and logging to audit if attempted without capability.
  - Hardened `PropertyReviewController.php`: Rendered the "Verify Property Ownership" action form only to actors possessing `VERIFY_PROPERTIES` (or `manage_options`). Reviewers with review-only capabilities see a clear informational notice.
  - Confirmed existing administrator role retains all permissions without regression.

### Property Document Streaming Permissions Scoping (`DocumentService.php`)
- Hardened `DocumentService::stream_protected_document()`:
  - Scoped property ownership document (`object_type = 'property_ownership'`) streaming strictly to property-specific capabilities (`REVIEW_PROPERTIES`, `VERIFY_PROPERTIES`, or `manage_options`).
  - Eliminated broad dependency on `REVIEW_USER_REGISTRATIONS`: user registration reviewers cannot stream property deeds.
  - Wrapped header streaming with `if ( ! headers_sent() )` to ensure clean execution across CLI testing and production web servers.
  - Maintained bidirectional cross-owner isolation, tenant blocking, and unauthenticated 403 access denial.

### Nonce CSRF Protection Architecture
- Documented and verified that WordPress nonces (`ft_view_doc_{id}`, `ft_admin_prop_review_{id}`) serve as session- and time-windowed CSRF defense mechanisms (valid 12–24 hours for the current user) rather than consumable single-use tokens.
- Server-side authorization, user capability verification, and property-to-owner linkage are independently enforced on every request regardless of nonce validity.

### Automated Verification
- Created and executed Phase 6.1.1 Capability Enforcement Suite (`scratch/test_phase6_1_1_capability_enforcement.php`): **13 / 13 assertions PASS (100%)**.
- Executed full test suite regression across all 5 suites: **159 / 159 assertions PASS (100%)**.

---
## [Phase 6.1] — Final Security & QA Reconciliation (2026-10-31)

### Document Authorization & Ownership Linkage Cross-Verification (`DocumentService.php`)
- Hardened `DocumentService::stream_protected_document()`:
  - Added ownership linkage cross-verification: when an owner requests an ownership document (`object_type = 'property_ownership'`), the service now queries `wp_ft_properties` to confirm that the property exists AND that `property->owner_id === `. Prevents cross-property desync or parameter tampering.
  - Enforced single-action CSRF nonces (`ft_stream_doc_{id}`) on document streaming requests.
  - Bidirectional verification: Owner B is blocked from Owner A documents, and Owner A is blocked from Owner B documents.
  - Confirmed tenants and unauthenticated visitors are blocked fail-closed with HTTP 403 Forbidden.
  - Audited all access denials with `document_access_denied` events.

### File Path Security & Directory Traversal Confinement (`DocumentService.php`)
- Enforced strict canonical directory separator boundary on protected storage:
  `$normalized_base = rtrim( $canonical_base, '/\' ) . DIRECTORY_SEPARATOR;`
- Guaranteed that relative traversal (`../../`), symlink escapes, and sibling-prefix directories sharing identical base names (e.g. `/fraud-tenant-protected_evil/`) cannot bypass confinement.
- Verified zero internal server filesystem path leakage in error responses and exception messages.

### Dedicated Administrative Capabilities & Least-Privilege Scoping (`Capabilities.php`, `RoleManager.php`, `AdminPortal.php`, `AdminReviewService.php`, `PropertyReviewController.php`)
- Introduced dedicated property review capabilities:
  - `Capabilities::REVIEW_PROPERTIES = 'ft_review_properties'`
  - `Capabilities::VERIFY_PROPERTIES = 'ft_verify_properties'`
- Added dedicated capabilities to `RoleManager::get_admin_caps()` and registered to the administrator role.
- Updated `AdminPortal.php` menu registration and action dispatcher to check `REVIEW_PROPERTIES` (with fallback to `manage_options`).
- Updated `PropertyReviewController.php` to require `REVIEW_PROPERTIES`.
- Added helper `AdminReviewService::can_review_property( int $user_id ): bool` and enforced across verification, rejection, and info request actions.

### Document Version History & Review Status Lifecycle (`Constants.php`, `AdminReviewService.php`)
- Added document status constant `Constants::DOC_STATUS_SUPERSEDED = 'superseded'`.
- Hardened `AdminReviewService::verify_property()`:
  - Selects only the latest document version (`ORDER BY id DESC LIMIT 1`) and marks it `approved`.
  - Automatically updates any older prior documents for that property to `superseded`, preserving immutable historical audit integrity.
- Hardened `AdminReviewService::reject_property()` to mark only the latest document as `rejected`.
- Confirmed that replacement uploads create new distinct records and that prior physical files remain intact on disk without unlinking or overwriting.

### Compliance Reverification Triggers (`PropertyService.php`)
- Added unit number modification (`$unit !== $existing->unit_number`) to compliance-critical address fields.
- Verified that changing unit numbers resets verified property status back to `pending_verification` and triggers an audit log.
- Verified that non-critical metadata updates (e.g. title) preserve verified status, and owners cannot alter verification metadata through update payloads.

### Cross-User Data Isolation Verification (`DashboardService.php`)
- Verified bidirectional isolation between Property Owner A and Property Owner B: properties, documents, updates, archives, and dashboard metrics.
- Verified bidirectional isolation between Tenant A and Tenant B: identity tokens, notifications, disputes, and audit timeline feeds.

### Production Storage & External Environment Guidelines (`BLOCKERS.md`, `SETUP.md`)
- Documented `FT_PROTECTED_STORAGE_DIR` for production deployments outside webroot.
- Documented Nginx configuration for environments where `.htaccess` is unparsed.
- Documented local Apache verification vs. production server verification.

### Automated Testing & Quality Assurance
- Created and executed Phase 6.1 Security Reconciliation Suite (`scratch/test_phase6_1_security_reconciliation.php`): **33 / 33 assertions PASS (100%)**.
- Executed full test suite regression across all 4 suites: **146 / 146 assertions PASS (100%)**:
  - `test_phase6_1_security_reconciliation.php`: 33 / 33 PASS
  - `test_phase6_property_management.php`: 45 / 45 PASS
  - `test_phase4_and_5_regression.php`: 24 / 24 PASS
  - `test_phase5_1_1_reconciliation.php`: 44 / 44 PASS
- Cumulative unique test catalog: **500 total, 495 PASS, 0 FAIL, 0 BLOCKED, 5 NOT TESTED (outbound SMTP delivery awaiting production mail server credentials)**.

---
## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 5.1] — Dashboard Hardening and Release Gate (2026-10-29)

### Tenant Record Messaging Hardening (`page-tenant-dashboard.php`, `DashboardService.php`)
- Eliminated all statements claiming or implying a guaranteed clean rental history:
  - Replaced metric card tag `"Clear Record on File"` with `"No Disclosed Incidents"`.
  - Replaced empty records panel heading `"No Negative Incident Records on File"` and text `"Your rental record with Fraud Tenant is currently clear"` with:
    - Heading: `"No Disclosable Incident Records Available"`.
    - Body: `"No disclosable incident records are currently available for your account. Please note: Available platform information may be incomplete and does not certify a completely clear rental history."`
- Hardened `DashboardService::get_tenant_dashboard_data()` to strictly require `status = 'approved' AND disclosable = 1` on incident record queries. Pending, submitted, rejected, or unapproved records are never disclosed or counted.

### Address Verification Label Corrections (`page-tenant-dashboard.php`)
- Relabeled residential address fields:
  - Current residence relabeled to `"Current Address on File"`.
  - Previous residence relabeled to `"Previous Address Reported During Registration"`.
  - Removed all misleading "verified" address claims or badges; address verification is not inferred from account approval.
  - Relabeled identity security badge to `"Identity Matching Token Active (HMAC Protected)"`.

### Phase 7 SSN Preview Immunity (`page-owner-dashboard.php`, `dashboard.js`)
- Converted the owner dashboard SSN search input to `disabled readonly aria-disabled="true"` with placeholder `"XXX-XX-XXXX (Preview — Screening Engine in Phase 7)"`.
- Converted the search action to `type="button" data-ft-modal-target="ftScreeningModal"`, triggering the informational compliance modal directly.
- Removed SSN input event listeners and form submission interceptors from `assets/js/dashboard.js`. Verified zero SSN values are accepted, processed, serialized, logged, or transmitted, and zero screening requests are generated in `wp_ft_screening_requests`.

### Secure Tenant Activity Timeline (`DashboardService.php`, `page-tenant-dashboard.php`)
- Refined audit query in `DashboardService::get_tenant_dashboard_data()`:
  - Restricted query strictly to `object_type = 'user' AND object_id = %d` where the target subject is the current tenant.
  - Restricts actions strictly to a safe user-facing allowlist: `admin_application_approved`, `auth_otp_verified`, `auth_tenant_registered`, `user_profile_updated`.
  - Queries ONLY `id, action, created_at` from `wp_ft_audit_log`, excluding `details_json`, `ip_address`, `user_agent`, and admin notes.
  - Returns human-friendly, escaped display titles.
  - When no allowlisted events exist, displays a neutral empty state (`"No recent activity recorded for your account."`) instead of hardcoded fallback events.

### Real Membership and Owner Data Scoping (`DashboardService.php`, `page-owner-dashboard.php`)
- Enforced strict `owner_id = %d` scoping on all owner queries (properties, searches, reports).
- Ensured recent searches display only masked `••••-XXXX` last 4 digits, never HMAC token.
- Verified real membership plan data (`Free Plan`, `Standard Monthly`, `Standard Annual`) with zero fabricated active benefits.

### Admin Review Historical Continuity (`UserReviewController.php`, `AdminReviewService.php`)
- Verified that repeated rejection or info-request actions preserve an append-only application-level audit history in `wp_ft_audit_log` without overwriting history.
- Enhanced the admin review history timeline in `UserReviewController.php` to cleanly display and highlight past reviewer notes and reasons.

### Testing & Screenshots
- Executed 34 Phase 5.1 hardening assertions (**34/34 PASS**).
- Executed 24 Phase 4 & 5 regression assertions (**24/24 PASS**).
- Recaptured Headless Chrome desktop (1440x1050) and mobile (390x844) screenshots reflecting hardened UI states.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 5] — Owner and Tenant Dashboards (2026-10-29)

### Separation of Shells & Dashboard Architecture (`header.php`, `footer.php`, `dashboard.css`, `dashboard.js`)
- Completely decoupled the authenticated application shell from the public marketing website: suppressed the public marketing header and oversized marketing footer on authenticated dashboard pages.
- Built sticky application top bar (`.dashboard-topbar`) featuring brand logo, role title ("Property Owner Dashboard" / "Tenant Dashboard"), notification bell with unread indicator badge, user capsule with avatar initials/name/role, and secure sign-out action.
- Built responsive mobile navigation drawer with hamburger toggle button and outside-click dismissal.
- Built minimal, compliant dashboard footer (`.auth-footer`) presenting copyright and direct legal links (Terms of Service, Privacy Policy, Tenant Rights, Support).

### Dashboard Service & Real Database Integration (`DashboardService.php`)
- Built `DashboardService` with single-responsibility queries retrieving only authentic database records with zero mock data.
- `get_owner_dashboard_data(int $owner_id)`: Queries properties from `wp_ft_properties`, screening searches from `wp_ft_screening_requests`, incident reports from `wp_ft_incident_reports`, membership subscription from `wp_ft_subscriptions` / `wp_ft_membership_plans`, unread notifications, and user profile.
- `get_tenant_dashboard_data(int $tenant_id)`: Queries disclosed incident records, disputes, unread notifications, residential addresses, and recent audit activity timeline from `wp_ft_audit_log`.
- `update_safe_profile(int $user_id, array $data)`: Safe profile field updates (phone number) protected by user-specific CSRF nonces (`ft_update_profile_<user_id>`) and IDOR ownership verification.
- Protected email, role, and SSN tokenization from silent modification.

### Strict Access Gate & Role Isolation (`DashboardService::enforce_access()`, `AuthService::login()`)
- Unauthenticated requests to `/owner-dashboard/` or `/tenant-dashboard/` strictly redirect to `/login/`.
- Unverified accounts (`email_unverified`) redirect to `/verify-otp/`.
- Accounts in review (`pending_approval`, `info_requested`) redirect to holding page `/account-status/`.
- Suspended or rejected accounts have sessions terminated and redirect to `/login/?error=status`.
- Role boundaries strictly enforced: `ft_owner` attempting to view `/tenant-dashboard/` redirects to `/owner-dashboard/`; `ft_tenant` attempting to view `/owner-dashboard/` redirects to `/tenant-dashboard/`. Administrators possess inspection privileges.
- Updated `AuthService::login()` to route approved owners to `/owner-dashboard/` and approved tenants to `/tenant-dashboard/`.
- Updated holding page `page-account-status.php` with direct CTA for approved users: "Go to Your Dashboard →".

### Property Owner Dashboard (`page-owner-dashboard.php`)
- Pixel-matched the approved design showcase (`Fraud Tenant Product UI Showcase.png`):
  - Left Navy sidebar (`#14263E`) with active teal `Dashboard` state and navigation items (Dashboard, My Properties, Search Tenants, My Reports, Membership, Notifications, Profile Settings).
  - Welcome greeting banner with owner first name and "+ Add Property" button triggering Phase 6 informational modal.
  - 4-column Stat Metrics bar: My Properties (real count + verification tag), Tenant Searches (real count), My Reports (real count), Membership ("Free Plan" badge + "Upgrade" pill).
  - "Search Tenant Rental History" box with formatted `XXX-XX-XXXX` input, lock indicator, Search button, and Phase 7 compliance modal.
  - Two-column grid: "My Properties" table displaying real street, city, state, zip, and status badge (`pending_verification` / `verified`), Property Verification Notice; "Recent Searches" table; "Upgrade Your Membership" promo card with checklist; and Account profile card.

### Tenant Dashboard (`page-tenant-dashboard.php`)
- Pixel-matched the approved design showcase:
  - Left Navy sidebar with active Dashboard, My Records, My Disputes, Notifications, Profile Settings, and "Know Your Rights" FCRA info in the footer.
  - Welcome greeting banner with tenant first name.
  - 3-column Stat Metrics bar: My Records (disclosed count or clean record status), My Disputes (real count), Notifications (account alerts).
  - Two-column grid: "Recent Activity" timeline with real audit log timestamps; "Residential Information on File" displaying current and previous addresses; "My Rental Records" with clean status state; "Your Rights Matter" card with "View My Records" CTA; and FCRA Dispute Resolution card.

### Account Reactivation Implementation (`AdminReviewService.php`, `AdminPortal.php`, `UserReviewController.php`)
- Added `AdminReviewService::reactivate_account(int $user_id, int $reviewer_id, string $reason)` enabling administrators to restore suspended accounts back to `approved` status.
- Added "Reactivate Suspended Account" form and button in `UserReviewController.php` when inspecting suspended applicants.
- Handled `reactivate` action in `AdminPortal.php` and recorded `admin.account_reactivated` in the audit log.

### Testing & Verification
- Executed 45 Step 0 security preflight assertions (**45/45 PASS**).
- Executed 32 Phase 5 functional suite assertions covering routing, role boundaries, data isolation, IDOR defense, and template integrity (**32/32 PASS**).
- Executed 35 Phase 4 regression assertions (**35/35 PASS**).
- Captured Headless Chrome screenshots across desktop (1440x900) and mobile (375x812) for both Owner and Tenant dashboards.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 4] — Administrative User Review and Approval Portal (2026-10-29)

### Administrative Portal Shell & Review Queue (`AdminPortal.php`, `UserReviewController.php`)
- Registered top-level WordPress admin menu `Fraud Tenant` with `dashicons-shield` icon, protected by capability `Capabilities::REVIEW_USER_REGISTRATIONS` (`ft_review_user_registrations`).
- Built pending applications queue table (`#ft-admin-queue`) with status filter tabs (All Active, Pending Review, Info Requested, Approved, Rejected, Suspended) featuring live database counter badges.
- Built account type filter (All, Property Owners, Tenants), real-time applicant search input (matches name, email, phone, login), pagination (20 items/page), and empty states.
- Implemented color-coded badges matching the approved Design System (Navy `#14263E`, Teal `#0E9488`, Amber `#D97706`, Red `#DC2626`).

### Detailed Applicant Review Screen (`UserReviewController::render_detail()`)
- Built two-column responsive interface presenting only real collected and stored registration data.
- **Property Owner View**: Legal name, contact details, primary rental property address (street, unit, city, state, zip), property verification badge, attached ownership proof document metadata card, and separate property ownership decision box.
- **Tenant View**: Legal name, contact details, current residential address, and optional previous rental history (street, city, state, zip).
- **Compliance Audit Timeline**: Real-time activity history displaying actor, target, timestamp, and sanitized details JSON.

### Secure Private Document Streaming (`DocumentService.php`, `AdminPortal.php`)
- Implemented authorized streaming controller `DocumentService::stream_protected_document()` invoked via `admin_post_ft_view_document`.
- Enforced reviewer capability check (`REVIEW_USER_REGISTRATIONS` or `manage_options`) on every request.
- Verified one-time action nonces (`ft_view_doc_<id>`).
- Enforced strict database lookup from `wp_ft_documents` and directory canonicalization via `realpath()`.
- Implemented path traversal trapping (`strpos($canonical_file, $canonical_base) === 0`) rejecting any relative traversal (`../`).
- Set safe security headers (`X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Expires: 0`).
- Recorded `admin.document_viewed` in `wp_ft_audit_log` without exposing physical paths or sensitive contents.

### Review Actions & Strict Property Separation (`AdminReviewService.php`)
- **Approve Application**: Transitions status to `approved`, activates platform login access, logs `admin.application_approved`, and dispatches approval email via `wp_mail()`.
- **Reject Application**: Requires mandatory meaningful rejection reason (>= 10 characters), transitions status to `rejected`, stores reason in `wp_ft_user_profiles.rejection_reason`, logs `admin.application_rejected`, and dispatches rejection email.
- **Request Additional Information**: Requires clear request explanation (>= 10 characters), transitions status to `info_requested`, stores request note in profile, keeps account restricted from dashboard and screening features, logs `admin.info_requested`, and dispatches notification email.
- **Strict Separation of Account and Property Decisions**: Approving an owner's account deliberately leaves attached primary properties in `pending_verification` status. Separate property verification action (`verify_property()` / `reject_property()`) updates `wp_ft_properties.status` (`verified` / `rejected`) and logs `admin.property_verified` without modifying user account status.

### Database Schema Version 1.2.0
- Added `Constants::STATUS_INFO_REQUESTED = 'info_requested'`.
- Updated `Constants::DB_VERSION` to `1.2.0`.
- Applied idempotent `dbDelta()` migration adding `admin_notes (text)` column to `wp_ft_user_profiles`.
- Updated `page-account-status.php` holding page to present clear instructions and reviewer request notes when an account is in `info_requested` status.

### Testing & Verification
- Executed 23 Step 0 preflight assertions covering nonces, open redirects, logout, role isolation, and lifecycle guards (**23/23 PASS**).
- Executed 35 Phase 4 integration assertions covering queue filtering, search, applicant detail fidelity, document traversal protection, state transitions, property separation, and audit logging (**35/35 PASS**).
- Captured and verified 4 Headless Chrome screenshots across desktop (1440px) and mobile (375px) for queue and review views.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 3.2] — Phase 3 Final Release-Gate Audit & Hardening (2026-10-29)

### Privacy and Terms Consent Alignment
- **`page-register.php`**:
  - Simplified Property Owner Terms of Service checkbox label to strictly represent agreement to the Terms of Service.
  - Simplified Property Owner Privacy Policy checkbox label to acknowledge the Privacy Policy, removing premature wording regarding blanket FCRA permissible purpose requirements.
  - Simplified Tenant Terms of Service and Privacy Policy checkbox labels to standard agreement and acknowledgement, removing premature FCRA dispute claims.
- **`AuthService.php`**:
  - Aligned server-side validation error messages to neutral, standard wording (`"You must agree to the Terms of Service to register."` and `"You must acknowledge the Privacy Policy to register."`).
  - Documented that screening-specific permissible purpose certification belongs to Phase 7 and requires US legal review.

### Protected Document Storage Hardening
- **`DocumentService.php`**:
  - Added support for `FT_PROTECTED_STORAGE_DIR` configuration constant, allowing production environments to place the protected document repository completely outside the web server root.
  - Retained triple-layer server protections (Apache `.htaccess` `Require all denied`, IIS `web.config`, `index.php` 403 exit).
  - Documented web server deployment requirements for Apache, Microsoft IIS, and Nginx.

### OTP Invalidation on Resend
- **`OtpService.php`**:
  - Relocated unconsumed OTP cancellation query to execute unconditionally on every code generation call, ensuring any prior unconsumed verifiers are invalidated upon resend.

### Quality Assurance & Release Gate
- Executed 32 automated integration test assertions via `scratch/run_release_gate_audit.php` covering privacy wording, OTP lifecycle, login throttling, status restrictions, role isolation, document access control, password recovery, and public regression (`Passed: 32 | Failed: 0`).
- Recommendation: **`READY FOR PHASE 4`**.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 3.1] — Phase 3 Correction: Distinct Registration Forms & Secure Document Storage (2026-10-28)

### Distinct Role-Specific Registration Experiences
- **Property Owner Registration Form (`#ft-register-owner-form`)**:
  - Full legal name (first/last), email address, phone number, password & confirmation with visibility eye toggles.
  - Primary rental property address details: street address, city, US state selection, and 5-digit ZIP code.
  - Property ownership verification document upload: supports genuine PDF, PNG, and JPEG files under 10MB.
  - Mandatory separate check-boxes for Terms of Service acceptance and Privacy Policy / FCRA permissible purpose acknowledgement.
  - Automatically provisions records across WordPress users (`ft_owner`), `wp_ft_user_profiles`, `wp_ft_properties` (status `pending_verification`), and `wp_ft_documents` (permission `private`).
- **Tenant Registration Form (`#ft-register-tenant-form`)**:
  - Full legal name (first/last), email address, phone number, password & confirmation with visibility eye toggles.
  - Current residential street address, city, US state selection, and 5-digit ZIP code.
  - Conditional previous rental history section: reveals previous street, city, state, and ZIP when opted in.
  - Strictly excludes Date of Birth collection per directives.
  - Mandatory separate check-boxes for Terms of Service and Privacy Policy agreements.
  - Automatically provisions records across WordPress users (`ft_tenant`) and `wp_ft_user_profiles`.

### Secure Document Management Pipeline (`DocumentService.php`)
- Built `FraudTenant\Services\DocumentService` located in `wp-content/plugins/fraud-tenant-core/includes/Services/DocumentService.php`.
- Established protected storage root at `wp-content/uploads/fraud-tenant-protected/`.
- Enforced multi-layered web access protection:
  - Apache `.htaccess` with `Order Deny,Allow`, `Deny from all`, `<IfModule mod_authz_core.c> Require all denied </IfModule>`, and explicit script extension execution blocking.
  - Directory index traversal prevention via `index.php` returning `HTTP 403 Forbidden`.
  - Microsoft IIS `web.config` with `<deny users="*" />`.
  - Direct HTTP verification: confirmed `curl.exe` returns `HTTP/1.1 403 Forbidden` on direct web access.
- Server-side MIME verification using PHP `finfo`, strict extension checking (`pdf`, `png`, `jpg`, `jpeg`), and 10MB size limit.
- Unguessable randomized file naming: `doc_<token>_<timestamp>.<ext>`.
- Zero public document URL exposure.

### Database Schema Version 1.1.0
- Updated `Constants::DB_VERSION` to `1.1.0`.
- Applied idempotent `dbDelta()` migration adding columns to `wp_ft_user_profiles`:
  - `prev_address_line1 (varchar(255))`
  - `prev_city (varchar(100))`
  - `prev_state (varchar(50))`
  - `prev_postal_code (varchar(20))`
  - `terms_accepted_at (datetime)`
  - `privacy_accepted_at (datetime)`

### Testing & Verification
- Executed 43 dedicated automated integration test assertions covering all field validation, role assignment, file upload constraints, protected storage, and account status transitions (`Passed: 43 | Failed: 0`).
- Captured Headless Chrome screenshots for Desktop Owner, Desktop Tenant, Mobile Owner, and Mobile Tenant registration views.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 3] — Registration, Email OTP, Login & Password Recovery (2026-10-28)

### Core Backend Services (`fraud-tenant-core`)
- **`OtpService` (`includes/Services/OtpService.php`)**:
  - Implemented cryptographically secure 6-digit numeric OTP generator using `random_int(100000, 999999)`.
  - Enforced HMAC-SHA-256 verifier hashing via `SecurityService::hash_otp()` using dedicated server secret `FT_OTP_SECRET_KEY`.
  - Built fail-closed validation when `FT_OTP_SECRET_KEY` is undefined or empty.
  - Implemented 60-second cooldown enforcement (`resend_available_at`), 10-minute challenge expiry (`expires_at`), max 5 verification attempts, constant-time `hash_equals()` verification, and single-use challenge consumption (`consumed_at`).
  - Added user lifecycle progression transitioning `approval_status` from `email_unverified` to `pending_approval` upon successful OTP confirmation.
- **`AuthService` (`includes/Auth/AuthService.php`)**:
  - Built user registration pipeline supporting `ft_owner` and `ft_tenant` roles, with default status `email_unverified` in `wp_ft_user_profiles`.
  - Added anti-enumeration response logic returning generic success message on duplicate email registration attempts without revealing existing account presence.
  - Added brute-force lockout defense enforcing a 15-minute temporary lockout after 5 consecutive failed login attempts from an IP.
  - Implemented lifecycle status authentication gateway:
    - Users with `email_unverified` redirected to `/verify-otp/`.
    - Users with `pending_approval` redirected to `/account-status/`.
    - Users with `rejected` or `suspended` status blocked with explanatory message.
    - System administrators retain immediate, unrestricted access.
  - Added password recovery request workflow with anti-enumeration protection and core WordPress reset key generation.
  - Implemented secure password reset endpoint using constant-time key validation.
- **`AuthHandler` (`includes/Auth/AuthHandler.php`)**:
  - Registered `admin_post_*` and `admin_post_nopriv_*` hooks for registration, OTP verification, OTP resend, login, logout, password recovery, and password reset.
  - Enforced cryptographic CSRF nonce verification on all POST actions.
  - Implemented session-isolated flash messaging via transients.

### Custom Theme Templates & UI (`fraud-tenant-theme`)
- **Showcase Fidelity & Auth Templates**:
  - `page-login.php`: Split auth card matching showcase mockup, left form column with email and eye toggle password inputs, right photographic column with modern suburban home visual and floating glassmorphism card ("Safer Tenancies Stronger Communities").
  - `page-register.php`: Dual role selector cards (Property Owner vs. Tenant with SVG icons and active state), first/last name, email, password & confirm with visibility toggle, terms checkbox, and full-width teal "Continue" CTA.
  - `page-verify-otp.php`: Envelope icon in teal circle, target email display, 6 individual numeric inputs with auto-advance and clipboard paste support, Verify OTP CTA, and live countdown timer toggling to "Resend Code".
  - `page-forgot-password.php`: Centered card with email input and generic dispatch confirmation.
  - `page-reset-password.php`: Secure key & login parameter verification, new password inputs with eye toggles, and update submission.
  - `page-account-status.php`: Holding page for pending/suspended/rejected accounts with status badge, 4-step progress indicator, user account details grid, and sign-out button.
- **Separation of Shells**:
  - Suppressed marketing header and 4-column footer on authentication pages, displaying a focused brand header and minimal legal footer.
- **Client-Side UX & Responsive Layouts (`assets/js/main.js`, `assets/css/main.css`)**:
  - Interactive password visibility eye toggles.
  - Dual role selection cards with synced hidden input.
  - 6-digit OTP input auto-advance, backspace navigation, numeric sanitization, and clipboard paste support.
  - 60-second live countdown timer for OTP resend.
  - Fully responsive styling verified down to 320px with zero horizontal scroll.

### Testing & Verification
- **Automated Integration Test Suite**: 30 comprehensive assertions executed via PHP CLI verifying registration, role assignment, initial unverified status, anti-enumeration, login blocking, OTP hashing, cooldowns, single-use replays, status transitions, administrator bypass, password reset keys, and password updates (`Passed: 30 | Failed: 0`).
- **Visual Capture**: Captured and verified desktop and mobile (375x812) Headless Chrome screenshots across all auth views.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 2.1] — Phase 2 Final Audit & Corrective Review (2026-10-28)

### UI Alignment & Showcase Fidelity
- **UI Match with Design Showcase (`Fraud Tenant Product UI Showcase.png`)**:
  - Re-architected `front-page.php` and `assets/css/main.css` to match the approved design showcase layout precisely:
    - Integrated photographic hero showcase composition with floating badges and an explicit `Illustrative Sample Preview` watermark.
    - Added bottom organic curved wave divider to the hero section.
    - Updated How It Works section with 4-step layout and directional transition arrows.
    - Implemented dual audience split cards for Property Owners and Tenants with dedicated card visuals (`owner-card-visual.png` and `tenant-card-visual.png`).
    - Implemented 4-column value proposition grid for "Why Choose Fraud Tenant?".
    - Built unified 3-column contact card featuring suburban residential photography (`contact-house-visual.png`), 3-input row form layout, and light teal-tinted sidebar details.
    - Updated header actions with crisp `Sign In` text link and `Get Started` teal CTA.

### Public Content & Legal Claims Audit (Task 1)
- Removed unsupported statistics and unverified claims ("Trusted by thousands" replaced with truthful architectural description; confirmed zero fabricated percentages).
- Added explicit `Illustrative Sample Preview` badge to hero applicant preview card.
- Clarified that screening, incident documentation, and disputes are planned capabilities subject to account verification and permissible purpose certification.
- Removed invented contact information (fake street address and phone numbers) in favor of genuine support channels (`support@fraudtenant.com`, Digital Helpdesk, and National US Service Area).
- Verified legal draft warnings on Privacy Policy and Terms of Service, and explicit disclaimer that platform information does not constitute formal legal counsel.

### Contact Form Privacy & Architecture Hardening (Task 2)
- Migrated contact enquiry handler to `FraudTenant\Services\ContactEnquiryHandler` inside the custom plugin `fraud-tenant-core` to ensure survival across theme switches.
- Implemented multi-field SSN pattern detection across all free-text fields (`enquiry_name`, `enquiry_subject`, `enquiry_message`).
- Enforced rejection prior to `wp_mail()` dispatch without logging sensitive candidates or echoing user input in URL parameters.
- Replaced spoofable IP header parsing with reliable `$_SERVER['REMOTE_ADDR']` for rate limiting.

### Multi-Device Responsive QA (Task 3)
- Verified and captured headless Chrome screenshots across 5 viewports:
  - 1440 × 900 (`phase2-audit-1440x900-home.png`)
  - 1024 × 768 (`phase2-audit-1024x768-home.png`)
  - 768 × 1024 (`phase2-audit-768x1024-home.png`)
  - 375 × 812 (`phase2-audit-375x812-home.png`)
  - 320 × 700 (`phase2-audit-320x700-home.png`)
  - Subpages at 1440 × 900 (`/contact/`, `/for-property-owners/`, `/for-tenants/`, `/tenant-rights/`)
- Confirmed zero horizontal overflow (`scrollWidth <= innerWidth`) and zero PHP/console errors.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 2] — Custom Theme, Design System & Public Website (2026-10-28)

### Added
- **Theme Foundation (`wp-content/themes/fraud-tenant-theme/`)**:
  - `style.css`: Theme header defining name, version 1.0.0, and CSS custom properties (Navy `#14263E`, Teal `#0E9488`, Background `#F2F5F9`, Card `#FFFFFF`, Muted Slate `#64748B`, Border `#E2E8F0`).
  - `assets/css/main.css`: Comprehensive, accessible stylesheet featuring CSS Grid/Flexbox layouts, typography, button variants, hero cards, floating badges, split audience cards, FAQ accordions, and responsive breakpoints down to 320px.
  - `assets/js/main.js`: Mobile drawer menu toggle with keyboard accessibility (ESC close, aria-expanded states), accessible accordion toggle for FAQs, and real-time form validation.
  - `header.php`: Semantic HTML5 header with SVG shield brand logo, skip-to-content accessibility link, navigation menu with fallback, public action buttons (Login, Get Started), and mobile slide-out drawer.
  - `footer.php`: 4-column footer featuring platform links, role-specific pages, transparency resources, legal drafts, copyright, and FCRA/non-affiliation disclaimer.
  - `front-page.php`: Full landing page matching `Fraud Tenant Product UI Showcase.png`:
    - Hero section with "A Safer Rental Community" floating badge and primary CTAs.
    - Hero visual showcase: "Safer Tenancies Stronger Communities" card and "Applicant Found - View Report (Locked)" preview card.
    - 4-step workflow: 1. Register -> 2. Get Verified -> 3. Access Features -> 4. Build Safer Rentals.
    - Trust & verification metrics bar.
    - Audience split cards for Property Owners and Tenants.
    - Interactive FAQ accordion section and bottom conversion CTA.
  - `page.php` & `index.php`: Standard WordPress page and template fallbacks with card wrappers.
  - `template-parts/page-header.php`: Reusable header banner for subpages.
  - `template-parts/contact-form.php`: Accessible contact form with CSRF nonce, honeypot field, and server error/success alert rendering.
  - `functions.php`: Theme setup, script/style enqueuing, fallback navigation, contact enquiry form handler (`admin_post_ft_submit_contact_enquiry`), and idempotent public page generator (`fraud_tenant_ensure_public_pages()`).
- **9 Public WordPress Pages**:
  - Home (`/`), How It Works (`/how-it-works/`), For Property Owners (`/for-property-owners/`), For Tenants (`/for-tenants/`), FAQ (`/faq/`), Contact & Support (`/contact/`), Privacy Policy (`/privacy-policy/`), Terms of Service (`/terms-of-service/`), Tenant Rights & Screening Information (`/tenant-rights/`).
- **Security & Privacy Safeguards in Public Forms**:
  - Anti-spam honeypot field (`ft_enquiry_hp`).
  - Cryptographic CSRF nonce verification (`wp_verify_nonce`).
  - Rate limiting transient cache preventing submission flood.
  - Privacy SSN pattern rejection: rejects enquiry messages containing Social Security Number patterns (`\d{3}[-\s]?\d{2}[-\s]?\d{4}`) to prevent accidental PII leakage in unencrypted emails.

### Fixed
- **Mobile Menu Visibility on Desktop**: Added base `.mobile-drawer { display: none; }` in `main.css` to prevent the drawer from appearing in normal document flow on desktop viewports.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 1.1] — Corrective Security Review and Remediation (2026-10-28)

### Security Enhancements & Hardening
- **Task 1: Strengthened OTP Hashing**:
  - Replaced unkeyed OTP hashing with **HMAC-SHA-256** using a dedicated server-side secret key: `FT_OTP_SECRET_KEY`.
  - Implemented fail-closed behavior: `SecurityService::hash_otp()` and `verify_otp()` return `false` if `FT_OTP_SECRET_KEY` is missing or invalid.
  - Enforced constant-time comparison via PHP's `hash_equals()`.
  - Isolated `FT_OTP_SECRET_KEY` from `FT_MATCH_SECRET_KEY`.
  - Updated `DiagnosticController` at `/status` to report `otp_hashing_ready: false` safely without exposing secrets.
- **Task 2: Administrator & Role Capability Verification**:
  - Verified User ID 1 (`anik`) directly: possesses all 18 custom administrative capabilities while retaining all core WordPress capabilities (`manage_options`, `install_plugins`, etc.).
  - Confirmed strict capability isolation: `ft_owner` and `ft_tenant` have zero administrative access, and `ft_tenant` has zero owner capabilities.
- **Task 3: Audit Log & Payment Event Data Minimization**:
  - Confirmed application-level append-only design: `AuditLogger` provides no `update` or `delete` methods, and no application endpoints allow users to modify or delete logs.
  - Implemented `Sanitizer::minimize_payment_payload()` to strip raw card details, customer addresses, and customer emails from payment event payloads before storage in `wp_ft_payment_events`.
  - Verified schema-level deduplication: MySQL `UNIQUE KEY event_id` strictly prevents duplicate webhook processing.
- **Test Suite**: Executed 21 dedicated security tests across all three tasks; all 21 passed.

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 1] — Custom Plugin Foundation and Database Architecture (2026-10-28)

### Added
- Created custom plugin `fraud-tenant-core` in `wp-content/plugins/fraud-tenant-core/`.
- Implemented modular object-oriented architecture:
  - `Autoloader.php`: PSR-4 compatible autoloader for the `FraudTenant\` namespace.
  - `Constants.php`: System constants, database version (`1.0.0`), table names, account states, and REST namespace (`fraud-tenant/v1`).
  - `Sanitizer.php`: Redaction helpers for sensitive keys (passwords, OTPs, SSNs, Stripe secrets) and SSN maskers (`••••-1234`).
  - `Capabilities.php`: Granular capability constants for Owners, Tenants, and Administrators.
  - `RoleManager.php`: Managed registration of custom roles `ft_owner` and `ft_tenant` with strict capability isolation.
  - `Schema.php`: Normalized schema definition for 14 custom database tables formatted for `dbDelta()`.
  - `MigrationManager.php`: Safe, version-controlled database migrations with automatic default membership tier seeding (`free`, `standard_monthly`, `standard_yearly`).
  - `AuditLogger.php`: Append-only audit logging service with automatic sensitive data redaction.
  - `SecurityService.php`: Keyed HMAC-SHA256 SSN tokenization and OTP hashing infrastructure.
  - `BaseController.php`: REST API base controller with standard response wrappers and server-side permission callbacks (`check_authenticated`, `check_admin_permission`, `check_owner_permission`, `check_tenant_permission`).
  - `DiagnosticController.php`: Public operational health endpoint (`/status`) and protected test route (`/auth-check`).
  - `RestServer.php`: REST API coordinator hooking into `rest_api_init`.
  - `Activator.php` & `Deactivator.php`: Plugin activation and non-destructive deactivation handlers.
  - `Plugin.php`: Main orchestrator hooking into WordPress lifecycle.
- Created and verified 14 custom database tables using dynamic `$wpdb->prefix`.
- Seeded default membership plans into `wp_ft_membership_plans`.
- Verified REST endpoints via HTTP:
  - `GET /wp-json/fraud-tenant/v1/status` (200 OK, zero secrets exposed)
  - `GET /wp-json/fraud-tenant/v1/auth-check` (401 Unauthorized for anonymous callers)

---

## [Phase 6] — Property Management & Secure Document Uploads (2026-10-31)

### Database Schema Version 1.3.0 (`Constants.php`, `Schema.php`, `MigrationManager.php`)
- Upgraded `Constants::DB_VERSION` to `1.3.0`.
- Added property verification status constants: `PROP_STATUS_PENDING`, `PROP_STATUS_VERIFIED`, `PROP_STATUS_REJECTED`, `PROP_STATUS_INFO_REQUESTED`, `PROP_STATUS_ARCHIVED`.
- Added document review status constants: `DOC_STATUS_PENDING`, `DOC_STATUS_APPROVED`, `DOC_STATUS_REJECTED`.
- Non-destructively added columns to `{$wpdb->prefix}ft_properties`: `admin_notes`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `archived_at`.
- Non-destructively added columns to `{$wpdb->prefix}ft_documents`: `review_status`, `admin_notes`.
- Executed `MigrationManager::migrate(true)` idempotently; schema verified in `wp_options`.

### Core Property Management Service Layer (`PropertyService.php`, `PropertyHandler.php`)
- Implemented `PropertyService::create_property()`:
  - Validates required street address, city, 2-letter state code, and postal code.
  - Sanitizes all inputs via `Sanitizer::text()` and `Sanitizer::zip()`.
  - Assigns initial status `pending_verification`.
  - Emits `owner_property_created` in append-only application-level audit log.
- Implemented `PropertyService::get_owner_properties()`:
  - Scoped strictly to authenticated `owner_id = %d`.
  - Supports filtering by active vs. archived properties.
  - Computes attached document counts and latest document status via SQL `LEFT JOIN`.
- Implemented `PropertyService::get_property()`:
  - Single-property retrieval with strict server-side `owner_id` verification to prevent IDOR vulnerabilities.
- Implemented `PropertyService::update_property()`:
  - Updates non-critical metadata (title, units count, property type) safely.
  - **Compliance Reverification Trigger**: Detects alterations to compliance-critical fields (street, city, state, zip). If a verified property has its address altered, its status automatically resets to `pending_verification`, requiring administrative re-inspection, and logs `owner_property_reverification_triggered`.
  - Strictly protects `owner_id` from modification.
  - Emits `owner_property_updated` audit log.
- Implemented `PropertyService::archive_property()` & `unarchive_property()`:
  - Non-destructive soft archival setting `status = 'archived'` and timestamping `archived_at`.
  - Excludes archived units from default active owner views.
  - Unarchival restores property to `pending_verification` verification queue.
- Implemented `PropertyHandler.php`:
  - Registers query vars (`ft_tab`, `ft_property_id`) and clean URL rewrites (`^owner-dashboard/properties/?$`, `^owner-dashboard/properties/([0-9]+)/?$`).
  - Processes secure POST mutations with CSRF nonces for adding, editing, archiving, unarchiving properties, and uploading ownership documents.

### Secure Ownership Document Upload & Protected Storage (`PropertyService.php`, `DocumentService.php`)
- Implemented `PropertyService::upload_property_document()`:
  - Strict file validation: max size 10MB (`10485760` bytes).
  - Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
  - Magic byte inspection and double-extension defense (`script.php.png` rejected).
  - File stored in protected directory with randomized, unguessable filename (`doc_{hex}_{uuid}.{ext}`).
  - Preserves original filename in database metadata.
  - Replacement uploads preserve all prior document history records without destructive deletion.
- Extended `DocumentService::stream_protected_document()`:
  - Dual authorization: Permits users with administrative review capabilities (`REVIEW_USER_REGISTRATIONS` / `manage_options`) OR the verified document owner (`(int) $doc->user_id === $requesting_user_id`).
  - Path canonicalization via `realpath()` with traversal guard (`strpos( $canonical_file, $canonical_base ) === 0`).
  - Verifies presence of `.htaccess`, `index.php`, and `web.config` barrier files.
  - Emits secure download headers: `nosniff`, `Cache-Control: private, no-store, no-cache, must-revalidate`, `Content-Disposition: attachment`.
  - Logs `owner_document_viewed`, `admin_document_viewed`, and `document_access_denied`.

### Dedicated Administrative Property Verification Portal (`AdminPortal.php`, `PropertyReviewController.php`, `AdminReviewService.php`)
- Added submenu item **Properties** under the top-level **Fraud Tenant** admin menu (`fraud-tenant-properties`).
- Implemented Property Verification Queue List:
  - Status tabs with real database counts (`All`, `Pending Verification`, `Verified`, `Action Needed`, `Rejected`, `Archived`).
  - Address keyword search and pagination.
  - Table displaying property address, owner name/email, property type, attached document count, status badge, and direct inspection action.
- Implemented Detailed Property Inspection Screen:
  - Property information card: Title, address, type, units, submission timestamp.
  - Owner details card: Full name, account email, phone, business profile, account approval status.
  - Ownership documents table: Original filename, file size, upload timestamp, review status badge, and secure streaming button.
  - Review Decision Action Form with CSRF protection:
    - **Verify**: Sets property status to `verified` and attached document status to `approved`.
    - **Reject**: Requires substantive explanation (>= 10 characters), sets property to `rejected` and document status to `rejected`.
    - **Request Info**: Requires note (>= 10 characters), sets property to `info_requested`.
  - Reviewer notes history and compliance audit trail log.
- **Decoupled Verification**: Verified that verifying, rejecting, or requesting info on a property operates completely independently of the owner's account approval (`wp_ft_user_profiles.approval_status` remains unaltered).

### Frontend Owner Dashboard Integration (`page-owner-dashboard.php`, `dashboard.css`)
- Integrated tab routing: Overview tab vs. Properties tab (`?ft_tab=properties`).
- Properties tab features:
  - Active Properties vs. Archived Properties sub-tabs.
  - Responsive table displaying property title, full address, units count, document status pill, and action buttons ("Edit & Documents", "Archive", "Restore").
  - Property Detail / Edit view (`?ft_tab=properties&property_id=<id>`):
    - Full address and configuration edit form with reverification warning notice.
    - Attached documents table with secure download links.
    - Drag-and-drop / file browser upload box for supplementary ownership proof.
- Replaced informational modal with functional "+ Add Property" modal submitting directly to `PropertyHandler`.

### Automated Testing & Visual Verification
- Created comprehensive Phase 6 test suite (`scratch/test_phase6_property_management.php`): **45 / 45 assertions PASS (100%)**.
- Executed Phases 4 & 5 regression suite (`scratch/test_phase4_and_5_regression.php`): **24 / 24 assertions PASS (100%)**.
- Executed Phase 5.1.1 reconciliation suite (`scratch/test_phase5_1_1_reconciliation.php`): **44 / 44 assertions PASS (100%)**.
- **Total verified assertions: 113 / 113 PASS (100%)**.
- Captured 11 visual QA screenshots across Desktop (1440x900), Tablet Landscape (1024x768), Tablet Portrait (768x1024), Mobile (390x844), and Small Mobile (320x700).
- Confirmed zero implementation of Phase 7 (SSN screening), Phase 8 (disputes), or Phase 9 (Stripe payments).

---

## [Phase 0] — Repository Audit and Implementation Preparation (2026-10-28)

### Added
- Initialized local Git repository tracking in `c:\xampp\htdocs\fraudtenant`.
- Configured `.gitignore` to strictly exclude sensitive files (`wp-config.php`, uploads, logs, database dumps, OS files).
- Created core documentation suite (`AGENTS.md`, `IMPLEMENTATION_PLAN.md`, `TASKS.md`, `QA_REPORT.md`, `BLOCKERS.md`, `SETUP.md`, `CHANGELOG.md`).
- Cataloged approved design assets (`Fraud Tenant Product UI Showcase.png`).
- Verified local development URL (`http://localhost/fraudtenant`) returning HTTP 200 OK.
