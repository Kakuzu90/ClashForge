# P1-06 Audit log and admin viewer: test cases

Source: [tasks/phase-1/P1-06-audit-log-and-viewer.md](../phase-1/P1-06-audit-log-and-viewer.md) · specs/12 §9, specs/07 `audit_logs`, specs/04 §2–3, specs/11 "Data exposure via page props" and §3, specs/18 §6 Admin

Shared setup used by several cases below:

- **Seed entries.** Starting from `migrate:fresh --seed` the log is empty. Run each command once
  from the repo root (each writes one `role.changed` entry by the console):
  `docker compose exec app php artisan platform:assign-role test_user moderator`, then
  `docker compose exec app php artisan platform:assign-role test_user user`.
- **More than one page (50 rows).** Run the pair above 26 times in a loop:
  `for i in $(seq 1 26); do docker compose exec -T app php artisan platform:assign-role test_user moderator; docker compose exec -T app php artisan platform:assign-role test_user user; done`
  (52 entries).
- **Staff state through Adminer** (table `users`, edit the row): restricted = `status` `restricted`,
  `status_expires_at` empty; pending deletion = `status` `pending_deletion`, `deletion_requested_at`
  now, `deletion_previous_status` `active`; suspended = `status` `suspended`, `status_reason` `QA`,
  `status_expires_at` a date next week. Put `status` back to `active` (and clear the other fields)
  after each case.

## Happy path

### TC-P1-06-001: Admin opens the audit log from the admin nav
- Priority: High · Type: Functional
- Ref: FR-ADMIN-4 / specs/18 §6 / task Scope "HTTP + UI"
- Preconditions: Seed entries exist. Signed in as `test_admin`.
- Steps:
  1. Click "Admin" in the top bar (opens http://localhost:8080/admin).
  2. In the left nav, click "Logs".
- Expected: URL is `/admin/audit`, tab title "Audit log". Heading "Audit log" with the line "Every privileged action, newest first. Entries are never edited or removed." The left nav shows Dashboard · Users · Logs, with "Logs" highlighted (`aria-current="page"`), not "Dashboard". A filter bar "Acted by", "Account", "Action", "From", "To" with "Apply filters". The table has the columns When, Action, Acted by, Account and lists the two `role.changed` entries, newest first.

### TC-P1-06-002: Console role change writes `role.changed` with before / after and no actor
- Priority: High · Type: Functional
- Ref: FR-MOD-6 / specs/12 §9 / task Scope "Auth", Decisions 2 and 9
- Preconditions: Fresh seed. Signed in as `test_admin` in the browser.
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_user moderator`.
  2. Reload `/admin/audit`.
  3. Click the row toggle (chevron) on the new entry.
- Expected: The command prints "Role set to moderator; the account's sessions were ended." The top row reads Action "Role changed", Acted by "Console" (no role line under it), Account "test_user". The expanded detail shows a diff table Field / Before / After / Change with one row: `role`, `user`, `moderator`, "Changed", and a context row `via` = `console`. No "Browser" row (console entries carry no user agent). In Adminer, the new `audit_logs` row has `actor_id` NULL, `actor_role` NULL, `ip_hash` NULL, `user_agent` NULL, `auditable_type` `user`.

### TC-P1-06-003: Re-assigning the same role writes nothing
- Priority: Medium · Type: Edge case
- Ref: task Scope "Auth"
- Preconditions: `test_user` already has role `user`. Note the number of rows in `audit_logs` (Adminer).
- Steps:
  1. Run `docker compose exec app php artisan platform:assign-role test_user user`.
  2. Reload `/admin/audit`.
- Expected: The command prints "Already user; nothing changed." No new row in the viewer or in `audit_logs`.

### TC-P1-06-004: Every audited action type appears with its label and actor
- Priority: High · Type: Functional
- Ref: specs/12 §9 / `AuditAction` (P1-06, P1-09, P1-11, P1-14)
- Preconditions: Fresh seed. Signed in as `test_admin` in one browser; `test_user` in a private window.
- Steps:
  1. Role change: run `platform:assign-role test_user moderator` then `platform:assign-role test_user user`.
  2. Sanction applied: as `test_admin`, open Users → `test_user` → "Suspend", reason any, length 3, message "QA suspension", submit "Suspend for 3 days".
  3. Sanction lifted: on the same page click "Lift the suspension", fill "Why lift it" with "QA lift", submit.
  4. Sanction ended: suspend `test_user` again for 1 day; in Adminer set its `users.status_expires_at` to one minute ago; run `docker compose exec app php artisan moderation:expire-sanctions`.
  5. Username changed: as `test_user` (private window, sign in again if signed out) change the username in Settings → Profile to `qa_renamed`.
  6. Account anonymised: register a throwaway account, verify it in Mailpit, request deletion in Settings → Danger zone; in Adminer set its `deletion_requested_at` to 31 days ago; run `docker compose exec app php artisan platform:anonymize-deleted`.
  7. Reload `/admin/audit`.
- Expected: One entry per action, newest first, with these labels and actors: "Role changed" by "Console"; "Sanction applied" by `test_admin` (role line "Admin"); "Sanction lifted" by `test_admin` ("Admin"); "Sanction ended" by "System" (context `via` = `scheduler`); "Username changed" by `qa_renamed` ("User"), diff `username` `test_user` → `qa_renamed`; "Account anonymised" by "Console", diff `status` `pending_deletion` → `banned`, context `command`. Account column shows the target's current username (`deleted_user_…` for the anonymised one).

### TC-P1-06-005: Expanded detail shows diff, context, request id and browser for a web action
- Priority: High · Type: Functional
- Ref: specs/18 §4 DiffViewer / task Scope "HTTP + UI"
- Preconditions: A "Sanction applied" entry from TC-P1-06-004 step 2.
- Steps:
  1. On `/admin/audit`, expand the "Sanction applied" row.
- Expected: The diff lists `status` (`active` → `suspended`, "Changed"), `reason` (`null` → "QA suspension", "Changed") and `until` (`null` → an ISO date 3 days ahead, "Changed"). Below it, context rows `sanction` = `suspension`, `reason_code` = the chosen code, `days` = `3`, then "Request id" (a value) and "Browser" (your browser's user agent). Clicking the toggle again collapses the row.

### TC-P1-06-006: Diff viewer labels added, removed and unchanged keys
- Priority: Low · Type: UI state
- Ref: specs/18 §4 DiffViewer / task Tests (Vitest)
- Preconditions: A "Sanction applied" (ban) and a "Sanction lifted" entry for the ban (ban `test_user` as `test_admin`, then lift it).
- Steps:
  1. Expand the ban's "Sanction applied" row.
- Expected: `until` shows `null` → `null` with change "Same"; `status` "Changed". Keys missing on one side render "None" in that column with "Added" or "Removed". Rows carry a coloured left border, but the change is always spelled out in the Change column.

### TC-P1-06-007: Empty log on a fresh database
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 / task Scope "States"
- Preconditions: Fresh seed (no entries). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/audit`.
- Expected: The table shows "Nothing has been logged yet." No "Newer" / "Older" buttons, no "Clear" button.

## Filters / search

### TC-P1-06-008: Filter by actor (Acted by)
- Priority: High · Type: Functional
- Ref: FR-ADMIN-4 / task Acceptance "Functional"
- Preconditions: Entries from TC-P1-06-004 (console, scheduler and `test_admin` actors).
- Steps:
  1. Type `test_admin` in "Acted by", click "Apply filters".
  2. Change it to `TEST_ADMIN`, apply.
  3. Change it to `test_adm`, apply.
- Expected: Step 1: URL gains `?actor=test_admin`; only entries acted by `test_admin` are listed. Step 2: same rows (usernames match without case). Step 3: "No entries match these filters." (exact username, not a prefix). A "Clear" button appears once a filter is applied.

### TC-P1-06-009: Filter by target (Account)
- Priority: High · Type: Functional
- Ref: FR-ADMIN-4 / FR-MOD-6
- Preconditions: Entries about `test_user` (role changes) and about another account (e.g. the anonymised one).
- Steps:
  1. Type the current username of `test_user` in "Account", apply.
- Expected: URL gains `?target=…`; only entries whose Account is that user are listed, whoever acted.

### TC-P1-06-010: Filter by action
- Priority: High · Type: Functional
- Ref: FR-ADMIN-4
- Preconditions: Entries of several actions (TC-P1-06-004).
- Steps:
  1. Open the "Action" select.
  2. Choose "Sanction applied", apply.
- Expected: The options are "Any action", "Role changed", "Sanction applied", "Sanction lifted", "Sanction ended", "Account anonymised", "Username changed". After applying, URL has `?action=sanction.applied` and only "Sanction applied" rows show.

### TC-P1-06-011: Filter by date range (whole UTC days, both ends included)
- Priority: High · Type: Functional
- Ref: FR-ADMIN-4 / Decision 6 / specs/23 §9
- Preconditions: At least three entries. The append-only trigger blocks edits, so in Adminer run, in one go: `ALTER TABLE audit_logs DISABLE TRIGGER USER; UPDATE audit_logs SET created_at = '2026-09-15 23:59:59' WHERE id = (SELECT MIN(id) FROM audit_logs); UPDATE audit_logs SET created_at = '2026-09-16 00:00:00' WHERE id = (SELECT MIN(id) + 1 FROM audit_logs); ALTER TABLE audit_logs ENABLE TRIGGER USER;` (test-data setup only; TC-P1-06-027 checks the trigger is back).
- Steps:
  1. Set "From" and "To" both to 2026-09-15, apply.
  2. Set "From" 2026-09-16, "To" empty, apply.
  3. Set "From" empty, "To" today's UTC date, apply.
- Expected: Step 1 lists the 23:59:59 row only. Step 2 lists the 00:00:00 row and everything later. Step 3 lists every entry up to the end of today (UTC). The date fields carry the hint "UTC".

### TC-P1-06-012: Combined filters and Clear
- Priority: Medium · Type: Functional
- Ref: FR-ADMIN-4 / specs/18 §4 FilterBar
- Preconditions: Entries from TC-P1-06-004.
- Steps:
  1. Set Acted by `test_admin`, Account `test_user` (or its current name), Action "Sanction lifted", apply.
  2. Click "Clear".
- Expected: Step 1 lists only lifts by `test_admin` on that account. Step 2 empties every field, returns to `/admin/audit` with no query string and lists all entries; "Clear" disappears.

### TC-P1-06-013: Pressing Enter applies the filters
- Priority: Low · Type: UI state
- Ref: specs/18 §4 FilterBar
- Preconditions: Entries exist.
- Steps:
  1. Type `test_admin` in "Acted by" and press Enter.
- Expected: Same result as clicking "Apply filters"; the button shows its loading state while the rows load.

### TC-P1-06-014: Cursor pagination keeps the applied filters
- Priority: High · Type: Functional
- Ref: task Tests "pagination" / Decision 4 / `platform.admin.per_page` (50)
- Preconditions: More than one page (52 `role.changed` entries, see setup).
- Steps:
  1. Open `/admin/audit`. Count the rows.
  2. Click "Older".
  3. Click "Newer".
  4. Apply Action "Role changed", then click "Older"; while on page 2 type `test_admin` in "Acted by" without applying, and click "Newer".
- Expected: Step 1: 50 rows, an "Older" button, no "Newer". Step 2: the remaining rows (oldest last), URL has `cursor=…`, a "Newer" button. Step 3: back to the first 50. Step 4: the cursor pages the filters as applied (`action=role.changed`), ignoring the unapplied "Acted by" text.

## Validation

### TC-P1-06-015: Invalid filter values return to the unfiltered log with field errors
- Priority: High · Type: Validation
- Ref: Decision 6 / task Tests "invalid filter values rejected"
- Preconditions: Signed in as `test_admin`.
- Steps: Open each URL in turn:
  1. `/admin/audit?action=bogus`
  2. `/admin/audit?from=15-09-2026`
  3. `/admin/audit?from=2026-09-20&to=2026-09-10`
  4. `/admin/audit?actor=abcdefghijklmnopqrstu` (21 characters)
- Expected: Each lands on `/admin/audit` with no filters applied and an error under the field: 1 "The selected action is invalid."; 2 "The from date field must match the format Y-m-d."; 3 "The to date field must be a date after or equal to from date."; 4 "The acted by field must not be greater than 20 characters." No 500 page.

### TC-P1-06-016: Tampered cursor is a field error, not a 500
- Priority: Medium · Type: Validation
- Ref: Review fixes "hand-edited cursor" / Decision 6
- Preconditions: More than one page of entries.
- Steps:
  1. Click "Older", then edit the URL's `cursor` value (change a few characters, or use `cursor=abc`) and load it.
- Expected: Back on `/admin/audit` unfiltered with the message "That page link is not valid. Start from the first page." No 500.

### TC-P1-06-017: NUL byte or invalid UTF-8 in a text filter is rejected
- Priority: Medium · Type: Security
- Ref: P1-12 Decision 12 (applies to the audit filters) / specs/11 "Injection"
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. Open `/admin/audit?actor=%00`.
  2. Open `/admin/audit?target=%FF`.
- Expected: Field errors "The acted by contains characters that are not allowed." and "The account contains characters that are not allowed."; no 500.

## Authorization

### TC-P1-06-018: Super admin reads the log
- Priority: High · Type: Authorization
- Ref: specs/04 §2 `View audit log` admin+
- Preconditions: Signed in as `test_super_admin`.
- Steps:
  1. Open `/admin/audit`.
- Expected: The log loads with the same entries and filters as for an admin; nav shows Dashboard · Users · Logs.

### TC-P1-06-019: Moderator cannot open the admin area at all, restricted or not
- Priority: High · Type: Authorization
- Ref: specs/04 §2–3 (`access-admin` and `view-audit-log` admin+) / task Acceptance "Authorization" / owner decision 2026-10-02
- Preconditions: Signed in as `test_moderator`.
- Steps:
  1. Look at the top bar.
  2. Type `/admin` in the address bar.
  3. Type `/admin/audit`.
  4. Type `/admin/audit?action=bogus`.
  5. In Adminer set `test_moderator` to restricted (see setup), reload, and repeat steps 2–4.
- Expected: Step 1: a "Reports" link (to `/moderation/reports`) before the bell; no "Admin" link. Steps 2–4: "403 | This action is unauthorized." each time, no admin layout or nav is shown. Step 4 shows the 403, not validation errors (Decision 11). Step 5: the same 403s for the restricted moderator. Each denial adds an `auth.permission_denied` line to `storage/logs/security-<today>.log` (`docker compose exec app tail -n 3 storage/logs/security-$(date -u +%F).log`).

### TC-P1-06-020: Regular user gets 403
- Priority: High · Type: Authorization
- Ref: specs/04 §2
- Preconditions: Signed in as `test_user`.
- Steps:
  1. Check the top bar for an "Admin" or "Reports" link.
  2. Type `/admin/audit` in the address bar.
- Expected: Neither link is shown. `/admin/audit` returns "403 | This action is unauthorized."

### TC-P1-06-021: Guest is sent to sign in
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1
- Preconditions: Signed out.
- Steps:
  1. Open `/admin/audit`.
- Expected: Redirect to `/login`. After signing in as `test_admin`, you land on `/admin/audit`.

### TC-P1-06-022: Restricted admin still reads the log
- Priority: High · Type: Authorization
- Ref: specs/04 §3 (read abilities stay open to restricted staff)
- Preconditions: `test_admin` set to restricted in Adminer (see setup). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/audit` and apply any filter.
- Expected: The log loads and filters normally; "Logs" stays in the nav.

### TC-P1-06-023: Pending-deletion admin still reads the log
- Priority: Medium · Type: Authorization
- Ref: specs/04 §3 / task Acceptance "Authorization"
- Preconditions: `test_admin` set to pending deletion in Adminer (see setup). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/audit`.
- Expected: The log loads; "Logs" is in the nav.

### TC-P1-06-024: Suspended staff are redirected to the suspension notice
- Priority: High · Type: Authorization
- Ref: specs/04 §1 / task Acceptance "Authorization" / owner decision 2026-10-02
- Preconditions: `test_admin` suspended (Adminer, or as `test_super_admin` via Users → `test_admin` → "Suspend"). Signed in as `test_admin`.
- Steps:
  1. Open `/admin/audit`.
  2. Repeat signed in as a suspended `test_super_admin` (Adminer only).
  3. Repeat signed in as a suspended `test_moderator` (Adminer only).
- Expected: All three redirect to `/account/suspended`, heading "Your account is suspended"; the suspended moderator gets the notice first, not the 403. The log is never shown.

### TC-P1-06-025: Banned staff are signed out
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1
- Preconditions: Signed in as `test_admin`. In Adminer set its `status` to `banned`.
- Steps:
  1. Reload `/admin/audit`.
- Expected: Redirect to `/login` with "This account is banned, so it cannot sign in." Signing in again is refused with the same message.

## Security

### TC-P1-06-026: Entries cannot be edited or deleted through the app
- Priority: High · Type: Security
- Ref: specs/12 §9 / task Acceptance "Data"
- Preconditions: Signed in as `test_super_admin`. Entries exist.
- Steps:
  1. Expand several rows and look for edit, delete or bulk controls.
  2. In the browser console on `/admin/audit` run `await fetch('/admin/audit', {method: 'DELETE'}).then(r => r.status)` and the same with `'PUT'` and `'POST'`.
- Expected: No edit or delete control anywhere on the page. Each request returns `405` (no write route exists).

### TC-P1-06-027: The database rejects UPDATE, DELETE and TRUNCATE on `audit_logs`
- Priority: High · Type: Security
- Ref: Open question 2 / Decision 1 / specs/07 `audit_logs`
- Preconditions: Adminer open on the app database; at least one entry.
- Steps:
  1. Run `UPDATE audit_logs SET action = 'role.changed' WHERE id = (SELECT MIN(id) FROM audit_logs);`
  2. Run `DELETE FROM audit_logs WHERE id = (SELECT MIN(id) FROM audit_logs);`
  3. Run `TRUNCATE audit_logs;`
- Expected: Each statement fails with "audit_logs is append-only"; the row count is unchanged.

### TC-P1-06-028: No raw IP or `ip_hash` in the page props
- Priority: High · Type: Security
- Ref: specs/11 "Data exposure via page props", §5 / NFR-SEC-6
- Preconditions: Entries from a web action (a sanction) exist. Signed in as `test_admin`. DevTools open on the Network tab.
- Steps:
  1. Load `/admin/audit`. Open the second (deferred) request to `/admin/audit` (request header `X-Inertia-Partial-Data: log`) and view its JSON response.
  2. Search the response and the page source for `ip_hash`, `ip`, and your machine's IP (e.g. `172.` / `192.168.`).
  3. In Adminer look at the same row's `ip_hash`.
- Expected: The props carry `id`, `action`, `actionLabel`, `actorUsername`, `actorRoleLabel`, `actorVia`, `subjectLabel`, `subjectName`, `before`, `after`, `context`, `userAgent`, `requestId`, `createdAt`, and no IP field or IP value. In the DB, `ip_hash` holds a hash, not a readable IP address.

### TC-P1-06-029: HTML in before / after, context and user agent renders as text
- Priority: High · Type: Security
- Ref: task Tests "XSS payload" / specs/18 §4 DiffViewer
- Preconditions: Signed in as `test_admin`. In DevTools → Network conditions, set a custom user agent `<img src=x onerror=alert('ua')>`.
- Steps:
  1. Suspend `test_user` with message `<script>alert('reason')</script>`, then lift it with "Why lift it" `<b>bold</b><img src=x onerror=alert('note')>`.
  2. Open `/admin/audit` and expand both entries.
- Expected: No alert fires. The diff shows the `reason` value and the context `note` as literal text including the angle brackets; "Browser" shows the user agent as literal text. Nothing is rendered bold.

### TC-P1-06-030: First response carries only the filters (deferred entries)
- Priority: Low · Type: Security
- Ref: Decision 10 / specs/18 §6
- Preconditions: Signed in as `test_admin`, DevTools Network open.
- Steps:
  1. Reload `/admin/audit` and inspect the first document response's `data-page` JSON.
- Expected: The first response has `filters` and `actions` but no `log`; the table shows skeleton rows until the deferred request returns `log`.

## Edge cases

### TC-P1-06-031: Renamed account: filters match the current username only
- Priority: Medium · Type: Edge case
- Ref: specs/23 §1 / task Scope "Queries"
- Preconditions: `test_user` renamed to `qa_renamed` (TC-P1-06-004 step 5) and has older role-change entries.
- Steps:
  1. Filter Account = `test_user`, apply.
  2. Filter Account = `qa_renamed`, apply.
- Expected: Step 1: "No entries match these filters." Step 2: every entry about the account, including those written before the rename; the Account column shows `qa_renamed` on all of them.

### TC-P1-06-032: Entry whose account no longer exists
- Priority: Low · Type: Edge case
- Ref: Decision 9
- Preconditions: Register a throwaway account that never acted; give it a role change by console (`platform:assign-role <name> moderator`). In Adminer, delete that `users` row (it has no entries as actor). If another foreign key refuses the delete, skip this case: the app never hard-deletes accounts.
- Steps:
  1. Open `/admin/audit`.
- Expected: The entry still shows; its Account cell reads "Account no longer exists" in muted text.

### TC-P1-06-033: Timestamps are stored in UTC
- Priority: Low · Type: Edge case
- Ref: specs/23 §9 "Clock skew"
- Preconditions: Your machine's timezone is not UTC. One new entry written now.
- Steps:
  1. Compare the entry's "When" cell with `created_at` in Adminer.
- Expected: `created_at` is the current UTC time; "When" shows the same instant in your local time (the `<time datetime>` attribute is the UTC ISO value).

## UI states

### TC-P1-06-034: Loading skeleton keeps the column widths
- Priority: Medium · Type: UI state
- Ref: specs/18 §4 DataTable / task Scope "States"
- Preconditions: Signed in as `test_admin`. DevTools Network throttling set to "Slow 3G".
- Steps:
  1. Reload `/admin/audit`.
  2. Apply a filter.
- Expected: While the rows load, the table header stays and five skeleton rows fill the columns at their final widths (no jump when rows arrive); a screen reader hears "Loading". During a filter visit the "Apply filters" button shows its loading state.

### TC-P1-06-035: Inline error with request id when the rows fail
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 / Review fixes "skeleton hidden while the error shows"
- Preconditions: Signed in as `test_admin` on `/admin/audit`. In Adminer run `ALTER TABLE audit_logs RENAME TO audit_logs_qa;`
- Steps:
  1. Reload the page.
  2. In Adminer run `ALTER TABLE audit_logs_qa RENAME TO audit_logs;`, then click "Try again".
- Expected: Step 1: a red alert "The audit log didn't load" with "Try again in a moment. If it keeps failing, quote request id <id>." and a "Try again" button; no skeleton under it. Step 2: the alert goes and the entries load.

### TC-P1-06-036: Too many searches shows a wait warning
- Priority: Medium · Type: UI state
- Ref: P1-12 Decision 11 / `platform.rate_limits.admin_search_per_minute` (60)
- Preconditions: Signed in as `test_admin` on `/admin/audit`.
- Steps:
  1. In the browser console run `for (let i = 0; i < 60; i++) await fetch('/admin/audit');`
  2. Click "Apply filters".
  3. Wait one minute, click "Try again".
- Expected: Step 2: a warning alert "Too many searches in a minute" with "Wait a moment, then try again." and a "Try again" button. Step 3: the log loads. (Full limiter coverage is in P1-12.)

### TC-P1-06-037: 375 px layout and keyboard access
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States" / specs/18 §6
- Preconditions: Signed in as `test_admin`, entries exist.
- Steps:
  1. In DevTools device mode set width 375 px and reload `/admin/audit`.
  2. Tap "Menu", then "Logs".
  3. On desktop, use only the keyboard: Tab through the filter bar, into the table region, then to a row toggle; press Enter, then Enter again.
- Expected: Step 1: the nav is folded behind "Menu"; filters stack in one column; the table scrolls horizontally inside its own bordered area and the page itself has no horizontal scroll. Step 2: the open menu lists Dashboard, Users, Logs and, last, "Back to site"; it closes after the pick. Step 3: every control has a visible focus ring; the table region takes focus and scrolls with arrow keys; the header stays sticky; Enter toggles the detail and `aria-expanded` flips between `false` and `true`.

### TC-P1-06-038: Long log from 768 px: only the content column scrolls
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 Admin / owner decision 2026-10-02
- Preconditions: More than one page (52 entries, see setup). Signed in as `test_admin`. Browser window at desktop width (also repeat at exactly 768 px wide in DevTools device mode).
- Steps:
  1. Open `/admin/audit` and wait for the 50 rows.
  2. Scroll down with the mouse wheel over the table, all the way to the end.
  3. Scroll with the mouse over the left sidebar.
  4. In the console run `[document.documentElement.scrollHeight, innerHeight, scrollY]`.
- Expected: Step 2: the rows and the "Older" button move up while the top bar ("Admin" beside the wordmark) and the left sidebar (Dashboard · Users · Logs, "Back to site" at its bottom) stay where they are. The footer ("Clash Commons" and the Supercell Fan Content Policy line) appears only at the end of the content column, after the "Older" button, never across the sidebar. Step 3: the content does not move. Step 4: the first two numbers are equal and `scrollY` is `0` (the page itself never scrolls). The scrollbar sits on the content column, not on the browser window.
