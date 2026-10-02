# P1-05 Security settings, sessions and new-device emails: test cases

Source: tasks/phase-1/P1-05-security-settings-and-sessions.md · specs/04 §3–§4, specs/11 "Authentication attacks" / "CSRF" / "Session security" / §5, specs/16 §2, specs/23 §1, FR-AUTH-2, FR-AUTH-7

Notes for the tester:
- "Browser A" and "Browser B" are two separate cookie jars: two browsers, or a normal and a private
  window. Each is listed as its own session.
- Locally `sessions.country_code` is NULL (no CDN header), so no country is shown.
- The session cookie is `clash-commons-session`; the remember-me cookie is `remember_web_*`.
- Emails are queued on `high`; the `queue` containers deliver them to Mailpit within seconds.
- A freshly seeded account has never signed in, so its very first sign-in sends "First sign-in to
  your Clash Commons account" instead of the new-device email (TC-P1-05-027). Sign in once before
  running cases that expect "New sign-in".
- Limits: `password-confirm` 5 per minute and 20 per hour per account, shared by the password form,
  the confirm page, the username, email-change and danger-zone forms. Absolute session cap 30 days;
  idle limit `session.lifetime` (`docker compose exec app php artisan config:show session.lifetime`).
- Reset every limiter between cases with `docker compose exec app php artisan cache:clear`.

## Happy path

### TC-P1-05-001: Security page loads with password form and session list
- Priority: High · Type: Functional
- Ref: FR-AUTH-7; task Scope "HTTP + UI"
- Preconditions: signed in as `test_user` in browser A only
- Steps:
  1. Open the avatar menu → "Settings", then click "Security" in the settings nav.
- Expected: URL `/settings/security`, title "Security settings". The settings nav reads "Profile · Privacy · Security · Notifications · Danger zone" with "Security" marked current. A "Password" card ("Changing it signs out every other device.") with "Current password", "New password" (hint "At least 10 characters."), "Repeat new password" and "Change password". A "Where you're signed in" card listing one row: the device label (e.g. "Chrome on macOS"), a "This device" badge, "Active now", no "Sign out" button, and the text "You are only signed in on this device.".

### TC-P1-05-002: Change password successfully
- Priority: High · Type: Functional
- Ref: FR-AUTH-2; PasswordChangeService
- Preconditions: signed in as `test_user` in browser A
- Steps:
  1. Current password `password`; New password and Repeat `Harbour-Lantern-42`; click "Change password".
  2. Sign out, sign in with `password`, then with `Harbour-Lantern-42`.
- Expected: the button shows a loading state, then "Saved." appears next to it and all three fields are cleared. Browser A stays signed in. The old password is refused with "That email and password do not match. Check both and try again."; the new one signs in. Security log: `auth.password_changed`.

### TC-P1-05-003: "Password changed" email
- Priority: High · Type: Email
- Ref: specs/16 §2 "Password changed"
- Preconditions: TC-P1-05-002 done
- Steps:
  1. Open Mailpit, open the newest message to test@example.com.
- Expected: subject "Your Clash Commons password was changed"; greeting "Hi test_user,"; body "The password for your Clash Commons account was changed on <d Month YYYY> at <HH:MM> UTC. Every other device was signed out." and the reset advice; button "Reset your password" linking to http://localhost:8080/forgot-password; signed "Clash Commons". Exactly one such email per change.

### TC-P1-05-004: Password change signs out every other session, current one survives
- Priority: High · Type: Functional
- Ref: specs/04 §4; Acceptance "other sessions and the remember token die on change"
- Preconditions: `test_user` signed in on browser A and browser B (B with "Keep me signed in" ticked)
- Steps:
  1. In A, reload `/settings/security` and confirm two rows are listed.
  2. In A, change the password.
  3. In B, reload any signed-in page (e.g. `/settings/profile`).
  4. In B, in DevTools delete `clash-commons-session` only, reload again.
  5. In A, reload `/settings/security`.
- Expected: step 3 lands on `/login`. Step 4: still signed out, the remember cookie no longer works. Step 5: A is still signed in and lists only "This device".

### TC-P1-05-005: Sign out one other device
- Priority: High · Type: Functional
- Ref: FR-AUTH-7; SessionService::revoke
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. In A, on `/settings/security`, click "Sign out" on B's row.
  2. Read the dialog, click "Sign out".
  3. In B, reload `/settings/profile`.
- Expected: dialog title "Sign out <B's device label>?", text "That device will need your password to sign in again.", buttons "Cancel" and "Sign out". After confirming, B's row disappears and "You are only signed in on this device." shows. B lands on `/login`. Security log: `auth.session_revoked` with `count: 1`.

### TC-P1-05-006: Cancel the sign-out dialog
- Priority: Medium · Type: UI state
- Ref: task States "revoke confirm"
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. In A click "Sign out" on B's row, then "Cancel".
  2. Open it again and press Escape.
- Expected: the dialog closes both times; B's row stays; B is still signed in.

### TC-P1-05-007: Sign out every other device
- Priority: High · Type: Functional
- Ref: FR-AUTH-7; SessionService::revokeOthers
- Preconditions: `test_user` signed in on A, B and a third browser C
- Steps:
  1. In A, click "Sign out every other device".
  2. Read the dialog, click "Sign out".
  3. Reload B and C.
- Expected: dialog title "Sign out every other device?", text "All 2 other devices will need your password to sign in again. This one stays signed in.". After confirming only "This device" remains. B and C land on `/login`. A stays signed in. Security log `auth.session_revoked` with `count: 2`.

### TC-P1-05-008: Dialog copy with exactly one other device
- Priority: Low · Type: UI state
- Ref: SettingsSessionList
- Preconditions: `test_user` signed in on A and B only
- Steps:
  1. In A click "Sign out every other device".
- Expected: text "The other device will need your password to sign in again. This one stays signed in.".

### TC-P1-05-009: New sign-in email for an unrecognised browser
- Priority: High · Type: Email
- Ref: specs/11 "Authentication attacks"; specs/16 §2; specs/23 §1
- Preconditions: `test_user` has signed in at least once before; browser B has never signed in as `test_user` (fresh private window)
- Steps:
  1. In B sign in as `test_user`.
  2. Open Mailpit.
- Expected: one email, subject "New sign-in to your Clash Commons account", greeting "Hi test_user,", line "Your account was signed in from a device we have not seen before: <device label>, on <d Month YYYY> at <HH:MM> UTC.", "If this was you, there is nothing to do.", "If it was not, sign that device out and change your password.", button "Review your sessions" → http://localhost:8080/settings/security. Security log `auth.new_device` with `first: false`.

### TC-P1-05-010: No email for a browser that already signed in to the account
- Priority: High · Type: Email
- Ref: Open question 2 (`known_devices` cookie)
- Preconditions: TC-P1-05-009 done in browser B (do not clear cookies)
- Steps:
  1. In B sign out, then sign in again as `test_user`.
  2. Check Mailpit.
- Expected: no new "New sign-in" email. In DevTools → Cookies, B holds a `known_devices` cookie (encrypted value, expiry about 365 days ahead).

### TC-P1-05-011: The new sign-in session is listed for revocation, not ended
- Priority: High · Type: Edge case
- Ref: specs/23 §1 "Session hijack suspected"
- Preconditions: TC-P1-05-009 done; A also signed in as `test_user`
- Steps:
  1. In A open `/settings/security`.
  2. Click the email's "Review your sessions" button in Mailpit (open in A).
- Expected: B is still signed in (nothing auto-ended) and listed with its device label and "Last active <date, time>"; it can be signed out from here. The email link opens the same page.

### TC-P1-05-012: In-app copy of the security events
- Priority: Medium · Type: Functional
- Ref: Decisions 10 (in-app copies → P1-07)
- Preconditions: TC-P1-05-002 and TC-P1-05-009 done
- Steps:
  1. As `test_user` open `/notifications`.
- Expected: entries "Your password was changed" ("Every other device was signed out. If you did not change it, reset your password now.") and "New sign-in to your account" ("From a device we have not seen before: <device>. If it was not you, sign that device out and change your password.").

### TC-P1-05-013: Session rows store a hashed IP and device data, no raw IP
- Priority: High · Type: Security
- Ref: specs/07 `sessions`; specs/11 §5; Open question 4
- Preconditions: `test_user` signed in
- Steps:
  1. In Adminer open table `sessions` (structure and data).
- Expected: there is no `ip_address` column. The row for `test_user` has `ip_hash` (a hash, not an IP), `device_label` (e.g. "Firefox on Windows"), `country_code` NULL locally, `created_at` set at sign-in, `last_activity` as a Unix timestamp.

### TC-P1-05-014: Header account menu works by mouse and keyboard
- Priority: Medium · Type: UI state
- Ref: Decisions 8 (`UiDropdownMenu`); specs/18 §4, §6
- Preconditions: signed in as `test_user`
- Steps:
  1. Click the avatar in the header.
  2. Close it, Tab to the avatar button, press Enter; use ArrowDown, ArrowUp, Home, End; press Escape.
  3. Focus the avatar and press ArrowUp; then press Tab.
  4. Open it and choose "Sign out".
- Expected: the menu lists "Your profile" (→ `/u/test_user`), "Settings" (→ `/settings/profile`), "Sign out". Enter opens with focus on the first item; arrows move, Home/End jump to first/last; Escape closes and returns focus to the avatar. ArrowUp opens on the last item; Tab closes the menu. "Sign out" shows "Signing out…" briefly and signs out.

## Validation

### TC-P1-05-015: Wrong current password
- Priority: High · Type: Validation
- Ref: PasswordChangeService; Tests "wrong current password limited and logged"
- Preconditions: signed in as `test_user`
- Steps:
  1. Current password `not-my-password`, new password `Harbour-Lantern-42` twice, submit.
- Expected: "That is not your current password." under "Current password"; the current-password field is cleared and focused; the password is unchanged; no email sent. Security log `auth.password_change_failed`.

### TC-P1-05-016: New password shorter than 10 characters
- Priority: High · Type: Validation
- Ref: FR-AUTH-2; `platform.auth.min_password_length` = 10
- Preconditions: signed in as `test_user`
- Steps:
  1. Current `password`, new `Short-123` (9 chars) twice, submit.
  2. Repeat with exactly 10 characters, e.g. `Short-1234` (if it is reported as breached, use `Qx7-vLm2pZ`).
- Expected: step 1: "The new password field must be at least 10 characters." under "New password". Step 2 is accepted ("Saved.").

### TC-P1-05-017: New password found in a breach
- Priority: High · Type: Validation
- Ref: FR-AUTH-2 (`uncompromised()`); specs/11 "HIBP on change"
- Preconditions: signed in as `test_user`; the app container has internet access
- Steps:
  1. Current `password`, new `password1234` twice, submit.
- Expected: "This password appears in a known data breach. Choose a different one." under "New password". (Without network the HIBP check fails open after 2 seconds and the password is accepted; note it as an environment limitation.)

### TC-P1-05-018: Repeat does not match
- Priority: Medium · Type: Validation
- Ref: ChangePasswordRequest messages
- Preconditions: signed in as `test_user`
- Steps:
  1. Current `password`, New `Harbour-Lantern-42`, Repeat `Harbour-Lantern-43`, submit.
- Expected: "The two new passwords do not match." under "New password".

### TC-P1-05-019: New password same as the current one
- Priority: Medium · Type: Validation
- Ref: ChangePasswordRequest `different:current_password`
- Preconditions: current password is `Harbour-Lantern-42`
- Steps:
  1. Current `Harbour-Lantern-42`, new `Harbour-Lantern-42` twice, submit.
- Expected: "Choose a new password that is different from your current one."

### TC-P1-05-020: Empty form
- Priority: Low · Type: Validation
- Ref: ChangePasswordRequest
- Preconditions: signed in as `test_user`
- Steps:
  1. Click "Change password" with every field empty.
- Expected: "The current password field is required." and "The new password field is required."; focus moves to "Current password". No request side effects.

## Authorization / account status

### TC-P1-05-021: Restricted account can change the password and sign out devices
- Priority: High · Type: Authorization
- Ref: specs/04 §3; Acceptance "restricted accounts can change password and revoke"
- Preconditions: `test_user` signed in on A and B; in Adminer `status` = `restricted`, `status_expires_at` = now + 1 day
- Steps:
  1. In A sign out B from the session list.
  2. In A change the password.
- Expected: both succeed as in TC-P1-05-005 and TC-P1-05-002. Clean-up: `status` = `active`.

### TC-P1-05-022: Suspended account can read the page but not write
- Priority: High · Type: Authorization
- Ref: specs/04 §1, §3; Decisions 7
- Preconditions: `test_user` signed in on A and B; in Adminer `status` = `suspended`, `status_reason` = "Test", `status_expires_at` = now + 1 day
- Steps:
  1. In A open `/settings/security`.
  2. Submit a valid password change.
  3. Go back, click "Sign out" on B's row and confirm.
- Expected: step 1 loads the page (no redirect to the notice). Steps 2 and 3 show the WriteBlocked page "Your account is suspended" / "Changes are off until the suspension ends." with HTTP 403; the password is unchanged and B is still signed in. Clean-up: `status` = `active`.

### TC-P1-05-023: Guest cannot reach Security settings
- Priority: Medium · Type: Authorization
- Ref: routes/web/settings.php (`auth`)
- Preconditions: signed out
- Steps:
  1. Open http://localhost:8080/settings/security.
- Expected: redirect to `/login`; after signing in, back on `/settings/security`.

## Security

### TC-P1-05-024: Another account's session key returns 404
- Priority: High · Type: Security
- Ref: specs/04 §3 IDOR; Decisions 3
- Preconditions: `test_moderator` signed in on browser B; `test_user` signed in on browsers A and C
- Steps:
  1. In B open `/settings/security`, DevTools → Network: find the response carrying `sessions` and copy the moderator session's `key` (32 hex characters).
  2. In A, sign out C from the session list; in the Network tab right-click the resulting `DELETE /settings/security/sessions/<key>` request → Copy → Copy as fetch.
  3. Paste into A's Console, replace the key with the moderator's key, run it and read the response status.
  4. Run it again with a malformed key `abc`.
- Expected: step 3 returns 404 and `test_moderator` stays signed in on B. Step 4 returns 404. Nothing in `sessions` changes.

### TC-P1-05-025: Security page props hold no raw IP, session id or payload
- Priority: High · Type: Security
- Ref: specs/11 "Data exposure via page props"; Tests "Security page props"
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. In A open `/settings/security`, inspect the Inertia responses (page and deferred `sessions`).
  2. Compare each `key` with the `id` values in Adminer `sessions`.
- Expected: each session has only `key`, `deviceLabel`, `country`, `lastActiveAt`, `signedInAt`, `isCurrent`. No IP, `ip_hash`, user agent, payload or user id. No `key` equals a session `id` or the `clash-commons-session` cookie value.

### TC-P1-05-026: The session id changes after a password change and after "sign out every other device"
- Priority: Medium · Type: Security
- Ref: Review fixes "the session id is regenerated"
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. In A note the `id` of A's row in Adminer `sessions` (latest `last_activity`).
  2. In A click "Sign out every other device" and confirm; reload Adminer.
  3. Change the password in A; reload Adminer.
- Expected: after step 2 and again after step 3, A's row has a new `id`; the old id no longer exists. A stays signed in throughout.

### TC-P1-05-027: First-ever sign-in email
- Priority: Medium · Type: Email
- Ref: TrackSignIn (first sign-in wording)
- Preconditions: fresh seed (`migrate:fresh --seed`), so `test_moderator.last_login_at` is NULL
- Steps:
  1. Sign in as `test_moderator` in a fresh private window.
  2. Check Mailpit and `/notifications`.
- Expected: email subject "First sign-in to your Clash Commons account", line "Your new account was signed in for the first time: <device>, on <d Month YYYY> at <HH:MM> UTC.", button "Review your sessions". No "New sign-in" in-app notification. Security log `auth.new_device` with `first: true`.

### TC-P1-05-028: Password guesses share the `password-confirm` limit (5 per minute)
- Priority: High · Type: Security
- Ref: Decisions 1; specs/11 "Authentication attacks"
- Preconditions: signed in as `test_user`; no password attempts in the last hour
- Steps:
  1. Submit the password form 5 times within a minute with a wrong current password.
  2. Submit a 6th time, this time with the right current password.
  3. Wait for the stated time and submit with the right current password.
- Expected: attempts 1–5 show "That is not your current password.". Attempt 6 shows "Too many attempts. Try again in N seconds." under "Current password" and the password is not changed. After waiting, the change succeeds. Security log has `auth.rate_limited` with `limiter: password-confirm`.

### TC-P1-05-029: The limit is shared across forms
- Priority: Medium · Type: Security
- Ref: routes/web/settings.php (shared `password-confirm` bucket)
- Preconditions: signed in as `test_user`; bucket empty
- Steps:
  1. Submit 3 wrong current passwords on `/settings/security`.
  2. Open `/settings/danger-zone`, submit 2 wrong passwords with the checkbox ticked.
  3. Open `/confirm-password` and submit any password.
- Expected: step 3 is throttled: "Too many attempts. Try again in N seconds." under "Password".

### TC-P1-05-030: Hourly tier of the limit (20 per hour)
- Priority: Low · Type: Security
- Ref: Review fixes "hourly tier"; `password_confirm_per_hour` = 20
- Preconditions: signed in as `test_user`; bucket empty
- Steps:
  1. Submit wrong current passwords 5 per minute over 4 minutes (20 in total), waiting for the minute limit each time.
  2. In minute 5 submit once more.
- Expected: the 21st attempt is refused with "Too many attempts. Try again in N minutes." (wait close to an hour), even though the minute window is clear.

## Edge cases

### TC-P1-05-031: Session older than 30 days is signed out while active
- Priority: High · Type: Edge case
- Ref: specs/04 §4; `platform.auth.absolute_session_days` = 30
- Preconditions: `test_user` signed in on A only, on `/settings/security`; do not click in A during step 1
- Steps:
  1. Move the session's sign-in time back 31 days:
     `docker compose exec app php artisan tinker --execute='$id = App\Models\User::where("username", "test_user")->value("id"); $row = DB::table("sessions")->where("user_id", $id)->orderByDesc("last_activity")->first(); $p = unserialize(Crypt::decrypt(base64_decode($row->payload))); $p["auth"]["signed_in_at"] = now()->subDays(31)->getTimestamp(); DB::table("sessions")->where("id", $row->id)->update(["payload" => base64_encode(Crypt::encrypt(serialize($p)))]); echo "ok";'`
  2. In A click "Profile" in the settings nav.
- Expected: A lands on `/login` with the notice "You were signed out because this sign-in is 30 days old. Sign in again to carry on.". Security log `auth.session_expired`.

### TC-P1-05-032: Absolute cap during a form submit uses a 303, the write is not repeated
- Priority: Medium · Type: Edge case
- Ref: Review fixes "the absolute-cap redirect is a 303"
- Preconditions: as TC-P1-05-031 step 1, but leave A on `/settings/security` with the password form filled
- Steps:
  1. Run the tinker command from TC-P1-05-031.
  2. Submit the password form in A; watch the Network tab.
- Expected: `PUT /settings/security/password` gets a 303 to `/login`, followed by one `GET /login` (no second PUT). The password is unchanged; the session-expired notice is shown.

### TC-P1-05-033: Remember-me cannot outlast the 30-day cap
- Priority: Medium · Type: Edge case
- Ref: Decisions 5 (`remember_since`)
- Preconditions: `test_user` signed in on A with "Keep me signed in" ticked
- Steps:
  1. In DevTools → Cookies check that `remember_web_*` and `remember_since` exist.
  2. Delete `clash-commons-session` and `remember_since`, keep `remember_web_*`; reload `/settings/security`.
- Expected: the browser is signed straight back out: `/login` with "You were signed out because this sign-in is 30 days old. Sign in again to carry on.". (With `remember_since` kept, deleting only the session cookie signs back in silently.)

### TC-P1-05-034: This browser's remember cookie is cleared, not re-issued, after a password change
- Priority: Medium · Type: Edge case
- Ref: Decisions 4; Review fixes "no remember cookie is ever re-issued"
- Preconditions: `test_user` signed in on A with "Keep me signed in" ticked
- Steps:
  1. In A change the password.
  2. In DevTools delete `clash-commons-session` only, reload.
- Expected: A is still signed in after step 1. After step 2 A is signed out (the remember cookie no longer works); sign in again needed.

### TC-P1-05-035: Idle-expired sessions are left out of the list
- Priority: Medium · Type: Edge case
- Ref: Review fixes "idle-expired rows are left out of the list and the counts"
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. In Adminer → SQL command run `SELECT extract(epoch from now() - interval '15 days')::int;` (use an interval longer than `session.lifetime`; 15 days for the default 14-day lifetime) and set B's row `last_activity` to the result.
  2. In A reload `/settings/security`.
  3. Click "Sign out every other device" if shown.
- Expected: only "This device" is listed and "You are only signed in on this device." shows (no button). B's stale row is not counted.

### TC-P1-05-036: Confirm-password page (15-minute re-confirmation)
- Priority: Medium · Type: Functional
- Ref: specs/11 "CSRF"; `auth.password_timeout` = 900
- Preconditions: signed in as `test_user`
- Steps:
  1. Open http://localhost:8080/confirm-password.
  2. Submit a wrong password.
  3. Submit `password`.
- Expected: heading "Confirm your password", text "This is a sensitive change, so enter your password first. You will not be asked again for 15 minutes.". Step 2: "That is not your password." under "Password", field cleared; security log `auth.password_confirm_failed`. Step 3 redirects to `/`. Signed out, the URL redirects to `/login`.

## UI states

### TC-P1-05-037: Session list skeleton while loading
- Priority: Low · Type: UI state
- Ref: task States "session list loading"
- Preconditions: signed in as `test_user`
- Steps:
  1. DevTools → Network → throttling "Slow 4G" (or "3G").
  2. Navigate to `/settings/security` from another settings page.
- Expected: the password card renders first; the "Where you're signed in" card shows a skeleton (screen-reader label "Loading your sessions") until the list arrives, then the rows replace it.

### TC-P1-05-038: Layout at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: task States "375 px and desktop"
- Preconditions: `test_user` signed in on A and B
- Steps:
  1. View `/settings/security` at 375 px, then at ≥1280 px; open the sign-out dialog at both widths; open the account menu at 375 px.
- Expected: at 375 px the settings nav scrolls horizontally inside itself (no page scroll), the new-password fields stack, session rows stack with the "Sign out" button below the text, the dialog fits the screen. At desktop the two new-password fields sit side by side. No console errors.
