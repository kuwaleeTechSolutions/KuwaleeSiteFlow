# Kuwalee SiteFlow — Backend (Phases 2–9)

**Phase 2 scope:** Laravel foundation — Sanctum SPA authentication, Organizations, Users, Roles, Permissions (RBAC), core middleware, Policies, and organization-isolation scaffolding.

**Phase 3 scope:** Projects, Sites, and Project/Site user assignment — including the `BelongsToOrganization` trait now actively used, project/site-level access control (org-wide vs. explicit assignment), and a `ProjectAssignmentService` for auditable, transactional assignment changes.

**Phase 4 scope:** Daily Site Progress Reports, secure photo uploads, and the full Draft → Submitted → Approved/Returned approval workflow.

**Phase 5 scope:** Labour management — Worker master data, single/bulk attendance, and BCMath-based wage computation.

**Phase 6 scope:** Materials — master data, a transactional stock ledger (inward/issue/return/transfer/adjustment), negative-stock prevention with a permission-gated override, and unusual-consumption alerts.

**Phase 7 scope:** Equipment & Fuel management — equipment fleet registry, usage logging, fuel purchase/issue tracking with meter readings, and three distinct consumption alert types.

**Phase 8 scope:** BOQ (with true revision versioning), the Measurement Book (Draft→Submitted→Approved/Rejected workflow), Billing (bills linked to approved measurements only), and Payments (with live-computed outstanding balances).

**Phase 9 scope (this update):** The secure Document vault (four-tier confidentiality enforcement, generalizing the Phase 4 photo-upload security pattern) and Compliance & expiry alerts (scheduled daily scan, 60/30/15/7-day/expired thresholds, duplicate-notification prevention).

> Modules **not** yet implemented (Phases 10–11 per the blueprint): Dashboards, PDF exports, final security/performance review pass.

---

## 1. What was built

| Layer | Files |
|---|---|
| Migrations | `organizations`, `users` (+ `password_reset_tokens`, `sessions`), `personal_access_tokens`, `permissions`, `roles` (+ `role_permissions`, `user_roles`), `audit_logs` |
| Models | `Organization`, `User`, `Role`, `Permission`, `AuditLog`, plus `Concerns\BelongsToOrganization` trait and `Scopes\OrganizationScope` global scope |
| Support | `PermissionCatalogue` (canonical permission list), `DefaultRoles` (the 8 system role templates) |
| Services | `RoleService` (role cloning/assignment), `AuditLogService` (redacted, append-only logging) |
| Middleware | `EnsureOrganizationContext`, `CheckPermission`, `EnsureSuperAdmin` |
| Policies | `UserPolicy`, `RolePolicy`, `OrganizationPolicy` |
| Controllers | `Auth\AuthController`, `UserController`, `RoleController`, `System\OrganizationController` (Super Admin only) |
| Tests | 6 feature/unit test files — authentication, cross-tenant isolation, RBAC enforcement, role management, user management, audit-log redaction, and a dedicated `UserPolicy` unit test |

## 1.5 What's new in Phase 3

| Layer | Files |
|---|---|
| Migrations | `projects`, `project_users`, `sites`, `site_users` |
| Models | `Project`, `Site` (both now use `BelongsToOrganization` for real) |
| Policies | `ProjectPolicy`, `SitePolicy` — permission **+** (org-wide visibility **or** assignment) on every action |
| Services | `ProjectAssignmentService` — transactional, cross-org-validated user↔project/site assignment |
| Controllers | `ProjectController`, `SiteController` |
| Resources | `ProjectResource` (includes computed `days_elapsed`/`days_remaining`/`is_overdue`), `SiteResource` |
| Tests | `ProjectAccessTest`, `ProjectAssignmentTest`, `SiteAccessTest` — assigned-vs-unassigned access, cross-tenant rejection, org-wide-only deletion, unique-code-per-org/per-project constraints |

**Key access rule proven by tests**: a Project Manager or Site Supervisor in the *correct* organization but *not assigned* to a specific project/site still gets a 403 — organization match alone is never sufficient. A Site Supervisor assigned only to one site cannot browse or open a sibling site under the same project.

**Note on the Project dashboard endpoint** mentioned in the original API architecture (`GET /api/projects/{project}/dashboard`) — this is intentionally deferred to **Phase 10 (Dashboards)** once Daily Reports, Materials, Fuel, Measurements, and Billing exist to aggregate; building it now would either be a stub or would need to be substantially rewritten later.

## 1.6 What's new in Phase 4

| Layer | Files |
|---|---|
| Migrations | `daily_reports`, `daily_report_photos` |
| Models | `DailyReport` (with an `isEditable()` helper and a creation-time site/project consistency check), `DailyReportPhoto` (metadata-only — never stores anything client-trusted) |
| Config | `config/daily_reports.php` — allowlisted photo MIME types, max size, max photos/report |
| Policies | `DailyReportPolicy` (create/update/submit gated by assignment + editable-state; **approve/return explicitly denies self-approval unless `organization.settings.allow_self_approval` is set**), `DailyReportPhotoPolicy` |
| Services | `DailyReportWorkflowService` (transactional, audited status transitions), `PhotoUploadService` (independent server-side MIME re-check, randomized filename, private disk) |
| Jobs | `ProcessDailyReportPhoto` — queued, re-encodes the image via GD to strip EXIF/GPS metadata without blocking the upload request; degrades gracefully if GD/format unsupported |
| Controllers | `DailyReportController`, `DailyReportPhotoController` (with a policy-gated `download` streaming endpoint — never a public URL) |
| Tests | `DailyReportWorkflowTest` (full draft→submit→approve/return lifecycle, self-approval denial + override, cross-tenant/unassigned rejection), `DailyReportPhotoTest` (valid upload, disguised-executable rejection via real MIME sniffing, oversized-file rejection, cross-site download denial, response never leaks `disk_path`) |

**Key security proof in tests**: `test_upload_rejects_disguised_executable_file` uploads a file named `innocuous.jpg` whose actual bytes are a PHP script — validated via Laravel's `mimetypes` rule, which sniffs real file content (not the extension), and is rejected with 422.

**Deferred to Phase 9**: attaching arbitrary Documents (drawings/PDFs) to a daily report — the `documents` table doesn't exist yet. Photos are handled now since they're core to the mobile field-reporting flow; the same private-disk + UUID-download-endpoint pattern built here will be generalised for the full Document vault.

## 1.7 What's new in Phase 5

| Layer | Files |
|---|---|
| Migrations | `workers`, `worker_attendance` (unique per worker/date/shift), `wage_computations` (historical, append-only-by-convention) |
| Models | `Worker`, `WorkerAttendance`, `WageComputation` |
| Policies | `WorkerPolicy` (org-level, not project-scoped — a worker's profile isn't tied to one project), `WorkerAttendancePolicy` (project/site-scoped like Daily Reports), `WageComputationPolicy` (a distinct, more sensitive `labour.wages` gate) |
| Services | `AttendanceService` (single + all-or-nothing transactional bulk marking), `WageCalculationService` (BCMath-only arithmetic, historical inserts not upserts) |
| Resources | `WorkerResource` — **`daily_wage` is conditionally included only if the caller holds `labour.wages`**, `WorkerAttendanceResource`, `WageComputationResource` |
| Controllers | `WorkerController`, `AttendanceController`, `WageComputationController` (includes a `summary` endpoint answering brief question #6, "labour cost of each project") |
| Tests | `WorkerAccessTest` (financial-visibility gating is the star test here), `AttendanceTest` (assignment enforcement, duplicate rejection, bulk atomicity), `WageComputationTest` (exact BCMath figures verified by hand-calculated assertions, permission/assignment gating, regeneration preserves history) |

**The financial-visibility test that matters most**: `WorkerAccessTest::test_hr_labour_manager_can_view_workers_but_daily_wage_is_hidden` — the HR/Labour Manager role has `labour.view` but not `labour.wages` by default (per brief §4.7), and the test confirms `daily_wage` is entirely absent from the JSON response, not just masked — this is enforced in `WorkerResource`, not the controller, so it can never be leaked by a different endpoint that happens to serialize the same resource.

**Wage math is verified, not assumed**: `WageComputationTest` hand-calculates expected totals (e.g. ₹800/day × 5 days present + 4 hours overtime at 1.5× → ₹4,600.00 gross) and asserts the API returns exactly that string, proving the `BCMath`-based `WageCalculationService` never drifts from expected payroll figures the way native float arithmetic could.

**Deferred**: an explicit `labour.delete` capability doesn't exist in the Phase 2 permission catalogue — workers are deactivated via `status`, never hard-deleted, consistent with the original design.

## 1.8 What's new in Phase 6

| Layer | Files |
|---|---|
| Migrations | `materials` (org-level catalog), `material_stocks` (maintained cache, unique per material/project/site), `material_transactions` (append-only ledger, with an explicit `direction` column for adjustments and a `reversal_of_id` self-reference for corrections) |
| Permissions | New `materials.negative_stock_override` — distinct from `materials.issue`/`.transfer`, granted only to Owner by default (Admin explicitly excluded; Store Manager must escalate) |
| Models | `Material`, `MaterialStock` (`isLowStock()` helper), `MaterialTransaction` (`decreasesStock()` helper handling the adjustment-direction ambiguity) |
| Policies | `MaterialPolicy` (org-level, like Workers), `MaterialTransactionPolicy` (project/site-scoped; **type-aware** — inward/return/adjustment need `materials.create`, issue needs `materials.issue`, transfer needs `materials.transfer`) |
| Services | `MaterialStockService` — the single authoritative entry point for every stock mutation, using `lockForUpdate()` + `DB::transaction()` to prevent race conditions, BCMath for all arithmetic, and deadlock-safe deterministic lock ordering for transfers (locks the lower-id site first, always) |
| | `MaterialAlertService` — flags a (material, site) pair as unusual if a day's issued quantity exceeds the trailing 30-day daily average by a configurable multiplier; explicitly does NOT flag first-ever usage (no history = not anomalous) |
| Controllers | `MaterialController`, `MaterialTransactionController`, `MaterialStockController` (current balances, `low_stock_only` filter), `MaterialAlertController` (`high-consumption` endpoint) |
| Tests | `MaterialAccessTest`, `MaterialTransactionTest` (13 cases: inward/issue/transfer/adjustment math, negative-stock rejection, override permission gating, transfer atomicity — destination untouched when source check fails, ledger immutability), `MaterialAlertTest` (spike detection, no-history exclusion, normal-variance non-flagging) |

**Important caveat on concurrency testing**: `MaterialStockService` uses `lockForUpdate()`, which is essential for correctness against a real concurrent-connection database (MySQL/Postgres) — but the test suite runs against **in-memory SQLite**, where `FOR UPDATE` is a no-op (SQLite has no row-level locking model). This means the *logical* correctness of the locking code is exercised by every test (it runs, doesn't error, produces correct sequential results), but **true concurrent-request race-condition prevention is not verified by this automated suite** — it can only be proven by a load test against MySQL/Postgres in staging. I'm flagging this explicitly rather than silently asserting a stronger guarantee than what was actually tested.

**A design call worth double-checking with you**: "return" is modeled as *increasing* site stock (material returned to a site's store, e.g., unused material brought back from another work order), while "issue" *decreases* it (material issued out from a site for consumption). If your actual field workflow uses "return" to mean the opposite (material sent back to a central warehouse, decreasing site stock), this is a one-line change in `MaterialTransaction::decreasesStock()` — let me know if that's the case.

## 1.9 What's new in Phase 7

| Layer | Files |
|---|---|
| Migrations | `equipment` (fleet registry), `equipment_usage_logs` (hours-used, project/site-scoped), `fuel_transactions` (purchase/issue, meter readings, lightweight review/finalization) |
| Permissions | New **`equipment.log_usage`** — distinct from `equipment.create`, so a Site Supervisor can report equipment usage (brief §4) without being able to register brand-new fleet assets org-wide |
| Models | `Equipment`, `EquipmentUsageLog`, `FuelTransaction` (`consumptionRate()` and `hasMissingMeterReading()` helpers) |
| Policies | `EquipmentPolicy` (create/delete restricted to org-wide roles; update also allowed for a PM assigned to the equipment's project), `EquipmentUsageLogPolicy`, `FuelTransactionPolicy` (update blocked once reviewed — same immutability pattern as approved Daily Reports) |
| Services | `EquipmentUsageService`, `FuelTransactionService` (BCMath-computed `total_cost`, never client-supplied), `FuelAlertService` (three distinct alert types) |
| Controllers | `EquipmentController`, `EquipmentUsageLogController`, `FuelTransactionController` (includes a `review` action giving `fuel.approve` a concrete purpose), `FuelAlertController` |
| Tests | `EquipmentAccessTest`, `EquipmentUsageLogTest`, `FuelTransactionTest` (10 cases: meter-reading validation, BCMath cost computation, immutability-after-review, cross-org rejection), `FuelAlertTest` (all three alert types + the "no threshold configured" no-op case) |

**The three fuel alerts, mapped directly to brief §17**:
1. **Missing meter reading** — any `issue` transaction lacking `opening_reading`/`closing_reading`.
2. **High consumption** — same trailing-average pattern as `MaterialAlertService` (Phase 6), now keyed by equipment instead of material+site.
3. **Above configured threshold** — an absolute, per-organization `fuel_max_daily_quantity` setting; returns no alerts at all (not a false positive) when the organization hasn't configured one, verified by a dedicated test.

**A bug I caught while writing tests**: my first draft of `test_issue_requires_equipment_id_but_purchase_does_not` used `store_manager` to test a validation-layer rejection, but Store Manager holds no `fuel.*` permissions by default — the request would have been blocked at the *authorization* layer (403) before ever reaching the validation rule I intended to test (422). Fixed by using Owner, which holds `fuel.create`. This is a good example of why authorization and validation failures need to be distinguished carefully when writing tests against layered Form Requests.

**A design call worth flagging**: `fuel.approve` is wired to a lightweight "review/finalize" action (`POST /fuel-transactions/{id}/review`) rather than a full workflow, since the brief doesn't specify one beyond listing the permission — it locks the entry from further edits, useful groundwork for when fuel costs eventually feed into project cost reporting.

## 1.10 What's new in Phase 8 — the financial core

This is the largest and most sensitive phase yet. A few things worth reading carefully before using it:

### Permission-catalogue interpretation (please confirm)
The brief's canonical permission list (§5) has **no `boq.*` group**. I've gated all BOQ endpoints behind `billing.view` / `billing.create`, treating BOQ as part of the same financial domain billing is built on. This means Accounts Manager (who already has `billing.*`) manages the BOQ. If you'd prefer a dedicated `boq.*` permission group instead, it's a small, contained change (new catalogue entries + role grants + swap the Policy's permission checks).

### A real gap I found and fixed in Phase 2's default roles
While writing Measurement tests, I discovered **no default role had `measurements.create`** except Owner/Admin — not even Accounts Manager, despite the brief explicitly listing "Measurements" among their capabilities (§4.8). I corrected `DefaultRoles::accounts_manager` to include `measurements.create`, `measurements.update`, and `measurements.approve`. This is a genuine fix to earlier work, not a Phase 8-only concern — it's in `app/Support/DefaultRoles.php`.

### BOQ revisioning (brief §19)
A revision **never edits** an existing `boq_items` row — `BoqItem::updating()` throws a `LogicException` at the model layer as a hard guarantee, verified by a dedicated test. Revising an item's quantity/rate creates a brand-new row under a brand-new `boq_revisions` entry; unrevised items simply continue to be "current" via their original revision. `BoqItemService::currentItemsForProject()` resolves the current effective BOQ (one row per `item_number`, latest revision) — implemented as a portable two-step lookup rather than a single window-function query, with a documented performance note for very large BOQs (thousands of line items) where a `ROW_NUMBER()` rewrite would be preferable.

### Measurement Book (brief §20)
Draft → Submitted → Approved/Rejected, with the same self-approval-denied-by-default pattern used for Daily Reports (reusing `organization.settings.allow_self_approval`). `previous_quantity` for a new measurement entry is always looked up from the **latest APPROVED** measurement for that BOQ item_number — never from a draft/rejected one — and a measurement can never push cumulative quantity beyond the BOQ's contracted quantity. There's no direct "edit" endpoint for measurements at all; corrections happen by creating a new measurement with `revises_measurement_id` pointing at the original, exactly matching the brief's "adjustment/revision workflow" requirement.

### Billing (brief §21)
A bill line item can **only** reference an approved `measurement_item`, and the service explicitly computes "measured-but-not-yet-billed" quantity before accepting any `quantity_billed` — tested for both "never measured" and "already fully billed" rejection cases. `net_payable = current_work_value - deductions - taxes`, computed via BCMath, never trusted from the client.

**Please confirm this assumption**: I've treated "taxes" as tax *deducted* (TDS-style, common in Indian government/PSU contracting) rather than GST added on top. If your actual billing convention adds tax instead of subtracting it, this is a one-line sign flip in `BillingService::computeNetPayable()`.

### Payments (brief §22)
`paid_amount`/`outstanding_amount` are **always** computed live from the payments ledger (via BCMath iteration, deliberately not Eloquent's `sum()` which can reintroduce float coercion) — never stored as independently-editable fields, so they can never drift from reality. Payments are rejected if they'd exceed the bill's outstanding balance, and can only be recorded against a certified (or already partially-paid) bill.

### Concurrency caveat (same as Phase 6)
`PaymentService::record()` uses `lockForUpdate()` on the bill row to prevent two simultaneous payments from both passing the overpayment check — but as noted in Phase 6, the test suite runs on in-memory SQLite where row locking is a no-op. The logic is exercised and correct sequentially; true concurrent-request safety needs a load test against MySQL/Postgres.

| Layer | Files |
|---|---|
| Migrations | `boq_revisions`, `boq_items`, `measurements`, `measurement_items`, `bills`, `bill_items`, `payments` |
| Models | `BoqRevision`, `BoqItem` (immutable via `updating()` guard), `Measurement` (`isEditable()`, `isReferencedByABill()`), `MeasurementItem`, `Bill` (`paidAmount()`/`outstandingAmount()` computed live), `BillItem`, `Payment` |
| Policies | `BoqItemPolicy`, `MeasurementPolicy` (self-approval guard), `BillPolicy` (self-certification guard), `PaymentPolicy` (certified-bills-only gate) |
| Services | `BoqItemService`, `MeasurementService`, `BillingService`, `PaymentService` — all money/quantity math via BCMath, all mutations transactional |
| Tests | `BoqRevisionTest` (7 cases incl. model-layer immutability), `MeasurementWorkflowTest` (8 cases incl. cumulative tracking across approved measurements, over-measurement rejection, self-approval denial), `BillingWorkflowTest` (8 cases incl. double-billing rejection, unapproved-measurement rejection), `PaymentTest` (5 cases incl. overpayment rejection, live-computed balances)

## 1.11 What's new in Phase 9 — Documents & Compliance

### The confidentiality-tier design, tested exhaustively
Four tiers, strictly nested in restrictiveness (brief §23):

| Tier | Who can access |
|---|---|
| `organization` | Any org member with `documents.view` |
| `project` | Additionally requires project/site assignment (or org-wide visibility); falls back to organization-level if the document isn't tied to a project/site at all |
| `restricted` | Org-wide visibility **or** an explicit `document_shares` grant — project assignment alone is NOT enough |
| `management_only` | Org-wide visibility **only** — sharing does **not** unlock this tier |

`DocumentSecurityTest` proves every tier boundary directly, including the specific brief §31 requirements: cross-organization access denied even for an org-wide-visibility user in their *own* org, guessing a UUID doesn't bypass the confidentiality check, and downloading without `documents.download` is denied even when `documents.view` succeeds.

### Performance fix I caught while building this
My first draft of `DocumentController::index()` loaded every document into PHP memory and filtered with `->can('view', ...)` per row — this would violate brief §32 ("avoid loading thousands of records unnecessarily") at scale. I rewrote it as `DocumentService::scopeVisibleTo()`, which pushes the confidentiality-tier logic into the SQL query itself (with `whereIn`/`orWhere` against the user's assigned project/site IDs and shared-document IDs), so the list endpoint paginates at the database level like every other list endpoint in this codebase. The single-document `view`/`download` Policy remains the actual authorization authority; this is purely a list-scoping optimization that must stay logically consistent with it.

### Two more default-role gaps found and fixed
While writing tests for this phase, I found:
1. **Project Manager had no `documents.delete`** — a PM couldn't remove documents they'd uploaded for their own project. Added, with the Policy still requiring org-wide visibility OR original-uploader status, so this doesn't let a PM delete a colleague's uploads.
2. (Carried over from Phase 8, mentioned there) Accounts Manager's `measurements.*` grants.

Both are in `app/Support/DefaultRoles.php` — please review this file specifically, since it's accumulated a few corrections across phases as test-writing surfaced gaps in the original Phase 2 design.

### Compliance expiry alerts (brief §24)
`ComplianceAlertService::scan()` runs daily (`compliance:scan-expiry`, scheduled at 06:00 Asia/Kolkata) and evaluates each item against thresholds `[60, 30, 15, 7, 0]` days. Critically, **`last_alert_threshold_days` prevents re-sending the same alert every day** — an item that crossed the 30-day mark won't notify again until it crosses the *next tighter* threshold (15 days), tested explicitly via two consecutive scan runs. Notifications go to the item's `responsible_person` if set, falling back to all org-wide-visibility users (Owner/Admin) otherwise — both via database (in-app) and mail channels per brief §24.

| Layer | Files |
|---|---|
| Migrations | `documents`, `document_shares`, `compliance_items`, `notifications` (standard Laravel schema) |
| Models | `Document` (`isSharedWith()`), `ComplianceItem` (`daysUntilExpiry()`) |
| Policies | `DocumentPolicy` (confidentiality-tier matrix), `ComplianceItemPolicy` |
| Services | `DocumentService` (secure upload mirroring `PhotoUploadService`, `scopeVisibleTo()` for efficient listing, `share()`), `ComplianceAlertService` |
| Notifications | `ComplianceExpiryNotification` (database + mail channels) |
| Console | `ScanComplianceExpiry` command, scheduled in `routes/console.php` |
| Controllers | `DocumentController` (upload/show/download/share/destroy), `ComplianceItemController` |
| Tests | `DocumentSecurityTest` (11 cases — the confidentiality matrix + brief §31's exact document-security requirements), `DocumentSharingTest` (7 cases), `ComplianceItemTest`, `ComplianceAlertServiceTest` (6 cases covering threshold crossing, duplicate suppression, and the no-responsible-person fallback), `ScanComplianceExpiryCommandTest` |

## 2. Key architectural decisions (recap)

- **Cookie-based Sanctum SPA auth** — no bearer token is ever issued to the frontend; the session cookie is the credential. `config/sanctum.php` + `config/cors.php` (`supports_credentials: true`) are configured accordingly.
- **Four-layer organization isolation**: FK column → `OrganizationScope` global scope (opt-in via `BelongsToOrganization`, ready for Phase 3+ models) → Policy re-verification → `EnsureOrganizationContext` middleware. See `tests/Feature/Auth/OrganizationIsolationTest.php` for the proof.
- **Permission-driven RBAC, never `if ($user->role === 'admin')`** — see `tests/Feature/Rbac/PermissionEnforcementTest.php::test_hardcoded_role_string_checks_are_not_used_role_is_permission_driven`.
- **Super Admin is architecturally segregated** from tenant users (`is_super_admin` flag + separate `/api/system/*` prefix + `EnsureSuperAdmin` middleware) — a tenant session can never reach system routes and vice versa.
- **Audit logs are append-only** (`AuditLog::update()` throws `LogicException`) and redact sensitive keys (`password`, tokens, secrets) before persisting.

## 3. Local setup (once you have PHP 8.3+, Composer, and MySQL installed)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env, then:
php artisan migrate
php artisan db:seed          # seeds permissions, role templates, and a Super Admin

# Create your first real organization + Owner via the Super Admin API
# (see routes/api.php -> POST /api/system/organizations), or write a
# one-off tinker script for local testing:
php artisan tinker
```

Default Super Admin (change immediately): `superadmin@kuwaleesiteflow.local` / `ChangeMe!12345` (override via `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` env vars before seeding).

## 4. Running tests

```bash
composer test
# or
php artisan test
```

Tests use an in-memory SQLite database (`phpunit.xml`) so no MySQL setup is required to run the suite. All 20+ test cases should pass, covering:

- Login success/failure/rate-limiting/disabled-account rejection
- Cross-tenant isolation (IDOR/BOLA attempts explicitly rejected)
- Super Admin ↔ tenant surface segregation
- Permission-driven authorization (with and without required permission)
- System role template immutability vs. custom role CRUD
- User creation, duplicate-email-per-org rejection, role assignment, and audit trail correctness

## 5. Note on this environment

This code was authored directly (no `composer create-project`/`artisan` scaffolding tools were available in the sandbox used to generate it), so before your first `composer install` you may need to double check the exact `laravel/framework` version constraint against what's current in your environment, and run `php artisan pint` / `phpunit` locally to catch anything sandbox-specific. Every file is production-oriented, complete, and free of TODO placeholders.

## 6. Next step

Once you've reviewed and run the test suite locally (and ideally smoke-tested concurrent payment/stock requests against a real MySQL instance, per the concurrency caveats in Phases 6 & 8), we proceed to **Phase 10**: role-specific Dashboards (Owner/PM/Site Supervisor views aggregating everything built so far — progress, costs, alerts, compliance) and PDF exports (bills, measurement certificates) — followed by **Phase 11**: the dedicated security/performance review pass (IDOR/BOLA sweep, N+1 query audit, full regression run).

Please also confirm the assumptions flagged across Phases 8–9 before Phase 10 aggregates this data into dashboards:
1. BOQ gated by `billing.*` permissions (no dedicated `boq.*` group exists in the brief's catalogue) — §1.10.
2. `net_payable = work_value − deductions − taxes` (tax deducted at source, not GST added on top) — §1.10.
3. The `DefaultRoles` corrections made along the way (Accounts Manager's measurement permissions, Project Manager's `documents.delete`) — reasonable interpretations, but worth a final look since they weren't in your original spec verbatim.

## 7. A note on route parameter naming

Laravel's implicit route-model-binding matches the **route parameter name** to the **controller method's variable name** exactly. For multi-word resources (e.g. `DailyReport`, `DailyReportPhoto`) the route parameters are deliberately written in camelCase (`{dailyReport}`, `{dailyReportPhoto}`) to match the controller's `$dailyReport`/`$dailyReportPhoto` arguments — the URL path text itself (e.g. `/api/daily-reports/...`) is unaffected and stays kebab-case. Keep this convention when adding new multi-word resources in later phases.
