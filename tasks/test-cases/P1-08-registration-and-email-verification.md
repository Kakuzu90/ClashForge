# P1-08 Registration and email verification: test cases

Source: tasks/phase-1/P1-08-registration-and-email-verification.md; specs/04 §4, specs/11 "Account enumeration", "API abuse", "Spam and fake accounts", specs/16 §2, specs/23 §1.

Local runs use Cloudflare's always-pass Turnstile test keys, so the widget stays invisible. The
register form rejects submissions made less than 3 seconds after the page loaded (silently), so
wait a few seconds before each submit. Limits are in `config/platform.php` `auth.*`. Only 3
registrations per IP and hour are accepted, so run `docker compose exec app php artisan cache:clear`
between cases (it resets every limiter) unless the case tests a limit.

## Happy path

### TC-P1-08-001: Register a new account
- Priority: High · Type: Functional
- Ref: FR-AUTH-1, FR-AUTH-3, Decision 2
- Preconditions: signed out; Mailpit empty; queue containers running.
- Steps:
  1. Open `/register`.
  2. Enter email `qa1@example.com`, username `qa_one`, password `Ember-Lantern-4417` twice.
  3. Wait at least 3 seconds; press "Create account".
- Expected: redirected to `/register/sent`, "Check your email", with "We sent an email to the address you entered. Open the link in it to confirm your account." and "The link expires in 60 minutes. Not there? Check your spam folder, or sign in and ask for a new one." The header still shows "Sign in" (nobody is signed in).

### TC-P1-08-002: Account rows after registering
- Priority: High · Type: Functional
- Ref: FR-AUTH-1, Decision 11 (`UserRegistered` → profile, privacy, stats)
- Preconditions: TC-P1-08-001 done.
- Steps:
  1. In Adminer open `users` where `username = 'qa_one'`.
  2. Open `profiles`, `privacy_settings` and `user_stats` for that user's `id`.
- Expected: `users` row with `email` qa1@example.com, `email_verified_at` NULL, `role` user, `status` active, a 26-character `ulid` and a bcrypt `password`. One row each in `profiles`, `privacy_settings` and `user_stats`.

### TC-P1-08-003: Verification email
- Priority: High · Type: Email
- Ref: FR-AUTH-3, specs/16 §2
- Preconditions: TC-P1-08-001 done.
- Steps:
  1. Open Mailpit.
- Expected: one email to qa1@example.com, subject "Confirm your Clash Commons email", greeting "Hi qa_one,", the line "Confirm this address to finish setting up your Clash Commons account.", button "Confirm your email", then "The link expires in 60 minutes. You can ask for a new one after signing in." and "If you did not create an account, ignore this email.", signed "Clash Commons". The link has the form `/email/verify/{ulid}/{40-hex hash}?expires=…&signature=…`.

### TC-P1-08-004: Open and confirm the link while signed out
- Priority: High · Type: Functional
- Ref: FR-AUTH-3, Decision 8, Open question 5
- Preconditions: TC-P1-08-003 done; signed out.
- Steps:
  1. Open the link from the email.
  2. Check `users.email_verified_at` for `qa_one` in Adminer.
  3. Press "Confirm my email".
- Expected: step 1 shows "Confirm your email" with "This confirms the email for the account qa_one." and "Did not sign up? Close this page and nothing happens."; step 2 is still NULL. After step 3 the browser is on `/email/verified`: heading "Email confirmed", alert "Your email is confirmed.", and a "Sign in" button. `email_verified_at` is now set.

### TC-P1-08-005: Confirm the link while signed in to the same account
- Priority: High · Type: Functional
- Ref: Decision 8, Decision 9
- Preconditions: a new unverified account (register `qa2@example.com` / `qa_two`), signed in to it in this browser.
- Steps:
  1. Open the verification link from Mailpit in the same browser; press "Confirm my email".
- Expected: "Email confirmed" with "Your email is confirmed." and a "Continue" button to `/`. The browser stays signed in; the "Confirm your email" banner no longer shows on app pages.

### TC-P1-08-006: In-app "Email confirmed" notification
- Priority: Medium · Type: Functional
- Ref: FR-NOTIF-2, Decision 11
- Preconditions: TC-P1-08-005 done.
- Steps:
  1. Open the notification bell, then `/notifications`.
- Expected: one notification titled "Your email is confirmed" with "Thanks for confirming. Your account is all set." No email is sent for it.

### TC-P1-08-007: Sign in to an unverified account and the first sign-in email
- Priority: High · Type: Email
- Ref: Open question 5, Decision 9
- Preconditions: a registered, never signed-in account (register `qa3@example.com` / `qa_three`); signed out; Mailpit cleared.
- Steps:
  1. Sign in at `/login` as qa3@example.com.
  2. Open Mailpit.
- Expected: sign-in succeeds although the email is unverified. Mailpit has one email to qa3@example.com with subject "First sign-in to your Clash Commons account" and the line "Your new account was signed in for the first time: …", not the "New sign-in" email.

### TC-P1-08-008: Confirm-your-email banner and notice page
- Priority: High · Type: UI state
- Ref: FR-AUTH-4, Decision 12
- Preconditions: signed in to an unverified account (`qa_three`).
- Steps:
  1. Open `/settings/profile`.
  2. Follow "Resend the link" in the banner.
- Expected: step 1 shows an info alert titled "Confirm your email" with "Until you do, you cannot upload, post or link a Clash of Clans account." and a "Resend the link" link. Step 2 opens `/email/verify`: heading "Confirm your email", "We sent a link to q***@example.com. Open it to finish setting up your account." (address masked), the line about browsing and settings with "Links expire after 60 minutes.", and a "Send a new link" button. The banner is not shown on `/email/verify` itself.

### TC-P1-08-009: Resend the verification link
- Priority: High · Type: Functional
- Ref: FR-AUTH-3, task Scope "Verification"
- Preconditions: on `/email/verify` as an unverified account; Mailpit cleared.
- Steps:
  1. Press "Send a new link".
- Expected: a green success alert "A new link is on its way. It expires in 60 minutes." above the card, on the page itself (not a toast, unchanged by the 2026-10-02 toast change); Mailpit has a new "Confirm your Clash Commons email" message to that account's address.

### TC-P1-08-010: Home hero register button for guests
- Priority: Low · Type: UI state
- Ref: specs/18 §6, Decision 12
- Preconditions: none.
- Steps:
  1. Signed out, open `/` and press "Create your account".
  2. Sign in and open `/`.
- Expected: step 1 opens `/register`. Signed in, the hero has no "Create your account" button.

## Validation

### TC-P1-08-011: Empty form
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-1, specs/18 §8
- Preconditions: signed out; `/register` open.
- Steps:
  1. Press "Create account" with every field empty.
- Expected: field errors under Email ("The email field is required."), Username ("The username field is required.") and Password ("The password field is required."); focus moves to Email. No user row is created.

### TC-P1-08-012: Invalid email format
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-1
- Preconditions: signed out; `/register` open.
- Steps:
  1. Enter `qa4@` as email with valid other fields; submit.
- Expected: "Enter a valid email address." under Email; focus on Email; both password fields are cleared.

### TC-P1-08-013: Disposable email domain and its subdomains
- Priority: High · Type: Validation
- Ref: FR-AUTH-11, Decision 6
- Preconditions: signed out.
- Steps:
  1. Register with `qa5@mailinator.com`.
  2. Register with `qa5@inbox.MAILINATOR.com`.
- Expected: both show "Use an email address you will keep. Throwaway inboxes are not accepted." under Email. No user row, no email.

### TC-P1-08-014: Username length
- Priority: Medium · Type: Validation
- Ref: FR-AUTH-1, Decision 7
- Preconditions: signed out; `/register` open.
- Steps:
  1. Enter username `ab` with valid other fields; submit.
  2. Try typing a 25-character username.
- Expected: step 1 shows "The username field must be at least 3 characters." Step 2: the field stops at 20 characters. The hint reads "3 to 20 lowercase letters, numbers or underscores. This is your public name."

### TC-P1-08-015: Username characters and lowercasing
- Priority: High · Type: Validation
- Ref: FR-AUTH-1, Decision 7
- Preconditions: signed out.
- Steps:
  1. Register with username `qa-six` (hyphen).
  2. Register with username `qa six`.
  3. Register with username `  QA_Six  ` and email `qa6@example.com`.
- Expected: steps 1 and 2 show "Use lowercase letters, numbers and underscores only." Step 3 succeeds and Adminer shows `username` `qa_six` (trimmed and lowercased).

### TC-P1-08-016: Reserved usernames
- Priority: High · Type: Validation
- Ref: FR-AUTH-1, `platform.auth.reserved_usernames`
- Preconditions: signed out.
- Steps:
  1. Register with username `admin`.
  2. Repeat with `Settings` and `clash_commons`.
- Expected: each shows "That username is reserved. Pick another." under Username.

### TC-P1-08-017: Taken username, any case, deleted accounts included
- Priority: High · Type: Validation
- Ref: FR-AUTH-1, Decision 7
- Preconditions: signed out; in Adminer, one test account soft-deleted (`deleted_at` set) with a known username, e.g. register `qa_gone` then set its `deleted_at`.
- Steps:
  1. Register with username `test_user` and a new email.
  2. Register with username `TEST_USER`.
  3. Register with username `qa_gone`.
- Expected: each shows "That username is taken. Pick another." under Username (a field error on submit; there is no live availability check).

### TC-P1-08-018: Password rules
- Priority: High · Type: Validation
- Ref: FR-AUTH-2
- Preconditions: signed out; app container online.
- Steps:
  1. Register with password `short123` twice.
  2. Register with `Ember-Lantern-4417` and `Ember-Lantern-4418`.
  3. Register with `password1234` twice.
- Expected: step 1 "The password field must be at least 10 characters."; step 2 "The two password entries do not match."; step 3 "This password appears in a known data breach. Choose a different one." Each under Password; the password fields are cleared after every failed submit. The hint reads "At least 10 characters."

### TC-P1-08-019: Form open too long
- Priority: Low · Type: Validation
- Ref: Decision 4 (`register_max_form_age_minutes` 120)
- Preconditions: signed out.
- Steps:
  1. Open `/register` and leave the tab open for more than 120 minutes.
  2. Fill in valid values and submit.
- Expected: "This form was open for a long time. Reload the page and try again." under Email; no account. After a reload the same values register.

## Authorization / account status

### TC-P1-08-020: Registration pages are for guests only
- Priority: High · Type: Authorization
- Ref: task Acceptance "guests only on register"
- Preconditions: signed in as `test_user`.
- Steps:
  1. Open `/register`.
  2. Open `/register/sent`.
- Expected: both redirect to `/`.

### TC-P1-08-021: Notice page and resend need a signed-in unverified account
- Priority: Medium · Type: Authorization
- Ref: task Acceptance "resend only for the signed-in unverified account"
- Preconditions: none.
- Steps:
  1. Signed out, open `/email/verify`.
  2. Sign in as `test_user` (verified) and open `/email/verify`.
- Expected: step 1 redirects to `/login`. Step 2 redirects to `/`; a verified account never sees the notice page or the banner.

### TC-P1-08-022: Unverified account: settings open, uploads blocked
- Priority: High · Type: Authorization
- Ref: FR-AUTH-4, Decision 10, owner decision 2026-10-02 (crop before upload)
- Preconditions: signed in to an unverified account.
- Steps:
  1. On `/settings/profile` change the display name and save.
  2. Press "Upload photo", choose a JPEG, then press "Save photo" in the "Crop your photo" dialog.
- Expected: step 1 saves with the success toast "Profile saved.". Step 2 is refused: the `/uploads` request answers 403 and the avatar field shows an error; no `media` row is created.

## Security

### TC-P1-08-023: Registering with a taken email looks the same
- Priority: High · Type: Security
- Ref: specs/23 §1 "registers with an email already in use", Decision 2
- Preconditions: signed out; Mailpit empty; DevTools Network open.
- Steps:
  1. Register with email `test@example.com`, username `qa_seven`, a valid password; note the redirect, the cookies set and the request time.
  2. Register with email `qa7@example.com`, username `qa_seven`, a valid password; note the same.
- Expected: both redirect to the same "Check your email" page; neither sets a `remember_web_…` or known-devices cookie and neither signs in; the two request times are similar (both at least ~0.7 s). Adminer: no new row for test@example.com, `test_user` is unchanged, and `qa_seven` exists for qa7@example.com.

### TC-P1-08-024: Notice email to the existing address
- Priority: High · Type: Email
- Ref: specs/23 §1, Decision 2
- Preconditions: TC-P1-08-023 step 1 done.
- Steps:
  1. Open Mailpit.
- Expected: one email to test@example.com, subject "Someone tried to sign up with your email", greeting "Hi test_user,", saying no new account was made, with a "Reset your password" button to `/forgot-password` and "If it was not you, there is nothing to do. Your account has not changed." No verification email goes to test@example.com.

### TC-P1-08-025: At most one taken-email notice an hour
- Priority: Medium · Type: Security
- Ref: Decision 2, Review fixes
- Preconditions: TC-P1-08-024 done less than an hour ago; signed out.
- Steps:
  1. Register again with `test@example.com` (new username, valid password).
- Expected: the same "Check your email" page; Mailpit gets no second "Someone tried to sign up" email.

### TC-P1-08-026: Email of a deleted account takes the taken-email path
- Priority: Medium · Type: Security
- Ref: Decision 2 (soft-deleted accounts included)
- Preconditions: the `qa_gone` account from TC-P1-08-017 with `deleted_at` set; signed out.
- Steps:
  1. Register with qa_gone's email, a new username and a valid password.
- Expected: "Check your email" page; no new `users` row; no verification email.

### TC-P1-08-027: Honeypot field filled
- Priority: High · Type: Security
- Ref: specs/11 "Spam and fake accounts", Decision 4
- Preconditions: signed out; Mailpit empty; `/register` open.
- Steps:
  1. Fill valid values (email `qa8@example.com`).
  2. In the DevTools console run `const f=document.getElementById('register-website'); f.value='https://spam.example'; f.dispatchEvent(new Event('input'))`.
  3. Wait 3 seconds and submit.
- Expected: the same "Check your email" page; no `users` row for qa8@example.com; no email. The security log (`docker compose exec app sh -c 'tail storage/logs/security-*.log'`) has `auth.registration_blocked` with reason `honeypot`.

### TC-P1-08-028: Submitted faster than 3 seconds
- Priority: Medium · Type: Security
- Ref: Decision 4 (`register_min_seconds` 3)
- Preconditions: signed out; values ready to paste or autofill.
- Steps:
  1. Load `/register` and, within 3 seconds, fill every field (email `qa9@example.com`) and submit.
- Expected: the same "Check your email" page; no account; no email; security log `auth.registration_blocked` with reason `too_fast`.

### TC-P1-08-029: Replayed form submission
- Priority: Low · Type: Security
- Ref: Decision 4 (single-use form token)
- Preconditions: TC-P1-08-001-style registration just made with `qa10@example.com`; its `POST /register` in the DevTools Network list.
- Steps:
  1. Right-click the request, "Copy as fetch"; in the console change the email to `qa11@example.com` and username to `qa_eleven`, then run it.
- Expected: the response redirects to `/register/sent`, but no account exists for qa11@example.com and no email is sent; security log reason `replayed_form`.

### TC-P1-08-030: Mass assignment and array input are ignored
- Priority: Medium · Type: Security
- Ref: task Tests "mass assignment", Review fixes (array fields)
- Preconditions: signed out; a fresh `/register` load.
- Steps:
  1. Copy a valid register POST as fetch. Fully reload `/register` and read the fresh form token with `JSON.parse(document.getElementById('app').dataset.page).props.formStarted`. In the copied body set `started` to that token, use a new email and username, and add `role: 'admin'`, `status: 'banned'`, `email_verified_at: '2026-01-01'`; wait 3 seconds and run it.
  2. Send another request with `email` as an array (`email: ['a@example.com']`).
- Expected: step 1 creates the user with `role` user, `status` active and `email_verified_at` NULL. Step 2 returns field errors (no 500 page).

### TC-P1-08-031: Accepted sign-ups limit, 3 per hour per IP
- Priority: High · Type: Security
- Ref: specs/04 §4 `register`, Decision 3 (`register_per_hour` 3)
- Preconditions: signed out; no registrations from this IP in the last hour.
- Steps:
  1. Register 3 accounts with valid values (new emails and usernames).
  2. Make a typo submission (mismatched passwords), then fix it and submit a 4th valid registration.
- Expected: the typo gets its field error, not a limit message. The 4th valid submission shows "Too many attempts. Try again in N minutes." under Email and creates no account.

### TC-P1-08-032: Route cap, 20 attempts per hour per IP
- Priority: Low · Type: Security
- Ref: Decision 3 (`register_attempts_per_hour` 20)
- Preconditions: signed out; no register attempts from this IP in the last hour.
- Steps:
  1. Submit the register form 20 times with a validation error (e.g. username `ab`).
  2. Submit a 21st time.
- Expected: step 2 shows "Too many attempts. Try again in N minutes." under Email.

### TC-P1-08-033: Turnstile failure
- Priority: Medium · Type: Security
- Ref: Open question 1, Decision 5
- Preconditions: `.env` sets `TURNSTILE_SECRET_KEY=2x0000000000000000000000000000000AA` (Cloudflare's always-fail test secret); `docker compose restart app`; signed out.
- Steps:
  1. Submit a valid registration.
  2. Submit a valid `/forgot-password` request.
- Expected: both show "We could not confirm you are not a bot. Try again." under the bot check; no account, no email. Restore the `.env` value afterwards.

### TC-P1-08-034: Turnstile script blocked
- Priority: Low · Type: UI state
- Ref: Decision 4, Decision 12
- Preconditions: signed out; DevTools > Network request blocking for `challenges.cloudflare.com`.
- Steps:
  1. Load `/register` and submit valid values.
- Expected: the form shows "The bot check did not load. Turn off content blockers for this site, then reload the page." and the submit is refused with "We could not confirm you are not a bot. Try again."

### TC-P1-08-035: A typo keeps the next submit working
- Priority: Medium · Type: Edge case
- Ref: Decision 4 (Turnstile runs last; widget resets after a failed submit)
- Preconditions: signed out.
- Steps:
  1. Submit with mismatched passwords.
  2. Fix them and submit again.
- Expected: step 2 registers (the "Check your email" page), with no bot-check or replay error.

## Edge cases

### TC-P1-08-036: Link opened by a mail scanner
- Priority: High · Type: Security
- Ref: specs/23 §1 "A verification link is opened by a mail scanner", Decision 8
- Preconditions: an unverified account with a fresh link.
- Steps:
  1. Open the link (GET) in a browser that is signed out, then close the tab without pressing the button. Optionally fetch it with `curl` too.
  2. Check Adminer.
- Expected: `email_verified_at` stays NULL; the account's sessions are untouched.

### TC-P1-08-037: Link used twice and double press
- Priority: High · Type: Edge case
- Ref: Decision 8 ("two presses verify once")
- Preconditions: an unverified account with a fresh link; Adminer `notifications` noted.
- Steps:
  1. Open the link and double-click "Confirm my email".
  2. Open the same link again.
- Expected: one `email_verified_at` value and one "Your email is confirmed" notification. Step 2 shows "Email confirmed" with "Your email is already confirmed."

### TC-P1-08-038: Expired or tampered link
- Priority: High · Type: Security
- Ref: task Acceptance "a used, expired or tampered link shows one message"
- Preconditions: an unverified account with a link.
- Steps:
  1. Change one character of the `signature` query value; open it.
  2. Change the `expires` value; open it.
  3. Open an untouched link more than 60 minutes after it was sent.
- Expected: each shows "Link not working" with "This link has already been used or has expired. Ask for a new one.", a "Get a new link" button to `/email/verify` and "Home". `email_verified_at` stays NULL.

### TC-P1-08-039: Link whose email hash no longer matches
- Priority: Medium · Type: Security
- Ref: task Acceptance "checks the signature and the email hash"
- Preconditions: an unverified account with a fresh link.
- Steps:
  1. In Adminer change that user's `email` to another address.
  2. Open the link.
- Expected: "Link not working" with "This link has already been used or has expired. Ask for a new one."

### TC-P1-08-040: Confirming ends the account's other sessions
- Priority: High · Type: Security
- Ref: Decision 8 (pre-hijack), Review fixes
- Preconditions: browser A registers `qa12@example.com` and signs in to it (unverified). Browser B (private window) signed out.
- Steps:
  1. Open the verification link in browser B and press "Confirm my email".
  2. Reload a page in browser A.
- Expected: browser B shows "Email confirmed" with a "Sign in" button. Browser A is signed out; `sessions` has no row for the account and `users.remember_token` changed.

### TC-P1-08-041: Confirming while signed in to another account
- Priority: Medium · Type: Edge case
- Ref: task Tests "other account signed in"
- Preconditions: an unverified account `qa13` with a fresh link; this browser signed in as `test_user`.
- Steps:
  1. Open qa13's link and confirm.
- Expected: "Email confirmed" with a "Sign in" button (not "Continue"); qa13 is verified; this browser is still signed in as `test_user`.

### TC-P1-08-042: Resend limit, 3 per hour per account
- Priority: High · Type: Security
- Ref: specs/04 §4 `verify-email-resend`, Decision 3 (`verify_resend_per_hour` 3), owner decision 2026-10-02 (flash toasts)
- Preconditions: on `/email/verify` as an unverified account with no resends in the last hour.
- Steps:
  1. Press "Send a new link" 3 times.
  2. Press it a 4th time and wait 10 s.
  3. Press "Dismiss notification" on the toast, then press "Send a new link" a 5th time.
- Expected: requests 1–3 show the green success alert on the page and send an email each. Step 2 shows a danger toast "You asked for a new link a few times already. Try again in an hour." (bottom right, above the bottom tabs below 768 px) that stays after 10 s; nothing shows inline on the page for it and nothing is sent. Step 3 closes the toast, then the same danger toast shows again; still no email.

### TC-P1-08-043: An older link still works after a resend
- Priority: Low · Type: Edge case
- Ref: Decision 8 (signed for 60 minutes, no stored token)
- Preconditions: an unverified account; two verification emails (original and one resend), both under 60 minutes old.
- Steps:
  1. Confirm with the older link.
  2. Open the newer link.
- Expected: step 1 verifies; step 2 shows "Your email is already confirmed."

### TC-P1-08-044: Result page without a result
- Priority: Low · Type: Edge case
- Ref: Decision 13 (`/email/verified` is flashed)
- Preconditions: none.
- Steps:
  1. Open `/email/verified` directly, or reload it after a confirmation.
- Expected: redirected to `/`.

## UI states

### TC-P1-08-045: Register pages at 375 px
- Priority: Medium · Type: UI state
- Ref: task Acceptance "375 px and desktop"
- Preconditions: DevTools device toolbar at 375 px.
- Steps:
  1. Open `/register`, trigger field errors, then open `/register/sent`, a verification link and `/email/verify` (signed in, unverified).
  2. On `/register` follow "Sign in"; on `/login` follow "Create an account".
- Expected: no horizontal scroll; the masked address and the username wrap inside their cards; the button shows its loading state while submitting; the hidden `website` field is never visible or reachable with Tab. The links go to `/login` and `/register`.

### TC-P1-08-046: Show and hide both register passwords
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed out; Mailpit empty; `/register` open.
- Steps:
  1. Fill email `qa14@example.com`, username `qa_fourteen`, and `Ember-Lantern-4417` in "Password" and "Repeat the password".
  2. Press the eye button in "Password".
  3. Press the eye button in "Repeat the password", then "Hide password" in "Password".
  4. Leave "Repeat the password" showing as text, wait 3 seconds and press "Create account".
- Expected: Email and Username have no toggle. Step 2 shows `Ember-Lantern-4417` in "Password" only; its button is now "Hide password" (`aria-pressed="true"`) while "Repeat the password" stays masked with "Show password". Step 3 leaves only "Repeat the password" revealed. The values never change. Step 4 registers (the "Check your email" page).

### TC-P1-08-047: Register password toggles with the keyboard
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed out; `/register` open.
- Steps:
  1. From Email (focused on load), press Tab until focus is on "Password"; type `Ember-Lantern-4417`.
  2. Press Tab; press Space.
  3. Press Tab, type `Ember-Lantern-4417`, press Tab, then Enter.
  4. Press Enter on the same toggle again.
- Expected: the Tab order is Email, Username, Password, its "Show password" button, Repeat the password, its "Show password" button, then the bot check and "Create account"; the hidden `website` field is skipped. Each toggle shows a focus ring. Step 2 reveals "Password". Step 3 reveals "Repeat the password". Step 4 masks it again. Space and Enter on a toggle never submit the form: no request, no field errors, both values kept.

### TC-P1-08-048: Revealed passwords are cleared after a failed submit
- Priority: Low · Type: Edge case
- Ref: FR-AUTH-2, owner decision 2026-10-02 (password toggle)
- Preconditions: signed out; `/register` open.
- Steps:
  1. Enter valid values with `Ember-Lantern-4417` and `Ember-Lantern-4418`; reveal both password fields.
  2. Wait 3 seconds and submit.
- Expected: "The two password entries do not match." under Password, and both password fields are emptied, as in TC-P1-08-018; typing again in a revealed field shows the new text, and "Hide password" still masks it.
