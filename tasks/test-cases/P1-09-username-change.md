# P1-09 Username change and old-name redirects: test cases

Source: tasks/phase-1/P1-09-username-change.md; specs/07 `users` / `username_history`, specs/04 §3–§4, specs/11 "Account enumeration", specs/23 §1, FR-PROFILE-7, FR-AUTH-1.

Time is moved in Adminer (SQL command): `users.username_changed_at` for the 30-day wait
(`platform.auth.username_change_days`), `username_history.released_at` for the 90-day hold
(`platform.auth.username_reservation_days`). Check redirects in DevTools Network with "Preserve log" on, or with
`curl -sI http://localhost:8080/u/<name>`. Change visibility through `/settings/privacy`, not Adminer (cached).
Current-password guesses share the `password-confirm` limiter (5 a minute, 20 an hour), so run
`docker compose exec app php artisan cache:clear` between cases unless the case tests the limit. Console snippets need `const t = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);` first.

## Happy path

### TC-P1-09-001: Username card in its idle state
- Priority: High · Type: UI state
- Ref: task Scope "UI"; Decisions 7
- Preconditions: fresh seed; signed in as `test_user` (verified, never renamed).
- Steps:
  1. Open `/settings/profile` and find the "Username" card (between Avatar and the profile form).
- Expected: Text "You are @test_user. You can change it once every 30 days. Your old username stays yours for 90 days, and links to it lead to your profile until then." A "New username" field with an `@` prefix and hint "3 to 20 lowercase letters, numbers or underscores.", a "Current password" field with a "Show password" button inside it (TC-P1-09-042), and a "Change username" button.

### TC-P1-09-002: Change the username
- Priority: High · Type: Functional
- Ref: FR-PROFILE-7; task Scope "Domain", Decisions 3 and 6; specs/18 §6 Toast (owner decision 2026-10-02)
- Preconditions: as TC-P1-09-001.
- Steps:
  1. New username `clash_chief`, current password `password`, click "Change username".
  2. Look at the card, the header and `/u/clash_chief`.
  3. In Adminer: `SELECT username, username_changed_at FROM users WHERE email = 'test@example.com';`, `SELECT * FROM username_history ORDER BY id DESC LIMIT 1;`, `SELECT action, before, after, actor_id FROM audit_logs ORDER BY id DESC LIMIT 1;`
  4. Check Mailpit and `docker compose exec app tail -n 3 storage/logs/security-$(date +%F).log`.
- Expected: A success toast "Username changed to @clash_chief. Links to your old name lead here for 90 days." appears bottom right and closes by itself after about 5 s. The fields clear; the card now reads "You are @clash_chief." and shows the locked line instead of the form. The header menu shows `clash_chief`. `/u/clash_chief` renders the profile. DB: `username` = `clash_chief`, `username_changed_at` = now; a history row (`test_user`, `released_at` = now, `reserved_forever` false); an audit row `user.username_changed`, before `test_user`, after `clash_chief`, actor = this account. Security log line `auth.username_changed` with `from` / `to`. No email in Mailpit.

### TC-P1-09-003: Input is trimmed and lowercased
- Priority: Medium · Type: Functional
- Ref: task Scope `ChangeUsernameRequest`
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. New username `  Clash_Chief2  ` (spaces and capitals), password `password`, submit.
- Expected: Accepted with the toast "Username changed to @clash_chief2. Links to your old name lead here for 90 days."; the card shows `@clash_chief2` and `users.username` = `clash_chief2`.

### TC-P1-09-004: The old URL redirects with 301 and no-store
- Priority: High · Type: Functional
- Ref: FR-PROFILE-7; task Open question 3, Decisions 4
- Preconditions: TC-P1-09-002 done (`test_user` → `clash_chief`), profile "Everyone".
- Steps:
  1. As a guest open `http://localhost:8080/u/test_user` with Network "Preserve log" on.
  2. Inspect the first request.
- Expected: `/u/test_user` answers 301 with `Location: http://localhost:8080/u/clash_chief` and `Cache-Control: no-store`; the browser lands on the `clash_chief` profile (200).

### TC-P1-09-005: The change shows in the admin audit log
- Priority: Medium · Type: Functional
- Ref: task Open question 4; specs/07 `audit_logs`
- Preconditions: TC-P1-09-002 done.
- Steps:
  1. Sign in as `test_admin`, open `/admin/audit` and find the newest entry.
- Expected: A "Username changed" entry (`user.username_changed`) for the account, actor the account itself, with before `test_user` and after `clash_chief`.

## Validation

### TC-P1-09-006: Required fields
- Priority: Medium · Type: Validation
- Ref: task Scope `ChangeUsernameRequest`
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. Click "Change username" with both fields empty.
- Expected: "The new username field is required." and "The current password field is required."; focus moves to "New username"; nothing changes.

### TC-P1-09-007: Length limits
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-1 (3–20)
- Preconditions: as TC-P1-09-006.
- Steps:
  1. New username `ab`, password `password`, submit.
  2. Try typing 25 characters; then remove the field's `maxlength` in DevTools, enter `abcdefghijklmnopqrstu` (21), submit.
- Expected: Step 1: "The new username field must be at least 3 characters." Step 2: typing stops at 20; after removing `maxlength`: "The new username field must not be greater than 20 characters." The password field is cleared after each error.

### TC-P1-09-008: Allowed characters only
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-1
- Preconditions: as TC-P1-09-006.
- Steps:
  1. Submit `clash-chief`, then `clash chief`, then `clásh`, each with password `password`.
- Expected: Each: "Use lowercase letters, numbers and underscores only."

### TC-P1-09-009: Reserved names
- Priority: Medium · Type: Validation
- Ref: `platform.auth.reserved_usernames`
- Preconditions: as TC-P1-09-006.
- Steps:
  1. Submit `admin`, then `Settings`, then `support`.
- Expected: Each: "That username is reserved. Pick another."

### TC-P1-09-010: Name used by another account
- Priority: High · Type: Validation
- Ref: FR-AUTH-1 (unique, case-insensitive)
- Preconditions: as TC-P1-09-006.
- Steps:
  1. Submit `test_admin`, then `TEST_ADMIN`.
- Expected: Each: "That username is taken. Pick another."

### TC-P1-09-011: Own current name
- Priority: Medium · Type: Validation
- Ref: task Tests "same name"
- Preconditions: as TC-P1-09-006.
- Steps:
  1. Submit `test_user`, then `TEST_USER`, with password `password`.
- Expected: Each: "That is already your username."; `username_changed_at` stays NULL (the 30-day wait does not start).

### TC-P1-09-012: Wrong current password
- Priority: High · Type: Validation
- Ref: task Open question 1
- Preconditions: as TC-P1-09-006.
- Steps:
  1. New username `clash_chief`, password `wrong-password`, submit.
  2. Check `users.username` and the security log.
- Expected: "That is not your current password." under "Current password"; the password field is cleared, the username stays filled. Nothing changes. Security log `auth.password_confirm_failed`.

## 30-day lock

### TC-P1-09-013: Locked card after a change
- Priority: High · Type: UI state
- Ref: FR-PROFILE-7; task Scope "UI" (locked state)
- Preconditions: TC-P1-09-002 done on 2 October 2026 (or note the actual date).
- Steps:
  1. Reload `/settings/profile`.
- Expected: No form. The line "You can change your username again on <date and time>" shows the change time plus 30 days in the browser's locale and timezone (e.g. "November 1, 2026 at …" for en-US).

### TC-P1-09-014: Lock boundary moved in Adminer
- Priority: High · Type: Edge case
- Ref: `platform.auth.username_change_days` 30; task Acceptance "one change per 30 days"
- Preconditions: signed in as the renamed account (`clash_chief`).
- Steps:
  1. `UPDATE users SET username_changed_at = now() - interval '29 days' WHERE username = 'clash_chief';` reload `/settings/profile`.
  2. `UPDATE users SET username_changed_at = now() - interval '31 days' WHERE username = 'clash_chief';` reload.
  3. Change to `clash_chief_b` with the right password.
- Expected: Step 1: locked, the date about one day ahead. Step 2: the form is back. Step 3: succeeds and the card locks again for 30 days from now.

### TC-P1-09-015: Server re-checks the wait (stale tab)
- Priority: High · Type: Security
- Ref: task Acceptance "the server re-checks the 30-day rule"
- Preconditions: fresh seed; `test_user` signed in with `/settings/profile` open in two tabs.
- Steps:
  1. Tab A: change to `clash_chief` (success).
  2. Tab B (not reloaded, form still shown): new username `clash_chief_c`, password `password`, submit.
- Expected: Tab B shows "You can change your username again on <d Month yyyy>." (e.g. "1 November 2026.") under "New username"; the username stays `clash_chief`.

## 90-day hold

### TC-P1-09-016: Another account cannot take a held name
- Priority: High · Type: Functional
- Ref: FR-PROFILE-7; specs/23 §1
- Preconditions: TC-P1-09-002 done (`test_user` released less than 90 days ago).
- Steps:
  1. Sign in as `test_moderator`, open `/settings/profile`, change to `test_user` with password `password`.
- Expected: "That username is taken. Pick another."; nothing changes.

### TC-P1-09-017: Registration cannot take a held name
- Priority: High · Type: Functional
- Ref: task Scope `RegistrationService`; Acceptance "a registration racing a release cannot take the held name"
- Preconditions: TC-P1-09-002 done; signed out.
- Steps:
  1. At `/register` enter username `test_user`, a new email and a valid password; wait a few seconds; submit.
- Expected: "That username is taken. Pick another." under Username; no account created.

### TC-P1-09-018: The owner may take the old name back
- Priority: High · Type: Functional
- Ref: task Open question 2
- Preconditions: TC-P1-09-002 done; signed in as `clash_chief`; `UPDATE users SET username_changed_at = now() - interval '31 days' WHERE username = 'clash_chief';`
- Steps:
  1. Change back to `test_user` with the right password.
  2. As a guest open `/u/test_user` and `/u/clash_chief`.
- Expected: Step 1 succeeds; the card locks again. `/u/test_user` renders the profile directly (200). `/u/clash_chief` answers 301 to `/u/test_user` (a new history row for `clash_chief`).

### TC-P1-09-019: After 90 days the old URL 404s and the name is free
- Priority: High · Type: Edge case
- Ref: FR-PROFILE-7; specs/23 §1 "Username released and immediately re-registered"
- Preconditions: TC-P1-09-002 done (`test_user` → `clash_chief`).
- Steps:
  1. `UPDATE username_history SET released_at = now() - interval '91 days' WHERE username = 'test_user';`
  2. As a guest open `/u/test_user`.
  3. Sign in as `test_moderator` and change to `test_user`.
  4. As a guest open `/u/test_user` again.
- Expected: Step 2: the "We couldn't find that profile" 404, no redirect. Step 3 succeeds. Step 4 shows the moderator's profile (now `@test_user`), not a redirect to `clash_chief`.

### TC-P1-09-020: Day 89 is still inside the hold
- Priority: Medium · Type: Edge case
- Ref: `platform.auth.username_reservation_days` 90
- Preconditions: TC-P1-09-002 done.
- Steps:
  1. `UPDATE username_history SET released_at = now() - interval '89 days' WHERE username = 'test_user';`
  2. As a guest open `/u/test_user`; as `test_moderator` try to change to `test_user`.
- Expected: The URL still answers 301 to `/u/clash_chief`; the moderator gets "That username is taken. Pick another."

### TC-P1-09-021: A deleted account's name is held forever
- Priority: Medium · Type: Edge case
- Ref: specs/08 §6; specs/23 §1 "A deleted account's original username is requested again"
- Preconditions: in Adminer `INSERT INTO username_history (user_id, username, released_at, reserved_forever, created_at, updated_at) VALUES ((SELECT id FROM users WHERE username = 'test_admin'), 'gone_forever', now() - interval '400 days', true, now(), now());`; signed in as `test_user`.
- Steps:
  1. Change to `gone_forever`.
  2. Register a new account with username `gone_forever`.
  3. As a guest open `/u/gone_forever`.
- Expected: Steps 1–2: "That username is taken. Pick another." Step 3: the 404 page, no redirect.

## Redirects

### TC-P1-09-022: A chain of renames goes straight to the current name
- Priority: High · Type: Functional
- Ref: task Acceptance "a chain (a → b → c) sends `/u/a` and `/u/b` to `/u/c`"
- Preconditions: TC-P1-09-002 done (`test_user` → `clash_chief`); then `UPDATE users SET username_changed_at = now() - interval '31 days' WHERE username = 'clash_chief';` and change to `clash_chief_c`.
- Steps:
  1. As a guest open `/u/test_user` and `/u/clash_chief` with "Preserve log" on.
- Expected: Each answers one 301 directly to `/u/clash_chief_c` (no hop through `/u/clash_chief`).

### TC-P1-09-023: Redirect to a private profile is a 404 for others
- Priority: High · Type: Security
- Ref: task Acceptance "Enumeration"; Open question 3
- Preconditions: TC-P1-09-002 done; as `clash_chief` set visibility "Only me".
- Steps:
  1. Open `/u/test_user` as a guest and as `test_moderator`.
  2. Open it as `clash_chief`.
- Expected: Step 1: the same 404 page as an unknown name; no `Location` header, nothing naming `clash_chief`. Step 2: 301 to `/u/clash_chief` (the owner can see their own profile).

### TC-P1-09-024: Redirect to a members-only profile
- Priority: High · Type: Security
- Ref: task Tests "members-only for guest → 404"
- Preconditions: TC-P1-09-002 done; `clash_chief` visibility "Signed-in members".
- Steps:
  1. Open `/u/test_user` as a guest.
  2. Open it as `test_moderator`.
- Expected: Guest: 404 page. Signed-in member: 301 to `/u/clash_chief`.

### TC-P1-09-025: Redirect target banned, pending deletion or suspended
- Priority: High · Type: Security
- Ref: task Acceptance "a redirect never reveals a hidden, banned or pending-deletion account"
- Preconditions: TC-P1-09-002 done; `clash_chief` public.
- Steps:
  1. `UPDATE users SET status = 'banned' WHERE username = 'clash_chief';` open `/u/test_user` as a guest.
  2. `UPDATE users SET status = 'pending_deletion' WHERE username = 'clash_chief';` open it again.
  3. `UPDATE users SET status = 'suspended', status_expires_at = NULL WHERE username = 'clash_chief';` open it again.
  4. Reset the status to `active`.
- Expected: Steps 1–2: the 404 page. Step 3: 301 to `/u/clash_chief` (suspended profiles stay visible).

### TC-P1-09-026: Hidden-target 404 matches an unknown-name 404
- Priority: High · Type: Security
- Ref: task Tests "same 404 body for hidden-target redirect and unknown name"
- Preconditions: as TC-P1-09-023 (target "Only me").
- Steps:
  1. As a guest view source of `/u/test_user` and of `/u/nobody_here`; note each status in Network.
- Expected: Both 404, title "Profile not found · Clash Commons", `noindex, nofollow`, component `Profile/NotFound`; identical apart from the requested path.

### TC-P1-09-027: The redirect is not cached by the browser
- Priority: Medium · Type: Edge case
- Ref: task Open question 3 (`Cache-Control: no-store`)
- Preconditions: TC-P1-09-002 done; `clash_chief` public; a guest window that has already followed `/u/test_user` → `/u/clash_chief`.
- Steps:
  1. As `clash_chief` set visibility "Only me".
  2. In the guest window open `/u/test_user` again (address bar, not back button).
- Expected: The guest now gets the 404 page; the browser did not reuse the earlier 301.

### TC-P1-09-028: Old URL in other letter case still redirects
- Priority: Low · Type: Edge case
- Ref: specs/07 `username_history` (case-insensitive)
- Preconditions: TC-P1-09-002 done; `clash_chief` public.
- Steps:
  1. As a guest open `/u/TEST_USER`.
- Expected: 301 to `/u/clash_chief`.

## Authorization / account status

### TC-P1-09-029: Restricted account can change
- Priority: High · Type: Authorization
- Ref: specs/04 §3; task Acceptance "restricted can change"
- Preconditions: fresh seed; `UPDATE users SET status = 'restricted', status_expires_at = NULL WHERE username = 'test_user';` signed in as `test_user`.
- Steps:
  1. Change to `clash_chief` with the right password.
  2. Reset the status to `active`.
- Expected: Succeeds as in TC-P1-09-002.

### TC-P1-09-030: Unverified email blocks the change
- Priority: High · Type: Authorization
- Ref: task Open question 6
- Preconditions: fresh seed; signed in as `test_user` with `/settings/profile` open in two tabs; then `UPDATE users SET email_verified_at = NULL WHERE username = 'test_user';`
- Steps:
  1. Reload tab A.
  2. In tab B (form still shown) submit `clash_chief` with the right password.
  3. Restore `email_verified_at = now()`.
- Expected: Tab A: no form; "Confirm your email address before changing your username." with the link "Send the confirmation email" to `/email/verify`. Tab B: the request is refused (403) and the username is unchanged.

### TC-P1-09-031: Suspended account cannot change
- Priority: High · Type: Authorization
- Ref: specs/04 §1, §3; task Review fixes "a suspended account saw the card with no reason"
- Preconditions: fresh seed; `/settings/profile` open in two tabs as `test_user`; then `UPDATE users SET status = 'suspended', status_expires_at = NULL WHERE username = 'test_user';`
- Steps:
  1. Reload tab A.
  2. In tab B submit `clash_chief` with the right password.
  3. Reset the status to `active`.
- Expected: Tab A: "Username changes are unavailable while your account is suspended." and no form. Tab B: 403 page "Your account is suspended" / "Changes are off until the suspension ends."; nothing changes.

### TC-P1-09-032: Pending-deletion account cannot change
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1, §3
- Preconditions: fresh seed; `/settings/profile` open as `test_user`; then `UPDATE users SET status = 'pending_deletion', deletion_requested_at = now(), deletion_previous_status = 'active' WHERE username = 'test_user';`
- Steps:
  1. Without reloading, submit `clash_chief` with the right password.
  2. Reset: `status = 'active', deletion_requested_at = NULL, deletion_previous_status = NULL`.
- Expected: 403 page "Your account is scheduled for deletion" / "Changes are off while the deletion is pending."; nothing changes.

### TC-P1-09-033: Banned account is signed out instead
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1
- Preconditions: fresh seed; `/settings/profile` open as `test_user`; then `UPDATE users SET status = 'banned' WHERE username = 'test_user';`
- Steps:
  1. Without reloading, submit `clash_chief` with the right password.
  2. Reset the status to `active`.
- Expected: Redirect to `/login` with "This account is banned, so it cannot sign in."; the username is unchanged.

## Security

### TC-P1-09-034: Password guesses are rate limited
- Priority: High · Type: Security
- Ref: specs/04 §4; `platform.auth.password_confirm_per_minute` 5, `password_confirm_per_hour` 20; Decisions 5
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. Submit `clash_chief` with a wrong password 5 times within a minute.
  2. Submit a 6th time, with the correct password.
  3. Wait a minute; submit with the correct password.
- Expected: Attempts 1–5: "That is not your current password." Attempt 6: "Too many attempts. Try again in N seconds." under "Current password" and no change (even with the right password). Step 3 succeeds. Security log has `auth.rate_limited` with `limiter` `password-confirm`. Past 20 attempts in an hour the message reads "Try again in N minutes."

### TC-P1-09-035: The limit is shared with other password forms
- Priority: Medium · Type: Security
- Ref: task Decisions 5 (`password-confirm` bucket)
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. On `/settings/security` submit the password change form with a wrong current password 3 times.
  2. On `/settings/profile` submit the username form with a wrong password 2 times, then once more.
- Expected: The 6th attempt within the minute (third on the username form) shows "Too many attempts. Try again in N seconds."

### TC-P1-09-036: Mass assignment on the change does nothing
- Priority: High · Type: Security
- Ref: task Tests "mass assignment (`username_changed_at`, `role`)"
- Preconditions: fresh seed; signed in as `test_user`, CSRF token `t` set.
- Steps:
  1. Run `fetch('/settings/profile/username', {method: 'PUT', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': t}, body: JSON.stringify({username: 'clash_chief', current_password: 'password', username_changed_at: '2020-01-01', role: 'admin', status: 'active'})}).then(r => console.log(r.status))`.
  2. Check the user row.
- Expected: The change succeeds; `username_changed_at` is now (not 2020), `role` is still `user`; the card is locked.

### TC-P1-09-037: Both profile cache entries are cleared
- Priority: Medium · Type: Security
- Ref: task Scope "After commit: `CacheInvalidator::profile()` for both names"
- Preconditions: fresh seed; `test_user` public.
- Steps:
  1. As a guest open `/u/test_user` (warms the cache) and `/u/clash_chief` (404).
  2. As `test_user` change to `clash_chief`.
  3. Within a minute, as the guest open `/u/test_user` and `/u/clash_chief`.
- Expected: `/u/test_user` redirects to `/u/clash_chief` and `/u/clash_chief` renders the profile with "@clash_chief", without waiting for the 5-minute cache.

### TC-P1-09-038: Two accounts racing for one name
- Priority: Low · Type: Edge case
- Ref: task Acceptance "two accounts racing for one name, one wins"
- Preconditions: fresh seed; `test_user` and `test_moderator` signed in in two browsers, both on `/settings/profile`.
- Steps:
  1. Fill both forms with `race_name` and the right password; click both buttons as close together as possible.
- Expected: Exactly one account becomes `race_name`; the other shows "That username is taken. Pick another." No 500 page.

### TC-P1-09-039: Unstorable input is a field error
- Priority: Low · Type: Security
- Ref: task Review fixes "the username rules `bail`"
- Preconditions: fresh seed; signed in as `test_user`, CSRF token `t` set.
- Steps:
  1. PUT `/settings/profile/username` as in TC-P1-09-036 with `username: 'ab\u0000cd'` and log `await r.text()`.
- Expected: 422 with "Use lowercase letters, numbers and underscores only."; no 500.

## UI states

### TC-P1-09-040: Saving state and error focus
- Priority: Medium · Type: UI state
- Ref: task Scope "UI" (saving, errors with focus on field)
- Preconditions: fresh seed; signed in as `test_user`; Network throttling "Slow 3G".
- Steps:
  1. Submit `clash-chief` with the right password; watch the button and focus.
  2. Submit `clash_chief` with a wrong password.
- Expected: The button shows a loading state while the request runs. Step 1: focus lands on "New username" with its error. Step 2: focus lands on "Current password", which is cleared. Neither failed attempt shows a toast; errors stay under the fields.

### TC-P1-09-041: Layout at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"
- Preconditions: signed in as an account whose username is 20 characters (e.g. `abcdefghij_klmnopqrs`), once with the form and once locked.
- Steps:
  1. At 375 × 812 view the Username card in the form state and in the locked state.
  2. Repeat at 1280 px.
- Expected: No horizontal scroll; the long `@username` breaks inside the text instead of overflowing; fields and button fit the width; the locked date wraps cleanly. At 1280 px the card matches the email card's flat style.

### TC-P1-09-042: Show and hide the current password
- Priority: Medium · Type: UI state
- Ref: UiInput password toggle (owner decision 2026-10-02); specs/18 §8
- Preconditions: fresh seed; signed in as `test_user` on `/settings/profile`; DevTools Elements or Accessibility pane open.
- Steps:
  1. Type `password` in "Current password"; inspect the eye button inside the right end of the field.
  2. Click it; then press Tab to reach it from the field and press Space.
  3. With the password shown, enter new username `clash-chief` and click "Change username".
  4. Enter `clash_chief`, type `password` again and submit.
- Expected: Step 1: the field shows dots (`type="password"`); the button sits inside the field, has the name "Show password" and `aria-pressed="false"`. Step 2: the click reveals `password` as text and the button becomes "Hide password" with `aria-pressed="true"`; Space toggles it back to "Show password"; the typed value never changes and the button is reachable by keyboard with a visible focus ring. Step 3: the field error shows under "New username" and the password field is cleared. Step 4 succeeds as in TC-P1-09-002.
