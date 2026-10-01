---
id: P1-06
title: Add the Audit module, the role-change audit entry and the admin audit log viewer
phase: 1
status: done
depends_on: [P1-02]
---

# Add the audit log and its admin viewer

## Spec refs
- Core: specs/12 §9 (audit log: every privileged action, two-year retention, never edited); specs/07 `audit_logs`; specs/05 §1 modules (Audit owns `audit_logs`, public surface `AuditLogger`; Admin is Http-only), §4 "Auditing" (explicit calls in services, never observers)
- Plus: specs/04 §2 (`View audit log` admin+), §3 (read abilities stay open to restricted / pending-deletion staff); specs/11 "Broken authorization" (c), "Data exposure via page props", §3, §5; specs/18 §4 admin components, §6 Admin; specs/19 §2 (Audit is a leaf), §4 `admin.php`; specs/08 §4 (`audit_logs` never deleted)
- FR: FR-MOD-6 (entry for every staff action on another user's data), FR-ADMIN-1, FR-ADMIN-4 (audit log half); NFR-SEC-6 (actor, target, before/after, IP)
- Edge cases: specs/23 §9 "Clock skew" (timestamps from the app in UTC)

## Scope
- **Migration** (07 `audit_logs`): columns as specified, `created_at` only (no `updated_at`), the three indexes, nullable `actor_id` FK `ON DELETE SET NULL`. On Postgres a trigger rejects `UPDATE` and `DELETE` (see Open question 2).
- **Domain** (`Domain/Audit`, leaf: no other `Domain\*` reference, 19 §2):
  - `Models/AuditLog`: `$fillable` explicit; `update`/`delete` throw.
  - `Enums/AuditAction` (`role.changed` now; later tasks add theirs) with labels for the filter.
  - `Data/AuditEntryData` (actor, action, auditable type + id, before, after, context) and `Data/AuditActorData` (id or null, role, console label).
  - `Services/AuditLogger::record(AuditEntryData)`: fills `ip_hash` (`App\Support\Privacy\IpHash`), user agent (truncated) and `request_id` (`Context`) from the current request, null on the console. Times from `Date::now()` (23 §9).
  - `Queries/AuditLogQuery`: filters actor, target, action, date range; newest first, cursor-paginated; joins `users` for actor and target usernames (05 §1 read-model escape hatch) → `AuditLogEntryData`.
- **Auth**: `RoleAssignmentService::assign` writes `role.changed` (before/after role, console actor) inside its transaction. The `security` log line and its alert stay (11 §3). Sync 11 "Broken authorization" (c).
- **Policy**: `view-audit-log` Gate (exists, admin+) in the controller; route keeps `can:access-admin`.
- **HTTP + UI**: `GET /admin/audit` with a Form Request for the filters (actor username, target username, action from the enum, `from`/`to` dates). Page `Admin/AuditLog`: `AdminFilterBar`, `AdminTable` (sticky header, row skeletons that keep column widths), expandable row with `AdminDiffViewer` (before/after rendered as text) and the request id. States: empty "No entries match these filters", loading, inline error with request id (18 §6). Admin left nav (`AdminNav`, items from ability flags): Dashboard · Logs; Users joins with P1-12.
- **Config keys**: `platform.admin.per_page` (50).

## Out of scope
- User list, user detail, suspend/ban/lift, `user_sanctions`, `moderation_actions`, expiry job → P1-12
- Moderation log viewer (FR-ADMIN-4 other half) → P3-06; dashboard tiles (FR-ADMIN-5) → P1-13
- Retention past two years and monthly partitioning (07) → later; CSV export of the log

## Acceptance criteria
- Functional: FR-MOD-6 groundwork; FR-ADMIN-4 audit half: filter by actor, target, action and date.
- Authorization: admin and super admin read the log, restricted and pending-deletion admins too; moderators and users get 403; suspended staff are redirected to the notice (04 §1, §3).
- Data: entries cannot be edited or deleted through the app (or the database on Postgres); no raw IP or `ip_hash` in props.
- States: empty / loading / error; 375 px and desktop; table keyboard-reachable.

## Tests
- Feature (`assertInertia`): viewer props and each filter; pagination; `platform:assign-role` writes `role.changed` with before/after and a null actor; nav items per role.
- Security: role × status matrix on `/admin/audit`; invalid filter values rejected; XSS payload in `before`/`after` and user agent rendered escaped; props carry no `ip_hash`, no IP.
- Unit: `AuditLogger` request vs console fields; `AuditLog` update/delete throw; arch test keeps Audit a leaf.
- Vitest: `AdminDiffViewer` (added, removed, changed keys).

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended):
1. Split: P1-06 here (audit log + viewer); P1-12 "Admin users + sanctions" (user list and detail, suspend / ban / lift via `SanctionService`, `user_sanctions` + `moderation_actions`, `moderation:expire-sanctions`, suspended / banned / lifted emails, sanction audit entries) after P1-06. P1-11 still depends on P1-06 only (it needs `AuditLogger`). synced → tasks/BOARD.md.
2. Immutability: model guard plus a Postgres trigger rejecting `UPDATE` and `DELETE`. The two-year retention is later met by dropping monthly partitions (07), which row triggers do not block. The suite runs on SQLite, so a Postgres-only test covers the trigger. Sync specs/07 `audit_logs` at implement.
3. Dashboard tiles (FR-ADMIN-5) → new P1-13 "Admin dashboard v1" after P1-12: new signups, failed jobs and media storage now; open reports, disputes and API health as their modules land. synced → tasks/BOARD.md.

### Decisions and divergences (implement, 2026-10-01)
1. The append-only trigger exists on SQLite too (rejects `UPDATE` / `DELETE`), so the default suite covers it; Postgres also rejects `TRUNCATE`, tested on the Postgres run. The function is `CREATE OR REPLACE` because `migrate:fresh` keeps functions. synced → specs/07 `audit_logs`.
2. `actor_id` is `ON DELETE RESTRICT`, not `SET NULL`: the trigger would refuse the FK's update anyway, and staff who acted are anonymised (P1-11), never deleted. A null actor is the console or the scheduler, with `context.via` naming which; `actor_role` is null for them. synced → specs/07 `audit_logs`, specs/08 §2.
3. `auditable_type` stores a short subject name from `AuditSubject` (`user`), not a class name, so Audit stays a leaf and the log survives refactors. synced → specs/07 `audit_logs`.
4. The viewer pages by `id` (cursor), which follows insertion order; the `created_at` indexes serve the date filter. Indexes are ascending: Postgres scans them backwards for newest-first. synced → specs/07 `audit_logs`.
5. `RoleAssignmentService::assign` takes an `AuditActorData` instead of a string actor; the `security` line keeps `actor: console`. synced → specs/11 "Broken authorization" (c).
6. Invalid filters return to `/admin/audit` (unfiltered) with field errors; dates are whole UTC days, both ends included. A cursor must decode to the shape the query issues (`AuditLogQuery::acceptsCursor`), else it is a `cursor` field error.
7. Shared `auth.can` gains `viewAuditLog` for the admin nav (`auth.can` was already allowlisted in specs/11). `AdminLayout` renders `AdminNav` from the ability flags (the `nav` slot is gone); the current item is the longest matching link. synced → specs/18 §6.
8. New UI: `AdminTable`, `AdminFilterBar`, `AdminDiffViewer`, `AdminNav`, a `date` type on `UiInput`, and `useVisitError` (a 5xx or network failure becomes an inline alert with the `X-Request-Id`). All in `/dev/components`. R-31: admin stays plain per specs/18 §4 (body font, `--radius-sm`, no lift); the diff rows use the same left-border accent as `UiAlert`. synced → specs/18 §4 (admin components, Input variants), §6.
9. Copy: an entry without an account reads "Console" (via console) or "System"; a target that no longer exists reads "Account no longer exists".
10. The entries are a deferred `log` prop, so the first response carries only the filters and the table shows its skeleton. synced → specs/18 §6.
11. `AuditLogFilterRequest` authorizes with `view-audit-log` before validating, so a moderator with bad filters gets a 403, not field errors. synced → specs/11 "Broken authorization".
12. `AuditLogQuery` is part of Audit's public surface. synced → specs/05 §1.
13. Nothing secret goes into `before` / `after` / `context` (the viewer shows them as recorded): documented on `AuditEntryData`. synced → specs/07 `audit_logs`.

### Review fixes (verify, 2026-10-01)
- Spec + security (low): a hand-edited `cursor` caused a 500; it is now validated (Decision 6), with four Pest cases.
- Spec (low): the Deferred fallback skeleton is hidden while the inline error shows.
- Spec (low): Domain DTOs renamed to `AuditLogRecordData` and `AuditLogSliceData` (specs/19 §3).
- Spec (low): Decisions 10 and 11 added and synced.
- Security (note): the secrets rule on `AuditEntryData` (Decision 13). "Admin data access" logging (specs/11 §3) is left to P1-12, where the user detail shows email.
- antislop audit-013 #1 (R-03, approved by the owner 2026-10-01): the `AdminTable` row toggle keeps its 40 px look and gains the `.hit-target` 44×44 area.
- Flaky tests (approved by the owner 2026-10-01): every limiter test asserting an exact wait ("60 seconds" / "60 minutes") now freezes time, since under `check.sh` load a second could tick between requests: two in `SecuritySettingsTest` (P1-05), two in `AuthAttacksTest` (P1-01).
