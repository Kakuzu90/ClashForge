---
id: P1-02
title: Add user roles and account status, the staff Gates and UserPolicy, status enforcement and the gated /admin area
phase: 1
status: done
depends_on: [P1-01]
---

# Add roles, account status and the policy scaffold

## Spec refs
- Core: specs/04 §1 (roles, account status), §2 (permission matrix + two structural rules), §3 (policies, Gates, `Gate::before`, write-gating middleware), §4 "Session security" (regenerate on privilege change); specs/11 "Broken authorization / privilege escalation", "Mass assignment", "Data exposure via page props", §3 (security events: role changes, permission denials), §4 (authorization matrix test)
- Plus: specs/19 §1 (`app/Policies/` registered centrally), §4 (`routes/admin.php`, `/admin/*` staff), §7 (console commands), §8; specs/07 `users` (role/status columns, partial indexes); specs/05 §2 (Auth `UserStatusService`, Admin = controllers/pages/Gates), §3 (enums with `label()`/`color()`); specs/12 §6 (sanction effects); specs/18 §5 (`AdminLayout`)
- FR: FR-ADMIN-1 (role-gated `/admin`, own layout), FR-ADMIN-6 (no impersonation)
- Edge cases: specs/23 §7 "a sanction expires while the user is mid-session"

## Scope
- **Migration / model / factory** (07 `users`): add `role` (default `user`), `status` (default `active`), `status_reason`, `status_expires_at` (timestamptz); partial indexes `(status) WHERE status <> 'active'` and `(role) WHERE role <> 'user'` (P1-01 follow-up). `User` casts both to enums; neither is fillable (11). `UserFactory` states: `moderator()`, `admin()`, `superAdmin()`, `restricted()`, `suspended()`, `banned()`, `pendingDeletion()`.
- **Domain** (`Domain/Auth`):
  - Enums `Role` (hierarchy: `includes(Role)`, `outranks(Role)`) and `UserStatus` (`canLogIn()`, `canWrite()`, per 04 §1), each with `label()`/`color()` (05 §3).
  - Enum `StaffAbility`: one case per staff row of the 04 §2 matrix, each with its minimum role; `impersonate` has none (FR-ADMIN-6).
  - `UserStatusService::effectiveStatus(User)`: a status whose `status_expires_at` has passed counts as `active` (23 §7). Applying and lifting sanctions stays in P1-06.
  - `RoleAssignmentService::assign(User $target, Role)`: role from the enum only (11 (d)); ends the target's sessions and cycles the remember token (04 §4 privilege change); logs `auth.role_changed` to `security` (11 §3, the interim audit record; 2FA check from Phase 2). Command `platform:assign-role {username} {role}`, the only way to set `super_admin` (04 §1).
- **Policy + Gates** (`App\Providers\AuthorizationServiceProvider`, 19 §1/§8): one Gate per `StaffAbility`, including `access-admin` (moderator+, 19 §4 "staff"), `manage-roles`, `resolve-disputes` and `view-audit-log` (04 §3). Super admin gets the staff abilities through the role hierarchy, and ownership policies still apply to them (04 §3). `UserPolicy` for staff actions on a user account (warn, restrict, suspend, ban, lift, change role, hard-delete), following structural rule 1: the actor must strictly outrank the target (04 §2). Every denial is logged as `auth.permission_denied` (11 §3).
- **Status enforcement**:
  - The login check refuses `banned` accounts after the password check. A wrong password gets the usual message, so no status is exposed.
  - `EnforceAccountStatus` (web group): signs out a banned session (remember cookie included) and sends a suspended user to the notice page from every route except the notice, logout, `/settings/*` and `/notifications` (04 §1). Both use `effectiveStatus`.
  - `EnsureAccountIsActive` (04 §3 #2): `account.active` blocks suspended, banned and pending-deletion writes; `account.active:content` also blocks restricted, on content writes starting with `uploads/*`. It renders a denial page with status, reason and end date.
- **HTTP + UI**:
  - `routes/admin.php`: `auth` + `account.active` + `can:access-admin`, plus a Gate call in the controller (11). `GET /admin` renders `Admin/Dashboard` on `AdminLayout` with an empty state (P1-06 fills it).
  - Pages `Account/Suspended` (reason, end date, no appeal link until P5-02) and `Account/WriteBlocked` on `AppLayout`.
  - `auth.can.accessAdmin` in shared props (no role, 11); the header shows "Admin" for staff via `navigation.ts`.
- **Seeders**: local `DatabaseSeeder` adds one account per role.
- **Config keys**: none expected.

## Out of scope
- Applying/lifting sanctions, `user_sanctions`, `audit_logs`, `AuditLogger` (incl. the role-change audit entry), admin user list/detail, `ExpireSanctionsJob` → P1-06
- `EnsureEmailIsVerified` gating → P1-08; `EnsureHasVerifiedCocAccount` → P2-02
- Deletion request/cancel for `pending_deletion` → P1-05; 2FA requirement for staff roles → Phase 2 (FR-AUTH-10); appeal link → P5-02
- Per-resource policies (`ProfilePolicy`, `BaseLayoutPolicy`, …) → the tasks that add those models

## Acceptance criteria
- Functional: FR-ADMIN-1 (guest → login, user → 403, moderator+ → 200 on `/admin`), FR-ADMIN-6 (`impersonate` denied for every role, super admin included).
- Authorization: Gate results equal the 04 §2 matrix for every role × staff ability; no staff member acts on someone at or above their own role (04 §2 rule 1); super admin still gets `○` on ownership rows; roles change only through `RoleAssignmentService`.
- Edge cases: an expired restriction/suspension stops blocking on the next request (23 §7).
- States: `Admin/Dashboard` empty, `Account/Suspended` (with and without end date), `Account/WriteBlocked`, banned sign-in message; 375 px and desktop.

## Tests
- Feature (`assertInertia`): `/admin` per role; suspended redirect + allowed routes; write blocked per status, restricted passes `account.active` but not `account.active:content`; banned user signed out on next request, remember cookie too; banned sign-in refused only with the right password; shared `auth.can.accessAdmin` per role, no `role` key; `platform:assign-role` happy path, unknown role, sessions ended.
- Security (`tests/Security/Authorization`): role × ability matrix (11 §4); `UserPolicy` rank rule (moderator→moderator, admin→admin denied); super admin denied on another user's owned resource; mass assignment (`role`, `status`, `status_*` not fillable, not set by any existing update endpoint); `auth.role_changed` and `auth.permission_denied` logged.
- Unit: `Role` hierarchy, `UserStatus` capabilities, `StaffAbility` minimum roles, `effectiveStatus` expiry.
- Vitest: none expected.

## Notes

### Decisions
- No `Gate::before` hook. Super admin holds every staff ability through the role hierarchy (`StaffAbility::minimumRole()`), and nobody holds `impersonate`. A before hook would also override ownership policies and the rank rule, which Open question 1 keeps in force. synced → specs/04 §3, specs/11.
- Gates are registered in `App\Providers\AuthorizationServiceProvider`, together with `UserPolicy`. `App\Domain\Auth\AuthServiceProvider` already exists, and a second class with that name would be confusing. synced → specs/19 §8, specs/04 §3.
- A staff ability also needs a status that allows it. The read abilities (`access-admin`, `view-report-queue`, `view-moderation-log`, `view-audit-log`) stay open to restricted and pending-deletion staff, who may still read (04 §1). Every other staff ability needs an active account. A suspended account has none. Powers return once a timed sanction passes. The first version required an active account for everything, which the spec review flagged as a new rule. synced → specs/04 §3.
- `StaffAbility` gives every staff row of 04 §2 a Gate (kebab-case values, e.g. `suspend-user`), plus `access-admin` for moderator+. `resolve-disputes`, `manage-roles` and `view-audit-log` keep their 04 §3 names. The permission-matrix test states the 04 §2 table literally.
- Expired sanctions: `UserStatus::effective()` treats a restriction or suspension whose `status_expires_at` has passed as `active`. A ban or pending deletion never lifts from a date (spec review). `User::effectiveStatus()` and `UserStatusService::effectiveStatus()` use it, so enforcement never waits for the expiry job.
- `App\Support\Auth\HasAccountStanding` (implemented by `User`) lets `MediaPolicy` refuse uploads from accounts that may not write content. Media is an edge module and cannot call Auth. This makes the policy the check and the `account.active:content` route middleware the defence in depth (04 §3).
- A banned sign-in is refused only after the password matched (`AccountBanned`, turned into a field error in `FortifyServiceProvider`), with the user-visible reason. A wrong password gets the usual message.
- `EnforceAccountStatus` runs in the web group after `HandleInertiaRequests`. It signs out a banned session: `logout()` cycles the remember token, and the session is invalidated. It also redirects a suspended one to `/account/suspended`. JSON requests (and `uploads/*`) get 401/403 JSON.
- The notice lives at `/account/suspended` (`account.suspended`), and `account/*` is added to `inertia.ssr.except`: private pages, and the end date is formatted in the viewer's timezone (`formatDateTime`). synced → specs/19 §4, specs/06, docs/ai/rules/backend.md. The Suspension row no longer promises an appeal link before P5-02. synced → specs/12 §6.
- `Account/WriteBlocked` is rendered in place with status 403 by `EnsureAccountIsActive`, so an Inertia form submit shows it as a page. The gate lets safe methods (GET/HEAD) through, so it can sit on the whole `/admin` group. synced → specs/04 §3.
- Role changes: `RoleAssignmentService::assign()` deletes the target's `sessions` rows and cycles the remember token, and logs `auth.role_changed` (`from`, `to`, `actor`). An unchanged role is a no-op. Command: `platform:assign-role {username} {role}`. synced → specs/19 §7, specs/05 §2.
- Denials are logged as `auth.permission_denied` from a render callback in `bootstrap/app.php`, for every `AuthorizationException` turned into a 403. `EnsureAccountIsActive` also logs, with `reason: status:<value>`. `can` flags in shared props use `allows()`, which throws nothing, so page views never log.
- Both go through `App\Support\Observability\PermissionDenialLog`, capped at `platform.security_log.denials_per_minute` (20) per account (or IP) and route, so a loop on a forbidden URL cannot flood the 90-day log (security review). synced → specs/11 §3.
- Uploads: `account.active:content` on `intent` and `complete`, not on the status poll (`GET /uploads/{media}`), so a user who is restricted mid-upload can still see where it stands.
- Header: staff see an "Admin" link (`headerLinks` in `navigation.ts`, gated by `auth.can.accessAdmin`). `Admin/Dashboard` is an empty state until P1-06.
- Local seeder: `test_moderator`, `test_admin`, `test_super_admin` (password `password`), seeded only when `APP_ENV` is `local` or `testing`. A known super admin password on staging would be a takeover (security review).

### Follow-ups
- P1-03: an avatar upload is a profile write, so restricted accounts should be allowed it. `account.active:content` on `uploads/intent` blocks every collection today; relax it by collection when avatars land.
- P1-06: the `audit_logs` entry for role changes, sanction apply/lift through `UserStatusService`, `ExpireSanctionsJob`, and the moderator ≤7-day restriction cap (04 §2), which is a service rule.
- The specs/11 route-enumeration test ("a state-changing route has no authorization call") has no owner yet.
- Per-user limiters on `/admin` and `uploads.complete` (security review): the log cap removes the flooding risk. Request throttling belongs with the `global-write` limiter (04 §4), which nothing defines yet.

### Verification
- `scripts/check.sh`: all green (Pest on SQLite + Postgres, Vitest, build).
- Browser, local: the seeded moderator sees "Admin" in the header, and `/admin` shows the empty dashboard. A suspended account signing in lands on the notice with its reason and end date, at 375 px and desktop, with no console errors.
- Reviews: antislop audit-009, no findings. Spec review: 6 findings. 1–5 are fixed (status rule for staff reads, specs synced, bans no longer lift from a date, missing tests added: remember cookie, expiry unit test, suspension with no end date, allowed routes). 6 is the P1-03 follow-up above. Security review: 2 findings, both fixed (seeder env guard, denial log cap).

### Open questions
Resolved by the owner, 2026-09-30 (all as recommended):
1. `Gate::before` covers staff abilities only; ownership policies still apply to super admins, so the 04 §2 matrix holds exactly. synced → specs/04 §3, specs/11.
2. The actor must strictly outrank the target, so same-level cases escalate; super admins change only through the console command. synced → specs/04 §2 rule 1.
3. Role changes log `auth.role_changed` to `security` for now; the `audit_logs` entry comes with P1-06 and the 2FA check with Phase 2. synced → specs/11, specs/04 §4 "Two-factor", tasks/BOARD.md P1-06 row.
4. `account.active` blocks suspended, banned and pending-deletion writes; `account.active:content` also blocks restricted on content writes; profile and settings writes stay open to restricted users. synced → specs/04 §3.
5. A suspended user is redirected to the notice from every route except the notice, logout, `/settings/*` and `/notifications`; the notice shows reason and end date, no appeal link until P5-02. synced → specs/04 §1.
