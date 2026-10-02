# P1-07 Notifications v1: bell, notification centre, in-app notices: test cases

Source: tasks/phase-1/P1-07-notifications-v1.md (Scope, Open questions 1–4, Decisions 1–11, Review fixes); specs/16 §1, §2, §6, §7; specs/04 §3; specs/18 §6.

## Happy path: the bell

### TC-P1-07-001: The bell shows for signed-in accounts only, without a badge at zero
- Priority: High · Type: UI state
- Ref: FR-NOTIF-1; specs/16 §6; Decision 6
- Preconditions: fresh seed; `test_user` has no notifications.
- Steps:
  1. Signed out, open `/`.
  2. Sign in as `test_user`; look at the header.
  3. Inspect the bell link in DevTools (Elements or Accessibility pane).
- Expected: step 1 no bell. Step 2 a bell sits before the account controls, with no count badge. Step 3 the link's accessible name is "Notifications" and it points to `/notifications`.

### TC-P1-07-002: The badge shows the unread count and announces it
- Priority: High · Type: Functional
- Ref: FR-NOTIF-1; specs/18 §8 (count live region)
- Preconditions: signed in as `test_user`; create 3 unread notices with this command (later cases call it "the helper" and change the username, type or count):
  ```
  docker compose exec app php artisan tinker --execute='$u = App\Models\User::where("username", "test_user")->first(); $n = app(App\Domain\Notifications\Services\Notifier::class); foreach (range(1, 3) as $i) { $n->send($u, new App\Domain\Notifications\Data\InAppMessageData(App\Domain\Notifications\Enums\NotificationType::PasswordChanged)); }'
  ```
- Steps:
  1. Reload any page.
  2. Inspect the bell and the polite live region next to it.
- Expected: a gold badge reads "3"; the link's accessible name is "Notifications, 3 unread"; the live region (`aria-live="polite"`) reads "3 unread notifications".

### TC-P1-07-003: The badge caps at 99+
- Priority: Medium · Type: UI state
- Ref: specs/16 §6; task Scope "count capped 99+"
- Preconditions: signed in as `test_user`; create 100 unread notices with the helper (`range(1, 100)`).
- Steps:
  1. Reload any page.
- Expected: the badge reads "99+"; the accessible name is "Notifications, 99+ unread". The centre lists the real rows (25 per page).

### TC-P1-07-004: The count refreshes every 60 seconds without a reload
- Priority: High · Type: Functional
- Ref: specs/16 §6; Decision 6 (`usePoll`, partial reload of `unreadCount`)
- Preconditions: signed in as `test_user` on `/settings/profile`, badge hidden; DevTools Network open.
- Steps:
  1. Wait and watch the requests for two minutes.
  2. While waiting, create 1 notice with the helper.
- Expected: about every 60 s a request to the current URL carries `X-Inertia-Partial-Data: unreadCount`; within one poll after step 2 the badge shows "1" with no page reload and no other prop changing on the page.

### TC-P1-07-005: The poll stops while the tab is hidden
- Priority: Medium · Type: Functional
- Ref: specs/16 §6; Decision 6
- Preconditions: as TC-P1-07-004.
- Steps:
  1. Switch to another browser tab for three minutes; come back and keep watching Network.
- Expected: no `unreadCount` poll request is sent while the tab is hidden; polling resumes after you return.

### TC-P1-07-006: The bell opens the notification centre
- Priority: Medium · Type: Functional
- Ref: Open question 3 (v1 bell is a link, no dropdown)
- Preconditions: signed in as `test_user`.
- Steps:
  1. Click the bell.
  2. Inspect the bell on `/notifications`.
- Expected: `/notifications` opens (page title "Notifications"); no dropdown appears; on that page the bell has `aria-current="page"`.

## Happy path: the notification centre

### TC-P1-07-007: Rows are newest first, with unread rows marked
- Priority: High · Type: Functional
- Ref: FR-NOTIF-1; specs/18 §6; Decision 2
- Preconditions: signed in as `test_user`; create 1 notice with the helper, wait a minute, create another; in Adminer set `read_at` = now() on the older one.
- Steps:
  1. Open `/notifications`.
- Expected: the newest row is first. The unread row has the gold left border, a raised background and a "New" label before the title; the read row has none of these. Each row shows its title, body and a date and time.

### TC-P1-07-008: Category tabs filter the list
- Priority: High · Type: Functional
- Ref: Decision 4 (tabs All · Security · Bases)
- Preconditions: `test_user` has a security notice (password changed) and a bases notice (TC-P1-07-021 step 1).
- Steps:
  1. Open `/notifications`; note the tabs.
  2. Click "Security", then "Bases", then "All".
- Expected: the tabs are "All", "Security", "Bases" (no Moderation tab). "Security" (`?category=security`) lists only the password notice; "Bases" (`?category=bases`) only the upload notice; "All" both. The active tab has `aria-current="page"`.

### TC-P1-07-009: Empty states
- Priority: Medium · Type: UI state
- Ref: task Scope "empty 'You're all caught up'"
- Preconditions: signed in as an account with no notifications (for example `test_moderator`).
- Steps:
  1. Open `/notifications`.
  2. Open the "Bases" tab.
- Expected: step 1 shows the bell-with-check illustration, "You're all caught up" and "Security alerts and updates about your uploads will show up here."; no "Mark all as read" button. Step 2 shows "Nothing in Bases" and "Notifications in this category will show up here."

### TC-P1-07-010: Cursor pages of 25
- Priority: Medium · Type: Functional
- Ref: FR-NOTIF-1 (pagination); config `platform.notifications.per_page` = 25; Decision 9
- Preconditions: `test_user` has 30 notices (helper with `range(1, 30)`).
- Steps:
  1. Open `/notifications`; count the rows.
  2. Press "Older"; then "Newer".
- Expected: page 1 has 25 rows and only an "Older" button; "Older" shows the remaining 5 (URL has `cursor=`) and only a "Newer" button; "Newer" returns the first 25. No row repeats or is skipped.

### TC-P1-07-011: Opening a notice with a target marks it read and goes there
- Priority: High · Type: Functional
- Ref: Decision 7
- Preconditions: `test_user` has one unread "Your password was changed" notice; badge "1".
- Steps:
  1. On `/notifications` click the row.
  2. Go back to `/notifications`.
- Expected: the browser lands on `/settings/security`; the badge disappears there at once (the count cache is cleared on read). Back on the list the row has no gold border or "New" label and is still clickable (it has a target). In Adminer the row has `read_at` set.

### TC-P1-07-012: Opening a notice without a target marks it read and stays on the list
- Priority: High · Type: Functional
- Ref: Decision 7
- Preconditions: `test_user` has an unread "Your suspension is over" notice (TC-P1-07-019) or "Your email is confirmed" notice; the list scrolled a little.
- Steps:
  1. Click the row.
  2. Click it again.
- Expected: step 1 the page stays on `/notifications` with the scroll position kept; the row loses the unread styling and the badge count drops by one. Step 2 nothing happens: a read row with no target is plain text, not a button.

### TC-P1-07-013: Mark all as read
- Priority: High · Type: Functional
- Ref: FR-NOTIF-1 (mark-all-read); Decision 7
- Preconditions: `test_user` has unread security notices and an unread bases notice; open the "Security" tab.
- Steps:
  1. Press "Mark all as read" (watch the label while it runs).
  2. Open the "Bases" and "All" tabs.
  3. In DevTools look at the Inertia response of the visit after the POST.
- Expected: the button shows "Marking…" while the request runs, then disappears; every row on every tab is read, including the Bases one; the bell badge is gone. The response props carry `flash.success` = "All notifications marked as read." (no toast shows it in v1).

## In-app notices per event

### TC-P1-07-014: Password changed writes a notice; the email is unchanged
- Priority: High · Type: Functional
- Ref: specs/16 §2 "Password changed" (I + E*); Decision 3
- Preconditions: signed in as `test_user`; Mailpit cleared; queue running.
- Steps:
  1. On `/settings/security` change the password (current `password`, a new strong password).
  2. Open `/notifications`; check Mailpit.
- Expected: one new unread row "Your password was changed", "Every other device was signed out. If you did not change it, reset your password now.", in the Security tab; clicking it opens `/settings/security`. Mailpit has the usual "Your Clash Commons password was changed" email.

### TC-P1-07-015: A sign-in from an unrecognised device writes a notice, the first sign-in does not
- Priority: High · Type: Functional
- Ref: specs/16 §2 "New sign-in"; Decision 3
- Preconditions: fresh seed (test_user has never signed in); Mailpit cleared.
- Steps:
  1. In browser A sign in as `test_user` for the first time; open `/notifications`.
  2. In a private window (no `known_devices` cookie) sign in as `test_user` again.
  3. In browser A open `/notifications`.
- Expected: step 1 the email "First sign-in to your Clash Commons account" arrives but the centre is empty. After step 2 the email "New sign-in to your Clash Commons account" arrives and the centre shows "New sign-in to your account", "From a device we have not seen before: <browser> on <system>. If it was not you, sign that device out and change your password." (a country is added after a comma when known), linking to `/settings/security`.

### TC-P1-07-016: Email confirmed writes a notice
- Priority: Medium · Type: Functional
- Ref: specs/16 §2 (Security); WriteInAppNotice
- Preconditions: register a new account `qa_notif` (P1-08 flow), unverified.
- Steps:
  1. Open the verification link from Mailpit and confirm.
  2. Sign in as qa_notif if needed; open `/notifications`.
- Expected: one row "Your email is confirmed", "Thanks for confirming. Your account is all set.", with no link.

### TC-P1-07-017: Account suspended writes a notice with the end and reason
- Priority: High · Type: Functional
- Ref: specs/16 §2 "Account suspended" (I + E*); P1-14
- Preconditions: `test_admin` suspends `test_user` for 7 days with message `Posting invite spam in profiles.`
- Steps:
  1. As test_user open `/notifications` (allowed while suspended).
  2. Click the row.
- Expected: the row reads "Your account is suspended", "Until <d Month YYYY> at <HH:MM> UTC. Reason: Posting invite spam in profiles." (the UTC end matches the email); clicking it opens `/account/suspended`.

### TC-P1-07-018: Account banned writes a notice, seen after the ban is lifted
- Priority: High · Type: Functional
- Ref: specs/16 §2 "Account banned"; AccountBannedNotification
- Preconditions: `test_admin` bans `test_user` with message `Selling fake gem codes.`, then lifts the ban.
- Steps:
  1. Sign in as test_user; open `/notifications`.
- Expected: a row "Your account is banned", "Reason: Selling fake gem codes.", not a link once read; above it (newer) the "Your ban is over" row (TC-P1-07-019).

### TC-P1-07-019: A lifted sanction writes "over" notices
- Priority: High · Type: Functional
- Ref: specs/16 §2 "Sanction lifted / expired"
- Preconditions: as TC-P1-07-017, then test_admin lifts the suspension; separately TC-P1-07-018 for a ban.
- Steps:
  1. As test_user open `/notifications`.
- Expected: after a suspension lift: "Your suspension is over", "It has been lifted. Your account works as normal again." After a ban lift: "Your ban is over" with the same body. Neither has a link.

### TC-P1-07-020: An expired suspension writes an "ended" notice
- Priority: High · Type: Functional
- Ref: specs/16 §2; P1-14 Decision 4
- Preconditions: test_user suspended; `docker compose stop scheduler`; in Adminer move both `users.status_expires_at` and the open `user_sanctions.expires_at` to `now() - interval '1 minute'`.
- Steps:
  1. Run `docker compose exec app php artisan moderation:expire-sanctions`.
  2. As test_user open `/notifications`; then `docker compose start scheduler`.
- Expected: a row "Your suspension is over", "It has ended. Your account works as normal again."; running the command again adds no second row.

### TC-P1-07-021: Media processing failed writes a Bases notice
- Priority: Medium · Type: Functional
- Ref: specs/16 §2 "Media processing failed"; specs/10 §9; Open question 2
- Preconditions: signed in as `test_user`.
- Steps:
  1. Run `docker compose exec app php artisan tinker --execute='App\Domain\Media\Events\MediaRetriesExhausted::dispatch((string) Illuminate\Support\Str::ulid(), App\Models\User::where("username", "test_user")->value("id"), App\Domain\Media\Enums\MediaCollection::Avatar);'`
  2. Repeat with `MediaCollection::BaseScreenshot`.
  3. Open `/notifications` and the "Bases" tab; click each row.
- Expected: two rows in the Bases tab (not in Security): "An upload could not be processed" with "Your avatar failed to process after several tries. Upload it again." (opens `/settings/profile`) and "Your base screenshot failed to process after several tries. Upload it again." (no link; stays on the list).

### TC-P1-07-022: A row stores the type and parameters only
- Priority: Medium · Type: Functional
- Ref: Decision 2 (rendered on read, nothing rendered stored)
- Preconditions: TC-P1-07-017 done.
- Steps:
  1. In Adminer open `notifications` for test_user's id (`notifiable_id`).
- Expected: one row per event; the suspension row has `type` = `account_suspended`, `data` = `{"params": {"reason": "...", "ends_at": "<ISO time>"}}`, `read_at` null until opened, a UUID `id`. No title, body, HTML or URL is stored.

## Access by account state

### TC-P1-07-023: Guests are sent to sign in
- Priority: High · Type: Authorization
- Ref: task Acceptance "guests to sign in"
- Preconditions: signed out.
- Steps:
  1. Open `/notifications`.
- Expected: redirect to `/login`; the shared `unreadCount` prop is null on public pages (no bell).

### TC-P1-07-024: Unverified, restricted and pending-deletion accounts can read and mark
- Priority: High · Type: Authorization
- Ref: Open question 4; Decision 8
- Preconditions: an unverified account (registered, link not opened) with two notices created by the helper; `test_user` with two unread notices.
- Steps:
  1. Sign in as the unverified account; open `/notifications`, click an unread row, press "Mark all as read".
  2. Sign in as `test_user`, then in Adminer set its `status` = `restricted`; create two notices; repeat step 1's actions.
  3. Still signed in (signing in again would cancel a pending deletion), set `status` = `pending_deletion`; create two notices; reload and repeat step 1's actions.
  4. Reset test_user to `active`.
- Expected: in every state the page opens, the bell shows the count, marking one and marking all work (no "not available" page).

### TC-P1-07-025: A suspended account keeps the notification centre
- Priority: High · Type: Authorization
- Ref: Decision 8 (`notifications.*` allowed while suspended)
- Preconditions: test_user suspended, with unread notices.
- Steps:
  1. Open `/notifications`, click a password notice, then go back and press "Mark all as read".
  2. Open `/` from the header.
- Expected: step 1 works as for an active account (the password notice opens `/settings/security`, which is reachable while suspended). Step 2 redirects to `/account/suspended`. The bell stays in the header on both pages.

### TC-P1-07-026: A banned account has no access
- Priority: Medium · Type: Authorization
- Ref: Open question 4 ("banned accounts are already signed out")
- Preconditions: test_user signed in on `/notifications` in browser B.
- Steps:
  1. As test_admin ban test_user.
  2. In browser B press "Mark all as read" or reload.
- Expected: browser B is signed out and ends on `/login`; no `read_at` changes.

## Security

### TC-P1-07-027: Another account's notification id is not found
- Priority: High · Type: Security
- Ref: specs/04 §3 (IDOR); Decision 8; Review fixes (staff roles 404)
- Preconditions: test_user has an unread notice; copy its `id` from Adminer. In the browser console define this helper (later cases call it `send`):
  ```js
  const send = (url, body = {}, method = 'POST') => fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]) }, body: JSON.stringify(body) }).then(async (r) => console.log(r.status, await r.text()));
  ```
- Steps:
  1. Sign in as `test_moderator`; run `send('/notifications/<test_user notice id>/read')`.
  2. Repeat as `test_super_admin`.
- Expected: both log 404; the row stays unread in Adminer; test_user's badge is unchanged.

### TC-P1-07-028: Malformed notification ids
- Priority: Medium · Type: Security
- Ref: task Tests "malformed id"
- Preconditions: signed in as `test_user`.
- Steps:
  1. `send('/notifications/123/read')`, `send('/notifications/not-a-uuid/read')`, `send('/notifications/00000000-0000-7000-8000-000000000000/read')`.
- Expected: all three log 404; no 500.

### TC-P1-07-029: Page props carry rendered rows only
- Priority: High · Type: Security
- Ref: specs/11 "Data exposure via page props"; task Acceptance "Data"
- Preconditions: signed in as `test_user` with a suspension and a password notice.
- Steps:
  1. Open `/notifications`; in DevTools read the page JSON (the `data-page` attribute of `#app`, or the Inertia XHR response after clicking a tab).
- Expected: each entry has only `id`, `category`, `title`, `body`, `hasTarget`, `read`, `createdAt`. No `data` / `params` blob, `notifiable_id`, `type`, target URL, or anything about another account.

### TC-P1-07-030: Forged cursors and unknown categories fall back to the first page
- Priority: Medium · Type: Security
- Ref: Decision 9; Review fixes (forged cursor was a 500)
- Preconditions: signed in as `test_user` with 30 notices.
- Steps:
  1. Open `/notifications?cursor=abc`.
  2. Open `/notifications?cursor=` followed by base64 of `{"created_at":"not-a-date","id":"x","_pointsToNextItems":true}`.
  3. Open `/notifications?category=moderation` and `/notifications?category=<script>`.
- Expected: each lands on `/notifications` showing the first page of All; no 500 and no error page.

### TC-P1-07-031: Writes are rate limited
- Priority: Low · Type: Security
- Ref: Decision 8 (`throttle:global-write`, 120 a minute)
- Preconditions: signed in as `test_user`.
- Steps:
  1. In the console run `for (let i = 0; i < 121; i++) await send('/notifications/read');`
- Expected: the first 120 succeed; the 121st logs 429 with "Too many changes. Wait a minute and try again."; the security log has an `auth.rate_limited` line for `global-write`.

### TC-P1-07-032: Text from staff is shown literally in the centre
- Priority: High · Type: Security
- Ref: specs/11 "XSS"; Decision 2 (never stored HTML)
- Preconditions: test_admin suspends test_user with message `<img src=x onerror=alert(1)> **bold**`.
- Steps:
  1. As test_user open `/notifications`.
- Expected: the body shows the characters exactly as typed after "Reason: "; no alert, no image, no bold.

## Edge cases

### TC-P1-07-033: A type the code no longer knows
- Priority: Medium · Type: Edge case
- Ref: Decision 2
- Preconditions: test_user has an unread notice.
- Steps:
  1. In Adminer change its `type` to `retired_type`.
  2. Open `/notifications`; click the row; click it again.
- Expected: the row reads "This notification is no longer available" with no body; it appears under All only; the first click marks it read and stays on the list; once read it is plain text.

### TC-P1-07-034: Missing parameters do not break a row
- Priority: Low · Type: Edge case
- Ref: NotificationType ("missing params tolerated")
- Preconditions: test_user has an `account_suspended` row and an `account_banned` row.
- Steps:
  1. In Adminer set both rows' `data` to `{"params": {}}`.
  2. Open `/notifications`.
- Expected: the suspension row reads "Your account is suspended", "You can still read your settings and notifications."; the ban row reads "Your account is banned", "You can no longer use Clash Commons."

### TC-P1-07-035: A suspension lifted before its notice ran writes no "suspended" row
- Priority: Medium · Type: Edge case
- Ref: task Edge cases; P1-14 Decision 17
- Preconditions: `docker compose stop queue`; test_user has no notifications.
- Steps:
  1. As test_admin suspend test_user, then lift the suspension.
  2. `docker compose start queue`; wait a few seconds; as test_user open `/notifications`.
- Expected: only "Your suspension is over" ("It has been lifted. …") appears; no "Your account is suspended" row.

### TC-P1-07-036: Database edits reach the badge within the cache lifetime
- Priority: Low · Type: Edge case
- Ref: Decision 5 (`notif:unread:{id}`, 60 s)
- Preconditions: signed in as `test_user` with 2 unread notices, badge "2".
- Steps:
  1. In Adminer set `read_at` = now() on both rows.
  2. Reload a page at once, then again after a minute.
- Expected: the badge may still read "2" right away (cached), and is gone within 60 s. Writes through the app (new notice, mark read, mark all) change it at once.

### TC-P1-07-037: Opening an already read notice with a target
- Priority: Low · Type: Edge case
- Ref: NotificationService::markRead
- Preconditions: a read "Your password was changed" row; badge shows other unread rows.
- Steps:
  1. Click the read row.
- Expected: `/settings/security` opens; the row's `read_at` does not change; the badge count is unchanged.

## Retention

### TC-P1-07-038: The prune is scheduled nightly and has a dry run
- Priority: Medium · Type: Functional
- Ref: specs/16 §7; specs/20 §3; Decision 10
- Preconditions: in Adminer, on test_user's rows: one read row with `created_at` = `now() - interval '91 days'`, one unread row with `created_at` = `now() - interval '181 days'`, one read row 89 days old, one unread row 179 days old.
- Steps:
  1. Run `docker compose exec app php artisan schedule:list`.
  2. Run `docker compose exec app php artisan notifications:prune --dry-run`.
- Expected: step 1 lists `notifications:prune` daily at 02:15. Step 2 prints "Would delete 1 read, 1 unread and 0 over-cap notifications." and every row is still in Adminer.

### TC-P1-07-039: The prune removes old rows and keeps recent ones
- Priority: Medium · Type: Functional
- Ref: specs/16 §7 (read > 90 d, unread > 180 d)
- Preconditions: TC-P1-07-038 data.
- Steps:
  1. Run `docker compose exec app php artisan notifications:prune`, then run it again.
- Expected: first run prints "Deleted 1 read, 1 unread and 0 over-cap notifications."; the 91-day read and 181-day unread rows are gone; the 89-day read and 179-day unread rows remain. Second run prints "Deleted 0 read, 0 unread and 0 over-cap notifications."

### TC-P1-07-040: The 500 cap trims the oldest read rows only
- Priority: Low · Type: Functional
- Ref: config `platform.notifications.max_per_user` = 500; Decision 10
- Preconditions: create 505 notices for `test_moderator` with the helper; mark all read; create 3 more (unread).
- Steps:
  1. Run `notifications:prune --dry-run`, then `notifications:prune`.
- Expected: both report 8 over-cap; afterwards test_moderator has 500 rows: the 3 unread rows and the newest 497 read rows; the oldest 8 read rows are gone.

## UI states

### TC-P1-07-041: Loading and error states
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States: list empty / loading / error"
- Preconditions: signed in as `test_user` on `/notifications` with rows.
- Steps:
  1. Set DevTools throttling to "Slow 3G"; click the "Security" tab.
  2. Set DevTools to "Offline"; click the "All" tab.
  3. Go back online; press "Try again".
- Expected: step 1 shows skeleton rows (labelled "Loading notifications") until the list loads. Step 2 shows the alert "Notifications didn't load", "Try again in a moment." and a "Try again" button. Step 3 reloads the list.

### TC-P1-07-042: 375 px layout
- Priority: Medium · Type: UI state
- Ref: task Acceptance "375 px and desktop"; Review fixes (tab row wraps)
- Preconditions: signed in as `test_user` with unread rows including a long reason.
- Steps:
  1. Set the viewport to 375 px; open `/notifications`.
  2. Check the header, the title row, the tabs, the rows and the paging buttons.
- Expected: the bell stays in the header with its badge; "Mark all as read" wraps under the title if needed; the tabs wrap onto a new line rather than scrolling (no vertical scroll inside the tab row); long text breaks inside the row; no horizontal page scroll; each tab and row is an easy tap target.
