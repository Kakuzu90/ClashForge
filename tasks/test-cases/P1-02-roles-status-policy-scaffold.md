# P1-02 Roles, account status and the policy scaffold: test cases

Source: tasks/phase-1/P1-02-roles-status-policy-scaffold.md · specs/04 §1–§4, specs/11 §3–§4, specs/23 §7, FR-ADMIN-1, FR-ADMIN-6

Notes for the tester:
- Statuses the admin panel cannot set (restricted, pending deletion, a suspension with no end date, a
  reason-less ban) are set in Adminer on the `users` row: `status`, `status_reason`,
  `status_expires_at` (timestamptz, `NULL` = no end). Status is re-read on every request, so a change
  applies on the next page load without signing out.
- The `security` log is JSON, one line per event:
  `docker compose exec app sh -c 'tail -n 20 storage/logs/security-$(date +%F).log'`.
- Inertia props: DevTools → Network → click any in-app link → the XHR response (JSON) → `props`.

## Happy path

### TC-P1-02-001: Seeder creates one account per role
- Priority: High · Type: Functional
- Ref: task Scope "Seeders"; Decisions "Local seeder"
- Preconditions: none
- Steps:
  1. Run `docker compose exec app php artisan migrate:fresh --seed`.
  2. In Adminer open table `users`, show columns `username`, `role`, `status`.
- Expected: `test_user` = `user`, `test_moderator` = `moderator`, `test_admin` = `admin`, `test_super_admin` = `super_admin`; all four have `status` = `active`, `status_reason` and `status_expires_at` NULL.

### TC-P1-02-002: Moderator sees the Admin link and opens the admin area
- Priority: High · Type: Functional
- Ref: FR-ADMIN-1; task Scope "HTTP + UI"
- Preconditions: signed in as `test_moderator`
- Steps:
  1. Look at the top bar, left of the avatar menu.
  2. Click "Admin".
- Expected: an "Admin" link is shown. It opens `/admin` (HTTP 200) on the admin layout with the heading "Dashboard". The admin nav shows only "Dashboard" (no "Users", no "Logs"). There are no sign-up, failed-jobs or media-storage panels; the page shows "Nothing to review yet".

### TC-P1-02-003: Admin and super admin open the admin area with the full nav
- Priority: High · Type: Functional
- Ref: FR-ADMIN-1; specs/04 §2
- Preconditions: none
- Steps:
  1. Sign in as `test_admin`, click "Admin".
  2. Sign out, sign in as `test_super_admin`, click "Admin".
- Expected: for both, `/admin` loads with the admin nav "Dashboard", "Users", "Logs", and the panels "New sign-ups", "Failed jobs" and "Media storage" load.

### TC-P1-02-004: Guest is sent to sign in, then back to /admin
- Priority: High · Type: Authorization
- Ref: FR-ADMIN-1
- Preconditions: signed out
- Steps:
  1. Open http://localhost:8080/admin.
  2. Sign in as `test_moderator` / `password`.
- Expected: step 1 redirects to `/login`. After sign-in the browser lands on `/admin` (intended URL).

### TC-P1-02-005: `platform:assign-role` promotes a user and ends their sessions
- Priority: High · Type: Functional
- Ref: task Scope "RoleAssignmentService"; Decisions "Role changes"; specs/04 §4
- Preconditions: `test_user` signed in on browser A (with "Keep me signed in" ticked) and on `/settings/profile`; a second browser B signed in as `test_admin`
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_user moderator`.
  2. In browser A, reload the page.
  3. In browser A, sign in again as `test_user`.
  4. In browser B open `/admin/audit`.
  5. Check the security log.
- Expected: the command prints "Role set to moderator; the account's sessions were ended.". Step 2 lands on `/login` (session ended, remember cookie no longer signs in). After step 3 the header shows "Admin". The audit log has a "Role changed" entry for `test_user` from `user` to `moderator`, actor "Console". The security log has `auth.role_changed` with `from: user`, `to: moderator`. Adminer: `sessions` rows of `test_user` from before step 1 are gone.

### TC-P1-02-006: Re-running `platform:assign-role` with the same role changes nothing
- Priority: Medium · Type: Edge case
- Ref: Decisions "An unchanged role is a no-op"
- Preconditions: TC-P1-02-005 done (`test_user` is moderator and signed in)
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_user moderator`.
  2. Reload the signed-in `test_user` browser.
- Expected: output "Already moderator; nothing changed.". `test_user` stays signed in. No new "Role changed" entry in `/admin/audit`. Clean-up: run the command with `user`.

### TC-P1-02-007: `platform:assign-role` is the way to grant super admin
- Priority: Medium · Type: Functional
- Ref: specs/04 §1 (super_admin only via console)
- Preconditions: `test_admin` exists
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_admin super_admin`.
  2. Sign in as `test_admin`.
  3. Run `docker compose exec app php artisan platform:assign-role test_admin admin` to restore.
- Expected: step 1 prints "Role set to super_admin; the account's sessions were ended."; Adminer `users.role` = `super_admin`. Step 3 restores `admin`. No screen in the app offers a role picker (check `/admin/users/{ulid}` of any account and `/settings/profile`).

## Validation

### TC-P1-02-008: `platform:assign-role` with an unknown role
- Priority: Medium · Type: Validation
- Ref: Tests "unknown role"
- Preconditions: none
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_user owner`.
- Expected: error "Unknown role. Use one of: user, moderator, admin, super_admin." Exit code 2 (`echo $?`). `test_user.role` unchanged.

### TC-P1-02-009: `platform:assign-role` with an unknown username
- Priority: Low · Type: Validation
- Ref: AssignRoleCommand
- Preconditions: none
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role nobody_here admin`.
- Expected: error "No account with that username." Exit code 1. No row changes.

## Authorization / account status

### TC-P1-02-010: Regular user gets 403 on /admin and sees no Admin link
- Priority: High · Type: Authorization
- Ref: FR-ADMIN-1
- Preconditions: signed in as `test_user`
- Steps:
  1. Check the header for an "Admin" link.
  2. Type http://localhost:8080/admin in the address bar.
- Expected: no "Admin" link. `/admin` returns HTTP 403 with the error page "403 | This action is unauthorized.". The security log gets an `auth.permission_denied` line for `test_user`.

### TC-P1-02-011: Moderator is denied admin-only pages
- Priority: High · Type: Authorization
- Ref: specs/04 §2 matrix (view users, view audit log = admin+)
- Preconditions: signed in as `test_moderator`
- Steps:
  1. Open http://localhost:8080/admin/users.
  2. Open http://localhost:8080/admin/audit.
- Expected: both return 403 "This action is unauthorized.". Each denial is logged as `auth.permission_denied`.

### TC-P1-02-012: Shared props carry `can` flags, never the role
- Priority: High · Type: Security
- Ref: task Scope "auth.can.accessAdmin"; specs/11 "Data exposure via page props"
- Preconditions: none
- Steps:
  1. Signed in as `test_user`, click any in-app link and inspect the Inertia response `props.auth`.
  2. Repeat as `test_moderator`, then as `test_admin`.
- Expected: `auth.user` holds only `username`, `avatarUrl`, `emailVerified`. `auth.can` = `{accessAdmin, viewUsers, viewAuditLog}`: user `false/false/false`, moderator `true/false/false`, admin `true/true/true`. No `role`, `status` or `status_*` key anywhere in the props.

### TC-P1-02-013: Rank rule: an admin cannot act on another admin
- Priority: High · Type: Authorization
- Ref: specs/04 §2 rule 1; UserPolicy
- Preconditions: a second admin: `docker compose exec app php artisan platform:assign-role test_moderator admin`; signed in as `test_admin`
- Steps:
  1. Open `/admin/users`, find `test_moderator` (now admin) and open the detail page.
  2. Open the detail page of `test_super_admin` (search by username).
  3. Open the detail page of `test_user`.
- Expected: steps 1 and 2 show "You can't change this account's standing." and no Suspend / Ban buttons. Step 3 shows "Suspend" and "Ban". Clean-up: assign `moderator` back.

### TC-P1-02-014: Rank rule: super admin can act on an admin
- Priority: Medium · Type: Authorization
- Ref: specs/04 §2 rule 1
- Preconditions: signed in as `test_super_admin`
- Steps:
  1. Open `/admin/users/{ulid}` of `test_admin`.
- Expected: "Suspend" and "Ban" are offered (super admin strictly outranks admin). Do not submit.

### TC-P1-02-015: No impersonation control for any role
- Priority: Medium · Type: Authorization
- Ref: FR-ADMIN-6
- Preconditions: signed in as `test_super_admin`
- Steps:
  1. Open the detail page of `test_user` in `/admin/users`.
  2. Look through the header, admin nav and the detail page actions.
- Expected: nothing offers "Impersonate", "Log in as" or "View as". There is no route for it (any guessed URL such as `/admin/users/{ulid}/impersonate` returns 404).

### TC-P1-02-016: Suspended account signing in lands on the notice
- Priority: High · Type: Functional
- Ref: specs/04 §1; task Scope "EnforceAccountStatus"
- Preconditions: as `test_admin`, suspend `test_user` from `/admin/users/{ulid}` → "Suspend", any reason, Length 3, Message to test_user "Spamming base comments", any internal note → "Suspend for 3 days"
- Steps:
  1. In another browser, sign in as `test_user`.
- Expected: the browser lands on `/account/suspended`: heading "Your account is suspended", text "While it is suspended, you can't post or browse other players' content. You can still sign out, and your settings and notifications stay open.", card "Reason: Spamming base comments" and "Ends:" with the date and time 3 days ahead, in the browser's local timezone. No appeal link.

### TC-P1-02-017: Suspended account is redirected from every route except the allowed ones
- Priority: High · Type: Authorization
- Ref: specs/04 §1; Open question 5
- Preconditions: `test_user` suspended and signed in (TC-P1-02-016)
- Steps:
  1. Open `/`, `/u/test_moderator`, `/admin`, `/email/verify`, `/confirm-password` one by one.
  2. Open `/settings/profile`, `/settings/privacy`, `/settings/security`, `/settings/notifications`, `/settings/danger-zone`, `/notifications`.
  3. Use the avatar menu → "Sign out".
- Expected: every URL in step 1 redirects to `/account/suspended`. Every page in step 2 loads normally (no redirect). Sign out works and lands signed out.

### TC-P1-02-018: Suspended account cannot save settings (WriteBlocked page)
- Priority: High · Type: Authorization
- Ref: specs/04 §3 #2; Decisions "Account/WriteBlocked is rendered in place with status 403"
- Preconditions: `test_user` suspended and signed in
- Steps:
  1. Open `/settings/profile`, change the display name, save.
  2. Check the security log.
- Expected: the page is replaced by "Your account is suspended" / "Changes are off until the suspension ends.", a card with Reason and Ends, and a "Back to home" button. The response status is 403 (Network tab). The display name is unchanged in Adminer `profiles`. The log has `auth.permission_denied` with `reason: status:suspended`.

### TC-P1-02-019: Suspended account can still mark notifications read
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1 (notifications stay open); routes/web/notifications.php
- Preconditions: `test_user` suspended, signed in, with at least one unread notification (the suspension itself creates one)
- Steps:
  1. Open `/notifications` and mark a notification read.
- Expected: the notification is marked read; no notice redirect, no WriteBlocked page.

### TC-P1-02-020: Suspension with no end date and no reason
- Priority: Medium · Type: UI state
- Ref: task States "Account/Suspended (with and without end date)"
- Preconditions: `test_user` signed in; in Adminer set `status` = `suspended`, `status_reason` = NULL, `status_expires_at` = NULL
- Steps:
  1. Reload any page as `test_user`.
- Expected: `/account/suspended` shows "Reason: No reason was given." and "Ends: No end date is set.".

### TC-P1-02-021: Notice page is only for suspended accounts
- Priority: Low · Type: Edge case
- Ref: SuspendedController
- Preconditions: none
- Steps:
  1. Signed out, open `/account/suspended`.
  2. Signed in as an active `test_user`, open `/account/suspended`.
- Expected: step 1 redirects to `/login`; step 2 redirects to `/` (home).

### TC-P1-02-022: Banned sign-in is refused only after the right password, with the reason
- Priority: High · Type: Security
- Ref: specs/04 §1; Decisions "A banned sign-in is refused only after the password matched"
- Preconditions: as `test_admin`, ban `test_user` (Message to test_user "Repeated scams")
- Steps:
  1. Sign in as test@example.com with a wrong password.
  2. Sign in as test@example.com / `password`.
- Expected: step 1 shows "That email and password do not match. Check both and try again." (no hint of the ban). Step 2 stays on `/login` with the email error "This account is banned, so it cannot sign in. Reason: Repeated scams". The security log has `auth.login_blocked`.

### TC-P1-02-023: Banned sign-in without a stored reason
- Priority: Low · Type: UI state
- Ref: lang/en/auth.php `banned`
- Preconditions: in Adminer set `test_user.status` = `banned`, `status_reason` = NULL
- Steps:
  1. Sign in as test@example.com / `password`.
- Expected: email error "This account is banned, so it cannot sign in." with no "Reason:" part.

### TC-P1-02-024: A signed-in session is ended on its next request once banned
- Priority: High · Type: Security
- Ref: Tests "banned user signed out on next request, remember cookie too"
- Preconditions: `test_user` signed in with "Keep me signed in" ticked
- Steps:
  1. In Adminer set `test_user.status` = `banned`, `status_reason` = "Ban test".
  2. Reload the page in the `test_user` browser.
  3. In DevTools → Application → Cookies delete `clash-commons-session` (keep `remember_web_*`), then reload.
- Expected: step 2 redirects to `/login` with "This account is banned, so it cannot sign in." under Email; the security log has `auth.banned_session_ended`. Step 3 still shows the sign-in page: the remember cookie no longer signs in.

### TC-P1-02-025: Restricted account can still make profile and settings writes
- Priority: High · Type: Authorization
- Ref: specs/04 §3 (account writes open to restricted); Open question 4
- Preconditions: `test_user` signed in; in Adminer `status` = `restricted`, `status_reason` = "Low-effort posts", `status_expires_at` = now + 2 days
- Steps:
  1. Change the display name on `/settings/profile` and save.
  2. Change a toggle on `/settings/privacy` and save.
  3. Open `/`, `/u/test_moderator`.
- Expected: both saves succeed (no WriteBlocked page). Pages in step 3 load; restricted accounts are not redirected anywhere.

### TC-P1-02-026: Restricted staff keep read access but lose staff actions
- Priority: High · Type: Authorization
- Ref: Decisions "A staff ability also needs a status that allows it"
- Preconditions: `test_admin` signed in; in Adminer set `test_admin.status` = `restricted`, `status_expires_at` = now + 1 day
- Steps:
  1. Reload; check the header and open `/admin`, `/admin/users`, `/admin/audit`.
  2. Open the detail page of `test_user`.
- Expected: "Admin" link still shown; all three pages load. The detail page shows "You can't change this account's standing." (no Suspend / Ban). Clean-up: set `status` = `active`.

### TC-P1-02-027: Suspended staff are sent to the notice from /admin
- Priority: High · Type: Authorization
- Ref: specs/04 §1, §3
- Preconditions: `test_moderator` signed in; in Adminer `status` = `suspended`, `status_expires_at` = now + 1 day
- Steps:
  1. Open `/admin`.
- Expected: redirect to `/account/suspended`. Clean-up: set `status` = `active`.

### TC-P1-02-028: Pending-deletion staff can read /admin but a sanction submit is blocked
- Priority: Medium · Type: Authorization
- Ref: specs/04 §3 #2; Decisions (read abilities stay open to pending deletion)
- Preconditions: `test_admin` signed in on `/admin/users/{ulid}` of `test_user`; click "Suspend" and fill the form but do not submit
- Steps:
  1. In Adminer set `test_admin.status` = `pending_deletion`.
  2. Submit the suspension form in the open tab.
  3. Reload `/admin`.
- Expected: step 2 shows the WriteBlocked page "Your account is scheduled for deletion" / "Changes are off while the deletion is pending." (HTTP 403); `test_user` is not suspended. Step 3 loads the dashboard. Clean-up: set `status` = `active`.

### TC-P1-02-029: Pending-deletion account cannot save settings
- Priority: Medium · Type: Authorization
- Ref: specs/04 §3 #2
- Preconditions: `test_user` signed in on `/settings/profile`; in Adminer `status` = `pending_deletion`
- Steps:
  1. Change the display name and save.
- Expected: WriteBlocked page "Your account is scheduled for deletion" / "Changes are off while the deletion is pending.", no Reason/Ends card (both empty), "Back to home" button. Clean-up: set `status` = `active`.

## Security

### TC-P1-02-030: Role and status cannot be set through a settings write
- Priority: High · Type: Security
- Ref: specs/11 "Mass assignment"
- Preconditions: signed in as `test_user`
- Steps:
  1. Save `/settings/profile` once normally. In DevTools → Network right-click the `PATCH /settings/profile` request → Copy → Copy as fetch.
  2. Paste into the Console, add `"role":"admin","status":"active","status_reason":"x","status_expires_at":null` to the JSON body, run it.
  3. Check `test_user` in Adminer; reload the app.
- Expected: the request succeeds or fails validation, but `role` stays `user` and the status columns are unchanged. No "Admin" link appears.

### TC-P1-02-031: Permission denials are logged, capped at 20 per minute
- Priority: Medium · Type: Security
- Ref: specs/11 §3; Decisions "capped at platform.security_log.denials_per_minute (20)"
- Preconditions: signed in as `test_user`; note the current line count of today's security log
- Steps:
  1. Open `/admin` and reload it 25 times within one minute.
  2. Count new `auth.permission_denied` lines for route `/admin`.
- Expected: exactly 20 new `auth.permission_denied` lines for that minute; every request still returns 403. Normal page views as staff (e.g. moderator header flags) log nothing.

## Edge cases

### TC-P1-02-032: An expired suspension stops blocking on the next request
- Priority: High · Type: Edge case
- Ref: specs/23 §7; Decisions "Expired sanctions"
- Preconditions: `test_user` suspended (TC-P1-02-016) and signed in on `/account/suspended`
- Steps:
  1. In Adminer set `status_expires_at` to one minute in the past (leave `status` = `suspended`).
  2. Click "Home" or reload `/account/suspended`.
  3. Save a change on `/settings/profile`.
- Expected: no redirect to the notice; `/account/suspended` itself now redirects to `/`. The settings save succeeds. This happens before `moderation:expire-sanctions` runs (the column still reads `suspended` until it does).

### TC-P1-02-033: An expired restriction stops limiting staff actions
- Priority: Medium · Type: Edge case
- Ref: specs/23 §7
- Preconditions: TC-P1-02-026 state (restricted `test_admin`)
- Steps:
  1. In Adminer set `test_admin.status_expires_at` to the past (keep `restricted`).
  2. Reload `/admin/users/{ulid}` of `test_user`.
- Expected: "Suspend" and "Ban" are offered again.

### TC-P1-02-034: A ban never lifts from a date
- Priority: High · Type: Edge case
- Ref: Decisions "A ban or pending deletion never lifts from a date"
- Preconditions: `test_user.status` = `banned` in Adminer
- Steps:
  1. Set `status_expires_at` to yesterday.
  2. Sign in as test@example.com / `password`.
- Expected: still refused with the banned message.

## UI states

### TC-P1-02-035: Status pages at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: task States
- Preconditions: a suspended `test_user` (TC-P1-02-016)
- Steps:
  1. With DevTools device toolbar at 375 px wide, view `/account/suspended` and trigger the WriteBlocked page (TC-P1-02-018).
  2. Repeat at desktop width (≥1280 px).
  3. View `/admin` as `test_moderator` at 375 px.
- Expected: no horizontal scroll; heading, body and Reason/Ends card readable; "Back to home" button fully visible; bottom tab bar does not cover content. Admin layout is usable at 375 px. No console errors.
