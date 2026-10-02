# P1-01 Login, logout, remember-me and password reset: test cases

Source: tasks/phase-1/P1-01-login-and-password-reset.md; specs/04 §4, specs/11 "Authentication attacks", "Account enumeration", "Session security", specs/23 §1.

Signing in from a browser the account has not used before may also send a "New sign-in" email
(P1-05); ignore it when counting reset emails below. Limits are in `config/platform.php` `auth.*`.
Reset links are limited to 3 per hour per email and IP, so run
`docker compose exec app php artisan cache:clear` between cases (it resets every limiter) unless
the case tests a limit.

## Happy path

### TC-P1-01-001: Sign in with email and password
- Priority: High · Type: Functional
- Ref: FR-AUTH-5, task Scope "HTTP + UI"
- Preconditions: fresh seed; signed out.
- Steps:
  1. Open `/login`.
  2. Enter `test@example.com` and `password`; leave "Keep me signed in" unchecked.
  3. Press "Sign in".
- Expected: redirected to `/`. The header shows the avatar and `test_user` with the account menu; the "Sign in" button is gone. In Adminer, `users` row `test_user` has `last_login_at` set to now and `last_login_ip_hash` a 64-character hex string (not an IP).

### TC-P1-01-002: Sign in returns to the intended page
- Priority: High · Type: Functional
- Ref: task Scope "After login, redirect to the intended URL or `/`"
- Preconditions: signed out.
- Steps:
  1. Open `/settings/profile` directly.
  2. On the sign-in page that loads, sign in as `test@example.com` / `password`.
- Expected: step 1 redirects to `/login`; after signing in the browser lands on `/settings/profile`, not `/`.

### TC-P1-01-003: Mixed-case email signs in
- Priority: Medium · Type: Functional
- Ref: task Scope "Migration" (`email` citext)
- Preconditions: signed out.
- Steps:
  1. On `/login` enter `TEST@Example.COM` and `password`, then submit.
- Expected: signed in as `test_user`.

### TC-P1-01-004: Sign out from the account menu
- Priority: High · Type: Functional
- Ref: task Scope "Session config" (logout invalidates the session and cycles the remember token)
- Preconditions: signed in as `test_user` with "Keep me signed in" checked; note `users.remember_token` for `test_user` in Adminer.
- Steps:
  1. Open the account menu (avatar, top right) and choose "Sign out".
  2. Reload Adminer's `users` row and `sessions` table.
- Expected: the menu item reads "Signing out…" while the request runs; the browser lands on `/` with the "Sign in" button in the header. `users.remember_token` has a new value. No `sessions` row with `test_user`'s `user_id` remains for this browser.

### TC-P1-01-005: Request a password reset link for a known email
- Priority: High · Type: Functional
- Ref: FR-AUTH-6, specs/16 §1 (security email, `high` queue)
- Preconditions: signed out; Mailpit empty; queue containers running.
- Steps:
  1. On `/login` press "Forgot your password?".
  2. On "Reset your password" enter `test@example.com` and press "Send the reset link".
- Expected: a success alert reads "If an account uses that email, a link to reset its password is on its way. It expires in 60 minutes." Mailpit has one email to test@example.com, subject "Reset your Clash Commons password", greeting "Hi test_user,", button "Choose a new password", the lines "The link works once and expires in 60 minutes. Resetting signs you out everywhere." and "If you did not ask for this, ignore this email. Your password stays as it is.", signed "Clash Commons". The Text tab in Mailpit shows the same copy as plain text.

### TC-P1-01-006: Reset the password with the emailed link
- Priority: High · Type: Functional
- Ref: FR-AUTH-6, FR-AUTH-2
- Preconditions: TC-P1-01-005 done.
- Steps:
  1. Open the "Choose a new password" link from Mailpit.
  2. Check the page "Choose a new password": the Email field is filled with test@example.com and read-only.
  3. Enter a new 12+ character password that is not in a breach list (e.g. `Ember-Lantern-4417`) in "New password" and "Repeat the new password"; press "Save the new password".
  4. Sign in with the new password.
- Expected: step 3 redirects to `/login` with the success alert "Your password is changed and every other session is signed out. Sign in with the new password." The old password `password` now fails; the new one signs in. In Adminer, `password_reset_tokens` has no row for test@example.com.

### TC-P1-01-007: Reset ends every other session and cycles the remember token
- Priority: High · Type: Security
- Ref: FR-AUTH-6, specs/04 §4
- Preconditions: Browser A signed in as `test_user` with "Keep me signed in"; note `users.remember_token`. Browser B (private window) signed out.
- Steps:
  1. In browser B request a reset for test@example.com and complete it with a new password (TC-P1-01-005/006).
  2. In browser A reload any page.
  3. In Adminer check `sessions` and `users.remember_token` for `test_user`.
- Expected: browser A is signed out (header shows "Sign in"), and its remember cookie does not sign it back in. `sessions` has no row for `test_user`. `remember_token` has changed.

### TC-P1-01-008: Remember-me keeps the browser signed in
- Priority: High · Type: Functional
- Ref: task Notes "Remember-me" (30-day cookie)
- Preconditions: signed out.
- Steps:
  1. Sign in as `test_user` with "Keep me signed in" checked.
  2. In DevTools > Application > Cookies, inspect the `remember_web_…` cookie.
  3. In Adminer delete this browser's `sessions` row for `test_user` (or delete only the session cookie in DevTools), then reload the page.
- Expected: the `remember_web_…` cookie exists, is HttpOnly and expires about 30 days from now. After step 3 the browser is still signed in as `test_user` (a new `sessions` row appears).

### TC-P1-01-009: Without remember-me no recaller cookie is set
- Priority: Medium · Type: Functional
- Ref: task Scope "remember-me"
- Preconditions: signed out; no `remember_web_…` cookie.
- Steps:
  1. Sign in as `test_user` with "Keep me signed in" unchecked.
  2. Delete the session cookie in DevTools and reload.
- Expected: no `remember_web_…` cookie was set; after step 2 the browser is signed out.

## Validation

### TC-P1-01-010: Empty sign-in form
- Priority: Medium · Type: Validation
- Ref: specs/18 §8, task Notes `focusFirstError()`
- Preconditions: signed out; `/login` open.
- Steps:
  1. Press "Sign in" with both fields empty.
- Expected: "The email field is required." under Email and "The password field is required." under Password; focus moves to the Email field. No request reaches the security log as `auth.login_failed`.

### TC-P1-01-011: Badly formed email on sign-in
- Priority: Medium · Type: Validation
- Ref: task Notes `LoginRequest` (email format check)
- Preconditions: signed out.
- Steps:
  1. Enter `test@` and any password; submit.
- Expected: "Enter a valid email address." under Email (not the wrong-password message); the Password field is cleared.

### TC-P1-01-012: Wrong password and unknown email read the same
- Priority: High · Type: Security
- Ref: FR-AUTH-5, specs/11 "Account enumeration"
- Preconditions: signed out; DevTools Network tab open.
- Steps:
  1. Sign in with `test@example.com` and `wrongpassword1`; note the message, the redirect and the request time.
  2. Sign in with `nobody-here@example.com` and `wrongpassword1`; note the same.
- Expected: both show "That email and password do not match. Check both and try again." under Email, both redirect back to `/login`, the Password field is cleared in both, and the two request times are similar (both include one password hash check, no case is clearly faster).

### TC-P1-01-013: New password shorter than 10 characters
- Priority: High · Type: Validation
- Ref: FR-AUTH-2
- Preconditions: a fresh reset link for test@example.com open.
- Steps:
  1. Enter `short123` in both password fields; save.
- Expected: "The password field must be at least 10 characters." under "New password"; focus on that field; both password fields cleared; the password is unchanged.

### TC-P1-01-014: New password and repeat do not match
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-2, `lang/en/validation.php` `confirmed`
- Preconditions: a fresh reset link open.
- Steps:
  1. Enter `Ember-Lantern-4417` and `Ember-Lantern-4418`; save.
- Expected: "The two password entries do not match." under "New password"; the token is not consumed (the same link still works with matching passwords).

### TC-P1-01-015: Breached password is rejected on reset
- Priority: High · Type: Validation
- Ref: FR-AUTH-2 (`uncompromised()`), Open question 4
- Preconditions: a fresh reset link open; the app container can reach the internet.
- Steps:
  1. Enter `password1234` in both fields; save.
- Expected: "This password appears in a known data breach. Choose a different one." under "New password"; the password is unchanged.

### TC-P1-01-016: Empty and malformed email on the reset-link form
- Priority: Low · Type: Validation
- Ref: task Scope "Pages … field errors"
- Preconditions: signed out; `/forgot-password` open.
- Steps:
  1. Submit with Email empty.
  2. Submit with `test@`.
- Expected: step 1 shows "The email field is required.", step 2 "Enter a valid email address."; focus on Email; no email in Mailpit.

## Authorization / account status

### TC-P1-01-017: Auth pages are for guests only
- Priority: High · Type: Authorization
- Ref: task Acceptance "guests only on login/forgot/reset"
- Preconditions: signed in as `test_user`.
- Steps:
  1. Open `/login`.
  2. Open `/forgot-password`.
  3. Open `/reset-password/anything?email=test@example.com`.
- Expected: each redirects to `/`; none of the forms renders.

### TC-P1-01-018: Sign-out needs a signed-in POST
- Priority: Medium · Type: Authorization
- Ref: task Acceptance "`POST /logout` for signed-in users only"
- Preconditions: signed out.
- Steps:
  1. Open `/logout` in the address bar (a GET).
  2. Signed out, replay a `POST /logout` from DevTools (copy any Inertia request as fetch and change the URL and method).
- Expected: step 1 answers 405 Method Not Allowed (no sign-out link works by GET). Step 2 redirects to `/login`.

## Security

### TC-P1-01-019: Reset request for an unknown email: same answer, no email
- Priority: High · Type: Security
- Ref: specs/23 §1 "reset requested for a non-existent account", specs/11 "Account enumeration"
- Preconditions: signed out; Mailpit empty; DevTools Network open.
- Steps:
  1. On `/forgot-password` submit `nobody-here@example.com`; note the status text and request time.
  2. Submit `test@example.com`; note the same.
- Expected: both show the same alert "If an account uses that email, a link to reset its password is on its way. It expires in 60 minutes." and both requests take about the same time (the lookup runs in a queued job). Mailpit has exactly one email, to test@example.com.

### TC-P1-01-020: Reset link used twice
- Priority: High · Type: Security
- Ref: specs/23 §1 "reset link used twice", FR-AUTH-6
- Preconditions: TC-P1-01-006 just completed; the same link at hand.
- Steps:
  1. Open the same reset link again; enter a valid new password twice; save.
- Expected: the page stays on "Choose a new password" with "This link has already been used or has expired. Ask for a new one." under Email and a link "Ask for a new reset link" to `/forgot-password`. The password from TC-P1-01-006 still works.

### TC-P1-01-021: Expired reset link
- Priority: High · Type: Security
- Ref: FR-AUTH-6 (60-minute token)
- Preconditions: a fresh reset link for test@example.com, not used.
- Steps:
  1. In Adminer set `password_reset_tokens.created_at` for test@example.com to 61 minutes ago.
  2. Open the link and save a valid new password.
- Expected: "This link has already been used or has expired. Ask for a new one." under Email; the password is unchanged.

### TC-P1-01-022: Link with a different email in the query
- Priority: Medium · Type: Security
- Ref: task Notes "A used, expired or mismatched link shows one message"
- Preconditions: a fresh reset link for test@example.com.
- Steps:
  1. Change `email=test@example.com` in the link to `email=moderator@example.com` and open it.
  2. Save a valid new password.
- Expected: the same "This link has already been used or has expired. Ask for a new one." message; neither account's password changes.

### TC-P1-01-023: A newer link replaces the older one
- Priority: Medium · Type: Edge case
- Ref: FR-AUTH-6 (single-use token)
- Preconditions: signed out; Mailpit empty.
- Steps:
  1. Request a reset for test@example.com; keep the link (link 1).
  2. Wait more than 60 seconds, then request again (link 2).
  3. Use link 1 with a valid new password.
  4. Use link 2 with a valid new password.
- Expected: step 3 shows "This link has already been used or has expired. Ask for a new one."; step 4 succeeds.

### TC-P1-01-024: Two requests inside a minute send one email
- Priority: Medium · Type: Edge case
- Ref: task Notes (broker's 60 s per-account throttle inside the job)
- Preconditions: signed out; Mailpit empty.
- Steps:
  1. Request a reset for test@example.com twice within 60 seconds.
- Expected: both requests show the same success alert; Mailpit has one reset email, not two.

### TC-P1-01-025: Sign-in limit, 5 per minute on IP + email
- Priority: High · Type: Security
- Ref: FR-AUTH-5, specs/04 §4 `login` (`login_per_minute` 5)
- Preconditions: signed out.
- Steps:
  1. Submit `test@example.com` with a wrong password 5 times within one minute.
  2. Submit a 6th time, this time with the right password.
  3. Within the same minute, sign in as `moderator@example.com` / `password`.
- Expected: step 2 shows "Too many attempts. Try again in N seconds." under Email (N at most 60), with the email kept in the field, and does not sign in. Step 3 signs in (the bucket is per email + IP). After the wait, test@example.com signs in normally.

### TC-P1-01-026: Sign-in limit, 20 per hour on IP + email
- Priority: Medium · Type: Security
- Ref: specs/04 §4 `login` (`login_per_hour` 20)
- Preconditions: signed out; no sign-in attempts for test@example.com in the last hour.
- Steps:
  1. Submit 5 wrong passwords for test@example.com, wait for the minute to pass; repeat until 20 attempts are made.
  2. Make a 21st attempt.
- Expected: step 2 shows "Too many attempts. Try again in N minutes." under Email (N in minutes, up to 60).

### TC-P1-01-027: Per-IP sign-in ceiling, 30 per minute
- Priority: Low · Type: Security
- Ref: task Notes "Per-IP ceilings" (`login_per_ip_per_minute` 30)
- Preconditions: signed out.
- Steps:
  1. Within one minute, submit the sign-in form 31 times with a different unknown email each time (`a1@example.com` … `a31@example.com`); a DevTools snippet replaying the POST is fine.
- Expected: the 31st attempt gets "Too many attempts. Try again in N seconds." even though each email was used once.

### TC-P1-01-028: Reset-link limit, 3 per hour on IP + email
- Priority: High · Type: Security
- Ref: specs/04 §4 `password-reset` (`password_reset_per_hour` 3)
- Preconditions: signed out; no reset requests for test@example.com in the last hour.
- Steps:
  1. Request a reset for test@example.com 3 times.
  2. Request a 4th time.
  3. Request a reset for `moderator@example.com`.
- Expected: requests 1–3 show the generic success alert. Step 2 shows "Too many attempts. Try again in N minutes." under Email. Step 3 succeeds (separate bucket).

### TC-P1-01-029: Reset-link per-IP ceiling, 20 per hour
- Priority: Low · Type: Security
- Ref: task Notes "Per-IP ceilings" (`password_reset_per_ip_per_hour` 20)
- Preconditions: signed out; no reset requests from this IP in the last hour.
- Steps:
  1. Request resets for 20 different addresses (`r1@example.com` … `r20@example.com`).
  2. Request a 21st with `r21@example.com`.
- Expected: step 2 shows "Too many attempts. Try again in N minutes." under Email.

### TC-P1-01-030: Typos on the reset form never lock the link
- Priority: Medium · Type: Edge case
- Ref: task Notes "`POST /reset-password` has no limiter"
- Preconditions: a fresh reset link open.
- Steps:
  1. Submit mismatched passwords 6 times in a row.
  2. Submit a valid matching password.
- Expected: steps 1 each show the mismatch error, never a "Too many attempts" message; step 2 succeeds.

### TC-P1-01-031: Session id changes on sign-in
- Priority: High · Type: Security
- Ref: specs/11 "Session security" (fixation)
- Preconditions: signed out; Adminer `sessions` open.
- Steps:
  1. Load `/login`; find this browser's guest `sessions` row (`user_id` empty, newest `last_activity`) and note its `id`.
  2. Sign in as `test_user`.
- Expected: the `sessions` row for `test_user` has a different `id` from the guest row noted in step 1.

### TC-P1-01-032: Session and remember cookies are protected
- Priority: Medium · Type: Security
- Ref: task Scope "Session config" (encrypt, secure from env, same_site lax)
- Preconditions: signed in as `test_user` with "Keep me signed in".
- Steps:
  1. In DevTools > Application > Cookies inspect the session cookie and the `remember_web_…` cookie.
  2. In the console run `document.cookie`.
  3. In Adminer open this browser's `sessions.payload`.
- Expected: both cookies are HttpOnly and SameSite=Lax (Secure is off only because `APP_ENV=local`); neither appears in `document.cookie`. The `payload` is encrypted, not readable serialized data.

### TC-P1-01-033: Security log records sign-ins without personal data
- Priority: Low · Type: Security
- Ref: task Notes "Security log", specs/11 §3
- Preconditions: TC-P1-01-012 and TC-P1-01-001 done.
- Steps:
  1. Run `docker compose exec app sh -c 'tail -n 20 storage/logs/security-*.log'`.
- Expected: `auth.login_failed` lines (with the ULID for test@example.com, `null` for the unknown email), an `auth.login` line, and `auth.rate_limited` after TC-P1-01-025. Each has an `ip_hash`; no line contains an email address, a password or a raw IP.

## UI states

### TC-P1-01-034: Header links for guests and members
- Priority: Medium · Type: UI state
- Ref: task Notes "Header: `AccountControls`"
- Preconditions: signed out.
- Steps:
  1. Open `/`, then `/login`.
  2. Sign in and open the account menu.
- Expected: on `/` the header shows "Sign in"; on `/login` it is hidden. Signed in, the header shows the avatar and username; the menu lists "Your profile", "Settings" and "Sign out".

### TC-P1-01-035: Links between the auth pages
- Priority: Low · Type: UI state
- Ref: task Scope "Pages"
- Preconditions: signed out.
- Steps:
  1. On `/login` follow "Forgot your password?".
  2. On `/forgot-password` follow "Back to sign in".
  3. On `/login` follow "Create an account".
- Expected: each link opens `/forgot-password`, `/login` and `/register` in turn. The reset page explains "At least 10 characters. Saving it signs you out on every other device."

### TC-P1-01-036: Submitting state on each form
- Priority: Low · Type: UI state
- Ref: task Acceptance "States: … submitting"
- Preconditions: signed out; DevTools Network throttled to "Slow 3G".
- Steps:
  1. Submit the sign-in form, the reset-link form and the new-password form.
- Expected: while each request runs, its button shows the loading state and cannot be pressed again.

### TC-P1-01-037: Pages at 375 px
- Priority: Medium · Type: UI state
- Ref: task Acceptance "375 px and desktop", specs/18 §5
- Preconditions: DevTools device toolbar at 375 px wide.
- Steps:
  1. Open `/login`, `/forgot-password` and a reset link; trigger a field error and a status alert on each.
- Expected: no horizontal scroll; the "Keep me signed in" row and the "Forgot your password?" link are at least 44 px tall; alerts and errors wrap inside the card.

### TC-P1-01-038: Show and hide the sign-in password
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed out; `/login` open.
- Steps:
  1. Enter `test@example.com`; type `password` in Password.
  2. Press the eye button at the right edge of the Password field.
  3. Press it again.
  4. Press it once more (text showing), then press "Sign in".
- Expected: before step 2 the field shows dots and the button is named "Show password" with `aria-pressed="false"`. Step 2 shows `password` as plain text; the button is now "Hide password" with `aria-pressed="true"`. Step 3 masks it again. The value is the same after every press (no characters lost or added). Step 4 signs in as `test_user`. The Email field has no toggle.

### TC-P1-01-039: Password toggle with the keyboard
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed out; `/login` open.
- Steps:
  1. Type `test@example.com` in Email (focused on load), press Tab, type `password`.
  2. Press Tab once.
  3. Press Space; then press Enter.
  4. Press Tab, then Shift+Tab twice.
- Expected: step 2 moves focus to the toggle (visible focus ring), not to "Keep me signed in". Space shows the password as text ("Hide password"); Enter masks it again ("Show password"). Neither key submits the form: no request, no field errors, the value is kept. Step 4 goes to "Keep me signed in", then back through the toggle to the Password field.

### TC-P1-01-040: Both fields on the reset page toggle on their own
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: a fresh reset link for test@example.com open.
- Steps:
  1. Type `Ember-Lantern-4417` in "New password" and in "Repeat the new password".
  2. Press "Show password" in "New password" only.
  3. Press "Show password" in "Repeat the new password", then "Hide password" in "New password".
  4. With "Repeat the new password" still showing as text, press "Save the new password".
- Expected: the read-only Email field has no toggle. Step 2 reveals only "New password"; "Repeat the new password" stays masked. Step 3 leaves only "Repeat the new password" revealed. Both values are unchanged. Step 4 saves as in TC-P1-01-006 (the "Your password is changed …" alert on `/login`).

### TC-P1-01-041: Show and hide on the confirm-password page
- Priority: Low · Type: UI state
- Ref: specs/11 "CSRF" (re-confirmation), owner decision 2026-10-02 (password toggle)
- Preconditions: signed in as `test_user`.
- Steps:
  1. Open `/confirm-password`; type `wrongpassword1` in Password.
  2. Press "Show password", then Tab to the button and press Space.
  3. Press "Show password" again and press "Confirm".
- Expected: step 2 shows `wrongpassword1` as text, then masks it again ("Show password", `aria-pressed="false"`). Step 3 shows "That is not your password." under Password and the field is cleared; the toggle still works on what is typed next.

### TC-P1-01-042: Toggle size at 375 px
- Priority: Low · Type: UI state
- Ref: specs/18 §8 (44 px targets), owner decision 2026-10-02 (password toggle)
- Preconditions: DevTools device toolbar at 375 px; signed out.
- Steps:
  1. Open `/login`; type a 40-character password.
  2. Tap just outside the drawn eye icon (within about 4 px of it).
  3. Inspect the toggle button.
- Expected: the tap in step 2 still toggles the field (the hit area is at least 44 × 44 px). The button sits inside the field's border at its right edge and never covers the typed text; a long value scrolls inside the field. The field and the page have no horizontal scroll.
