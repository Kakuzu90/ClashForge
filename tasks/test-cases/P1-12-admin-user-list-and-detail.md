# P1-12 Admin user list and detail: test cases

Source: [tasks/phase-1/P1-12-admin-user-list-and-detail.md](../phase-1/P1-12-admin-user-list-and-detail.md) · specs/04 §1–4 (`view-users`, admin+), specs/12 §4 and §9, specs/11 §3 and §5, specs/18 §6 Admin, specs/23 §1 and §7

Shared setup used by several cases below:

- **ULIDs.** An account's ULID is in Adminer (`users.ulid`) or in the row link on `/admin/users`.
- **Security log.** `docker compose exec app tail -n 5 storage/logs/security-$(date -u +%F).log`
  (one JSON object per line).
- **Many accounts.** `docker compose exec app php artisan tinker --execute="App\Models\User::factory()->count(55)->create();"`
  adds 55 verified, active `user` accounts.
- **Status through Adminer** (table `users`): see each case. Put `status` back to `active` and clear
  `status_reason`, `status_expires_at`, `deleted_at` after the case.

## Happy path

### TC-P1-12-001: Admin opens the user list from the admin nav
- Priority: High · Type: Functional
- Ref: FR-ADMIN-2 / task Scope "HTTP + UI" / Decision 9
- Preconditions: Fresh seed. Signed in as `test_admin`.
- Steps:
  1. Click "Admin" in the top bar, then "Users" in the left nav.
- Expected: URL `/admin/users`, tab title "Users". The nav reads Dashboard · Users · Logs, "Users" highlighted. Heading "Users" with "Every account, newest first, including banned and deleted ones." Filter bar: "Search" (hint "Start of a username, or a full email"), "Role", "Status", "Apply filters". Table columns Username, Email, Role, Status, Joined, Last sign-in.

### TC-P1-12-002: The list hides the viewer's own account and every super admin
- Priority: High · Type: Functional
- Ref: Decision 15 / specs/04 §3
- Preconditions: Fresh seed.
- Steps:
  1. Signed in as `test_admin`, open `/admin/users`.
  2. Sign out, sign in as `test_super_admin`, open `/admin/users`.
- Expected: Step 1 lists `test_moderator` and `test_user` only (newest first): no `test_admin` (self), no `test_super_admin`. Step 2 lists `test_admin`, `test_moderator`, `test_user`: no `test_super_admin` (self and super admin).

### TC-P1-12-003: A second super admin is hidden from another super admin too
- Priority: Medium · Type: Functional
- Ref: Decision 15
- Preconditions: Register `qa_super`, verify it, then run `docker compose exec app php artisan platform:assign-role qa_super super_admin`. Signed in as `test_super_admin`.
- Steps:
  1. Open `/admin/users`; search `qa_super`.
  2. Open `/admin/users/<ULID of qa_super>`.
- Expected: Step 1: "No accounts match." Step 2: 404.

### TC-P1-12-004: Row content: role, status pill, email, joined, last sign-in
- Priority: High · Type: Functional
- Ref: task Scope "HTTP + UI"
- Preconditions: Fresh seed. `test_user` has signed in once; `test_moderator` never has (fresh seed). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users` and read the `test_user` and `test_moderator` rows.
- Expected: Each Username cell starts with a round 32 px avatar (here the initial "T", as neither account has an avatar), then the username as a link to `/admin/users/<ulid>`. Email shows the full address. Role "User" / "Moderator". Status a green "Active" pill. Joined shows date and time. Last sign-in shows the time `test_user` signed in, and "Never" for an account that never signed in.

### TC-P1-12-005: Unverified, deleted and sanctioned rows are listed and marked
- Priority: High · Type: Functional
- Ref: specs/04 §3 / Decision 5 / task Scope "Queries"
- Preconditions: Register `qa_unverified` (do not verify). In Adminer: set `test_moderator` `deleted_at` = now; register and verify `qa_banned`, then set its `status` `banned`; register and verify `qa_pending`, then set its `status` `pending_deletion`. Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users`.
- Expected: All are listed. `qa_unverified` shows "Not verified" under its email. `test_moderator` shows "Deleted" under its username. `qa_banned` has a red "Banned" pill; `qa_pending` a grey "Deletion requested" pill.

### TC-P1-12-006: Detail page shows the account panel
- Priority: High · Type: Functional
- Ref: FR-ADMIN-2 / FR-ADMIN-6 / specs/12 §4 / Decisions 2 and 8
- Preconditions: Signed in as `test_admin`. `test_user` is signed in in another browser.
- Steps:
  1. On `/admin/users`, click `test_user`.
- Expected: URL `/admin/users/<ulid>`, tab title `test_user`. Header: avatar, `test_user`, the display name if one is set, and an "All users" button. "Account" definition list: Status (pill), Role "User", Email (with "Verified <date>"), Joined, Last sign-in, "Signed in now" "1 browser" (or "N browsers"), Account id (the ULID). Sections "Sanctions" and "Audit trail" follow. No "verified accounts" row (arrives in P2-02).

### TC-P1-12-007: Audit trail lists the latest entries about the account
- Priority: High · Type: Functional
- Ref: FR-MOD-6 / task Scope "HTTP + UI" / `platform.admin.audit_trail_limit` (10)
- Preconditions: Run `platform:assign-role test_user moderator` then `platform:assign-role test_user user`. Signed in as `test_admin`.
- Steps:
  1. Open the `test_user` detail and read "Audit trail".
- Expected: Two items, newest first, each "Role changed by Console · <date and time>" with a diff table (`role` `moderator` → `user`, then `user` → `moderator`). No "See all in the audit log" button (10 or fewer entries). Context, user agent and request id are not shown here.

### TC-P1-12-008: More than 10 entries: trail capped with a link to the full log
- Priority: Medium · Type: Functional
- Ref: Decision 13 / task Scope "HTTP + UI"
- Preconditions: Run the role pair 6 times for `test_user` (12 entries). Signed in as `test_admin`.
- Steps:
  1. Open the `test_user` detail.
  2. Click "See all in the audit log".
- Expected: Step 1: exactly 10 trail items, newest first, and a "See all in the audit log" button. Step 2: `/admin/audit?target=test_user` with the Account filter filled and all 12 entries.

### TC-P1-12-009: Trail names a staff actor with their role
- Priority: Low · Type: Functional
- Ref: specs/18 §4 AuditTrailList
- Preconditions: As `test_admin`, suspend `test_user` for 3 days and lift it (P1-14 panel).
- Steps:
  1. Reload the `test_user` detail.
- Expected: "Sanction lifted by test_admin (Admin)" and "Sanction applied by test_admin (Admin)" at the top of the trail.

### TC-P1-12-010: Account with no audit entries
- Priority: Low · Type: UI state
- Ref: specs/18 §6
- Preconditions: Fresh seed. Signed in as `test_admin`.
- Steps:
  1. Open the `test_moderator` detail.
- Expected: "Audit trail" shows "Nothing has been logged about this account."

### TC-P1-12-038: A ready avatar shows beside the username in the list
- Priority: Medium · Type: Functional
- Ref: specs/18 §6 Admin / owner decision 2026-10-02
- Preconditions: As `test_user`, Settings → Profile, pick a JPEG or PNG avatar, press "Save photo" in the "Crop your photo" dialog and wait until the avatar shows on the settings page (the `queue-media` container makes the variants). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users` and look at the `test_user` row.
  2. Right-click its avatar and open the image in a new tab.
- Expected: Step 1: a round 32 px picture of the uploaded photo sits left of `test_user`, vertically centred with the username link; the photo is not stretched. Step 2: the image loads (the thumbnail variant from the media bucket). Other rows still show initials.

### TC-P1-12-039: No avatar: the initial stands in
- Priority: Medium · Type: UI state
- Ref: specs/18 §4 Avatar "initials fallback" / owner decision 2026-10-02
- Preconditions: Fresh seed (no avatars). Signed in as `test_super_admin`.
- Steps:
  1. Open `/admin/users`.
  2. As `test_user`, upload an avatar (TC-P1-12-038), then press "Remove" on Settings → Profile; reload the list.
- Expected: Step 1: `test_admin`, `test_moderator` and `test_user` each show a round 32 px circle with the initial "T" in place of a picture; no broken-image icon. Step 2: `test_user` is back to the "T" initial.

## Filters / search

### TC-P1-12-011: Search by username prefix, case-insensitive
- Priority: High · Type: Functional
- Ref: Decision 3 / task Acceptance "Functional"
- Preconditions: Fresh seed. Signed in as `test_super_admin`.
- Steps:
  1. Search `test_m`, apply.
  2. Search `TEST_U`, apply.
  3. Search `user`, apply.
  4. Search `   test_admin   ` (spaces around), apply.
- Expected: 1: `test_moderator` only. 2: `test_user`. 3: "No accounts match." (prefix only, not contains). 4: `test_admin` (the input is trimmed).

### TC-P1-12-012: Search by exact email
- Priority: High · Type: Functional
- Ref: Decision 3
- Preconditions: Fresh seed. Signed in as `test_admin`.
- Steps:
  1. Search `test@example.com`.
  2. Search `TEST@Example.COM`.
  3. Search `test@`.
  4. Search `example.com`.
- Expected: 1 and 2: `test_user`. 3: "No accounts match." (an `@` means an exact email). 4: "No accounts match." (no `@`, so it is a username prefix).

### TC-P1-12-013: LIKE wildcards are matched literally
- Priority: High · Type: Security
- Ref: Decision 3 / specs/11 "Injection"
- Preconditions: Fresh seed. Signed in as `test_super_admin`.
- Steps:
  1. Search `%`.
  2. Search `_`.
  3. Search `test%`.
  4. Search `test\`.
  5. Search `test_`.
- Expected: 1–4: "No accounts match." (no username starts with those characters). 5: `test_admin`, `test_moderator`, `test_user` (the underscore matches only a literal `_`). No 500.

### TC-P1-12-014: Role filter, without a super admin option
- Priority: High · Type: Functional
- Ref: Decision 15 / task Scope "Queries"
- Preconditions: Fresh seed. Signed in as `test_super_admin`.
- Steps:
  1. Open the "Role" select.
  2. Choose "Admin", apply.
- Expected: Options "Any role", "User", "Moderator", "Admin" (no "Super admin"). After applying, URL has `?role=admin` and only `test_admin` is listed.

### TC-P1-12-015: Status filter uses the effective status
- Priority: High · Type: Functional
- Ref: Decision 4 / specs/23 §7 / task Acceptance "Data"
- Preconditions: Signed in as `test_admin`. In Adminer: `test_user` `status` `suspended`, `status_reason` `QA past`, `status_expires_at` one hour ago; a registered `qa_susp` `status` `suspended`, `status_expires_at` next week; a registered `qa_restr` `status` `restricted`, `status_expires_at` empty.
- Steps:
  1. Open the "Status" select; choose "Suspended", apply.
  2. Choose "Active", apply.
  3. Choose "Restricted", apply.
- Expected: Options "Any status", "Active", "Restricted", "Suspended", "Banned", "Deletion requested". 1: `qa_susp` only (not `test_user`, whose suspension has passed). 2: includes `test_user` with an "Active" pill. 3: `qa_restr` with a yellow "Restricted" pill.

### TC-P1-12-016: Combined filters and Clear
- Priority: Medium · Type: Functional
- Ref: specs/18 §4 FilterBar
- Preconditions: Accounts from TC-P1-12-005. Signed in as `test_admin`.
- Steps:
  1. Search `qa_`, Role "User", Status "Banned", apply.
  2. Click "Clear".
- Expected: 1: `qa_banned` only; a "Clear" button is visible. 2: all fields reset, URL `/admin/users` with no query, full list; "Clear" disappears.

### TC-P1-12-017: Cursor paging, 50 per page, filters kept
- Priority: High · Type: Functional
- Ref: Decision 6 / `platform.admin.per_page` (50)
- Preconditions: 55 extra accounts (setup). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users`; count rows.
  2. Click "Older", then "Newer".
  3. Set Status "Active", apply, click "Older".
- Expected: 1: 50 rows, newest sign-up first, "Older" only. 2: the remaining rows with a `cursor` in the URL and a "Newer" button; "Newer" returns the first 50. 3: page 2 keeps `status=active` in the URL.

### TC-P1-12-018: Invalid filters return to the unfiltered list with field errors
- Priority: High · Type: Validation
- Ref: task Tests "filter validation" / Decision 12
- Preconditions: Signed in as `test_admin`.
- Steps: Open each URL:
  1. `/admin/users?role=super_admin`
  2. `/admin/users?status=frozen`
  3. `/admin/users?search=` followed by 255 `a` characters
  4. `/admin/users?search=%00`
  5. `/admin/users?search=%FF`
  6. `/admin/users?cursor=abc`
- Expected: Each lands on `/admin/users` unfiltered, with an error: 1 "The selected role is invalid."; 2 "The selected status is invalid."; 3 "The search field must not be greater than 254 characters."; 4 and 5 "The search contains characters that are not allowed."; 6 "That page link is not valid. Start from the first page." No 500.

## Authorization

### TC-P1-12-019: Moderator gets 403 on the admin area and works from Reports
- Priority: High · Type: Authorization
- Ref: specs/04 §2–3 `access-admin` and `view-users` admin+ / Open question 2 / owner decision 2026-10-02
- Preconditions: Signed in as `test_moderator`.
- Steps:
  1. Check the top bar, then click "Reports".
  2. Open `/admin`, `/admin/users`, `/admin/users?role=bogus` and `/admin/users/<ULID of test_user>`.
- Expected: Step 1: the top bar shows "Reports" (no "Admin"); it opens `/moderation/reports` in the member layout, heading "Reports", empty state "No open reports". Step 2: every URL returns "403 | This action is unauthorized." (403 even with the bad filter, not validation errors); no admin nav is ever shown. An `auth.permission_denied` line is logged per denial.

### TC-P1-12-020: Regular user and guest are refused
- Priority: High · Type: Authorization
- Ref: specs/04 §1–2
- Preconditions: none.
- Steps:
  1. Signed in as `test_user`, open `/admin/users` and `/admin/users/<ULID of test_moderator>`.
  2. Signed out, open the same URLs.
- Expected: 1: "403 | This action is unauthorized." for both. 2: redirect to `/login`.

### TC-P1-12-021: Restricted and pending-deletion admins can still read
- Priority: High · Type: Authorization
- Ref: specs/04 §3 (read abilities) / Decision 1
- Preconditions: Signed in as `test_admin`. In Adminer set `test_admin` `status` `restricted` (no expiry).
- Steps:
  1. Open `/admin/users`, search `test_`, then open the `test_user` detail.
  2. Change `test_admin` to `pending_deletion` (`deletion_requested_at` now, `deletion_previous_status` `active`) and repeat.
- Expected: The list, the search and the detail all load in both states; "Users" stays in the nav.

### TC-P1-12-022: Restricted moderator is still refused
- Priority: Medium · Type: Authorization
- Ref: Review fixes "restricted moderator refused" / owner decision 2026-10-02
- Preconditions: `test_moderator` `status` `restricted`. Signed in as `test_moderator`.
- Steps:
  1. Open `/admin`, `/admin/users` and `/admin/users/<ULID of test_user>`.
  2. Click "Reports" in the top bar.
- Expected: Step 1: "403 | This action is unauthorized." for each. Step 2: `/moderation/reports` opens (reading stays open to restricted staff).

### TC-P1-12-023: Suspended staff are sent to the notice; banned staff are signed out
- Priority: High · Type: Authorization
- Ref: specs/04 §1 / Review fixes "access matrix extended" / owner decision 2026-10-02
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. In Adminer set `test_admin` `status` `suspended`, `status_expires_at` next week. Open `/admin/users`, then `/admin/users/<ULID of test_user>`.
  2. Set `status` `banned`. Reload `/admin/users`.
  3. Sign in as `test_moderator`; in Adminer set it `suspended`, `status_expires_at` next week; open `/admin/users`.
- Expected: 1: both redirect to `/account/suspended` ("Your account is suspended"). 2: redirect to `/login` with "This account is banned, so it cannot sign in." 3: redirect to `/account/suspended`, not the 403 (the suspension check comes first).

### TC-P1-12-024: Detail is reachable by ULID only
- Priority: High · Type: Security
- Ref: specs/04 §3 (IDOR) / specs/23 §1 / Decision 5
- Preconditions: Signed in as `test_admin`. Note `test_user`'s numeric `id` and ULID in Adminer.
- Steps: Open each URL:
  1. `/admin/users/<ULID of test_user>` in upper case.
  2. `/admin/users/test_user`
  3. `/admin/users/<numeric id of test_user>`
  4. `/admin/users/01ARZ3NDEKTSV4RRFFQ69G5FAV` (valid format, no such account)
  5. `/admin/users/not-a-ulid`
- Expected: 1: the `test_user` detail (case does not matter). 2–5: "404 | Not Found". No `admin.user_viewed` line for 2–5.

### TC-P1-12-025: Own account and super admin detail URLs 404 without a view log
- Priority: High · Type: Security
- Ref: Decision 15
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users/<ULID of test_admin>`.
  2. Open `/admin/users/<ULID of test_super_admin>`.
  3. Check the security log.
- Expected: Both "404 | Not Found". No `admin.user_viewed` line was written for either.

## Security

### TC-P1-12-026: Opening a detail writes one `admin.user_viewed` line
- Priority: High · Type: Security
- Ref: Open question 3 / Decision 7 / specs/11 §3
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. Open the `test_user` detail once.
  2. Read the last lines of the security log.
- Expected: Exactly one new line with message `admin.user_viewed` and context `actor` = `test_admin`'s ULID, `user` = `test_user`'s ULID, `ip_hash` (a hash, not an IP).

### TC-P1-12-027: Each rows load writes `admin.users_listed` without the search text
- Priority: High · Type: Security
- Ref: Decision 14 / specs/11 §3
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users`.
  2. Search `test@example.com` with Role "User", apply.
  3. With 55 extra accounts, clear the filters and click "Older".
  4. Read the security log after each step.
- Expected: One `admin.users_listed` line per rows load (the page shell adds none). 1: `searched` false, `role` null, `status` null, `paged` false, `rows` = the row count, `actor`, `ip_hash`. 2: `searched` true, `role` `user`; the email `test@example.com` appears nowhere in the line. 3: `paged` true.

### TC-P1-12-028: No password, token, 2FA or IP data in the props
- Priority: High · Type: Security
- Ref: specs/11 "Data exposure via page props", §5 / task Acceptance "Data"
- Preconditions: `test_user` has signed in (so `last_login_ip_hash` is set) and has audit entries. Signed in as `test_admin`. DevTools Network open.
- Steps:
  1. Load `/admin/users`; open the deferred request (`X-Inertia-Partial-Data: users`) and view its JSON.
  2. Load the `test_user` detail; view the `data-page` JSON of the response.
  3. Search both for `password`, `remember`, `two_factor`, `ip_hash`, `last_login_ip`, `userAgent`, `requestId`.
- Expected: No match in either. Rows carry `ulid`, `username`, `avatarUrl` (a media URL or `null`), `email`, `emailVerified`, `roleLabel`, `statusLabel`, `statusTone`, `joinedAt`, `lastSignInAt`, `deleted`. The trail items carry only `id`, `actionLabel`, `actorUsername`, `actorRoleLabel`, `actorVia`, `before`, `after`, `createdAt`.

### TC-P1-12-029: Admin-search rate limit: 60 loads a minute per staff member, shared by both lists
- Priority: High · Type: Security
- Ref: Decision 11 / specs/04 §4 / `platform.rate_limits.admin_search_per_minute` (60)
- Preconditions: Signed in as `test_admin` on `/admin/users`. `test_super_admin` signed in in a private window.
- Steps:
  1. In the console run `for (let i = 0; i < 60; i++) await fetch('/admin/audit');` (uses the whole budget on the audit log).
  2. On `/admin/users`, click "Apply filters".
  3. Type `/admin/users` in the address bar and press Enter.
  4. In the private window (`test_super_admin`), open `/admin/users`.
  5. As `test_admin`, open the `test_user` detail page.
  6. Wait 60 seconds, return to `/admin/users` and click "Try again" (or reload).
- Expected: 2: a warning alert "Too many searches in a minute" / "Wait a moment, then try again." with a "Try again" button (no error modal). 3: a bare page "Too many searches." (HTTP 429). 4: loads normally (the limit is per staff member). 5: the detail loads (not throttled). 6: the list loads. The security log has `auth.rate_limited` with `limiter` `admin-search`. Each normal page visit counts twice (page plus deferred rows), so about 30 visits a minute trigger it.

### TC-P1-12-030: HTML in a display name or sanction reason renders as text
- Priority: Medium · Type: Security
- Ref: specs/11 XSS
- Preconditions: As `test_user`, set the display name to `<img src=x onerror=alert(1)>` (Settings → Profile). As `test_admin`, suspend `test_user` with message `<script>alert(2)</script>`.
- Steps:
  1. Open the `test_user` detail.
- Expected: No alert. The display name under the heading and "Reason shown to them: <script>alert(2)</script>" show as literal text.

## Edge cases

### TC-P1-12-031: Expired suspension reads Active on the detail, without reason or end
- Priority: High · Type: Edge case
- Ref: specs/23 §7 / Decision 4 / task Acceptance "Data"
- Preconditions: Signed in as `test_admin`. In Adminer: `test_user` `status` `suspended`, `status_reason` `QA past`, `status_expires_at` one hour ago (do not run the expiry job).
- Steps:
  1. Open the `test_user` detail.
  2. Set `status_expires_at` to next week and reload.
  3. Set `status_expires_at` empty and reload.
- Expected: 1: green "Active" pill, no reason line, no end line. 2: red "Suspended" pill, "Reason shown to them: QA past", "Ends <date>". 3: "Suspended", the reason, "No end date".

### TC-P1-12-032: Soft-deleted account opens with a deleted banner
- Priority: Medium · Type: Edge case
- Ref: Decision 5 / specs/04 §3
- Preconditions: In Adminer set `test_moderator` `deleted_at` = now. Signed in as `test_admin`.
- Steps:
  1. Click `test_moderator` in the list.
- Expected: The detail opens with a warning alert "This account is deleted" / "Deleted on <date>." and the full account panel.

### TC-P1-12-033: Detail keyed by ULID survives a username change
- Priority: Medium · Type: Edge case
- Ref: specs/23 §1 "Username released and re-registered"
- Preconditions: Signed in as `test_admin` with the `test_user` detail open (note the URL).
- Steps:
  1. As `test_user`, change the username to `qa_renamed`.
  2. Reload the noted detail URL; then search `test_user` and `qa_renamed` on the list.
- Expected: The same URL shows `qa_renamed` with the same Account id. The search finds the account under `qa_renamed` only; the trail shows "Username changed by qa_renamed (User)".

### TC-P1-12-034: "Signed in now" counts live sessions only
- Priority: Low · Type: Edge case
- Ref: Decision 8
- Preconditions: `test_user` signed in in two different browsers. Signed in as `test_admin`.
- Steps:
  1. Open the `test_user` detail.
  2. Sign `test_user` out in one browser; reload the detail.
  3. Sign it out in the other; reload.
- Expected: "2 browsers", then "1 browser", then "0 browsers".

### TC-P1-12-035: Account without a profile row still opens
- Priority: Low · Type: Edge case
- Ref: Decision 8
- Preconditions: In Adminer delete `test_moderator`'s row in `profiles` (if any). Signed in as `test_admin`.
- Steps:
  1. Open the `test_moderator` detail.
- Expected: The page renders with initials in the avatar and no display name line; no error.

### TC-P1-12-040: Avatar still processing shows the initial until it is ready
- Priority: Medium · Type: Edge case
- Ref: owner decision 2026-10-02 / `AvatarUrlService` (ready avatars only)
- Preconditions: `test_user` has no avatar. Stop the media worker: `docker compose stop queue-media`. Signed in as `test_admin`.
- Steps:
  1. As `test_user`, upload an avatar through "Crop your photo" → "Save photo".
  2. In Adminer check the new `media` row's `status` (not `ready`), then reload `/admin/users`.
  3. `docker compose start queue-media`; wait until the `media` row reads `ready`; reload the list.
- Expected: Step 2: the `test_user` row shows the "T" initial, no broken image, and the list loads normally. Step 3: the uploaded photo replaces the initial.

### TC-P1-12-041: A full page of avatars loads in one batch
- Priority: Low · Type: Edge case
- Ref: owner decision 2026-10-02 (avatars in one batch, list within the 25-query budget) / `UserListTest` "within the query budget"
- Preconditions: 55 extra accounts (setup) and one ready avatar on `test_user` (TC-P1-12-038). Give many accounts the same avatar: in Adminer run `UPDATE profiles SET avatar_media_id = (SELECT avatar_media_id FROM profiles p JOIN users u ON u.id = p.user_id WHERE u.username = 'test_user');`. Signed in as `test_admin`.
- Steps:
  1. Open `/admin/users`, then click "Older".
  2. Run `docker compose exec app php artisan test --filter="query budget"`.
  3. Put the profiles back: `UPDATE profiles SET avatar_media_id = NULL WHERE user_id <> (SELECT id FROM users WHERE username = 'test_user');`
- Expected: Step 1: every row on both pages shows the photo; the one deferred `users` request (DevTools Network) carries every row's `avatarUrl`, with no extra app request per avatar (only the image fetches from the bucket), and the rows arrive about as fast as without avatars. Step 2: both "query budget" tests pass (25 queries at most for a page, with or without avatars).

## UI states

### TC-P1-12-036: Empty, loading and error states on the list
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 / task Acceptance "States"
- Preconditions: Signed in as `test_admin` on `/admin/users`.
- Steps:
  1. Search `zzz_nobody`, apply.
  2. Set DevTools throttling to "Slow 3G" and reload.
  3. Reset throttling. In DevTools → Network request blocking, block `*/admin/users*`; click "Apply filters". Then unblock and click "Try again".
- Expected: 1: "No accounts match." 2: the header stays and five skeleton rows keep the column widths until the rows arrive. 3: a red alert "The user list didn't load" with "Try again in a moment." and "Try again" (no request id: the request never reached the server); after unblocking, "Try again" loads the list.

### TC-P1-12-037: 375 px layout and keyboard access
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. In DevTools device mode at 375 px, open `/admin/users`, then a detail page.
  2. On desktop, use only the keyboard to search, move into the table and open a username link.
- Expected: 1: nav folded behind "Menu"; filters stacked; the table scrolls horizontally inside its own area with no page-level horizontal scroll; long emails and the ULID wrap; the detail's definition list stacks label over value. 2: visible focus on every control; the table region is focusable and scrolls with arrow keys; Enter on a username opens the detail.

### TC-P1-12-042: Status pills never wrap, "Deletion requested" included
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 Admin / owner decision 2026-10-02 (pills `whitespace-nowrap`, wider status column)
- Preconditions: `qa_pending` with `status` `pending_deletion` and `qa_banned` with `status` `banned` (TC-P1-12-005). Signed in as `test_admin`.
- Steps:
  1. At desktop width open `/admin/users` and look at the Status column.
  2. Narrow the window step by step down to 768 px, watching the `qa_pending` pill.
  3. In DevTools device mode at 375 px, reload and scroll the table sideways to the Status column.
  4. Open the `qa_pending` detail at 375 px.
- Expected: Steps 1–3: the grey "Deletion requested" pill (shown in capitals) stays on one line at every width, its text never breaks onto a second line or spills out of the pill border; all pills in the column have the same height. The status column is wide enough that the pill does not overlap the Joined column; at 375 px the table scrolls inside its own area instead and the page has no horizontal scroll. Step 4: the Status pill in the Account panel is also on one line.
