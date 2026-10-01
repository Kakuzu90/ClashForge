---
id: P1-12
title: Add the admin user list and user detail pages
phase: 1
status: done
depends_on: [P1-06]
---

# Add the admin user list and user detail

## Spec refs
- Core: specs/12 §4 (author context: account age, verified accounts, status, prior sanctions), §9 (audit trail); specs/04 §1 (status table), §2 (staff matrix), §3 ("staff see hidden accounts through the admin user detail", read abilities, IDOR); specs/07 `users`
- Plus: specs/11 "Data exposure via page props", §3 ("admin data access" is a security event), §5 (email only to the owner and admins, IPs hashed); specs/18 §4 admin components, §6 Admin (states); specs/05 §1 (Admin is Http-only over modules' public surfaces); specs/19 §4 `admin.php`
- FR: FR-ADMIN-2 (manage users, the read half), FR-ADMIN-6 (support from data, no impersonation), FR-MOD-6 (the account's audit trail)
- Edge cases: specs/23 §1 "Username released and re-registered" (detail keyed by ULID, not username); §7 "A sanction expires while the user is mid-session" (show the effective status)

## Scope
- **Domain** (`Domain/Auth`):
  - `Queries/AdminUserQuery`: `page(filters, perPage, cursor)` newest first, cursor-paginated; filters: `q` (username prefix, or an exact email), role, effective status. Banned, pending-deletion and soft-deleted accounts included (04 §3). → `AdminUserRowData`.
  - `detail(ulid)` → `AdminUserDetailData`: username, ULID, email + verified, role, effective status with reason and end, joined, last sign-in, verified accounts count, active session count. Unknown ULID 404s. No password, remember token, 2FA or IP fields.
  - `Domain/Audit`: `AuditLogFilterData` gains a subject id, so the detail lists the latest entries about this account.
- **Policy**: a `view-users` staff ability (admin+, read: open to restricted and pending-deletion admins; Open question 2), checked in the Form Request and the controller.
- **HTTP + UI**:
  - `GET /admin/users` (`AdminUserFilterRequest`): page `Admin/Users/Index` with `AdminFilterBar` (search, role, status) and `AdminTable` (username, email, role, status, joined, last sign-in); rows link to the detail; deferred rows, empty "No accounts match", inline error with request id.
  - `GET /admin/users/{ulid}`: page `Admin/Users/Show`: detail panel (definition list), status block (reason, end date), new `AdminAuditTrailList` (latest 10 entries about the account, link to `/admin/audit?target=`). The sanction history and action panel join with P1-14.
  - Avatar and display name via Users' `ProfileReadModel`; the controller composes Auth, Users and Audit public surfaces.
  - `adminNav` gains Users (`can: viewUsers`); shared `auth.can.viewUsers`.
- **Logging**: opening a detail page writes `admin.user_viewed` (actor, target ULID) to the `security` channel (11 §3; see Open question 3).
- **Config keys**: reuses `platform.admin.per_page`; `platform.admin.audit_trail_limit` (10).

## Out of scope
- Suspend / ban / lift, `user_sanctions`, `moderation_actions`, expiry job, sanction emails → P1-14
- Role changes from the web (console only, 04 §1); hard delete; CoC accounts, bases and content tabs (later phases); CSV export

## Acceptance criteria
- Functional: FR-ADMIN-2 (users, read): find an account by username prefix or email; open its detail with status, role and audit trail.
- Authorization: admins and super admins only; moderators and users 403; suspended staff to the notice; detail by ULID only.
- Data: email shown to admins only; no password, token, 2FA or IP data in props; effective status shown (an expired suspension reads active).
- States: list empty / loading / error; detail 404; 375 px and desktop.

## Tests
- Feature (`assertInertia`): list props and each filter; cursor paging; detail props; audit trail on the detail; nav item per role; query budget ≤ 25 on both pages.
- Security: role × status matrix on both routes; unknown and malformed ULID 404; props exposure (no password, remember token, `ip_hash`, `last_login_ip_hash`); filter validation; `admin.user_viewed` logged once per view.
- Unit: effective-status filter (expired sanction counts as active).
- Vitest: `AdminAuditTrailList`.

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended):
1. Split: P1-12 here (read side); new P1-14 "Sanctions: suspend / ban / lift" (`user_sanctions` + `moderation_actions` in `Domain/Moderation`, `SanctionService` setting the status through Auth's `UserStatusService` in the same transaction so it applies on the next request, `SanctionApplied` / `SanctionLifted` after commit, `moderation:expire-sanctions`, suspended / banned / lifted emails, audit entries, sanction history and action panel on the detail) after P1-12. synced → tasks/BOARD.md.
2. A new `view-users` staff ability, admin+, read-only (open to restricted and pending-deletion admins), added to the specs/04 §2 matrix and the matrix test at implement. Moderators do not see the Users section.
3. Detail views write `admin.user_viewed` (actor, target ULID) to the `security` channel; `audit_logs` stays for privileged actions. Sync specs/11 §3 at implement.

### Decisions and divergences (implement, 2026-10-01)
1. `view-users` is `App\Domain\Auth\Enums\StaffAbility::ViewUsers`, admin+, read-only; the matrix test gains its row. synced → specs/04 §2 (new row), §3 (read abilities list).
2. The detail drops "verified accounts count": `users.verified_accounts_count` arrives with Phase 2 (P2-02), which adds it to the detail. No spec change (07 already lists the column).
3. Search: with an `@`, an exact email; otherwise a username prefix with `%`, `_` and `\` escaped (`_` is common in usernames). Both ignore case through the column (citext / NOCASE). No index serves the prefix match yet; fine at MVP size. synced → specs/11 "Injection" (the one raw `LIKE ? ESCAPE`).
4. The status filter is the effective status, mirroring `UserStatus::effective()` in SQL (specs/23 §7). A passed sanction also hides its reason and end date on the detail.
5. Detail by ULID (`whereUlid`, case-insensitive); unknown and malformed ULIDs 404 before anything is logged. Soft-deleted accounts are listed and open, marked "Deleted".
6. Pagination by `id` (sign-up order) with the shared `App\Support\Pagination\CursorShape` check, now also used by `AuditLogQuery`.
7. `admin.user_viewed` carries actor ULID, target ULID and `ip_hash`. synced → specs/11 §3.
8. `SessionService::liveCount` gives "Signed in now" (rows inside the idle lifetime). `ProfileReadModel::displayNameOf` tolerates accounts without a profile row.
9. New UI: `AdminAuditTrailList` (latest entries with who acted and the diff), pages `Admin/Users/Index` and `Admin/Users/Show`; Users in `adminNav` between Dashboard and Logs (specs/18 §6 order); shared `auth.can.viewUsers`. Status pills reuse `UiPill` tones (success / warning / danger / neutral) with the label as text. R-31: same plain admin register as P1-06. synced → specs/18 §4 (built admin components). `AdminUserQuery` joins Auth's public surface: synced → specs/05 §1.
10. The detail is not deferred: one account and ten entries stay well inside the query budget, so it renders in one response.
11. Admin list loads (user list and audit log, deferred rows included) share a named `admin-search` limiter, 60 / min per staff member (`platform.rate_limits.admin_search_per_minute`). A GET cannot redirect back on a breach (the previous URL is throttled too), so it is a bare 429 that `useVisitError` shows as a "wait a moment" warning. synced → specs/04 §4, specs/18 §6.
12. Free-text filters (user search, audit actor / account) reject invalid UTF-8 and NUL bytes (`App\Support\Rules\Utf8Text`); Laravel's `string` rule lets them through and Postgres raises. synced → specs/11 "Injection".
14. Each load of the user list rows writes `admin.users_listed` (actor ULID, whether a search was used, role and status filters, whether paged, row count, `ip_hash`; never the search text, which may be an email). Logged in the deferred callback, so the page shell does not count. Added by the owner after review, 2026-10-01. synced → specs/11 §3.
15. The list and detail never show the viewer's own account or any super admin (owner decision after review, 2026-10-01): both 404 on the detail, without a view log line; `super_admin` is not a role filter option. synced → specs/04 §3.
13. The detail's audit trail uses a leaner `AuditTrailEntryData` (no context, user agent or request id), since the trail does not show them.

### Review fixes (verify, 2026-10-01)
- Spec (low): `SessionService` docblock put back on `pastAbsoluteLifetime`.
- Spec (low): boundary test for the effective-status filter (`tests/Feature/Auth/AdminUserQueryTest.php`, every row checked against `UserStatus::effective()`; a Feature test because it needs the database).
- Spec (low): access matrix extended: suspended admin and moderator on both routes, banned admin signed out on both, restricted moderator refused.
- Spec + security (low): props-exposure test now includes audit rows; the trail DTO is leaner (Decision 13).
- Spec (low): Vitest for the Users nav item and the Dashboard · Users · Logs order.
- Security (low): `admin-search` limiter on both admin lists (Decision 11).
- Security (low): invalid UTF-8 / NUL in a filter was a 500 on Postgres; now a field error, also on the audit log filters (Decision 12).
- antislop audit-014: no findings.
